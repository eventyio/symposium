<?php

namespace App\CallingAllPapers;

use App\Exceptions\InvalidAddressGeocodingException;
use App\Models\Conference;
use App\Services\Geocoder\Geocoder;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use DateTime;
use Illuminate\Support\Facades\Validator;

class ConferenceImporter
{
    private $authorId;

    private $geocoder;

    public function __construct(?int $authorId = null)
    {
        $this->authorId = $authorId ?: auth()->user()->id;
        $this->geocoder = app(Geocoder::class);
    }

    private static function carbonFromIso(?string $dateFromApi)
    {
        if (! $dateFromApi || $dateFromApi == '1970-01-01T00:00:00+00:00') {
            return null;
        }

        try {
            return Carbon::createFromFormat(DateTime::ISO8601, $dateFromApi);
        } catch (InvalidFormatException $e) {
            return null;
        }
    }

    public function import(Event $event)
    {
        // Ensure entities (i.e. tests) sharing this object are not affected by directly changing it
        $event = clone $event;

        $event->dateCfpStart = self::carbonFromIso($event->dateCfpStart);
        $event->dateCfpEnd = self::carbonFromIso($event->dateCfpEnd);
        $event->dateEventStart = self::carbonFromIso($event->dateEventStart);
        $event->dateEventEnd = self::carbonFromIso($event->dateEventEnd);

        $validator = Validator::make([
            'cfp_starts_at' => $event->dateCfpStart,
            'cfp_ends_at' => $event->dateCfpEnd,
            'starts_at' => $event->dateEventStart,
            'ends_at' => $event->dateEventEnd,
        ], [
            'cfp_starts_at' => ['nullable', 'date'],
            'cfp_ends_at' => [
                'nullable',
                'date',
                'after:cfp_starts_at',
                $event->dateEventStart ? 'before:starts_at' : '',
                'before:'.Carbon::parse($event->dateCfpStart)->addYears(2),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                'before:'.Carbon::parse($event->dateEventStart)->addYears(2),
            ],
        ]);

        $conference = Conference::query()
            ->withoutGlobalScope('notRejected')
            ->firstOrNew(['calling_all_papers_id' => $event->id]);
        $this->updateConferenceFromCallingAllPapersEvent($conference, $event);

        if (! $conference->latitude && ! $conference->longitude && $conference->location) {
            $this->geocodeLocation($conference);
        }

        if ($validator->fails()) {
            $conference->rejected_at = now();
        }

        if ($conference->isDirty()) {
            $conference->save();
        }

        return $conference;
    }

    private function updateConferenceFromCallingAllPapersEvent(Conference $conference, Event $event)
    {
        if ($event->location != $conference->location) {
            $conference->latitude = $this->nullifyInvalidLatLong($event->latitude, $event->longitude);
            $conference->longitude = $this->nullifyInvalidLatLong($event->longitude, $event->latitude);
        }

        $conference->title = trim($event->name);
        $conference->description = trim($event->description);
        $conference->url = trim($event->eventUri);
        $conference->cfp_url = $event->uri;
        $conference->location = $event->location;
        $conference->starts_at = $event->dateEventStart;
        $conference->ends_at = $event->dateEventEnd;
        $conference->cfp_starts_at = $event->dateCfpStart;
        $conference->cfp_ends_at = $event->dateCfpEnd;
        $conference->author_id = $this->authorId;
    }

    private function nullifyInvalidLatLong($primary, $secondary)
    {
        return (float) $primary && (float) $secondary ? $primary : null;
    }

    private function geocodeLocation(Conference $conference): void
    {
        try {
            $result = $this->geocoder->geocode($conference->location);
        } catch (InvalidAddressGeocodingException $e) {
            return;
        }

        $conference->coordinates = $result->getCoordinates();
        $conference->location_name = $result->getLocationName();
    }
}

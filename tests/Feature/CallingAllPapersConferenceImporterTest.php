<?php

namespace Tests\Feature;

use App\CallingAllPapers\Client;
use App\CallingAllPapers\ConferenceImporter;
use App\CallingAllPapers\Event;
use App\Casts\Coordinates;
use App\Exceptions\InvalidAddressGeocodingException;
use App\Models\Conference;
use App\Services\Geocoder\Geocoder;
use App\Services\Geocoder\GeocoderResponse;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\MocksCallingAllPapers;
use Tests\TestCase;
use UnexpectedValueException;

class CallingAllPapersConferenceImporterTest extends TestCase
{
    use MocksCallingAllPapers;

    #[Before]
    public function prepareEventStub()
    {
        parent::setUp();

        $this->stubEvent();
    }

    #[Test]
    public function it_gets_the_id_from_the_rel_link(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $this->assertEquals(1, Conference::count());
        $conference = Conference::first();

        $this->assertEquals($this->eventId, $conference->calling_all_papers_id);
    }

    #[Test]
    public function epoch_start_dates_are_nullified_prior_to_validation(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->dateEventStart = '1970-01-01T00:00:00+00:00'; // Jan 1, 1970 is the unix epoch start
        $importer->import($event);

        $this->assertEquals(1, Conference::count());
        $conference = Conference::first();

        $this->assertNull($conference->date_start);
        $this->assertEquals($this->eventId, $conference->calling_all_papers_id);
    }

    #[Test]
    public function it_imports_basic_text_fields(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $this->assertEquals(1, Conference::count());
        $conference = Conference::first();

        $this->assertEquals($this->eventStub->name, $conference->title);
        $this->assertEquals($this->eventStub->description, $conference->description);
        $this->assertEquals($this->eventStub->uri, $conference->cfp_url);
    }

    #[Test]
    public function events_without_a_start_date_can_be_imported_with_cfp_end_date(): void
    {
        $this->stubEvent([
            'dateEventStart' => '',
            'dateCfpEnd' => '2017-12-22T00:00:00-04:00',
        ]);
        $this->mockClient($this->eventStub);

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $conference = Conference::first();
        $this->assertNotNull($conference);
    }

    #[Test]
    public function events_without_a_start_date_or_cfp_end_date_cannot_be_imported(): void
    {
        try {
            $this->stubEvent([
                'dateEventStart' => '',
                'dateCfpEnd' => '',
            ]);

            $this->mockClient($this->eventStub);
            $importer = new ConferenceImporter(1);
            $importer->import($this->eventStub);
        } catch (UnexpectedValueException $e) {
            $conference = Conference::first();
            $this->assertNull($conference);

            return;
        }

        $this->fail('An '.UnexpectedValueException::class.' was expected but not thrown');
    }

    #[Test]
    public function it_imports_dates_if_we_dont_care_about_time_zones(): void
    {
        $event = $this->eventStub;

        $event->dateCfpStart = '2017-01-20T00:00:00+00:00';
        $event->dateCfpEnd = '2017-02-22T00:00:00+00:00';
        $event->dateEventStart = '2017-03-20T00:00:00+00:00';
        $event->dateEventEnd = '2017-04-22T00:00:00+00:00';

        $this->mockClient($event);

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $conference = Conference::first();

        /*
         * Problem here: Our importer discards the time zone when it saves
         * it to the database. So we have to decide: Do we add time zone?
         * Do we convert to UTC? But nothing else in the app is in UTC; we just
         * let it all live in non-existent time zone, the user's sort of
         * intended time zone, I guess.
         *
         * That makes this test fail if it's written correctly--because it saves
         * the *time* but returns it in the wrong time zone.
         *
         * Right now, let's just say that, assuming the way it handles dates
         * right now, we at least want to make sure the strong is stored
         * correctly. Because of time zones and daylight savings time, the
         * easiest option is this piece of hideousness.
         */
        $this->assertEquals(
            substr($event->dateCfpStart, 0, 19),
            substr($conference->cfp_starts_at->toIso8601String(), 0, 19)
        );
        $this->assertEquals(
            substr($event->dateCfpEnd, 0, 19),
            substr($conference->cfp_ends_at->toIso8601String(), 0, 19)
        );
        $this->assertEquals(
            substr($event->dateEventStart, 0, 19),
            substr($conference->starts_at->toIso8601String(), 0, 19)
        );
        $this->assertEquals(
            substr($event->dateEventEnd, 0, 19),
            substr($conference->ends_at->toIso8601String(), 0, 19)
        );
    }

    #[Test]
    public function imported_dates_are_adjusted_for_daylight_saving_time_changes(): void
    {
        $this->mockClient();

        $event = $this->eventStub;
        $event->dateCfpStart = '2017-08-20T00:00:00-04:00';
        $event->dateCfpEnd = '2017-09-22T00:00:00-04:00';
        $event->dateEventStart = '2017-10-20T00:00:00-04:00';
        $event->dateEventEnd = '2017-12-22T00:00:00-04:00';

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertEquals('2017-08-20T00:00:00-04:00', $conference->cfp_starts_at->toIso8601String());
        $this->assertEquals('2017-09-22T00:00:00-04:00', $conference->cfp_ends_at->toIso8601String());
        $this->assertEquals('2017-10-20T00:00:00-04:00', $conference->starts_at->toIso8601String());

        // The conference ends after the time change so it is -5:00 from UTC instead of -4:00
        $this->assertEquals('2017-12-22T00:00:00-05:00', $conference->ends_at->toIso8601String());
    }

    #[Test]
    public function it_imports_null_dates_as_null(): void
    {
        $event = $this->eventStub;

        $event->dateCfpEnd = null;

        $this->mockClient($event);

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertNull($conference->cfp_ends_at);
    }

    #[Test]
    public function it_imports_jan_1_1970_dates_as_null(): void
    {
        $event = $this->eventStub;

        $event->dateCfpStart = '1970-01-01T00:00:00+00:00';
        $event->dateCfpEnd = '1970-02-01T00:00:00+00:00';
        $event->dateEventStart = '1970-03-01T00:00:00+00:00';
        $event->dateEventEnd = '1970-04-01T00:00:00+00:00';

        $this->mockClient($event);

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertNull($conference->cfp_starts_at);
    }

    #[Test]
    public function invalid_dates_are_ignored(): void
    {
        $event = $this->eventStub;

        $event->dateCfpStart = 'invalid';
        $event->dateCfpEnd = 'invalid';
        $event->dateEventStart = 'invalid';
        $event->dateEventEnd = 'invalid';

        $this->mockClient($event);

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertNull($conference->starts_at);
        $this->assertNull($conference->ends_at);
        $this->assertNull($conference->cfp_starts_at);
        $this->assertNull($conference->cfp_ends_at);
    }

    #[Test]
    public function it_imports_zero_in_latitude_or_longitude_as_null(): void
    {
        $event = $this->eventStub;

        $event->latitude = '0';
        $event->longitude = '-82.682221';

        $this->mockClient($event);

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertNull($conference->latitude);
        $this->assertNull($conference->longitude);
    }

    #[Test]
    public function it_fills_latitude_and_longitude_from_location_if_lat_long_are_null(): void
    {
        $event = $this->eventStub;

        $event->latitude = '0';
        $event->longitude = '-82.682221';
        $event->location = '10th St. & Constitution Ave. NW, Washington, DC';

        $this->mockClient($event);
        $response = $this->mock(GeocoderResponse::class, function ($mock) {
            $mock->shouldReceive('getCoordinates')
                ->andReturn(new Coordinates('38.8921062', '-77.0259036'))
                ->shouldReceive('getLocationName');
        });
        $this->mock(Geocoder::class, function ($mock) use ($response) {
            $mock->shouldReceive('geocode')
                ->andReturn($response);
        });

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertEquals('38.8921062', $conference->latitude);
        $this->assertEquals('-77.0259036', $conference->longitude);
    }

    #[Test]
    public function it_fills_location_name(): void
    {
        $event = $this->eventStub;

        $event->latitude = '0';
        $event->longitude = '-82.682221';
        $event->location = '10th St. & Constitution Ave. NW, Washington, DC';

        $this->mockClient($event);
        $response = $this->mock(GeocoderResponse::class, function ($mock) {
            $mock->shouldReceive('getCoordinates')
                ->andReturn(new Coordinates('38.8921062', '-77.0259036'))
                ->shouldReceive('getLocationName')
                ->andReturn('Göteborg, Sweden');
        });
        $this->mock(Geocoder::class, function ($mock) use ($response) {
            $mock->shouldReceive('geocode')
                ->andReturn($response);
        });

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertEquals('Göteborg, Sweden', $conference->location_name);
    }

    #[Test]
    public function it_keeps_lat_long_values_null_if_no_results(): void
    {
        $event = $this->eventStub;

        $event->latitude = '0';
        $event->longitude = '-82.682221';
        $event->location = 'Not a Valid Location';

        $this->mockClient($event);
        $this->mock(Geocoder::class, function ($mock) {
            $mock->shouldReceive('geocode')
                ->andThrow(new InvalidAddressGeocodingException);
        });

        $importer = new ConferenceImporter(1);
        $importer->import($event);

        $conference = Conference::first();

        $this->assertNull($conference->latitude);
        $this->assertNull($conference->longitude);
    }

    #[Test]
    public function imported_conferences_are_approved(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $this->assertTrue(Conference::first()->is_approved);
    }

    #[Test]
    public function it_updates_data_for_existing_conferences(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $conference = Conference::first();

        $this->assertEquals($this->eventStub->name, $conference->title);
        $this->assertEquals($this->eventStub->description, $conference->description);
        $this->assertEquals($this->eventStub->eventUri, $conference->url);

        $updatedEvent = clone $this->eventStub;
        $updatedEvent->name = 'Updated name';
        $updatedEvent->description = 'Updated description';
        $updatedEvent->eventUri = 'https://www.example.org/';
        $importer->import($updatedEvent);

        $updatedConference = Conference::first();

        $this->assertEquals($updatedEvent->name, $updatedConference->title);
        $this->assertEquals($updatedEvent->description, $updatedConference->description);
        $this->assertEquals($updatedEvent->eventUri, $updatedConference->url);
    }

    #[Test]
    public function updating_existing_unapproved_conferences_leaves_them_unapproved(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $importer->import($this->eventStub);

        $conference = Conference::first();
        $conference->update(['is_approved' => false]);

        $updatedEvent = clone $this->eventStub;
        $importer->import($updatedEvent);

        $this->assertFalse(Conference::first()->is_approved);
    }

    #[Test]
    public function conferences_with_cfp_end_after_conference_start_are_rejected(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->id = 'fake-cfp-id';
        $event->dateEventStart = '2017-09-01T00:00:00-04:00';
        $event->dateCfpEnd = '2017-09-02T00:00:00-04:00';
        $importer->import($event);

        $conference = Conference::withoutGlobalScope('notRejected')
            ->where('calling_all_papers_id', 'fake-cfp-id')
            ->first();
        $this->assertNotNull($conference);
        $this->assertNotNull($conference->rejected_at);
    }

    #[Test]
    public function conferences_with_over_2_year_duration_are_rejected(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->id = 'fake-cfp-id';
        $event->dateEventStart = '2017-10-01T00:00:00-04:00';
        $event->dateEventEnd = '2020-10-01T00:00:00-04:00';
        $importer->import($event);

        $conference = Conference::withoutGlobalScope('notRejected')
            ->where('calling_all_papers_id', 'fake-cfp-id')
            ->first();
        $this->assertNotNull($conference);
        $this->assertNotNull($conference->rejected_at);
    }

    #[Test]
    public function conferences_with_cfp_duration_over_2_years_are_rejected(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->id = 'fake-cfp-id';
        $event->dateCfpStart = '2014-06-01T00:00:00-04:00';
        $event->dateCfpEnd = '2017-06-01T00:00:00-04:00';
        $importer->import($event);

        $conference = Conference::withoutGlobalScope('notRejected')
            ->where('calling_all_papers_id', 'fake-cfp-id')
            ->first();
        $this->assertNotNull($conference);
        $this->assertNotNull($conference->rejected_at);
    }

    #[Test]
    public function rejected_conferences_cannot_be_reimported(): void
    {
        $this->mockClient();

        $conference = Conference::factory()->rejected()->create([
            'calling_all_papers_id' => 'fake-cfp-id',
        ]);

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->id = 'fake-cfp-id';
        $event->dateCfpStart = '2014-06-01T00:00:00-04:00';
        $event->dateCfpEnd = '2017-06-01T00:00:00-04:00';
        $importer->import($event);

        $conferenceCount = Conference::withoutGlobalScope('notRejected')
            ->where('calling_all_papers_id', 'fake-cfp-id')
            ->count();

        $this->assertEquals(1, $conferenceCount);
    }

    #[Test]
    public function conferences_with_null_cfp_start_are_valid_with_cfp_end_less_than_2_years_in_future(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->dateCfpStart = null;
        $event->dateCfpEnd = now()->addMonths(23)->toIso8601String();
        $event->dateEventStart = now()->addMonths(26)->toIso8601String();
        $event->dateEventEnd = now()->addMonths(27)->toIso8601String();
        $importer->import($event);

        $this->assertEquals(1, Conference::count());
    }

    #[Test]
    public function conferences_with_null_start_are_valid_with_end_less_than_2_years_in_future(): void
    {
        $this->mockClient();

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->dateCfpStart = now()->toIso8601String();
        $event->dateCfpEnd = now()->addMonths(2)->toIso8601String();
        $event->dateEventStart = null;
        $event->dateEventEnd = now()->addMonths(23)->toIso8601String();
        $importer->import($event);

        $this->assertEquals(1, Conference::count());
    }

    #[Test]
    public function the_geocoder_is_not_called_for_conferences_already_having_coordinates(): void
    {
        $this->mockClient();
        $spy = $this->spy(Geocoder::class);

        $importer = new ConferenceImporter(1);
        $event = $this->eventStub;
        $event->location = 'Somewhere';
        $event->latitude = 123;
        $event->longitude = 321;

        $importer->import($event);

        $spy->shouldNotHaveReceived('geocode');
        $this->assertEquals(1, Conference::count());
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function an_exception_is_thrown_for_an_invalid_cfp_uri(): void
    {
        $this->mockClient();

        $_rel = new stdClass;
        $_rel->cfp_uri = 'v0/cfp/invalid-uri';

        $event = new stdClass;
        $event->_rel = $_rel;

        try {
            $this->eventStub = Event::createFromApiObject($event);
        } catch (UnexpectedValueException $e) {
            return;
        }

        $this->fail('An '.UnexpectedValueException::class.' was expected but not thrown');
    }

    #[Test]
    public function returning_events_from_the_client(): void
    {
        Http::fake([
            '*' => Http::response(['cfps' => [
                array_merge($this->rawEvent(), [
                    'name' => 'Metal conference',
                    '_rel' => ['cfp_uri' => 'v1/cfp/abcdef1234567890abcdef1234567890abcdef122017'],
                ]),
            ]]),
        ]);

        $events = app(Client::class)->getEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('Metal conference', $events->first()->name);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.callingallpapers.com/v1/cfp';
        });
    }
}

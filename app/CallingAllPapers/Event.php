<?php

namespace App\CallingAllPapers;

use stdClass;
use UnexpectedValueException;

class Event
{
    public $id;

    public $dateCfpEnd;

    public $dateCfpStart;

    public $dateEventEnd;

    public $dateEventStart;

    public $description;

    /** The URI to the event's homepage */
    public $eventUri;

    public $iconUri;

    public $lastChange;

    public $latitude;

    public $location;

    public $longitude;

    public $name;

    public $sources;

    public $tags;

    public $timezone;

    /** The URI to the event's CFP */
    public $uri;

    private function __construct() {}

    public static function createFromApiObject(stdClass $object)
    {
        $event = new self;
        $event->id = self::generateIdWithYear($object);

        foreach (get_object_vars($event) as $property => $unused) {
            if ($property == 'id') {
                continue;
            }

            $event->$property = $object->$property ?? null;
        }

        return $event;
    }

    private static function generateIdWithYear($eventObject)
    {
        if (! preg_match('|v1/cfp/([a-z0-9]{40})|', $eventObject->_rel->cfp_uri ?? null, $matches)) {
            throw new UnexpectedValueException('Hash not found on CallingAllPapers event');
        }

        // The end of the CFP URI contains a SHA-1 of the URI of conference's homepage
        // Some conferences use the same URI from year to year, so we append the date
        // to this URI in order to ensure each year's conference is represented separately
        return $matches[1].self::conferenceYear($eventObject);
    }

    private static function conferenceYear($eventObject)
    {
        if (self::isValidDateString($eventObject->dateEventStart)) {
            return substr($eventObject->dateEventStart, 0, 4);
        }

        if (self::isValidDateString($eventObject->dateCfpEnd)) {
            return substr($eventObject->dateCfpEnd, 0, 4);
        }

        throw new UnexpectedValueException("No valid year found on {$eventObject->_rel->cfp_uri}");
    }

    private static function isValidDateString(string $date)
    {
        return $date && $date !== '1970-01-01T00:00:00+00:00';
    }
}

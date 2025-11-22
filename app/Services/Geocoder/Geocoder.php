<?php

namespace App\Services\Geocoder;

use App\Exceptions\InvalidAddressGeocodingException;
use Illuminate\Support\Facades\Http;

class Geocoder
{
    public function geocode(string $address): GeocoderResponse
    {
        if ($this->isInvalidAddress($address)) {
            throw new InvalidAddressGeocodingException;
        }

        return $this->requestGeocoding($address);
    }

    private function requestGeocoding($address)
    {
        $response = Http::acceptJson()
            ->withHeaders([
                'User-Agent' => 'Symposium CLI',
            ])
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => config('services.google.maps.key'),
            ])
            ->json();

        if (! count($response['results'])) {
            cache()->set('invalid-address::'.md5($address), true);
            throw new InvalidAddressGeocodingException;
        }

        return new GeocoderResponse($response);
    }

    private function isInvalidAddress($address)
    {
        return cache('invalid-address::'.md5($address));
    }
}

<?php

namespace Tests\Unit;

use App\Exceptions\InvalidAddressGeocodingException;
use App\Services\Geocoder\Geocoder;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeocoderTest extends TestCase
{
    #[Test]
    public function geocoding_a_us_address(): void
    {
        $this->fakeAddressResponse('United States', 'Fakeville', 'NY');

        $response = app(Geocoder::class)->geocode('123 Fake St Fakeville, NY');

        $this->assertEquals(
            'Fakeville, NY, United States',
            $response->getLocationName(),
        );
        $this->assertNotNull($response->getCoordinates()->getLatitude());
        $this->assertNotNull($response->getCoordinates()->getLongitude());
    }

    #[Test]
    public function geocoding_a_non_us_address(): void
    {
        $this->fakeAddressResponse('Japan', 'Tokyo');

        $response = app(Geocoder::class)->geocode('123 Fake St Tokyo, Japan');

        $this->assertEquals(
            'Tokyo, Japan',
            $response->getLocationName(),
        );
        $this->assertNotNull($response->getCoordinates()->getLatitude());
        $this->assertNotNull($response->getCoordinates()->getLongitude());
    }

    #[Test]
    public function invalid_addresses_are_cached(): void
    {
        cache()->delete('invalid-address::'.md5('fake-address'));
        $this->assertFalse(cache()->has('invalid-address::'.md5('fake-address')));

        $this->fakeResponse(null);

        try {
            app(Geocoder::class)->geocode('fake-address');
        } catch (InvalidAddressGeocodingException $e) {
            $this->assertTrue(cache()->has('invalid-address::'.md5('fake-address')));

            return;
        }

        $this->fail('An '.InvalidAddressGeocodingException::class.' exception was expected but not thrown');
    }

    #[Test]
    public function cached_invalid_addresses_are_not_geocoded(): void
    {
        Http::fake();
        cache()->set('invalid-address::'.md5('fake-address'), true);

        try {
            app(Geocoder::class)->geocode('fake-address');
        } catch (InvalidAddressGeocodingException $e) {
            Http::assertNothingSent();
            $this->assertTrue(cache()->has('invalid-address::'.md5('fake-address')));

            return;
        }

        $this->fail('An '.InvalidAddressGeocodingException::class.' exception was expected but not thrown');
    }

    private function fakeAddressResponse($country, $city, $state = null)
    {
        $addressComponents = [
            ['types' => ['locality'], 'long_name' => $city],
            ['types' => ['country'], 'long_name' => $country],
        ];

        if ($state) {
            $addressComponents[] = [
                'types' => ['administrative_area_level_1'],
                'short_name' => $state,
            ];
        }

        $this->fakeResponse([
            'geometry' => [
                'location' => ['lat' => 12345, 'lng' => 54321],
            ],
            'address_components' => $addressComponents,
        ]);
    }

    private function fakeResponse(?array $addresses)
    {
        $results = $addresses ? [$addresses] : [];

        Http::fake([
            '*' => Http::response(['results' => $results]),
        ]);
    }
}

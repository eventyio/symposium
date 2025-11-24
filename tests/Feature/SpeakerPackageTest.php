<?php

namespace Tests\Feature;

use App\Casts\SpeakerPackage;
use App\Models\Conference;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpeakerPackageTest extends TestCase
{
    #[Test]
    public function speaker_package_can_be_saved_when_conference_is_edited(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()
            ->author($user)
            ->approved()
            ->create([
                'title' => 'My Conference',
                'description' => 'A conference that I made.',
            ]);
        $speakerPackage = [
            'currency' => 'usd',
            'travel' => 10.00,
            'food' => 10.00,
            'hotel' => 10.00,
        ];

        $this->actingAs($user)
            ->put("/conferences/{$conference->id}", array_merge($conference->toArray(), [
                'title' => 'My updated conference',
                'description' => 'Conference has been changed a bit.',
                'speaker_package' => $speakerPackage,
            ]));

        $this->assertDatabaseHasSpeakerPackage($speakerPackage, [
            'title' => 'My updated conference',
            'description' => 'Conference has been changed a bit.',
        ]);
    }

    #[Test]
    public function speaker_package_can_be_updated(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()
            ->author($user)
            ->withSpeakerPackage()
            ->create();

        // Factory sets values to 10 by default
        $updatedPackage = [
            'currency' => 'usd',
            'travel' => 5,
            'food' => 10.00,
            'hotel' => 20,
        ];

        $this->actingAs($user)
            ->put("/conferences/{$conference->id}", array_merge($conference->toArray(), [
                'speaker_package' => $updatedPackage,
            ]));

        $this->assertDatabaseHasSpeakerPackage($updatedPackage);
    }

    #[Test]
    public function speaker_package_can_be_removed(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()
            ->author($user)
            ->withSpeakerPackage()
            ->create();

        $this->actingAs($user)
            ->put("/conferences/{$conference->id}", array_merge($conference->toArray(), [
                'speaker_package' => [],
            ]));

        tap($conference->fresh(), function ($conference) {
            $this->assertNull($conference->speaker_package->currency);
            $this->assertEquals(0, $conference->speaker_package->count());
        });
    }

    private function assertDatabaseHasSpeakerPackage($package, $data = [])
    {
        $this->assertDatabaseHas(Conference::class, array_merge($data, [
            'speaker_package' => json_encode(
                (new SpeakerPackage($package))->toDatabase()
            ),
        ]));
    }
}

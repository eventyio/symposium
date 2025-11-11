<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function including_favorited_conferences(): void
    {
        $user = User::factory()->create();
        Conference::factory()->favoritedBy($user)->create(['title' => 'Awesome Conference']);
        Conference::factory()->create(['title' => 'Boring Conference']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('Awesome Conference');
        $response->assertDontSee('Boring Conference');
    }

    #[Test]
    public function including_submissions(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->create();
        Conference::factory()->received($talk->revisions->first())
            ->create(['title' => 'Awesome Conference']);
        Conference::factory()->create(['title' => 'Boring Conference']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('Awesome Conference');
        $response->assertDontSee('Boring Conference');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSocial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function redirecting_to_socialite_service(): void
    {
        $response = $this->get('login/github');

        $response->assertRedirectContains('github.com');
    }

    #[Test]
    public function redirecting_to_no_signups_view_when_logging_in_a_new_user(): void
    {
        $socialiteUser = $this->mock(SocialiteUser::class, function ($user) {
            $user->shouldReceive('getId')->andReturn(1234)
                ->shouldReceive('getEmail')->andReturn('luke@rebels.net')
                ->shouldReceive('getName')->andReturn('Luke Skywalker');
        });
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('login/github/callback');

        $response->assertOk();
        $response->assertViewIs('auth.no-signups');
        $user = User::where([
            'email' => 'luke@rebels.net',
            'name' => 'Luke Skywalker',
        ])->first();
        $this->assertNull($user);
    }

    #[Test]
    public function logging_in_an_existing_user_with_a_new_social_service(): void
    {
        $socialiteUser = $this->mock(SocialiteUser::class, function ($user) {
            $user->shouldReceive('getId')->andReturn(1234)
                ->shouldReceive('getEmail')->andReturn('luke@rebels.net')
                ->shouldReceive('getName')->andReturn('Luke Skywalker');
        });
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $user = User::factory()->create([
            'email' => 'luke@rebels.net',
            'name' => 'Luke Skywalker',
        ]);
        $this->assertCount(0, $user->social);

        $response = $this->get('login/github/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertCount(1, $user->refresh()->social);
        tap($user->social->first(), function ($social) {
            $this->assertEquals('github', $social->service);
            $this->assertEquals(1234, $social->social_id);
        });
    }

    #[Test]
    public function logging_in_an_existing_user_with_an_existing_social_service(): void
    {
        $socialiteUser = $this->mock(SocialiteUser::class, function ($user) {
            $user->shouldReceive('getId')->andReturn(1234)
                ->shouldReceive('getEmail')->andReturn('luke@rebels.net')
                ->shouldReceive('getName')->andReturn('Luke Skywalker');
        });
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $user = User::factory()
            ->has(UserSocial::factory()->state([
                'social_id' => 1234,
                'service' => 'github',
            ]), 'social')
            ->create([
                'email' => 'luke@rebels.net',
                'name' => 'Luke Skywalker',
            ]);

        $response = $this->get('login/github/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertCount(1, $user->refresh()->social);
        tap($user->social->first(), function ($social) {
            $this->assertEquals('github', $social->service);
            $this->assertEquals(1234, $social->social_id);
        });
    }

    #[Test]
    public function authenticated_users_are_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('login/github');

        $response->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function specifying_an_undefined_social_service(): void
    {
        $response = $this->get('login/skynet');

        $response->assertRedirect();
    }
}

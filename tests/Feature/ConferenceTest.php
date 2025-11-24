<?php

namespace Tests\Feature;

use App\Livewire\ConferenceList;
use App\Models\Conference;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConferenceTest extends TestCase
{
    #[Test]
    public function viewing_the_edit_conference_form(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->author($user)->create();

        $response = $this->actingAs($user)->get(route('conferences.edit', $conference));

        $response->assertSuccessful();
    }

    #[Test]
    public function users_who_didnt_author_the_conference_cannot_view_the_edit_form(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conference = Conference::factory()->author($userA)->create();

        $response = $this->actingAs($userB)->get(route('conferences.edit', $conference));

        $response->assertRedirect('/');
    }

    #[Test]
    public function users_who_didnt_author_the_conference_cannot_update_it(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conference = Conference::factory()->author($userA)->create([
            'title' => 'My Conference',
        ]);

        $response = $this->actingAs($userB)
            ->put(
                route('conferences.update', $conference),
                array_merge($conference->toArray(), ['title' => 'No, My Conference']),
            );

        $response->assertRedirect('/');
        $this->assertEquals('My Conference', $conference->fresh()->title);
    }

    #[Test]
    public function user_can_edit_conference(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()->author($user)->approved()->create([
            'title' => 'Rubycon',
            'description' => 'A conference about Ruby',
        ]);

        $this->actingAs($user)
            ->put("conferences/{$conference->id}", [
                'title' => 'Laracon',
                'description' => 'A conference about Laravel',
                'url' => $conference->url,
            ]);

        $this->assertDatabaseHas(Conference::class, [
            'title' => 'Laracon',
            'description' => 'A conference about Laravel',
        ]);

        $this->assertDatabaseMissing(Conference::class, [
            'title' => 'Rubycon',
            'description' => 'A conference about Ruby',
        ]);
    }

    #[Test]
    public function location_coordinates_can_be_updated(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->author($user)->create();

        $this->actingAs($user)
            ->put("/conferences/{$conference->id}", array_merge($conference->toArray(), [
                'title' => 'Updated JediCon',
                'latitude' => '37.7991531',
                'longitude' => '-122.45050129999998',
            ]));

        $this->assertDatabaseHas(Conference::class, [
            'title' => 'Updated JediCon',
            'latitude' => '37.7991531',
            'longitude' => '-122.45050129999998',
        ]);
    }

    #[Test]
    public function a_conference_cannot_be_updated_to_end_before_it_begins(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()->author($user)->approved()->create([
            'title' => 'Rubycon',
            'description' => 'A conference about Ruby',
            'starts_at' => Carbon::parse('+3 days')->toDateString(),
            'ends_at' => Carbon::parse('+4 days')->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->put("conferences/{$conference->id}", array_merge($conference->toArray(), [
                'ends_at' => Carbon::parse('+2 days')->toDateString(),
            ]));

        $response->assertSessionHasErrors('ends_at');
        $this->assertEquals(
            Carbon::parse('+4 days')->toDateString(),
            $conference->fresh()->ends_at->toDateString(),
        );
    }

    #[Test]
    public function conferences_accept_proposals_during_the_call_for_papers(): void
    {
        $conference = Conference::factory()->create([
            'cfp_starts_at' => Carbon::yesterday(),
            'cfp_ends_at' => Carbon::tomorrow(),
        ]);

        $this->assertTrue($conference->isCurrentlyAcceptingProposals());
    }

    #[Test]
    public function conferences_dont_accept_proposals_outside_of_the_call_for_papers(): void
    {
        $conference = Conference::factory()->create([
            'cfp_starts_at' => Carbon::tomorrow(),
            'cfp_ends_at' => Carbon::tomorrow()->addDay(),
        ]);

        $this->assertFalse($conference->isCurrentlyAcceptingProposals());

        $conference = Conference::factory()->create([
            'cfp_starts_at' => Carbon::yesterday()->subDay(),
            'cfp_ends_at' => Carbon::yesterday(),
        ]);

        $this->assertFalse($conference->isCurrentlyAcceptingProposals());
    }

    #[Test]
    public function conferences_that_havent_announced_their_cfp_are_not_accepting_proposals(): void
    {
        $conference = Conference::factory()->create([
            'cfp_starts_at' => null,
            'cfp_ends_at' => null,
        ]);

        $this->assertFalse($conference->isCurrentlyAcceptingProposals());
    }

    #[Test]
    public function non_owners_can_view_conference(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();
        $conference = Conference::factory()->create();
        $otherUser->conferences()->save($conference);

        $this->actingAs($user)
            ->get("conferences/{$conference->id}")
            ->assertSee($conference->title);
    }

    #[Test]
    public function guests_can_view_conference(): void
    {
        $conference = Conference::factory()->approved()->create();

        $this->get("conferences/{$conference->id}")
            ->assertSee($conference->title);
    }

    #[Test]
    public function guests_can_view_conference_list(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()
            ->dates(now())
            ->approved()
            ->create();
        $user->conferences()
            ->save($conference);

        $this->get('conferences?filter=all')
            ->assertSee($conference->title);
    }

    #[Test]
    public function viewing_a_conference_includes_user_talks_with_submissions(): void
    {
        $user = User::factory()
            ->has(Talk::factory()->revised(['title' => 'My Best Talk']))
            ->has(Talk::factory()->revised(['title' => 'My Worst Talk']))
            ->create();
        [$accepted, $rejected] = $user->talks;
        $conference = Conference::factory()
            ->acceptedTalk($accepted->revisions->last())
            ->rejectedTalk($rejected->revisions->last())
            ->create();

        $response = $this->actingAs($user)->get(route('conferences.show', $conference));

        $response->assertSuccessful();
        $response->assertSee('My Best Talk');
        $response->assertViewHas('talks', function ($talks) {
            return $talks->contains(fn ($talk) => $talk['title'] === 'My Best Talk' && $talk['accepted'])
                && $talks->contains(fn ($talk) => $talk['title'] === 'My Worst Talk' && $talk['rejected']);
        });
    }

    #[Test]
    public function viewing_conference_with_a_speaker_package(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()
            ->withSpeakerPackage([
                'currency' => 'usd',
                'food' => 100,
            ])
            ->create([
            ]);

        $response = $this->actingAs($user)->get(route('conferences.show', $conference));

        $response->assertSuccessful();
        $response->assertSee(100.00);
    }

    #[Test]
    public function speaker_package_isnt_visible_when_currency_missing(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()
            ->withSpeakerPackage([
                'currency' => null,
                'food' => 100,
            ])
            ->create([
            ]);

        $response = $this->actingAs($user)->get(route('conferences.show', $conference));

        $response->assertSuccessful();
        $response->assertDontSee('food');
    }

    #[Test]
    public function it_can_pull_only_approved_conferences(): void
    {
        Conference::factory()->notApproved()->create();
        Conference::factory()->approved()->create();

        $this->assertEquals(1, Conference::approved()->count());
    }

    #[Test]
    public function it_can_pull_only_not_shared_conferences(): void
    {
        Conference::factory()->create();
        Conference::factory()->shared()->create();

        $this->assertEquals(1, Conference::notShared()->count());
    }

    #[Test]
    public function navigating_to_next_month()
    {
        Carbon::setTestNow('2023-05-04');

        $conferenceA = Conference::factory()->approved()->create([
            'starts_at' => Carbon::now()->addDay(),
            'cfp_ends_at' => Carbon::now()->subDays(2),
        ]);
        $conferenceB = Conference::factory()->approved()->create([
            'starts_at' => Carbon::now()->addDays(30),
            'cfp_ends_at' => Carbon::now(),
        ]);

        $response = Livewire::test(ConferenceList::class)
            ->set('filter', 'all')
            ->set('sort', 'date')
            ->call('next');

        tap(
            $response->conferences->flatten()->values()->pluck('id'),
            function ($conferenceIds) use ($conferenceA, $conferenceB) {
                $this->assertNotContains($conferenceA->id, $conferenceIds);
                $this->assertContains($conferenceB->id, $conferenceIds);
            }
        );
    }

    #[Test]
    public function navigating_to_previous_month()
    {
        Carbon::setTestNow('2023-05-04');

        $conferenceA = Conference::factory()->approved()->create([
            'starts_at' => Carbon::now()->addDay(),
            'cfp_ends_at' => Carbon::now()->subDays(2),
        ]);
        $conferenceB = Conference::factory()->approved()->create([
            'starts_at' => Carbon::now()->subDays(30),
            'cfp_ends_at' => Carbon::now(),
        ]);

        $response = Livewire::test(ConferenceList::class)
            ->set('filter', 'all')
            ->set('sort', 'date')
            ->call('previous');

        tap(
            $response->conferences->flatten()->values()->pluck('id'),
            function ($conferenceIds) use ($conferenceA, $conferenceB) {
                $this->assertNotContains($conferenceA->id, $conferenceIds);
                $this->assertContains($conferenceB->id, $conferenceIds);
            }
        );
    }

    #[Test]
    public function sorting_by_cfp_filters_out_null_cfp(): void
    {
        Carbon::setTestNow('2023-05-04');

        $nullCfp = Conference::factory()->approved()->create([
            'cfp_starts_at' => null,
            'cfp_ends_at' => null,
            'title' => 'Null CFP',
        ]);
        $pastCfp = Conference::factory()->approved()->create([
            'cfp_starts_at' => Carbon::yesterday()->subDay(),
            'cfp_ends_at' => Carbon::yesterday(),
            'title' => 'Past CFP',
        ]);
        $futureCfp = Conference::factory()->approved()->create([
            'cfp_starts_at' => Carbon::yesterday(),
            'cfp_ends_at' => Carbon::tomorrow(),
            'title' => 'Future CFP',
        ]);

        $response = Livewire::test(ConferenceList::class)
            ->set('filter', 'all')
            ->set('sort', 'cfp_closing_next');

        $response->assertSee($pastCfp->title);
        $response->assertSee($futureCfp->title);
        $response->assertDontSee($nullCfp->title);
    }

    #[Test]
    public function sorting_by_event_date(): void
    {
        Carbon::setTestNow('2023-05-04');

        $conferenceA = Conference::factory()->approved()->create([
            'starts_at' => Carbon::now()->addDay(),
            'cfp_ends_at' => Carbon::now()->subDays(2),
        ]);
        $conferenceB = Conference::factory()->approved()->create([
            'starts_at' => Carbon::now()->addDays(30),
            'cfp_ends_at' => Carbon::now(),
        ]);

        $response = Livewire::test(ConferenceList::class)
            ->set('filter', 'future')
            ->set('sort', 'date');

        $this->assertConferenceSort([
            $conferenceA,
            $conferenceB,
        ], $response->conferences);
    }

    #[Test]
    public function sorting_by_cfp_opening_date(): void
    {
        $conferenceA = Conference::factory()->create([
            'starts_at' => Carbon::now()->addMonth(),
            'cfp_starts_at' => Carbon::now()->addDay(),
        ]);
        $conferenceB = Conference::factory()->create([
            'starts_at' => Carbon::now()->addWeek(),
            'cfp_starts_at' => Carbon::now()->addDays(2),
        ]);

        $response = Livewire::test(ConferenceList::class)
            ->set('filter', 'future')
            ->set('sort', 'cfp_opening_next');

        $this->assertConferenceSort([
            $conferenceA,
            $conferenceB,
        ], $response->conferences);
    }

    #[Test]
    public function sorting_by_cfp_closing_date(): void
    {
        $conferenceA = Conference::factory()->create([
            'starts_at' => Carbon::now()->addMonth(),
            'cfp_starts_at' => Carbon::now()->subDay(),
            'cfp_ends_at' => Carbon::now()->addDay(),
        ]);
        $conferenceB = Conference::factory()->create([
            'starts_at' => Carbon::now()->addWeek(),
            'cfp_starts_at' => Carbon::now()->subDay(),
            'cfp_ends_at' => Carbon::now()->addDays(2),
        ]);

        $response = Livewire::test(ConferenceList::class)
            ->set('filter', 'future')
            ->set('sort', 'cfp_closing_next');

        $this->assertConferenceSort([
            $conferenceA,
            $conferenceB,
        ], $response->conferences);
    }

    #[Test]
    public function dismissed_conferences_do_not_show_up_in_conference_list(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->dismissedBy($user)->create();

        $response = $this->actingAs($user)->get('conferences?filter=all');

        $response->assertDontSee($conference->title);
    }

    #[Test]
    public function filtering_by_open_cfp_hides_non_cfp_conferences(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()->approved()->create([
            'has_cfp' => false,
        ]);
        $user->conferences()->save($conference);

        $this->actingAs($user)
            ->get('conferences?filter=open_cfp')
            ->assertDontSee($conference->title);
    }

    #[Test]
    public function filtering_by_open_cfp_hides_conferences_without_event_dates(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()->approved()->create([
            'has_cfp' => true,
            'cfp_starts_at' => now()->subDay(),
            'cfp_ends_at' => now()->addDay(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay(),
        ]);
        $user->conferences()->save($conference);

        $this->actingAs($user)
            ->get('conferences?filter=open_cfp')
            ->assertDontSee($conference->title);
    }

    #[Test]
    public function filtering_by_future_cfp_hides_non_cfp_conferences(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()->approved()->create([
            'has_cfp' => false,
        ]);
        $user->conferences()->save($conference);

        $this->actingAs($user)
            ->get('conferences?filter=future_cfp')
            ->assertDontSee($conference->title);
    }

    #[Test]
    public function filtering_by_unclosed_cfp_shows_open_and_future_cfp(): void
    {
        $user = User::factory()->create();
        Conference::factory()
            ->cfpDates(now()->subDay(), now()->addDay())
            ->create(['title' => 'Open CFP Conference']);
        Conference::factory()
            ->cfpDates(now()->addDay(), now()->addDays(2))
            ->create(['title' => 'Future CFP Conference']);
        Conference::factory()->create([
            'has_cfp' => false,
            'title' => 'No CFP Conference',
        ]);

        $this->actingAs($user)
            ->get('conferences?filter=unclosed_cfp')
            ->assertSee('Open CFP Conference')
            ->assertSee('Future CFP Conference')
            ->assertDontSee('No CFP Conference');
    }

    #[Test]
    public function filtering_by_future_shows_future_conferences(): void
    {
        $conferenceA = Conference::factory()->create([
            'starts_at' => now()->addDay(),
            'title' => 'Conference A',
        ]);
        $conferenceB = Conference::factory()->create([
            'starts_at' => now()->subDay(),
            'title' => 'Conference B',
        ]);

        $response = $this->get('conferences?filter=future');

        $response->assertSee('Conference A');
        $response->assertDontSee('Conference B');
    }

    #[Test]
    public function filtering_by_future_shows_future_cfp_openings_when_sorting_by_cfp_opening(): void
    {
        $conferenceA = Conference::factory()->create([
            'starts_at' => now()->addMonth(),
            'cfp_starts_at' => now()->addDay(),
            'title' => 'Conference A',
        ]);
        $conferenceB = Conference::factory()->create([
            'starts_at' => now()->addMonth(),
            'cfp_ends_at' => now()->subDay(),
            'title' => 'Conference B',
        ]);

        $response = $this->get('conferences?filter=future&sort=cfp_opening_next');

        $response->assertSee('Conference A');
        $response->assertDontSee('Conference B');
    }

    #[Test]
    public function filtering_by_future_shows_future_cfp_closings_when_sorting_by_cfp_closing(): void
    {
        $conferenceA = Conference::factory()->create([
            'starts_at' => now()->addMonth(),
            'cfp_starts_at' => now()->subWeek(),
            'cfp_ends_at' => now()->addDay(),
            'title' => 'Conference A',
        ]);
        $conferenceB = Conference::factory()->create([
            'starts_at' => now()->addMonth(),
            'cfp_ends_at' => now()->subWeek(),
            'cfp_ends_at' => now()->subDay(),
            'title' => 'Conference B',
        ]);

        $response = $this->get('conferences?filter=future&sort=cfp_closing_next');

        $response->assertSee('Conference A');
        $response->assertDontSee('Conference B');
    }

    #[Test]
    public function filtering_by_dismissed_shows_dismissed_conferences(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->dismissedBy($user)->create();

        $response = $this->actingAs($user)->get('conferences?filter=dismissed');

        $response->assertSee($conference->title);
    }

    #[Test]
    public function filtering_by_dismissed_does_not_show_undismissed_conferences(): void
    {
        $user = User::factory()->create();

        $conference = Conference::factory()->create();
        $user->conferences()->save($conference);

        $this->actingAs($user)
            ->get('conferences?filter=dismissed')
            ->assertDontSee($conference->title);
    }

    #[Test]
    public function filtering_by_favorites_shows_favorite_conferences(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->favoritedBy($user)->create();

        $response = $this->actingAs($user)->get('conferences?filter=favorites');

        $response->assertSee($conference->title);
    }

    #[Test]
    public function filtering_by_favorites_does_not_show_nonfavorite_conferences(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->create();

        $response = $this->actingAs($user)->get('conferences?filter=favorites');

        $response->assertDontSee($conference->title);
    }

    #[Test]
    public function a_favorited_conference_cannot_be_dismissed(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->favoritedBy($user)->create();
        $this->assertFalse($conference->isDismissedBy($user));

        Livewire::actingAs($user)
            ->test(ConferenceList::class)
            ->call('toggleDismissed', $conference);

        $this->assertFalse($conference->isDismissedBy($user->fresh()));
    }

    #[Test]
    public function a_dismissed_conference_cannot_be_favorited(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->dismissedBy($user)->create();
        $this->assertFalse($conference->isFavoritedBy($user));

        Livewire::actingAs($user)
            ->test(ConferenceList::class)
            ->call('toggleFavorite', $conference);

        $this->assertFalse($conference->isFavoritedBy($user->fresh()));
    }

    #[Test]
    public function displaying_event_dates_with_no_dates_set(): void
    {
        $conference = Conference::factory()->make([
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertNull($conference->event_dates_display);
    }

    #[Test]
    public function displaying_event_dates_with_a_start_date_and_no_end_date(): void
    {
        $conference = Conference::factory()->make([
            'starts_at' => '2020-01-01 09:00:00',
            'ends_at' => null,
        ]);

        $this->assertEquals('January 1, 2020', $conference->event_dates_display);
    }

    #[Test]
    public function displaying_event_dates_with_an_end_date_and_no_start_date(): void
    {
        $conference = Conference::factory()->make([
            'starts_at' => null,
            'ends_at' => '2020-01-01 09:00:00',
        ]);

        $this->assertNull($conference->event_dates_display);
    }

    #[Test]
    public function displaying_event_dates_with_the_same_start_and_end_dates(): void
    {
        $conference = Conference::factory()->make([
            'starts_at' => '2020-01-01 09:00:00',
            'ends_at' => '2020-01-01 16:00:00',
        ]);

        $this->assertEquals('January 1, 2020', $conference->event_dates_display);
    }

    #[Test]
    public function displaying_event_dates_with_the_different_start_and_end_dates(): void
    {
        $conference = Conference::factory()->make([
            'starts_at' => '2020-01-01 09:00:00',
            'ends_at' => '2020-01-03 16:00:00',
        ]);

        $this->assertEquals('Jan 1 2020 - Jan 3 2020', $conference->event_dates_display);
    }

    public function assertConferenceSort($expectedConferences, $conferences)
    {
        foreach ($expectedConferences as $sortPosition => $conference) {
            $sortedConference = $conferences->flatten()->values()[$sortPosition];

            $this->assertTrue($sortedConference->is($conference), "Conference ID {$conference->id} was expected in position {$sortPosition}, but {$sortedConference->id } was in position {$sortPosition}.");
        }
    }

    #[Test]
    public function scoping_conferences_queries_where_has_dates(): void
    {
        $conferenceA = Conference::factory()->create(['starts_at' => Carbon::parse('yesterday'), 'ends_at' => Carbon::parse('tomorrow')]);
        $conferenceB = Conference::factory()->create(['starts_at' => Carbon::parse('yesterday'), 'ends_at' => null]);
        $conferenceC = Conference::factory()->create(['starts_at' => null, 'ends_at' => Carbon::parse('tomorrow')]);
        $conferenceD = Conference::factory()->create(['starts_at' => null, 'ends_at' => null]);

        $conferenceIds = Conference::whereHasDates()->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
        $this->assertNotContains($conferenceC->id, $conferenceIds);
        $this->assertNotContains($conferenceD->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_has_cfp_start_date(): void
    {
        $conferenceA = Conference::factory()->create(['cfp_starts_at' => Carbon::parse('yesterday')]);
        $conferenceB = Conference::factory()->create(['cfp_starts_at' => null]);

        $conferenceIds = Conference::whereHasCfpStart()->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_favorited_by_user(): void
    {
        $user = User::factory()->create();
        $conferenceA = Conference::factory()->favoritedBy($user)->create();
        $conferenceB = Conference::factory()->create();

        $conferenceIds = Conference::whereFavoritedBy($user)->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_dismissed_by_user(): void
    {
        $user = User::factory()->create();
        $conferenceA = Conference::factory()->dismissedBy($user)->create();
        $conferenceB = Conference::factory()->create();

        $conferenceIds = Conference::whereDismissedBy($user)->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_not_dismissed_by_user(): void
    {
        $user = User::factory()->create();
        $conferenceA = Conference::factory()->dismissedBy($user)->create();
        $conferenceB = Conference::factory()->create();

        $conferenceIds = Conference::whereNotDismissedBy($user)->get()->pluck('id');

        $this->assertNotContains($conferenceA->id, $conferenceIds);
        $this->assertContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_cfp_is_open(): void
    {
        Carbon::setTestNow('2023-05-04');

        $conferenceA = Conference::factory()->cfpDates('2023-05-01', '2023-06-01')->create();
        $conferenceB = Conference::factory()->cfpDates('2023-06-01', '2023-07-01')->create();

        $conferenceIds = Conference::whereCfpIsOpen()->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_cfp_is_future(): void
    {
        Carbon::setTestNow('2023-05-04');

        $conferenceA = Conference::factory()->cfpDates('2023-05-01', '2023-06-01')->create();
        $conferenceB = Conference::factory()->cfpDates('2023-06-01', '2023-07-01')->create();

        $conferenceIds = Conference::whereCfpIsFuture()->get()->pluck('id');

        $this->assertNotContains($conferenceA->id, $conferenceIds);
        $this->assertContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conferences_queries_where_has_cfp_end_date(): void
    {
        $conferenceA = Conference::factory()->create(['cfp_ends_at' => Carbon::parse('yesterday')]);
        $conferenceB = Conference::factory()->create(['cfp_ends_at' => null]);

        $conferenceIds = Conference::whereHasCfpEnd()->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conference_queries_by_event_year_and_month(): void
    {
        $conferenceA = Conference::factory()->dates('2023-01-01')->create();
        $conferenceB = Conference::factory()->dates('2022-12-01')->create();
        $conferenceC = Conference::factory()->dates('2022-12-31', '2023-01-31')->create();
        $conferenceD = Conference::factory()->dates('2022-12-31', '2023-02-01')->create();

        $conferenceIds = Conference::whereDateDuring(2023, 1, 'starts_at')->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
        $this->assertNotContains($conferenceC->id, $conferenceIds);
        $this->assertNotContains($conferenceD->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conference_queries_by_cfp_start_year_and_month(): void
    {
        $conferenceA = Conference::factory()->cfpDates('2023-01-01')->create();
        $conferenceB = Conference::factory()->cfpDates('2022-12-01')->create();
        $conferenceC = Conference::factory()->cfpDates('2022-12-31', '2023-01-31')->create();
        $conferenceD = Conference::factory()->cfpDates('2022-12-31', '2023-02-01')->create();

        $conferenceIds = Conference::whereDateDuring(2023, 1, 'cfp_starts_at')->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
        $this->assertNotContains($conferenceC->id, $conferenceIds);
        $this->assertNotContains($conferenceD->id, $conferenceIds);
    }

    #[Test]
    public function scoping_conference_queries_by_cfp_end_year_and_month(): void
    {
        $conferenceA = Conference::factory()->cfpDates('2023-01-01')->create();
        $conferenceB = Conference::factory()->cfpDates('2022-12-01')->create();
        $conferenceC = Conference::factory()->cfpDates('2022-12-31', '2023-01-31')->create();
        $conferenceD = Conference::factory()->cfpDates('2022-12-31', '2023-02-01')->create();

        $conferenceIds = Conference::whereDateDuring(2023, 1, 'cfp_ends_at')->get()->pluck('id');

        $this->assertContains($conferenceA->id, $conferenceIds);
        $this->assertNotContains($conferenceB->id, $conferenceIds);
        $this->assertContains($conferenceC->id, $conferenceIds);
        $this->assertNotContains($conferenceD->id, $conferenceIds);
    }

    #[Test]
    public function conferences_with_reported_issues_are_flagged(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $conference = Conference::factory()->create();
        $this->assertFalse($conference->isFlagged());

        $conference->reportIssue('spam', 'Conference has spam', $user);

        $conference->loadCount('openIssues');
        $this->assertTrue($conference->isFlagged());
    }

    #[Test]
    public function conferences_with_closed_issues_are_not_flagged(): void
    {
        $conference = Conference::factory()->withClosedIssue()->create();

        $conference->loadCount('openIssues');
        $this->assertFalse($conference->isFlagged());
    }

    #[Test]
    public function rejected_conferences_are_not_found(): void
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->rejected()->create();

        $response = $this->actingAs($user)->get($conference->link);

        $response->assertNotFound();
    }

    #[Test]
    public function admins_can_see_rejected_conferences(): void
    {
        $user = User::factory()->admin()->create();
        $conference = Conference::factory()->rejected()->create();

        $response = $this->actingAs($user)->get($conference->link);

        $response->assertSuccessful();
    }

    #[Test]
    public function rejecting_conferences(): void
    {
        $conference = Conference::factory()->create();
        $this->assertNull($conference->rejected_at);

        $conference->reject();

        $this->assertNotNull($conference->fresh()->rejected_at);
    }

    #[Test]
    public function restoring_rejected_conferences(): void
    {
        $conference = Conference::factory()->rejected()->create();
        $this->assertNotNull($conference->fresh()->rejected_at);

        $conference->restore();

        $this->assertNull($conference->rejected_at);
    }

    #[Test]
    public function checking_whether_a_conferences_is_rejected(): void
    {
        $conferenceA = Conference::factory()->create();
        $conferenceB = Conference::factory()->rejected()->create();

        $this->assertFalse($conferenceA->isRejected());
        $this->assertTrue($conferenceB->isRejected());
    }

    #[Test]
    public function searching_conferences_by_name(): void
    {
        $conferenceA = Conference::factory()->create(['location' => 'Boston, MA']);
        $conferenceB = Conference::factory()->create(['location' => 'New York, NY']);

        $results = Conference::searchQuery('boston', fn ($query) => $query)->get();

        $this->assertContains($conferenceA->id, $results->pluck('id'));
        $this->assertNotContains($conferenceB->id, $results->pluck('id'));
    }

    #[Test]
    public function past_conferences_are_not_searchable(): void
    {
        $conferenceA = Conference::factory()->dates(now()->subDay())->create();
        $conferenceB = Conference::factory()->dates(now()->addDay())->create();

        $this->assertFalse($conferenceA->shouldBeSearchable());
        $this->assertTrue($conferenceB->shouldBeSearchable());
    }

    #[Test]
    public function rejected_conferences_are_not_searchable(): void
    {
        $conferenceA = Conference::factory()->create(['rejected_at' => now()]);
        $conferenceB = Conference::factory()->create(['rejected_at' => null]);

        $this->assertFalse($conferenceA->shouldBeSearchable());
        $this->assertTrue($conferenceB->shouldBeSearchable());
    }

    #[Test]
    public function conferences_with_open_issues_are_flagged_on_the_index_page(): void
    {
        $conference = Conference::factory()->withOpenIssue()->create();

        $response = Livewire::test(ConferenceList::class);

        tap($response->conferences->flatten(), function ($conferences) {
            $this->assertEquals(1, $conferences->count());
            $this->assertTrue($conferences->first()->isFlagged());
        });
    }

    #[Test]
    public function conferences_with_open_issues_are_flagged_on_the_show_page(): void
    {
        $conference = Conference::factory()->withOpenIssue()->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('conferences.show', $conference));

        $response->assertSee('An issue has been reported for this conference.');
    }

    #[Test]
    public function conferences_with_open_issues_are_flagged_on_the_public_show_page(): void
    {
        $conference = Conference::factory()->withOpenIssue()->create();

        $response = $this->get(route('conferences.show', $conference));

        $response->assertSee('An issue has been reported for this conference.');
    }

    #[Test]
    public function deleting_a_conference()
    {
        $user = User::factory()->create();
        $conference = Conference::factory()->author($user)->create();

        $response = $this->actingAs($user)->delete(route('conferences.destroy', $conference));

        $response->assertRedirect(route('conferences.index'));
    }
}

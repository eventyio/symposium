<?php

namespace Tests\Feature;

use App\Models\Acceptance;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\Talk;
use App\Models\TalkRevision;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TalkTest extends TestCase
{
    #[Test]
    public function archiving_a_talk(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->create();
        $this->assertFalse($talk->isArchived());

        $response = $this->actingAs($user)->get(route('talks.archive', $talk));

        $response->assertRedirect();
        $this->assertTrue($talk->fresh()->isArchived());
    }

    #[Test]
    public function archived_talks_are_not_included_on_the_index_page(): void
    {
        $user = User::factory()->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'my active talk'])
            ->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'my archived talk'])
            ->archived()
            ->create();

        $response = $this->actingAs($user)
            ->get('talks')
            ->assertSuccessful();

        $response->assertSee('my active talk');
        $response->assertDontSee('my archived talk');
    }

    #[Test]
    public function active_talks_are_not_included_on_the_archived_index_page(): void
    {
        $user = User::factory()->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'my active talk'])
            ->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'my archived talk'])
            ->archived()
            ->create();

        $response = $this->actingAs($user)
            ->get('archive')
            ->assertSuccessful();

        $response->assertSee('my archived talk');
        $response->assertDontSee('my active talk');
    }

    #[Test]
    public function filtering_talks_by_those_submitted_to_conferences(): void
    {
        $user = User::factory()->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'My Submitted Talk'])
            ->submitted()
            ->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'My Backup Talk'])
            ->create();

        $response = $this->actingAs($user)->get(route('talks.index', ['filter' => 'submitted']));

        $response->assertSuccessful();
        $response->assertSee('My Submitted Talk');
        $response->assertDontSee('My Backup Talk');
    }

    #[Test]
    public function filtering_talks_by_those_accepted_by_conferences(): void
    {
        $user = User::factory()->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'My Accepted Talk'])
            ->accepted()
            ->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'My Backup Talk'])
            ->create();

        $response = $this->actingAs($user)->get(route('talks.index', ['filter' => 'accepted']));

        $response->assertSuccessful();
        $response->assertSee('My Accepted Talk');
        $response->assertDontSee('My Backup Talk');
    }

    #[Test]
    public function it_shows_the_talk_title_on_its_page(): void
    {
        $user = User::factory()->create();
        Conference::factory()->create();
        $talk = Talk::factory()->author($user)->create();
        $revision = TalkRevision::factory()->create();
        $talk->revisions()->save($revision);

        $response = $this->actingAs($user)
            ->get("talks/{$talk->id}");

        $response->assertSee($revision->title);
    }

    #[Test]
    public function viewing_a_talk_revision(): void
    {
        $user = User::factory()->create();
        Conference::factory()->create();
        $talk = Talk::factory()->author($user)->create();

        $firstDraft = TalkRevision::factory()->create(['title' => 'First Draft']);
        $talk->revisions()->save($firstDraft);

        $current = TalkRevision::factory()->create(['title' => 'Final Talk']);
        $talk->revisions()->save($current);

        $response = $this->actingAs($user)
            ->get("talks/{$talk->id}?revision=".$firstDraft->id);

        $response->assertSuccessful();
        $response->assertSee('First Draft');
    }

    #[Test]
    public function user_talks_are_sorted_alphabetically(): void
    {
        $user = User::factory()->create();
        $talk1 = Talk::factory()->author($user)->revised(['title' => 'zyxwv'])->create();
        $talk2 = Talk::factory()->author($user)->revised(['title' => 'abcde'])->create();

        $talks = $user->talks()->withCurrentRevision()->get();

        $this->assertEquals('abcde', $talks->sortByTitle()->first()->currentRevision->title);
        $this->assertEquals('zyxwv', $talks->sortByTitle()->last()->currentRevision->title);
    }

    #[Test]
    public function user_talks_can_be_sorted_by_date()
    {
        $user = User::factory()->create();
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'My Other Talk'])
            ->create(['created_at' => now()->subDays(2)]);
        Talk::factory()
            ->author($user)
            ->revised(['title' => 'My Awesome Talk'])
            ->create(['created_at' => now()->subDays(1)]);

        $response = $this->actingAs($user)->get(route('talks.index', ['sort' => 'date']));

        $response->assertSuccessful();
        $response->assertSeeTextInOrder([
            'My Awesome Talk',
            'My Other Talk',
        ]);
    }

    #[Test]
    public function user_talks_json_encode_without_keys(): void
    {
        $user = User::factory()->create();

        $talk1 = Talk::factory()->author($user)->create();
        $revision1 = TalkRevision::factory()->create(['title' => 'zyxwv']);
        $talk1->revisions()->save($revision1);

        $talk2 = Talk::factory()->author($user)->create();
        $revision2 = TalkRevision::factory()->create(['title' => 'abcde']);
        $talk2->revisions()->save($revision2);

        $json = json_encode($user->talks);

        $this->assertTrue(is_array(json_decode($json)));
    }

    #[Test]
    public function viewing_the_create_talk_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('talks.create'));

        $response->assertSuccessful();
    }

    #[Test]
    public function user_can_create_a_talk(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('talks', [
                'title' => 'Your Best Talk Now',
                'type' => 'keynote',
                'level' => 'intermediate',
                'description' => 'No, really.',
                'length' => '123',
                'slides' => 'http://www.google.com/slides',
                'organizer_notes' => "It'll be awesome!",
                'public' => '1',
            ]);

        $this->assertDatabaseHas(TalkRevision::class, [
            'title' => 'Your Best Talk Now',
            'type' => 'keynote',
            'level' => 'intermediate',
            'description' => 'No, really.',
            'length' => '123',
            'slides' => 'http://www.google.com/slides',
            'organizer_notes' => "It'll be awesome!",
        ]);

        $talk = Talk::first();

        $this->assertTrue($talk->public);

        $this->get("talks/{$talk->id}")
            ->assertSee('Your Best Talk Now')
            ->assertSee('No, really.');
    }

    #[Test]
    public function new_talks_must_include_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('talks', []);

        $response->assertInvalid([
            'title',
            'type',
            'level',
            'length',
            'organizer_notes',
            'description',
            'public',
        ]);
    }

    #[Test]
    public function new_talks_must_include_a_valid_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('talks', [
            'title' => 'My invalid talk',
            'type' => 'keynote',
            'level' => 'intermediate',
            'length' => 'invalid',
            'organizer_notes' => "It'll be awesome!",
            'description' => 'No, really.',
            'public' => '1',
        ]);

        $response->assertInvalid(['length']);
    }

    #[Test]
    public function new_talks_with_slides_must_include_a_valid_slides_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('talks', [
            'title' => 'My invalid talk',
            'type' => 'keynote',
            'level' => 'intermediate',
            'length' => '123',
            'organizer_notes' => "It'll be awesome!",
            'description' => 'No, really.',
            'public' => '1',
            'slides' => 'invalid url',
        ]);

        $response->assertInvalid(['slides']);
    }

    #[Test]
    public function user_can_delete_a_talk(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->create();
        $talkRevision = TalkRevision::factory()->create([
            'title' => 'zyxwv',
            'talk_id' => $talk->id,
        ]);

        $this->be($user);

        $this->get("talks/{$talk->id}/delete");

        $this->assertModelMissing($talk);
        $this->assertModelMissing($talkRevision);
    }

    #[Test]
    public function user_can_save_a_new_revision_of_a_talk(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)
            ->revised([
                'title' => 'old title',
                'created_at' => Carbon::now()->subMinutes(2),
            ])
            ->create([
                'public' => false,
            ]);

        $this->actingAs($user)
            ->put("/talks/{$talk->id}", array_merge($talk->loadCurrentRevision()->currentRevision->toArray(), [
                'title' => 'New',
                'public' => '1',
            ]));

        $talk = Talk::first();

        $this->assertTrue($talk->public);
        $this->assertEquals('New', $talk->loadCurrentRevision()->currentRevision->title);
        $this->assertEquals('old title', $talk->revisions->last()->title);
    }

    #[Test]
    public function revised_talks_must_include_required_fields(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->create();

        $response = $this->actingAs($user)->put("/talks/{$talk->id}", []);

        $response->assertInvalid([
            'title',
            'type',
            'level',
            'length',
            'organizer_notes',
            'description',
            'public',
        ]);
    }

    #[Test]
    public function revised_talks_must_include_a_valid_length(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->create();

        $response = $this->actingAs($user)
            ->put("/talks/{$talk->id}", array_merge($talk->loadCurrentRevision()->currentRevision->toArray(), [
                'length' => 'invalid',
            ]));

        $response->assertInvalid(['length']);
    }

    #[Test]
    public function revised_talks_with_slides_must_include_a_valid_slides_url(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->create();

        $response = $this->actingAs($user)
            ->put("/talks/{$talk->id}", array_merge($talk->loadCurrentRevision()->currentRevision->toArray(), [
                'slides' => 'invalid url',
            ]));

        $response->assertInvalid(['slides']);
    }

    #[Test]
    public function scoping_talks_where_submitted(): void
    {
        [$talkRevisionA, $talkRevisionB] = TalkRevision::factory()->count(2)->create();
        $conference = Conference::factory()->create();
        Submission::factory()->create([
            'talk_revision_id' => $talkRevisionA->id,
            'conference_id' => $conference->id,
        ]);

        $submittedTalkIds = Talk::submitted()->get()->pluck('id');

        $this->assertContains($talkRevisionA->talk_id, $submittedTalkIds);
        $this->assertNotContains($talkRevisionB->talk_id, $submittedTalkIds);
    }

    #[Test]
    public function scoping_talks_where_accepted(): void
    {
        [$talkRevisionA, $talkRevisionB] = TalkRevision::factory()->count(2)->create();
        $conference = Conference::factory()->create();
        TalkRevision::all()->each(function ($talkRevision) use ($conference) {
            Submission::factory()->create([
                'talk_revision_id' => $talkRevision->id,
                'conference_id' => $conference->id,
            ]);
        });

        Acceptance::factory()->create([
            'talk_revision_id' => $talkRevisionA->id,
            'conference_id' => $conference->id,
        ]);

        $acceptedTalkIds = Talk::accepted()->get()->pluck('id');

        $this->assertContains($talkRevisionA->talk_id, $acceptedTalkIds);
        $this->assertNotContains($talkRevisionB->talk_id, $acceptedTalkIds);
    }

    #[Test]
    public function archived_talks_are_not_included_in_queries_by_default(): void
    {
        $talk = Talk::factory()->archived()->create();

        $activeTalks = Talk::all();
        $this->assertNotContains($talk->id, $activeTalks->pluck('id'));

        // talk is included when excluding query scope
        $allTalks = Talk::withoutGlobalScope('active')->get();
        $this->assertContains($talk->id, $allTalks->pluck('id'));
    }

    #[Test]
    public function archived_talks_can_be_restored(): void
    {
        $talk = Talk::factory()->archived()->create();

        $response = $this->actingAs($talk->author)
            ->get(route('talks.restore', $talk));

        $response->assertRedirect('archive');
        $this->assertFalse($talk->fresh()->isArchived());
    }

    #[Test]
    public function archived_talks_can_be_deleted(): void
    {
        $talk = Talk::factory()->archived()->create();

        $response = $this->actingAs($talk->author)
            ->delete(route('talks.destroy', $talk));

        $response->assertRedirect('talks');
        $this->assertDatabaseMissing('talks', ['id' => $talk->id]);
    }

    #[Test]
    public function editing_a_talk(): void
    {
        $talk = Talk::factory()->create();

        $response = $this->actingAs($talk->author)
            ->get(route('talks.edit', $talk));

        $response->assertSuccessful();
    }

    #[Test]
    public function users_cannot_see_the_edit_form_for_talks_they_dont_own(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $talk = Talk::factory()->author($userA)->create();

        $response = $this->actingAs($userB)
            ->get(route('talks.edit', $talk));

        $response->assertNotFound();
    }
}

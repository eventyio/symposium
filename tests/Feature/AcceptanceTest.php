<?php

namespace Tests;

use App\Models\Acceptance;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\Talk;
use App\Models\TalkRevision;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class AcceptanceTest extends TestCase
{
    #[Test]
    public function can_create_from_submission(): void
    {
        $user = User::factory()->create();
        $this->be($user);

        $conference = Conference::factory()->create();
        $talk = Talk::factory()->author($user)->create();
        $revision = TalkRevision::factory()->create();
        $talk->revisions()->save($revision);

        $submission = Submission::factory()->create([
            'talk_revision_id' => $revision->id,
            'conference_id' => $conference->id,
        ]);

        $acceptance = Acceptance::createFromSubmission($submission);

        $submission = $submission->refresh();

        $this->assertTrue($submission->isAccepted());
        $this->assertEquals($submission->id, $acceptance->submission->id);
    }

    #[Test]
    public function user_can_remove_acceptance_via_http(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->author($user)->accepted()->create();

        $this->actingAs($user)
            ->delete("acceptances/{$talk->acceptances->first()->id}");

        $this->assertFalse($talk->submissions()->first()->isAccepted());
        $this->assertEquals(0, $talk->acceptances()->count());
    }

    #[Test]
    public function users_cannot_delete_acceptances_of_other_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $talk = Talk::factory()->author($userB)->accepted()->create();

        $response = $this->actingAs($userA)
            ->delete("acceptances/{$talk->acceptances->first()->id}");

        $response->assertUnauthorized();
        $this->assertTrue($talk->submissions()->first()->isAccepted());
        $this->assertEquals(1, $talk->acceptances()->count());
    }
}

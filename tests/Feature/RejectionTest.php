<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Rejection;
use App\Models\Submission;
use App\Models\Talk;
use App\Models\TalkRevision;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RejectionTest extends TestCase
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

        $rejection = Rejection::createFromSubmission($submission);

        $submission = $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertEquals($submission->id, $rejection->submission->id);
    }

    #[Test]
    public function user_can_remove_rejection_via_http(): void
    {
        $user = User::factory()->create();
        $this->be($user);

        $conference = Conference::factory()->create();
        $talk = Talk::factory()->author($user)->create();
        $revision = TalkRevision::factory()->create();
        $talk->revisions()->save($revision);

        $rejection = Rejection::factory()->create();

        $submission = Submission::factory()->create([
            'talk_revision_id' => $revision->id,
            'conference_id' => $conference->id,
            'rejection_id' => $rejection->id,
        ]);

        $this->delete("rejections/{$rejection->id}");

        $this->assertFalse($submission->refresh()->isRejected());
    }

    #[Test]
    public function users_cannot_delete_rejections_belonging_to_other_users()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $talk = Talk::factory()->author($userA)->create();
        $revision = $talk->revisions()->first();
        $conference = Conference::factory()->rejectedTalk($revision)->create();
        $this->assertEquals(1, $revision->rejections()->count());
        $this->assertEquals(1, $revision->submissions()->count());

        $this->actingAs($userB)->delete(route('rejections.delete', $revision->rejections->first()));

        $this->assertTrue($revision->submissions->first()->isRejected());
        $this->assertEquals(1, $revision->rejections()->count());
    }
}

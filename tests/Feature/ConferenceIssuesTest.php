<?php

namespace Tests\Feature;

use App\Models\ConferenceIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConferenceIssuesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function issues_that_have_not_been_closed_are_open(): void
    {
        $openIssue = ConferenceIssue::factory()->create([
            'closed_at' => null,
        ]);
        $closedIssue = ConferenceIssue::factory()->create([
            'closed_at' => now(),
        ]);

        $this->assertTrue($openIssue->isOpen());
        $this->assertFalse($closedIssue->isOpen());
    }

    #[Test]
    public function closing_an_issue(): void
    {
        $user = User::factory()->create();
        $issue = ConferenceIssue::factory()->create();
        $this->assertTrue($issue->isOpen());

        $issue->close($user, 'This conference is spam');

        tap($issue->fresh(), function ($issue) use ($user) {
            $this->assertEquals($user->id, $issue->closed_by);
            $this->assertEquals('This conference is spam', $issue->admin_note);
            $this->assertNotNull($issue->closed_at);
        });
    }
}

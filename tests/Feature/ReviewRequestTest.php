<?php

namespace Tests\Feature;

use App\Enums\MessageEvent;
use App\Models\Attendance;
use App\Models\MessageLog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function checkIns($member, int $times): void
    {
        foreach (range(1, $times) as $i) {
            Attendance::withoutGlobalScopes()->create(['gym_id' => $member->gym_id, 'member_id' => $member->id, 'checked_in_at' => now()->subDays($times - $i)]);
        }
    }

    protected function reviewLogs()
    {
        return MessageLog::withoutGlobalScopes()->where('event', MessageEvent::ReviewRequest)->get();
    }

    public function test_review_link_is_sent_once_on_the_chosen_visit(): void
    {
        $gym = $this->createGym(['settings' => [
            'review_request_enabled' => true, 'google_review_url' => 'https://g.page/r/iron/review',
            'review_request_after_visits' => 3, 'channels' => ['whatsapp'],
        ]]);
        $member = $this->createMember($gym, ['name' => 'Asha']);

        $this->checkIns($member, 2);
        $this->assertCount(0, $this->reviewLogs());

        $this->checkIns($member, 3);
        $logs = $this->reviewLogs();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('https://g.page/r/iron/review', $logs->first()->body);
        $this->assertStringContainsString('Asha', $logs->first()->body);
    }

    public function test_nothing_is_sent_when_turned_off_or_without_a_link(): void
    {
        $off = $this->createGym(['settings' => ['review_request_enabled' => false, 'google_review_url' => 'https://g.page/r/x/review', 'review_request_after_visits' => 2]]);
        $noLink = $this->createGym(['settings' => ['review_request_enabled' => true, 'review_request_after_visits' => 2]]);

        $this->checkIns($this->createMember($off), 3);
        $this->checkIns($this->createMember($noLink), 3);

        $this->assertCount(0, $this->reviewLogs());
        $this->assertNull(Tenancy::gym());
    }
}

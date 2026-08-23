<?php

namespace Tests\Unit\Services;

use App\Models\GuestSession;
use App\Models\QuizResult;
use App\Models\User;
use App\Services\AccountMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountMigrationService;
    }

    public function test_returns_no_session_token_when_none_provided(): void
    {
        $result = $this->service->migrate(null, User::factory()->create());

        $this->assertSame(['migrated' => false, 'reason' => 'no_session_token', 'results_count' => null], $result);
    }

    public function test_returns_session_not_found_for_unknown_token(): void
    {
        $result = $this->service->migrate('00000000-0000-0000-0000-000000000000', User::factory()->create());

        $this->assertFalse($result['migrated']);
        $this->assertSame('session_not_found', $result['reason']);
    }

    public function test_returns_already_migrated_when_session_was_already_linked(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay(), 'migrated_at' => now()]);

        $result = $this->service->migrate($guestSession->session_token, User::factory()->create());

        $this->assertFalse($result['migrated']);
        $this->assertSame('session_already_migrated', $result['reason']);
    }

    public function test_returns_expired_for_an_expired_session(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->subDay()]);

        $result = $this->service->migrate($guestSession->session_token, User::factory()->create());

        $this->assertFalse($result['migrated']);
        $this->assertSame('session_expired', $result['reason']);
    }

    public function test_migrates_quiz_results_and_marks_session_migrated_on_success(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token, 'completed_at' => now()]);
        $user = User::factory()->create();

        $result = $this->service->migrate($guestSession->session_token, $user);

        $this->assertTrue($result['migrated']);
        $this->assertSame(1, $result['results_count']);
        $this->assertSame($user->id, $quizResult->fresh()->user_id);
        $this->assertNotNull($guestSession->fresh()->migrated_at);
        $this->assertSame($user->id, $guestSession->fresh()->user_id);
    }
}

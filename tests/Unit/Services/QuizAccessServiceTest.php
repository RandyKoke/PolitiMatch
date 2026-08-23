<?php

namespace Tests\Unit\Services;

use App\Models\GuestSession;
use App\Models\QuizResult;
use App\Models\User;
use App\Services\QuizAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class QuizAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuizAccessService $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->access = new QuizAccessService;
    }

    public function test_grants_access_with_the_matching_session_token(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        $this->access->ensureAccess($quizResult, $guestSession->session_token);
        $this->addToAssertionCount(1); // aucune exception levée
    }

    public function test_denies_access_with_a_wrong_session_token(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        $this->expectException(AuthorizationException::class);
        $this->access->ensureAccess($quizResult, '00000000-0000-0000-0000-000000000000');
    }

    public function test_denies_access_with_a_malformed_session_token(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        $this->expectException(AuthorizationException::class);
        $this->access->ensureAccess($quizResult, 'pas-un-uuid');
    }

    public function test_grants_access_to_the_owning_authenticated_user(): void
    {
        $user = User::factory()->create();
        $quizResult = QuizResult::create(['user_id' => $user->id]);
        Auth::login($user);

        $this->access->ensureAccess($quizResult, null);
        $this->addToAssertionCount(1);
    }

    public function test_denies_access_to_a_different_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $quizResult = QuizResult::create(['user_id' => $owner->id]);
        Auth::login($other);

        $this->expectException(AuthorizationException::class);
        $this->access->ensureAccess($quizResult, null);
    }
}

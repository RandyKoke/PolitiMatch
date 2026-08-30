<?php

namespace Tests\Feature\Results;

use App\Enums\QuizResultStatus;
use App\Models\GuestSession;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/results/{uuid} est réservé au propriétaire du résultat (compte
 * connecté ou session_token exact du visiteur anonyme), depuis que
 * ResultController::show vérifie la propriété via QuizAccessService plutôt
 * que de considérer la seule possession de l'UUID comme suffisante. Le lien
 * de partage public (ShareController::show, /api/share/{token}) reste le
 * seul moyen prévu de rendre un résultat consultable par un tiers.
 */
class ResultAccessTest extends TestCase
{
    use RefreshDatabase;

    private function guestQuizResult(): QuizResult
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);

        return QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
            'profile_description' => 'Une description.',
        ]);
    }

    public function test_the_uuid_alone_is_not_enough_to_view_a_guests_result(): void
    {
        $quizResult = $this->guestQuizResult();

        $response = $this->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(403);
    }

    public function test_the_owning_guest_session_token_grants_access(): void
    {
        $quizResult = $this->guestQuizResult();

        $response = $this->getJson("/api/results/{$quizResult->uuid}?session_token={$quizResult->session_token}");

        $response->assertStatus(200);
    }

    public function test_another_guests_session_token_is_rejected(): void
    {
        $quizResult = $this->guestQuizResult();
        $otherGuestSession = GuestSession::create(['expires_at' => now()->addDay()]);

        $response = $this->getJson("/api/results/{$quizResult->uuid}?session_token={$otherGuestSession->session_token}");

        $response->assertStatus(403);
    }

    public function test_the_owning_authenticated_user_grants_access_without_a_session_token(): void
    {
        $user = User::factory()->create();
        $quizResult = QuizResult::create([
            'user_id' => $user->id,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
            'profile_description' => 'Une description.',
        ]);

        $response = $this->actingAs($user)->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(200);
    }

    public function test_a_different_authenticated_user_is_rejected(): void
    {
        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();
        $quizResult = QuizResult::create([
            'user_id' => $owner->id,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
            'profile_description' => 'Une description.',
        ]);

        $response = $this->actingAs($someoneElse)->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(403);
    }
}

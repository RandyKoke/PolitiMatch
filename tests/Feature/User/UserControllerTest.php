<?php

namespace Tests\Feature\User;

use App\Enums\QuizResultStatus;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_returns_the_authenticated_users_quiz_history_newest_first(): void
    {
        $user = User::factory()->create();
        $older = QuizResult::create([
            'user_id' => $user->id, 'status' => QuizResultStatus::Completed,
            'completed_at' => now()->subDay(), 'profile_label' => 'Ancien profil',
        ]);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = QuizResult::create([
            'user_id' => $user->id, 'status' => QuizResultStatus::Completed,
            'completed_at' => now(), 'profile_label' => 'Nouveau profil',
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/results');

        $response->assertStatus(200)->assertJsonCount(2, 'quiz_results');
        $this->assertSame($newer->uuid, $response->json('quiz_results.0.uuid'));
        $this->assertSame($older->uuid, $response->json('quiz_results.1.uuid'));
    }

    /**
     * Un quiz non terminé reste listé (avec son statut) : ce n'est pas au
     * backend de le cacher, "Refaire le quiz" a du sens même pour ces cas.
     */
    public function test_results_includes_non_completed_quizzes(): void
    {
        $user = User::factory()->create();
        QuizResult::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/user/results');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'quiz_results')
            ->assertJsonPath('quiz_results.0.status', 'pending')
            ->assertJsonPath('quiz_results.0.profile_label', null);
    }

    public function test_results_never_returns_another_users_quizzes(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        QuizResult::create(['user_id' => $stranger->id, 'status' => QuizResultStatus::Completed, 'completed_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/user/results');

        $response->assertStatus(200)->assertJsonCount(0, 'quiz_results');
    }

    public function test_results_requires_authentication(): void
    {
        $this->getJson('/api/user/results')->assertStatus(401);
    }

    public function test_update_avatar_persists_the_new_seed(): void
    {
        $user = User::factory()->create();
        $newSeed = (string) Str::uuid();

        $response = $this->actingAs($user)->patchJson('/api/user/avatar', ['avatar_seed' => $newSeed]);

        $response->assertStatus(200)->assertJsonPath('user.avatar_seed', $newSeed);
        $this->assertSame($newSeed, $user->fresh()->avatar_seed);
    }

    public function test_update_avatar_requires_authentication(): void
    {
        $response = $this->patchJson('/api/user/avatar', ['avatar_seed' => (string) Str::uuid()]);

        $response->assertStatus(401);
    }

    public function test_update_avatar_rejects_a_non_uuid_seed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/user/avatar', ['avatar_seed' => 'ceci-nest-pas-un-uuid']);

        $response->assertStatus(422);
    }

    /**
     * IDOR : le endpoint ne doit modifier que le compte authentifié
     * (Auth::user()), jamais un autre compte quel que soit le payload
     * envoyé — aucun id de ressource n'est même accepté en entrée.
     */
    public function test_update_avatar_never_changes_another_users_avatar(): void
    {
        $user = User::factory()->create(['avatar_seed' => 'seed-original-intact']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->patchJson('/api/user/avatar', ['avatar_seed' => (string) Str::uuid()]);

        $this->assertSame('seed-original-intact', $user->fresh()->avatar_seed);
    }
}

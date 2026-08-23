<?php

namespace Tests\Feature\Avatar;

use Tests\TestCase;

class AvatarControllerTest extends TestCase
{
    public function test_suggestions_returns_nine_avatars_with_distinct_seeds(): void
    {
        $response = $this->getJson('/api/avatars/suggestions');

        $response->assertStatus(200)->assertJsonCount(9, 'avatars');

        $seeds = collect($response->json('avatars'))->pluck('seed');
        $this->assertCount(9, $seeds->unique());
    }

    public function test_suggestions_returns_a_preview_url_built_from_the_seed(): void
    {
        $response = $this->getJson('/api/avatars/suggestions');

        $first = $response->json('avatars.0');
        $this->assertStringStartsWith('https://api.dicebear.com/', $first['url']);
        $this->assertStringContainsString($first['seed'], $first['url']);
    }

    /**
     * Régression directe demandée : ne renvoie pas le même jeu de
     * propositions à tout le monde ("Voir d'autres propositions" doit
     * proposer autre chose, et deux visiteurs différents ne doivent pas
     * voir la même grille).
     */
    public function test_suggestions_returns_different_seeds_on_each_call(): void
    {
        $first = collect($this->getJson('/api/avatars/suggestions')->json('avatars'))->pluck('seed');
        $second = collect($this->getJson('/api/avatars/suggestions')->json('avatars'))->pluck('seed');

        $this->assertNotEquals($first->all(), $second->all());
    }

    /**
     * throttle:30,1 (routes/api.php) : route publique, jamais un proxy
     * gratuit et illimité vers l'API DiceBear tierce.
     */
    public function test_suggestions_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/avatars/suggestions');
        }

        $response = $this->getJson('/api/avatars/suggestions');

        $response->assertStatus(429);
    }
}

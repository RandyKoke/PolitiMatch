<?php

namespace Tests\Unit\Services;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AccountMigrationService;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private SocialAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialAuthService(new AccountMigrationService);
    }

    public function test_logs_in_directly_when_social_account_already_exists(): void
    {
        $user = User::factory()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => SocialProvider::Google,
            'provider_user_id' => 'google-id-1',
            'access_token' => 'token',
        ]);

        $result = $this->service->handleCallback(
            SocialProvider::Google,
            SocialiteUser::fake(['id' => 'google-id-1', 'email' => 'autre@example.com']),
            null,
            null,
        );

        $this->assertSame('authenticated', $result['status']);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertTrue(Auth::check());
    }

    /**
     * Point de sécurité central : un email correspondant à un compte existant
     * ne doit jamais fusionner/lier automatiquement un nouveau compte social.
     */
    public function test_never_auto_merges_when_email_matches_an_existing_user(): void
    {
        User::factory()->create(['email' => 'existant@example.com']);

        $result = $this->service->handleCallback(
            SocialProvider::Google,
            SocialiteUser::fake(['id' => 'google-id-nouveau', 'email' => 'existant@example.com']),
            null,
            null,
        );

        $this->assertSame('account_exists', $result['status']);
        $this->assertNull($result['user']);
        $this->assertSame('existant@example.com', $result['email']);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertGuest();
    }

    public function test_creates_new_user_and_social_account_when_nothing_matches(): void
    {
        $result = $this->service->handleCallback(
            SocialProvider::Google,
            SocialiteUser::fake(['id' => 'google-id-2', 'email' => 'nouveau@example.com']),
            null,
            null,
        );

        $this->assertSame('authenticated', $result['status']);
        $this->assertDatabaseHas('users', ['email' => 'nouveau@example.com']);
        $this->assertDatabaseHas('social_accounts', ['provider_user_id' => 'google-id-2']);
        $this->assertNull(User::where('email', 'nouveau@example.com')->first()->password_hash);
    }

    public function test_links_social_account_to_currently_authenticated_user_when_link_intent_matches(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $result = $this->service->handleCallback(
            SocialProvider::Google,
            SocialiteUser::fake(['id' => 'google-id-3', 'email' => 'ignore@example.com']),
            $user->id,
            null,
        );

        $this->assertSame('linked', $result['status']);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'google-id-3']);
        // Aucun nouveau compte ne doit avoir été créé pour cet email.
        $this->assertDatabaseMissing('users', ['email' => 'ignore@example.com']);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_message_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'inconnu@example.com']);

        $response->assertStatus(200);
        Notification::assertNothingSent();
    }

    public function test_forgot_password_sends_notification_and_creates_token_for_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'randy@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'randy@example.com']);

        $response->assertStatus(200);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('AncienMotDePasse1')]);
        $rawToken = Str::random(64);
        PasswordResetToken::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($rawToken),
            'expires_at' => now()->addMinutes(60),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $rawToken,
            'password' => 'NouveauMotDePasse1',
            'password_confirmation' => 'NouveauMotDePasse1',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('NouveauMotDePasse1', $user->fresh()->password_hash));
        $this->assertNotNull(PasswordResetToken::first()->used_at);
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('AncienMotDePasse1')]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'jeton-invalide',
            'password' => 'NouveauMotDePasse1',
            'password_confirmation' => 'NouveauMotDePasse1',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('AncienMotDePasse1', $user->fresh()->password_hash));
    }
}

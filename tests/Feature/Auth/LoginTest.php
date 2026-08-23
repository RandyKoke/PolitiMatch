<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'randy@example.com',
            'password_hash' => Hash::make('Password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'randy@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)->assertJsonPath('user.email', 'randy@example.com');
        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password_and_unknown_email_return_the_same_generic_message(): void
    {
        User::factory()->create([
            'email' => 'randy@example.com',
            'password_hash' => Hash::make('Password123'),
        ]);

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'randy@example.com',
            'password' => 'MauvaisMotDePasse1',
        ]);

        $unknownEmail = $this->postJson('/api/auth/login', [
            'email' => 'inconnu@example.com',
            'password' => 'PeuImporte1',
        ]);

        $wrongPassword->assertStatus(401);
        $unknownEmail->assertStatus(401);
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
        $this->assertGuest();
    }
}

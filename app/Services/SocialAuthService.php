<?php

namespace App\Services;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SocialAuthService
{
    public function __construct(private readonly AccountMigrationService $migrationService) {}

    /**
     * Traite le retour du fournisseur OAuth. Trois cas, jamais de fusion
     * automatique par email (cf. cahier des charges) :
     *  1. Ce compte social est déjà lié à un User -> connexion directe.
     *  2. Intention de liaison confirmée (utilisateur déjà connecté qui a
     *     initié le flux avec ?link=1) -> on lie le compte social au User
     *     courant, sans jamais créer ni authentifier un autre compte.
     *  3. Sinon, si l'email existe déjà côté User -> on ne lie ni ne fusionne
     *     rien automatiquement, on remonte un conflit pour que le frontend
     *     invite l'utilisateur à se connecter puis à lier son compte.
     *  4. Sinon -> nouveau User (password_hash NULL) + SocialAccount créés
     *     ensemble dans une même transaction.
     *
     * @return array{status: string, user: User|null, migration: array|null, email: string|null}
     */
    public function handleCallback(
        SocialProvider $provider,
        SocialiteUser $socialiteUser,
        ?int $linkIntentUserId,
        ?string $sessionToken,
    ): array {
        $existingSocialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_user_id', $socialiteUser->getId())
            ->first();

        if ($existingSocialAccount !== null) {
            $user = $existingSocialAccount->user;
            Auth::login($user);

            return [
                'status' => 'authenticated',
                'user' => $user,
                'migration' => $this->migrationService->migrate($sessionToken, $user),
                'email' => null,
            ];
        }

        if ($linkIntentUserId !== null) {
            $userToLink = User::find($linkIntentUserId);

            if ($userToLink !== null && Auth::id() === $userToLink->id) {
                SocialAccount::create([
                    'user_id' => $userToLink->id,
                    'provider' => $provider,
                    'provider_user_id' => $socialiteUser->getId(),
                    'access_token' => $socialiteUser->token,
                ]);

                return ['status' => 'linked', 'user' => $userToLink, 'migration' => null, 'email' => null];
            }
            // Intention de liaison invalide (session expirée, utilisateur
            // déconnecté entre-temps...) : on retombe sur le parcours normal.
        }

        $existingUser = User::where('email', $socialiteUser->getEmail())->first();

        if ($existingUser !== null) {
            return [
                'status' => 'account_exists',
                'user' => null,
                'migration' => null,
                'email' => $socialiteUser->getEmail(),
            ];
        }

        $newUser = DB::transaction(function () use ($provider, $socialiteUser) {
            $user = User::create([
                'username' => $this->generateUniqueUsername($socialiteUser),
                'email' => $socialiteUser->getEmail(),
                'password_hash' => null,
                'avatar_seed' => (string) Str::uuid(),
            ]);

            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $socialiteUser->getId(),
                'access_token' => $socialiteUser->token,
            ]);

            return $user;
        });

        Auth::login($newUser);

        return [
            'status' => 'authenticated',
            'user' => $newUser,
            'migration' => $this->migrationService->migrate($sessionToken, $newUser),
            'email' => null,
        ];
    }

    /**
     * Dérive un username disponible à partir du profil social (Google ne
     * fournit ni username ni garantie d'unicité) — base slugifiée + suffixe
     * numérique si collision.
     */
    private function generateUniqueUsername(SocialiteUser $socialiteUser): string
    {
        $base = Str::slug(Str::before($socialiteUser->getEmail() ?? $socialiteUser->getName() ?? 'user', '@'));
        $base = $base !== '' ? $base : 'user';
        $username = $base;
        $suffix = 0;

        while (User::where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}{$suffix}";
        }

        return $username;
    }
}

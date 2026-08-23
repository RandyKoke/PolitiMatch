<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AccountMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly AccountMigrationService $migrationService) {}

    /**
     * Le session_token du Guest Flow voyage en en-tête (et non en champ du
     * corps de la requête) : ces routes sont des appels JSON/fetch depuis la
     * SPA, où un en-tête dédié se met en place une fois pour toutes côté
     * client (intercepteur axios) sans polluer chaque payload de requête.
     * Pour le flux OAuth (redirection plein-page), voir SocialAuthController,
     * qui utilise un mécanisme différent car un en-tête n'y est pas possible.
     */
    private const SESSION_TOKEN_HEADER = 'X-Session-Token';

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username' => $request->string('username'),
            'email' => $request->string('email'),
            'password_hash' => Hash::make($request->string('password')),
            'role' => UserRole::User,
            // Seed choisi via la grille de propositions (RegisterView) si
            // fourni, sinon repli aléatoire — l'avatar reste toujours
            // secondaire par rapport à la création du compte elle-même.
            'avatar_seed' => $request->filled('avatar_seed') ? $request->string('avatar_seed') : (string) Str::uuid(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $migration = $this->migrationService->migrate(
            $request->header(self::SESSION_TOKEN_HEADER),
            $user,
        );

        return response()->json([
            'account_created' => true,
            'user' => $user,
            'migrated' => $migration['migrated'],
            'reason' => $migration['reason'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        // Message générique dans les deux cas (email inconnu ou mot de passe
        // incorrect) : ne jamais laisser un attaquant déduire quels emails
        // sont enregistrés.
        if ($user === null || $user->password_hash === null || ! Hash::check($request->string('password'), $user->password_hash)) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
            ], 401);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $migration = $this->migrationService->migrate(
            $request->header(self::SESSION_TOKEN_HEADER),
            $user,
        );

        return response()->json([
            'user' => $user,
            'migrated' => $migration['migrated'],
            'reason' => $migration['reason'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}

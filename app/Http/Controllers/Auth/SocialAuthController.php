<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * Route où la SPA retombe après l'aller-retour OAuth (succès ou échec) :
     * c'est une navigation plein-page côté fournisseur, donc contrairement
     * aux routes /api/auth/*, le résultat ne peut pas être renvoyé en JSON
     * direct — il est encodé en query string et lu côté client par Vue Router.
     */
    private const FRONTEND_CALLBACK_PATH = '/oauth/callback';

    public function __construct(private readonly SocialAuthService $socialAuthService) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $providerEnum = $this->resolveConfiguredProvider($provider);

        // Le session_token du Guest Flow et l'intention de liaison de compte
        // ne peuvent pas voyager en en-tête HTTP ici (redirection plein-page,
        // pas un fetch/XHR) : on les stocke côté serveur dans la session
        // Laravel, le temps de l'aller-retour chez le fournisseur OAuth.
        if ($request->filled('session_token')) {
            $request->session()->put('social_guest_session_token', $request->string('session_token'));
        }

        if ($request->boolean('link') && Auth::check()) {
            $request->session()->put('social_link_user_id', Auth::id());
        } else {
            $request->session()->forget('social_link_user_id');
        }

        return Socialite::driver($providerEnum->value)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $providerEnum = $this->resolveConfiguredProvider($provider);

        try {
            $socialiteUser = Socialite::driver($providerEnum->value)->user();
        } catch (Throwable $e) {
            report($e);

            return $this->redirectToFrontend(['status' => 'error']);
        }

        $linkIntentUserId = $request->session()->pull('social_link_user_id');
        $sessionToken = $request->session()->pull('social_guest_session_token');

        $result = $this->socialAuthService->handleCallback(
            $providerEnum,
            $socialiteUser,
            $linkIntentUserId,
            $sessionToken,
        );

        return match ($result['status']) {
            'account_exists' => $this->redirectToFrontend([
                'status' => 'account_exists',
                'email' => $result['email'],
                'provider' => $providerEnum->value,
            ]),
            'linked' => $this->redirectToFrontend(['status' => 'linked']),
            'authenticated' => $this->redirectToFrontend([
                'status' => 'authenticated',
                'migrated' => $result['migration']['migrated'] ? '1' : '0',
                'migration_reason' => $result['migration']['reason'] ?? '',
            ]),
        };
    }

    /**
     * @param  array<string, string>  $params
     */
    private function redirectToFrontend(array $params): RedirectResponse
    {
        return redirect(self::FRONTEND_CALLBACK_PATH.'?'.http_build_query($params));
    }

    /**
     * Nettoyage routes OAuth orphelines : routes/web.php n'enregistre plus
     * aucune contrainte whereIn sur {provider} (deux routes littéralement
     * identiques avec des contraintes différentes s'écrasent l'une l'autre
     * dans RouteCollection, cf. commentaire de routes/web.php) — c'est donc
     * ici, au même endroit pour redirect() et callback(), que se décide si
     * un provider est acceptable : un enum valide ET réellement configuré
     * (config/services.php, client_id renseigné). Un provider inconnu de
     * l'enum (SocialProvider::tryFrom() renvoie null) ou connu de l'enum
     * mais jamais configuré (ex. facebook, github) reçoit exactement le même
     * 404 propre, standard, sans aucune trace technique.
     */
    private function resolveConfiguredProvider(string $provider): SocialProvider
    {
        $providerEnum = SocialProvider::tryFrom($provider);

        abort_if($providerEnum === null || blank(config("services.{$providerEnum->value}.client_id")), 404);

        return $providerEnum;
    }
}

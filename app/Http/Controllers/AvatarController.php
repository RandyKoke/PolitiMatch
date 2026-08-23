<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AvatarController extends Controller
{
    /**
     * Nombre de propositions affichées dans la grille de choix (cf.
     * RegisterView / AvatarPicker) : assez pour un vrai choix, assez peu pour
     * rester une "grille compacte" sans étape de défilement.
     */
    private const SUGGESTIONS_COUNT = 9;

    /**
     * Style DiceBear utilisé pour tout avatar de l'app (ici et dans
     * AuthController::register / SocialAuthService, qui ne construisent que
     * le seed, l'URL de rendu étant toujours construite avec CE style).
     * Choisi pour sa diversité réelle de représentation (7 teintes de peau,
     * 33 coiffures couvrant plusieurs textures) plutôt que par défaut.
     */
    private const DICEBEAR_STYLE = 'avataaars';

    /**
     * Propositions d'avatars pour l'étape de choix (inscription ou
     * changement depuis le tableau de bord). Public et sans état : aucune
     * donnée personnelle en jeu, chaque seed est un UUID aléatoire sans lien
     * avec un compte tant qu'il n'est pas explicitement choisi et envoyé à
     * /auth/register ou /user/avatar. Throttle sur la route (cf. api.php)
     * plutôt qu'ici : cohérent avec le reste des routes publiques du projet.
     */
    public function suggestions(): JsonResponse
    {
        $avatars = collect(range(1, self::SUGGESTIONS_COUNT))
            ->map(fn () => self::buildAvatar((string) Str::uuid()))
            ->values();

        return response()->json(['avatars' => $avatars]);
    }

    /**
     * @return array{seed: string, url: string}
     */
    public static function buildAvatar(string $seed): array
    {
        return [
            'seed' => $seed,
            'url' => sprintf('https://api.dicebear.com/9.x/%s/svg?seed=%s', self::DICEBEAR_STYLE, urlencode($seed)),
        ];
    }
}

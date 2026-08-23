<?php

namespace App\Http\Controllers;

use App\Enums\QuizResultStatus;
use App\Http\Requests\Quiz\ShareResultRequest;
use App\Models\QuizResult;
use App\Services\OgImageService;
use App\Services\QuizAccessService;
use App\Services\QuizReliabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    public function __construct(
        private readonly QuizAccessService $access,
        private readonly OgImageService $ogImages,
        private readonly QuizReliabilityService $reliability,
    ) {}

    /**
     * Active le partage d'un résultat et renvoie son share_token — distinct
     * de l'UUID interne du QuizResult (cf. cahier des charges, Module
     * Partage) : un identifiant séparé permet à son propriétaire de
     * partager un lien sans jamais exposer directement quiz_result.uuid, et
     * ouvre la voie à une désactivation du partage plus tard sans toucher à
     * l'accès privé habituel (/results/{uuid}).
     *
     * Idempotent : un appel répété sur un résultat déjà partagé renvoie le
     * même token plutôt que d'en générer un nouveau — un lien déjà distribué
     * ne doit jamais se retrouver cassé par un second clic sur "Partager".
     *
     * Délibérément AUCUNE vérification de fiabilité ici (à la différence de
     * show() ci-dessous). Le bouton "Partager" est déjà absent
     * côté frontend pour un résultat en état bloquant (ResultsView.vue),
     * donc cette route n'est jamais atteinte via l'UI normale dans ce cas ;
     * et même si elle l'était, show()/showPage() refusent quoi qu'il arrive
     * d'exposer un contenu bloqué à quiconque consulterait ensuite le lien —
     * la garde utile est côté LECTURE, pas côté activation, cf. commentaire
     * de show().
     */
    public function create(ShareResultRequest $request, QuizResult $quizResult): JsonResponse
    {
        $data = $request->validated();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        if ($quizResult->status !== QuizResultStatus::Completed) {
            return response()->json([
                'message' => "Ce résultat n'est pas encore disponible, impossible de le partager.",
            ], 409);
        }

        if ($quizResult->share_token === null) {
            $quizResult->share_token = (string) Str::uuid();
        }
        $quizResult->is_shared = true;
        $quizResult->save();

        // Génération au moment du partage, pas au premier accès du lien (cf.
        // OgImageService pour la justification complète). Idempotente
        // (ensureGenerated ne régénère pas si le fichier existe déjà) et
        // jamais bloquante : une image personnalisée manquante retombe sur
        // l'image générique au moment de l'affichage (showPage ci-dessous),
        // jamais une erreur ici.
        $this->ogImages->ensureGenerated($quizResult->load('resultPartyScores.party'));

        return response()->json(['share_token' => $quizResult->share_token]);
    }

    /**
     * Entièrement public, à dessein : quiconque reçoit un lien de partage
     * doit pouvoir le consulter sans compte ni session_token — ce n'est PAS
     * QuizAccessService (réservé au propriétaire du quiz) qui protège cette
     * route, uniquement la possession du share_token lui-même, et le fait
     * que son propriétaire ait explicitement activé le partage
     * (is_shared = true, révocable en théorie en repassant ce champ à false,
     * même si aucune route de révocation n'est exposée pour l'instant).
     *
     * toResultPayload() applique ici EXACTEMENT la même garde de fiabilité
     * que ResultController::show : un lien déjà distribué pointant vers un
     * résultat désormais qualifié de bloquant affiche donc le message
     * explicatif plutôt que les anciens boutons de partage actifs, sans
     * qu'aucune donnée n'ait besoin d'être migrée (le calcul est fait à la
     * volée, jamais stocké).
     */
    public function show(string $token): JsonResponse
    {
        $quizResult = QuizResult::withFullQuizData()
            ->where('share_token', $token)
            ->where('is_shared', true)
            ->firstOrFail();

        return response()->json($quizResult->toResultPayload($this->reliability));
    }

    /**
     * Coquille HTML de cette page précise (pas la coquille générique du
     * joker SPA, routes/web.php) avec de vraies balises Open Graph/Twitter
     * Card, calculées côté serveur. Nécessaire car les robots des réseaux
     * sociaux (Facebook, X, WhatsApp...) n'exécutent jamais le JavaScript de
     * la SPA pour générer un aperçu de lien : une injection dynamique côté
     * client (ex. @vueuse/head) serait invisible pour eux, contrairement à
     * un utilisateur humain. La SPA continue de démarrer normalement en
     * dessous (mêmes @vite/@fonts qu'app.blade.php) : ce n'est qu'une
     * coquille HTML différente pour cette seule route, pas un changement du
     * rendu réellement affiché à un visiteur humain.
     *
     * Jamais de 404 ici, même pour un token invalide/non partagé : ce
     * serait une confirmation/infirmation de l'existence du résultat visible
     * dans le code de statut HTTP lui-même, avant même que la vérification
     * dédiée (ShareController::show, is_shared already enforced) n'entre en
     * jeu. La coquille générique (mêmes balises que app.blade.php) s'affiche
     * dans ce cas, et c'est la SPA elle-même (ShareView.vue) qui gère
     * l'état "introuvable" une fois chargée, exactement comme aujourd'hui.
     *
     * og:image : personnalisée par résultat SEULEMENT pour un résultat
     * réellement partagé (même garde is_shared que le
     * reste de cette méthode) — jamais pour un token inconnu/non partagé,
     * qui recevrait alors l'image d'un résultat privé et en révélerait
     * indirectement l'existence/le contenu. ensureGenerated() sert aussi de
     * filet de sécurité auto-réparateur ici : si l'image générée au moment
     * du partage (ShareController::create) a disparu depuis (ex.
     * redéploiement sans volume persistant), elle est régénérée à la volée ;
     * si la génération échoue pour une raison quelconque, `null` est renvoyé
     * et l'image générique reste utilisée — jamais de page cassée.
     */
    public function showPage(string $token): View
    {
        $quizResult = QuizResult::with('resultPartyScores.party')
            ->where('share_token', $token)
            ->where('is_shared', true)
            ->first();

        return view('share', [
            'ogTitle' => $quizResult !== null
                ? "Mon profil politique sur PolitiMatch : {$quizResult->profile_label}"
                : config('app.name', 'PolitiMatch'),
            'ogDescription' => $quizResult?->profile_description
                ?? "PolitiMatch aide les jeunes Belges francophones à comprendre leurs valeurs politiques et à trouver les partis qui leur correspondent, simplement et sans jargon.",
            'ogImage' => ($quizResult !== null ? $this->ogImages->ensureGenerated($quizResult) : null) ?? asset('og-image.png'),
            'ogUrl' => url()->current(),
        ]);
    }
}

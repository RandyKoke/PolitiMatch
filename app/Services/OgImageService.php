<?php

namespace App\Services;

use App\Models\QuizResult;
use GdImage;
use Throwable;

/**
 * Image de partage personnalisée par résultat (Open Graph/Twitter Card),
 * générée en PHP pur (extension GD, déjà présente dans tout environnement
 * PHP standard), jamais via un navigateur headless (Playwright/Puppeteer)
 * comme pour l'image générique : cette dernière approche demanderait un
 * runtime Node + Chromium disponible en production (Railway, déploiement
 * mono-service PHP), une dépendance lourde que ce service évite complètement.
 *
 * Génération EAGER (au moment où le partage est activé,
 * ShareController::create), pas lazy au premier accès : plus simple à
 * raisonner (un seul déclencheur, pas de logique de cache à invalider), et
 * plus performant à l'usage (l'image est déjà prête avant qu'aucun robot de
 * réseau social n'ait pu la demander — plusieurs robots différents
 * peuvent réclamer la même image dans les secondes qui suivent un partage,
 * générer à la demande risquerait des générations concurrentes redondantes).
 * `ensureGenerated()` reste malgré tout appelée aussi depuis
 * ShareController::showPage() : filet de sécurité auto-réparateur si le
 * fichier a disparu (ex. redéploiement sans volume persistant sur
 * Railway) sans que la base de données n'ait besoin d'un champ dédié — le
 * chemin se déduit uniquement de share_token, toujours stable.
 */
class OgImageService
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const DIRECTORY = 'og-images';

    // Répertoire et clé DISTINCTS de ceux de l'image Open Graph ci-dessus,
    // à dessein. Le fichier og-images/{share_token}.png n'existe QUE si le
    // partage a été activé (relativePath() renvoie null sinon) — or le
    // bouton "Télécharger mon résultat" doit fonctionner dès l'écran de
    // résultat, sans jamais forcer indirectement l'activation du partage
    // public juste pour permettre un téléchargement privé. Indexé sur
    // quiz_result.uuid (déjà l'identifiant d'accès privé au résultat,
    // cf. ResultController::show), jamais share_token.
    private const DOWNLOAD_DIRECTORY = 'result-images';

    // Mêmes teintes que --color-ink/--color-cream/--color-brand-600/
    // --color-bordeaux-600 (resources/css/app.css) : jamais une nouvelle
    // palette.
    private const COLOR_CREAM = [250, 249, 246];

    private const COLOR_INK = [26, 26, 26];

    private const COLOR_GOLD = [212, 160, 23];

    private const COLOR_BORDEAUX = [98, 39, 59];

    /**
     * Chemin public (relatif à public/) de l'image pour ce résultat —
     * toujours dérivé de share_token, jamais stocké séparément en base :
     * un seul champ à tenir cohérent, jamais de désynchronisation possible.
     */
    public function relativePath(QuizResult $quizResult): ?string
    {
        if ($quizResult->share_token === null) {
            return null;
        }

        return self::DIRECTORY.'/'.$quizResult->share_token.'.png';
    }

    /**
     * Génère l'image si elle n'existe pas déjà (idempotent, comme
     * share_token lui-même) et renvoie son URL publique absolue. Ne lève
     * jamais d'exception : toute erreur (police manquante, échec d'écriture
     * disque...) est journalisée puis dégradée en `null`, à charge de
     * l'appelant de retomber sur l'image générique.
     */
    public function ensureGenerated(QuizResult $quizResult): ?string
    {
        $relativePath = $this->relativePath($quizResult);
        if ($relativePath === null) {
            return null;
        }

        $absolutePath = public_path($relativePath);

        if (! is_file($absolutePath)) {
            try {
                $this->render($quizResult, $absolutePath);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }

        return is_file($absolutePath) ? asset($relativePath) : null;
    }

    /**
     * Pendant privé du couple relativePath()/ensureGenerated() ci-dessus,
     * pour la carte de résultat téléchargeable : même moteur de rendu
     * (render() ci-dessous, partagé sans duplication), mais jamais
     * conditionné à un partage actif, et jamais exposé via asset() (chemin
     * absolu disque uniquement, le contrôleur appelant renvoie le fichier
     * lui-même avec un Content-Disposition: attachment).
     */
    public function ensureDownloadImageGenerated(QuizResult $quizResult): ?string
    {
        $absolutePath = public_path(self::DOWNLOAD_DIRECTORY.'/'.$quizResult->uuid.'.png');

        if (! is_file($absolutePath)) {
            try {
                $this->render($quizResult, $absolutePath);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }

        return is_file($absolutePath) ? $absolutePath : null;
    }

    private function render(QuizResult $quizResult, string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fontDisplay = resource_path('fonts/Fraunces-Variable.ttf');
        $fontSans = resource_path('fonts/Manrope-Variable.ttf');

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $cream = $this->allocate($image, self::COLOR_CREAM);
        $ink = $this->allocate($image, self::COLOR_INK);
        $gold = $this->allocate($image, self::COLOR_GOLD);
        $bordeaux = $this->allocate($image, self::COLOR_BORDEAUX);

        imagefill($image, 0, 0, $cream);

        // Liseré tricolore (même ordre noir → or → bordeaux que partout
        // ailleurs dans l'app, cf. --gradient-tricolore).
        $stripeHeight = 16;
        imagefilledrectangle($image, 0, 0, (int) (self::WIDTH / 3), $stripeHeight, $ink);
        imagefilledrectangle($image, (int) (self::WIDTH / 3), 0, (int) (2 * self::WIDTH / 3), $stripeHeight, $gold);
        imagefilledrectangle($image, (int) (2 * self::WIDTH / 3), 0, self::WIDTH, $stripeHeight, $bordeaux);

        // Logo (chip noir + "P" or) + nom de l'app, même déclinaison que le
        // header de l'application (TopBar.vue).
        $chipX = 90;
        $chipY = 70;
        $chipSize = 90;
        imagefilledrectangle($image, $chipX, $chipY, $chipX + $chipSize, $chipY + $chipSize, $ink);
        $this->centeredTextInBox($image, $fontDisplay, 40, $gold, 'P', $chipX, $chipX + $chipSize, $chipY + (int) ($chipSize * 0.68));
        $this->text($image, $fontDisplay, 30, $ink, $chipX + $chipSize + 24, $chipY + (int) ($chipSize * 0.62), 'PolitiMatch');

        // Libellé du profil : pièce centrale, en grand, avec retour à la
        // ligne simple si nécessaire (aucune garantie de longueur du texte
        // rédigé par ProfileLabelService).
        $lines = $this->wrapText($fontDisplay, 64, $quizResult->profile_label ?? '', self::WIDTH - 200);
        $lineHeight = 78;
        $blockHeight = count($lines) * $lineHeight;
        $startY = (int) ((self::HEIGHT - $blockHeight) / 2) + 60;
        foreach ($lines as $i => $line) {
            $this->centeredTextInBox($image, $fontDisplay, 64, $ink, $line, 0, self::WIDTH, $startY + $i * $lineHeight);
        }

        // Parti le plus compatible, seulement si un score exploitable existe
        // (jamais "Données insuffisantes" affiché ici — cf. ResultsView, la
        // ligne est simplement omise plutôt que d'afficher un texte creux).
        $topParty = $quizResult->resultPartyScores
            ->sortBy('rank')
            ->first(fn ($score) => $score->compatibility_score !== null);
        if ($topParty !== null) {
            $partyLine = 'Le plus compatible : '.$topParty->party->name;
            $this->centeredTextInBox($image, $fontSans, 30, $bordeaux, $partyLine, 0, self::WIDTH, $startY + count($lines) * $lineHeight + 20);
        }

        imagepng($image, $absolutePath);
    }

    /**
     * @return array<int, string>
     */
    private function wrapText(string $font, int $size, string $text, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate);
            $width = $box[2] - $box[0];

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function text(GdImage $image, string $font, int $size, int $color, int $x, int $y, string $text): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    private function centeredTextInBox(GdImage $image, string $font, int $size, int $color, string $text, int $boxLeft, int $boxRight, int $y): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = $box[2] - $box[0];
        $x = $boxLeft + (int) ((($boxRight - $boxLeft) - $textWidth) / 2);
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function allocate(GdImage $image, array $rgb): int
    {
        return imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    }
}

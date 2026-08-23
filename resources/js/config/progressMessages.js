/**
 * Grille de paliers pour les messages motivationnels affichés pendant le
 * quiz (QuizView), indexée sur le NUMÉRO DE QUESTION AFFICHÉE (1-based,
 * c'est-à-dire `currentQuestionIndex + 1` dans quizStore) — jamais sur un
 * pourcentage de réponses données. Ces deux valeurs peuvent diverger (ex.
 * juste après avoir répondu à la question 20, `answeredCount` vaut déjà 20
 * alors que la question 21 est affichée) : c'est exactement ce qui causait
 * le bug signalé — le message "mi-chemin" restait affiché après la
 * question 20, calculé sur un pourcentage de réponses et non sur la
 * question réellement visible à l'écran.
 *
 * CALIBRÉE PRÉCISÉMENT POUR UN QUIZ DE `CALIBRATED_TOTAL_QUESTIONS`
 * QUESTIONS (30 actuellement, cf. QuestionSeeder). Les bornes ci-dessous
 * sont volontairement des numéros de question absolus, pas des pourcentages
 * recalculés dynamiquement à partir du total réel : les pourcentages que
 * représente cette grille (≈16,7 % / 46,7 % / 66,7 % / 76,7 %...) ne sont
 * pas des fractions rondes choisies au départ, elles découlent simplement
 * du découpage en numéros de question ci-dessous. Les recalculer pour un
 * total différent produirait des bornes arbitraires sans rapport avec
 * l'intention réelle de cette grille (ex. "mi-parcours" doit rester autour
 * de la question 15-20 d'un quiz qui *reste* un quiz d'une trentaine de
 * questions, pas glisser silencieusement vers un pourcentage abstrait).
 * Si le nombre total de questions change un jour, cette grille doit être
 * recalibrée à la main — `resolveProgressMessage()` avertit en
 * développement si le total réel diverge de `CALIBRATED_TOTAL_QUESTIONS`,
 * pour que cette dérive ne passe jamais inaperçue.
 */
export const CALIBRATED_TOTAL_QUESTIONS = 30;

// `to: Infinity` sur le dernier palier : filet de sécurité pour ne jamais
// laisser de trou après la dernière question si le total venait à changer
// sans que cette grille soit mise à jour (cf. avertissement ci-dessus) — le
// dernier message reste toujours atteint, jamais de "aucun message" par
// défaut en fin de quiz.
export const PROGRESS_MESSAGE_TIERS = [
    { from: 1, to: 5, message: null },
    { from: 6, to: 14, message: 'Tu avances bien, continue !' },
    { from: 15, to: 20, message: 'Tu es à mi-chemin, continue comme ça !' },
    { from: 21, to: 23, message: 'Plus que quelques questions...' },
    { from: 24, to: Infinity, message: 'Presque fini, encore un petit effort !' },
];

// Palier de complétion : pour ne jamais rester bloqué sur "Presque fini..."
// une fois le quiz effectivement terminé. Volontairement PAS un 6e
// enregistrement `{ from: 30, to: Infinity, ... }` dans la grille ci-dessus,
// et volontairement PAS déduit de `questionNumber === totalQuestions` non
// plus : `displayedQuestionNumber` (cf. QuizView) plafonne déjà à
// `totalQuestions` pendant qu'on RÉPOND à la toute dernière question, pas
// seulement une fois qu'elle est validée — les deux moments sont donc
// numériquement indiscernables (Q30/30 dans les deux cas), alors qu'ils
// doivent afficher des messages différents ("Presque fini" en train de
// répondre, complétion une fois validée). Le seul signal correct est un
// booléen explicite fourni par l'appelant (`quizStore.isComplete` côté
// QuizView), jamais reconstruit à partir d'une coïncidence de numéros.
export const COMPLETION_MESSAGE = 'Et voilà, c\'est fait !';

/**
 * Nombre de "Passer" cliqués d'affilée (quizStore.consecutiveSkips) à partir
 * duquel afficher un encouragement dédié à répondre sincèrement — jamais
 * culpabilisant, jamais bloquant (cf. QuizView : le bouton "Passer" reste
 * pleinement utilisable après ce seuil, ce n'est qu'une incitation).
 */
export const SKIP_ENCOURAGEMENT_THRESHOLD = 5;

export const SKIP_ENCOURAGEMENT_MESSAGE = 'Plus tu réponds sincèrement, plus ton résultat te ressemblera. '
    + 'Tu restes bien sûr libre de passer si tu préfères.';

/**
 * @param {number} questionNumber Numéro 1-based de la question actuellement
 *   affichée (`currentQuestionIndex + 1`, borné au nombre réel de questions
 *   — cf. QuizView, même valeur que celle affichée sur la barre "Question
 *   N / total").
 * @param {number} totalQuestions Nombre total de questions du quiz en cours.
 * @param {boolean} [isComplete] Vrai une fois que TOUTES les questions ont
 *   effectivement été répondues (`quizStore.isComplete`) — distinct du fait
 *   d'afficher simplement la dernière question, cf. commentaire ci-dessus.
 * @returns {string|null} Le message à afficher, ou `null` si aucun (palier
 *   "Démarrage").
 */
export function resolveProgressMessage(questionNumber, totalQuestions, isComplete = false) {
    if (import.meta.env.DEV && totalQuestions > 0 && totalQuestions !== CALIBRATED_TOTAL_QUESTIONS) {
        // eslint-disable-next-line no-console
        console.warn(
            `progressMessages: grille calibrée pour ${CALIBRATED_TOTAL_QUESTIONS} questions, `
            + `mais ce quiz en compte ${totalQuestions} — les paliers ne correspondent plus aux `
            + 'bonnes proportions du parcours. Voir resources/js/config/progressMessages.js.',
        );
    }

    if (isComplete) {
        return COMPLETION_MESSAGE;
    }

    const tier = PROGRESS_MESSAGE_TIERS.find((t) => questionNumber >= t.from && questionNumber <= t.to);

    return tier ? tier.message : null;
}

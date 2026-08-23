import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import QuizView from '@/views/QuizView.vue';
import { apiQuiz } from '@/services/apiQuiz';
import { useAuthStore } from '@/stores/authStore';
import { SKIP_ENCOURAGEMENT_MESSAGE } from '@/config/progressMessages';

vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
    onBeforeRouteLeave: vi.fn(),
}));

vi.mock('@/services/apiQuiz', () => ({
    apiQuiz: {
        state: vi.fn(),
        submitAnswer: vi.fn(),
    },
}));

const QUESTION = { id: 42, label: 'Une question test.', weight: 2, theme: { name: 'Économie' } };

function buildQuestions(count) {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        label: `Question ${i + 1}`,
        weight: 1,
        theme: { name: 'Économie' },
    }));
}

function buildAnswers(count) {
    return Array.from({ length: count }, (_, i) => ({
        question_id: i + 1,
        user_score: 0,
        was_skipped: false,
    }));
}

async function clickSkip(wrapper) {
    const skipButton = wrapper.findAll('button').find((b) => b.text() === 'Passer');
    await skipButton.trigger('click');
    await flushPromises();
}

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
    // resumeState() a besoin d'un quiz en cours pour ne pas rediriger vers
    // /start avant même le montage — cf. QuizView::initialize().
    localStorage.setItem('politimatch.quiz_result_uuid', 'quiz-uuid');
    apiQuiz.state.mockResolvedValue({
        data: {
            quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
            answers: [],
            questions: [QUESTION],
        },
    });
});

/**
 * Régression du passage de 3 à 5 niveaux de réponse (échelle de Likert
 * complète -2..+2, conforme au document expert et à ScoreCalculator/
 * PoliticalAxisCalculator, qui exploitent déjà nativement les 5 valeurs) —
 * l'ancienne échelle à 3 boutons (-2/0/+2) ne permettait pas d'exprimer les
 * nuances -1/+1, silencieusement absentes de toute réponse utilisateur
 * possible malgré leur usage réel par le contenu de l'expert.
 */
describe('QuizView answer scale', () => {
    it('renders exactly 5 graduated answer buttons, distinct from "Passer"', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        const expectedLabels = [
            "Pas du tout d'accord",
            "Pas d'accord",
            'Neutre',
            "D'accord",
            "Tout à fait d'accord",
        ];
        for (const label of expectedLabels) {
            expect(wrapper.text()).toContain(label);
        }
        expect(wrapper.text()).toContain('Passer');
    });

    it.each([
        ["Pas du tout d'accord", -2],
        ["Pas d'accord", -1],
        ['Neutre', 0],
        ["D'accord", 1],
        ["Tout à fait d'accord", 2],
    ])('clicking "%s" submits user_score = %i', async (label, expectedScore) => {
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });
        const wrapper = mount(QuizView);
        await flushPromises();

        const button = wrapper.findAll('button').find((b) => b.text() === label);
        await button.trigger('click');
        await flushPromises();

        expect(apiQuiz.submitAnswer).toHaveBeenCalledWith(expect.objectContaining({
            question_id: QUESTION.id,
            user_score: expectedScore,
            was_skipped: false,
        }));
    });

    /**
     * "Passer" n'est pas une 6e position sur l'échelle : was_skipped=true,
     * pas un user_score distinct des 5 niveaux ci-dessus.
     */
    it('clicking "Passer" submits was_skipped = true, not a 6th scale position', async () => {
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });
        const wrapper = mount(QuizView);
        await flushPromises();

        const skipButton = wrapper.findAll('button').find((b) => b.text() === 'Passer');
        await skipButton.trigger('click');
        await flushPromises();

        expect(apiQuiz.submitAnswer).toHaveBeenCalledWith(expect.objectContaining({
            question_id: QUESTION.id,
            was_skipped: true,
        }));
    });
});

/**
 * Le message motivationnel doit rester aligné sur la question réellement
 * affichée (`currentQuestionIndex`), jamais sur `progress`
 * (answeredCount / total), qui peut en diverger : juste après avoir répondu
 * à la question 20, une fois la question 21 à l'écran, le message ne doit
 * plus être celui de "mi-chemin". `resolveProgressMessage()`
 * (config/progressMessages.js) se base explicitement sur la question
 * affichée. Ce test vérifie l'intégration
 * réelle dans QuizView, pas seulement la fonction pure isolée (déjà
 * couverte exhaustivement par config/__tests__/progressMessages.test.js).
 */
describe('QuizView : message motivationnel aligné sur la question affichée', () => {
    it('affiche "Plus que quelques questions..." (plus "mi-chemin") juste après avoir répondu à Q20, une fois Q21 affichée', async () => {
        const questions = Array.from({ length: 30 }, (_, i) => ({
            id: i + 1,
            label: `Question ${i + 1}`,
            weight: 1,
            theme: { name: 'Économie' },
        }));
        const answers = Array.from({ length: 20 }, (_, i) => ({
            question_id: i + 1,
            user_score: 0,
            was_skipped: false,
        }));

        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers,
                questions,
            },
        });

        const wrapper = mount(QuizView);
        await flushPromises();

        expect(wrapper.text()).toContain('Question 21 / 30');
        expect(wrapper.text()).toContain('Plus que quelques questions...');
        expect(wrapper.text()).not.toContain('mi-chemin');
    });

    /**
     * Régression du second défaut signalé : "Presque fini, encore un petit
     * effort !" restait affiché même une fois les 30 questions répondues
     * (carte "Toutes les questions ont une réponse" déjà affichée) —
     * incohérent, le quiz est terminé à ce moment-là. Le palier de
     * complétion (`quizStore.isComplete`, pas une coïncidence de numéro de
     * question) doit prendre le relais.
     */
    it('affiche le message de complétion (pas "Presque fini...") une fois les 30 questions effectivement répondues', async () => {
        const questions = Array.from({ length: 30 }, (_, i) => ({
            id: i + 1,
            label: `Question ${i + 1}`,
            weight: 1,
            theme: { name: 'Économie' },
        }));
        const answers = Array.from({ length: 30 }, (_, i) => ({
            question_id: i + 1,
            user_score: 0,
            was_skipped: false,
        }));

        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers,
                questions,
            },
        });

        const wrapper = mount(QuizView);
        await flushPromises();

        // "Voir mes résultats" (bouton stable même si le texte descriptif de
        // cette carte change) : sert ici uniquement à confirmer que la carte
        // de fin est bien affichée, sans coupler ce test au texte descriptif
        // exact.
        expect(wrapper.text()).toContain('Voir mes résultats');
        expect(wrapper.text()).toContain('Et voilà, c\'est fait !');
        expect(wrapper.text()).not.toContain('Presque fini');
    });
});

/**
 * Cliquer systématiquement sur "Passer" ne doit jamais afficher les messages
 * motivationnels habituels ("Tu avances bien", "Presque fini"...), destinés
 * à récompenser une vraie progression : quizStore.consecutiveSkips suspend
 * leur affichage tant que la dernière action est un "Passer", sans changer
 * la grille de paliers (progressMessages.js) ni son calibrage sur la
 * question affichée.
 */
describe('QuizView : le message motivationnel ne se déclenche plus sur "Passer"', () => {
    it('masque le message motivationnel habituel immédiatement après un "Passer", même sans changer de palier', async () => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                // Q1-6 répondues normalement : reprise sur Q7, palier "Lancé".
                answers: buildAnswers(6),
                questions: buildQuestions(30),
            },
        });
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });

        const wrapper = mount(QuizView);
        await flushPromises();

        expect(wrapper.text()).toContain('Tu avances bien, continue !');

        await clickSkip(wrapper);

        expect(wrapper.text()).not.toContain('Tu avances bien, continue !');
    });

    it('le message motivationnel reste masqué tant que la série de "Passer" continue, même en changeant de palier', async () => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                // Reprise sur Q14 (encore "Lancé"), deux "Passer" mènent à Q16
                // (palier "mi-chemin") : le message doit rester absent malgré
                // le changement de palier, la dernière action reste "Passer".
                answers: buildAnswers(13),
                questions: buildQuestions(30),
            },
        });
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });

        const wrapper = mount(QuizView);
        await flushPromises();

        await clickSkip(wrapper);
        await clickSkip(wrapper);

        expect(wrapper.text()).not.toContain('mi-chemin');
        expect(wrapper.text()).not.toContain('Tu avances bien');
    });

    it('le message de complétion reste affiché même si la toute dernière question a été passée', async () => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers: buildAnswers(29),
                questions: buildQuestions(30),
            },
        });
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });

        const wrapper = mount(QuizView);
        await flushPromises();

        await clickSkip(wrapper);

        expect(wrapper.text()).toContain('Et voilà, c\'est fait !');
    });
});

/**
 * Nouveau message d'encouragement dédié (déclencheur propre, distinct de la
 * grille de paliers) : jamais avant le seuil, exactement au seuil, jamais en
 * boucle après un retour à un comportement d'engagement normal. Vérifié en
 * mode invité ET en mode connecté (dernier describe ci-dessous) : la logique
 * vit entièrement dans quizStore.consecutiveSkips, indépendante de
 * authStore, donc identique dans les deux cas.
 */
describe('QuizView : message d\'encouragement après plusieurs "Passer" consécutifs', () => {
    beforeEach(() => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers: [],
                questions: buildQuestions(30),
            },
        });
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });
    });

    it('n\'affiche rien avant le 5e "Passer" consécutif', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        for (let i = 0; i < 4; i++) {
            await clickSkip(wrapper);
            expect(wrapper.text()).not.toContain(SKIP_ENCOURAGEMENT_MESSAGE);
        }
    });

    it('affiche le message exactement au 5e "Passer" consécutif, une seule fois', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        for (let i = 0; i < 5; i++) {
            await clickSkip(wrapper);
        }

        expect(apiQuiz.submitAnswer).toHaveBeenCalledTimes(5);
        expect(wrapper.text()).toContain(SKIP_ENCOURAGEMENT_MESSAGE);
        expect(wrapper.findAll('p').filter((p) => p.text() === SKIP_ENCOURAGEMENT_MESSAGE)).toHaveLength(1);
    });

    it('ne bloque jamais "Passer" : la progression continue normalement après le seuil', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        for (let i = 0; i < 6; i++) {
            await clickSkip(wrapper);
        }

        expect(apiQuiz.submitAnswer).toHaveBeenCalledTimes(6);
        const skipButton = wrapper.findAll('button').find((b) => b.text() === 'Passer');
        expect(skipButton.attributes('disabled')).toBeUndefined();
    });

    it('réinitialise le compteur dès qu\'une vraie réponse est donnée : le message disparaît', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        for (let i = 0; i < 5; i++) {
            await clickSkip(wrapper);
        }
        expect(wrapper.text()).toContain(SKIP_ENCOURAGEMENT_MESSAGE);

        const realAnswerButton = wrapper.findAll('button').find((b) => b.text() === 'Neutre');
        await realAnswerButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain(SKIP_ENCOURAGEMENT_MESSAGE);
    });

    /**
     * Ne doit pas réapparaître "en boucle" dès la reprise d'un comportement
     * normal : après la réinitialisation, il faut de nouveau 5 "Passer"
     * complets, pas seulement reprendre où le compteur s'était arrêté.
     */
    it('ne réaffiche pas le message avant un nouveau cycle complet de 5 "Passer" après une vraie réponse', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        for (let i = 0; i < 5; i++) {
            await clickSkip(wrapper);
        }
        expect(wrapper.text()).toContain(SKIP_ENCOURAGEMENT_MESSAGE);

        const realAnswerButton = wrapper.findAll('button').find((b) => b.text() === 'Neutre');
        await realAnswerButton.trigger('click');
        await flushPromises();

        for (let i = 0; i < 4; i++) {
            await clickSkip(wrapper);
            expect(wrapper.text()).not.toContain(SKIP_ENCOURAGEMENT_MESSAGE);
        }
    });
});

/**
 * Un rechargement de page pile pendant l'écran de calcul ne doit jamais
 * laisser l'utilisateur sur un quiz vide ni une erreur : QuizView::initialize()
 * rappelle systématiquement quizStore.resumeState() au montage (donc à
 * chaque rechargement), qui reflète l'état RÉEL du QuizResult en base, pas
 * un état local perdu au rechargement. Ces deux tests couvrent les deux
 * sorties possibles au moment précis du rechargement : le calcul est encore
 * en cours côté serveur, ou il vient de se terminer.
 */
/**
 * jsdom ne reproduit pas fidèlement l'activation clavier native d'un
 * <button> (Entrée/Espace -> click), contrairement à un vrai navigateur :
 * vérification manuelle requise en complément (Tab pour atteindre chaque
 * bouton, Entrée/Espace pour l'activer, contour de focus visible). Ce test
 * automatisé verrouille la garantie STRUCTURELLE qui la rend possible :
 * chaque contrôle interactif du quiz doit rester un vrai <button
 * type="button"> natif, jamais un <div>/<span> avec un simple @click, seule
 * façon de garantir l'accessibilité clavier native sans réimplémenter la
 * gestion des touches à la main.
 */
describe('QuizView : accessibilité clavier (structure)', () => {
    it('chaque bouton de réponse et "Passer" est un vrai <button type="button">', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        const labels = ["Pas du tout d'accord", "Pas d'accord", 'Neutre', "D'accord", 'Tout à fait d\'accord', 'Passer'];
        for (const label of labels) {
            const button = wrapper.findAll('button').find((b) => b.text() === label);
            expect(button.element.tagName).toBe('BUTTON');
            expect(button.attributes('type')).toBe('button');
        }
    });
});

describe('QuizView : rechargement pendant/après le calcul', () => {
    it('affiche l\'écran "Calcul en cours" (jamais un quiz vide) si le calcul est encore en cours au rechargement', async () => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'computing', completed_at: null, user_id: null, session_token: null },
                answers: buildAnswers(30),
                questions: buildQuestions(30),
            },
        });

        const wrapper = mount(QuizView);
        await flushPromises();

        expect(wrapper.text()).toContain('Calcul en cours');
        expect(wrapper.text()).toContain('Rafraîchir');
        // Jamais l'écran de quiz normal (questions/boutons de réponse) en
        // parallèle de l'écran de calcul.
        expect(wrapper.text()).not.toContain('Passer');
    });

    it('ne montre jamais le quiz ni l\'écran de calcul si le calcul s\'est terminé entre-temps (redirection vers /results)', async () => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'completed', completed_at: new Date().toISOString(), user_id: null, session_token: null },
                answers: buildAnswers(30),
                questions: buildQuestions(30),
            },
        });

        const wrapper = mount(QuizView);
        await flushPromises();

        // QuizView::initialize() redirige (router.replace) dès que
        // quizStore.status === 'completed', avant même de sortir de l'état
        // "initializing" — le test confirme donc l'absence de tout rendu de
        // quiz ou de calcul, jamais une redirection reconstruite via un espion
        // sur le router (le mock module-level ne partage pas la même
        // instance de fonction entre les tests, cf. tête de fichier).
        expect(wrapper.text()).not.toContain('Passer');
        expect(wrapper.text()).not.toContain('Calcul en cours');
        expect(wrapper.text()).not.toContain('Question 30 / 30');
    });
});

describe('QuizView : comportement identique en mode invité et en mode connecté', () => {
    it.each([
        ['invité (session_token présent, non authentifié)', () => {
            useAuthStore().sessionToken = 'guest-session-token';
        }],
        ['connecté (utilisateur authentifié, pas de session_token)', () => {
            const auth = useAuthStore();
            auth.isAuthenticated = true;
            auth.user = { id: 1, username: 'randy' };
        }],
    ])('déclenche le message d\'encouragement après 5 "Passer" d\'affilée — %s', async (_label, setupAuth) => {
        setupAuth();
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers: [],
                questions: buildQuestions(30),
            },
        });
        apiQuiz.submitAnswer.mockResolvedValue({ data: { answer: {} } });

        const wrapper = mount(QuizView);
        await flushPromises();

        for (let i = 0; i < 5; i++) {
            await clickSkip(wrapper);
        }

        expect(wrapper.text()).toContain(SKIP_ENCOURAGEMENT_MESSAGE);
    });
});

/**
 * Le champ `explanation` des 30 questions est rempli avec le contenu réel de
 * l'expert politique. Ce test vérifie le rendu du bouton "Pourquoi cette
 * question ?" dès qu'une question en possède un, indépendamment de son
 * contenu exact (déjà couvert côté backend par ExpertContentSeederTest). Le
 * fixture QUESTION par défaut (utilisé par la plupart des autres tests de ce
 * fichier) n'a volontairement pas d'explanation, pour que ces autres tests
 * restent inchangés : ce bloc fournit son propre fixture dédié.
 */
describe('QuizView : bouton "Pourquoi cette question ?"', () => {
    const EXPLANATION_TEXT = "En Belgique, le salaire minimum brut mensuel s'élève actuellement à un peu plus de 2 070 euros.";
    const QUESTION_WITH_EXPLANATION = { id: 99, label: 'Une question avec explication.', weight: 1, theme: { name: 'Économie' }, explanation: EXPLANATION_TEXT };

    beforeEach(() => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers: [],
                questions: [QUESTION_WITH_EXPLANATION],
            },
        });
    });

    it('n\'affiche pas le bouton pour une question sans explanation', async () => {
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending', completed_at: null, user_id: null, session_token: null },
                answers: [],
                questions: [QUESTION],
            },
        });
        const wrapper = mount(QuizView);
        await flushPromises();

        expect(wrapper.text()).not.toContain('Pourquoi cette question');
    });

    it('affiche le bouton mais pas le texte tant qu\'il n\'est pas ouvert', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        expect(wrapper.text()).toContain('Pourquoi cette question ?');
        expect(wrapper.text()).not.toContain(EXPLANATION_TEXT);
    });

    it('ouvre l\'explication au clic et l\'affiche avec le contenu réel', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        const toggle = wrapper.findAll('button').find((b) => b.text().includes('Pourquoi cette question'));
        await toggle.trigger('click');

        expect(wrapper.text()).toContain(EXPLANATION_TEXT);
        expect(wrapper.text()).toContain("Masquer l'explication");
    });

    /**
     * Activation clavier : un vrai <button> natif convertit Entrée/Espace en
     * évènement 'click' (jsdom ne reproduit pas fidèlement ce comportement
     * natif, donc testé ici via un déclenchement direct de 'click'), la
     * garantie d'accessibilité réelle venant de la structure native du
     * bouton, pas d'un gestionnaire keydown réimplémenté à la main.
     */
    it('est un vrai bouton natif avec aria-expanded reflétant l\'état ouvert/fermé', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        const toggle = wrapper.findAll('button').find((b) => b.text().includes('Pourquoi cette question'));
        expect(toggle.element.tagName).toBe('BUTTON');
        expect(toggle.attributes('type')).toBe('button');
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');

        await toggle.trigger('click');
        expect(wrapper.text()).not.toContain(EXPLANATION_TEXT);
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });

    it('referme et rouvre correctement sur des clics répétés', async () => {
        const wrapper = mount(QuizView);
        await flushPromises();

        const toggle = wrapper.findAll('button').find((b) => b.text().includes('Pourquoi cette question'));
        await toggle.trigger('click');
        await toggle.trigger('click');
        await toggle.trigger('click');

        expect(wrapper.text()).toContain(EXPLANATION_TEXT);
    });
});

/**
 * Reprise ciblée : quand quizStore.resumeMode === 'skipped' (déjà chargé
 * par resultsStore/quizStore.resumeSkippedOnly juste avant la navigation
 * vers /quiz, cf. ResultsView::handleResumeSkipped), QuizView ne doit PAS
 * rappeler resumeState() à son montage, cela écraserait la liste déjà
 * filtrée (uniquement les questions passées) avec les 30 questions
 * habituelles.
 */
describe('QuizView : mode reprise ciblée', () => {
    it('ne rappelle pas apiQuiz.state au montage quand resumeMode = "skipped", et affiche uniquement les questions déjà filtrées', async () => {
        const { useQuizStore } = await import('@/stores/quizStore');
        const quizStore = useQuizStore();
        quizStore.setQuizUuid('quiz-uuid');
        quizStore.status = 'pending';
        quizStore.questions = [
            { id: 7, label: 'Question passée numéro 7.', weight: 1, theme: { name: 'Économie' } },
        ];
        quizStore.resumeMode = 'skipped';

        const wrapper = mount(QuizView);
        await flushPromises();

        expect(apiQuiz.state).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Question passée numéro 7.');
        expect(wrapper.text()).toContain('Question 1 / 1');
    });
});

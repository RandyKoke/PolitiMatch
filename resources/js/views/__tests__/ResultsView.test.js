import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ResultsView from '@/views/ResultsView.vue';
import { apiResults } from '@/services/apiResults';
import { apiShare } from '@/services/apiShare';

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { uuid: 'quiz-uuid' } }),
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

vi.mock('@/services/apiResults', () => ({ apiResults: { show: vi.fn() } }));
vi.mock('@/services/apiShare', () => ({ apiShare: { create: vi.fn(), show: vi.fn() } }));
vi.mock('@/services/apiCompare', () => ({ apiCompare: { index: vi.fn() } }));
vi.mock('@/services/apiUsers', () => ({ apiUsers: { results: vi.fn() } }));
vi.mock('@/services/apiQuiz', () => ({
    apiQuiz: { state: vi.fn(), resumeSkipped: vi.fn(), start: vi.fn() },
}));

const BASE_PAYLOAD = {
    quiz_uuid: 'quiz-uuid',
    political_axis_x: 0.5,
    political_axis_y: -0.2,
    profile_label: 'Progressiste équilibré',
    profile_description: 'Une description.',
    party_scores: [{ party_id: 1, compatibility_score: 80, rank: 1, party: { id: 1, name: 'PS' } }],
};

function reliabilityPayload(state, extra = {}) {
    return {
        ...BASE_PAYLOAD,
        reliability: { state, real_answers_count: 0, skipped_count: 0, total_questions: 30, ...extra },
    };
}

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
    // isOwnActiveQuiz : le quiz actif (localStorage) correspond à l'UUID de
    // la route consultée — nécessaire pour que les boutons "Refaire le
    // test"/"Reprendre le quiz" apparaissent (cf. ResultsView::isOwnActiveQuiz).
    localStorage.setItem('politimatch.quiz_result_uuid', 'quiz-uuid');
});

describe('ResultsView : état bloquant "empty" (0 réponse réelle)', () => {
    it('shows the empty-state message and a "Refaire le test" button, hides the normal content', async () => {
        apiResults.show.mockResolvedValue({ data: reliabilityPayload('empty', { real_answers_count: 0 }) });
        const wrapper = mount(ResultsView);
        await flushPromises();

        expect(wrapper.text()).toContain("Tu n'as répondu à aucune question");
        expect(wrapper.text()).toContain('Refaire le test');
        expect(wrapper.text()).not.toContain('Reprendre le quiz');
        expect(wrapper.text()).not.toContain('Partager mon résultat');
        expect(wrapper.text()).not.toContain('Voir le comparateur');
        expect(wrapper.text()).not.toContain('Créer un compte');
        expect(wrapper.text()).not.toContain('Progressiste équilibré');
        expect(wrapper.text()).not.toContain('Télécharger mon résultat');
    });
});

describe('ResultsView : état bloquant "too_few" (0 < réponses < 15)', () => {
    it('shows the too-few message and a "Reprendre le quiz" button, hides share/compare/save', async () => {
        apiResults.show.mockResolvedValue({ data: reliabilityPayload('too_few', { real_answers_count: 8, skipped_count: 22 }) });
        const wrapper = mount(ResultsView);
        await flushPromises();

        expect(wrapper.text()).toContain('Tu as répondu à trop peu de questions');
        expect(wrapper.text()).toContain('Reprendre le quiz');
        expect(wrapper.text()).not.toContain('Refaire le test');
        expect(wrapper.text()).not.toContain('Partager mon résultat');
        expect(wrapper.text()).not.toContain('Voir le comparateur');
        expect(wrapper.text()).not.toContain('Télécharger mon résultat');
    });
});

describe('ResultsView : état non bloquant "partial" (>= 15 réponses, au moins une passée)', () => {
    it('shows the normal result content plus the transparency mention with the real count', async () => {
        apiResults.show.mockResolvedValue({ data: reliabilityPayload('partial', { real_answers_count: 22, skipped_count: 8 }) });
        const wrapper = mount(ResultsView);
        await flushPromises();

        expect(wrapper.text()).toContain('Progressiste équilibré');
        expect(wrapper.text()).toContain('Partager mon résultat');
        expect(wrapper.text()).toContain('Voir le comparateur');
        expect(wrapper.text()).toContain('Ton profil est basé sur 22 réponses sur 30');
        expect(wrapper.text()).not.toContain('Tu as répondu à trop peu de questions');
        expect(wrapper.text()).toContain('Télécharger mon résultat');
        expect(wrapper.find('a[href="/api/results/quiz-uuid/download-image"]').exists()).toBe(true);
    });
});

describe('ResultsView : état non bloquant "full" (30/30, aucune passée)', () => {
    it('shows the normal result content without any transparency mention', async () => {
        apiResults.show.mockResolvedValue({ data: reliabilityPayload('full', { real_answers_count: 30, skipped_count: 0 }) });
        const wrapper = mount(ResultsView);
        await flushPromises();

        expect(wrapper.text()).toContain('Progressiste équilibré');
        expect(wrapper.text()).toContain('Partager mon résultat');
        expect(wrapper.text()).not.toContain('Ton profil est basé sur');
        expect(wrapper.text()).toContain('Télécharger mon résultat');
    });
});

describe('ResultsView : bouton "Refaire le test"', () => {
    it('discards the current quiz and navigates to /start', async () => {
        apiResults.show.mockResolvedValue({ data: reliabilityPayload('empty') });
        const wrapper = mount(ResultsView);
        await flushPromises();

        const button = wrapper.findAll('button').find((b) => b.text() === 'Refaire le test');
        await button.trigger('click');

        expect(localStorage.getItem('politimatch.quiz_result_uuid')).toBeNull();
    });
});

describe('ResultsView : bouton "Reprendre le quiz"', () => {
    it('calls the resume-skipped API before navigating', async () => {
        const { apiQuiz } = await import('@/services/apiQuiz');
        apiResults.show.mockResolvedValue({ data: reliabilityPayload('too_few', { real_answers_count: 5 }) });
        apiQuiz.resumeSkipped.mockResolvedValue({ data: { quiz_result_uuid: 'quiz-uuid' } });
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending' },
                answers: [],
                questions: [],
            },
        });
        const wrapper = mount(ResultsView);
        await flushPromises();

        const button = wrapper.findAll('button').find((b) => b.text() === 'Reprendre le quiz');
        await button.trigger('click');
        await flushPromises();

        expect(apiQuiz.resumeSkipped).toHaveBeenCalledWith('quiz-uuid', expect.objectContaining({}));
    });
});

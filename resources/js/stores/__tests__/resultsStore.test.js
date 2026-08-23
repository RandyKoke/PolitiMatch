import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useResultsStore } from '@/stores/resultsStore';
import { apiResults } from '@/services/apiResults';
import { apiShare } from '@/services/apiShare';
import { apiCompare } from '@/services/apiCompare';
import { apiUsers } from '@/services/apiUsers';

vi.mock('@/services/apiResults', () => ({ apiResults: { show: vi.fn() } }));
vi.mock('@/services/apiShare', () => ({ apiShare: { create: vi.fn(), show: vi.fn() } }));
vi.mock('@/services/apiCompare', () => ({ apiCompare: { index: vi.fn() } }));
vi.mock('@/services/apiUsers', () => ({ apiUsers: { results: vi.fn() } }));

const RESULT_PAYLOAD = {
    quiz_uuid: 'quiz-uuid',
    political_axis_x: 0.5,
    political_axis_y: -0.2,
    profile_label: 'Progressiste équilibré',
    profile_description: 'Une description.',
    party_scores: [{ party_id: 1, compatibility_score: 80, rank: 1, party: { id: 1, name: 'PS' } }],
    // Présent sur toute réponse de GET /results et GET /share.
    reliability: { state: 'full', real_answers_count: 30, skipped_count: 0, total_questions: 30 },
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('resultsStore.loadResults', () => {
    it('populates compatibilityScores/axes/profile from GET /results/:uuid', async () => {
        apiResults.show.mockResolvedValue({ data: RESULT_PAYLOAD });
        const store = useResultsStore();

        await store.loadResults('quiz-uuid');

        expect(store.quizUuid).toBe('quiz-uuid');
        expect(store.compatibilityScores).toEqual(RESULT_PAYLOAD.party_scores);
        expect(store.axes).toEqual({ x: 0.5, y: -0.2 });
        expect(store.profileLabel).toBe('Progressiste équilibré');
        expect(store.error).toBeNull();
        expect(store.notReadyStatus).toBeNull();
        expect(store.reliability).toEqual(RESULT_PAYLOAD.reliability);
    });

    /**
     * Régression du comportement documenté dans resultsStore.js : un 409
     * avec un champ `status` (quiz pas encore 'completed') est un état
     * normal côté UI (ResultsView affiche un écran d'attente dédié), jamais
     * une erreur générique affichée à l'utilisateur.
     */
    it('treats a 409 with a status field as "not ready", not a generic error', async () => {
        apiResults.show.mockRejectedValue({ response: { status: 409, data: { status: 'pending' } } });
        const store = useResultsStore();

        await expect(store.loadResults('quiz-uuid')).rejects.toBeDefined();

        expect(store.notReadyStatus).toBe('pending');
        expect(store.error).toBeNull();
    });

    it('treats a 404 (unknown uuid) as a real error, not "not ready"', async () => {
        apiResults.show.mockRejectedValue({ response: { status: 404, data: {} } });
        const store = useResultsStore();

        await expect(store.loadResults('unknown')).rejects.toBeDefined();

        expect(store.notReadyStatus).toBeNull();
        expect(store.error).not.toBeNull();
    });
});

describe('resultsStore.loadShareData', () => {
    it('populates the same fields as loadResults, from the public share endpoint', async () => {
        apiShare.show.mockResolvedValue({ data: RESULT_PAYLOAD });
        const store = useResultsStore();

        await store.loadShareData('some-share-token');

        expect(apiShare.show).toHaveBeenCalledWith('some-share-token');
        expect(store.profileLabel).toBe('Progressiste équilibré');
        expect(store.compatibilityScores).toEqual(RESULT_PAYLOAD.party_scores);
    });
});

describe('resultsStore.createShareLink', () => {
    it('stores the returned share_token', async () => {
        apiShare.create.mockResolvedValue({ data: { share_token: 'abc-123' } });
        const store = useResultsStore();

        const token = await store.createShareLink('quiz-uuid');

        expect(token).toBe('abc-123');
        expect(store.shareToken).toBe('abc-123');
    });
});

describe('resultsStore.loadCompareData', () => {
    it('stores the raw compare response', async () => {
        const compareResponse = { has_answers: true, questions: [], user: { scores: {} }, parties: [] };
        apiCompare.index.mockResolvedValue({ data: compareResponse });
        const store = useResultsStore();

        await store.loadCompareData('quiz-uuid');

        expect(store.compareData).toEqual(compareResponse);
    });
});

describe('resultsStore.loadHistory', () => {
    it('stores the quiz_results array from GET /user/results', async () => {
        const history = [{ uuid: 'a', status: 'completed', profile_label: 'X' }];
        apiUsers.results.mockResolvedValue({ data: { quiz_results: history } });
        const store = useResultsStore();

        await store.loadHistory();

        expect(store.history).toEqual(history);
    });
});

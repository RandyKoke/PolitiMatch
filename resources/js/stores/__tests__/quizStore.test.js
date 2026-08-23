import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useQuizStore } from '@/stores/quizStore';
import { apiQuiz } from '@/services/apiQuiz';

vi.mock('@/services/apiQuiz', () => ({
    apiQuiz: {
        resumeSkipped: vi.fn(),
        state: vi.fn(),
    },
}));

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
});

/**
 * Reprise ciblée : resumeSkippedOnly() doit filtrer `questions` pour ne
 * garder QUE celles marquées was_skipped dans la réponse de
 * GET /state — jamais les questions déjà répondues réellement, qui ne
 * doivent ni être redemandées ni risquer d'être écrasées par erreur.
 */
describe('quizStore.resumeSkippedOnly', () => {
    it('keeps only the questions marked was_skipped, discards the rest', async () => {
        apiQuiz.resumeSkipped.mockResolvedValue({ data: { quiz_result_uuid: 'quiz-uuid' } });
        apiQuiz.state.mockResolvedValue({
            data: {
                quiz_result: { uuid: 'quiz-uuid', status: 'pending' },
                answers: [
                    { question_id: 1, user_score: 1, was_skipped: false },
                    { question_id: 2, user_score: 0, was_skipped: true },
                    { question_id: 3, user_score: 0, was_skipped: true },
                ],
                questions: [
                    { id: 1, label: 'Répondue réellement.' },
                    { id: 2, label: 'Passée A.' },
                    { id: 3, label: 'Passée B.' },
                ],
            },
        });
        const store = useQuizStore();

        await store.resumeSkippedOnly('quiz-uuid');

        expect(store.questions.map((q) => q.id)).toEqual([2, 3]);
        expect(store.currentQuizUuid).toBe('quiz-uuid');
        expect(store.status).toBe('pending');
        expect(store.resumeMode).toBe('skipped');
        expect(store.answers).toEqual({});
        expect(store.currentQuestionIndex).toBe(0);
    });

    it('calls POST /resume-skipped before reloading state', async () => {
        apiQuiz.resumeSkipped.mockResolvedValue({ data: {} });
        apiQuiz.state.mockResolvedValue({
            data: { quiz_result: { uuid: 'quiz-uuid', status: 'pending' }, answers: [], questions: [] },
        });
        const store = useQuizStore();

        await store.resumeSkippedOnly('quiz-uuid');

        expect(apiQuiz.resumeSkipped).toHaveBeenCalledWith('quiz-uuid', expect.any(Object));
    });
});

describe('quizStore.discardCurrentQuiz', () => {
    it('resets resumeMode along with the rest of the quiz state', () => {
        const store = useQuizStore();
        store.resumeMode = 'skipped';

        store.discardCurrentQuiz();

        expect(store.resumeMode).toBeNull();
        expect(store.currentQuizUuid).toBeNull();
    });
});

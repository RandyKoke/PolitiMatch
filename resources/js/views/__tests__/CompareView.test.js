import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import CompareView from '@/views/CompareView.vue';
import { apiCompare } from '@/services/apiCompare';

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { uuid: 'quiz-uuid' } }),
}));

vi.mock('@/services/apiCompare', () => ({ apiCompare: { index: vi.fn() } }));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('CompareView : résultat en état bloquant', () => {
    it.each(['empty', 'too_few'])('shows the explanatory message instead of the comparison table (state = %s)', async (state) => {
        apiCompare.index.mockResolvedValue({
            data: {
                quiz_uuid: 'quiz-uuid',
                blocked: true,
                reliability: { state, real_answers_count: 0, skipped_count: 0, total_questions: 30 },
            },
        });
        const wrapper = mount(CompareView);
        await flushPromises();

        expect(wrapper.find('table').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Comparateur indisponible');
    });
});

describe('CompareView : résultat fiable ou quiz en cours', () => {
    it('shows the comparison table normally when not blocked', async () => {
        apiCompare.index.mockResolvedValue({
            data: {
                quiz_uuid: 'quiz-uuid',
                has_answers: true,
                questions: [{ id: 1, label: 'Q1', theme_name: 'Économie', axe_ideologique: 'economique', weight: 1 }],
                user: { scores: { 1: 2 } },
                parties: [{ id: 1, name: 'PS', color_hex: '#ff0000', positions: { 1: { score: 1, justification: 'x' } } }],
            },
        });
        const wrapper = mount(CompareView);
        await flushPromises();

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.text()).toContain('Comparateur');
    });
});

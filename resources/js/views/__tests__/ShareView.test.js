import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ShareView from '@/views/ShareView.vue';
import { apiShare } from '@/services/apiShare';

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { token: 'share-token' } }),
    useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/services/apiShare', () => ({ apiShare: { show: vi.fn() } }));

const BASE_PAYLOAD = {
    quiz_uuid: 'quiz-uuid',
    political_axis_x: 0.5,
    political_axis_y: -0.2,
    profile_label: 'Progressiste équilibré',
    profile_description: 'Une description.',
    party_scores: [{ party_id: 1, compatibility_score: 80, rank: 1, party: { id: 1, name: 'PS' } }],
};

function reliabilityPayload(state) {
    return { ...BASE_PAYLOAD, reliability: { state, real_answers_count: 0, skipped_count: 0, total_questions: 30 } };
}

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('ShareView : lien de partage pointant vers un résultat en état bloquant', () => {
    it.each(['empty', 'too_few'])('shows the explanatory message instead of active share buttons (state = %s)', async (state) => {
        apiShare.show.mockResolvedValue({ data: reliabilityPayload(state) });
        const wrapper = mount(ShareView);
        await flushPromises();

        expect(wrapper.text()).not.toContain('Copier le lien');
        expect(wrapper.text()).not.toContain('Progressiste équilibré');
        expect(wrapper.findAll('a[href*="twitter.com"]')).toHaveLength(0);
        expect(wrapper.findAll('a[href*="wa.me"]')).toHaveLength(0);
        expect(wrapper.text()).not.toContain('Télécharger cette carte');
    });
});

describe('ShareView : lien de partage pointant vers un résultat fiable', () => {
    it('shows the profile and active share buttons normally', async () => {
        apiShare.show.mockResolvedValue({ data: reliabilityPayload('full') });
        const wrapper = mount(ShareView);
        await flushPromises();

        expect(wrapper.text()).toContain('Progressiste équilibré');
        expect(wrapper.text()).toContain('Copier le lien');
        expect(wrapper.text()).toContain('Télécharger cette carte');
        expect(wrapper.find('a[href="/api/results/quiz-uuid/download-image"]').exists()).toBe(true);
    });
});

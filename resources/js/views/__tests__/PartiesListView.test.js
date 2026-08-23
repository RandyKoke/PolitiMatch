import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import PartiesListView from '@/views/PartiesListView.vue';
import PartyDetailView from '@/views/PartyDetailView.vue';
import { apiParties } from '@/services/apiParties';

vi.mock('@/services/apiParties', () => ({
    apiParties: { index: vi.fn(), show: vi.fn() },
}));

const PARTIES = [
    {
        id: 1, name: 'Écolo', abbreviation: 'ECOLO', logo_url: null, color_hex: '#4C9A2A',
        description: 'Parti écologiste.', slogan: 'Choisir l\'avenir', language_community: 'FR',
    },
    {
        id: 2, name: 'MR', abbreviation: 'MR', logo_url: null, color_hex: '#0055A4',
        description: 'Parti libéral.', slogan: null, language_community: 'FR',
    },
];

async function mountAt(path) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/parties', component: PartiesListView },
            { path: '/parties/:id', component: PartyDetailView },
        ],
    });
    router.push(path);
    await router.isReady();

    return mount(PartiesListView, { global: { plugins: [router] } });
}

beforeEach(() => {
    vi.clearAllMocks();
});

/**
 * Module Fiches Partis : annuaire accessible sans contexte de quiz, seul
 * point d'entrée vers les fiches partis qui ne dépende pas d'un résultat ou
 * du comparateur.
 */
describe('PartiesListView', () => {
    it('affiche la liste des partis actifs après chargement', async () => {
        apiParties.index.mockResolvedValue({ data: { parties: PARTIES } });

        const wrapper = await mountAt('/parties');
        await flushPromises();

        expect(wrapper.text()).toContain('Écolo');
        expect(wrapper.text()).toContain('MR');
    });

    it('affiche le slogan quand il existe, sinon un extrait de la description', async () => {
        apiParties.index.mockResolvedValue({ data: { parties: PARTIES } });

        const wrapper = await mountAt('/parties');
        await flushPromises();

        expect(wrapper.text()).toContain('Choisir l\'avenir');
        expect(wrapper.text()).toContain('Parti libéral.');
    });

    it('chaque entrée pointe vers la fiche détaillée, sans paramètre quiz', async () => {
        apiParties.index.mockResolvedValue({ data: { parties: PARTIES } });

        const wrapper = await mountAt('/parties');
        await flushPromises();

        const links = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(links).toContain('/parties/1');
        expect(links).toContain('/parties/2');
        expect(links.some((href) => href.includes('quiz='))).toBe(false);
    });

    it('affiche un message d\'erreur avec un bouton "Réessayer" en cas d\'échec', async () => {
        apiParties.index.mockRejectedValue(new Error('network error'));

        const wrapper = await mountAt('/parties');
        await flushPromises();

        expect(wrapper.text()).toContain('Impossible de charger la liste des partis.');
        expect(wrapper.findAll('button').some((b) => b.text() === 'Réessayer')).toBe(true);
    });
});

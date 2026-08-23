import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import PartyDetailView from '@/views/PartyDetailView.vue';
import { apiParties } from '@/services/apiParties';

vi.mock('@/services/apiParties', () => ({
    apiParties: { show: vi.fn() },
}));

const PARTY = {
    id: 7, name: 'PTB', abbreviation: 'PTB', logo_url: null, color_hex: '#B0212F',
    description: 'Un parti.', slogan: 'Le choix de la rupture', language_community: 'FR', is_active: true,
    ideological_x: 0.43, ideological_y: 0.70,
};

async function mountAt(path) {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/parties/:id', component: PartyDetailView }] });
    router.push(path);
    await router.isReady();

    return mount(PartyDetailView, { global: { plugins: [router] } });
}

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

/**
 * Un parti désactivé reste consultable depuis un ancien résultat (backend :
 * PartyController::show, ?quiz={uuid}). Ce test couvre le pendant frontend,
 * la mention discrète attendue plutôt qu'un écran "Parti introuvable".
 */
describe('PartyDetailView : mention "parti plus actif" (point 11)', () => {
    it('n\'affiche aucune mention pour un parti actif', async () => {
        apiParties.show.mockResolvedValue({ data: { party: PARTY, notable_positions: [] } });

        const wrapper = await mountAt('/parties/7');
        await flushPromises();

        expect(wrapper.text()).not.toContain("n'est plus actif");
    });

    it('affiche la mention discrète pour un parti désactivé mais accessible (contexte historique)', async () => {
        apiParties.show.mockResolvedValue({ data: { party: { ...PARTY, is_active: false }, notable_positions: [] } });

        const wrapper = await mountAt('/parties/7?quiz=11111111-1111-1111-1111-111111111111');
        await flushPromises();

        expect(wrapper.text()).toContain("Ce parti n'est plus actif dans le quiz actuel.");
    });

    it('transmet le paramètre ?quiz= à apiParties.show quand présent', async () => {
        apiParties.show.mockResolvedValue({ data: { party: PARTY, notable_positions: [] } });

        await mountAt('/parties/7?quiz=11111111-1111-1111-1111-111111111111');
        await flushPromises();

        expect(apiParties.show).toHaveBeenCalledWith('7', '11111111-1111-1111-1111-111111111111');
    });

    it('n\'envoie aucun paramètre quiz en son absence (accès direct, sans contexte)', async () => {
        apiParties.show.mockResolvedValue({ data: { party: PARTY, notable_positions: [] } });

        await mountAt('/parties/7');
        await flushPromises();

        expect(apiParties.show).toHaveBeenCalledWith('7', null);
    });
});

/**
 * Relance du module Fiches Partis : slogan, communauté linguistique et
 * mention textuelle des axes idéologiques, absents jusqu'ici de la fiche
 * bien qu'existants (ou nouvellement ajoutés) en base.
 */
describe('PartyDetailView : slogan, communauté linguistique et positionnement idéologique', () => {
    it('affiche le slogan en citation quand il est présent', async () => {
        apiParties.show.mockResolvedValue({ data: { party: PARTY, notable_positions: [] } });

        const wrapper = await mountAt('/parties/7');
        await flushPromises();

        expect(wrapper.text()).toContain('« Le choix de la rupture »');
    });

    it('n\'affiche aucune citation quand le slogan est absent', async () => {
        apiParties.show.mockResolvedValue({ data: { party: { ...PARTY, slogan: null }, notable_positions: [] } });

        const wrapper = await mountAt('/parties/7');
        await flushPromises();

        expect(wrapper.text()).not.toContain('«');
    });

    it('affiche le libellé complet de la communauté linguistique', async () => {
        apiParties.show.mockResolvedValue({ data: { party: PARTY, notable_positions: [] } });

        const wrapper = await mountAt('/parties/7');
        await flushPromises();

        expect(wrapper.text()).toContain('Francophone');
    });

    it('affiche une mention textuelle du positionnement idéologique, sans reproduire de graphique', async () => {
        apiParties.show.mockResolvedValue({ data: { party: PARTY, notable_positions: [] } });

        const wrapper = await mountAt('/parties/7');
        await flushPromises();

        expect(wrapper.text()).toContain('interventionniste');
        expect(wrapper.text()).toContain('progressiste');
        expect(wrapper.find('canvas').exists()).toBe(false);
    });

    it('n\'affiche aucune mention idéologique quand les axes sont null', async () => {
        apiParties.show.mockResolvedValue({
            data: { party: { ...PARTY, ideological_x: null, ideological_y: null }, notable_positions: [] },
        });

        const wrapper = await mountAt('/parties/7');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Positionnement calculé');
    });
});

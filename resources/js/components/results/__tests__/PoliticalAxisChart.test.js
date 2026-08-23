import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PoliticalAxisChart from '@/components/results/PoliticalAxisChart.vue';

// Chart.js n'obtient pas de contexte 2D exploitable sous jsdom (pas de vrai
// canvas) : on remplace <Scatter> par un stub inoffensif pour tester la
// logique propre à ce composant (légende, dégradation) sans dépendre du
// rendu réel de Chart.js, qui est de toute façon vérifié en navigateur réel.
vi.mock('vue-chartjs', () => ({
    Scatter: { name: 'ScatterStub', template: '<canvas />' },
}));

const PARTIES = [
    { id: 1, name: 'Mouvement Réformateur', abbreviation: 'MR', color_hex: '#0055A4', ideological_x: -0.6, ideological_y: -0.1 },
    { id: 2, name: 'Parti du Travail de Belgique', abbreviation: 'PTB', color_hex: '#B0212F', ideological_x: 0.7, ideological_y: 0.3 },
];

describe('PoliticalAxisChart', () => {
    it('shows the "Toi" legend entry with the user marker when both axes are available', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: { axisX: 0.2, axisY: -0.1, parties: PARTIES },
        });

        expect(wrapper.text()).toContain('Toi');
        expect(wrapper.text()).toContain('Mouvement Réformateur');
        expect(wrapper.text()).toContain('Parti du Travail de Belgique');
    });

    it('does not show the "Toi" legend entry when the user has no computable position (parties-only chart)', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: { axisX: null, axisY: null, parties: PARTIES },
        });

        expect(wrapper.text()).not.toContain('Toi');
        expect(wrapper.text()).toContain('Mouvement Réformateur');
    });

    it('falls back to the "not enough answers" message when neither axes nor party positions are available', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: { axisX: null, axisY: null, parties: [] },
        });

        expect(wrapper.text()).toContain('Pas assez de réponses');
    });

    it('falls back to a single-axis gauge when only one axis is computable and no parties are positioned', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: { axisX: 0.4, axisY: null, parties: [] },
        });

        expect(wrapper.text()).toContain('Un seul axe a pu être calculé');
        expect(wrapper.text()).toContain('Libéral');
        expect(wrapper.text()).not.toContain('Pas assez de réponses');
    });

    it('falls back to the party name when a party is missing an abbreviation, rather than crashing', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: {
                axisX: null,
                axisY: null,
                parties: [{ id: 9, name: 'Sans Abréviation', color_hex: '#123456', ideological_x: 0.1, ideological_y: 0.1 }],
            },
        });

        expect(wrapper.text()).toContain('Sans Abréviation');
    });

    /**
     * Un canvas Chart.js n'expose par défaut aucun contenu à un lecteur
     * d'écran. Vérifie l'alternative textuelle
     * (role="img" + aria-label sur le conteneur, paragraphe sr-only en
     * renfort), avec un contenu qui reflète réellement la position calculée
     * (pas un texte générique identique quelle que soit la position).
     */
    it('exposes the user\'s computed position as an accessible text alternative to the canvas', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: { axisX: -0.6, axisY: 0.5, parties: PARTIES },
        });

        const chartContainer = wrapper.find('[role="img"]');
        expect(chartContainer.exists()).toBe(true);
        expect(chartContainer.attributes('aria-label')).toContain('libéral');
        expect(chartContainer.attributes('aria-label')).toContain('progressiste');
        expect(wrapper.find('p.sr-only').text()).toBe(chartContainer.attributes('aria-label'));
        // Le canvas lui-même reste caché des technologies d'assistance :
        // l'information n'existe qu'une seule fois, dans le texte alternatif.
        expect(wrapper.find('canvas').attributes('aria-hidden')).toBe('true');
    });

    it('uses a generic (but still present) accessible label when only party positions are shown, no user position', () => {
        const wrapper = mount(PoliticalAxisChart, {
            props: { axisX: null, axisY: null, parties: PARTIES },
        });

        const chartContainer = wrapper.find('[role="img"]');
        expect(chartContainer.attributes('aria-label')).toBeTruthy();
        expect(chartContainer.attributes('aria-label')).not.toContain('Ta position');
    });

    it('reflects a different position in the accessible label for a different computed axis pair', () => {
        const conservative = mount(PoliticalAxisChart, {
            props: { axisX: 0.6, axisY: -0.6, parties: PARTIES },
        });
        const progressive = mount(PoliticalAxisChart, {
            props: { axisX: -0.6, axisY: 0.6, parties: PARTIES },
        });

        expect(conservative.find('[role="img"]').attributes('aria-label'))
            .not.toBe(progressive.find('[role="img"]').attributes('aria-label'));
    });
});

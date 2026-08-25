import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import StartAccessView from '@/views/StartAccessView.vue';

vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn() }),
}));

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
});

describe('StartAccessView', () => {
    it('renders its "prêt à commencer" content when no quiz is in progress (the default guest case)', async () => {
        const wrapper = mount(StartAccessView);
        await flushPromises();

        expect(wrapper.text()).toContain('Prêt à commencer');
        expect(wrapper.text()).toContain('Continuer en invité');
    });

    /**
     * Régression structurelle : un écran /start vide après une navigation
     * SPA. AppLayout enveloppe chaque vue dans <Transition mode="out-in">,
     * qui exige un unique nœud racine réel par composant, sinon Vue avertit
     * "renders non-element root node that cannot be animated" et, en usage
     * réel (navigateur), l'écran reste vide au lieu de s'afficher.
     *
     * Reproduire l'avertissement lui-même dans jsdom s'est révélé peu
     * fiable (essayé avec un vrai <Transition> + swap de composant keyé,
     * identique au mécanisme d'AppLayout : ne déclenche pas le warning en
     * environnement de test, même sans le correctif). On teste donc
     * directement la cause structurelle plutôt que le symptôme : l'arbre de
     * rendu de Vue (`$.subTree`) doit avoir un seul nœud racine réel, pas
     * un Fragment à plusieurs enfants. Vérifié empiriquement que ce test
     * échoue bien sans le correctif (subTree = Fragment à 2 enfants réels :
     * la carte active + <PmModal>, à cause du nœud ancre laissé par son
     * Teleport) avant de le restaurer.
     */
    it('disables "Continuer en invité" tant que le consentement RGPD n\'est pas coché, puis l\'active', async () => {
        const wrapper = mount(StartAccessView);
        await flushPromises();

        const startButton = wrapper.findAll('button').find((b) => b.text() === 'Continuer en invité');
        expect(startButton.attributes('disabled')).toBeDefined();

        await wrapper.find('input[type="checkbox"]').setValue(true);

        expect(startButton.attributes('disabled')).toBeUndefined();
    });

    it('renders as a single real root node, not a multi-root fragment (PmModal must not be a sibling)', async () => {
        const wrapper = mount(StartAccessView);
        await flushPromises();

        const subTree = wrapper.vm.$.subTree;
        const isFragment = typeof subTree.type === 'symbol' && String(subTree.type) === 'Symbol(v-fgt)';

        if (!isFragment) {
            // Un seul enfant direct : le cas sain, rien à vérifier de plus.
            return;
        }

        // v-cmt : nœud commentaire laissé par une branche v-if/v-else-if non
        // retenue — ne compte pas comme une "vraie" racine supplémentaire.
        const realChildren = subTree.children.filter((child) => String(child.type) !== 'Symbol(v-cmt)');

        expect(realChildren).toHaveLength(1);
    });
});

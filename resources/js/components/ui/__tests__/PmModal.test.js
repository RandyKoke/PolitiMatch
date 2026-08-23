import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PmModal from '@/components/ui/PmModal.vue';

/**
 * PmModal doit piéger le focus : sans ça, Tab répété atteindrait des
 * éléments situés derrière la modale. Montage
 * avec attachTo : document.body nécessaire : le focus réel (document.
 * activeElement) et les événements clavier testés ici ne se comportent pas
 * de façon fiable sur un arbre détaché du document.
 *
 * Le contenu de PmModal passe par <Teleport to="body">, que les méthodes
 * find/findAll de Vue Test Utils ne traversent pas (elles ne voient que les
 * marqueurs de téléportation dans l'arbre du wrapper) : les recherches
 * ci-dessous interrogent donc le DOM réel via document, scopées au dialogue
 * (role="dialog") pour ne jamais confondre ses boutons avec le bouton
 * déclencheur externe créé dans beforeEach.
 */
describe('PmModal : piège de focus et gestion du focus clavier', () => {
    let wrapper;
    let trigger;

    beforeEach(() => {
        trigger = document.createElement('button');
        trigger.textContent = 'Ouvrir';
        document.body.appendChild(trigger);
        trigger.focus();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        trigger?.remove();
    });

    function dialogButtons() {
        return Array.from(document.querySelector('[role="dialog"]').querySelectorAll('button'));
    }

    async function mountOpenModal(slotContent = '<button>Premier</button><button>Deuxieme</button>') {
        wrapper = mount(PmModal, {
            attachTo: document.body,
            props: { modelValue: false, title: 'Titre de test' },
            slots: { default: slotContent },
        });
        await wrapper.setProps({ modelValue: true });
        await wrapper.vm.$nextTick();
        return wrapper;
    }

    it('place le focus sur le premier élément focusable du contenu à l\'ouverture, pas sur le bouton fermer', async () => {
        await mountOpenModal();

        expect(document.activeElement?.textContent).toBe('Premier');
    });

    it('boucle Tab du dernier élément focusable vers le premier (le bouton fermer, en tête du dialogue)', async () => {
        await mountOpenModal();

        const buttons = dialogButtons();
        const last = buttons[buttons.length - 1];
        last.focus();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(buttons[0]);
    });

    it('boucle Maj+Tab du premier élément focusable (bouton fermer) vers le dernier', async () => {
        await mountOpenModal();

        const buttons = dialogButtons();
        const first = buttons[0];
        first.focus();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(buttons[buttons.length - 1]);
    });

    it('ne laisse jamais le focus s\'échapper vers un élément externe après de nombreuses tabulations', async () => {
        await mountOpenModal();

        const dialog = document.querySelector('[role="dialog"]');
        for (let i = 0; i < 15; i += 1) {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
        }
        await wrapper.vm.$nextTick();

        expect(document.activeElement).not.toBe(trigger);
        expect(dialog.contains(document.activeElement)).toBe(true);
    });

    it('se ferme toujours avec Échap (non-régression)', async () => {
        await mountOpenModal();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('restitue le focus à l\'élément déclencheur après la fermeture', async () => {
        await mountOpenModal();
        expect(document.activeElement?.textContent).toBe('Premier');

        await wrapper.setProps({ modelValue: false });
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(trigger);
    });

    it('place le focus sur la modale elle-même (pas sur le bouton fermer) si le contenu n\'a aucun élément focusable', async () => {
        await mountOpenModal('<p>Contenu sans élément focusable</p>');

        const dialog = document.querySelector('[role="dialog"]');
        const closeButton = dialog.querySelector('button[aria-label="Fermer"]');
        expect(document.activeElement).toBe(dialog);
        expect(document.activeElement).not.toBe(closeButton);
    });
});

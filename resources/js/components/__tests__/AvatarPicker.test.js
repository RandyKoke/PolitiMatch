import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import AvatarPicker from '@/components/AvatarPicker.vue';
import { apiAvatars } from '@/services/apiAvatars';

vi.mock('@/services/apiAvatars', () => ({
    apiAvatars: { suggestions: vi.fn() },
}));

function makeAvatars(count = 9) {
    return Array.from({ length: count }, (_, i) => ({
        seed: `seed-${i}`,
        url: `https://api.dicebear.com/9.x/avataaars/svg?seed=seed-${i}`,
    }));
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('AvatarPicker', () => {
    it('fetches suggestions on mount and renders one button per avatar', async () => {
        apiAvatars.suggestions.mockResolvedValue({ data: { avatars: makeAvatars(9) } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: null } });
        await flushPromises();

        const buttons = wrapper.findAll('button[aria-pressed]');
        expect(buttons).toHaveLength(9);
        expect(apiAvatars.suggestions).toHaveBeenCalledTimes(1);
    });

    /**
     * Comportement de repli explicitement demandé : si rien n'est
     * sélectionné, le premier avatar de la grille est retenu automatiquement
     * dès qu'elle charge, pour que l'inscription ne soit jamais bloquée par
     * cette étape (même sans interaction de l'utilisateur avec la grille).
     */
    it('auto-selects the first avatar once loaded when no value is set yet', async () => {
        const avatars = makeAvatars(9);
        apiAvatars.suggestions.mockResolvedValue({ data: { avatars } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: null } });
        await flushPromises();

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([avatars[0].seed]);
    });

    it('does not override an already-selected seed once suggestions load', async () => {
        const avatars = makeAvatars(9);
        apiAvatars.suggestions.mockResolvedValue({ data: { avatars } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: 'already-chosen' } });
        await flushPromises();

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    /**
     * Chaque tuile est un vrai <button type="button"> : la navigation par
     * tabulation et la sélection par Entrée/Espace exigées par le cahier
     * des charges sont alors natives, pas un comportement à réimplémenter.
     */
    it('renders each avatar tile as a native, keyboard-operable button', async () => {
        apiAvatars.suggestions.mockResolvedValue({ data: { avatars: makeAvatars(3) } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: null } });
        await flushPromises();

        const buttons = wrapper.findAll('button[aria-pressed]');
        buttons.forEach((button) => {
            expect(button.element.tagName).toBe('BUTTON');
            expect(button.attributes('type')).toBe('button');
        });
    });

    it('emits the clicked seed and reflects the selection via aria-pressed', async () => {
        const avatars = makeAvatars(3);
        apiAvatars.suggestions.mockResolvedValue({ data: { avatars } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: avatars[0].seed } });
        await flushPromises();

        const buttons = wrapper.findAll('button[aria-pressed]');
        expect(buttons[0].attributes('aria-pressed')).toBe('true');
        expect(buttons[1].attributes('aria-pressed')).toBe('false');

        await buttons[1].trigger('click');

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([avatars[1].seed]);
    });

    /**
     * Aucun avatar n'est mis en avant avant interaction : sans sélection
     * (parent qui ignorerait le repli), aucune tuile ne doit porter
     * aria-pressed="true" à tort — le seul état "actif" possible correspond
     * toujours à modelValue.
     */
    it('marks no tile as pressed when modelValue matches none of the loaded avatars', async () => {
        apiAvatars.suggestions.mockResolvedValue({ data: { avatars: makeAvatars(3) } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: 'not-in-the-grid' } });
        await flushPromises();

        const buttons = wrapper.findAll('button[aria-pressed]');
        buttons.forEach((button) => expect(button.attributes('aria-pressed')).toBe('false'));
    });

    it('shows a retry action and never crashes when the suggestions request fails', async () => {
        apiAvatars.suggestions.mockRejectedValue({ response: { status: 500 } });
        const wrapper = mount(AvatarPicker, { props: { modelValue: null } });
        await flushPromises();

        expect(wrapper.text()).toContain('Réessayer');

        apiAvatars.suggestions.mockResolvedValue({ data: { avatars: makeAvatars(3) } });
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('button[aria-pressed]')).toHaveLength(3);
    });
});

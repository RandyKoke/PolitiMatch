<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const dialogRef = ref(null);
const contentRef = ref(null);

// Élément ayant déclenché l'ouverture (bouton, etc.) : le focus doit lui
// revenir à la fermeture, plutôt que de retomber sur <body> comme le
// comportement par défaut du navigateur le ferait.
let triggerElement = null;

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

function getFocusableElements(root) {
    if (!root) {
        return [];
    }
    return Array.from(root.querySelectorAll(FOCUSABLE_SELECTOR));
}

function close() {
    emit('update:modelValue', false);
}

// Piège de focus : Tab et Maj+Tab restent confinés à l'intérieur de la
// modale pendant qu'elle est ouverte (bouclage manuel du dernier élément
// focusable vers le premier et inversement). Implémentation manuelle légère
// plutôt qu'une librairie dédiée (focus-trap) : le besoin se limite à deux
// modales à contenu simple sur tout le projet, une dépendance supplémentaire
// n'apporterait rien ici.
function trapFocus(event) {
    const focusables = getFocusableElements(dialogRef.value);
    if (focusables.length === 0) {
        event.preventDefault();
        return;
    }
    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

// Fermeture au clavier (Échap) : exigée par l'accessibilité clavier du
// cahier des charges §14, pas seulement le clic sur l'overlay.
function onKeydown(event) {
    if (!props.modelValue) {
        return;
    }
    if (event.key === 'Escape') {
        close();
    } else if (event.key === 'Tab') {
        trapFocus(event);
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

// Empêche le scroll de la page derrière la modale ouverte, positionne le
// focus initial à l'ouverture et le restitue au déclencheur à la fermeture.
// Focus initial volontairement cherché dans le CONTENU (slot) plutôt que
// sur l'ensemble de la modale : sinon le premier élément focusable trouvé
// serait systématiquement le bouton de fermeture (icône croix, qui précède
// le contenu dans le DOM), un choix peu utile pour l'utilisateur qui vient
// d'ouvrir la modale pour interagir avec son contenu, pas pour la refermer
// aussitôt. Le bouton de fermeture reste bien sûr atteignable au Tab
// suivant, et le piège de focus ci-dessus (trapFocus) couvre bien toute la
// modale, lui, pas seulement le contenu.
watch(
    () => props.modelValue,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            triggerElement = document.activeElement;
            await nextTick();
            const [first] = getFocusableElements(contentRef.value);
            (first ?? dialogRef.value)?.focus();
        } else {
            triggerElement?.focus?.();
            triggerElement = null;
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition name="pm-modal-fade">
            <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <div class="fixed inset-0 bg-gray-900/50" @click="close" />
                <div
                    ref="dialogRef"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title || undefined"
                    tabindex="-1"
                    class="relative z-10 w-full max-w-md rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl focus:outline-none"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 v-if="title" class="font-display text-lg font-semibold text-ink">{{ title }}</h2>
                        <button
                            type="button"
                            class="ml-auto rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                            aria-label="Fermer"
                            @click="close"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <div ref="contentRef">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.pm-modal-fade-enter-active,
.pm-modal-fade-leave-active {
    transition: opacity 0.15s ease;
}
.pm-modal-fade-enter-from,
.pm-modal-fade-leave-to {
    opacity: 0;
}
</style>

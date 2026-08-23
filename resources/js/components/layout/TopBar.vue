<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const router = useRouter();
const authStore = useAuthStore();
const { user, isAuthenticated } = storeToRefs(authStore);

const menuOpen = ref(false);
const menuButtonRef = ref(null);
const menuRef = ref(null);

function closeMenu({ returnFocus = false } = {}) {
    if (!menuOpen.value) {
        return;
    }
    menuOpen.value = false;
    if (returnFocus) {
        menuButtonRef.value?.focus();
    }
}

// Clic en dehors du menu : ferme sans jamais intercepter le clic lui-même
// (écouteur en phase de bulle sur document, comme le reste de l'app pour ce
// genre de motif, cf. PmModal.vue pour Échap) — le gestionnaire natif de
// l'élément réellement cliqué s'exécute normalement avant que celui-ci ne
// s'exécute, rien n'est bloqué. Exclusion explicite des clics sur le bouton
// déclencheur lui-même : sans elle, le clic qui vient d'OUVRIR le menu
// serait aussi capturé par cet écouteur et le refermerait aussitôt.
function onDocumentClick(event) {
    if (menuButtonRef.value?.contains(event.target) || menuRef.value?.contains(event.target)) {
        return;
    }
    closeMenu();
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape' && menuOpen.value) {
        closeMenu({ returnFocus: true });
    }
}

// Écouteurs attachés seulement pendant que le menu est ouvert (pas en
// permanence dès le montage du header) : conforme au principe déjà en place
// sur ce projet (cf. PmModal.vue).
watch(menuOpen, (open) => {
    if (open) {
        document.addEventListener('click', onDocumentClick);
        document.addEventListener('keydown', onDocumentKeydown);
    } else {
        document.removeEventListener('click', onDocumentClick);
        document.removeEventListener('keydown', onDocumentKeydown);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});

/**
 * authStore.logout() réinitialise déjà `user`/`isAuthenticated` dans son
 * bloc `finally`, donc l'état est garanti à jour AVANT ce push (jamais de
 * flash intermédiaire) — mais sans navigation explicite, l'utilisateur
 * restait auparavant sur la route actuelle (ex. /dashboard) : le contenu
 * privé ne disparaissait jamais, seul le header réagissait (isAuthenticated
 * y est branché en direct via storeToRefs), puisqu'aucune transition de
 * route ne se produisait pour redonner la main au garde `requiresAuth`
 * (router/index.js). Redirection vers l'accueil (`/`, guestOnly) plutôt que
 * /auth/login : cohérent avec le reste du parcours, qui traite déjà "/"
 * comme l'écran neutre pour un visiteur non connecté (le lien "Se
 * connecter" du header reste à portée immédiate depuis là).
 */
async function handleLogout() {
    menuOpen.value = false;
    await authStore.logout();
    router.push({ name: 'home' });
}
</script>

<template>
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-lg items-center justify-between px-4">
            <router-link to="/" class="flex items-center gap-2 font-display font-semibold text-ink">
                <!-- Chip noir + "P" or, liseré tricolore en bordure basse, pas
                     un simple rond de couleur unie. -->
                <span class="relative flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-ink text-sm text-brand-500" aria-hidden="true">
                    P
                    <span class="absolute inset-x-0 bottom-0 h-[3px]" style="background: var(--gradient-tricolore);" />
                </span>
                <span>PolitiMatch</span>
            </router-link>

            <div class="flex items-center gap-3">
                <!-- Annuaire des partis (module Fiches Partis) : visible et
                     accessible à tout visiteur, sans compte ni quiz déjà
                     entamé, le seul point d'entrée vers les fiches partis qui
                     ne dépende pas d'un résultat ou du comparateur. Traité
                     comme un lien tertiaire discret (gris neutre, pas de mise
                     en avant), séparateur vertical et espacement le
                     distinguant de "Se connecter" ci-dessous, deux
                     affordances différentes (annuaire de contenu vs accès
                     compte). Scintillement discret : couleurs de base
                     surchargées en gris (--pm-shimmer-base/-hover) plutôt que
                     le bordeaux par défaut de .pm-shimmer-text, cohérent avec
                     le traitement tertiaire de ce lien. -->
                <router-link
                    to="/parties"
                    class="pm-shimmer-text text-sm font-medium text-stone-500 hover:text-stone-700"
                    style="--pm-shimmer-base: var(--color-stone-500); --pm-shimmer-hover: var(--color-stone-700)"
                >
                    Les partis
                </router-link>

                <span v-if="!isAuthenticated" class="h-4 w-px bg-stone-200" aria-hidden="true" />

                <div v-if="isAuthenticated" class="relative">
                    <button
                        ref="menuButtonRef"
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-medium text-bordeaux-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink"
                        :aria-expanded="menuOpen"
                        aria-haspopup="menu"
                        :aria-label="`Menu de ${user?.username ?? 'votre compte'}`"
                        @click="menuOpen = !menuOpen"
                    >
                        {{ (user?.username ?? '?').charAt(0).toUpperCase() }}
                    </button>

                    <div
                        v-if="menuOpen"
                        ref="menuRef"
                        role="menu"
                        class="absolute right-0 mt-2 w-44 rounded-xl border border-stone-200 bg-white py-1 shadow-lg"
                    >
                        <router-link
                            to="/dashboard"
                            role="menuitem"
                            class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50"
                            @click="closeMenu()"
                        >
                            Tableau de bord
                        </router-link>
                        <button
                            type="button"
                            role="menuitem"
                            class="block w-full px-4 py-2 text-left text-sm text-stone-700 hover:bg-stone-50"
                            @click="handleLogout"
                        >
                            Se déconnecter
                        </button>
                    </div>
                </div>

                <!--
                    "Se connecter" et non "Commencer" ici : la page d'accueil a
                    déjà son propre gros bouton "Commencer" vers /start — dupliquer
                    le même intitulé à deux endroits de l'écran (un au centre, un
                    en haut à droite) a été signalé comme source de confusion
                    réelle (deux CTA identiques, résultats différents perçus).
                    Ce lien du header sert un usage différent et complémentaire :
                    accès rapide à la connexion depuis n'importe quel écran de
                    l'app, pas seulement depuis l'accueil.
                -->
                <router-link v-else to="/auth/login" class="text-sm font-medium text-bordeaux-600 hover:text-bordeaux-700">
                    Se connecter
                </router-link>
            </div>
        </div>

        <!-- Liseré tricolore, structurel : le séparateur du header sur toutes
             les pages. 8px : en dessous, le bordeaux, plus sombre et désaturé
             que le rouge du drapeau, reste à peine perceptible à l'œil nu, en
             particulier accolé au noir. Reste proportionné (moins de 15% des
             56px du header) sans devenir un bandeau dominant. -->
        <div class="h-2 w-full" style="background: var(--gradient-tricolore);" aria-hidden="true" />
    </header>
</template>

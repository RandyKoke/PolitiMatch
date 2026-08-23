<script setup>
import { storeToRefs } from 'pinia';
import { useUiStore } from '@/stores/uiStore';
import TopBar from '@/components/layout/TopBar.vue';
import ToastContainer from '@/components/layout/ToastContainer.vue';
import PmLoader from '@/components/ui/PmLoader.vue';

const uiStore = useUiStore();
const { globalLoading } = storeToRefs(uiStore);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-cream">
        <TopBar />

        <!-- max-w-lg : contenu centré mobile-first (cahier des charges §7.1),
             s'élargit sobrement sur desktop sans jamais devenir une mise en
             page "bureau" à trois colonnes. -->
        <main class="mx-auto w-full max-w-lg flex-1 px-4 py-6">
            <router-view v-slot="{ Component, route }">
                <Transition name="pm-view-fade" mode="out-in">
                    <component :is="Component" :key="route.path" />
                </Transition>
            </router-view>
        </main>

        <div v-if="globalLoading" class="fixed inset-0 z-[90] flex items-center justify-center bg-white/70">
            <PmLoader label="Chargement" />
        </div>

        <ToastContainer />
    </div>
</template>

<style scoped>
/* Transition de vue volontairement discrète (cahier des charges §7 :
   animations légères, jamais lourdes ou distrayantes). */
.pm-view-fade-enter-active,
.pm-view-fade-leave-active {
    transition: opacity 0.12s ease;
}
.pm-view-fade-enter-from,
.pm-view-fade-leave-to {
    opacity: 0;
}
</style>

<script setup>
import { storeToRefs } from 'pinia';
import { useUiStore } from '@/stores/uiStore';

const uiStore = useUiStore();
const { toasts } = storeToRefs(uiStore);

const variantClasses = {
    info: 'bg-gray-900 text-white',
    success: 'bg-vote-agree text-white',
    error: 'bg-danger-500 text-white',
};
</script>

<template>
    <!-- aria-live="polite" : les messages système (erreurs réseau, succès de
         partage...) doivent être annoncés par un lecteur d'écran sans voler
         le focus (cahier des charges §14). -->
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[100] flex flex-col items-center gap-2 px-4" aria-live="polite" role="status">
        <TransitionGroup name="pm-toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto max-w-sm rounded-xl px-4 py-3 text-sm shadow-lg"
                :class="variantClasses[toast.variant] || variantClasses.info"
            >
                {{ toast.message }}
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.pm-toast-enter-active,
.pm-toast-leave-active {
    transition: all 0.2s ease;
}
.pm-toast-enter-from,
.pm-toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>

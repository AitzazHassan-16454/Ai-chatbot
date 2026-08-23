<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-sm neu-card oc-fade-in">
            <div class="flex items-center gap-2.5 px-5 pt-5 pb-1">
                <div class="w-8 h-8 rounded-lg bg-oc-error/15 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-arrow-right-from-bracket text-oc-error text-[13px]"></i>
                </div>
                <h2 class="text-[15px] font-semibold text-oc-text">Sign out</h2>
            </div>

            <div class="px-5 py-4">
                <p class="text-[13px] text-oc-muted leading-5">
                    Are you sure you want to sign out{{ name ? ', ' + name : '' }}? You can sign back in anytime.
                </p>
            </div>

            <div class="flex gap-2.5 px-5 pb-5">
                <button type="button" @click="$emit('close')" class="oc-btn oc-btn-ghost flex-1">Cancel</button>
                <button type="button" @click="handleConfirm" class="oc-btn flex-1 bg-oc-error text-white hover:bg-oc-error/80 transition cursor-pointer">
                    <i v-if="signingOut" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                    {{ signingOut ? 'Signing out...' : 'Sign out' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    name: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const signingOut = ref(false);

watch(
    () => props.show,
    (open) => {
        if (open) signingOut.value = false;
    }
);

function handleConfirm() {
    signingOut.value = true;
    emit('confirm');
}
</script>

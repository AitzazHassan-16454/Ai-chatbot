<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close"></div>

            <div class="relative bg-oc-surface border border-oc-border-strong rounded-xl w-full max-w-md shadow-2xl">
                <button
                    @click="close"
                    class="absolute top-4 right-4 w-8 h-8 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark text-[14px] text-oc-muted"></i>
                </button>

                <div class="p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center">
                            <i class="fa-solid fa-comment-dots text-oc-primary text-[15px]"></i>
                        </div>
                        <div>
                            <h3 class="text-[15px] font-medium text-oc-text">Share Feedback</h3>
                            <p class="text-[11px] text-oc-muted">Help us improve your experience</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitFeedback" class="space-y-4">
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Category</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="cat in categories"
                                    :key="cat.value"
                                    type="button"
                                    @click="form.category = cat.value"
                                    class="transition cursor-pointer"
                                    :class="form.category === cat.value
                                        ? 'oc-chip-active'
                                        : 'oc-chip hover:bg-oc-hover hover:text-oc-text'"
                                >
                                    {{ cat.label }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Rating (optional)</label>
                            <div class="flex gap-2">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    @click="form.rating = star"
                                    class="w-9 h-9 rounded-lg flex items-center justify-center transition cursor-pointer"
                                    :class="form.rating >= star
                                        ? 'bg-amber-400/15 text-amber-400'
                                        : 'bg-oc-raised border border-oc-border text-oc-faint hover:text-oc-text'"
                                >
                                    <i class="fa-solid fa-star text-[12px]"></i>
                                </button>
                                <button
                                    v-if="form.rating"
                                    type="button"
                                    @click="form.rating = null"
                                    class="px-2 text-[11px] text-oc-muted hover:text-oc-text transition"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Your feedback</label>
                            <textarea
                                v-model="form.message"
                                rows="4"
                                maxlength="2000"
                                placeholder="Tell us what you think, report a bug, or suggest a feature..."
                                class="oc-input resize-none"
                            ></textarea>
                            <div class="text-right text-[10px] text-oc-faint mt-1">
                                {{ form.message.length }}/2000
                            </div>
                        </div>

                        <div v-if="error" class="bg-oc-error/10 border border-oc-error/25 rounded-lg p-3">
                            <p class="text-[12px] text-oc-error">{{ error }}</p>
                        </div>

                        <div v-if="success" class="bg-oc-success/10 border border-oc-success/25 rounded-lg p-3">
                            <p class="text-[12px] text-oc-success">Thank you! Your feedback has been submitted.</p>
                        </div>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="close"
                                class="oc-btn oc-btn-ghost flex-1"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="!form.message || form.message.length < 10 || submitting"
                                class="oc-btn oc-btn-primary flex-1"
                            >
                                <span v-if="submitting">Sending...</span>
                                <span v-else>Submit</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';

var props = defineProps({
    show: Boolean,
    trigger: String,
});

var emit = defineEmits(['close']);

var page = usePage();
var user = page.props.user;

var submitting = ref(false);
var error = ref('');
var success = ref(false);

var categories = [
    { value: 'general', label: 'General' },
    { value: 'bug', label: 'Bug Report' },
    { value: 'feature', label: 'Feature Request' },
    { value: 'other', label: 'Other' },
];

var form = reactive({
    category: 'general',
    message: '',
    rating: null,
});

watch(() => props.show, function (val) {
    if (val) {
        error.value = '';
        success.value = false;
        form.category = 'general';
        form.message = '';
        form.rating = null;
    }
});

function close() {
    emit('close');
}

async function submitFeedback() {
    if (!form.message || form.message.length < 10) return;

    submitting.value = true;
    error.value = '';
    success.value = false;

        try {
            var payload = {
                category: form.category,
                message: form.message,
                page_context: props.trigger || 'sign-out',
            };

            if (form.rating) payload.rating = form.rating;
            await axios.post('/feedback', payload, {
                headers: {
                    'Accept': 'application/json',
                },
            });
            success.value = true;

            setTimeout(function () {
                close();
            }, 1500);
        } catch (err) {
        if (err.response && err.response.data && err.response.data.errors) {
            var errors = err.response.data.errors;
            error.value = Object.values(errors)[0][0] || 'Something went wrong.';
        } else {
            error.value = 'Failed to submit feedback. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

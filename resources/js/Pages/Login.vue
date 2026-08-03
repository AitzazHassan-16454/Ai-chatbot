<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-xl bg-oc-raised border border-oc-border flex items-center justify-center mx-auto mb-5">
                    <NovaIcon :size="30" />
                </div>
                <h1 class="text-[22px] font-semibold text-oc-text tracking-tight">Welcome back</h1>
                <p class="text-[13px] text-oc-muted mt-1.5">Sign in to <span class="text-oc-primary-strong font-medium">Nova</span></p>
            </div>

            <div class="bg-oc-surface border border-oc-border rounded-xl p-6 sm:p-7">
                <form @submit.prevent="submit" class="space-y-4">
                    <div v-if="form.errors.email" class="bg-oc-error/10 border border-oc-error/25 text-oc-error text-[13px] px-4 py-3 rounded-lg">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5 text-[11px]"></i>{{ form.errors.email }}
                    </div>

                    <div>
                        <label class="block text-[12px] text-oc-muted mb-1.5 ml-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="oc-input"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div>
                        <label class="block text-[12px] text-oc-muted mb-1.5 ml-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="oc-input"
                            placeholder="Your password"
                        >
                    </div>

                    <button type="submit" :disabled="form.processing" class="oc-btn oc-btn-primary w-full py-3">
                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-[13px] text-oc-muted mt-6">
                Don't have an account?
                <a href="/register" class="text-oc-primary-strong font-medium hover:underline">Create one</a>
            </p>

            <div class="text-center mt-5">
                <a href="/" class="oc-btn oc-btn-ghost w-full">Continue as guest</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import NovaIcon from '@/Components/NovaIcon.vue';

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/login');
}
</script>

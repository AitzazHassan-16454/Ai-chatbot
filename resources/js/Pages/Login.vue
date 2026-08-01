<template>
    <div class="min-h-screen flex items-center justify-center bg-transparent px-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="relative w-16 h-16 rounded-2xl glass flex items-center justify-center mx-auto mb-5 nova-glow">
                    <div class="absolute inset-0 rounded-2xl nova-gradient opacity-20 blur-lg"></div>
                    <div class="relative">
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="url(#novaG1)" aria-hidden="true">
                            <defs>
                                <linearGradient id="novaG1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#818cf8"/>
                                    <stop offset="55%" stop-color="#c084fc"/>
                                    <stop offset="100%" stop-color="#f472b6"/>
                                </linearGradient>
                            </defs>
                            <path d="M12 1.8c.62 4.02 2.18 5.58 6.2 6.2-4.02.62-5.58 2.18-6.2 6.2-.62-4.02-2.18-5.58-6.2-6.2 4.02-.62 5.58-2.18 6.2-6.2z"/>
                            <path d="M18.8 14.5c.27 1.75.95 2.43 2.7 2.7-1.75.27-2.43.95-2.7 2.7-.27-1.75-.95-2.43-2.7-2.7 1.75-.27 2.43-.95 2.7-2.7z" opacity="0.85"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-[22px] font-semibold text-[#eef1f8] tracking-tight">Welcome back</h1>
                <p class="text-[13px] text-[#94a3b8] mt-1.5">Sign in to <span class="nova-gradient-text font-medium">Nova</span></p>
            </div>

            <div class="glass-strong rounded-2xl p-6 sm:p-7 border-white/[0.1]">
                <form @submit.prevent="submit" class="space-y-4">
                    <div v-if="form.errors.email" class="bg-red-500/10 border border-red-500/25 text-red-400 text-[13px] px-4 py-3 rounded-xl">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5 text-[11px]"></i>{{ form.errors.email }}
                    </div>

                    <div>
                        <label class="block text-[12px] text-[#94a3b8] mb-1.5 ml-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="nova-input"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div>
                        <label class="block text-[12px] text-[#94a3b8] mb-1.5 ml-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="nova-input"
                            placeholder="Your password"
                        >
                    </div>

                    <button type="submit" :disabled="form.processing" class="nova-btn-primary w-full py-3">
                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-[13px] text-[#94a3b8] mt-6">
                Don't have an account?
                <a href="/register" class="nova-gradient-text font-medium hover:underline">Create one</a>
            </p>

            <div class="text-center mt-5">
                <a href="/" class="nova-btn-ghost w-full">Continue as guest</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/login');
}
</script>

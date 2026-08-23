<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-10 bg-oc-bg">
        <div class="fixed top-4 right-4 z-50">
            <button
                @click="toggleTheme"
                type="button"
                class="w-9 h-9 rounded-xl neu-btn !p-0 flex items-center justify-center text-oc-muted hover:text-oc-text transition"
                :title="isDarkMode ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
            >
                <i :class="isDarkMode ? 'fa-solid fa-sun text-amber-400' : 'fa-solid fa-moon text-indigo-500'" class="text-[13px]"></i>
            </button>
        </div>

        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl neu-card flex items-center justify-center mx-auto mb-5">
                    <NovaIcon :size="32" />
                </div>
                <h1 class="text-[24px] font-bold text-oc-text tracking-tight">Create your account</h1>
                <p class="text-[13px] text-oc-muted mt-1.5">Start chatting with <span class="text-oc-primary font-semibold">Nova AI</span></p>
            </div>

            <div class="neu-card p-6 sm:p-8">
                <form @submit.prevent="submit" class="space-y-4">
                    <div v-if="form.errors.email" class="bg-oc-error/10 border border-oc-error/25 text-oc-error text-[13px] px-4 py-3 rounded-xl">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5 text-[11px]"></i>{{ form.errors.email }}
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-oc-muted mb-2 ml-1">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="oc-input"
                            placeholder="Your name"
                        >
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-oc-muted mb-2 ml-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="oc-input"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-oc-muted mb-2 ml-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="oc-input"
                            placeholder="Min 6 characters"
                        >
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-oc-muted mb-2 ml-1">Confirm password</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            class="oc-input"
                            placeholder="Repeat your password"
                        >
                    </div>

                    <button type="submit" :disabled="form.processing" class="neu-btn neu-btn-primary w-full py-3 text-[14px]">
                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                        {{ form.processing ? 'Creating...' : 'Create account' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-[13px] text-oc-muted mt-6">
                Already have an account?
                <a href="/login" class="text-oc-primary font-medium hover:underline ml-1">Sign in</a>
            </p>

            <div class="text-center mt-5">
                <a href="/" class="neu-btn w-full py-2.5">Continue as guest</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NovaIcon from '@/Components/NovaIcon.vue';

const isDarkMode = ref(typeof localStorage !== 'undefined' ? localStorage.getItem('theme') !== 'light' : true);

function toggleTheme() {
    isDarkMode.value = !isDarkMode.value;
    const theme = isDarkMode.value ? 'dark' : 'light';
    localStorage.setItem('theme', theme);
    if (theme === 'light') {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
    }
}

onMounted(() => {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    if (savedTheme === 'light') {
        isDarkMode.value = false;
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
    } else {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
    }
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register');
}
</script>

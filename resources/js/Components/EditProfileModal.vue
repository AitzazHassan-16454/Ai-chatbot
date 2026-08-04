<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-md bg-oc-surface border border-oc-border rounded-xl shadow-2xl oc-fade-in">
            <div class="flex items-center justify-between px-5 py-4 border-b border-oc-border">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center">
                        <i class="fa-solid fa-user-pen text-oc-primary text-[13px]"></i>
                    </div>
                    <h2 class="text-[15px] font-semibold text-oc-text">Edit profile</h2>
                </div>
                <button @click="$emit('close')" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer">
                    <i class="fa-solid fa-xmark text-[13px] text-oc-muted"></i>
                </button>
            </div>

            <form @submit.prevent="saveProfile" class="p-5 space-y-5">
                <div v-if="editMsg" class="bg-oc-success/10 border border-oc-success/25 rounded-lg px-3 py-2 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-oc-success text-[11px]"></i>
                    <p class="text-[12px] text-oc-success">{{ editMsg }}</p>
                </div>
                <div v-if="editError" class="bg-oc-error/10 border border-oc-error/25 rounded-lg px-3 py-2 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-oc-error text-[11px]"></i>
                    <p class="text-[12px] text-oc-error">{{ editError }}</p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative w-16 h-16 shrink-0">
                        <div class="w-16 h-16 rounded-full bg-oc-raised border border-oc-border flex items-center justify-center overflow-hidden">
                            <img v-if="avatarPreview || user.avatar_url" :src="avatarPreview || user.avatar_url" class="w-full h-full object-cover" alt="Avatar" />
                            <span v-else class="text-[22px] font-semibold text-oc-primary-strong">{{ user.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <button
                            type="button"
                            @click="$refs.avatarInput.click()"
                            class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-oc-primary text-oc-bg flex items-center justify-center border-2 border-oc-surface hover:bg-oc-primary-strong transition cursor-pointer"
                            title="Upload photo"
                        >
                            <i class="fa-solid fa-camera text-[10px]"></i>
                        </button>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium text-oc-text">Profile photo</p>
                        <p class="text-[11px] text-oc-muted">JPG, PNG, WebP or GIF. Max 2MB.</p>
                        <div class="flex gap-2 mt-1.5">
                            <button type="button" @click="$refs.avatarInput.click()" class="text-[12px] text-oc-primary hover:underline cursor-pointer">Upload</button>
                            <span v-if="user.avatar_url" class="text-oc-border-strong">·</span>
                            <button v-if="user.avatar_url" type="button" @click="removeAvatar" class="text-[12px] text-oc-error hover:underline cursor-pointer">Remove</button>
                        </div>
                    </div>
                    <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="handleAvatarSelect" />
                </div>

                <div>
                    <label class="block text-[12px] text-oc-muted mb-1.5">Full name</label>
                    <input v-model="profileForm.name" type="text" required class="oc-input" />
                </div>

                <div>
                    <label class="block text-[12px] text-oc-muted mb-1.5">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-oc-faint">@</span>
                        <input v-model="profileForm.username" type="text" placeholder="username" class="oc-input pl-7" />
                    </div>
                    <p class="text-[10px] text-oc-faint mt-1">Letters, numbers, dots and underscores. Optional.</p>
                </div>

                <div class="flex gap-2.5 pt-1">
                    <button type="button" @click="$emit('close')" class="oc-btn oc-btn-ghost flex-1">Cancel</button>
                    <button type="submit" :disabled="savingProfile" class="oc-btn oc-btn-primary flex-1">
                        <i v-if="savingProfile" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                        {{ savingProfile ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    user: { type: Object, required: true },
});

const emit = defineEmits(['close', 'updated']);

const savingProfile = ref(false);
const editMsg = ref('');
const editError = ref('');
const avatarPreview = ref(null);

const profileForm = reactive({
    name: props.user.name || '',
    username: props.user.username || '',
});

watch(
    () => props.show,
    (open) => {
        if (open) {
            profileForm.name = props.user.name || '';
            profileForm.username = props.user.username || '';
            avatarPreview.value = null;
            editMsg.value = '';
            editError.value = '';
        }
    }
);

function handleAvatarSelect(event) {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file) return;
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
        editError.value = 'Only JPG, PNG, WebP or GIF images are accepted.';
        return;
    }
    editError.value = '';
    const reader = new FileReader();
    reader.onload = (e) => {
        avatarPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
    uploadAvatar(file);
}

async function uploadAvatar(file) {
    const formData = new FormData();
    formData.append('avatar', file);
    try {
        const response = await axios.post('/profile/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        emit('updated', {
            avatar_url: response.data.avatar_url,
            has_avatar: true,
        });
    } catch (err) {
        editError.value = err.response?.data?.errors?.avatar?.[0] || err.response?.data?.error || 'Failed to upload photo.';
        avatarPreview.value = null;
    }
}

async function removeAvatar() {
    editError.value = '';
    try {
        await axios.delete('/profile/avatar');
        emit('updated', { avatar_url: null, has_avatar: false });
        avatarPreview.value = null;
    } catch (err) {
        editError.value = 'Failed to remove photo.';
    }
}

async function saveProfile() {
    editMsg.value = '';
    editError.value = '';
    savingProfile.value = true;
    try {
        const response = await axios.put('/profile', {
            name: profileForm.name,
            username: profileForm.username,
        });
        emit('updated', {
            name: response.data.user.name,
            username: response.data.user.username,
        });
        editMsg.value = 'Profile updated!';
        setTimeout(() => emit('close'), 700);
    } catch (err) {
        editError.value = err.response?.data?.errors?.username?.[0] || err.response?.data?.errors?.name?.[0] || err.response?.data?.error || 'Failed to update profile.';
    } finally {
        savingProfile.value = false;
    }
}
</script>

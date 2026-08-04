<template>
    <div class="h-screen w-screen flex bg-oc-bg text-oc-text overflow-hidden font-sans">

        <div
            v-if="sidebarOpen && isMobile"
            class="fixed inset-0 bg-black/60 z-40 md:hidden"
            @click="sidebarOpen = false"
        ></div>

        <div class="flex-1 flex flex-col min-w-0">

            <header class="shrink-0 h-12 px-4 sm:px-6 border-b border-oc-border flex items-center">
                <div class="max-w-3xl mx-auto w-full flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <button
                            v-if="user"
                            class="md:hidden w-7 h-7 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer shrink-0"
                            :title="'Show sessions'"
                            @click="sidebarOpen = true"
                        >
                            <i class="fa-solid fa-bars text-[14px] text-oc-muted"></i>
                        </button>
                        <div class="flex items-center gap-1.5 text-[13px] min-w-0">
                            <div class="w-6 h-6 rounded-md bg-oc-raised border border-oc-border flex items-center justify-center shrink-0">
                                <NovaIcon :size="13" />
                            </div>
                            <span class="text-oc-text font-semibold tracking-tight">Nova</span>
                            <i class="fa-solid fa-chevron-right text-[9px] text-oc-faint shrink-0"></i>
                            <span class="text-oc-muted truncate">{{ activeConversationTitle }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <div
                            v-if="apiStatus"
                            class="hidden md:flex items-center gap-2 px-2.5 h-7 rounded-lg bg-oc-surface border transition-colors"
                            :class="statusStyles.border"
                        >
                            <div class="w-1.5 h-1.5 rounded-full" :class="statusStyles.dot"></div>
                            <span class="text-[11px]" :class="statusStyles.text">
                                {{ statusStyles.label }}
                            </span>
                        </div>
                        <template v-else>
                            <a href="/login" class="oc-btn oc-btn-ghost">Sign in</a>
                        </template>
                    </div>
                </div>
            </header>

            <div ref="messagesRef" class="flex-1 overflow-y-auto scrollbar-thin">

                <div v-if="!messages.length && !loading" class="h-full flex flex-col items-center justify-center px-5 sm:px-8">
                    <div class="text-center mb-8">
                        <div class="w-12 h-12 mx-auto mb-5 rounded-xl bg-oc-raised border border-oc-border flex items-center justify-center">
                            <NovaIcon :size="24" />
                        </div>
                        <h2 class="text-[22px] sm:text-[26px] font-semibold text-oc-text tracking-tight mb-2">
                            How can <span class="text-oc-primary-strong">Nova</span> help?
                        </h2>
                        <p class="text-[13px] text-oc-muted">
                            {{ user ? 'Ask anything — I think, write, and speak back.' : 'Sign in to save your chat history, or continue as a guest.' }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 w-full max-w-lg">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion.label"
                            @click="useSuggestion(suggestion.prompt)"
                            class="group text-left px-4 py-3.5 rounded-xl bg-oc-surface border border-oc-border hover:bg-oc-hover hover:border-oc-border-strong transition-colors cursor-pointer"
                        >
                            <div class="w-7 h-7 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center mb-2 group-hover:border-oc-border-strong">
                                <i :class="suggestion.icon" class="text-[11px] text-oc-primary"></i>
                            </div>
                            <span class="text-[12px] text-oc-muted group-hover:text-oc-text transition-colors">{{ suggestion.label }}</span>
                        </button>
                    </div>
                </div>

                <div v-else class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
                    <ChatMessage
                        v-for="(msg, i) in messages"
                        :key="i"
                        :msg="msg"
                        :index="i"
                        :streaming="loading && i === messages.length - 1 && msg.role === 'assistant'"
                    />

                    <div v-if="loading && messages[messages.length - 1]?.role === 'assistant' && !messages[messages.length - 1]?.text" class="flex justify-start mb-6 msg-in">
                        <div class="flex items-center gap-2 text-oc-faint text-[13px] font-medium min-h-5">
                            <ProgressDots :size="16" class="text-oc-muted" />
                            <span>Thinking...</span>
                        </div>
                    </div>
                </div>
            </div>

            <StatusBanners :user="user" :messages="messages" :apiStatus="apiStatus" />

            <div class="shrink-0 px-4 sm:px-6 pb-4 sm:pb-5 pt-1">
                <form @submit.prevent="sendMessage" class="max-w-3xl mx-auto" @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false" @drop.prevent="handleDrop">

                    <div
                        class="relative min-h-[96px] w-full overflow-clip rounded-xl bg-oc-surface transition-shadow duration-200 shadow-[0_2px_4px_rgba(0,0,0,0.3),0_1px_2px_rgba(0,0,0,0.3),0_0_0_0.5px_rgba(255,255,255,0.16),0_-0.5px_0_rgba(255,255,255,0.06)] focus-within:shadow-[0_8px_16px_rgba(0,0,0,0.3),0_4px_8px_rgba(0,0,0,0.3),0_0_0_0.5px_rgba(255,255,255,0.16),0_-0.5px_0_rgba(255,255,255,0.06)]"
                        :class="dragActive ? 'border border-oc-primary border-dashed' : ''"
                    >
                        <div v-if="dragActive" class="pointer-events-none absolute inset-0 z-20 grid place-items-center rounded-xl bg-oc-surface/90">
                            <p class="text-[13px] text-oc-text">Drop files to attach</p>
                        </div>

                        <div v-if="pendingFile" class="px-4 pt-3">
                            <div class="relative inline-block">
                                <img v-if="pendingFile.isImage" :src="pendingFile.preview" class="h-16 w-16 object-cover rounded-lg border border-oc-border bg-oc-raised" />
                                <div v-else class="h-16 w-16 rounded-lg bg-oc-raised border border-oc-border flex flex-col items-center justify-center gap-1">
                                    <i :class="pendingFile.icon" class="text-[16px]" :style="{ color: pendingFile.iconColor }"></i>
                                    <p class="text-[8px] text-oc-faint truncate max-w-[58px] px-1">{{ pendingFile.ext }}</p>
                                </div>
                                <button
                                    @click="pendingFile = null"
                                    type="button"
                                    class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-oc-raised border border-oc-border flex items-center justify-center transition cursor-pointer hover:text-oc-error"
                                >
                                    <i class="fa-solid fa-xmark text-[9px] text-oc-text"></i>
                                </button>
                                <div class="absolute bottom-0 left-0 right-0 bg-black/60 rounded-b-lg px-1.5 py-0.5">
                                    <p class="text-[9px] text-oc-text truncate text-center">{{ pendingFile.name }}</p>
                                </div>
                            </div>
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff,application/pdf,text/plain,text/csv"
                            class="hidden"
                            @change="handleFileSelect"
                        >

                        <div class="relative min-h-[60px]">
                            <textarea
                                ref="textareaRef"
                                v-model="input"
                                :disabled="loading || isSystemBusy"
                                rows="1"
                                spellcheck="false"
                                class="relative z-10 block w-full min-h-[60px] max-h-[180px] resize-none overflow-y-auto whitespace-pre-wrap bg-transparent px-4 pt-4 pb-2 text-[13px] font-[440] leading-5 text-oc-text focus:outline-none disabled:opacity-50"
                                @input="autoResize"
                                @keydown="handleKeydown"
                            ></textarea>

                            <div
                                v-if="!input && !pendingFile"
                                class="pointer-events-none absolute inset-x-0 top-0 px-4 pt-4 text-[13px] font-[440] leading-5 text-oc-faint"
                            >
                                {{ isSystemBusy ? 'System busy, try later...' : 'Ask anything...' }}
                            </div>
                        </div>

                        <div class="flex h-11 items-center px-2">
                            <div class="flex min-w-0 flex-1 items-center gap-1">
                                <button
                                    type="button"
                                    @click="$refs.fileInput.click()"
                                    :disabled="loading || isSystemBusy"
                                    class="size-7 rounded-md flex items-center justify-center shrink-0 transition cursor-pointer hover:bg-oc-hover disabled:opacity-40"
                                    :title="isSystemBusy ? 'System busy' : 'Attach files'"
                                >
                                    <i class="fa-solid fa-paperclip text-[13px] text-oc-muted"></i>
                                </button>

                                <div
                                    class="flex h-7 max-w-[220px] items-center gap-1.5 rounded-md px-2 text-[12px] font-[440] text-oc-muted"
                                    :title="activeModel.name"
                                >
                                    <span class="text-oc-primary shrink-0">
                                        <NovaIcon :size="12" />
                                    </span>
                                    <span class="truncate">{{ activeModel.name }}</span>
                                </div>

                                <button
                                    type="button"
                                    @click="toggleRecording"
                                    :disabled="loading || isSystemBusy"
                                    :class="isRecording ? 'bg-oc-error/15 text-oc-error nova-recording' : 'text-oc-primary hover:bg-oc-hover hover:text-oc-primary-strong'"
                                    class="size-7 rounded-md flex items-center justify-center shrink-0 transition cursor-pointer disabled:opacity-40"
                                    :title="isRecording ? 'Stop recording' : 'Dictate your message'"
                                >
                                    <i :class="transcribing ? 'fa-solid fa-spinner fa-spin' : isRecording ? 'fa-solid fa-microphone-lines' : 'fa-solid fa-microphone'" class="text-[13px]"></i>
                                </button>
                            </div>

                            <button
                                v-if="loading"
                                type="button"
                                @click="stopGeneration"
                                class="size-7 rounded-md p-[6px] flex items-center justify-center shrink-0 transition cursor-pointer active:scale-95 bg-oc-raised border border-oc-border text-oc-text hover:border-oc-border-strong"
                                :title="'Stop generating'"
                            >
                                <i class="fa-solid fa-stop text-[11px]"></i>
                            </button>
                            <button
                                v-else
                                type="submit"
                                :disabled="(!input.trim() && !pendingFile) || isSystemBusy"
                                class="size-7 rounded-md p-[6px] flex items-center justify-center shrink-0 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer active:scale-95"
                                :class="(!input.trim() && !pendingFile) || isSystemBusy ? 'bg-oc-raised border border-oc-border text-oc-faint' : 'text-oc-bg shadow-[0_1px_2px_rgba(0,0,0,0.4),0_0_0_0.5px_rgba(255,255,255,0.4),0_-0.5px_0_rgba(255,255,255,0.3)]'"
                                :style="(!input.trim() && !pendingFile) || isSystemBusy ? '' : { backgroundImage: 'linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%), linear-gradient(90deg, var(--color-oc-primary), var(--color-oc-primary))' }"
                                :title="'Send message'"
                            >
                                <i class="fa-solid fa-arrow-up text-[11px]"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="isRecording" class="flex items-center justify-center gap-2 mt-2.5">
                        <span class="w-2 h-2 rounded-full bg-oc-error animate-pulse"></span>
                        <span class="text-[11px] text-oc-error">Recording… speak now</span>
                    </div>
                    <div v-else-if="transcribing" class="flex items-center justify-center gap-2 mt-2.5">
                        <i class="fa-solid fa-spinner fa-spin text-[11px] text-oc-primary-strong"></i>
                        <span class="text-[11px] text-oc-muted">Transcribing…</span>
                    </div>
                    <div v-else-if="sttError" class="text-center text-[11px] text-oc-error mt-2.5">{{ sttError }}</div>
                    <p class="text-center text-[10px] sm:text-[11px] text-oc-faint mt-2.5 sm:mt-3">Nova may display inaccurate info, including about people, so double-check its responses.</p>
                </form>
            </div>
        </div>

        <aside
            v-if="user"
            :class="[
                sidebarOpen ? 'w-[260px]' : isMobile ? 'w-0' : 'w-12',
                isMobile ? 'fixed inset-y-0 left-0 z-50' : 'relative'
            ]"
            class="shrink-0 order-first flex flex-col transition-all duration-300 overflow-hidden bg-oc-surface border-r border-oc-border"
        >
            <div class="h-full flex flex-col min-w-0" :class="sidebarOpen ? 'w-[260px]' : 'w-12'">

                <template v-if="sidebarOpen">
                    <div class="shrink-0 h-12 px-3 border-b border-oc-border flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-6 h-6 rounded-md bg-oc-raised border border-oc-border flex items-center justify-center shrink-0">
                                <NovaIcon :size="13" />
                            </div>
                            <span class="text-[13px] font-semibold text-oc-text tracking-tight truncate">Nova</span>
                        </div>
                        <button
                            @click="sidebarOpen = false"
                            class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer shrink-0"
                            :title="isMobile ? 'Close sidebar' : 'Collapse sidebar'"
                        >
                            <i :class="isMobile ? 'fa-solid fa-xmark' : 'fa-solid fa-chevron-left'" class="text-[12px] text-oc-muted"></i>
                        </button>
                    </div>

                    <div class="shrink-0 space-y-1.5 p-3 pb-2">
                        <button
                            @click="newChat"
                            class="oc-btn oc-btn-primary w-full"
                        >
                            <i class="fa-solid fa-plus text-[11px]"></i>
                            <span>New chat</span>
                        </button>
                        <a href="/profile" class="oc-btn oc-btn-ghost w-full">
                            <i class="fa-solid fa-briefcase text-[11px]"></i>
                            <span>Job Alerts</span>
                        </a>
                    </div>

                    <div class="flex-1 overflow-y-auto scrollbar-thin px-3 pt-1 pb-3">
                        <p v-if="conversations.length" class="px-2 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-oc-faint">Sessions</p>
                        <div v-if="conversations.length === 0" class="text-center text-[12px] text-oc-faint mt-8 px-4">
                            No sessions yet
                        </div>
                        <div v-for="conv in conversations" :key="conv.id">
                            <button
                                @click="loadConversation(conv)"
                                class="group w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] transition-colors cursor-pointer text-left border"
                                :class="activeConversationId === conv.id
                                    ? 'bg-oc-active border-oc-border-strong text-oc-text'
                                    : 'border-transparent text-oc-muted hover:bg-oc-hover hover:text-oc-text'"
                            >
                                <span class="w-1 h-4 rounded-full shrink-0 transition-colors" :class="activeConversationId === conv.id ? 'bg-oc-primary' : 'bg-transparent'"></span>
                                <span class="truncate flex-1">{{ conv.title }}</span>
                                <span
                                    @click.stop="deleteConversation(conv)"
                                    class="shrink-0 opacity-0 group-hover:opacity-100 text-oc-faint hover:text-oc-error transition-all cursor-pointer"
                                >
                                    <i class="fa-solid fa-xmark text-[11px]"></i>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-oc-border p-3 space-y-2">
                        <div class="bg-oc-raised border border-oc-border rounded-lg px-3 py-2.5">
                            <template v-if="hasContext">
                                <div v-if="user.cv_name" class="flex items-center gap-2 min-w-0" :title="user.cv_name">
                                    <i class="fa-solid fa-file-lines text-[10px] text-oc-primary shrink-0"></i>
                                    <span class="text-[11px] text-oc-text truncate">{{ user.cv_name }}</span>
                                </div>
                                <div v-if="user.job_location || salaryLabel" class="flex items-center gap-2 min-w-0">
                                    <i class="fa-solid fa-location-dot text-[10px] text-oc-primary shrink-0"></i>
                                    <span class="text-[11px] text-oc-muted truncate">{{ [user.job_location, salaryLabel].filter(Boolean).join(' · ') }}</span>
                                </div>
                                <div v-if="keywordList.length" class="flex flex-wrap gap-1">
                                    <span v-for="kw in keywordList" :key="kw" class="text-[10px] px-1.5 py-0.5 rounded bg-oc-surface border border-oc-border text-oc-muted">{{ kw }}</span>
                                </div>
                            </template>
                            <p v-else class="text-[11px] text-oc-faint leading-4">
                                No profile context yet.
                                <a href="/profile" class="text-oc-primary hover:underline">Add your CV</a>
                            </p>
                        </div>

                        <div class="bg-oc-raised border border-oc-border rounded-lg px-3 py-2.5 space-y-1">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-oc-muted">Tokens used</span>
                                <span class="text-oc-primary-strong font-semibold tabular-nums">{{ systemTokens.toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-oc-muted border-t border-oc-border pt-1">
                                <span>This session</span>
                                <span class="text-oc-text font-medium tabular-nums">{{ sessionTokens.toLocaleString() }}</span>
                            </div>
                        </div>

                        <div class="relative pt-1">
                            <div v-if="profileMenuOpen" class="fixed inset-0 z-40" @click="profileMenuOpen = false"></div>

                            <div
                                v-if="profileMenuOpen"
                                class="absolute bottom-full left-0 right-0 mb-1.5 z-50 rounded-xl border border-oc-border bg-oc-surface shadow-[0_10px_30px_rgba(0,0,0,0.6)] overflow-hidden oc-menu"
                                style="transform-origin: bottom center"
                                @click.stop
                            >
                                <div class="px-3.5 py-3 border-b border-oc-border">
                                    <p class="text-[13px] font-medium text-oc-text truncate">{{ user.name }}</p>
                                    <p class="text-[11px] text-oc-muted truncate">{{ user.username ? '@' + user.username : user.email }}</p>
                                </div>

                                <div class="py-1.5">
                                    <button @click="openEditProfile" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[13px] text-oc-text hover:bg-oc-hover transition cursor-pointer">
                                        <i class="fa-solid fa-user-pen text-[12px] text-oc-muted w-4 text-center"></i>
                                        Edit profile
                                    </button>
                                    <a href="/profile" @click="profileMenuOpen = false" class="flex items-center gap-2.5 px-3.5 py-2 text-[13px] text-oc-text hover:bg-oc-hover transition cursor-pointer">
                                        <i class="fa-solid fa-file-arrow-up text-[12px] text-oc-muted w-4 text-center"></i>
                                        Upload CV
                                    </a>
                                    <div class="my-1.5 border-t border-oc-border"></div>
                                    <button @click="requestSignOut" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[13px] text-oc-error hover:bg-oc-hover transition cursor-pointer">
                                        <i class="fa-solid fa-arrow-right-from-bracket text-[12px] w-4 text-center"></i>
                                        Sign out
                                    </button>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="profileMenuOpen = !profileMenuOpen"
                                class="w-full flex items-center gap-3 rounded-lg px-2.5 py-2 transition cursor-pointer border"
                                :class="profileMenuOpen ? 'bg-oc-hover border-oc-border-strong' : 'bg-transparent border-transparent hover:bg-oc-hover'"
                            >
                                <div class="w-8 h-8 rounded-md bg-oc-raised border border-oc-border flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="user.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" alt="Profile" />
                                    <span v-else class="text-[12px] font-semibold text-oc-primary-strong">{{ user.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="min-w-0 text-left">
                                    <div class="text-[12px] font-medium text-oc-text truncate">{{ user.name }}</div>
                                    <div class="text-[10px] text-oc-faint truncate">{{ user.email }}</div>
                                </div>
                                <i class="fa-solid fa-chevron-down text-[9px] text-oc-faint ml-auto shrink-0 transition-transform duration-200" :class="profileMenuOpen ? 'rotate-180' : ''"></i>
                            </button>
                        </div>
                    </div>
                </template>

                <div v-else class="flex-1 flex flex-col items-center gap-1 py-3">
                    <button
                        @click="sidebarOpen = true"
                        class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer"
                        title="Expand sidebar"
                    >
                        <i class="fa-solid fa-bars text-[14px] text-oc-muted"></i>
                    </button>
                    <button
                        @click="newChat"
                        class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer"
                        title="New chat"
                    >
                        <i class="fa-solid fa-plus text-[13px] text-oc-muted"></i>
                    </button>
                    <a
                        href="/profile"
                        class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-oc-hover transition cursor-pointer"
                        title="Job Alerts"
                    >
                        <i class="fa-solid fa-briefcase text-[13px] text-oc-muted"></i>
                    </a>
                    <div class="flex-1"></div>
                    <button
                        @click="sidebarOpen = true"
                        class="w-8 h-8 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center hover:bg-oc-hover transition cursor-pointer"
                        :title="user.name"
                    >
                        <span class="text-[11px] font-semibold text-oc-primary-strong">{{ user.name.charAt(0).toUpperCase() }}</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <FeedbackModal
        :show="showFeedbackModal"
        trigger="chat"
        @close="showFeedbackModal = false"
    />

    <EditProfileModal
        :show="showEditProfileModal"
        :user="user"
        @close="showEditProfileModal = false"
        @updated="handleProfileUpdated"
    />

    <SignOutModal
        :show="showSignOutModal"
        :name="user ? user.name : ''"
        @close="showSignOutModal = false"
        @confirm="logout"
    />
</template>

<script setup>
import axios from 'axios';
import { ref, nextTick, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import NovaIcon from '@/Components/NovaIcon.vue';
import ChatMessage from '@/Components/ChatMessage.vue';
import ProgressDots from '@/Components/ProgressDots.vue';
import StatusBanners from '@/Components/StatusBanners.vue';
import FeedbackModal from '@/Components/FeedbackModal.vue';
import EditProfileModal from '@/Components/EditProfileModal.vue';
import SignOutModal from '@/Components/SignOutModal.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const activeModel = { name: 'Gemini 2.5 Flash', provider: 'Google' };

const activeConversationTitle = computed(() => {
    const conv = conversations.value.find((c) => c.id === activeConversationId.value);
    return conv ? conv.title : 'New chat';
});

const liveTokens = ref({ prompt: 0, candidates: 0, total: 0 });
const sessionTokens = ref(0);
const systemTokens = ref(page.props.token_usage?.total_tokens ?? 0);

const keywordList = computed(() => {
    const kw = user.value?.job_keywords;
    if (!kw) return [];
    return String(kw).split(/[\s,]+/).filter(Boolean).slice(0, 6);
});

const salaryLabel = computed(() => {
    const min = user.value?.job_salary_min;
    const max = user.value?.job_salary_max;
    if (!min && !max) return '';
    const fmt = (n) => '$' + Number(n).toLocaleString();
    if (min && max) return `${fmt(min)} – ${fmt(max)}`;
    return min ? fmt(min) : fmt(max);
});

const hasContext = computed(() => {
    const u = user.value || {};
    return !!(u.cv_name || u.job_keywords || u.job_location || u.job_salary_min || u.job_salary_max || u.job_alerts_enabled);
});

const input = ref('');
const messages = ref([]);
const loading = ref(false);
const messagesRef = ref(null);
const textareaRef = ref(null);
const fileInput = ref(null);
const pendingFile = ref(null);
const dragActive = ref(false);
const sidebarOpen = ref(false);
const profileMenuOpen = ref(false);
const conversations = ref([]);
const activeConversationId = ref(null);
const isMobile = ref(false);
const showFeedbackModal = ref(false);
const showEditProfileModal = ref(false);
const showSignOutModal = ref(false);
const messageCount = ref(0);
const feedbackShown = ref(false);
let abortController = null;

const isSpeechSupported = ref(typeof window !== 'undefined' && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia));
const isRecording = ref(false);
const transcribing = ref(false);
const sttError = ref('');
let recordingStream = null;
let audioContext = null;
let scriptProcessor = null;
let sourceNode = null;
let silenceGain = null;
let recordingChunks = [];
let recordingTimer = null;

const defaultStatus = { status: 'healthy', percentage: 0, resets_at: null };
const apiStatus = ref(page.props.api_status || defaultStatus);

const suggestions = [
    { label: 'Explain a concept', prompt: 'Explain quantum computing in simple terms', icon: 'fa-solid fa-lightbulb' },
    { label: 'Write something', prompt: 'Write a short poem about the ocean', icon: 'fa-solid fa-pen-fancy' },
    { label: 'Plan ahead', prompt: 'Plan a productive morning routine', icon: 'fa-solid fa-calendar' },
    { label: 'Solve a problem', prompt: 'Help me debug a JavaScript async/await issue', icon: 'fa-solid fa-code' },
];

const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff', 'application/pdf', 'text/plain', 'text/csv'];

const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];

function friendlyError(status) {
    if (status === 429) return "You've reached the AI request limit. Please wait a minute and try again.";
    if (status === 403) return 'The AI service is rejecting requests right now. Please try again later.';
    if (status === 400) return 'That message was blocked by safety filters. Please rephrase and try again.';
    if (status >= 500) return 'The AI service is temporarily unavailable. Please try again in a moment.';
    return 'Something went wrong. Please try again.';
}

function getFileIcon(mimeType) {
    if (mimeType === 'application/pdf') return { icon: 'fa-solid fa-file-pdf', iconColor: '#f47067', ext: 'PDF' };
    if (mimeType === 'text/plain') return { icon: 'fa-solid fa-file-lines', iconColor: '#7fb4ff', ext: 'TXT' };
    if (mimeType === 'text/csv') return { icon: 'fa-solid fa-file-csv', iconColor: '#4ade80', ext: 'CSV' };
    return { icon: 'fa-solid fa-file', iconColor: '#9c9ca5', ext: 'FILE' };
}

const isSystemBusy = computed(() => apiStatus.value.status === 'exhausted');

const statusStyles = computed(() => {
    const status = apiStatus.value.status;
    const rpmLabel = `${apiStatus.value.rpm || 0}/${apiStatus.value.rpm_limit || 15} RPM`;

    if (status === 'exhausted') return { dot: 'bg-oc-error shadow-[0_0_8px_rgba(244,112,103,0.8)]', border: 'border-oc-error/30', text: 'text-oc-error', label: 'System busy' };
    if (status === 'critical') return { dot: 'bg-orange-400 shadow-[0_0_8px_rgba(251,146,60,0.8)]', border: 'border-orange-400/30', text: 'text-orange-300', label: rpmLabel };
    if (status === 'warning') return { dot: 'bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.8)]', border: 'border-amber-400/30', text: 'text-amber-300', label: rpmLabel };
    return { dot: 'bg-oc-success shadow-[0_0_8px_rgba(74,222,128,0.8)]', border: 'border-oc-border-strong', text: 'text-oc-muted', label: 'System OK' };
});

function autoResize() {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 180) + 'px';
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function stopGeneration() {
    if (abortController) abortController.abort();
}

async function toggleRecording() {
    if (isRecording.value) {
        stopRecording();
    } else {
        await startRecording();
    }
}

async function startRecording() {
    if (transcribing.value || isRecording.value) return;
    if (loading.value) {
        sttError.value = 'Wait for the current response to finish before recording.';
        return;
    }
    if (isSystemBusy.value) {
        sttError.value = 'System busy, try later.';
        return;
    }
    if (!isSpeechSupported.value) {
        sttError.value = window.isSecureContext
            ? 'Voice input is not supported in this browser.'
            : 'Mic access needs HTTPS. Run `valet secure` in the project and open https://ai-chatbot.test, then reload.';
        return;
    }
    sttError.value = '';
    try {
        recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        sourceNode = audioContext.createMediaStreamSource(recordingStream);
        scriptProcessor = audioContext.createScriptProcessor(4096, 1, 1);
        silenceGain = audioContext.createGain();
        silenceGain.gain.value = 0;
        scriptProcessor.onaudioprocess = (event) => {
            recordingChunks.push(new Float32Array(event.inputBuffer.getChannelData(0)));
        };
        sourceNode.connect(scriptProcessor);
        scriptProcessor.connect(silenceGain);
        silenceGain.connect(audioContext.destination);
        isRecording.value = true;
        recordingTimer = setTimeout(stopRecording, 60000);
    } catch (err) {
        sttError.value = 'Microphone access was denied. Allow mic access and try again.';
        cleanupRecording();
    }
}

async function stopRecording() {
    if (!isRecording.value) return;
    isRecording.value = false;

    const chunks = recordingChunks;
    const sampleRate = audioContext ? audioContext.sampleRate : 16000;
    cleanupRecording();

    if (!chunks.length) {
        sttError.value = 'No audio was captured. Please try again.';
        return;
    }

    const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
    const samples = new Float32Array(totalLength);
    let offset = 0;
    for (const chunk of chunks) {
        samples.set(chunk, offset);
        offset += chunk.length;
    }

    transcribing.value = true;
    sttError.value = '';

    try {
        const formData = new FormData();
        formData.append('audio', encodeWav(samples, sampleRate), 'recording.wav');
        const response = await axios.post('/stt', formData);
        if (response.data.text) {
            input.value = response.data.text;
            autoResize();
        }
    } catch (err) {
        sttError.value = err?.response?.data?.error || 'Could not transcribe audio. Try again.';
    } finally {
        transcribing.value = false;
    }
}

function cleanupRecording() {
    if (recordingTimer) {
        clearTimeout(recordingTimer);
        recordingTimer = null;
    }
    if (scriptProcessor) {
        scriptProcessor.onaudioprocess = null;
        scriptProcessor.disconnect();
        scriptProcessor = null;
    }
    if (sourceNode) {
        sourceNode.disconnect();
        sourceNode = null;
    }
    if (silenceGain) {
        silenceGain.disconnect();
        silenceGain = null;
    }
    if (audioContext) {
        audioContext.close().catch(() => {});
        audioContext = null;
    }
    if (recordingStream) {
        recordingStream.getTracks().forEach((track) => track.stop());
        recordingStream = null;
    }
    recordingChunks = [];
}

function encodeWav(samples, sampleRate) {
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    writeWavString(view, 0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    writeWavString(view, 8, 'WAVE');
    writeWavString(view, 12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeWavString(view, 36, 'data');
    view.setUint32(40, samples.length * 2, true);
    let writeOffset = 44;
    for (let i = 0; i < samples.length; i++, writeOffset += 2) {
        const s = Math.max(-1, Math.min(1, samples[i]));
        view.setInt16(writeOffset, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    }
    return new Blob([buffer], { type: 'audio/wav' });
}

function writeWavString(view, offset, str) {
    for (let i = 0; i < str.length; i++) {
        view.setUint8(offset + i, str.charCodeAt(i));
    }
}

function addFile(file) {
    if (!allowedTypes.includes(file.type)) return;
    const isImage = imageTypes.includes(file.type);
    const reader = new FileReader();
    reader.onload = (event) => {
        const fullDataUrl = event.target.result;
        const iconInfo = isImage ? {} : getFileIcon(file.type);
        pendingFile.value = {
            name: file.name,
            mime_type: file.type,
            isImage,
            preview: isImage ? fullDataUrl : null,
            data: fullDataUrl.split(',')[1],
            ...iconInfo,
        };
    };
    reader.readAsDataURL(file);
}

function handleFileSelect(event) {
    if (event.target.files.length > 0) addFile(event.target.files[0]);
    event.target.value = '';
}

function handleDrop(event) {
    dragActive.value = false;
    if (event.dataTransfer.files.length > 0) addFile(event.dataTransfer.files[0]);
}

function handlePaste(event) {
    const clipboardItems = event.clipboardData?.items;
    if (!clipboardItems) return;
    for (let i = 0; i < clipboardItems.length; i++) {
        const item = clipboardItems[i];
        if (item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if (file) {
                event.preventDefault();
                addFile(file);
                break;
            }
        }
    }
}

function checkMobile() {
    isMobile.value = window.innerWidth < 768;
    if (!isMobile.value) sidebarOpen.value = true;
}

function scrollDown() {
    nextTick(() => {
        if (messagesRef.value) {
            messagesRef.value.scrollTo({ top: messagesRef.value.scrollHeight, behavior: 'smooth' });
        }
    });
}

function scrollToLastMessage() {
    nextTick(() => {
        const container = messagesRef.value;
        if (!container) return;
        const lastMsg = container.querySelector('.msg-in:last-child');
        if (lastMsg) {
            const containerTop = container.getBoundingClientRect().top;
            const msgTop = lastMsg.getBoundingClientRect().top;
            container.scrollTo({ top: container.scrollTop + (msgTop - containerTop) - 12, behavior: 'smooth' });
        }
    });
}

function newChat() {
    activeConversationId.value = null;
    messages.value = [];
    input.value = '';
    pendingFile.value = null;
    sttError.value = '';
    liveTokens.value = { prompt: 0, candidates: 0, total: 0 };
    sessionTokens.value = 0;
    if (isMobile.value) sidebarOpen.value = false;
    scrollDown();
}

function useSuggestion(promptText) {
    input.value = promptText;
    sendMessage();
}

async function loadConversations() {
    if (!user.value) return;
    try {
        const response = await axios.get('/conversations');
        conversations.value = response.data.data;
    } catch (err) {
        console.error('Could not load conversations', err);
    }
}

async function loadConversation(conversation) {
    try {
        const response = await axios.get('/conversations/' + conversation.id);
        activeConversationId.value = conversation.id;
        messages.value = response.data.messages.map((msg) => ({
            role: msg.role,
            text: msg.content,
            attachments: msg.attachments || null,
        }));
        if (isMobile.value) sidebarOpen.value = false;
        scrollDown();
    } catch (err) {
        console.error('Could not load conversation', err);
    }
}

async function deleteConversation(conversation) {
    if (!confirm(`Delete "${conversation.title}"?`)) return;
    try {
        await axios.delete('/conversations/' + conversation.id);
        if (activeConversationId.value === conversation.id) newChat();
        await loadConversations();
    } catch (err) {
        console.error('Could not delete conversation', err);
    }
}

async function sendMessage() {
    const text = input.value.trim();
    if ((!text && !pendingFile.value) || loading.value) return;

    const attachment = pendingFile.value ? { mime_type: pendingFile.value.mime_type, data: pendingFile.value.data, name: pendingFile.value.name } : null;

    messages.value.push({
        role: 'user',
        text: text || (pendingFile.value?.isImage ? '(image)' : '(file)'),
        attachments: attachment ? [attachment] : null,
    });

    input.value = '';
    pendingFile.value = null;
    if (textareaRef.value) textareaRef.value.style.height = 'auto';
    loading.value = true;
    liveTokens.value = { prompt: 0, candidates: 0, total: 0 };
    scrollDown();

    const assistantIndex = messages.value.length;
    messages.value.push({ role: 'assistant', text: '' });

    abortController = new AbortController();

    try {
        const payload = { message: text || (attachment && imageTypes.includes(attachment.mime_type) ? 'Describe this image.' : 'Analyze this file.') };
        if (attachment) payload.attachments = [attachment];
        if (activeConversationId.value) payload.conversation_id = activeConversationId.value;

        const response = await fetch('/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
            },
            body: JSON.stringify(payload),
            signal: abortController.signal,
        });

        if (!response.ok && response.headers.get('content-type')?.includes('application/json')) {
            const errData = await response.json();
            throw { response: { status: response.status, data: errData } };
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const events = buffer.split('\n\n');
            buffer = events.pop();

            for (const eventBlock of events) {
                const lines = eventBlock.split('\n');
                let eventType = '';
                let eventData = '';

                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        eventType = line.substring(7);
                    } else if (line.startsWith('data: ')) {
                        eventData = line.substring(6);
                    }
                }

                if (!eventType || !eventData) continue;

                let data;
                try {
                    data = JSON.parse(eventData);
                } catch {
                    continue;
                }

                if (eventType === 'chunk' && data.text) {
                    messages.value[assistantIndex].text += data.text;
                    scrollDown();
                } else if (eventType === 'usage' && data.usage_metadata) {
                    const u = data.usage_metadata;
                    liveTokens.value = {
                        prompt: u.promptTokenCount || 0,
                        candidates: u.candidatesTokenCount || 0,
                        total: u.totalTokenCount || (u.promptTokenCount || 0) + (u.candidatesTokenCount || 0),
                    };
                } else if (eventType === 'done') {
                    const u = data.usage_metadata || {};
                    const total = data.tokens_used || u.totalTokenCount || 0;
                    sessionTokens.value += total;
                    systemTokens.value = data.system_tokens_used ?? systemTokens.value;
                    liveTokens.value = {
                        prompt: u.promptTokenCount || 0,
                        candidates: u.candidatesTokenCount || 0,
                        total,
                    };
                    if (data.conversation_id && !activeConversationId.value) {
                        activeConversationId.value = data.conversation_id;
                    }
                    if (user.value && data.user_tokens_remaining !== null) {
                        user.value.tokens_used = user.value.tokens_limit - data.user_tokens_remaining;
                    }
                    if (data.api_status) {
                        apiStatus.value = data.api_status;
                    }
                } else if (eventType === 'error') {
                    messages.value[assistantIndex].role = 'error';
                    messages.value[assistantIndex].text = data.message || friendlyError(data.status);
                    if (data.api_status) {
                        apiStatus.value = data.api_status;
                    } else if (data.status === 429) {
                        apiStatus.value = { status: 'exhausted', percentage: 100, resets_at: null };
                    }
                }
            }
        }

        if (messages.value[assistantIndex].role === 'assistant' && !messages.value[assistantIndex].text) {
            messages.value[assistantIndex].role = 'error';
            messages.value[assistantIndex].text = friendlyError(500);
        }

        if (user.value && messages.value[assistantIndex].role === 'assistant') await loadConversations();

        messageCount.value++;
        if (!feedbackShown.value && messageCount.value >= 5 && Math.random() < 0.8) {
            showFeedbackModal.value = true;
            feedbackShown.value = true;
            messageCount.value = 0;
        }
    } catch (err) {
        if (err?.name === 'AbortError') {
            if (messages.value[assistantIndex] && !messages.value[assistantIndex].text && messages.value[assistantIndex].role === 'assistant') {
                messages.value[assistantIndex].text = '— generation stopped —';
            }
        } else {
            let errorMsg = friendlyError(err?.response?.status);
            if (err?.response?.data?.error) {
                errorMsg = err.response.data.error;
            }
            if (err?.response?.data?.api_status) {
                apiStatus.value = err.response.data.api_status;
            } else if (err?.response?.status === 429) {
                apiStatus.value = { status: 'exhausted', percentage: 100, resets_at: null };
            }

            if (messages.value[assistantIndex] && messages.value[assistantIndex].role === 'assistant') {
                messages.value[assistantIndex].role = 'error';
                messages.value[assistantIndex].text = errorMsg;
            } else {
                messages.value.push({ role: 'error', text: errorMsg });
            }
        }
    }

    loading.value = false;
    abortController = null;
    scrollToLastMessage();
}

function openEditProfile() {
    profileMenuOpen.value = false;
    showEditProfileModal.value = true;
}

function requestSignOut() {
    profileMenuOpen.value = false;
    showSignOutModal.value = true;
}

function handleProfileUpdated(updates) {
    Object.assign(page.props.auth.user, updates);
}

function logout() {
    router.post('/logout');
}

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    document.addEventListener('paste', handlePaste);
    if (user.value) {
        loadConversations();
        sidebarOpen.value = !isMobile.value;
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.removeEventListener('paste', handlePaste);
    cleanupRecording();
    if (abortController) abortController.abort();
});
</script>

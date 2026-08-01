<template>
    <div class="h-screen w-screen flex bg-transparent overflow-hidden font-sans">

        <div
            v-if="sidebarOpen && isMobile"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 md:hidden"
            @click="sidebarOpen = false"
        ></div>

        <aside
            v-if="user"
            :class="[
                sidebarOpen ? 'w-[288px]' : 'w-0',
                isMobile ? 'fixed inset-y-0 left-0 z-50' : 'relative'
            ]"
            class="shrink-0 flex flex-col transition-all duration-300 overflow-hidden p-3 pl-3"
        >
            <div class="w-[264px] h-full flex flex-col glass-strong rounded-2xl overflow-hidden">
                <div class="px-3 pt-3">
                    <a href="/" class="flex items-center gap-2.5 px-3 py-2 mb-1 rounded-xl hover:bg-white/[0.05] transition-colors">
                        <div class="w-7 h-7 rounded-lg glass flex items-center justify-center">
                            <NovaIcon :size="16" />
                        </div>
                        <span class="text-[14px] font-semibold tracking-tight nova-gradient-text">Nova</span>
                        <span class="nova-chip ml-auto">AI</span>
                    </a>
                </div>
                <div class="p-3 pt-2 border-b border-white/[0.06] space-y-1.5">
                    <button
                        @click="newChat"
                        class="nova-btn-primary w-full"
                    >
                        <i class="fa-solid fa-plus text-[11px]"></i>
                        <span>New chat</span>
                    </button>
                    <a href="/profile" class="nova-btn-ghost w-full">
                        <i class="fa-solid fa-briefcase text-[11px]"></i>
                        <span>Job Alerts</span>
                    </a>
                </div>

                <div class="flex-1 overflow-y-auto scrollbar-glass px-3 pt-4 pb-3">
                    <p v-if="conversations.length" class="px-3 pb-2 text-[10px] font-medium uppercase tracking-widest text-[#94a3b8]/50">Recent</p>
                    <div v-if="conversations.length === 0" class="text-center text-[12px] text-[#94a3b8]/50 mt-8 px-4">
                        No conversations yet
                    </div>
                    <div v-for="conv in conversations" :key="conv.id">
                        <button
                            @click="loadConversation(conv)"
                            class="group w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-[13px] transition-colors cursor-pointer text-left"
                            :class="activeConversationId === conv.id ? 'bg-[#c084fc]/10 border border-[#c084fc]/20 text-[#eef1f8]' : 'text-[#94a3b8] hover:bg-white/[0.05] hover:text-[#eef1f8]'"
                        >
                            <i class="fa-regular fa-comment-dots text-[11px] shrink-0" :class="activeConversationId === conv.id ? 'text-[#c084fc]' : ''"></i>
                            <span class="truncate flex-1">{{ conv.title }}</span>
                            <span
                                @click.stop="deleteConversation(conv)"
                                class="shrink-0 opacity-0 group-hover:opacity-100 text-[#94a3b8] hover:text-[#f472b6] transition-all cursor-pointer"
                            >
                                <i class="fa-solid fa-xmark text-[11px]"></i>
                            </span>
                        </button>
                    </div>
                </div>

                <div v-if="user" class="p-3 border-t border-white/[0.06]">
                    <a href="/profile" class="px-3 flex items-center gap-3 hover:bg-white/[0.06] rounded-xl py-2 transition cursor-pointer">
                        <div class="w-8 h-8 rounded-full nova-gradient flex items-center justify-center shrink-0">
                            <span class="text-[12px] font-semibold text-white">{{ user.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[12px] font-medium text-[#eef1f8] truncate">{{ user.name }}</div>
                            <div class="text-[10px] text-[#94a3b8]/70 truncate">{{ user.email }}</div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-[10px] text-[#94a3b8] ml-auto shrink-0"></i>
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">

            <header class="shrink-0 px-4 sm:px-6 py-3">
                <div class="max-w-3xl mx-auto flex items-center justify-between">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <button
                            v-if="user"
                            @click="sidebarOpen = !sidebarOpen"
                            class="w-9 h-9 rounded-xl glass flex items-center justify-center hover:bg-white/[0.08] transition cursor-pointer"
                        >
                            <i class="fa-solid fa-bars text-[15px] text-[#94a3b8]"></i>
                        </button>
                        <div class="flex items-center gap-2.5 sm:gap-3">
                            <div class="relative w-9 h-9 rounded-xl glass flex items-center justify-center nova-glow">
                                <div class="absolute inset-0 rounded-xl nova-gradient opacity-15 blur-md"></div>
                                <div class="relative">
                                    <NovaIcon :size="20" />
                                </div>
                            </div>
                            <div>
                                <h1 class="text-[16px] font-semibold text-[#eef1f8] tracking-tight leading-none nova-gradient-text">Nova</h1>
                                <p class="text-[10px] sm:text-[11px] text-[#94a3b8] mt-1.5 flex items-center gap-1.5">
                                    <span class="w-1 h-1 rounded-full bg-emerald-400/80"></span>
                                    Gemini 2.5 Flash
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            v-if="apiStatus"
                            class="hidden sm:flex items-center gap-2 mr-2 px-3 py-1.5 rounded-full glass transition-colors"
                            :class="statusStyles.border"
                        >
                            <div class="w-1.5 h-1.5 rounded-full" :class="statusStyles.dot"></div>
                            <span class="text-[11px]" :class="statusStyles.text">
                                {{ statusStyles.label }}
                            </span>
                        </div>
                        <template v-if="user">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl nova-gradient flex items-center justify-center cursor-pointer transition" :title="user.name">
                                <span class="text-[12px] sm:text-[13px] font-semibold text-white">{{ user.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <form @submit.prevent="logout" class="inline">
                                <button type="submit" class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl glass flex items-center justify-center hover:bg-white/[0.08] transition cursor-pointer" title="Sign out">
                                    <i class="fa-solid fa-right-from-bracket text-[12px] sm:text-[13px] text-[#94a3b8]"></i>
                                </button>
                            </form>
                        </template>
                        <template v-else>
                            <a href="/login" class="px-4 py-2 rounded-xl glass text-[12px] sm:text-[13px] text-[#eef1f8] hover:bg-white/[0.08] transition-colors">
                                Sign in
                            </a>
                        </template>
                    </div>
                </div>
            </header>

            <div ref="messagesRef" class="flex-1 overflow-y-auto scrollbar-glass">

                <div v-if="!messages.length && !loading" class="h-full flex flex-col items-center justify-center px-5 sm:px-8">
                    <div class="text-center mb-6 sm:mb-8">
                        <div class="relative w-16 h-16 mx-auto mb-5 rounded-2xl glass flex items-center justify-center nova-glow">
                            <div class="absolute inset-0 rounded-2xl nova-gradient opacity-20 blur-lg"></div>
                            <div class="relative">
                                <NovaIcon :size="30" />
                            </div>
                        </div>
                        <h2 class="text-[22px] sm:text-[26px] font-semibold text-[#eef1f8] tracking-tight mb-2">
                            How can <span class="nova-gradient-text">Nova</span> help?
                        </h2>
                        <p class="text-[12px] sm:text-[13px] text-[#94a3b8]">
                            {{ user ? 'Ask anything — I think, write, and speak back.' : 'Sign in to save your chat history, or continue as a guest.' }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 w-full max-w-lg">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion.label"
                            @click="useSuggestion(suggestion.prompt)"
                            class="group text-left px-4 py-3.5 rounded-xl glass glass-hover hover:bg-white/[0.07] hover:border-white/[0.18] hover:-translate-y-0.5 hover:shadow-[0_12px_40px_-16px_rgba(129,140,248,0.4)] cursor-pointer transition-all"
                        >
                            <div class="w-7 h-7 rounded-lg nova-gradient-soft flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                <i :class="suggestion.icon" class="text-[11px] nova-gradient-text"></i>
                            </div>
                            <span class="text-[12px] text-[#cbd5e1] group-hover:text-[#eef1f8] transition-colors">{{ suggestion.label }}</span>
                        </button>
                    </div>
                </div>

                <div v-else class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
                    <ChatMessage v-for="(msg, i) in messages" :key="i" :msg="msg" :index="i" />

                    <div v-if="loading && messages[messages.length - 1]?.role === 'assistant' && !messages[messages.length - 1]?.text" class="flex justify-start msg-in mb-5 sm:mb-6">
                        <div class="flex gap-3 sm:gap-3.5">
                            <div class="shrink-0 mt-0.5">
                                <NovaIcon :size="24" class="sm:w-6 sm:h-6" />
                            </div>
                            <div class="flex gap-1.5 px-1 py-2">
                                <span class="nova-dot w-1.5 h-1.5 bg-[#a5b4fc] rounded-full" style="animation-delay:0s"></span>
                                <span class="nova-dot w-1.5 h-1.5 bg-[#c084fc] rounded-full" style="animation-delay:0.15s"></span>
                                <span class="nova-dot w-1.5 h-1.5 bg-[#f472b6] rounded-full" style="animation-delay:0.3s"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <StatusBanners :user="user" :messages="messages" :apiStatus="apiStatus" />

            <div class="shrink-0 px-4 sm:px-6 pb-4 sm:pb-5 pt-2">
                <form @submit.prevent="sendMessage" class="max-w-3xl mx-auto" @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false" @drop.prevent="handleDrop">
                    <div v-if="dragActive" class="mb-3 glass rounded-2xl border-2 border-dashed border-[#a5b4fc]/50 bg-[#a5b4fc]/[0.06] p-8 flex flex-col items-center justify-center transition-all">
                        <i class="fa-solid fa-cloud-arrow-up nova-gradient-text text-2xl mb-2"></i>
                        <p class="text-[13px] text-[#cbd5e1]">Drop files here</p>
                    </div>

                    <div v-if="pendingFile" class="mb-3">
                        <div class="relative inline-block">
                            <img v-if="pendingFile.isImage" :src="pendingFile.preview" class="h-20 w-20 object-cover rounded-xl border border-white/10 glass" />
                            <div v-else class="h-20 w-20 rounded-xl glass flex flex-col items-center justify-center gap-1">
                                <i :class="pendingFile.icon" class="text-[18px]" :style="{ color: pendingFile.iconColor }"></i>
                                <p class="text-[8px] text-[#94a3b8] truncate max-w-[70px] px-1">{{ pendingFile.ext }}</p>
                            </div>
                            <button
                                @click="pendingFile = null"
                                type="button"
                                class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full glass-strong flex items-center justify-center transition cursor-pointer hover:text-red-400"
                            >
                                <i class="fa-solid fa-xmark text-[9px] text-[#eef1f8]"></i>
                            </button>
                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 backdrop-blur-md rounded-b-xl px-1.5 py-0.5">
                                <p class="text-[9px] text-[#eef1f8] truncate text-center">{{ pendingFile.name }}</p>
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
                    <div class="flex items-end gap-2.5 sm:gap-3 glass rounded-2xl sm:rounded-3xl px-4 sm:px-5 py-2.5 sm:py-3 shadow-[0_20px_60px_-24px_rgba(0,0,0,0.7)] transition-all duration-200 focus-within:border-[#818cf8]/40 focus-within:shadow-[0_0_0_1px_rgba(129,140,248,0.25),0_20px_60px_-24px_rgba(0,0,0,0.7)]">
                        <button
                            type="button"
                            @click="$refs.fileInput.click()"
                            :disabled="loading || isSystemBusy"
                            class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center shrink-0 transition cursor-pointer hover:bg-white/[0.08] disabled:opacity-40"
                            :title="isSystemBusy ? 'System busy' : 'Attach files'"
                        >
                            <i class="fa-solid fa-paperclip text-[13px] text-[#94a3b8]"></i>
                        </button>
                        <textarea
                            ref="textareaRef"
                            v-model="input"
                            :placeholder="isSystemBusy ? 'System busy, try later...' : 'Enter a prompt here'"
                            autocomplete="off"
                            :disabled="loading || isSystemBusy"
                            rows="1"
                            class="flex-1 bg-transparent text-[#eef1f8] text-[13.5px] sm:text-[14.5px] placeholder-[#94a3b8]/60 focus:outline-none disabled:opacity-40 resize-none max-h-[150px] py-0.5 leading-[1.5]"
                            @input="autoResize"
                            @keydown="handleKeydown"
                        ></textarea>
                        <button
                            type="button"
                            @click="toggleRecording"
                            :class="isRecording ? 'bg-red-500/20 text-red-400 nova-recording' : 'hover:bg-white/[0.08] text-[#94a3b8] hover:text-[#eef1f8]'"
                            class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center shrink-0 transition cursor-pointer"
                            :title="isRecording ? 'Stop recording' : 'Dictate your message'"
                        >
                            <i :class="transcribing ? 'fa-solid fa-spinner fa-spin' : isRecording ? 'fa-solid fa-microphone-lines' : 'fa-solid fa-microphone'" class="text-xs"></i>
                        </button>
                        <button
                            type="submit"
                            :disabled="loading || (!input.trim() && !pendingFile) || isSystemBusy"
                            class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center shrink-0 transition disabled:opacity-20 disabled:cursor-not-allowed cursor-pointer active:scale-95"
                            :class="loading || (!input.trim() && !pendingFile) || isSystemBusy ? 'bg-white/[0.06] text-[#94a3b8]' : 'nova-gradient text-white nova-glow hover:scale-105'"
                        >
                            <i :class="loading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-arrow-up'" class="text-xs"></i>
                        </button>
                    </div>
                    <div v-if="isRecording" class="flex items-center justify-center gap-2 mt-2.5">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        <span class="text-[11px] text-red-400">Recording… speak now</span>
                    </div>
                    <div v-else-if="transcribing" class="flex items-center justify-center gap-2 mt-2.5">
                        <i class="fa-solid fa-spinner fa-spin text-[11px] text-[#a5b4fc]"></i>
                        <span class="text-[11px] text-[#94a3b8]">Transcribing…</span>
                    </div>
                    <div v-else-if="sttError" class="text-center text-[11px] text-red-400 mt-2.5">{{ sttError }}</div>
                    <p class="text-center text-[10px] sm:text-[11px] text-[#94a3b8]/50 mt-2.5 sm:mt-3">Nova may display inaccurate info, including about people, so double-check its responses.</p>
                </form>
            </div>
        </div>
    </div>

    <FeedbackModal 
        :show="showFeedbackModal" 
        trigger="chat"
        @close="showFeedbackModal = false" 
    />
</template>

<script setup>
import axios from 'axios';
import { ref, nextTick, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import NovaIcon from '@/Components/NovaIcon.vue';
import ChatMessage from '@/Components/ChatMessage.vue';
import StatusBanners from '@/Components/StatusBanners.vue';
import FeedbackModal from '@/Components/FeedbackModal.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const input = ref('');
const messages = ref([]);
const loading = ref(false);
const messagesRef = ref(null);
const textareaRef = ref(null);
const fileInput = ref(null);
const pendingFile = ref(null);
const dragActive = ref(false);
const sidebarOpen = ref(false);
const conversations = ref([]);
const activeConversationId = ref(null);
const isMobile = ref(false);
const showFeedbackModal = ref(false);
const messageCount = ref(0);
const feedbackShown = ref(false);

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
    if (mimeType === 'application/pdf') return { icon: 'fa-solid fa-file-pdf', iconColor: '#f87171', ext: 'PDF' };
    if (mimeType === 'text/plain') return { icon: 'fa-solid fa-file-lines', iconColor: '#a5b4fc', ext: 'TXT' };
    if (mimeType === 'text/csv') return { icon: 'fa-solid fa-file-csv', iconColor: '#4ade80', ext: 'CSV' };
    return { icon: 'fa-solid fa-file', iconColor: '#94a3b8', ext: 'FILE' };
}

const isSystemBusy = computed(() => apiStatus.value.status === 'exhausted');

const statusStyles = computed(() => {
    const status = apiStatus.value.status;
    const rpmLabel = `${apiStatus.value.rpm || 0}/${apiStatus.value.rpm_limit || 15} RPM`;

    if (status === 'exhausted') return { dot: 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)]', border: 'border-red-500/30', text: 'text-red-400', label: 'System busy' };
    if (status === 'critical') return { dot: 'bg-orange-400 shadow-[0_0_10px_rgba(251,146,60,0.8)]', border: 'border-orange-400/30', text: 'text-orange-300', label: rpmLabel };
    if (status === 'warning') return { dot: 'bg-amber-400 shadow-[0_0_10px_rgba(251,191,36,0.8)]', border: 'border-amber-400/30', text: 'text-amber-300', label: rpmLabel };
    return { dot: 'bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.8)]', border: 'border-white/10', text: 'text-[#94a3b8]', label: 'System OK' };
});

function autoResize() {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 150) + 'px';
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
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
    scrollDown();

    const assistantIndex = messages.value.length;
    messages.value.push({ role: 'assistant', text: '' });

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
                } else if (eventType === 'done') {
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

    loading.value = false;
    scrollToLastMessage();
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
});
</script>

<template>
    <div class="msg-in group mb-5">

        <div v-if="msg.role === 'user'">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-5 h-5 rounded-md bg-oc-raised border border-oc-border flex items-center justify-center shrink-0">
                    <NovaIcon :size="11" />
                </span>
                <span class="text-[11px] font-medium text-oc-faint">You</span>
            </div>
            <div v-if="msg.attachments && msg.attachments.length" class="flex flex-wrap gap-2 mb-1.5">
                <template v-for="(att, ai) in msg.attachments" :key="ai">
                    <img
                        v-if="att.mime_type && att.mime_type.startsWith('image/')"
                        :src="'data:' + att.mime_type + ';base64,' + att.data"
                        class="h-16 w-16 sm:h-20 sm:w-20 rounded-md object-cover border border-oc-border"
                    />
                    <div v-else class="flex items-center gap-2.5 px-3 py-2 rounded-md bg-oc-raised border border-oc-border max-w-[220px]">
                        <i :class="getFileIcon(att.mime_type).icon" class="text-[18px] shrink-0" :style="{ color: getFileIcon(att.mime_type).color }"></i>
                        <div class="min-w-0">
                            <p class="text-[11px] text-oc-text truncate">{{ att.name || getFileIcon(att.mime_type).label }}</p>
                            <p class="text-[9px] text-oc-faint">{{ getFileIcon(att.mime_type).label }}</p>
                        </div>
                    </div>
                </template>
            </div>
            <div class="msg text-oc-text text-[13.5px] sm:text-[14.5px] leading-[1.75] whitespace-pre-wrap break-words">{{ msg.text }}</div>
            <div class="flex items-center gap-1 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                    @click="copyText(msg.text)"
                    class="w-6 h-6 rounded-md flex items-center justify-center text-oc-faint hover:bg-oc-hover hover:text-oc-text transition cursor-pointer"
                    :title="'Copy message'"
                >
                    <i class="fa-regular fa-copy text-[11px]"></i>
                </button>
            </div>
        </div>

        <div v-else-if="msg.role === 'error'">
            <div class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-md bg-oc-error/10 border border-oc-error/25 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fa-solid fa-triangle-exclamation text-[10px] text-oc-error"></i>
                </span>
                <div class="text-oc-error/90 text-[13px] sm:text-[14px] leading-relaxed">{{ msg.text }}</div>
            </div>
        </div>

        <div v-else>
            <div class="msg text-oc-text/90 text-[13.5px] sm:text-[14.5px] leading-[1.75]" v-html="formatText(msg.text)"></div>
            <span v-if="streaming" class="nova-cursor text-oc-muted" aria-hidden="true"></span>

            <div class="flex items-center gap-1 mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                    @click="copyText(msg.text)"
                    class="w-6 h-6 rounded-md flex items-center justify-center text-oc-faint hover:bg-oc-hover hover:text-oc-text transition cursor-pointer"
                    :title="'Copy reply'"
                >
                    <i class="fa-regular fa-copy text-[11px]"></i>
                </button>
                <button
                    @click="toggleSpeak"
                    :disabled="loading"
                    class="w-6 h-6 rounded-md flex items-center justify-center text-oc-faint hover:bg-oc-hover hover:text-oc-text transition cursor-pointer disabled:opacity-40 active:scale-90"
                    :title="isSpeaking ? 'Stop' : 'Listen to this reply'"
                >
                    <i :class="loading ? 'fa-solid fa-spinner fa-spin' : isSpeaking ? 'fa-solid fa-stop' : 'fa-solid fa-volume-high'" class="text-[11px]"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, computed, onUnmounted } from 'vue';
import axios from 'axios';
import NovaIcon from '@/Components/NovaIcon.vue';

const props = defineProps({
    msg: { type: Object, required: true },
    index: { type: Number, required: true },
    streaming: { type: Boolean, default: false },
});

const player = reactive({
    speakingKey: null,
    audio: null,
    objectUrl: null,
});

const loading = ref(false);
const isSpeaking = computed(() => player.speakingKey === props.index);

function stopCurrent() {
    if (player.audio) {
        player.audio.pause();
        player.audio = null;
    }
    if (player.objectUrl) {
        URL.revokeObjectURL(player.objectUrl);
        player.objectUrl = null;
    }
    player.speakingKey = null;
}

async function toggleSpeak() {
    if (isSpeaking.value) {
        stopCurrent();
        return;
    }

    stopCurrent();
    loading.value = true;

    try {
        const response = await axios.post('/tts', { text: props.msg.text }, { responseType: 'blob' });
        const objectUrl = URL.createObjectURL(response.data);
        player.objectUrl = objectUrl;
        player.audio = new Audio(objectUrl);
        player.speakingKey = props.index;
        player.audio.onended = () => {
            if (player.objectUrl) URL.revokeObjectURL(player.objectUrl);
            player.objectUrl = null;
            player.audio = null;
            player.speakingKey = null;
        };
        await player.audio.play();
    } catch (err) {
        stopCurrent();
        console.error('TTS failed', err);
    } finally {
        loading.value = false;
    }
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (err) {
        console.error('Copy failed', err);
    }
}

onUnmounted(() => {
    if (player.speakingKey === props.index) stopCurrent();
});

function formatText(text) {
    return text
        .replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/```(\w*)\n?([\s\S]*?)```/g, '<pre class="code-block"><code>$2</code></pre>')
        .replace(/`([^`]+)`/g, '<code class="inline-code">$1</code>')
        .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/(?<!\w)\*(?!\*)/g, '')
        .replace(/\n/g, '<br>');
}

function getFileIcon(mimeType) {
    if (mimeType === 'application/pdf') return { icon: 'fa-solid fa-file-pdf', color: '#f47067', label: 'PDF Document' };
    if (mimeType === 'text/plain') return { icon: 'fa-solid fa-file-lines', color: '#7fb4ff', label: 'Text File' };
    if (mimeType === 'text/csv') return { icon: 'fa-solid fa-file-csv', color: '#4ade80', label: 'CSV File' };
    return { icon: 'fa-solid fa-file', color: '#9c9ca5', label: 'File' };
}
</script>

<style scoped>
.msg { overflow-wrap: break-word; word-break: break-word; }
.msg :deep(pre.code-block) {
    background: #131316;
    color: #ececef;
    padding: 14px 16px;
    border-radius: 10px;
    overflow-x: hidden;
    font-size: 12.5px;
    margin: 12px 0;
    line-height: 1.65;
    border: 1px solid #232328;
    font-family: ui-monospace, 'SF Mono', SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace;
    word-break: break-all;
    white-space: pre-wrap;
}
.msg :deep(code.inline-code) {
    background: rgba(118, 152, 253, 0.14);
    padding: 2px 6px;
    border-radius: 5px;
    font-size: 12.5px;
    color: #a2bcff;
    font-family: ui-monospace, 'SF Mono', SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace;
}
.msg :deep(pre.code-block code) { background: none; padding: 0; color: inherit; word-break: break-all; white-space: pre-wrap; }
.msg :deep(strong) { color: #ededed; font-weight: 600; }
.msg :deep(em) { color: #a2bcff; font-style: italic; }
.msg :deep(a) { color: #a2bcff; text-decoration: underline; text-underline-offset: 2px; }
.msg :deep(ul), .msg :deep(ol) { margin: 8px 0; padding-left: 22px; }
.msg :deep(li) { margin: 4px 0; }
.msg :deep(blockquote) { border-left: 3px solid #7698fd; padding-left: 16px; margin: 12px 0; color: #a0a0a0; }
.msg :deep(hr) { border: none; border-top: 1px solid #262626; margin: 20px 0; }
.msg :deep(table) { border-collapse: collapse; width: 100%; margin: 14px 0; font-size: 13px; }
.msg :deep(th), .msg :deep(td) { border: 1px solid #2e2e2e; padding: 8px 12px; text-align: left; color: #ededed; }
.msg :deep(th) { background: #1a1a1e; font-weight: 600; }
</style>

<template>
    <div class="msg-in mb-5 sm:mb-6" :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">

        <div v-if="msg.role === 'user'" class="max-w-[85%] sm:max-w-[75%] nova-gradient-soft text-[#eef1f8] rounded-2xl rounded-br-sm px-4 sm:px-5 py-2.5 sm:py-3 text-[13.5px] sm:text-[14.5px] leading-relaxed border border-white/[0.12] shadow-[0_8px_30px_-12px_rgba(129,140,248,0.35)]">
            <div v-if="msg.attachments && msg.attachments.length" class="flex flex-wrap gap-2 mb-2">
                <template v-for="(att, ai) in msg.attachments" :key="ai">
                    <img v-if="att.mime_type && att.mime_type.startsWith('image/')" :src="'data:' + att.mime_type + ';base64,' + att.data" class="max-h-48 rounded-lg object-contain" />
                    <div v-else class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-black/30 border border-white/[0.08] max-w-[220px]">
                        <i :class="getFileIcon(att.mime_type).icon" class="text-[20px] shrink-0" :style="{ color: getFileIcon(att.mime_type).color }"></i>
                        <div class="min-w-0">
                            <p class="text-[11px] text-[#eef1f8] truncate">{{ att.name || getFileIcon(att.mime_type).label }}</p>
                            <p class="text-[9px] text-[#94a3b8]">{{ getFileIcon(att.mime_type).label }}</p>
                        </div>
                    </div>
                </template>
            </div>
            {{ msg.text }}
        </div>

        <div v-else-if="msg.role === 'error'" class="max-w-[85%] sm:max-w-[75%] bg-red-500/10 border border-red-500/20 text-red-400 rounded-2xl rounded-bl-sm px-4 sm:px-5 py-2.5 sm:py-3 text-[13.5px] sm:text-[14.5px] leading-relaxed backdrop-blur-xl">
            {{ msg.text }}
        </div>

        <div v-else class="flex gap-3 sm:gap-3.5">
            <div v-if="msg.text" class="shrink-0 mt-0.5">
                <div class="w-7 h-7 rounded-xl nova-gradient-soft border border-white/[0.12] flex items-center justify-center">
                    <NovaIcon :size="18" />
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <div v-if="msg.text" class="msg glass border-white/[0.1] rounded-2xl rounded-bl-sm px-4 sm:px-5 py-3 text-[#eef1f8]/90 text-[13.5px] sm:text-[14.5px] leading-[1.75]" v-html="formatText(msg.text)"></div>
                <div v-if="msg.text" class="flex items-center gap-1.5 mt-2">
                    <button
                        @click="toggleSpeak"
                        :disabled="loading"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition cursor-pointer disabled:opacity-40 active:scale-90"
                        :class="isSpeaking ? 'bg-[#c084fc]/20 text-[#c084fc]' : 'text-[#94a3b8] hover:bg-white/[0.07] hover:text-[#eef1f8]'"
                        :title="isSpeaking ? 'Stop' : 'Listen to this reply'"
                    >
                        <i :class="loading ? 'fa-solid fa-spinner fa-spin' : isSpeaking ? 'fa-solid fa-stop' : 'fa-solid fa-volume-high'" class="text-[11px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, computed, onUnmounted } from 'vue';
import axios from 'axios';
import NovaIcon from './NovaIcon.vue';

const props = defineProps({
    msg: { type: Object, required: true },
    index: { type: Number, required: true },
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
    if (mimeType === 'application/pdf') return { icon: 'fa-solid fa-file-pdf', color: '#f87171', label: 'PDF Document' };
    if (mimeType === 'text/plain') return { icon: 'fa-solid fa-file-lines', color: '#a5b4fc', label: 'Text File' };
    if (mimeType === 'text/csv') return { icon: 'fa-solid fa-file-csv', color: '#4ade80', label: 'CSV File' };
    return { icon: 'fa-solid fa-file', color: '#94a3b8', label: 'File' };
}
</script>

<style scoped>
.msg { overflow-wrap: break-word; word-break: break-word; }
.msg :deep(pre.code-block) {
    background: rgba(10, 12, 20, 0.7);
    color: #eef1f8;
    padding: 14px 16px;
    border-radius: 12px;
    overflow-x: hidden;
    font-size: 12.5px;
    margin: 12px 0;
    line-height: 1.65;
    border: 1px solid rgba(255, 255, 255, 0.08);
    font-family: 'SF Mono', 'Fira Code', Menlo, Consolas, monospace;
    word-break: break-all;
    white-space: pre-wrap;
}
.msg :deep(code.inline-code) {
    background: rgba(165, 180, 252, 0.12);
    padding: 2px 6px;
    border-radius: 5px;
    font-size: 12.5px;
    color: #a5b4fc;
    font-family: 'SF Mono', 'Fira Code', Menlo, Consolas, monospace;
}
.msg :deep(pre.code-block code) { background: none; padding: 0; color: inherit; word-break: break-all; white-space: pre-wrap; }
.msg :deep(strong) { color: #eef1f8; font-weight: 600; }
.msg :deep(em) { color: #a5b4fc; font-style: italic; }
.msg :deep(a) { color: #a5b4fc; text-decoration: underline; text-underline-offset: 2px; }
.msg :deep(ul), .msg :deep(ol) { margin: 8px 0; padding-left: 22px; }
.msg :deep(li) { margin: 4px 0; }
.msg :deep(blockquote) { border-left: 3px solid #c084fc; padding-left: 16px; margin: 12px 0; color: #94a3b8; }
.msg :deep(hr) { border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 20px 0; }
.msg :deep(table) { border-collapse: collapse; width: 100%; margin: 14px 0; font-size: 13px; }
.msg :deep(th), .msg :deep(td) { border: 1px solid rgba(255, 255, 255, 0.12); padding: 8px 12px; text-align: left; color: #eef1f8; }
.msg :deep(th) { background: rgba(255, 255, 255, 0.06); font-weight: 600; }
</style>

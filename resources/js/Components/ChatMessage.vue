<template>
    <div class="msg-in group mb-6">

        <div v-if="msg.role === 'user'" class="flex items-end justify-end gap-2.5 sm:gap-3">
            <div class="max-w-[85%] sm:max-w-[72%] flex flex-col items-end min-w-0">
                <span class="text-[10px] font-medium text-oc-faint mb-1 mr-1">You</span>

                <div v-if="msg.attachments && msg.attachments.length" class="flex flex-wrap gap-2 mb-1.5 justify-end">
                    <template v-for="(att, ai) in msg.attachments" :key="ai">
                        <img
                            v-if="att.mime_type && att.mime_type.startsWith('image/')"
                            :src="'data:' + att.mime_type + ';base64,' + att.data"
                            class="h-16 w-16 sm:h-20 sm:w-20 rounded-lg object-cover border border-oc-border bg-oc-raised"
                        />
                        <div v-else class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-oc-raised border border-oc-border max-w-[220px]">
                            <i :class="getFileIcon(att.mime_type).icon" class="text-[18px] shrink-0" :style="{ color: getFileIcon(att.mime_type).color }"></i>
                            <div class="min-w-0">
                                <p class="text-[11px] text-oc-text truncate">{{ att.name || getFileIcon(att.mime_type).label }}</p>
                                <p class="text-[9px] text-oc-faint">{{ getFileIcon(att.mime_type).label }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="neu-card-sm !rounded-2xl !rounded-br-md px-4.5 py-3 border border-oc-border">
                    <p class="msg text-oc-text text-[13.5px] sm:text-[14.5px] leading-[1.7] whitespace-pre-wrap break-words font-normal">{{ msg.text }}</p>
                </div>

                <div class="flex items-center gap-1.5 mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        @click="copyText(msg.text)"
                        class="w-7 h-7 rounded-lg neu-btn !p-0 flex items-center justify-center text-oc-muted hover:text-oc-text"
                        :title="'Copy message'"
                    >
                        <i class="fa-regular fa-copy text-[11px]"></i>
                    </button>
                </div>
            </div>

            <div class="w-8 h-8 rounded-xl neu-card-sm flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user text-[12px] text-oc-primary"></i>
            </div>
        </div>

        <div v-else-if="msg.role === 'error'" class="flex items-start gap-2.5 sm:gap-3">
            <span class="w-8 h-8 rounded-xl bg-oc-error/10 border border-oc-error/25 flex items-center justify-center shrink-0 mt-0.5">
                <i class="fa-solid fa-triangle-exclamation text-[12px] text-oc-error"></i>
            </span>
            <div class="text-oc-error/90 text-[13px] sm:text-[14px] leading-relaxed pt-1.5 font-medium">{{ msg.text }}</div>
        </div>

        <div v-else-if="msg.text" class="flex items-start gap-2.5 sm:gap-3">
            <div class="w-8 h-8 rounded-xl neu-card-sm flex items-center justify-center shrink-0 mt-0.5">
                <NovaIcon :size="15" />
            </div>

            <div class="min-w-0 flex-1">
                <span class="block text-[11px] font-bold text-oc-muted mb-1 tracking-wide">Nova AI</span>

                <div class="msg text-oc-text/95 text-[13.5px] sm:text-[14.5px] leading-[1.75]" v-html="formatText(msg.text)"></div>
                <span v-if="streaming" class="nova-cursor text-oc-primary" aria-hidden="true"></span>

                <div class="flex items-center gap-1.5 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        @click="copyText(msg.text)"
                        class="w-7 h-7 rounded-lg neu-btn !p-0 flex items-center justify-center text-oc-muted hover:text-oc-text"
                        :title="'Copy reply'"
                    >
                        <i class="fa-regular fa-copy text-[11px]"></i>
                    </button>
                    <button
                        @click="toggleSpeak"
                        :disabled="loading"
                        class="w-7 h-7 rounded-lg neu-btn !p-0 flex items-center justify-center text-oc-muted hover:text-oc-text disabled:opacity-40"
                        :title="isSpeaking ? 'Stop' : 'Listen to this reply'"
                    >
                        <i :class="loading ? 'fa-solid fa-spinner fa-spin' : isSpeaking ? 'fa-solid fa-stop text-oc-error' : 'fa-solid fa-volume-high'" class="text-[11px]"></i>
                    </button>
                </div>
            </div>
        </div>

        <div v-else></div>
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

function inline(text) {
    const codes = [];
    let out = text.replace(/`([^`]+)`/g, (_, c) => {
        codes.push(c);
        return '\u0000IC\u0000' + codes.length + '\u0000';
    });
    out = out
        .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
        .replace(/(\*|_)([^*_]+)\1/g, '<em>$2</em>')
        .replace(/\*/g, '');
    return out.replace(/\u0000IC\u0000(\d+)\u0000/g, (_, n) => {
        return '<code class="inline-code">' + codes[Number(n) - 1] + '</code>';
    });
}

function formatText(text) {
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const codeBlocks = [];
    let body = escaped.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
        codeBlocks.push(code.trim());
        return '\u0000CODE\u0000' + codeBlocks.length + '\u0000';
    });

    body = body.replace(/^(#{1,3})\s+(.+)$/gm, (_, hashes, content) => {
        const lvl = hashes.length;
        return `<h${lvl} class="msg-h${lvl}">${inline(content)}</h${lvl}>`;
    });

    body = body.replace(/^&gt;\s?(.+)$/gm, (_, content) => `<blockquote>${inline(content)}</blockquote>`);

    body = body.replace(/^([-*_]\s*){3,}$/gm, '<hr>');

    const lines = body.split('\n');
    let html = '';
    let inUl = false;
    let inOl = false;

    const closeLists = () => {
        if (inUl) { html += '</ul>'; inUl = false; }
        if (inOl) { html += '</ol>'; inOl = false; }
    };

    for (const rawLine of lines) {
        const line = rawLine.replace(/\r$/, '');

        if (/^<h\d class="msg-h/.test(line) || /^<blockquote>/.test(line) || line === '<hr>') {
            closeLists();
            html += line;
            continue;
        }
        if (line.startsWith('\u0000CODE\u0000')) {
            closeLists();
            html += (html ? '<br>' : '') + line;
            continue;
        }

        const ulMatch = line.match(/^[-*•]\s+(.+)$/);
        const olMatch = line.match(/^\d+[.)]\s+(.+)$/);

        if (ulMatch) {
            if (!inUl) { html += '<ul>'; inUl = true; }
            html += '<li>' + inline(ulMatch[1]) + '</li>';
            continue;
        }
        if (olMatch) {
            if (!inOl) { html += '<ol>'; inOl = true; }
            html += '<li>' + inline(olMatch[1]) + '</li>';
            continue;
        }

        closeLists();
        if (line.trim() === '') continue;
        html += (html && !html.endsWith('<ul>') && !html.endsWith('<ol>') ? '<br>' : '') + inline(line);
    }
    closeLists();

    html = html.replace(/<br>\s*$/, '');

    return html.replace(/\u0000CODE\u0000(\d+)\u0000/g, (_, n) => {
        const code = codeBlocks[Number(n) - 1];
        return '<pre class="code-block"><code>' + code + '</code></pre>';
    });
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
.msg :deep(h1) { font-size: 18px; font-weight: 650; margin: 18px 0 8px; color: #f0f0f3; }
.msg :deep(h2) { font-size: 16px; font-weight: 650; margin: 16px 0 8px; color: #f0f0f3; }
.msg :deep(h3) { font-size: 14.5px; font-weight: 600; margin: 14px 0 6px; color: #f0f0f3; }
.msg :deep(ul), .msg :deep(ol) { margin: 8px 0; padding-left: 22px; }
.msg :deep(li) { margin: 4px 0; }
.msg :deep(blockquote) { border-left: 3px solid #7698fd; padding-left: 16px; margin: 12px 0; color: #a0a0a0; }
.msg :deep(hr) { border: none; border-top: 1px solid #262626; margin: 20px 0; }
.msg :deep(table) { border-collapse: collapse; width: 100%; margin: 14px 0; font-size: 13px; }
.msg :deep(th), .msg :deep(td) { border: 1px solid #2e2e2e; padding: 8px 12px; text-align: left; color: #ededed; }
.msg :deep(th) { background: #1a1a1e; font-weight: 600; }
</style>

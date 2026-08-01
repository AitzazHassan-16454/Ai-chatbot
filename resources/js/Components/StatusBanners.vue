<template>
    <div v-if="apiStatus && apiStatus.status === 'warning'" class="shrink-0 mx-4 sm:mx-6 mb-2">
        <div class="max-w-3xl mx-auto glass rounded-xl px-4 py-3 flex items-center gap-3 border-amber-400/25">
            <div class="w-7 h-7 rounded-lg bg-amber-400/10 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-amber-400 text-xs"></i>
            </div>
            <p class="text-[13px] text-amber-300">API quota at {{ apiStatus.percentage }}%. Some features may be limited soon.</p>
        </div>
    </div>

    <div v-if="apiStatus && apiStatus.status === 'critical'" class="shrink-0 mx-4 sm:mx-6 mb-2">
        <div class="max-w-3xl mx-auto glass rounded-xl px-4 py-3 flex items-center gap-3 border-orange-400/25">
            <div class="w-7 h-7 rounded-lg bg-orange-400/10 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-exclamation text-orange-400 text-xs"></i>
            </div>
            <div>
                <p class="text-[13px] text-orange-300">API quota at {{ apiStatus.percentage }}%. Service may be interrupted.</p>
                <p v-if="apiStatus.resets_at" class="text-[11px] text-orange-300/70 mt-0.5">Quota resets at {{ formatResetTime(apiStatus.resets_at) }}</p>
            </div>
        </div>
    </div>

    <div v-if="apiStatus && apiStatus.status === 'exhausted'" class="shrink-0 mx-4 sm:mx-6 mb-2">
        <div class="max-w-3xl mx-auto glass rounded-xl px-4 py-3 flex items-center gap-3 border-red-500/25">
            <div class="w-7 h-7 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-ban text-red-400 text-xs"></i>
            </div>
            <div>
                <p class="text-[13px] text-red-400 font-medium">System busy, try later.</p>
                <p v-if="apiStatus.resets_at" class="text-[11px] text-red-400/70 mt-0.5">Quota resets at {{ formatResetTime(apiStatus.resets_at) }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
var props = defineProps({
    user: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    apiStatus: { type: Object, default: null },
});

function formatResetTime(isoString) {
    if (!isoString) return '';
    var date = new Date(isoString);
    var hours = date.getHours();
    var minutes = date.getMinutes().toString().padStart(2, '0');
    var ampm = hours >= 12 ? 'PM' : 'AM';
    var h = hours % 12 || 12;
    return h + ':' + minutes + ' ' + ampm + ' tomorrow';
}
</script>

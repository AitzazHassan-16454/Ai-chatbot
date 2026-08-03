<template>
    <svg viewBox="0 0 16 16" :width="size" :height="size" fill="none" aria-hidden="true" :class="class">
        <rect
            v-for="i in 25"
            :key="i - 1"
            :data-dot="i - 1"
            :x="1.5 + ((i - 1) % 5) * 3"
            :y="1.5 + Math.floor((i - 1) / 5) * 3"
            width="2"
            height="2"
            :style="{ animationDelay: ((i - 1) * 50) + 'ms' }"
            class="nova-progress-dot"
        />
    </svg>
</template>

<script setup>
defineProps({
    size: { type: [Number, String], default: 16 },
    class: { type: String, default: '' },
});
</script>

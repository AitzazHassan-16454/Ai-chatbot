<template>
    <svg viewBox="0 0 24 24" :width="size" :height="size" :fill="color" aria-hidden="true">
        <path d="M12 2.5c.6 5.6 2.6 7.6 8.2 8.2-5.6.6-7.6 2.6-8.2 8.2-.6-5.6-2.6-7.6-8.2-8.2 5.6-.6 7.6-2.6 8.2-8.2z"/>
        <circle cx="19.4" cy="17.8" r="1.2" opacity="0.8"/>
    </svg>
</template>

<script setup>
defineProps({
    size: { type: [Number, String], default: 20 },
    color: { type: String, default: '#7698fd' },
});
</script>

<template>
    <svg viewBox="0 0 24 24" :width="size" :height="size" :fill="'url(#' + uid + ')'" aria-hidden="true">
        <defs>
            <linearGradient :id="uid" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#818cf8"/>
                <stop offset="55%" stop-color="#c084fc"/>
                <stop offset="100%" stop-color="#f472b6"/>
            </linearGradient>
        </defs>
        <path d="M12 1.8c.62 4.02 2.18 5.58 6.2 6.2-4.02.62-5.58 2.18-6.2 6.2-.62-4.02-2.18-5.58-6.2-6.2 4.02-.62 5.58-2.18 6.2-6.2z"/>
        <path d="M18.8 14.5c.27 1.75.95 2.43 2.7 2.7-1.75.27-2.43.95-2.7 2.7-.27-1.75-.95-2.43-2.7-2.7 1.75-.27 2.43-.95 2.7-2.7z" opacity="0.85"/>
        <circle cx="6" cy="16.5" r="1.15" opacity="0.7"/>
    </svg>
</template>

<script>
var count = 0;
</script>

<script setup>
var uid = 'nova-' + (++count);

defineProps({
    size: { type: [Number, String], default: 20 },
});
</script>

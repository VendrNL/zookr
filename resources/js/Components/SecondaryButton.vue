<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-300 disabled:opacity-50"
    >
        <slot />
    </button>
</template>

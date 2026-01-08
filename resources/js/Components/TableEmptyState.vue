<script setup>
defineProps({
    colspan: {
        type: Number,
        required: true,
    },
    message: {
        type: String,
        default: "Geen resultaten gevonden.",
    },
});
</script>

<template>
    <tr>
        <td :colspan="colspan" class="px-4 py-6 text-sm text-gray-600">
            {{ message }}
        </td>
    </tr>
</template>

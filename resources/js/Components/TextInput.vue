<script setup>
import { onMounted, ref, useAttrs } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const emit = defineEmits(['blur']);
const input = ref(null);
const attrs = useAttrs();

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
        v-model="model"
        ref="input"
        v-bind="attrs"
        @blur="emit('blur', $event)"
    />
</template>

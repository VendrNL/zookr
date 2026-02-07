<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: [String, Number],
    required: true,
});

const emit = defineEmits(['blur']);
const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({
    focus: () => {
        input.value?.focus();
    },
});
</script>

<template>
    <input
        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
        v-model="model"
        ref="input"
        v-bind="$attrs"
        @blur="emit('blur', $event)"
    />
</template>

<script setup>
import { useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["activate"]);
const attrs = useAttrs();

const activate = () => {
    if (props.disabled) return;
    emit("activate");
};
</script>

<template>
    <tr
        v-bind="attrs"
        :class="[
            'cursor-pointer hover:bg-gray-50 focus-within:bg-gray-50',
            props.disabled ? 'cursor-default' : '',
            attrs.class,
        ]"
        role="link"
        tabindex="0"
        @click="activate"
        @keydown.enter.prevent="activate"
        @keydown.space.prevent="activate"
    >
        <slot />
    </tr>
</template>

<script setup>
import { computed, useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
    align: {
        type: String,
        default: "left",
    },
});

const attrs = useAttrs();

const alignClass = computed(() => {
    if (props.align === "center") return "text-center";
    if (props.align === "right") return "text-right";
    return "text-left";
});
</script>

<template>
    <td
        v-bind="attrs"
        :class="[
            'align-top px-4 py-3 text-sm text-gray-700',
            alignClass,
            attrs.class,
        ]"
    >
        <slot />
    </td>
</template>

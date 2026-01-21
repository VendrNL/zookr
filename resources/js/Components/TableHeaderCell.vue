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
    <th
        v-bind="attrs"
        :class="[
            'align-top px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600',
            alignClass,
            attrs.class,
        ]"
    >
        <slot />
    </th>
</template>

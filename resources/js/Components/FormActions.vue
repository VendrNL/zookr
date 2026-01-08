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
    if (props.align === "right") return "justify-end";
    if (props.align === "center") return "justify-center";
    return "justify-start";
});
</script>

<template>
    <div
        v-bind="attrs"
        :class="[
            'flex items-center gap-4',
            alignClass,
            attrs.class,
        ]"
    >
        <slot />
    </div>
</template>

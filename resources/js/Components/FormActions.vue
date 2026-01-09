<script setup>
import { computed, useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
    align: {
        type: String,
        default: "left",
    },
    stackOnMobile: {
        type: Boolean,
        default: true,
    },
});

const attrs = useAttrs();

const alignClass = computed(() => {
    if (props.stackOnMobile) {
        if (props.align === "right") return "sm:justify-end";
        if (props.align === "center") return "sm:justify-center";
        return "sm:justify-start";
    }
    if (props.align === "right") return "justify-end";
    if (props.align === "center") return "justify-center";
    return "justify-start";
});
</script>

<template>
    <div
        v-bind="attrs"
        :class="[
            props.stackOnMobile
                ? 'flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:gap-4 [&>*]:w-full sm:[&>*]:w-auto [&>*]:text-center sm:[&>*]:text-left [&>*]:justify-center sm:[&>*]:justify-start'
                : 'flex items-center gap-4',
            alignClass,
            attrs.class,
        ]"
    >
        <slot />
    </div>
</template>

<script setup>
import { computed, useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
    align: {
        type: String,
        default: "center",
    },
});

const attrs = useAttrs();

const alignClass = computed(() => {
    if (props.align === "left") return "text-left items-start";
    if (props.align === "right") return "text-right items-end";
    return "text-center items-center";
});
</script>

<template>
    <div
        v-bind="attrs"
        :class="[
            'flex w-full max-w-md aspect-[4/3] flex-col justify-center p-6',
            alignClass,
            attrs.class,
        ]"
    >
        <div v-if="$slots.title" class="w-full">
            <slot name="title" />
        </div>
        <div v-if="$slots.body" class="mt-3 w-full">
            <slot name="body" />
        </div>
        <div v-if="$slots.actions" class="mt-8 w-full">
            <slot name="actions" />
        </div>
    </div>
</template>

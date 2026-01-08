<script setup>
import { useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
</script>

<template>
    <div
        v-bind="attrs"
        :class="[
            'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8',
            attrs.class,
        ]"
    >
        <slot />
    </div>
</template>

<script setup>
import { useAttrs } from 'vue';
import zookrLogo from '../../images/Zookr.svg';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
</script>

<template>
    <img
        v-bind="attrs"
        :src="zookrLogo"
        alt="Zookr"
        class="block"
    />
</template>

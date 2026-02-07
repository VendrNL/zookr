<script setup>
import { FwbBadge, FwbButton, FwbCard } from 'flowbite-vue';
</script>

<template>
    <fwb-card class="border border-surface-border bg-surface-base">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-surface-text">
                    Flowbite Design System
                </h3>
                <p class="mt-2 text-sm text-surface-text-muted">
                    Deze kaart gebruikt Flowbite-Vue componenten en Tailwind classes op basis van design tokens.
                </p>
            </div>
            <fwb-badge type="green">
                Live
            </fwb-badge>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <fwb-button color="default">
                Primaire actie
            </fwb-button>
            <fwb-button color="light">
                Secundaire actie
            </fwb-button>
        </div>
    </fwb-card>
</template>

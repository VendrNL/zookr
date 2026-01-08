<template>
    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
        <div v-if="$slots.header" class="border-b border-gray-200 px-6 py-4">
            <slot name="header" />
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <slot />
            </table>
        </div>
        <slot name="footer" />
    </div>
</template>

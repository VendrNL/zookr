<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppFooter from '@/Components/AppFooter.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-50">
        <main
            class="flex flex-1 flex-col items-center justify-center px-6 py-8"
        >
            <Link href="/" class="mb-6">
                <ApplicationLogo class="h-32 w-32" />
            </Link>

            <div class="w-full rounded-lg bg-white shadow sm:max-w-md xl:p-0">
                <div class="space-y-4 p-6 sm:p-8 md:space-y-6">
                    <slot />
                </div>
            </div>
        </main>

        <AppFooter />
    </div>
</template>

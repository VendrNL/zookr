<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageContainer from '@/Components/PageContainer.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <PageContainer>
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Welkom terug
                    </h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Je bent ingelogd en klaar om te starten.
                    </p>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

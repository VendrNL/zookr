<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DesignSystemShowcase from '@/Components/DesignSystemShowcase.vue';
import PageContainer from '@/Components/PageContainer.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Zoekvragen" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                Zoekvragen
            </h2>
        </template>

        <div class="py-8">
            <PageContainer>
                <DesignSystemShowcase />
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

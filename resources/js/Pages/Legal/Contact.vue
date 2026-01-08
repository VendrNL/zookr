<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Contact" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Contact</h1>
        </template>

        <div class="py-8">
            <PageContainer>
                <FormSection>
                    <div class="space-y-4 text-sm text-gray-600">
                        <p>Zookr is een initiatief van RESAAS B.V.</p>
                        <div class="space-y-1">
                            <p>RESAAS B.V.</p>
                            <p>Leon van Leersum</p>
                            <p>Koediefstraat 1</p>
                            <p>2511 CG Den Haag</p>
                        </div>
                        <div class="space-y-1">
                            <p>e-mail: info@zookr.nl</p>
                            <p>telefoon 06-11781051</p>
                        </div>
                    </div>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

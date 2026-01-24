<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Gebruiksvoorwaarden" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                Gebruiksvoorwaarden
            </h2>
        </template>

        <div class="py-8">
            <PageContainer>
                <FormSection>
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Deze gebruiksvoorwaarden zijn van toepassing op het gebruik van
                            Zookr. Door de applicatie te gebruiken ga je akkoord met deze
                            voorwaarden.
                        </p>
                        <p class="text-sm text-gray-600">
                            Je bent verantwoordelijk voor de juistheid van de gegevens die
                            je invoert en voor het vertrouwelijk houden van je
                            inloggegevens. Misbruik of ongeoorloofd gebruik is niet
                            toegestaan.
                        </p>
                        <p class="text-sm text-gray-600">
                            Zookr kan deze voorwaarden aanpassen. De meest recente versie is
                            altijd beschikbaar via deze pagina.
                        </p>
                    </div>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Privacyverklaring" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                Privacyverklaring
            </h2>
        </template>

        <div class="py-8">
            <PageContainer>
                <FormSection>
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Zookr verwerkt persoonsgegevens om de dienstverlening mogelijk
                            te maken. We gaan zorgvuldig om met je gegevens en verwerken
                            alleen wat nodig is voor het gebruik van de applicatie.
                        </p>
                        <p class="text-sm text-gray-600">
                            Gegevens worden niet verkocht aan derden. Je kunt contact
                            opnemen voor vragen over inzage, wijziging of verwijdering van
                            je gegevens.
                        </p>
                    </div>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

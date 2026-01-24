<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const form = useForm({
    file: null,
});

const handleFile = (event) => {
    form.file = event.target.files?.[0] ?? null;
};

const submit = () => {
    form.post(route("admin.organizations.import.store"), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Makelaars importeren" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Makelaars importeren
                </h2>
                <Link
                    :href="route('admin.organizations.index')"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                >
                    Terug naar overzicht
                </Link>
            </div>
        </template>

        <div class="py-8">
            <PageContainer class="max-w-2xl">
                <FormSection>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div>
                            <InputLabel for="file" value="CSV-bestand" />
                            <input
                                id="file"
                                type="file"
                                accept=".csv,text/csv"
                                class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                @change="handleFile"
                            />
                            <InputError class="mt-2" :message="form.errors.file" />
                            <p class="mt-2 text-xs text-gray-500">
                                Excelbestand? Exporteer als CSV (UTF-8) en upload
                                dat bestand.
                            </p>
                        </div>

                        <FormActions align="right">
                            <SecondaryButton
                                type="button"
                                :disabled="form.processing"
                                @click="form.reset()"
                            >
                                Annuleren
                            </SecondaryButton>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing || !form.file"
                            >
                                Importeren
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

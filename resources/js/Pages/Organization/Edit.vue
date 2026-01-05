<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, useForm } from "@inertiajs/vue3";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";
import { ref } from "vue";

const props = defineProps({
    organization: {
        type: Object,
        default: () => ({
            name: "",
            phone: "",
            email: "",
            website: "",
            logo_path: null,
            logo_url: null,
        }),
    },
});

const form = useForm({
    name: props.organization.name ?? "",
    phone: props.organization.phone ?? "",
    email: props.organization.email ?? "",
    website: props.organization.website ?? "",
    logo: null,
    remove_logo: false,
});

const { confirmLeave } = useDirtyConfirm(form);

const newLogoPreview = ref(null);

const submit = () => {
    form.post(route("organization.update"), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const handleFile = (event) => {
    const file = event.target.files[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    newLogoPreview.value = file ? URL.createObjectURL(file) : null;
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    newLogoPreview.value = null;
};

const handleCancel = () => {
    if (!confirmLeave()) {
        return;
    }
    form.reset();
    newLogoPreview.value = null;
};
</script>

<template>
    <Head title="Mijn organisatie" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Mijn organisatie
                    </h1>
                    <p class="text-sm text-gray-500">
                        Beheer organisatiegegevens en logo.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Naam organisatie" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="organization"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Telefoonnummer" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="tel"
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="email" value="E-mail" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                autocomplete="email"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="website" value="Website" />
                            <TextInput
                                id="website"
                                v-model="form.website"
                                type="url"
                                class="mt-1 block w-full"
                                placeholder="https://"
                                autocomplete="url"
                            />
                            <InputError class="mt-2" :message="form.errors.website" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <InputLabel for="logo" value="Logo" />
                        <div class="flex flex-wrap items-center gap-4">
                            <label
                                class="inline-flex cursor-pointer items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            >
                                <input
                                    id="logo"
                                    type="file"
                                    class="hidden"
                                    accept="image/*"
                                    @change="handleFile"
                                />
                                Kies bestand
                            </label>
                            <span class="text-sm text-gray-600" v-if="form.logo">
                                {{ form.logo.name }}
                            </span>
                            <span
                                class="text-sm text-gray-500"
                                v-else-if="organization.logo_path"
                            >
                                Bestaand logo is ingesteld.
                            </span>
                            <span class="text-sm text-gray-400" v-else>
                                Geen logo geüpload.
                            </span>
                        </div>
                        <div v-if="organization.logo_url || newLogoPreview" class="mt-3">
                            <p class="text-sm font-medium text-gray-700">
                                Voorbeeld
                            </p>
                            <img
                                :src="newLogoPreview || organization.logo_url"
                                alt="Organisatielogo"
                                class="mt-2 h-20 w-auto rounded-md border border-gray-200 bg-white object-contain p-2 shadow-sm"
                            />
                        </div>
                        <div class="flex items-center gap-3">
                            <SecondaryButton
                                type="button"
                                :disabled="form.processing"
                                @click="removeLogo"
                            >
                                Logo verwijderen
                            </SecondaryButton>
                        </div>
                        <InputError class="mt-2" :message="form.errors.logo" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            :disabled="form.processing"
                            @click="handleCancel"
                        >
                            Annuleren
                        </SecondaryButton>
                        <PrimaryButton
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Opslaan
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

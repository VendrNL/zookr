<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const WEBSITE_PREFIX = "https://";

const form = useForm({
    name: "",
    phone: "",
    email: "",
    website: "",
    logo: null,
    is_active: true,
});

const logoInput = ref(null);
const logoPreview = ref(null);
const isDragging = ref(false);

const submit = (onSuccess) => {
    form
        .transform((data) => ({
            ...data,
            website: data.website
                ? data.website.match(/^https?:\/\//i)
                    ? data.website
                    : `${WEBSITE_PREFIX}${data.website}`
                : null,
        }))
        .post(route("admin.organizations.store"), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => form.reset("logo"),
            onSuccess,
        });
};

const { confirmLeave } = useDirtyConfirm(form, undefined, {
    onSave: (done) => submit(done),
});

const handleLogo = (event) => {
    const file = event.target.files[0] ?? null;
    form.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : null;
};

const handleDrop = (event) => {
    event.preventDefault();
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    form.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : null;
};

const handleDragOver = (event) => {
    event.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = () => {
    isDragging.value = false;
};

const openLogoPicker = () => {
    logoInput.value?.click();
};

const handleCancel = () => {
    confirmLeave({
        onConfirm: () => {
            window.location.href = route("admin.organizations.index");
        },
        onSave: (done) => submit(done),
    });
};
</script>

<template>
    <Head title="Nieuwe Makelaar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Nieuwe Makelaar
                </h2>
                <Link
                    :href="route('admin.organizations.index')"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    <span class="hidden sm:inline">Terug naar overzicht</span>
                    <span class="sr-only">Terug naar overzicht</span>
                    <MaterialIcon
                        name="reply"
                        class="h-5 w-5 sm:hidden"
                    />
                </Link>
            </div>
        </template>

        <div class="py-8">
            <PageContainer class="space-y-8">
                <FormSection>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="flex flex-wrap items-start gap-4">
                            <div>
                                <input
                                    ref="logoInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    required
                                    @change="handleLogo"
                                />
                                <div
                                    v-if="logoPreview"
                                    class="flex min-h-[140px] w-full max-w-[280px] cursor-pointer items-center justify-center"
                                    role="button"
                                    tabindex="0"
                                    @click="openLogoPicker"
                                    @keydown.enter.space.prevent="openLogoPicker"
                                    @dragover="handleDragOver"
                                    @dragleave="handleDragLeave"
                                    @drop="handleDrop"
                                >
                                    <img
                                        :src="logoPreview"
                                        alt="Makelaarlogo"
                                        class="max-h-[120px] max-w-[220px] object-contain pointer-events-none"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="flex min-h-[140px] w-full max-w-[280px] cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                    :class="isDragging ? 'border-gray-900' : 'border-gray-300'"
                                    role="button"
                                    tabindex="0"
                                    @click="openLogoPicker"
                                    @keydown.enter.space.prevent="openLogoPicker"
                                    @dragover="handleDragOver"
                                    @dragleave="handleDragLeave"
                                    @drop="handleDrop"
                                >
                                    <span class="px-4 text-center text-sm font-medium text-gray-700">
                                        Klik hier om een logo te uploaden of sleep een logo hier naar toe.
                                    </span>
                                </div>
                                <InputError class="mt-2" :message="form.errors.logo" />
                            </div>
                            <div class="ml-auto flex items-center gap-3 text-sm text-gray-700">
                                <span class="text-sm font-semibold text-gray-900">
                                    Actief
                                </span>
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.is_active ? 'bg-blue-700' : 'bg-gray-300'"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    tabindex="0"
                                    @click="form.is_active = !form.is_active"
                                    @keydown.enter.space.prevent="form.is_active = !form.is_active"
                                >
                                    <span
                                        class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                                        :class="form.is_active ? 'translate-x-5' : 'translate-x-1'"
                                    />
                                </button>
                            </div>
                        </div>

                        <div>
                            <InputLabel for="name" value="Naam Makelaar" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="organization"
                                required
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
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="email" value="E-mail" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                autocomplete="email"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="website" value="Website" />
                            <div
                                class="mt-1 flex w-full min-w-0 items-center rounded-base border border-default-medium bg-neutral-secondary-medium shadow-xs focus-within:border-brand focus-within:ring-1 focus-within:ring-brand"
                            >
                                <span class="select-none pl-3 pr-0 py-2.5 text-sm text-body">
                                    https://
                                </span>
                                <input
                                    id="website"
                                    type="text"
                                    class="flex-1 min-w-0 border-0 bg-transparent pl-0 pr-3 py-2.5 text-sm text-heading focus:border-0 focus:outline-none focus:ring-0 placeholder:text-body"
                                    v-model="form.website"
                                    autocomplete="url"
                                    placeholder="www.example.com"
                                    required
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.website" />
                        </div>

                        <FormActions align="right" class="pt-2">
                            <SecondaryButton
                                type="button"
                                @click="handleCancel"
                            >
                                Annuleren
                            </SecondaryButton>
                            <PrimaryButton :disabled="form.processing">
                                Opslaan
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

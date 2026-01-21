<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Modal from "@/Components/Modal.vue";
import ModalCard from "@/Components/ModalCard.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const LINKEDIN_PREFIX = "https://www.linkedin.com/in/";

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: "",
    email: "",
    phone: "",
    linkedin_url: "",
    avatar: null,
    invite: false,
});

const avatarPreview = ref(null);
const isDragging = ref(false);
const avatarInput = ref(null);
const linkedinHandle = ref("");
const showInviteModal = ref(false);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            linkedin_url: linkedinHandle.value
                ? `${LINKEDIN_PREFIX}${linkedinHandle.value}`
                : null,
        }))
        .post(route("organization.users.store"), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => form.reset("avatar"),
        });
};

const confirmInvite = (invite) => {
    form.invite = invite;
    showInviteModal.value = false;
    submit();
};

const setAvatarFile = (file) => {
    if (!file) {
        return;
    }
    if (file.type && !file.type.startsWith("image/")) {
        return;
    }
    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const handleAvatar = (event) => {
    const file = event.target.files[0] ?? null;
    setAvatarFile(file);
};

const onDragOver = () => {
    isDragging.value = true;
};

const onDragLeave = () => {
    isDragging.value = false;
};

const onDrop = (event) => {
    const file = event.dataTransfer?.files?.[0] ?? null;
    setAvatarFile(file);
    isDragging.value = false;
};

const openAvatarPicker = () => {
    avatarInput.value?.click();
};
</script>

<template>
    <Head title="Nieuwe medewerker" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Nieuwe medewerker
                    </h1>
                    <p class="text-sm text-gray-500">
                        Voeg een medewerker toe aan {{ organization.name }}.
                    </p>
                </div>
                <Link
                    :href="route('organization.edit')"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    <span class="hidden sm:inline">Terug naar organisatie</span>
                    <span class="sr-only">Terug naar organisatie</span>
                    <MaterialIcon
                        name="reply"
                        class="h-5 w-5 sm:hidden"
                    />
                </Link>
            </div>
        </template>

        <div class="py-8">
            <PageContainer class="space-y-6">
                <FormSection>
                    <header class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-medium text-gray-900">
                                Profielgegevens
                            </h2>

                            <p class="mt-1 text-sm text-gray-600">
                                Werk profielgegevens, e-mail en avatar bij.
                            </p>
                        </div>
                    </header>

                    <form @submit.prevent="showInviteModal = true" class="mt-6 space-y-6">
                        <div class="flex items-center gap-4">
                            <div
                                class="h-20 w-20 overflow-hidden rounded-full border border-gray-200 bg-gray-50 shadow-sm"
                                :class="{ 'ring-2 ring-blue-500 ring-offset-2': isDragging }"
                                @click="openAvatarPicker"
                                role="button"
                                tabindex="0"
                                @keydown.enter.space.prevent="openAvatarPicker"
                                @dragover.prevent="onDragOver"
                                @dragenter.prevent="onDragOver"
                                @dragleave.prevent="onDragLeave"
                                @drop.prevent="onDrop"
                            >
                                <img
                                    v-if="avatarPreview"
                                    :src="avatarPreview"
                                    alt="Avatar"
                                    class="h-full w-full object-cover pointer-events-none"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-sm text-gray-500 cursor-pointer"
                                >
                                    Kies foto
                                </div>
                            </div>
                            <div class="space-y-2">
                                <input
                                    ref="avatarInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleAvatar"
                                />
                                <InputError class="mt-1" :message="form.errors.avatar" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="name" value="Naam" />

                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name"
                                required
                                autocomplete="name"
                            />

                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="E-mail" />

                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full"
                                v-model="form.email"
                                required
                                autocomplete="username"
                            />

                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Telefoonnummer" />

                            <TextInput
                                id="phone"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.phone"
                                autocomplete="tel"
                                required
                            />

                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="linkedin_url" value="LinkedIn-profiel" />
                            <div
                                class="mt-1 flex w-full min-w-0 items-center rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-gray-900 focus-within:ring-1 focus-within:ring-gray-900"
                            >
                                <span class="select-none pr-0 pl-2 py-2 text-base text-gray-500">
                                    {{ LINKEDIN_PREFIX }}
                                </span>
                                <input
                                    id="linkedin_url"
                                    type="text"
                                    class="flex-1 min-w-0 border-0 bg-transparent px-0 py-2 text-base text-gray-900 focus:border-0 focus:outline-none focus:ring-0"
                                    v-model="linkedinHandle"
                                    autocomplete="url"
                                    placeholder="gebruikersnaam"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.linkedin_url" />
                        </div>

                        <FormActions align="left">
                            <PrimaryButton :disabled="form.processing">
                                Opslaan
                            </PrimaryButton>
                            <SecondaryButton
                                type="button"
                                :disabled="form.processing"
                                :href="route('organization.edit')"
                                as="a"
                            >
                                Annuleren
                            </SecondaryButton>
                        </FormActions>
                    </form>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>

    <Modal :show="showInviteModal" maxWidth="md" @close="showInviteModal = false">
        <ModalCard>
            <template #title>
                <h2 class="text-2xl font-semibold text-gray-900">
                    Uitnodiging
                </h2>
            </template>
            <template #body>
                <p class="text-base font-normal text-gray-900">
                    Wil je deze gebruiker een uitnodiging sturen om zijn account te activeren?
                </p>
                <p class="mt-2 text-sm font-normal text-gray-600">
                    Als je ja kiest, ontvangt de gebruiker een e-mail om een wachtwoord in te stellen.
                </p>
            </template>
            <template #actions>
                <FormActions align="center">
                    <SecondaryButton type="button" @click="confirmInvite(false)">
                        Niet versturen
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="confirmInvite(true)">
                        Uitnodiging versturen
                    </PrimaryButton>
                </FormActions>
            </template>
        </ModalCard>
    </Modal>
</template>

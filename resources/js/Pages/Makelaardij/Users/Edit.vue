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
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const LINKEDIN_PREFIX = "https://www.linkedin.com/in/";

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
    user: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.user.name ?? "",
    email: props.user.email ?? "",
    phone: props.user.phone ?? "",
    linkedin_url: props.user.linkedin_url ?? "",
    avatar: null,
});

const avatarPreview = ref(null);
const isDragging = ref(false);
const avatarInput = ref(null);
const linkedinHandle = ref(
    form.linkedin_url?.startsWith(LINKEDIN_PREFIX)
        ? form.linkedin_url.replace(LINKEDIN_PREFIX, "")
        : form.linkedin_url?.replace("linkedin.com/in/", "") || ""
);
const initialLinkedinHandle = ref(linkedinHandle.value);
const showConfirmModal = ref(false);

const hasChanges = computed(() => {
    return form.isDirty || linkedinHandle.value !== initialLinkedinHandle.value;
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            linkedin_url: linkedinHandle.value
                ? `${LINKEDIN_PREFIX}${linkedinHandle.value}`
                : null,
            _method: "patch",
        }))
        .post(route("makelaardij.users.update", props.user.id), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => form.reset("avatar"),
        });
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

const goBack = () => {
    window.location.href = route("makelaardij.edit");
};

const handleCancel = () => {
    if (hasChanges.value) {
        showConfirmModal.value = true;
        return;
    }
    goBack();
};

const confirmSave = () => {
    showConfirmModal.value = false;
    submit();
};

const confirmDiscard = () => {
    showConfirmModal.value = false;
    goBack();
};
</script>

<template>
    <Head title="Medewerker beheren" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Medewerker beheren
                </h2>
                <button
                    type="button"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                    @click="handleCancel"
                >
                    <span class="hidden sm:inline">Terug naar makelaar</span>
                    <span class="sr-only">Terug naar makelaar</span>
                    <MaterialIcon
                        name="reply"
                        class="h-5 w-5 sm:hidden"
                    />
                </button>
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

                    <form @submit.prevent="submit" class="mt-6 space-y-6">
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
                                    v-if="avatarPreview || user.avatar_url"
                                    :src="avatarPreview || user.avatar_url"
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
                            />

                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="linkedin_url" value="LinkedIn-profiel" />
                            <div
                                class="mt-1 flex w-full min-w-0 items-center rounded-base border border-default-medium bg-neutral-secondary-medium shadow-xs focus-within:border-brand focus-within:ring-1 focus-within:ring-brand"
                            >
                                <span class="select-none pl-3 pr-0 py-2.5 text-sm text-body">
                                    {{ LINKEDIN_PREFIX }}
                                </span>
                                <input
                                    id="linkedin_url"
                                    type="text"
                                    class="flex-1 min-w-0 border-0 bg-transparent pl-0 pr-3 py-2.5 text-sm text-heading focus:border-0 focus:outline-none focus:ring-0 placeholder:text-body"
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
                                @click="handleCancel"
                            >
                                Annuleren
                            </SecondaryButton>
                        </FormActions>
                    </form>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>

    <Modal :show="showConfirmModal" maxWidth="md" @close="showConfirmModal = false">
        <ModalCard>
            <template #title>
                <h2 class="text-2xl font-semibold text-gray-900">
                    Gegevens zijn gewijzigd
                </h2>
            </template>
            <template #body>
                <p class="text-base font-normal text-gray-900">
                    Wil je deze wijzigingen opslaan?
                </p>
            </template>
            <template #actions>
                <FormActions align="center">
                    <SecondaryButton type="button" @click="confirmDiscard">
                        Niet opslaan
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="confirmSave">
                        Wijzigingen opslaan
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="showConfirmModal = false">
                        Annuleren
                    </SecondaryButton>
                </FormActions>
            </template>
        </ModalCard>
    </Modal>
</template>


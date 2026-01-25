<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    specialism: {
        type: Object,
        required: true,
    },
    organizations: {
        type: Array,
        default: () => [],
    },
    return_to: {
        type: String,
        required: true,
    },
});

const form = useForm({
    name: props.user.name ?? "",
    email: props.user.email ?? "",
    phone: props.user.phone ?? "",
    linkedin_url: props.user.linkedin_url ?? "",
    avatar: null,
    remove_avatar: false,
    is_active: props.user.is_active ?? true,
    is_admin: props.user.is_admin ?? false,
    organization_id: props.user.organization_id ?? "",
    return_to: props.return_to,
});

const specialismForm = useForm({
    types: props.specialism.selection?.types ?? [],
    provinces: props.specialism.selection?.provinces ?? [],
    return_to: props.return_to,
});

const avatarPreview = ref(null);
const isDragging = ref(false);
const LINKEDIN_PREFIX = "https://www.linkedin.com/in/";
const linkedinHandle = ref(
    form.linkedin_url?.startsWith(LINKEDIN_PREFIX)
        ? form.linkedin_url.replace(LINKEDIN_PREFIX, "")
        : form.linkedin_url?.replace("linkedin.com/in/", "") || ""
);
const avatarInput = ref(null);

const page = usePage();
const isSelf = computed(() => page.props.auth?.user?.id === props.user.id);

const submit = (callbacks = {}) => {
    form
        .transform((data) => ({
            ...data,
            linkedin_url: linkedinHandle.value
                ? `${LINKEDIN_PREFIX}${linkedinHandle.value}`
                : null,
            _method: "patch",
        }))
        .post(route("admin.users.update", props.user.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.remove_avatar = false;
                if (typeof callbacks.onSuccess === "function") {
                    callbacks.onSuccess();
                }
            },
            onFinish: () => form.reset("avatar"),
            onError: callbacks.onError,
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
    form.remove_avatar = false;
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

const clearAvatar = () => {
    form.avatar = null;
    form.remove_avatar = true;
    avatarPreview.value = null;
    submit();
};

const handleCancel = () => {
    confirmLeave({
        onConfirm: () => {
            router.visit(props.return_to);
        },
        onSave: (done) => saveAndProceed(done),
    });
};

const toggleSpecialism = (key, value) => {
    const list = new Set(specialismForm[key]);
    if (list.has(value)) {
        list.delete(value);
    } else {
        list.add(value);
    }
    specialismForm[key] = Array.from(list);
};

const submitSpecialism = (callbacks = {}) => {
    specialismForm.patch(route("admin.users.specialism.update", props.user.id), {
        preserveScroll: true,
        onSuccess: callbacks.onSuccess,
        onError: callbacks.onError,
    });
};

const saveAndProceed = (done) => {
    let pending = 0;
    let failed = false;

    const handleDone = () => {
        if (failed) {
            return;
        }
        pending -= 1;
        if (pending <= 0) {
            done();
        }
    };

    const fail = () => {
        failed = true;
    };

    if (form.isDirty) {
        pending += 1;
        submit({ onSuccess: handleDone, onError: fail });
    }

    if (specialismForm.isDirty) {
        pending += 1;
        submitSpecialism({ onSuccess: handleDone, onError: fail });
    }

    if (pending === 0) {
        done();
    }
};

const { confirmLeave } = useDirtyConfirm([form, specialismForm], undefined, {
    onSave: (done) => saveAndProceed(done),
});

const provinceFill = (key) =>
    specialismForm.provinces.includes(key) ? "#e5e7eb" : "#ffffff";
</script>

<template>
    <Head title="Gebruiker beheren" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Gebruiker beheren
                </h2>
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
                        <div class="flex flex-wrap items-center gap-6 text-sm text-gray-700">
                            <span>Actief</span>
                            <button
                                type="button"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="form.is_active ? 'bg-gray-900' : 'bg-gray-300'"
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
                            <template v-if="!isSelf">
                                <span>Admin</span>
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.is_admin ? 'bg-gray-900' : 'bg-gray-300'"
                                    role="switch"
                                    :aria-checked="form.is_admin"
                                    tabindex="0"
                                    @click="form.is_admin = !form.is_admin"
                                    @keydown.enter.space.prevent="form.is_admin = !form.is_admin"
                                >
                                    <span
                                        class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                                        :class="form.is_admin ? 'translate-x-5' : 'translate-x-1'"
                                    />
                                </button>
                            </template>
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
                            <InputLabel for="organization_id" value="Makelaar" />
                            <select
                                id="organization_id"
                                v-model="form.organization_id"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                            >
                                <option value="">
                                    Selecteer Makelaar
                                </option>
                                <option
                                    v-for="organization in organizations"
                                    :key="organization.id"
                                    :value="organization.id"
                                >
                                    {{ organization.name }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="form.errors.organization_id"
                            />
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
                                @click="handleCancel"
                            >
                                Annuleren
                            </SecondaryButton>

                            <span
                                v-if="form.recentlySuccessful"
                                class="text-sm text-gray-600"
                            >
                                Opgeslagen.
                            </span>
                        </FormActions>
                    </form>
                </FormSection>
                
                <FormSection>
                    <form class="space-y-6" @submit.prevent="submitSpecialism">
                                        <div>
                                            <h2 class="text-base font-semibold text-gray-900">
                                                Type vastgoed
                                            </h2>
                                            <p class="text-sm text-gray-500">
                                                Meerdere keuzes mogelijk.
                                            </p>
                                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                <label
                                                    v-for="option in specialism.options.types"
                                                    :key="option"
                                                    class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 shadow-sm hover:border-gray-300"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                                        :checked="specialismForm.types.includes(option)"
                                                        @change="toggleSpecialism('types', option)"
                                                    />
                                                    <span class="capitalize">
                                                        {{ option.replaceAll('_', ' ') }}
                                                    </span>
                                                </label>
                                            </div>
                                            <InputError
                                                class="mt-2"
                                                :message="specialismForm.errors.types || specialismForm.errors['types.*']"
                                            />
                                        </div>

                                        <div>
                                            <h2 class="text-base font-semibold text-gray-900">
                                                Provincie
                                            </h2>
                                            <p class="text-sm text-gray-500">
                                                Meerdere keuzes mogelijk.
                                            </p>
                                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                                <label
                                                    v-for="option in specialism.options.provinces"
                                                    :key="option"
                                                    class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 shadow-sm hover:border-gray-300"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                                        :checked="specialismForm.provinces.includes(option)"
                                                        @change="toggleSpecialism('provinces', option)"
                                                    />
                                                    <span class="capitalize">
                                                        {{ option.replaceAll("_", " ") }}
                                                    </span>
                                                </label>
                                            </div>
                                            <InputError
                                                class="mt-2"
                                                :message="
                                                    specialismForm.errors.provinces ||
                                                    specialismForm.errors['provinces.*']
                                                "
                                            />

                                            <div class="mt-6">
                                                <h3 class="text-sm font-semibold text-gray-900">
                                                    Provinciekaart (klik om te selecteren)
                                                </h3>
                                                <div class="mt-3">
                                                    <svg
                                                        viewBox="0 0 199.32 236.75"
                                                        class="h-auto w-full max-h-[500px]"
                                                        role="img"
                                                        aria-label="Kaart van provincies"
                                                        style="border: 0 !important; outline: 0 !important; box-shadow: none !important; display: block;"
                                                        fill-rule="evenodd"
                                                    >
                                                        <g
                                                            stroke="#9ca3af"
                                                            stroke-width="0.5"
                                                            stroke-miterlimit="10"
                                                            stroke-linejoin="bevel"
                                                        >
                                                        <path
                                                            id="Drenthe"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('drenthe')"
                                                            @click="toggleSpecialism('provinces', 'drenthe')"
                                                            d="M160.49,29.52l-.8.4-.4.4v.4l-.8.4-.8.4-.4.4v.3l-.4.4-.4.8-.4.4v.4l-.8,1.9v1.6h-.4l-.4,1.5h-.4l-1.2-.4-.4.8,1.2.8h1.2l.8.4-.4,1.2v1.6l.8,1.2,1.5,2.3.4.8.4.8.4.4-1.2,1.9v.2l-.4.4-.4.4-1.2,1.2-1.5,1.2-1.5-1.5h-1.2l-1.2-.4-.8.4v.7l-.8.4-1.2,1.5-.4.4-.4.4-1.5.8-.8.8-1.2.8.4.8v.4l1.2,1.2.4.4.8.8.4.4.4.4.4.8h.4l-.4.8-.4.4-.8.8v.4h-.4l-.4.4-1.2.4-.4.4h-.1v.4l.4.8.4.8.4.8v-.1.4l.8,1.2v.4l.8.4v.4l-.4.4,1.2.4-.4-.4.4-.4v.4l.4.4v-.4l.4-.4h1.2v.4h1.2v.4l.4.4h.4v.4h.8v-.4h.4v.4h1.2l.4-.4h.8v.4h-.4v.4h.4v.2l.4.4h.4l.4.4h.4v.8h.4v.4l.4.4v.8l.4.4h.4v-.4h.8v-.4h.4v.4h.4v-.4l.4-.4h.4v.8h.4l.4.4.4-.4.4-.4v-.4l.4.4.4.4h1.2l.4.4h.4v-.4l-.4-2.3.8-.8.8-.8.4-.4h1.5l1.2-.4h.4v-.4l.8.4.8.4.8.4,1.5.8h.8l.4.4-.4.4v1.1h.4l.4-.4h.4l.4-.4.4-.8h1.2l.4-.4h2l.4.4-.6-.4h2l.4-.4.4.4h1.2l.8.4v.4h2.8l.4-.4h.4l.4.4.4-.4v.4h.4l1.2.4h.4l.8-.8v-2.7l.4-3.9v-7.3l1.2-2.3h-1.6v-.8l-.4-.4-.8-1.2h-.4v-.4h-1.2v-.4l.8-2.3.4-.4v-.8h-.8l-.4-.4-.4-.4-.4-.8-.8-1.2h-.4v-.4h-.2l-.4-.8-.4-.8-.4-.4-.4-.4v-.4l-.4-.4-.8-.8-.8-.8-.4-.4-1.9-1.9-1.2-1.2-1.2-1.2v-.4l-1.5-1.2-.4-.8-1.5-1.2v-.4h-.3l-.4-.4h-2l-.8.4h-.4l-1.9.8v-.4l-.4-.4.4-.4h-.4v-.4l-.4-.4-.4-.4-.4-.4v.2h-.8v-.4l-.4-.4v-.8l-.8.4-.4-.4v-.4l-.4-1.2-.4.4-.4-.4-.4-1.2h-.8l-.4-.4-.4.4-.4-.4h-.8v.1ZM191.39,58.63h.4v-.4l-.4.4Z"
                                                        />
                                                        <path
                                                            id="Overijssel"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('overijssel')"
                                                            @click="toggleSpecialism('provinces', 'overijssel')"
                                                            d="M143.39,58.63l-1.2.8-.4.4-.4.4h-1.6l.4.8v.4h-.4l-.4.4-.8.4v-.8l-1.2.4-.4.4v-.4h-.4l-.4-.4-.4-.8v-.8l-.8.4v.4l-.8-.4v.4h-.8l-.4.4v.4l-.4.4v.4l-.4.4h-.4v.4h-.4v.2h-1.2l-.4-.4h-.8v-.4h-.8v-.4h-.4l-.8.8.4.8.4.4.4.4v.4h1.6l.4.4,1.2.4.4.8.4.4h.4l.8.8.4,1.2.8,1.5v.4h.4l.4.4v.4l.4.4v.8l-.8.4-.4.4-.4.4v.8h.4v-.4l.8.4h.4l.8.4.4.4.4.4h.8l.4.8-.4.4-1.2.8h-.6l-1.2.4v.4l-.8.4h-.4l-.8.4-.4-.4-.4-.4-3.1.8-.4.4v1.6l.4.4v.4l-1.5-.4.4.4h.8v-.1l.4.4v.8l.4.4.4.4v.8l.4.4v1.6l.8-.4.4.4.8,1.2.4.8.8,1.2v.8l.8-.4.4-.4.4-.4v.4l.4.4.4-.4.8-.8.8-.8.4-.4h.8l.4-.4.4.4h.4l.4.4.4.4v.6l.4.4,1.2.4.4.4v.4l.4,1.2v.8l.4.4.4.4v.8l-.4.4v1l.4.4h.4v1.6h-.4v.4h-.8l-.8.4h-.8v.4h-.4v.4l.4.4h.4l.4.4h-.4l-.4.4h-.4v2.4h-.4v.4h.8v1.6h1.2v.8h.4v1.2l.4.4.4.4v1.2l.4.4h1.2v.4h-.4l-.4.4v.4l.4.8h.8v-.4l.8-.4h.4v.4h1.2l.4.4v-.4h2.4l.4.4.4-.4h1.2v.4h1.2v-.4h.8v-.8h.4l.4-.4h1.2l.4-.4v.4h2v.8l.4.8,1.2.8v.8h.4v.4h.8v.8l.4.4v.4h.4l.4.4.8-.4h.4l1.9.4,1.5-.4h.4l.8.4-.4.4.4.4.4.4v-.4l.4-.4.4-.4.4.4v.4l.4-.8h.4l1.2.8v2l-.8,1.2h1.2l.8.4.8.4h7l.4-.4.4-.4.4-.4v-.4l.4-1.9h.4v-.4l1.2-.8v-.4l1.2-.4h1.2l.4-.8v-1.1l.4-.4v-.4l.4-.4v-1.2l.4-.4.8-.4.4.4h1.6l1.2-.8v-.4l-1.2-1.5-.4-.4v-.8h-.4v-1.5l.4-1.2v-.4h.4v-.4h.4v-.8l.4-.8v-.2l.8-1.2v-1.9l-.8-1.5v-.8h-2.4l-.4-.8-.4-.8-.8-.8v-.4l-.4-.8v-.8l-.4-.8v-.4h-.8l-.4.8-.4,1.2-.8.4v.4h-.4l-1.9-.4-.8-.4-1.2-.4h-.8l.4-.8h-4.8l-.4-.4-1.5-.8-.4-.4-.8-.4.4-3.1-.8-1.2-.8-1.5h.4l.8.4h.8l.4-1.2h.8l.8.4.4-.4-2.3-1.9v-.8l.4-1.5v-.4l-.4-.4-.4-.4h-.4v-1.2l.4-.4-.4-.4h-.8l-1.5-.8-.8-.4-.8-.4-.8-.4v.4h-.4l-1.2.4h-1.5l-.4.4-.8.8-.8.8.4,2.3v.4h-.4l-.4-.4h-1.2l-.4-.4-.4-.4v.4l-.4.4-.4.4-.4-.4h-.4v-.8h-.4l-.4.4v.4h-.4v-.4h-.4v.4h-.5v.4h-.4l-.4-.4v-.8l-.4-.4v-.4h-.4v-.8h-.4l-.4-.4h-.4l-.4-.4v-.4h-.4v-.4h.4v-.4h-.8l-.4.4h-1.2v-.4h-.4v.4h-.8v-.4h-.2l-.4-.4v-.4h-1.2v-.4h-1.2l-.4.4v.4l-.4-.4v-.4l-.4.4.4.4-1.2-.4.4-.4v-.4l-.8-.4v-.4l-.8-1.2v.1-.4l-.4-.8-.4-.8-.4-.8v-.4h.4l.4-.4,1.2-.4.4-.4h.4v-.4l.8-.8.4-.4.4-.8h-.4l-.4-.8-.4-.4-.4-.4-.8-.8-.4-.4-1.2-1.2v-.4l-.2-.4ZM146.09,111.32v.8l.4-.4-.4-.4Z"
                                                        />
                                                        <path
                                                            id="Gelderland"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('gelderland')"
                                                            @click="toggleSpecialism('provinces', 'gelderland')"
                                                            d="M131.39,86.53l-.8.4v.4l-.4,1.2-.4.8v.8l-.4.8v.8l-.4-.4v.8h-.4v-.8l-.4.8v.4h.4v.4h-.4l.4.4-.4.4-.4.4-1.2,1.2-.8.8-.8.4v.4h-.4l-.4.4h-.4l-.4.4v.4h-.4v.4h-.8l-.8.4-.4.4h-.4l-.4.4-.8.4h0l-.4.4-.4.4v.4h-.8v.8h-.4v.4h-.4v.4h-.4v.4l-.8.4-.4.4-.4.4v.4l-.4.8v.8l-.4.8v.4l-.4.8-.4.4h-1.9l-.8.8-.4-.4-.4.4-1.2.4h-.8l-.4.4h-.8l-.4,1.9v.4l.8.4v-.4l1.5,1.2v2.8l.4.4h1.9v.4h-.4l.4.4v.8h.8v.8h.8v.8h-.8v.8h.4v.4h-.8v.4l-.4.4v1.2l-.4.4-.4.4h-.4v.4l.4.4.8.4h.8l.4-.4.4-.4h.4l.4-.4v-.4h.4v-.8l-.4-.4,1.2-.4.4.8v1.2l.4.4-.4.4v2.3h.8v.4h-.4l1.2.8v2.4l.4.4.4.4h.4v.4l.4.8v.4l.4.4-.4.4.4.4v.8l-.4.4-.8.4-.8-.4-1.2-.4-.8-.4-.4-.8h-.8l-.4-.4h-.4l-.8-.4-.4-.4h-2l-.4-.4h-.4l-.4.4-.4.4h-.4l-.8.4h-.4l-.4.4h-.4l-.8-.4-.4.4h-.4l-.4.4v.4h-.4l-.4.4h-.4l-.4-.4-.4-.4h-1.2.3l-.8-.8-.8-.4h-.8v.8l-.4.4-.4.4h-.8l-.4-.4-.4-.4-.4.8-.4,1.2-.8.8-.4.4h.4l-.4.4-.2-.7h-.4v.4l-.4,1.2-.4.4v.4h-1.6v.8l-.4.4h-1.2v.8l.4.4v.4h-.4l-.4-.4-1.2.4-.4-.4-.8.4-.4-.4v.8l.4.4h-.4l.4.4h1.6l-.4,1.5v.4h-.8v.8h.4l.4.4.8.4.4.4h.4v.4l.4.4v.4h.4v.4h.6l.4-.4.4-.4h.4l.4.4.4.4,1.2.4v1.2l.4.4-.6.1-.4.4h-.4v.4l.4.4.8-.4h3.6l.4-.4.4.4.8.4.4.4.4-.4.4-.4h.8l.8.4h.4v-.4l.8-.4,1.9-.8v-.4h.4v-1.6l.4-.8v-.4l.8-1.2.4-.4.4-.4v-.4l.4-.4h.4v.4l.4.4.4.4h2l.4-.4.4-.8v-.4h1.2l.4.4v.4h.8l.4.4h.4l.4-.4v-.4l.4-.4h2l.8.4.4.4h1.2l.4.4v.8l.4.4.4.4.4.4h.8l.4.4h.4v.8h.4l.4.4h.8l.4.4.4.4v.4l.4.4h2l1.2-.4h3.2v-.8l-.4-.4.4-.4h1.2l.4.4.4.4v.4l.4.4v.4l.4.4h.4v.4l.4.4h.4l.4-.4v-.4l.4-.4.8-.4h.4l.4-.8v-.4h-.4v-.8h.4l-.4-.4h-.4v-1.2l-.8-.8h-.8v-.4l.4-.4h.4l-.8-.4.8-1.2h.4v.4h1.2l.8-.4h.4l.4-.4.4-.4.4.4.4-.4.4-.4h.4l-.4-.4.4-.4.4-.4.4.4.8.8h.8l1.5.4h1.2l.4.4v-2l-.8-.4h-1.8v-.8l-.4-.8-.4-.4h-1.2l.4-.4v-.4h.4l.4.4,1.5-.8.4.4h.4l.4.4.8.4v.4h-.8l.4.4.4.4.4.4.8.4h.8v-.4h.8l.4.4h.4v-.4h.4v-.4l.4.4,1.2.4v.4l.4.8v.4l.8-.4h1.2l.4.4h.4l.4.4v.8h1.2l.8.4h.4l-.4-.8.4.4-.4-.8v-.4l.4-.4h-.4v-.4h-.4l-.4-.4v-1.2l.8.4h.4l.4.4h.4v.4h.8l.4-.4.4.8h.4l.4.4.4-.4,1.2-.4v-.8h.4l.4-.4h.8l.4-.4v-.4h.4l.4-.4v.4h.4l.4-.4.8-.4,1.9-.8h.8v-.4l.8-.4.8-.4h.4l.4-.4v.4h.8v.4l.4.4.8.8.8-.4v-.4h.4l.4-.4h.4v-.4l.4-.4h.4l.8-1.2.4-.4v-2l.8-.4h.8l.4-.8-.4-.4v-1.2l-1.2-.4h.4l-2.3-1.5-.4-.4-.4-.8-1.9-.8h-1.5v-.9l.4-.4v-.8h1.2l.8-.4.8-.4v-1.2h.4v-.4l.4-1.5h-3.9l-.8-.4-.8-.4h-1.2l.8-1.2v-2l-1.2-.8h-.4l-.4.8v-.4l-.4-.4-.4.4-.4.4v.4l-.4-.4-.4-.4.4-.4-.8-.4h-.4l-1.5.4-1.9-.4h-.4l-.8.4-.4-.4h-.2v-.4l-.4-.4v-.5h-.8v-.4h-.4v-.8l-1.2-.8-.4-.8v-.8h-1.8v-.4l-.4.4h-1.2l-.4.4h-.4v.8h-.8v.4h-1.2v-.4h-1.2l-.4.4-.4-.4h-2.4v.4l-.4-.4h-1.2v-.4h-.4l-.8.4v.4l.4.4-.4.4v-.8h-.4l-.4-.8v-.4l.4-.4h.4v-.4h-1.2l-.4-.4v-1l-.4-.4-.4-.4v-1.2h-.4v-.8h-1.2v-1.6h-.8v-.4h.4v-2.4h.4l.4-.4h.4l-.4-.4h-.4l-.4-.4v-.4h.4v-.4h.8l.8-.4h.4v-.4h.4v-1.6h-.8s0-.8,0-.8v-.4l.4-.4v-.8l-.4-.4-.4-.4v-.8l-.4-1.2v-.4l-.4-.4-1.2-.4-.4-.4v-.6l-.4-.4-.4-.4h-.4l-.4-.4-.4.4h-.6l-.4.4-.8.8-.8.8-.4.4-.4-.4v-.4l-.4.4-.4.4-.8.4v-.8l-.8-1.2-.4-.8-.8-1.2-.2-.3ZM118.59,100.42l-.8-.4.4.4h.4Z"
                                                        />
                                                        <path
                                                            id="Utrecht"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('utrecht')"
                                                            @click="toggleSpecialism('provinces', 'utrecht')"
                                                            d="M86.89,105.13v.4h-.4v.8l-.8-.4v.4l-.8.4v.4h-1.2l-.8-.4-.4.4v.4l.4.4-.4.4-.4.4v.4h-.7v.4l-.4-.4h-.8l-.4.4-.8.4v.4h-.4l-.4.4h-.4v.4h-.4l-.4.4h-1v.4l.4.4v1.2h.4v.4l.4.4h.4v.4l.4.4.4.4h.8l.4.4,1.5,1.2-1.2.4v1.5h-2v-.4h-.8l-.4.4-.4.4h.4l-.4.4v.4h-.8v.4l1.5.8.4.4h-.4v1.2l.4,1.2v.4h.4l-.4.4h2.3l-.4.4-.4.4h-.4l-1.2.8-.4,1.2-.4.4h-.1v.4l-.4.4.4.8h.4l.4-.4h1.6l.4,1.2h-.4l-1.5.4.4.4.4.8.4.8.4.4.4.4.4.4v.4l.4.4v1.2h1.3l.4-.4h.4v-.4h.4v-.4l.3.3.4,1.6.2-.3.2-.2.4,1.5.4.2v.3l.4.2v1l1.1-.5v.6l.1.5v.7l-.1.3.1.3,1.4-.7-.1.9.1.3.2.6-.1.6,1.2-.4.4.4h.4v-.4l-.4-.4v-.8h1.2l.4-.4v-.8h1.6v-1.2l.4-.4.4-1.2v-.4h.2l.4-.8.4-.4h-.4l.4-.4.8-.8.4-1.2.4-.8.4.4.4.4h.8l.4-.4.4-.4v-.6h.8l.8.4.8.8h1.6l.4.4.4.4h.4l.4-.4h.4v-.4l.4-.4h.4l.4-.4.8.4h.4l.4-.4h.4l.8-.4h.4l.4-.4.4-.4h.4l.4.4h2l.4.4.8.4h.4l.4.4h.8l.4.8.8.4,1.2.4.8.4.8-.4.4-.4v-.8l-.4-.4.4-.4-.4-.4v-.4l-.4-.8v-.4h-.4l-.4-.4-.4-.4v-2.4l-1.2-.8h.4v-.4h-.8v-2.3l.4-.4-.4-.4v-1.2l-.4-.8-1.2.4.4.4v.8h-.4v.4l-.4.4h-.4l-.4.4-.4.4h-.8l-.8-.4-.4-.4v-.4h.4l.4-.4.4-.4v-1.2l.4-.4v-.4h.8v-.1h-.4v-.8h.8v-.8h-.8v-.8h-.8v-.8l-.4-.4h.4v-.4h-1.9l-.4-.4v-2.6l-1.5-1.2v.4l-.8-.4v-.4l.4-1.9-.4-.4-.4-.4-.4-.4h-.4v-.3l-.4-.4h-1.2l-.4-.4h-.8l-.4-.4h-1.5l-.8-.4-1.2,2.3v.8l-.4.8-.4,1.2-.4.8v.8l-.4.4v.4l-.8,1.5h-1.9l-2.3-.4h-.4l-1.9.8-1.5.4v-1.5h-.5v-1.2h-.4l-.4-.4h.8v-2.4h.4l.8-.4-.8-.4v-1.2h-.4v.4l-.4-.4-.4-1.2v-.4h.8v-.4h.8v-.4h.8l-.4-.4h-.4l-.4.4h-.4l-.4-.4-.4.4v-1.5h-.7v.1Z"
                                                        />
                                                        <g
                                                            id="Noord-Holland"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('noord_holland')"
                                                            @click="toggleSpecialism('provinces', 'noord_holland')"
                                                        >
                                                            <path d="M71.39,48.93l-.8.4h0l-.4.4h-.4v.4h-.4l-.4-.4h0v-1.2h1.2l1.2-.4h.8v.4h0l-.8.4Z" />
                                                            <path d="M73.29,49.72l-.8,1.2v1.5l-.4,1.2v2.7l-.7,2.3-.4,2.3-.8,2.3v.8h-.4v.8l-.8,1.5v.8l-.8,2.3v2.8l-.4,1.5v1.5l-.4,1.5v2l-.4.4v.4l-.3,2.7-.4,3.5-.4,1.9-.4.8-.3.8-.4.8-.4,1.1.8-.4h.4l.8-.4.4.4h-.4v.4h-.8l-.4.4h.8v.4l-.8-.4h-.4v.4l-.8-.4v.8l.4.8-.4.4v.7l-.4.8-.4,1.5-.4,1.2-.8,1.9-1.5,3.1,2.7,1.2,1.2.4v-.8l.4.4.4-.4h.8l.8.4-.4.8-.4.4v.8h-.4v.8l-.4.4-.4.4-.4.4v2l-.4.4v.4h-.4v1.2h.4l.4.4h.4l.4-.4.8.4h2l.4-.4.8-.4h.2v-.4l.4.4h.8l.4-.4.4-.4,1.2.4-.4,1.2.4.4.4.4.4-.8h1.2l.4-.4v-.4h2.2l.4-.4h.4v-.4h.4l.4-.4h.4v-.4l.8-.4.4-.4h.8l.4.4v-.4h.8v-.4l.4-.4.4-.4-.4-.4v-.4l.4-.4.8.4h1.2v-.4l.8-.4v-.4l.8.4v-.8h.4v-.4h.8v1.5l.4-.4.4.4h.4l.4-.4h.4l.4.4h-.8v.4h-1.2v.4h-.8v.4l.4,1.2.4.4v-.4h.4v1.2l.8.4-.8.4h-.4v2.4h-.8l.4.4h.4v1.2h.4v1.5l1.5-.4,1.9-.8h.4l2.3.4h1.7l1.2-2.3v-.8l.4-.8.4-1.2.4-.8v-.8l1.2-2.3.8.4h1.5l-.8-.8v-.4l.4-.4h-.8l-.4-.4h-.5l-.4-.4h-.4v-.4l-.4-.4-.4.4h-2.3l-.4.4h-.4l-.4.4h-.4v-.4h-.4l-.4-.4-.4-.4-.4-.8h-.4v-.4h-1.2l-.4-.8h-1.7l-.8.4-.4-.4v.4h-.4v-.4h.4v-.4l-.4-.4h-.4l-.4.4h-.8v-.3h.4v-.8l-.8-.4h-.4v.4h-.4l-.4-.4h-.4v-.4h.8l.4-.4-1.2-.8v-.4l.8.4.4.4h.4l.4-.4v.4l.4.4.4-.4v-.8h.4l.4-.4v-.8l.4-.4.4-.4.4-.4h.8v-.4l.4-.4v-.4l.8-.8h-.8l-.4-.4v.4l-.8-.4v-1.2l.4-.4v-.4h-.4l-.4.4-.4-.1.8-.4h.8v-.4l-.4-.4v-.8l.4-.4.4.4v-.3l.4-.4.4-.8h-.4v-.8h-.4v-.4l-.4-.4v-.4l-.4-.8v-.4l-.8-1.2v-.4l-.4-.2v-.8l-.4-.8v-.4l-.4-.4.4-.4-.4-.4v-2.4h.4v-.2h.4l.4-.4h.8v.4l.4.4v-.4l.4-.4h1.2l.4.4.4.4h.4l.4.4v.4h1.6l.4-.4.8-.8h1.2v-.4l.4-.4h.4v-.4l.8-.4v-.4l.4-.4v-1.2l.4-.8h1.2l.4-.4h.4l-.4-.4h.4l.4-.4.4-.4-.4-.4-.4-.4v-2.2h-.4l-.4-.4-.8-.4h-1.6l-.4-.4h-1.2l-.4.8-.4.4v.4l-.6-.3h-1.2l-.8-.8-.4-.4-.4-.4.4-.8h-.4l-.4-.4h-.4l.8-5.8-.8-1.2-1.2-2.3v-.4l-.2-.3-1.2-2.3h-.4v-.8l-.4-.4h-.4l-.4.4h-.4l-.4-.4h-.4l-.4.4h-.8l-.7.8v.4l-.8,1.2h-.3l-.8.4-.4.4-1.5.8-.4-.4-1.2-.8h-.4l-1.2-.8-.4-.4-.4-.4-.3-.8v-1.6l.4-.8v-.4h-.4v.4h-.8v-.8h-2.3v.1ZM76.79,50.13v-.4h-.4l.4.4ZM88.79,52.43l.4.4v-.8h-.4v.4ZM85.29,99.72v.4h.4l-.4-.4ZM91.89,91.13v.8l-.4.8h.4l.4-.4h.4l.4-.4h.3v-.4l-.8.4-.4-.4-.3-.4ZM102.39,104.72v.4h.4l-.4-.4Z" />
                                                            <path d="M79.89,43.13l-.8.8-.4.3h-1.2l-1.1,1.6v.4h0v.4h-.8l-.4-.4-.8-.4h-.4l.4.4.4.4h0l.4.4h.4-.8v.4h-1.2.4l-.4.4h-.4l-.4.4h-.4v-1.2l-.4-1.5v-1.3l.4-1.2v-.4h0l.4-1.2.4-1.2.4-1.2.4-.4.8-1.2.8-1.5,1.2-1.2h0v.8l.4-.4h0l.4-.4h0l.4-.4h.4v.4-.4h-.4v-.1l-.4.4h0l-.8.4h0l1.2-2.3.4-.4.8-.4v-.4h.8l.4.4.4.4v.8h.4-.4v.4l.4.4h0l.2.3.8,1.2v.4h0l-.4.4v3.9l-.4.7h-.4l-.3.4h0v.8l-.8.8v.3Z" />
                                                            <path d="M95.39,47.02h0l-5.4,4.6-.4.4h-.4v-.4h.8l5.4-4.6Z" />
                                                        </g>
                                                        <path
                                                            id="Limburg"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('limburg')"
                                                            @click="toggleSpecialism('provinces', 'limburg')"
                                                            d="M131.39,148.92l-.4.4.4.4v.8h-.4l.4.4h.4v.8l.4.8v.8l.4.4h.8l.8.4h.8l.4.4h.4l.4,1.2v1.2h.4v2.8l.4.4v.4l1.2.4.4.4.4.4.4.4v.4l.4.4v1.6l.4.4.4.8v1.6l.4.4h-.8v.4l-.8-.8-.4-.4h-.6v-.4l-.8.4-.8.4-1.2.4-.8.4h-1.6l-.8-.4-1.2-.4-1.5-.4.4,3.5.4,1.2v.8l.4.8.8,3.5v.4l1.5,2.7.8,1.5.8,1.2-.8.4-1.2,1.2-.8.8h-.4l-.4.4h-.4l-1.2.4-3.1.8-.4.4h-.4l-.8.4-1.9.4h-.4l-1.5.8-1.2,1.2-.4.8-.8,1.5-.4,1.9v1.5l-1.2.4h-.4l-1.2.4.8.4,3.1,1.2.4.4v.4l.4.8.4-.4.4.4h3.2l.4-.4h.4l.4.4h.8l.4.4v.4h.4l-.4.4h-.2v.4h.4v1.2h.4v-.4h.8v-.4h.8v-.4l.8-.4v.4h.4v.8l1.2.8-.4.4-.4.4v.4l-.8.4h-.4v.4l-.4.4-.4.4.4.4h.8l.4.4v.4l-.4.4v.4h-.4l-.4-.4h-.8v.8l.4.4v.4h-.4v.4l.4.8h-.4v.4h-.4l-.8-.4-.4,1.2v.8l-.4.4v.4h.4l.4.4v.4l-.4.8v1.2l-.4.4-.4.4-.4.8h-.4l-.4.8-.4.8v.4h.8l.4-.4h.4l.4.4v.4l-.8.4v.8l-.8.8v.4l-.4.4.4.4-.4.4h-1.2l-.4.4v.8l-.8.8v.4h-.4l-.4.4h-.4v.4h-.8l.4.4-.4.4v2.4h.4v.8h.4v.4l.4.4h.8v.4h.8l.4.4v.4l-.4.4.4,1.2v.8l-.8,1.2v.4h.8l.4-.4h.4l.4-.4v.4l.4.4h.4l.4-.4v-.4l.4-.4.8-1.2h.8v.8l.4.8.4-.4.8,1.2h.4l.4-.4.4.4.4-.4.4-.4v1.2l.4-.4.4-.4v-.4h.8l.8-.4v1.2l.8.4v-.4h.8v.4l.8-.4.4-.4h1.6l.4.4.8.4h.4v-.4h1.2v-.8l.4-.8-1.2-.8h-.4l-.4-.4v-.4h-.4l-.4-.4v-2.8l.4-.4.4.4.8.4.4-.8.4-.4v-.4h.4l-.4-.4v-.4l-.4-.4v-.8l.4-.4h.8l.4-.4h.4v-.4h.4v.4l.8.4v-1h.4v-.4l.4-.4v-.8l-.4-.4v.4l-.4-.8v-.8l.4-.4v-.4l.4-.4v-.4l-.4-.4h-1.6v-.8l-.4.4-.4-.4-1.2-.4v-1l-.8-.4.4-.4h.4v-.4l.4-1.5h-2.3l-.4.4h-.4l-.4-.4v-.4h-.4l-.8.4h-.4l-.4.4h-.4l-.4.4h-.4l-.4-.4.8-.8v-1.2h-.4l-.4-.4v-.4h-.4v-.4h-.4v-1.9l-.4-.4-.4-.8h1.6v-.4l.8-.4.4-.4v.4l.8,1.2.4.8v.4l.4-.4h.4v.4h.4v-.4l.4-.4v-.4l.4-.4v-.8l.4-.4v-.4l.4-.4v-.4l.4-.4.8-.4.4-.4.8-.4h.4l.4-.4.4-.4v-.4l.4-.4h.8l.4-.4v-.8l.4-.4h.8l.4-.4h.4v-.4h.8l.4-.4h1.2l-.4-.4h.4l.4-.4-1.5-1.2h-.4l2.3-1.2-.8-.8-2.3,1.2-.4.4-.4.4h-2.4v-.4l-.4-.4v-2.7l-.4-.4.4-.4h.8l-.8-1.5.8-.4.4-.8,1.5-1.5v-.4l.4-.4.8-1.2.4-.8v-.4l.4-.4v-.8l.4-.4v-.4h1.2v-.4l1.9-1.9-.4-1.2-.4-.8v-.4l.4-.4.4-.4h-1.2v-.4l.4-2.3v-1.2h.4l-.4-.8.4-.4v-1.2l-.4-.8-.4-.8.4-.8-.4-.4v-.8.5l-.4-.8-.8-.4-.8-.4-.8-2.3-.8-.4-.4-.4-.4-.4-.4-.8v-.4h-.4l-1.2-.8v-1.6h.4v-.4l.4-1.2v-.4l.4-.4v-.4l-.8-.4h-.8v-.1h-.8l-1.9-.8-.4-.4v-.8l.4-.4-.4-.4v-1.2h.8v-.4h-.4v-.8h-.4l-1.2-.8-.4-.4h-2.4v-.4l-.4.4h-.4l-.4-.4v-.2h-.4l-.4-.4v-.4l-.4-.4v-.4l-.4-.4-.4-.4h-.7v.1Z"
                                                        />
                                                        <g
                                                            id="Flevoland"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('flevoland')"
                                                            @click="toggleSpecialism('provinces', 'flevoland')"
                                                        >
                                                            <path d="M118.99,79.53l-.4.4-2.7.8h-.4l-.4.4-3.1,2.7v.4h-2.3v.4l-.4,1.2v-.4l-.4.8v1.6l-.8.4h-.4l-.4.4-.4.8h-.8v.4l-6.6,4.3-1.2.8v.4h-.4v.4h-.6l-2.5,1.5-.4.4-1.5,1.2h-.4v.4l.8.8v2l-.4.4h.5l.4.8h-.4l.4.4.4-.4h.4l1.5-.4h.8l.4.4h1.6l1.2.4.8.8,1.2.4.8.8,3.5,3.1.4.8h.4l.8.4.8-.4,1.9-.8h1.2l.4-.4,1.2-.4h.4l.4-1.5.4-.4v-1.2h.4v-2h-.7v-.4l-.4-.8.4-.4,1.2-1.2.8.4,1.2-.4h.4l.4-.4h-.4l.4-.4.4-.4.4-.4,1.2-1.2,3.1-1.5h.8l.4.4h.4l1.5-1.2.4-.8.8-.8.4-.8.4-.4.4-.8,1.2-2.3.4-.8v-3.1l-.4-.8-1.9-1.9v-.4h-2.7l-1.5-.4-3.5-1.2h-.4l-.4-.8h-.8v-.1ZM97.49,95.42l.6-.4-.8.4h.2Z" />
                                                            <path d="M123.29,59.72l.4.8-.8-.4v.4h-.4l-1.9.4h-.4v.4l-1.2,1.9-1.9,3.1v.4l-.4,1.2v7.3h.4l.4.4.4.4.8,1.2,1.2,1.5.4.4h9.4l3.1-.8.4.4.4.4.8-.4h.4l.8-.4v-.4l1.2-.4h.4l1.2-.8.4-.4-.4-.8h-.8l-.4-.4-.4-.4-.8-.4h-.4l-.8-.4v.4h-.4v-.8l.4-.4.4-.4.8-.4v-.8l-.4-.4v-.4l-.4-.4h-.4v-.1l-.8-1.5-.4-1.2-.8-.8h-.4l-.4-.4-.4-.8-1.2-.4-.4-.4h-1.6v-.4l-.4-.4-.4-.4-.4-.8-.4.4v-.4l-.4-.8-1.5-1.9h-.5l-1.1-.3Z" />
                                                            <path d="M123.99,79.92h.8l.4.4-.4.4h-.4v.4h0l-.4-.4h0v-.4h-.4v-.4h.4Z" />
                                                            <path d="M101.19,72.53h.8l2.7,1.2.4.4,1.9,1.9.8,1.2v.4l1.9,3.1.8,1.9v.4h-.4l-.4.4-.8.4-.4,1.5-.4.8h.4l-.4.8v-.8h0l.4-.8.4-1.5.4-.4.8-.4h.4v-.4l-.8-1.9-2.7-4.6-1.9-1.9-.4-.4-2.3-1.2-.4.8h-1.2.4l.4-.4.4-.4h-.8v-.1Z" />
                                                        </g>
                                                        <g
                                                            id="Friesland"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('friesland')"
                                                            @click="toggleSpecialism('provinces', 'friesland')"
                                                        >
                                                            <path d="M95.39,47.02l1.5-1.5.4-.4,4.3-3.9h.4l1.2-.4h.4v-.4.4l.4.4-.4-.4h-.4l-.8.4h-.8l-3.5,3.5-.4.4h-.4l-.4.4-1.5,1.5h0Z" />
                                                            <path d="M105.49,41.52v-.3l-.4-.8h.4v.4h.8v.8h.4v.4h0v.4h-.4l-.4-.4-.4-.5Z" />
                                                            <path d="M145.69,12.12l-1.9.8h-.4l-.8-.4h-1.2v.4h-.8l-.4-.4-1.5.4h-1.2l-.4.4-1.5.4h-.8l-1.5.8h-1.9v-.4l-.4-.4v.8l-.8.4-.8.4-.4.4-.8.4-.4.4-.8.4h-.6l-1.2.8-1.5.8-.8.4-1.9,1.2h-1.2l-.4.1-.8.4h-.4l-.4.4-.8.4-.8.4-.4.4-.8.8-.4.8-.4.4v.4h-.4v.4l-.8.4-.4.4h-.2l-.8.8-.4.4h-.4l-.4.4-.4.8h-.4l-.4.4-.4.8-.4,1.2-.4.4v.4h.4l-.4.4-.4.4h-.2v.4h.4v.8l-.4.8v2.4l-.4.4v.4l-.8.4h-.4l.4.4-.4.4v.4l-.4.4h.4l.4.4.4.8v1l.4.8h-.4l.4.4h-.4v-.4h-.8v.8h.8v1l.4-.4v.4h-.4v.4h.4l.4.4v1.2l-.4.4v.8l.4,1.2v.8l.4.4v.1l.8-.5-.8.8v-.3l-.4.3v1h-.4l-.4.4.4.4.4.8v1.6l-.4.4h-.8l-.8.8-.4.4-.4.4.4.4v.8h.8v.4h.4l.8.8.8.8h2v-.4l1.2.4h.8l.4.4,1.5.8h.8v.4h.8l.4-.4h.4v-.4l.4-.4v-.4l.8-.4.8.4.4-.4.8-.4h.4l.4.4v.8h2.4v.4h.4l.4-.4v.4l.8.4-.4-.8,1.2.4h.4l1.5,1.9.4.8v.1l.4-.4.8-.8h.4v.4h.8v.4h.8l.4.4h1.2l.4-.4h.4v-.4h.4l.4-.4v-.4l.4-.4v-.4l.4-.4h.8v-.4l.8.4v-.4l.8-.4v.8l.4.8.4.4h.4v.4l.4-.4,1.2-.4v.8l.8-.4.4-.4h.4v-.4l-.4-.8h1.6l.4-.4.4-.4,1.2-.8,1.2-.8.8-.8,1.5-.8.4-.4.4-.4,1.2-1.5.8-.4v-.5l.8-.4,1.2.4h1.2l1.5,1.5,1.5-1.2,1.2-1.2.4-.4.4-.4v-.4l1.2-1.9-.4-.4-.4-.8-.4-.8-1.5-2.3-.8-1.2v-1.6l.4-1.2-.8-.4h-1.2l-1.2-.8.4-.8-1.2-.4v-.4l-.4-.4-.8-.4-.4-.4h-4.2l-.4-.8-.4-.4-.4-.4v-3.5l.4-.4.4-.8v-.4l.4-.4h.4l.4-.4.4-.8v-1h-.8v-.4l.4-.4.4-.4.4-.4v-.8l.4-.4v-.4h.4l.4-.4h-.4v-1.2l.4-.4h.4l.4-.4v-.4l.4-.4h.4v-.4h-.4v-2.5h-.4v-.4h-1.2l-.4.4h-.8l.4.4v.4h-.4v-.4l-.4-.4v-.4h-.4l.4-.4h-.4l-.4-.4v.4l-.4.8v-.8l-.8-.4-.4.4v.4l.8.8v.4h-.4v-.4l-.4.4v-.4l-.4-.4v-1.6l-.4-.4v-.4l.6-.3h-.4v-.8l-.4-.4.4-.8v-.8l.7-.3ZM148.09,17.12v.4l.4.4-.4-.8ZM105.49,39.22l-1.2,1.2h-.8l.4.4h.4l.4-.4.8-1.2ZM108.19,31.52v-.4l-.4.4h.4ZM145.69,19.52h.4v.4l-.4-.4ZM144.99,19.83h.4l-.4.4v-.4ZM106.99,43.13h.4l-.4.4v-.4Z" />
                                                            <path d="M139.89,8.62v.4l-.4.4h-.4v-.8h0l.4-.4h.4v.4Z" />
                                                            <path d="M123.69,8.22h2.4l5-.4,2.3-.4h1.2l.8.4h.4v.4h0v.4h-3.6l-.8.4h0l-.8.4h-.4l-1.5.4-1.5.4h-1.6l-1.2-.4h-1.6v.4l-.4.4-.4.4h-.8l-.4.4h-.8.4l-.4.4-.4-.4h-.8l-.4-.4-.8-.4-.4-.8v-.4l.4-.4.4-.8.4-.4.8-.8h.8l.4.4h.4l.4.4h-.4l-.4-.4h-1l-.4.4h1.2l1.5.4s2,0,2,0Z" />
                                                            <path d="M154.29,4.03v.8h-1.6l-.8.4h0l-1.2.4-.3.3v.4l-.8.4h-1.6l-1.2.4h-1.8l-.4.8v.3h-.4v.4h-.8v.4h0l-.4-.8h0v-.4h.4l.4-.4-.4-.3v-1.2l.4-.4.8-.4v-.4h-1.6.4l.4-.4.8.4h1.2l3.9-.4h1.2l2.3-.4.8-.4.3.5Z" />
                                                            <path d="M99.29,26.02h0l-.4-.4h1.6-.4v.4h-.8v-.4h0v.4Z" />
                                                            <path d="M108.59,12.92h-1.5l-.8.4h-.4l-.4.4h0l-.8.8-.4.4h-.8v.3h-.4l-.4-.4v.4h-.3l-.4.4h-.4v.4h0l-.4-.4h-1.2l-.4.4-.4.4-.4-.4-.4.4h-.4l-.4.4v.4h-.4l-.8.4h.4l-.4.4h-1l-.4-.8h-.4v-.4l.4-.4.8-1.2.8-1.2h0l.8-.4.8-.4,1.5-.4,2.3-.4h.8l2.3-.8,1.9-.8,4.3-1.5h1.2l1.2-.4.4.4h.4l.4.4h0v1.2h0v-.7l-.4-.4h0v.4l-.4.4h0l-.4.4h-1.3l-.8.4h0l-.4-.4h0v.4h.4l-.4.4h-.8v-.4h-.4l.4.4v.4h-.8l-.4-.4h0l.4.4.4.4h.8l-.4.4h-1.8v-.1Z" />
                                                            <path d="M94.99,21.83h0l-.4.4-.4.4h-1.2v.4h-.4v-.4h.4-.4l.4-.4.4-.4h1.6Z" />
                                                            <path d="M89.99,22.22v.4l-.4.4-.8.4-2.3.8-.8.4v1.2h.4v-.4h.4l-.4.4-.4.8h0l-1.2.6-.4.4h-.4v.4l-.3.4-.8.4h-2.4l-.4.4h0v.4h.4-.8v-1.6l2.3-1.2,1.2-1.2.8-.4.8-.8.4-.4,1.5-1.2.8-.4.4-.4h0l1.2-.4.8-.4.8-.4h.4l1.2.4v.4l-.5.6h-1.5Z" />
                                                            <path d="M154.29,4.03h.8v.4h0l-.8.4v-.8Z" />
                                                        </g>
                                                        <g
                                                            id="Groningen"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('groningen')"
                                                            @click="toggleSpecialism('provinces', 'groningen')"
                                                        >
                                                            <path d="M173.99,7.13l-.8.4-3.1.4-.3.3-1.2.4h-.4l-.4.4h-.8l-.8.8h-1.2l-.8.4-.8.4-.4-.4-.4.4h-1.9l-1.2.4-1.5.4h-.6l-.8.4h-.8l-1.2.4-1.2.4-.4.4v.4l-.8.4-.4-.4-.4-.4-1.9-1.2h-.4l-1.5.8v-.4h-.4v.4h-.4v-.4l-.4.4.4.4h.4l.4.4h.4l.4.4h.4v.4h-1.2v-.4l-.8-.4v-.4h-.8l-.4.8h.4l.4.4h.4v.4l-.4.4-.4-.4v.4l.4.4h2v.4h-1.2v.4h1.2l.4-.4v.8h-.4l.4.4.4-.4h.4v.4l-.4.4h-.8v.4l.4.4.4-.4h1.2l.4.4h.4v2.4h.4v.4h-.4l-.4.4v.4l-.4.4h-.4l-.4.4v1.2h.4l-.4.4h-.4v.4l-.4.4v.8l-.4.4-.4.4-.4.4v.2h.8v1.6l-.4.8-.4.4h-.4l-.4.4v.4l-.4.8-.4.4v3.5l.4.4.4.4.4.8h3.2l.4.4.8.4.4.4v.4l1.2.4,1.2.4h.4l.4-1.5h.4v-1.6l.8-1.9v-.4l.4-.4.4-.8.2-.7v-.4l.4-.4.8-.4.8-.4v-.4l.4-.4.8-.4h1.2l.4.4.4-.4.4.4h.8l.4,1.2.4.4.4-.4.4,1.2v.4l.4.4.8-.4v.8l.4.4v.4h.8v.4l.4.4.4.4.4.4v.4h.4l-.4.4.4.4v.4l1.9-.8h.4l.8-.4h2l.4.4h.4v.4l1.5,1.2.4.8,1.5,1.2v.4l1.2,1.2,1.2,1.2,1.9,1.9.4.4.8.8.8.8.4.4v.4l.4.4.4.4.4.8.4.8h.4v.4h.4l.8,1.2.4.8.4.4.4.4h.8v.8l-.4.4-.8,2.3v.4h1.2v.4h.4l.8,1.2.4.4.4-.4v.4h-1.2v.8h1.2l-.4-.8v-1l.8-1.2,2.3-3.9,1.5-2.7.8-3.1.4-1.9.4-.4v-.4l-.8-3.5v-5l-1.2-.8v-1.5l.4-.4v-1.2l.4-1.2h.4l.4-.4h.8l-.8-.4-.4-.4.4-.4v-1.9l-.4-1.2v-.4h-.4l-1.2-.4h-.4v-.4h-1.6v-.4h-1.4l-.4-.4h-.4l-.4-.4-.4-.4v-1.2l.4-.1-.4-.8v-.4h.4l.4-.8v-.4l-.8.4-.4.4h-2.4l-.8-.4v-.4h-.8l-.8-.4-.8-.4v.4l-.8-.4-.8-.4h-.4v-.4h.4l-.4-.4-.8-.8h-.4l-.4-.4v-2l-.4-.4v-1.6l-.4-.8h-.4v-1.2l.4-1.2v-.4l-.8-.8-.8-.4-.4.8.4.4h-1.2v-.4l.4.4v-.4l.4-.8h-.4l-.8-.4-.7.8-.4-.4-.8-.4h-1.1l-.4-.3ZM183.69,18.33l.4.4v-.4h-.4ZM197.59,26.02v-.8l-.4.4.4.4Z" />
                                                            <path d="M161.19.52v.4h0l.4.4.4.4.4.4-.4-.4h-1.6v.8h.4l-.3.7-.4-.4-.4-1.2-.4-.4v-.3l.4-.8h1.2l.3.4h0Z" />
                                                            <path d="M165.09.93h1.6l.7.3v.4-.4h-2.4l-.4.4h0v-.4l.5-.3h0Z" />
                                                            <path d="M146.09,15.22l.4.4v-.4h0l.8.4h0v.4h-.8l-.4-.4v-.4h0Z" />
                                                        </g>
                                                        <g
                                                            id="Zeeland"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('zeeland')"
                                                            @click="toggleSpecialism('provinces', 'zeeland')"
                                                        >
                                                            <g id="g3511">
                                                                <path d="M40.39,158.62v.4l-.8.8h-.4l.4.8h.4l.4.8.4.8h2.4v-.4h.4v.8l-.4.4h-2l-.4.4h-.4l-.8-.8-1.5-.4h-.4l-.8.4-.4.4h-.4l-.7.2-.4.4-.4-.4-.8.4-.4.4v.8h.4v.4h.8l.4.4.4.4h.4v.8l.4.4h.4l.4.4.4.4v.8l.4.4h.4v.4h3l.4.4h.4v-.4l.8.4.8.4-.4.4v.8h.4l1.5.8.4.4v2.7l.4,1.5h-1.3l-.4-.4h-1.2l-.4.4h-.4l-.4.4h-1.2v-.4h-.2l-1.2-.8h-.4l-.4-1.2h-.4l-.8-1.2v-.4l-.4-.4.4-.4-.4-.4v-.8h-.4l-.4-.4-.6-.8-.4-.4h-.8l-.4-.4h-.4l-.8-.4h-.4l-.4-.4-.4.4h-.4v.4l-.8-1.2h.4l-.4-.4v-.4h-1.2l-.4.4h-2.6v-.4l-.4-.4h-.8l-.8.4h-.4l-.4.4-1.2-.4h-.4l-.4-.4-.4.4-.8.4v.4l-.4.4v.4l-.4.4h-.4l-.4.4h.8l-.8.4v-.4l-.8-.8-.4-.4v-.4l-.4-1.2v-.4h-.4l-.4-.4-.8-.4-.4-.4-.4-.4v-1.2l.4-.4h-.4l-.4-.4h-2.8v.4h-.8l-.4.4-.8.4-.4.4-.8.4h-.1l-.4.4-.4.4-.8.4-.4.4h-.4l-.4.4-.4.4v1.2l.4.8h.4l.4.4.4.4.8.4v.4h.4l.4.4.4.4v.4l.4.4v.4l.4.4v.4l.4.4.4.4.8.4v.4h.4l.4.4v.4h1.6v-1.2h.4v.8l.4.4h1.2l.4-.4v-.6h1.3l-.8-.4.4-.4.8-.4h.4v.4l1.2-.8v-.8l.4.4v.4h.4l-.4.4v.8h.4l-.4.4.4.4v.4l-.4-.4-.4.4h-.4l.4.4.4.4v.4h.4v1.2h.4l.4.4h.4v-.4h.4l.8.4h.4v.4l.8.4v.4h.4l.8.8h1.2l.8-.4h0l.4-.4h1.2l.4.2.4-.4.4-1.5h.4v-.8h.4v-2.3l.8.4.8-.4h.4l.8-.8.4.4.2.4.4.4v.4h.4v.4h.8l.4.4.4.4h.8v1.2l.8.4h.4l.4.4h.4l.8.4.8.4h.8l.4.4h.8l1.2-.4.8-.4h.8v.4h.4,0l.8.4.4,1.2v.8h1.2l.8-.4-.4-.4h-.4v-.4l.4-1.2-.4-1.2v-.4l.8-.4-.8-1.2-1.5-4.3-.8-1.5v-.4l.4-.8.4-1.2.4-.8v-.4l-.4-.4v-.8l-1.5-1.9-.4-.4v-1.6l.4-.8.4-.4.4-.4,1.2-.4h-.4l-.8-.4h-1.6l-.8-.4-1.2-.8h-.4l-1.2-.1ZM18.29,176.03l.4-.8-.4.4v.4Z" />
                                                                <path d="M42.69,182.62v.4h.4v.4h-.4v.4h.4-.8l-.4-.4v-.8h.8Z" />
                                                                <path d="M30.69,182.62h.4l.4-.4v.4h-.8Z" />
                                                                <path d="M41.49,183.72h0v.4h0v.4h-.8v-.4h-.3.8l-.4-.4h.4-.4l-.4.4v-.4h.4-.4l.4-.4h-.8v-.4.4h-.4v-.4h1.2l.4.4h0l.3.4Z" />
                                                                <path d="M7.89,178.72v.4h-.8l-.8.4h-.8l-.4.4h-1.9v.4h-.4l-.4.3h-.7l-.4.4h-.4v.4l.4.4v1.6l.4.4v.8l-1.2,1.2-.4.4.4.4.8.8H.49v.4l.4.4.4.4v.4l-.4.2.4.4h.4l.4.8h.3l.4.8.4-.4.4.4v.8h.4l.4.4h1.6l3.1-.4-.5-2.8-.4-.4.8-.4.4.4h.4v-.4h.8v-.4h.4l.4.4.4.4.4-.4-.4-.8.8-.4.4.4.8.4.4.4.8.4h.4v-.4h.8l.8.8h.4l.4.4v.4h1.2l.4.4v-.4l1.5.4h.4l.4.8h.4v-.4l.4.4.4.4h.4v.4l-.4.4v2.8l.4.4h.4v-.4l.4.4h2.4-.1l.8-.8h.8v.8h-.4l-.4.4.4.4h.8l.8-.4h.4l-.4-.8.8-.4.4.8h.4v-.4h.8l.4-.4v-.6h.8l.4-.8h.2l.4-.4.8-.4h.4v.4h.4l.4-.4v.4h.8l.4-.4h.4l.4-.4.8-.8.8-.4.8-.4,1.9-1.5.4-.4,3.9-4.6h-.4v-.4h-.6v-.8l-.4.4v-.8l-.4-.4h-.8l.4-.4h-.8v.4l-.4.4v.4l.4.4h.4v.8l-.4-.4-.4.4h.4l-.4.4h.4v.4l-.4-.4h-.8l.4.4v.4l-.4-.8-.4-.4h-.4l.4.4.4.4h-.4v-.4h-1.2v.4h.4v.4h-.4l-.8-1.5h-.4l-1.2-.8v-.4h-.4v.4l-1.5-.4-.4-.4v-.4l-.4-.4v-.4l-.4-.4.4-.4h-.1l-.4-.4v-.4h-1.2l-.4-.4h-.8l-.4.8v.4h-.4v2l-.4.4h-.8l-1.2.4-.8.4-.4.4h-.4v.4h-.8v.4l-.4.4h-1.4l-.4-.4h-.4v.4l-.4-.4v.8l-.4-.8v-.4h-.8v-.4h-1.2v1.2l-.8-.4v-.4l.4-.4h-2.4v-.4l-.4-.4-.4-.4h-.4v-.4l-.4-.4h-.8l-.8-.4h-.4l-1.2-.4h-.4v-.4h-.4l-.8-.4h-.4l-.4-.4-.4-.4h-.4l-.4-.4h-.4v-.4h-2.1v.1ZM43.09,184.12l-.4-.4-.4.4h.8ZM42.69,183.72h1.2l-.4-.4h-.8v.4ZM43.49,182.62l.4.4h-.4v-.4ZM44.29,182.62h.4l-.4.4v-.4ZM41.49,185.33h.4v.4h-.4v-.4Z" />
                                                                <path d="M17.89,167.53h0l-.4-.4v-.4h.4l.4.4v.4l-.4.4h0v-.4Z" />
                                                                <path d="M19.89,158.62h0l-.4.4v1.2h0l.4.4.4-.4-.4-.4h0l.4.4-.4.4h0l-.8-.4v.8h0l-.4-.4h0l-.4.4h.4v.4h-.4v-.4l-.4.4h0v1.2h-.4l.4-.8v-.4l-.4-.4h-.4.4l.4-.8.8-.4v-.4h0v-.4h0v.4h.8v-.4l.4-.4h0Z" />
                                                                <path d="M18.69,176.42l-1.2-.8-.4-.4h0l.4-.8.4.4.8.4-.4.4v.4l.4.4Z" />
                                                                <path d="M19.09,174.83h-.4l-.8-.4h0l.8-.4h.4v.8Z" />
                                                                <path d="M29.89,151.62h0v-.4h.4l.8.4h-1.2Z" />
                                                                <path d="M44.29,160.12v-.4l-.4-.4-.4-.4v.4h-.4l-.8-.8.4-.4h-.4v.4h-.8v-.3h2.4-.8v.4h.8v.4l.4.4v.7h0Z" />
                                                                <path d="M32.29,150.83l-.4.4h-.8v-.4h1.6-.4Z" />
                                                                <path d="M31.09,160.12l-.4.4h-.8l-.8-.4h-.4l.8-.4v-.4.4l-.8.4h-.3l.4-.8-.4-.4v-.7h-.4v-.8l-.4-.8-.8-.4-.4.4v-.4l-1.2-.4h0v-.4h0v-.4l-.4-.4h-1.2l-.4.4v.4h.4l.4-.4.4.4-.4-.4-.4.4h-.8l-.4.8-.8.4h-.6l-.8.4-.4.4v.8h.4v.4h-.4l-.4-.4h.4v-.8h0l-.4-.4h-.8v-.4l-.4-.4-.4-.8v-2l.4-.8.8-.8.8-.4h.8l1.5-.4.4-.4h1.6l1.2.4v-.4h0v-.4l.4-.4v-.4h.7l-.4.4h.4l-.4.4h0v-.4h-.4v.4l-.4.8h2.8l.8-.4h0l.4.4h0l.4.4v.4h1.2l.8-.4.4.4h1.2v.4h0v-.4l.4.4v.8h0l.4.4.8.8.4.8v.8h1.4l.8.4h0l1.2.8h.4v.4h.4l.8-.8h1.8l.4-.4h.4v1.2h.4l-.4.4h-.4l.4-.4h0l-.4-.4v-.4h-.4v.4l-.4-.4-1.2.4h-.4l-.4.4h-.4v.4l.4.4h0v.4h0l-.4.4h.4v-.4l-.4.4v.4h-.4l-.4.4-.4.8h-.4l-.4.4-.4-.4-.8.4-.4.4h-.8v.4h-2.6l-.8-.8h0l-.3-.9h0Z" />
                                                                <path d="M27.19,167.83v-.4h-.4.4v.4h0Z" />
                                                                <path d="M26.09,162.42h.3v.4h.8l.4.4v.4l.4.8.4.4v.4h.4v.8h0v.4h0l-.4.4h-.8l-.4.8h-.8l-.4-.4-.4-.4h-1.2v.4h-1.6l-.4-.4h-.8l-1.5.4-.4.4-.4.8h-.4l-.4.8h0v-.4l-.4-.4.4-.4v-.8l-.8-.8h.4v-.4l.4-.4h0l-.4.4h0l-.4.4h0l-.4-.4-.4-.4h-.4l-.4-.4h-.4v-.8h-.4v-.8h.4-.8l1.5-.4,1.2-.4v-.4h.4v.4h.8l.4.4h.4l.4.4h.4l.4-.4h.4l.8-.4h0l.8.4.4-.4h0l.8-.4h.8l.4.4.8-.4h.1Z" />
                                                            </g>
                                                        </g>
                                                        <g
                                                            id="Zuid-Holland"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('zuid_holland')"
                                                            @click="toggleSpecialism('provinces', 'zuid_holland')"
                                                        >
                                                            <path d="M32.29,148.12l.4.4h0l-.4-.4h-.8l-.8.4-.8.4h-.4v-.4h0l.8-.4v-.4l.4-.4.4-.4v.4h.4l.8.8h0Z" />
                                                            <path d="M60.49,102.82l-2.3,5-.8,1.2-.4.8-1.2,1.9-.4.4v.4l-.8,1.2-.4.8-1.2,1.5-2.3,3.1-1.2.8v.4l-.8.8h-.4v.8h.4l-.4.4v-.4h-.4l-.4.4-.4.8-.4.4-1.2,1.2-.8.8-1.2,1.5-.4.4-.8.8-.4.4v.4l-.8,1.2-.8.4-.8.8h-.4l-.4-.4-1.9-.4-.4.4h-.8l-.4.4v.4l-.4.4-.4.8.4.4.4.4v.4l-.8.8-.4.8-.8,1.2h.8l.4.4.4-.4,1.5-.4h.4l.4.8h-.4v.4l-.8.8v.4l-.4.4v.4l.4.4,1.5,2.3v.8l-.4.4.8-.4.4.4.4.4.4.4v.4h2.2l-.4.4.4.4h.4v-.4h1.2l.4.4.8,1.2h1.2v.4h.8l.4.8.4.4h.6l-.4.4h-.4l.4.8.4.4,1.2.4v.4h.8l.8.4h.8v.4l1.4-.5.8.8h.4l.8.4,1.2.8h.4l.4.4h.8v.4h1.9l.8.4h.8l.4.4h.4l.4.4h.4l.8-.4h.4l1.2-.4h.4l.4-.4h.8l.4-.8.4.4h.4l.8-.4v.4l1.5-.4.4-.4.4-.4.8-.4,1.2-.8.4-.8.4-.4.4-1.2.4-.4.4-.4v-.4l.4-.4h.4l.8-.4h2.2l.8-.4h.4l.8-1.2h.4l.8-.4.4-.4h1.4l.8.4h1.2l.4.4h2v-.4l.4-1.5h-1.6l-.4-.4h.4l-.4-.4v-.8l.4.4.8-.4.4.4.1-.6-.2-.6-.1-.3.1-.9-1.4.7-.1-.3.1-.3v-.7l-.1-.5v-.4l-1.1.5v-1l-.4-.2v-.3l-.4-.2-.4-1.5-.2.2-.2.3-.4-1.6-.3-.3v.4h-.4v.4h-.4l-.4.4h-1.6v-1.2l-.4-.4v-.4l-.4-.4-.4-.4-.4-.4-.4-.8-.4-.8-.4-.4,1.5-.4h.4l-.4-1.2h-1.6l-.4.4h-.3l-.4-.8.4-.4v-.4h.4l.4-.4.4-1.2,1.2-.8h.4l.4-.4.4-.4h-2.3l.4-.4h-.4v-.4l-.4-1.2v-1.2h.4l-.4-.4-1.5-.8v-.4h.6v-.4l.4-.4h-.4l.4-.4.4-.4h.8v.4h2v-1.5l1.2-.4-1.5-1.2-.4-.4h-.8l-.4-.4-.4-.4v-.4h-.4l-.4-.4v.2h-.5v-1.2l-.4-.4v-.4h-1.2v.4l-.4.4h-1.2l-.4.8-.4-.4-.4-.4.4-1.2-1.2-.4-.4.4-.4.4h-.8l-.4-.4v.4h-.4l-.8.4-.4.4h-2l-.8-.4-.4.4h0l-.4-.4h-.4v-1.2h.4v-.4l.4-.4v-1.8l.4-.4.4-.4.4-.4v-.8h.4v-.8l.4-.4.4-.8-.8-.4h-.8l-.4.4-.4-.4v.8l-1.2-.4-2.7-1ZM37.69,143.12l-.4.4h.4v-.4Z" />
                                                            <path d="M34.99,142.33l-.8.4h.8v-.4ZM34.99,142.72l-1.2.4h-.8l.8-.4-1.2.4-2.3.8-.8.4h-.4l-1.2.4h-.4l-.4.8v.8l.4,1.2v.4h.4v.4h.5l.8-.4v-.4h-.4v-.4h1.2l.4-.4h1.2l.4-.4.4-.4.8.4h.8l.8.4v.4l.4.4.4.8h.4v1.2h-.6v.4h.4l-.4.4v.4l.4.8h.4v.4l.4.4v.4l.4.8v.8h.8l.4.4.4-.4v.4h2.2l.4.4,1.2.4.4.8.4.4h1.2v.4h.8l.4.4h.4v.8l.8.4.4.4h2l.8-.4h1.2l1.2-.4.8-.4.4-.4.4-.8v-.4h-.4v-.4h1.6l.8-.4h-.4v-.8h-.4l-.4.4h-3.6l-.8-1.2h-.8l-.4-.4h-.4l-.4-.4v-.4l-.8-.4v-1.1h-.4l-1.2-1.2-1.2-1.2h-.4v-.4h-.8l-.4-.4h-.4l-.8-.8-.8-.4h-.4l-1.5-.8-.4-.4-1.2-.4v-.4l-.4-.4h-.4v.4l-.4-.4h.4v-.6h-.4v.4h-.4v-.4l-.8-.4v-.4l-.3-.3ZM27.59,148.12l-.4.8-.4.4-.8.4h.4l.8.4.4.4h.4v-.4l-.4-.4-.4-.4.4-1.2Z" />
                                                            <path d="M50.09,150.83l.8.4,1.2.4.8.4h0l.4.4h-.4l-.8.4h-1.6l-.4-.4-.4-.4h-.4l-.8-.8h-.4l-.4-.4h0l.4-.4h.8l1.2.4Z" />
                                                            <path d="M33.39,150.03h.4l.4-.4h0v.8l.4,1.2.8.8h0l-.4.4h-.4v-.4l-.4-.4v-.4l-.4-.8-.4-.4h0s0-.4,0-.4Z" />
                                                            <path d="M55.49,155.12l-.4.4h.4-.4v.4l-.4-.4h0l.8-.4Z" />
                                                        </g>
                                                        <g
                                                            id="Noord-Brabant"
                                                            class="cursor-pointer"
                                                            :fill="provinceFill('noord_brabant')"
                                                            @click="toggleSpecialism('provinces', 'noord_brabant')"
                                                        >
                                                            <path d="M81.79,144.62l-.4.4-.8.4h-.4l-.8,1.2h-.4l-.8.4h-2.6l-.8.4h-.4l-.4.4v.4l-.4.4-.4.4-.4,1.2-.4.4-.4.8-1.2.8-.8.4-.4.4-.4.4-1.5.4v.4l-.8.4-.4.4h-.4l-.4.4-.4.4-3.1.8h-.8l-1.2.4h-.8l-1.2-.4-.4-.4h-.4l-.4-.4h-1.2l-.4.4-.8.4-.4.4v.4l-.4.8-.4.4-.4.4-.4.4-.8.4h-.4l-.4.4h-.4v-.4h-.4l-.4.4h-1.2l-.4.4h-.8l-.4.8h.4v-.4h-.8l-1.2.4-.4.4-.4.4-.4.8v1.6l.4.4,1.5,1.9v.8l.4.4v.4l-.4.8-.4,1.2-.4.8v.4l.8,1.5,1.5,4.3.8,1.2-.8.4v.4l.4,1.2-.4,1.2v.4h.4l.4.4h2.7v.4l.4.4v1.2l.4-.4,1.2.4h1.6l.4-.4.8-.4h.4v-1.7l-1.9-2.7-.4-1.2.4-.4h.4v-1.6h-.4l-.4-.4.4-.4,2.7-1.2h.8l.4-.4.4-.4h.7l2.7-.4.4.8-.8,1.9.4,2.3,1.9-.8h1.2l1.5.4.8.4h.4v-.4l.4.4.8-.4-.4-1.5,1.2-.4h.4l.4-1.2.8-.4.4-.4.4-.8.8-.4.4-.4-.4-.4h.8v-.4l.8-.4.4.4v.4h.8l.8.4h.4v.8l.4.4.4-.4.4.4-.4.8v.8h.4v.4h-.4l-.4.4-.4.4.4,1.5v.4h.8v.4h-.4l-1.2-.4-.4-.4h-.8v-.4h-.4l-.8.4v.8l.4.4.8.4h.4l1.2-.4h1.6l.4.4h.4v-.4h1.6l.8.8v.4h.4l.4.4h.4l1.5-2.3,2.3-1.5v-.8h.4v-.4l-.4-.4v-.4l.4-.4v-.4l.4-.4.8-.4h-.1l-.4.4.4.4v.4h2l1.2,3.5-.4.8-1.2,2.3,2.3,2.7v.4l.4.4.4.4v2.7l1.5.4.4-.4,1.2-.4h.4v-.4l2.3,1.5-.8,3.1.4.4,1.5-.4,1.5.4h2l.4-.4.4-.8,1.2.4,1.5.4.8.4,1.2-1.2.4-.4,1.2-.4.4-.4.4-.4v-.4h.4l1.5.4v.4l.4.8,1.2.8.4.4v2.4l.4,1.5h.4l1.2-.4h.4l1.2-.4v-1.5l.4-1.9.8-1.5.4-.8,1.2-1.2,1.5-.8h.4l1.9-.4.8-.4h.4l.4-.4,3.1-.8,1.2-.4h.4l.4-.4h.4l.8-.8,1.2-1.2.8-.4-.8-1.2-.8-1.5-1.5-2.7v-.4l-.8-3.5-.4-.8v-.8l-.4-1.2-.4-3.5,1.5.4,1.2.4.8.4h1.6l.8-.4,1.2-.4.8-.4.8-.4v.4h.4l.4.4.8.8v-.4h.8l-.4-.4v-1.6l-.4-.8-.4-.4v-1.6l-.4-.4v-.4l-.4-.4-.4-.4-.4-.4-1.2-.4v-.4l-.4-.4v-2.1h-.4v-1.2l-.4-1.2h-.4l-.4-.4h-.8l-.8-.4h-.8l-.4-.4v-.8l-.4-.8v-.6h-.8l-.4-.4h-2.8l-1.2.4h-2l-.4-.4v-.4l-.4-.4-.4-.4h-.8l-.4-.4h-.4v-.8h-.4l-.4-.4h-.8l-.4-.4-.4-.4-.4-.4v-.8l-.4-.4h-1.2l-.4-.4-.8-.4h-2l-.4.4v.4l-.4.4h-.4l-.4-.4h-.8v-.4l-.4-.4h-1.2v.4l-.4.8-.4.4h-2l-.4-.4-.4-.4v-.4h.4l-.4.4v.4l-.4.4-.4.4-.8,1.2v.4l-.4.8v1.6h-.4v.4l-1.9.8-.8.4v.4h-.4l-.8-.4h-.8l-.4.4-.4.4-.4-.4-.8-.4-.4-.4-.4.4h-3.3l-.8.4-.4-.4v-.4h.4l.4-.4.4-.4-.4-.4v-1.2l-1.2-.4-.4-.4-.4-.4h-.4l-.4.4-.4.4h-.4v-.4h-.4v-.4l-.4-.4v-.4h-.4l-.4-.4-.8-.4-.4-.4h-.4v-.8h-1.2l-.4-.4h-1.2l-.8-.4h-1.2v.2ZM82.19,180.62h-.8l.4.4.4-.4ZM81.79,176.42h.4v.4l.4.4h.4v.8h-.4l-.4-.4h-.4l.4-.4-.4-.4v-.4ZM82.59,176.42l.4.4h-.4v-.4Z" />
                                                            <path d="M82.19,176.42v.4l.4.4h0v-.8l.4.4h-.4v.4h.4v.4h0v.4h-.4l-.4-.4h-.4l.4-.4-.4-.4v-.4h.4Z" />
                                                        </g>
                                                        </g>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <FormActions align="right">
                                            <SecondaryButton
                                                type="button"
                                                :disabled="specialismForm.processing"
                                                @click="specialismForm.reset()"
                                            >
                                                Annuleren
                                            </SecondaryButton>
                                            <PrimaryButton
                                                :class="{ 'opacity-25': specialismForm.processing }"
                                                :disabled="specialismForm.processing"
                                            >
                                                Opslaan
                                            </PrimaryButton>
                                        </FormActions>
                                    </form>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>



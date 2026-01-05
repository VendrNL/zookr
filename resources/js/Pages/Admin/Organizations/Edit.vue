<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
    members: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.organization.name ?? "",
    phone: props.organization.phone ?? "",
    email: props.organization.email ?? "",
    website: props.organization.website ?? "",
    logo: null,
    is_active: props.organization.is_active ?? true,
});

const { confirmLeave } = useDirtyConfirm(form);

const logoInput = ref(null);
const logoPreview = ref(null);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            _method: "patch",
        }))
        .post(route("admin.organizations.update", props.organization.id), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => form.reset("logo"),
        });
};

const handleLogo = (event) => {
    const file = event.target.files[0] ?? null;
    form.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : null;
};

const openLogoPicker = () => {
    logoInput.value?.click();
};

const handleCancel = () => {
    if (!confirmLeave()) {
        return;
    }
    window.location.href = route("admin.organizations.index");
};
</script>

<template>
    <Head title="Organisatie beheren" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Organisatie beheren
                    </h1>
                    <p class="text-sm text-gray-500">
                        Bewerk gegevens en bekijk de medewerkers.
                    </p>
                </div>
                <Link
                    :href="route('admin.organizations.index')"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    Terug naar overzicht
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <form class="mt-4 space-y-4" @submit.prevent="submit">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-900">
                                Status
                            </span>
                            <div class="flex items-center gap-3 text-sm text-gray-700">
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
                            </div>
                        </div>

                        <div>
                            <input
                                ref="logoInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleLogo"
                            />
                            <div
                                class="inline-flex cursor-pointer items-center justify-center text-sm text-gray-500"
                                role="button"
                                tabindex="0"
                                @click="openLogoPicker"
                                @keydown.enter.space.prevent="openLogoPicker"
                            >
                                <img
                                    v-if="logoPreview || organization.logo_url"
                                    :src="logoPreview || organization.logo_url"
                                    alt="Organisatielogo"
                                    class="max-h-[94px] max-w-[188px] object-contain"
                                />
                                <span v-else class="max-h-[94px] max-w-[188px]">
                                    Logo
                                </span>
                            </div>
                            <InputError class="mt-2" :message="form.errors.logo" />
                        </div>

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
                            <div
                                class="mt-1 flex w-full items-center rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-gray-900 focus-within:ring-1 focus-within:ring-gray-900"
                            >
                                <span class="select-none pl-2 text-base text-gray-500">
                                    https://
                                </span>
                                <input
                                    id="website"
                                    type="text"
                                    class="flex-1 border-0 bg-transparent px-0 py-2 text-base text-gray-900 focus:border-0 focus:outline-none focus:ring-0"
                                    v-model="form.website"
                                    autocomplete="url"
                                    placeholder="www.example.com"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.website" />
                        </div>

                        <div class="flex items-center gap-3 pt-2">
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
                                class="text-sm text-gray-500"
                            >
                                Opgeslagen.
                            </span>
                        </div>
                    </form>
                </section>

                <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">
                            Medewerkers
                        </h2>
                        <p class="text-sm text-gray-500">
                            Gebruikers gekoppeld aan deze organisatie.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Naam</th>
                                    <th class="px-6 py-3">E-mail</th>
                                    <th class="px-6 py-3">Telefoonnummer</th>
                                    <th class="px-6 py-3">LinkedIn-profiel</th>
                                    <th class="px-6 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-if="members.length === 0">
                                    <td class="px-6 py-6 text-gray-500" colspan="4">
                                        Geen medewerkers gevonden.
                                    </td>
                                </tr>
                                <tr v-for="member in members" :key="member.id">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 overflow-hidden rounded-full bg-gray-100">
                                                <img
                                                    v-if="member.avatar_url"
                                                    :src="member.avatar_url"
                                                    alt=""
                                                    class="h-full w-full object-cover"
                                                />
                                            </div>
                                            <Link
                                                :href="route('admin.users.edit', { user: member.id, return_to: route('admin.organizations.edit', organization.id, false) })"
                                                class="font-medium text-gray-900 hover:text-gray-700"
                                            >
                                                {{ member.name }}
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-gray-700">
                                        <a
                                            class="text-gray-900 hover:text-gray-700"
                                            :href="`mailto:${member.email}`"
                                        >
                                            {{ member.email }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 text-gray-700">
                                        {{ member.phone || "—" }}
                                    </td>
                                    <td class="px-6 py-3 text-gray-700">
                                        <a
                                            v-if="member.linkedin_url"
                                            class="inline-flex h-8 w-8 items-center justify-center text-gray-600 hover:text-gray-900"
                                            :href="member.linkedin_url"
                                            target="_blank"
                                            rel="noreferrer"
                                            aria-label="LinkedIn-profiel"
                                        >
                                            <svg
                                                viewBox="0 0 72 72"
                                                class="h-5 w-5"
                                                role="img"
                                                aria-hidden="true"
                                                fill="none"
                                            >
                                                <path
                                                    d="M8,72 L64,72 C68.418278,72 72,68.418278 72,64 L72,8 C72,3.581722 68.418278,-8.11624501e-16 64,0 L8,0 C3.581722,8.11624501e-16 -5.41083001e-16,3.581722 0,8 L0,64 C5.41083001e-16,68.418278 3.581722,72 8,72 Z"
                                                    fill="#007EBB"
                                                />
                                                <path
                                                    d="M62,62 L51.315625,62 L51.315625,43.8021149 C51.315625,38.8127542 49.4197917,36.0245323 45.4707031,36.0245323 C41.1746094,36.0245323 38.9300781,38.9261103 38.9300781,43.8021149 L38.9300781,62 L28.6333333,62 L28.6333333,27.3333333 L38.9300781,27.3333333 L38.9300781,32.0029283 C38.9300781,32.0029283 42.0260417,26.2742151 49.3825521,26.2742151 C56.7356771,26.2742151 62,30.7644705 62,40.051212 L62,62 Z M16.349349,22.7940133 C12.8420573,22.7940133 10,19.9296567 10,16.3970067 C10,12.8643566 12.8420573,10 16.349349,10 C19.8566406,10 22.6970052,12.8643566 22.6970052,16.3970067 C22.6970052,19.9296567 19.8566406,22.7940133 16.349349,22.7940133 Z M11.0325521,62 L21.769401,62 L21.769401,27.3333333 L11.0325521,27.3333333 L11.0325521,62 Z"
                                                    fill="#FFF"
                                                />
                                            </svg>
                                        </a>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-700">
                                        <span
                                            v-if="member.is_active === false"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                        >
                                            Inactief
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

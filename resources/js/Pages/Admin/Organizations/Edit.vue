<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import TableRowLink from "@/Components/TableRowLink.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const WEBSITE_PREFIX = "https://";

const stripWebsitePrefix = (value) => {
    if (!value) return "";
    return value.replace(/^https?:\/\//i, "");
};

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
    website: stripWebsitePrefix(props.organization.website ?? ""),
    logo: null,
    is_active: props.organization.is_active ?? true,
});

const logoInput = ref(null);
const logoPreview = ref(null);
const isDragging = ref(false);

const submit = (onSuccess) => {
    form
        .transform((data) => ({
            ...data,
            _method: "patch",
            website: data.website
                ? data.website.match(/^https?:\/\//i)
                    ? data.website
                    : `${WEBSITE_PREFIX}${data.website}`
                : null,
        }))
        .post(route("admin.organizations.update", props.organization.id), {
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

const openMember = (id) => {
    router.visit(route("admin.users.edit", { user: id, return_to: route("admin.organizations.edit", props.organization.id, false) }));
};

const toggleMemberStatus = (member) => {
    router.patch(
        route("admin.users.status", member.id),
        { is_active: !member.is_active },
        { preserveState: true, preserveScroll: true }
    );
};
</script>

<template>
    <Head title="Makelaar beheren" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Makelaar beheren
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
                    <form class="mt-4 space-y-4" @submit.prevent="submit">
                        <div class="flex flex-wrap items-start gap-4">
                            <div>
                                <input
                                    ref="logoInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleLogo"
                                />
                                <div
                                    v-if="logoPreview || organization.logo_url"
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
                                        :src="logoPreview || organization.logo_url"
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
                            <span
                                v-if="form.recentlySuccessful"
                                class="text-sm text-gray-500"
                            >
                                Opgeslagen.
                            </span>
                        </FormActions>
                    </form>
                </FormSection>

                <div class="hidden sm:block">
                    <div class="relative rounded-lg bg-white shadow-md">
                        <div class="flex items-center justify-between gap-4 p-4">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900">
                                    Medewerkers
                                </h2>
                                <p class="text-sm text-gray-500">
                                    Gebruikers gekoppeld aan {{ organization.name }}.
                                </p>
                            </div>
                            <Link
                                :href="route('admin.organizations.users.create', organization.id)"
                                class="inline-flex items-center rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300"
                            >
                                <MaterialIcon name="person_add" class="mr-2 h-4 w-4" />
                                Nieuwe medewerker
                            </Link>
                        </div>
                        <div>
                            <table class="min-w-full w-full table-fixed text-left text-sm text-gray-600">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                                    <tr>
                                        <TableHeaderCell class="w-[36%] min-w-[240px]">
                                            Naam
                                        </TableHeaderCell>
                                        <TableHeaderCell class="w-[18%] hidden md:table-cell">
                                            Telefoonnummer
                                        </TableHeaderCell>
                                        <TableHeaderCell align="center" class="w-[16%] hidden lg:table-cell">
                                            LinkedIn
                                        </TableHeaderCell>
                                        <TableHeaderCell align="center" class="w-[12%]">
                                            Actief
                                        </TableHeaderCell>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <TableEmptyState
                                        v-if="members.length === 0"
                                        :colspan="4"
                                        message="Geen medewerkers gevonden."
                                    />
                                    <TableRowLink
                                        v-for="member in members"
                                        :key="member.id"
                                        @activate="openMember(member.id)"
                                    >
                                        <TableCell class="whitespace-normal break-words">
                                            <div class="flex items-center gap-3">
                                                <div class="h-8 w-8 overflow-hidden rounded-full bg-gray-100">
                                                    <img
                                                        v-if="member.avatar_url"
                                                        :src="member.avatar_url"
                                                        alt=""
                                                        class="h-full w-full object-cover"
                                                    />
                                                </div>
                                                <div>
                                                    <span class="font-medium text-gray-900">
                                                        {{ member.name }}
                                                    </span>
                                                    <div class="text-xs text-gray-500">
                                                        {{ member.email || "-" }}
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="hidden md:table-cell truncate">
                                            <span class="text-sm text-gray-700">
                                                {{ member.phone || "-" }}
                                            </span>
                                        </TableCell>
                                        <TableCell align="center" class="hidden lg:table-cell">
                                            <a
                                                v-if="member.linkedin_url"
                                                class="inline-flex h-8 w-8 items-center justify-center text-gray-600 hover:text-gray-900"
                                                :href="member.linkedin_url"
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label="LinkedIn-profiel"
                                                @click.stop
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
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell align="center">
                                            <div class="flex items-center justify-center" @click.stop>
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        class="sr-only peer"
                                                        :checked="member.is_active"
                                                        @change="toggleMemberStatus(member)"
                                                    />
                                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-700 transition-colors"></div>
                                                    <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                                                </label>
                                            </div>
                                        </TableCell>
                                    </TableRowLink>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 sm:hidden">
                    <p
                        v-if="members.length === 0"
                        class="text-sm text-gray-500"
                    >
                        Geen medewerkers gevonden.
                    </p>
                    <Link
                        v-for="member in members"
                        :key="member.id"
                        :href="
                            route('admin.users.edit', {
                                user: member.id,
                                return_to: route('admin.organizations.edit', organization.id, false),
                            })
                        "
                        class="block"
                    >
                        <FormSection class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 overflow-hidden rounded-full bg-gray-100">
                                        <img
                                            v-if="member.avatar_url"
                                            :src="member.avatar_url"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ member.name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ member.email || "-" }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    v-if="member.is_active === false"
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                >
                                    Inactief
                                </span>
                            </div>
                            <div class="mt-3 grid gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-gray-500">Telefoonnummer</p>
                                    <p class="text-gray-900">
                                        {{ member.phone || "-" }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">LinkedIn-profiel</p>
                                    <p class="text-gray-900">
                                        {{ member.linkedin_url ? "Beschikbaar" : "-" }}
                                    </p>
                                </div>
                            </div>
                        </FormSection>
                    </Link>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>


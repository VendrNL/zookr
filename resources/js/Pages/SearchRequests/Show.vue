<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DangerButton from "@/Components/DangerButton.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import Modal from "@/Components/Modal.vue";
import ModalCard from "@/Components/ModalCard.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TableCard from "@/Components/TableCard.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
    item: Object,
    offeredProperties: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({
            update: false,
            assign: false,
            delete: false,
            offer: false,
        }),
    },
});

const placeholderUsers = [
    { id: 1, name: "Demo admin" },
    { id: 2, name: "Demo medewerker" },
    { id: 3, name: "Demo toegewezen" },
];

const assignForm = useForm({
    assigned_to: props.item.assigned_to,
});

const deleteForm = useForm({});
const showDeleteModal = ref(false);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const activeTab = ref("search-request");

function statusBadgeClass(status) {
    switch (status) {
        case "concept":
            return "bg-slate-50 text-slate-700 ring-slate-200";
        case "open":
            return "bg-blue-50 text-blue-700 ring-blue-200";
        case "afgerond":
            return "bg-emerald-50 text-emerald-700 ring-emerald-200";
        case "geannuleerd":
            return "bg-rose-50 text-rose-700 ring-rose-200";
        default:
            return "bg-gray-50 text-gray-700 ring-gray-200";
    }
}

function statusLabel(status) {
    const map = {
        concept: "Concept",
        open: "Open",
        afgerond: "Afgerond",
        geannuleerd: "Geannuleerd",
    };
    return map[status] ?? status;
}

function submitAssignment() {
    assignForm.patch(
        route("search-requests.assign", props.item.id),
        {
            preserveScroll: true,
        }
    );
}

function assignToSelf() {
    if (!currentUserId.value) return;
    assignForm.assigned_to = currentUserId.value;
    submitAssignment();
}

function clearAssignment() {
    assignForm.assigned_to = null;
    submitAssignment();
}

function confirmDelete() {
    if (deleteForm.processing) return;
    showDeleteModal.value = true;
}

function cancelDelete() {
    showDeleteModal.value = false;
}

function destroyRequest() {
    deleteForm.delete(route("search-requests.destroy", props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
        },
        onFinish: () => {
            showDeleteModal.value = false;
        },
    });
}

function formatLabel(value) {
    if (!value) return "-";
    const parts = value.replaceAll("_", " ").split(" ");
    return parts
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function formatProvince(value) {
    if (!value) return "-";
    const parts = value.split("_");
    if (parts.length > 1) {
        return parts
            .map(
                (part) =>
                    part.charAt(0).toUpperCase() + part.slice(1)
            )
            .join("-");
    }
    return formatLabel(value);
}

function formatProvinceList(list) {
    if (!list?.length) return "-";
    return list.map(formatProvince).join(", ");
}

function acquisitionLabel(value) {
    if (!value) return "-";
    return value === "huur" ? "Huur" : "Koop";
}

function formatCurrency(value) {
    if (value === null || value === undefined || value === "") return "-";
    return new Intl.NumberFormat("nl-NL", {
        style: "currency",
        currency: "EUR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

function goToProperty(propertyId) {
    router.visit(
        route("search-requests.properties.edit", [props.item.id, propertyId])
    );
}

const applyTabFromQuery = () => {
    const url = page?.url ?? "";
    const queryString = url.includes("?") ? url.split("?")[1] : "";
    const params = new URLSearchParams(queryString);
    const tab = params.get("tab");
    if (tab === "offers") {
        activeTab.value = "offers";
    }
};

onMounted(applyTabFromQuery);
watch(
    () => page.url,
    () => {
        applyTabFromQuery();
    },
    { immediate: true }
);
</script>

<template>
    <Head :title="item.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Zoekvraag
                </h2>
                <Link
                    :href="route('search-requests.index')"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
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
            <PageContainer class="max-w-5xl space-y-6">
                <div class="text-sm font-medium text-center text-gray-500 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px">
                        <li class="me-2">
                            <button
                                type="button"
                                class="inline-block p-4 border-b-2 rounded-t-lg"
                                :class="activeTab === 'search-request'
                                    ? 'text-blue-600 border-blue-600'
                                    : 'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                @click="activeTab = 'search-request'"
                            >
                                Zoekvraag
                            </button>
                        </li>
                        <li class="me-2">
                            <button
                                type="button"
                                class="inline-block p-4 border-b-2 rounded-t-lg"
                                :class="activeTab === 'offers'
                                    ? 'text-blue-600 border-blue-600'
                                    : 'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                @click="activeTab = 'offers'"
                            >
                                Aangeboden panden
                            </button>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="activeTab === 'search-request'"
                    class="grid gap-6 lg:grid-cols-3 lg:items-start"
                >
                    <FormSection class="lg:col-span-2 space-y-4">
                        <div>
                            <img
                                v-if="item.organization?.logo_url"
                                :src="item.organization.logo_url"
                                alt="Makelaar logo"
                                class="mb-2 h-10 w-auto"
                            />
                            <h1 class="text-xl font-semibold text-gray-900">
                                {{ item.title }}
                            </h1>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusBadgeClass(item.status)"
                            >
                                {{ statusLabel(item.status) }}
                            </span>
                            <div class="text-sm text-gray-500">
                                Aangemaakt:
                                {{
                                    item.created_at
                                        ? new Date(
                                              item.created_at
                                          ).toLocaleString("nl-NL")
                                        : "-"
                                }}
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500">Naam klant</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.customer_name ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Locatie</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.location ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Provincies</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ formatProvinceList(item.provinces) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Type vastgoed</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ formatLabel(item.property_type) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Oppervlakte</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.surface_area ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Parkeren</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.parking ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">
                                    Beschikbaarheid
                                </p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.availability ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">
                                    Bereikbaarheid
                                </p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.accessibility ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Verwerving</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{
                                        item.acquisitions?.length
                                            ? item.acquisitions
                                                  .map(acquisitionLabel)
                                                  .join(", ")
                                            : "-"
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">
                                    Toegewezen aan
                                </p>
                                <p class="text-base font-medium text-gray-900">
                                    <span v-if="item.assignee">
                                        {{ item.assignee.name }}
                                    </span>
                                    <span v-else class="text-gray-500">
                                        Nog niet toegewezen
                                    </span>
                                </p>
                                <p
                                    v-if="item.assignee?.email"
                                    class="text-sm text-gray-500"
                                >
                                    {{ item.assignee.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm text-gray-500">Bijzonderheden</p>
                            <ul
                                v-if="item.notes"
                                class="list-disc space-y-1 pl-5 text-gray-900"
                            >
                                <template
                                    v-for="(line, index) in item.notes.split('\n')"
                                    :key="index"
                                >
                                    <li v-if="line.trim()">
                                        {{ line }}
                                    </li>
                                </template>
                            </ul>
                            <p v-else class="text-gray-900">-</p>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <Link
                                v-if="can.update"
                                :href="route('search-requests.edit', item.id)"
                                class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            >
                                Bewerk
                            </Link>
                            <Link
                                v-if="can.offer"
                                :href="route('search-requests.properties.create', item.id)"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:border-gray-400 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            >
                                Pand aanbieden
                            </Link>
                            <DangerButton
                                v-if="can.delete"
                                type="button"
                                @click="confirmDelete"
                                :disabled="deleteForm.processing"
                            >
                                Verwijderen
                            </DangerButton>
                        </div>
                    </FormSection>

                    <div class="space-y-6">
                        <FormSection
                            v-if="can.assign"
                            class="space-y-4"
                        >
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Toewijzing
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Kies een gebruiker-id of wijs snel aan jezelf toe.
                                </p>
                            </div>

                            <form
                                class="space-y-3"
                                @submit.prevent="submitAssignment"
                            >
                                <div>
                                    <InputLabel
                                        for="assigned_to"
                                        value="Toegewezen aan"
                                    />
                                    <select
                                        id="assigned_to"
                                        v-model.number="assignForm.assigned_to"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    >
                                        <option :value="null">
                                            Geen toewijzing
                                        </option>
                                        <option
                                            v-for="user in placeholderUsers"
                                            :key="user.id"
                                            :value="user.id"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="assignForm.errors.assigned_to"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <PrimaryButton
                                        :class="{
                                            'opacity-25': assignForm.processing,
                                        }"
                                        :disabled="assignForm.processing"
                                    >
                                        Opslaan
                                    </PrimaryButton>
                                    <SecondaryButton
                                        type="button"
                                        :disabled="assignForm.processing || !currentUserId"
                                        @click="assignToSelf"
                                    >
                                        Wijs aan mij toe
                                    </SecondaryButton>
                                    <SecondaryButton
                                        type="button"
                                        :disabled="assignForm.processing"
                                        @click="clearAssignment"
                                    >
                                        Verwijder toewijzing
                                    </SecondaryButton>
                                </div>
                            </form>
                        </FormSection>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <TableCard>
                        <thead class="bg-gray-50">
                            <tr>
                                <TableHeaderCell class="w-[60%] min-w-[260px]">
                                    Pand
                                </TableHeaderCell>
                                <TableHeaderCell class="w-[40%]">
                                    Aangeboden door
                                </TableHeaderCell>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <TableEmptyState
                                v-if="offeredProperties.length === 0"
                                :colspan="2"
                                message="Er zijn nog geen panden aangeboden door jouw kantoor."
                            />
                            <tr
                                v-for="property in offeredProperties"
                                :key="property.id"
                                class="cursor-pointer hover:bg-gray-50 focus-within:bg-gray-50"
                                role="link"
                                tabindex="0"
                                @click="goToProperty(property.id)"
                                @keydown.enter.prevent="goToProperty(property.id)"
                                @keydown.space.prevent="goToProperty(property.id)"
                            >
                                <TableCell class="whitespace-normal break-words">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ property.name || property.address }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ property.address }} {{ property.city ? `- ${property.city}` : "" }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 overflow-hidden rounded-full bg-gray-100">
                                            <img
                                                v-if="property.contact_user?.avatar_url || property.user?.avatar_url"
                                                :src="property.contact_user?.avatar_url || property.user?.avatar_url"
                                                alt=""
                                                class="h-full w-full object-cover"
                                            />
                                        </div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ property.contact_user?.name || property.user?.name || "-" }}
                                        </div>
                                    </div>
                                </TableCell>
                            </tr>
                        </tbody>
                    </TableCard>
                </div>
            </PageContainer>
        </div>

        <Modal :show="showDeleteModal" maxWidth="md" @close="cancelDelete">
            <ModalCard>
                <template #title>
                    <h2 class="text-2xl font-semibold text-gray-900">
                        Weet je het zeker?
                    </h2>
                </template>
                <template #body>
                    <p class="text-base font-normal text-gray-900">
                        Deze aanvraag wordt verwijderd (soft delete) en verdwijnt
                        uit het overzicht. Je kunt deze actie later niet ongedaan
                        maken vanuit de applicatie.
                    </p>
                </template>
                <template #actions>
                    <FormActions align="center">
                        <SecondaryButton
                            type="button"
                            :disabled="deleteForm.processing"
                            @click="cancelDelete"
                        >
                            Annuleren
                        </SecondaryButton>
                        <DangerButton
                            :class="{ 'opacity-25': deleteForm.processing }"
                            :disabled="deleteForm.processing"
                            @click="destroyRequest"
                        >
                            Verwijderen
                        </DangerButton>
                    </FormActions>
                </template>
            </ModalCard>
        </Modal>
    </AuthenticatedLayout>
</template>


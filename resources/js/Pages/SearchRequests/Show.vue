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
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
    item: Object,
    offeredProperties: {
        type: Array,
        default: () => [],
    },
    viewAllOffers: {
        type: Boolean,
        default: false,
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

const canViewAllOffers = computed(() => props.can?.viewAllOffers || props.viewAllOffers);
const offeredProperties = computed(() => props.offeredProperties ?? []);
const statusFilter = ref("all");
const sortKey = ref("created_at");
const sortDirection = ref("desc");

const setActiveTab = (tab) => {
    activeTab.value = tab;
    if (typeof window === "undefined") return;
    const url = new URL(window.location.href);
    if (tab === "offers") {
        url.searchParams.set("tab", "offers");
    } else {
        url.searchParams.delete("tab");
    }
    window.history.replaceState({}, "", url.toString());
};

const ensureOffersTab = () => {
    setActiveTab("offers");
};

const statusFilterLabel = computed(() => {
    if (statusFilter.value === "geschikt") return "Geschikt";
    if (statusFilter.value === "ongeschikt") return "Ongeschikt";
    if (statusFilter.value === "niet_beoordeeld") return "Niet beoordeeld";
    return "Status";
});

const setStatusFilter = (value) => {
    statusFilter.value = value;
    ensureOffersTab();
};

const normalizeOfferStatus = (status) => {
    const normalized = typeof status === "string" ? status.trim().toLowerCase() : "";
    if (normalized === "geschikt") return "geschikt";
    if (normalized === "ongeschikt") return "ongeschikt";
    return "niet_beoordeeld";
};

const isNotReviewed = (status) => {
    return status === null || status === undefined || status === "";
};

const filteredOfferedProperties = computed(() => {
    if (statusFilter.value === "all") {
        return offeredProperties.value;
    }
    if (statusFilter.value === "niet_beoordeeld") {
        return offeredProperties.value.filter(
            (property) => isNotReviewed(property.status)
        );
    }
    return offeredProperties.value.filter(
        (property) => normalizeOfferStatus(property.status) === statusFilter.value
    );
});

const offerStatusLabel = (status) => {
    switch (normalizeOfferStatus(status)) {
        case "geschikt":
            return "Geschikt";
        case "ongeschikt":
            return "Ongeschikt";
        default:
            return "Niet beoordeeld";
    }
};

const offerStatusClass = (status) => {
    switch (normalizeOfferStatus(status)) {
        case "geschikt":
            return "bg-emerald-50 text-emerald-700 ring-emerald-200";
        case "ongeschikt":
            return "bg-rose-50 text-rose-700 ring-rose-200";
        default:
            return "bg-gray-50 text-gray-700 ring-gray-200";
    }
};

const getOfferContactName = (property) =>
    property?.contact_user?.name || property?.user?.name || "";

const getOfferOrganizationName = (property) =>
    property?.organization?.name || "";

const setSort = (key) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
        ensureOffersTab();
        return;
    }
    sortKey.value = key;
    sortDirection.value = "asc";
    ensureOffersTab();
};

const sortIndicator = (key) => {
    if (sortKey.value !== key) return "";
    return sortDirection.value === "asc" ? "▲" : "▼";
};

const sortedOfferedProperties = computed(() => {
    const items = [ ...filteredOfferedProperties.value ];
    const factor = sortDirection.value === "asc" ? 1 : -1;
    return items.sort((a, b) => {
        let left = "";
        let right = "";
        if (sortKey.value === "contact") {
            left = getOfferContactName(a);
            right = getOfferContactName(b);
        } else if (sortKey.value === "organization") {
            left = getOfferOrganizationName(a);
            right = getOfferOrganizationName(b);
        } else {
            left = a?.created_at ?? "";
            right = b?.created_at ?? "";
        }
        return String(left).localeCompare(String(right), "nl", { sensitivity: "base" }) * factor;
    });
});
const avatarErrors = ref({});

const userInitials = (name = "") => {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "";
    const first = parts[0]?.[0] ?? "";
    const last = parts.length > 1 ? parts[parts.length - 1]?.[0] ?? "" : "";
    return `${first}${last}`.toUpperCase();
};

const getAvatarUrl = (user) => {
    if (!user) return null;
    if (user.avatar_url) return user.avatar_url;
    if (user.avatar_path) return `/storage/${user.avatar_path}`;
    return null;
};

const handleAvatarError = (key) => {
    avatarErrors.value = { ...avatarErrors.value, [key]: true };
};

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
const activeTabParam = computed(() => page?.props?.tab || null);

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
    const property = offeredProperties.value.find((item) => item.id === propertyId);
    if (property && property.can_update) {
        router.visit(
            route("search-requests.properties.edit", [props.item.id, propertyId])
        );
        return;
    }
    router.visit(
        route("search-requests.properties.view", [props.item.id, propertyId])
    );
}

const applyTabFromQuery = () => {
    const url = page?.url ?? "";
    const queryString = url.includes("?") ? url.split("?")[1] : "";
    const params = queryString
        ? new URLSearchParams(queryString)
        : typeof window !== "undefined"
            ? new URLSearchParams(window.location.search)
            : new URLSearchParams();
    const tab = params.get("tab") || activeTabParam.value;
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
            <div class="flex min-w-0 items-center justify-between gap-4">
                <div class="relative min-w-0 max-w-[60vw] overflow-hidden whitespace-nowrap pr-8">
                    <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                        {{ item.title }}
                    </h2>
                    <span class="pointer-events-none absolute right-0 top-0 h-full w-10 bg-gradient-to-l from-white"></span>
                </div>
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
                                @click="setActiveTab('search-request')"
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
                                @click="setActiveTab('offers')"
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

                        <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                            <Link
                                v-if="can.offer"
                                :href="route('search-requests.properties.create', item.id)"
                                class="inline-flex items-center rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300"
                            >
                                Pand aanbieden
                            </Link>
                            <button
                                v-if="can.delete"
                                type="button"
                                class="inline-flex items-center justify-center text-white bg-red-600 box-border border border-transparent hover:bg-red-700 focus:ring-4 focus:ring-red-300 shadow-xs font-medium leading-5 rounded-lg text-sm px-4 py-2.5 focus:outline-none"
                                :disabled="deleteForm.processing"
                                @click="confirmDelete"
                            >
                                Verwijderen
                            </button>
                            <Link
                                v-if="can.update"
                                :href="route('search-requests.edit', item.id)"
                                class="inline-flex items-center rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300"
                            >
                                Bewerk
                            </Link>
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
                                        class="mt-1 block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
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
                                <TableHeaderCell :class="canViewAllOffers ? 'w-[45%] min-w-[260px]' : 'w-[60%] min-w-[260px]'">
                                    Pand
                                </TableHeaderCell>
                                  <TableHeaderCell :class="canViewAllOffers ? 'w-[26%]' : 'w-[34%]'">
                                      <button
                                          type="button"
                                          class="inline-flex items-center gap-2 text-gray-600 uppercase"
                                          @click="setSort('contact')"
                                      >
                                          Aangeboden door
                                          <svg
                                              v-if="sortKey === 'contact'"
                                              class="h-3.5 w-3.5 text-gray-400"
                                              viewBox="0 0 20 20"
                                              fill="currentColor"
                                              aria-hidden="true"
                                          >
                                              <path
                                                  v-if="sortDirection === 'asc'"
                                                  fill-rule="evenodd"
                                                  d="M10 3a1 1 0 01.707.293l5 5a1 1 0 01-1.414 1.414L10 5.414 5.707 9.707A1 1 0 014.293 8.293l5-5A1 1 0 0110 3z"
                                                  clip-rule="evenodd"
                                              />
                                              <path
                                                  v-else
                                                  fill-rule="evenodd"
                                                  d="M10 17a1 1 0 01-.707-.293l-5-5a1 1 0 011.414-1.414L10 14.586l4.293-4.293a1 1 0 011.414 1.414l-5 5A1 1 0 0110 17z"
                                                  clip-rule="evenodd"
                                              />
                                          </svg>
                                      </button>
                                  </TableHeaderCell>
                                  <TableHeaderCell v-if="canViewAllOffers" class="w-[25%]">
                                      <button
                                          type="button"
                                          class="inline-flex items-center gap-2 text-gray-600 uppercase"
                                          @click="setSort('organization')"
                                      >
                                          Makelaarskantoor
                                          <svg
                                              v-if="sortKey === 'organization'"
                                              class="h-3.5 w-3.5 text-gray-400"
                                              viewBox="0 0 20 20"
                                              fill="currentColor"
                                              aria-hidden="true"
                                          >
                                              <path
                                                  v-if="sortDirection === 'asc'"
                                                  fill-rule="evenodd"
                                                  d="M10 3a1 1 0 01.707.293l5 5a1 1 0 01-1.414 1.414L10 5.414 5.707 9.707A1 1 0 014.293 8.293l5-5A1 1 0 0110 3z"
                                                  clip-rule="evenodd"
                                              />
                                              <path
                                                  v-else
                                                  fill-rule="evenodd"
                                                  d="M10 17a1 1 0 01-.707-.293l-5-5a1 1 0 011.414-1.414L10 14.586l4.293-4.293a1 1 0 011.414 1.414l-5 5A1 1 0 0110 17z"
                                                  clip-rule="evenodd"
                                              />
                                          </svg>
                                      </button>
                                  </TableHeaderCell>
                                  <TableHeaderCell align="left" class="w-[22%]">
                                      <Dropdown align="right" width="48">
                                          <template #trigger>
                                              <button
                                                  type="button"
                                                  class="inline-flex w-full items-center gap-2 text-gray-600 uppercase"
                                                  aria-label="Filter status"
                                              >
                                                  {{ statusFilterLabel }}
                                                  <svg
                                                      v-if="statusFilter !== 'all'"
                                                      class="h-4 w-4 text-gray-500"
                                                      viewBox="0 0 20 20"
                                                      fill="currentColor"
                                                      aria-hidden="true"
                                                  >
                                                      <path
                                                          fill-rule="evenodd"
                                                          d="M10 3a1 1 0 01.707.293l5 5a1 1 0 01-1.414 1.414L10 5.414 5.707 9.707A1 1 0 014.293 8.293l5-5A1 1 0 0110 3z"
                                                          clip-rule="evenodd"
                                                      />
                                                      <path
                                                          fill-rule="evenodd"
                                                          d="M10 17a1 1 0 01-.707-.293l-5-5a1 1 0 011.414-1.414L10 14.586l4.293-4.293a1 1 0 011.414 1.414l-5 5A1 1 0 0110 17z"
                                                          clip-rule="evenodd"
                                                      />
                                                  </svg>
                                              </button>
                                          </template>
                                          <template #content>
                                              <div>
                                                  <DropdownLink href="#" class="normal-case font-normal" @click.prevent="setStatusFilter('all')">
                                                      Alle statussen
                                                  </DropdownLink>
                                                  <DropdownLink href="#" class="normal-case font-normal" @click.prevent="setStatusFilter('niet_beoordeeld')">
                                                      Niet beoordeeld
                                                  </DropdownLink>
                                                  <DropdownLink href="#" class="normal-case font-normal" @click.prevent="setStatusFilter('geschikt')">
                                                      Geschikt
                                                  </DropdownLink>
                                                  <DropdownLink href="#" class="normal-case font-normal" @click.prevent="setStatusFilter('ongeschikt')">
                                                      Ongeschikt
                                                  </DropdownLink>
                                              </div>
                                          </template>
                                      </Dropdown>
                                  </TableHeaderCell>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                              <TableEmptyState
                                  v-if="filteredOfferedProperties.length === 0"
                                  :colspan="canViewAllOffers ? 4 : 3"
                                  :message="canViewAllOffers
                                      ? 'Er zijn nog geen panden aangeboden.'
                                      : 'Er zijn nog geen panden aangeboden door jouw kantoor.'"
                              />
                              <tr
                                  v-for="property in sortedOfferedProperties"
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
                                                  v-if="getAvatarUrl(property.contact_user || property.user) && !avatarErrors[property.contact_user?.id || property.user?.id || property.id]"
                                                  :src="getAvatarUrl(property.contact_user || property.user)"
                                                  alt=""
                                                  class="h-full w-full object-cover"
                                                  @error="handleAvatarError(property.contact_user?.id || property.user?.id || property.id)"
                                              />
                                              <div
                                                  v-else
                                                  class="flex h-full w-full items-center justify-center bg-gray-200 text-[11px] font-semibold text-gray-700"
                                              >
                                                  {{ userInitials(property.contact_user?.name || property.user?.name) }}
                                              </div>
                                          </div>
                                          <div class="text-sm font-medium text-gray-900">
                                              {{ property.contact_user?.name || property.user?.name || "-" }}
                                          </div>
                                      </div>
                                  </TableCell>
                                  <TableCell v-if="canViewAllOffers">
                                      <div class="text-sm font-medium text-gray-900">
                                          {{ property.organization?.name || "-" }}
                                      </div>
                                  </TableCell>
                                    <TableCell>
                                        <span
                                            class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                            :class="offerStatusClass(property.status)"
                                        >
                                            {{ offerStatusLabel(property.status) }}
                                        </span>
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

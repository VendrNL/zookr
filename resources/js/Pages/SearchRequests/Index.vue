<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageContainer from "@/Components/PageContainer.vue";
import FormSection from "@/Components/FormSection.vue";
import Dropdown from "@/Components/Dropdown.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import TableRowLink from "@/Components/TableRowLink.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            status: [],
            q: "",
            province: [],
            property_type: [],
            sort: "",
            direction: "desc",
        }),
    },
    items: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        default: () => ({ create: false, is_admin: false }),
    },
    options: {
        type: Object,
        default: () => ({ types: [], provinces: [] }),
    },
});

const form = reactive({
    status: Array.isArray(props.filters?.status)
        ? [...props.filters.status]
        : props.filters?.status
            ? [props.filters.status]
            : [],
    q: props.filters?.q ?? "",
    province: Array.isArray(props.filters?.province)
        ? [...props.filters.province]
        : props.filters?.province
            ? [props.filters.province]
            : [],
    property_type: Array.isArray(props.filters?.property_type)
        ? [...props.filters.property_type]
        : props.filters?.property_type
            ? [props.filters.property_type]
            : [],
    sort: props.filters?.sort ?? "",
    direction: props.filters?.direction ?? "desc",
});

const statuses = [
    { value: "concept", label: "Concept" },
    { value: "open", label: "Open" },
    { value: "afgerond", label: "Afgerond" },
    { value: "geannuleerd", label: "Geannuleerd" },
];

const searchTimeout = ref(null);

function formatDate(value) {
    if (!value) return "-";
    return new Intl.DateTimeFormat("nl-NL", {
        day: "numeric",
        month: "short",
        year: "numeric",
    })
        .format(new Date(value))
        .replaceAll(".", "");
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
    return value === "huur" ? "Huur" : "Koop";
}

function acquisitionList(list) {
    if (!list?.length) return "-";
    return list.map(acquisitionLabel).join(", ");
}

function applyFilters() {
    router.get(
        route("search-requests.index"),
        {
            status: form.status.length ? form.status : undefined,
            province: form.province.length ? form.province : undefined,
            property_type: form.property_type.length
                ? form.property_type
                : undefined,
            q: form.q || undefined,
            sort: form.sort || undefined,
            direction: form.direction || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function toggleSelection(list, value) {
    const index = list.indexOf(value);
    if (index === -1) {
        list.push(value);
    } else {
        list.splice(index, 1);
    }
    applyFilters();
}

function toggleSort(field) {
    if (form.sort === field) {
        form.direction = form.direction === "asc" ? "desc" : "asc";
    } else {
        form.sort = field;
        form.direction = "asc";
    }
    applyFilters();
}

const propertyTypeLabel = computed(() => {
    if (!form.property_type.length) return "Type";
    if (form.property_type.length === 1) {
        return formatLabel(form.property_type[0]);
    }
    return "Type";
});

const provinceLabel = computed(() => {
    if (!form.province.length) return "Provincie";
    if (form.province.length === 1) {
        return formatProvince(form.province[0]);
    }
    return "Provincie";
});

const statusLabelText = computed(() => {
    if (!form.status.length) return "Status";
    if (form.status.length === 1) {
        return statusLabel(form.status[0]);
    }
    return "Status";
});

const propertyTypeCount = computed(() => form.property_type.length);
const provinceCount = computed(() => form.province.length);
const statusCount = computed(() => form.status.length);

const emptyRows = computed(() => {
    const count = props.items?.data?.length ?? 0;
    return Math.max(0, 3 - count);
});

const lastLinkIndex = computed(() => (props.items?.links?.length ?? 1) - 1);

watch(
    () => form.q,
    () => {
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value);
        }
        searchTimeout.value = setTimeout(() => {
            applyFilters();
        }, 300);
    }
);

function openItem(id) {
    router.visit(route("search-requests.show", id));
}

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

function isPreviousLabel(label) {
    const normalized = String(label ?? "").toLowerCase();
    return normalized.includes("previous") || normalized.includes("pagination.previous");
}

function isNextLabel(label) {
    const normalized = String(label ?? "").toLowerCase();
    return normalized.includes("next") || normalized.includes("pagination.next");
}

</script>

<template>
    <Head title="Zoekvragen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                        Zoekvragen
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8">
            <PageContainer>
                <!-- Filters -->
                <div class="sm:hidden">
                    <div class="flex w-full items-center gap-2">
                        <input
                            v-model="form.q"
                            type="text"
                            placeholder="Titel, klant of locatie."
                            class="h-[42px] w-full min-w-0 rounded-base border border-default-medium bg-neutral-secondary-medium px-3 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand placeholder:text-body"
                        />
                        <button
                            type="button"
                            class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-base bg-brand p-0 text-white hover:bg-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-300"
                            @click="applyFilters"
                        >
                            <MaterialIcon name="search" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Tabel -->
                <div class="mt-6 hidden sm:block">
                    <div class="relative rounded-base bg-white shadow-xs ring-1 ring-default-medium">
                                <div
                                    class="flex flex-col items-center justify-between gap-3 p-4 md:flex-row md:gap-4"
                                >
                                    <div class="w-full md:w-4/12">
                                        <form class="flex items-center" @submit.prevent="applyFilters">
                                            <label for="simple-search" class="sr-only">
                                                Zoeken
                                            </label>
                                            <div class="relative w-full">
                                                <div
                                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"
                                                >
                                                    <svg
                                                        aria-hidden="true"
                                                        class="h-5 w-5"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </div>
                                                <input
                                                    id="simple-search"
                                                    v-model="form.q"
                                                    type="text"
                                                    class="block w-full rounded-base border border-default-medium bg-neutral-secondary-medium p-2.5 pl-10 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand placeholder:text-body"
                                                    placeholder="Zoek op titel, klant of locatie"
                                                />
                                            </div>
                                        </form>
                                    </div>
                                    <div
                                        class="flex w-full flex-col items-stretch justify-end space-y-2 md:w-auto md:flex-row md:items-center md:space-x-3 md:space-y-0"
                                    >
                                        <Link
                                            v-if="can.create"
                                            :href="route('search-requests.create')"
                                            class="inline-flex items-center rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300"
                                        >
                                            <MaterialIcon name="add" class="mr-2 h-4 w-4" />
                                            Nieuwe zoekvraag
                                        </Link>
                                    </div>
                                </div>
                                <div>
                                    <table class="w-full table-fixed text-left text-sm text-gray-600">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                                            <tr>
                                                <TableHeaderCell class="w-[30%] min-w-[280px]">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 text-gray-600 uppercase"
                                                        @click="toggleSort('title')"
                                                    >
                                                        Titel
                                                        <svg
                                                            v-if="form.sort === 'title'"
                                                            class="h-3 w-3 text-gray-500"
                                                            viewBox="0 0 20 20"
                                                            fill="currentColor"
                                                            aria-hidden="true"
                                                        >
                                                            <path
                                                                v-if="form.direction === 'asc'"
                                                                d="M10 4l-4 4h8l-4-4z"
                                                            />
                                                            <path
                                                                v-else
                                                                d="M10 16l4-4H6l4 4z"
                                                            />
                                                        </svg>
                                                    </button>
                                                </TableHeaderCell>
                                                <TableHeaderCell class="w-[16%]">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 text-gray-600 uppercase"
                                                        @click="toggleSort('organization')"
                                                    >
                                                        Makelaar
                                                        <svg
                                                            v-if="form.sort === 'organization'"
                                                            class="h-3 w-3 text-gray-500"
                                                            viewBox="0 0 20 20"
                                                            fill="currentColor"
                                                            aria-hidden="true"
                                                        >
                                                            <path
                                                                v-if="form.direction === 'asc'"
                                                                d="M10 4l-4 4h8l-4-4z"
                                                            />
                                                            <path
                                                                v-else
                                                                d="M10 16l4-4H6l4 4z"
                                                            />
                                                        </svg>
                                                    </button>
                                                </TableHeaderCell>
                                                <TableHeaderCell class="w-[12%] hidden md:table-cell">
                                                    <Dropdown align="left" width="48">
                                                        <template #trigger>
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center gap-2 text-gray-600 uppercase"
                                                            >
                                                                <span>{{ propertyTypeLabel }}</span>
                                                                <span
                                                                    v-if="propertyTypeCount"
                                                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                                                                >
                                                                    {{ propertyTypeCount }}
                                                                </span>
                                                                <svg
                                                                    class="h-3 w-3 text-gray-500"
                                                                    viewBox="0 0 20 20"
                                                                    fill="currentColor"
                                                                    aria-hidden="true"
                                                                >
                                                                    <path
                                                                        fill-rule="evenodd"
                                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                                        clip-rule="evenodd"
                                                                    />
                                                                </svg>
                                                            </button>
                                                        </template>
                                                        <template #content>
                                                            <div class="max-h-64 overflow-auto px-3 py-2" @click.stop>
                                                                <div
                                                                    v-for="option in options.types"
                                                                    :key="option"
                                                                    class="flex items-center py-1"
                                                                >
                                                                    <input
                                                                        :id="`type-${option}`"
                                                                        type="checkbox"
                                                                        :checked="form.property_type.includes(option)"
                                                                        class="h-4 w-4 rounded-xs border border-default-medium bg-white text-blue-700 checked:border-blue-700 checked:bg-blue-700 focus:ring-2 focus:ring-brand-soft"
                                                                        @change="toggleSelection(form.property_type, option)"
                                                                    />
                                                            <label
                                                                :for="`type-${option}`"
                                                                class="ml-2 truncate text-xs font-normal text-gray-500 normal-case"
                                                            >
                                                                {{ formatLabel(option) }}
                                                            </label>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </Dropdown>
                                                </TableHeaderCell>
                                                <TableHeaderCell class="w-[12%] hidden md:table-cell">
                                                    <Dropdown align="left" width="48">
                                                        <template #trigger>
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center gap-2 text-gray-600 uppercase"
                                                            >
                                                                <span>{{ provinceLabel }}</span>
                                                                <span
                                                                    v-if="provinceCount"
                                                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                                                                >
                                                                    {{ provinceCount }}
                                                                </span>
                                                                <svg
                                                                    class="h-3 w-3 text-gray-500"
                                                                    viewBox="0 0 20 20"
                                                                    fill="currentColor"
                                                                    aria-hidden="true"
                                                                >
                                                                    <path
                                                                        fill-rule="evenodd"
                                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                                        clip-rule="evenodd"
                                                                    />
                                                                </svg>
                                                            </button>
                                                        </template>
                                                        <template #content>
                                                            <div class="max-h-64 overflow-auto px-3 py-2" @click.stop>
                                                                <div
                                                                    v-for="option in options.provinces"
                                                                    :key="option"
                                                                    class="flex items-center py-1"
                                                                >
                                                                    <input
                                                                        :id="`province-${option}`"
                                                                        type="checkbox"
                                                                        :checked="form.province.includes(option)"
                                                                        class="h-4 w-4 rounded-xs border border-default-medium bg-white text-blue-700 checked:border-blue-700 checked:bg-blue-700 focus:ring-2 focus:ring-brand-soft"
                                                                        @change="toggleSelection(form.province, option)"
                                                                    />
                                                            <label
                                                                :for="`province-${option}`"
                                                                class="ml-2 truncate text-xs font-normal text-gray-500 normal-case"
                                                            >
                                                                {{ formatProvince(option) }}
                                                            </label>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </Dropdown>
                                                </TableHeaderCell>
                                                <TableHeaderCell class="w-[10%] hidden lg:table-cell">
                                                    Oppervlakte
                                                </TableHeaderCell>
                                                <TableHeaderCell class="w-[10%] hidden lg:table-cell">
                                                    Verwerving
                                                </TableHeaderCell>
                                                <TableHeaderCell class="w-[12%]">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 text-gray-600 uppercase"
                                                        @click="toggleSort('created_at')"
                                                    >
                                                        Aangemaakt
                                                        <svg
                                                            v-if="form.sort === 'created_at'"
                                                            class="h-3 w-3 text-gray-500"
                                                            viewBox="0 0 20 20"
                                                            fill="currentColor"
                                                            aria-hidden="true"
                                                        >
                                                            <path
                                                                v-if="form.direction === 'asc'"
                                                                d="M10 4l-4 4h8l-4-4z"
                                                            />
                                                            <path
                                                                v-else
                                                                d="M10 16l4-4H6l4 4z"
                                                            />
                                                        </svg>
                                                    </button>
                                                </TableHeaderCell>
                                                <TableHeaderCell align="center" class="w-[8%]">
                                                    <Dropdown align="left" width="48">
                                                        <template #trigger>
                                                            <button
                                                                type="button"
                                                                class="inline-flex w-full items-center justify-center gap-2 text-gray-600 uppercase"
                                                            >
                                                                <span>{{ statusLabelText }}</span>
                                                                <span
                                                                    v-if="statusCount"
                                                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                                                                >
                                                                    {{ statusCount }}
                                                                </span>
                                                                <svg
                                                                    class="h-3 w-3 text-gray-500"
                                                                    viewBox="0 0 20 20"
                                                                    fill="currentColor"
                                                                    aria-hidden="true"
                                                                >
                                                                    <path
                                                                        fill-rule="evenodd"
                                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                                        clip-rule="evenodd"
                                                                    />
                                                                </svg>
                                                            </button>
                                                        </template>
                                                        <template #content>
                                                            <div class="px-3 py-2" @click.stop>
                                                                <div
                                                                    v-for="status in statuses"
                                                                    :key="status.value"
                                                                    class="flex items-center py-1"
                                                                >
                                                                    <input
                                                                        :id="`status-${status.value}`"
                                                                        type="checkbox"
                                                                        :checked="form.status.includes(status.value)"
                                                                        class="h-4 w-4 rounded-xs border border-default-medium bg-white text-blue-700 checked:border-blue-700 checked:bg-blue-700 focus:ring-2 focus:ring-brand-soft"
                                                                        @change="toggleSelection(form.status, status.value)"
                                                                    />
                                                            <label
                                                                :for="`status-${status.value}`"
                                                                class="ml-2 truncate text-xs font-normal text-gray-500 normal-case"
                                                            >
                                                                {{ status.label }}
                                                            </label>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </Dropdown>
                                                </TableHeaderCell>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-gray-100">
                                            <TableEmptyState
                                                v-if="items.data?.length === 0"
                                                :colspan="8"
                                                message="Geen aanvragen gevonden."
                                            />

                                            <TableRowLink
                                                v-for="item in items.data"
                                                :key="item.id"
                                                @activate="openItem(item.id)"
                                            >
                                                <TableCell class="whitespace-normal break-words">
                                                    <div class="text-sm font-semibold text-gray-900">
                                                        {{ item.title }}
                                                    </div>
                                                    <div
                                                        v-if="item.customer_name"
                                                        class="text-xs text-gray-500"
                                                    >
                                                        Klant: {{ item.customer_name }}
                                                    </div>
                                                </TableCell>

                                                <TableCell class="truncate">
                                                    {{ item.organization?.name || "-" }}
                                                </TableCell>

                                                <TableCell class="hidden md:table-cell truncate">
                                                    {{ formatLabel(item.property_type) }}
                                                </TableCell>

                                                <TableCell class="hidden md:table-cell truncate">
                                                    {{ formatProvinceList(item.provinces) }}
                                                </TableCell>

                                                <TableCell class="hidden lg:table-cell truncate">
                                                    {{ item.surface_area || "-" }}
                                                </TableCell>

                                                <TableCell class="hidden lg:table-cell truncate">
                                                    {{ acquisitionList(item.acquisitions) }}
                                                </TableCell>

                                                <TableCell class="truncate">
                                                    {{ formatDate(item.created_at) }}
                                                </TableCell>

                                                <TableCell align="center">
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                                        :class="statusBadgeClass(item.status)"
                                                    >
                                                        {{ statusLabel(item.status) }}
                                                    </span>
                                                </TableCell>
                                            </TableRowLink>
                                            <tr
                                                v-for="row in emptyRows"
                                                :key="`empty-${row}`"
                                                class="h-12"
                                            >
                                                <td colspan="8" class="px-4 py-3"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <nav
                                    v-if="items.links?.length"
                                    class="flex flex-col items-start justify-between space-y-3 border-t border-gray-200 p-4 md:flex-row md:items-center md:space-y-0"
                                    aria-label="Table navigation"
                                >
                                    <span class="text-sm font-normal text-gray-500">
                                        <span v-if="items.from && items.to && items.total">
                                            Resultaten
                                            <span class="font-semibold text-gray-900">
                                                {{ items.from }}-{{ items.to }}
                                            </span>
                                            van
                                            <span class="font-semibold text-gray-900">
                                                {{ items.total }}
                                            </span>
                                        </span>
                                    </span>
                                    <div class="inline-flex items-stretch -space-x-px">
                                        <Link
                                            v-for="(link, i) in items.links"
                                            :key="i"
                                            :href="link.url || ''"
                                            class="flex items-center justify-center border border-gray-300 bg-white px-3 py-2 text-sm leading-tight text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                            :class="[
                                                i === 0 ? 'rounded-l-lg' : '',
                                                i === lastLinkIndex ? 'rounded-r-lg' : '',
                                                link.active
                                                    ? 'z-10 border-brand-medium bg-brand-soft text-fg-brand hover:bg-brand-medium hover:text-white'
                                                    : '',
                                                !link.url
                                                    ? 'pointer-events-none opacity-40'
                                                    : '',
                                            ]"
                                        >
                                            <template v-if="isPreviousLabel(link.label)">
                                                <MaterialIcon name="chevron_left" class="h-4 w-4" />
                                                <span class="sr-only">Vorige</span>
                                            </template>
                                            <template v-else-if="isNextLabel(link.label)">
                                                <MaterialIcon name="chevron_right" class="h-4 w-4" />
                                                <span class="sr-only">Volgende</span>
                                            </template>
                                            <span v-else v-html="link.label"></span>
                                        </Link>
                                    </div>
                                </nav>
                    </div>
                </div>

                <div class="mt-6 space-y-3 sm:hidden">
                    <p
                        v-if="items.data?.length === 0"
                        class="text-sm text-gray-500"
                    >
                        Geen aanvragen gevonden.
                    </p>
                    <Link
                        v-for="item in items.data"
                        :key="item.id"
                        :href="route('search-requests.show', item.id)"
                        class="block"
                    >
                        <FormSection class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ item.title }}
                                    </p>
                                    <p
                                        v-if="item.customer_name"
                                        class="text-xs text-gray-500"
                                    >
                                        Klant: {{ item.customer_name }}
                                    </p>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="statusBadgeClass(item.status)"
                                >
                                    {{ statusLabel(item.status) }}
                                </span>
                            </div>
                            <div class="mt-3 grid gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-gray-500">Makelaar</p>
                                    <p class="text-gray-900">
                                        {{ item.organization?.name || "-" }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Type vastgoed</p>
                                    <p class="text-gray-900">
                                        {{ formatLabel(item.property_type) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Provincies</p>
                                    <p class="text-gray-900">
                                        {{ formatProvinceList(item.provinces) }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs text-gray-500">Oppervlakte</p>
                                        <p class="text-gray-900">
                                            {{ item.surface_area || "-" }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Verwerving</p>
                                        <p class="text-gray-900">
                                            {{ acquisitionList(item.acquisitions) }}
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Aangemaakt</p>
                                    <p class="text-gray-900">
                                        {{ formatDate(item.created_at) }}
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




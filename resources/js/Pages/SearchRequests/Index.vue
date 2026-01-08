<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageContainer from "@/Components/PageContainer.vue";
import FormSection from "@/Components/FormSection.vue";
import TableCard from "@/Components/TableCard.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import TableRowLink from "@/Components/TableRowLink.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, reactive } from "vue";

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            status: "",
            q: "",
            province: "",
            property_type: "",
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
    status: props.filters?.status ?? "",
    q: props.filters?.q ?? "",
    province: props.filters?.province ?? "",
    property_type: props.filters?.property_type ?? "",
});

const statuses = [
    { value: "", label: "Alle statussen" },
    { value: "concept", label: "Concept" },
    { value: "open", label: "Open" },
    { value: "afgerond", label: "Afgerond" },
    { value: "geannuleerd", label: "Geannuleerd" },
];

const hasFilters = computed(() =>
    Boolean(form.status || form.q || form.province || form.property_type)
);

function formatDate(value) {
    if (!value) return "-";
    return new Intl.DateTimeFormat("nl-NL", {
        day: "numeric",
        month: "long",
        year: "numeric",
    }).format(new Date(value));
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
            status: form.status || undefined,
            province: form.province || undefined,
            property_type: form.property_type || undefined,
            q: form.q || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function resetFilters() {
    form.status = "";
    form.q = "";
    form.province = "";
    form.property_type = "";
    applyFilters();
}

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

function paginationLabel(label) {
    if (!label) return "";
    return label.replace("Previous", "Vorige").replace("Next", "Volgende");
}

</script>

<template>
    <Head title="Zoekvragen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Zoekvragen
                    </h2>
                    <p class="text-sm text-gray-500">
                        Overzicht van zoekvragen (met filters en paginatie).
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="can.create"
                        :href="route('search-requests.create')"
                        class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                    >
                        <span class="hidden sm:inline">Nieuwe aanvraag</span>
                        <span class="sr-only">Nieuwe aanvraag</span>
                        <MaterialIcon
                            name="add"
                            class="h-5 w-5 sm:hidden"
                        />
                    </Link>
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
                            class="h-[38px] w-full min-w-0 rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                            @keyup.enter="applyFilters"
                        />
                        <button
                            type="button"
                            class="inline-flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-md bg-gray-900 p-0 text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            @click="applyFilters"
                        >
                            <MaterialIcon name="search" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-md border border-gray-200 bg-white p-0 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            :disabled="!hasFilters"
                            @click="resetFilters"
                        >
                            <MaterialIcon name="replay" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div
                    class="hidden rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:block"
                >
                    <div
                        class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end"
                    >
                        <div class="md:col-span-10">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Zoeken</label
                            >
                            <input
                                v-model="form.q"
                                type="text"
                                placeholder="Titel, klant of locatie."
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <div class="md:col-span-2 flex gap-2">
                            <button
                                type="button"
                                class="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                                @click="applyFilters"
                            >
                                Zoeken
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:opacity-50"
                                :disabled="!hasFilters"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabel -->
                <div class="mt-6 hidden sm:block">
                    <TableCard>
                    <thead class="bg-gray-50">
                                <tr>
                                    <TableHeaderCell>
                                        Titel
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        Makelaar
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        <select
                                            v-model="form.property_type"
                                            class="w-36 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                            aria-label="Type vastgoed"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Type vastgoed
                                            </option>
                                            <option
                                                v-for="option in options.types"
                                                :key="option"
                                                :value="option"
                                            >
                                                {{ formatLabel(option) }}
                                            </option>
                                        </select>
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        <select
                                            v-model="form.province"
                                            class="w-32 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                            aria-label="Provincie"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Provincie
                                            </option>
                                            <option
                                                v-for="option in options.provinces"
                                                :key="option"
                                                :value="option"
                                            >
                                                {{ formatProvince(option) }}
                                            </option>
                                        </select>
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        Oppervlakte
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        Verwerving
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        Aangemaakt
                                    </TableHeaderCell>
                                    <TableHeaderCell align="right">
                                        <div
                                            class="flex items-center justify-end"
                                        >
                                            <select
                                                v-model="form.status"
                                                class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                                aria-label="Status"
                                                @change="applyFilters"
                                            >
                                                <option
                                                    v-for="s in statuses"
                                                    :key="s.value"
                                                    :value="s.value"
                                            >
                                                {{ s.label }}
                                            </option>
                                        </select>
                                        </div>
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
                                    <TableCell>
                                        <div
                                            class="text-sm font-semibold text-gray-900"
                                        >
                                            {{ item.title }}
                                        </div>
                                        <div
                                            class="text-xs text-gray-500"
                                            v-if="item.customer_name"
                                        >
                                            Klant: {{ item.customer_name }}
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        {{ item.organization?.name || "-" }}
                                    </TableCell>

                                    <TableCell>
                                        {{ formatLabel(item.property_type) }}
                                    </TableCell>

                                    <TableCell>
                                        {{ formatProvinceList(item.provinces) }}
                                    </TableCell>

                                    <TableCell>
                                        {{ item.surface_area || "-" }}
                                    </TableCell>

                                    <TableCell>
                                        {{ acquisitionList(item.acquisitions) }}
                                    </TableCell>

                                    <TableCell>
                                        {{
                                            formatDate(item.created_at)
                                        }}
                                    </TableCell>

                                    <TableCell align="right">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                            :class="
                                                statusBadgeClass(item.status)
                                            "
                                        >
                                            {{ statusLabel(item.status) }}
                                        </span>
                                    </TableCell>
                                </TableRowLink>
                            </tbody>
                    <template #footer>
                        <div
                            v-if="items.links?.length"
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-white px-4 py-3"
                        >
                            <div class="text-sm text-gray-600">
                                <span v-if="items.from && items.to && items.total">
                                    Resultaten {{ items.from }}-{{ items.to }} van
                                    {{ items.total }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-1">
                                <Link
                                    v-for="(link, i) in items.links"
                                    :key="i"
                                    :href="link.url || ''"
                                    class="rounded-md px-3 py-1 text-sm"
                                    :class="[
                                        link.active
                                            ? 'bg-gray-900 text-white'
                                            : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50',
                                        !link.url
                                            ? 'pointer-events-none opacity-40'
                                            : '',
                                    ]"
                                    v-html="paginationLabel(link.label)"
                                />
                            </div>
                        </div>
                    </template>
                    </TableCard>
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

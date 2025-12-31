<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, reactive } from "vue";

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ status: "", q: "" }),
    },
    items: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        default: () => ({ create: false, is_admin: false }),
    },
});

const form = reactive({
    status: props.filters?.status ?? "",
    q: props.filters?.q ?? "",
});

const statuses = [
    { value: "", label: "Alle statussen" },
    { value: "open", label: "Open" },
    { value: "in_behandeling", label: "In behandeling" },
    { value: "afgerond", label: "Afgerond" },
    { value: "geannuleerd", label: "Geannuleerd" },
];

const hasFilters = computed(() => Boolean(form.status || form.q));

function applyFilters() {
    router.get(
        route("search-requests.index"),
        { status: form.status || undefined, q: form.q || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function resetFilters() {
    form.status = "";
    form.q = "";
    applyFilters();
}

function statusBadgeClass(status) {
    switch (status) {
        case "open":
            return "bg-blue-50 text-blue-700 ring-blue-200";
        case "in_behandeling":
            return "bg-amber-50 text-amber-700 ring-amber-200";
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
        open: "Open",
        in_behandeling: "In behandeling",
        afgerond: "Afgerond",
        geannuleerd: "Geannuleerd",
    };
    return map[status] ?? status;
}
</script>

<template>
    <Head title="Search Requests" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Search Requests
                    </h2>
                    <p class="text-sm text-gray-500">
                        Overzicht van aanvragen (met filters en paginatie).
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="can.create"
                        :href="route('search-requests.create')"
                        class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                    >
                        Nieuwe aanvraag
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div
                    class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200"
                >
                    <div
                        class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end"
                    >
                        <div class="md:col-span-3">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Status</label
                            >
                            <select
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
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

                        <div class="md:col-span-7">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Zoeken</label
                            >
                            <input
                                v-model="form.q"
                                type="text"
                                placeholder="Titel of locatie…"
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
                                Filter
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
                <div
                    class="mt-6 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                    >
                                        Titel
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                    >
                                        Locatie
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                    >
                                        Aangemaakt
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                    >
                                        Toegewezen
                                    </th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="items.data?.length === 0">
                                    <td
                                        class="px-4 py-6 text-sm text-gray-600"
                                        colspan="6"
                                    >
                                        Geen aanvragen gevonden.
                                    </td>
                                </tr>

                                <tr
                                    v-for="item in items.data"
                                    :key="item.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3">
                                        <div
                                            class="text-sm font-semibold text-gray-900"
                                        >
                                            {{ item.title }}
                                        </div>
                                        <div
                                            class="text-xs text-gray-500"
                                            v-if="item.creator"
                                        >
                                            Door: {{ item.creator.name
                                            }}<span v-if="can.is_admin">
                                                ({{ item.creator.email }})</span
                                            >
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ item.location || "—" }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                            :class="
                                                statusBadgeClass(item.status)
                                            "
                                        >
                                            {{ statusLabel(item.status) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{
                                            item.created_at
                                                ? new Date(
                                                      item.created_at
                                                  ).toLocaleString("nl-NL")
                                                : "—"
                                        }}
                                    </td>

                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <div v-if="item.assignee">
                                            {{ item.assignee.name
                                            }}<span v-if="can.is_admin">
                                                ({{
                                                    item.assignee.email
                                                }})</span
                                            >
                                        </div>
                                        <div v-else class="text-gray-400">
                                            —
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="
                                                route(
                                                    'search-requests.show',
                                                    item.id
                                                )
                                            "
                                            class="text-sm font-semibold text-gray-900 hover:underline"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginatie -->
                    <div
                        v-if="items.links?.length"
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-white px-4 py-3"
                    >
                        <div class="text-sm text-gray-600">
                            <span v-if="items.from && items.to && items.total">
                                Resultaten {{ items.from }}–{{ items.to }} van
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
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

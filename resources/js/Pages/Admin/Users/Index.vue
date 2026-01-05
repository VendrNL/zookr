<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            status: "active",
            admin: "all",
            sort: "name",
            direction: "asc",
        }),
    },
});

const form = reactive({
    status: props.filters?.status ?? "active",
    admin: props.filters?.admin ?? "all",
});

function applyFilters() {
    router.get(
        route("admin.users.index"),
        {
            status: form.status,
            admin: form.admin,
            sort: props.filters?.sort || "name",
            direction: props.filters?.direction || "asc",
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function toggleSort(column) {
    const currentSort = props.filters?.sort ?? "name";
    const currentDirection = props.filters?.direction ?? "asc";
    const direction =
        currentSort === column && currentDirection === "asc" ? "desc" : "asc";

    router.get(
        route("admin.users.index"),
        {
            status: form.status || undefined,
            admin: form.admin || undefined,
            sort: column,
            direction,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function sortLabel(column) {
    if (props.filters?.sort !== column) return "";
    return props.filters?.direction === "desc" ? "desc" : "asc";
}
</script>

<template>
    <Head title="Gebruikers" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Gebruikers</h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead
                                class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <tr>
                                    <th class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 hover:text-gray-700"
                                                @click="toggleSort('name')"
                                            >
                                                Naam
                                                <span
                                                    v-if="sortLabel('name')"
                                                    class="text-[10px] uppercase text-gray-400"
                                                >
                                                    {{ sortLabel("name") }}
                                                </span>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 hover:text-gray-700"
                                                @click="toggleSort('organization')"
                                            >
                                                Organisatie
                                                <span
                                                    v-if="sortLabel('organization')"
                                                    class="text-[10px] uppercase text-gray-400"
                                                >
                                                    {{ sortLabel("organization") }}
                                                </span>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">E-mail</th>
                                    <th class="px-4 py-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <select
                                                v-model="form.status"
                                                class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                                aria-label="Status"
                                                @change="applyFilters"
                                            >
                                                <option value="all">
                                                    Alle statussen
                                                </option>
                                                <option value="active">
                                                    Actief
                                                </option>
                                                <option value="inactive">
                                                    Inactief
                                                </option>
                                            </select>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-between gap-2">
                                            <select
                                                v-model="form.admin"
                                                class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                                aria-label="Rol"
                                                @change="applyFilters"
                                            >
                                                <option value="all">
                                                    Alle rollen
                                                </option>
                                                <option value="1">Admin</option>
                                                <option value="0">
                                                    Geen admin
                                                </option>
                                            </select>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-if="users.length === 0">
                                    <td class="px-4 py-6 text-gray-500" colspan="5">
                                        Geen gebruikers gevonden.
                                    </td>
                                </tr>
                                <tr v-for="user in users" :key="user.id">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <Link
                                            :href="route('admin.users.edit', user.id)"
                                            class="text-gray-900 hover:text-gray-700"
                                        >
                                            {{ user.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <Link
                                            v-if="user.organization_name"
                                            :href="
                                                route(
                                                    'admin.organizations.edit',
                                                    user.id
                                                )
                                            "
                                            class="text-gray-900 hover:text-gray-700"
                                        >
                                            {{ user.organization_name }}
                                        </Link>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <a
                                            v-if="user.email"
                                            class="text-gray-900 hover:text-gray-700"
                                            :href="`mailto:${user.email}`"
                                        >
                                            {{ user.email }}
                                        </a>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span
                                            v-if="user.is_active === false"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                        >
                                            Inactief
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="user.is_admin"
                                            class="inline-flex items-center rounded-full bg-gray-900 px-2.5 py-0.5 text-xs font-semibold text-white"
                                        >
                                            Admin
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import TableCard from "@/Components/TableCard.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import TableRowLink from "@/Components/TableRowLink.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import MaterialIcon from "@/Components/MaterialIcon.vue";
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
            q: "",
        }),
    },
});

const form = reactive({
    status: props.filters?.status ?? "active",
    admin: props.filters?.admin ?? "all",
    q: props.filters?.q ?? "",
});

function applyFilters() {
    router.get(
        route("admin.users.index"),
        {
            status: form.status,
            admin: form.admin,
            sort: props.filters?.sort || "name",
            direction: props.filters?.direction || "asc",
            q: form.q || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function resetFilters() {
    form.q = "";
    applyFilters();
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

function openUser(id) {
    router.visit(route("admin.users.edit", id));
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
            <PageContainer>
                <div class="hidden rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:block">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                        <div class="md:col-span-10">
                            <label class="block text-sm font-medium text-gray-700">
                                Zoeken
                            </label>
                            <input
                                v-model="form.q"
                                type="text"
                                placeholder="Zoek gebruiker"
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
                                :disabled="!form.q"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div class="hidden sm:block mt-6">
                    <TableCard>
                            <thead class="bg-gray-50">
                                <tr>
                                    <TableHeaderCell>
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
                                    </TableHeaderCell>
                                    <TableHeaderCell>
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
                                    </TableHeaderCell>
                                    <TableHeaderCell>E-mail</TableHeaderCell>
                                    <TableHeaderCell>Telefoon</TableHeaderCell>
                                    <TableHeaderCell>
                                        <div class="flex items-center gap-2">
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
                                    </TableHeaderCell>
                                    <TableHeaderCell>
                                        <div class="flex items-center gap-2">
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
                                    </TableHeaderCell>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <TableEmptyState
                                    v-if="users.length === 0"
                                    :colspan="6"
                                    message="Geen gebruikers gevonden."
                                />
                                <TableRowLink
                                    v-for="user in users"
                                    :key="user.id"
                                    @activate="openUser(user.id)"
                                >
                                    <TableCell>
                                        <span class="text-sm font-semibold text-gray-900">
                                            {{ user.name }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-gray-700">
                                            {{ user.organization_name || "-" }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-gray-700">
                                            {{ user.email || "-" }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-gray-700">
                                            {{ user.phone || "-" }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="user.is_active === false"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                        >
                                            Inactief
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="user.is_admin"
                                            class="inline-flex items-center rounded-full bg-gray-900 px-2.5 py-0.5 text-xs font-semibold text-white"
                                        >
                                            Admin
                                        </span>
                                    </TableCell>
                                </TableRowLink>
                            </tbody>
                    </TableCard>
                </div>

                <div class="space-y-3 sm:hidden">
                    <div class="flex w-full items-center gap-2">
                        <input
                            v-model="form.q"
                            type="text"
                            placeholder="Zoek gebruiker"
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
                            @click="resetFilters"
                        >
                            <MaterialIcon name="replay" class="h-5 w-5" />
                        </button>
                    </div>
                    <p
                        v-if="users.length === 0"
                        class="text-sm text-gray-500"
                    >
                        Geen gebruikers gevonden.
                    </p>
                    <Link
                        v-for="user in users"
                        :key="user.id"
                        :href="route('admin.users.edit', user.id)"
                        class="block"
                    >
                        <FormSection class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 overflow-hidden rounded-full bg-gray-100">
                                        <img
                                            v-if="user.avatar_url"
                                            :src="user.avatar_url"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ user.name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ user.organization_name || "-" }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span
                                        v-if="user.is_admin"
                                        class="inline-flex items-center rounded-full bg-gray-900 px-2.5 py-0.5 text-xs font-semibold text-white"
                                    >
                                        Admin
                                    </span>
                                    <span
                                        v-if="user.is_active === false"
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                    >
                                        Inactief
                                    </span>
                                </div>
                            </div>
                            <div class="mt-3 grid w-full grid-cols-3 gap-2">
                                <a
                                    :href="user.email ? `mailto:${user.email}` : '#'"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-200 py-2 text-gray-700 hover:bg-gray-50"
                                    :class="user.email ? '' : 'pointer-events-none opacity-40'"
                                    @click.stop
                                >
                                    <MaterialIcon name="mail" class="h-5 w-5" />
                                </a>
                                <a
                                    :href="user.phone ? `tel:${user.phone}` : '#'"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-200 py-2 text-gray-700 hover:bg-gray-50"
                                    :class="user.phone ? '' : 'pointer-events-none opacity-40'"
                                    @click.stop
                                >
                                    <MaterialIcon name="phone_enabled" class="h-5 w-5" />
                                </a>
                                <a
                                    :href="user.linkedin_url || '#'"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-200 py-2 text-gray-700 hover:bg-gray-50"
                                    :class="user.linkedin_url ? '' : 'pointer-events-none opacity-40'"
                                    target="_blank"
                                    rel="noreferrer"
                                    @click.stop
                                >
                                    <img
                                        src="/images/icons/linkedin.svg"
                                        alt="LinkedIn"
                                        class="h-5 w-5"
                                    />
                                </a>
                            </div>
                        </FormSection>
                    </Link>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

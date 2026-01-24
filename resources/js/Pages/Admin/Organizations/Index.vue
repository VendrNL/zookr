<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import TableRowLink from "@/Components/TableRowLink.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
    organizations: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            q: "",
            status: "all",
            sort: "",
            direction: "asc",
        }),
    },
});

const form = reactive({
    q: props.filters?.q ?? "",
    status: props.filters?.status ?? "all",
    sort: props.filters?.sort ?? "",
    direction: props.filters?.direction ?? "asc",
});

const searchTimeout = ref(null);

function openOrganization(id) {
    router.visit(route("admin.organizations.edit", id));
}

function applyFilters() {
    router.get(
        route("admin.organizations.index"),
        {
            q: form.q || undefined,
            status: form.status || undefined,
            sort: form.sort || undefined,
            direction: form.direction || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
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

function toggleStatus(organization) {
    router.patch(
        route("admin.organizations.status", organization.id),
        { is_active: !organization.is_active },
        { preserveState: true, preserveScroll: true }
    );
}

const lastLinkIndex = computed(
    () => (props.organizations?.links?.length ?? 1) - 1
);

const statusFilterLabel = computed(() => {
    if (form.status === "active") return "Actief";
    if (form.status === "inactive") return "Inactief";
    return "Alle";
});

function setStatusFilter(value) {
    form.status = value;
    applyFilters();
}

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
</script>

<template>
    <Head title="Organisaties" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Organisaties
                </h2>
            </div>
        </template>

        <div class="py-8">
            <PageContainer>
                <div class="hidden sm:block mt-6">
                    <div class="relative rounded-lg bg-white shadow-md">
                        <div class="flex flex-col items-center justify-between gap-3 p-4 md:flex-row md:gap-4">
                            <div class="w-full md:w-5/12">
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
                                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2 pl-10 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Zoek organisatie"
                                        />
                                    </div>
                                </form>
                            </div>
                            <div
                                class="flex w-full flex-col items-stretch justify-end space-y-2 md:w-auto md:flex-row md:items-center md:space-x-3 md:space-y-0"
                            >
                                <Link
                                    :href="route('admin.organizations.import')"
                                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200"
                                >
                                    Importeer organisaties
                                </Link>
                                <Link
                                    :href="route('admin.organizations.create')"
                                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300"
                                >
                                    <MaterialIcon name="add" class="mr-2 h-4 w-4" />
                                    Nieuwe organisatie
                                </Link>
                            </div>
                        </div>
                        <div>
                            <table class="w-full table-fixed text-left text-sm text-gray-600">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                                    <tr>
                                        <TableHeaderCell class="w-[35%] min-w-[280px]">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 text-gray-600 uppercase"
                                                @click="toggleSort('name')"
                                            >
                                                Naam organisatie
                                                <svg
                                                    v-if="form.sort === 'name'"
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
                                        <TableHeaderCell class="w-[18%] hidden md:table-cell">
                                            Telefoonnummer
                                        </TableHeaderCell>
                                        <TableHeaderCell class="w-[18%] hidden md:table-cell">
                                            E-mail
                                        </TableHeaderCell>
                                        <TableHeaderCell class="w-[18%] hidden lg:table-cell">
                                            Website
                                        </TableHeaderCell>
                                        <TableHeaderCell align="center" class="w-[11%]">
                                            <Dropdown align="right" width="48">
                                                <template #trigger>
                                                    <button
                                                        type="button"
                                                        class="inline-flex w-full items-center justify-center gap-2 text-gray-600 uppercase"
                                                        aria-label="Filter status"
                                                    >
                                                        Actief
                                                        <svg
                                                            v-if="form.status !== 'all'"
                                                            class="h-4 w-4 text-gray-500"
                                                            viewBox="0 0 20 20"
                                                            fill="currentColor"
                                                            aria-hidden="true"
                                                        >
                                                            <path
                                                                fill-rule="evenodd"
                                                                d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V16a1 1 0 01-.553.894l-2 1A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
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
                                                        <DropdownLink href="#" class="normal-case font-normal" @click.prevent="setStatusFilter('active')">
                                                            Actief
                                                        </DropdownLink>
                                                        <DropdownLink href="#" class="normal-case font-normal" @click.prevent="setStatusFilter('inactive')">
                                                            Inactief
                                                        </DropdownLink>
                                                    </div>
                                                </template>
                                            </Dropdown>
                                        </TableHeaderCell>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <TableEmptyState
                                        v-if="organizations.data?.length === 0"
                                        :colspan="5"
                                        message="Geen organisaties gevonden."
                                    />
                                    <TableRowLink
                                        v-for="organization in organizations.data"
                                        :key="organization.id"
                                        @activate="openOrganization(organization.id)"
                                    >
                                        <TableCell class="whitespace-normal break-words">
                                            <span class="text-sm font-semibold text-gray-900">
                                                {{ organization.name }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="hidden md:table-cell truncate">
                                            <span class="text-sm text-gray-700">
                                                {{ organization.phone || "-" }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="hidden md:table-cell truncate">
                                            <span class="text-sm text-gray-700">
                                                {{ organization.email || "-" }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="hidden lg:table-cell truncate">
                                            <span class="text-sm text-gray-700">
                                                {{ organization.website || "-" }}
                                            </span>
                                        </TableCell>
                                        <TableCell align="center">
                                            <div
                                                class="flex items-center justify-center gap-2"
                                                @click.stop
                                            >
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        class="sr-only peer"
                                                        :checked="organization.is_active"
                                                        @change="toggleStatus(organization)"
                                                    />
                                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                                                    <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                                                </label>
                                            </div>
                                        </TableCell>
                                    </TableRowLink>
                                </tbody>
                            </table>
                        </div>
                        <nav
                            v-if="organizations.links?.length"
                            class="flex flex-col items-start justify-between space-y-3 border-t border-gray-200 p-4 md:flex-row md:items-center md:space-y-0"
                            aria-label="Table navigation"
                        >
                            <span class="text-sm font-normal text-gray-500">
                                <span
                                    v-if="
                                        organizations.from &&
                                        organizations.to &&
                                        organizations.total
                                    "
                                >
                                    Resultaten {{ organizations.from }}-{{ organizations.to }} van
                                    {{ organizations.total }}
                                </span>
                            </span>
                            <div class="inline-flex items-stretch -space-x-px">
                                <Link
                                    v-for="(link, i) in organizations.links"
                                    :key="i"
                                    :href="link.url || ''"
                                    class="flex items-center justify-center border border-gray-300 bg-white px-3 py-2 text-sm leading-tight text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                    :class="[
                                        i === 0 ? 'rounded-l-lg' : '',
                                        i === lastLinkIndex ? 'rounded-r-lg' : '',
                                        link.active
                                            ? 'z-10 border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:text-blue-800'
                                            : '',
                                        !link.url
                                            ? 'pointer-events-none opacity-40'
                                            : '',
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </nav>
                    </div>
                </div>

                <div class="space-y-3 sm:hidden">
                    <div class="flex w-full items-center gap-2">
                        <input
                            v-model="form.q"
                            type="text"
                            placeholder="Zoek organisatie"
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
                    </div>
                    <p
                        v-if="organizations.data?.length === 0"
                        class="text-sm text-gray-500"
                    >
                        Geen organisaties gevonden.
                    </p>
                    <div
                        v-for="organization in organizations.data"
                        :key="organization.id"
                        class="block"
                        role="link"
                        tabindex="0"
                        @click="openOrganization(organization.id)"
                        @keydown.enter.prevent="openOrganization(organization.id)"
                        @keydown.space.prevent="openOrganization(organization.id)"
                    >
                        <FormSection class="p-4">
                            <div class="flex flex-col items-start gap-3 text-left">
                                <div
                                    v-if="organization.logo_url"
                                    class="flex w-full"
                                >
                                    <img
                                        :src="organization.logo_url"
                                        alt="Organisatielogo"
                                        class="max-h-[25%] max-w-[25%] object-contain"
                                    />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ organization.name }}
                                    </p>
                                </div>
                                <div class="grid w-full grid-cols-3 grid-flow-col gap-2">
                                    <a
                                        :href="organization.email ? `mailto:${organization.email}` : '#'"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-200 py-2 text-gray-700 hover:bg-gray-50"
                                        :class="organization.email ? '' : 'pointer-events-none opacity-40'"
                                        @click.stop
                                    >
                                        <MaterialIcon name="mail" class="h-5 w-5" />
                                    </a>
                                    <a
                                        :href="organization.phone ? `tel:${organization.phone}` : '#'"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-200 py-2 text-gray-700 hover:bg-gray-50"
                                        :class="organization.phone ? '' : 'pointer-events-none opacity-40'"
                                        @click.stop
                                    >
                                        <MaterialIcon name="phone_enabled" class="h-5 w-5" />
                                    </a>
                                    <a
                                        :href="organization.website || '#'"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-200 py-2 text-gray-700 hover:bg-gray-50"
                                        :class="organization.website ? '' : 'pointer-events-none opacity-40'"
                                        target="_blank"
                                        rel="noreferrer"
                                        @click.stop
                                    >
                                        <MaterialIcon name="public" class="h-5 w-5" />
                                    </a>
                                </div>
                            </div>
                        </FormSection>
                    </div>

                    <div
                        v-if="organizations.links?.length"
                        class="flex items-center justify-between gap-4"
                    >
                        <div class="text-sm text-gray-600">
                            <span
                                v-if="
                                    organizations.from &&
                                    organizations.to &&
                                    organizations.total
                                "
                            >
                                Resultaten {{ organizations.from }}-{{ organizations.to }} van
                                {{ organizations.total }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4">
                            <Link
                                :href="organizations.prev_page_url || ''"
                                class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                                :class="organizations.prev_page_url ? '' : 'pointer-events-none opacity-40'"
                            >
                                Vorige
                            </Link>
                            <Link
                                :href="organizations.next_page_url || ''"
                                class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                                :class="organizations.next_page_url ? '' : 'pointer-events-none opacity-40'"
                            >
                                Volgende
                            </Link>
                        </div>
                    </div>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

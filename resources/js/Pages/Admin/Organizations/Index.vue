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
    organizations: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            q: "",
        }),
    },
});

const form = reactive({
    q: props.filters?.q ?? "",
});

function openOrganization(id) {
    router.visit(route("admin.organizations.edit", id));
}

function applyFilters() {
    router.get(
        route("admin.organizations.index"),
        {
            q: form.q || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function resetFilters() {
    form.q = "";
    applyFilters();
}
</script>

<template>
    <Head title="Organisaties" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">
                    Organisaties
                </h1>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.organizations.import')"
                        class="hidden items-center rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:inline-flex"
                    >
                        Importeer organisaties
                    </Link>
                    <Link
                        :href="route('admin.organizations.create')"
                        class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                    >
                        <span class="hidden sm:inline">Nieuwe organisatie</span>
                        <span class="sr-only">Nieuwe organisatie</span>
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
                <div class="hidden rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:block">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                        <div class="md:col-span-10">
                            <label class="block text-sm font-medium text-gray-700">
                                Zoeken
                            </label>
                            <input
                                v-model="form.q"
                                type="text"
                                placeholder="Zoek organisatie"
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
                                    <TableHeaderCell>Naam organisatie</TableHeaderCell>
                                    <TableHeaderCell>Telefoonnummer</TableHeaderCell>
                                    <TableHeaderCell>E-mail</TableHeaderCell>
                                    <TableHeaderCell>Website</TableHeaderCell>
                                    <TableHeaderCell>Status</TableHeaderCell>
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
                                    <TableCell>
                                        <span class="text-sm font-semibold text-gray-900">
                                            {{ organization.name }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-gray-700">
                                            {{ organization.phone || "-" }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-gray-700">
                                            {{ organization.email || "-" }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-gray-700">
                                            {{ organization.website || "-" }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="organization.is_active === false"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                        >
                                            Inactief
                                        </span>
                                    </TableCell>
                                </TableRowLink>
                            </tbody>
                            <template #footer>
                                <div
                                    v-if="organizations.links?.length"
                                    class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-white px-4 py-3"
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

                                    <div class="flex flex-wrap gap-1">
                                        <Link
                                            v-for="(link, i) in organizations.links"
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
                            </template>
                    </TableCard>
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
                        <button
                            type="button"
                            class="inline-flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-md border border-gray-200 bg-white p-0 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            @click="resetFilters"
                        >
                            <MaterialIcon name="replay" class="h-5 w-5" />
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

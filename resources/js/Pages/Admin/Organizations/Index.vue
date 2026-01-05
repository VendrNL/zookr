<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    organizations: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Organisaties" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">
                    Organisaties
                </h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Naam organisatie</th>
                                    <th class="px-4 py-3">Telefoonnummer</th>
                                    <th class="px-4 py-3">E-mail</th>
                                    <th class="px-4 py-3">Website</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-if="organizations.length === 0">
                                    <td class="px-4 py-6 text-gray-500" colspan="5">
                                        Geen organisaties gevonden.
                                    </td>
                                </tr>
                                <tr v-for="organization in organizations" :key="organization.id">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <Link
                                            :href="route('admin.organizations.edit', organization.id)"
                                            class="text-gray-900 hover:text-gray-700"
                                        >
                                            {{ organization.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ organization.phone || "—" }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <a
                                            v-if="organization.email"
                                            class="text-gray-900 hover:text-gray-700"
                                            :href="`mailto:${organization.email}`"
                                        >
                                            {{ organization.email }}
                                        </a>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <a
                                            v-if="organization.website"
                                            class="text-gray-900 hover:text-gray-700"
                                            :href="organization.website"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {{ organization.website }}
                                        </a>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span
                                            v-if="organization.is_active === false"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                                        >
                                            Inactief
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

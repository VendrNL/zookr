<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import TableCard from "@/Components/TableCard.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import { Head, Link } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const selected = reactive(
    Object.fromEntries(props.users.map((user) => [user.id, true]))
);
</script>

<template>
    <Head title="Zoekvraag verzenden" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Zoekvraag verzenden
                </h2>
                <Link
                    :href="route('search-requests.show', item.id)"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                >
                    Terug naar detail
                </Link>
            </div>
        </template>

        <div class="py-8">
            <PageContainer>
                <div class="hidden sm:block">
                    <TableCard>
                        <thead class="bg-gray-50">
                            <tr>
                                <TableHeaderCell class="w-10">
                                    <span class="sr-only">Selecteer</span>
                                </TableHeaderCell>
                                <TableHeaderCell>
                                    Organisatie
                                </TableHeaderCell>
                                <TableHeaderCell>
                                    Gebruiker
                                </TableHeaderCell>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <TableEmptyState
                                v-if="users.length === 0"
                                :colspan="3"
                                message="Geen gebruikers gevonden."
                            />
                            <tr v-for="user in users" :key="user.id">
                                <TableCell>
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                        v-model="selected[user.id]"
                                    />
                                </TableCell>
                                <TableCell>
                                    {{ user.organization?.name || "-" }}
                                </TableCell>
                                <TableCell>
                                    {{ user.name }}
                                </TableCell>
                            </tr>
                        </tbody>
                    </TableCard>
                </div>

                <div class="space-y-3 sm:hidden">
                    <p v-if="users.length === 0" class="text-sm text-gray-500">
                        Geen gebruikers gevonden.
                    </p>
                    <FormSection
                        v-for="user in users"
                        :key="user.id"
                        class="p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ user.name }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ user.organization?.name || "-" }}
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                v-model="selected[user.id]"
                            />
                        </div>
                    </FormSection>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

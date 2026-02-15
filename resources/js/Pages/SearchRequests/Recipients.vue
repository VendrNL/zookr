<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import PageContainer from "@/Components/PageContainer.vue";
import TableCard from "@/Components/TableCard.vue";
import TableCell from "@/Components/TableCell.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import TableHeaderCell from "@/Components/TableHeaderCell.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, reactive } from "vue";

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

const form = useForm({
    selected_user_ids: [],
});

const selectedIds = computed(() =>
    props.users
        .filter((user) => selected[user.id])
        .map((user) => user.id)
);

const submit = () => {
    form.selected_user_ids = selectedIds.value;
    form.post(route("search-requests.recipients.send", props.item.id));
};
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
                <form class="space-y-4" @submit.prevent="submit">
                <div class="hidden sm:block">
                    <TableCard>
                        <thead class="bg-gray-50">
                            <tr>
                                <TableHeaderCell class="w-10">
                                    <span class="sr-only">Selecteer</span>
                                </TableHeaderCell>
                                <TableHeaderCell>
                                    Makelaar
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

                <InputError
                    v-if="form.errors.selected_user_ids"
                    :message="form.errors.selected_user_ids"
                />

                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('search-requests.show', item.id)">
                        <SecondaryButton type="button" :disabled="form.processing">
                            Annuleren
                        </SecondaryButton>
                    </Link>
                    <PrimaryButton :disabled="form.processing || selectedIds.length === 0">
                        Verzenden
                    </PrimaryButton>
                </div>
                </form>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

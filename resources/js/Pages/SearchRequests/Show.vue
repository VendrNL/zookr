<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DangerButton from "@/Components/DangerButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    item: Object,
    can: {
        type: Object,
        default: () => ({ update: false, assign: false, delete: false }),
    },
});

const placeholderUsers = [
    { id: 1, name: "Demo Admin" },
    { id: 2, name: "Demo Member" },
    { id: 3, name: "Demo Assignee" },
];

const statusOptions = [
    { value: "open", label: "Open" },
    { value: "in_behandeling", label: "In behandeling" },
    { value: "afgerond", label: "Afgerond" },
    { value: "geannuleerd", label: "Geannuleerd" },
];

const statusForm = useForm({
    status: props.item.status ?? "open",
});

const assignForm = useForm({
    assigned_to: props.item.assigned_to,
});

const deleteForm = useForm({});
const showDeleteModal = ref(false);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

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

function updateStatus() {
    statusForm.patch(route("search-requests.status", props.item.id), {
        preserveScroll: true,
    });
}

function submitAssignment() {
    assignForm.patch(
        route("search-requests.assign", props.item.id),
        {
            preserveScroll: true,
        }
    );
}

function assignToSelf() {
    if (!currentUserId.value) return;
    assignForm.assigned_to = currentUserId.value;
    submitAssignment();
}

function clearAssignment() {
    assignForm.assigned_to = null;
    submitAssignment();
}

function confirmDelete() {
    if (deleteForm.processing) return;
    showDeleteModal.value = true;
}

function cancelDelete() {
    showDeleteModal.value = false;
}

function destroyRequest() {
    deleteForm.delete(route("search-requests.destroy", props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
        },
        onFinish: () => {
            showDeleteModal.value = false;
        },
    });
}

function formatDate(value) {
    if (!value) return "-";
    return new Date(value).toLocaleDateString("nl-NL");
}
</script>

<template>
    <Head :title="item.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Search Request</p>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ item.title }}
                    </h1>
                </div>
                <Link
                    :href="route('search-requests.index')"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                >
                    Terug naar overzicht
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
                    <div
                        class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-2"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusBadgeClass(item.status)"
                            >
                                {{ statusLabel(item.status) }}
                            </span>
                            <div class="text-sm text-gray-500">
                                Aangemaakt:
                                {{
                                    item.created_at
                                        ? new Date(
                                              item.created_at
                                          ).toLocaleString("nl-NL")
                                        : "-"
                                }}
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500">Locatie</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ item.location ?? "-" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Deadline</p>
                                <p class="text-base font-medium text-gray-900">
                                    {{ formatDate(item.due_date) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Budget</p>
                                <p class="text-base font-medium text-gray-900">
                                    <span v-if="item.budget_min || item.budget_max">
                                        EUR {{ item.budget_min ?? "-" }} - EUR
                                        {{ item.budget_max ?? "-" }}
                                    </span>
                                    <span v-else>-</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">
                                    Toegewezen aan
                                </p>
                                <p class="text-base font-medium text-gray-900">
                                    <span v-if="item.assignee">
                                        {{ item.assignee.name }}
                                    </span>
                                    <span v-else class="text-gray-500">
                                        Nog niet toegewezen
                                    </span>
                                </p>
                                <p
                                    v-if="item.assignee?.email"
                                    class="text-sm text-gray-500"
                                >
                                    {{ item.assignee.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm text-gray-500">Omschrijving</p>
                            <p class="whitespace-pre-wrap text-gray-900">
                                {{ item.description ?? "-" }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <Link
                                v-if="can.update"
                                :href="route('search-requests.edit', item.id)"
                                class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                            >
                                Bewerk
                            </Link>
                            <DangerButton
                                v-if="can.delete"
                                type="button"
                                @click="confirmDelete"
                                :disabled="deleteForm.processing"
                            >
                                Verwijderen
                            </DangerButton>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div
                            v-if="can.update"
                            class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                        >
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Status bijwerken
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Kies een nieuwe status voor deze aanvraag.
                                </p>
                            </div>

                            <form class="space-y-3" @submit.prevent="updateStatus">
                                <div>
                                    <InputLabel for="status" value="Status" />
                                    <select
                                        id="status"
                                        v-model="statusForm.status"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                        :disabled="statusForm.processing"
                                    >
                                        <option
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="statusForm.errors.status"
                                    />
                                </div>
                                <PrimaryButton
                                    class="w-full justify-center"
                                    :class="{
                                        'opacity-25': statusForm.processing,
                                    }"
                                    :disabled="statusForm.processing"
                                >
                                    Opslaan
                                </PrimaryButton>
                            </form>
                        </div>

                        <div
                            v-if="can.assign"
                            class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                        >
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Toewijzing
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Kies een gebruiker-id of wijs snel aan jezelf toe.
                                </p>
                            </div>

                            <form
                                class="space-y-3"
                                @submit.prevent="submitAssignment"
                            >
                                <div>
                                    <InputLabel
                                        for="assigned_to"
                                        value="Toegewezen aan"
                                    />
                                    <select
                                        id="assigned_to"
                                        v-model.number="assignForm.assigned_to"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    >
                                        <option :value="null">
                                            Geen toewijzing
                                        </option>
                                        <option
                                            v-for="user in placeholderUsers"
                                            :key="user.id"
                                            :value="user.id"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="assignForm.errors.assigned_to"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <PrimaryButton
                                        :class="{
                                            'opacity-25': assignForm.processing,
                                        }"
                                        :disabled="assignForm.processing"
                                    >
                                        Opslaan
                                    </PrimaryButton>
                                    <SecondaryButton
                                        type="button"
                                        :disabled="assignForm.processing || !currentUserId"
                                        @click="assignToSelf"
                                    >
                                        Wijs aan mij toe
                                    </SecondaryButton>
                                    <SecondaryButton
                                        type="button"
                                        :disabled="assignForm.processing"
                                        @click="clearAssignment"
                                    >
                                        Verwijder toewijzing
                                    </SecondaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showDeleteModal" @close="cancelDelete">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    Weet je het zeker?
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Deze aanvraag wordt verwijderd (soft delete) en verdwijnt uit het overzicht.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        :disabled="deleteForm.processing"
                        @click="cancelDelete"
                    >
                        Annuleren
                    </SecondaryButton>
                    <DangerButton
                        :class="{ 'opacity-25': deleteForm.processing }"
                        :disabled="deleteForm.processing"
                        @click="destroyRequest"
                    >
                        Verwijderen
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

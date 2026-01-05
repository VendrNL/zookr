<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const props = defineProps({ item: Object });

const statusOptions = [
    { value: "open", label: "Open" },
    { value: "in_behandeling", label: "In behandeling" },
    { value: "afgerond", label: "Afgerond" },
    { value: "geannuleerd", label: "Geannuleerd" },
];

const form = useForm({
    title: props.item.title ?? "",
    description: props.item.description ?? "",
    location: props.item.location ?? "",
    budget_min: props.item.budget_min,
    budget_max: props.item.budget_max,
    due_date: props.item.due_date ?? "",
    status: props.item.status ?? "open",
});

useDirtyConfirm(form);

function submit() {
    form.put(route("search-requests.update", props.item.id));
}
</script>

<template>
    <Head title="Bewerk Search Request" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Bewerk Search Request
                    </h1>
                    <p class="text-sm text-gray-500">
                        Pas de gegevens van deze aanvraag aan.
                    </p>
                </div>
                <Link
                    :href="route('search-requests.show', item.id)"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                >
                    Terug naar detail
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="title" value="Titel *" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Omschrijving" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="5"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.description"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="location" value="Locatie" />
                            <TextInput
                                id="location"
                                v-model="form.location"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.location"
                            />
                        </div>

                        <div>
                            <InputLabel for="due_date" value="Deadline" />
                            <TextInput
                                id="due_date"
                                v-model="form.due_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.due_date"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="budget_min" value="Budget min" />
                            <TextInput
                                id="budget_min"
                                v-model.number="form.budget_min"
                                type="number"
                                class="mt-1 block w-full"
                                min="0"
                                step="1"
                                inputmode="numeric"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.budget_min"
                            />
                        </div>

                        <div>
                            <InputLabel for="budget_max" value="Budget max" />
                            <TextInput
                                id="budget_max"
                                v-model.number="form.budget_max"
                                type="number"
                                class="mt-1 block w-full"
                                min="0"
                                step="1"
                                inputmode="numeric"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.budget_max"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="status" value="Status" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
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
                            :message="form.errors.status"
                        />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Opslaan
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

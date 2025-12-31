<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({ item: Object });

const form = useForm({
    title: props.item.title,
    description: props.item.description,
    location: props.item.location,
    budget_min: props.item.budget_min,
    budget_max: props.item.budget_max,
    due_date: props.item.due_date,
    status: props.item.status,
});

function submit() {
    form.put(route("search-requests.update", props.item.id));
}
</script>

<template>
    <Head title="Bewerk Search Request" />

    <div class="max-w-3xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Bewerk Search Request</h1>
            <Link
                :href="route('search-requests.show', item.id)"
                class="underline"
                >Terug</Link
            >
        </div>

        <div class="bg-white rounded border p-6">
            <div class="mb-4">
                <label class="block text-sm mb-1">Titel *</label>
                <input
                    v-model="form.title"
                    class="w-full border rounded px-3 py-2"
                />
                <div v-if="form.errors.title" class="text-red-600 text-sm mt-1">
                    {{ form.errors.title }}
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm mb-1">Omschrijving</label>
                <textarea
                    v-model="form.description"
                    class="w-full border rounded px-3 py-2"
                    rows="5"
                />
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-1">Locatie</label>
                    <input
                        v-model="form.location"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm mb-1">Deadline</label>
                    <input
                        v-model="form.due_date"
                        type="date"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-1">Budget min</label>
                    <input
                        v-model="form.budget_min"
                        type="number"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm mb-1">Budget max</label>
                    <input
                        v-model="form.budget_max"
                        type="number"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm mb-1">Status</label>
                <select
                    v-model="form.status"
                    class="w-full border rounded px-3 py-2"
                >
                    <option value="open">Open</option>
                    <option value="in_behandeling">In behandeling</option>
                    <option value="afgerond">Afgerond</option>
                    <option value="geannuleerd">Geannuleerd</option>
                </select>
            </div>

            <button
                @click="submit"
                class="px-4 py-2 rounded bg-black text-white"
                :disabled="form.processing"
            >
                Opslaan
            </button>
        </div>
    </div>
</template>

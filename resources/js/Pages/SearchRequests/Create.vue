<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const form = useForm({
    title: "",
    description: "",
    location: "",
    budget_min: null,
    budget_max: null,
    due_date: null,
});

function submit() {
    form.post(route("search-requests.store"));
}
</script>

<template>
    <Head title="Nieuwe Search Request" />

    <div class="max-w-3xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Nieuwe Search Request</h1>
            <Link :href="route('search-requests.index')" class="underline"
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

            <div class="grid grid-cols-2 gap-4 mb-6">
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

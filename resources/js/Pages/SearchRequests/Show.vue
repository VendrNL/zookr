<script setup>
import { Head, Link, router } from "@inertiajs/vue3";

const props = defineProps({ item: Object });

function destroyItem() {
    if (!confirm("Weet je zeker dat je deze aanvraag wilt verwijderen?"))
        return;
    router.delete(route("search-requests.destroy", props.item.id));
}
</script>

<template>
    <Head :title="item.title" />

    <div class="max-w-3xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">{{ item.title }}</h1>
            <Link :href="route('search-requests.index')" class="underline"
                >Terug</Link
            >
        </div>

        <div class="bg-white rounded border p-6 space-y-3">
            <div>
                <span class="font-semibold">Status:</span> {{ item.status }}
            </div>
            <div>
                <span class="font-semibold">Locatie:</span>
                {{ item.location ?? "—" }}
            </div>
            <div>
                <span class="font-semibold">Deadline:</span>
                {{ item.due_date ?? "—" }}
            </div>
            <div>
                <span class="font-semibold">Budget:</span>
                <span v-if="item.budget_min || item.budget_max">
                    €{{ item.budget_min ?? "—" }} – €{{
                        item.budget_max ?? "—"
                    }}
                </span>
                <span v-else>—</span>
            </div>

            <div>
                <div class="font-semibold mb-1">Omschrijving</div>
                <div class="whitespace-pre-wrap text-gray-800">
                    {{ item.description ?? "—" }}
                </div>
            </div>

            <div class="pt-4 flex gap-3">
                <Link
                    :href="route('search-requests.edit', item.id)"
                    class="px-4 py-2 rounded bg-black text-white"
                >
                    Bewerk
                </Link>
                <button @click="destroyItem" class="px-4 py-2 rounded border">
                    Verwijder
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { Head, useForm } from "@inertiajs/vue3";

const props = defineProps({
    fields: {
        type: Array,
        required: true,
    },
    domains: {
        type: Array,
        required: true,
    },
    mappings: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    mappings: props.domains.reduce((acc, domain) => {
        const domainMap = props.mappings?.[domain] ?? {};
        acc[domain] = props.fields.reduce((fieldAcc, field) => {
            fieldAcc[field] = domainMap[field] ?? "";
            return fieldAcc;
        }, {});
        return acc;
    }, {}),
});

function saveMappings() {
    form.patch(route("admin.mapping.update"), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Mapping" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Mapping
                </h2>
            </div>
        </template>

        <div class="py-8">
            <PageContainer>
                <div class="rounded-lg bg-white shadow-md">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <p class="text-sm text-gray-500">
                            Koppel velden in Zookr aan selectors per domein.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                                <tr>
                                    <th class="w-64 px-6 py-3">Veld</th>
                                    <th
                                        v-for="domain in props.domains"
                                        :key="domain"
                                        class="px-6 py-3"
                                    >
                                        {{ domain }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="field in props.fields"
                                    :key="field"
                                    class="border-b border-gray-100"
                                >
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                        {{ field }}
                                    </td>
                                    <td
                                        v-for="domain in props.domains"
                                        :key="`${field}-${domain}`"
                                        class="px-6 py-4"
                                    >
                                        <input
                                            v-model="form.mappings[domain][field]"
                                            type="text"
                                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="CSS selector"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 px-6 py-4">
                        <PrimaryButton type="button" @click="saveMappings">
                            Opslaan
                        </PrimaryButton>
                    </div>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

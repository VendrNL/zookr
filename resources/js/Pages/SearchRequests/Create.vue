<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const props = defineProps({
    options: {
        type: Object,
        default: () => ({
            types: [],
            provinces: [],
            acquisitions: [],
        }),
    },
});

const form = useForm({
    title: "",
    customer_name: "",
    location: "",
    provinces: [],
    property_type: "",
    surface_area: "",
    parking: "",
    availability: "",
    accessibility: "",
    acquisitions: [],
    notes: "",
    send: false,
});

function formatLabel(value) {
    if (!value) return "";
    const parts = value.replaceAll("_", " ").split(" ");
    return parts
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function formatProvince(value) {
    if (!value) return "";
    const parts = value.split("_");
    if (parts.length > 1) {
        return parts
            .map(
                (part) =>
                    part.charAt(0).toUpperCase() + part.slice(1)
            )
            .join("-");
    }
    return formatLabel(value);
}

function acquisitionLabel(value) {
    return value === "huur" ? "Huur" : "Koop";
}

function submitConcept(onSuccess) {
    if (typeof onSuccess !== "function") {
        onSuccess = undefined;
    }
    form.send = false;
    form.post(route("search-requests.store"), { onSuccess });
}

function submitAndSend() {
    form.send = true;
    form.post(route("search-requests.store"));
}

const { confirmLeave } = useDirtyConfirm(form, undefined, {
    onSave: (done) => submitConcept(done),
});

const handleCancel = () => {
    confirmLeave({
        onConfirm: () => {
            router.visit(route("search-requests.index"));
        },
    });
};
</script>

<template>
    <Head title="Nieuwe zoekvraag" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Nieuwe zoekvraag
                </h2>
                <Link
                    :href="route('search-requests.index')"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                >
                    <span class="hidden sm:inline">Terug naar overzicht</span>
                    <span class="sr-only">Terug naar overzicht</span>
                    <MaterialIcon
                        name="reply"
                        class="h-5 w-5 sm:hidden"
                    />
                </Link>
            </div>
        </template>

        <div class="py-8">
            <PageContainer class="max-w-3xl">
                <FormSection>
                    <form
                        class="space-y-6"
                        @submit.prevent="submitConcept"
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
                        <InputLabel
                            for="customer_name"
                            value="Naam klant *"
                        />
                        <TextInput
                            id="customer_name"
                            v-model="form.customer_name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autocomplete="off"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.customer_name"
                        />
                    </div>

                    <div>
                        <InputLabel for="location" value="Locatie *" />
                        <TextInput
                            id="location"
                            v-model="form.location"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autocomplete="off"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.location"
                        />
                    </div>

                    <div>
                        <InputLabel value="Provincies *" />
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="option in options.provinces"
                                :key="option"
                                class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 shadow-sm hover:border-gray-300"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                    :checked="form.provinces.includes(option)"
                                    @change="
                                        form.provinces = form.provinces.includes(option)
                                            ? form.provinces.filter((p) => p !== option)
                                            : [...form.provinces, option]
                                    "
                                />
                                <span>
                                    {{ formatProvince(option) }}
                                </span>
                            </label>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.provinces || form.errors['provinces.*']"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel
                                for="property_type"
                                value="Type vastgoed *"
                            />
                            <select
                                id="property_type"
                                v-model="form.property_type"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                required
                            >
                                <option value="" disabled>
                                    Kies een type vastgoed
                                </option>
                                <option
                                    v-for="option in options.types"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ formatLabel(option) }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="form.errors.property_type"
                            />
                        </div>

                        <div>
                            <InputLabel
                                for="availability"
                                value="Beschikbaarheid *"
                            />
                            <TextInput
                                id="availability"
                                v-model="form.availability"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.availability"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel
                                for="surface_area"
                                value="Oppervlakte *"
                            />
                            <TextInput
                                id="surface_area"
                                v-model="form.surface_area"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.surface_area"
                            />
                        </div>

                        <div>
                            <InputLabel for="parking" value="Parkeren" />
                            <TextInput
                                id="parking"
                                v-model="form.parking"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.parking"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel
                            for="accessibility"
                            value="Bereikbaarheid"
                        />
                        <TextInput
                            id="accessibility"
                            v-model="form.accessibility"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="off"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.accessibility"
                        />
                    </div>

                    <div>
                        <InputLabel value="Verwerving *" />
                        <div class="mt-3 flex flex-wrap gap-3">
                            <label
                                v-for="option in options.acquisitions"
                                :key="option"
                                class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 shadow-sm hover:border-gray-300"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                    :checked="form.acquisitions.includes(option)"
                                    @change="
                                        form.acquisitions = form.acquisitions.includes(option)
                                            ? form.acquisitions.filter((a) => a !== option)
                                            : [...form.acquisitions, option]
                                    "
                                />
                                <span>
                                    {{ acquisitionLabel(option) }}
                                </span>
                            </label>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.acquisitions || form.errors['acquisitions.*']"
                        />
                    </div>

                    <div>
                        <InputLabel for="notes" value="Bijzonderheden" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="5"
                            maxlength="800"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <FormActions align="right">
                        <SecondaryButton
                            type="button"
                            :disabled="form.processing"
                            @click="() => submitConcept()"
                        >
                            Opslaan als concept
                        </SecondaryButton>
                        <PrimaryButton
                            type="button"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="submitAndSend"
                        >
                            Opslaan en verzenden
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="form.processing"
                            @click="handleCancel"
                        >
                            Annuleren
                        </SecondaryButton>
                    </FormActions>
                    </form>
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

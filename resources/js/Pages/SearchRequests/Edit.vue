<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";
import { onBeforeUnmount, onMounted, ref } from "vue";

const props = defineProps({
    item: Object,
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
    title: props.item.title ?? "",
    customer_name: props.item.customer_name ?? "",
    location: props.item.location ?? "",
    provinces: props.item.provinces ?? [],
    property_type: props.item.property_type ?? "",
    surface_area: props.item.surface_area ?? "",
    parking: props.item.parking ?? "",
    availability: props.item.availability ?? "",
    accessibility: props.item.accessibility ?? "",
    acquisitions: props.item.acquisitions ?? [],
    notes: props.item.notes ?? "",
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
    form.put(route("search-requests.update", props.item.id), { onSuccess });
}

function submitAndSend() {
    form.send = true;
    form.put(route("search-requests.update", props.item.id));
}

const { confirmLeave } = useDirtyConfirm(form, undefined, {
    onSave: (done) => submitConcept(done),
});

const propertyTypeMenuOpen = ref(false);
const propertyTypeMenuRef = ref(null);

function selectedPropertyTypeLabel() {
    return form.property_type ? formatLabel(form.property_type) : "";
}

function togglePropertyTypeMenu() {
    propertyTypeMenuOpen.value = !propertyTypeMenuOpen.value;
}

function selectPropertyType(option) {
    form.property_type = option;
    propertyTypeMenuOpen.value = false;
}

function handlePropertyTypeOutsideClick(event) {
    if (!propertyTypeMenuRef.value) return;
    if (!propertyTypeMenuRef.value.contains(event.target)) {
        propertyTypeMenuOpen.value = false;
    }
}

const handleCancel = () => {
    confirmLeave({
        onConfirm: () => {
            router.visit(route("search-requests.show", props.item.id));
        },
    });
};

onMounted(() => {
    document.addEventListener("click", handlePropertyTypeOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handlePropertyTypeOutsideClick);
});
</script>

<template>
    <Head title="Zoekvraag bewerken" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                    Zoekvraag bewerken
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
                                class="flex min-h-[42px] items-center gap-3 rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded-xs border border-default-medium bg-white text-blue-700 checked:border-blue-700 checked:bg-blue-700 focus:ring-2 focus:ring-brand-soft"
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
                            <div
                                ref="propertyTypeMenuRef"
                                class="relative mt-1"
                            >
                                <button
                                    id="property_type"
                                    type="button"
                                    class="inline-flex w-full items-center justify-between rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-left text-sm text-heading shadow-xs focus:outline-none focus:ring-1 focus:ring-brand"
                                    :aria-expanded="propertyTypeMenuOpen ? 'true' : 'false'"
                                    @click="togglePropertyTypeMenu"
                                >
                                    <span :class="form.property_type ? 'text-heading' : 'text-body'">
                                        {{ selectedPropertyTypeLabel() || "Kies een type vastgoed" }}
                                    </span>
                                    <svg
                                        class="h-4 w-4 text-gray-500"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>

                                <div
                                    v-if="propertyTypeMenuOpen"
                                    class="absolute z-20 mt-1 w-full rounded-md bg-white py-1 ring-1 ring-black ring-opacity-5"
                                >
                                    <button
                                        v-for="option in options.types"
                                        :key="option"
                                        type="button"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                        @click="selectPropertyType(option)"
                                    >
                                        {{ formatLabel(option) }}
                                    </button>
                                </div>
                            </div>
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
                                class="flex min-h-[42px] items-center gap-2 rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded-xs border border-default-medium bg-white text-blue-700 checked:border-blue-700 checked:bg-blue-700 focus:ring-2 focus:ring-brand-soft"
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
                            class="mt-1 block w-full rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand placeholder:text-body"
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


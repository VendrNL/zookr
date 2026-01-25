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
import { computed, ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const props = defineProps({
    item: Object,
    users: Array,
    currentUserId: Number,
    options: {
        type: Object,
        default: () => ({
            acquisitions: [],
        }),
    },
});

const form = useForm({
    address: "",
    city: "",
    name: "",
    surface_area: "",
    availability: "",
    acquisition: "",
    parking_spots: "",
    rent_price_per_m2: "",
    rent_price_parking: "",
    notes: "",
    images: [],
    url: "",
    brochure: null,
    drawings: null,
    contact_user_id: props.currentUserId ?? null,
});

const imagesInput = ref(null);
const brochureInput = ref(null);
const drawingsInput = ref(null);
const isDraggingImages = ref(false);
const isDraggingBrochure = ref(false);
const isDraggingDrawings = ref(false);

const imageNames = computed(() => form.images.map((file) => file.name));

const setImages = (files) => {
    form.images = files ? Array.from(files) : [];
};

const handleImagesChange = (event) => {
    setImages(event.target.files);
};

const handleImagesDrop = (event) => {
    event.preventDefault();
    isDraggingImages.value = false;
    setImages(event.dataTransfer?.files);
};

const handleDragOverImages = (event) => {
    event.preventDefault();
    isDraggingImages.value = true;
};

const handleDragLeaveImages = () => {
    isDraggingImages.value = false;
};

const openImagesPicker = () => {
    imagesInput.value?.click();
};

const setBrochure = (file) => {
    form.brochure = file ?? null;
};

const handleBrochureChange = (event) => {
    setBrochure(event.target.files?.[0] ?? null);
};

const handleBrochureDrop = (event) => {
    event.preventDefault();
    isDraggingBrochure.value = false;
    setBrochure(event.dataTransfer?.files?.[0] ?? null);
};

const handleDragOverBrochure = (event) => {
    event.preventDefault();
    isDraggingBrochure.value = true;
};

const handleDragLeaveBrochure = () => {
    isDraggingBrochure.value = false;
};

const openBrochurePicker = () => {
    brochureInput.value?.click();
};

const setDrawings = (file) => {
    form.drawings = file ?? null;
};

const handleDrawingsChange = (event) => {
    setDrawings(event.target.files?.[0] ?? null);
};

const handleDrawingsDrop = (event) => {
    event.preventDefault();
    isDraggingDrawings.value = false;
    setDrawings(event.dataTransfer?.files?.[0] ?? null);
};

const handleDragOverDrawings = (event) => {
    event.preventDefault();
    isDraggingDrawings.value = true;
};

const handleDragLeaveDrawings = () => {
    isDraggingDrawings.value = false;
};

const openDrawingsPicker = () => {
    drawingsInput.value?.click();
};

const submit = (onSuccess) => {
    form.post(route("search-requests.properties.store", props.item.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess,
    });
};

const { confirmLeave } = useDirtyConfirm(form, undefined, {
    onSave: (done) => submit(done),
});

const handleCancel = () => {
    confirmLeave({
        onConfirm: () => {
            router.visit(route("search-requests.show", props.item.id));
        },
        onSave: (done) => submit(done),
    });
};
</script>

<template>
    <Head title="Pand aanbieden" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                        Pand aanbieden
                    </h2>
                    <p class="text-sm text-gray-500">
                        Zoekvraag: {{ item.title }}
                    </p>
                </div>
                <Link
                    :href="route('search-requests.show', item.id)"
                    class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                >
                    <span class="hidden sm:inline">Terug naar zoekvraag</span>
                    <span class="sr-only">Terug naar zoekvraag</span>
                    <MaterialIcon
                        name="reply"
                        class="h-5 w-5 sm:hidden"
                    />
                </Link>
            </div>
        </template>

        <div class="py-8">
            <PageContainer class="max-w-4xl">
                <FormSection>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel for="address" value="Adres *" />
                                <TextInput
                                    id="address"
                                    v-model="form.address"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    autocomplete="street-address"
                                />
                                <InputError class="mt-2" :message="form.errors.address" />
                            </div>
                            <div>
                                <InputLabel for="city" value="Plaats *" />
                                <TextInput
                                    id="city"
                                    v-model="form.city"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    autocomplete="address-level2"
                                />
                                <InputError class="mt-2" :message="form.errors.city" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel for="name" value="Projectnaam" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>
                            <div>
                                <InputLabel
                                    for="surface_area"
                                    value="Oppervlakte *"
                                />
                                <TextInput
                                    id="surface_area"
                                    v-model="form.surface_area"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.surface_area" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
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
                                <InputError class="mt-2" :message="form.errors.availability" />
                            </div>
                            <div>
                                <InputLabel
                                    for="acquisition"
                                    value="Verwerving *"
                                />
                                <select
                                    id="acquisition"
                                    v-model="form.acquisition"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    required
                                >
                                    <option value="" disabled>
                                        Kies huur of koop
                                    </option>
                                    <option
                                        v-for="option in options.acquisitions"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option === "huur" ? "Huur" : "Koop" }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.acquisition" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel
                                    for="parking_spots"
                                    value="Aantal parkeerplaatsen"
                                />
                                <TextInput
                                    id="parking_spots"
                                    v-model="form.parking_spots"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors.parking_spots" />
                            </div>
                            <div>
                                <InputLabel
                                    for="rent_price_per_m2"
                                    value="Huurprijs per m2 per jaar *"
                                />
                                <TextInput
                                    id="rent_price_per_m2"
                                    v-model="form.rent_price_per_m2"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.rent_price_per_m2" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel
                                    for="rent_price_parking"
                                    value="Huurprijs per parkeerplaats per jaar *"
                                />
                                <TextInput
                                    id="rent_price_parking"
                                    v-model="form.rent_price_parking"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.rent_price_parking" />
                            </div>
                            <div>
                                <InputLabel for="url" value="URL" />
                                <TextInput
                                    id="url"
                                    v-model="form.url"
                                    type="url"
                                    class="mt-1 block w-full"
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.url" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="notes" value="Toelichting" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="5"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>

                        <div class="space-y-3">
                            <InputLabel value="Afbeeldingen *" />
                            <input
                                ref="imagesInput"
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImagesChange"
                            />
                            <div
                                class="flex min-h-[120px] cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                :class="isDraggingImages ? 'border-gray-900' : 'border-gray-300'"
                                role="button"
                                tabindex="0"
                                @click="openImagesPicker"
                                @keydown.enter.space.prevent="openImagesPicker"
                                @dragover="handleDragOverImages"
                                @dragleave="handleDragLeaveImages"
                                @drop="handleImagesDrop"
                            >
                                <span class="px-4 text-center text-sm font-medium text-gray-700">
                                    Klik om afbeeldingen te kiezen of sleep ze hierheen.
                                </span>
                                <span v-if="imageNames.length" class="mt-2 text-xs text-gray-500">
                                    {{ imageNames.join(", ") }}
                                </span>
                            </div>
                            <InputError class="mt-2" :message="form.errors.images || form.errors['images.*']" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-3">
                                <InputLabel value="Brochure" />
                                <input
                                    ref="brochureInput"
                                    type="file"
                                    class="hidden"
                                    @change="handleBrochureChange"
                                />
                                <div
                                    class="flex min-h-[110px] cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                    :class="isDraggingBrochure ? 'border-gray-900' : 'border-gray-300'"
                                    role="button"
                                    tabindex="0"
                                    @click="openBrochurePicker"
                                    @keydown.enter.space.prevent="openBrochurePicker"
                                    @dragover="handleDragOverBrochure"
                                    @dragleave="handleDragLeaveBrochure"
                                    @drop="handleBrochureDrop"
                                >
                                    <span class="px-4 text-center text-sm font-medium text-gray-700">
                                        Klik om een brochure te kiezen of sleep deze hierheen.
                                    </span>
                                    <span v-if="form.brochure" class="mt-2 text-xs text-gray-500">
                                        {{ form.brochure.name }}
                                    </span>
                                </div>
                                <InputError class="mt-2" :message="form.errors.brochure" />
                            </div>
                            <div class="space-y-3">
                                <InputLabel value="Tekeningen" />
                                <input
                                    ref="drawingsInput"
                                    type="file"
                                    class="hidden"
                                    @change="handleDrawingsChange"
                                />
                                <div
                                    class="flex min-h-[110px] cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                    :class="isDraggingDrawings ? 'border-gray-900' : 'border-gray-300'"
                                    role="button"
                                    tabindex="0"
                                    @click="openDrawingsPicker"
                                    @keydown.enter.space.prevent="openDrawingsPicker"
                                    @dragover="handleDragOverDrawings"
                                    @dragleave="handleDragLeaveDrawings"
                                    @drop="handleDrawingsDrop"
                                >
                                    <span class="px-4 text-center text-sm font-medium text-gray-700">
                                        Klik om tekeningen te kiezen of sleep deze hierheen.
                                    </span>
                                    <span v-if="form.drawings" class="mt-2 text-xs text-gray-500">
                                        {{ form.drawings.name }}
                                    </span>
                                </div>
                                <InputError class="mt-2" :message="form.errors.drawings" />
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                for="contact_user_id"
                                value="Contactpersoon *"
                            />
                            <select
                                id="contact_user_id"
                                v-model.number="form.contact_user_id"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                required
                            >
                                <option value="" disabled>
                                    Kies een contactpersoon
                                </option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.contact_user_id" />
                        </div>

                        <FormActions align="right">
                            <PrimaryButton :disabled="form.processing">
                                Opslaan
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

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
import { computed, nextTick, onMounted, ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const props = defineProps({
    item: Object,
    property: Object,
    propertyMedia: {
        type: Object,
        default: () => ({
            images: [],
            brochure: null,
            drawings: [],
        }),
    },
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
    address: props.property.address ?? "",
    city: props.property.city ?? "",
    name: props.property.name ?? "",
    surface_area: props.property.surface_area ?? "",
    availability: props.property.availability ?? "",
    acquisition: props.property.acquisition ?? "",
    parking_spots: props.property.parking_spots ?? "",
    rent_price_per_m2: props.property.rent_price_per_m2 ?? "",
    rent_price_parking: props.property.rent_price_parking ?? "",
    notes: props.property.notes ?? "",
    images: [],
    url: props.property.url ?? "",
    brochure: null,
    drawings: null,
    contact_user_id: props.property.contact_user_id ?? props.currentUserId ?? null,
    remove_images: [],
    remove_brochure: false,
    remove_drawings: [],
});

const existingImages = ref([...(props.propertyMedia.images ?? [])]);
const existingBrochure = ref(props.propertyMedia.brochure ?? null);
const existingDrawings = ref([...(props.propertyMedia.drawings ?? [])]);

const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;
const MAX_TOTAL_SIZE_BYTES = 100 * 1024 * 1024;

const imagesInput = ref(null);
const brochureInput = ref(null);
const drawingsInput = ref(null);
const isDraggingImages = ref(false);
const isDraggingBrochure = ref(false);
const isDraggingDrawings = ref(false);
const showUploadLimitModal = ref(false);
const notesInput = ref(null);

const imageNames = computed(() => form.images.map((file) => file.name));
const existingBrochureLabel = computed(() =>
    existingBrochure.value?.path?.split("/").pop()
);
const existingDrawingLabels = computed(() =>
    existingDrawings.value.map((drawing) => drawing.path.split("/").pop())
);

const openUploadLimitModal = () => {
    showUploadLimitModal.value = true;
};

const closeUploadLimitModal = () => {
    showUploadLimitModal.value = false;
};

const exceedsUploadLimits = (files) => {
    const totalSize = files.reduce((total, file) => total + (file?.size ?? 0), 0);
    const hasTooLargeFile = files.some((file) => (file?.size ?? 0) > MAX_FILE_SIZE_BYTES);

    return hasTooLargeFile || totalSize > MAX_TOTAL_SIZE_BYTES;
};

const selectedFiles = (overrides = {}) => {
    const images = overrides.images ?? form.images;
    const brochure = overrides.brochure ?? form.brochure;
    const drawings = overrides.drawings ?? form.drawings;

    return [ ...(images ?? []), brochure, drawings ].filter(Boolean);
};

const setImages = (files) => {
    const nextImages = files ? Array.from(files) : [];

    if (exceedsUploadLimits(selectedFiles({ images: nextImages }))) {
        openUploadLimitModal();
        return;
    }

    form.images = nextImages;
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
    const nextBrochure = file ?? null;

    if (exceedsUploadLimits(selectedFiles({ brochure: nextBrochure }))) {
        openUploadLimitModal();
        return;
    }

    form.brochure = nextBrochure;
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
    const nextDrawings = file ?? null;

    if (exceedsUploadLimits(selectedFiles({ drawings: nextDrawings }))) {
        openUploadLimitModal();
        return;
    }

    form.drawings = nextDrawings;
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
    if (exceedsUploadLimits(selectedFiles())) {
        openUploadLimitModal();
        return;
    }

    syncNumberField(surfaceAreaInput, "surface_area", formatNumberValue);
    syncNumberField(parkingSpotsInput, "parking_spots", formatNumberValue);
    syncNumberField(rentPerM2Input, "rent_price_per_m2", formatCurrencyValue);
    syncNumberField(rentParkingInput, "rent_price_parking", formatCurrencyValue);

    form
        .transform((data) => ({
            ...data,
            _method: "patch",
        }))
        .post(
            route("search-requests.properties.update", [props.item.id, props.property.id]),
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess,
                onFinish: () => {
                    form.transform((data) => data);
                },
            }
        );
};

const removeExistingImage = (image) => {
    form.remove_images.push(image.path);
    existingImages.value = existingImages.value.filter((item) => item.path !== image.path);
};

const removeExistingBrochure = () => {
    form.remove_brochure = true;
    existingBrochure.value = null;
};

const removeExistingDrawing = (drawing) => {
    form.remove_drawings.push(drawing.path);
    existingDrawings.value = existingDrawings.value.filter((item) => item.path !== drawing.path);
};

const numberFormat = new Intl.NumberFormat("nl-NL", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const currencyFormat = new Intl.NumberFormat("nl-NL", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const normalizeNumber = (value) => {
    if (!value) return "";
    const cleaned = value
        .replace(/[^0-9.,]/g, "")
        .replace(/\s/g, "");
    const parts = cleaned.split(",");
    if (parts.length > 1) {
        return `${parts[0].replace(/\./g, "")}.${parts.slice(1).join("")}`;
    }
    return cleaned.replace(/\./g, "");
};

const parseNumber = (value) => {
    const normalized = normalizeNumber(String(value ?? ""));
    if (!normalized) return "";
    const parsed = Number.parseFloat(normalized);
    return Number.isNaN(parsed) ? "" : parsed;
};

const formatNumberValue = (value) => {
    if (value === "" || value === null || value === undefined) return "";
    return numberFormat.format(value);
};

const formatCurrencyValue = (value) => {
    if (value === "" || value === null || value === undefined) return "";
    return currencyFormat.format(value);
};

const surfaceAreaInput = ref(formatNumberValue(form.surface_area));
const parkingSpotsInput = ref(formatNumberValue(form.parking_spots));
const rentPerM2Input = ref(formatCurrencyValue(form.rent_price_per_m2));
const rentParkingInput = ref(formatCurrencyValue(form.rent_price_parking));

const updateNumberField = (inputRef, key) => {
    const parsed = parseNumber(inputRef.value);
    form[key] = parsed === "" ? "" : parsed;
};

const syncNumberField = (inputRef, key, formatter) => {
    const parsed = parseNumber(inputRef.value);
    form[key] = parsed === "" ? "" : parsed;
    inputRef.value = parsed === "" ? "" : formatter(parsed);
};

const handleSurfaceAreaInput = () => {
    updateNumberField(surfaceAreaInput, "surface_area");
};

const handleSurfaceAreaBlur = () => {
    syncNumberField(surfaceAreaInput, "surface_area", formatNumberValue);
};

const handleParkingSpotsInput = () => {
    updateNumberField(parkingSpotsInput, "parking_spots");
};

const handleParkingSpotsBlur = () => {
    syncNumberField(parkingSpotsInput, "parking_spots", formatNumberValue);
};

const handleRentPerM2Input = () => {
    updateNumberField(rentPerM2Input, "rent_price_per_m2");
};

const handleRentPerM2Blur = () => {
    syncNumberField(rentPerM2Input, "rent_price_per_m2", formatCurrencyValue);
};

const handleRentParkingInput = () => {
    updateNumberField(rentParkingInput, "rent_price_parking");
};

const handleRentParkingBlur = () => {
    syncNumberField(rentParkingInput, "rent_price_parking", formatCurrencyValue);
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

const autoResizeNotes = () => {
    if (!notesInput.value) return;
    notesInput.value.style.height = "auto";
    notesInput.value.style.height = `${notesInput.value.scrollHeight + 10}px`;
};

onMounted(() => {
    nextTick(autoResizeNotes);
});
</script>

<template>
    <Head title="Aangeboden pand aanpassen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                        Aangeboden pand aanpassen
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
                                    v-model="surfaceAreaInput"
                                    type="text"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                    @input="handleSurfaceAreaInput"
                                    @blur="handleSurfaceAreaBlur"
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
                                    v-model="parkingSpotsInput"
                                    type="text"
                                    min="0"
                                    step="1"
                                    class="mt-1 block w-full"
                                    @input="handleParkingSpotsInput"
                                    @blur="handleParkingSpotsBlur"
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
                                    v-model="rentPerM2Input"
                                    type="text"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                    @input="handleRentPerM2Input"
                                    @blur="handleRentPerM2Blur"
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
                                    v-model="rentParkingInput"
                                    type="text"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                    @input="handleRentParkingInput"
                                    @blur="handleRentParkingBlur"
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
                                ref="notesInput"
                                v-model="form.notes"
                                rows="5"
                                class="mt-1 block w-full resize-none rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                @input="autoResizeNotes"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>

                        <div v-if="existingImages.length" class="space-y-2">
                            <InputLabel value="Bestaande afbeeldingen" />
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div
                                    v-for="image in existingImages"
                                    :key="image.path"
                                    class="group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 aspect-[3/2]"
                                >
                                    <img
                                        :src="image.url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 hidden rounded-full bg-white/90 p-1 text-gray-700 shadow group-hover:block"
                                        @click="removeExistingImage(image)"
                                    >
                                        <span class="sr-only">Verwijderen</span>
                                        <MaterialIcon name="close" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="existingBrochure" class="space-y-2">
                            <InputLabel value="Bestaande brochure" />
                            <div class="group relative flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                <MaterialIcon name="description" class="h-5 w-5 text-gray-500" />
                                <a
                                    :href="existingBrochure.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-sm text-gray-700 hover:text-gray-900"
                                >
                                    {{ existingBrochureLabel }}
                                </a>
                                <button
                                    type="button"
                                    class="absolute right-2 top-2 hidden rounded-full bg-white/90 p-1 text-gray-700 shadow group-hover:block"
                                    @click="removeExistingBrochure"
                                >
                                    <span class="sr-only">Verwijderen</span>
                                    <MaterialIcon name="close" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div v-if="existingDrawings.length" class="space-y-2">
                            <InputLabel value="Bestaande tekeningen" />
                            <div class="space-y-2">
                                <div
                                    v-for="(drawing, index) in existingDrawings"
                                    :key="drawing.path"
                                    class="group relative flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
                                >
                                    <MaterialIcon name="assignment" class="h-5 w-5 text-gray-500" />
                                    <a
                                        :href="drawing.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-sm text-gray-700 hover:text-gray-900"
                                    >
                                        {{ existingDrawingLabels[index] }}
                                    </a>
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 hidden rounded-full bg-white/90 p-1 text-gray-700 shadow group-hover:block"
                                        @click="removeExistingDrawing(drawing)"
                                    >
                                        <span class="sr-only">Verwijderen</span>
                                        <MaterialIcon name="close" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <InputLabel value="Afbeeldingen" />
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

        <div
            v-if="showUploadLimitModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="upload-limit-title"
            @click.self="closeUploadLimitModal"
        >
            <div class="relative w-full max-w-md">
                <div class="rounded-lg bg-white shadow">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3
                            id="upload-limit-title"
                            class="text-lg font-semibold text-gray-900"
                        >
                            Overschreiduing uploadlimiet
                        </h3>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900"
                            aria-label="Sluiten"
                            @click="closeUploadLimitModal"
                        >
                            <span class="sr-only">Sluiten</span>
                            <svg
                                class="h-4 w-4"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 14 14"
                            >
                                <path
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="m1 1 12 12M13 1 1 13"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="space-y-3 px-4 py-5 text-sm text-gray-600">
                        <p>
                            Bestanden kunnen maximaal 10 MB groot zijn en het totaal van de bestanden kan maximaal 100 MB zijn.
                        </p>
                    </div>
                    <div class="flex items-center justify-end border-t border-gray-200 px-4 py-3">
                        <button
                            type="button"
                            class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                            @click="closeUploadLimitModal"
                        >
                            Begrepen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

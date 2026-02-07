<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Dropdown from "@/Components/Dropdown.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import TokenDropdown from "@/Components/TokenDropdown.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";
import useImageDrop from "@/Composables/useImageDrop";

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
    cached_images: [],
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
const isDraggingBrochure = ref(false);
const isDraggingDrawings = ref(false);
const showUploadLimitModal = ref(false);
const notesInput = ref(null);
const remoteImageInput = ref("");
const submitError = ref("");

const safeCreateObjectUrl = (file) => {
    if (!file) return "";
    const creator = globalThis?.URL?.createObjectURL;
    if (typeof creator !== "function") return "";
    return creator(file);
};

const isPdfFile = (file) => {
    if (!file) return false;
    if (file.type === "application/pdf") return true;
    return file.name?.toLowerCase().endsWith(".pdf");
};

const safeImages = computed(() =>
    Array.isArray(form.images) ? form.images.filter(Boolean) : []
);
const imageNames = computed(() => safeImages.value.map((file) => file.name));
const existingBrochureLabel = computed(() =>
    existingBrochure.value?.path?.split("/").pop()
);
const existingDrawingLabels = computed(() =>
    existingDrawings.value.map((drawing) => drawing.path.split("/").pop())
);
const pdfPreviewUrl = (url) => {
    if (!url) return "";
    const separator = url.includes("?") ? "&" : "?";
    return `${url}${separator}toolbar=0&navpanes=0&scrollbar=0`;
};
const brochurePreviewUrl = computed(() => safeCreateObjectUrl(form.brochure));
const drawingsPreviewUrl = computed(() => safeCreateObjectUrl(form.drawings));
const existingBrochureIsPdf = computed(() =>
    existingBrochure.value?.url ? existingBrochure.value.url.toLowerCase().endsWith(".pdf") : false
);
const isPdfUrl = (url) => {
    if (!url) return false;
    return url.toLowerCase().includes(".pdf");
};

const acquisitionOptionLabel = (value) => (value === "huur" ? "Huur" : "Koop");
const selectedAcquisitionLabel = computed(() =>
    form.acquisition ? acquisitionOptionLabel(form.acquisition) : "Kies huur of koop"
);

const contactUserOptions = computed(() => [
    { value: "", label: "Kies een contactpersoon", disabled: true },
    ...(props.users ?? []).map((user) => ({
        value: user.id,
        label: user.name,
    })),
]);

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

const isImageFile = (file) => {
    if (!file || typeof file !== "object") return false;
    if (typeof file.type === "string") {
        return [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
            "image/bmp",
            "image/svg+xml",
        ].includes(file.type);
    }
    if (typeof file.name !== "string") return false;
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(file.name);
};

const setImages = (files) => {
    const nextImages = files ? Array.from(files).filter(Boolean) : [];
    const mergedImages = [ ...safeImages.value, ...nextImages ].filter(isImageFile);

    if (exceedsUploadLimits(selectedFiles({ images: mergedImages }))) {
        openUploadLimitModal();
        return;
    }

    form.images = mergedImages;
};

const sanitizeImages = () => {
    const filtered = safeImages.value.filter(isImageFile);
    if (filtered.length !== safeImages.value.length) {
        form.images = filtered;
    }
};

const debugImages = () => {
    if (!new URLSearchParams(window.location.search).has("debug-images")) {
        return;
    }
    const summary = safeImages.value.map((file) => ({
        name: file?.name,
        type: file?.type,
        size: file?.size,
        isImage: isImageFile(file),
    }));
    console.log("[images-debug]", summary);
};

const debugImagesEnabled = computed(() =>
    new URLSearchParams(window.location.search).has("debug-images")
);

const debugImagesSummary = computed(() =>
    safeImages.value.map((file) => ({
        name: file?.name,
        type: file?.type,
        size: file?.size,
        isImage: isImageFile(file),
    }))
);

const handleImagesChange = async (event) => {
    const prepared = await prepareImageFiles(event.target.files);
    setImages(prepared);
};

const openImagesPicker = () => {
    imagesInput.value?.click();
};

const removeUploadedImage = (index) => {
    const nextImages = safeImages.value.filter((_, idx) => idx !== index);
    form.images = nextImages;
};

const removeCachedImage = (path) => {
    cachedImages.value = cachedImages.value.filter((item) => item.path !== path);
    form.cached_images = cachedImages.value.map((image) => image.path);
};

const addRemoteImageFromInput = () => {
    const value = remoteImageInput.value.trim();
    if (!value) {
        cacheError.value = "Plak een afbeelding-URL.";
        return;
    }
    const urls = value
        .split("\n")
        .map((line) => line.trim())
        .filter((line) => line && !line.startsWith("#"));
    remoteImageInput.value = "";
    urls.forEach((url) => cacheRemoteImage(url));
};

const handleRemoteImagePaste = (event) => {
    const text = event.clipboardData?.getData("text/plain") ?? "";
    if (!text) {
        return;
    }

    const urls = text
        .split("\n")
        .map((line) => line.trim())
        .filter((line) => line && !line.startsWith("#"));

    if (!urls.length) {
        return;
    }

    event.preventDefault();
    urls.forEach((url) => cacheRemoteImage(url));
};

const handleGlobalPaste = (event) => {
    const text = event.clipboardData?.getData("text/plain") ?? "";
    if (!text) {
        return;
    }

    const urls = text
        .split("\n")
        .map((line) => line.trim())
        .filter((line) => line && !line.startsWith("#"));

    if (!urls.length) {
        return;
    }

    const activeElement = document.activeElement;
    if (activeElement && (activeElement.tagName === "INPUT" || activeElement.tagName === "TEXTAREA")) {
        if (activeElement.id === "remote_image_url") {
            event.preventDefault();
        }
    } else {
        event.preventDefault();
    }

    urls.forEach((url) => cacheRemoteImage(url));
};

const setBrochure = (file) => {
    const nextBrochure = file ?? null;

    if (exceedsUploadLimits(selectedFiles({ brochure: nextBrochure }))) {
        openUploadLimitModal();
        return;
    }

    form.brochure = nextBrochure;
    if (nextBrochure) {
        form.remove_brochure = true;
        existingBrochure.value = null;
    }
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

const removeSelectedBrochure = () => {
    form.brochure = null;
};

const setDrawings = (file) => {
    const nextDrawings = file ?? null;

    if (exceedsUploadLimits(selectedFiles({ drawings: nextDrawings }))) {
        openUploadLimitModal();
        return;
    }

    form.drawings = nextDrawings;
    if (nextDrawings && existingDrawings.value.length) {
        existingDrawings.value.forEach((drawing) => {
            form.remove_drawings.push(drawing.path);
        });
        existingDrawings.value = [];
    }
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

const removeSelectedDrawings = () => {
    form.drawings = null;
};

const submit = (onSuccess) => {
    if (exceedsUploadLimits(selectedFiles())) {
        openUploadLimitModal();
        return;
    }

    sanitizeImages();
    debugImages();
    submitError.value = "";
    form.url = normalizeUrl(urlInput.value);

    syncNumberField(surfaceAreaInput, "surface_area", formatNumberValue);
    syncNumberField(parkingSpotsInput, "parking_spots", formatNumberValue);
    syncNumberField(rentPerM2Input, "rent_price_per_m2", formatCurrencyValue);
    syncNumberField(rentParkingInput, "rent_price_parking", formatCurrencyValue);

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            form.transform((data) => data);
        },
        onError: (errors) => {
            const messages = Object.values(errors ?? {}).flat();
            submitError.value = messages.length
                ? `Opslaan mislukt: ${messages.join(" ")}`
                : "Opslaan mislukt.";
        },
    };
    if (typeof onSuccess === "function") {
        options.onSuccess = onSuccess;
    }
    form
        .transform((data) => ({
            ...data,
            _method: "patch",
        }))
        .post(
            route("search-requests.properties.update", [props.item.id, props.property.id]),
            options
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

const normalizeUrl = (value) => {
    if (!value) return "";
    const trimmed = value.trim();
    if (!trimmed) return "";
    const withoutDuplicateScheme = trimmed.replace(/^(https?:\/\/)+/i, "https://");
    if (/^https?:\/\//i.test(withoutDuplicateScheme)) {
        try {
            return new URL(withoutDuplicateScheme).toString();
        } catch {
            return withoutDuplicateScheme;
        }
    }
    try {
        return new URL(`https://${withoutDuplicateScheme}`).toString();
    } catch {
        return `https://${withoutDuplicateScheme}`;
    }
};

const stripScheme = (value) => {
    if (!value) return "";
    return value.replace(/^https?:\/\//i, "");
};

const urlInput = ref(stripScheme(form.url));
const {
    cachedImages,
    cacheError,
    isCachingImage,
    isDraggingImages,
    cacheRemoteImage,
    prepareImageFiles,
    handleImagesDrop,
    handleDragOverImages,
    handleDragLeaveImages,
} = useImageDrop({
    form,
    itemId: props.item.id,
    setImages,
    normalizeUrl,
    debugLabel: "edit",
});

const openUrlInNewTab = () => {
    const normalized = normalizeUrl(urlInput.value || form.url);
    if (!normalized) return;
    window.open(normalized, "_blank", "noopener");
};


const handleUrlBlur = () => {
    form.url = normalizeUrl(urlInput.value);
    urlInput.value = stripScheme(form.url);
};

const { confirmLeave } = useDirtyConfirm(form, undefined, {
    onSave: (done) => submit(done),
});

const handleCancel = () => {
    confirmLeave({
        onConfirm: () => {
            router.visit(
                route("search-requests.show", {
                    search_request: props.item.id,
                    tab: "offers",
                })
            );
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
    document.addEventListener("paste", handleGlobalPaste);
});

onBeforeUnmount(() => {
    document.removeEventListener("paste", handleGlobalPaste);
});
</script>

<template>
    <Head title="Aangeboden pand aanpassen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="relative min-w-0 max-w-[30vw] overflow-hidden whitespace-nowrap pr-6 sm:max-w-[35vw] lg:max-w-[40vw]">
                        <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                            {{ item.title }}
                        </h2>
                        <span class="pointer-events-none absolute right-0 top-0 h-full w-8 bg-gradient-to-l from-white"></span>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        Bewerken
                    </span>
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
                        <div
                            v-if="debugImagesEnabled"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900"
                        >
                            <div class="font-semibold">debug-images actief</div>
                            <pre class="mt-2 whitespace-pre-wrap">{{ debugImagesSummary }}</pre>
                        </div>
                        <div
                            v-if="submitError"
                            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                        >
                            {{ submitError }}
                        </div>
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
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
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
                            <div class="hidden md:block"></div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
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
                                    for="parking_spots"
                                    value="Parkeerplaatsen"
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
                                <Dropdown align="left" width="48" contentClasses="py-1 bg-white">
                                    <template #trigger>
                                        <button
                                            id="acquisition"
                                            type="button"
                                            class="mt-1 flex w-full items-center justify-between rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-2 focus:ring-brand"
                                        >
                                            <span :class="form.acquisition ? 'text-gray-900' : 'text-gray-500'">
                                                {{ selectedAcquisitionLabel }}
                                            </span>
                                            <svg
                                                class="h-4 w-4 text-gray-500"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <button
                                            v-for="option in options.acquisitions"
                                            :key="option"
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            @click="form.acquisition = option"
                                        >
                                            <span>{{ acquisitionOptionLabel(option) }}</span>
                                            <span v-if="form.acquisition === option" class="text-fg-brand">•</span>
                                        </button>
                                    </template>
                                </Dropdown>
                                <InputError class="mt-2" :message="form.errors.acquisition" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="url" value="URL" />
                            <div class="mt-1 flex w-full rounded-base shadow-xs">
                                <span
                                    class="inline-flex cursor-pointer items-center rounded-s-lg border border-e-0 border-default-medium bg-neutral-secondary-medium px-3 text-sm text-body"
                                    @click="openUrlInNewTab"
                                >
                                    https://
                                </span>
                                <input
                                    id="url"
                                    v-model="urlInput"
                                    type="text"
                                    class="block w-full rounded-e-lg rounded-none border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading placeholder:text-body focus:border-brand focus:ring-brand"
                                    autocomplete="off"
                                    @blur="handleUrlBlur"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.url" />
                        </div>
                        <div>
                            <InputLabel for="notes" value="Toelichting" />
                            <textarea
                                id="notes"
                                ref="notesInput"
                                v-model="form.notes"
                                rows="5"
                                class="mt-1 block w-full resize-none rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand placeholder:text-body"
                                @input="autoResizeNotes"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>


                        <div class="space-y-2">
                            <InputLabel for="property_images" value="Afbeeldingen" />
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div
                                    v-for="image in existingImages"
                                    :key="image.path"
                                    class="group relative aspect-[3/2] w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                                >
                                    <img
                                        :src="image.url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click="removeExistingImage(image)"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div
                                    v-for="(image, index) in cachedImages"
                                    :key="`${image.path}-${index}`"
                                    class="group relative aspect-[3/2] w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                                >
                                    <img
                                        :src="image.url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click="removeCachedImage(image.path)"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div
                                    v-for="(file, index) in safeImages"
                                    :key="`${file.name}-${index}`"
                                    class="group relative aspect-[3/2] w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                                >
                                    <img
                                        v-if="safeCreateObjectUrl(file)"
                                        :src="safeCreateObjectUrl(file)"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click="removeUploadedImage(index)"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div
                                    class="flex aspect-[3/2] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                    :class="isDraggingImages ? 'border-gray-900' : 'border-gray-300'"
                                    role="button"
                                    tabindex="0"
                                    @click="openImagesPicker"
                                    @keydown.enter.space.prevent="openImagesPicker"
                                    @dragover="handleDragOverImages"
                                    @dragleave="handleDragLeaveImages"
                                    @drop="handleImagesDrop"
                                >
                                    <span class="px-3 text-center text-xs font-medium text-gray-700">
                                        Klik om afbeeldingen te kiezen of sleep ze hierheen.
                                    </span>
                                </div>
                            </div>
                            <input
                                ref="imagesInput"
                                id="property_images"
                                name="images"
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImagesChange"
                            />
                            <InputError class="mt-2" :message="form.errors.images || form.errors['images.*']" />
                            <InputError class="mt-2" :message="form.errors.cached_images || form.errors['cached_images.*']" />
                            <p v-if="cacheError" class="mt-2 text-sm text-red-600">
                                {{ cacheError }}
                            </p>
                            <p v-else-if="isCachingImage" class="mt-2 text-sm text-gray-500">
                                Afbeelding wordt opgeslagen...
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                            <InputLabel for="property_brochure" value="Brochure" />
                                <div
                                    v-if="form.brochure && brochurePreviewUrl"
                                    class="group relative flex aspect-[3/2] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-sm text-gray-600"
                                    :class="[
                                        isDraggingBrochure ? 'border-gray-900' : 'border-gray-300',
                                        isPdfFile(form.brochure)
                                            ? 'border-2 border-solid'
                                            : 'border-2 border-dashed',
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    @click="openBrochurePicker"
                                    @keydown.enter.space.prevent="openBrochurePicker"
                                    @dragover="handleDragOverBrochure"
                                    @dragleave="handleDragLeaveBrochure"
                                    @drop="handleBrochureDrop"
                                >
                                    <iframe
                                        v-if="isPdfFile(form.brochure)"
                                        :src="brochurePreviewUrl"
                                        class="pointer-events-none h-full w-full"
                                        title="Brochure preview"
                                    />
                                    <img
                                        v-else
                                        :src="brochurePreviewUrl"
                                        alt=""
                                        class="pointer-events-none h-full w-full object-cover"
                                    />
                                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 px-4 text-center text-base font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                        Klik om te vervangen of sleep een bestand hierheen
                                    </div>
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click.stop="removeSelectedBrochure"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div
                                    v-else-if="existingBrochure"
                                    class="group relative flex aspect-[3/2] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-sm text-gray-600"
                                    :class="[
                                        isDraggingBrochure ? 'border-gray-900' : 'border-gray-300',
                                        existingBrochureIsPdf ? 'border-2 border-solid' : 'border-2 border-dashed',
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    @click="openBrochurePicker"
                                    @keydown.enter.space.prevent="openBrochurePicker"
                                    @dragover="handleDragOverBrochure"
                                    @dragleave="handleDragLeaveBrochure"
                                    @drop="handleBrochureDrop"
                                >
                                    <iframe
                                        v-if="existingBrochureIsPdf"
                                        :src="pdfPreviewUrl(existingBrochure.url)"
                                        class="pointer-events-none h-full w-full"
                                        title="Brochure preview"
                                    />
                                    <img
                                        v-else
                                        :src="existingBrochure.url"
                                        alt=""
                                        class="pointer-events-none h-full w-full object-cover"
                                    />
                                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 px-4 text-center text-base font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                        Klik om te vervangen of sleep een bestand hierheen
                                    </div>
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click.stop="removeExistingBrochure"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div
                                    v-else
                                    class="flex aspect-[3/2] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                    :class="isDraggingBrochure ? 'border-gray-900' : 'border-gray-300'"
                                    role="button"
                                    tabindex="0"
                                    @click="openBrochurePicker"
                                    @keydown.enter.space.prevent="openBrochurePicker"
                                    @dragover="handleDragOverBrochure"
                                    @dragleave="handleDragLeaveBrochure"
                                    @drop="handleBrochureDrop"
                                >
                                    <span class="px-3 text-center text-xs font-medium text-gray-700">
                                        Klik om een brochure te kiezen of sleep deze hierheen.
                                    </span>
                                    <span v-if="form.brochure" class="mt-2 text-[11px] text-gray-500">
                                        {{ form.brochure.name }}
                                    </span>
                                </div>
                                <input
                                    ref="brochureInput"
                                    id="property_brochure"
                                    name="brochure"
                                    type="file"
                                    class="hidden"
                                    @change="handleBrochureChange"
                                />
                                <InputError class="mt-2" :message="form.errors.brochure" />
                            </div>

                            <div class="space-y-2">
                            <InputLabel for="property_drawings" value="Tekeningen" />
                                <div
                                    v-if="form.drawings && drawingsPreviewUrl"
                                    class="group relative flex aspect-[3/2] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-sm text-gray-600"
                                    :class="[
                                        isDraggingDrawings ? 'border-gray-900' : 'border-gray-300',
                                        isPdfFile(form.drawings)
                                            ? 'border-2 border-solid'
                                            : 'border-2 border-dashed',
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    @click="openDrawingsPicker"
                                    @keydown.enter.space.prevent="openDrawingsPicker"
                                    @dragover="handleDragOverDrawings"
                                    @dragleave="handleDragLeaveDrawings"
                                    @drop="handleDrawingsDrop"
                                >
                                    <iframe
                                        v-if="isPdfFile(form.drawings)"
                                        :src="drawingsPreviewUrl"
                                        class="pointer-events-none h-full w-full"
                                        title="Tekening preview"
                                    />
                                    <img
                                        v-else
                                        :src="drawingsPreviewUrl"
                                        alt=""
                                        class="pointer-events-none h-full w-full object-cover"
                                    />
                                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 px-4 text-center text-base font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                        Klik om te vervangen of sleep een bestand hierheen
                                    </div>
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click.stop="removeSelectedDrawings"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div
                                    v-else-if="existingDrawings.length"
                                    class="grid grid-cols-1 gap-3"
                                >
                                    <div
                                        v-for="(drawing, index) in existingDrawings"
                                        :key="drawing.path"
                                        class="group relative flex aspect-[3/2] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-sm text-gray-600"
                                        :class="[
                                            isDraggingDrawings ? 'border-gray-900' : 'border-gray-300',
                                            drawing.path.toLowerCase().endsWith('.pdf')
                                                ? 'border-2 border-solid'
                                                : 'border-2 border-dashed',
                                        ]"
                                        role="button"
                                        tabindex="0"
                                        @click="openDrawingsPicker"
                                        @keydown.enter.space.prevent="openDrawingsPicker"
                                        @dragover="handleDragOverDrawings"
                                        @dragleave="handleDragLeaveDrawings"
                                        @drop="handleDrawingsDrop"
                                    >
                                        <iframe
                                            v-if="isPdfUrl(drawing.url)"
                                            :src="pdfPreviewUrl(drawing.url)"
                                            class="pointer-events-none h-full w-full"
                                            title="Tekening preview"
                                        />
                                        <img
                                            v-else
                                            :src="drawing.url"
                                            alt=""
                                            class="pointer-events-none h-full w-full object-cover"
                                        />
                                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 px-4 text-center text-base font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                            Klik om te vervangen of sleep een bestand hierheen
                                        </div>
                                        <button
                                            type="button"
                                            class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                            @click.stop="removeExistingDrawing(drawing)"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="flex aspect-[3/2] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed bg-gray-50 text-sm text-gray-600"
                                    :class="isDraggingDrawings ? 'border-gray-900' : 'border-gray-300'"
                                    role="button"
                                    tabindex="0"
                                    @click="openDrawingsPicker"
                                    @keydown.enter.space.prevent="openDrawingsPicker"
                                    @dragover="handleDragOverDrawings"
                                    @dragleave="handleDragLeaveDrawings"
                                    @drop="handleDrawingsDrop"
                                >
                                    <span class="px-3 text-center text-xs font-medium text-gray-700">
                                        Klik om tekeningen te kiezen of sleep deze hierheen.
                                    </span>
                                    <span v-if="form.drawings" class="mt-2 text-[11px] text-gray-500">
                                        {{ form.drawings.name }}
                                    </span>
                                </div>
                                <input
                                    ref="drawingsInput"
                                    id="property_drawings"
                                    name="drawings"
                                    type="file"
                                    class="hidden"
                                    @change="handleDrawingsChange"
                                />
                                <InputError class="mt-2" :message="form.errors.drawings" />
                            </div>
                        </div>
                        <div>
                            <InputLabel
                                for="contact_user_id"
                                value="Contactpersoon *"
                            />
                            <TokenDropdown
                                id="contact_user_id"
                                v-model="form.contact_user_id"
                                :options="contactUserOptions"
                                placeholder="Kies een contactpersoon"
                            />
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


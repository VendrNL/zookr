<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FormActions from "@/Components/FormActions.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import Dropdown from "@/Components/Dropdown.vue";
import PageContainer from "@/Components/PageContainer.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onMounted, ref } from "vue";
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
    remote_images: [],
    url: "",
    brochure: null,
    drawings: null,
    contact_user_id: props.currentUserId ?? null,
});

const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;
const MAX_TOTAL_SIZE_BYTES = 100 * 1024 * 1024;

const imagesInput = ref(null);
const brochureInput = ref(null);
const drawingsInput = ref(null);
const isDraggingImages = ref(false);
const isDraggingBrochure = ref(false);
const isDraggingDrawings = ref(false);
const showUploadLimitModal = ref(false);
const showOverwriteModal = ref(false);
const showManualImportModal = ref(false);
const isScraping = ref(false);
const scrapeError = ref("");
const manualHtml = ref("");
const manualError = ref("");
const notesInput = ref(null);

const safeImages = computed(() =>
    Array.isArray(form.images) ? form.images.filter(Boolean) : []
);
const imageNames = computed(() => safeImages.value.map((file) => file.name));
const safeCreateObjectUrl = (file) => {
    if (!file) return "";
    const creator = globalThis?.URL?.createObjectURL;
    if (typeof creator !== "function") return "";
    return creator(file);
};

const brochurePreviewUrl = computed(() => safeCreateObjectUrl(form.brochure));
const drawingsPreviewUrl = computed(() => safeCreateObjectUrl(form.drawings));
const remoteImages = computed(() =>
    Array.isArray(form.remote_images) ? form.remote_images : []
);

const acquisitionOptionLabel = (value) => (value === "huur" ? "Huur" : "Koop");
const selectedAcquisitionLabel = computed(() =>
    form.acquisition ? acquisitionOptionLabel(form.acquisition) : "Kies huur of koop"
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
    const rawImages = overrides.images ?? form.images;
    const images = Array.isArray(rawImages)
        ? rawImages.filter(Boolean)
        : Array.from(rawImages ?? []).filter(Boolean);
    const brochure = overrides.brochure ?? form.brochure;
    const drawings = overrides.drawings ?? form.drawings;

    return [ ...(images ?? []), brochure, drawings ].filter(Boolean);
};

const setImages = (files) => {
    const nextImages = files ? Array.from(files).filter(Boolean) : [];
    const mergedImages = [ ...safeImages.value, ...nextImages ];

    if (exceedsUploadLimits(selectedFiles({ images: mergedImages }))) {
        openUploadLimitModal();
        return;
    }

    form.images = mergedImages;
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

const openUrlInNewTab = () => {
    const normalized = normalizeUrl(urlInput.value || form.url);
    if (!normalized) return;
    window.open(normalized, "_blank", "noopener");
};

const handleUrlBlur = () => {
    form.url = normalizeUrl(urlInput.value);
    urlInput.value = stripScheme(form.url);
};

const hasScrapeData = computed(() => {
    return Boolean(
        form.address ||
            form.city ||
            form.name ||
            form.surface_area ||
            form.availability ||
            form.acquisition ||
            form.parking_spots ||
            form.rent_price_per_m2 ||
            form.rent_price_parking ||
            form.notes ||
            (form.images?.length ?? 0) > 0 ||
            (form.remote_images?.length ?? 0) > 0 ||
            form.brochure ||
            form.drawings
    );
});

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const applyScrapePayload = (payload) => {
    const toNumber = (value) => {
        const parsed = parseNumber(String(value ?? ""));
        return parsed === "" ? "" : parsed;
    };

    form.address = payload.address ?? "";
    form.city = payload.city ?? "";
    form.name = payload.name ?? "";
    form.availability = payload.availability ?? "";
    form.acquisition = payload.acquisition ?? "";
    form.surface_area = toNumber(payload.surface_area);
    form.parking_spots = toNumber(payload.parking_spots);
    form.rent_price_per_m2 = toNumber(payload.rent_price_per_m2);
    form.rent_price_parking = toNumber(payload.rent_price_parking);
    form.notes = payload.notes ?? "";
    form.url = payload.url ?? form.url;
    urlInput.value = stripScheme(form.url);

    form.images = [];
    form.remote_images = Array.isArray(payload.images) ? payload.images : [];
    form.brochure = null;
    form.drawings = null;

    surfaceAreaInput.value = formatNumberValue(form.surface_area);
    parkingSpotsInput.value = formatNumberValue(form.parking_spots);
    rentPerM2Input.value = formatCurrencyValue(form.rent_price_per_m2);
    rentParkingInput.value = formatCurrencyValue(form.rent_price_parking);

    nextTick(autoResizeNotes);
};

const fetchScrape = async () => {
    const normalizedUrl = normalizeUrl(urlInput.value || form.url);
    if (!normalizedUrl) {
        form.setError("url", "Vul een geldige URL in.");
        return;
    }

    form.clearErrors("url");
    scrapeError.value = "";
    isScraping.value = true;

    try {
        const response = await fetch(
            route("search-requests.properties.import-funda-business", props.item.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
                body: JSON.stringify({
                    url: normalizedUrl,
                    contact_user_id: form.contact_user_id,
                }),
            }
        );

        if (!response.ok) {
            const errorPayload = await response.json().catch(() => ({}));
            const message =
                errorPayload?.message ||
                errorPayload?.errors?.url?.[0] ||
                "Scrapen mislukt. Probeer het opnieuw.";
            scrapeError.value = message;
            return;
        }

        const data = await response.json();
        if (!data?.payload) {
            scrapeError.value = "Geen data ontvangen van de scraper.";
            return;
        }

        applyScrapePayload(data.payload);
    } catch (error) {
        scrapeError.value = "Scrapen mislukt. Probeer het opnieuw.";
    } finally {
        isScraping.value = false;
        showOverwriteModal.value = false;
    }
};

const handleScrapeClick = () => {
    if (hasScrapeData.value) {
        showOverwriteModal.value = true;
        return;
    }

    fetchScrape();
};

const confirmOverwrite = () => {
    fetchScrape();
};

const removeRemoteImage = (url) => {
    form.remote_images = (form.remote_images ?? []).filter((item) => item !== url);
};

const removeUploadedImage = (index) => {
    const nextImages = safeImages.value.filter((_, idx) => idx !== index);
    form.images = nextImages;
};

const openManualImport = () => {
    manualError.value = "";
    showManualImportModal.value = true;
};

const fetchManualImport = async () => {
    if (!manualHtml.value.trim()) {
        manualError.value = "Plak de HTML van de pagina.";
        return;
    }

    const normalizedUrl = normalizeUrl(urlInput.value || form.url);
    manualError.value = "";
    scrapeError.value = "";
    isScraping.value = true;

    try {
        const response = await fetch(
            route("search-requests.properties.import-funda-business-html", props.item.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
                body: JSON.stringify({
                    url: normalizedUrl || null,
                    html: manualHtml.value,
                    contact_user_id: form.contact_user_id,
                }),
            }
        );

        if (!response.ok) {
            const errorPayload = await response.json().catch(() => ({}));
            const message =
                errorPayload?.message ||
                errorPayload?.errors?.html?.[0] ||
                "Importeren mislukt. Probeer het opnieuw.";
            manualError.value = message;
            return;
        }

        const data = await response.json();
        if (!data?.payload) {
            manualError.value = "Geen data ontvangen van de importer.";
            return;
        }

        applyScrapePayload(data.payload);
        manualHtml.value = "";
        showManualImportModal.value = false;
    } catch (error) {
        manualError.value = "Importeren mislukt. Probeer het opnieuw.";
    } finally {
        isScraping.value = false;
    }
};

const handleManualHtmlFileChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    manualHtml.value = await file.text();
};

const getImagePreview = (file) => {
    if (!file || typeof file !== "object" || typeof file.name !== "string") {
        return "";
    }

    return safeCreateObjectUrl(file);
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
};

const handleDrawingsChange = (event) => {
    setDrawings(event.target.files?.[0] ?? null);
};

const handleDrawingsDrop = (event) => {
    event.preventDefault();
    isDraggingDrawings.value = false;
    setDrawings(event.dataTransfer?.files?.[0] ?? null);
};

const isPdfFile = (file) => {
    if (!file) return false;
    if (file.type === "application/pdf") return true;
    return file.name?.toLowerCase().endsWith(".pdf");
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

    form.url = normalizeUrl(urlInput.value);

    syncNumberField(surfaceAreaInput, "surface_area", formatNumberValue);
    syncNumberField(parkingSpotsInput, "parking_spots", formatNumberValue);
    syncNumberField(rentPerM2Input, "rent_price_per_m2", formatCurrencyValue);
    syncNumberField(rentParkingInput, "rent_price_parking", formatCurrencyValue);

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
    <Head title="Pand aanbieden" />

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
                        Aanmaken
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
                                            class="mt-1 flex w-full items-center justify-between rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
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
                                            <span v-if="form.acquisition === option" class="text-blue-600">•</span>
                                        </button>
                                    </template>
                                </Dropdown>
                                <InputError class="mt-2" :message="form.errors.acquisition" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="url" value="URL" />
                            <div class="mt-1 flex w-full flex-col gap-2 sm:flex-row">
                                <div class="flex w-full rounded-base shadow-xs">
                                    <span
                                        class="inline-flex cursor-pointer items-center rounded-s-lg border border-e-0 border-gray-300 bg-gray-100 px-3 text-sm text-gray-500"
                                        @click="openUrlInNewTab"
                                    >
                                        https://
                                    </span>
                                    <input
                                        id="url"
                                        v-model="urlInput"
                                        type="text"
                                        class="block w-full rounded-e-lg rounded-none border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                                        autocomplete="off"
                                        @blur="handleUrlBlur"
                                    />
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <PrimaryButton
                                        type="button"
                                        class="h-11 justify-center sm:w-36"
                                        :disabled="isScraping"
                                        @click="handleScrapeClick"
                                    >
                                        {{ isScraping ? "Ophalen..." : "Ophalen" }}
                                    </PrimaryButton>
                                    <SecondaryButton
                                        type="button"
                                        class="h-11 justify-center sm:w-48"
                                        :disabled="isScraping"
                                        @click="openManualImport"
                                    >
                                        Handmatig importeren
                                    </SecondaryButton>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.url" />
                            <p v-if="scrapeError" class="mt-2 text-sm text-red-600">
                                {{ scrapeError }}
                            </p>
                        </div>

                        <div>
                            <InputLabel for="notes" value="Toelichting" />
                            <textarea
                                id="notes"
                                ref="notesInput"
                                v-model="form.notes"
                                rows="5"
                                class="mt-1 block w-full resize-none rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                @input="autoResizeNotes"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>

                        <div class="space-y-2">
                            <InputLabel value="Afbeeldingen *" />
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div
                                    v-for="(url, index) in remoteImages"
                                    :key="`${url}-${index}`"
                                    class="group relative aspect-[3/2] w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                                >
                                    <img
                                        :src="url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm font-semibold text-gray-700 shadow-md opacity-0 transition group-hover:opacity-100 hover:bg-white"
                                        @click="removeRemoteImage(url)"
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
                                        v-if="getImagePreview(file)"
                                        :src="getImagePreview(file)"
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
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImagesChange"
                            />
                            <InputError class="mt-2" :message="form.errors.images || form.errors['images.*']" />
                            <InputError class="mt-2" :message="form.errors.remote_images || form.errors['remote_images.*']" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <InputLabel value="Brochure" />
                                <div
                                    class="group flex aspect-[3/2] w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-sm text-gray-600"
                                    :class="[
                                        isDraggingBrochure ? 'border-gray-900' : 'border-gray-300',
                                        form.brochure && brochurePreviewUrl && isPdfFile(form.brochure)
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
                                    <div v-if="form.brochure && brochurePreviewUrl" class="relative h-full w-full">
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
                                    <span v-else class="px-3 text-center text-xs font-medium text-gray-700">
                                        Klik om een brochure te kiezen of sleep deze hierheen.
                                    </span>
                                </div>
                                <input
                                    ref="brochureInput"
                                    type="file"
                                    class="hidden"
                                    @change="handleBrochureChange"
                                />
                                <InputError class="mt-2" :message="form.errors.brochure" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel value="Tekeningen" />
                                <div
                                    class="group flex aspect-[3/2] w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-sm text-gray-600"
                                    :class="[
                                        isDraggingDrawings ? 'border-gray-900' : 'border-gray-300',
                                        form.drawings && drawingsPreviewUrl && isPdfFile(form.drawings)
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
                                    <div v-if="form.drawings && drawingsPreviewUrl" class="relative h-full w-full">
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
                                    <span v-else class="px-3 text-center text-xs font-medium text-gray-700">
                                        Klik om tekeningen te kiezen of sleep deze hierheen.
                                    </span>
                                </div>
                                <input
                                    ref="drawingsInput"
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

        <div
            v-if="showOverwriteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="overwrite-title"
            @click.self="showOverwriteModal = false"
        >
            <div class="relative w-full max-w-md">
                <div class="rounded-lg bg-white shadow">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3
                            id="overwrite-title"
                            class="text-lg font-semibold text-gray-900"
                        >
                            Gegevens overschrijven
                        </h3>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900"
                            aria-label="Sluiten"
                            @click="showOverwriteModal = false"
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
                        <p>Wil je de bestaande gegevens overschrijven?</p>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-4 py-3">
                        <SecondaryButton type="button" @click="showOverwriteModal = false">
                            Annuleren
                        </SecondaryButton>
                        <PrimaryButton type="button" :disabled="isScraping" @click="confirmOverwrite">
                            Ja, overschrijf de gegevens
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showManualImportModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="manual-import-title"
            @click.self="showManualImportModal = false"
        >
            <div class="relative w-full max-w-2xl">
                <div class="rounded-lg bg-white shadow">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3
                            id="manual-import-title"
                            class="text-lg font-semibold text-gray-900"
                        >
                            Handmatige import
                        </h3>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900"
                            aria-label="Sluiten"
                            @click="showManualImportModal = false"
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
                        <p>Plak de HTML van de Funda Business detailpagina hieronder.</p>
                        <textarea
                            v-model="manualHtml"
                            rows="8"
                            class="block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                            placeholder="&lt;!doctype html&gt;..."
                        />
                        <div class="flex items-center justify-between gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    type="file"
                                    class="hidden"
                                    accept=".html,.htm,text/html"
                                    @change="handleManualHtmlFileChange"
                                />
                                <span class="rounded-lg border border-gray-300 bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">
                                    Upload HTML bestand
                                </span>
                            </label>
                        </div>
                        <p v-if="manualError" class="text-sm text-red-600">
                            {{ manualError }}
                        </p>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-4 py-3">
                        <SecondaryButton type="button" @click="showManualImportModal = false">
                            Annuleren
                        </SecondaryButton>
                        <PrimaryButton type="button" :disabled="isScraping" @click="fetchManualImport">
                            Importeren
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

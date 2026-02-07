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
import TokenDropdown from "@/Components/TokenDropdown.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";
import useImageDrop from "@/Composables/useImageDrop";

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
    bag_address_id: "",
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
    cached_images: [],
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
const remoteImageInput = ref("");
const submitError = ref("");
const addressSuggestions = ref([]);
const isAddressLookupBusy = ref(false);
const showAddressSuggestions = ref(false);
const addressLookupError = ref("");
const addressLookupInfo = ref("");
const addressLookupTimer = ref(null);
const addressLookupAbortController = ref(null);
const enrichmentData = ref(null);
const isEnrichmentLoading = ref(false);
const enrichmentError = ref("");
const mapContainer = ref(null);
const mapInstance = ref(null);
const mapMarker = ref(null);
const mapOverlay = ref(null);
const mapMode = ref("kaart");
const mapApiLoading = ref(false);

const diagnosticBadgeClass = (status) => {
    if (status === "ok") return "bg-green-100 text-green-800";
    if (status === "missing_key") return "bg-amber-100 text-amber-800";
    if (status === "failed") return "bg-red-100 text-red-800";
    if (status === "skipped") return "bg-gray-200 text-gray-700";
    if (status === "no_data") return "bg-blue-100 text-blue-800";
    return "bg-gray-100 text-gray-700";
};

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
    const rawImages = overrides.images ?? form.images;
    const images = Array.isArray(rawImages)
        ? rawImages.filter(Boolean)
        : Array.from(rawImages ?? []).filter(Boolean);
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
    debugLabel: "offer",
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

const resetAddressSelection = () => {
    form.bag_address_id = "";
    form.city = "";
};

const fetchAddressSuggestions = async (query) => {
    if (addressLookupAbortController.value) {
        addressLookupAbortController.value.abort();
    }

    const controller = new AbortController();
    addressLookupAbortController.value = controller;
    isAddressLookupBusy.value = true;
    addressLookupError.value = "";

    try {
        const params = new URLSearchParams({ q: query });
        const response = await fetch(
            `${route("search-requests.properties.bag-addresses", props.item.id)}?${params.toString()}`,
            {
                method: "GET",
                headers: {
                    Accept: "application/json",
                },
                signal: controller.signal,
            }
        );

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            addressLookupError.value =
                data?.message || "Adres suggesties ophalen is mislukt.";
            addressLookupInfo.value = "";
            addressSuggestions.value = [];
            return;
        }

        addressSuggestions.value = Array.isArray(data?.items) ? data.items : [];
        const fallbackMessage = typeof data?.message === "string" ? data.message : "";
        addressLookupInfo.value = /PDOK Locatieserver/i.test(fallbackMessage)
            ? "BAG offline, fallback via PDOK actief"
            : "";
        showAddressSuggestions.value = true;
    } catch (error) {
        if (error?.name !== "AbortError") {
            addressLookupError.value = "Adres suggesties ophalen is mislukt.";
            addressLookupInfo.value = "";
            addressSuggestions.value = [];
        }
    } finally {
        if (addressLookupAbortController.value === controller) {
            addressLookupAbortController.value = null;
        }
        isAddressLookupBusy.value = false;
    }
};

const scheduleAddressLookup = () => {
    if (addressLookupTimer.value) {
        clearTimeout(addressLookupTimer.value);
    }

    const query = (form.address ?? "").trim();

    if (query.length < 3) {
        if (addressLookupAbortController.value) {
            addressLookupAbortController.value.abort();
            addressLookupAbortController.value = null;
        }
        addressSuggestions.value = [];
        showAddressSuggestions.value = false;
        addressLookupError.value = "";
        addressLookupInfo.value = "";
        isAddressLookupBusy.value = false;
        return;
    }

    addressLookupTimer.value = setTimeout(() => {
        fetchAddressSuggestions(query);
    }, 250);
};

const handleAddressInput = () => {
    if (addressLookupAbortController.value) {
        addressLookupAbortController.value.abort();
        addressLookupAbortController.value = null;
    }
    resetAddressSelection();
    enrichmentData.value = null;
    enrichmentError.value = "";
    form.clearErrors("address", "bag_address_id", "city");
    scheduleAddressLookup();
};

const selectAddressSuggestion = async (item) => {
    form.address = item.label ?? item.address ?? "";
    form.city = item.city ?? "";
    form.bag_address_id = item.id ?? "";
    showAddressSuggestions.value = false;
    addressSuggestions.value = [];
    addressLookupError.value = "";
    addressLookupInfo.value = "";
    form.clearErrors("address", "bag_address_id", "city");
    mapMode.value = "kaart";
    await fetchAddressEnrichment();
};

const handleAddressBlur = () => {
    if (!form.bag_address_id && addressSuggestions.value.length > 0) {
        selectAddressSuggestion(addressSuggestions.value[0]);
        return;
    }

    setTimeout(() => {
        showAddressSuggestions.value = false;
    }, 120);
};

const handleAddressFocus = () => {
    if (addressSuggestions.value.length > 0) {
        showAddressSuggestions.value = true;
    }
};

const parsePointWkt = (wkt) => {
    if (typeof wkt !== "string") return null;
    const match = wkt.match(/POINT\(([-\d.]+)\s+([-\d.]+)\)/i);
    if (!match) return null;
    const lng = Number.parseFloat(match[1]);
    const lat = Number.parseFloat(match[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    return { lat, lng };
};

const ensureGoogleMapsApi = async () => {
    if (globalThis.google?.maps) {
        return true;
    }
    if (mapApiLoading.value) {
        return false;
    }

    mapApiLoading.value = true;
    try {
        const apiKey = enrichmentData.value?.map?.google_maps_api_key;
        if (!apiKey) {
            return false;
        }

        if (document.getElementById("google-maps-script")) {
            return true;
        }

        await new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.id = "google-maps-script";
            script.src =
                "https://maps.googleapis.com/maps/api/js?key=" +
                encodeURIComponent(apiKey);
            script.async = true;
            script.defer = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return Boolean(globalThis.google?.maps);
    } catch {
        return false;
    } finally {
        mapApiLoading.value = false;
    }
};

const tileToMercatorBbox = (x, y, z) => {
    const originShift = 20037508.342789244;
    const tiles = 2 ** z;
    const resolution = (2 * originShift) / (256 * tiles);
    const minx = x * 256 * resolution - originShift;
    const maxx = (x + 1) * 256 * resolution - originShift;
    const maxy = originShift - y * 256 * resolution;
    const miny = originShift - (y + 1) * 256 * resolution;
    return [ minx, miny, maxx, maxy ];
};

const createWmsOverlay = (baseUrl, layerName) => {
    if (!baseUrl || !layerName || !globalThis.google?.maps) {
        return null;
    }

    return new globalThis.google.maps.ImageMapType({
        tileSize: new globalThis.google.maps.Size(256, 256),
        opacity: 0.85,
        getTileUrl: (coord, zoom) => {
            if (!coord) return "";
            const [ minx, miny, maxx, maxy ] = tileToMercatorBbox(coord.x, coord.y, zoom);
            const params = new URLSearchParams({
                service: "WMS",
                request: "GetMap",
                version: "1.3.0",
                layers: layerName,
                styles: "",
                crs: "EPSG:3857",
                bbox: `${minx},${miny},${maxx},${maxy}`,
                width: "256",
                height: "256",
                format: "image/png",
                transparent: "true",
            });
            return `${baseUrl}?${params.toString()}`;
        },
    });
};

const updateMapMode = () => {
    if (!mapInstance.value) return;

    const mapConfig = enrichmentData.value?.map ?? {};
    mapInstance.value.overlayMapTypes.clear();

    if (mapMode.value === "earth") {
        mapInstance.value.setMapTypeId("satellite");
        return;
    }

    mapInstance.value.setMapTypeId("roadmap");

    if (mapMode.value === "kadaster") {
        mapOverlay.value = createWmsOverlay(
            mapConfig.kadastraal_wms_url,
            mapConfig.kadastraal_wms_layer || "KadastraleGrens"
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        }
    }

    if (mapMode.value === "bodemkaart") {
        mapOverlay.value = createWmsOverlay(
            mapConfig.bodemkaart_wms_url,
            mapConfig.bodemkaart_wms_layer
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        }
    }
};

const renderMap = async () => {
    const geocode = enrichmentData.value?.geocode;
    if (!geocode?.lat || !geocode?.lng || !mapContainer.value) {
        return;
    }

    const mapConfig = enrichmentData.value?.map ?? {};
    if (!mapConfig.google_maps_api_key_available) {
        return;
    }

    const ok = await ensureGoogleMapsApi();
    if (!ok || !globalThis.google?.maps) {
        return;
    }

    const center = { lat: geocode.lat, lng: geocode.lng };
    if (!mapInstance.value) {
        mapInstance.value = new globalThis.google.maps.Map(mapContainer.value, {
            center,
            zoom: 17,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
        });
        mapMarker.value = new globalThis.google.maps.Marker({
            map: mapInstance.value,
            position: center,
        });
    } else {
        mapInstance.value.setCenter(center);
        mapMarker.value?.setPosition(center);
    }

    updateMapMode();
};

const fetchAddressEnrichment = async () => {
    if (!form.bag_address_id) {
        enrichmentData.value = null;
        enrichmentError.value = "";
        return;
    }

    isEnrichmentLoading.value = true;
    enrichmentError.value = "";

    try {
        const response = await fetch(
            route("search-requests.properties.address-enrichment", props.item.id) +
                `?bag_address_id=${encodeURIComponent(form.bag_address_id)}`,
            {
                method: "GET",
                headers: { Accept: "application/json" },
            }
        );
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            enrichmentData.value = null;
            enrichmentError.value =
                payload?.message || "Adresverrijking ophalen is mislukt.";
            return;
        }

        enrichmentData.value = payload;

        const bagArea = payload?.bag?.oppervlakte_m2;
        if (
            (form.surface_area === "" || form.surface_area === null) &&
            Number.isFinite(Number(bagArea))
        ) {
            form.surface_area = Number(bagArea);
            surfaceAreaInput.value = formatNumberValue(form.surface_area);
        }

        await nextTick();
        await renderMap();
    } catch {
        enrichmentData.value = null;
        enrichmentError.value = "Adresverrijking ophalen is mislukt.";
    } finally {
        isEnrichmentLoading.value = false;
    }
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

const applyScrapePayload = (payload) => {
    const toNumber = (value) => {
        const parsed = parseNumber(String(value ?? ""));
        return parsed === "" ? "" : parsed;
    };

    form.address = payload.address ?? "";
    form.city = payload.city ?? "";
    form.bag_address_id = "";
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

const removeCachedImage = (path) => {
    cachedImages.value = cachedImages.value.filter((item) => item.path !== path);
    form.cached_images = cachedImages.value.map((image) => image.path);
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

    sanitizeImages();
    debugImages();
    submitError.value = "";
    form.url = normalizeUrl(urlInput.value);

    syncNumberField(surfaceAreaInput, "surface_area", formatNumberValue);
    syncNumberField(parkingSpotsInput, "parking_spots", formatNumberValue);
    syncNumberField(rentPerM2Input, "rent_price_per_m2", formatCurrencyValue);
    syncNumberField(rentParkingInput, "rent_price_parking", formatCurrencyValue);

    if (!form.bag_address_id) {
        form.setError("address", "Kies een adres uit de BAG-suggesties.");
        submitError.value = "Opslaan mislukt: kies een adres uit de BAG-suggesties.";
        return;
    }

    const options = {
        preserveScroll: true,
        forceFormData: true,
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
    form.post(route("search-requests.properties.store", props.item.id), options);
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
    document.addEventListener("paste", handleGlobalPaste);
});

onBeforeUnmount(() => {
    document.removeEventListener("paste", handleGlobalPaste);
    if (addressLookupTimer.value) {
        clearTimeout(addressLookupTimer.value);
    }
    if (addressLookupAbortController.value) {
        addressLookupAbortController.value.abort();
    }
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
                                <div class="relative mt-1">
                                    <TextInput
                                        id="address"
                                        v-model="form.address"
                                        type="text"
                                        class="block w-full"
                                        required
                                        autocomplete="street-address"
                                        placeholder="Zoek op straat, huisnummer, postcode of plaats"
                                        @input="handleAddressInput"
                                        @focus="handleAddressFocus"
                                        @blur="handleAddressBlur"
                                    />
                                    <div
                                        v-if="showAddressSuggestions && addressSuggestions.length"
                                        class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-base border border-default-medium bg-white shadow-lg"
                                    >
                                        <button
                                            v-for="item in addressSuggestions"
                                            :key="item.id"
                                            type="button"
                                            class="block w-full px-3 py-2 text-left text-sm text-heading hover:bg-neutral-secondary-medium"
                                            @mousedown.prevent="selectAddressSuggestion(item)"
                                        >
                                            <div class="font-medium">{{ item.label }}</div>
                                            <div v-if="item.postcode" class="text-xs text-body">{{ item.postcode }}</div>
                                        </button>
                                    </div>
                                </div>
                                <p v-if="isAddressLookupBusy" class="mt-2 text-xs text-body">
                                    BAG adressen laden...
                                </p>
                                <p
                                    v-else-if="addressLookupInfo"
                                    class="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800"
                                >
                                    {{ addressLookupInfo }}
                                </p>
                                <p v-else-if="addressLookupError" class="mt-2 text-xs text-red-600">
                                    {{ addressLookupError }}
                                </p>
                                <InputError class="mt-2" :message="form.errors.address" />
                                <InputError class="mt-2" :message="form.errors.bag_address_id" />
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

                        <input v-model="form.city" type="hidden" />
                        <input v-model="form.bag_address_id" type="hidden" />

                        <div v-if="isEnrichmentLoading" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            Adresgegevens worden verrijkt...
                        </div>
                        <div v-else-if="enrichmentError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ enrichmentError }}
                        </div>
                        <div
                            v-else-if="enrichmentData"
                            class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                        >
                            <div
                                v-if="enrichmentData?.diagnostics"
                                class="rounded-lg border border-gray-300 bg-white p-3"
                            >
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Debug status externe bronnen
                                </div>
                                <div class="mt-2 space-y-2">
                                    <div
                                        v-for="(diag, key) in enrichmentData.diagnostics"
                                        :key="key"
                                        class="flex items-start justify-between gap-3 rounded border border-gray-200 p-2"
                                    >
                                        <div class="text-sm text-gray-800">
                                            <div class="font-semibold">{{ key }}</div>
                                            <div class="text-xs text-gray-600">{{ diag?.detail || "-" }}</div>
                                        </div>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="diagnosticBadgeClass(diag?.status)"
                                        >
                                            {{ diag?.status || "unknown" }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold text-gray-900">Locatie-informatie</h3>
                                <a
                                    v-if="enrichmentData?.zoning?.omgevingsplan_url"
                                    :href="enrichmentData.zoning.omgevingsplan_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                                >
                                    Open omgevingsplangegevens
                                </a>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Geocode</div>
                                    <div class="mt-1 text-sm text-gray-800">
                                        {{ enrichmentData?.geocode?.lat ?? "-" }}, {{ enrichmentData?.geocode?.lng ?? "-" }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">
                                        Bron: {{ enrichmentData?.geocode?.source === "bag_geometry_rd" ? "BAG geometrie" : "Google geocoding" }}
                                    </div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Kadastrale aanduiding</div>
                                    <div class="mt-1 text-sm text-gray-800">
                                        {{ enrichmentData?.cadastre?.kadastrale_aanduiding ?? "-" }}
                                    </div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bouwjaar</div>
                                    <div class="mt-1 text-sm text-gray-800">{{ enrichmentData?.bag?.bouwjaar ?? "-" }}</div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Gebruiksfunctie</div>
                                    <div class="mt-1 text-sm text-gray-800">{{ enrichmentData?.bag?.gebruiksfunctie ?? "-" }}</div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Oppervlakte</div>
                                    <div class="mt-1 text-sm text-gray-800">{{ enrichmentData?.bag?.oppervlakte_m2 ?? "-" }} m2</div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Perceelsgrootte</div>
                                    <div class="mt-1 text-sm text-gray-800">{{ enrichmentData?.cadastre?.perceelsgrootte_m2 ?? "-" }} m2</div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Energielabel</div>
                                    <div class="mt-1 text-sm text-gray-800">{{ enrichmentData?.bag?.energielabel ?? "Niet automatisch beschikbaar" }}</div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">WOZ-waarde</div>
                                    <div class="mt-1 text-sm text-gray-800">{{ enrichmentData?.woz?.toelichting ?? "-" }}</div>
                                    <a
                                        v-if="enrichmentData?.woz?.waardeloket_url"
                                        :href="enrichmentData.woz.waardeloket_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-800"
                                    >
                                        Open WOZ Waardeloket
                                    </a>
                                </div>
                            </div>

                            <div class="rounded bg-white p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Kaartlagen
                                    </div>
                                    <select
                                        v-model="mapMode"
                                        class="rounded-base border border-default-medium bg-white px-2 py-1 text-xs text-heading"
                                        @change="updateMapMode"
                                    >
                                        <option value="kaart">Kaart</option>
                                        <option value="earth">Earth</option>
                                        <option value="kadaster">Kadaster</option>
                                        <option value="bodemkaart">Bodemkaart</option>
                                    </select>
                                </div>
                                <div
                                    v-if="enrichmentData?.map?.google_maps_api_key_available"
                                    ref="mapContainer"
                                    class="mt-3 h-72 w-full overflow-hidden rounded-lg border border-gray-200"
                                ></div>
                                <div v-else class="mt-3 text-sm text-gray-600">
                                    Google Maps API-key ontbreekt; kaart kan niet geladen worden.
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bestemmingsplan / WKBP</div>
                                    <div class="mt-1 text-sm text-gray-700">{{ enrichmentData?.zoning?.toelichting }}</div>
                                    <div
                                        v-if="enrichmentData?.zoning?.planobjecten?.length"
                                        class="mt-3 space-y-2"
                                    >
                                        <div
                                            v-for="plan in enrichmentData.zoning.planobjecten"
                                            :key="plan.identificatie || plan.naam"
                                            class="rounded border border-gray-200 bg-gray-50 p-2"
                                        >
                                            <div class="text-sm font-semibold text-gray-900">
                                                {{ plan.naam || plan.identificatie || "Planobject" }}
                                            </div>
                                            <div class="mt-1 text-xs text-gray-700">
                                                {{ plan.typeplan || "Onbekend type" }} | {{ plan.planstatus || "Onbekende status" }}
                                            </div>
                                            <div class="mt-1 text-xs text-gray-700">
                                                {{ plan.naamoverheid || "Onbekende overheid" }}
                                            </div>
                                            <a
                                                v-if="plan.verwijzing_tekst_urls?.[0]"
                                                :href="plan.verwijzing_tekst_urls[0]"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-800"
                                            >
                                                Open plantekst
                                            </a>
                                            <a
                                                v-if="plan.verwijzing_vaststellingsbesluit_urls?.[0]"
                                                :href="plan.verwijzing_vaststellingsbesluit_urls[0]"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="ml-2 mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-800"
                                            >
                                                Open vaststellingsbesluit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Monumentenstatus</div>
                                    <div class="mt-1 text-sm text-gray-700">
                                        Rijksmonument: {{ enrichmentData?.heritage?.is_monument ? "Ja" : "Nee" }}
                                    </div>
                                    <div class="text-sm text-gray-700">
                                        Beschermd gezicht: {{ enrichmentData?.heritage?.beschermd_stads_dorpsgezicht ? "Ja" : "Nee" }}
                                    </div>
                                    <div
                                        v-if="enrichmentData?.heritage?.rijksmonumenten?.length"
                                        class="mt-2 space-y-2"
                                    >
                                        <div
                                            v-for="monument in enrichmentData.heritage.rijksmonumenten"
                                            :key="monument.resource || monument.nummer"
                                            class="rounded border border-gray-200 bg-gray-50 p-2"
                                        >
                                            <div class="text-sm font-semibold text-gray-900">
                                                {{ monument.naam || "Rijksmonument" }}
                                            </div>
                                            <div class="text-xs text-gray-700">
                                                Nummer: {{ monument.nummer || "-" }}
                                            </div>
                                            <a
                                                v-if="monument.detail_url"
                                                :href="monument.detail_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-800"
                                            >
                                                Open detail
                                            </a>
                                        </div>
                                    </div>
                                    <div
                                        v-if="enrichmentData?.heritage?.gezichten?.length"
                                        class="mt-2 space-y-2"
                                    >
                                        <div
                                            v-for="gezicht in enrichmentData.heritage.gezichten"
                                            :key="gezicht.resource || gezicht.nummer"
                                            class="rounded border border-gray-200 bg-gray-50 p-2"
                                        >
                                            <div class="text-sm font-semibold text-gray-900">
                                                {{ gezicht.naam || "Beschermd gezicht" }}
                                            </div>
                                            <div class="text-xs text-gray-700">
                                                Gezichtsnummer: {{ gezicht.nummer || "-" }}
                                            </div>
                                            <div class="text-xs text-gray-700">
                                                Registratiedatum: {{ gezicht.registratiedatum || "-" }}
                                            </div>
                                            <a
                                                v-if="gezicht.detail_url"
                                                :href="gezicht.detail_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-800"
                                            >
                                                Open detail
                                            </a>
                                        </div>
                                    </div>
                                    <a
                                        v-if="enrichmentData?.heritage?.monumentenregister_url"
                                        :href="enrichmentData.heritage.monumentenregister_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-2 inline-block text-sm font-semibold text-blue-700 hover:text-blue-800"
                                    >
                                        Open monumentenregister
                                    </a>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bodemvervuiling</div>
                                    <a
                                        v-if="enrichmentData?.soil?.bodemloket_url"
                                        :href="enrichmentData.soil.bodemloket_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 inline-block text-sm font-semibold text-blue-700 hover:text-blue-800"
                                    >
                                        Open Bodemloket
                                    </a>
                                </div>
                                <div class="rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bereikbaarheid</div>
                                    <div class="mt-1 text-sm text-gray-700">
                                        OV/auto/knooppunten via open bronnen (PDOK/CBS) zijn als bronlinks toegevoegd.
                                    </div>
                                </div>
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
                            <div class="mt-1 flex w-full flex-col gap-2 sm:flex-row">
                                <div class="flex w-full rounded-base shadow-xs">
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
                                class="mt-1 block w-full resize-none rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand placeholder:text-body"
                                @input="autoResizeNotes"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>

                        <div class="space-y-2">
                            <InputLabel for="property_images" value="Afbeeldingen *" />
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
                                id="property_images"
                                name="images"
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImagesChange"
                            />
                            <InputError class="mt-2" :message="form.errors.images || form.errors['images.*']" />
                            <InputError class="mt-2" :message="form.errors.remote_images || form.errors['remote_images.*']" />
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
                            <PrimaryButton type="submit" :disabled="form.processing">
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
                            class="block w-full rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand placeholder:text-body"
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
                                <span class="rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-1.5 text-xs font-semibold text-heading hover:bg-neutral-secondary-medium/80">
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


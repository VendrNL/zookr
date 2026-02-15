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
import { computed, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
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
const mapStreetView = ref(null);
const mapApiLoading = ref(false);
const mapLoadError = ref("");
const mapRenderRetryTimer = ref(null);
const mapFeatureInfoItems = ref([]);
const mapFeatureInfoLoading = ref(false);
const mapFeatureInfoMessage = ref("");
const mapFeatureInfoNotice = ref("");
const mapVisibilityObserver = ref(null);
const mapShouldRender = ref(false);

const enrichmentSequenceRunId = ref(0);
const enrichmentProgress = ref([]);
const enrichmentSourceDefinitions = [
    { key: "bag", label: "Adresgegevens" },
    { key: "geocode", label: "Geocode" },
    { key: "pdok_cadastre", label: "Kadaster" },
    { key: "pdok_zoning", label: "Bestemmingsplannen" },
    { key: "rce_heritage", label: "Monumentenregister" },
    { key: "osm_poi", label: "Voorzieningen" },
    { key: "cbs_85830", label: "Buurtinformatie" },
    { key: "rivm_air_quality", label: "Milieu" },
];

const geocodeOverallStatus = (diagnostics) => {
    const google = diagnostics?.google_geocode?.status;
    const fallback = diagnostics?.geocode_fallback_bag?.status;
    if (google === "ok" || fallback === "ok") return "ok";
    if (google === "skipped" || fallback === "skipped") return "skipped";
    if (google === "missing_key" && fallback === "pending") return "pending";
    if (fallback && fallback !== "pending") return fallback;
    return google ?? "pending";
};

const enrichmentStatusMap = (diagnostics = {}) => ({
    bag: diagnostics?.bag?.status ?? "pending",
    geocode: geocodeOverallStatus(diagnostics),
    pdok_cadastre: diagnostics?.pdok_cadastre?.status ?? "pending",
    pdok_zoning: diagnostics?.pdok_zoning?.status ?? "pending",
    rce_heritage: diagnostics?.rce_heritage?.status ?? "pending",
    osm_poi: diagnostics?.osm_poi?.status ?? "pending",
    cbs_85830: diagnostics?.cbs_85830?.status ?? "pending",
    rivm_air_quality: diagnostics?.rivm_air_quality?.status ?? "pending",
});

const normalizeProgressStatus = (status) =>
    [ "ok", "skipped", "no_data" ].includes(String(status ?? "").toLowerCase())
        ? "done"
        : (String(status ?? "").toLowerCase() === "pending" ? "pending" : "failed");

const initializeEnrichmentProgress = () => {
    enrichmentProgress.value = enrichmentSourceDefinitions.map((item) => ({
        key: item.key,
        label: item.label,
        status: "pending",
        detail: "",
    }));
};

const updateEnrichmentProgressFromDiagnostics = (diagnostics = {}) => {
    const statusMap = enrichmentStatusMap(diagnostics);
    enrichmentProgress.value = enrichmentProgress.value.map((row) => {
        if (!(row.key in statusMap)) {
            return row;
        }
        const nextStatus = normalizeProgressStatus(statusMap[row.key]);
        if (nextStatus === "pending" && row.status !== "pending") {
            return row;
        }
        return {
            ...row,
            status: nextStatus,
            detail: nextStatus === "done" ? "" : row.detail,
        };
    });
};

const stageToProgressKeys = (stage) => {
    if (stage === "zoning") return [ "pdok_zoning" ];
    if (stage === "heritage") return [ "rce_heritage" ];
    if (stage === "accessibility") return [ "osm_poi", "cbs_85830" ];
    if (stage === "milieu") return [ "rivm_air_quality" ];
    if (stage === "core") return [ "bag", "geocode", "pdok_cadastre" ];
    return [];
};

const markProgressFailedByStage = (stage) => {
    const keys = stageToProgressKeys(stage);
    if (!keys.length) {
        return;
    }
    enrichmentProgress.value = enrichmentProgress.value.map((row) =>
        keys.includes(row.key) && row.status === "pending"
            ? { ...row, status: "failed" }
            : row
    );
};

const setProgressDetailByStage = (stage, detail) => {
    const keys = stageToProgressKeys(stage);
    const message = String(detail ?? "").trim();
    if (!keys.length || message === "") {
        return;
    }

    enrichmentProgress.value = enrichmentProgress.value.map((row) =>
        keys.includes(row.key)
            ? { ...row, detail: message }
            : row
    );
};

const isDiagnosticErrorStatus = (status) => {
    const normalized = String(status ?? "").trim().toLowerCase();
    return ![ "ok", "skipped", "pending", "no_data" ].includes(normalized);
};

const errorDiagnostics = computed(() => {
    const diagnostics = enrichmentData.value?.diagnostics;
    if (!diagnostics || typeof diagnostics !== "object") {
        return [];
    }

    return Object.entries(diagnostics)
        .filter(([, diag]) => isDiagnosticErrorStatus(diag?.status))
        .map(([ key, diag ]) => ({ key, diag }));
});

const energyLabelOrder = [ "A++++", "A+++", "A++", "A+", "A", "B", "C", "D", "E", "F", "G" ];

const energyLabelColorMap = {
    "A++++": "#1d8f45",
    "A+++": "#1f9546",
    "A++": "#229b47",
    "A+": "#24a24a",
    A: "#2fab4d",
    B: "#49ad49",
    C: "#9cb83b",
    D: "#dfdb66",
    E: "#e2c543",
    F: "#ef8a1f",
    G: "#e1472c",
};

const normalizeEnergyLabel = (value) => {
    if (typeof value !== "string") return "";
    const normalized = value.trim().toUpperCase().replace(/\s+/g, "");
    if (normalized === "A4+") return "A++++";
    if (normalized === "A3+") return "A+++";
    if (normalized === "A2+") return "A++";
    if (normalized === "A1+") return "A+";
    return normalized;
};

const selectedEnergyLabel = computed(() =>
    normalizeEnergyLabel(enrichmentData.value?.bag?.energielabel ?? "")
);

const hasGraphicalEnergyLabel = computed(() =>
    energyLabelOrder.includes(selectedEnergyLabel.value)
);

const pandIds = computed(() => {
    const values = enrichmentData.value?.bag?.pand_identificaties;
    if (Array.isArray(values)) {
        return values
            .map((value) => String(value ?? "").trim())
            .filter((value) => value !== "");
    }

    const single = String(values ?? "").trim();
    return single !== "" ? [single] : [];
});

const pandIdViewerUrl = (pandId) =>
    `https://bagviewer.kadaster.nl/?objectId=${encodeURIComponent(String(pandId ?? "").trim())}`;

const geocodeMapsLink = computed(() => {
    const lat = Number.parseFloat(String(enrichmentData.value?.geocode?.lat ?? ""));
    const lng = Number.parseFloat(String(enrichmentData.value?.geocode?.lng ?? ""));
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lng}`)}`;
});

const buurtcodeInfoLink = computed(() => {
    const code = String(enrichmentData.value?.accessibility?.buurtcode ?? "").trim().toUpperCase();
    if (!code) {
        return null;
    }
    return `https://allecijfers.nl/zoeken/?q=${encodeURIComponent(code)}`;
});

const bestemmingSourceLink = computed(() =>
    {
        const aanduiding = String(enrichmentData.value?.cadastre?.kadastrale_aanduiding ?? "").trim();
        if (aanduiding) {
            return `https://omgevingswet.overheid.nl/regels-op-de-kaart/zoeken/locatie?locatie=${encodeURIComponent(`Perceel ${aanduiding}`)}`;
        }

        return enrichmentData.value?.zoning?.omgevingsplan_url ?? null;
    }
);

const monumentStatusRijksmonumentLink = computed(() =>
    enrichmentData.value?.heritage?.rijksmonumenten?.[0]?.detail_url
    ?? enrichmentData.value?.heritage?.monumentenregister_url
    ?? null
);

const monumentStatusRijksmonumentNummer = computed(() =>
    enrichmentData.value?.heritage?.rijksmonumenten?.[0]?.nummer ?? null
);

const monumentStatusGemeentelijkLink = computed(() =>
    enrichmentData.value?.heritage?.gemeentelijke_monumenten?.[0]?.detail_url
    ?? enrichmentData.value?.heritage?.monumentenregister_url
    ?? null
);

const monumentStatusGemeentelijkLabel = computed(() => {
    const item = enrichmentData.value?.heritage?.gemeentelijke_monumenten?.[0] ?? null;
    if (!item) return null;
    return item?.nummer ?? item?.naam ?? null;
});

const monumentStatusGezichtLink = computed(() =>
    enrichmentData.value?.heritage?.gezichten?.[0]?.detail_url
    ?? enrichmentData.value?.heritage?.monumentenregister_url
    ?? null
);

const monumentStatusGezichtNaam = computed(() =>
    enrichmentData.value?.heritage?.gezichten?.[0]?.naam ?? null
);

const monumentStatusGezichtType = computed(() =>
    enrichmentData.value?.heritage?.gezichten?.[0]?.type ?? "Beschermd gezicht"
);

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
        mapLoadError.value = "";
        return true;
    }
    if (mapApiLoading.value) {
        return false;
    }

    mapApiLoading.value = true;
    try {
        const apiKey = enrichmentData.value?.map?.google_maps_api_key;
        if (!apiKey) {
            mapLoadError.value = "Google Maps API-key ontbreekt.";
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

        mapLoadError.value = "";
        return Boolean(globalThis.google?.maps);
    } catch {
        mapLoadError.value =
            "Google Maps script kon niet geladen worden. Controleer Maps JavaScript API en key-restricties.";
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

const tileToLonLatBbox = (x, y, z) => {
    const tiles = 2 ** z;
    const lonLeft = (x / tiles) * 360 - 180;
    const lonRight = ((x + 1) / tiles) * 360 - 180;
    const latTop = (Math.atan(Math.sinh(Math.PI * (1 - (2 * y) / tiles))) * 180) / Math.PI;
    const latBottom =
        (Math.atan(Math.sinh(Math.PI * (1 - (2 * (y + 1)) / tiles))) * 180) / Math.PI;
    return [ lonLeft, latBottom, lonRight, latTop ];
};

const createWmsOverlayCrs84 = (baseUrl, layerName) => {
    if (!baseUrl || !layerName || !globalThis.google?.maps) {
        return null;
    }

    return new globalThis.google.maps.ImageMapType({
        tileSize: new globalThis.google.maps.Size(256, 256),
        opacity: 0.9,
        getTileUrl: (coord, zoom) => {
            if (!coord) return "";
            const [ minLon, minLat, maxLon, maxLat ] = tileToLonLatBbox(
                coord.x,
                coord.y,
                zoom
            );
            const params = new URLSearchParams({
                service: "WMS",
                request: "GetMap",
                version: "1.3.0",
                layers: layerName,
                styles: "",
                crs: "CRS:84",
                bbox: `${minLon},${minLat},${maxLon},${maxLat}`,
                width: "256",
                height: "256",
                format: "image/png",
                transparent: "true",
            });
            return `${baseUrl}?${params.toString()}`;
        },
    });
};

const createWmtsOverlay = (baseUrl, layerName, matrixSet = "EPSG:3857", format = "image/png") => {
    if (!baseUrl || !layerName || !globalThis.google?.maps) {
        return null;
    }

    return new globalThis.google.maps.ImageMapType({
        tileSize: new globalThis.google.maps.Size(256, 256),
        opacity: 1,
        getTileUrl: (coord, zoom) => {
            if (!coord) return "";
            if (zoom < 0 || zoom > 19) return "";
            const maxTile = 2 ** zoom;
            if (coord.y < 0 || coord.y >= maxTile) return "";
            const normalizedX = ((coord.x % maxTile) + maxTile) % maxTile;
            const tileMatrix = String(zoom).padStart(2, "0");

            const params = new URLSearchParams({
                SERVICE: "WMTS",
                REQUEST: "GetTile",
                VERSION: "1.0.0",
                LAYER: layerName,
                STYLE: "default",
                FORMAT: format,
                TILEMATRIXSET: matrixSet,
                TILEMATRIX: tileMatrix,
                TILEROW: String(coord.y),
                TILECOL: String(normalizedX),
            });
            return `${baseUrl}?${params.toString()}`;
        },
    });
};

const mapLegendContainerClass = computed(() =>
    [ "energielabels", "gebruiksfuncties", "bestemmingsplannen" ].includes(mapMode.value)
        ? "pointer-events-none absolute bottom-2 left-2 z-[5] max-h-[78%] max-w-[85%] overflow-auto rounded-md border border-gray-300 bg-white/95 p-3 shadow-sm"
        : "pointer-events-none absolute bottom-2 left-2 z-[5] max-w-[60%] rounded-md border border-gray-300 bg-white/95 p-2 shadow-sm"
);

const mapLegendImageClass = computed(() =>
    [ "energielabels", "gebruiksfuncties", "bestemmingsplannen" ].includes(mapMode.value)
        ? "max-h-[55vh] w-auto"
        : "max-h-24 w-auto"
);

const neutralOverlayBaseMapStyles = [
    { elementType: "labels", stylers: [{ visibility: "off" }] },
    { featureType: "poi", stylers: [{ visibility: "off" }] },
    { featureType: "transit", stylers: [{ visibility: "off" }] },
];
const legendImageErrors = ref({});

const buildWmsLegendItems = (baseUrl, layerNames) => {
    if (!baseUrl || !layerNames) {
        return [];
    }

    return String(layerNames)
        .split(",")
        .map((value) => value.trim())
        .filter((value) => value !== "")
        .map((layer) => {
            const params = new URLSearchParams({
                service: "WMS",
                request: "GetLegendGraphic",
                format: "image/png",
                layer,
            });

            return {
                layer,
                url: `${baseUrl}?${params.toString()}`,
            };
        });
};

const buildDirectLegendItem = (url, layer = "legend") => {
    if (!url) return [];
    return [ { layer, url } ];
};

const mapLegend = computed(() => {
    const mapConfig = enrichmentData.value?.map ?? {};

    if (mapMode.value === "kadaster") {
        return {
            title: "Legenda Kadaster",
            items: buildWmsLegendItems(
                mapConfig.kadastraal_wms_url,
                mapConfig.kadastraal_wms_layer || "Perceel,Label,KadastraleGrens"
            ),
        };
    }

    if (mapMode.value === "bodemverontreiniging") {
        return {
            title: "Legenda Bodemverontreiniging",
            items: buildWmsLegendItems(
                mapConfig.bodemverontreiniging_wms_url,
                mapConfig.bodemverontreiniging_wms_layer
            ),
        };
    }

    if (mapMode.value === "energielabels") {
        return {
            title: "Legenda Energielabels",
            items: buildWmsLegendItems(
                mapConfig.energielabel_wms_url,
                mapConfig.energielabel_wms_layer
            ),
        };
    }

    if (mapMode.value === "gebruiksfuncties") {
        return {
            title: "Legenda Gebruiksfuncties",
            items: buildWmsLegendItems(
                mapConfig.gebruiksfuncties_wms_url,
                mapConfig.gebruiksfuncties_wms_layer
            ),
        };
    }

    if (mapMode.value === "bestemmingsplannen") {
        return {
            title: "Legenda Bestemmingsplannen",
            items: buildDirectLegendItem(
                mapConfig.ruimtelijke_plannen_legend_url,
                mapConfig.ruimtelijke_plannen_wms_layer || "enkelbestemming"
            ),
        };
    }

    return {
        title: "",
        items: [],
    };
});

const visibleLegendItems = computed(() =>
    (mapLegend.value.items ?? []).filter(
        (item) => item?.url && !legendImageErrors.value[item.url]
    )
);

const layerLabel = (name) => String(name ?? "").replace(/_/g, " ");

const fallbackLegendItems = computed(() => {
    if (mapMode.value === "kadaster") {
        return [
            {
                label: "Perceelvlak",
                style: {
                    backgroundColor: "rgba(37, 99, 235, 0.10)",
                    border: "1px solid #2563eb",
                },
            },
            {
                label: "Kadastrale grens",
                style: {
                    backgroundColor: "transparent",
                    border: "2px solid #1d4ed8",
                },
            },
            {
                label: "Perceelnummer (label)",
                style: {
                    backgroundColor: "#f3f4f6",
                    border: "1px dashed #6b7280",
                },
            },
        ];
    }

    if (mapMode.value === "bodemverontreiniging") {
        return [
            {
                label: "Saneringsactiviteit",
                style: {
                    backgroundColor: "#e7f8ea",
                    border: "2px solid #22c55e",
                },
            },
            {
                label: "Voldoende onderzocht/gesaneerd",
                style: {
                    backgroundColor: "#f3e8ff",
                    border: "2px solid #a855f7",
                },
            },
            {
                label: "Onderzoek uitvoeren",
                style: {
                    backgroundColor: "#fff3e6",
                    border: "2px solid #f59e0b",
                },
            },
            {
                label: "Historie bekend",
                style: {
                    backgroundColor: "#e0f2fe",
                    border: "2px solid #06b6d4",
                },
            },
            {
                label: "Gegevens aanwezig, status onbekend",
                style: {
                    backgroundColor: "#e8edff",
                    border: "2px solid #4f46e5",
                },
            },
        ];
    }

    if (mapMode.value === "energielabels") {
        return [
            { label: "Geen label", style: { backgroundColor: "#f3f4f6", border: "1px solid #9ca3af" } },
            { label: "Klasse A+++++", style: { backgroundColor: "#14532d", border: "1px solid #14532d" } },
            { label: "Klasse A++++", style: { backgroundColor: "#166534", border: "1px solid #166534" } },
            { label: "Klasse A+++", style: { backgroundColor: "#15803d", border: "1px solid #15803d" } },
            { label: "Klasse A++", style: { backgroundColor: "#16a34a", border: "1px solid #16a34a" } },
            { label: "Klasse A+", style: { backgroundColor: "#22c55e", border: "1px solid #22c55e" } },
            { label: "Klasse A", style: { backgroundColor: "#65a30d", border: "1px solid #65a30d" } },
            { label: "Klasse B", style: { backgroundColor: "#a3e635", border: "1px solid #84cc16" } },
            { label: "Klasse C", style: { backgroundColor: "#facc15", border: "1px solid #eab308" } },
            { label: "Klasse D", style: { backgroundColor: "#f59e0b", border: "1px solid #d97706" } },
            { label: "Klasse E", style: { backgroundColor: "#fb923c", border: "1px solid #ea580c" } },
            { label: "Klasse F", style: { backgroundColor: "#f97316", border: "1px solid #ea580c" } },
            { label: "Klasse G", style: { backgroundColor: "#ef4444", border: "1px solid #dc2626" } },
        ];
    }

    if (mapMode.value === "bestemmingsplannen") {
        return [
            { label: "agrarisch", style: { backgroundColor: "#e8ebcd", border: "1px solid #94a370" } },
            { label: "agrarisch met waarden", style: { backgroundColor: "#c8d98c", border: "1px solid #8ca35f" } },
            { label: "bedrijf", style: { backgroundColor: "#a15bc9", border: "1px solid #7e3ba5" } },
            { label: "bedrijventerrein", style: { backgroundColor: "#a87cc8", border: "1px solid #7e5aa1" } },
            { label: "bos", style: { backgroundColor: "#4d8e3d", border: "1px solid #3c6f31" } },
            { label: "centrum", style: { backgroundColor: "#f2c39d", border: "1px solid #d59a70" } },
            { label: "cultuur en ontspanning", style: { backgroundColor: "#f45b86", border: "1px solid #c94166" } },
            { label: "detailhandel", style: { backgroundColor: "#ef9c7a", border: "1px solid #cb7c5f" } },
            { label: "dienstverlening", style: { backgroundColor: "#e090b8", border: "1px solid #ba6e95" } },
            { label: "gemengd", style: { backgroundColor: "#eeb887", border: "1px solid #cb9a6d" } },
            { label: "groen", style: { backgroundColor: "#56c861", border: "1px solid #3da34a" } },
            { label: "horeca", style: { backgroundColor: "#ff7f1a", border: "1px solid #d5670f" } },
            { label: "infrastructuur", style: { backgroundColor: "#cbcbcb", border: "1px solid #a7a7a7" } },
            { label: "kantoor", style: { backgroundColor: "#e0b8d3", border: "1px solid #b891ab" } },
            { label: "maatschappelijk", style: { backgroundColor: "#d8a678", border: "1px solid #b88861" } },
            { label: "natuur", style: { backgroundColor: "#8a9d79", border: "1px solid #6e7e61" } },
            { label: "recreatie", style: { backgroundColor: "#c7d43f", border: "1px solid #9faa2f" } },
            { label: "sport", style: { backgroundColor: "#9ac130", border: "1px solid #799a24" } },
            { label: "tuin", style: { backgroundColor: "#d5de63", border: "1px solid #b3bd50" } },
            { label: "verkeer", style: { backgroundColor: "#d9d9d9", border: "1px solid #b8b8b8" } },
            { label: "ontspanning en vermaak", style: { backgroundColor: "#ef4c93", border: "1px solid #c63b77" } },
            { label: "water", style: { backgroundColor: "#b8d6e8", border: "1px solid #8db5cd" } },
            { label: "wonen", style: { backgroundColor: "#f1ee23", border: "1px solid #cecb10" } },
            { label: "woongebied", style: { backgroundColor: "#e8e8a6", border: "1px solid #c4c47f" } },
            { label: "overig", style: { backgroundColor: "#e7e1e8", border: "1px solid #c6bfc8" } },
        ];
    }

    if (mapMode.value === "gebruiksfuncties") {
        const names = (mapLegend.value.items ?? []).map((item) => item.layer);
        return names.map((name) => ({
            label: layerLabel(name),
            style: {
                backgroundColor: "#eef2ff",
                border: "1px solid #6366f1",
            },
        }));
    }

    return [];
});

const handleLegendImageError = (url) => {
    legendImageErrors.value = {
        ...legendImageErrors.value,
        [url]: true,
    };
};

const clearMapFeatureInfo = () => {
    mapFeatureInfoItems.value = [];
    mapFeatureInfoLoading.value = false;
    mapFeatureInfoMessage.value = "";
    mapFeatureInfoNotice.value = "";
};

const fetchMapFeatureInfo = async (lat, lng) => {
    const interactiveModes = ["kadaster", "bodemverontreiniging", "energielabels", "gebruiksfuncties", "bestemmingsplannen"];
    if (!interactiveModes.includes(mapMode.value)) {
        clearMapFeatureInfo();
        return;
    }

    mapFeatureInfoLoading.value = true;
    mapFeatureInfoMessage.value = "";
    mapFeatureInfoNotice.value = "";
    mapFeatureInfoItems.value = [];

    try {
        const params = new URLSearchParams({
            mode: mapMode.value,
            lat: String(lat),
            lng: String(lng),
        });
        const response = await fetch(
            `${route("search-requests.properties.map-feature-info", props.item.id)}?${params.toString()}`,
            {
                method: "GET",
                headers: { Accept: "application/json" },
            }
        );
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            mapFeatureInfoMessage.value =
                payload?.message || "Kaartinformatie ophalen is mislukt.";
            return;
        }

        mapFeatureInfoNotice.value =
            typeof payload?.notice === "string" ? payload.notice : "";

        const items = Array.isArray(payload?.items) ? payload.items : [];
        mapFeatureInfoItems.value = items.filter(
            (item) =>
                item &&
                item.title &&
                (
                    (item.value !== null && item.value !== "") ||
                    (typeof item.url === "string" && item.url !== "")
                )
        );
        if (mapFeatureInfoItems.value.length === 0) {
            mapFeatureInfoMessage.value = "Geen aanvullende kaartinformatie op dit punt.";
        }
    } catch {
        mapFeatureInfoMessage.value = "Kaartinformatie ophalen is mislukt.";
    } finally {
        mapFeatureInfoLoading.value = false;
    }
};

const getEnrichmentCoordinates = () => {
    const markerData = enrichmentData.value?.map?.marker;
    const fallbackGeocode = enrichmentData.value?.geocode;
    const lat = Number.parseFloat(String(markerData?.lat ?? fallbackGeocode?.lat ?? ""));
    const lng = Number.parseFloat(String(markerData?.lng ?? fallbackGeocode?.lng ?? ""));
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }
    return { lat, lng };
};

const parseDistanceKm = (value) => {
    const parsed = Number.parseFloat(String(value ?? ""));
    return Number.isFinite(parsed) ? parsed : null;
};

const formatDistanceMeters = (valueKm) => {
    const km = parseDistanceKm(valueKm);
    if (km === null) return "-";
    const meters = Math.round(km * 1000);
    return `${new Intl.NumberFormat("nl-NL").format(meters)} m`;
};

const hasDistanceWithinFiveKm = (valueKm) => {
    const km = parseDistanceKm(valueKm);
    return km !== null && km <= 5;
};

const formatVoorzieningDistance = (valueKm) => {
    const km = parseDistanceKm(valueKm);
    if (km === null || km > 5) return "Niet binnen 5.000 m";
    return formatDistanceMeters(km);
};

const formatWalkingDurationMinutes = (valueKm) => {
    const km = parseDistanceKm(valueKm);
    if (km === null) return null;
    const minutes = Math.max(1, Math.round((km / 5) * 60));
    return `${minutes} min`;
};

const formatDrivingDurationMinutes = (valueKm, explicitMinutes = null) => {
    const explicit = Number.parseFloat(String(explicitMinutes ?? ""));
    if (Number.isFinite(explicit) && explicit > 0) {
        return `${Math.max(1, Math.round(explicit))} min`;
    }
    const km = parseDistanceKm(valueKm);
    if (km === null) return null;
    const minutes = Math.max(1, Math.round((km / 50) * 60));
    return `${minutes} min`;
};

const googleRouteUrl = (destination, travelMode = "walking", fallbackQuery = null) => {
    const origin = getEnrichmentCoordinates();
    const destinationLat = Number.parseFloat(String(destination?.lat ?? ""));
    const destinationLng = Number.parseFloat(String(destination?.lng ?? ""));
    if (!origin) {
        return null;
    }

    const mode = [ "walking", "driving" ].includes(travelMode) ? travelMode : "walking";
    const originParam = `${origin.lat},${origin.lng}`;
    let destinationParam = null;
    if (Number.isFinite(destinationLat) && Number.isFinite(destinationLng)) {
        destinationParam = `${destinationLat},${destinationLng}`;
    } else {
        const query = String(
            fallbackQuery
            ?? destination?.query
            ?? destination?.naam
            ?? ""
        ).trim();
        if (query === "") {
            return null;
        }
        destinationParam = query;
    }

    return `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(originParam)}&destination=${encodeURIComponent(destinationParam)}&travelmode=${mode}`;
};

const googleWalkingRouteUrl = (destination, fallbackQuery = null) => googleRouteUrl(destination, "walking", fallbackQuery);
const googleDrivingRouteUrl = (destination, fallbackQuery = null) => googleRouteUrl(destination, "driving", fallbackQuery);

const milieuInfoLinks = {
    fijnstof: "https://www.atlasleefomgeving.nl/thema/schone-lucht/fijnstof",
    stikstofdioxide: "https://www.atlasleefomgeving.nl/thema/schone-lucht/stikstofdioxide",
    geluid: "https://www.atlasleefomgeving.nl/thema/geluid",
    zomerhitte: "https://www.atlasnatuurlijkkapitaal.nl/stedelijk-hitte-eiland-effect-uhi-in-nederland",
    overstroming: "https://www.atlasleefomgeving.nl/thema/klimaatverandering/overstroming",
    gevaarlijke_stoffen: "https://www.atlasleefomgeving.nl/thema/veilige-omgeving/externe-veiligheid",
};

const toMilieuNumber = (value) => {
    const num = Number.parseFloat(String(value ?? "").replace(",", "."));
    return Number.isFinite(num) ? num : null;
};

const formatMilieuValue = (value, unit = "") => {
    if (value === null || value === undefined || String(value).trim() === "") return "-";
    const num = toMilieuNumber(value);
    if (num === null) return String(value);
    const rounded = Number.isInteger(num) ? String(num) : String(Math.round(num * 100) / 100);
    return unit ? `${rounded} ${unit}` : rounded;
};

const formatMilieuDisplayValue = (kind, value) => {
    if (value === null || value === undefined || String(value).trim() === "") return "-";
    const num = toMilieuNumber(value);

    if (kind === "fijnstof") {
        if (num === null) return String(value);
        const rounded = Number.isInteger(num) ? String(num) : String(Math.round(num * 10) / 10);
        return `${rounded} μg PM2,5 / m3`;
    }

    if (kind === "stikstofdioxide") {
        if (num === null) return String(value);
        const rounded = Number.isInteger(num) ? String(num) : String(Math.round(num * 10) / 10);
        return `${rounded} μg NO2 / m3`;
    }

    if (kind === "geluid") {
        if (num === null) return String(value);
        const rounded = Number.isInteger(num) ? String(num) : String(Math.round(num * 10) / 10);
        return `${rounded} dB`;
    }

    if (kind === "zomerhitte") {
        if (num === null) return String(value);
        const rounded = Number.isInteger(num) ? String(num) : String(Math.round(num * 10) / 10);
        const signed = num > 0 ? `+ ${rounded}` : rounded;
        return `${signed} °C t.o.v. landelijk gebied`;
    }

    return String(value);
};

const milieuSmileyByLevel = {
    donkergroen: {
        icon: "/smileys/smiley-donkergroen.svg",
    },
    lichtgroen: {
        icon: "/smileys/smiley-lichtgroen.svg",
    },
    geel: {
        icon: "/smileys/smiley-geel.svg",
    },
    oranje: {
        icon: "/smileys/smiley-oranje.svg",
    },
    rood: {
        icon: "/smileys/smiley-rood.svg",
    },
};

const milieuHelperItemsByKind = {
    fijnstof: [
        { level: "lichtgroen", text: "De fijnstofconcentratie is onder de WHO advieswaarde (5 ug/ m3)." },
        { level: "geel", text: "De fijnstofconcentratie is tussen de WHO advieswaarde (5 ug/ m3) en WHO Interim target 4 (10 ug/ m3)." },
        { level: "oranje", text: "De fijnstofconcentratie is tussen WHO Interim target 4 (10 ug/ m3) en WHO Interim target 2 (25 ug/ m3)." },
        { level: "rood", text: "De fijnstofconcentratie is boven WHO Interim target 2 (25 ug/ m3)." },
    ],
    stikstofdioxide: [
        { level: "lichtgroen", text: "Onder WHO advieswaarde (10 ug/ m3)." },
        { level: "geel", text: "Tussen WHO advieswaarde (10 ug/ m3) en WHO Interim target 3 (20 ug/ m3)." },
        { level: "oranje", text: "Tussen WHO Interim target 3 (20 ug/ m3) en WHO Interim target 2 (30 ug/ m3)." },
        { level: "rood", text: "Boven WHO Interim target 2 (30 ug/ m3)." },
    ],
    geluid: [
        { level: "donkergroen", text: "De geluidsbelasting is minder dan 46 decibel (dB)." },
        { level: "lichtgroen", text: "De geluidsbelasting is tussen 46 dB en 50 dB." },
        { level: "geel", text: "De geluidsbelasting is tussen 51 dB en 55 dB." },
        { level: "oranje", text: "De geluidsbelasting is tussen 56dB en 60 dB." },
        { level: "rood", text: "De geluidsbelasting is meer dan 60 dB." },
    ],
    zomerhitte: [
        { level: "donkergroen", text: "Zeer goed, de temperatuur is minder dan 0,5 °C hoger dan in omliggende landelijke gebieden." },
        { level: "lichtgroen", text: "Goed, de temperatuur is tussen de 0,5 en 1 °C hoger dan in omliggende landelijke gebieden." },
        { level: "geel", text: "Redelijk, de temperatuur is tussen de 1 en 1,5 °C hoger dan in omliggende landelijke gebieden." },
        { level: "oranje", text: "Matig, de temperatuur is tussen de 1,5 en 2 °C hoger dan in omliggende landelijke gebieden." },
        { level: "rood", text: "Slecht, de temperatuur is 2 °C of meer hoger dan in omliggende landelijke gebieden." },
    ],
    overstroming: [
        { level: "donkergroen", text: "Overstroomt niet of is oppervlaktewater." },
        { level: "lichtgroen", text: "Zeer kleine kans." },
        { level: "geel", text: "Kleine kans." },
        { level: "oranje", text: "Middelgrote kans." },
        { level: "rood", text: "Grote kans." },
    ],
    gevaarlijke_stoffen: [
        { level: "donkergroen", text: "Geen activiteiten bekend binnen 1 km." },
        { level: "geel", text: "Mogelijke of beperkte activiteiten in de omgeving." },
        { level: "rood", text: "Meerdere of relevante risicobronnen binnen 1 km." },
    ],
};

const MilieuSmileyIcon = (props, { attrs }) => {
    const icon = props.icon ?? milieuSmileyByLevel.geel.icon;
    const iconClass = attrs?.class ?? "h-4 w-4";

    return h("img", {
        class: iconClass,
        src: icon,
        alt: "",
        draggable: "false",
    });
};
MilieuSmileyIcon.props = {
    icon: String,
};

const milieuAssessment = (kind, rawValue) => {
    const num = toMilieuNumber(rawValue);
    const text = String(rawValue ?? "").toLowerCase();
    let level = "geel";

    if (kind === "fijnstof") {
        if (num !== null) {
            level = num <= 5 ? "lichtgroen" : num <= 10 ? "geel" : num <= 25 ? "oranje" : "rood";
        }
    } else if (kind === "stikstofdioxide") {
        if (num !== null) {
            level = num <= 10 ? "lichtgroen" : num <= 20 ? "geel" : num <= 30 ? "oranje" : "rood";
        }
    } else if (kind === "geluid") {
        if (num !== null) {
            level = num < 46 ? "donkergroen" : num <= 50 ? "lichtgroen" : num <= 55 ? "geel" : num <= 60 ? "oranje" : "rood";
        }
    } else if (kind === "zomerhitte") {
        if (num !== null) {
            level = num < 0.5 ? "donkergroen" : num <= 1 ? "lichtgroen" : num <= 1.5 ? "geel" : num <= 2 ? "oranje" : "rood";
        }
    } else if (kind === "overstroming") {
        if (text.includes("overstroomt niet") || text.includes("oppervlaktewater")) {
            level = "donkergroen";
        } else if (text.includes("zeer kleine kans")) {
            level = "lichtgroen";
        } else if (text.includes("middelgrote kans")) {
            level = "oranje";
        } else if (text.includes("grote kans")) {
            level = "rood";
        } else if (text.includes("kleine kans")) {
            level = "geel";
        } else if (num !== null) {
            level = num <= 2 ? "donkergroen" : num <= 3 ? "lichtgroen" : num <= 4 ? "geel" : num <= 5 ? "oranje" : "rood";
        }
    } else if (kind === "gevaarlijke_stoffen") {
        if (text.includes("geen activiteiten bekend") || text === "geen" || text.includes("geen")) {
            level = "donkergroen";
        } else if (text === "-" || text === "") {
            level = "geel";
        } else if (text.includes("beperkt") || text.includes("laag") || text.includes("mogelijk") || text.includes("enkele")) {
            level = "geel";
        } else if (text.includes("meerdere") || text.includes("relevant")) {
            level = "rood";
        } else if (num !== null) {
            level = num <= 1 ? "donkergroen" : num <= 3 ? "geel" : "rood";
        } else {
            level = "rood";
        }
    }

    const smiley = milieuSmileyByLevel[level] ?? milieuSmileyByLevel.geel;
    const helperItems = milieuHelperItemsByKind[kind] ?? [];

    return {
        smileyLevel: level,
        smileyIcon: smiley.icon,
        helperItems,
        link: milieuInfoLinks[kind] ?? "https://www.atlasleefomgeving.nl/",
    };
};

const calculateHeading = (from, to) => {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const toDeg = (rad) => (rad * 180) / Math.PI;
    const lat1 = toRad(from.lat);
    const lon1 = toRad(from.lng);
    const lat2 = toRad(to.lat);
    const lon2 = toRad(to.lng);
    const dLon = lon2 - lon1;
    const y = Math.sin(dLon) * Math.cos(lat2);
    const x =
        Math.cos(lat1) * Math.sin(lat2) -
        Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
    return (toDeg(Math.atan2(y, x)) + 360) % 360;
};

const showStreetViewForLocation = (target) => {
    if (!mapContainer.value || !mapInstance.value || !globalThis.google?.maps) {
        return;
    }

    if (!mapStreetView.value) {
        mapStreetView.value = mapInstance.value.getStreetView();
        mapStreetView.value.setOptions({
            position: target,
            pov: { heading: 0, pitch: 0 },
            zoom: 1,
            addressControl: false,
        });
    }

    const service = new globalThis.google.maps.StreetViewService();
    service.getPanorama({ location: target, radius: 250 }, (data, status) => {
        const okStatus = globalThis.google?.maps?.StreetViewStatus?.OK;
        if (status === okStatus && data?.location?.latLng && mapStreetView.value) {
            const panoLatLng = data.location.latLng;
            const panoPos = {
                lat: panoLatLng.lat(),
                lng: panoLatLng.lng(),
            };
            mapStreetView.value.setPano(data.location.pano);
            mapStreetView.value.setPov({
                heading: calculateHeading(panoPos, target),
                pitch: 0,
            });
            mapStreetView.value.setZoom(1);
            mapLoadError.value = "";
        } else if (mapStreetView.value) {
            mapStreetView.value.setPosition(target);
            mapStreetView.value.setPov({ heading: 0, pitch: 0 });
            mapStreetView.value.setZoom(1);
            mapLoadError.value =
                status === globalThis.google?.maps?.StreetViewStatus?.ZERO_RESULTS
                    ? "Geen Street View-panorama gevonden dicht bij deze locatie."
                    : `Street View kon niet geladen worden (status: ${String(status)}).`;
        }

        if (mapStreetView.value) {
            mapStreetView.value.setVisible(true);
        }
    });
};

const updateMapMode = () => {
    if (!mapInstance.value) return;

    const mapConfig = enrichmentData.value?.map ?? {};
    const coords = getEnrichmentCoordinates();
    mapLoadError.value = "";
    mapInstance.value.overlayMapTypes.clear();
    mapInstance.value.setOptions({ styles: null });
    if (mapStreetView.value) {
        mapStreetView.value.setVisible(false);
    }
    clearMapFeatureInfo();

    if (mapMode.value === "streetview") {
        if (!coords) {
            mapLoadError.value = "Geen coördinaten beschikbaar om Street View te tonen.";
            return;
        }
        mapInstance.value.setMapTypeId("roadmap");
        showStreetViewForLocation(coords);
        return;
    }

    if (mapMode.value === "earth") {
        mapInstance.value.setMapTypeId("satellite");
        return;
    }

    mapInstance.value.setMapTypeId("roadmap");

    if (mapMode.value === "kadaster") {
        mapInstance.value.setOptions({
            styles: neutralOverlayBaseMapStyles,
        });

        mapOverlay.value = createWmsOverlay(
            mapConfig.kadastraal_wms_url,
            mapConfig.kadastraal_wms_layer || "Perceel,Label,KadastraleGrens"
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        }
    }

    if (mapMode.value === "bodemverontreiniging") {
        mapInstance.value.setOptions({
            styles: neutralOverlayBaseMapStyles,
        });
        mapOverlay.value = createWmsOverlay(
            mapConfig.bodemverontreiniging_wms_url,
            mapConfig.bodemverontreiniging_wms_layer
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        } else {
            mapLoadError.value =
                "Bodemverontreiniging-laag niet beschikbaar. Stel PDOK_BODEMVERONTREINIGING_WMS_URL en PDOK_BODEMVERONTREINIGING_WMS_LAYER in.";
        }
    }

    if (mapMode.value === "energielabels") {
        mapInstance.value.setOptions({
            styles: neutralOverlayBaseMapStyles,
        });

        const wmtsOverlay = createWmtsOverlay(
            mapConfig.wegenkaart_grijs_wmts_url,
            mapConfig.wegenkaart_grijs_wmts_layer || "grijs",
            mapConfig.wegenkaart_grijs_wmts_matrixset || "EPSG:3857"
        );
        if (wmtsOverlay) {
            mapInstance.value.overlayMapTypes.push(wmtsOverlay);
        }

        mapOverlay.value = createWmsOverlay(
            mapConfig.energielabel_wms_url,
            mapConfig.energielabel_wms_layer
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        } else {
            mapLoadError.value =
                "Energielabel-laag niet beschikbaar. Stel PDOK_ENERGIELABEL_WMS_URL en PDOK_ENERGIELABEL_WMS_LAYER in.";
        }
    }

    if (mapMode.value === "gebruiksfuncties") {
        mapInstance.value.setOptions({
            styles: neutralOverlayBaseMapStyles,
        });

        const wmtsOverlay = createWmtsOverlay(
            mapConfig.wegenkaart_grijs_wmts_url,
            mapConfig.wegenkaart_grijs_wmts_layer || "grijs",
            mapConfig.wegenkaart_grijs_wmts_matrixset || "EPSG:3857"
        );
        if (wmtsOverlay) {
            mapInstance.value.overlayMapTypes.push(wmtsOverlay);
        }

        mapOverlay.value = createWmsOverlay(
            mapConfig.gebruiksfuncties_wms_url,
            mapConfig.gebruiksfuncties_wms_layer
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        } else {
            mapLoadError.value =
                "Gebruiksfuncties-laag niet beschikbaar. Stel PDOK_GEBRUIKSFUNCTIES_WMS_URL en PDOK_GEBRUIKSFUNCTIES_WMS_LAYER in.";
        }
    }

    if (mapMode.value === "bestemmingsplannen") {
        mapInstance.value.setOptions({
            styles: neutralOverlayBaseMapStyles,
        });

        const wmtsOverlay = createWmtsOverlay(
            mapConfig.wegenkaart_grijs_wmts_url,
            mapConfig.wegenkaart_grijs_wmts_layer || "grijs",
            mapConfig.wegenkaart_grijs_wmts_matrixset || "EPSG:3857"
        );
        if (wmtsOverlay) {
            mapInstance.value.overlayMapTypes.push(wmtsOverlay);
        }

        mapOverlay.value = createWmsOverlayCrs84(
            mapConfig.ruimtelijke_plannen_wms_url,
            mapConfig.ruimtelijke_plannen_wms_layer || "enkelbestemming"
        );
        if (mapOverlay.value) {
            mapInstance.value.overlayMapTypes.push(mapOverlay.value);
        } else {
            mapLoadError.value =
                "Bestemmingsplannen-laag niet beschikbaar. Stel PDOK_RUIMTELIJKE_PLANNEN_WMS_URL en PDOK_RUIMTELIJKE_PLANNEN_WMS_LAYER in.";
        }
    }
};

const renderMap = async () => {
    const mapConfig = enrichmentData.value?.map ?? {};
    if (!mapConfig.google_maps_api_key_available) {
        mapLoadError.value = "Google Maps API-key ontbreekt in de backend-config.";
        return;
    }

    if (!mapContainer.value) {
        if (mapRenderRetryTimer.value) {
            clearTimeout(mapRenderRetryTimer.value);
        }
        mapRenderRetryTimer.value = setTimeout(() => {
            renderMap();
        }, 120);
        return;
    }

    const coords = getEnrichmentCoordinates();
    if (!coords) {
        mapLoadError.value = "Geen coördinaten beschikbaar om de kaart te tonen.";
        return;
    }

    const ok = await ensureGoogleMapsApi();
    if (!ok || !globalThis.google?.maps) {
        return;
    }
    mapLoadError.value = "";

    const center = coords;
    const currentMapDiv =
        mapInstance.value && typeof mapInstance.value.getDiv === "function"
            ? mapInstance.value.getDiv()
            : null;
    const needsNewMapInstance = !mapInstance.value || currentMapDiv !== mapContainer.value;

    if (needsNewMapInstance) {
        mapInstance.value = new globalThis.google.maps.Map(mapContainer.value, {
            center,
            zoom: 19,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
        });
        mapStreetView.value = null;
        mapOverlay.value = null;
        mapInstance.value.addListener("click", (event) => {
            const lat = event?.latLng?.lat?.();
            const lng = event?.latLng?.lng?.();
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }
            fetchMapFeatureInfo(lat, lng);
        });
        mapMarker.value = new globalThis.google.maps.Marker({
            map: mapInstance.value,
            position: center,
            title: form.address || "Objectlocatie",
        });
    } else {
        mapInstance.value.setCenter(center);
        mapInstance.value.setZoom(19);
        mapMarker.value?.setPosition(center);
    }

    updateMapMode();
};

const disconnectMapObserver = () => {
    if (mapVisibilityObserver.value) {
        mapVisibilityObserver.value.disconnect();
        mapVisibilityObserver.value = null;
    }
};

const scheduleLazyMapRender = async () => {
    if (!mapShouldRender.value) {
        return;
    }
    await nextTick();
    await renderMap();
};

const setupLazyMapObserver = async () => {
    disconnectMapObserver();

    const hasApiKey = Boolean(enrichmentData.value?.map?.google_maps_api_key_available);
    if (!hasApiKey) {
        mapShouldRender.value = false;
        return;
    }

    await nextTick();
    if (!mapContainer.value) {
        return;
    }

    mapVisibilityObserver.value = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }
                mapShouldRender.value = true;
                scheduleLazyMapRender();
                disconnectMapObserver();
                break;
            }
        },
        {
            root: null,
            threshold: 0.12,
        }
    );

    mapVisibilityObserver.value.observe(mapContainer.value);
};

const mergeEnrichmentPayload = (payload = {}) => {
    const current = enrichmentData.value ?? {};
    const currentDiagnostics = current?.diagnostics ?? {};
    const incomingDiagnostics = payload?.diagnostics ?? {};
    const mergedDiagnostics = { ...currentDiagnostics };

    for (const [key, diag] of Object.entries(incomingDiagnostics)) {
        if (!diag || typeof diag !== "object") {
            continue;
        }

        const incomingStatus = String(diag.status ?? "").toLowerCase();
        const existing = mergedDiagnostics[key];
        const existingStatus = String(existing?.status ?? "").toLowerCase();

        if (incomingStatus === "pending" && existingStatus && existingStatus !== "pending") {
            continue;
        }

        mergedDiagnostics[key] = {
            ...(existing ?? {}),
            ...diag,
        };
    }

    enrichmentData.value = {
        ...current,
        ...payload,
        diagnostics: mergedDiagnostics,
        bag: {
            ...(current?.bag ?? {}),
            ...(payload?.bag ?? {}),
        },
        map: {
            ...(current?.map ?? {}),
            ...(payload?.map ?? {}),
        },
    };
};

const buildEnrichmentUrl = (params) =>
    `${route("search-requests.properties.address-enrichment", props.item.id)}?${new URLSearchParams(params).toString()}`;

const fetchEnrichmentStage = async ({ stage, bagAddressId, contextKey }) => {
    const params = { stage };
    if (bagAddressId) {
        params.bag_address_id = bagAddressId;
    }
    if (contextKey) {
        params.context_key = contextKey;
    }

    const response = await fetch(buildEnrichmentUrl(params), {
        method: "GET",
        headers: { Accept: "application/json" },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(payload?.message || `Stage ${stage} ophalen is mislukt.`);
        error.payload = payload;
        throw error;
    }
    return payload;
};

const fetchAddressEnrichment = async () => {
    if (!form.bag_address_id) {
        enrichmentData.value = null;
        enrichmentError.value = "";
        enrichmentProgress.value = [];
        mapShouldRender.value = false;
        disconnectMapObserver();
        return;
    }

    const runId = ++enrichmentSequenceRunId.value;
    initializeEnrichmentProgress();
    isEnrichmentLoading.value = true;
    enrichmentError.value = "";
    mapShouldRender.value = false;
    disconnectMapObserver();

    try {
        const corePayload = await fetchEnrichmentStage({
            stage: "core",
            bagAddressId: form.bag_address_id,
        });
        if (runId !== enrichmentSequenceRunId.value) {
            return;
        }

        enrichmentData.value = corePayload;
        updateEnrichmentProgressFromDiagnostics(corePayload?.diagnostics ?? {});
        await setupLazyMapObserver();

        const contextKey = corePayload?.context_key;
        const stageRequests = [ "zoning", "heritage", "accessibility", "milieu" ].map((stage) =>
            fetchEnrichmentStage({
                stage,
                contextKey,
                bagAddressId: form.bag_address_id,
            })
                .then((payload) => {
                    if (runId !== enrichmentSequenceRunId.value) {
                        return;
                    }
                    mergeEnrichmentPayload(payload ?? {});
                    updateEnrichmentProgressFromDiagnostics(enrichmentData.value?.diagnostics ?? {});
                })
                .catch((error) => {
                    if (runId !== enrichmentSequenceRunId.value) {
                        return;
                    }
                    const message = error?.message || `Stage ${stage} ophalen is mislukt.`;
                    enrichmentError.value = message;
                    markProgressFailedByStage(stage);
                    const diagnosticDetail =
                        error?.payload?.diagnostics &&
                        typeof error.payload.diagnostics === "object"
                            ? Object.values(error.payload.diagnostics)
                                .map((diag) => String(diag?.detail ?? "").trim())
                                .find((value) => value !== "")
                            : "";
                    setProgressDetailByStage(stage, diagnosticDetail || message);
                })
        );

        await Promise.allSettled(stageRequests);

    } catch {
        enrichmentData.value = null;
        enrichmentError.value = "Adresverrijking ophalen is mislukt.";
        enrichmentProgress.value = [];
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

watch(
    () => [enrichmentData.value?.map?.google_maps_api_key_available, mapContainer.value],
    async () => {
        legendImageErrors.value = {};
        clearMapFeatureInfo();
        if (enrichmentData.value?.map?.google_maps_api_key_available) {
            await setupLazyMapObserver();
            if (mapShouldRender.value) {
                await renderMap();
            }
        }
    }
);

watch(
    () => mapMode.value,
    () => {
        legendImageErrors.value = {};
        clearMapFeatureInfo();
        if (mapShouldRender.value && mapInstance.value) {
            updateMapMode();
        }
    }
);

onBeforeUnmount(() => {
    document.removeEventListener("paste", handleGlobalPaste);
    enrichmentSequenceRunId.value += 1;
    disconnectMapObserver();
    if (addressLookupTimer.value) {
        clearTimeout(addressLookupTimer.value);
    }
    if (addressLookupAbortController.value) {
        addressLookupAbortController.value.abort();
    }
    if (mapRenderRetryTimer.value) {
        clearTimeout(mapRenderRetryTimer.value);
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

                        <div v-if="enrichmentProgress.length" class="space-y-1 text-sm">
                            <div
                                v-for="item in enrichmentProgress"
                                :key="`progress-${item.key}`"
                                class="flex items-start gap-2"
                            >
                                <span
                                    v-if="item.status === 'pending'"
                                    class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"
                                ></span>
                                <span v-else-if="item.status === 'done'" class="font-semibold text-green-600">✓</span>
                                <span v-else class="font-semibold text-red-600">✕</span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-800">{{ item.label }} ophalen</span>
                                        <span v-if="item.status === 'done'" class="text-xs font-semibold text-green-600">Gereed</span>
                                        <span v-else-if="item.status === 'failed'" class="text-xs font-semibold text-red-600">Mislukt</span>
                                        <span v-else class="text-xs text-gray-500">Bezig</span>
                                    </div>
                                    <div v-if="item.status === 'failed' && item.detail" class="text-xs text-red-600">
                                        {{ item.detail }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="enrichmentError && !isEnrichmentLoading" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ enrichmentError }}
                        </div>
                        <div
                            v-if="enrichmentData && !isEnrichmentLoading"
                            class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold text-gray-900">Locatie-informatie</h3>
                            </div>

                            <div class="grid items-stretch gap-3 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Identificatie</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Geocode</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="geocodeMapsLink && enrichmentData?.geocode?.lat != null && enrichmentData?.geocode?.lng != null"
                                                    :href="geocodeMapsLink"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ enrichmentData?.geocode?.lat }}, {{ enrichmentData?.geocode?.lng }}
                                                </a>
                                                <span v-else>{{ enrichmentData?.geocode?.lat ?? "-" }}, {{ enrichmentData?.geocode?.lng ?? "-" }}</span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">BAG-ID</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="enrichmentData?.bag?.bag_viewer_url && (enrichmentData?.bag?.bag_id ?? enrichmentData?.bag_id)"
                                                    :href="enrichmentData.bag.bag_viewer_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ enrichmentData?.bag?.bag_id ?? enrichmentData?.bag_id }}
                                                </a>
                                                <span v-else>{{ enrichmentData?.bag?.bag_id ?? enrichmentData?.bag_id ?? "-" }}</span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Pand-ID</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <template v-if="pandIds.length">
                                                    <template v-for="(pandId, index) in pandIds" :key="pandId">
                                                        <a
                                                            :href="pandIdViewerUrl(pandId)"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="text-blue-700 hover:text-blue-800"
                                                        >
                                                            {{ pandId }}
                                                        </a>
                                                        <span v-if="index < pandIds.length - 1">, </span>
                                                    </template>
                                                </template>
                                                <span v-else>-</span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Buurtcode</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="enrichmentData?.accessibility?.buurtcode && buurtcodeInfoLink"
                                                    :href="buurtcodeInfoLink"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ enrichmentData?.accessibility?.buurtcode }}
                                                </a>
                                                <span v-else>-</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Kadaster</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Kadastrale aanduiding</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">{{ enrichmentData?.cadastre?.kadastrale_aanduiding ?? "-" }}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Perceelsgrootte</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">{{ enrichmentData?.cadastre?.perceelsgrootte_m2 ?? "-" }} m2</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bouwkundig</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Bouwjaar</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">{{ enrichmentData?.bag?.bouwjaar ?? "-" }}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Oppervlakte</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">{{ enrichmentData?.bag?.oppervlakte_m2 ?? "-" }} m2</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bestemming</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Gebruiksfunctie</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="bestemmingSourceLink && enrichmentData?.bag?.gebruiksfunctie"
                                                    :href="bestemmingSourceLink"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ enrichmentData?.bag?.gebruiksfunctie }}
                                                </a>
                                                <span v-else>{{ enrichmentData?.bag?.gebruiksfunctie ?? "-" }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-full rounded bg-white p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Monumentenstatus</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Rijksmonument</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="enrichmentData?.heritage?.is_monument && monumentStatusRijksmonumentLink && monumentStatusRijksmonumentNummer"
                                                    :href="monumentStatusRijksmonumentLink"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ monumentStatusRijksmonumentNummer }}
                                                </a>
                                                <span v-else-if="enrichmentData?.heritage?.is_monument">Ja</span>
                                                <span v-else>Nee</span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Gemeentelijk monument</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="enrichmentData?.heritage?.is_gemeentelijk_monument && monumentStatusGemeentelijkLink && monumentStatusGemeentelijkLabel"
                                                    :href="monumentStatusGemeentelijkLink"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ monumentStatusGemeentelijkLabel }}
                                                </a>
                                                <span v-else-if="enrichmentData?.heritage?.is_gemeentelijk_monument">Ja</span>
                                                <span v-else>Nee</span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Beschermd gezicht</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="enrichmentData?.heritage?.beschermd_stads_dorpsgezicht && monumentStatusGezichtLink && monumentStatusGezichtNaam"
                                                    :href="monumentStatusGezichtLink"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ monumentStatusGezichtType }} - {{ monumentStatusGezichtNaam }}
                                                </a>
                                                <span v-else-if="enrichmentData?.heritage?.beschermd_stads_dorpsgezicht && monumentStatusGezichtNaam">
                                                    {{ monumentStatusGezichtType }} - {{ monumentStatusGezichtNaam }}
                                                </span>
                                                <span v-else>Nee</span>
                                            </span>
                                        </div>
                                </div>
                            </div>
                            </div>

                            <div class="grid items-stretch gap-3 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bereikbaarheid</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Station/metro/tram</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value inline-flex items-baseline gap-1 whitespace-nowrap">
                                                <a
                                                    v-if="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde_ov?.station_metro_tram)"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde_ov?.station_metro_tram)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatDistanceMeters(enrichmentData?.accessibility?.afstand_tot_treinstation_ov_knooppunt_km) }}
                                                </a>
                                                <span v-else>
                                                    {{ formatDistanceMeters(enrichmentData?.accessibility?.afstand_tot_treinstation_ov_knooppunt_km) }}
                                                </span>
                                                <span
                                                    v-if="formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_treinstation_ov_knooppunt_km)"
                                                    class="inline-flex items-center gap-0.5 text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true">
                                                        <path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" />
                                                    </svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_treinstation_ov_knooppunt_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Bushalte</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value inline-flex items-baseline gap-1 whitespace-nowrap">
                                                <a
                                                    v-if="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde_ov?.bushalte)"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde_ov?.bushalte)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatDistanceMeters(enrichmentData?.accessibility?.afstand_tot_bushalte_km) }}
                                                </a>
                                                <span v-else>
                                                    {{ formatDistanceMeters(enrichmentData?.accessibility?.afstand_tot_bushalte_km) }}
                                                </span>
                                                <span
                                                    v-if="formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_bushalte_km)"
                                                    class="inline-flex items-center gap-0.5 text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true">
                                                        <path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" />
                                                    </svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_bushalte_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Oprit hoofdweg</span>
                                            <span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value inline-flex items-baseline gap-1 whitespace-nowrap">
                                                <a
                                                    v-if="googleDrivingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde_ov?.oprit_hoofdweg)"
                                                    :href="googleDrivingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde_ov?.oprit_hoofdweg)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatDistanceMeters(enrichmentData?.accessibility?.afstand_tot_oprit_hoofdweg_km) }}
                                                </a>
                                                <span v-else>
                                                    {{ formatDistanceMeters(enrichmentData?.accessibility?.afstand_tot_oprit_hoofdweg_km) }}
                                                </span>
                                                <span
                                                    v-if="formatDrivingDurationMinutes(
                                                        enrichmentData?.accessibility?.afstand_tot_oprit_hoofdweg_km,
                                                        enrichmentData?.accessibility?.dichtstbijzijnde_ov?.oprit_hoofdweg?.duur_min
                                                    )"
                                                    class="inline-flex items-center gap-0.5 text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true">
                                                        <path d="M18.9 6.5c-.3-.9-1.1-1.5-2.1-1.5H7.2c-1 0-1.8.6-2.1 1.5L3 12v7h2v-2h14v2h2v-7l-2.1-5.5ZM7.2 7h9.6l1.4 4H5.8L7.2 7ZM6 15a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm12 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
                                                    </svg>
                                                    {{
                                                        formatDrivingDurationMinutes(
                                                            enrichmentData?.accessibility?.afstand_tot_oprit_hoofdweg_km,
                                                            enrichmentData?.accessibility?.dichtstbijzijnde_ov?.oprit_hoofdweg?.duur_min
                                                        )
                                                    }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Voorzieningen</div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                        <div class="detail-row">
                                            <span class="detail-label">Supermarkt</span><span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_supermarkt_km) && googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt)"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatVoorzieningDistance(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_supermarkt_km) }}
                                                </a>
                                                <span v-else>{{ formatVoorzieningDistance(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_supermarkt_km) }}</span>
                                                <span
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_supermarkt_km) && formatWalkingDurationMinutes(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_supermarkt_km)"
                                                    class="ml-1 inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" /></svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.dichtstbijzijnde?.supermarkt?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_supermarkt_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Sport- en beweegmogelijkheden</span><span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.dichtstbijzijnde?.sport?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_sport_km) && googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.sport, 'sportvoorziening')"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.sport, 'sportvoorziening')"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatVoorzieningDistance(enrichmentData?.accessibility?.dichtstbijzijnde?.sport?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_sport_km) }}
                                                </a>
                                                <span v-else>{{ formatVoorzieningDistance(enrichmentData?.accessibility?.dichtstbijzijnde?.sport?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_sport_km) }}</span>
                                                <span
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.dichtstbijzijnde?.sport?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_sport_km) && formatWalkingDurationMinutes(enrichmentData?.accessibility?.dichtstbijzijnde?.sport?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_sport_km)"
                                                    class="ml-1 inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" /></svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.dichtstbijzijnde?.sport?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_sport_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Groenvoorzieningen</span><span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.dichtstbijzijnde?.groen?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_groen_km) && googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.groen, 'groenvoorziening')"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.groen, 'groenvoorziening')"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatVoorzieningDistance(enrichmentData?.accessibility?.dichtstbijzijnde?.groen?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_groen_km) }}
                                                </a>
                                                <span v-else>{{ formatVoorzieningDistance(enrichmentData?.accessibility?.dichtstbijzijnde?.groen?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_groen_km) }}</span>
                                                <span
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.dichtstbijzijnde?.groen?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_groen_km) && formatWalkingDurationMinutes(enrichmentData?.accessibility?.dichtstbijzijnde?.groen?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_groen_km)"
                                                    class="ml-1 inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" /></svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.dichtstbijzijnde?.groen?.afstand_km ?? enrichmentData?.accessibility?.afstand_tot_groen_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Cafe</span><span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.afstand_tot_cafe_km) && googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.cafe, 'cafe')"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.cafe, 'cafe')"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatVoorzieningDistance(enrichmentData?.accessibility?.afstand_tot_cafe_km) }}
                                                </a>
                                                <span v-else>{{ formatVoorzieningDistance(enrichmentData?.accessibility?.afstand_tot_cafe_km) }}</span>
                                                <span
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.afstand_tot_cafe_km) && formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_cafe_km)"
                                                    class="ml-1 inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" /></svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_cafe_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Restaurant</span><span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.afstand_tot_restaurant_km) && googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.restaurant, 'restaurant')"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.restaurant, 'restaurant')"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatVoorzieningDistance(enrichmentData?.accessibility?.afstand_tot_restaurant_km) }}
                                                </a>
                                                <span v-else>{{ formatVoorzieningDistance(enrichmentData?.accessibility?.afstand_tot_restaurant_km) }}</span>
                                                <span
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.afstand_tot_restaurant_km) && formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_restaurant_km)"
                                                    class="ml-1 inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" /></svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_restaurant_km) }}
                                                </span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Hotel</span><span class="detail-dots" aria-hidden="true"></span>
                                            <span class="detail-value">
                                                <a
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.afstand_tot_hotel_km) && googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.hotel, 'hotel')"
                                                    :href="googleWalkingRouteUrl(enrichmentData?.accessibility?.dichtstbijzijnde?.hotel, 'hotel')"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ formatVoorzieningDistance(enrichmentData?.accessibility?.afstand_tot_hotel_km) }}
                                                </a>
                                                <span v-else>{{ formatVoorzieningDistance(enrichmentData?.accessibility?.afstand_tot_hotel_km) }}</span>
                                                <span
                                                    v-if="hasDistanceWithinFiveKm(enrichmentData?.accessibility?.afstand_tot_hotel_km) && formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_hotel_km)"
                                                    class="ml-1 inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current" aria-hidden="true"><path d="M13.5 5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM9.8 8.9l-2.3 11H9l1.4-6.3 2.2 2.1v6.2h1.5V14.7l-2.3-2.2.7-3.5c1.1 1.3 2.7 2.1 4.5 2.1V9.6c-1.5 0-2.8-.8-3.5-2l-.8-1.3c-.3-.5-.9-.8-1.5-.8-.7 0-1.3.4-1.6 1l-1.2 2.4c-.2.4-.3.8-.3 1.2V14h1.5V10.2l1.4-2.3Zm-2.9 12.5a1.5 1.5 0 0 0 1.5 1.8h2.1v-1.5H8.8l1.8-8H9.1l-2.2 7.7Z" /></svg>
                                                    {{ formatWalkingDurationMinutes(enrichmentData?.accessibility?.afstand_tot_hotel_km) }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid items-stretch gap-3 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                <div class="h-full rounded bg-white p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Milieu
                                    </div>
                                    <div class="mt-2 detail-list text-sm text-gray-700">
                                    <div class="detail-row">
                                        <span class="detail-label">Energielabel</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                        <span v-if="hasGraphicalEnergyLabel" class="inline-flex align-middle">
                                            <span
                                                class="h-6 text-white"
                                                :style="{
                                                    width: '96px',
                                                    backgroundColor: energyLabelColorMap[selectedEnergyLabel] ?? '#9ca3af',
                                                    clipPath: 'polygon(0 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 0 100%)',
                                                }"
                                            >
                                                <span class="flex h-full items-center px-2 text-xs font-bold drop-shadow-[0_1px_1px_rgba(0,0,0,0.35)]">
                                                    {{ selectedEnergyLabel }}
                                                </span>
                                            </span>
                                        </span>
                                        <span v-else>Niet beschikbaar</span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Bodemvervuiling</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                        <a
                                            v-if="enrichmentData?.soil?.bodemloket_url && enrichmentData?.soil?.status"
                                            :href="enrichmentData.soil.bodemloket_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-blue-700 hover:text-blue-800"
                                        >
                                            {{ enrichmentData.soil.status }}
                                        </a>
                                        <span v-else>
                                            {{ enrichmentData?.soil?.status ?? "-" }}
                                        </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Fijnstof</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                            {{ formatMilieuDisplayValue('fijnstof', enrichmentData?.air_quality?.pm25_ug_m3) }}
                                        <span class="relative ml-1 inline-flex cursor-help items-center align-middle">
                                            <span class="group inline-flex items-center">
                                                <MilieuSmileyIcon class="h-4 w-4" :icon="milieuAssessment('fijnstof', enrichmentData?.air_quality?.pm25_ug_m3).smileyIcon" />
                                                <span class="invisible absolute left-0 top-full z-20 mt-1 w-72 rounded border border-gray-200 bg-white p-2 text-xs text-gray-700 shadow-lg group-hover:visible">
                                                    <div
                                                        v-for="(item, idx) in milieuAssessment('fijnstof', enrichmentData?.air_quality?.pm25_ug_m3).helperItems"
                                                        :key="`fijnstof-${idx}`"
                                                        class="mb-1 flex items-start gap-1.5 last:mb-0"
                                                    >
                                                        <MilieuSmileyIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" :icon="milieuSmileyByLevel[item.level]?.icon ?? milieuSmileyByLevel.geel.icon" />
                                                        <span>{{ item.text }}</span>
                                                    </div>
                                                    <a
                                                        :href="milieuAssessment('fijnstof', enrichmentData?.air_quality?.pm25_ug_m3).link"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:text-blue-800"
                                                    >
                                                        Meer uitleg
                                                    </a>
                                                </span>
                                            </span>
                                        </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Stikstofdioxide</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                            {{ formatMilieuDisplayValue('stikstofdioxide', enrichmentData?.air_quality?.no2_ug_m3) }}
                                        <span class="relative ml-1 inline-flex cursor-help items-center align-middle">
                                            <span class="group inline-flex items-center">
                                                <MilieuSmileyIcon class="h-4 w-4" :icon="milieuAssessment('stikstofdioxide', enrichmentData?.air_quality?.no2_ug_m3).smileyIcon" />
                                                <span class="invisible absolute left-0 top-full z-20 mt-1 w-72 rounded border border-gray-200 bg-white p-2 text-xs text-gray-700 shadow-lg group-hover:visible">
                                                    <div
                                                        v-for="(item, idx) in milieuAssessment('stikstofdioxide', enrichmentData?.air_quality?.no2_ug_m3).helperItems"
                                                        :key="`stikstof-${idx}`"
                                                        class="mb-1 flex items-start gap-1.5 last:mb-0"
                                                    >
                                                        <MilieuSmileyIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" :icon="milieuSmileyByLevel[item.level]?.icon ?? milieuSmileyByLevel.geel.icon" />
                                                        <span>{{ item.text }}</span>
                                                    </div>
                                                    <a
                                                        :href="milieuAssessment('stikstofdioxide', enrichmentData?.air_quality?.no2_ug_m3).link"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:text-blue-800"
                                                    >
                                                        Meer uitleg
                                                    </a>
                                                </span>
                                            </span>
                                        </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Geluid in de omgeving</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                            {{ formatMilieuDisplayValue('geluid', enrichmentData?.air_quality?.geluid_omgeving?.waarde) }}
                                        <span class="relative ml-1 inline-flex cursor-help items-center align-middle">
                                            <span class="group inline-flex items-center">
                                                <MilieuSmileyIcon class="h-4 w-4" :icon="milieuAssessment('geluid', enrichmentData?.air_quality?.geluid_omgeving?.score ?? enrichmentData?.air_quality?.geluid_omgeving?.waarde).smileyIcon" />
                                                <span class="invisible absolute left-0 top-full z-20 mt-1 w-72 rounded border border-gray-200 bg-white p-2 text-xs text-gray-700 shadow-lg group-hover:visible">
                                                    <div
                                                        v-for="(item, idx) in milieuAssessment('geluid', enrichmentData?.air_quality?.geluid_omgeving?.score ?? enrichmentData?.air_quality?.geluid_omgeving?.waarde).helperItems"
                                                        :key="`geluid-${idx}`"
                                                        class="mb-1 flex items-start gap-1.5 last:mb-0"
                                                    >
                                                        <MilieuSmileyIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" :icon="milieuSmileyByLevel[item.level]?.icon ?? milieuSmileyByLevel.geel.icon" />
                                                        <span>{{ item.text }}</span>
                                                    </div>
                                                    <a
                                                        :href="milieuAssessment('geluid', enrichmentData?.air_quality?.geluid_omgeving?.score ?? enrichmentData?.air_quality?.geluid_omgeving?.waarde).link"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:text-blue-800"
                                                    >
                                                        Meer uitleg
                                                    </a>
                                                </span>
                                            </span>
                                        </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Zomerhitte in de stad</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                            {{ formatMilieuDisplayValue('zomerhitte', enrichmentData?.air_quality?.zomerhitte_stad?.waarde) }}
                                        <span class="relative ml-1 inline-flex cursor-help items-center align-middle">
                                            <span class="group inline-flex items-center">
                                                <MilieuSmileyIcon class="h-4 w-4" :icon="milieuAssessment('zomerhitte', enrichmentData?.air_quality?.zomerhitte_stad?.score ?? enrichmentData?.air_quality?.zomerhitte_stad?.waarde).smileyIcon" />
                                                <span class="invisible absolute left-0 top-full z-20 mt-1 w-72 rounded border border-gray-200 bg-white p-2 text-xs text-gray-700 shadow-lg group-hover:visible">
                                                    <div
                                                        v-for="(item, idx) in milieuAssessment('zomerhitte', enrichmentData?.air_quality?.zomerhitte_stad?.score ?? enrichmentData?.air_quality?.zomerhitte_stad?.waarde).helperItems"
                                                        :key="`zomerhitte-${idx}`"
                                                        class="mb-1 flex items-start gap-1.5 last:mb-0"
                                                    >
                                                        <MilieuSmileyIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" :icon="milieuSmileyByLevel[item.level]?.icon ?? milieuSmileyByLevel.geel.icon" />
                                                        <span>{{ item.text }}</span>
                                                    </div>
                                                    <a
                                                        :href="milieuAssessment('zomerhitte', enrichmentData?.air_quality?.zomerhitte_stad?.score ?? enrichmentData?.air_quality?.zomerhitte_stad?.waarde).link"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:text-blue-800"
                                                    >
                                                        Meer uitleg
                                                    </a>
                                                </span>
                                            </span>
                                        </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Kans op overstroming</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                            {{ formatMilieuDisplayValue('overstroming', enrichmentData?.air_quality?.kans_op_overstroming?.waarde) }}
                                        <span class="relative ml-1 inline-flex cursor-help items-center align-middle">
                                            <span class="group inline-flex items-center">
                                                <MilieuSmileyIcon class="h-4 w-4" :icon="milieuAssessment('overstroming', enrichmentData?.air_quality?.kans_op_overstroming?.score ?? enrichmentData?.air_quality?.kans_op_overstroming?.waarde).smileyIcon" />
                                                <span class="invisible absolute left-0 top-full z-20 mt-1 w-72 rounded border border-gray-200 bg-white p-2 text-xs text-gray-700 shadow-lg group-hover:visible">
                                                    <div
                                                        v-for="(item, idx) in milieuAssessment('overstroming', enrichmentData?.air_quality?.kans_op_overstroming?.score ?? enrichmentData?.air_quality?.kans_op_overstroming?.waarde).helperItems"
                                                        :key="`overstroming-${idx}`"
                                                        class="mb-1 flex items-start gap-1.5 last:mb-0"
                                                    >
                                                        <MilieuSmileyIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" :icon="milieuSmileyByLevel[item.level]?.icon ?? milieuSmileyByLevel.geel.icon" />
                                                        <span>{{ item.text }}</span>
                                                    </div>
                                                    <a
                                                        :href="milieuAssessment('overstroming', enrichmentData?.air_quality?.kans_op_overstroming?.score ?? enrichmentData?.air_quality?.kans_op_overstroming?.waarde).link"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:text-blue-800"
                                                    >
                                                        Meer uitleg
                                                    </a>
                                                </span>
                                            </span>
                                        </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Gevaarlijke stoffen (binnen 1 km)</span>
                                        <span class="detail-dots" aria-hidden="true"></span>
                                        <span class="detail-value">
                                            {{ formatMilieuDisplayValue('gevaarlijke_stoffen', enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.waarde) }}
                                        <span class="relative ml-1 inline-flex cursor-help items-center align-middle">
                                            <span class="group inline-flex items-center">
                                                <MilieuSmileyIcon class="h-4 w-4" :icon="milieuAssessment('gevaarlijke_stoffen', enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.score ?? enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.waarde).smileyIcon" />
                                                <span class="invisible absolute left-0 top-full z-20 mt-1 w-72 rounded border border-gray-200 bg-white p-2 text-xs text-gray-700 shadow-lg group-hover:visible">
                                                    <div
                                                        v-for="(item, idx) in milieuAssessment('gevaarlijke_stoffen', enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.score ?? enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.waarde).helperItems"
                                                        :key="`gevaar-${idx}`"
                                                        class="mb-1 flex items-start gap-1.5 last:mb-0"
                                                    >
                                                        <MilieuSmileyIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" :icon="milieuSmileyByLevel[item.level]?.icon ?? milieuSmileyByLevel.geel.icon" />
                                                        <span>{{ item.text }}</span>
                                                    </div>
                                                    <a
                                                        :href="milieuAssessment('gevaarlijke_stoffen', enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.score ?? enrichmentData?.air_quality?.gevaarlijke_stoffen_binnen_1km?.waarde).link"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:text-blue-800"
                                                    >
                                                        Meer uitleg
                                                    </a>
                                                </span>
                                            </span>
                                        </span>
                                        </span>
                                    </div>
                                </div>
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
                                        <option value="streetview">StreetView</option>
                                        <option value="kadaster">Kadaster</option>
                                        <option value="bodemverontreiniging">Bodemverontreiniging en sanering</option>
                                        <option value="energielabels">Energielabels</option>
                                        <option value="gebruiksfuncties">Gebruiksfuncties</option>
                                        <option value="bestemmingsplannen">Bestemmingsplannen</option>
                                    </select>
                                </div>
                                <div
                                    v-if="enrichmentData?.map?.google_maps_api_key_available"
                                    class="relative mt-3 aspect-[3/2] w-full overflow-hidden rounded-lg border border-gray-200"
                                >
                                    <div ref="mapContainer" class="h-full w-full"></div>
                                    <div
                                        v-if="mapLegend.items?.length"
                                        :class="mapLegendContainerClass"
                                    >
                                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-700">
                                            {{ mapLegend.title }}
                                        </div>
                                        <div v-if="visibleLegendItems.length" class="space-y-1">
                                            <img
                                                v-for="item in visibleLegendItems"
                                                :key="item.url"
                                                :src="item.url"
                                                :alt="`${mapLegend.title} ${item.layer}`"
                                                :class="mapLegendImageClass"
                                                @error="handleLegendImageError(item.url)"
                                            />
                                        </div>
                                        <div v-else-if="fallbackLegendItems.length" class="space-y-1">
                                            <div
                                                v-for="item in fallbackLegendItems"
                                                :key="item.label"
                                                class="flex items-center gap-2 text-xs text-gray-700"
                                            >
                                                <span
                                                    class="inline-block h-3 w-4 shrink-0 rounded-[2px]"
                                                    :style="item.style"
                                                ></span>
                                                <span>{{ item.label }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-gray-600">
                                            Geen legenda beschikbaar voor deze laag.
                                        </div>
                                    </div>
                                    <div
                                        v-if="['kadaster', 'bodemverontreiniging', 'energielabels', 'gebruiksfuncties', 'bestemmingsplannen'].includes(mapMode)"
                                        class="absolute right-2 top-2 z-[5] max-w-[55%] rounded-md border border-gray-300 bg-white/95 p-2 shadow-sm"
                                    >
                                        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-700">
                                            Patroon Uitleg (klik op kaart)
                                        </div>
                                        <div v-if="mapFeatureInfoLoading" class="mt-1 text-xs text-gray-600">
                                            Informatie ophalen...
                                        </div>
                                        <div v-else>
                                            <div
                                                v-if="mapFeatureInfoNotice"
                                                class="mt-1 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800"
                                            >
                                                {{ mapFeatureInfoNotice }}
                                            </div>
                                            <div
                                                v-if="mapFeatureInfoItems.length"
                                                class="mt-1 space-y-1 text-xs text-gray-700"
                                            >
                                            <div
                                                v-for="item in mapFeatureInfoItems"
                                                :key="`${item.title}:${item.value}`"
                                                class="flex gap-1"
                                            >
                                                <span class="font-semibold">{{ item.title }}:</span>
                                                <a
                                                    v-if="item.url"
                                                    :href="item.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="break-words text-blue-700 hover:text-blue-800"
                                                >
                                                    {{ item.value || "Open" }}
                                                </a>
                                                <span v-else class="break-words">{{ item.value }}</span>
                                            </div>
                                            </div>
                                            <div v-else class="mt-1 text-xs text-gray-600">
                                                {{ mapFeatureInfoMessage || "Klik op een vlak/patroon voor detailuitleg." }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="mapLoadError" class="mt-2 text-sm text-red-700">
                                    {{ mapLoadError }}
                                </div>
                                <div
                                    v-if="!enrichmentData?.map?.google_maps_api_key_available"
                                    class="mt-3 text-sm text-gray-600"
                                >
                                    Google Maps API-key ontbreekt; kaart kan niet geladen worden.
                                </div>
                            </div>

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

<style scoped>
.detail-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-list-two-cols {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.5rem 1rem;
}

.detail-row {
    display: grid;
    grid-template-columns: max-content minmax(1.25rem, 1fr) minmax(0, 14rem);
    align-items: baseline;
    gap: 0.5rem;
    min-width: 0;
}

.detail-label {
    white-space: nowrap;
}

.detail-dots {
    min-width: 0.75rem;
    color: rgb(209 213 219);
    background-image: radial-gradient(circle, currentColor 1px, transparent 1.2px);
    background-size: 6px 2px;
    background-repeat: repeat-x;
    background-position: left calc(100% - 1px);
}

.detail-value {
    min-width: 0;
    justify-self: start;
    text-align: left;
    font-weight: 600;
    color: rgb(17 24 39);
}

@media (min-width: 768px) {
    .detail-list-two-cols {
        grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    }

    .detail-list-two-cols .detail-row {
        grid-template-columns: max-content minmax(1rem, 1fr) minmax(0, 12rem);
    }
}

@media (max-width: 640px) {
    .detail-row {
        grid-template-columns: 1fr;
        gap: 0.125rem;
    }

    .detail-label {
        white-space: normal;
    }

    .detail-dots {
        display: none;
    }
}
</style>


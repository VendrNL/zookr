import { onBeforeUnmount, onMounted, ref } from "vue";

const DROP_DEDUP_MS = 800;

const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const extractUrlsFromDataTransfer = (dataTransfer) => {
    if (!dataTransfer) return Promise.resolve([]);

    const urls = [];
    const pushCandidate = (value) => {
        if (!value) return;
        const normalized = value.trim();
        if (!normalized || normalized.startsWith("#")) return;
        if (!urls.includes(normalized)) {
            urls.push(normalized);
        }
    };
    const pushCandidates = (value) => {
        if (!value) return;
        value
            .split("\n")
            .map((line) => line.trim())
            .filter((line) => line && !line.startsWith("#"))
            .forEach((line) => pushCandidate(line));
    };

    const uriList = dataTransfer.getData("text/uri-list") || "";
    const textData = dataTransfer.getData("text/plain") || "";
    const htmlData = dataTransfer.getData("text/html") || "";
    if (htmlData) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlData, "text/html");
        const img = doc.querySelector("img");
        if (img?.src) {
            pushCandidate(img.src);
            return Promise.resolve([img.src]);
        }
        const link = doc.querySelector("a");
        if (link?.href) {
            pushCandidate(link.href);
            return Promise.resolve([link.href]);
        }
    }
    pushCandidates(uriList);
    pushCandidates(textData);

    const items = Array.from(dataTransfer.items || []);
    if (!items.length) {
        return Promise.resolve(urls.length ? [urls[0]] : []);
    }

    return new Promise((resolve) => {
        let pending = 0;
        let resolved = false;
        const finish = () => {
            pending -= 1;
            if (pending <= 0 && !resolved) {
                resolved = true;
                resolve(urls.length ? [urls[0]] : []);
            }
        };

        items.forEach((item) => {
            if (item.kind !== "string") {
                return;
            }
            pending += 1;
            item.getAsString((value) => {
                if (item.type === "text/html") {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(value, "text/html");
                    const img = doc.querySelector("img");
                    if (img?.src) {
                        pushCandidate(img.src);
                        if (!resolved) {
                            resolved = true;
                            resolve([img.src]);
                        }
                        return;
                    }
                    const link = doc.querySelector("a");
                    if (link?.href) {
                        pushCandidate(link.href);
                        if (!resolved) {
                            resolved = true;
                            resolve([link.href]);
                        }
                        return;
                    }
                } else {
                    pushCandidates(value);
                }
                finish();
            });
        });

        if (pending === 0) {
            resolve(urls.length ? [urls[0]] : []);
        }
    });
};

const isRelevantDrop = (dataTransfer) => {
    if (!dataTransfer) return false;
    const types = Array.from(dataTransfer.types || []);
    return types.some((type) =>
        ["Files", "text/html", "text/uri-list", "text/plain"].includes(type)
    );
};

const isAvifFile = (file) => {
    if (!file || typeof file !== "object") return false;
    if (file.type === "image/avif") return true;
    if (typeof file.name !== "string") return false;
    return file.name.toLowerCase().endsWith(".avif");
};

const convertAvifToJpeg = (file) =>
    new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement("canvas");
            canvas.width = img.naturalWidth || img.width;
            canvas.height = img.naturalHeight || img.height;
            const ctx = canvas.getContext("2d");
            if (!ctx) {
                URL.revokeObjectURL(url);
                resolve(null);
                return;
            }
            ctx.drawImage(img, 0, 0);
            canvas.toBlob(
                (blob) => {
                    URL.revokeObjectURL(url);
                    if (!blob) {
                        resolve(null);
                        return;
                    }
                    const safeName = file.name?.replace(/\.avif$/i, "") || "image";
                    resolve(new File([blob], `${safeName}.jpg`, { type: "image/jpeg" }));
                },
                "image/jpeg",
                0.92
            );
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        img.src = url;
    });

const useImageDrop = ({
    form,
    itemId,
    setImages,
    normalizeUrl,
    debugLabel,
    enableGlobalDrop = false,
}) => {
    const cachedImages = ref([]);
    const pendingCacheUrls = ref(new Set());
    const lastCacheAttempt = ref({ url: "", time: 0 });
    const isCachingImage = ref(false);
    const cacheError = ref("");
    const isDraggingImages = ref(false);
    const showDropOverlay = ref(false);
    const debugDrop = ref(false);
    const dragDepth = ref(0);
    const lastDropAt = ref(0);

    const shouldIgnoreDrop = () => {
        const now = Date.now();
        if (now - lastDropAt.value < DROP_DEDUP_MS) {
            return true;
        }
        lastDropAt.value = now;
        return false;
    };

    const logDropPayload = (event, label) => {
        if (!debugDrop.value) return;
        const dataTransfer = event?.dataTransfer;
        const types = Array.from(dataTransfer?.types || []);
        const items = Array.from(dataTransfer?.items || []).map((item) => ({
            kind: item.kind,
            type: item.type,
        }));
        const payload = {
            label,
            types,
            items,
            files: dataTransfer?.files?.length ?? 0,
            uriList: dataTransfer?.getData?.("text/uri-list") ?? "",
            textPlain: dataTransfer?.getData?.("text/plain") ?? "",
            textHtml: dataTransfer?.getData?.("text/html") ?? "",
        };
        console.log("[drop-debug]", payload);
    };

    const recordDropEvent = (event, stage) => {
        if (!debugDrop.value) return;
        const dataTransfer = event?.dataTransfer;
        const types = Array.from(dataTransfer?.types || []);
        const entry = {
            stage,
            types,
            files: dataTransfer?.files?.length ?? 0,
            time: Date.now(),
        };
        window.__dropTest = window.__dropTest || {
            page: debugLabel,
            mounted: true,
            events: [],
        };
        window.__dropTest.events.push(entry);
        console.log("[drop-debug]", entry);
    };

    const prepareImageFiles = async (files) => {
        const incoming = Array.from(files || []).filter(Boolean);
        const prepared = [];
        for (const file of incoming) {
            if (isAvifFile(file)) {
                const converted = await convertAvifToJpeg(file);
                if (converted) {
                    prepared.push(converted);
                } else {
                    cacheError.value = "AVIF kon niet worden omgezet. Gebruik PNG of JPG.";
                }
                continue;
            }
            prepared.push(file);
        }
        return prepared;
    };

    const cacheRemoteImage = async (url) => {
        const normalizedUrl = normalizeUrl(url);
        if (!normalizedUrl) {
            cacheError.value = "Vul een geldige afbeelding-URL in.";
            return;
        }
        const now = Date.now();
        const lastAttempt = lastCacheAttempt.value;
        if (lastAttempt.url === normalizedUrl && now - lastAttempt.time < 1500) {
            return;
        }
        lastCacheAttempt.value = { url: normalizedUrl, time: now };
        if (pendingCacheUrls.value.has(normalizedUrl)) {
            return;
        }
        if (cachedImages.value.some((image) => image.url === normalizedUrl)) {
            return;
        }

        cacheError.value = "";
        isCachingImage.value = true;
        pendingCacheUrls.value.add(normalizedUrl);

        try {
            const response = await fetch(
                route("search-requests.properties.cache-remote-image", itemId),
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": getCsrfToken(),
                    },
                    body: JSON.stringify({ url: normalizedUrl }),
                }
            );

            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data?.path) {
                cacheError.value = data?.message || "Afbeelding kon niet worden opgeslagen.";
                return;
            }

            cachedImages.value.push({
                path: data.path,
                url: data.url,
            });
            form.cached_images = cachedImages.value.map((image) => image.path);
        } catch (error) {
            cacheError.value = "Afbeelding kon niet worden opgeslagen.";
        } finally {
            pendingCacheUrls.value.delete(normalizedUrl);
            isCachingImage.value = false;
        }
    };

    const handleImagesDrop = async (event) => {
        event.preventDefault();
        event.stopPropagation();
        isDraggingImages.value = false;
        logDropPayload(event, "images");
        if (shouldIgnoreDrop()) {
            return;
        }
        const files = event.dataTransfer?.files;
        if (files && files.length) {
            const prepared = await prepareImageFiles(files);
            if (prepared.length) {
                setImages(prepared);
                return;
            }
        }
        const urls = await extractUrlsFromDataTransfer(event.dataTransfer);
        const firstUrl = urls[0];
        if (!firstUrl) {
            cacheError.value = "Geen afbeelding-URL gevonden in de drop.";
            return;
        }
        cacheRemoteImage(firstUrl);
    };

    const handleDragOverImages = (event) => {
        event.preventDefault();
        event.stopPropagation();
        isDraggingImages.value = true;
    };

    const handleDragLeaveImages = () => {
        isDraggingImages.value = false;
    };

    const handleGlobalDragEnter = (event) => {
        if (!isRelevantDrop(event.dataTransfer)) {
            return;
        }
        recordDropEvent(event, "dragenter");
        dragDepth.value += 1;
        showDropOverlay.value = true;
    };

    const handleGlobalDragOver = (event) => {
        if (!isRelevantDrop(event.dataTransfer)) {
            return;
        }
        event.preventDefault();
        recordDropEvent(event, "dragover");
        showDropOverlay.value = true;
    };

    const handleGlobalDragLeave = () => {
        dragDepth.value = Math.max(0, dragDepth.value - 1);
        if (dragDepth.value === 0) {
            showDropOverlay.value = false;
        }
    };

    const handleGlobalDrop = async (event) => {
        event.preventDefault();
        recordDropEvent(event, "drop");
        dragDepth.value = 0;
        showDropOverlay.value = false;
        if (shouldIgnoreDrop()) {
            return;
        }
        const urls = await extractUrlsFromDataTransfer(event.dataTransfer);
        const firstUrl = urls[0];
        if (firstUrl) {
            cacheRemoteImage(firstUrl);
            return;
        }
        const files = event.dataTransfer?.files;
        if (files && files.length) {
            setImages(files);
            return;
        }
        cacheError.value = "Geen afbeelding-URL gevonden in de drop.";
    };

    onMounted(() => {
        debugDrop.value = new URLSearchParams(window.location.search).has("debug-drop");
        if (debugDrop.value) {
            window.__dropTest = { page: debugLabel, mounted: true, events: [] };
            console.log(`[drop-debug] mounted ${debugLabel}`);
        }
        if (!enableGlobalDrop) {
            return;
        }
        window.addEventListener("dragenter", handleGlobalDragEnter, true);
        window.addEventListener("dragover", handleGlobalDragOver, true);
        window.addEventListener("dragleave", handleGlobalDragLeave, true);
        window.addEventListener("drop", handleGlobalDrop, true);
    });

    onBeforeUnmount(() => {
        if (!enableGlobalDrop) {
            return;
        }
        window.removeEventListener("dragenter", handleGlobalDragEnter, true);
        window.removeEventListener("dragover", handleGlobalDragOver, true);
        window.removeEventListener("dragleave", handleGlobalDragLeave, true);
        window.removeEventListener("drop", handleGlobalDrop, true);
    });

    return {
        cachedImages,
        cacheError,
        isCachingImage,
        isDraggingImages,
        showDropOverlay,
        cacheRemoteImage,
        prepareImageFiles,
        handleImagesDrop,
        handleDragOverImages,
        handleDragLeaveImages,
    };
};

export default useImageDrop;

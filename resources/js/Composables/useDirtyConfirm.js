import { router } from "@inertiajs/vue3";
import { onBeforeUnmount, onMounted } from "vue";

const DEFAULT_MESSAGE =
    "Wijzigingen zijn niet opgeslagen. Weet je zeker dat je wilt weggaan?";

const normalizeForms = (forms) => {
    if (Array.isArray(forms)) {
        return forms;
    }
    return forms ? [forms] : [];
};

const dirtyCheckers = new Set();
let listenersAttached = false;
let removeRouterListener = null;

const anyDirty = () => {
    for (const checker of dirtyCheckers) {
        if (checker()) {
            return true;
        }
    }
    return false;
};

const handleBeforeUnload = (event) => {
    if (!anyDirty()) {
        return;
    }

    event.preventDefault();
    event.returnValue = "";
};

const attachListeners = (message) => {
    if (listenersAttached) {
        return;
    }

    window.addEventListener("beforeunload", handleBeforeUnload);

    if (router?.on) {
        removeRouterListener = router.on("before", (event) => {
            const method = event?.detail?.visit?.method ?? "get";
            if (method.toLowerCase() !== "get") {
                return;
            }
            if (!anyDirty()) {
                return;
            }

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    }

    listenersAttached = true;
};

const detachListeners = () => {
    if (!listenersAttached || dirtyCheckers.size > 0) {
        return;
    }

    window.removeEventListener("beforeunload", handleBeforeUnload);

    if (typeof removeRouterListener === "function") {
        removeRouterListener();
        removeRouterListener = null;
    }

    listenersAttached = false;
};

export default function useDirtyConfirm(forms, message = DEFAULT_MESSAGE) {
    const trackedForms = normalizeForms(forms);

    const isDirty = () => {
        if (trackedForms.some((form) => form && form.processing)) {
            return false;
        }
        return trackedForms.some((form) => form && form.isDirty);
    };

    const confirmLeave = () => {
        if (!isDirty()) {
            return true;
        }
        return window.confirm(message);
    };

    onMounted(() => {
        dirtyCheckers.add(isDirty);
        attachListeners(message);
    });

    onBeforeUnmount(() => {
        dirtyCheckers.delete(isDirty);
        detachListeners();
    });

    return { confirmLeave };
}

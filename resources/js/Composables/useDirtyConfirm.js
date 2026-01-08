import { router } from "@inertiajs/vue3";
import { onBeforeUnmount, onMounted } from "vue";
import { openDirtyConfirm } from "@/Stores/dirtyConfirm";

const DEFAULT_MESSAGE = "Wil je deze wijzigingen opslaan?";

const normalizeForms = (forms) => {
    if (Array.isArray(forms)) {
        return forms;
    }
    return forms ? [forms] : [];
};

const dirtyCheckers = new Set();
const saveHandlers = new Set();
let listenersAttached = false;
let removeRouterListener = null;
let allowNextVisit = false;

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

export default function useDirtyConfirm(
    forms,
    message = DEFAULT_MESSAGE,
    options = {}
) {
    const trackedForms = normalizeForms(forms);
    const defaultOnSave = options?.onSave;

    const isDirty = () => {
        if (trackedForms.some((form) => form && form.processing)) {
            return false;
        }
        return trackedForms.some((form) => form && form.isDirty);
    };

    const runSave = (done) => {
        if (!isDirty()) {
            done();
            return;
        }
        if (typeof defaultOnSave === "function") {
            defaultOnSave(done);
            return;
        }
        done();
    };

    const attachListeners = () => {
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

                if (allowNextVisit) {
                    allowNextVisit = false;
                    return;
                }

                event.preventDefault();

                const visit = event?.detail?.visit;
                const url = visit?.url ?? visit;
                const visitOptions =
                    visit && typeof visit === "object" ? { ...visit } : {};
                if (visitOptions.url) {
                    delete visitOptions.url;
                }

                const proceed = () => {
                    allowNextVisit = true;
                    router.visit(url, visitOptions);
                };

                const handleSave = () => {
                    const handlers = Array.from(saveHandlers);
                    if (!handlers.length) {
                        proceed();
                        return;
                    }

                    let pending = 0;
                    let failed = false;

                    const done = () => {
                        if (failed) {
                            return;
                        }
                        pending -= 1;
                        if (pending <= 0) {
                            proceed();
                        }
                    };

                    handlers.forEach((handler) => {
                        pending += 1;
                        try {
                            handler(done);
                        } catch (error) {
                            failed = true;
                            pending -= 1;
                        }
                    });

                    if (pending === 0 && !failed) {
                        proceed();
                    }
                };

                openDirtyConfirm(message, proceed, handleSave);
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

    const confirmLeave = ({ onConfirm, onSave, onCancel } = {}) => {
        if (!isDirty()) {
            if (typeof onConfirm === "function") {
                onConfirm();
            }
            return true;
        }

        const proceed = () => {
            if (typeof onConfirm === "function") {
                onConfirm();
            }
        };

        const handleSave = () => {
            const saveHandler =
                typeof onSave === "function" ? onSave : defaultOnSave;
            if (typeof saveHandler === "function") {
                saveHandler(proceed);
                return;
            }
            proceed();
        };

        openDirtyConfirm(
            message,
            proceed,
            handleSave,
            () => {
                if (typeof onCancel === "function") {
                    onCancel();
                }
            }
        );

        return false;
    };

    onMounted(() => {
        dirtyCheckers.add(isDirty);
        saveHandlers.add(runSave);
        attachListeners();
    });

    onBeforeUnmount(() => {
        dirtyCheckers.delete(isDirty);
        saveHandlers.delete(runSave);
        detachListeners();
    });

    return { confirmLeave };
}

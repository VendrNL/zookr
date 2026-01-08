import { reactive } from "vue";

const state = reactive({
    open: false,
    message: "",
    onConfirm: null,
    onSave: null,
    onCancel: null,
});

export const useDirtyConfirmState = () => state;

export const openDirtyConfirm = (message, onConfirm, onSave, onCancel) => {
    state.open = true;
    state.message = message;
    state.onConfirm = onConfirm;
    state.onSave = onSave;
    state.onCancel = onCancel;
};

export const closeDirtyConfirm = () => {
    state.open = false;
    state.message = "";
    state.onConfirm = null;
    state.onSave = null;
    state.onCancel = null;
};

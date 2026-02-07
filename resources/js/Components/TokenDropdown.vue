<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

const model = defineModel({
    default: null,
});

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: "Selecteer een optie",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const rootRef = ref(null);
const open = ref(false);

const normalizeOption = (option) => {
    if (option && typeof option === "object" && "value" in option) {
        return {
            value: option.value,
            label: option.label ?? String(option.value ?? ""),
            disabled: Boolean(option.disabled),
        };
    }

    return {
        value: option,
        label: String(option ?? ""),
        disabled: false,
    };
};

const normalizedOptions = computed(() => props.options.map(normalizeOption));

const valueEquals = (left, right) => {
    if (left === right) return true;
    if (left === null || left === undefined) return right === null || right === undefined;
    if (right === null || right === undefined) return false;
    return String(left) === String(right);
};

const selectedOption = computed(() =>
    normalizedOptions.value.find((option) => valueEquals(option.value, model.value))
);

const buttonLabel = computed(() => selectedOption.value?.label || props.placeholder);

const toggleMenu = () => {
    if (props.disabled) return;
    open.value = !open.value;
};

const selectOption = (option) => {
    if (option.disabled) return;
    model.value = option.value;
    open.value = false;
};

const closeMenu = () => {
    open.value = false;
};

const handleOutsideClick = (event) => {
    if (!rootRef.value) return;
    if (!rootRef.value.contains(event.target)) {
        closeMenu();
    }
};

const handleEscape = (event) => {
    if (event.key === "Escape") {
        closeMenu();
    }
};

onMounted(() => {
    document.addEventListener("click", handleOutsideClick);
    document.addEventListener("keydown", handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleOutsideClick);
    document.removeEventListener("keydown", handleEscape);
});
</script>

<template>
    <div ref="rootRef" class="relative mt-1">
        <button
            :id="id"
            type="button"
            class="inline-flex w-full items-center justify-between rounded-base border border-default-medium bg-neutral-secondary-medium px-3 py-2.5 text-left text-sm text-heading shadow-xs focus:outline-none focus:ring-1 focus:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
            :aria-expanded="open ? 'true' : 'false'"
            :disabled="disabled"
            @click="toggleMenu"
        >
            <span :class="selectedOption ? 'text-heading' : 'text-body'">
                {{ buttonLabel }}
            </span>
            <svg
                class="h-4 w-4 text-gray-500"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute z-20 mt-1 w-full rounded-md bg-white py-1 ring-1 ring-black ring-opacity-5"
        >
            <button
                v-for="option in normalizedOptions"
                :key="`${id || 'token-dropdown'}-${String(option.value)}`"
                type="button"
                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="option.disabled"
                @click="selectOption(option)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>

<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
    phone: page.props.auth.user.phone ?? "",
    linkedin_url: page.props.auth.user.linkedin_url ?? "",
    avatar: null,
    remove_avatar: false,
});

const avatarPreview = ref(null);
const isDragging = ref(false);
const LINKEDIN_PREFIX = "https://www.linkedin.com/in/";
const linkedinHandle = ref(
    form.linkedin_url?.startsWith(LINKEDIN_PREFIX)
        ? form.linkedin_url.replace(LINKEDIN_PREFIX, "")
        : form.linkedin_url?.replace("linkedin.com/in/", "") || ""
);
const avatarInput = ref(null);

const submit = (onSuccess) => {
    form
        .transform((data) => ({
            ...data,
            linkedin_url: linkedinHandle.value
                ? `${LINKEDIN_PREFIX}${linkedinHandle.value}`
                : null,
            _method: "patch",
        }))
        .post(route("profile.update"), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.remove_avatar = false;
                if (typeof onSuccess === "function") {
                    onSuccess();
                }
            },
            onFinish: () => form.reset("avatar"),
        });
};

useDirtyConfirm(form, undefined, {
    onSave: (done) => submit(done),
});

const setAvatarFile = (file) => {
    if (!file) {
        return;
    }
    if (file.type && !file.type.startsWith("image/")) {
        return;
    }
    form.avatar = file;
    form.remove_avatar = false;
    avatarPreview.value = URL.createObjectURL(file);
};

const handleAvatar = (event) => {
    const file = event.target.files[0] ?? null;
    setAvatarFile(file);
};

const onDragOver = () => {
    isDragging.value = true;
};

const onDragLeave = () => {
    isDragging.value = false;
};

const onDrop = (event) => {
    const file = event.dataTransfer?.files?.[0] ?? null;
    setAvatarFile(file);
    isDragging.value = false;
};

const openAvatarPicker = () => {
    avatarInput.value?.click();
};

const clearAvatar = () => {
    form.avatar = null;
    form.remove_avatar = true;
    avatarPreview.value = null;
    submit();
};
</script>

<template>
<section>
    <header>
        <h2 class="text-xl font-medium text-gray-900">
            Profielgegevens
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Werk je profielgegevens, e-mail en avatar bij.
        </p>
    </header>

    <form
        @submit.prevent="submit"
        class="mt-6 space-y-6"
    >
        <div class="flex items-center gap-4">
            <div
                class="h-20 w-20 overflow-hidden rounded-full border border-gray-200 bg-gray-50 shadow-sm"
                :class="{ 'ring-2 ring-blue-500 ring-offset-2': isDragging }"
                @click="openAvatarPicker"
                role="button"
                tabindex="0"
                @keydown.enter.space.prevent="openAvatarPicker"
                @dragover.prevent="onDragOver"
                @dragenter.prevent="onDragOver"
                @dragleave.prevent="onDragLeave"
                @drop.prevent="onDrop"
            >
                <img
                    v-if="avatarPreview || user.avatar_url"
                    :src="avatarPreview || user.avatar_url"
                    alt="Avatar"
                    class="h-full w-full object-cover pointer-events-none"
                />
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center text-sm text-gray-500 cursor-pointer"
                >
                    Kies foto
                </div>
            </div>
            <div class="space-y-2">
                <input
                    ref="avatarInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handleAvatar"
                />
                <p class="text-sm text-gray-500">
                    Klik op de cirkel om een avatar te kiezen.
                </p>
                <InputError class="mt-1" :message="form.errors.avatar" />
            </div>
        </div>

        <div>
            <InputLabel for="name" value="Naam" />

            <TextInput
                id="name"
                type="text"
                class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel for="email" value="E-mail" />

            <TextInput
                id="email"
                type="email"
                class="mt-1 block w-full"
                v-model="form.email"
                required
                autocomplete="username"
            />

            <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <div>
            <InputLabel for="phone" value="Telefoonnummer" />

            <TextInput
                id="phone"
                type="text"
                class="mt-1 block w-full"
                v-model="form.phone"
                autocomplete="tel"
            />

            <InputError class="mt-2" :message="form.errors.phone" />
        </div>

        <div>
            <InputLabel for="linkedin_url" value="LinkedIn-profiel" />
            <div
                class="mt-1 flex w-full min-w-0 items-center rounded-base border border-default-medium bg-neutral-secondary-medium shadow-xs focus-within:border-brand focus-within:ring-1 focus-within:ring-brand"
            >
                <span class="select-none pl-3 pr-0 py-2.5 text-sm text-body">
                    {{ LINKEDIN_PREFIX }}
                </span>
                <input
                    id="linkedin_url"
                    type="text"
                    class="flex-1 min-w-0 border-0 bg-transparent pl-0 pr-3 py-2.5 text-sm text-heading focus:border-0 focus:outline-none focus:ring-0 placeholder:text-body"
                    v-model="linkedinHandle"
                    autocomplete="url"
                    placeholder="gebruikersnaam"
                />
            </div>
            <InputError class="mt-2" :message="form.errors.linkedin_url" />
        </div>

        <div v-if="mustVerifyEmail && user.email_verified_at === null">
            <p class="mt-2 text-sm text-gray-800">
                Je e-mailadres is niet geverifieerd.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Klik hier om opnieuw een verificatie-mail te sturen.
                </Link>
            </p>

            <div
                v-show="status === 'verification-link-sent'"
                class="mt-2 text-sm font-medium text-green-600"
            >
                Er is een nieuwe verificatielink verzonden naar je e-mailadres.
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <PrimaryButton :disabled="form.processing">Opslaan</PrimaryButton>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                    Opgeslagen.
                </p>
            </Transition>
        </div>
    </form>
</section>
</template>

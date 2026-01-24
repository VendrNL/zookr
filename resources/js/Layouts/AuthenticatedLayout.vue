<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FormActions from '@/Components/FormActions.vue';
import MaterialIcon from '@/Components/MaterialIcon.vue';
import Modal from '@/Components/Modal.vue';
import ModalCard from '@/Components/ModalCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppFooter from '@/Components/AppFooter.vue';
import { Link } from '@inertiajs/vue3';
import { closeDirtyConfirm, useDirtyConfirmState } from '@/Stores/dirtyConfirm';

const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(false);
const dirtyConfirm = useDirtyConfirmState();

const sidebarWidthClass = computed(() =>
    isSidebarCollapsed.value ? 'w-20' : 'w-64',
);

const sidebarLabelClass = computed(() =>
    isSidebarCollapsed.value
        ? 'w-0 opacity-0'
        : 'w-auto opacity-100',
);

const contentPaddingClass = computed(() =>
    isSidebarCollapsed.value ? 'lg:pl-20' : 'lg:pl-64',
);

const handleResize = () => {
    isSidebarOpen.value = window.innerWidth >= 1024;
};

const closeSidebar = () => {
    if (window.innerWidth < 1024) {
        isSidebarOpen.value = false;
    }
};

const handleStay = () => {
    const action = dirtyConfirm.onCancel;
    closeDirtyConfirm();
    if (typeof action === 'function') {
        action();
    }
};

const handleSave = () => {
    const action = dirtyConfirm.onSave;
    closeDirtyConfirm();
    if (typeof action === 'function') {
        action();
    }
};

const handleCancel = () => {
    closeDirtyConfirm();
};

const handleLeave = () => {
    const action = dirtyConfirm.onConfirm;
    closeDirtyConfirm();
    if (typeof action === 'function') {
        action();
    }
};

onMounted(() => {
    handleResize();
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav
                class="fixed left-0 right-0 top-0 z-50 border-b border-gray-100 bg-white"
            >
                <div class="mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-200 lg:hidden"
                            aria-label="Open menu"
                            @click="isSidebarOpen = true"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                        <div class="flex shrink-0 items-center gap-3">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo
                                    class="block h-12 w-auto fill-current text-gray-800"
                                />
                            </Link>
                            <div
                                v-if="$slots.header"
                                class="pl-4 pr-4 text-lg font-semibold text-gray-900 translate-y-[20%] sm:pl-[5rem] max-w-[60vw] truncate"
                            >
                                <slot name="header" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="$page.props.auth?.user"
                        class="flex items-center gap-3"
                    >
                        <div class="hidden items-center lg:flex">
                            <div class="relative">
                                <MaterialIcon
                                    name="search"
                                    class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-gray-400"
                                />
                                <input
                                    type="search"
                                    placeholder="Zoeken"
                                    class="w-72 rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                />
                            </div>
                        </div>

                        <div class="relative">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-full border border-transparent bg-white px-2 py-1 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                <span class="mr-2 inline-flex h-7 w-7 overflow-hidden rounded-full bg-gray-100">
                                                    <img
                                                        v-if="$page.props.auth.user.avatar_url"
                                                        :src="$page.props.auth.user.avatar_url"
                                                        alt=""
                                                        class="h-full w-full object-cover"
                                                    />
                                                </span>
                                                <span class="hidden sm:inline">
                                                    {{ $page.props.auth.user.name }}
                                                </span>

                                                <svg
                                                    class="-me-0.5 ms-2 hidden h-4 w-4 sm:inline"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Mijn profiel
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('organization.edit')"
                                        >
                                            Mijn organisatie
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('specialism.edit')"
                                        >
                                            Mijn specialisme
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Uitloggen
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div
                v-if="isSidebarOpen"
                class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
                @click="isSidebarOpen = false"
            ></div>

            <aside
                class="fixed bottom-0 left-0 top-16 z-40 flex flex-col border-r border-gray-200 bg-white shadow-xl transition-all duration-200"
                :class="[
                    sidebarWidthClass,
                    isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
                    'lg:translate-x-0',
                ]"
                aria-label="Sidebar"
            >
                <div class="flex h-full flex-col gap-4 px-3 py-4">
                    <div
                        class="flex items-center justify-between gap-2 px-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
                        :class="isSidebarCollapsed ? 'justify-center' : ''"
                    >
                        <span
                            class="transition-all duration-200"
                            :class="sidebarLabelClass"
                        >
                            Navigatie
                        </span>
                        <button
                            type="button"
                            class="hidden rounded-full p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:inline-flex"
                            aria-label="Toggle sidebar"
                            @click="isSidebarCollapsed = !isSidebarCollapsed"
                        >
                            <svg
                                class="h-5 w-5 transition-transform duration-200"
                                :class="isSidebarCollapsed ? 'rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </button>
                    </div>

                    <nav class="flex flex-1 flex-col gap-1">
                        <Link
                            :href="route('dashboard')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="route().current('dashboard')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            @click="closeSidebar"
                        >
                            <MaterialIcon name="public" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Dashboard
                            </span>
                        </Link>
                        <Link
                            v-if="$page.props.auth?.user?.is_admin"
                            :href="route('admin.organizations.index')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="route().current('admin.organizations.*')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            @click="closeSidebar"
                        >
                            <MaterialIcon name="link" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Organisaties
                            </span>
                        </Link>
                        <Link
                            v-if="$page.props.auth?.user?.is_admin"
                            :href="route('admin.users.index')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="route().current('admin.users.*')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            @click="closeSidebar"
                        >
                            <MaterialIcon name="person_add" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Gebruikers
                            </span>
                        </Link>
                        <Link
                            :href="route('profile.edit')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="route().current('profile.edit')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            @click="closeSidebar"
                        >
                            <MaterialIcon name="mail" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Mijn profiel
                            </span>
                        </Link>
                        <Link
                            :href="route('organization.edit')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="route().current('organization.edit')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            @click="closeSidebar"
                        >
                            <MaterialIcon name="public" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Mijn organisatie
                            </span>
                        </Link>
                        <Link
                            :href="route('specialism.edit')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="route().current('specialism.edit')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            @click="closeSidebar"
                        >
                            <MaterialIcon name="reply" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Mijn specialisme
                            </span>
                        </Link>
                    </nav>

                    <div class="mt-auto">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 lg:hidden"
                            @click="isSidebarOpen = false"
                        >
                            <MaterialIcon name="close" class="h-5 w-5" />
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Sluiten
                            </span>
                        </button>
                    </div>
                </div>
            </aside>

            <div class="flex min-h-screen flex-col pt-16 transition-all duration-200">
                <main class="flex-1 px-4 pb-10 pt-8 sm:px-6 lg:px-8" :class="contentPaddingClass">
                    <slot />
                </main>
                <AppFooter />
            </div>
        </div>

        <Modal :show="dirtyConfirm.open" maxWidth="md" @close="handleStay">
            <ModalCard>
                <template #title>
                    <h2 class="text-2xl font-semibold text-gray-900">
                        Gegevens zijn gewijzigd
                    </h2>
                </template>
                <template #body>
                    <p class="text-base font-normal text-gray-900">
                        {{ dirtyConfirm.message }}
                    </p>
                </template>
                <template #actions>
                    <FormActions align="center">
                        <SecondaryButton type="button" @click="handleLeave">
                            Niet opslaan
                        </SecondaryButton>
                        <PrimaryButton type="button" @click="handleSave">
                            Wijzigingen opslaan
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="handleCancel">
                            Annuleren
                        </SecondaryButton>
                    </FormActions>
                </template>
            </ModalCard>
        </Modal>
    </div>
</template>

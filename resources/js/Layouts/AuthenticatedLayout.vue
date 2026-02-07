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
                            <Link :href="route('search-requests.index')">
                                <ApplicationLogo
                                    class="block h-12 w-auto fill-current text-gray-800"
                                />
                            </Link>
                            <div
                                v-if="$slots.header"
                                class="min-w-0 pl-4 pr-4 text-lg font-semibold text-gray-900 translate-y-[20%] sm:pl-[5rem]"
                            >
                                <slot name="header" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="$page.props.auth?.user"
                        class="flex items-center gap-3"
                    >
                        <div class="relative">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-md !bg-white px-3 py-2 text-sm font-medium leading-4 !text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 hover:text-gray-900 focus:outline-none"
                                            >
                                                <span class="inline-flex h-7 w-7 shrink-0 overflow-hidden rounded-full bg-gray-100">
                                                    <img
                                                        v-if="$page.props.auth.user.avatar_url"
                                                        :src="$page.props.auth.user.avatar_url"
                                                        alt=""
                                                        class="h-full w-full object-cover"
                                                    />
                                                </span>
                                                <span class="hidden max-w-[180px] truncate lg:inline">
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
                                            :href="route('makelaardij.edit')"
                                        >
                                            Mijn kantoor
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

                    <nav class="flex flex-1 flex-col gap-2">
                        <Link
                            :href="route('search-requests.index')"
                            class="group relative flex h-14 items-center rounded-lg text-sm font-medium transition"
                            :class="[
                                route().current('search-requests.*')
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                isSidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                            ]"
                            @click="closeSidebar"
                        >
                            <svg class="h-[42px] w-[42px] text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 11H4m15.5 5a.5.5 0 0 0 .5-.5V8a1 1 0 0 0-1-1h-3.75a1 1 0 0 1-.829-.44l-1.436-2.12a1 1 0 0 0-.828-.44H8a1 1 0 0 0-1 1M4 9v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-7a1 1 0 0 0-1-1h-3.75a1 1 0 0 1-.829-.44L9.985 8.44A1 1 0 0 0 9.157 8H5a1 1 0 0 0-1 1Z"/>
                            </svg>
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Zoekvragen
                            </span>
                            <span
                                v-if="isSidebarCollapsed"
                                class="pointer-events-none absolute left-full top-1/2 ml-4 -translate-y-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100"
                            >
                                Zoekvragen
                                <span class="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 border-8 border-transparent border-r-gray-900"></span>
                            </span>
                        </Link>
                        <template v-if="$page.props.auth?.user?.is_admin">
                            <div
                                v-if="!isSidebarCollapsed"
                                class="px-3 text-xs font-semibold uppercase tracking-wide text-gray-400"
                            >
                                Admin
                            </div>
                            <Link
                                :href="route('admin.organizations.index')"
                                class="group relative flex h-14 items-center rounded-lg text-sm font-medium transition"
                                :class="[
                                    route().current('admin.organizations.*')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                    isSidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                                ]"
                                @click="closeSidebar"
                            >
                                <svg class="h-[42px] w-[42px] text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-width="1" d="M3 11h18M3 15h18m-9-4v8m-8 0h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/>
                                </svg>
                                <span
                                    class="transition-all duration-200"
                                    :class="sidebarLabelClass"
                                >
                                    Makelaars
                                </span>
                                <span
                                    v-if="isSidebarCollapsed"
                                    class="pointer-events-none absolute left-full top-1/2 ml-4 -translate-y-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100"
                                >
                                    Makelaars
                                    <span class="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 border-8 border-transparent border-r-gray-900"></span>
                                </span>
                            </Link>
                            <Link
                                :href="route('admin.users.index')"
                                class="group relative flex h-14 items-center rounded-lg text-sm font-medium transition"
                                :class="[
                                    route().current('admin.users.*')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                    isSidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                                ]"
                                @click="closeSidebar"
                            >
                                <svg class="h-[42px] w-[42px] text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-width="1" d="M16 19h4a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-2m-2.236-4a3 3 0 1 0 0-4M3 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                <span
                                    class="transition-all duration-200"
                                    :class="sidebarLabelClass"
                                >
                                    Gebruikers
                                </span>
                                <span
                                    v-if="isSidebarCollapsed"
                                    class="pointer-events-none absolute left-full top-1/2 ml-4 -translate-y-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100"
                                >
                                    Gebruikers
                                    <span class="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 border-8 border-transparent border-r-gray-900"></span>
                                </span>
                            </Link>
                        </template>
                        <div
                            v-if="!isSidebarCollapsed"
                            class="px-3 text-xs font-semibold uppercase tracking-wide text-gray-400"
                        >
                            Mijn Zookr
                        </div>
                        <Link
                            :href="route('profile.edit')"
                            class="group relative flex h-14 items-center rounded-lg text-sm font-medium transition"
                            :class="[
                                route().current('profile.edit')
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                isSidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                            ]"
                            @click="closeSidebar"
                        >
                            <svg class="h-[42px] w-[42px] text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                            </svg>
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Mijn profiel
                            </span>
                            <span
                                v-if="isSidebarCollapsed"
                                class="pointer-events-none absolute left-full top-1/2 ml-4 -translate-y-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100"
                            >
                                Mijn profiel
                                <span class="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 border-8 border-transparent border-r-gray-900"></span>
                            </span>
                        </Link>
                        <Link
                            :href="route('makelaardij.edit')"
                            class="group relative flex h-14 items-center rounded-lg text-sm font-medium transition"
                            :class="[
                                route().current('makelaardij.edit')
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                isSidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                            ]"
                            @click="closeSidebar"
                        >
                            <svg class="h-[42px] w-[42px] text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M6 4h12M6 4v16M6 4H5m13 0v16m0-16h1m-1 16H6m12 0h1M6 20H5M9 7h1v1H9V7Zm5 0h1v1h-1V7Zm-5 4h1v1H9v-1Zm5 0h1v1h-1v-1Zm-3 4h2a1 1 0 0 1 1 1v4h-4v-4a1 1 0 0 1 1-1Z"/>
                            </svg>
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Mijn kantoor
                            </span>
                            <span
                                v-if="isSidebarCollapsed"
                                class="pointer-events-none absolute left-full top-1/2 ml-4 -translate-y-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100"
                            >
                                Mijn kantoor
                                <span class="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 border-8 border-transparent border-r-gray-900"></span>
                            </span>
                        </Link>
                        <Link
                            :href="route('specialism.edit')"
                            class="group relative flex h-14 items-center rounded-lg text-sm font-medium transition"
                            :class="[
                                route().current('specialism.edit')
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                isSidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                            ]"
                            @click="closeSidebar"
                        >
                            <svg class="h-[42px] w-[42px] text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-width="1" d="M4.37 7.657c2.063.528 2.396 2.806 3.202 3.87 1.07 1.413 2.075 1.228 3.192 2.644 1.805 2.289 1.312 5.705 1.312 6.705M20 15h-1a4 4 0 0 0-4 4v1M8.587 3.992c0 .822.112 1.886 1.515 2.58 1.402.693 2.918.351 2.918 2.334 0 .276 0 2.008 1.972 2.008 2.026.031 2.026-1.678 2.026-2.008 0-.65.527-.9 1.177-.9H20M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            <span
                                class="transition-all duration-200"
                                :class="sidebarLabelClass"
                            >
                                Mijn specialisme
                            </span>
                            <span
                                v-if="isSidebarCollapsed"
                                class="pointer-events-none absolute left-full top-1/2 ml-4 -translate-y-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100"
                            >
                                Mijn specialisme
                                <span class="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 border-8 border-transparent border-r-gray-900"></span>
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


<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FormActions from '@/Components/FormActions.vue';
import MaterialIcon from '@/Components/MaterialIcon.vue';
import Modal from '@/Components/Modal.vue';
import ModalCard from '@/Components/ModalCard.vue';
import NavLink from '@/Components/NavLink.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppFooter from '@/Components/AppFooter.vue';
import { Link } from '@inertiajs/vue3';
import { closeDirtyConfirm, useDirtyConfirmState } from '@/Stores/dirtyConfirm';

const showingNavigationDropdown = ref(false);
const showPrimaryNav = ref(true);
const lastScrollY = ref(0);
const ticking = ref(false);
const dirtyConfirm = useDirtyConfirmState();

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

const updatePrimaryNav = () => {
    const currentY = window.scrollY || 0;
    const diff = currentY - lastScrollY.value;

    if (Math.abs(diff) < 8) {
        return;
    }

    showPrimaryNav.value = diff <= 0;
    lastScrollY.value = currentY;
};

const handleScroll = () => {
    if (ticking.value) return;
    ticking.value = true;
    window.requestAnimationFrame(() => {
        updatePrimaryNav();
        ticking.value = false;
    });
};

onMounted(() => {
    lastScrollY.value = window.scrollY || 0;
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div>
        <div class="flex min-h-screen flex-col bg-gray-100">
            <nav
                class="sticky top-0 z-40 border-b border-gray-100 bg-white transition-transform duration-200"
                :class="showPrimaryNav ? 'translate-y-0' : '-translate-y-full'"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth?.user?.is_admin"
                                    :href="route('admin.organizations.index')"
                                    :active="route().current('admin.organizations.*')"
                                >
                                    Organisaties
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth?.user?.is_admin"
                                    :href="route('admin.users.index')"
                                    :active="route().current('admin.users.*')"
                                >
                                    Gebruikers
                                </NavLink>
                                <NavLink
                                    :href="route('profile.edit')"
                                    :active="route().current('profile.edit')"
                                >
                                    Mijn profiel
                                </NavLink>
                                <NavLink
                                    :href="route('organization.edit')"
                                    :active="route().current('organization.edit')"
                                >
                                    Mijn organisatie
                                </NavLink>
                                <NavLink
                                    :href="route('specialism.edit')"
                                    :active="route().current('specialism.edit')"
                                >
                                    Mijn specialisme
                                </NavLink>
                            </div>
                        </div>

                        <div
                            v-if="$page.props.auth?.user"
                            class="hidden sm:ms-6 sm:flex sm:items-center"
                        >
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                <span class="mr-2 inline-flex h-7 w-7 overflow-hidden rounded-full bg-gray-100">
                                                    <img
                                                        v-if="$page.props.auth.user.avatar_url"
                                                        :src="$page.props.auth.user.avatar_url"
                                                        alt=""
                                                        class="h-full w-full object-cover"
                                                    />
                                                </span>
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
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

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </nav>

            <!-- Responsive Navigation Overlay -->
            <div
                :class="{
                    'pointer-events-auto translate-x-0': showingNavigationDropdown,
                    'pointer-events-none translate-x-full': !showingNavigationDropdown,
                }"
                class="fixed inset-0 z-50 bg-white transition-transform duration-200 sm:hidden"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900"
                    aria-label="Menu sluiten"
                    @click="showingNavigationDropdown = false"
                >
                    <MaterialIcon name="close" class="h-5 w-5" />
                </button>
                <div class="flex h-full flex-col bg-white px-6 pb-8 pt-20">
                    <div class="space-y-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            @click="showingNavigationDropdown = false"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth?.user?.is_admin"
                            :href="route('admin.organizations.index')"
                            @click="showingNavigationDropdown = false"
                        >
                            Organisaties
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth?.user?.is_admin"
                            :href="route('admin.users.index')"
                            @click="showingNavigationDropdown = false"
                        >
                            Gebruikers
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('profile.edit')"
                            @click="showingNavigationDropdown = false"
                        >
                            Mijn profiel
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('organization.edit')"
                            @click="showingNavigationDropdown = false"
                        >
                            Mijn organisatie
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('specialism.edit')"
                            @click="showingNavigationDropdown = false"
                        >
                            Mijn specialisme
                        </ResponsiveNavLink>
                    </div>

                    <div
                        v-if="$page.props.auth?.user"
                        class="mt-6 border-t border-gray-200 pt-6"
                    >
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                            @click="showingNavigationDropdown = false"
                        >
                            Uitloggen
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>

            <!-- Page Heading -->
            <header
                class="sticky top-0 z-30 bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1">
                <slot />
            </main>
            <AppFooter />
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

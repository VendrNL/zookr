<script setup>
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto flex max-w-screen-xl items-center justify-between gap-6 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-3 text-sm text-gray-600">
                <Link :href="route('search-requests.index')" aria-label="Zookr">
                    <ApplicationLogo class="h-6 w-auto grayscale opacity-70" />
                </Link>
                <span>© 2026 Zookr. Alle rechten voorbehouden.</span>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-600">
                <Link :href="route('legal.privacy')" class="hover:text-blue-600">
                    Privacyverklaring
                </Link>
                <Link :href="route('legal.terms')" class="hover:text-blue-600">
                    Gebruiksvoorwaarden
                </Link>
                <a href="#" class="hover:text-blue-600">
                    Over Zookr
                </a>
                <Link :href="route('legal.contact')" class="hover:text-blue-600">
                    Contact
                </Link>
                <a href="#" class="ml-4 text-gray-500 hover:text-blue-600" aria-label="LinkedIn">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM0 8.98h5v15H0v-15zM7.98 8.98h4.79v2.05h.07c.67-1.27 2.3-2.61 4.74-2.61 5.07 0 6.01 3.34 6.01 7.69v8.87h-5v-7.86c0-1.88-.03-4.29-2.62-4.29-2.62 0-3.02 2.05-3.02 4.16v7.99h-5v-15z" />
                    </svg>
                </a>
            </div>
        </div>
    </footer>
</template>

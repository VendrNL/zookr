<script setup>
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <ApplicationLogo class="h-6 w-6 fill-current text-gray-400" />
                <span>Zookr</span>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                <Link
                    :href="route('legal.terms')"
                    class="hover:text-gray-900"
                >
                    Gebruiksvoorwaarden
                </Link>
                <Link
                    :href="route('legal.privacy')"
                    class="hover:text-gray-900"
                >
                    Privacyverklaring
                </Link>
                <Link
                    :href="route('legal.contact')"
                    class="hover:text-gray-900"
                >
                    Contact
                </Link>
            </div>
        </div>
    </footer>
</template>

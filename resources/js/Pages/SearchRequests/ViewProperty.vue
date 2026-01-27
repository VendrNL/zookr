<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MaterialIcon from "@/Components/MaterialIcon.vue";
import PageContainer from "@/Components/PageContainer.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    item: Object,
    property: Object,
    media: Object,
    contact: Object,
    can: {
        type: Object,
        default: () => ({}),
    },
});

const images = computed(() => props.media?.images ?? []);
const hasImages = computed(() => images.value.length > 0);
const currentIndex = ref(0);
const status = ref(props.property?.status ?? null);
const statusBusy = ref(false);

const activeImage = computed(() => images.value[currentIndex.value] ?? "");
const canPrev = computed(() => currentIndex.value > 0);
const canNext = computed(() => currentIndex.value < images.value.length - 1);

const goPrev = () => {
    if (!canPrev.value) return;
    currentIndex.value -= 1;
};

const goNext = () => {
    if (!canNext.value) return;
    currentIndex.value += 1;
};

const title = computed(() => props.property?.name || props.property?.address || "-");
const subtitle = computed(() => {
    if (props.property?.name) {
        const address = props.property?.address ?? "";
        const city = props.property?.city ?? "";
        return `${address}${city ? `, ${city}` : ""}`;
    }
    return props.property?.city ?? "";
});

const formatNumber = (value) => {
    if (value === null || value === undefined || value === "") return "-";
    return new Intl.NumberFormat("nl-NL").format(value);
};

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === "") return "-";
    return new Intl.NumberFormat("nl-NL", {
        style: "currency",
        currency: "EUR",
    }).format(value);
};

const rentLabel = computed(() => {
    const perM2 = formatCurrency(props.property?.rent_price_per_m2);
    const parking = formatCurrency(props.property?.rent_price_parking);
    return `${perM2} p/m² • Parkeren ${parking}`;
});

const contactInitials = computed(() => {
    const name = props.contact?.name ?? "";
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "";
    const first = parts[0]?.[0] ?? "";
    const last = parts.length > 1 ? parts[parts.length - 1]?.[0] ?? "" : "";
    return `${first}${last}`.toUpperCase();
});

const statusButtonClass = (value) => {
    const base =
        "inline-flex h-12 w-12 items-center justify-center rounded-md border transition";
    const isActive = status.value === value;
    if (value === "geschikt") {
        return isActive
            ? `${base} border-emerald-500 bg-emerald-100 text-emerald-700`
            : `${base} border-gray-300 bg-white text-gray-600 hover:bg-gray-50`;
    }
    return isActive
        ? `${base} border-rose-500 bg-rose-100 text-rose-700`
        : `${base} border-gray-300 bg-white text-gray-600 hover:bg-gray-50`;
};

const setStatus = (nextStatus) => {
    if (!props.can?.setStatus || statusBusy.value) return;
    statusBusy.value = true;
    router.patch(
        route("search-requests.properties.status", [props.item.id, props.property.id]),
        { status: nextStatus },
        {
            preserveScroll: true,
            onSuccess: () => {
                status.value = nextStatus;
            },
            onFinish: () => {
                statusBusy.value = false;
            },
        }
    );
};
</script>

<template>
    <Head title="Aangeboden pand" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="relative min-w-0 max-w-[60vw] overflow-hidden whitespace-nowrap pr-6">
                        <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                            {{ title }}
                        </h2>
                        <span class="pointer-events-none absolute right-0 top-0 h-full w-8 bg-gradient-to-l from-white"></span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('search-requests.show', { search_request: item.id, tab: 'offers' })"
                        class="text-sm font-semibold text-gray-700 hover:text-gray-900"
                    >
                        <span class="hidden sm:inline">Terug naar zoekvraag</span>
                        <span class="sr-only">Terug naar zoekvraag</span>
                        <MaterialIcon name="reply" class="h-5 w-5 sm:hidden" />
                    </Link>
                    <Link
                        v-if="can.update"
                        :href="route('search-requests.properties.edit', [item.id, property.id])"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Bewerken
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <PageContainer class="max-w-5xl">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                    <div class="relative">
                        <div class="space-y-1 pr-28">
                            <h1 class="text-3xl font-semibold text-gray-900">{{ title }}</h1>
                            <p class="text-sm text-gray-500">{{ subtitle }}</p>
                        </div>
                        <div v-if="can.setStatus" class="absolute right-0 top-0 flex items-center gap-2">
                            <button
                                type="button"
                                :class="statusButtonClass('geschikt')"
                                :disabled="statusBusy"
                                aria-label="Geschikt"
                                @click="setStatus('geschikt')"
                            >
                                <svg class="w-12 h-12 text-current" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 11c.889-.086 1.416-.543 2.156-1.057a22.323 22.323 0 0 0 3.958-5.084 1.6 1.6 0 0 1 .582-.628 1.549 1.549 0 0 1 1.466-.087c.205.095.388.233.537.406a1.64 1.64 0 0 1 .384 1.279l-1.388 4.114M7 11H4v6.5A1.5 1.5 0 0 0 5.5 19v0A1.5 1.5 0 0 0 7 17.5V11Zm6.5-1h4.915c.286 0 .372.014.626.15.254.135.472.332.637.572a1.874 1.874 0 0 1 .215 1.673l-2.098 6.4C17.538 19.52 17.368 20 16.12 20c-2.303 0-4.79-.943-6.67-1.475"/>
                                </svg>
                            </button>
                            <button
                                type="button"
                                :class="statusButtonClass('ongeschikt')"
                                :disabled="statusBusy"
                                aria-label="Ongeschikt"
                                @click="setStatus('ongeschikt')"
                            >
                                <svg class="w-12 h-12 text-current" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 13c-.889.086-1.416.543-2.156 1.057a22.322 22.322 0 0 0-3.958 5.084 1.6 1.6 0 0 1-.582.628 1.549 1.549 0 0 1-1.466.087 1.587 1.587 0 0 1-.537-.406 1.666 1.666 0 0 1-.384-1.279l1.389-4.114M17 13h3V6.5A1.5 1.5 0 0 0 18.5 5v0A1.5 1.5 0 0 0 17 6.5V13Zm-6.5 1H5.585c-.286 0-.372-.014-.626-.15a1.797 1.797 0 0 1-.637-.572 1.873 1.873 0 0 1-.215-1.673l2.098-6.4C6.462 4.48 6.632 4 7.88 4c2.302 0 4.79.943 6.67 1.475"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <section class="mt-6">
                        <div class="relative overflow-hidden rounded-2xl bg-gray-100">
                            <div class="aspect-[16/9] w-full">
                                <img
                                    v-if="hasImages"
                                    :src="activeImage"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-sm text-gray-500"
                                >
                                    Geen afbeeldingen beschikbaar
                                </div>
                            </div>
                            <div
                                v-if="hasImages && images.length > 1"
                                class="absolute inset-0 flex items-center justify-between px-4"
                            >
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/80 text-gray-700 shadow hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canPrev"
                                    @click="goPrev"
                                >
                                    ‹
                                </button>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/80 text-gray-700 shadow hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canNext"
                                    @click="goNext"
                                >
                                    ›
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="hasImages && images.length > 1"
                            class="mt-3 hidden gap-2 overflow-x-auto pb-2 sm:flex"
                        >
                            <button
                                v-for="(image, index) in images"
                                :key="image"
                                type="button"
                                class="h-14 w-24 shrink-0 overflow-hidden rounded-lg border-2"
                                :class="index === currentIndex ? 'border-gray-900' : 'border-transparent'"
                                @click="currentIndex = index"
                            >
                                <img :src="image" alt="" class="h-full w-full object-cover" />
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="property.url || media?.brochure || media?.drawings?.length"
                        class="mt-6 text-sm"
                    >
                        <div class="flex flex-wrap items-center gap-4">
                            <a
                                v-if="property.url"
                                :href="property.url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 font-medium text-blue-700 hover:underline"
                            >
                                <svg class="w-8 h-8 text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13.213 9.787a3.391 3.391 0 0 0-4.795 0l-3.425 3.426a3.39 3.39 0 0 0 4.795 4.794l.321-.304m-.321-4.49a3.39 3.39 0 0 0 4.795 0l3.424-3.426a3.39 3.39 0 0 0-4.794-4.795l-1.028.961"/>
                                </svg>
                                Website openen
                            </a>
                            <a
                                v-if="media?.brochure"
                                :href="media.brochure"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 font-medium text-blue-700 hover:underline"
                            >
                                <svg class="w-8 h-8 text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 17v-5h1.5a1.5 1.5 0 1 1 0 3H5m12 2v-5h2m-2 3h2M5 10V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1v6M5 19v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1M10 3v4a1 1 0 0 1-1 1H5m6 4v5h1.375A1.627 1.627 0 0 0 14 15.375v-1.75A1.627 1.627 0 0 0 12.375 12H11Z"/>
                                </svg>
                                Brochure bekijken
                            </a>
                            <a
                                v-for="drawing in media?.drawings ?? []"
                                :key="drawing"
                                :href="drawing"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 font-medium text-blue-700 hover:underline"
                            >
                                <svg class="w-8 h-8 text-gray-800" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 17v-5h1.5a1.5 1.5 0 1 1 0 3H5m12 2v-5h2m-2 3h2M5 10V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1v6M5 19v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1M10 3v4a1 1 0 0 1-1 1H5m6 4v5h1.375A1.627 1.627 0 0 0 14 15.375v-1.75A1.627 1.627 0 0 0 12.375 12H11Z"/>
                                </svg>
                                Bekijk tekening
                            </a>
                        </div>
                    </section>

                    <section class="mt-6 space-y-4 md:hidden">
                        <div class="text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Oppervlakte</div>
                            {{ formatNumber(property.surface_area) }} m²
                        </div>
                        <div class="text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Parkeerplaatsen</div>
                            {{ formatNumber(property.parking_spots) }}
                        </div>
                        <div class="text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Huurprijs per m²</div>
                            {{ formatCurrency(property.rent_price_per_m2) }}
                        </div>
                        <div class="text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Huurprijs parkeren</div>
                            {{ formatCurrency(property.rent_price_parking) }}
                        </div>
                        <div class="text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Beschikbaarheid</div>
                            {{ property.availability || "-" }}
                        </div>
                        <div class="text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Verwerving</div>
                            {{ property.acquisition === "huur" ? "Huur" : property.acquisition === "koop" ? "Koop" : "-" }}
                        </div>
                    </section>

                    <section class="mt-6 hidden overflow-hidden rounded-2xl border border-gray-200 md:block">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-200">
                                <tr class="bg-gray-50">
                                    <th class="w-1/4 px-3 py-3 text-left font-semibold text-gray-700">Oppervlakte</th>
                                    <td class="px-3 py-3 text-gray-900">
                                        {{ formatNumber(property.surface_area) }} m²
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-700">Parkeerplaatsen</th>
                                    <td class="px-3 py-3 text-gray-900">
                                        {{ formatNumber(property.parking_spots) }}
                                    </td>
                                </tr>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-3 text-left font-semibold text-gray-700">Huurprijs per m²</th>
                                    <td class="px-3 py-3 text-gray-900">
                                        {{ formatCurrency(property.rent_price_per_m2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-700">Huurprijs parkeren</th>
                                    <td class="px-3 py-3 text-gray-900">
                                        {{ formatCurrency(property.rent_price_parking) }}
                                    </td>
                                </tr>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-3 text-left font-semibold text-gray-700">Beschikbaarheid</th>
                                    <td class="px-3 py-3 text-gray-900">
                                        {{ property.availability || "-" }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-700">Verwerving</th>
                                    <td class="px-3 py-3 text-gray-900">
                                        {{ property.acquisition === "huur" ? "Huur" : property.acquisition === "koop" ? "Koop" : "-" }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="mt-6 space-y-3">
                        <h3 class="text-lg font-semibold text-gray-900">Toelichting</h3>
                        <p class="whitespace-pre-line text-sm text-gray-700">
                            {{ property.notes || "Geen toelichting beschikbaar." }}
                        </p>
                    </section>

                    <section class="mt-6">
                        <h3 class="text-lg font-semibold text-gray-900">Contactpersoon</h3>
                        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-gray-200 text-xl font-semibold text-gray-700">
                                <img
                                    v-if="contact?.avatar_url"
                                    :src="contact.avatar_url"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ contactInitials }}</span>
                            </div>
                            <div class="space-y-1">
                                <div class="text-base font-semibold text-gray-900">
                                    {{ contact?.name || "-" }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ contact?.organization?.name || "-" }}
                                </div>
                                <div class="text-sm text-gray-700">
                                    {{ contact?.phone || "-" }}
                                </div>
                                <div class="text-sm text-blue-700">
                                    <a v-if="contact?.email" :href="`mailto:${contact.email}`" class="hover:underline">
                                        {{ contact.email }}
                                    </a>
                                    <span v-else>-</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

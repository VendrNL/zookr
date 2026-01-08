<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="E-mailverificatie" />

        <div class="mb-4 text-sm text-gray-600">
            Bedankt voor je registratie! Voordat je kunt beginnen, vragen we je
            om je e-mailadres te verifieren via de link die we net hebben
            gestuurd. Als je geen e-mail hebt ontvangen, sturen we graag een
            nieuwe.
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            Er is een nieuwe verificatielink gestuurd naar het e-mailadres dat
            je tijdens de registratie hebt opgegeven.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Verificatie-e-mail opnieuw versturen
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >Uitloggen</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>

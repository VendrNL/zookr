<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Inloggen" />

        <div
            v-if="status"
            class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800"
            role="alert"
        >
            {{ status }}
        </div>

        <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl">
            Inloggen op je account
        </h1>

        <form class="space-y-4 md:space-y-6" @submit.prevent="submit">
            <div>
                <label
                    for="email"
                    class="mb-2 block text-sm font-medium text-gray-900"
                >
                    E-mail
                </label>

                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                    placeholder="name@bedrijf.nl"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium text-gray-900"
                >
                    Wachtwoord
                </label>

                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            v-model="form.remember"
                            class="h-4 w-4 rounded border border-gray-300 bg-gray-100 text-blue-600 focus:ring-4 focus:ring-blue-300"
                        />
                    </div>
                    <span class="ms-2 text-sm text-gray-600">
                        Onthoud mij
                    </span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-blue-600 hover:underline"
                >
                    Wachtwoord vergeten?
                </Link>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-blue-600 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300"
                :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing"
            >
                Inloggen
            </button>
        </form>
    </GuestLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageContainer from '@/Components/PageContainer.vue';
import FormSection from '@/Components/FormSection.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profiel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Profiel
            </h2>
        </template>

        <div class="py-12">
            <PageContainer class="space-y-6">
                <FormSection>
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </FormSection>

                <FormSection>
                    <UpdatePasswordForm class="max-w-xl" />
                </FormSection>

                <FormSection>
                    <DeleteUserForm class="max-w-xl" />
                </FormSection>
            </PageContainer>
        </div>
    </AuthenticatedLayout>
</template>

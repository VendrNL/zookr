<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import FormActions from '@/Components/FormActions.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import ModalCard from '@/Components/ModalCard.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import useDirtyConfirm from "@/Composables/useDirtyConfirm";

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

useDirtyConfirm(form, undefined, {
    onSave: (done) => done(),
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-xl font-medium text-gray-900">
                Verwijder account
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Als je account wordt verwijderd, dan worden al je gegevens permanent verwijderd.
            </p>
        </header>

        <div class="flex justify-end">
            <DangerButton @click="confirmUserDeletion">Verwijder account</DangerButton>
        </div>

        <Modal :show="confirmingUserDeletion" maxWidth="md" @close="closeModal">
            <ModalCard>
                <template #title>
                    <h2 class="text-2xl font-semibold text-gray-900">
                        Weet je zeker dat je je account wilt verwijderen?
                    </h2>
                </template>
                <template #body>
                    <p class="text-base font-normal text-gray-900">
                        Als je account wordt verwijderd, dan worden al je gegevens permanent verwijderd.
                    </p>

                    <div class="mt-6 w-full">
                        <InputLabel
                            for="password"
                            value="Wachtwoord"
                            class="sr-only"
                        />

                        <TextInput
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full text-left"
                            placeholder="Wachtwoord"
                            @keyup.enter="deleteUser"
                        />

                        <InputError :message="form.errors.password" class="mt-2" />
                    </div>
                </template>
                <template #actions>
                    <FormActions align="center">
                        <SecondaryButton @click="closeModal">
                            Annuleren
                        </SecondaryButton>

                        <DangerButton
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="deleteUser"
                        >
                            Verwijder account
                        </DangerButton>
                    </FormActions>
                </template>
            </ModalCard>
        </Modal>
    </section>
</template>

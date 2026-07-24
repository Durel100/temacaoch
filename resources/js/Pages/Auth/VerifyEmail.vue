<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post('/email/verification-notification');
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent'
);
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">
        <Head title="Vérification email — TemaCoach" />

        <div class="flex-1 flex flex-col justify-center px-6 py-12 max-w-md mx-auto w-full">

            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-tema-ocre/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">📧</span>
                </div>
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] mb-2">
                    Vérifie ton email
                </h1>
                <p class="text-[13px] text-[#1A2E2B]/60 max-w-xs mx-auto">
                    Un lien de vérification a été envoyé à ton adresse email.
                    Clique dessus pour activer ton compte.
                </p>
            </div>

            <!-- Succès renvoi -->
            <div v-if="verificationLinkSent"
                 class="mb-5 bg-tema-green/10 border border-tema-green/20 rounded-2xl px-4 py-3 text-[13px] text-tema-green font-medium text-center">
                ✓ Un nouveau lien de vérification a été envoyé à ton adresse email.
            </div>

            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-6 space-y-3">

                <button type="button"
                        @click="submit"
                        :disabled="form.processing"
                        class="w-full bg-tema-green text-white font-semibold py-3.5 rounded-xl text-[14px] hover:bg-tema-green-light transition-all disabled:opacity-40">
                    {{ form.processing ? 'Envoi...' : 'Renvoyer le lien de vérification' }}
                </button>

                <Link :href="route('logout')"
                      method="post"
                      as="button"
                      class="w-full text-center text-[13px] text-[#1A2E2B]/50 hover:text-tema-brick transition-colors py-2 block">
                    Se déconnecter
                </Link>
            </div>

        </div>
    </div>
</template>
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">
        <Head title="Mot de passe oublié — TemaCoach" />

        <div class="flex-1 flex flex-col justify-center px-6 py-12 max-w-md mx-auto w-full">

            <div class="text-center mb-10">
                <div class="w-16 h-16 bg-tema-green/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">🔑</span>
                </div>
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] mb-2">
                    Mot de passe oublié ?
                </h1>
                <p class="text-[13px] text-[#1A2E2B]/50 max-w-xs mx-auto">
                    Indique ton email et on t'envoie un lien pour réinitialiser ton mot de passe.
                </p>
            </div>

            <!-- Succès -->
            <div v-if="status"
                 class="mb-5 bg-tema-green/10 border border-tema-green/20 rounded-2xl px-4 py-3 text-[13px] text-tema-green font-medium">
                {{ status }}
            </div>

            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-6 space-y-4">

                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Email
                    </label>
                    <input type="email"
                           v-model="form.email"
                           placeholder="ton@email.com"
                           required autofocus autocomplete="username"
                           class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    <p v-if="form.errors.email" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.email }}
                    </p>
                </div>

                <button type="button"
                        @click="submit"
                        :disabled="form.processing || !form.email"
                        class="w-full bg-tema-green text-white font-semibold py-3.5 rounded-xl text-[14px] hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ form.processing ? 'Envoi...' : 'Envoyer le lien de réinitialisation' }}
                </button>
            </div>

            <p class="text-center text-[13px] text-[#1A2E2B]/50 mt-5">
                <Link :href="route('login')"
                      class="text-tema-green font-semibold hover:underline">
                    ← Retour à la connexion
                </Link>
            </p>

        </div>
    </div>
</template>
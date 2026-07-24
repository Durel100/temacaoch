<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post('/confirm-password', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">
        <Head title="Confirmer le mot de passe — TemaCoach" />

        <div class="flex-1 flex flex-col justify-center px-6 py-12 max-w-md mx-auto w-full">

            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-tema-green/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">🛡️</span>
                </div>
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] mb-2">
                    Zone sécurisée
                </h1>
                <p class="text-[13px] text-[#1A2E2B]/60 max-w-xs mx-auto">
                    Pour accéder à cette section, confirme ton mot de passe.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-6 space-y-4">

                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Mot de passe
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               v-model="form.password"
                               placeholder="Ton mot de passe"
                               required autocomplete="current-password"
                               @keyup.enter="submit"
                               class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3 pr-12">
                        <button type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#1A2E2B]/30 hover:text-[#1A2E2B]/60 transition-colors text-[13px]">
                            {{ showPassword ? '🙈' : '👁️' }}
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.password }}
                    </p>
                </div>

                <button type="button"
                        @click="submit"
                        :disabled="form.processing || !form.password"
                        class="w-full bg-tema-green text-white font-semibold py-3.5 rounded-xl text-[14px] hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ form.processing ? 'Vérification...' : 'Confirmer' }}
                </button>
            </div>

        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token:                 props.token,
    email:                 props.email,
    password:              '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">
        <Head title="Réinitialiser le mot de passe — TemaCoach" />

        <div class="flex-1 flex flex-col justify-center px-6 py-12 max-w-md mx-auto w-full">

            <div class="text-center mb-10">
                <div class="w-16 h-16 bg-tema-green/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">🔒</span>
                </div>
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] mb-2">
                    Nouveau mot de passe
                </h1>
                <p class="text-[13px] text-[#1A2E2B]/50">
                    Choisis un nouveau mot de passe pour ton compte.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-6 space-y-4">

                <!-- Email (readonly) -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Email
                    </label>
                    <input type="email"
                           v-model="form.email"
                           required autocomplete="username"
                           class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3 bg-[#FAF6F0]">
                    <p v-if="form.errors.email" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Nouveau mot de passe -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Nouveau mot de passe
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               v-model="form.password"
                               placeholder="Minimum 8 caractères"
                               required autocomplete="new-password"
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

                <!-- Confirmer mot de passe -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Confirmer le mot de passe
                    </label>
                    <input :type="showPassword ? 'text' : 'password'"
                           v-model="form.password_confirmation"
                           placeholder="Répète ton mot de passe"
                           required autocomplete="new-password"
                           class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    <p v-if="form.errors.password_confirmation" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>

                <button type="button"
                        @click="submit"
                        :disabled="form.processing || !form.password || !form.password_confirmation"
                        class="w-full bg-tema-green text-white font-semibold py-3.5 rounded-xl text-[14px] hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ form.processing ? 'Réinitialisation...' : 'Réinitialiser mon mot de passe' }}
                </button>
            </div>

        </div>
    </div>
</template>
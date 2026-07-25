<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">
        <Head title="Inscription — TemaCoach" />

        <div class="flex-1 flex flex-col justify-center px-6 py-12 max-w-md mx-auto w-full">

            <div class="text-center mb-10">
                <h1 class="font-display text-[36px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    TemaCoach
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/50">
                    Commence à gérer ton argent aujourd'hui.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-6 space-y-4">

                <!-- Nom -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Nom complet
                    </label>
                    <input type="text"
                           v-model="form.name"
                           placeholder="Ex : Jean Dupont"
                           required autofocus autocomplete="name"
                           class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    <p v-if="form.errors.name" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Email
                    </label>
                    <input type="email"
                           v-model="form.email"
                           placeholder="ton@email.com"
                           required autocomplete="username"
                           class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    <p v-if="form.errors.email" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Mot de passe
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               v-model="form.password"
                               @change="form.password_confirmation = form.password"
                               placeholder="Minimum 8 caractères"
                               autocomplete="new-password"
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
                           autocomplete="new-password"
                           class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    <p v-if="form.errors.password_confirmation" class="text-[12px] text-tema-brick mt-1">
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>

                <!-- Bouton inscription -->
                <button type="button"
                        @click="submit"
                        :disabled="form.processing || !form.name || !form.email || !form.password"
                        class="w-full bg-tema-green text-white font-semibold py-3.5 rounded-xl text-[14px] hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ form.processing ? 'Création...' : 'Créer mon compte' }}
                </button>
            </div>

            <!-- Lien connexion -->
            <p class="text-center text-[13px] text-[#1A2E2B]/50 mt-5">
                Déjà un compte ?
                <Link :href="route('login')"
                      class="text-tema-green font-semibold hover:underline">
                    Se connecter
                </Link>
            </p>

        </div>

        <div class="py-4 text-center">
            <p class="text-[11px] text-[#1A2E2B]/30">
                TemaCoach · Ton coach financier personnel
            </p>
        </div>

    </div>
</template>
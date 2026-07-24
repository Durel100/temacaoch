<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status:           String,
});

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

const resetPasswordUrl = computed(() => {
    try {
        return typeof route === 'function' ? route('password.request') : '/forgot-password';
    } catch {
        return '/forgot-password';
    }
});
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">
        <Head title="Connexion — TemaCoach" />

        <!-- Logo + tagline -->
        <div class="flex-1 flex flex-col justify-center px-6 py-12 max-w-md mx-auto w-full">

            <div class="text-center mb-10">
                <h1 class="font-display text-[36px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    TemaCoach
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/50">
                    Gérons ton argent, ensemble.
                </p>
            </div>

            <!-- Alerte statut (ex: email de reset envoyé) -->
            <div v-if="status"
                 class="mb-5 bg-tema-green/10 border border-tema-green/20 rounded-2xl px-4 py-3 text-[13px] text-tema-green font-medium">
                {{ status }}
            </div>

            <!-- Formulaire -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-6 space-y-4">

                <!-- Email -->
                <div>
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        Email
                    </label>
                    <input type="email"
                           v-model="form.email"
                            @input="form.email = form.email.toLowerCase()"
                           placeholder="ton@email.com"
                           required autofocus autocomplete="username"
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
                               placeholder="••••••••"
                               required autocomplete="current-password"
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

                <!-- Se souvenir de moi + mot de passe oublié -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox"
                               v-model="form.remember"
                               class="rounded border-[#1A2E2B]/20 text-tema-green focus:ring-tema-green">
                        <span class="text-[12px] text-[#1A2E2B]/60">Se souvenir de moi</span>
                    </label>
                    <a v-if="canResetPassword"
                        href="/forgot-password"
                        class="text-[12px] text-tema-green hover:underline">
                        Mot de passe oublié ?
                    </a>
                </div>

                <!-- Bouton connexion -->
                <button type="button"
                        @click="submit"
                        :disabled="form.processing || !form.email || !form.password"
                        class="w-full bg-tema-green text-white font-semibold py-3.5 rounded-xl text-[14px] hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ form.processing ? 'Connexion...' : 'Se connecter' }}
                </button>
            </div>

            <!-- Lien inscription -->
            <p class="text-center text-[13px] text-[#1A2E2B]/50 mt-5">
                Pas encore de compte ?
                <a href="/register"
                    class="text-tema-green font-semibold hover:underline">
                    Créer un compte
                </a>
            </p>

        </div>

        <!-- Footer -->
        <div class="py-4 text-center">
            <p class="text-[11px] text-[#1A2E2B]/30">
                TemaCoach · Ton coach financier personnel
            </p>
        </div>

    </div>
</template>
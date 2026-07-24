<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    stats:        Object,
    signupsByDay: Array,
});

function formatNumber(n) {
    return new Intl.NumberFormat('fr-FR').format(n ?? 0);
}

const maxSignups = computed(() =>
    Math.max(...props.signupsByDay.map(d => d.count), 1)
);

function barHeight(count) {
    return Math.max(4, (count / maxSignups.value) * 100) + '%';
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-[#1A2E2B] border-b border-white/10">
            <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="font-display text-[18px] font-semibold text-white">
                        TemaCoach — Admin
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="router.get(route('admin.users'))"
                            class="text-[13px] text-white/60 hover:text-white transition-colors">
                        Utilisateurs
                    </button>
                    <button @click="router.get(route('dashboard'))"
                            class="text-[13px] bg-white/10 text-white px-3 py-1.5 rounded-lg hover:bg-white/20 transition-all">
                        ← App
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

            <!-- Titre -->
            <div>
                <h2 class="font-display text-[24px] font-semibold text-[#1A2E2B]">
                    Vue d'ensemble
                </h2>
                <p class="text-[13px] text-[#1A2E2B]/50 mt-0.5">
                    Données en temps réel
                </p>
            </div>

            <!-- KPIs principaux -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-2">
                        Total utilisateurs
                    </p>
                    <p class="font-display text-[32px] font-semibold text-[#1A2E2B] leading-none">
                        {{ formatNumber(stats.total_users) }}
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-2">
                        Actifs 7 jours
                    </p>
                    <p class="font-display text-[32px] font-semibold text-tema-green leading-none">
                        {{ formatNumber(stats.active_7d) }}
                    </p>
                    <p class="text-[11px] text-[#1A2E2B]/40 mt-1">
                        {{ stats.total_users > 0 ? Math.round((stats.active_7d / stats.total_users) * 100) : 0 }}% du total
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-2">
                        Actifs 30 jours
                    </p>
                    <p class="font-display text-[32px] font-semibold text-tema-ocre leading-none">
                        {{ formatNumber(stats.active_30d) }}
                    </p>
                    <p class="text-[11px] text-[#1A2E2B]/40 mt-1">
                        {{ stats.total_users > 0 ? Math.round((stats.active_30d / stats.total_users) * 100) : 0 }}% du total
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-2">
                        Nouveaux ce mois
                    </p>
                    <p class="font-display text-[32px] font-semibold text-[#1A2E2B] leading-none">
                        {{ formatNumber(stats.new_this_month) }}
                    </p>
                </div>
            </div>

            <!-- Onboarding + Transactions -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-4">
                        Onboarding
                    </p>
                    <div class="flex items-center gap-4">
                        <!-- Cercle SVG -->
                        <div class="relative flex-shrink-0">
                            <svg width="80" height="80" class="-rotate-90">
                                <circle cx="40" cy="40" r="32" fill="none" stroke="#FAF6F0" stroke-width="8"/>
                                <circle cx="40" cy="40" r="32" fill="none"
                                        stroke="#2D6A4F" stroke-width="8"
                                        stroke-linecap="round"
                                        :stroke-dasharray="2 * Math.PI * 32"
                                        :stroke-dashoffset="2 * Math.PI * 32 * (1 - stats.onboarding_rate / 100)"
                                        style="transition: stroke-dashoffset 0.8s ease"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="font-display text-[16px] font-semibold text-[#1A2E2B]">
                                    {{ stats.onboarding_rate }}%
                                </span>
                            </div>
                        </div>
                        <div>
                            <p class="text-[28px] font-display font-semibold text-tema-green">
                                {{ formatNumber(stats.onboarding_completed) }}
                            </p>
                            <p class="text-[12px] text-[#1A2E2B]/50">
                                ont complété l'onboarding
                            </p>
                            <p class="text-[12px] text-[#1A2E2B]/40 mt-0.5">
                                {{ formatNumber(stats.total_users - stats.onboarding_completed) }} en attente
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-4">
                        Transactions
                    </p>
                    <p class="font-display text-[32px] font-semibold text-[#1A2E2B]">
                        {{ formatNumber(stats.total_transactions) }}
                    </p>
                    <p class="text-[12px] text-[#1A2E2B]/50 mt-1">transactions enregistrées au total</p>
                    <div class="mt-3 pt-3 border-t border-[#1A2E2B]/6">
                        <p class="text-[13px] font-semibold text-tema-green">
                            + {{ formatNumber(stats.transactions_month) }}
                        </p>
                        <p class="text-[11px] text-[#1A2E2B]/40">ce mois-ci</p>
                    </div>
                </div>
            </div>

            <!-- Graphique inscriptions 30 jours -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <div class="flex justify-between items-center mb-6">
                    <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold">
                        Inscriptions — 30 derniers jours
                    </p>
                    <p class="text-[13px] font-semibold text-[#1A2E2B]">
                        {{ formatNumber(signupsByDay.reduce((s, d) => s + d.count, 0)) }} au total
                    </p>
                </div>

                <div class="flex items-end gap-1 h-32 mb-2">
                    <div v-for="day in signupsByDay" :key="day.date"
                         class="flex-1 flex flex-col items-center">
                        <div class="w-full flex flex-col justify-end" style="height: 100%">
                            <div class="w-full bg-tema-green rounded-t-sm transition-all duration-300"
                                 :class="day.count === 0 ? 'opacity-10' : ''"
                                 :style="{ height: barHeight(day.count) }"
                                 :title="`${day.label} : ${day.count} inscription(s)`"/>
                        </div>
                    </div>
                </div>

                <div class="flex gap-1">
                    <div v-for="(day, index) in signupsByDay" :key="day.date" class="flex-1 text-center">
                        <p v-if="index % 5 === 0" class="text-[9px] text-[#1A2E2B]/30">
                            {{ day.label }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Accès rapide -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] text-[#1A2E2B]/50 uppercase tracking-widest font-semibold mb-4">
                    Gestion
                </p>
                <button @click="router.get(route('admin.users'))"
                        class="w-full sm:w-auto flex items-center gap-3 px-5 py-3 bg-[#1A2E2B] text-white rounded-xl hover:bg-[#1A2E2B]/90 transition-all text-[13px] font-semibold">
                    👥 Voir tous les utilisateurs
                    <span class="bg-white/20 text-white text-[11px] px-2 py-0.5 rounded-full">
                        {{ formatNumber(stats.total_users) }}
                    </span>
                </button>
            </div>

        </div>
    </div>
</template>
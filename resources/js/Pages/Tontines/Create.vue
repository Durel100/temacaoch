<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const commonCycles = [7, 10, 14, 30, 45, 60];
const commonMonths = [1, 2, 3, 6];

const form = useForm({
    name:                '',
    contribution_amount: null,
    cycle_days:          null,
    cycle_months:        null,
    frequency_type:      'days', // 'days' ou 'months'
    total_members:       null,
    my_positions:        [],
    start_date:          '',
});

function togglePosition(pos) {
    const idx = form.my_positions.indexOf(pos);
    if (idx === -1) {
        form.my_positions.push(pos);
        form.my_positions.sort((a, b) => a - b);
    } else {
        form.my_positions.splice(idx, 1);
    }
}

function isMyPosition(pos) {
    return form.my_positions.includes(pos);
}

const availablePositions = computed(() => {
    if (!form.total_members || form.total_members < 2) return [];
    return Array.from({ length: form.total_members }, (_, i) => i + 1);
});

const totalPayoutPreview = computed(() => {
    if (!form.contribution_amount || !form.total_members || !form.my_positions.length) return null;
    return form.contribution_amount * form.total_members * form.my_positions.length;
});

const myPayoutDatesPreview = computed(() => {
    if (!form.start_date || !form.my_positions.length) return [];
    if (form.frequency_type === 'months' && !form.cycle_months) return [];
    if (form.frequency_type === 'days' && !form.cycle_days) return [];

    return form.my_positions.map(pos => {
        const start = new Date(form.start_date);
        if (form.frequency_type === 'months') {
            start.setMonth(start.getMonth() + (pos - 1) * form.cycle_months);
        } else {
            start.setDate(start.getDate() + (pos - 1) * form.cycle_days);
        }
        return {
            position: pos,
            date: start.toLocaleDateString('fr-FR', {
                day: 'numeric', month: 'long', year: 'numeric',
            }),
        };
    });
});

const totalDurationPreview = computed(() => {
    if (!form.total_members) return null;

    if (form.frequency_type === 'months' && form.cycle_months) {
        const totalMonths = form.total_members * form.cycle_months;
        const years  = Math.floor(totalMonths / 12);
        const months = totalMonths % 12;
        if (years === 0) return `${totalMonths} ${t('months')}`;
        if (months === 0) return `${years} an(s)`;
        return `${years} an(s) ${months} ${t('months')}`;
    }

    if (form.cycle_days) {
        const totalDays = form.total_members * form.cycle_days;
        const months = Math.floor(totalDays / 30);
        const days   = totalDays % 30;
        if (months === 0) return `${totalDays} ${t('days_label')}`;
        if (days === 0)   return `${months} ${t('months')}`;
        return `${months} ${t('months')} ${days} ${t('days_label')}`;
    }

    return null;
});

const canSubmit = computed(() => {
    if (!form.name) return false;
    if (!form.contribution_amount) return false;
    if (form.frequency_type === 'months' && !form.cycle_months) return false;
    if (form.frequency_type === 'days' && !form.cycle_days) return false;
    if (!form.total_members || form.total_members < 2) return false;
    if (!form.my_positions.length) return false;
    if (!form.start_date) return false;
    if (form.processing) return false;
    return true;
});

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount)) + ' FCFA';
}

function submit() {
    form.post(route('tontines.store'));
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8" style="padding-top: env(safe-area-inset-top)">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
                <button @click="router.get(route('tontines.index'))"
                        class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                    ←
                </button>
                <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B]">
                    {{ t('new_tontine_title') }}
                </h1>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

            <!-- Nom -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                    {{ t('tontine_name_label') }}
                </p>
                <input type="text"
                       v-model="form.name"
                       :placeholder="t('tontine_name_ph')"
                       class="w-full text-[14px] font-medium rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                <p v-if="form.errors.name" class="text-[12px] text-tema-brick mt-1">
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Cycle -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                    {{ t('tontine_cycle_label') }}
                </p>
                <p class="text-[12px] text-[#1A2E2B]/50 mb-3">
                     {{ form.frequency_type === 'months' ? t('tontine_cycle_desc_months') : t('tontine_cycle_desc') }}
                </p>

                <!-- Toggle jours / mois -->
                <div class="flex bg-[#FAF6F0] rounded-xl p-1 mb-4">
                    <button type="button"
                            @click="form.frequency_type = 'days'; form.cycle_months = null"
                            class="flex-1 py-2 text-[13px] font-semibold rounded-lg transition-all"
                            :class="form.frequency_type === 'days'
                                ? 'bg-white text-tema-green shadow-sm'
                                : 'text-[#1A2E2B]/50 hover:text-[#1A2E2B]'">
                        {{ t('days_label') }}
                    </button>
                    <button type="button"
                            @click="form.frequency_type = 'months'; form.cycle_days = null"
                            class="flex-1 py-2 text-[13px] font-semibold rounded-lg transition-all"
                            :class="form.frequency_type === 'months'
                                ? 'bg-white text-tema-green shadow-sm'
                                : 'text-[#1A2E2B]/50 hover:text-[#1A2E2B]'">
                        {{ t('months') }}
                    </button>
                </div>

                <!-- Options jours -->
                <div v-if="form.frequency_type === 'days'">
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button v-for="days in commonCycles" :key="days"
                                type="button"
                                @click="form.cycle_days = days"
                                class="px-3 py-2 rounded-full border-[1.5px] text-[12px] font-medium transition-all"
                                :class="form.cycle_days === days
                                    ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                    : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-tema-green/40 hover:text-tema-green'">
                            {{ days }} {{ t('days_label') }}
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="number"
                               v-model.number="form.cycle_days"
                               :placeholder="t('tontine_custom')"
                               min="1" max="365"
                               class="flex-1 text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <span class="text-[13px] text-[#1A2E2B]/50 flex-shrink-0">{{ t('days_label') }}</span>
                    </div>
                </div>

                <!-- Options mois -->
                <div v-if="form.frequency_type === 'months'">
                    <p class="text-[12px] text-tema-green/70 bg-tema-green/8 rounded-xl px-3 py-2 mb-3">
                        ✓ Les dates seront calculées exactement mois par mois (1 mois = 1er au 1er, etc.)
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button v-for="m in commonMonths" :key="m"
                                type="button"
                                @click="form.cycle_months = m"
                                class="px-3 py-2 rounded-full border-[1.5px] text-[12px] font-medium transition-all"
                                :class="form.cycle_months === m
                                    ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                    : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-tema-green/40 hover:text-tema-green'">
                            {{ m }} {{ t('months') }}
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="number"
                               v-model.number="form.cycle_months"
                               placeholder="Ex : 2"
                               min="1" max="24"
                               class="flex-1 text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <span class="text-[13px] text-[#1A2E2B]/50 flex-shrink-0">{{ t('months') }}</span>
                    </div>
                </div>

                <p v-if="form.errors.cycle_days || form.errors.cycle_months"
                   class="text-[12px] text-tema-brick mt-1">
                    {{ form.errors.cycle_days || form.errors.cycle_months }}
                </p>
            </div>

            <!-- Cotisation -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                    {{ t('tontine_amount') }}
                </p>
                <div class="relative">
                    <input type="number"
                           v-model.number="form.contribution_amount"
                           placeholder="Ex : 10 000"
                           min="500"
                           class="w-full text-[20px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3.5 pr-16">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] text-[#1A2E2B]/40 font-medium">
                        FCFA
                    </span>
                </div>
                <p v-if="form.errors.contribution_amount" class="text-[12px] text-tema-brick mt-1">
                    {{ form.errors.contribution_amount }}
                </p>
            </div>

            <!-- Nombre de membres -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                    {{ t('tontine_members') }}
                </p>
                <input type="number"
                       v-model.number="form.total_members"
                       placeholder="Ex : 10"
                       min="2" max="50"
                       class="w-full text-[17px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                <p v-if="form.errors.total_members" class="text-[12px] text-tema-brick mt-1">
                    {{ form.errors.total_members }}
                </p>
            </div>

            <!-- Mes positions -->
            <div v-if="availablePositions.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                    {{ t('tontine_positions') }}
                </p>
                <p class="text-[12px] text-[#1A2E2B]/50 mb-4">
                    {{ t('tontine_pos_desc') }}
                </p>

                <div class="grid grid-cols-5 gap-2 mb-3">
                    <button v-for="pos in availablePositions"
                            :key="pos"
                            type="button"
                            @click="togglePosition(pos)"
                            class="aspect-square rounded-xl border-[1.5px] flex items-center justify-center text-[13px] font-semibold transition-all"
                            :class="isMyPosition(pos)
                                ? 'border-tema-green bg-tema-green text-white shadow-sm shadow-tema-green/30'
                                : 'border-[#1A2E2B]/10 text-[#1A2E2B]/50 hover:border-tema-green/40 hover:text-tema-green'">
                        {{ pos }}
                    </button>
                </div>

                <div v-if="form.my_positions.length > 0"
                     class="bg-tema-green/8 rounded-xl px-3 py-2.5">
                    <p class="text-[12px] text-tema-green font-semibold">
                        {{ form.my_positions.length === 1
                            ? `${t('tontine_pos_one')} ${form.my_positions[0]}`
                            : `${t('tontine_pos_sel')} ${form.my_positions.join(', ')}` }}
                    </p>
                    <p class="text-[11px] text-tema-green/70 mt-0.5">
                        {{ form.my_positions.length }} {{ t('tontine_receptions') }}
                    </p>
                </div>

                <p v-if="form.errors.my_positions" class="text-[12px] text-tema-brick mt-2">
                    {{ form.errors.my_positions }}
                </p>
            </div>

            <!-- Date de départ -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                    {{ t('tontine_start') }}
                </p>
                <p class="text-[12px] text-[#1A2E2B]/50 mb-3">
                    {{ t('tontine_start_desc') }}
                </p>
                <input type="date"
                       v-model="form.start_date"
                       class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                <p v-if="form.errors.start_date" class="text-[12px] text-tema-brick mt-1">
                    {{ form.errors.start_date }}
                </p>
            </div>

            <!-- Aperçu -->
            <div v-if="totalPayoutPreview || myPayoutDatesPreview.length > 0"
                 class="bg-tema-green/10 border border-tema-green/20 rounded-2xl p-5 space-y-3">
                <p class="text-[11px] font-semibold text-tema-green uppercase tracking-widest">
                    {{ t('tontine_preview') }}
                </p>

                <div class="flex justify-between items-center">
                    <span class="text-[13px] text-[#1A2E2B]/70">{{ t('tontine_total_get') }}</span>
                    <span class="font-display font-semibold text-tema-green text-[17px]">
                        {{ formatFcfa(totalPayoutPreview) }}
                    </span>
                </div>

                <div class="text-[12px] text-[#1A2E2B]/60">
                    {{ form.my_positions.length }} {{ t('tontine_receptions') }} ×
                    {{ form.contribution_amount ? formatFcfa(form.contribution_amount * form.total_members) : '—' }}
                </div>

                <div v-if="myPayoutDatesPreview.length > 0"
                     class="pt-2 border-t border-tema-green/20 space-y-1.5">
                    <p class="text-[11px] text-tema-green/70 font-semibold mb-2">
                        {{ t('tontine_est_dates') }}
                    </p>
                    <div v-for="item in myPayoutDatesPreview" :key="item.position"
                         class="flex justify-between items-center">
                        <span class="text-[12px] text-[#1A2E2B]/60">
                            {{ t('turn') }} {{ item.position }}
                        </span>
                        <span class="text-[12px] font-semibold text-[#1A2E2B]">
                            {{ item.date }}
                        </span>
                    </div>
                </div>

                <div v-if="totalDurationPreview"
                     class="flex justify-between items-center pt-2 border-t border-tema-green/20">
                    <span class="text-[13px] text-[#1A2E2B]/70">{{ t('tontine_duration') }}</span>
                    <span class="text-[13px] font-semibold text-[#1A2E2B]">
                        {{ totalDurationPreview }}
                    </span>
                </div>
            </div>

            <!-- Bouton créer -->
            <button @click="submit"
                    :disabled="!canSubmit"
                    class="w-full bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light disabled:opacity-40 disabled:cursor-not-allowed shadow-sm mb-6">
                {{ form.processing ? t('tontine_creating') : t('tontine_create_go') }}
            </button>

        </div>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
    resteAVivre:        Number,
    totalCharges:       Number,
    safeIncome:         Number,
    employmentType:     String,
    spouseContributes:  Boolean,
    spouseContribution: Number,
});

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

const healthLabel = computed(() => {
    if (props.resteAVivre >= props.safeIncome * 0.3)
        return { text: t('status_stable'),  color: 'text-tema-green',  bg: 'bg-tema-green/10 border-tema-green/20',  emoji: '💚' };
    if (props.resteAVivre >= 0)
        return { text: t('status_watch'),   color: 'text-tema-ocre',   bg: 'bg-tema-ocre/15 border-tema-ocre/25',    emoji: '⚠️' };
    return   { text: t('status_deficit'),  color: 'text-tema-brick',  bg: 'bg-tema-brick/10 border-tema-brick/20',  emoji: '🚨' };
});
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">
        <div class="max-w-2xl mx-auto px-4 py-12">

            <div class="text-center mb-10">
                <div class="text-6xl mb-4">🎉</div>
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] mb-2">
                    {{ t('onboarding_title_summary') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">
                    {{ t('onboarding_subtitle_summary') }}
                </p>
            </div>

            <div class="space-y-3 mb-10">

                <!-- Revenu -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        {{ t('reference_income') }}
                    </p>
                    <p class="font-display text-[28px] font-semibold text-[#1A2E2B]">
                        {{ formatFcfa(safeIncome) }}
                    </p>
                    <div class="mt-2 space-y-1">
                        <p class="text-[12px] text-[#1A2E2B]/40">
                            {{ employmentType === 'salaried'
                                ? t('reference_income_desc_salaried')
                                : t('reference_income_desc_nonsalaried') }}
                        </p>
                        <p v-if="spouseContributes && spouseContribution > 0"
                           class="text-[12px] text-tema-green">
                            ✓ {{ t('spouse_included') }}
                            +{{ formatFcfa(spouseContribution) }}
                            {{ t('spouse_contribution_of') }}
                        </p>
                    </div>
                </div>

                <!-- Charges -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        {{ t('fixed_charges_total') }}
                    </p>
                    <p class="font-display text-[28px] font-semibold text-tema-terracotta">
                        {{ formatFcfa(totalCharges) }}
                    </p>
                    <p class="text-[12px] text-[#1A2E2B]/40 mt-1">{{ t('fixed_charges_desc') }}</p>
                </div>

                <!-- Reste à vivre -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest">
                            {{ t('rest_to_live') }}
                        </p>
                        <span class="text-[12px] font-semibold px-3 py-1 rounded-full border flex items-center gap-1"
                              :class="healthLabel.bg">
                            <span>{{ healthLabel.emoji }}</span>
                            <span :class="healthLabel.color">{{ healthLabel.text }}</span>
                        </span>
                    </div>
                    <p class="font-display text-[32px] font-semibold"
                       :class="resteAVivre >= 0 ? 'text-[#1A2E2B]' : 'text-tema-brick'">
                        {{ formatFcfa(resteAVivre) }}
                    </p>
                    <p class="text-[12px] text-[#1A2E2B]/40 mt-1">{{ t('rest_desc') }}</p>
                </div>

                <!-- Message contextuel -->
                <div v-if="resteAVivre < 0"
                     class="bg-tema-brick/10 border border-tema-brick/20 rounded-2xl p-4">
                    <p class="text-[13px] text-tema-brick font-medium">{{ t('msg_deficit') }}</p>
                </div>
                <div v-else-if="resteAVivre < safeIncome * 0.2"
                     class="bg-tema-ocre/15 border border-tema-ocre/25 rounded-2xl p-4">
                    <p class="text-[13px] text-[#1A2E2B]/70 font-medium">{{ t('msg_tight') }}</p>
                </div>
                <div v-else
                     class="bg-tema-green/10 border border-tema-green/20 rounded-2xl p-4">
                    <p class="text-[13px] text-tema-green font-medium">{{ t('msg_good') }}</p>
                </div>

            </div>

            <button type="button"
                    @click="router.get(route('dashboard'))"
                    class="w-full bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light shadow-sm">
                {{ t('go_to_dashboard') }}
            </button>

        </div>
    </div>
</template>
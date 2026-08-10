<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
    employment_type: String,
});

const isSalaried = computed(() => props.employment_type === 'salaried');

const form = ref({
    salary_day:                         null,
    salary_already_received:            null,
    current_month_remaining:            null,
    remaining_fixed_charges_this_month: null,
});

const errors       = ref({});
const isSubmitting = ref(false);
const today        = new Date().getDate();

function autoDetect() {
    if (!form.value.salary_day) return;
    form.value.salary_already_received = form.value.salary_day <= today;
}

function selectReceived(value) {
    form.value.salary_already_received = value;
    // On repart propre : le montant est requis dans les deux cas (Oui / Non)
    form.value.current_month_remaining = null;
}

const budgetPreview = computed(() => {
    const remaining = form.value.current_month_remaining;
    const charges   = form.value.remaining_fixed_charges_this_month;
    // ← Accepter 0 comme valeur valide (pas seulement null)
    if (remaining !== null && remaining !== undefined && charges !== null && charges !== undefined) {
        return remaining - charges;
    }
    return null;
});

const canSubmit = computed(() => {
    if (isSalaried.value) {
        if (!form.value.salary_day) return false;
        if (form.value.salary_already_received === null) return false;
        // ← Corrigé : vérifier null/undefined au lieu de falsy (0 est valide)
        if (form.value.current_month_remaining === null) return false;
    }
    return true;
});

function submit() {
    if (!canSubmit.value || isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(route('onboarding.current-situation.store'), form.value, {
        onError:  (e) => { errors.value = e; },
        onFinish: () => { isSubmitting.value = false; },
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8" style="padding-top: env(safe-area-inset-top)">
            <div class="max-w-2xl mx-auto flex items-center gap-3 px-4 py-3">
                <div class="flex gap-1 flex-1">
                    <div v-for="step in 7" :key="step"
                         class="flex-1 h-[3px] rounded-full transition-all duration-300"
                         :class="step <= 4 ? 'bg-tema-green' : 'bg-[#1A2E2B]/10'"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 4 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_situation') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">
                    {{ isSalaried ? t('onboarding_subtitle_salaried') : t('onboarding_subtitle_nonsalaried') }}
                </p>
            </div>

            <div class="space-y-4">

                <!-- SALARIÉ -->
                <template v-if="isSalaried">

                    <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('salary_day_label') }}
                        </p>
                        <p class="text-[13px] text-[#1A2E2B]/60 mb-4">{{ t('salary_day_desc') }}</p>
                        <div class="flex items-center gap-3">
                            <input type="number"
                                   v-model.number="form.salary_day"
                                   @change="autoDetect"
                                   placeholder="Ex : 25"
                                   min="1" max="31"
                                   class="w-28 text-[20px] font-semibold text-center rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                            <span class="text-[14px] text-[#1A2E2B]/50">{{ t('each_month') }}</span>
                        </div>
                    </div>

                    <div v-if="form.salary_day" class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                        <p class="text-[14px] font-semibold text-[#1A2E2B] mb-4">
                            {{ t('received_this_month') }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <button v-for="opt in [
                                    { value: true,  label: t('yes_received'), emoji: '✅' },
                                    { value: false, label: t('not_yet'),      emoji: '⏳' },
                                ]"
                                    :key="String(opt.value)"
                                    type="button"
                                    @click="selectReceived(opt.value)"
                                    class="flex flex-col items-center py-5 rounded-2xl border-[1.5px] transition-all"
                                    :class="form.salary_already_received === opt.value
                                        ? 'border-tema-green bg-tema-green/8'
                                        : 'border-[#1A2E2B]/10 hover:border-tema-green/30'">
                                <span class="text-2xl mb-2">{{ opt.emoji }}</span>
                                <span class="text-[14px] font-semibold"
                                      :class="form.salary_already_received === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/70'">
                                    {{ opt.label }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="form.salary_already_received !== null"
                         class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ form.salary_already_received ? t('how_much_left') : t('how_much_left_prev_month') }}
                        </p>
                        <p class="text-[13px] text-[#1A2E2B]/55 mb-4">{{ form.salary_already_received ? t('how_much_left_desc') : t('how_much_left_prev_month_desc') }}</p>
                        <div class="relative">
                            <input type="number"
                                   v-model.number="form.current_month_remaining"
                                   placeholder="Ex : 45 000" min="0"
                                   class="w-full text-[20px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3.5">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] text-[#1A2E2B]/40 font-medium">FCFA</span>
                        </div>
                    </div>

                </template>

                <!-- NON SALARIÉ -->
                <template v-else>
                    <div class="bg-tema-ocre/15 border border-tema-ocre/25 rounded-2xl p-4">
                        <p class="text-[13px] text-[#1A2E2B]/70">{{ t('prorata_info') }}</p>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                            {{ t('how_much_now') }}
                            <span class="normal-case font-normal ml-1">{{ t('optional') }}</span>
                        </p>
                        <p class="text-[13px] text-[#1A2E2B]/55 mb-4">{{ t('how_much_now_desc') }}</p>
                        <div class="relative">
                            <input type="number"
                                   v-model.number="form.current_month_remaining"
                                   placeholder="Ex : 25 000" min="0"
                                   class="w-full text-[20px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3.5">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] text-[#1A2E2B]/40 font-medium">FCFA</span>
                        </div>
                    </div>
                </template>

                <!-- Commun -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        {{ t('remaining_charges') }}
                    </p>
                    <p class="text-[13px] text-[#1A2E2B]/55 mb-4">{{ t('remaining_charges_desc') }}</p>
                    <div class="relative">
                        <input type="number"
                               v-model.number="form.remaining_fixed_charges_this_month"
                               placeholder="Ex : 30 000" min="0"
                               class="w-full text-[20px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3.5">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] text-[#1A2E2B]/40 font-medium">FCFA</span>
                    </div>
                </div>

                <!-- Aperçu -->
                <div v-if="budgetPreview !== null"
                     class="rounded-2xl p-5"
                     :class="budgetPreview >= 0
                         ? 'bg-tema-green/10 border border-tema-green/20'
                         : 'bg-tema-brick/10 border border-tema-brick/20'">
                    <p class="text-[12px] font-semibold mb-1"
                       :class="budgetPreview >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                        {{ t('budget_preview') }}
                    </p>
                    <p class="font-display text-[26px] font-semibold"
                       :class="budgetPreview >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                        {{ new Intl.NumberFormat('fr-FR').format(Math.round(budgetPreview)) }} FCFA
                    </p>
                    <p class="text-[12px] mt-1"
                       :class="budgetPreview >= 0 ? 'text-tema-green/70' : 'text-tema-brick/70'">
                        {{ t('budget_preview_formula') }}
                    </p>
                </div>

            </div>

            <div class="flex gap-3 mt-10">
                <button type="button"
                        @click="router.get(route('onboarding.charges'))"
                        class="w-14 h-14 rounded-2xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 flex items-center justify-center hover:border-[#1A2E2B]/25 transition-all flex-shrink-0">
                    {{ t('back') }}
                </button>
                <button type="button" @click="submit"
                        :disabled="!canSubmit || isSubmitting"
                        class="flex-1 bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light disabled:opacity-40 shadow-sm">
                    {{ isSubmitting ? t('saving') : t('continue') }}
                </button>
            </div>

        </div>
    </div>
</template>
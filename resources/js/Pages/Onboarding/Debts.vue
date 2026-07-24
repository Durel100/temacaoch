<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const form         = ref({ debts: [] });
const errors       = ref({});
const isSubmitting = ref(false);
const hasDebts     = ref(null);

function addDebt() {
    form.value.debts.push({
        label: '', total_amount: null, remaining_amount: null,
        interest_rate: null, monthly_payment: null,
    });
}

function removeDebt(index) {
    form.value.debts.splice(index, 1);
}

function selectHasDebts(value) {
    hasDebts.value = value;
    if (!value) form.value.debts = [];
    else if (form.value.debts.length === 0) addDebt();
}

const totalMonthlyPayments = computed(() =>
    form.value.debts.reduce((sum, d) => sum + (d.monthly_payment || 0), 0)
);

const highInterestDebts = computed(() =>
    form.value.debts.filter(d => d.interest_rate > 15)
);

// Valider chaque champ obligatoire et retourner l'index de la première dette incomplète
const firstIncompleteIndex = computed(() => {
    if (!hasDebts.value) return -1;
    return form.value.debts.findIndex(d =>
        !d.label || !d.total_amount || !d.remaining_amount
    );
});

function submit() {
    if (hasDebts.value === null || isSubmitting.value) return;

    // Validation côté Vue
    if (hasDebts.value === true && firstIncompleteIndex.value !== -1) {
        errors.value = {
            debts: firstIncompleteIndex.value,
        };
        // Scroller vers la dette incomplète
        const el = document.getElementById(`debt-${firstIncompleteIndex.value}`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    errors.value = {};
    isSubmitting.value = true;
    router.post(route('onboarding.debts.store'), form.value, {
        onError:  (e) => { errors.value = e; },
        onFinish: () => { isSubmitting.value = false; },
    });
}

function isDebtIncomplete(index) {
    return errors.value.debts === index;
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto flex items-center gap-3 px-4 py-3">
                <div class="flex gap-1 flex-1">
                    <div v-for="step in 7" :key="step"
                         class="flex-1 h-[3px] rounded-full transition-all duration-300"
                         :class="step <= 6 ? 'bg-tema-green' : 'bg-[#1A2E2B]/10'"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 6 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_debts') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">{{ t('onboarding_subtitle_debts') }}</p>
            </div>

            <div class="space-y-4">

                <!-- Choix oui/non -->
                <div v-if="hasDebts === null" class="grid grid-cols-2 gap-3">
                    <button v-for="opt in [
                            { value: true,  label: t('has_debts_yes'), sub: t('has_debts_yes_sub'), emoji: '💸' },
                            { value: false, label: t('has_debts_no'),  sub: t('has_debts_no_sub'),  emoji: '✨' },
                        ]"
                            :key="String(opt.value)"
                            type="button"
                            @click="selectHasDebts(opt.value)"
                            class="flex flex-col items-center py-8 rounded-2xl border-[1.5px] border-[#1A2E2B]/10 bg-white hover:border-tema-green/30 transition-all">
                        <span class="text-3xl mb-2">{{ opt.emoji }}</span>
                        <span class="text-[15px] font-semibold text-[#1A2E2B]">{{ opt.label }}</span>
                        <span class="text-[12px] text-[#1A2E2B]/50 mt-0.5">{{ opt.sub }}</span>
                    </button>
                </div>

                <!-- Aucune dette -->
                <div v-if="hasDebts === false"
                     class="bg-tema-green/10 border border-tema-green/20 rounded-2xl p-5 flex items-center gap-3">
                    <span class="text-2xl">✨</span>
                    <div class="flex-1">
                        <p class="text-[14px] font-semibold text-tema-green">{{ t('no_debt_declared') }}</p>
                        <p class="text-[12px] text-tema-green/70 mt-0.5">{{ t('no_debt_desc') }}</p>
                    </div>
                    <button type="button" @click="hasDebts = null"
                            class="text-[12px] text-tema-green/60 underline hover:text-tema-green">
                        {{ t('modify') }}
                    </button>
                </div>

                <!-- Dettes -->
                <template v-if="hasDebts === true">

                    <div v-for="(debt, index) in form.debts"
                         :key="index"
                         :id="`debt-${index}`"
                         class="rounded-2xl border overflow-hidden transition-all"
                         :class="isDebtIncomplete(index)
                             ? 'border-tema-brick bg-white'
                             : 'border-[#1A2E2B]/10 bg-white'">

                        <!-- Alerte champs manquants -->
                        <div v-if="isDebtIncomplete(index)"
                             class="bg-tema-brick/10 px-4 py-2.5 flex items-center gap-2">
                            <span class="text-tema-brick text-[13px]">⚠️</span>
                            <p class="text-[12px] text-tema-brick font-semibold">
                                {{ t('debt_incomplete_warning') }}
                            </p>
                        </div>

                        <div class="p-5 space-y-4">

                            <div class="flex justify-between items-center">
                                <p class="text-[13px] font-semibold text-[#1A2E2B]">
                                    {{ t('debt') }} {{ index + 1 }}
                                </p>
                                <button type="button" @click="removeDebt(index)"
                                        class="w-7 h-7 rounded-full bg-tema-brick/10 text-tema-brick text-xs flex items-center justify-center hover:bg-tema-brick/20 transition-all">
                                    ✕
                                </button>
                            </div>

                            <!-- Nom de la dette -->
                            <div>
                                <input type="text"
                                       v-model="debt.label"
                                       :placeholder="t('debt_label_placeholder')"
                                       class="w-full text-[14px] font-medium rounded-xl py-3 border focus:ring-tema-green transition-all"
                                       :class="isDebtIncomplete(index) && !debt.label
                                           ? 'border-tema-brick/50 focus:border-tema-brick'
                                           : 'border-[#1A2E2B]/15 focus:border-tema-green'">
                                <p v-if="isDebtIncomplete(index) && !debt.label"
                                   class="text-[11px] text-tema-brick mt-1">
                                    {{ t('field_required') }}
                                </p>
                            </div>

                            <!-- Montants — colonne unique sur mobile -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                                <!-- Montant total -->
                                <div class="relative">
                                    <label class="block text-[11px] text-[#1A2E2B]/40 mb-1.5 font-semibold uppercase tracking-wide">
                                        {{ t('total_amount') }}
                                        <span class="text-tema-brick">*</span>
                                    </label>
                                    <input type="number"
                                           v-model.number="debt.total_amount"
                                           placeholder="200 000" min="0"
                                           class="w-full text-[13px] rounded-xl py-3 pr-14 border focus:ring-tema-green transition-all"
                                           :class="isDebtIncomplete(index) && !debt.total_amount
                                               ? 'border-tema-brick/50 focus:border-tema-brick'
                                               : 'border-[#1A2E2B]/15 focus:border-tema-green'">
                                    <span class="absolute right-3 bottom-3 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                                    <p v-if="isDebtIncomplete(index) && !debt.total_amount"
                                       class="text-[11px] text-tema-brick mt-1">
                                        {{ t('field_required') }}
                                    </p>
                                </div>

                                <!-- Reste à payer -->
                                <div class="relative">
                                    <label class="block text-[11px] text-[#1A2E2B]/40 mb-1.5 font-semibold uppercase tracking-wide">
                                        {{ t('remaining_amount') }}
                                        <span class="text-tema-brick">*</span>
                                    </label>
                                    <input type="number"
                                           v-model.number="debt.remaining_amount"
                                           placeholder="150 000" min="0"
                                           class="w-full text-[13px] rounded-xl py-3 pr-14 border focus:ring-tema-green transition-all"
                                           :class="isDebtIncomplete(index) && !debt.remaining_amount
                                               ? 'border-tema-brick/50 focus:border-tema-brick'
                                               : 'border-[#1A2E2B]/15 focus:border-tema-green'">
                                    <span class="absolute right-3 bottom-3 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                                    <p v-if="isDebtIncomplete(index) && !debt.remaining_amount"
                                       class="text-[11px] text-tema-brick mt-1">
                                        {{ t('field_required') }}
                                    </p>
                                </div>

                                <!-- Taux intérêt (optionnel) -->
                                <div class="relative">
                                    <label class="block text-[11px] text-[#1A2E2B]/40 mb-1.5 font-semibold uppercase tracking-wide">
                                        {{ t('interest_rate') }}
                                    </label>
                                    <input type="number"
                                           v-model.number="debt.interest_rate"
                                           placeholder="15" min="0" step="0.1"
                                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-8 py-3">
                                    <span class="absolute right-3 bottom-3 text-[11px] text-[#1A2E2B]/40">%</span>
                                    <p v-if="debt.interest_rate > 15"
                                       class="text-[11px] text-tema-brick mt-1">
                                        {{ t('high_rate_warning') }}
                                    </p>
                                </div>

                                <!-- Paiement mensuel (optionnel) -->
                                <div class="relative">
                                    <label class="block text-[11px] text-[#1A2E2B]/40 mb-1.5 font-semibold uppercase tracking-wide">
                                        {{ t('monthly_payment') }}
                                    </label>
                                    <input type="number"
                                           v-model.number="debt.monthly_payment"
                                           placeholder="25 000" min="0"
                                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-14 py-3">
                                    <span class="absolute right-3 bottom-3 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                                </div>

                            </div>
                        </div>
                    </div>

                    <button type="button" @click="addDebt"
                            class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all">
                        {{ t('add_debt_btn') }}
                    </button>

                    <!-- Résumé impact -->
                    <div v-if="totalMonthlyPayments > 0 || highInterestDebts.length > 0"
                         class="bg-[#1A2E2B]/4 rounded-2xl p-4 space-y-1">
                        <p class="text-[12px] font-semibold text-[#1A2E2B]/60 mb-2">{{ t('debt_impact') }}</p>
                        <p v-if="totalMonthlyPayments > 0" class="text-[13px] text-[#1A2E2B]/70">
                            −{{ new Intl.NumberFormat('fr-FR').format(totalMonthlyPayments) }}
                            {{ t('debt_monthly_deducted') }}
                        </p>
                        <p v-if="highInterestDebts.length > 0" class="text-[13px] text-tema-brick">
                            {{ highInterestDebts.length }} {{ t('debt_high_rate_info') }}
                        </p>
                    </div>

                </template>

            </div>

            <div class="flex gap-3 mt-10">
                <button type="button"
                        @click="router.get(route('onboarding.habits'))"
                        class="w-14 h-14 rounded-2xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 flex items-center justify-center hover:border-[#1A2E2B]/25 transition-all flex-shrink-0">
                    {{ t('back') }}
                </button>
                <button type="button" @click="submit"
                        :disabled="hasDebts === null || isSubmitting"
                        class="flex-1 bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light disabled:opacity-40 shadow-sm">
                    {{ isSubmitting ? t('saving') : t('continue') }}
                </button>
            </div>

        </div>
    </div>
</template>
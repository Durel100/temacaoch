<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
    activeDebts:          Array,
    settledDebts:         Array,
    activeCharges:        Array,
    inactiveCharges:      Array,
    chargesConsumption:   Object,
    totalActiveDebt:      Number,
    totalMonthlyPayments: Number,
});

// --- Onglets ---
const activeTab = ref('debts');

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

// --- Dettes ---
const showDebtForm = ref(false);
const showSettled  = ref(false);
const repayingDebt = ref(null);

const debtForm = useForm({
    label:            '',
    total_amount:     null,
    remaining_amount: null,
    interest_rate:    null,
    monthly_payment:  null,
});

const repayForm = useForm({ amount: null });

function submitDebt() {
    debtForm.post(route('finances.debts.store'), {
        onSuccess: () => { showDebtForm.value = false; debtForm.reset(); },
    });
}

function openRepay(debt) {
    repayingDebt.value = debt;
    repayForm.amount   = null;
}

function closeRepay() {
    repayingDebt.value = null;
    repayForm.amount   = null;
}

function submitRepay() {
    if (!repayingDebt.value) return;
    repayForm.post(route('finances.debts.repay', repayingDebt.value.id), {
        onSuccess: () => closeRepay(),
    });
}

function deleteDebt(id) {
    if (!confirm(t('confirm_delete_debt'))) return;
    router.delete(route('finances.debts.destroy', id));
}

function debtProgress(debt) {
    if (!debt.total_amount) return 0;
    const paid = debt.total_amount - debt.remaining_amount;
    return Math.round((paid / debt.total_amount) * 100);
}

// --- Charges fixes ---
const showChargeForm = ref(false);
const showInactive   = ref(false);

const chargeForm = useForm({
    label:     '',
    amount:    null,
    frequency: 'monthly',
});

function submitCharge() {
    chargeForm.post(route('finances.charges.store'), {
        onSuccess: () => { showChargeForm.value = false; chargeForm.reset(); },
    });
}

function toggleCharge(id) {
    router.patch(route('finances.charges.toggle', id), {}, { preserveScroll: true });
}

function deleteCharge(id) {
    if (!confirm(t('confirm_delete_charge'))) return;
    router.delete(route('finances.charges.destroy', id));
}

function getConsumption(chargeId) {
    return props.chargesConsumption?.[chargeId] ?? null;
}

function circleCircumference(r) { return 2 * Math.PI * r; }
function circleDashOffset(percent, r) {
    return circleCircumference(r) * (1 - Math.min(100, percent) / 100);
}

const frequencyLabel = computed(() => ({
    monthly: t('freq_monthly_short'),
    weekly:  t('freq_weekly_short'),
    yearly:  t('freq_yearly_short'),
}));
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-4">
                <button @click="router.get(route('dashboard'))"
                        class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                    ←
                </button>
                <h1 class="font-display text-[20px] font-semibold text-[#1A2E2B] flex-1">
                    {{ t('finances_title') }}
                </h1>
            </div>

            <!-- Onglets -->
            <div class="max-w-2xl mx-auto px-4 flex gap-1 pb-0">
                <button v-for="tab in [
                        { value: 'debts',   label: t('debts_tab'),   count: activeDebts.length },
                        { value: 'charges', label: t('charges_tab'), count: activeCharges.length },
                    ]"
                        :key="tab.value"
                        @click="activeTab = tab.value"
                        class="flex-1 py-3 text-[13px] font-semibold border-b-2 transition-all"
                        :class="activeTab === tab.value
                            ? 'border-tema-green text-tema-green'
                            : 'border-transparent text-[#1A2E2B]/40 hover:text-[#1A2E2B]/70'">
                    {{ tab.label }}
                    <span class="ml-1.5 text-[11px] px-1.5 py-0.5 rounded-full"
                          :class="activeTab === tab.value
                              ? 'bg-tema-green/10 text-tema-green'
                              : 'bg-[#1A2E2B]/6 text-[#1A2E2B]/40'">
                        {{ tab.count }}
                    </span>
                </button>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-6">

            <!-- ══════════════ ONGLET DETTES ══════════════ -->
            <div v-if="activeTab === 'debts'" class="space-y-4">

                <!-- Résumé -->
                <div v-if="activeDebts.length > 0" class="grid grid-cols-2 gap-3">
                    <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 text-center">
                        <p class="text-[11px] text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                            {{ t('total_remaining') }}
                        </p>
                        <p class="font-display text-[20px] font-semibold text-tema-brick">
                            {{ formatFcfa(totalActiveDebt) }}
                        </p>
                    </div>
                    <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 text-center">
                        <p class="text-[11px] text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                            {{ t('monthly_payments') }}
                        </p>
                        <p class="font-display text-[20px] font-semibold text-tema-terracotta">
                            {{ formatFcfa(totalMonthlyPayments) }}
                        </p>
                    </div>
                </div>

                <!-- Dettes actives vide -->
                <div v-if="activeDebts.length === 0"
                     class="bg-white rounded-2xl border border-dashed border-[#1A2E2B]/10 p-8 text-center">
                    <p class="text-4xl mb-3">✨</p>
                    <p class="text-[14px] font-semibold text-[#1A2E2B] mb-1">{{ t('no_active_debt') }}</p>
                    <p class="text-[13px] text-[#1A2E2B]/50">{{ t('no_active_debt_desc') }}</p>
                </div>

                <!-- Liste dettes actives -->
                <div v-for="(debt, index) in activeDebts" :key="debt.id"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">

                    <div class="p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-[15px] font-semibold text-[#1A2E2B]">{{ debt.label }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span v-if="debt.interest_rate > 15"
                                          class="text-[11px] bg-tema-brick/10 text-tema-brick px-2 py-0.5 rounded-full font-medium">
                                        {{ debt.interest_rate }}% — {{ t('high_rate') }}
                                    </span>
                                    <span v-else-if="debt.interest_rate > 0"
                                          class="text-[11px] text-[#1A2E2B]/40">
                                        {{ debt.interest_rate }}% {{ t('interest') }}
                                    </span>
                                </div>
                            </div>
                            <button @click="deleteDebt(debt.id)"
                                    class="w-7 h-7 rounded-full bg-[#1A2E2B]/5 text-[#1A2E2B]/30 text-xs flex items-center justify-center hover:bg-tema-brick/10 hover:text-tema-brick transition-all">
                                ✕
                            </button>
                        </div>

                        <!-- Barre de progression -->
                        <div class="h-2 rounded-full bg-[#1A2E2B]/6 overflow-hidden mb-2">
                            <div class="h-full rounded-full transition-all duration-700"
                                 :class="debtProgress(debt) >= 80 ? 'bg-tema-green'
                                     : debtProgress(debt) >= 50 ? 'bg-tema-ocre'
                                     : 'bg-tema-brick/70'"
                                 :style="{ width: debtProgress(debt) + '%' }"/>
                        </div>
                        <div class="flex justify-between text-[12px] text-[#1A2E2B]/50 mb-3">
                            <span>{{ debtProgress(debt) }}% {{ t('repaid') }}</span>
                            <span>{{ formatFcfa(debt.remaining_amount) }} {{ t('remaining_budget_label') }} / {{ formatFcfa(debt.total_amount) }}</span>
                        </div>

                        <div v-if="debt.monthly_payment"
                             class="text-[12px] text-[#1A2E2B]/50 mb-3">
                            {{ t('monthly_payment_label') }} :
                            <span class="font-semibold text-[#1A2E2B]">{{ formatFcfa(debt.monthly_payment) }}</span>
                        </div>

                        <!-- Actions remboursement -->
                        <div v-if="repayingDebt?.id === debt.id" class="space-y-2">
                            <div class="flex gap-2">
                                <input type="number"
                                       v-model.number="repayForm.amount"
                                       :max="debt.remaining_amount"
                                       :placeholder="t('repay_amount_ph')"
                                       class="flex-1 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                                <button @click="submitRepay"
                                        :disabled="!repayForm.amount || repayForm.processing"
                                        class="bg-tema-green text-white text-[13px] font-semibold px-4 rounded-xl disabled:opacity-40 hover:bg-tema-green-light transition-all">
                                    {{ repayForm.processing ? '...' : 'OK' }}
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <button v-for="pct in [25, 50, 100]" :key="pct"
                                        @click="repayForm.amount = Math.round(debt.remaining_amount * pct / 100)"
                                        class="flex-1 text-[12px] py-1.5 rounded-xl border-[1.5px] border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-tema-green hover:text-tema-green transition-all">
                                    {{ pct === 100 ? t('settle_all') : pct + '%' }}
                                </button>
                            </div>
                            <button @click="closeRepay"
                                    class="w-full text-[12px] text-[#1A2E2B]/40 hover:text-[#1A2E2B] transition-colors py-1">
                                {{ t('cancel') }}
                            </button>
                        </div>

                        <button v-else
                                @click="openRepay(debt)"
                                class="w-full py-2.5 rounded-xl bg-tema-green/8 text-tema-green text-[13px] font-semibold hover:bg-tema-green/15 transition-all">
                            {{ t('record_repayment') }}
                        </button>
                    </div>
                </div>

                <!-- Bouton ajouter dette -->
                <div v-if="!showDebtForm">
                    <button @click="showDebtForm = true"
                            class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all">
                        {{ t('add_debt') }}
                    </button>
                </div>

                <!-- Formulaire nouvelle dette -->
                <div v-if="showDebtForm"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5 space-y-3">
                    <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('new_debt') }}</p>

                    <input type="text"
                           v-model="debtForm.label"
                           :placeholder="t('debt_label_placeholder')"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">

                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            <label class="block text-[11px] text-[#1A2E2B]/40 mb-1 font-semibold uppercase tracking-wide">
                                {{ t('total_amount') }}
                            </label>
                            <input type="number" v-model.number="debtForm.total_amount"
                                   placeholder="200 000" min="0"
                                   class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-14 py-2.5">
                            <span class="absolute right-3 bottom-2.5 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                        <div class="relative">
                            <label class="block text-[11px] text-[#1A2E2B]/40 mb-1 font-semibold uppercase tracking-wide">
                                {{ t('remaining_amount') }}
                            </label>
                            <input type="number" v-model.number="debtForm.remaining_amount"
                                   placeholder="150 000" min="0"
                                   class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-14 py-2.5">
                            <span class="absolute right-3 bottom-2.5 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                        <div class="relative">
                            <label class="block text-[11px] text-[#1A2E2B]/40 mb-1 font-semibold uppercase tracking-wide">
                                {{ t('interest_rate') }}
                            </label>
                            <input type="number" v-model.number="debtForm.interest_rate"
                                   placeholder="0" min="0" step="0.1"
                                   class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-8 py-2.5">
                            <span class="absolute right-3 bottom-2.5 text-[11px] text-[#1A2E2B]/40">%</span>
                            <p v-if="debtForm.interest_rate > 15"
                               class="text-[11px] text-tema-brick mt-1">
                                {{ t('high_rate_warning') }}
                            </p>
                        </div>
                        <div class="relative">
                            <label class="block text-[11px] text-[#1A2E2B]/40 mb-1 font-semibold uppercase tracking-wide">
                                {{ t('monthly_payment') }}
                            </label>
                            <input type="number" v-model.number="debtForm.monthly_payment"
                                   placeholder="25 000" min="0"
                                   class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-14 py-2.5">
                            <span class="absolute right-3 bottom-2.5 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button @click="showDebtForm = false; debtForm.reset()"
                                class="flex-1 py-3 rounded-xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 text-[13px] hover:border-[#1A2E2B]/25 transition-all">
                            {{ t('cancel') }}
                        </button>
                        <button @click="submitDebt"
                                :disabled="!debtForm.label || !debtForm.total_amount || debtForm.processing"
                                class="flex-1 py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                            {{ debtForm.processing ? '...' : t('add') }}
                        </button>
                    </div>
                </div>

                <!-- Dettes soldées -->
                <div v-if="settledDebts.length > 0" class="mt-2">
                    <button @click="showSettled = !showSettled"
                            class="w-full flex items-center justify-between px-4 py-3 bg-white rounded-2xl border border-[#1A2E2B]/10 text-[13px] text-[#1A2E2B]/60 hover:text-[#1A2E2B] transition-all">
                        <span class="font-semibold">
                            {{ t('settled_debts') }} ({{ settledDebts.length }})
                        </span>
                        <span :class="showSettled ? 'rotate-180' : ''"
                              class="transition-transform text-[#1A2E2B]/40">▾</span>
                    </button>

                    <div v-if="showSettled" class="mt-2 space-y-2">
                        <div v-for="debt in settledDebts" :key="debt.id"
                             class="bg-white rounded-2xl border border-[#1A2E2B]/8 p-4 opacity-70">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-[14px] font-medium text-[#1A2E2B] line-through">
                                        {{ debt.label }}
                                    </p>
                                    <p class="text-[12px] text-tema-green mt-0.5">
                                        ✓ {{ t('debt_settled') }} — {{ formatFcfa(debt.total_amount) }} {{ t('repaid_label') }}
                                    </p>
                                </div>
                                <button @click="deleteDebt(debt.id)"
                                        class="w-7 h-7 rounded-full bg-[#1A2E2B]/5 text-[#1A2E2B]/30 text-xs flex items-center justify-center hover:bg-tema-brick/10 hover:text-tema-brick transition-all">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ══════════════ ONGLET CHARGES FIXES ══════════════ -->
            <div v-if="activeTab === 'charges'" class="space-y-4">

                <!-- Charges actives vide -->
                <div v-if="activeCharges.length === 0"
                     class="bg-white rounded-2xl border border-dashed border-[#1A2E2B]/10 p-8 text-center">
                    <p class="text-4xl mb-3">📋</p>
                    <p class="text-[14px] font-semibold text-[#1A2E2B] mb-1">{{ t('no_active_charge') }}</p>
                    <p class="text-[13px] text-[#1A2E2B]/50">{{ t('no_active_charge_desc') }}</p>
                </div>

                <!-- Liste charges actives -->
                <div v-for="charge in activeCharges" :key="charge.id"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">

                    <div class="flex items-start gap-3">

                        <!-- Cercle SVG consommation -->
                        <div v-if="getConsumption(charge.id)" class="flex-shrink-0">
                            <div class="relative">
                                <svg width="52" height="52" class="-rotate-90">
                                    <circle cx="26" cy="26" r="20"
                                            fill="none" stroke="#FAF6F0" stroke-width="5"/>
                                    <circle cx="26" cy="26" r="20"
                                            fill="none" stroke="currentColor" stroke-width="5"
                                            stroke-linecap="round"
                                            :class="getConsumption(charge.id).is_over ? 'text-tema-brick'
                                                : getConsumption(charge.id).percent > 80 ? 'text-tema-ocre'
                                                : 'text-tema-green'"
                                            :stroke-dasharray="circleCircumference(20)"
                                            :stroke-dashoffset="circleDashOffset(getConsumption(charge.id).percent, 20)"
                                            style="transition: stroke-dashoffset 0.8s ease"/>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-[10px] font-semibold"
                                          :class="getConsumption(charge.id).is_over ? 'text-tema-brick'
                                              : getConsumption(charge.id).percent > 80 ? 'text-tema-ocre'
                                              : 'text-[#1A2E2B]'">
                                        {{ Math.min(100, getConsumption(charge.id).percent) }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Infos -->
                        <div class="flex-1 min-w-0">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ charge.label }}</p>
                            <p class="text-[13px] text-[#1A2E2B]/60 mt-0.5">
                                {{ formatFcfa(charge.amount) }}
                                <span class="text-[#1A2E2B]/40">{{ frequencyLabel[charge.frequency] }}</span>
                            </p>
                            <div v-if="getConsumption(charge.id)" class="mt-1">
                                <p v-if="getConsumption(charge.id).is_over"
                                   class="text-[12px] text-tema-brick">
                                    +{{ formatFcfa(getConsumption(charge.id).surplus) }} {{ t('overrun_this_month') }}
                                </p>
                                <p v-else class="text-[12px] text-[#1A2E2B]/40">
                                    {{ formatFcfa(getConsumption(charge.id).spent) }} {{ t('spent_this_month') }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-1.5 flex-shrink-0">
                            <button @click="toggleCharge(charge.id)"
                                    class="text-[11px] px-2.5 py-1.5 rounded-lg bg-[#1A2E2B]/6 text-[#1A2E2B]/50 hover:bg-tema-ocre/10 hover:text-tema-ocre transition-all font-medium whitespace-nowrap">
                                {{ t('archive') }}
                            </button>
                            <button @click="deleteCharge(charge.id)"
                                    class="text-[11px] px-2.5 py-1.5 rounded-lg bg-[#1A2E2B]/3 text-[#1A2E2B]/30 hover:bg-tema-brick/10 hover:text-tema-brick transition-all font-medium">
                                {{ t('delete') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bouton ajouter charge -->
                <div v-if="!showChargeForm">
                    <button @click="showChargeForm = true"
                            class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all">
                        {{ t('add_charge') }}
                    </button>
                </div>

                <!-- Formulaire nouvelle charge -->
                <div v-if="showChargeForm"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5 space-y-3">
                    <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('new_charge') }}</p>

                    <input type="text"
                           v-model="chargeForm.label"
                           :placeholder="t('charge_name_placeholder')"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">

                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input type="number"
                                   v-model.number="chargeForm.amount"
                                   :placeholder="t('amount')"
                                   min="0"
                                   class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3 pr-16">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                        <span class="text-[12px] font-semibold text-[#1A2E2B]/40 bg-[#1A2E2B]/6 px-3 py-3 rounded-xl flex-shrink-0">
                            {{ t('freq_monthly_short') }}
                        </span>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button @click="showChargeForm = false; chargeForm.reset()"
                                class="flex-1 py-3 rounded-xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 text-[13px] hover:border-[#1A2E2B]/25 transition-all">
                            {{ t('cancel') }}
                        </button>
                        <button @click="submitCharge"
                                :disabled="!chargeForm.label || !chargeForm.amount || chargeForm.processing"
                                class="flex-1 py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                            {{ chargeForm.processing ? '...' : t('add') }}
                        </button>
                    </div>
                </div>

                <!-- Charges archivées -->
                <div v-if="inactiveCharges.length > 0" class="mt-2">
                    <button @click="showInactive = !showInactive"
                            class="w-full flex items-center justify-between px-4 py-3 bg-white rounded-2xl border border-[#1A2E2B]/10 text-[13px] text-[#1A2E2B]/60 hover:text-[#1A2E2B] transition-all">
                        <span class="font-semibold">
                            {{ t('archived_charges') }} ({{ inactiveCharges.length }})
                        </span>
                        <span :class="showInactive ? 'rotate-180' : ''"
                              class="transition-transform text-[#1A2E2B]/40">▾</span>
                    </button>

                    <div v-if="showInactive" class="mt-2 space-y-2">
                        <div v-for="charge in inactiveCharges" :key="charge.id"
                             class="bg-white rounded-2xl border border-[#1A2E2B]/8 p-4 opacity-60">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-[14px] font-medium text-[#1A2E2B]/70">
                                        {{ charge.label }}
                                    </p>
                                    <p class="text-[12px] text-[#1A2E2B]/40 mt-0.5">
                                        {{ formatFcfa(charge.amount) }} {{ frequencyLabel[charge.frequency] }} — {{ t('archived') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <button @click="toggleCharge(charge.id)"
                                            class="text-[11px] px-2.5 py-1.5 rounded-lg bg-tema-green/8 text-tema-green hover:bg-tema-green/15 transition-all font-medium whitespace-nowrap">
                                        {{ t('reactivate') }}
                                    </button>
                                    <button @click="deleteCharge(charge.id)"
                                            class="text-[11px] px-2.5 py-1.5 rounded-lg bg-[#1A2E2B]/3 text-[#1A2E2B]/30 hover:bg-tema-brick/10 hover:text-tema-brick transition-all font-medium">
                                        {{ t('delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</template>
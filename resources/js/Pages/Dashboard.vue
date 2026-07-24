<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import axios from 'axios';

const { t, locale, tCategoryName } = useTranslation();

const props = defineProps({
    safeIncome:              Number,
    totalCharges:            Number,
    resteAVivre:             Number,
    variableSpending:        Number,
    transactionsIn:          Number,
    realRemaining:           Number,
    currentMonthIn:          Number,
    healthScore:             Number,
    healthStatus:            String,
    healthScoreDetail:       Object,
    recommendations:         Array,
    upcomingTontinePayout:   Object,
    spendingByCategory:      Object,
    daysLeftInMonth:         Number,
    debts:                   Array,
    fixedChargesConsumption: Array,
    isSnapshotMode:          Boolean,
    fixedChargesSurplus:     Number,
    goals:                   Array,
    employmentType:          String,
});

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

// ─── Santé financière ───────────────────────────────────────────────
const showHealthDetail = ref(false);

const healthConfig = computed(() => {
    switch (props.healthStatus) {
        case 'stable':   return { label: t('status_stable'), color: 'text-tema-green', bg: 'bg-tema-green' };
        case 'to_watch': return { label: t('status_watch'),  color: 'text-tema-ocre',  bg: 'bg-tema-ocre'  };
        default:         return { label: t('status_deficit'),color: 'text-tema-brick', bg: 'bg-tema-brick' };
    }
});

function circleCircumference(r) { return 2 * Math.PI * r; }
function circleDashOffset(percent, r) {
    return circleCircumference(r) * (1 - Math.min(100, percent) / 100);
}

const healthCriteria = computed(() => [
    {
        label:       t('income_stability'),
        description: t('income_stability_desc'),
        score: props.healthScoreDetail?.income_stability ?? 0,
        max: 25,
    },
    {
        label:       t('debt_level'),
        description: t('debt_level_desc'),
        score: props.healthScoreDetail?.debt_level ?? 0,
        max: 25,
    },
    {
        label:       t('emergency_fund'),
        description: t('emergency_fund_desc'),
        score: props.healthScoreDetail?.emergency_fund ?? 0,
        max: 25,
    },
    {
        label:       t('tontine_regularity'),
        description: t('tontine_regularity_desc'),
        score: props.healthScoreDetail?.tontine_regularity ?? 0,
        max: 25,
    },
]);

// ─── Budget ─────────────────────────────────────────────────────────
const showBudgetDetail = ref(false);

const spendingPercent = computed(() => {
    if (!props.resteAVivre) return 0;
    return Math.min(100, Math.round(
        ((props.variableSpending + (props.fixedChargesSurplus ?? 0)) / props.resteAVivre) * 100
    ));
});

const budgetStatusColor = computed(() => {
    if (props.realRemaining < 0) return 'text-tema-brick';
    if (props.realRemaining < props.resteAVivre * 0.2) return 'text-tema-ocre';
    return 'text-tema-dark';
});

// ─── Objectifs ──────────────────────────────────────────────────────
const hasActiveGoal    = computed(() => props.goals && props.goals.length > 0);
const showGoalForm     = ref(false);
const addingProgressTo = ref(null);

const goalForm = useForm({
    label:         '',
    target_amount: null,
    target_date:   '',
});

const progressForm = useForm({ amount: null });

const suggestedGoals = computed(() => [
    { label: t('goal_emergency'), emoji: '🛡️' },
    { label: t('goal_phone'),     emoji: '📱' },
    { label: t('goal_moto'),      emoji: '🏍️' },
    { label: t('goal_travel'),    emoji: '✈️' },
    { label: t('goal_business'),  emoji: '🚀' },
    { label: t('goal_wedding'),   emoji: '💍' },
]);

function submitGoal() {
    goalForm.post(route('goals.store'), {
        onSuccess: () => { showGoalForm.value = false; goalForm.reset(); },
    });
}

function submitProgress(goalId) {
    progressForm.post(route('goals.progress', goalId), {
        onSuccess: () => { addingProgressTo.value = null; progressForm.reset(); },
    });
}

const estimations     = ref({});
const loadingEstimate = ref(null);

async function getEstimate(goalId) {
    if (estimations.value[goalId]) { delete estimations.value[goalId]; return; }
    loadingEstimate.value = goalId;
    try {
        const res = await axios.get(route('goals.estimate', goalId));
        estimations.value[goalId] = res.data;
    } catch {
        estimations.value[goalId] = { estimation: null, message: t('service_unavailable') };
    } finally {
        loadingEstimate.value = null;
    }
}

const deleteGoalForm = useForm({});
function deleteGoal(id) {
    if (!confirm(t('confirm_delete_goal'))) return;
    deleteGoalForm.delete(route('goals.destroy', id));
}

// ─── Dettes ─────────────────────────────────────────────────────────
const totalDebt    = computed(() => (props.debts ?? []).reduce((s, d) => s + d.remaining_amount, 0));
const repayingDebt = ref(null);
const repayForm    = useForm({ amount: null });

function openRepay(debt)  { repayingDebt.value = debt; repayForm.amount = null; }
function closeRepay()     { repayingDebt.value = null; repayForm.amount = null; }

function submitRepay() {
    if (!repayingDebt.value) return;
    repayForm.post(route('finances.debts.repay', repayingDebt.value.id), {
        onSuccess: () => closeRepay(),
    });
}

function debtProgressPercent(debt) {
    if (!debt.total_amount) return 0;
    return Math.round(((debt.total_amount - debt.remaining_amount) / debt.total_amount) * 100);
}

function isAutoDebt(debt) { return debt.label === 'Découvert budget'; }

// Correspondance nom base → clé de traduction
const categoryKeyMap = {
    'Transport':               'cat_transport',
    'Nourriture/Marché':       'cat_food',
    'Recharge téléphonique':   'cat_phone',
    'Retrait agent':           'cat_withdrawal',
    'Tontine':                 'cat_tontine',
    'Famille/Aide':            'cat_family_help',
    'Factures (eau/élec)':     'cat_bills',
    'Scolarité':               'cat_school',
    'Santé':                   'cat_health',
    'Argent de poche':         'cat_pocket_money',
    'Loyer':                   'cat_rent',
    'Loisirs/Sorties':         'cat_leisure',
    'Salaire':                 'cat_salary',
    'Vente/Activité':          'cat_sales',
    'Don/Cadeau reçu':         'cat_gift',
    'Réception tontine':       'cat_tontine_received',
    'Argent envoyé par la famille': 'cat_family_received',
    'Remboursement découvert': 'cat_overdraft_repay',
};

function translateCategory(name) {
    const key = categoryKeyMap[name];
    return key ? t(key) : name;
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- ── Header ── -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
                <div>
                    <h1 class="font-display text-[20px] font-semibold text-tema-dark leading-tight">
                        TemaCoach
                    </h1>
                    <p class="text-[11px] text-tema-dark/40">{{ t('tagline') }}</p>
                </div>
                <button @click="router.get(route('profile.edit'))"
                        class="w-9 h-9 rounded-full bg-tema-green/10 text-tema-green font-semibold text-[13px] flex items-center justify-center hover:bg-tema-green/20 transition-all">
                    P
                </button>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

            <!-- ── Score de santé + Objectif actif ── -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <div class="flex gap-4 items-start">

                    <div :class="hasActiveGoal ? 'flex flex-col items-center flex-shrink-0' : 'flex items-center gap-5 flex-1'">
                        <div class="relative flex-shrink-0">
                            <svg :width="hasActiveGoal ? 64 : 88"
                                 :height="hasActiveGoal ? 64 : 88"
                                 class="-rotate-90">
                                <circle :cx="hasActiveGoal ? 32 : 44"
                                        :cy="hasActiveGoal ? 32 : 44"
                                        :r="hasActiveGoal ? 25 : 36"
                                        fill="none" stroke="#FAF6F0"
                                        :stroke-width="hasActiveGoal ? 5 : 7"/>
                                <circle :cx="hasActiveGoal ? 32 : 44"
                                        :cy="hasActiveGoal ? 32 : 44"
                                        :r="hasActiveGoal ? 25 : 36"
                                        fill="none" stroke="currentColor"
                                        :class="healthConfig.bg"
                                        :stroke-width="hasActiveGoal ? 5 : 7"
                                        :stroke-dasharray="circleCircumference(hasActiveGoal ? 25 : 36)"
                                        :stroke-dashoffset="circleDashOffset(healthScore, hasActiveGoal ? 25 : 36)"
                                        stroke-linecap="round"
                                        style="transition: stroke-dashoffset 0.8s ease"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="font-display font-semibold"
                                      :class="hasActiveGoal ? 'text-[13px]' : 'text-[18px]'"
                                      style="color:#1A2E2B">
                                    {{ healthScore }}
                                </span>
                            </div>
                        </div>
                        <div :class="hasActiveGoal ? 'text-center mt-1' : 'flex-1'">
                            <p v-if="!hasActiveGoal" class="text-[11px] text-tema-dark/40 mb-0.5">
                                {{ t('score_title') }}
                            </p>
                            <p class="font-semibold"
                               :class="[healthConfig.color, hasActiveGoal ? 'text-[11px]' : 'text-[20px] font-display']">
                                {{ healthConfig.label }}
                            </p>
                            <p v-if="!hasActiveGoal" class="text-[12px] text-tema-dark/50 mt-0.5">
                                {{ t('score_label') }}
                            </p>
                        </div>
                    </div>

                    <!-- Objectif prioritaire -->
                    <div v-if="hasActiveGoal" class="flex-1 min-w-0 border-l border-[#1A2E2B]/8 pl-4">
                        <div v-for="goal in goals.slice(0, 1)" :key="goal.id">
                            <p class="text-[11px] text-tema-dark/40 uppercase tracking-widest mb-1">
                                {{ t('priority_goal') }}
                            </p>
                            <p class="text-[14px] font-semibold text-tema-dark truncate mb-2">
                                {{ goal.label }}
                            </p>

                            <div class="h-1.5 rounded-full bg-[#FAF6F0] overflow-hidden mb-1.5">
                                <div class="h-full rounded-full bg-tema-green transition-all duration-700"
                                     :style="{ width: goal.progress_percent + '%' }"/>
                            </div>

                            <div class="flex justify-between text-[11px] mb-2">
                                <span class="text-tema-green font-semibold">
                                    {{ formatFcfa(goal.current_amount) }}
                                </span>
                                <span class="text-tema-dark/40">
                                    {{ formatFcfa(goal.target_amount) }}
                                </span>
                            </div>

                            <p class="text-[11px] text-tema-dark/40 mb-2">
                                {{ goal.progress_percent }}{{ t('achieved') }}
                                <span v-if="goal.target_date">
                                    · {{ new Date(goal.target_date).toLocaleDateString(locale === 'en' ? 'en-GB' : 'fr-FR', { month: 'short', year: 'numeric' }) }}
                                </span>
                            </p>

                            <div class="flex gap-2">
                                <button @click="addingProgressTo = addingProgressTo === goal.id ? null : goal.id"
                                        class="flex-1 text-[11px] bg-tema-green/8 text-tema-green font-semibold py-1.5 rounded-lg hover:bg-tema-green/15 transition-all">
                                    {{ t('goal_save_btn') }}
                                </button>
                                <button @click="getEstimate(goal.id)"
                                        :disabled="loadingEstimate === goal.id"
                                        class="flex-1 text-[11px] bg-[#1A2E2B]/5 text-tema-dark/60 font-semibold py-1.5 rounded-lg hover:bg-tema-ocre/10 transition-all disabled:opacity-40">
                                    {{ loadingEstimate === goal.id ? '...' : t('goal_estimate_btn') }}
                                </button>
                            </div>

                            <!-- Panel épargne -->
                            <div v-if="addingProgressTo === goal.id" class="mt-2 flex gap-2">
                                <input type="number"
                                       v-model.number="progressForm.amount"
                                       :placeholder="t('amount_saved_ph')"
                                       min="1"
                                       class="flex-1 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2">
                                <button @click="submitProgress(goal.id)"
                                        :disabled="!progressForm.amount || progressForm.processing"
                                        class="bg-tema-green text-white text-[13px] font-semibold px-3 rounded-xl disabled:opacity-40 hover:bg-tema-green-light transition-all">
                                    OK
                                </button>
                            </div>

                            <!-- Estimation IA -->
                            <div v-if="estimations[goal.id]" class="mt-2 p-3 bg-[#FAF6F0] rounded-xl">
                                <div v-if="estimations[goal.id].estimation">
                                    <p class="text-[11px] font-semibold text-tema-dark mb-1">🤖 {{ t('estimate') }}</p>
                                    <p class="text-[11px] text-tema-dark/60 mb-2">
                                        {{ estimations[goal.id].estimation.commentaire }}
                                    </p>
                                    <div class="flex gap-2 text-[11px]">
                                        <div class="flex-1 text-center">
                                            <p class="text-tema-dark/40">/ {{ t('freq_monthly') }}</p>
                                            <p class="font-semibold text-tema-green">
                                                {{ formatFcfa(estimations[goal.id].estimation.montant_epargne_suggere) }}
                                            </p>
                                        </div>
                                        <div class="flex-1 text-center">
                                            <p class="text-tema-dark/40">{{ t('duration') }}</p>
                                            <p class="font-semibold text-tema-dark">
                                                {{ estimations[goal.id].estimation.duree_mois }} {{ t('months') }}
                                            </p>
                                        </div>
                                        <div class="flex-1 text-center">
                                            <p class="text-tema-dark/40">{{ t('date') }}</p>
                                            <p class="font-semibold text-tema-dark">
                                                {{ estimations[goal.id].estimation.date_estimee }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-[11px] text-tema-dark/50 italic">
                                    {{ estimations[goal.id].message }}
                                </p>
                            </div>

                            <p v-if="goals.length > 1" class="text-[11px] text-tema-dark/30 mt-2 text-center">
                                +{{ goals.length - 1 }} {{ t('goal_other') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Bouton explication score -->
                <button @click="showHealthDetail = !showHealthDetail"
                        class="w-full mt-4 text-[11px] text-tema-dark/40 flex items-center justify-center gap-1 hover:text-tema-dark transition-colors">
                    {{ t('score_how') }}
                    <span :class="showHealthDetail ? 'rotate-180' : ''" class="transition-transform">▾</span>
                </button>

                <!-- Détail du score -->
                <div v-if="showHealthDetail" class="mt-3 pt-3 border-t border-[#1A2E2B]/6 space-y-3">
                    <p class="text-[11px] text-tema-dark/50">{{ t('score_4criteria') }}</p>
                    <div v-for="c in healthCriteria" :key="c.label">
                        <div class="flex justify-between items-center mb-1">
                            <p class="text-[12px] font-semibold text-tema-dark">{{ c.label }}</p>
                            <p class="text-[12px] font-semibold"
                               :class="c.score >= 20 ? 'text-tema-green' : c.score >= 10 ? 'text-tema-ocre' : 'text-tema-brick'">
                                {{ c.score }}/{{ c.max }}
                            </p>
                        </div>
                        <div class="h-1.5 rounded-full bg-[#FAF6F0] overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700"
                                 :class="c.score >= 20 ? 'bg-tema-green' : c.score >= 10 ? 'bg-tema-ocre' : 'bg-tema-brick'"
                                 :style="{ width: (c.score / c.max * 100) + '%' }"/>
                        </div>
                        <p class="text-[11px] text-tema-dark/35 mt-0.5">{{ c.description }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Mes objectifs ── -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <div class="flex justify-between items-center mb-3">
                    <div>
                        <p class="text-[14px] font-semibold text-tema-dark">{{ t('my_goals') }}</p>
                        <p class="text-[11px] text-tema-dark/40 mt-0.5">
                            {{ goals.length }} {{ t('in_progress') }}
                        </p>
                    </div>
                    <button @click="showGoalForm = !showGoalForm"
                            class="text-[12px] bg-tema-green/8 text-tema-green font-semibold px-3 py-1.5 rounded-xl hover:bg-tema-green/15 transition-all">
                        {{ t('new') }}
                    </button>
                </div>

                <!-- Formulaire -->
                <div v-if="showGoalForm" class="mb-4 space-y-2.5 pt-3 border-t border-[#1A2E2B]/6">
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="s in suggestedGoals" :key="s.label"
                                @click="goalForm.label = s.label"
                                class="flex items-center gap-1 px-2.5 py-1.5 rounded-full border-[1.5px] text-[12px] font-medium transition-all"
                                :class="goalForm.label === s.label
                                    ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                    : 'border-[#1A2E2B]/10 text-tema-dark/60 hover:border-tema-green/40'">
                            {{ s.emoji }} {{ s.label }}
                        </button>
                    </div>
                    <input type="text"
                           v-model="goalForm.label"
                           :placeholder="t('goal_name')"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            <input type="number"
                                   v-model.number="goalForm.target_amount"
                                   :placeholder="t('target_amount')"
                                   class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 pr-14">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-tema-dark/40">FCFA</span>
                        </div>
                        <input type="date"
                               v-model="goalForm.target_date"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                    </div>
                    <div class="flex gap-2">
                        <button @click="showGoalForm = false; goalForm.reset()"
                                class="flex-1 py-2.5 rounded-xl border-[1.5px] border-[#1A2E2B]/12 text-tema-dark/50 text-[13px] hover:border-[#1A2E2B]/25 transition-all">
                            {{ t('cancel') }}
                        </button>
                        <button @click="submitGoal"
                                :disabled="!goalForm.label || !goalForm.target_amount || goalForm.processing"
                                class="flex-1 py-2.5 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                            {{ goalForm.processing ? '...' : t('create') }}
                        </button>
                    </div>
                </div>

                <!-- Liste des autres objectifs -->
                <div v-if="goals.length > 1" class="space-y-3 pt-2 border-t border-[#1A2E2B]/6">
                    <div v-for="goal in goals.slice(1)" :key="goal.id"
                         class="flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-tema-dark truncate">{{ goal.label }}</p>
                            <div class="h-1 rounded-full bg-[#FAF6F0] overflow-hidden mt-1">
                                <div class="h-full rounded-full bg-tema-green/60"
                                     :style="{ width: goal.progress_percent + '%' }"/>
                            </div>
                            <p class="text-[11px] text-tema-dark/40 mt-0.5">
                                {{ formatFcfa(goal.current_amount) }} / {{ formatFcfa(goal.target_amount) }}
                            </p>
                        </div>
                        <button @click="deleteGoal(goal.id)"
                                class="text-tema-dark/20 hover:text-tema-brick transition-colors text-[12px] flex-shrink-0">
                            ✕
                        </button>
                    </div>
                </div>

                <div v-if="goals.length === 0" class="text-center py-4">
                    <p class="text-[12px] text-tema-dark/35">{{ t('no_goals') }}</p>
                </div>
            </div>

            <!-- ── Budget ce mois ── -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <div class="flex justify-between items-center mb-4">
                    <p class="text-[11px] font-semibold text-tema-dark/40 uppercase tracking-widest">
                        {{ t('budget_this_month') }}
                    </p>
                    <span class="text-[11px] text-tema-dark/40 bg-[#FAF6F0] rounded-full px-2.5 py-1">
                        {{ daysLeftInMonth }} {{ t('days_remaining') }}
                    </span>
                </div>

                <!-- Montant principal -->
                <div class="text-center mb-4">
                    <p class="text-[12px] text-tema-dark/50 mb-1">{{ t('remaining_budget') }}</p>
                    <p class="font-display text-[36px] font-semibold leading-none mb-1"
                       :class="budgetStatusColor">
                        {{ formatFcfa(realRemaining) }}
                    </p>
                    <p class="text-[11px]"
                       :class="isSnapshotMode ? 'text-tema-ocre' : 'text-tema-dark/35'">
                        {{ isSnapshotMode
                            ? t('snapshot_mode')
                            : employmentType === 'non_salaried'
                                ? t('prorata_mode')
                                : t('reference_mode') }}
                    </p>
                    <p v-if="realRemaining < 0"
                       class="text-[12px] text-tema-brick font-semibold mt-1">
                        {{ t('budget_exceeded') }}
                    </p>
                    <p v-else-if="realRemaining < resteAVivre * 0.2 && realRemaining >= 0"
                       class="text-[12px] text-tema-ocre mt-1">
                        {{ t('tight_margin') }}
                    </p>
                </div>

                <!-- Alerte surplus charges fixes -->
                <div v-if="fixedChargesSurplus > 0"
                     class="bg-tema-brick/8 rounded-xl px-3 py-2 text-[12px] text-tema-brick mb-3">
                    {{ formatFcfa(fixedChargesSurplus) }} {{ t('fixed_surplus_alert') }}
                </div>

                <!-- Barre de progression -->
                <div class="h-2 rounded-full bg-[#FAF6F0] overflow-hidden mb-1.5">
                    <div class="h-full rounded-full transition-all duration-700"
                         :class="realRemaining < 0 ? 'bg-tema-brick' : 'bg-tema-terracotta'"
                         :style="{ width: spendingPercent + '%' }"/>
                </div>
                <div class="flex justify-between text-[11px] text-tema-dark/40 mb-4">
                    <span>{{ formatFcfa(variableSpending) }} {{ t('spent_excl_fixed') }}</span>
                    <span>{{ spendingPercent }}%</span>
                </div>

                <!-- Toggle entrées/sorties -->
                <button @click="showBudgetDetail = !showBudgetDetail"
                        class="w-full flex items-center justify-between px-4 py-2.5 bg-[#FAF6F0] rounded-xl text-[12px] text-tema-dark/60 hover:bg-tema-ocre/8 transition-all">
                    <span class="font-semibold">{{ t('see_income_expenses') }}</span>
                    <span :class="showBudgetDetail ? 'rotate-180' : ''" class="transition-transform text-tema-dark/40">▾</span>
                </button>

                <div v-if="showBudgetDetail" class="mt-3 grid grid-cols-2 gap-3">
                    <div class="bg-tema-green/8 rounded-xl p-3 text-center">
                        <p class="text-[11px] text-tema-dark/50 mb-0.5">{{ t('monthly_income') }}</p>
                        <p class="font-display font-semibold text-tema-green text-[15px]">
                            +{{ formatFcfa(currentMonthIn) }}
                        </p>
                    </div>
                    <div class="bg-tema-brick/8 rounded-xl p-3 text-center">
                        <p class="text-[11px] text-tema-dark/50 mb-0.5">{{ t('free_expenses') }}</p>
                        <p class="font-display font-semibold text-tema-brick text-[15px]">
                            -{{ formatFcfa(variableSpending) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Budgets fixes ── -->
            <div v-if="fixedChargesConsumption && fixedChargesConsumption.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <div class="flex justify-between items-center mb-5">
                    <p class="text-[11px] font-semibold text-tema-dark/40 uppercase tracking-widest">
                        {{ t('fixed_budgets_month') }}
                    </p>
                    <button @click="router.get(route('finances.index', {tab: 'charges'}))"
                            class="text-[11px] text-tema-green hover:underline">
                        {{ t('manage') }}
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-5">
                    <div v-for="charge in fixedChargesConsumption" :key="charge.id"
                         class="flex flex-col items-center">
                        <div class="relative mb-2">
                            <svg width="72" height="72" class="-rotate-90">
                                <circle cx="36" cy="36" r="28" fill="none" stroke="#FAF6F0" stroke-width="6"/>
                                <circle cx="36" cy="36" r="28"
                                        fill="none" stroke="currentColor" stroke-width="6"
                                        stroke-linecap="round"
                                        :class="charge.is_over ? 'text-tema-brick'
                                            : charge.percent > 80 ? 'text-tema-ocre'
                                            : 'text-tema-green'"
                                        :stroke-dasharray="circleCircumference(28)"
                                        :stroke-dashoffset="circleDashOffset(charge.percent, 28)"
                                        style="transition: stroke-dashoffset 0.8s ease"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[12px] font-semibold"
                                      :class="charge.is_over ? 'text-tema-brick'
                                          : charge.percent > 80 ? 'text-tema-ocre'
                                          : 'text-tema-dark'">
                                    {{ Math.min(100, charge.percent) }}%
                                </span>
                            </div>
                        </div>
                        <p class="text-[12px] font-semibold text-tema-dark text-center truncate w-full">
                            {{ charge.label }}
                        </p>
                        <p class="text-[11px] text-tema-dark/50 text-center">
                            {{ formatFcfa(charge.spent) }}
                            <span class="text-tema-dark/30"> / {{ formatFcfa(charge.budget) }}</span>
                        </p>
                        <p v-if="charge.is_over"
                           class="text-[11px] text-tema-brick text-center mt-0.5">
                            +{{ formatFcfa(charge.surplus) }} {{ t('surplus_on_remaining') }}
                        </p>
                        <p v-else class="text-[11px] text-tema-dark/35 text-center mt-0.5">
                            {{ formatFcfa(charge.remaining) }} {{ t('remaining_budget_label') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Alerte tontine ── -->
            <div v-if="upcomingTontinePayout"
                 class="bg-tema-ocre/15 border border-tema-ocre/25 rounded-2xl p-4 flex items-center gap-3">
                <span class="text-2xl flex-shrink-0">🔔</span>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-tema-dark">
                        {{ t('incoming_tontine') }} {{ upcomingTontinePayout.days_until }} {{ t('days') }}
                    </p>
                    <p class="text-[12px] text-tema-dark/60 mt-0.5">
                        {{ formatFcfa(upcomingTontinePayout.amount) }} {{ t('incoming_tontine_plan') }}
                    </p>
                </div>
            </div>

            <!-- ── Dettes ── -->
            <div v-if="debts && debts.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <div class="flex justify-between items-center mb-4">
                    <p class="text-[11px] font-semibold text-tema-dark/40 uppercase tracking-widest">
                        {{ t('active_debts') }}
                    </p>
                    <button @click="router.get(route('finances.index'))"
                            class="text-[11px] text-tema-green hover:underline">
                        {{ t('manage') }}
                    </button>
                </div>

                <div v-for="(debt, index) in debts" :key="debt.id">

                    <div class="flex items-center gap-3 py-3"
                         :class="index < debts.length - 1 || repayingDebt?.id === debt.id
                             ? 'border-b border-[#1A2E2B]/5' : ''">

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                <p class="text-[13px] font-semibold text-tema-dark truncate">
                                    {{ debt.label }}
                                </p>
                                <span v-if="isAutoDebt(debt)"
                                      class="text-[10px] bg-tema-brick/10 text-tema-brick px-1.5 py-0.5 rounded-full font-semibold flex-shrink-0">
                                    {{ t('debt_auto_badge') }}
                                </span>
                            </div>
                            <div class="h-1.5 rounded-full bg-[#FAF6F0] overflow-hidden mb-1.5">
                                <div class="h-full rounded-full transition-all duration-700"
                                     :class="debtProgressPercent(debt) >= 80 ? 'bg-tema-green'
                                         : debtProgressPercent(debt) >= 50 ? 'bg-tema-ocre'
                                         : 'bg-tema-brick/70'"
                                     :style="{ width: debtProgressPercent(debt) + '%' }"/>
                            </div>
                            <div class="flex justify-between text-[11px]">
                                <span class="text-tema-dark/50">
                                    {{ formatFcfa(debt.remaining_amount) }} {{ t('remaining_budget_label') }}
                                </span>
                                <span class="text-tema-dark/30">
                                    / {{ formatFcfa(debt.total_amount) }}
                                </span>
                            </div>
                        </div>

                        <button @click="repayingDebt?.id === debt.id ? closeRepay() : openRepay(debt)"
                                class="flex-shrink-0 text-[11px] font-semibold px-3 py-1.5 rounded-lg transition-all"
                                :class="repayingDebt?.id === debt.id
                                    ? 'bg-[#1A2E2B]/8 text-tema-dark/50'
                                    : 'bg-tema-green/8 text-tema-green hover:bg-tema-green/15'">
                            {{ repayingDebt?.id === debt.id ? t('cancel') : t('repay') }}
                        </button>
                    </div>

                    <!-- Panel remboursement -->
                    <div v-if="repayingDebt?.id === debt.id"
                         class="py-3"
                         :class="index < debts.length - 1 ? 'border-b border-[#1A2E2B]/5' : ''">
                        <div class="flex gap-2 mb-2">
                            <input type="number"
                                   v-model.number="repayForm.amount"
                                   :max="debt.remaining_amount"
                                   min="1"
                                   :placeholder="t('repay_amount_ph')"
                                   class="flex-1 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                            <button @click="submitRepay"
                                    :disabled="!repayForm.amount || repayForm.amount <= 0 || repayForm.processing"
                                    class="bg-tema-green text-white text-[13px] font-semibold px-4 rounded-xl disabled:opacity-40 hover:bg-tema-green-light transition-all">
                                {{ repayForm.processing ? '...' : t('validate') }}
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button v-for="pct in [25, 50, 100]" :key="pct"
                                    @click="repayForm.amount = Math.round(debt.remaining_amount * pct / 100)"
                                    class="flex-1 text-[11px] py-1.5 rounded-full border-[1.5px] border-[#1A2E2B]/10 text-tema-dark/60 hover:border-tema-green hover:text-tema-green transition-all">
                                {{ pct === 100 ? t('settle_all') : pct + '%' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-[#1A2E2B]/5 flex justify-between items-center">
                    <p class="text-[12px] text-tema-dark/50">{{ t('total_remaining') }}</p>
                    <p class="font-display font-semibold text-tema-brick text-[15px]">
                        {{ formatFcfa(totalDebt) }}
                    </p>
                </div>
            </div>

            <!-- ── Recommandations ── -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-tema-dark/40 uppercase tracking-widest mb-3">
                    {{ t('coach_advice') }}
                </p>

                <div v-if="!recommendations || recommendations.length === 0"
                     class="text-[13px] text-tema-dark/40 text-center py-3">
                    {{ t('no_advice') }}
                </div>

                <div v-for="(rec, index) in recommendations" :key="rec.rule_id"
                     class="flex gap-3 py-3"
                     :class="index < recommendations.length - 1 ? 'border-b border-[#1A2E2B]/5' : ''">
                    <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center text-[11px] font-semibold mt-0.5"
                         :class="rec.priority === 1 ? 'bg-tema-brick/12 text-tema-brick'
                             : rec.priority === 2 ? 'bg-tema-ocre/15 text-tema-ocre'
                             : 'bg-tema-green/8 text-tema-green'">
                        {{ rec.priority }}
                    </div>
                    <p class="text-[13px] text-tema-dark/80 leading-relaxed">{{ rec.message }}</p>
                </div>

                <button @click="router.get(route('coach.index'))"
                        class="w-full mt-4 text-[13px] text-tema-green font-semibold border border-tema-green/20 rounded-xl py-2.5 hover:bg-tema-green/5 transition-all">
                    {{ t('talk_to_coach') }}
                </button>
            </div>

            <!-- ── Dépenses par catégorie ── -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <div class="flex justify-between items-center mb-4">
                    <p class="text-[11px] font-semibold text-tema-dark/40 uppercase tracking-widest">
                        {{ t('monthly_spending') }}
                    </p>
                    <button @click="router.get(route('transactions.index'))"
                            class="text-[11px] text-tema-green hover:underline">
                        {{ t('see_all') }}
                    </button>
                </div>

                <div v-if="spendingByCategory && Object.keys(spendingByCategory).length > 0"
                     class="space-y-2.5">
                    <div v-for="(amount, category) in spendingByCategory" :key="category"
                         class="flex items-center justify-between">
                        <span class="text-[13px] text-tema-dark/70 truncate flex-1 mr-2">
                            {{ translateCategory(category) }}
                        </span>
                        <span class="text-[13px] font-semibold text-tema-dark flex-shrink-0">
                            {{ formatFcfa(amount) }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-[13px] text-tema-dark/35 text-center py-2">
                    {{ t('no_spending') }}
                </p>
            </div>

            <!-- ── Accès rapide ── -->
            <div class="grid grid-cols-2 gap-3 pb-6">
                <button @click="router.get(route('transactions.create'))"
                        class="bg-tema-green text-white font-semibold py-4 rounded-2xl hover:bg-tema-green-light transition-all text-[14px] col-span-2">
                    {{ t('add_transaction') }}
                </button>
                <button @click="router.get(route('coach.index'))"
                        class="bg-white border-[1.5px] border-tema-green/30 text-tema-green font-semibold py-4 rounded-2xl hover:bg-tema-green/5 transition-all text-[13px]">
                    💬 {{ t('coach') }}
                </button>
                <button @click="router.get(route('tontines.index'))"
                        class="bg-white border-[1.5px] border-[#1A2E2B]/10 text-tema-dark/70 font-semibold py-4 rounded-2xl hover:border-[#1A2E2B]/20 transition-all text-[13px]">
                    🤝 {{ t('tontines') }}
                </button>
                <button @click="router.get(route('finances.index'))"
                        class="bg-white border-[1.5px] border-[#1A2E2B]/10 text-tema-dark/70 font-semibold py-4 rounded-2xl hover:border-[#1A2E2B]/20 transition-all text-[13px]">
                    💳 {{ t('finances') }}
                </button>
                <button @click="router.get(route('stats.index'))"
                        class="bg-white border-[1.5px] border-[#1A2E2B]/10 text-tema-dark/70 font-semibold py-4 rounded-2xl hover:border-[#1A2E2B]/20 transition-all text-[13px]">
                    📊 {{ t('stats') }}
                </button>
            </div>

        </div>
    </div>
</template>
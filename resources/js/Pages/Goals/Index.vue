<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import axios from 'axios';

const { t, locale } = useTranslation();

const props = defineProps({
    goals: Array,
});

const showGoalForm     = ref(false);
const addingProgressTo = ref(null);
const estimations      = ref({});
const loadingEstimate  = ref(null);

const goalForm = useForm({
    label:         '',
    target_amount: null,
    target_date:   '',
});

const progressForm = useForm({ amount: null });
const deleteGoalForm = useForm({});

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

function deleteGoal(id) {
    if (!confirm(t('confirm_delete_goal'))) return;
    deleteGoalForm.delete(route('goals.destroy', id));
}

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

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'fr-FR', {
        month: 'short', year: 'numeric',
    });
}

function progressColor(percent) {
    if (percent >= 100) return 'bg-tema-green';
    if (percent >= 50)  return 'bg-tema-ocre';
    return 'bg-tema-terracotta';
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8" style="padding-top: env(safe-area-inset-top)">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="router.get(route('dashboard'))"
                            class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                        ←
                    </button>
                    <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B]">
                        {{ t('my_goals') }}
                    </h1>
                    <span class="bg-tema-green/10 text-tema-green text-[11px] font-semibold px-2.5 py-1 rounded-full">
                        {{ goals.length }}
                    </span>
                </div>
                <button @click="showGoalForm = !showGoalForm"
                        class="text-[12px] bg-tema-green/10 text-tema-green font-semibold px-3 py-1.5 rounded-xl hover:bg-tema-green/20 transition-all">
                    + {{ t('new') }}
                </button>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

            <!-- Formulaire nouveau objectif -->
            <div v-if="showGoalForm"
                 class="bg-white rounded-2xl border border-tema-green/20 p-5 space-y-3">
                <p class="text-[13px] font-semibold text-[#1A2E2B]">{{ t('new_goal_title') }}</p>

                <div class="flex flex-wrap gap-1.5">
                    <button v-for="s in suggestedGoals" :key="s.label"
                            @click="goalForm.label = s.label"
                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-full border-[1.5px] text-[12px] font-medium transition-all"
                            :class="goalForm.label === s.label
                                ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-tema-green/40'">
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
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                    </div>
                    <input type="date"
                           v-model="goalForm.target_date"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                </div>

                <div class="flex gap-2">
                    <button @click="showGoalForm = false; goalForm.reset()"
                            class="flex-1 py-2.5 rounded-xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 text-[13px] hover:border-[#1A2E2B]/25 transition-all">
                        {{ t('cancel') }}
                    </button>
                    <button @click="submitGoal"
                            :disabled="!goalForm.label || !goalForm.target_amount || goalForm.processing"
                            class="flex-1 py-2.5 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                        {{ goalForm.processing ? '...' : t('create') }}
                    </button>
                </div>
            </div>

            <!-- Aucun objectif -->
            <div v-if="goals.length === 0 && !showGoalForm"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 py-12 text-center">
                <p class="text-[32px] mb-3">🎯</p>
                <p class="text-[14px] font-semibold text-[#1A2E2B]/70 mb-1">{{ t('no_goals') }}</p>
                <p class="text-[12px] text-[#1A2E2B]/40 mb-4">{{ t('no_goals_desc') }}</p>
                <button @click="showGoalForm = true"
                        class="text-[13px] bg-tema-green text-white font-semibold px-5 py-2.5 rounded-xl hover:bg-tema-green-light transition-all">
                    {{ t('create_first_goal') }}
                </button>
            </div>

            <!-- Liste des objectifs -->
            <div v-for="goal in goals" :key="goal.id"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <!-- En-tête objectif -->
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-[15px] font-semibold text-[#1A2E2B] truncate">{{ goal.label }}</p>
                        <p v-if="goal.target_date"
                           class="text-[11px] text-[#1A2E2B]/40 mt-0.5">
                            {{ t('target_date') }} : {{ formatDate(goal.target_date) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                              :class="goal.progress_percent >= 100
                                  ? 'bg-tema-green/10 text-tema-green'
                                  : goal.progress_percent >= 50
                                      ? 'bg-tema-ocre/15 text-tema-ocre'
                                      : 'bg-[#1A2E2B]/5 text-[#1A2E2B]/50'">
                            {{ goal.progress_percent }}%
                        </span>
                        <button @click="deleteGoal(goal.id)"
                                class="w-7 h-7 rounded-lg bg-tema-brick/8 text-tema-brick flex items-center justify-center hover:bg-tema-brick/20 transition-all text-[12px]">
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Barre de progression -->
                <div class="h-2.5 rounded-full bg-[#FAF6F0] overflow-hidden mb-2">
                    <div class="h-full rounded-full transition-all duration-700"
                         :class="progressColor(goal.progress_percent)"
                         :style="{ width: Math.min(100, goal.progress_percent) + '%' }"/>
                </div>

                <!-- Montants -->
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <p class="text-[15px] font-display font-semibold text-tema-green">
                            {{ formatFcfa(goal.current_amount) }}
                        </p>
                        <p class="text-[11px] text-[#1A2E2B]/40">{{ t('saved') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[15px] font-display font-semibold text-[#1A2E2B]">
                            {{ formatFcfa(goal.target_amount) }}
                        </p>
                        <p class="text-[11px] text-[#1A2E2B]/40">{{ t('target') }}</p>
                    </div>
                </div>

                <!-- Reste à atteindre -->
                <div v-if="goal.progress_percent < 100"
                     class="bg-[#FAF6F0] rounded-xl px-3 py-2 mb-4 flex justify-between items-center">
                    <p class="text-[12px] text-[#1A2E2B]/50">{{ t('remaining_to_goal') }}</p>
                    <p class="text-[13px] font-semibold text-[#1A2E2B]">
                        {{ formatFcfa(goal.target_amount - goal.current_amount) }}
                    </p>
                </div>

                <!-- Objectif atteint -->
                <div v-if="goal.progress_percent >= 100"
                     class="bg-tema-green/10 rounded-xl px-3 py-2 mb-4 text-center">
                    <p class="text-[13px] font-semibold text-tema-green">🎉 {{ t('goal_reached') }}</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-2">
                    <button @click="addingProgressTo = addingProgressTo === goal.id ? null : goal.id; progressForm.reset()"
                            class="flex-1 text-[12px] font-semibold py-2 rounded-xl transition-all"
                            :class="addingProgressTo === goal.id
                                ? 'bg-[#1A2E2B]/8 text-[#1A2E2B]/50'
                                : 'bg-tema-green/8 text-tema-green hover:bg-tema-green/15'">
                        {{ addingProgressTo === goal.id ? t('cancel') : t('goal_save_btn') }}
                    </button>
                    <button @click="getEstimate(goal.id)"
                            :disabled="loadingEstimate === goal.id"
                            class="flex-1 text-[12px] font-semibold py-2 rounded-xl bg-[#1A2E2B]/5 text-[#1A2E2B]/60 hover:bg-tema-ocre/10 transition-all disabled:opacity-40">
                        {{ loadingEstimate === goal.id ? '...' : t('goal_estimate_btn') }}
                    </button>
                </div>

                <!-- Panel épargne -->
                <div v-if="addingProgressTo === goal.id" class="mt-3 space-y-2">
                    <div class="relative">
                        <input type="number"
                               v-model.number="progressForm.amount"
                               :placeholder="t('amount_saved_ph')"
                               min="1"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 pr-16">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                    </div>
                    <button @click="submitProgress(goal.id)"
                            :disabled="!progressForm.amount || progressForm.processing"
                            class="w-full bg-tema-green text-white text-[13px] font-semibold py-2.5 rounded-xl disabled:opacity-40 hover:bg-tema-green-light transition-all">
                        {{ progressForm.processing ? t('saving') : t('validate') }}
                    </button>
                </div>

                <!-- Estimation IA -->
                <div v-if="estimations[goal.id]" class="mt-3 p-3 bg-[#FAF6F0] rounded-xl">
                    <div v-if="estimations[goal.id].estimation">
                        <p class="text-[11px] font-semibold text-[#1A2E2B] mb-1">🤖 {{ t('estimate') }}</p>
                        <p class="text-[11px] text-[#1A2E2B]/60 mb-2">
                            {{ estimations[goal.id].estimation.commentaire }}
                        </p>
                        <div class="flex gap-2 text-[11px]">
                            <div class="flex-1 text-center">
                                <p class="text-[#1A2E2B]/40">/ {{ t('freq_monthly') }}</p>
                                <p class="font-semibold text-tema-green">
                                    {{ formatFcfa(estimations[goal.id].estimation.montant_epargne_suggere) }}
                                </p>
                            </div>
                            <div class="flex-1 text-center border-x border-[#1A2E2B]/8">
                                <p class="text-[#1A2E2B]/40">{{ t('duration') }}</p>
                                <p class="font-semibold text-[#1A2E2B]">
                                    {{ estimations[goal.id].estimation.duree_mois }} {{ t('months') }}
                                </p>
                            </div>
                            <div class="flex-1 text-center">
                                <p class="text-[#1A2E2B]/40">{{ t('date') }}</p>
                                <p class="font-semibold text-[#1A2E2B]">
                                    {{ estimations[goal.id].estimation.date_estimee }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-[11px] text-[#1A2E2B]/50 italic">
                        {{ estimations[goal.id].message }}
                    </p>
                </div>

            </div>

        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import axios from 'axios';

const { t, locale } = useTranslation();

const props = defineProps({
    goals:         Array,
    archivedGoals: Array,
});

const showGoalForm    = ref(false);
const estimations     = ref({});
const loadingEstimate = ref(null);
const showArchived    = ref(false);

const goalForm       = useForm({ label: '', target_amount: null, target_date: '' });
const archiveForm    = useForm({});
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

function archiveGoal(goalId) {
    if (!confirm(t('confirm_goal_achieved'))) return;
    archiveForm.post(route('goals.archive', goalId));
}

function deleteGoal(id) {
    if (!confirm(t('confirm_delete_goal'))) return;
    deleteGoalForm.delete(route('goals.destroy', id));
}

async function getEstimate(goal) {
    if (!goal.can_estimate) return;
    if (estimations.value[goal.id]) { delete estimations.value[goal.id]; return; }
    loadingEstimate.value = goal.id;
    try {
        const res = await axios.get(route('goals.estimate', goal.id));
        estimations.value[goal.id] = res.data;
    } catch {
        estimations.value[goal.id] = { estimation: null, message: t('service_unavailable') };
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
                            class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">←</button>
                    <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B]">{{ t('my_goals') }}</h1>
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

            <!-- Info nouvelle logique -->
            <div class="bg-tema-green/8 border border-tema-green/15 rounded-2xl px-4 py-3 flex items-start gap-2">
                <span class="text-base mt-0.5">💡</span>
                <p class="text-[12px] text-tema-green/80">
                    {{ t('goal_transaction_hint') }}
                </p>
            </div>

            <!-- Formulaire nouveau objectif -->
            <div v-if="showGoalForm" class="bg-white rounded-2xl border border-tema-green/20 p-5 space-y-3">
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
                <input type="text" v-model="goalForm.label" :placeholder="t('goal_name')"
                       class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                <div class="grid grid-cols-2 gap-2">
                    <div class="relative">
                        <input type="number" v-model.number="goalForm.target_amount" :placeholder="t('target_amount')"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 pr-14">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                    </div>
                    <input type="date" v-model="goalForm.target_date"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                </div>
                <div class="flex gap-2">
                    <button @click="showGoalForm = false; goalForm.reset()"
                            class="flex-1 py-2.5 rounded-xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 text-[13px] hover:border-[#1A2E2B]/25 transition-all">
                        {{ t('cancel') }}
                    </button>
                    <button @click="submitGoal" :disabled="!goalForm.label || !goalForm.target_amount || goalForm.processing"
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

            <!-- Liste des objectifs actifs -->
            <div v-for="goal in goals" :key="goal.id"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <!-- En-tête -->
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p class="text-[15px] font-semibold text-[#1A2E2B] truncate">{{ goal.label }}</p>
                            <!-- Badge catégorie liée -->
                            <span v-if="goal.category_name"
                                  class="text-[10px] bg-tema-green/8 text-tema-green px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">
                                📊 {{ t('linked') }}
                            </span>
                        </div>
                        <p v-if="goal.target_date" class="text-[11px] text-[#1A2E2B]/40">
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
                        <p class="text-[15px] font-display font-semibold text-tema-green">{{ formatFcfa(goal.current_amount) }}</p>
                        <p class="text-[11px] text-[#1A2E2B]/40">{{ t('saved') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[15px] font-display font-semibold text-[#1A2E2B]">{{ formatFcfa(goal.target_amount) }}</p>
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

                <!-- Comment épargner -->
                <div v-if="goal.category_name && goal.progress_percent < 100"
                     class="bg-tema-green/5 rounded-xl px-3 py-2.5 mb-3">
                    <p class="text-[11px] text-tema-green/80">
                        💡 {{ t('goal_how_to_save') }}
                        <span class="font-semibold">{{ goal.category_name }}</span>
                        {{ t('goal_how_to_save_2') }}
                    </p>
                </div>

                <!-- Actions : Estimer + Objectif atteint -->
                <div class="flex gap-2">
                    <!-- Bouton Estimer -->
                    <button @click="getEstimate(goal)"
                            :disabled="loadingEstimate === goal.id || !goal.can_estimate"
                            class="flex-1 text-[12px] font-semibold py-2 rounded-xl transition-all"
                            :class="goal.can_estimate
                                ? 'bg-[#1A2E2B]/5 text-[#1A2E2B]/60 hover:bg-tema-ocre/10'
                                : 'bg-[#1A2E2B]/3 text-[#1A2E2B]/30 cursor-not-allowed'">
                        <span v-if="loadingEstimate === goal.id">...</span>
                        <span v-else-if="!goal.can_estimate">
                            🔒 {{ t('estimate_locked') }}
                        </span>
                        <span v-else>🤖 {{ t('goal_estimate_btn') }}</span>
                    </button>

                    <!-- Bouton Objectif atteint -->
                    <button @click="archiveGoal(goal.id)"
                            class="flex-1 text-[12px] font-semibold py-2 rounded-xl bg-tema-green/10 text-tema-green hover:bg-tema-green/20 transition-all">
                        ✓ {{ t('goal_achieved_btn') }}
                    </button>
                </div>

                <!-- Message estimation verrouillée -->
                <div v-if="!goal.can_estimate && goal.estimation_locked_until"
                     class="mt-2 text-center">
                    <p class="text-[11px] text-[#1A2E2B]/35">
                        {{ t('estimate_available_on') }} {{ goal.estimation_locked_until }}
                    </p>
                </div>

                <!-- Estimation IA — visible en permanence jusqu'à archivage -->
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

            <!-- Objectifs archivés -->
            <div v-if="archivedGoals && archivedGoals.length > 0">
                <button @click="showArchived = !showArchived"
                        class="w-full text-center text-[12px] text-[#1A2E2B]/40 hover:text-[#1A2E2B] transition-colors py-2">
                    {{ showArchived ? '▲' : '▼' }}
                    {{ archivedGoals.length }} {{ t('archived_goals') }}
                </button>

                <div v-if="showArchived" class="space-y-3 mt-2">
                    <div v-for="goal in archivedGoals" :key="goal.id"
                         class="bg-white rounded-2xl border border-[#1A2E2B]/6 p-4 opacity-60">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-[13px] font-semibold text-[#1A2E2B]">{{ goal.label }}</p>
                                <p class="text-[11px] text-tema-green mt-0.5">
                                    ✓ {{ t('goal_achieved_on') }} {{ formatDate(goal.archived_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[13px] font-semibold text-tema-green">{{ formatFcfa(goal.target_amount) }}</p>
                                <button @click="deleteGoal(goal.id)"
                                        class="text-[11px] text-tema-brick/50 hover:text-tema-brick mt-0.5">
                                    {{ t('delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const suggestedGoals = computed(() => [
    { label: t('goal_emergency'),    emoji: '🛡️' },
    { label: t('goal_phone'),        emoji: '📱' },
    { label: t('goal_moto'),         emoji: '🏍️' },
    { label: t('goal_travel'),       emoji: '✈️' },
    { label: t('goal_business'),     emoji: '🚀' },
    { label: t('goal_wedding'),      emoji: '💍' },
]);

const form        = ref({ financial_goals: [] });
const errors      = ref({});
const isSubmitting = ref(false);

const hasFondsUrgence = computed(() =>
    form.value.financial_goals.some(g =>
        g.label.toLowerCase().includes('urgence') ||
        g.label.toLowerCase().includes('emergency')
    )
);

function addGoal(label = '') {
    form.value.financial_goals.push({ label, target_amount: null, target_date: '' });
}

function removeGoal(index) {
    form.value.financial_goals.splice(index, 1);
}

function isAlreadyAdded(label) {
    return form.value.financial_goals.some(g => g.label === label);
}

function submit() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(route('onboarding.goals.store'), form.value, {
        onError:  (e) => { errors.value = e; },
        onFinish: () => { isSubmitting.value = false; },
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto flex items-center gap-3 px-4 py-3">
                <div class="flex gap-1 flex-1">
                    <div v-for="step in 7" :key="step"
                         class="flex-1 h-[3px] rounded-full bg-tema-green"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 7 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_goals') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">{{ t('onboarding_subtitle_goals') }}</p>
            </div>

            <!-- Suggestions -->
            <div class="mb-6">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                    {{ t('frequent_goals') }}
                </p>
                <div class="flex flex-row flex-wrap gap-2">
                    <button v-for="g in suggestedGoals" :key="g.label"
                            type="button"
                            @click="!isAlreadyAdded(g.label) && addGoal(g.label)"
                            :disabled="isAlreadyAdded(g.label)"
                            class="flex items-center gap-1.5 px-4 py-2.5 rounded-full border-[1.5px] text-[13px] font-medium transition-all whitespace-nowrap"
                            :class="isAlreadyAdded(g.label)
                                ? 'border-tema-green/30 bg-tema-green/8 text-tema-green cursor-default'
                                : 'border-[#1A2E2B]/10 bg-white text-[#1A2E2B]/70 hover:border-tema-green/40 hover:text-tema-green'">
                        <span>{{ g.emoji }}</span>{{ g.label }}
                    </button>
                </div>
            </div>

            <!-- Suggestion fonds d'urgence -->
            <div v-if="form.financial_goals.length > 0 && !hasFondsUrgence"
                 class="bg-tema-ocre/15 border border-tema-ocre/25 rounded-2xl p-4 mb-4">
                <p class="text-[13px] text-[#1A2E2B]/70">{{ t('no_emergency_fund') }}</p>
                <button type="button"
                        @click="addGoal(t('goal_emergency'))"
                        class="mt-2 text-[12px] text-tema-ocre font-semibold underline">
                    {{ t('add_emergency_fund') }}
                </button>
            </div>

            <!-- État vide -->
            <div v-if="form.financial_goals.length === 0"
                 class="bg-[#1A2E2B]/3 rounded-2xl border border-dashed border-[#1A2E2B]/10 px-4 py-6 text-center mb-4">
                <p class="text-[13px] text-[#1A2E2B]/35">{{ t('no_goal') }}</p>
            </div>

            <!-- Liste -->
            <div class="space-y-3 mb-4">
                <div v-for="(goal, index) in form.financial_goals" :key="index"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5 space-y-3">

                    <div class="flex gap-3 items-center">
                        <input type="text"
                               v-model="goal.label"
                               :placeholder="t('goal_placeholder')"
                               class="flex-1 text-[14px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <button type="button" @click="removeGoal(index)"
                                class="w-9 h-9 rounded-full bg-tema-brick/10 text-tema-brick text-xs flex items-center justify-center hover:bg-tema-brick/20 transition-all flex-shrink-0">
                            ✕
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <label class="block text-[11px] text-[#1A2E2B]/40 mb-1.5 font-semibold uppercase tracking-wide">
                                {{ t('target_amount') }}
                            </label>
                            <input type="number"
                                   v-model.number="goal.target_amount"
                                   placeholder="500 000" min="0"
                                   class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-14 py-3">
                            <span class="absolute right-3 bottom-3 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                        <div>
                            <label class="block text-[11px] text-[#1A2E2B]/40 mb-1.5 font-semibold uppercase tracking-wide">
                                {{ t('date_optional') }}
                            </label>
                            <input type="date"
                                   v-model="goal.target_date"
                                   class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                            <p v-if="goal.target_date"
                               class="text-[11px] text-tema-green mt-1">
                                {{ t('deadline_alert') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" @click="addGoal()"
                    class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all mb-10">
                {{ t('add_goal_btn') }}
            </button>

            <div class="flex gap-3">
                <button type="button"
                        @click="router.get(route('onboarding.debts'))"
                        class="w-14 h-14 rounded-2xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 flex items-center justify-center hover:border-[#1A2E2B]/25 transition-all flex-shrink-0">
                    {{ t('back') }}
                </button>
                <button type="button" @click="submit"
                        :disabled="isSubmitting"
                        class="flex-1 bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light disabled:opacity-40 shadow-sm">
                    {{ isSubmitting ? t('saving') : t('see_summary') }}
                </button>
            </div>

        </div>
    </div>
</template>
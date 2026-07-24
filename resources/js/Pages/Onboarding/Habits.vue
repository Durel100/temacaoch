<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const form = ref({
    spending_tendency:         '',
    budget_struggle_frequency: '',
    budget_preference:         '',
});

const errors       = ref({});
const isSubmitting = ref(false);

const canSubmit = computed(() =>
    form.value.spending_tendency &&
    form.value.budget_struggle_frequency &&
    form.value.budget_preference
);

const impactMessage = computed(() => {
    if (!canSubmit.value) return null;
    const threshold = form.value.spending_tendency === 'spends_quickly' ? '70%'
        : form.value.spending_tendency === 'saves' ? '90%' : '80%';
    const pref = form.value.budget_preference === 'strict'
        ? '+20%' : '+40%';
    return `${threshold} · ${pref}`;
});

function submit() {
    if (!canSubmit.value || isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(route('onboarding.habits.store'), form.value, {
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
                         class="flex-1 h-[3px] rounded-full transition-all duration-300"
                         :class="step <= 5 ? 'bg-tema-green' : 'bg-[#1A2E2B]/10'"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 5 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_habits') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">{{ t('onboarding_subtitle_habits') }}</p>
            </div>

            <div class="space-y-5">

                <!-- Tendance -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[15px] font-semibold text-[#1A2E2B] mb-1">
                        {{ t('habit_tendency_title') }}
                    </p>
                    <p class="text-[12px] text-[#1A2E2B]/50 mb-4">{{ t('habit_tendency_desc') }}</p>
                    <div class="space-y-2">
                        <button v-for="opt in [
                            { value: 'spends_quickly', label: t('tendency_quick'),   emoji: '💸', hint: t('tendency_quick_hint') },
                            { value: 'depends',        label: t('tendency_depends'), emoji: '🤷', hint: t('tendency_depends_hint') },
                            { value: 'saves',          label: t('tendency_saves'),   emoji: '🐖', hint: t('tendency_saves_hint') },
                        ]"
                                :key="opt.value"
                                type="button"
                                @click="form.spending_tendency = opt.value"
                                class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl border-[1.5px] text-left transition-all"
                                :class="form.spending_tendency === opt.value
                                    ? 'border-tema-green bg-tema-green/8'
                                    : 'border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20'">
                            <span class="text-xl flex-shrink-0">{{ opt.emoji }}</span>
                            <div class="flex-1">
                                <span class="text-[14px] font-medium block"
                                      :class="form.spending_tendency === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/80'">
                                    {{ opt.label }}
                                </span>
                                <span v-if="form.spending_tendency === opt.value"
                                      class="text-[11px] text-tema-green/70">{{ opt.hint }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Difficulté -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[15px] font-semibold text-[#1A2E2B] mb-1">
                        {{ t('habit_struggle_title') }}
                    </p>
                    <p class="text-[12px] text-[#1A2E2B]/50 mb-4">{{ t('habit_struggle_desc') }}</p>
                    <div class="space-y-2">
                        <button v-for="opt in [
                            { value: 'often',     label: t('struggle_often'),     emoji: '😰', hint: t('struggle_often_hint') },
                            { value: 'sometimes', label: t('struggle_sometimes'), emoji: '😐', hint: t('struggle_sometimes_hint') },
                            { value: 'rarely',    label: t('struggle_rarely'),    emoji: '😌', hint: t('struggle_rarely_hint') },
                        ]"
                                :key="opt.value"
                                type="button"
                                @click="form.budget_struggle_frequency = opt.value"
                                class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl border-[1.5px] text-left transition-all"
                                :class="form.budget_struggle_frequency === opt.value
                                    ? 'border-tema-green bg-tema-green/8'
                                    : 'border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20'">
                            <span class="text-xl flex-shrink-0">{{ opt.emoji }}</span>
                            <div class="flex-1">
                                <span class="text-[14px] font-medium block"
                                      :class="form.budget_struggle_frequency === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/80'">
                                    {{ opt.label }}
                                </span>
                                <span v-if="form.budget_struggle_frequency === opt.value"
                                      class="text-[11px] text-tema-green/70">{{ opt.hint }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Préférence -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                    <p class="text-[15px] font-semibold text-[#1A2E2B] mb-1">
                        {{ t('habit_preference_title') }}
                    </p>
                    <p class="text-[12px] text-[#1A2E2B]/50 mb-4">{{ t('habit_preference_desc') }}</p>
                    <div class="space-y-2">
                        <button v-for="opt in [
                            { value: 'strict',   label: t('preference_strict'),   emoji: '📋', hint: t('preference_strict_hint') },
                            { value: 'flexible', label: t('preference_flexible'), emoji: '🌊', hint: t('preference_flexible_hint') },
                        ]"
                                :key="opt.value"
                                type="button"
                                @click="form.budget_preference = opt.value"
                                class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl border-[1.5px] text-left transition-all"
                                :class="form.budget_preference === opt.value
                                    ? 'border-tema-green bg-tema-green/8'
                                    : 'border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20'">
                            <span class="text-xl flex-shrink-0">{{ opt.emoji }}</span>
                            <div class="flex-1">
                                <span class="text-[14px] font-medium block"
                                      :class="form.budget_preference === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/80'">
                                    {{ opt.label }}
                                </span>
                                <span v-if="form.budget_preference === opt.value"
                                      class="text-[11px] text-tema-green/70">{{ opt.hint }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Résumé profil -->
                <div v-if="impactMessage"
                     class="bg-tema-green/10 border border-tema-green/20 rounded-2xl p-4">
                    <p class="text-[12px] font-semibold text-tema-green mb-1">
                        {{ t('coaching_profile') }}
                    </p>
                    <p class="text-[13px] text-[#1A2E2B]/70">{{ impactMessage }}</p>
                </div>

            </div>

            <div class="flex gap-3 mt-10">
                <button type="button"
                        @click="router.get(route('onboarding.current-situation'))"
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
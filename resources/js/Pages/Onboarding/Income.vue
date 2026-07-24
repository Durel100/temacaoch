<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t } = useTranslation();

const incomeTypes = computed(() => [
    { value: 'salary',             label: t('income_type_salary'),      emoji: '💼', desc: t('income_type_salary_desc') },
    { value: 'irregular_business', label: t('income_type_business'),    emoji: '🛍️', desc: t('income_type_business_desc') },
    { value: 'family_allowance',   label: t('income_type_family'),      emoji: '👨‍👩‍👧', desc: t('income_type_family_desc') },
    { value: 'scholarship',        label: t('income_type_scholarship'),  emoji: '🎓', desc: t('income_type_scholarship_desc') },
    { value: 'other',              label: t('income_type_other'),        emoji: '💰', desc: t('income_type_other_desc') },
]);

const frequencies = computed(() => [
    { value: 'monthly',   label: t('freq_monthly') },
    { value: 'weekly',    label: t('freq_weekly') },
    { value: 'biweekly',  label: t('freq_biweekly') },
    { value: 'irregular', label: t('freq_irregular') },
]);

const form = ref({
    income_sources: [{ type: '', frequency: 'monthly', amount: null, label: '' }],
});

const errors       = ref({});
const isSubmitting = ref(false);

function addSource() {
    form.value.income_sources.push({ type: '', frequency: 'monthly', amount: null, label: '' });
}

function removeSource(index) {
    if (form.value.income_sources.length > 1) {
        form.value.income_sources.splice(index, 1);
    }
}

const canSubmit = computed(() =>
    form.value.income_sources.every(s => s.type && s.amount > 0)
);

function submit() {
    if (!canSubmit.value || isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(route('onboarding.income.store'), form.value, {
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
                         :class="step <= 2 ? 'bg-tema-green' : 'bg-[#1A2E2B]/10'"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 2 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_income') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">{{ t('onboarding_subtitle_income') }}</p>
            </div>

            <div class="space-y-4">

                <div v-for="(source, index) in form.income_sources" :key="index"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">

                    <div class="flex justify-between items-center px-4 pt-4 pb-3 border-b border-[#1A2E2B]/6">
                        <p class="text-[13px] font-semibold text-[#1A2E2B]">
                            {{ t('source') }} {{ index + 1 }}
                        </p>
                        <button v-if="form.income_sources.length > 1"
                                type="button" @click="removeSource(index)"
                                class="w-7 h-7 rounded-full bg-tema-brick/10 text-tema-brick text-xs flex items-center justify-center hover:bg-tema-brick/20 transition-all">
                            ✕
                        </button>
                    </div>

                    <div class="p-4 space-y-4">

                        <!-- Type -->
                        <div>
                            <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                                {{ t('income_type') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="tp in incomeTypes" :key="tp.value"
                                        type="button"
                                        @click="source.type = tp.value"
                                        class="flex items-center gap-2 px-3 py-2 rounded-xl border-[1.5px] text-[13px] font-medium transition-all"
                                        :class="source.type === tp.value
                                            ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                            : 'border-[#1A2E2B]/10 text-[#1A2E2B]/70 hover:border-[#1A2E2B]/20'">
                                    <span>{{ tp.emoji }}</span>
                                    {{ tp.label }}
                                </button>
                            </div>
                            <p v-if="source.type"
                               class="text-[12px] text-[#1A2E2B]/50 mt-2">
                                {{ incomeTypes.find(t => t.value === source.type)?.desc }}
                            </p>
                        </div>

                        <!-- Montant -->
                        <div>
                            <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                                {{ t('amount') }}
                            </p>
                            <div class="relative">
                                <input type="number"
                                       v-model.number="source.amount"
                                       placeholder="Ex : 150 000"
                                       min="0"
                                       class="w-full text-[17px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3.5">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] text-[#1A2E2B]/40 font-medium">
                                    FCFA
                                </span>
                            </div>
                        </div>

                        <!-- Fréquence -->
                        <div>
                            <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                                {{ t('frequency') }}
                            </p>
                            <div class="flex gap-2 flex-wrap">
                                <button v-for="f in frequencies" :key="f.value"
                                        type="button"
                                        @click="source.frequency = f.value"
                                        class="px-3 py-2 rounded-full border-[1.5px] text-[12px] font-medium transition-all"
                                        :class="source.frequency === f.value
                                            ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                            : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                                    {{ f.label }}
                                </button>
                            </div>
                            <p v-if="source.frequency === 'irregular'"
                               class="text-[12px] text-tema-terracotta bg-tema-terracotta/10 rounded-xl px-3 py-2 mt-2">
                                {{ t('freq_irregular_hint') }}
                            </p>
                        </div>

                        <!-- Label optionnel -->
                        <input type="text"
                               v-model="source.label"
                               :placeholder="t('label_optional')"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    </div>
                </div>

                <button type="button" @click="addSource"
                        class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all">
                    {{ t('add_source') }}
                </button>
            </div>

            <div class="flex gap-3 mt-10">
                <button type="button"
                        @click="router.get(route('onboarding.personal-info'))"
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
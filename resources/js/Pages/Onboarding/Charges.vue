<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';


const { t } = useTranslation();

const commonCharges = computed(() => [
    { label: t('charge_rent'),        emoji: '🏠' },
    { label: t('charge_utilities'),   emoji: '💡' },
    { label: t('charge_transport'),   emoji: '🚌' },
    { label: t('charge_school'),      emoji: '📚' },
    { label: t('charge_phone'),       emoji: '📱' },
    { label: t('charge_internet'),    emoji: '🌐' },
]);

const form        = ref({ fixed_charges: [] });
const errors      = ref({});
const isSubmitting = ref(false);

function addCharge(label = '') {
    form.value.fixed_charges.push({ label, amount: null, frequency: 'monthly' });
}

function removeCharge(index) {
    form.value.fixed_charges.splice(index, 1);
}

function isAlreadyAdded(label) {
    return form.value.fixed_charges.some(c => c.label === label);
}

function submit() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(route('onboarding.charges.store'), form.value, {
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
                         :class="step <= 3 ? 'bg-tema-green' : 'bg-[#1A2E2B]/10'"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 3 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_charges') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55">{{ t('onboarding_subtitle_charges') }}</p>
            </div>

            <!-- Suggestions -->
            <div class="mb-6">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                    {{ t('frequent_charges') }}
                </p>
                <div class="flex flex-row flex-wrap gap-2">
                    <button v-for="c in commonCharges" :key="c.label"
                            type="button"
                            @click="!isAlreadyAdded(c.label) && addCharge(c.label)"
                            :disabled="isAlreadyAdded(c.label)"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-full border-[1.5px] text-[13px] font-medium transition-all whitespace-nowrap"
                            :class="isAlreadyAdded(c.label)
                                ? 'border-tema-green/30 bg-tema-green/8 text-tema-green cursor-default'
                                : 'border-[#1A2E2B]/10 bg-white text-[#1A2E2B]/70 hover:border-tema-green/40 hover:text-tema-green'">
                        <span>{{ c.emoji }}</span>{{ c.label }}
                    </button>
                </div>
            </div>

            <!-- État vide -->
            <div v-if="form.fixed_charges.length === 0"
                 class="bg-[#1A2E2B]/3 rounded-2xl border border-dashed border-[#1A2E2B]/10 px-4 py-6 text-center mb-4">
                <p class="text-[13px] text-[#1A2E2B]/35">{{ t('no_charges') }}</p>
            </div>

            <!-- Liste -->
            <div class="space-y-3 mb-4">
                <div v-for="(charge, index) in form.fixed_charges" :key="index"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 space-y-3 min-w-0">
                            <input type="text"
                                   v-model="charge.label"
                                   :placeholder="t('charge_name')"
                                   class="w-full text-[14px] font-medium rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                            <div class="flex items-center gap-3">
                                <div class="relative flex-1">
                                    <input type="number"
                                           v-model.number="charge.amount"
                                           :placeholder="t('amount')"
                                           min="0"
                                           class="w-full text-[15px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3 pr-16">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] text-[#1A2E2B]/40 font-medium">
                                        FCFA
                                    </span>
                                </div>
                                <span class="text-[12px] font-semibold text-[#1A2E2B]/40 bg-[#1A2E2B]/6 px-3 py-2 rounded-xl flex-shrink-0">
                                    {{ t('per_month_badge') }}
                                </span>
                            </div>
                        </div>
                        <button type="button" @click="removeCharge(index)"
                                class="w-8 h-8 rounded-full bg-tema-brick/10 text-tema-brick text-sm flex items-center justify-center hover:bg-tema-brick/20 transition-all mt-1 flex-shrink-0">
                            ✕
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="addCharge()"
                    class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all mb-10">
                {{ t('add_charge_manual') }}
            </button>

            <div class="flex gap-3">
                <button type="button"
                        @click="router.get(route('onboarding.income'))"
                        class="w-14 h-14 rounded-2xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 flex items-center justify-center hover:border-[#1A2E2B]/25 transition-all flex-shrink-0">
                    {{ t('back') }}
                </button>
                <button type="button" @click="submit"
                        :disabled="isSubmitting"
                        class="flex-1 bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light disabled:opacity-40 shadow-sm">
                    {{ isSubmitting ? t('saving') : t('continue') }}
                </button>
            </div>

        </div>
    </div>
</template>
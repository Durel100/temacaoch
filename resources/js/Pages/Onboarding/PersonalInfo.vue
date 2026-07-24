<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import axios from 'axios';

const { t, locale } = useTranslation();

const switchingLocale = ref(false);

function switchLocale(loc) {
    if (loc === locale.value || switchingLocale.value) return;
    switchingLocale.value = true;

    axios.patch('/locale', { locale: loc })
        .then(() => {
            window.location.reload();
        })
        .catch((error) => {
            console.error('Erreur:', error.response?.status);
            switchingLocale.value = false;
        });
}

// ─── Formulaire ──────────────────────────────────────────────────────
const maritalStatuses = computed(() => [
    { value: 'single',          label: t('marital_single') },
    { value: 'married',         label: t('marital_married') },
    { value: 'in_relationship', label: t('marital_couple') },
    { value: 'divorced',        label: t('marital_divorced') },
    { value: 'widowed',         label: t('marital_widowed') },
]);

const ageRanges = computed(() => [
    { value: '0-5',   label: t('age_0_5') },
    { value: '6-12',  label: t('age_6_12') },
    { value: '13-18', label: t('age_13_18') },
    { value: 'adult', label: t('adult') },
]);
const form = ref({
    marital_status:              '',
    employment_type:             '',
    spouse_contributes:          null,
    spouse_monthly_contribution: null,
    shared_fixed_charges:        null,
    dependents:                  [],
});

const errors       = ref({});
const isSubmitting = ref(false);

const hasSpouse = computed(() =>
    ['married', 'in_relationship'].includes(form.value.marital_status)
);

function onMaritalChange(value) {
    form.value.marital_status = value;
    if (!['married', 'in_relationship'].includes(value)) {
        form.value.spouse_contributes          = null;
        form.value.spouse_monthly_contribution = null;
        form.value.shared_fixed_charges        = null;
    }
}

function addDependent() {
    form.value.dependents.push({
        relation:             'child',
        age_range:            '6-12',
        is_schooled:          false,
        has_allowance:        false,
        allowance_amount:     null,
        allowance_frequency:  'weekly',
        allowance_managed_by: 'child',
    });
}

function removeDependent(index) {
    form.value.dependents.splice(index, 1);
}

function onRelationChange(dependent, value) {
    dependent.relation = value;
    if (value === 'parent') {
        dependent.is_schooled      = false;
        dependent.has_allowance    = false;
        dependent.allowance_amount = null;
    }
    if (value !== 'child') {
        dependent.is_schooled = false;
    }
}

const canSubmit = computed(() => {
    if (!form.value.marital_status || !form.value.employment_type) return false;
    if (hasSpouse.value && form.value.spouse_contributes === null) return false;
    if (form.value.spouse_contributes === true) {
        if (!form.value.spouse_monthly_contribution) return false;
        if (form.value.shared_fixed_charges === null) return false;
    }
    return true;
});

function submit() {
    if (!canSubmit.value || isSubmitting.value) return;
    isSubmitting.value = true;

    router.post(route('onboarding.personal-info.store'), {
        marital_status:              form.value.marital_status,
        employment_type:             form.value.employment_type,
        spouse_contributes:          form.value.spouse_contributes,
        spouse_monthly_contribution: form.value.spouse_monthly_contribution,
        shared_fixed_charges:        form.value.shared_fixed_charges,
        dependents: form.value.dependents.map((d) => ({
            relation:             d.relation,
            age_range:            d.age_range,
            is_schooled:          d.relation === 'child' ? d.is_schooled : false,
            allowance_amount:     (d.relation !== 'parent' && d.has_allowance) ? d.allowance_amount : null,
            allowance_frequency:  (d.relation !== 'parent' && d.has_allowance) ? d.allowance_frequency : null,
            allowance_managed_by: (d.relation !== 'parent' && d.has_allowance) ? d.allowance_managed_by : null,
        })),
    }, {
        onError:  (e) => { errors.value = e; },
        onFinish: () => { isSubmitting.value = false; },
    });
}
</script>
<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Barre de progression -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto flex items-center gap-3 px-4 py-3">
                <div class="flex gap-1 flex-1">
                    <div v-for="step in 7" :key="step"
                         class="flex-1 h-[3px] rounded-full transition-all duration-300"
                         :class="step <= 1 ? 'bg-tema-green' : 'bg-[#1A2E2B]/10'"/>
                </div>
                <span class="text-[11px] text-[#1A2E2B]/40 font-medium whitespace-nowrap">
                    {{ t('onboarding_step') }} 1 {{ t('of') }} 7
                </span>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8">

            <!-- Sélecteur de langue -->
            <div class="flex gap-1.5 bg-[#1A2E2B]/5 rounded-full p-1">
                <button v-for="lang in [
                        { value: 'fr', label: '🇫🇷 FR' },
                        { value: 'en', label: '🇬🇧 EN' },
                    ]"
                        :key="lang.value"
                        type="button"
                        @click="switchLocale(lang.value)"
                        :disabled="switchingLocale"
                        class="px-3 py-1.5 rounded-full text-[12px] font-semibold transition-all disabled:opacity-50"
                        :class="locale === lang.value
                            ? 'bg-white text-tema-green shadow-sm'
                            : 'text-[#1A2E2B]/40 hover:text-[#1A2E2B]/70'">
                    {{ switchingLocale && locale !== lang.value ? '...' : lang.label }}
                </button>
            </div>

            <div class="mb-8">
                <h1 class="font-display text-[28px] font-semibold text-[#1A2E2B] leading-tight mb-2">
                    {{ t('onboarding_title_personal') }}
                </h1>
                <p class="text-[14px] text-[#1A2E2B]/55 leading-relaxed">
                    {{ t('onboarding_subtitle_personal') }}
                </p>
            </div>

            <div class="space-y-7">

                <!-- Situation matrimoniale -->
                <div>
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                        {{ t('situation') }}
                    </p>
                    <div class="flex flex-row flex-wrap gap-2">
                        <button v-for="s in maritalStatuses" :key="s.value"
                                type="button"
                                @click="onMaritalChange(s.value)"
                                class="px-4 py-2.5 rounded-full border-[1.5px] text-[13px] font-medium transition-all whitespace-nowrap"
                                :class="form.marital_status === s.value
                                    ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                    : 'border-[#1A2E2B]/12 bg-white text-[#1A2E2B]/70 hover:border-tema-green/40 hover:text-tema-green'">
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <!-- Situation professionnelle -->
                <div>
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                        {{ t('pro') }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button v-for="emp in [
                                { value: 'salaried',     label: t('salaried'),     desc: t('salaried_desc'),     emoji: '💼' },
                                { value: 'non_salaried', label: t('non_salaried'), desc: t('non_salaried_desc'), emoji: '🛍️' },
                            ]"
                                :key="emp.value"
                                type="button"
                                @click="form.employment_type = emp.value"
                                class="flex items-center gap-4 px-4 py-4 rounded-2xl border-[1.5px] transition-all text-left"
                                :class="form.employment_type === emp.value
                                    ? 'border-tema-green bg-tema-green/5'
                                    : 'border-[#1A2E2B]/10 bg-white hover:border-tema-green/30'">
                            <div class="w-11 h-11 rounded-xl bg-[#FAF6F0] flex items-center justify-center text-2xl flex-shrink-0">
                                {{ emp.emoji }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[15px] font-semibold leading-tight mb-0.5"
                                   :class="form.employment_type === emp.value ? 'text-tema-green' : 'text-[#1A2E2B]'">
                                    {{ emp.label }}
                                </p>
                                <p class="text-[12px] text-[#1A2E2B]/50">{{ emp.desc }}</p>
                            </div>
                            <div class="w-[22px] h-[22px] rounded-full border-[1.5px] flex items-center justify-center flex-shrink-0"
                                 :class="form.employment_type === emp.value
                                     ? 'bg-tema-green border-tema-green'
                                     : 'border-[#1A2E2B]/15'">
                                <span v-if="form.employment_type === emp.value"
                                      class="text-white text-[11px] font-bold">✓</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Conjoint -->
                <div v-if="hasSpouse">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                        {{ t('partner') }}
                    </p>

                    <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 mb-3">
                        <p class="text-[14px] font-semibold text-[#1A2E2B] mb-4">
                            {{ t('partner_contributes') }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <button v-for="opt in [
                                    { value: true,  label: t('yes'), emoji: '✅' },
                                    { value: false, label: t('no'),  emoji: '❌' },
                                ]"
                                    :key="String(opt.value)"
                                    type="button"
                                    @click="form.spouse_contributes = opt.value; if (!opt.value) { form.spouse_monthly_contribution = null; form.shared_fixed_charges = null; }"
                                    class="flex flex-col items-center py-4 rounded-2xl border-[1.5px] transition-all"
                                    :class="form.spouse_contributes === opt.value
                                        ? 'border-tema-green bg-tema-green/8'
                                        : 'border-[#1A2E2B]/10 hover:border-tema-green/30'">
                                <span class="text-2xl mb-1">{{ opt.emoji }}</span>
                                <span class="text-[14px] font-semibold"
                                      :class="form.spouse_contributes === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/70'">
                                    {{ opt.label }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="form.spouse_contributes === true" class="space-y-3">

                        <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                            <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                                {{ t('contribution') }}
                            </p>
                            <p class="text-[12px] text-[#1A2E2B]/50 mb-3">{{ t('contribution_desc') }}</p>
                            <div class="relative">
                                <input type="number"
                                       v-model.number="form.spouse_monthly_contribution"
                                       placeholder="Ex : 80 000" min="0"
                                       class="w-full text-[20px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3.5">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] text-[#1A2E2B]/40 font-medium">
                                    FCFA
                                </span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                            <p class="text-[14px] font-semibold text-[#1A2E2B] mb-1">
                                {{ t('shared_charges') }}
                            </p>
                            <p class="text-[12px] text-[#1A2E2B]/50 mb-3">{{ t('shared_charges_desc') }}</p>
                            <div class="grid grid-cols-2 gap-3">
                                <button v-for="opt in [
                                        { value: true,  label: t('shared_yes'), emoji: '🤝' },
                                        { value: false, label: t('shared_no'),  emoji: '👤' },
                                    ]"
                                        :key="String(opt.value)"
                                        type="button"
                                        @click="form.shared_fixed_charges = opt.value"
                                        class="flex flex-col items-center py-4 rounded-2xl border-[1.5px] transition-all"
                                        :class="form.shared_fixed_charges === opt.value
                                            ? 'border-tema-green bg-tema-green/8'
                                            : 'border-[#1A2E2B]/10 hover:border-tema-green/30'">
                                    <span class="text-2xl mb-1">{{ opt.emoji }}</span>
                                    <span class="text-[12px] font-semibold text-center"
                                          :class="form.shared_fixed_charges === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/70'">
                                        {{ opt.label }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div v-if="form.spouse_monthly_contribution > 0 && form.shared_fixed_charges !== null"
                             class="bg-tema-green/10 border border-tema-green/20 rounded-2xl p-4">
                            <p class="text-[12px] font-semibold text-tema-green mb-1">{{ t('impact') }}</p>
                            <p class="text-[13px] text-[#1A2E2B]/70">
                                +{{ new Intl.NumberFormat('fr-FR').format(form.spouse_monthly_contribution) }} FCFA
                                {{ t('impact_added') }}
                                <span v-if="form.shared_fixed_charges"> {{ t('impact_divided') }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Personnes à charge -->
                <div>
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                        {{ t('dependents') }}
                    </p>

                    <div v-if="form.dependents.length === 0"
                         class="bg-[#1A2E2B]/3 rounded-2xl border border-dashed border-[#1A2E2B]/10 px-4 py-5 text-center mb-3">
                        <p class="text-[13px] text-[#1A2E2B]/35">{{ t('no_dependents') }}</p>
                    </div>

                    <div v-for="(dependent, index) in form.dependents" :key="index"
                         class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 mb-3">

                        <div class="flex justify-between items-center mb-4">
                            <p class="text-[13px] font-semibold text-[#1A2E2B]">
                                {{ t('person') }} {{ index + 1 }}
                            </p>
                            <button type="button" @click="removeDependent(index)"
                                    class="w-7 h-7 rounded-full bg-tema-brick/10 text-tema-brick text-xs flex items-center justify-center hover:bg-tema-brick/20 transition-all">
                                ✕
                            </button>
                        </div>

                        <div class="flex gap-2 mb-3">
                            <button v-for="rel in [
                                    { v: 'child',  l: t('child'),  e: '👶' },
                                    { v: 'parent', l: t('parent'), e: '👴' },
                                    { v: 'other',  l: t('other'),  e: '👤' },
                                ]"
                                    :key="rel.v"
                                    type="button"
                                    @click="onRelationChange(dependent, rel.v)"
                                    class="flex-1 flex flex-col items-center py-3 rounded-xl border-[1.5px] transition-all"
                                    :class="dependent.relation === rel.v
                                        ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                        : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                                <span class="text-xl mb-1">{{ rel.e }}</span>
                                <span class="text-[11px] font-semibold">{{ rel.l }}</span>
                            </button>
                        </div>

                        <select v-model="dependent.age_range"
                                class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green mb-3 py-3">
                            <option v-for="r in ageRanges" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>

                        <div v-if="dependent.relation === 'child'"
                             class="flex items-center justify-between py-3 border-t border-[#1A2E2B]/6">
                            <div>
                                <span class="text-[13px] text-[#1A2E2B]/80">{{ t('schooled') }}</span>
                                <p class="text-[11px] text-[#1A2E2B]/40 mt-0.5">{{ t('schooled_desc') }}</p>
                            </div>
                            <div @click="dependent.is_schooled = !dependent.is_schooled"
                                 class="w-12 h-6 rounded-full transition-colors cursor-pointer relative flex-shrink-0"
                                 :class="dependent.is_schooled ? 'bg-tema-green' : 'bg-[#1A2E2B]/15'">
                                <div class="w-5 h-5 bg-white rounded-full shadow-sm absolute top-0.5 transition-transform"
                                     :class="dependent.is_schooled ? 'translate-x-6' : 'translate-x-0.5'"/>
                            </div>
                        </div>

                        <div v-if="dependent.relation !== 'parent'"
                             class="flex items-center justify-between py-3 border-t border-[#1A2E2B]/6">
                            <div>
                                <span class="text-[13px] text-[#1A2E2B]/80">
                                    {{ dependent.relation === 'child' ? t('allowance') : t('allowance_other') }}
                                </span>
                                <p class="text-[11px] text-[#1A2E2B]/40 mt-0.5">{{ t('allowance_desc') }}</p>
                            </div>
                            <div @click="dependent.has_allowance = !dependent.has_allowance"
                                 class="w-12 h-6 rounded-full transition-colors cursor-pointer relative flex-shrink-0"
                                 :class="dependent.has_allowance ? 'bg-tema-green' : 'bg-[#1A2E2B]/15'">
                                <div class="w-5 h-5 bg-white rounded-full shadow-sm absolute top-0.5 transition-transform"
                                     :class="dependent.has_allowance ? 'translate-x-6' : 'translate-x-0.5'"/>
                            </div>
                        </div>

                        <div v-if="dependent.relation !== 'parent' && dependent.has_allowance"
                            class="grid grid-cols-2 gap-2 mt-2">
                            <div class="relative">
                                <input type="number"
                                    v-model.number="dependent.allowance_amount"
                                    :placeholder="t('amount')"
                                    class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3 pr-14">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                            <select v-model="dependent.allowance_frequency"
                                    class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                                <option value="daily">{{ t('per_day') }}</option>
                                <option value="weekly">{{ t('per_week') }}</option>
                                <option value="monthly">{{ t('per_month') }}</option>
                            </select>
                        </div>

                        <div v-if="dependent.relation === 'parent'"
                             class="mt-2 pt-3 border-t border-[#1A2E2B]/6">
                            <p class="text-[12px] text-[#1A2E2B]/45 italic">{{ t('parent_note') }}</p>
                        </div>
                    </div>

                    <button type="button" @click="addDependent"
                            class="w-full py-4 rounded-2xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[13px] font-semibold hover:bg-tema-green/3 transition-all">
                        {{ t('add_person') }}
                    </button>
                </div>

            </div>

            <div class="mt-10">
                <button type="button" @click="submit"
                        :disabled="!canSubmit || isSubmitting"
                        class="w-full bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] transition-all hover:bg-tema-green-light disabled:opacity-40 disabled:cursor-not-allowed shadow-sm">
                    {{ isSubmitting ? t('saving') : t('continue') }}
                </button>
            </div>

        </div>
    </div>
</template>
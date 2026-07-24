<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import axios from 'axios';

const { t, locale } = useTranslation();

const props = defineProps({
    user:               Object,
    profile:            Object,
    dependents:         Array,
    incomeSources:      Array,
    summary:            Object,
    activeDebtsCount:   Number,
    activeChargesCount: Number,
});

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

const openSection = ref(null);
function toggleSection(section) {
    openSection.value = openSection.value === section ? null : section;
}

// ─── Infos de base ──────────────────────────────────────────────────
const infoForm = useForm({
    name:  props.user.name  ?? '',
    email: props.user.email ?? '',
});

function submitInfo() {
    infoForm.patch(route('profile.info.update'), {
        onSuccess: () => openSection.value = null,
    });
}

// ─── Mot de passe ───────────────────────────────────────────────────
const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

function submitPassword() {
    passwordForm.patch(route('profile.password.update'), {
        onSuccess: () => { passwordForm.reset(); openSection.value = null; },
    });
}

// ─── Situation personnelle ───────────────────────────────────────────
const personalForm = useForm({
    marital_status:              props.profile?.marital_status              ?? 'single',
    employment_type:             props.profile?.employment_type             ?? 'non_salaried',
    spouse_contributes:          props.profile?.spouse_contributes          ?? null,
    spouse_monthly_contribution: props.profile?.spouse_monthly_contribution ?? null,
    shared_fixed_charges:        props.profile?.shared_fixed_charges        ?? null,
    salary_day:                  props.profile?.salary_day                  ?? null,
});

const hasSpouse = computed(() =>
    ['married', 'in_relationship'].includes(personalForm.marital_status)
);

function onMaritalChange(value) {
    personalForm.marital_status = value;
    if (!['married', 'in_relationship'].includes(value)) {
        personalForm.spouse_contributes          = null;
        personalForm.spouse_monthly_contribution = null;
        personalForm.shared_fixed_charges        = null;
    }
}

function submitPersonal() {
    personalForm.patch(route('profile.personal.update'), {
        onSuccess: () => openSection.value = null,
    });
}

// ─── Habitudes ──────────────────────────────────────────────────────
const habitsForm = useForm({
    spending_tendency:         props.profile?.spending_tendency         ?? '',
    budget_struggle_frequency: props.profile?.budget_struggle_frequency ?? '',
    budget_preference:         props.profile?.budget_preference         ?? '',
});

function submitHabits() {
    habitsForm.patch(route('profile.habits.update'), {
        onSuccess: () => openSection.value = null,
    });
}

// ─── Revenus ────────────────────────────────────────────────────────
const incomeTypes = computed(() => [
    { value: 'salary',             label: t('income_type_salary'),    emoji: '💼' },
    { value: 'irregular_business', label: t('income_type_business'),  emoji: '🛍️' },
    { value: 'family_allowance',   label: t('income_type_family'),    emoji: '👨‍👩‍👧' },
    { value: 'scholarship',        label: t('income_type_scholarship'),emoji: '🎓' },
    { value: 'other',              label: t('income_type_other'),      emoji: '💰' },
]);

const frequencies = computed(() => [
    { value: 'monthly',   label: t('freq_monthly') },
    { value: 'weekly',    label: t('freq_weekly') },
    { value: 'biweekly',  label: t('freq_biweekly') },
    { value: 'irregular', label: t('freq_irregular') },
]);

const incomeForm = useForm({
    income_sources: props.incomeSources?.length
        ? props.incomeSources.map(s => ({
            type:      s.type,
            frequency: s.frequency,
            amount:    s.amount,
            label:     s.label ?? '',
          }))
        : [{ type: '', frequency: 'monthly', amount: null, label: '' }],
});

function addIncomeSource() {
    incomeForm.income_sources.push({ type: '', frequency: 'monthly', amount: null, label: '' });
}

function removeIncomeSource(index) {
    if (incomeForm.income_sources.length > 1) {
        incomeForm.income_sources.splice(index, 1);
    }
}

function submitIncome() {
    incomeForm.patch(route('profile.income.update'), {
        onSuccess: () => openSection.value = null,
    });
}

// ─── Personnes à charge ─────────────────────────────────────────────
const ageRanges = computed(() => [
    { value: '0-5',   label: t('age_0_5') },
    { value: '6-12',  label: t('age_6_12') },
    { value: '13-18', label: t('age_13_18') },
    { value: 'adult', label: t('adult') },
]);

const dependentsForm = useForm({
    dependents: props.dependents?.length
        ? props.dependents.map(d => ({
            relation:             d.relation,
            age_range:            d.age_range,
            is_schooled:          d.is_schooled ?? false,
            has_allowance:        !!d.allowance_amount,
            allowance_amount:     d.allowance_amount,
            allowance_frequency:  d.allowance_frequency ?? 'weekly',
            allowance_managed_by: d.allowance_managed_by ?? 'child',
          }))
        : [],
});

function addDependent() {
    dependentsForm.dependents.push({
        relation: 'child', age_range: '6-12',
        is_schooled: false, has_allowance: false,
        allowance_amount: null, allowance_frequency: 'weekly',
        allowance_managed_by: 'child',
    });
}

function removeDependent(index) {
    dependentsForm.dependents.splice(index, 1);
}

function onRelationChange(dep, value) {
    dep.relation = value;
    if (value === 'parent') {
        dep.is_schooled   = false;
        dep.has_allowance = false;
    }
    if (value !== 'child') dep.is_schooled = false;
}

function submitDependents() {
    dependentsForm.patch(route('profile.dependents.update'), {
        onSuccess: () => openSection.value = null,
    });
}

// ─── Langue ─────────────────────────────────────────────────────────
const localeForm      = useForm({ locale: props.user.locale ?? 'fr' });
const switchingLocale = ref(false);

function submitLocale() {
    if (localeForm.locale === props.user.locale || switchingLocale.value) return;
    switchingLocale.value = true;

    axios.patch('/locale', { locale: localeForm.locale })
        .then(() => { window.location.reload(); })
        .catch(() => { switchingLocale.value = false; });
}

// ─── Suppression compte ─────────────────────────────────────────────
const deleteForm        = useForm({ password: '' });
const showDeleteConfirm = ref(false);

function submitDelete() {
    deleteForm.delete(route('profile.destroy'));
}

// ─── Labels lisibles traduits ────────────────────────────────────────
const maritalLabels = computed(() => ({
    single:          t('marital_single'),
    married:         t('marital_married'),
    in_relationship: t('marital_couple'),
    divorced:        t('marital_divorced'),
    widowed:         t('marital_widowed'),
}));

const employmentLabels = computed(() => ({
    salaried:     t('salaried'),
    non_salaried: t('non_salaried'),
}));

const tendencyLabels = computed(() => ({
    spends_quickly: t('tendency_quick') + ' 💸',
    depends:        t('tendency_depends') + ' 🤷',
    saves:          t('tendency_saves') + ' 🐖',
}));

const preferenceLabels = computed(() => ({
    strict:   t('preference_strict') + ' 📋',
    flexible: t('preference_flexible') + ' 🌊',
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
                <h1 class="font-display text-[20px] font-semibold text-[#1A2E2B]">
                    {{ t('my_profile') }}
                </h1>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-6 space-y-3">

            <!-- Avatar + résumé -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-tema-green/10 flex items-center justify-center flex-shrink-0">
                        <span class="font-display text-[22px] font-semibold text-tema-green">
                            {{ user.name?.charAt(0)?.toUpperCase() }}
                        </span>
                    </div>
                    <div>
                        <p class="text-[16px] font-semibold text-[#1A2E2B]">{{ user.name }}</p>
                        <p class="text-[12px] text-[#1A2E2B]/50">{{ user.email }}</p>
                        <p class="text-[11px] text-[#1A2E2B]/40 mt-0.5">
                            {{ employmentLabels[profile?.employment_type] ?? '—' }} ·
                            {{ maritalLabels[profile?.marital_status] ?? '—' }}
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 pt-4 border-t border-[#1A2E2B]/6">
                    <div class="text-center">
                        <p class="text-[11px] text-[#1A2E2B]/40 mb-0.5">{{ t('reference_income') }}</p>
                        <p class="text-[13px] font-semibold text-[#1A2E2B]">
                            {{ formatFcfa(summary.safeIncome) }}
                        </p>
                    </div>
                    <div class="text-center border-x border-[#1A2E2B]/6">
                        <p class="text-[11px] text-[#1A2E2B]/40 mb-0.5">{{ t('fixed_charges_total') }}</p>
                        <p class="text-[13px] font-semibold text-tema-terracotta">
                            {{ formatFcfa(summary.totalCharges) }}
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-[11px] text-[#1A2E2B]/40 mb-0.5">{{ t('rest_to_live') }}</p>
                        <p class="text-[13px] font-semibold"
                           :class="summary.resteAVivre >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                            {{ formatFcfa(summary.resteAVivre) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Informations personnelles -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="toggleSection('info')"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">👤</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('personal_info') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">{{ t('personal_info_desc') }}</p>
                        </div>
                    </div>
                    <span :class="openSection === 'info' ? 'rotate-180' : ''"
                          class="transition-transform text-[#1A2E2B]/30 text-[12px]">▾</span>
                </button>

                <div v-if="openSection === 'info'" class="px-5 pb-5 border-t border-[#1A2E2B]/6 space-y-5">
                    <div class="pt-4 space-y-3">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest">
                            {{ t('name_email') }}
                        </p>
                        <input type="text" v-model="infoForm.name" :placeholder="t('full_name')"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <input type="email" v-model="infoForm.email" placeholder="Email"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <p v-if="infoForm.errors.name || infoForm.errors.email"
                           class="text-[12px] text-tema-brick">
                            {{ infoForm.errors.name || infoForm.errors.email }}
                        </p>
                        <button @click="submitInfo" :disabled="infoForm.processing"
                                class="w-full py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                            {{ infoForm.processing ? '...' : t('save_changes') }}
                        </button>
                    </div>

                    <div class="pt-4 border-t border-[#1A2E2B]/6 space-y-3">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest">
                            {{ t('change_password') }}
                        </p>
                        <input type="password" v-model="passwordForm.current_password"
                               :placeholder="t('current_password')"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <input type="password" v-model="passwordForm.password"
                               :placeholder="t('new_password')"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <input type="password" v-model="passwordForm.password_confirmation"
                               :placeholder="t('confirm_password')"
                               class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                        <p v-if="passwordForm.errors.current_password || passwordForm.errors.password"
                           class="text-[12px] text-tema-brick">
                            {{ passwordForm.errors.current_password || passwordForm.errors.password }}
                        </p>
                        <button @click="submitPassword"
                                :disabled="!passwordForm.current_password || !passwordForm.password || passwordForm.processing"
                                class="w-full py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                            {{ passwordForm.processing ? '...' : t('change_password') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Situation personnelle -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="toggleSection('personal')"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🏠</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('personal_situation') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">
                                {{ maritalLabels[profile?.marital_status] ?? '—' }} ·
                                {{ employmentLabels[profile?.employment_type] ?? '—' }}
                                <span v-if="profile?.salary_day"> · {{ t('payday_label') }} {{ profile.salary_day }}</span>
                            </p>
                        </div>
                    </div>
                    <span :class="openSection === 'personal' ? 'rotate-180' : ''"
                          class="transition-transform text-[#1A2E2B]/30 text-[12px]">▾</span>
                </button>

                <div v-if="openSection === 'personal'"
                     class="px-5 pb-5 border-t border-[#1A2E2B]/6 pt-4 space-y-5">

                    <!-- Statut marital -->
                    <div>
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('situation') }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="s in [
                                    { value: 'single',          label: t('marital_single') },
                                    { value: 'married',         label: t('marital_married') },
                                    { value: 'in_relationship', label: t('marital_couple') },
                                    { value: 'divorced',        label: t('marital_divorced') },
                                    { value: 'widowed',         label: t('marital_widowed') },
                                ]"
                                    :key="s.value" type="button"
                                    @click="onMaritalChange(s.value)"
                                    class="px-3 py-2 rounded-full border-[1.5px] text-[12px] font-medium transition-all"
                                    :class="personalForm.marital_status === s.value
                                        ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                        : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-tema-green/30'">
                                {{ s.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Type d'emploi -->
                    <div>
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('pro') }}
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="emp in [
                                    { value: 'salaried',     label: t('salaried'),     emoji: '💼' },
                                    { value: 'non_salaried', label: t('non_salaried'), emoji: '🛍️' },
                                ]"
                                    :key="emp.value" type="button"
                                    @click="personalForm.employment_type = emp.value"
                                    class="flex items-center gap-2 px-3 py-3 rounded-xl border-[1.5px] transition-all text-left"
                                    :class="personalForm.employment_type === emp.value
                                        ? 'border-tema-green bg-tema-green/5'
                                        : 'border-[#1A2E2B]/10 hover:border-tema-green/30'">
                                <span>{{ emp.emoji }}</span>
                                <span class="text-[13px] font-semibold"
                                      :class="personalForm.employment_type === emp.value ? 'text-tema-green' : 'text-[#1A2E2B]'">
                                    {{ emp.label }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Jour de paye -->
                    <div v-if="personalForm.employment_type === 'salaried'">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('salary_day_label') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <input type="number"
                                   v-model.number="personalForm.salary_day"
                                   placeholder="Ex : 25" min="1" max="31"
                                   class="w-24 text-[17px] font-semibold text-center rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                            <span class="text-[13px] text-[#1A2E2B]/50">{{ t('each_month') }}</span>
                        </div>
                    </div>

                    <!-- Conjoint -->
                    <div v-if="hasSpouse">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                            {{ t('partner') }}
                        </p>
                        <div class="bg-[#FAF6F0] rounded-xl p-4 space-y-3">
                            <p class="text-[13px] font-semibold text-[#1A2E2B]">
                                {{ t('partner_contributes') }}
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="opt in [
                                        { value: true,  label: t('yes'), emoji: '✅' },
                                        { value: false, label: t('no'),  emoji: '❌' },
                                    ]"
                                        :key="String(opt.value)" type="button"
                                        @click="personalForm.spouse_contributes = opt.value; if (!opt.value) { personalForm.spouse_monthly_contribution = null; personalForm.shared_fixed_charges = null; }"
                                        class="flex flex-col items-center py-3 rounded-xl border-[1.5px] transition-all"
                                        :class="personalForm.spouse_contributes === opt.value
                                            ? 'border-tema-green bg-tema-green/8'
                                            : 'border-[#1A2E2B]/10 bg-white hover:border-tema-green/30'">
                                    <span class="text-xl mb-1">{{ opt.emoji }}</span>
                                    <span class="text-[12px] font-semibold"
                                          :class="personalForm.spouse_contributes === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/70'">
                                        {{ opt.label }}
                                    </span>
                                </button>
                            </div>
                            <div v-if="personalForm.spouse_contributes === true" class="space-y-3">
                                <div class="relative">
                                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('contribution') }}</p>
                                    <input type="number"
                                           v-model.number="personalForm.spouse_monthly_contribution"
                                           placeholder="Ex : 80 000" min="0"
                                           class="w-full text-[15px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3">
                                    <span class="absolute right-3 top-1/2 translate-y-1 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                                </div>
                                <div>
                                    <p class="text-[11px] text-[#1A2E2B]/50 mb-2">{{ t('shared_charges') }}</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button v-for="opt in [
                                                { value: true,  label: t('shared_yes'), emoji: '🤝' },
                                                { value: false, label: t('shared_no'),  emoji: '👤' },
                                            ]"
                                                :key="String(opt.value)" type="button"
                                                @click="personalForm.shared_fixed_charges = opt.value"
                                                class="flex flex-col items-center py-3 rounded-xl border-[1.5px] transition-all"
                                                :class="personalForm.shared_fixed_charges === opt.value
                                                    ? 'border-tema-green bg-tema-green/8'
                                                    : 'border-[#1A2E2B]/10 bg-white hover:border-tema-green/30'">
                                            <span class="text-xl mb-1">{{ opt.emoji }}</span>
                                            <span class="text-[12px] font-semibold text-center leading-tight"
                                                  :class="personalForm.shared_fixed_charges === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/70'">
                                                {{ opt.label }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button @click="submitPersonal" :disabled="personalForm.processing"
                            class="w-full py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                        {{ personalForm.processing ? '...' : t('save_changes') }}
                    </button>
                </div>
            </div>

            <!-- Préférences de coaching -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="toggleSection('habits')"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🧠</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('coaching_prefs') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">
                                {{ tendencyLabels[profile?.spending_tendency] ?? '—' }} ·
                                {{ preferenceLabels[profile?.budget_preference] ?? '—' }}
                            </p>
                        </div>
                    </div>
                    <span :class="openSection === 'habits' ? 'rotate-180' : ''"
                          class="transition-transform text-[#1A2E2B]/30 text-[12px]">▾</span>
                </button>

                <div v-if="openSection === 'habits'"
                     class="px-5 pb-5 border-t border-[#1A2E2B]/6 pt-4 space-y-4">

                    <div>
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('habit_tendency_title') }}
                        </p>
                        <div class="space-y-2">
                            <button v-for="opt in [
                                    { value: 'spends_quickly', label: t('tendency_quick'),   emoji: '💸', hint: t('tendency_quick_hint') },
                                    { value: 'depends',        label: t('tendency_depends'), emoji: '🤷', hint: t('tendency_depends_hint') },
                                    { value: 'saves',          label: t('tendency_saves'),   emoji: '🐖', hint: t('tendency_saves_hint') },
                                ]"
                                    :key="opt.value" type="button"
                                    @click="habitsForm.spending_tendency = opt.value"
                                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-[1.5px] text-left transition-all"
                                    :class="habitsForm.spending_tendency === opt.value
                                        ? 'border-tema-green bg-tema-green/8'
                                        : 'border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20'">
                                <span class="text-lg flex-shrink-0">{{ opt.emoji }}</span>
                                <div class="flex-1">
                                    <span class="text-[13px] font-medium block"
                                          :class="habitsForm.spending_tendency === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/80'">
                                        {{ opt.label }}
                                    </span>
                                    <span v-if="habitsForm.spending_tendency === opt.value"
                                          class="text-[11px] text-tema-green/70">{{ opt.hint }}</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('habit_struggle_title') }}
                        </p>
                        <div class="space-y-2">
                            <button v-for="opt in [
                                    { value: 'often',     label: t('struggle_often'),     emoji: '😰', hint: t('struggle_often_hint') },
                                    { value: 'sometimes', label: t('struggle_sometimes'), emoji: '😐', hint: t('struggle_sometimes_hint') },
                                    { value: 'rarely',    label: t('struggle_rarely'),    emoji: '😌', hint: t('struggle_rarely_hint') },
                                ]"
                                    :key="opt.value" type="button"
                                    @click="habitsForm.budget_struggle_frequency = opt.value"
                                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-[1.5px] text-left transition-all"
                                    :class="habitsForm.budget_struggle_frequency === opt.value
                                        ? 'border-tema-green bg-tema-green/8'
                                        : 'border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20'">
                                <span class="text-lg flex-shrink-0">{{ opt.emoji }}</span>
                                <div class="flex-1">
                                    <span class="text-[13px] font-medium block"
                                          :class="habitsForm.budget_struggle_frequency === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/80'">
                                        {{ opt.label }}
                                    </span>
                                    <span v-if="habitsForm.budget_struggle_frequency === opt.value"
                                          class="text-[11px] text-tema-green/70">{{ opt.hint }}</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                            {{ t('habit_preference_title') }}
                        </p>
                        <div class="space-y-2">
                            <button v-for="opt in [
                                    { value: 'strict',   label: t('preference_strict'),   emoji: '📋', hint: t('preference_strict_hint') },
                                    { value: 'flexible', label: t('preference_flexible'), emoji: '🌊', hint: t('preference_flexible_hint') },
                                ]"
                                    :key="opt.value" type="button"
                                    @click="habitsForm.budget_preference = opt.value"
                                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-[1.5px] text-left transition-all"
                                    :class="habitsForm.budget_preference === opt.value
                                        ? 'border-tema-green bg-tema-green/8'
                                        : 'border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20'">
                                <span class="text-lg flex-shrink-0">{{ opt.emoji }}</span>
                                <div class="flex-1">
                                    <span class="text-[13px] font-medium block"
                                          :class="habitsForm.budget_preference === opt.value ? 'text-tema-green' : 'text-[#1A2E2B]/80'">
                                        {{ opt.label }}
                                    </span>
                                    <span v-if="habitsForm.budget_preference === opt.value"
                                          class="text-[11px] text-tema-green/70">{{ opt.hint }}</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <button @click="submitHabits"
                            :disabled="!habitsForm.spending_tendency || !habitsForm.budget_struggle_frequency || !habitsForm.budget_preference || habitsForm.processing"
                            class="w-full py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                        {{ habitsForm.processing ? '...' : t('save_changes') }}
                    </button>
                </div>
            </div>

            <!-- Revenus -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="toggleSection('income')"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">💰</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('my_income') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">
                                {{ incomeSources?.length ?? 0 }} {{ t('source') }}(s) ·
                                {{ formatFcfa(summary.safeIncome) }}
                            </p>
                        </div>
                    </div>
                    <span :class="openSection === 'income' ? 'rotate-180' : ''"
                          class="transition-transform text-[#1A2E2B]/30 text-[12px]">▾</span>
                </button>

                <div v-if="openSection === 'income'"
                     class="px-5 pb-5 border-t border-[#1A2E2B]/6 pt-4 space-y-3">

                    <div v-for="(source, index) in incomeForm.income_sources" :key="index"
                         class="bg-[#FAF6F0] rounded-xl p-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <p class="text-[12px] font-semibold text-[#1A2E2B]/60">
                                {{ t('source') }} {{ index + 1 }}
                            </p>
                            <button v-if="incomeForm.income_sources.length > 1"
                                    type="button" @click="removeIncomeSource(index)"
                                    class="w-6 h-6 rounded-full bg-tema-brick/10 text-tema-brick text-[11px] flex items-center justify-center hover:bg-tema-brick/20 transition-all">
                                ✕
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="tp in incomeTypes" :key="tp.value"
                                    type="button" @click="source.type = tp.value"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border-[1.5px] text-[11px] font-medium transition-all"
                                    :class="source.type === tp.value
                                        ? 'border-tema-green bg-white text-tema-green'
                                        : 'border-[#1A2E2B]/10 bg-white text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                                {{ tp.emoji }} {{ tp.label }}
                            </button>
                        </div>
                        <div class="relative">
                            <input type="number" v-model.number="source.amount"
                                   :placeholder="t('amount')" min="0"
                                   class="w-full text-[15px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green pr-16 py-3 bg-white">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-[#1A2E2B]/40">FCFA</span>
                        </div>
                        <div class="flex gap-1.5 flex-wrap">
                            <button v-for="f in frequencies" :key="f.value"
                                    type="button" @click="source.frequency = f.value"
                                    class="px-2.5 py-1.5 rounded-full border-[1.5px] text-[11px] font-medium transition-all"
                                    :class="source.frequency === f.value
                                        ? 'border-tema-green bg-white text-tema-green'
                                        : 'border-[#1A2E2B]/10 bg-white text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                                {{ f.label }}
                            </button>
                        </div>
                        <input type="text" v-model="source.label"
                               :placeholder="t('label_optional')"
                               class="w-full text-[12px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 bg-white">
                    </div>

                    <button type="button" @click="addIncomeSource"
                            class="w-full py-3 rounded-xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[12px] font-semibold hover:bg-tema-green/3 transition-all">
                        {{ t('add_source') }}
                    </button>
                    <button @click="submitIncome" :disabled="incomeForm.processing"
                            class="w-full py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                        {{ incomeForm.processing ? '...' : t('save_changes') }}
                    </button>
                </div>
            </div>

            <!-- Personnes à charge -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="toggleSection('dependents')"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">👨‍👩‍👧</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('dependents') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">
                                {{ dependents?.length ?? 0 }} {{ t('person') }}(s)
                            </p>
                        </div>
                    </div>
                    <span :class="openSection === 'dependents' ? 'rotate-180' : ''"
                          class="transition-transform text-[#1A2E2B]/30 text-[12px]">▾</span>
                </button>

                <div v-if="openSection === 'dependents'"
                     class="px-5 pb-5 border-t border-[#1A2E2B]/6 pt-4 space-y-3">

                    <div v-if="dependentsForm.dependents.length === 0"
                         class="bg-[#FAF6F0] rounded-xl px-4 py-4 text-center">
                        <p class="text-[12px] text-[#1A2E2B]/35">{{ t('no_dependents') }}</p>
                    </div>

                    <div v-for="(dep, index) in dependentsForm.dependents" :key="index"
                         class="bg-[#FAF6F0] rounded-xl p-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <p class="text-[12px] font-semibold text-[#1A2E2B]/60">
                                {{ t('person') }} {{ index + 1 }}
                            </p>
                            <button type="button" @click="removeDependent(index)"
                                    class="w-6 h-6 rounded-full bg-tema-brick/10 text-tema-brick text-[11px] flex items-center justify-center hover:bg-tema-brick/20 transition-all">
                                ✕
                            </button>
                        </div>

                        <div class="flex gap-2">
                            <button v-for="rel in [
                                    { v: 'child',  l: t('child'),  e: '👶' },
                                    { v: 'parent', l: t('parent'), e: '👴' },
                                    { v: 'other',  l: t('other'),  e: '👤' },
                                ]"
                                    :key="rel.v" type="button"
                                    @click="onRelationChange(dep, rel.v)"
                                    class="flex-1 flex flex-col items-center py-2.5 rounded-xl border-[1.5px] transition-all"
                                    :class="dep.relation === rel.v
                                        ? 'border-tema-green bg-white text-tema-green'
                                        : 'border-[#1A2E2B]/10 bg-white text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                                <span class="text-lg mb-0.5">{{ rel.e }}</span>
                                <span class="text-[11px] font-semibold">{{ rel.l }}</span>
                            </button>
                        </div>

                        <select v-model="dep.age_range"
                                class="w-full text-[12px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 bg-white">
                            <option v-for="r in ageRanges" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>

                        <div v-if="dep.relation === 'child'"
                             class="flex items-center justify-between py-2 border-t border-[#1A2E2B]/6">
                            <span class="text-[12px] text-[#1A2E2B]/70">{{ t('schooled') }}</span>
                            <div @click="dep.is_schooled = !dep.is_schooled"
                                 class="w-11 h-6 rounded-full transition-colors cursor-pointer relative"
                                 :class="dep.is_schooled ? 'bg-tema-green' : 'bg-[#1A2E2B]/15'">
                                <div class="w-[18px] h-[18px] bg-white rounded-full shadow-sm absolute top-[3px] transition-transform"
                                     :class="dep.is_schooled ? 'translate-x-[20px]' : 'translate-x-[3px]'"/>
                            </div>
                        </div>

                        <div v-if="dep.relation !== 'parent'"
                             class="flex items-center justify-between py-2 border-t border-[#1A2E2B]/6">
                            <span class="text-[12px] text-[#1A2E2B]/70">
                                {{ dep.relation === 'child' ? t('allowance') : t('allowance_other') }}
                            </span>
                            <div @click="dep.has_allowance = !dep.has_allowance"
                                 class="w-11 h-6 rounded-full transition-colors cursor-pointer relative"
                                 :class="dep.has_allowance ? 'bg-tema-green' : 'bg-[#1A2E2B]/15'">
                                <div class="w-[18px] h-[18px] bg-white rounded-full shadow-sm absolute top-[3px] transition-transform"
                                     :class="dep.has_allowance ? 'translate-x-[20px]' : 'translate-x-[3px]'"/>
                            </div>
                        </div>

                        <div v-if="dep.relation !== 'parent' && dep.has_allowance" class="flex gap-2">
                            <div class="relative flex-1">
                                <input type="number" v-model.number="dep.allowance_amount"
                                       :placeholder="t('amount')" min="0"
                                       class="w-full text-[12px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 pr-14 bg-white">
                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-[#1A2E2B]/40">FCFA</span>
                            </div>
                            <select v-model="dep.allowance_frequency"
                                    class="flex-1 text-[12px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5 bg-white">
                                <option value="daily">{{ t('per_day') }}</option>
                                <option value="weekly">{{ t('per_week') }}</option>
                                <option value="monthly">{{ t('per_month') }}</option>
                            </select>
                        </div>

                        <div v-if="dep.relation === 'parent'" class="pt-2 border-t border-[#1A2E2B]/6">
                            <p class="text-[11px] text-[#1A2E2B]/40 italic">{{ t('parent_note') }}</p>
                        </div>
                    </div>

                    <button type="button" @click="addDependent"
                            class="w-full py-3 rounded-xl border-[1.5px] border-dashed border-tema-green/30 text-tema-green text-[12px] font-semibold hover:bg-tema-green/3 transition-all">
                        {{ t('add_person') }}
                    </button>
                    <button @click="submitDependents" :disabled="dependentsForm.processing"
                            class="w-full py-3 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                        {{ dependentsForm.processing ? '...' : t('save_changes') }}
                    </button>
                </div>
            </div>

            <!-- Finances (lien) -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="router.get(route('finances.index'))"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">💳</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('charges_debts') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">
                                {{ activeChargesCount }} {{ t('active_charges').toLowerCase() }} ·
                                {{ activeDebtsCount }} {{ t('active_debts').toLowerCase() }}
                            </p>
                        </div>
                    </div>
                    <span class="text-[#1A2E2B]/30 text-[12px]">→</span>
                </button>
            </div>

            <!-- Mon compte -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <button @click="toggleSection('account')"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-[#FAF6F0] transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">⚙️</span>
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ t('my_account') }}</p>
                            <p class="text-[11px] text-[#1A2E2B]/40">{{ t('account_desc') }}</p>
                        </div>
                    </div>
                    <span :class="openSection === 'account' ? 'rotate-180' : ''"
                          class="transition-transform text-[#1A2E2B]/30 text-[12px]">▾</span>
                </button>

                <div v-if="openSection === 'account'"
                     class="px-5 pb-5 border-t border-[#1A2E2B]/6 pt-4 space-y-3">

                    <!-- Langue -->
                    <div class="bg-[#FAF6F0] rounded-xl p-4">
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-3">
                            {{ t('language') }}
                        </p>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <button v-for="lang in [
                                    { value: 'fr', label: '🇫🇷 Français' },
                                    { value: 'en', label: '🇬🇧 English' },
                                ]"
                                    :key="lang.value" type="button"
                                    @click="localeForm.locale = lang.value"
                                    class="py-3 rounded-xl border-[1.5px] text-[13px] font-semibold transition-all"
                                    :class="localeForm.locale === lang.value
                                        ? 'border-tema-green bg-tema-green/8 text-tema-green'
                                        : 'border-[#1A2E2B]/10 bg-white text-[#1A2E2B]/60 hover:border-tema-green/30'">
                                {{ lang.label }}
                            </button>
                        </div>
                        <button @click="submitLocale"
                                :disabled="localeForm.locale === user.locale || switchingLocale"
                                class="w-full py-2.5 rounded-xl bg-tema-green text-white text-[13px] font-semibold disabled:opacity-40 hover:bg-tema-green-light transition-all">
                            {{ switchingLocale ? '...' : 'Appliquer / Apply' }}
                        </button>
                    </div>

                    <!-- Déconnexion -->
                    <button @click="router.post(route('logout'))"
                            class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl border-[1.5px] border-[#1A2E2B]/10 hover:border-[#1A2E2B]/20 transition-all">
                        <span class="text-lg">🚪</span>
                        <span class="text-[13px] font-semibold text-[#1A2E2B]/70">{{ t('disconnect') }}</span>
                    </button>

                    <!-- Suppression compte -->
                    <button v-if="!showDeleteConfirm"
                            @click="showDeleteConfirm = true"
                            class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl border-[1.5px] border-tema-brick/20 hover:bg-tema-brick/5 transition-all">
                        <span class="text-lg">🗑️</span>
                        <span class="text-[13px] font-semibold text-tema-brick/70">
                            {{ t('delete_account') }}
                        </span>
                    </button>

                    <div v-if="showDeleteConfirm"
                         class="bg-tema-brick/5 border border-tema-brick/20 rounded-xl p-4 space-y-3">
                        <p class="text-[13px] font-semibold text-tema-brick">
                            ⚠️ {{ t('delete_irreversible') }}
                        </p>
                        <p class="text-[12px] text-[#1A2E2B]/60">
                            {{ t('delete_warning') }}
                        </p>
                        <input type="password"
                               v-model="deleteForm.password"
                               :placeholder="t('confirm_password')"
                               class="w-full text-[13px] rounded-xl border-tema-brick/30 focus:border-tema-brick focus:ring-tema-brick py-3">
                        <div class="flex gap-2">
                            <button @click="showDeleteConfirm = false; deleteForm.reset()"
                                    class="flex-1 py-3 rounded-xl border-[1.5px] border-[#1A2E2B]/12 text-[#1A2E2B]/50 text-[12px] hover:border-[#1A2E2B]/25 transition-all">
                                {{ t('cancel') }}
                            </button>
                            <button @click="submitDelete"
                                    :disabled="!deleteForm.password || deleteForm.processing"
                                    class="flex-1 py-3 rounded-xl bg-tema-brick text-white text-[12px] font-semibold disabled:opacity-40 hover:bg-tema-brick/80 transition-all">
                                {{ deleteForm.processing ? '...' : t('delete_confirm_btn') }}
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="pb-6"></div>

        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t, locale, tCategoryName } = useTranslation();

const props = defineProps({
    period:           String,
    year:             Number,
    month:            Number,
    startDate:        String,
    endDate:          String,
    totalIn:          Number,
    totalOut:         Number,
    balance:          Number,
    totalCount:       Number,
    savingsRate:      Number,
    comparison:       Object,
    byDayDetail:      Array,
    habitsByDay:      Array,
    forecast:         Object,
    biggestExpense:   Object,
    biggestIncome:    Object,
    byCategory:       Array,
    byIncomeSource:   Array,
    monthlyEvolution: Array,
    dailyEvolution:   Array,
    busiestDay:       Object,
    busiestHour:      Number,
    transactions:     Array,
    resteAVivre:      Number,
    salaryDay:        Number,
    cycleStart:       String,
    cycleEnd:         String,
});

const selectedPeriod = ref(props.period);
const selectedYear   = ref(props.year);
const selectedMonth  = ref(props.month);
const showAllTr      = ref(false);

const periods = computed(() => [
    { value: 'today', label: t('today') },
    { value: 'week',  label: t('this_week_short') },
    { value: 'month', label: t('this_month_short') },
    { value: 'year',  label: t('this_year_short') },
]);

const months = computed(() => locale.value === 'en'
    ? ['January','February','March','April','May','June','July','August','September','October','November','December']
        .map((l, i) => ({ value: i + 1, label: l }))
    : ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre']
        .map((l, i) => ({ value: i + 1, label: l }))
);

function applyFilters() {
    router.get(route('stats.index'), {
        period: selectedPeriod.value,
        year:   selectedYear.value,
        month:  selectedMonth.value,
    }, { preserveState: true, replace: true });
}

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

function formatDate(dateStr) {
    const loc = locale.value === 'en' ? 'en-GB' : 'fr-FR';
    return new Date(dateStr).toLocaleDateString(loc);
}

function formatDateTime(dateStr) {
    const loc = locale.value === 'en' ? 'en-GB' : 'fr-FR';
    return new Date(dateStr).toLocaleString(loc, {
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
    });
}

function categoryBarWidth(percent) { return Math.max(2, percent) + '%'; }

const categoryColors = [
    'bg-tema-terracotta', 'bg-tema-ocre', 'bg-tema-green',
    'bg-tema-green-light', 'bg-tema-brick/60',
];
function categoryColor(i) { return categoryColors[i % categoryColors.length]; }

const maxMonthlyOut = computed(() => Math.max(...props.monthlyEvolution.map(m => m.out), 1));
function barHeight(amount) { return Math.max(4, (amount / maxMonthlyOut.value) * 100) + '%'; }

const maxHabitsTotal = computed(() => Math.max(...(props.habitsByDay ?? []).map(d => d.total), 1));

const savingsColor = computed(() => {
    if (props.savingsRate >= 20) return 'text-tema-green';
    if (props.savingsRate >= 0)  return 'text-tema-ocre';
    return 'text-tema-brick';
});

const visibleTransactions = computed(() =>
    showAllTr.value ? props.transactions : props.transactions.slice(0, 10)
);
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
                <button @click="router.get(route('dashboard'))"
                        class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">←</button>
                <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B]">
                    {{ t('statistics') }}
                </h1>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

            <!-- Filtres -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 space-y-3">
                <div class="flex gap-2">
                    <button v-for="p in periods" :key="p.value"
                            @click="selectedPeriod = p.value; applyFilters()"
                            class="flex-1 text-[12px] py-2 rounded-xl border-[1.5px] transition-all"
                            :class="selectedPeriod === p.value
                                ? 'border-tema-green bg-tema-green/10 text-tema-green font-semibold'
                                : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                        {{ p.label }}
                    </button>
                </div>
                <div v-if="selectedPeriod === 'month'" class="flex gap-2">
                    <select v-model.number="selectedMonth" @change="applyFilters()"
                            class="flex-1 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <select v-model.number="selectedYear" @change="applyFilters()"
                            class="w-24 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                        <option v-for="y in [2024,2025,2026,2027]" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <div v-if="selectedPeriod === 'year'">
                    <select v-model.number="selectedYear" @change="applyFilters()"
                            class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                        <option v-for="y in [2024,2025,2026,2027]" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Info cycle financier -->
            <div v-if="salaryDay && period === 'month'"
                 class="bg-tema-green/8 border border-tema-green/15 rounded-2xl px-4 py-2.5 flex items-center gap-2">
                <span class="text-base">📅</span>
                <p class="text-[12px] text-tema-green/80">
                    {{ locale === 'en'
                        ? `Financial cycle: ${formatDate(cycleStart)} → ${formatDate(cycleEnd)} (payday: ${salaryDay})`
                        : `Cycle financier : ${formatDate(cycleStart)} → ${formatDate(cycleEnd)} (paie le ${salaryDay})` }}
                </p>
            </div>

            <!-- Résumé + Taux d'épargne -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('income') }}</p>
                    <p class="font-display font-semibold text-tema-green text-[15px]">+{{ formatFcfa(totalIn) }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('expenses') }}</p>
                    <p class="font-display font-semibold text-tema-brick text-[15px]">-{{ formatFcfa(totalOut) }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('balance') }}</p>
                    <p class="font-display font-semibold text-[15px]"
                       :class="balance >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                        {{ balance >= 0 ? '+' : '' }}{{ formatFcfa(balance) }}
                    </p>
                    <p v-if="resteAVivre" class="text-[10px] text-[#1A2E2B]/35 mt-0.5">
                        {{ locale === 'en' ? 'incl. declared balance' : 'solde déclaré inclus' }}
                    </p>
                </div>
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('savings_rate') }}</p>
                    <p class="font-display font-semibold text-[15px]" :class="savingsColor">
                        {{ savingsRate }}%
                    </p>
                    <p class="text-[10px] mt-0.5"
                       :class="savingsRate >= 20 ? 'text-tema-green/60' : savingsRate >= 0 ? 'text-tema-ocre/60' : 'text-tema-brick/60'">
                        {{ savingsRate >= 20 ? t('savings_good') : savingsRate >= 0 ? t('savings_ok') : t('savings_negative') }}
                    </p>
                </div>
            </div>

            <!-- Comparaison mois précédent -->
            <div v-if="comparison" class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-4">
                    {{ t('vs_prev_month') }} {{ comparison.prev_label }}
                </p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-[#FAF6F0] rounded-xl p-3">
                        <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('income') }}</p>
                        <p class="text-[13px] font-semibold text-[#1A2E2B]">{{ formatFcfa(comparison.prev_in) }}</p>
                        <div v-if="comparison.diff_in_pct !== null"
                             class="flex items-center gap-1 mt-1">
                            <span class="text-[11px] font-semibold"
                                  :class="comparison.diff_in >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                                {{ comparison.diff_in >= 0 ? '▲' : '▼' }}
                                {{ Math.abs(comparison.diff_in_pct) }}%
                            </span>
                            <span class="text-[11px] text-[#1A2E2B]/40">
                                ({{ comparison.diff_in >= 0 ? '+' : '' }}{{ formatFcfa(comparison.diff_in) }})
                            </span>
                        </div>
                    </div>
                    <div class="bg-[#FAF6F0] rounded-xl p-3">
                        <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('expenses') }}</p>
                        <p class="text-[13px] font-semibold text-[#1A2E2B]">{{ formatFcfa(comparison.prev_out) }}</p>
                        <div v-if="comparison.diff_out_pct !== null"
                             class="flex items-center gap-1 mt-1">
                            <span class="text-[11px] font-semibold"
                                  :class="comparison.diff_out <= 0 ? 'text-tema-green' : 'text-tema-brick'">
                                {{ comparison.diff_out >= 0 ? '▲' : '▼' }}
                                {{ Math.abs(comparison.diff_out_pct) }}%
                            </span>
                            <span class="text-[11px] text-[#1A2E2B]/40">
                                ({{ comparison.diff_out >= 0 ? '+' : '' }}{{ formatFcfa(comparison.diff_out) }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prévision fin de mois -->
            <div v-if="forecast"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-4">
                    {{ t('forecast_title') }}
                </p>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <div class="text-center">
                        <p class="text-[10px] text-[#1A2E2B]/40 mb-0.5">{{ t('days_elapsed') }}</p>
                        <p class="text-[18px] font-display font-semibold text-[#1A2E2B]">{{ forecast.days_elapsed }}</p>
                    </div>
                    <div class="text-center border-x border-[#1A2E2B]/6">
                        <p class="text-[10px] text-[#1A2E2B]/40 mb-0.5">{{ t('daily_avg_out') }}</p>
                        <p class="text-[13px] font-semibold text-tema-brick">{{ formatFcfa(forecast.daily_avg_out) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] text-[#1A2E2B]/40 mb-0.5">{{ t('days_remaining') }}</p>
                        <p class="text-[18px] font-display font-semibold text-[#1A2E2B]">{{ forecast.days_remaining }}</p>
                    </div>
                </div>
                <div class="bg-[#FAF6F0] rounded-xl p-4 space-y-2">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/50 uppercase tracking-widest">
                        {{ t('forecast_end_month') }}
                    </p>
                    <div class="flex justify-between items-center">
                        <span class="text-[13px] text-[#1A2E2B]/70">{{ t('forecast_out') }}</span>
                        <span class="font-semibold text-tema-brick text-[13px]">{{ formatFcfa(forecast.forecast_out) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[13px] text-[#1A2E2B]/70">{{ t('forecast_in') }}</span>
                        <span class="font-semibold text-tema-green text-[13px]">{{ formatFcfa(forecast.forecast_in) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-[#1A2E2B]/8">
                        <span class="text-[13px] font-semibold text-[#1A2E2B]">{{ t('forecast_balance') }}</span>
                        <span class="font-display font-semibold text-[15px]"
                              :class="forecast.forecast_balance >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                            {{ forecast.forecast_balance >= 0 ? '+' : '' }}{{ formatFcfa(forecast.forecast_balance) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[12px] text-[#1A2E2B]/50">{{ t('forecast_savings_rate') }}</span>
                        <span class="text-[12px] font-semibold"
                              :class="forecast.forecast_savings_rate >= 20 ? 'text-tema-green' : forecast.forecast_savings_rate >= 0 ? 'text-tema-ocre' : 'text-tema-brick'">
                            {{ forecast.forecast_savings_rate }}%
                        </span>
                    </div>
                </div>
                <p class="text-[11px] text-[#1A2E2B]/35 mt-2 text-center italic">
                    {{ t('forecast_disclaimer') }}
                </p>
            </div>

            <!-- Détail jour par jour -->
            <div v-if="byDayDetail && byDayDetail.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                <div class="px-5 py-4 border-b border-[#1A2E2B]/6">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest">
                        {{ t('day_by_day') }}
                    </p>
                </div>
                <div>
                    <div v-for="(day, index) in byDayDetail" :key="day.date"
                         class="px-5 py-3"
                         :class="[
                             index < byDayDetail.length - 1 ? 'border-b border-[#1A2E2B]/5' : '',
                             day.is_weekend ? 'bg-tema-ocre/3' : '',
                         ]">
                        <div class="flex justify-between items-center mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-semibold text-[#1A2E2B] capitalize">
                                    {{ day.label }}
                                </span>
                                <span v-if="day.is_weekend"
                                      class="text-[10px] bg-tema-ocre/15 text-tema-ocre px-1.5 py-0.5 rounded-full">
                                    Weekend
                                </span>
                            </div>
                            <span class="text-[11px] text-[#1A2E2B]/40">{{ day.count }} opé.</span>
                        </div>
                        <div class="flex justify-between text-[12px]">
                            <span class="text-tema-green" v-if="day.in > 0">+{{ formatFcfa(day.in) }}</span>
                            <span v-else class="text-[#1A2E2B]/20">+0</span>
                            <span class="font-semibold text-tema-brick">-{{ formatFcfa(day.out) }}</span>
                        </div>
                        <div class="h-1 rounded-full bg-[#FAF6F0] overflow-hidden mt-1.5">
                            <div class="h-full rounded-full bg-tema-terracotta/70 transition-all"
                                 :style="{ width: totalOut > 0 ? Math.min(100, (day.out / totalOut) * 100 * 3) + '%' : '0%' }"/>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyse habitudes par jour de la semaine -->
            <div v-if="habitsByDay && habitsByDay.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-4">
                    {{ t('habits_by_day') }}
                </p>
                <div class="flex items-end gap-2 h-24 mb-2">
                    <div v-for="day in habitsByDay" :key="day.day"
                         class="flex-1 flex flex-col items-center gap-1">
                        <div class="w-full flex flex-col justify-end" style="height: 100%">
                            <div class="w-full rounded-t-sm transition-all duration-500"
                                 :class="day.total === Math.max(...habitsByDay.map(d => d.total)) && day.total > 0
                                     ? 'bg-tema-brick'
                                     : 'bg-tema-terracotta/60'"
                                 :style="{ height: maxHabitsTotal > 0 ? Math.max(4, (day.total / maxHabitsTotal) * 100) + '%' : '4%' }"/>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <div v-for="day in habitsByDay" :key="day.day" class="flex-1 text-center">
                        <p class="text-[10px] text-[#1A2E2B]/50">{{ day.day }}</p>
                        <p v-if="day.total > 0" class="text-[10px] text-tema-brick font-semibold">
                            {{ formatFcfa(day.total) }}
                        </p>
                    </div>
                </div>
                <div v-if="busiestDay"
                     class="mt-3 pt-3 border-t border-[#1A2E2B]/5 text-center">
                    <p class="text-[12px] text-[#1A2E2B]/50">
                        📅 {{ t('busiest_day') }} :
                        <span class="font-semibold text-[#1A2E2B]">{{ busiestDay.date }}</span>
                        — {{ formatFcfa(busiestDay.total) }}
                    </p>
                </div>
                <div v-if="busiestHour !== null"
                     class="mt-1 text-center">
                    <p class="text-[12px] text-[#1A2E2B]/50">
                        🕐 {{ t('usual_time') }} {{ busiestHour }}h
                    </p>
                </div>
            </div>

            <!-- Faits marquants -->
            <div class="grid grid-cols-2 gap-3">
                <div v-if="biggestExpense" class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-2">{{ t('biggest_expense') }}</p>
                    <p class="font-display text-[15px] font-semibold text-tema-brick">{{ formatFcfa(biggestExpense.amount) }}</p>
                    <p class="text-[12px] text-[#1A2E2B]/70 mt-1 font-semibold">
                        {{ tCategoryName(biggestExpense.category, biggestExpense.translation_key) }}
                    </p>
                    <p class="text-[11px] text-[#1A2E2B]/40 mt-0.5">{{ biggestExpense.date }}</p>
                </div>
                <div v-if="biggestIncome" class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-2">{{ t('biggest_income') }}</p>
                    <p class="font-display text-[15px] font-semibold text-tema-green">+{{ formatFcfa(biggestIncome.amount) }}</p>
                    <p class="text-[12px] text-[#1A2E2B]/70 mt-1 font-semibold">
                        {{ tCategoryName(biggestIncome.category, biggestIncome.translation_key) }}
                    </p>
                    <p class="text-[11px] text-[#1A2E2B]/40 mt-0.5">{{ biggestIncome.date }}</p>
                </div>
            </div>

            <!-- Graphique évolution mensuelle -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-5">
                    {{ t('evolution_12m') }}
                </p>
                <div class="flex items-end gap-1 h-28 mb-2">
                    <div v-for="m in monthlyEvolution" :key="m.label"
                         class="flex-1 flex flex-col items-center gap-0.5">
                        <div class="w-full flex flex-col justify-end" style="height: 100%">
                            <div class="w-full bg-tema-terracotta/80 rounded-t-sm transition-all duration-500"
                                 :style="{ height: barHeight(m.out) }"/>
                        </div>
                    </div>
                </div>
                <div class="flex gap-1 mb-3">
                    <div v-for="m in monthlyEvolution" :key="m.label" class="flex-1 text-center">
                        <p class="text-[10px] text-[#1A2E2B]/40 truncate">{{ m.short }}</p>
                    </div>
                </div>
                <div class="flex justify-between items-center pt-3 border-t border-[#1A2E2B]/5">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-tema-terracotta/80"/>
                        <span class="text-[11px] text-[#1A2E2B]/50">{{ t('expenses') }}</span>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] text-[#1A2E2B]/50">{{ t('stats_12m_total') }}</p>
                        <p class="text-[13px] font-semibold text-[#1A2E2B]">
                            {{ formatFcfa(monthlyEvolution.reduce((s, m) => s + m.out, 0)) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Dépenses par catégorie -->
            <div v-if="byCategory.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-4">
                    {{ t('spending_breakdown') }}
                </p>
                <div class="space-y-3">
                    <div v-for="(cat, index) in byCategory" :key="cat.name" class="space-y-1">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="categoryColor(index)"/>
                                <span class="text-[13px] text-[#1A2E2B]/80">
                                    {{ tCategoryName(cat.name, cat.translation_key) }}
                                </span>
                                <span class="text-[11px] text-[#1A2E2B]/40">({{ cat.count }}x)</span>
                            </div>
                            <div class="text-right flex-shrink-0 ml-2">
                                <span class="text-[13px] font-semibold text-[#1A2E2B]">{{ formatFcfa(cat.total) }}</span>
                                <span class="text-[11px] text-[#1A2E2B]/40 ml-1">{{ cat.percent }}%</span>
                            </div>
                        </div>
                        <div class="h-1.5 rounded-full bg-[#FAF6F0] overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700"
                                 :class="categoryColor(index)"
                                 :style="{ width: categoryBarWidth(cat.percent) }"/>
                        </div>
                        <p v-if="cat.percent > 40"
                           class="text-[11px] text-tema-brick">
                            ⚠️ {{ cat.percent }}% {{ t('category_high_share') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Sources de revenus -->
            <div v-if="byIncomeSource.length > 0"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-4">
                    {{ t('income_sources') }}
                </p>
                <div class="space-y-3">
                    <div v-for="(source, index) in byIncomeSource" :key="source.name" class="space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-[13px] text-[#1A2E2B]/80">
                                {{ tCategoryName(source.name, source.translation_key) }}
                            </span>
                            <div class="text-right flex-shrink-0 ml-2">
                                <span class="text-[13px] font-semibold text-tema-green">+{{ formatFcfa(source.total) }}</span>
                                <span class="text-[11px] text-[#1A2E2B]/40 ml-1">{{ source.percent }}%</span>
                            </div>
                        </div>
                        <div class="h-1.5 rounded-full bg-[#FAF6F0] overflow-hidden">
                            <div class="h-full rounded-full bg-tema-green transition-all duration-700"
                                 :style="{ width: categoryBarWidth(source.percent) }"/>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des transactions -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">
                <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-4">
                    {{ t('transaction_count') }} ({{ totalCount }})
                </p>
                <div v-if="transactions.length === 0"
                     class="text-center py-6 text-[13px] text-[#1A2E2B]/50">
                    {{ t('no_transactions') }}
                </div>
                <div v-else class="space-y-0">
                    <div v-for="(tr, index) in visibleTransactions" :key="tr.id"
                         class="flex items-center gap-3 py-3"
                         :class="index < visibleTransactions.length - 1 ? 'border-b border-[#1A2E2B]/5' : ''">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 text-[13px] font-semibold"
                             :class="tr.direction === 'out' ? 'bg-tema-brick/10 text-tema-brick' : 'bg-tema-green/10 text-tema-green'">
                            {{ tr.direction === 'out' ? '−' : '+' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-[#1A2E2B] truncate">
                                {{ tCategoryName(tr.category, tr.translation_key) }}
                            </p>
                            <p class="text-[11px] text-[#1A2E2B]/50 mt-0.5">
                                {{ formatDateTime(tr.transacted_at) }}
                                <span v-if="tr.note" class="text-[#1A2E2B]/30"> · {{ tr.note }}</span>
                            </p>
                        </div>
                        <p class="font-display font-semibold text-[13px] flex-shrink-0"
                           :class="tr.direction === 'out' ? 'text-tema-brick' : 'text-tema-green'">
                            {{ tr.direction === 'out' ? '-' : '+' }}{{ formatFcfa(tr.amount) }}
                        </p>
                    </div>
                    <div v-if="transactions.length > 10 && !showAllTr" class="pt-3 text-center">
                        <button @click="showAllTr = true"
                                class="text-[13px] text-tema-green font-semibold hover:underline">
                            {{ t('see_more') }} {{ transactions.length - 10 }} {{ t('other_transactions') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Période -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 text-center mb-6">
                <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('stats_period_total') }}</p>
                <p class="font-display text-[28px] font-semibold text-[#1A2E2B]">{{ totalCount }}</p>
                <p class="text-[11px] text-[#1A2E2B]/40 mt-1">
                    {{ t('period') }} {{ formatDate(startDate) }} {{ t('to') }} {{ formatDate(endDate) }}
                </p>
            </div>

        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t, locale, tCategory } = useTranslation();

const props = defineProps({
    transactions: Object,
    totals:       Object,
    categories:   Array,
    filters:      Object,
});

const selectedPeriod    = ref(props.filters?.period      ?? 'month');
const selectedDirection = ref(props.filters?.direction   ?? '');
const selectedCategory  = ref(props.filters?.category_id ?? '');
const customDate        = ref(props.filters?.date_from   ?? '');

const periods = computed(() => [
    { value: 'today', label: t('today') },
    { value: 'week',  label: t('this_week') },
    { value: 'month', label: t('this_month') },
    { value: 'year',  label: t('this_year') },
    { value: 'custom', label: t('custom_period') },
]);

function applyFilters() {
    router.get(route('transactions.index'), {
        period:      selectedPeriod.value    || undefined,
        direction:   selectedDirection.value || undefined,
        category_id: selectedCategory.value  || undefined,
        // Pour custom : même date en from et to = filtre sur un jour précis
        date_from:   selectedPeriod.value === 'custom' ? (customDate.value || undefined) : undefined,
        date_to:     selectedPeriod.value === 'custom' ? (customDate.value || undefined) : undefined,
    }, { preserveState: true, replace: true });
}

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

function formatDateTime(dateStr) {
    const date      = new Date(dateStr);
    const today     = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    const loc     = locale.value === 'en' ? 'en-GB' : 'fr-FR';
    const timeStr = date.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });

    if (date.toDateString() === today.toDateString()) {
        return locale.value === 'en'
            ? `Today at ${timeStr}`
            : `Aujourd'hui à ${timeStr}`;
    }
    if (date.toDateString() === yesterday.toDateString()) {
        return locale.value === 'en'
            ? `Yesterday at ${timeStr}`
            : `Hier à ${timeStr}`;
    }
    return date.toLocaleDateString(loc, { day: 'numeric', month: 'short' })
        + (locale.value === 'en' ? ` at ${timeStr}` : ` à ${timeStr}`);
}

function formatDay(dateStr) {
    const loc = locale.value === 'en' ? 'en-GB' : 'fr-FR';
    return new Date(dateStr).toLocaleDateString(loc, {
        weekday: 'long', day: 'numeric', month: 'long',
    });
}

const groupedTransactions = computed(() => {
    const groups = {};
    props.transactions.data.forEach(tr => {
        const day = new Date(tr.transacted_at).toLocaleDateString('fr-FR');
        if (!groups[day]) groups[day] = { label: formatDay(tr.transacted_at), items: [] };
        groups[day].items.push(tr);
    });
    return Object.values(groups);
});

function deleteTransaction(id) {
    if (!confirm(t('confirm_delete_transaction'))) return;
    router.delete(route('transactions.destroy', id), { preserveScroll: true });
}

const balance = computed(() => (props.totals?.total_in ?? 0) - (props.totals?.total_out ?? 0));
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="router.get(route('dashboard'))"
                            class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                        ←
                    </button>
                    <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B]">
                        {{ t('history') }}
                    </h1>
                </div>
                <button @click="router.get(route('transactions.create'))"
                        class="bg-tema-green text-white text-[12px] font-semibold px-4 py-2 rounded-xl hover:bg-tema-green-light transition-all">
                    {{ t('add') }}
                </button>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

            <!-- Filtres période -->
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button v-for="p in periods"
                        :key="p.value"
                        @click="selectedPeriod = p.value; if (p.value !== 'custom') applyFilters()"
                        class="flex-shrink-0 text-[12px] px-3 py-1.5 rounded-full border-[1.5px] transition-all"
                        :class="selectedPeriod === p.value
                            ? 'border-tema-green bg-tema-green/10 text-tema-green font-semibold'
                            : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                    {{ p.label }}
                </button>
            </div>

            <!-- Filtre date unique (si période = custom) -->
            <div v-if="selectedPeriod === 'custom'" class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                    {{ t('select_date') }}
                </label>
                <input type="date"
                       v-model="customDate"
                       @change="applyFilters()"
                       class="w-full text-[14px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
            </div>

            <!-- Filtres direction + catégorie -->
            <div class="flex gap-2">
                <select v-model="selectedDirection"
                        @change="applyFilters()"
                        class="flex-1 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                    <option value="">{{ t('all_directions') }}</option>
                    <option value="in">{{ t('income') }}</option>
                    <option value="out">{{ t('expenses') }}</option>
                </select>
                <select v-model="selectedCategory"
                        @change="applyFilters()"
                        class="flex-1 text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
                    <option value="">{{ t('all_categories') }}</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ tCategory(cat) }}
                    </option>
                </select>
            </div>

            <!-- Résumé de la période -->
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-3 text-center">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('income') }}</p>
                    <p class="font-display font-semibold text-tema-green text-[13px]">
                        +{{ formatFcfa(totals?.total_in ?? 0) }}
                    </p>
                </div>
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-3 text-center">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('expenses') }}</p>
                    <p class="font-display font-semibold text-tema-brick text-[13px]">
                        -{{ formatFcfa(totals?.total_out ?? 0) }}
                    </p>
                </div>
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-3 text-center">
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-1">{{ t('balance') }}</p>
                    <p class="font-display font-semibold text-[13px]"
                       :class="balance >= 0 ? 'text-tema-green' : 'text-tema-brick'">
                        {{ balance >= 0 ? '+' : '' }}{{ formatFcfa(balance) }}
                    </p>
                </div>
            </div>

            <!-- Liste groupée par jour -->
            <div v-if="transactions.data.length === 0"
                 class="text-center py-10 text-[13px] text-[#1A2E2B]/50 bg-white rounded-2xl border border-[#1A2E2B]/10">
                {{ t('no_transactions') }}
            </div>

            <div v-for="group in groupedTransactions"
                 :key="group.label"
                 class="space-y-0">

                <p class="text-[11px] text-[#1A2E2B]/50 font-semibold mb-2 capitalize">
                    {{ group.label }}
                </p>

                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">
                    <div v-for="(transaction, index) in group.items"
                         :key="transaction.id"
                         class="flex items-center gap-3 px-4 py-3"
                         :class="index < group.items.length - 1 ? 'border-b border-[#1A2E2B]/5' : ''">

                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-[13px] font-semibold"
                             :class="transaction.direction === 'out'
                                 ? 'bg-tema-brick/10 text-tema-brick'
                                 : 'bg-tema-green/10 text-tema-green'">
                            {{ transaction.direction === 'out' ? '−' : '+' }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-[#1A2E2B] truncate">
                                {{ tCategory(transaction.category) }}
                            </p>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                                <p class="text-[11px] font-medium text-[#1A2E2B]/60">
                                    {{ formatDateTime(transaction.transacted_at) }}
                                </p>
                                <span v-if="transaction.fixed_charge"
                                      class="text-[10px] bg-tema-green/10 text-tema-green px-1.5 py-0.5 rounded-full">
                                    {{ transaction.fixed_charge.label }}
                                </span>
                                <span v-if="transaction.note"
                                      class="text-[11px] text-[#1A2E2B]/40 italic truncate max-w-[120px]">
                                    {{ transaction.note }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <p class="font-display font-semibold text-[13px]"
                               :class="transaction.direction === 'out' ? 'text-tema-brick' : 'text-tema-green'">
                                {{ transaction.direction === 'out' ? '-' : '+' }}
                                {{ formatFcfa(transaction.amount) }}
                            </p>
                            <p class="text-[11px] text-[#1A2E2B]/30 mt-0.5">
                                {{ transaction.source === 'quick_action' ? t('quick') : t('manual') }}
                            </p>
                        </div>

                        <button @click.stop="deleteTransaction(transaction.id)"
                                class="text-[#1A2E2B]/20 hover:text-tema-brick transition-colors ml-1 flex-shrink-0">
                            ✕
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1"
                 class="flex justify-center gap-2">
                <button v-if="transactions.prev_page_url"
                        @click="router.get(transactions.prev_page_url)"
                        class="px-4 py-2 text-[13px] bg-white border border-[#1A2E2B]/10 rounded-xl hover:border-[#1A2E2B]/20 transition-all">
                    ← {{ t('previous') }}
                </button>
                <span class="px-4 py-2 text-[13px] text-[#1A2E2B]/50">
                    {{ t('page') }} {{ transactions.current_page }} / {{ transactions.last_page }}
                </span>
                <button v-if="transactions.next_page_url"
                        @click="router.get(transactions.next_page_url)"
                        class="px-4 py-2 text-[13px] bg-white border border-[#1A2E2B]/10 rounded-xl hover:border-[#1A2E2B]/20 transition-all">
                    {{ t('next') }} →
                </button>
            </div>

        </div>
    </div>
</template>
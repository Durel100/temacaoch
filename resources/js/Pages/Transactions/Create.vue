<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t, locale, tCategory } = useTranslation();

const props = defineProps({
    categories:   Array,
    quickActions: Array,
    fixedCharges: Array,
});

const mode = ref('quick');

const form = ref({
    amount:          null,
    direction:       'out',
    category_id:     null,
    quick_action_id: null,
    fixed_charge_id: null,
    source:          'manual_custom',
    transacted_at:   new Date().toISOString().slice(0, 16),
    note:            '',
});

const errors         = ref({});
const isSubmitting   = ref(false);
const confirmAction  = ref(null);

function selectQuickAction(action) {
    confirmAction.value = {
        id:              action.id,
        label:           action.label,
        amount:          action.default_amount,
        direction:       action.direction,
        category:        action.category,
        fixed_charge_id: action.fixed_charge_id ?? null,
        is_fixed_charge: action.is_fixed_charge ?? false,
    };
}

function confirmQuickAction() {
    if (!confirmAction.value || isSubmitting.value) return;
    isSubmitting.value = true;

    router.post(route('transactions.store'), {
        amount:          confirmAction.value.amount,
        direction:       confirmAction.value.direction,
        category_id:     confirmAction.value.category.id,
        quick_action_id: confirmAction.value.id,
        fixed_charge_id: confirmAction.value.fixed_charge_id,
        source:          'quick_action',
        transacted_at:   form.value.transacted_at,
        note:            '',
    }, {
        onError:  (e) => { errors.value = e; },
        onFinish: () => {
            isSubmitting.value   = false;
            confirmAction.value  = null;
        },
    });
}

function cancelConfirm() {
    confirmAction.value = null;
}

const canSubmitManual = computed(() =>
    form.value.amount > 0 && form.value.category_id
);

function submitManual() {
    if (!canSubmitManual.value || isSubmitting.value) return;
    isSubmitting.value = true;

    router.post(route('transactions.store'), {
        ...form.value,
        source: 'manual_custom',
    }, {
        onError:  (e) => { errors.value = e; },
        onFinish: () => { isSubmitting.value = false; },
    });
}

const outCategories = computed(() =>
    props.categories.filter(c => c.default_direction === 'out' || !c.default_direction)
);
const inCategories = computed(() =>
    props.categories.filter(c => c.default_direction === 'in' || !c.default_direction)
);

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
}

// Dans le script setup de Create.vue
const quickActionKeyMap = {
    'Transport':              'cat_transport',
    'Nourriture/Marché':      'cat_food',
    'Recharge téléphonique':  'cat_phone',
    'Retrait agent':          'cat_withdrawal',
};

function translateActionLabel(label) {
    const key = quickActionKeyMap[label];
    return key ? t(key) : label;
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
                <button @click="router.get(route('dashboard'))"
                        class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                    ←
                </button>
                <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B]">
                    {{ t('add_transaction_title') }}
                </h1>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-5">

            <!-- Toggle mode -->
            <div class="flex bg-white rounded-xl border border-[#1A2E2B]/10 p-1 mb-5">
                <button @click="mode = 'quick'"
                        class="flex-1 py-2.5 text-[13px] font-semibold rounded-lg transition-all"
                        :class="mode === 'quick'
                            ? 'bg-tema-green text-white'
                            : 'text-[#1A2E2B]/60 hover:text-[#1A2E2B]'">
                    {{ t('quick') }}
                </button>
                <button @click="mode = 'manual'"
                        class="flex-1 py-2.5 text-[13px] font-semibold rounded-lg transition-all"
                        :class="mode === 'manual'
                            ? 'bg-tema-green text-white'
                            : 'text-[#1A2E2B]/60 hover:text-[#1A2E2B]'">
                    {{ t('manual') }}
                </button>
            </div>

            <!-- DATE commune -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 mb-4">
                <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                    {{ t('transaction_date') }}
                </label>
                <input type="datetime-local"
                       v-model="form.transacted_at"
                       class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
            </div>

            <!-- MODE RAPIDE -->
            <div v-if="mode === 'quick'">

                <!-- Panneau de confirmation -->
                <div v-if="confirmAction"
                     class="bg-white rounded-2xl border-2 border-tema-green p-5 mb-4">

                    <div class="flex justify-between items-start mb-3">
                        <p class="text-[14px] font-semibold text-[#1A2E2B]">
                            {{ t('confirm_transaction') }}
                        </p>
                        <span v-if="confirmAction.is_fixed_charge"
                              class="text-[11px] bg-tema-green/10 text-tema-green px-2 py-0.5 rounded-full font-semibold">
                            {{ t('fixed_budget') }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[13px] text-[#1A2E2B]/70">{{ translateActionLabel(confirmAction.label) }}</span>
                        <span class="font-display text-[20px] font-semibold"
                              :class="confirmAction.direction === 'out' ? 'text-tema-brick' : 'text-tema-green'">
                            {{ confirmAction.direction === 'out' ? '-' : '+' }}
                            {{ formatFcfa(confirmAction.amount) }}
                        </span>
                    </div>

                    <p v-if="confirmAction.is_fixed_charge"
                       class="text-[12px] text-tema-green/80 bg-tema-green/5 rounded-xl px-3 py-2 mb-3">
                        ✓ {{ t('fixed_charge_info') }}
                    </p>
                    <p v-else-if="confirmAction.direction === 'out'"
                       class="text-[12px] text-[#1A2E2B]/50 mb-3">
                        {{ t('free_expense_info') }}
                    </p>

                    <!-- Ajustement montant -->
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                        {{ t('adjust_amount') }}
                    </label>
                    <input type="number"
                           v-model.number="confirmAction.amount"
                           min="1"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3 mb-4">

                    <div class="flex gap-2">
                        <button @click="cancelConfirm"
                                class="flex-1 border-[1.5px] border-[#1A2E2B]/15 text-[#1A2E2B]/70 py-3 rounded-xl text-[13px] font-semibold hover:border-[#1A2E2B]/30 transition-all">
                            {{ t('cancel') }}
                        </button>
                        <button @click="confirmQuickAction"
                                :disabled="isSubmitting"
                                class="flex-1 bg-tema-green text-white py-3 rounded-xl text-[13px] font-semibold hover:bg-tema-green-light transition-all disabled:opacity-40">
                            {{ isSubmitting ? '...' : t('confirm') }}
                        </button>
                    </div>
                </div>

                <!-- Grille de boutons rapides -->
                <div v-else>
                    <p class="text-[12px] text-[#1A2E2B]/50 mb-3">
                        {{ t('quick_action_hint') }}
                    </p>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button v-for="action in quickActions"
                                :key="action.id ?? action.label"
                                @click="selectQuickAction(action)"
                                class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 text-left hover:border-tema-green hover:shadow-sm transition-all">

                            <div class="flex justify-between items-start mb-1">
                                <p class="text-[13px] font-semibold text-[#1A2E2B]">
                                    {{ translateActionLabel(action.label) }}
                                </p>
                                <span v-if="action.is_fixed_charge"
                                      class="text-[10px] bg-tema-green/10 text-tema-green px-1.5 py-0.5 rounded-full ml-1 flex-shrink-0 font-semibold">
                                    {{ t('fixed_badge') }}
                                </span>
                            </div>

                            <p class="text-[17px] font-display font-semibold"
                               :class="action.direction === 'out' ? 'text-tema-brick' : 'text-tema-green'">
                                {{ action.direction === 'out' ? '-' : '+' }}
                                {{ formatFcfa(action.default_amount) }}
                            </p>

                            <p v-if="action.usage_count > 0"
                               class="text-[11px] text-[#1A2E2B]/30 mt-1">
                                {{ t('used') }} {{ action.usage_count }}x
                            </p>
                        </button>
                    </div>

                    <button @click="mode = 'manual'"
                            class="w-full text-[13px] text-tema-green font-semibold border-[1.5px] border-dashed border-tema-green/30 rounded-2xl py-3.5 hover:bg-tema-green/5 transition-all">
                        {{ t('other_amount') }}
                    </button>
                </div>
            </div>

            <!-- MODE MANUEL -->
            <div v-if="mode === 'manual'" class="space-y-4">

                <!-- Entrée / Sortie -->
                <div class="grid grid-cols-2 gap-3">
                    <button @click="form.direction = 'out'; form.category_id = null; form.fixed_charge_id = null"
                            class="py-3.5 rounded-2xl border-[1.5px] text-[13px] font-semibold transition-all"
                            :class="form.direction === 'out'
                                ? 'border-tema-brick bg-tema-brick/10 text-tema-brick'
                                : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                        − {{ t('expenses') }}
                    </button>
                    <button @click="form.direction = 'in'; form.category_id = null; form.fixed_charge_id = null"
                            class="py-3.5 rounded-2xl border-[1.5px] text-[13px] font-semibold transition-all"
                            :class="form.direction === 'in'
                                ? 'border-tema-green bg-tema-green/10 text-tema-green'
                                : 'border-[#1A2E2B]/10 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/20'">
                        + {{ t('income') }}
                    </button>
                </div>

                <!-- Montant -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        {{ t('amount') }} (FCFA)
                    </label>
                    <input type="number"
                           v-model.number="form.amount"
                           placeholder="Ex : 5 000"
                           min="1"
                           class="w-full text-[20px] font-semibold rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                    <p v-if="errors.amount" class="text-[12px] text-tema-brick mt-1">
                        {{ errors.amount }}
                    </p>
                </div>

                <!-- Lier à un budget fixe -->
                <div v-if="form.direction === 'out' && fixedCharges && fixedCharges.length > 0"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-1">
                        {{ t('related_budget') }}
                    </label>
                    <p class="text-[12px] text-[#1A2E2B]/40 mb-3">
                        {{ t('related_budget_desc') }}
                    </p>
                    <div class="space-y-2">
                        <button type="button"
                                @click="form.fixed_charge_id = null"
                                class="w-full text-left px-3 py-2.5 rounded-xl border-[1.5px] text-[13px] transition-all"
                                :class="form.fixed_charge_id === null
                                    ? 'border-tema-green bg-tema-green/10 text-tema-green font-semibold'
                                    : 'border-[#1A2E2B]/10 text-[#1A2E2B]/70 hover:border-[#1A2E2B]/20'">
                            {{ t('free_expense_label') }}
                        </button>
                        <button v-for="charge in fixedCharges"
                                :key="charge.id"
                                type="button"
                                @click="form.fixed_charge_id = charge.id"
                                class="w-full text-left px-3 py-2.5 rounded-xl border-[1.5px] text-[13px] transition-all"
                                :class="form.fixed_charge_id === charge.id
                                    ? 'border-tema-green bg-tema-green/10 text-tema-green font-semibold'
                                    : 'border-[#1A2E2B]/10 text-[#1A2E2B]/70 hover:border-[#1A2E2B]/20'">
                            {{ charge.label }}
                            <span class="text-[11px] opacity-60 ml-1">
                                ({{ formatFcfa(charge.amount) }}/{{ t('freq_monthly') }})
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Catégorie -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        {{ t('income_type') }}
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="cat in (form.direction === 'out' ? outCategories : inCategories)"
                                :key="cat.id"
                                type="button"
                                @click="form.category_id = cat.id"
                                class="text-left text-[13px] px-3 py-2.5 rounded-xl border-[1.5px] transition-all"
                                :class="form.category_id === cat.id
                                    ? 'border-tema-green bg-tema-green/10 text-tema-green font-semibold'
                                    : 'border-[#1A2E2B]/10 text-[#1A2E2B]/70 hover:border-[#1A2E2B]/20'">
                            {{ tCategory(cat) }}
                        </button>
                    </div>
                    <p v-if="errors.category_id" class="text-[12px] text-tema-brick mt-1">
                        {{ errors.category_id }}
                    </p>
                </div>

                <!-- Note optionnelle -->
                <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4">
                    <label class="block text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest mb-2">
                        {{ t('note_optional') }}
                    </label>
                    <input type="text"
                           v-model="form.note"
                           :placeholder="t('note_placeholder')"
                           class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-3">
                </div>

                <button @click="submitManual"
                        :disabled="!canSubmitManual || isSubmitting"
                        class="w-full bg-tema-green text-white font-semibold py-4 rounded-2xl text-[15px] hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-sm mb-6">
                    {{ isSubmitting ? t('saving') : t('save_transaction') }}
                </button>

            </div>

        </div>
    </div>
</template>
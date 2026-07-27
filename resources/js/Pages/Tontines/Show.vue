<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t, locale } = useTranslation();

const props = defineProps({
    tontine:       Object,
    payoutAmount:  Number,
    myPayoutCycle: Object,
    nextCycle:     Object,
});

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'fr-FR', {
        day: 'numeric', month: 'long', year: 'numeric',
    });
}

function formatDateShort(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'fr-FR', {
        day: 'numeric', month: 'short',
    });
}

function daysUntil(dateStr) {
    if (!dateStr) return null;
    const dateOnly = dateStr.substring(0, 10);
    const target   = new Date(dateOnly + 'T12:00:00');
    const today    = new Date();
    today.setHours(12, 0, 0, 0);
    return Math.round((target - today) / (1000 * 60 * 60 * 24));
}

function cycleStatusLabel(cycle) {
    if (cycle.status === 'completed')
        return { label: t('cycle_completed'), cls: 'bg-tema-green/10 text-tema-green' };
    if (cycle.status === 'missed')
        return { label: t('cycle_missed'),    cls: 'bg-tema-brick/10 text-tema-brick' };
    if (cycle.contribution?.status === 'paid')
        return { label: t('cycle_paid'),      cls: 'bg-tema-green/10 text-tema-green' };
    if (cycle.contribution?.status === 'late')
        return { label: t('cycle_late'),      cls: 'bg-tema-brick/10 text-tema-brick' };
    return   { label: t('cycle_upcoming'),    cls: 'bg-[#1A2E2B]/8 text-[#1A2E2B]/50' };
}

const paidCount = computed(() =>
    props.tontine.cycles?.filter(c =>
        c.contribution?.status === 'paid' || c.status === 'completed'
    ).length ?? 0
);

const progressPercent = computed(() => {
    const total = props.tontine.cycles?.length ?? 0;
    return total > 0 ? Math.round((paidCount.value / total) * 100) : 0;
});

const markPaidForm = useForm({});

function markPaid(cycleId) {
    const amount = new Intl.NumberFormat('fr-FR').format(Math.round(props.tontine.contribution_amount ?? 0));
    const msg = locale.value === 'en'
        ? `This contribution of ${amount} FCFA will be deducted from your monthly budget. Confirm?`
        : `Cette cotisation de ${amount} FCFA sera déduite de ton budget du mois. Confirmer ?`;
    if (!confirm(msg)) return;
    markPaidForm.post(route('tontines.cycles.mark-paid', cycleId), {
        preserveScroll: true,
    });
}

function markReceived(cycleId) {
    if (!confirm(t('confirm_reception'))) return;
    markPaidForm.post(route('tontines.cycles.mark-received', cycleId), {
        preserveScroll: true,
    });
}

function deactivate() {
    if (!confirm(t('confirm_deactivate'))) return;
    router.patch(route('tontines.deactivate', props.tontine.id));
}

function reactivate() {
    if (!confirm(t('confirm_reactivate'))) return;
    router.patch(route('tontines.reactivate', props.tontine.id));
}

function deleteTontine() {
    if (!confirm(t('confirm_delete_tontine'))) return;
    router.delete(route('tontines.destroy', props.tontine.id));
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header sticky -->
        <div class="sticky top-0 z-50 bg-white border-b border-[#1A2E2B]/8" style="padding-top: env(safe-area-inset-top)">
            <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="router.get(route('tontines.index'))"
                            class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                        ←
                    </button>
                    <div>
                        <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B] leading-tight">
                            {{ tontine.name }}
                        </h1>
                        <p class="text-[11px] text-[#1A2E2B]/40">
                            {{ tontine.total_members }} {{ t('members_count') }} · {{ t('position') }} {{ tontine.my_position }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="tontine.is_active"
                            @click="deactivate"
                            class="text-[12px] text-[#1A2E2B]/35 hover:text-tema-brick transition-colors">
                        {{ t('tontine_deactivate') }}
                    </button>
                    <button v-if="!tontine.is_active"
                            @click="reactivate"
                            class="text-[12px] bg-tema-green/10 text-tema-green font-semibold px-2.5 py-1 rounded-lg hover:bg-tema-green/20 transition-all">
                        {{ t('tontine_reactivate') }}
                    </button>
                    <button @click="deleteTontine"
                            class="text-[12px] text-tema-brick/50 hover:text-tema-brick transition-colors ml-1">
                        🗑
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

            <!-- Résumé + progression -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5">

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-[#FAF6F0] rounded-xl p-3 text-center">
                        <p class="text-[11px] text-[#1A2E2B]/40 mb-0.5">{{ t('tontine_my_contrib') }}</p>
                        <p class="font-display text-[16px] font-semibold text-[#1A2E2B]">
                            {{ formatFcfa(tontine.contribution_amount) }}
                        </p>
                        <p class="text-[10px] text-[#1A2E2B]/35 mt-0.5">
                            {{ t('every_days') }} {{ tontine.cycle_days }} {{ t('days_label') }}
                        </p>
                    </div>
                    <div class="bg-[#FAF6F0] rounded-xl p-3 text-center">
                        <p class="text-[11px] text-[#1A2E2B]/40 mb-0.5">{{ t('tontine_will_get') }}</p>
                        <p class="font-display text-[16px] font-semibold text-tema-green">
                            {{ formatFcfa(payoutAmount) }}
                        </p>
                        <p class="text-[10px] text-[#1A2E2B]/35 mt-0.5">
                            {{ t('tontine_at_turn') }}
                        </p>
                    </div>
                </div>

                <!-- Barre de progression globale -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <p class="text-[11px] text-[#1A2E2B]/40">{{ t('tontine_progress') }}</p>
                        <p class="text-[11px] font-semibold text-[#1A2E2B]/60">
                            {{ paidCount }} / {{ tontine.cycles?.length ?? 0 }} {{ t('tontine_cycles') }}
                        </p>
                    </div>
                    <div class="h-2 rounded-full bg-[#FAF6F0] overflow-hidden">
                        <div class="h-full rounded-full bg-tema-green transition-all duration-700"
                             :style="{ width: progressPercent + '%' }"/>
                    </div>
                </div>
            </div>

            <!-- Alerte : mon tour arrive bientôt -->
            <div v-if="myPayoutCycle
                       && daysUntil(myPayoutCycle.scheduled_date) <= 14
                       && daysUntil(myPayoutCycle.scheduled_date) >= 0"
                 class="bg-tema-ocre/15 border border-tema-ocre/25 rounded-2xl p-4">
                <p class="text-[14px] font-semibold text-[#1A2E2B] mb-1">
                    {{ t('tontine_your_turn') }} {{ daysUntil(myPayoutCycle.scheduled_date) }} {{ t('tontine_days') }}
                </p>
                <p class="text-[12px] text-[#1A2E2B]/60 mb-3">
                    {{ formatFcfa(payoutAmount) }}
                    {{ t('tontine_receive') }} {{ formatDate(myPayoutCycle.scheduled_date) }}.
                    {{ t('tontine_plan') }}
                </p>
                <button @click="markReceived(myPayoutCycle.id)"
                        :disabled="markPaidForm.processing"
                        class="w-full bg-tema-ocre text-white text-[13px] font-semibold py-3 rounded-xl hover:opacity-90 disabled:opacity-40 transition-all">
                    {{ t('tontine_confirm') }}
                </button>
            </div>

            <!-- Prochain cycle -->
            <div v-if="nextCycle && tontine.is_active"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-tema-green/8 flex items-center justify-center flex-shrink-0">
                    <span class="text-lg">📅</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[12px] text-[#1A2E2B]/40">{{ t('tontine_next_pay') }}</p>
                    <p class="text-[14px] font-semibold text-[#1A2E2B]">
                        {{ formatDate(nextCycle.scheduled_date) }}
                    </p>
                </div>
                <span class="text-[12px] font-semibold text-tema-green flex-shrink-0">
                    {{ formatFcfa(tontine.contribution_amount) }}
                </span>
            </div>

            <!-- Calendrier des cycles -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">

                <div class="px-5 py-4 border-b border-[#1A2E2B]/6">
                    <p class="text-[11px] font-semibold text-[#1A2E2B]/40 uppercase tracking-widest">
                        {{ t('tontine_calendar') }}
                    </p>
                </div>

                <div>
                    <div v-for="(cycle, index) in tontine.cycles"
                         :key="cycle.id"
                         class="flex items-center gap-3 px-4 py-3.5"
                         :class="[
                             index < tontine.cycles.length - 1 ? 'border-b border-[#1A2E2B]/5' : '',
                             cycle.is_my_turn ? 'bg-tema-green/3' : '',
                         ]">

                        <!-- Numéro du cycle -->
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[12px] font-semibold flex-shrink-0"
                             :class="cycle.is_my_turn
                                 ? 'bg-tema-green text-white'
                                 : 'bg-[#FAF6F0] text-[#1A2E2B]/50'">
                            {{ cycle.cycle_number }}
                        </div>

                        <!-- Date + infos -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="text-[13px] font-semibold text-[#1A2E2B] truncate">
                                    {{ formatDateShort(cycle.scheduled_date) }}
                                </p>
                                <span v-if="cycle.is_my_turn"
                                      class="text-[10px] bg-tema-green/10 text-tema-green px-1.5 py-0.5 rounded-full font-semibold flex-shrink-0">
                                    {{ t('my_turn_label') }}
                                </span>
                            </div>
                            <p v-if="cycle.contribution?.amount_paid > 0"
                               class="text-[11px] text-[#1A2E2B]/35 mt-0.5">
                                {{ t('paid_on') }} {{ formatDateShort(cycle.contribution?.paid_date) }}
                            </p>
                        </div>

                        <!-- Statut -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-[11px] px-2 py-1 rounded-full font-medium"
                                  :class="cycleStatusLabel(cycle).cls">
                                {{ cycleStatusLabel(cycle).label }}
                            </span>

                            <!-- Bouton J'ai payé -->
                            <button v-if="!cycle.is_my_turn && tontine.is_active
                                         && (!cycle.contribution || cycle.contribution?.status === 'pending')"
                                    @click="markPaid(cycle.id)"
                                    :disabled="markPaidForm.processing"
                                    class="text-[11px] bg-tema-green/8 text-tema-green font-semibold px-2.5 py-1.5 rounded-lg hover:bg-tema-green/15 disabled:opacity-40 transition-all whitespace-nowrap">
                                {{ t('tontine_paid_btn') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tontine désactivée -->
            <div v-if="!tontine.is_active"
                 class="bg-[#1A2E2B]/4 rounded-2xl p-5">
                <p class="text-[13px] text-[#1A2E2B]/50 font-medium text-center mb-3">
                    {{ t('tontine_done') }}
                </p>
                <div class="flex gap-2">
                    <button @click="reactivate"
                            class="flex-1 bg-tema-green text-white text-[13px] font-semibold py-2.5 rounded-xl hover:bg-tema-green-light transition-all">
                        {{ t('tontine_reactivate') }}
                    </button>
                    <button @click="deleteTontine"
                            class="flex-1 border-[1.5px] border-tema-brick/30 text-tema-brick text-[13px] font-semibold py-2.5 rounded-xl hover:bg-tema-brick/8 transition-all">
                        {{ t('tontine_delete') }}
                    </button>
                </div>
            </div>

            <div class="pb-4"></div>

        </div>
    </div>
</template>
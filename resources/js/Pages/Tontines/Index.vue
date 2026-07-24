<script setup>
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

const { t, locale } = useTranslation();

const props = defineProps({
    tontines: Array,
});

function formatFcfa(amount) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(amount ?? 0)) + ' FCFA';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'fr-FR', {
        day: 'numeric', month: 'long', year: 'numeric'
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
                        {{ t('tontines_title') }}
                    </h1>
                </div>
                <button @click="router.get(route('tontines.create'))"
                        class="bg-tema-green text-white text-[12px] font-semibold px-4 py-2 rounded-xl hover:bg-tema-green-light transition-all">
                    {{ t('tontine_new_btn') }}
                </button>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

            <!-- État vide -->
            <div v-if="tontines.length === 0" class="text-center py-16">
                <div class="text-5xl mb-4">🤝</div>
                <h2 class="font-display text-[20px] font-semibold text-[#1A2E2B] mb-2">
                    {{ t('tontine_empty') }}
                </h2>
                <p class="text-[13px] text-[#1A2E2B]/60 mb-6 max-w-xs mx-auto">
                    {{ t('tontine_empty_desc') }}
                </p>
                <button @click="router.get(route('tontines.create'))"
                        class="bg-tema-green text-white font-semibold px-6 py-3 rounded-xl hover:bg-tema-green-light transition-all text-[14px]">
                    {{ t('tontine_create_btn') }}
                </button>
            </div>

            <!-- Liste des tontines -->
            <div v-for="tontine in tontines"
                 :key="tontine.id"
                 @click="router.get(route('tontines.show', tontine.id))"
                 class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-5 cursor-pointer hover:border-tema-green/30 hover:shadow-sm transition-all">

                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h2 class="font-display text-[16px] font-semibold text-[#1A2E2B] mb-0.5">
                            {{ tontine.name }}
                        </h2>
                        <p class="text-[12px] text-[#1A2E2B]/50">
                            {{ tontine.cycle_days }} {{ t('days_label') }} ·
                            {{ tontine.total_members }} {{ t('members_count') }} ·
                            {{ t('position') }} {{ tontine.my_position }}
                        </p>
                    </div>
                    <span class="text-[11px] px-2.5 py-1 rounded-full font-semibold"
                          :class="tontine.is_active
                              ? 'bg-tema-green/10 text-tema-green'
                              : 'bg-[#1A2E2B]/8 text-[#1A2E2B]/50'">
                        {{ tontine.is_active ? t('active') : t('tontine_done') }}
                    </span>
                </div>

                <!-- Infos clés -->
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-[#FAF6F0] rounded-xl p-3">
                        <p class="text-[11px] text-[#1A2E2B]/50 mb-0.5">{{ t('tontine_my_contrib') }}</p>
                        <p class="font-display font-semibold text-[#1A2E2B] text-[14px]">
                            {{ formatFcfa(tontine.contribution_amount) }}
                        </p>
                    </div>
                    <div class="bg-[#FAF6F0] rounded-xl p-3">
                        <p class="text-[11px] text-[#1A2E2B]/50 mb-0.5">{{ t('tontine_will_get') }}</p>
                        <p class="font-display font-semibold text-tema-green text-[14px]">
                            {{ formatFcfa(tontine.payout_amount) }}
                        </p>
                    </div>
                </div>

                <!-- Alerte cotisation en retard -->
                <div v-if="tontine.late_contributions_count > 0"
                     class="bg-tema-brick/10 rounded-xl px-3 py-2 mb-3 text-[12px] text-tema-brick font-medium">
                    ⚠️ {{ tontine.late_contributions_count }} {{ t('tontine_late') }}
                </div>

                <!-- Prochaine échéance -->
                <div v-if="tontine.next_cycle && tontine.is_active"
                     class="flex justify-between items-center text-[11px] text-[#1A2E2B]/50 pt-3 border-t border-[#1A2E2B]/5">
                    <span>{{ t('tontine_next') }}</span>
                    <span class="font-semibold"
                          :class="daysUntil(tontine.next_cycle.scheduled_date) <= 3
                              ? 'text-tema-brick'
                              : 'text-[#1A2E2B]'">
                        {{ formatDate(tontine.next_cycle.scheduled_date) }}
                        <span v-if="daysUntil(tontine.next_cycle.scheduled_date) >= 0">
                            ({{ t('in') }} {{ daysUntil(tontine.next_cycle.scheduled_date) }} {{ t('days_label') }})
                        </span>
                    </span>
                </div>

                <!-- Mon tour de réception -->
                <div v-if="tontine.my_payout_cycle && tontine.is_active"
                     class="flex justify-between items-center text-[11px] mt-2"
                     :class="daysUntil(tontine.my_payout_cycle.scheduled_date) <= 14
                         ? 'text-tema-ocre font-semibold'
                         : 'text-[#1A2E2B]/50'">
                    <span>🎯 {{ t('my_turn') }}</span>
                    <span>{{ formatDate(tontine.my_payout_cycle.scheduled_date) }}</span>
                </div>
            </div>

        </div>
    </div>
</template>
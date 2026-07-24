<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    users:   Object,
    filters: Object,
    total:   Number,
});

const search     = ref(props.filters?.search  ?? '');
const status     = ref(props.filters?.status  ?? 'all');
const searching  = ref(false);

let searchTimer = null;

function applyFilters() {
    router.get(route('admin.users'), {
        search: search.value || undefined,
        status: status.value !== 'all' ? status.value : undefined,
    }, { preserveState: true, replace: true });
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 400);
}

const statusOptions = [
    { value: 'all',       label: 'Tous' },
    { value: 'active',    label: 'Actifs (7j)' },
    { value: 'inactive',  label: 'Inactifs (30j+)' },
    { value: 'onboarding', label: 'Onboarding incomplet' },
];
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0]">

        <!-- Header -->
        <div class="sticky top-0 z-50 bg-[#1A2E2B] border-b border-white/10">
            <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="router.get(route('admin.dashboard'))"
                            class="text-white/60 hover:text-white transition-colors text-[14px]">
                        ←
                    </button>
                    <h1 class="font-display text-[18px] font-semibold text-white">
                        Utilisateurs
                    </h1>
                    <span class="bg-white/15 text-white text-[11px] font-semibold px-2.5 py-1 rounded-full">
                        {{ total }}
                    </span>
                </div>
                <button @click="router.get(route('dashboard'))"
                        class="text-[13px] bg-white/10 text-white px-3 py-1.5 rounded-lg hover:bg-white/20 transition-all">
                    ← App
                </button>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-6 space-y-4">

            <!-- Filtres -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 space-y-3">

                <!-- Recherche -->
                <input type="text"
                       v-model="search"
                       @input="onSearchInput"
                       placeholder="Rechercher par nom ou email..."
                       class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">

                <!-- Statut -->
                <div class="flex gap-2 flex-wrap">
                    <button v-for="opt in statusOptions" :key="opt.value"
                            @click="status = opt.value; applyFilters()"
                            class="text-[12px] px-3 py-1.5 rounded-full border-[1.5px] transition-all"
                            :class="status === opt.value
                                ? 'border-[#1A2E2B] bg-[#1A2E2B] text-white font-semibold'
                                : 'border-[#1A2E2B]/15 text-[#1A2E2B]/60 hover:border-[#1A2E2B]/30'">
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- Table utilisateurs -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 overflow-hidden">

                <!-- En-tête -->
                <div class="grid grid-cols-12 gap-2 px-4 py-3 bg-[#1A2E2B]/3 border-b border-[#1A2E2B]/8
                            text-[11px] font-semibold text-[#1A2E2B]/50 uppercase tracking-widest">
                    <div class="col-span-4">Utilisateur</div>
                    <div class="col-span-2 text-center">Statut</div>
                    <div class="col-span-2 text-center">Transactions</div>
                    <div class="col-span-2 text-center">Inscription</div>
                    <div class="col-span-2 text-center">Dernière activité</div>
                </div>

                <!-- Vide -->
                <div v-if="users.data.length === 0"
                     class="text-center py-12 text-[13px] text-[#1A2E2B]/40">
                    Aucun utilisateur trouvé
                </div>

                <!-- Lignes -->
                <div v-for="(user, index) in users.data" :key="user.id"
                     class="grid grid-cols-12 gap-2 px-4 py-3 items-center"
                     :class="[
                         index < users.data.length - 1 ? 'border-b border-[#1A2E2B]/5' : '',
                         !user.onboarding_completed ? 'bg-tema-ocre/3' : '',
                     ]">

                    <!-- Utilisateur -->
                    <div class="col-span-4 min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-tema-green/10 flex items-center justify-center flex-shrink-0">
                                <span class="text-[12px] font-semibold text-tema-green">
                                    {{ user.name?.charAt(0)?.toUpperCase() }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-[#1A2E2B] truncate">
                                    {{ user.name }}
                                </p>
                                <p class="text-[11px] text-[#1A2E2B]/40 truncate">
                                    {{ user.email }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Statut -->
                    <div class="col-span-2 flex flex-col items-center gap-1">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                              :class="user.is_active
                                  ? 'bg-tema-green/10 text-tema-green'
                                  : 'bg-[#1A2E2B]/8 text-[#1A2E2B]/50'">
                            {{ user.is_active ? 'Actif' : 'Inactif' }}
                        </span>
                        <span v-if="!user.onboarding_completed"
                              class="text-[10px] bg-tema-ocre/15 text-tema-ocre px-2 py-0.5 rounded-full font-semibold">
                            Onboarding
                        </span>
                        <span class="text-[10px] text-[#1A2E2B]/30">
                            {{ user.locale?.toUpperCase() }}
                        </span>
                    </div>

                    <!-- Transactions -->
                    <div class="col-span-2 text-center">
                        <p class="text-[14px] font-semibold text-[#1A2E2B]">
                            {{ user.transactions_count }}
                        </p>
                        <p class="text-[10px] text-[#1A2E2B]/40">transactions</p>
                    </div>

                    <!-- Date inscription -->
                    <div class="col-span-2 text-center">
                        <p class="text-[12px] text-[#1A2E2B]/70">{{ user.created_at }}</p>
                    </div>

                    <!-- Dernière activité -->
                    <div class="col-span-2 text-center">
                        <p class="text-[12px] text-[#1A2E2B]/50">{{ user.last_active }}</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1"
                 class="flex justify-center gap-2">
                <button v-if="users.prev_page_url"
                        @click="router.get(users.prev_page_url)"
                        class="px-4 py-2 text-[13px] bg-white border border-[#1A2E2B]/10 rounded-xl hover:border-[#1A2E2B]/20 transition-all">
                    ← Précédent
                </button>
                <span class="px-4 py-2 text-[13px] text-[#1A2E2B]/50">
                    Page {{ users.current_page }} / {{ users.last_page }}
                </span>
                <button v-if="users.next_page_url"
                        @click="router.get(users.next_page_url)"
                        class="px-4 py-2 text-[13px] bg-white border border-[#1A2E2B]/10 rounded-xl hover:border-[#1A2E2B]/20 transition-all">
                    Suivant →
                </button>
            </div>

        </div>
    </div>
</template>
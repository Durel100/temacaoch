<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    users:   Object,
    filters: Object,
    total:   Number,
});

const search      = ref(props.filters?.search ?? '');
const status      = ref(props.filters?.status ?? 'all');
const confirmDelete = ref(null);

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

function deleteUser(user) {
    confirmDelete.value = user;
}

function confirmDeleteUser() {
    if (!confirmDelete.value) return;
    router.delete(route('admin.users.destroy', confirmDelete.value.id), {
        onSuccess: () => { confirmDelete.value = null; },
    });
}

const statusOptions = [
    { value: 'all',        label: 'Tous' },
    { value: 'active',     label: 'Actifs (7j)' },
    { value: 'inactive',   label: 'Inactifs (30j+)' },
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
                            class="text-white/60 hover:text-white transition-colors text-[14px]">←</button>
                    <h1 class="font-display text-[18px] font-semibold text-white">Utilisateurs</h1>
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

        <!-- Popup confirmation suppression -->
        <div v-if="confirmDelete"
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
            <div class="bg-white rounded-2xl p-6 max-w-sm w-full">
                <p class="text-[15px] font-semibold text-[#1A2E2B] mb-2">
                    ⚠️ Supprimer ce compte ?
                </p>
                <p class="text-[13px] text-[#1A2E2B]/60 mb-1">
                    <span class="font-semibold">{{ confirmDelete.name }}</span>
                </p>
                <p class="text-[13px] text-[#1A2E2B]/40 mb-5">
                    {{ confirmDelete.email }}
                </p>
                <p class="text-[12px] text-tema-brick bg-tema-brick/8 rounded-xl px-3 py-2 mb-5">
                    Cette action est irréversible. Toutes les données de l'utilisateur seront supprimées.
                </p>
                <div class="flex gap-3">
                    <button @click="confirmDelete = null"
                            class="flex-1 py-3 rounded-xl border-[1.5px] border-[#1A2E2B]/15 text-[13px] font-semibold text-[#1A2E2B]/60 hover:border-[#1A2E2B]/30 transition-all">
                        Annuler
                    </button>
                    <button @click="confirmDeleteUser"
                            class="flex-1 py-3 rounded-xl bg-tema-brick text-white text-[13px] font-semibold hover:bg-tema-brick/80 transition-all">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-6 space-y-4">

            <!-- Filtres -->
            <div class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4 space-y-3">
                <input type="text"
                       v-model="search"
                       @input="onSearchInput"
                       placeholder="Rechercher par nom ou email..."
                       class="w-full text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green py-2.5">
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

            <!-- Liste utilisateurs — cartes au lieu de table -->
            <div class="space-y-3">

                <div v-if="users.data.length === 0"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 py-12 text-center text-[13px] text-[#1A2E2B]/40">
                    Aucun utilisateur trouvé
                </div>

                <div v-for="user in users.data" :key="user.id"
                     class="bg-white rounded-2xl border border-[#1A2E2B]/10 p-4"
                     :class="!user.onboarding_completed ? 'border-tema-ocre/30' : ''">

                    <div class="flex items-start gap-3">

                        <!-- Avatar -->
                        <div class="w-10 h-10 rounded-xl bg-tema-green/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-[14px] font-semibold text-tema-green">
                                {{ user.name?.charAt(0)?.toUpperCase() }}
                            </span>
                        </div>

                        <!-- Infos principales -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                <p class="text-[14px] font-semibold text-[#1A2E2B]">{{ user.name }}</p>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                                      :class="user.is_active
                                          ? 'bg-tema-green/10 text-tema-green'
                                          : 'bg-[#1A2E2B]/8 text-[#1A2E2B]/40'">
                                    {{ user.is_active ? 'Actif' : 'Inactif' }}
                                </span>
                                <span v-if="!user.onboarding_completed"
                                      class="text-[10px] bg-tema-ocre/15 text-tema-ocre px-2 py-0.5 rounded-full font-semibold">
                                    Onboarding
                                </span>
                            </div>
                            <p class="text-[12px] text-[#1A2E2B]/50 truncate">{{ user.email }}</p>
                        </div>

                        <!-- Bouton supprimer -->
                        <button @click="deleteUser(user)"
                                class="w-8 h-8 rounded-xl bg-tema-brick/8 text-tema-brick flex items-center justify-center hover:bg-tema-brick/20 transition-all flex-shrink-0 text-[13px]">
                            🗑
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-[#1A2E2B]/6">
                        <div class="text-center">
                            <p class="text-[15px] font-semibold text-[#1A2E2B]">{{ user.transactions_count }}</p>
                            <p class="text-[10px] text-[#1A2E2B]/40">transactions</p>
                        </div>
                        <div class="text-center border-x border-[#1A2E2B]/6">
                            <p class="text-[12px] font-semibold text-[#1A2E2B]">{{ user.created_at }}</p>
                            <p class="text-[10px] text-[#1A2E2B]/40">inscription</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[12px] text-[#1A2E2B]/60">{{ user.last_active }}</p>
                            <p class="text-[10px] text-[#1A2E2B]/40">activité</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="flex justify-center gap-2">
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
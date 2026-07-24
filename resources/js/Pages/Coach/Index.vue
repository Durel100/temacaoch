<script setup>
import { ref, nextTick, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import axios from 'axios';

const { t, locale } = useTranslation();

const props = defineProps({
    messages:       Array,
    conversationId: Number,
});

const messages      = ref([...props.messages]);
const inputMessage  = ref('');
const isLoading     = ref(false);
const messagesContainer = ref(null);

// Suggestions selon la langue
const suggestions = computed(() => locale.value === 'en'
    ? [
        "How much do I have left this month?",
        "How can I improve my health score?",
        "Should I repay my debts before saving?",
        "How can I better manage my tontine?",
        "Help me plan my monthly budget",
    ]
    : [
        "Combien me reste-t-il ce mois ?",
        "Comment améliorer mon score de santé ?",
        "Dois-je rembourser mes dettes avant d'épargner ?",
        "Comment mieux gérer ma tontine ?",
        "Aide-moi à planifier mon budget du mois",
    ]
);

// Label "Toi" / "You"
const youLabel = computed(() => locale.value === 'en' ? 'You' : 'Toi');

// Message d'erreur technique selon la langue
const techErrorMessage = computed(() => locale.value === 'en'
    ? "I'm having a technical issue. Please try again in a moment."
    : "Je rencontre un problème technique. Réessaie dans un moment."
);

onMounted(() => {
    scrollToBottom();
});

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

async function sendMessage(content = null) {
    const text = content ?? inputMessage.value.trim();
    if (!text || isLoading.value) return;

    messages.value.push({
        id:         Date.now(),
        role:       'user',
        content:    text,
        created_at: new Date().toISOString(),
    });

    inputMessage.value = '';
    isLoading.value    = true;
    scrollToBottom();

    try {
        const response = await axios.post(route('coach.send'), {
            message:         text,
            conversation_id: props.conversationId,
        });

        messages.value.push(response.data.message);
        scrollToBottom();

    } catch (error) {
        messages.value.push({
            id:         Date.now() + 1,
            role:       'assistant',
            content:    error.response?.data?.error ?? techErrorMessage.value,
            created_at: new Date().toISOString(),
        });
        scrollToBottom();
    } finally {
        isLoading.value = false;
    }
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function clearConversation() {
    if (!confirm(t('confirm_clear_chat'))) return;
    router.delete(route('coach.clear'), {
        onSuccess: () => { messages.value = []; }
    });
}

function formatTime(dateStr) {
    const loc = locale.value === 'en' ? 'en-GB' : 'fr-FR';
    return new Date(dateStr).toLocaleTimeString(loc, {
        hour:   '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#FAF6F0] flex flex-col">

        <!-- Header -->
        <div class="bg-white border-b border-[#1A2E2B]/8 px-4 py-3 flex items-center justify-between flex-shrink-0 sticky top-0 z-50">
            <div class="max-w-2xl mx-auto w-full flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="router.get(route('dashboard'))"
                            class="text-[#1A2E2B]/50 hover:text-[#1A2E2B] transition-colors text-[14px]">
                        ←
                    </button>
                    <div>
                        <h1 class="font-display text-[18px] font-semibold text-[#1A2E2B] leading-tight">
                            {{ t('coach_title') }}
                        </h1>
                        <p class="text-[11px] text-[#1A2E2B]/50">
                            {{ t('coach_subtitle') }}
                        </p>
                    </div>
                </div>

                <button v-if="messages.length > 0"
                        @click="clearConversation"
                        class="text-[12px] text-[#1A2E2B]/40 hover:text-tema-brick transition-colors">
                    {{ t('erase') }}
                </button>
            </div>
        </div>

        <!-- Zone de messages -->
        <div ref="messagesContainer"
             class="flex-1 overflow-y-auto px-4 py-6 max-w-2xl mx-auto w-full">

            <!-- État vide — suggestions -->
            <div v-if="messages.length === 0" class="space-y-6">

                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-tema-green/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-3xl">💬</span>
                    </div>
                    <h2 class="font-display text-[20px] font-semibold text-[#1A2E2B] mb-2">
                        {{ t('coach_empty_title') }}
                    </h2>
                    <p class="text-[13px] text-[#1A2E2B]/60 max-w-xs mx-auto">
                        {{ t('coach_empty_subtitle') }}
                    </p>
                </div>

                <div>
                    <p class="text-[11px] text-[#1A2E2B]/50 mb-3 text-center uppercase tracking-widest font-semibold">
                        {{ t('frequent_questions') }}
                    </p>
                    <div class="space-y-2">
                        <button v-for="suggestion in suggestions"
                                :key="suggestion"
                                @click="sendMessage(suggestion)"
                                class="w-full text-left text-[13px] px-4 py-3 bg-white rounded-xl border border-[#1A2E2B]/10 text-[#1A2E2B]/80 hover:border-tema-green hover:text-tema-green transition-all">
                            {{ suggestion }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div v-else class="space-y-4">
                <div v-for="message in messages"
                     :key="message.id"
                     class="flex"
                     :class="message.role === 'user' ? 'justify-end' : 'justify-start'">

                    <!-- Avatar coach -->
                    <div v-if="message.role === 'assistant'"
                         class="w-8 h-8 rounded-xl bg-tema-green/10 flex items-center justify-center flex-shrink-0 mr-2 mt-1">
                        <span class="text-sm">🧑‍💼</span>
                    </div>

                    <!-- Bulle -->
                    <div class="max-w-[75%]">
                        <div class="px-4 py-3 rounded-2xl text-[13px] leading-relaxed"
                             :class="message.role === 'user'
                                 ? 'bg-tema-green text-white rounded-tr-sm'
                                 : 'bg-white border border-[#1A2E2B]/10 text-[#1A2E2B]/90 rounded-tl-sm'">
                            {{ message.content }}
                        </div>
                        <p class="text-[11px] text-[#1A2E2B]/30 mt-1"
                           :class="message.role === 'user' ? 'text-right' : 'text-left'">
                            {{ formatTime(message.created_at) }}
                        </p>
                    </div>

                    <!-- Avatar utilisateur -->
                    <div v-if="message.role === 'user'"
                         class="w-8 h-8 rounded-xl bg-tema-green flex items-center justify-center flex-shrink-0 ml-2 mt-1">
                        <span class="text-white text-[11px] font-semibold">{{ youLabel }}</span>
                    </div>
                </div>

                <!-- Indicateur de frappe -->
                <div v-if="isLoading" class="flex justify-start">
                    <div class="w-8 h-8 rounded-xl bg-tema-green/10 flex items-center justify-center flex-shrink-0 mr-2">
                        <span class="text-sm">🧑‍💼</span>
                    </div>
                    <div class="bg-white border border-[#1A2E2B]/10 rounded-2xl rounded-tl-sm px-4 py-3">
                        <div class="flex gap-1 items-center">
                            <div class="w-2 h-2 rounded-full bg-[#1A2E2B]/30 animate-bounce"
                                 style="animation-delay: 0ms"/>
                            <div class="w-2 h-2 rounded-full bg-[#1A2E2B]/30 animate-bounce"
                                 style="animation-delay: 150ms"/>
                            <div class="w-2 h-2 rounded-full bg-[#1A2E2B]/30 animate-bounce"
                                 style="animation-delay: 300ms"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone de saisie -->
        <div class="bg-white border-t border-[#1A2E2B]/8 px-4 py-4 flex-shrink-0">
            <div class="max-w-2xl mx-auto flex gap-3 items-end">
                <textarea v-model="inputMessage"
                          @keydown="handleKeydown"
                          :placeholder="t('type_message')"
                          rows="1"
                          :disabled="isLoading"
                          class="flex-1 resize-none text-[13px] rounded-xl border-[#1A2E2B]/15 focus:border-tema-green focus:ring-tema-green disabled:opacity-50 max-h-32"
                          style="field-sizing: content; min-height: 42px;"/>
                <button @click="sendMessage()"
                        :disabled="!inputMessage.trim() || isLoading"
                        class="bg-tema-green text-white w-11 h-11 rounded-xl flex items-center justify-center hover:bg-tema-green-light transition-all disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"/>
                    </svg>
                </button>
            </div>
            <p class="text-[11px] text-[#1A2E2B]/30 text-center mt-2">
                {{ t('send_hint') }}
            </p>
        </div>

    </div>
</template>
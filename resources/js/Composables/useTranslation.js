import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import fr from '../lang/fr.js';
import en from '../lang/en.js';

const translations = { fr, en };

export function useTranslation() {
    const page   = usePage();
    const locale = computed(() => page.props.locale ?? 'fr');

    function t(key) {
        const trans = translations[locale.value] ?? translations['fr'];
        return trans[key] ?? key;
    }

    /**
     * Traduit un nom de catégorie via sa translation_key
     * Fallback sur category.name si la clé n'existe pas dans les fichiers de langue
     */
    function tCategory(category) {
        if (!category) return t('no_category');

        if (category.translation_key) {
            const trans      = translations[locale.value] ?? translations['fr'];
            const translated = trans[category.translation_key];
            // Si la clé existe dans les fichiers de langue, on l'utilise
            // Sinon on retombe sur le name stocké en base
            if (translated) return translated;
        }

        return category.name ?? t('no_category');
    }

    /**
     * Traduit un nom de catégorie depuis un objet simple { name, translation_key }
     * ou directement depuis un string (fallback)
     */
    function tCategoryName(name, translationKey = null) {
        if (translationKey) {
            const trans      = translations[locale.value] ?? translations['fr'];
            const translated = trans[translationKey];
            if (translated) return translated;
        }
        return name ?? t('no_category');
    }

    return { t, locale, tCategory, tCategoryName };
}
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Supprimer les catégories système existantes avant de re-seeder
        Category::whereNull('user_id')->delete();

        $categories = [
            // ── Dépenses (out) ──────────────────────────────────────────────
            [
                'name'            => 'Transport',
                'translation_key' => 'cat_transport',
                'icon'            => 'car',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Nourriture/Marché',
                'translation_key' => 'cat_food',
                'icon'            => 'shopping-basket',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Recharge téléphonique',
                'translation_key' => 'cat_phone',
                'icon'            => 'phone',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Retrait agent',
                'translation_key' => 'cat_withdrawal',
                'icon'            => 'cash',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Tontine',
                'translation_key' => 'cat_tontine',
                'icon'            => 'users',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Famille/Aide',
                'translation_key' => 'cat_family_help',
                'icon'            => 'heart',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Factures (eau/élec)',
                'translation_key' => 'cat_bills',
                'icon'            => 'bolt',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Scolarité',
                'translation_key' => 'cat_school',
                'icon'            => 'book',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Santé',
                'translation_key' => 'cat_health',
                'icon'            => 'medical',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Argent de poche',
                'translation_key' => 'cat_pocket_money',
                'icon'            => 'wallet',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Loyer',
                'translation_key' => 'cat_rent',
                'icon'            => 'home',
                'default_direction' => 'out',
            ],
            [
                'name'            => 'Loisirs/Sorties',
                'translation_key' => 'cat_leisure',
                'icon'            => 'film',
                'default_direction' => 'out',
            ],

            // ── Revenus (in) ─────────────────────────────────────────────────
            [
                'name'            => 'Salaire',
                'translation_key' => 'cat_salary',
                'icon'            => 'briefcase',
                'default_direction' => 'in',
            ],
            [
                'name'            => 'Vente/Activité',
                'translation_key' => 'cat_sales',
                'icon'            => 'store',
                'default_direction' => 'in',
            ],
            [
                'name'            => 'Don/Cadeau reçu',
                'translation_key' => 'cat_gift',
                'icon'            => 'gift',
                'default_direction' => 'in',
            ],
            [
                'name'            => 'Réception tontine',
                'translation_key' => 'cat_tontine_received',
                'icon'            => 'users',
                'default_direction' => 'in',
            ],
            [
                'name'            => 'Argent envoyé par la famille',
                'translation_key' => 'cat_family_received',
                'icon'            => 'send',
                'default_direction' => 'in',
            ],

            // ── Système ──────────────────────────────────────────────────────
            [
                'name'            => 'Remboursement découvert',
                'translation_key' => 'cat_overdraft_repay',
                'icon'            => 'wallet',
                'default_direction' => 'in',
                'is_system'       => true,
            ],
            [
                'name'            => 'Remboursement dette',
                'translation_key' => 'cat_debt_repay',
                'icon'            => 'wallet',
                'default_direction' => 'out',
                'is_system'       => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'user_id'           => null,
                'is_system'         => $category['is_system'] ?? true,
                'name'              => $category['name'],
                'translation_key'   => $category['translation_key'],
                'icon'              => $category['icon'],
                'default_direction' => $category['default_direction'],
            ]);
        }
    }
}
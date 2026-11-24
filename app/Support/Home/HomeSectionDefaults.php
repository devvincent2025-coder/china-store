<?php

namespace App\Support\Home;

use App\Models\HomeSection;
use Illuminate\Support\Facades\Schema;

/**
 * The homepage layout that shipped before sections became admin-editable — installed as real
 * home_sections rows (by the create_home_sections migration and HomeSectionSeeder) so admins
 * start from today's page and edit from there, rather than from a blank homepage.
 */
class HomeSectionDefaults
{
    /** Creates the default sections, but only when there are none yet (idempotent). */
    public static function install(): void
    {
        if (! Schema::hasTable('home_sections') || HomeSection::query()->exists()) {
            return;
        }

        foreach (self::definitions() as $index => $definition) {
            HomeSection::create($definition + ['sort_order' => ($index + 1) * 10, 'is_active' => true]);
        }
    }

    public static function definitions(): array
    {
        return [
            [
                'type' => 'hero',
                'title' => ['en' => 'Welcome to ChinaInNigeria'],
                'subtitle' => ['en' => 'Chinese-run stores · Unbeatable prices'],
                'settings' => ['show_account_card' => true],
            ],
            [
                'type' => 'categories',
                'title' => ['en' => 'Shop by category'],
            ],
            [
                'type' => 'trust',
                'title' => ['en' => 'Why shop with us'],
                'settings' => [
                    'items' => [
                        ['icon' => 'shield', 'title' => ['en' => 'Verified vendors'], 'text' => ['en' => 'Chinese-run stores across Nigeria']],
                        ['icon' => 'truck', 'title' => ['en' => 'Fast delivery'], 'text' => ['en' => 'Fast Lagos & Abuja delivery']],
                        ['icon' => 'lock', 'title' => ['en' => 'Secure checkout'], 'text' => ['en' => 'Secure multi-vendor checkout']],
                        ['icon' => 'tag', 'title' => ['en' => 'Naira pricing'], 'text' => ['en' => 'Compare prices across stores']],
                    ],
                ],
            ],
            [
                'type' => 'flash_deals',
                'title' => ['en' => 'Flash Deals', 'zh' => '限时秒杀', 'fr' => 'Ventes flash'],
                'item_limit' => 4,
                'auto_fill' => true,
            ],
            [
                'type' => 'collection',
                'title' => ['en' => 'Live picks'],
                'subtitle' => ['en' => 'Trending now'],
                'settings' => ['theme' => 'rose', 'link_url' => '/products'],
                'item_limit' => 2,
                'auto_fill' => true,
            ],
            [
                'type' => 'collection',
                'title' => ['en' => 'Factory direct'],
                'subtitle' => ['en' => 'Vendor sourced'],
                'settings' => ['theme' => 'ink', 'link_url' => '/products'],
                'item_limit' => 2,
                'auto_fill' => true,
            ],
            [
                'type' => 'collection',
                'title' => ['en' => 'Low-price zone'],
                'subtitle' => ['en' => 'Budget finds'],
                'settings' => ['theme' => 'mint', 'link_url' => '/products'],
                'item_limit' => 2,
                'auto_fill' => true,
            ],
            [
                'type' => 'coupons',
                'eyebrow' => ['en' => 'Save more'],
                'title' => ['en' => 'Coupons for you'],
            ],
            [
                'type' => 'top_picks',
                'eyebrow' => ['en' => 'Sponsored'],
                'title' => ['en' => 'Top Picks — Recommended for you'],
            ],
            [
                'type' => 'product_grid',
                'eyebrow' => ['en' => 'Just for you'],
                'title' => ['en' => 'You might like'],
                'item_limit' => 30,
                'auto_fill' => true,
            ],
            [
                'type' => 'sell_cta',
                'eyebrow' => ['en' => 'Sell on CIN'],
                'title' => ['en' => 'Reach buyers across Nigeria with your own store'],
                'subtitle' => ['en' => 'Set up in minutes, list products in Naira, and get paid securely.'],
                'settings' => ['button_label' => ['en' => 'Open a shop'], 'button_url' => '/open-shop'],
            ],
            [
                'type' => 'faq',
                'eyebrow' => ['en' => 'Help centre'],
                'title' => ['en' => 'Frequently Asked Questions'],
                'settings' => [
                    'faqs' => [
                        [
                            'question' => ['en' => 'Are there Chinese stores I can buy from directly in Nigeria?'],
                            'answer' => ['en' => 'Yes. ChinaInNigeria hosts Chinese-run vendor stores already based in Nigeria, so you buy directly from a local seller and pay in Naira — no importing or waiting on international shipping.'],
                        ],
                        [
                            'question' => ['en' => 'Is it safe to buy from Chinese vendors online in Nigeria?'],
                            'answer' => ['en' => "Every vendor on ChinaInNigeria goes through identity verification before they can sell, and every order is held in escrow and only released to the vendor once you confirm you're happy with what you received."],
                        ],
                        [
                            'question' => ['en' => 'How is delivery handled?'],
                            'answer' => ['en' => 'Vendors ship from their local Nigerian base, so delivery times and costs are typically faster and cheaper than ordering directly from China.'],
                        ],
                        [
                            'question' => ['en' => 'Do I pay in Naira?'],
                            'answer' => ['en' => 'Yes, every price on ChinaInNigeria is listed and charged in Naira, with no foreign currency conversion or customs charges to worry about.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}

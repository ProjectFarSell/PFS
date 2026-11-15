<?php

namespace App\Support;

use App\Models\Category;

class CatalogSections
{
    public const SECTIONS = [
        'trading-cards' => ['Trading Cards', 'trading card'],
        'figures-models' => ['Figures & Models', 'figure'],
        'custom-keyboards' => ['Custom Keyboards', 'keyboard'],
        'gaming-audio' => ['Gaming & Audio', 'gaming'],
        'anime-merch' => ['Anime Merch', 'anime'],
        'desk-accessories' => ['Desk Accessories', 'desk'],
    ];

    public static function links(): array
    {
        $ids = Category::whereIn('slug', array_keys(self::SECTIONS))->pluck('id', 'slug');
        $links = [];
        foreach (self::SECTIONS as $slug => [$label, $search]) {
            $links[] = ['label' => $label, 'filter' => isset($ids[$slug]) ? ['category' => $ids[$slug]] : ['q' => $search]];
        }

        return $links;
    }
}

<?php

namespace App\Enums;

enum PackagePreset: string
{
    case SmallPouch = 'small_pouch';
    case MediumBox = 'medium_box';
    case LargeBox = 'large_box';

    public function label(): string
    {
        return match ($this) {
            self::SmallPouch => 'Small pouch · 0.5 kg', self::MediumBox => 'Medium box · 2 kg', self::LargeBox => 'Large box · 5 kg'
        };
    }

    public function measurements(): array
    {
        return match ($this) {
            self::SmallPouch => ['length_cm' => 20, 'width_cm' => 15, 'height_cm' => 5, 'weight_kg' => 0.5],
            self::MediumBox => ['length_cm' => 30, 'width_cm' => 25, 'height_cm' => 20, 'weight_kg' => 2],
            self::LargeBox => ['length_cm' => 50, 'width_cm' => 40, 'height_cm' => 30, 'weight_kg' => 5],
        };
    }
}

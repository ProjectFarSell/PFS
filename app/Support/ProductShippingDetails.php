<?php

namespace App\Support;

use App\Enums\HandlingFlag;
use Illuminate\Validation\Rule;

class ProductShippingDetails
{
    public const MAX_DIMENSION_CM = 1000;

    public const MAX_WEIGHT_KG = 1000;

    public static function rules(): array
    {
        return [
            'length_cm' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_DIMENSION_CM],
            'width_cm' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_DIMENSION_CM],
            'height_cm' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_DIMENSION_CM],
            'weight_kg' => ['nullable', 'numeric', 'decimal:0,3', 'gt:0', 'max:'.self::MAX_WEIGHT_KG],
            'handling_flags' => ['nullable', 'array', 'list', 'max:'.count(HandlingFlag::cases())],
            'handling_flags.*' => ['required', 'string', Rule::enum(HandlingFlag::class), 'distinct:strict'],
        ];
    }

    /** Decimal strings in storage remain exact; JSON APIs expose nullable numbers. */
    public static function api(?array $details): ?array
    {
        if ($details === null) {
            return null;
        }
        $result = [];
        foreach (['length_cm', 'width_cm', 'height_cm', 'weight_kg'] as $key) {
            $result[$key] = isset($details[$key]) ? (float) $details[$key] : null;
        }
        $result['handling_flags'] = $details['handling_flags'] ?? null;

        return $result;
    }
}

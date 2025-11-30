<?php

namespace Zap\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Zap\Enums\Frequency;

class SafeFrequencyCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        $enum = Frequency::tryFrom($value);

        if ($enum) {
            return $enum;
        }

        // Return raw value if no matching enum (preserve backwards compatibility)
        return $value;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Frequency) {
            return $value->value;
        }

        return $value;
    }
}

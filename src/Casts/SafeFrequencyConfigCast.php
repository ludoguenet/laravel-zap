<?php

namespace Zap\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Zap\Data\FrequencyConfig;
use Zap\Models\Schedule;

class SafeFrequencyConfigCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        /** @var Schedule $schedule */
        $schedule = $model;
        $configArray = json_decode($value, true);

        $frequency = $schedule->frequency;

        if (! $frequency || $configArray === null) {
            return null;
        }

        if (is_string($frequency)) {
            return $configArray;
        }

        return $frequency->configClass()::fromArray($configArray);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        /** @var FrequencyConfig|array|null $config */
        $config = $value;

        if ($config === null) {
            return null;
        }

        if (is_array($config)) {
            return json_encode($config);
        }

        return json_encode($config->toArray());
    }
}

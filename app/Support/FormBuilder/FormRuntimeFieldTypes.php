<?php

namespace App\Support\FormBuilder;

class FormRuntimeFieldTypes
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return config('form-builder.supported_runtime_field_types', []);
    }

    /**
     * @return array<int, string>
     */
    public static function unsupported(): array
    {
        $all = collect(config('form-builder.field_type_groups', []))->flatten()->unique()->values()->all();

        return array_values(array_diff($all, self::supported()));
    }

    public static function isSupported(string $type): bool
    {
        return in_array($type, self::supported(), true);
    }
}

<?php

namespace App\Support;

class DrivingLicence
{
    public const FIELD = 'vaditaja_apliecibas_kategorija';

    public const CATEGORIES = ['AM', 'A1', 'A2', 'A', 'B1', 'B', 'BE', 'C', 'CE', 'D', 'DE', 'NONE', 'OTHER'];

    public static function messages(string $field = self::FIELD): array
    {
        return [
            "$field.required" => __('vehicle.licence_required'),
            "$field.in" => __('vehicle.licence_invalid'),
            "$field.string" => __('vehicle.licence_invalid'),
        ];
    }
}

<?php

namespace App\Enums;

enum ProductStatus :string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Discontinued = 'discontinued';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

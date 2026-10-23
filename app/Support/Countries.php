<?php

namespace App\Support;

class Countries
{
    /** @return list<string> */
    public static function all(): array
    {
        static $countries;

        return $countries ??= require resource_path('data/countries.php');
    }
}

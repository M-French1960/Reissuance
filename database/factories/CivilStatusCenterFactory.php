<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CivilStatusCenter;
use App\Models\Commune;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CivilStatusCenter> */
class CivilStatusCenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TESTC-'.Str::upper(Str::random(6)),
            'name' => 'Centre de test',
            'city' => 'Ville de test',
            'commune_id' => Commune::factory(),
            'is_active' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commune;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Commune> */
class CommuneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TEST-'.Str::upper(Str::random(6)),
            'name' => 'Commune de test',
            'region' => 'Centre',
            'is_active' => true,
        ];
    }
}

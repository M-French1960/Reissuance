<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CivilStatusCenter;
use App\Models\Commune;
use Illuminate\Database\Seeder;

/**
 * Referentiel geographique de demonstration.
 *
 * Le perimetre reel du deploiement reste a definir (docs/AUDIT_FRONTEND.md
 * 12.5). Ces valeurs sont un point de depart plausible, pas une reference
 * administrative verifiee.
 */
class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $communes = [
            ['code' => 'YDE-I', 'name' => 'Yaoundé I', 'region' => 'Centre'],
            ['code' => 'YDE-II', 'name' => 'Yaoundé II', 'region' => 'Centre'],
            ['code' => 'YDE-III', 'name' => 'Yaoundé III', 'region' => 'Centre'],
            ['code' => 'DLA-I', 'name' => 'Douala I', 'region' => 'Littoral'],
        ];

        foreach ($communes as $data) {
            $commune = Commune::updateOrCreate(['code' => $data['code']], $data);

            // Un centre par commune pour la demonstration. Le modele autorise
            // plusieurs centres par commune.
            CivilStatusCenter::updateOrCreate(
                ['code' => $data['code'].'-CEC'],
                [
                    'name' => "Centre d'état civil de {$data['name']}",
                    'city' => str_contains($data['code'], 'YDE') ? 'Yaoundé' : 'Douala',
                    'commune_id' => $commune->id,
                ]
            );
        }
    }
}

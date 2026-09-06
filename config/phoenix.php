<?php

declare(strict_types=1);

return [
    /*
     * Cle HMAC de l'index aveugle sur le numero de piece.
     * Distincte de APP_KEY : sa perte rend la recherche par numero impossible,
     * sans perte de donnees. Voir docs/DATA_MODEL.md 3.
     */
    'blind_index_key' => env('PHOENIX_BLIND_INDEX_KEY', ''),

    /*
     * Adaptateurs d'integration. « fake » est le defaut : aucune API reelle
     * n'est documentee a ce jour (docs/INTEGRATIONS.md).
     */
    'providers' => [
        'identity' => env('PHOENIX_IDENTITY_PROVIDER', 'fake'),
        'registry' => env('PHOENIX_REGISTRY_PROVIDER', 'fake'),
        'signature' => env('PHOENIX_SIGNATURE_PROVIDER', 'fake'),
    ],

    /*
     * Cible de compression cote navigateur avant envoi (D-008).
     */
    'uploads' => [
        'max_bytes' => 2 * 1024 * 1024,
        'target_bytes' => 250 * 1024,
        'max_dimension' => 1600,
        'accepted_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];

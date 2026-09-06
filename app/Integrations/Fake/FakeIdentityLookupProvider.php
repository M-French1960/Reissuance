<?php

declare(strict_types=1);

namespace App\Integrations\Fake;

use App\Contracts\IdentityLookupProvider;
use App\Support\BlindIndex;
use App\Support\ProviderOutcome;
use App\Support\ProviderResponse;

/**
 * Adaptateur factice de la base de la police.
 *
 * Deterministe : le meme numero produit toujours le meme resultat. C'est ce
 * qui permet aux seeders et aux tests de couvrir les cas degrades, et pas
 * seulement le cas heureux.
 *
 * Le declenchement se fait par prefixe du numero de piece, pour que les jeux
 * de demonstration puissent exercer chaque branche sans configuration.
 */
final class FakeIdentityLookupProvider implements IdentityLookupProvider
{
    public const PROVIDER = 'fake-police';

    /**
     * Prefixes de declenchement des cas de test.
     *
     * SANS TIRET, a dessein : le numero de piece est stocke apres passage par
     * BlindIndex::normalise(), qui supprime tout ce qui n'est ni lettre ni
     * chiffre. Un declencheur ecrit « DEMO-DOWN » ne correspondrait jamais a
     * la valeur relue, qui vaut « DEMODOWN ». Erreur commise puis rattrapee
     * par les tests.
     *
     * Documente dans docs/INTEGRATIONS.md 2.
     */
    public const TRIGGERS = [
        'DEMONOMATCH' => ProviderOutcome::NoMatch,
        'DEMOSTOLEN' => ProviderOutcome::NoMatch,
        'DEMODOUBT' => ProviderOutcome::Inconclusive,
        'DEMODOWN' => ProviderOutcome::Unavailable,
        'DEMOTIMEOUT' => ProviderOutcome::Unavailable,
    ];

    public function verify(string $documentNumber, string $claimedName): ProviderResponse
    {
        // Meme normalisation que celle appliquee au stockage : sans elle, le
        // declencheur depend de la ponctuation saisie par le citoyen.
        $normalised = BlindIndex::normalise($documentNumber);

        foreach (self::TRIGGERS as $prefix => $outcome) {
            if (str_starts_with($normalised, $prefix)) {
                return $this->respond($outcome, $prefix, $documentNumber, $claimedName);
            }
        }

        // Cas nominal : correspondance exacte.
        return ProviderResponse::make(
            ProviderOutcome::Match,
            self::PROVIDER,
            [
                'name_on_document' => $claimedName,
                'document_status' => 'valid',
                'name_similarity' => 1.0,
            ],
            'La pièce est connue de la base et le nom correspond.',
        );
    }

    private function respond(
        ProviderOutcome $outcome,
        string $prefix,
        string $documentNumber,
        string $claimedName,
    ): ProviderResponse {
        return match ($prefix) {
            'DEMONOMATCH' => ProviderResponse::make(
                $outcome, self::PROVIDER,
                ['name_on_document' => 'Nom DIFFÉRENT', 'document_status' => 'valid', 'name_similarity' => 0.2],
                'La pièce existe, mais le nom enregistré diffère du nom déclaré.',
            ),
            'DEMOSTOLEN' => ProviderResponse::make(
                $outcome, self::PROVIDER,
                ['name_on_document' => $claimedName, 'document_status' => 'reported_stolen'],
                'Cette pièce est signalée comme volée ou perdue.',
            ),
            'DEMODOUBT' => ProviderResponse::make(
                $outcome, self::PROVIDER,
                ['name_on_document' => $claimedName, 'document_status' => 'valid', 'name_similarity' => 0.72],
                'Le nom est proche sans être identique. Un contrôle humain est nécessaire.',
            ),
            'DEMOTIMEOUT' => ProviderResponse::make(
                $outcome, self::PROVIDER, [],
                "Le service n'a pas répondu dans le délai imparti.",
            ),
            default => ProviderResponse::make(
                $outcome, self::PROVIDER, [],
                'Le service de vérification est momentanément indisponible.',
            ),
        };
    }
}

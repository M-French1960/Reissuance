<?php

declare(strict_types=1);

namespace App\Integrations\Fake;

use App\Contracts\FacialRecognitionProvider;
use App\Models\RequestAttachment;
use App\Support\BlindIndex;
use App\Support\ProviderOutcome;
use App\Support\ProviderResponse;

/**
 * Adaptateur factice de comparaison faciale.
 *
 * IL NE REGARDE AUCUNE IMAGE. Il lit le numero de piece du demandeur et rend
 * un resultat determine par son prefixe, pour que les jeux de demonstration et
 * les tests couvrent les cas degrades — et surtout le faux negatif, qui est le
 * cas le plus important a savoir traiter.
 *
 * Prefixes SANS PONCTUATION : le numero est relu apres normalisation. Un
 * declencheur ecrit « DEMO-VISAGE » ne correspondrait jamais (D-021).
 */
final class FakeFacialRecognitionProvider implements FacialRecognitionProvider
{
    public const PROVIDER = 'fake-facial-recognition';

    /** @var array<string, array{ProviderOutcome, float}> */
    public const TRIGGERS = [
        'DEMOVISAGEKO' => [ProviderOutcome::NoMatch, 0.21],
        'DEMOJUMEAU' => [ProviderOutcome::Inconclusive, 0.58],
        'DEMOVISAGEFLOU' => [ProviderOutcome::Inconclusive, 0.44],
        'DEMOVISAGEPANNE' => [ProviderOutcome::Unavailable, 0.0],
    ];

    public function compare(RequestAttachment $selfie, RequestAttachment $idDocument): ProviderResponse
    {
        $numero = BlindIndex::normalise(
            (string) ($selfie->request?->citizen?->profile?->national_id_number ?? '')
        );

        foreach (self::TRIGGERS as $prefixe => [$issue, $score]) {
            if ($numero !== '' && str_starts_with($numero, $prefixe)) {
                return $this->repondre($issue, $score, $prefixe);
            }
        }

        return $this->repondre(ProviderOutcome::Match, 0.94, null);
    }

    private function repondre(ProviderOutcome $issue, float $score, ?string $prefixe): ProviderResponse
    {
        $message = match ($issue) {
            ProviderOutcome::Match => 'Les deux visages correspondent avec une confiance élevée.',
            ProviderOutcome::NoMatch => 'Les deux visages ne correspondent pas.',
            ProviderOutcome::Inconclusive => 'Comparaison non concluante : examinez vous-même les photographies.',
            ProviderOutcome::Unavailable => 'Le service de comparaison faciale est injoignable.',
        };

        return ProviderResponse::make(
            $issue,
            self::PROVIDER,
            [
                // Le score et l'avis, jamais un gabarit biometrique.
                'similarity' => $score,
                'simulated' => true,
                'trigger' => $prefixe,
            ],
            $message,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Support\Pdf\PdfDocument;

/**
 * Composition de l'acte réédité.
 *
 * La mention « SANS VALEUR JURIDIQUE » est apposée ICI, et non par
 * l'adaptateur de signature : un document doit la porter même si quelqu'un
 * contourne l'adaptateur. Elle ne disparaîtra que le jour où un prestataire
 * agréé sera branché ET la question A1 tranchée
 * (docs/COMPLIANCE_OPEN_QUESTIONS.md).
 */
final class DocumentBuilder
{
    public const DEMO_NOTICE = 'DOCUMENT DE DEMONSTRATION - SANS VALEUR JURIDIQUE';

    public function build(ReissuanceRequest $request, User $mayor, bool $legallyBinding): string
    {
        $pdf = new PdfDocument;

        // Le bandeau est la PREMIÈRE chose écrite, en haut de la première
        // page : impossible de le manquer, impossible de le recadrer.
        if (! $legallyBinding) {
            $pdf->text(self::DEMO_NOTICE, 13, true)
                ->text('Ce document ne peut etre presente a aucune administration.', 9)
                ->rule()
                ->spacer(6);
        }

        $pdf->text('REPUBLIQUE DU CAMEROUN', 12, true)
            ->text('Paix - Travail - Patrie', 9)
            ->spacer(10)
            ->text("EXTRAIT D'ACTE DE NAISSANCE", 17, true)
            ->text('Copie rééditée', 10)
            ->spacer(8)
            ->rule();

        $profile = $request->citizen->profile;

        $pdf->spacer(6)
            ->text("Titulaire de l'acte", 12, true)
            ->spacer(4)
            ->labelled('Nom à la naissance', (string) $request->full_name_at_birth)
            ->labelled('Né(e) le', $request->date_of_birth?->translatedFormat('d F Y') ?? '—')
            ->labelled('Lieu de naissance', (string) $request->place_of_birth)
            ->labelled("Année d'enregistrement", (string) $request->registration_year);

        if ($request->original_certificate_number) {
            $pdf->labelled("Numéro d'acte d'origine", $request->original_certificate_number);
        }

        $pdf->spacer(10)
            ->text('Filiation', 12, true)
            ->spacer(4)
            ->labelled('Père', $request->father_name.' ('.$request->father_nationality.')')
            ->labelled('Mère', $request->mother_name.' ('.$request->mother_nationality.')')
            ->labelled('Adresse des parents', (string) $request->parents_address);

        $pdf->spacer(10)
            ->text('Délivrance', 12, true)
            ->spacer(4)
            ->labelled('Centre d\'état civil', $request->center?->name ?? '—')
            ->labelled('Commune', $request->commune?->name ?? '—')
            ->labelled('Référence de la demande', $request->reference)
            ->labelled('Exemplaires demandés', (string) $request->copies_requested)
            ->labelled('Délivré le', now()->translatedFormat('d F Y'));

        $pdf->spacer(12)
            ->rule()
            ->spacer(4)
            ->text('Signé par', 12, true)
            ->spacer(4)
            ->labelled('Autorité signataire', $mayor->name)
            ->labelled('Qualité', 'Maire de '.($request->commune?->name ?? '—'));

        if (! $legallyBinding) {
            $pdf->spacer(14)
                ->rule()
                ->spacer(4)
                ->text(self::DEMO_NOTICE, 11, true)
                ->paragraph(
                    'Ce document a ete produit par un adaptateur de signature de demonstration. '
                    ."Il ne resulte d'aucune signature electronique agreee. La valeur legale d'un "
                    ."acte d'etat civil signe electroniquement au Cameroun, ainsi que les exigences "
                    ."d'agrement du prestataire de signature, restent a confirmer : voir le bloc A "
                    .'de docs/COMPLIANCE_OPEN_QUESTIONS.md.',
                    9
                );
        }

        unset($profile);

        return $pdf->render();
    }

    /**
     * Ajoute la preuve de signature en dernière page.
     *
     * Séparée de build() : l'empreinte porte sur le document tel qu'il a été
     * signé, donc la preuve ne peut pas en faire partie.
     *
     * @param  array<string, mixed>  $proof
     */
    public function appendProof(string $document, array $proof, string $hash): string
    {
        // Le PDF signé n'est pas modifié : la preuve est un second document,
        // servi à part. Modifier le document invaliderait son empreinte.
        $pdf = new PdfDocument;

        $pdf->text('PREUVE DE SIGNATURE', 15, true)->spacer(6)->rule()->spacer(6)
            ->labelled('Empreinte du document (SHA-256)', '')
            ->paragraph($hash, 9)
            ->spacer(6);

        foreach ([
            'Prestataire' => $proof['provider'] ?? '—',
            'Algorithme' => $proof['algorithm'] ?? '—',
            'Référence de signature' => $proof['signature_reference'] ?? '—',
            'Signé le' => $proof['signed_at'] ?? '—',
            'Signataire' => $proof['signatory'] ?? '—',
            'Commune' => $proof['commune'] ?? '—',
            'Valeur juridique' => ($proof['legally_binding'] ?? false) ? 'Oui' : 'NON - demonstration',
        ] as $label => $value) {
            $pdf->labelled($label, (string) $value, 10);
        }

        if (isset($proof['seal'])) {
            $pdf->spacer(6)->text('Sceau', 11, true)->paragraph((string) $proof['seal'], 8);
        }

        if (! ($proof['legally_binding'] ?? false)) {
            $pdf->spacer(10)->rule()->spacer(4)
                ->text(self::DEMO_NOTICE, 11, true);
        }

        unset($document);

        return $pdf->render();
    }
}

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

    /** Bandeau du projet : un projet ne doit jamais passer pour un acte. */
    public const DRAFT_NOTICE = 'PROJET D\'ACTE - NON SIGNE - SANS VALEUR';

    /**
     * Empreinte du CONTENU de l'acte, independamment de sa mise en page.
     *
     * POURQUOI ELLE EXISTE. Depuis D-064, c'est l'officier qui redige le
     * projet d'acte et le maire qui le signe. Deplacer la redaction en amont
     * de la decision ouvre une faille : l'officier pourrait modifier le
     * dossier APRES que le maire a lu le projet, et le maire signerait autre
     * chose que ce qu'il a vu.
     *
     * Cette empreinte est relevee a la redaction, puis recalculee au moment de
     * signer. Si elle a bouge, la signature est refusee.
     *
     * POURQUOI PAS L'EMPREINTE DU PDF. Le projet porte un bandeau « PROJET »
     * que l'acte final n'a pas : les deux fichiers different forcement, et
     * comparer leurs octets ne dirait rien. C'est le contenu qui doit etre
     * stable, pas la mise en page.
     *
     * Les champs listes ici sont exactement ceux que `build()` imprime. En
     * ajouter un a l'acte sans l'ajouter ici rendrait ce champ modifiable
     * apres lecture du maire — `DocumentBuilderTest` verifie que les deux
     * listes coincident.
     */
    public static function contentFingerprint(ReissuanceRequest $request): string
    {
        $champs = [
            'full_name_at_birth' => $request->full_name_at_birth,
            'date_of_birth' => $request->date_of_birth?->toDateString(),
            'place_of_birth' => $request->place_of_birth,
            'registration_year' => $request->registration_year,
            'original_certificate_number' => $request->original_certificate_number,
            'father_name' => $request->father_name,
            'father_nationality' => $request->father_nationality,
            'mother_name' => $request->mother_name,
            'mother_nationality' => $request->mother_nationality,
            'parents_address' => $request->parents_address,
            'center' => $request->center?->name,
            'commune' => $request->commune?->name,
            'reference' => $request->reference,
            'copies_requested' => $request->copies_requested,
        ];

        return hash('sha256', json_encode($champs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** Les champs couverts par l'empreinte — lus par le test de coherence. */
    public const FINGERPRINTED_FIELDS = [
        'full_name_at_birth', 'date_of_birth', 'place_of_birth', 'registration_year',
        'original_certificate_number', 'father_name', 'father_nationality',
        'mother_name', 'mother_nationality', 'parents_address',
        'center', 'commune', 'reference', 'copies_requested',
    ];

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

        $this->body($pdf, $request);

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

        return $pdf->render();
    }

    /**
     * Le PROJET d'acte, redige par l'officier (D-064).
     *
     * Meme contenu que l'acte, et deux differences qui comptent :
     *
     *   - un bandeau « PROJET » en tete, avant tout le reste, que l'acte final
     *     n'a pas ;
     *   - aucun bloc « Signe par » — un projet n'a pas de signataire. A sa
     *     place, le nom de l'officier qui l'a redige, parce que la
     *     responsabilite du contenu est desormais la sienne.
     *
     * C'est ce qui permet a la lecture 2 de respecter le 4.3 du brief :
     * l'officier redige, mais aucun document ayant valeur d'acte n'existe
     * avant la decision du maire.
     */
    public function buildDraft(ReissuanceRequest $request, User $officer): string
    {
        $pdf = new PdfDocument;

        $pdf->text(self::DRAFT_NOTICE, 13, true)
            ->text('Ce document n’est pas un acte. Il attend la décision du maire.', 9)
            ->rule()
            ->spacer(6);

        $pdf->text('REPUBLIQUE DU CAMEROUN', 12, true)
            ->text('Paix - Travail - Patrie', 9)
            ->spacer(10)
            ->text("PROJET D'EXTRAIT D'ACTE DE NAISSANCE", 16, true)
            ->text('Copie rééditée — projet soumis à la signature du maire', 10)
            ->spacer(8)
            ->rule();

        $this->body($pdf, $request);

        $pdf->spacer(12)
            ->rule()
            ->spacer(4)
            ->text('Rédigé par', 12, true)
            ->spacer(4)
            ->labelled('Officier d’état civil', $officer->name)
            ->labelled('Centre', $request->center?->name ?? '—')
            ->labelled('Rédigé le', now()->translatedFormat('d F Y'))
            ->spacer(10)
            ->rule()
            ->spacer(4)
            ->text(self::DRAFT_NOTICE, 11, true)
            ->paragraph(
                "Aucune signature n'a ete apposee. Ce projet n'a aucune valeur et ne peut etre "
                ."presente a aucune administration. Seule la decision du maire fait naitre l'acte.",
                9
            );

        return $pdf->render();
    }

    /** Le corps de l'acte : identique au projet et a l'acte signe. */
    private function body(PdfDocument $pdf, ReissuanceRequest $request): void
    {
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

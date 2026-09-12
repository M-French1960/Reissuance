<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Support\Pdf\HtmlToPdf;

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
    public function __construct(private readonly HtmlToPdf $pdf) {}

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
        return $this->pdf->render('documents.act', [
            'demande' => $request,
            'signataire' => $mayor->name,
            'valeurJuridique' => $legallyBinding,
            'mentionDemo' => self::DEMO_NOTICE,
            'delivreLe' => now()->translatedFormat('d F Y'),
        ]);
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
        return $this->pdf->render('documents.draft', [
            'demande' => $request,
            'redacteur' => $officer->name,
            'mentionProjet' => self::DRAFT_NOTICE,
            'delivreLe' => now()->translatedFormat('d F Y'),
        ]);
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
        unset($document);

        $valeurJuridique = (bool) ($proof['legally_binding'] ?? false);

        return $this->pdf->render('documents.proof', [
            'empreinte' => $hash,
            'valeurJuridique' => $valeurJuridique,
            'mentionDemo' => self::DEMO_NOTICE,
            'sceau' => isset($proof['seal']) ? (string) $proof['seal'] : null,
            'elements' => [
                'Prestataire' => (string) ($proof['provider'] ?? '—'),
                'Algorithme' => (string) ($proof['algorithm'] ?? '—'),
                'Référence de signature' => (string) ($proof['signature_reference'] ?? '—'),
                'Signé le' => (string) ($proof['signed_at'] ?? '—'),
                'Signataire' => (string) ($proof['signatory'] ?? '—'),
                'Commune' => (string) ($proof['commune'] ?? '—'),
                'Valeur juridique' => $valeurJuridique ? 'Oui' : 'NON - demonstration',
            ],
        ]);
    }
}

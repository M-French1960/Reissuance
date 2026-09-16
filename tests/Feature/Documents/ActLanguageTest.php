<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\DocumentBuilder;
use App\Support\ActLanguage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsPdfText;
use Tests\TestCase;

/**
 * The language a certificate is issued in (D-076).
 *
 * WHAT WE COULD ESTABLISH. English and French are official languages of equal
 * standing in Cameroon (Constitution of 18 January 1996), and the Act of
 * 24 December 2019 requires both to be usable in decentralised local
 * authorities, which is what a commune is.
 *
 * WHAT WE COULD NOT, and so did not code: whether an individual certificate is
 * drawn up in one language, bilingually, or at the applicant's choice. That is
 * a deployment setting, and question A6 of COMPLIANCE_OPEN_QUESTIONS.md.
 *
 * WHAT THESE TESTS DO GUARD, whatever the administration decides: the language
 * is frozen on the request, so a signed certificate never changes language
 * afterwards. The signature binds a content fingerprint to the exact text, and
 * a certificate that read French for one person and English for another would
 * no longer match what the mayor signed.
 */
class ActLanguageTest extends TestCase
{
    use ReadsPdfText;

    private function demande(?string $langue): ReissuanceRequest
    {
        $centre = CivilStatusCenter::factory()->create();
        $citoyen = User::factory()->citizen()->create();

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'act_language' => $langue,
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST',
            'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST',
            'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);

        /*
         * Pas de saut d'etat ici. Le declencheur de la machine a etats refuse
         * draft -> awaiting_signature, et il a raison : on ne contourne pas un
         * controle pour faire passer un test (§13). La composition du document
         * ne depend pas du statut, seulement de la langue figee au dossier.
         */
        $demande->forceFill(['submitted_at' => now()])->save();

        return $demande->refresh();
    }

    private function acte(ReissuanceRequest $demande): string
    {
        $maire = User::factory()->mayor($demande->center->commune)->create();

        return $this->extractText(app(DocumentBuilder::class)->build($demande, $maire, false));
    }

    /** A certificate frozen in French prints in French, whoever downloads it. */
    #[Test]
    public function un_acte_fige_en_francais_s_imprime_en_francais(): void
    {
        app()->setLocale('en');

        $texte = $this->acte($this->demande('fr'));

        $this->assertStringContainsString("EXTRAIT D'ACTE DE NAISSANCE", $texte);
        $this->assertStringNotContainsString('BIRTH CERTIFICATE EXTRACT', $texte);
    }

    /** And one frozen in English prints in English, read from a French session. */
    #[Test]
    public function un_acte_fige_en_anglais_s_imprime_en_anglais(): void
    {
        app()->setLocale('fr');

        $texte = $this->acte($this->demande('en'));

        $this->assertStringContainsString('BIRTH CERTIFICATE EXTRACT', $texte);
        $this->assertStringNotContainsString("EXTRAIT D'ACTE DE NAISSANCE", $texte);
    }

    /** Rendering a document leaves the surrounding request in its own language. */
    #[Test]
    public function rendre_un_document_ne_change_pas_la_langue_de_la_page(): void
    {
        app()->setLocale('fr');

        $this->acte($this->demande('en'));

        $this->assertSame('fr', app()->getLocale());
    }

    /**
     * A request submitted before the column existed falls back to the default
     * rather than pretending to a choice nobody made.
     */
    #[Test]
    public function une_demande_sans_langue_retombe_sur_la_langue_par_defaut(): void
    {
        $this->assertSame('en', ActLanguage::forRequest($this->demande(null)));
    }

    /** The setting decides what a new request freezes. */
    #[Test]
    public function le_reglage_decide_de_la_langue_figee_au_depot(): void
    {
        app()->setLocale('fr');

        config(['phoenix.documents.language' => 'requester']);
        $this->assertSame('fr', ActLanguage::forNewRequest());

        config(['phoenix.documents.language' => 'en']);
        $this->assertSame('en', ActLanguage::forNewRequest());

        // Un code inconnu ne laisse pas un acte sans langue.
        config(['phoenix.documents.language' => 'de']);
        $this->assertSame('en', ActLanguage::forNewRequest());
    }
}

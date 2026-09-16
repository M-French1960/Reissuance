<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le message qui suit une action porte la couleur de ce qui s'est passe (D-074).
 *
 * LE DEFAUT QUE CECI GARDE. Tous les messages transitaient par une seule cle
 * de session, rendue en VERT dans les quinze vues qui l'affichaient. Le vert
 * est la couleur de « c'est fait ». Or la meme cle portait des refus :
 *
 *  - « Terminez cette étape avant de passer à la suivante. » — le citoyen
 *    venait d'etre renvoye en arriere, et l'ecran le felicitait ;
 *  - « Aucune correspondance » — le resultat d'un controle d'identite
 *    INFRUCTUEUX, c'est-a-dire la trace exacte d'une piece volee, annonce a
 *    l'officier dans la couleur du succes, au moment ou il doit se mefier.
 *
 * Aucun test ne pouvait le voir : le message etait bien present, la page
 * rendait 200. Seule la couleur mentait.
 */
class FlashToneTest extends TestCase
{
    /** Un renvoi en arriere dans l'assistant n'est pas un succes. */
    #[Test]
    public function un_renvoi_en_arriere_dans_l_assistant_n_est_pas_vert(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-700000001', 'completed_at' => now(),
        ]);

        $brouillon = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'reason' => 'lost',
        ]);

        // L'etape 3 est hors d'atteinte : rien n'est complete.
        $contenu = (string) $this->actingAs($citoyen)
            ->followingRedirects()
            ->get(route('citizen.requests.step', ['reissuanceRequest' => $brouillon, 'step' => 3]))
            ->assertOk()
            ->assertSee('Finish this step before moving on to the next one.')
            ->getContent();

        $this->assertStringNotContainsString(
            'alert--success',
            $contenu,
            "Le citoyen vient d'être refusé : l'écran ne doit pas le féliciter."
        );
        $this->assertStringContainsString('alert--attention', $contenu);
    }

    /**
     * ET SURTOUT : un controle d'identite infructueux s'annonce en rouge.
     *
     * Le prefixe DEMOSTOLEN declenche « Aucune correspondance » dans
     * l'adaptateur factice (docs/INTEGRATIONS.md 2) : c'est le scenario de la
     * piece volee.
     */
    #[Test]
    public function un_controle_d_identite_infructueux_s_annonce_en_rouge(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($centre)->create();

        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'PIECE-VOLEE',
            'national_id_number' => 'DEMOSTOLEN-0001', 'completed_at' => now(),
        ]);

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne PIECE-VOLEE',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
        ]);
        $demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $demande->forceFill(['assigned_officer_id' => $officier->id])->save();

        // `back()` sans referent retombe sur l'accueil : on dit d'ou l'on vient.
        $etape = route('officer.verification.step', [
            'reissuanceRequest' => $demande, 'step' => 2,
        ]);

        $contenu = (string) $this->actingAs($officier)
            ->from($etape)
            ->followingRedirects()
            ->post(route('officer.verification.identity', $demande))
            ->assertOk()
            ->assertSee('No match')
            ->getContent();

        $this->assertStringContainsString(
            'alert--danger',
            $contenu,
            "Une identité qui ne correspond pas ne s'annonce pas dans la couleur du succès."
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chaque notification doit se RENDRE, pour chaque etat possible.
 *
 * POURQUOI CE FICHIER EXISTE. `RequestStatusChanged` construit son titre et
 * son corps avec un `match` sur l'etat d'arrivee. L'etat `cancelled`, ajoute en
 * D-044, n'y avait pas d'entree : toute annulation levait une
 * `UnhandledMatchError` DANS LE WORKER. La transition passait, la demande
 * etait bien annulee, et le citoyen n'etait jamais prevenu — la tache echouait
 * en silence, hors de la requete HTTP.
 *
 * Aucun test ne l'a vu parce que tous utilisent `Notification::fake()`, qui
 * intercepte l'envoi AVANT le rendu. Ils prouvaient qu'une notification part,
 * jamais qu'elle se fabrique.
 *
 * Ces tests parcourent l'enumeration entiere : un etat ajoute demain sans
 * entree dans le `match` les fera echouer, ici, et non chez un usager.
 */
class NotificationRenderingTest extends TestCase
{
    /** @return iterable<string, array{RequestStatus}> */
    public static function tousLesEtats(): iterable
    {
        foreach (RequestStatus::cases() as $etat) {
            yield $etat->value => [$etat];
        }
    }

    #[Test]
    #[DataProvider('tousLesEtats')]
    public function le_courriel_se_fabrique_pour_chaque_etat(RequestStatus $vers): void
    {
        $destinataire = User::factory()->citizen()->create();

        $courriel = (new RequestStatusChanged('PHX-TEST-0001', 1, RequestStatus::Pending, $vers))
            ->toMail($destinataire);

        $this->assertNotSame('', trim((string) $courriel->subject), "Sujet vide pour {$vers->value}.");
        $this->assertNotEmpty($courriel->introLines, "Corps vide pour {$vers->value}.");
    }

    #[Test]
    #[DataProvider('tousLesEtats')]
    public function la_notification_en_base_se_fabrique_pour_chaque_etat(RequestStatus $vers): void
    {
        $destinataire = User::factory()->citizen()->create();

        $charge = (new RequestStatusChanged('PHX-TEST-0001', 1, RequestStatus::Pending, $vers))
            ->toArray($destinataire);

        $this->assertNotSame('', trim((string) $charge['title']), "Titre vide pour {$vers->value}.");
        $this->assertNotSame('', trim((string) $charge['body']), "Corps vide pour {$vers->value}.");
        $this->assertSame($vers->value, $charge['to']);
    }

    /**
     * Le retour du maire a son propre libelle : deux chemins arrivent au meme
     * etat, et ils ne disent pas la meme chose au demandeur.
     */
    #[Test]
    public function le_retour_du_maire_ne_se_lit_pas_comme_une_prise_en_charge(): void
    {
        $destinataire = User::factory()->citizen()->create();

        $priseEnCharge = (new RequestStatusChanged('PHX-TEST-0001', 1, RequestStatus::Pending, RequestStatus::UnderReview))
            ->toArray($destinataire);

        $retour = (new RequestStatusChanged('PHX-TEST-0001', 1, RequestStatus::AwaitingSignature, RequestStatus::UnderReview))
            ->toArray($destinataire);

        $this->assertNotSame($priseEnCharge['title'], $retour['title']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\User;
use App\Notifications\ComplementProvided;
use App\Notifications\ComplementRequested;
use App\Notifications\RequestAwaitsMayor;
use App\Notifications\RequestStatusChanged;
use Illuminate\Notifications\Notification;
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

    /**
     * CE FICHIER AVAIT UN TROU, ET IL A DURE (D-093).
     *
     * Ce fichier existe parce qu'une notification qui ne se rend pas echoue
     * DANS LE WORKER, en silence. Il ne couvrait pourtant qu'une seule des
     * quatre notifications : `RequestAwaitsMayor` (D-089) et les deux
     * notifications de complement (D-087) sont arrivees apres lui, et personne
     * — moi — n'a pense a les y ajouter. La garde existait et n'etait pas
     * nourrie, ce qui est pire qu'une garde absente : elle rassure.
     *
     * Cette methode ferme le trou par construction. Elle lit le repertoire
     * `app/Notifications` et refuse toute classe qui n'est pas declaree dans
     * le fournisseur ci-dessous. Ajouter une notification demain sans la
     * rendre ici fera echouer CE test, avec le nom de la classe oubliee.
     */
    #[Test]
    public function aucune_notification_n_echappe_au_rendu(): void
    {
        $surDisque = [];
        foreach (glob(app_path('Notifications/*.php')) ?: [] as $fichier) {
            $surDisque[] = basename($fichier, '.php');
        }
        sort($surDisque);

        $couvertes = [];
        foreach (self::toutesLesNotifications() as $cas) {
            $couvertes[] = class_basename($cas[0]);
        }
        $couvertes = array_values(array_unique($couvertes));
        sort($couvertes);

        $this->assertSame(
            $surDisque,
            $couvertes,
            "Une notification de app/Notifications n'est rendue par aucun cas de "
            .'toutesLesNotifications(). Une notification qui ne se rend pas echoue '
            .'dans le worker, sans requete HTTP pour le montrer.'
        );
    }

    /**
     * Chaque variante qui change le texte a son cas : le `match` interne est
     * precisement l'endroit ou une entree manque.
     *
     * @return iterable<string, array{class-string<Notification>, callable(): Notification, string}>
     */
    public static function toutesLesNotifications(): iterable
    {
        yield 'changement d etat' => [
            RequestStatusChanged::class,
            fn () => new RequestStatusChanged('PHX-TEST-0001', 1, RequestStatus::Pending, RequestStatus::Signed),
            'citizen',
        ];

        yield 'le maire doit signer' => [
            RequestAwaitsMayor::class,
            fn () => new RequestAwaitsMayor('PHX-TEST-0001', 1, false),
            'mayor',
        ];

        yield 'le maire recoit une escalade' => [
            RequestAwaitsMayor::class,
            fn () => new RequestAwaitsMayor('PHX-TEST-0001', 1, true),
            'mayor',
        ];

        foreach (['id_document', 'selfie'] as $nature) {
            yield "complement demande ({$nature})" => [
                ComplementRequested::class,
                fn () => new ComplementRequested('PHX-TEST-0001', 1, $nature),
                'citizen',
            ];

            yield "complement fourni ({$nature})" => [
                ComplementProvided::class,
                fn () => new ComplementProvided('PHX-TEST-0001', 1, $nature),
                'officer',
            ];
        }
    }

    /**
     * Les deux langues, parce qu'une cle absente d'un seul fichier de langue
     * se rend comme sa propre cle — « notifications.bodies.mayor_awaiting » —
     * et part ainsi chez l'usager sans que rien ne leve.
     *
     * @param  callable(): Notification  $fabrique
     */
    #[Test]
    #[DataProvider('toutesLesNotifications')]
    public function chaque_notification_se_fabrique_dans_les_deux_langues(
        string $classe,
        callable $fabrique,
        string $role,
    ): void {
        foreach (['en', 'fr'] as $langue) {
            $destinataire = $this->destinataire($role, $langue);
            $notification = $fabrique();

            $courriel = $notification->toMail($destinataire);
            $charge = $notification->toArray($destinataire);

            $sujet = trim((string) $courriel->subject);
            $this->assertNotSame('', $sujet, "{$classe} [{$langue}] : sujet vide.");
            $this->assertNotEmpty($courriel->introLines, "{$classe} [{$langue}] : corps vide.");

            foreach (['title', 'body'] as $champ) {
                $valeur = trim((string) $charge[$champ]);
                $this->assertNotSame('', $valeur, "{$classe} [{$langue}] : {$champ} vide.");
                $this->assertStringNotContainsString(
                    'notifications.',
                    $valeur,
                    "{$classe} [{$langue}] : la cle de traduction est servie telle quelle dans {$champ}."
                );
            }

            $this->assertStringNotContainsString('notifications.', $sujet, "{$classe} [{$langue}] : cle brute dans le sujet.");
        }
    }

    /**
     * LE COURRIEL NE PORTE AUCUNE DONNEE D IDENTITE (garde-fou n6).
     *
     * Une notification part en clair, vers une boite dont nous ne maitrisons
     * rien. Elle ne porte donc qu'une reference et un etat ; le contenu du
     * dossier se lit dans l'application. Ce test fige la regle plutot que de
     * la confier a la relecture.
     *
     * @param  callable(): Notification  $fabrique
     */
    #[Test]
    #[DataProvider('toutesLesNotifications')]
    public function aucun_courriel_ne_transporte_de_donnee_personnelle(
        string $classe,
        callable $fabrique,
        string $role,
    ): void {
        $destinataire = $this->destinataire($role, 'en');
        $destinataire->name = 'Personne DE TEST';

        $courriel = $fabrique()->toMail($destinataire);

        $texte = $courriel->subject.' '.implode(' ', $courriel->introLines).' '.implode(' ', $courriel->outroLines);

        $this->assertStringNotContainsString('Personne DE TEST', $texte, "{$classe} : le courriel nomme une personne.");
        $this->assertStringNotContainsString('@', $texte, "{$classe} : le courriel porte une adresse.");
    }

    private function destinataire(string $role, string $langue): User
    {
        $centre = CivilStatusCenter::factory()->create();

        $utilisateur = match ($role) {
            'mayor' => User::factory()->mayor($centre->commune),
            'officer' => User::factory()->officer($centre),
            default => User::factory()->citizen(),
        };

        return $utilisateur->create(['locale' => $langue]);
    }

    /**
     * L'ETAT VIDE NE PROMET PAS CE QU'IL NE TIENDRA PAS (D-074).
     *
     * « Vous serez prévenu ici à chaque étape de VOS DEMANDES » etait servi
     * a l'officier, au maire et a l'administrateur — qui n'ont pas de
     * demandes. Il leur promettait en outre des messages qu'ils ne recevront
     * pas : seul le citoyen est notifie a chaque etape, et l'officier
     * uniquement lorsque le maire lui RETOURNE un dossier.
     */
    #[Test]
    public function l_etat_vide_des_notifications_parle_au_role_qui_le_lit(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        $citoyen = User::factory()->citizen()->create();
        $this->actingAs($citoyen)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('at every step of your requests');

        $officier = User::factory()->officer($centre)->create();
        $this->actingAs($officier)->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('your requests')
            ->assertSee('the mayor returns a file to you');

        // D-093 : depuis D-089 le maire EST prevenu. Se contenter de verifier
        // qu'on ne lui promet pas « vos demandes » laissait passer l'inverse :
        // « rien ne vous a encore ete signale », qui lui cache une promesse
        // que le systeme tient desormais.
        $maire = User::factory()->mayor($centre->commune)->create();
        $this->actingAs($maire)->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('your requests')
            ->assertDontSee('Nothing has been reported to you yet')
            ->assertSee('awaits your signature');
    }
}

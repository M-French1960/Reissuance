<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CitizenProfile;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La coquille de l'espace connecte (D-079).
 *
 * CE QUE CES TESTS FERMENT. Le passage a la barre laterale touche vingt-neuf
 * ecrans d'un coup, et la suite est restee verte pendant que la mise en page
 * etait cassee : un troisieme enfant dans la grille — le voile du tiroir —
 * prenait la deuxieme colonne, et TOUT le contenu basculait en rangee deux,
 * dans un ruban de 264 pixels sur un ecran de 1366. Le HTML etait valide, les
 * routes justes, chaque page rendait 200.
 *
 * Aucun test PHP ne peut mesurer une grille : celui-ci verifie donc le
 * CONTRAT dont la mise en page depend, et le fait de maniere a tomber si
 * quelqu'un le rompt.
 */
class ShellTest extends TestCase
{
    /**
     * CHAQUE DESTINATION DU MENU EXISTE, ET LE ROLE PEUT L'ATTEINDRE.
     *
     * La maquette proposait « Paiements », « Parametres » et « Contacter le
     * support » : trois pages jamais ecrites. Ce test suit reellement chaque
     * lien du menu de chaque role et refuse tout ce qui n'arrive pas.
     */
    #[Test]
    public function chaque_lien_du_menu_mene_quelque_part_pour_son_role(): void
    {
        foreach ($this->comptes() as $etiquette => $utilisateur) {
            $html = (string) $this->actingAs($utilisateur)
                ->get(route('dashboard'))
                ->assertOk()
                ->getContent();

            preg_match('/<ul class="shell__nav">(.*?)<\/ul>/s', $html, $menu);
            $this->assertNotEmpty($menu, "Aucun menu lateral pour {$etiquette}.");

            preg_match_all('/href="([^"]+)"/', $menu[1], $liens);
            $this->assertNotEmpty($liens[1], "Menu vide pour {$etiquette}.");

            foreach (array_unique($liens[1]) as $lien) {
                $this->actingAs($utilisateur)
                    ->get($lien)
                    ->assertOk(); // Un 403 ou un 404 ferait de ce lien une impasse.
            }
        }
    }

    /** Un visiteur anonyme n'a pas de menu de travail : il garde la barre du haut. */
    #[Test]
    public function un_visiteur_anonyme_n_a_pas_de_barre_laterale(): void
    {
        foreach ([route('login'), route('register'), route('health'), route('home')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('data-shell-side', $html,
                "La coquille connectee apparait sur {$url}, qui est publique.");
        }
    }

    /**
     * LE VOILE DU TIROIR N'EST PAS UNE COLONNE DE LA GRILLE.
     *
     * C'est le defaut qui a casse vingt-neuf ecrans sans faire rougir un seul
     * test. Sans `display: none` de base, il compte comme enfant de la grille,
     * occupe la deuxieme colonne et repousse toute la page en rangee deux.
     */
    #[Test]
    public function le_voile_du_tiroir_est_hors_de_la_grille(): void
    {
        $css = (string) file_get_contents(public_path('css/shell.css'));

        $this->assertMatchesRegularExpression(
            '/(^|\})\s*\.shell__scrim\s*\{[^}]*display:\s*none/m',
            $css,
            "Le voile n'est pas neutralise par defaut : il redeviendra un enfant de la grille, "
                .'et toute la page basculera sous la barre laterale.'
        );
    }

    /**
     * LE MENU RESTE ATTEIGNABLE SANS JAVASCRIPT.
     *
     * Meme lecon qu'a l'accueil (D-078) : le tiroir est une amelioration, pas
     * une condition d'acces. Toute regle qui escamote la barre doit etre
     * gardee par l'attribut que le script pose.
     */
    #[Test]
    public function l_escamotage_de_la_barre_est_reserve_au_script(): void
    {
        $css = (string) file_get_contents(public_path('css/shell.css'));

        preg_match_all('/([^{}]*\.shell__side[^{}]*)\{([^}]*)\}/', $css, $regles, PREG_SET_ORDER);

        foreach ($regles as $regle) {
            if (! preg_match('/position:\s*fixed|transform:\s*translateX\(-100%\)|display:\s*none/i', $regle[2])) {
                continue;
            }

            $this->assertStringContainsString('[data-js]', $regle[1],
                "Une regle escamote la barre laterale sans attendre le script : « {$regle[1]} ». "
                    .'Sans JavaScript, le menu deviendrait inatteignable.');
        }
    }

    /** Le compte connecte est nomme, et son role avec : on sait ou on est. */
    #[Test]
    public function la_barre_du_haut_nomme_le_compte_et_son_role(): void
    {
        $utilisateur = User::factory()->admin()->create(['name' => 'Personne DE TEST']);

        $this->actingAs($utilisateur)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Personne DE TEST')
            ->assertSee($utilisateur->role->label());
    }

    /** @return array<string, User> */
    private function comptes(): array
    {
        $citoyen = User::factory()->citizen()->create();

        // Un profil complet : le menu du citoyen mene a des ecrans qui en
        // dependent, et un profil vide ne prouverait rien de plus.
        CitizenProfile::create([
            'user_id' => $citoyen->id,
            'first_name' => 'Personne',
            'last_name' => 'DE TEST',
            'birth_date' => '1990-01-15',
            'birth_place' => 'Ville de test',
            'national_id_number' => 'DEMO-000000001',
            'phone' => '+237600000000',
            'address' => 'Adresse de test',
            'completed_at' => now(),
        ]);

        return [
            'citoyen' => $citoyen,
            'officier' => User::factory()->officer()->create(),
            'maire' => User::factory()->mayor()->create(),
            'administrateur' => User::factory()->admin()->create(),
        ];
    }
}

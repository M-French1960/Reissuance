<?php

declare(strict_types=1);

namespace App\Support\Security;

use App\Models\ReissuanceRequest;
use App\Models\Scopes\RequestVisibilityScope;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Verifie au demarrage que les portees globales sont bien en place.
 *
 * POURQUOI CETTE CLASSE EXISTE. Au jalon 2, la portee globale sur les demandes
 * a disparu SANS AUCUNE ERREUR : un formateur a retire le `use` en tete du
 * modele, l'attribut `#[ScopedBy(...)]` a pointe vers une classe inexistante,
 * et rien n'a ete signale. Toutes les requetes ont rendu toutes les demandes,
 * tous centres confondus, jusqu'a ce qu'un test de refus le voie (D-013).
 *
 * CE QUE CE GARDE-FOU COUVRE, EXACTEMENT. Verifie sur Laravel 13.30 : cette
 * version-la leve desormais elle-meme une InvalidArgumentException quand
 * l'attribut designe une classe inexistante. La forme precise de D-013 n'est
 * donc plus silencieuse, et ce controle n'est pas ce qui l'attraperait.
 *
 * Restent silencieuses les deux formes les plus banales : l'attribut
 * simplement SUPPRIME — une ligne perdue dans une fusion, un modele recopie
 * sans elle — et l'attribut designant une portee valide mais qui n'est pas
 * celle attendue. Ni l'une ni l'autre ne leve quoi que ce soit : le modele
 * fonctionne, et rend tout. C'est cela que ce controle attrape, et il a ete
 * eprouve en retirant reellement l'attribut.
 *
 * Un test rattrape ce defaut la ou des tests tournent. En production, il n'en
 * tourne aucun : la fuite y vivrait aussi longtemps que personne ne la
 * remarquerait. Ce controle transforme donc un defaut silencieux en panne
 * bruyante — l'application refuse de demarrer.
 *
 * C'EST UNE COMPENSATION, PAS UN EQUIVALENT. Sur PostgreSQL, la securite au
 * niveau des lignes aurait fait refuser la fuite par la base elle-meme. MySQL
 * n'a pas cette fonctionnalite (D-051), et la portee globale Eloquent est
 * redevenue la seule barriere contre une fuite EN LECTURE entre centres et
 * communes. Ce garde-fou protege la barriere ; il ne la remplace pas. Voir
 * docs/RLS.md 7.
 *
 * Le cout est negligeable : une instanciation de modele et une comparaison de
 * tableau, une fois par demarrage.
 */
final class VisibilityScopeGuard
{
    /**
     * Modele => portees globales obligatoires.
     *
     * Ajouter ici toute portee dont l'absence ferait fuir une donnee.
     *
     * @var array<class-string<Model>, list<class-string>>
     */
    private const REQUIRED = [
        ReissuanceRequest::class => [RequestVisibilityScope::class],
    ];

    /** @throws RuntimeException si une portee obligatoire manque */
    public static function assertRegistered(): void
    {
        foreach (self::REQUIRED as $modele => $portees) {
            $enregistrees = array_keys((new $modele)->getGlobalScopes());

            foreach ($portees as $portee) {
                if (in_array($portee, $enregistrees, true)) {
                    continue;
                }

                throw new RuntimeException(sprintf(
                    "Portée globale manquante : %s n'est pas appliquée sur %s.\n".
                    'Sans elle, une requête sans `where` explicite retourne les demandes de TOUS '
                    ."les centres et de toutes les communes (D-013).\n"
                    ."Vérifiez l'attribut #[ScopedBy(...)] du modèle : supprimé, ou remplacé par "
                    .'une autre portée, il ne lève aucune erreur — le modèle fonctionne et rend tout.',
                    class_basename($portee),
                    class_basename($modele),
                ));
            }
        }
    }
}

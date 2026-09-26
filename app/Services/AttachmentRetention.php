<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\RequestAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * La duree de vie d'une piece d'identite (D-094).
 *
 * LE DEFAUT QUE CELA CORRIGE. `request_attachments.purge_after` existait
 * depuis la premiere migration : declaree, castee en date, et INDEXEE — un
 * index ne se pose que pour servir une requete. Cette requete n'a jamais ete
 * ecrite, et la colonne n'etait jamais renseignee. Autrement dit, chaque piece
 * d'identite et chaque selfie etait conserve INDEFINIMENT, derriere une
 * colonne qui promettait le contraire. C'est la donnee la plus sensible du
 * systeme, et c'etait la seule dont rien ne bornait la duree.
 *
 * CE QUE CETTE CLASSE NE FAIT PAS : choisir la duree. La question B3 n'a pas
 * de reponse — la loi n 2024/017 renvoie a un referentiel que l'Autorite doit
 * publier et que nous n'avons pas trouve. Le 10 du brief interdit de coder une
 * hypothese juridique. La duree est donc une variable d'environnement SANS
 * VALEUR PAR DEFAUT, et non renseignee, rien n'est purge.
 *
 * LES DEUX REGLES DE SURETE, qui comptent plus que le mecanisme lui-meme :
 *
 * 1. UNE PIECE SANS DATE DE PURGE N'EST JAMAIS PURGEE. Les pieces deposees
 *    avant ce mecanisme ont `purge_after` a NULL. Deviner leur echeance a
 *    partir de `captured_at` reviendrait a supprimer des donnees sur une regle
 *    que personne n'a posee. Elles sont donc SIGNALEES, jamais supprimees.
 *
 * 2. UNE PIECE N'EST PURGEE QUE SI LA DEMANDE EST TERMINEE. Tant qu'un dossier
 *    vit, l'officier en a besoin pour verifier, et le maire pour decider. Une
 *    echeance courte ne doit pas desarmer une verification en cours : une piece
 *    echue sur un dossier vivant est CONSERVEE et signalee, pas supprimee.
 *
 * La purge laisse une trace dans le journal en ajout seul. La tension de la
 * question B6 — droit a l'effacement contre journal inalterable — se resout
 * ainsi : on efface le DOCUMENT, on garde le FAIT qu'il a existe et qu'il a
 * ete purge. Le journal ne porte jamais le contenu.
 */
final class AttachmentRetention
{
    public const ACTION = 'identity.purged';

    /** La duree configuree, ou null si l'exploitant ne l'a pas posee. */
    public static function retentionDays(): ?int
    {
        $valeur = config('phoenix.uploads.retention_days');

        if ($valeur === null || $valeur === '') {
            return null;
        }

        $jours = (int) $valeur;

        return $jours > 0 ? $jours : null;
    }

    /**
     * L'echeance d'une piece capturee maintenant, ou null sans duree posee.
     *
     * Appelee au depot : `purge_after` est la date de reference, ecrite une
     * fois. La purge la LIT, elle ne la recalcule pas — sinon un changement de
     * reglage avancerait l'echeance de pieces deja deposees, et la colonne
     * redeviendrait decorative.
     */
    public static function dueDateFor(CarbonImmutable $capturedAt): ?CarbonImmutable
    {
        $jours = self::retentionDays();

        return $jours === null ? null : $capturedAt->addDays($jours);
    }

    /**
     * LA PURGE EXIGE UN CONTEXTE SANS UTILISATEUR AUTHENTIFIE.
     *
     * `RequestVisibilityScope` ne s'applique pas hors authentification, et
     * c'est ce qui permet a cette classe de voir tous les dossiers. Sous un
     * utilisateur, la portee RESTREINDRAIT silencieusement le balayage : la
     * purge tournerait, annoncerait un succes, et laisserait des pieces
     * derriere elle.
     *
     * Le 16 ne tolere le contournement de la portee que dans trois methodes
     * auditees de `ReissuanceRequest` — voir ScopeBypassTest — et en ajouter
     * une quatrieme pour ce besoin elargirait une surface sensible. Nous
     * exigeons donc le contexte plutot que de le contourner, et nous echouons
     * bruyamment, ce qui est exactement ce qu'un mecanisme de confidentialite
     * silencieux ne fait pas.
     *
     * AU PASSAGE, ScopeBypassTest S'EST DECLENCHE SUR CE COMMENTAIRE, parce
     * qu'il cherche l'idiome par simple recherche de texte, commentaires
     * compris. Je ne l'ai pas rendu plus fin : une garde qui se met a
     * distinguer le code des commentaires acquiert une surface de bug, et sa
     * raison d'etre est qu'un `grep` sur `app/` reste un audit fiable. La
     * sur-detection coute une phrase reformulee ; l'assouplir couterait la
     * propriete.
     */
    private static function exigerContexteSansPortee(): void
    {
        if (Auth::hasUser()) {
            throw new RuntimeException(
                'La retention des pieces ne peut pas s\'executer sous un utilisateur '
                .'authentifie : la portee de visibilite restreindrait le balayage et la '
                .'purge laisserait des pieces derriere elle sans le dire. '
                .'Lancez-la depuis la console.'
            );
        }
    }

    /**
     * Les pieces qu'AUCUNE echeance ne borne.
     *
     * Lisible partout, y compris dans une requete authentifiee : ce compte ne
     * joint pas la table des demandes, donc aucune portee de visibilite ne
     * s'applique et la garde ci-dessus n'a pas lieu d'etre. C'est ce que le
     * controle de sante affiche, parce que c'est l'exposition reelle : une
     * piece sans echeance est une piece conservee pour toujours.
     */
    public static function unboundedCount(): int
    {
        return RequestAttachment::whereNull('purge_after')->count();
    }

    /**
     * Ce qu'une purge ferait, sans rien supprimer.
     *
     * @return array{purgeables: int, dossiers_vivants: int, sans_echeance: int}
     */
    public static function survey(): array
    {
        self::exigerContexteSansPortee();

        return [
            'purgeables' => self::purgeable()->count(),
            'dossiers_vivants' => self::echuesSurDossierVivant()->count(),
            'sans_echeance' => self::unboundedCount(),
        ];
    }

    /**
     * Supprime les pieces echues des dossiers termines.
     *
     * Le fichier part du disque prive et la ligne de la table ; le journal
     * garde la trace. L'ordre compte : la ligne n'est supprimee que si le
     * fichier l'a ete, pour ne jamais laisser un fichier orphelin sur le
     * disque, qu'aucune ligne ne designerait plus et que personne ne
     * retrouverait.
     *
     * @return list<int> les identifiants purges
     */
    public static function purge(): array
    {
        self::exigerContexteSansPortee();

        $purges = [];

        foreach (self::purgeable()->cursor() as $piece) {
            DB::transaction(function () use ($piece, &$purges): void {
                if (Storage::disk($piece->disk)->exists($piece->path)) {
                    Storage::disk($piece->disk)->delete($piece->path);
                }

                AuditLog::create([
                    // Personne n'a decide cette suppression : elle applique un
                    // reglage. Le journal admet l'acteur nul, et inventer un
                    // administrateur ici serait une fausse imputation.
                    'actor_id' => null,
                    'actor_role' => null,
                    'action' => self::ACTION,
                    'auditable_type' => 'request_attachment',
                    'auditable_id' => $piece->id,
                ]);

                $purges[] = $piece->id;

                $piece->delete();
            });
        }

        return $purges;
    }

    /** Echues, et sur un dossier termine. */
    private static function purgeable(): Builder
    {
        return RequestAttachment::query()
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->whereHas('request', fn ($q) => $q->whereIn('status', self::etatsTermines()));
    }

    /** Echues, mais le dossier vit encore : conservees, et signalees. */
    private static function echuesSurDossierVivant(): Builder
    {
        return RequestAttachment::query()
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->whereHas('request', fn ($q) => $q->whereNotIn('status', self::etatsTermines()));
    }

    /** @return list<string> */
    private static function etatsTermines(): array
    {
        return Collection::make(RequestStatus::cases())
            ->filter(fn ($etat) => $etat->isTerminal())
            ->map(fn ($etat) => $etat->value)
            ->values()
            ->all();
    }
}

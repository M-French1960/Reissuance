<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AttachmentRetention;
use Illuminate\Console\Command;

/**
 * Purge les pieces d'identite echues (D-094).
 *
 * POURQUOI ELLE NE SUPPRIME RIEN PAR DEFAUT. Cette commande detruit la donnee
 * la plus sensible du systeme, de facon irreversible, et une piece d'identite
 * supprimee a tort ne se redemande pas : elle se rephotographie, ce qui veut
 * dire redeposer une demande. Une erreur de date, une variable mal posee, et
 * la commande fait exactement ce qu'on lui demande, en silence et vite.
 *
 * Elle RECENSE donc, et n'agit que sur `--apply`. Une entree d'ordonnanceur
 * porte alors le drapeau, ce qui rend l'intention lisible dans la
 * configuration de deploiement au lieu d'etre implicite dans le nom de la
 * commande. Le cout est un mot de plus a ecrire une fois ; le gain est qu'un
 * lancement a la main ne detruit rien par surprise.
 *
 * ELLE N'EST PAS PLANIFIEE. Le projet n'a aucune tache planifiee, et la
 * planifier supposerait une duree de conservation que personne n'a fixee : la
 * question B3 est ouverte. Tant que `PHOENIX_ATTACHMENT_RETENTION_DAYS` n'est
 * pas pose, cette commande n'a rien a purger et le dit.
 */
class PurgeAttachments extends Command
{
    protected $signature = 'phoenix:purge-attachments {--apply : Supprime reellement les pieces echues}';

    protected $description = 'Recense, et avec --apply supprime, les pieces d\'identite dont l\'echeance est passee.';

    public function handle(): int
    {
        $jours = AttachmentRetention::retentionDays();

        if ($jours === null) {
            $this->warn('Aucune duree de conservation configuree.');
            $this->line('  PHOENIX_ATTACHMENT_RETENTION_DAYS n\'est pas pose, donc aucune piece');
            $this->line('  deposee ne porte d\'echeance et rien ne sera purge. Ce n\'est pas un');
            $this->line('  oubli du code : la duree legale est la question B3 de');
            $this->line('  docs/COMPLIANCE_OPEN_QUESTIONS.md, et elle est ouverte.');
        } else {
            $this->line("Duree de conservation : {$jours} jours.");
        }

        $etat = AttachmentRetention::survey();

        $this->newLine();
        $this->line("Pieces echues sur un dossier termine : {$etat['purgeables']}");
        $this->line("Pieces echues sur un dossier en cours : {$etat['dossiers_vivants']} (conservees : le dossier vit)");
        $this->line("Pieces sans echeance : {$etat['sans_echeance']} (jamais purgees)");

        if ($etat['sans_echeance'] > 0 && $jours !== null) {
            $this->newLine();
            $this->warn("{$etat['sans_echeance']} piece(s) n'ont pas d'echeance.");
            $this->line('  Elles ont ete deposees avant que la duree ne soit configuree. Elles ne');
            $this->line('  seront jamais purgees, et deviner leur echeance a partir de la date de');
            $this->line('  capture reviendrait a supprimer des donnees sur une regle que personne');
            $this->line('  n\'a posee. Leur sort est une decision d\'exploitation.');
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('Recensement seul. Relancez avec --apply pour supprimer.');

            return self::SUCCESS;
        }

        if ($etat['purgeables'] === 0) {
            $this->newLine();
            $this->info('Rien a purger.');

            return self::SUCCESS;
        }

        $purges = AttachmentRetention::purge();

        $this->newLine();
        $this->info(count($purges).' piece(s) purgee(s). Chaque suppression est inscrite au journal.');

        return self::SUCCESS;
    }
}

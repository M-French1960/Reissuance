<?php

declare(strict_types=1);

namespace App\Support\Tracking;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;

/**
 * Ou en est une demande, du point de vue de son auteur.
 *
 * POURQUOI CETTE CLASSE EXISTE (D-079). Cette frise vivait dans le controleur
 * de suivi, en methode privee. Le tableau de bord du citoyen devait afficher
 * la meme chose : la tentation etait d'en ecrire une deuxieme, plus simple,
 * qui aurait deduit l'avancement du statut courant.
 *
 * C'est exactement le defaut que D-075 a corrige ici. Un brouillon annule
 * affichait alors « Demande envoyee ✓ », « Verification par l'officier ✓ » et
 * « Decision du maire ✓ » : trois etapes qui n'avaient jamais eu lieu. La
 * raison est que `signed`, `rejected` et `cancelled` partagent le meme rang —
 * le parcours s'arrete la, qu'il aboutisse ou non — et qu'un rang ne dit donc
 * pas ce qui s'est passe.
 *
 * Une deuxieme frise aurait refait cette erreur, et personne ne l'aurait vue :
 * la premiere serait restee juste. Il n'y en a donc qu'une.
 */
final class RequestTimeline
{
    /**
     * Construit la frise a partir du journal d'audit, qui est la seule trace
     * fiable de ce qui s'est reellement passe.
     *
     * @return list<array{titre: string, etat: string, detail: string, date: ?string}>
     */
    public function for(ReissuanceRequest $demande): array
    {
        $transitions = AuditLog::query()
            ->where('auditable_type', 'reissuance_request')
            ->where('auditable_id', $demande->id)
            ->whereNotNull('to_status')
            ->orderBy('created_at')
            ->get()
            ->keyBy('to_status');

        $statut = $demande->status;

        $jalons = [
            [
                'titre' => __('citizen.tracking.milestone_submitted'),
                'statut' => RequestStatus::Pending,
                // Arriver a cet etat, c'est AVOIR ENVOYE : le jalon est
                // accompli des qu'il est atteint.
                'entreeAcheve' => true,
                /*
                 * THE CENTRE'S NAME SPEAKS FOR ITSELF (D-073/D-075). Centres
                 * are already called "Civil status centre of Yaounde I", so
                 * prefixing produced "Sent to the civil status centre of Civil
                 * status centre of Yaounde I". Found by looking at the screen.
                 */
                'detail' => $demande->center
                    ? __('citizen.tracking.milestone_submitted_detail', ['centre' => $demande->center->name])
                    : __('citizen.tracking.milestone_submitted_detail_generic'),
            ],
            [
                'titre' => __('citizen.tracking.milestone_checked'),
                'statut' => RequestStatus::UnderReview,
                // Arriver a cet etat, c'est que la verification COMMENCE.
                'entreeAcheve' => false,
                'detail' => __('citizen.tracking.milestone_checked_detail'),
            ],
            [
                'titre' => __('citizen.tracking.milestone_mayor'),
                'statut' => RequestStatus::AwaitingSignature,
                // Arriver a cet etat, c'est que le dossier ATTEND le maire.
                'entreeAcheve' => false,
                'detail' => __('citizen.tracking.milestone_mayor_detail'),
            ],
            [
                'titre' => __('citizen.tracking.milestone_available'),
                'statut' => RequestStatus::Signed,
                // Arriver a cet etat, c'est que l'acte EXISTE.
                'entreeAcheve' => true,
                /*
                 * IN THE RIGHT TENSE (D-073). "You will be able to download
                 * your certificate" was showing under a step marked "done":
                 * the future under an accomplished fact. Present tense as soon
                 * as the certificate exists.
                 */
                'detail' => $demande->signature !== null
                    ? __('citizen.tracking.milestone_available_ready')
                    : __('citizen.tracking.milestone_available_pending'),
            ],
        ];

        // L'ordre vit sur l'enumeration : un statut ajoute sans rang y leve
        // une erreur a la source, plutot que de casser cet ecran (D-043).
        //
        // MAIS LE RANG NE SUFFIT PAS QUAND LE PARCOURS S'EST ARRETE. `signed`,
        // `rejected` et `cancelled` partagent le rang 4 : le parcours s'arrete
        // la, qu'il aboutisse ou non. S'en servir pour decider quels jalons
        // sont « terminés » revenait a annoncer au demandeur des etapes qui
        // n'ont jamais eu lieu — un brouillon annule affichait « Demande
        // envoyée ✓ », « Vérification par l'officier ✓ » et « Décision du
        // maire ✓ ». Mentir au demandeur sur l'instruction de son dossier est
        // grave, et c'est exactement ce que la frise faisait.
        //
        // Pour un parcours arrete, le point d'arret se lit donc dans le
        // JOURNAL D'AUDIT — la seule trace de ce qui s'est reellement passe —
        // et non dans un rang que trois etats se partagent.
        $dernierJalonTrace = 0;

        foreach ($jalons as $index => $jalon) {
            if ($transitions->get($jalon['statut']->value) !== null) {
                $dernierJalonTrace = $index + 1;
            }
        }

        $atteint = $statut->isStopped() ? $dernierJalonTrace : $statut->timelineRank();
        $frise = [];

        foreach ($jalons as $index => $jalon) {
            $rang = $index + 1;
            $trace = $transitions->get($jalon['statut']->value);

            /*
             * ENTRER DANS UNE ETAPE N'EST PAS L'AVOIR TERMINEE (D-087).
             *
             * La version precedente marquait « fait » des qu'une trace d'audit
             * existait pour l'etat du jalon. Pour « Demande envoyee », entrer
             * en `pending` EST l'accomplissement, et c'etait juste. Pour
             * « Verification par l'officier », entrer en `under_review` veut
             * dire que le controle COMMENCE — et le demandeur lisait
             * « Verification par l'officier : terminé » pendant que son
             * dossier etait encore sur le bureau de l'agent.
             *
             * C'est la meme faute que D-075, sous une autre forme : la frise
             * annoncait au demandeur une etape qui n'avait pas eu lieu. Trouve
             * en regardant l'ecran, pas en lisant le code — et rendu plus grave
             * par la piece reclamee : on pouvait annoncer la verification
             * terminée a quelqu'un a qui l'on demandait justement une photo.
             *
             * Chaque jalon dit donc maintenant si son entree vaut son
             * achevement. Un parcours ARRETE fait exception : le dossier ne
             * bougera plus, l'etape ou il s'est arrete est bel et bien close.
             */
            $etat = match (true) {
                $trace !== null && ($jalon['entreeAcheve'] || $rang < $atteint || $statut->isStopped()) => 'fait',
                $trace !== null => 'en_cours',
                $statut->isStopped() && $rang > $atteint => 'arrete',
                $rang < $atteint => 'fait',
                $rang === $atteint && ! $statut->isStopped() => 'en_cours',
                default => 'a_venir',
            };

            $frise[] = [
                'titre' => $jalon['titre'],
                'etat' => $etat,
                /*
                 * UN JALON NON ATTEINT NE PROMET RIEN (D-075).
                 *
                 * Sur un dossier refuse, la frise affichait « Acte disponible
                 * — non atteint » et, dessous, « Vous pourrez télécharger
                 * votre acte ». Le demandeur venait d'etre refuse : il ne
                 * telechargera pas d'acte. Les etapes qui n'auront pas lieu
                 * se taisent plutot que d'annoncer un avenir qui n'existe pas.
                 */
                'detail' => $etat === 'arrete' ? '' : $jalon['detail'],
                /*
                 * THE PREPOSITION IS PART OF THE LANGUAGE, NOT OF THE DATE
                 * (D-077). The format string carried a literal « à », so the
                 * English timeline read "16 September 2026 à 01:03": the date
                 * translated, the word between it and the time did not.
                 */
                'date' => $trace?->created_at === null ? null : __('common.date_and_time', [
                    'date' => $trace->created_at->translatedFormat('d F Y'),
                    'time' => $trace->created_at->format('H:i'),
                ]),
            ];
        }

        return $frise;
    }
}

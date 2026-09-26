<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Support\Tracking\RequestTimeline;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Suivre une demande SANS COMPTE (D-096).
 *
 * J'AI REFUSE CE SUIVI DEUX FOIS, ET VOICI CE QUI A CHANGE. Mon refus portait
 * sur une porte publique ouverte par une REFERENCE SEULE : celui qui devine
 * une reference lit l'avancement du dossier d'un inconnu. La maquette du
 * client ajoute un SECOND FACTEUR — les quatre derniers chiffres du telephone
 * donne lors de la demande — et cela change l'analyse, pas par magie mais
 * parce qu'un attaquant doit alors tenir deux choses a la fois.
 *
 * QUATRE BARRIERES, ET AUCUNE NE SUFFIT SEULE :
 *
 *   1. LA REFERENCE N'EST PAS CELLE DE LA MAQUETTE. Le prototype proposait
 *      « PHX-AAAA-000000 » : une annee et six chiffres, soit un million de
 *      possibilites par an, qu'un script parcourt en une apres-midi. La notre
 *      est `PHX-` suivi de huit caracteres tires au hasard, soit environ
 *      2,8 x 10^12. La porte n'est pas gardee par le seul second facteur.
 *   2. LE SECOND FACTEUR, quatre chiffres, qui ne vaut rien tout seul mais
 *      multiplie par dix mille le cout de la premiere barriere.
 *   3. LA LIMITATION DE DEBIT, posee sur la route, qui arrete le balayage.
 *   4. LA REPONSE EST LA MEME dans tous les cas d'echec : reference inconnue,
 *      mal formee, telephone faux, ou dossier non suivable. Distinguer ces
 *      cas ferait de cette page un ORACLE a references — « celle-la existe,
 *      il ne me manque que quatre chiffres ».
 *
 * LA REGLE QUI COMPTE LE PLUS : SANS TELEPHONE AU DOSSIER, PAS DE SUIVI.
 * `citizen_profiles.phone` est NULLABLE. Un dossier sans numero ne peut pas
 * satisfaire le second facteur — et la tentation serait de « tolerer » la
 * reference seule dans ce cas, ce qui rouvrirait exactement la porte que j'ai
 * refusee deux fois. Un tel dossier n'est donc pas suivable publiquement, et
 * la reponse ne dit meme pas pourquoi.
 *
 * UN BROUILLON N'EST PAS SUIVABLE. Il n'existe que pour son auteur, il n'a pas
 * ete envoye, et rien ne le concerne au dehors.
 *
 * CE QUE LA REPONSE MONTRE. Les quatre jalons publics et l'etat d'avancement,
 * rien d'autre : ni nom, ni date de naissance, ni filiation, ni centre, ni
 * horodatage. Le motif d'un rejet n'est pas donne — on renvoie a l'espace du
 * demandeur, qui exige un compte.
 */
class PublicTrackingController extends Controller
{
    public function show(): View
    {
        return view('public.track', ['resultat' => null, 'referenceSaisie' => null]);
    }

    public function check(Request $request, RequestTimeline $frise): View
    {
        $saisie = trim((string) $request->input('reference', ''));
        $chiffres = preg_replace('/\D/', '', (string) $request->input('telephone', '')) ?? '';

        $demande = $this->retrouver($saisie, $chiffres);

        if ($demande !== null) {
            /*
             * Seules les consultations qui ABOUTISSENT sont journalisees, pour
             * la meme raison qu'en D-088 : enregistrer chaque echec ferait
             * d'une table en ajout seul un levier pour la remplir depuis
             * l'exterieur.
             */
            AuditLog::create([
                'actor_id' => null,
                'actor_role' => null,
                'action' => 'request.tracked_publicly',
                'auditable_type' => 'reissuance_request',
                'auditable_id' => $demande->id,
                'ip_address' => $request->ip(),
            ]);
        }

        return view('public.track', [
            'resultat' => $demande === null ? false : [
                'reference' => $demande->reference,
                'jalons' => $frise->publicFor($demande),
                'message' => $this->message($demande),
                'ton' => $this->ton($demande),
            ],
            'referenceSaisie' => $saisie,
        ]);
    }

    /**
     * Retrouve le dossier, ou rien — sans jamais dire laquelle des conditions
     * a echoue.
     */
    private function retrouver(string $reference, string $chiffres): ?ReissuanceRequest
    {
        if ($reference === '' || strlen($chiffres) !== 4) {
            return null;
        }

        /*
         * La portee de visibilite ne s'applique pas : aucun utilisateur n'est
         * authentifie sur cette route. C'est donc CE code qui porte la
         * restriction, et il la porte entierement.
         */
        $demande = ReissuanceRequest::query()
            ->where('reference', $reference)
            ->where('status', '!=', RequestStatus::Draft->value)
            ->with('citizen.profile:id,user_id,phone')
            ->first();

        if ($demande === null) {
            return null;
        }

        $telephone = preg_replace('/\D/', '', (string) $demande->citizen?->profile?->phone) ?? '';

        // SANS NUMERO AU DOSSIER, LE SECOND FACTEUR NE PEUT PAS ETRE SATISFAIT.
        // Ne pas « tolerer » la reference seule ici est tout l'interet de
        // l'ecran : la tolerance serait la faille.
        if (strlen($telephone) < 4) {
            return null;
        }

        // Comparaison a temps constant : une comparaison qui s'arrete au
        // premier chiffre different se mesure, et quatre chiffres se
        // devineraient alors un par un.
        return hash_equals(substr($telephone, -4), $chiffres) ? $demande : null;
    }

    private function message(ReissuanceRequest $demande): string
    {
        if ($demande->complements()->whereNull('fulfilled_at')->exists()) {
            return __('public.track.awaiting_complement');
        }

        return match ($demande->status) {
            RequestStatus::Rejected => __('public.track.rejected'),
            RequestStatus::Cancelled => __('public.track.cancelled'),
            RequestStatus::Signed => __('public.track.ready'),
            default => __('public.track.in_progress'),
        };
    }

    private function ton(ReissuanceRequest $demande): string
    {
        if ($demande->complements()->whereNull('fulfilled_at')->exists()) {
            return 'attention';
        }

        return match ($demande->status) {
            RequestStatus::Rejected, RequestStatus::Cancelled => 'danger',
            RequestStatus::Signed => 'success',
            default => 'info',
        };
    }
}

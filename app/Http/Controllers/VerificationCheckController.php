<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentSignature;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verifier l'authenticite d'un acte, SANS COMPTE (D-088).
 *
 * POURQUOI CETTE PORTE EST PUBLIQUE, ALORS QUE J'AI REFUSE LE SUIVI PUBLIC.
 * Celui qui verifie n'est pas le demandeur : c'est l'administration, l'ecole,
 * l'employeur ou l'ambassade qui RECOIT une copie. Lui demander un compte
 * reviendrait a rendre la verification impossible, donc a laisser circuler des
 * faux. La personne qui verifie a deja le document sous les yeux : elle ne
 * decouvre rien qu'elle ne lise deja sur le papier.
 *
 * CE QUE LA REPONSE MONTRE, ET CE QU'ELLE CACHE. Le but est de COMPARER, pas
 * de renseigner : la reponse donne la reference, la date de delivrance, le
 * centre, les INITIALES du titulaire et son ANNEE de naissance. Quelqu'un qui
 * tient la copie peut tout comparer ; quelqu'un qui aurait seulement un code
 * n'apprend ni un nom, ni une date de naissance, ni une filiation.
 *
 * TROIS BARRIERES, ET NON UNE SEULE :
 *
 *   1. le code fait 60 bits d'entropie — une recherche exhaustive est hors de
 *      portee, limitation de debit ou non ;
 *   2. la limitation de debit, posee sur la route, arrete le balayage ;
 *   3. la reponse est la MEME pour un code inconnu et pour un code mal forme :
 *      elle n'apprend pas si un code « existe presque ».
 *
 * CE QUE CET ECRAN NE FAIT PAS : dire qu'un acte a ete revoque. Aucune
 * revocation n'existe dans le systeme, et afficher « valide » face a un etat
 * qui n'est jamais calcule serait pire que de se taire. Voir D-088.
 */
class VerificationCheckController extends Controller
{
    public function show(): View
    {
        return view('public.verify', ['resultat' => null, 'codeSaisi' => null]);
    }

    public function check(Request $request): View
    {
        $saisi = (string) $request->input('code', '');
        $code = DocumentSignature::normalizeVerificationCode($saisi);

        $signature = $code === '' ? null : DocumentSignature::query()
            ->where('verification_code', $code)
            ->with(['request:id,reference,full_name_at_birth,date_of_birth,civil_status_center_id,act_language',
                'request.center:id,name'])
            ->first();

        if ($signature !== null) {
            /*
             * SEULES LES VERIFICATIONS QUI ABOUTISSENT SONT JOURNALISEES.
             *
             * Une tentative infructueuse vient de n'importe qui : les
             * enregistrer toutes ferait d'une table en AJOUT SEUL un levier
             * pour la remplir depuis l'exterieur. Une verification qui aboutit,
             * en revanche, est un fait utile : elle dit qu'une copie de cet
             * acte circule, et combien de fois elle a ete presentee.
             */
            AuditLog::create([
                'actor_id' => null,
                'actor_role' => null,
                'action' => 'act.verified',
                'auditable_type' => 'document_signature',
                'auditable_id' => $signature->id,
                'ip_address' => $request->ip(),
            ]);
        }

        return view('public.verify', [
            'resultat' => $signature === null ? false : $this->resume($signature),
            'codeSaisi' => $saisi,
        ]);
    }

    /**
     * Ce que l'on montre d'un acte authentique : de quoi COMPARER.
     *
     * @return array<string, ?string>
     */
    private function resume(DocumentSignature $signature): array
    {
        $demande = $signature->request;

        return [
            'reference' => $demande?->reference,
            'centre' => $demande?->center?->name,
            'delivre' => $signature->signed_at?->translatedFormat('d F Y'),
            'initiales' => self::initiales($demande?->full_name_at_birth),
            'annee' => $demande?->date_of_birth?->format('Y'),
            'valeurJuridique' => $signature->legally_binding,
        ];
    }

    /**
     * « Marie Flore NGONO » devient « M. F. N. ».
     *
     * Assez pour confirmer que la copie presentee correspond, trop peu pour
     * apprendre a qui appartient un acte dont on aurait seulement le code.
     */
    private static function initiales(?string $nom): ?string
    {
        if ($nom === null || trim($nom) === '') {
            return null;
        }

        $mots = preg_split('/[\s\-]+/u', trim($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(
            static fn (string $mot): string => mb_strtoupper(mb_substr($mot, 0, 1)).'.',
            $mots
        ));
    }
}

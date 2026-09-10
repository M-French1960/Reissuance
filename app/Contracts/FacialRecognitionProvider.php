<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\RequestAttachment;
use App\Support\ProviderResponse;

/**
 * Comparaison faciale entre le selfie du demandeur et la photographie de sa
 * piece d'identite. Etape 3 de la verification.
 *
 * TRAITEMENT BIOMETRIQUE — les regles qui l'encadrent ici, et qui ne sont pas
 * negociables au niveau du code :
 *
 *  1. **La machine ne decide pas.** Elle rend un avis. L'officier tranche, et
 *     un resultat autre qu'une correspondance rend son motif obligatoire
 *     (D-031). Aucun rejet automatique fonde sur un score n'existe et n'a a
 *     exister.
 *
 *  2. **Une personne non reconnue n'est jamais bloquee.** Un `no_match`, un
 *     resultat non concluant ou un service indisponible sont des resultats
 *     ENREGISTRES : la vérification est complete, et l'officier peut accepter
 *     en motivant. Sans cette porte, un faux negatif priverait quelqu'un de
 *     son acte d'etat civil, donc de l'acces a a peu pres tout.
 *
 *  3. **Aucun gabarit biometrique n'est conserve.** L'adaptateur compare deux
 *     images deja presentes au dossier et rend un avis ; on enregistre l'avis
 *     et le score, jamais un encodage du visage. Voir docs/BIOMETRIE.md.
 *
 * ATTENTION : aucun fournisseur reel n'est documente. Ce contrat est une
 * HYPOTHESE de travail.
 */
interface FacialRecognitionProvider
{
    /**
     * @param  RequestAttachment  $selfie  photographie prise par le demandeur
     * @param  RequestAttachment  $idDocument  photographie de la piece presentee
     */
    public function compare(RequestAttachment $selfie, RequestAttachment $idDocument): ProviderResponse;
}

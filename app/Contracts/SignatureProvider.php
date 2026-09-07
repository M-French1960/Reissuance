<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\SignatureResult;

/**
 * Signature électronique d'un acte (jalon 5, transitions T7 et T9).
 *
 * ATTENTION — docs/INTEGRATIONS.md 4 et docs/COMPLIANCE_OPEN_QUESTIONS.md
 * bloc A. Ce contrat est une HYPOTHÈSE de travail. Cinq questions restent
 * sans réponse, dont deux déterminantes :
 *
 *   A1. Un acte d'état civil signé électroniquement a-t-il valeur légale au
 *       Cameroun ? **Sans réponse, ce jalon produit un document dont on
 *       ignore s'il vaut quoi que ce soit.**
 *   A4. Qui est juridiquement le signataire — le maire en tant que personne,
 *       ou la commune en tant qu'institution ? La réponse détermine si le
 *       certificat est nominatif, et donc toute la gestion des clés.
 *
 * En attendant, l'adaptateur factice produit un document **portant en clair
 * la mention qu'il est sans valeur juridique**. Ce n'est pas une précaution
 * de développement : c'est une exigence de sécurité. Aucun document produit
 * ici ne doit pouvoir être confondu avec un acte authentique.
 */
interface SignatureProvider
{
    /**
     * @param  string  $documentContents  le PDF à signer
     * @param  array<string, mixed>  $context  référence, signataire, commune
     */
    public function sign(string $documentContents, array $context): SignatureResult;
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SignatureProvider;
use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Production de l'acte signé.
 *
 * Le §4.3 du brief est catégorique : aucun chemin de code ne doit permettre de
 * produire un acte signé sans demande citoyenne avec pièces, vérification
 * complète par un officier habilité du centre, et décision explicite du maire
 * habilité de la commune.
 *
 * Cette classe est le SEUL endroit qui produit un acte. Elle repose sur la
 * transition T7/T9, elle-même gardée par le déclencheur PostgreSQL : atteindre
 * `signed` exige un maire de la commune, et n'est possible que depuis
 * `awaiting_signature` ou `escalated` — deux états qu'un officier habilité est
 * seul à pouvoir produire.
 */
final class ActIssuanceService
{
    public function __construct(
        private readonly SignatureProvider $signature,
        private readonly DocumentBuilder $builder,
        private readonly RequestTransitionService $transitions,
    ) {}

    public function issue(ReissuanceRequest $request, User $mayor, ?string $reason = null): DocumentSignature
    {
        return DB::transaction(function () use ($request, $mayor, $reason): DocumentSignature {
            // 1. La transition d'abord : si elle est refusée, aucun document
            //    n'est produit. L'ordre n'est pas anodin.
            $this->transitions->transition(
                $request, RequestStatus::Signed, $mayor, $reason, request()->ip()
            );

            $request->refresh()->load('citizen.profile', 'center', 'commune');

            // 2. Composition, puis signature du document tel quel.
            $document = $this->builder->build($request, $mayor, legallyBinding: false);

            $resultat = $this->signature->sign($document, [
                'signatory' => $mayor->name,
                'commune' => $request->commune?->name,
                'reference' => $request->reference,
            ]);

            $proof = array_merge($resultat->proof, ['provider' => $resultat->provider]);
            $proofDocument = $this->builder->appendProof(
                $resultat->signedDocument, $proof, $resultat->documentHash
            );

            // 3. Écriture hors de public/, sous des chemins opaques.
            $base = 'acts/'.Str::uuid();
            $documentPath = "{$base}/acte.pdf";
            $proofPath = "{$base}/preuve.pdf";

            Storage::disk('private')->put($documentPath, $resultat->signedDocument);
            Storage::disk('private')->put($proofPath, $proofDocument);

            $signature = DocumentSignature::create([
                'request_id' => $request->id,
                'mayor_id' => $mayor->id,
                'document_hash' => $resultat->documentHash,
                'document_path' => $documentPath,
                'proof_path' => $proofPath,
                'provider' => $resultat->provider,
                'legally_binding' => $resultat->legallyBinding,
                'signature_payload' => $proof,
                'signed_at' => now(),
            ]);

            AuditLog::create([
                'actor_id' => $mayor->id,
                'actor_role' => $mayor->role->value,
                'action' => 'act.issued',
                'auditable_type' => 'document_signature',
                'auditable_id' => $signature->id,
                'reason' => $resultat->legallyBinding
                    ? null
                    : 'Signature de démonstration, sans valeur juridique.',
                'ip_address' => request()->ip(),
            ]);

            return $signature;
        });
    }
}

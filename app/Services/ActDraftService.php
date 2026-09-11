<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActDraft;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Redaction du projet d'acte par l'officier — « Generate Certificate ».
 *
 * LECTURE 2 DU DIAGRAMME (D-064). Le diagramme place « Generate Certificate »
 * chez l'officier. Retenu : l'officier REDIGE le projet, le maire SIGNE ce
 * projet. La responsabilite du contenu passe donc a l'officier, et celle de la
 * delivrance reste au maire.
 *
 * CE QUE CE SERVICE NE FAIT PAS, et c'est le point qui rend la lecture 2
 * tenable : il ne produit AUCUN document qui puisse passer pour un acte. Le
 * projet porte un bandeau « PROJET — NON SIGNE », vit dans une table distincte
 * de `document_signatures`, n'est jamais accessible au citoyen, et n'a ni
 * signataire ni preuve. Le 4.3 du brief tient : aucun document ayant valeur
 * d'acte n'existe avant la decision du maire.
 */
final class ActDraftService
{
    public function __construct(
        private readonly DocumentBuilder $builder,
    ) {}

    /**
     * Redige le projet, et en releve l'empreinte de contenu.
     *
     * Appele quand la decision de l'officier remet le dossier au maire — une
     * acceptation (T4) comme une escalade (T6). Les deux chemins menent a une
     * signature possible, ils doivent donc tous deux porter un projet.
     */
    public function draft(ReissuanceRequest $request, User $officer): ActDraft
    {
        $request->loadMissing('citizen.profile', 'center', 'commune');

        $document = $this->builder->buildDraft($request, $officer);
        $chemin = 'drafts/'.Str::uuid().'/projet-acte.pdf';

        Storage::disk('private')->put($chemin, $document);

        return DB::transaction(function () use ($request, $officer, $chemin): ActDraft {
            $projet = ActDraft::create([
                'request_id' => $request->id,
                'officer_id' => $officer->id,
                'content_hash' => DocumentBuilder::contentFingerprint($request),
                'document_path' => $chemin,
            ]);

            AuditLog::create([
                'actor_id' => $officer->id,
                'actor_role' => $officer->role->value,
                'action' => 'act.draft_written',
                'auditable_type' => 'act_draft',
                'auditable_id' => $projet->id,
                'ip_address' => request()->ip(),
            ]);

            return $projet;
        });
    }

    /** Le dernier projet redige pour cette demande, s'il y en a un. */
    public function latest(ReissuanceRequest $request): ?ActDraft
    {
        return ActDraft::query()
            ->where('request_id', $request->id)
            ->latest('id')
            ->first();
    }
}

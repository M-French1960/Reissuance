<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\RequestComplement;
use App\Models\User;
use App\Notifications\ComplementProvided;
use App\Notifications\ComplementRequested;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reclamer une piece au demandeur, et recevoir sa reponse (D-087).
 *
 * LE POINT LE PLUS IMPORTANT DE CE FICHIER : quand la piece remplacee est une
 * piece d'identite, un NOUVEAU CYCLE DE VERIFICATION est ouvert.
 *
 * Sans cela, les etapes deja franchies sur l'ANCIENNE photo resteraient
 * valides pour la nouvelle. Un dossier pourrait donc etre signe sur la foi
 * d'une verification portant sur un document qui a ete remplace depuis — un
 * acte signe sans verification reelle, exactement ce que le §4.3 interdit.
 * Le mecanisme de cycle existait deja pour le retour du maire (T8) ; il est
 * reutilise ici plutot que reinvente.
 */
class ComplementService
{
    public function __construct(
        private readonly IdentityDocumentStore $store,
        private readonly VerificationWorkflow $verification,
    ) {}

    /** L'officier reclame une piece. */
    public function open(
        ReissuanceRequest $request,
        User $officer,
        string $kind,
        string $message,
    ): RequestComplement {
        if ($request->pendingComplement()->exists()) {
            // La base l'interdit aussi, par une contrainte d'unicite. Ce
            // garde-fou rend un message utilisable plutot qu'une erreur SQL.
            throw new RuntimeException(__('flash.officer.complement_already_open'));
        }

        return DB::transaction(function () use ($request, $officer, $kind, $message): RequestComplement {
            $complement = $request->complements()->create([
                'requested_by' => $officer->id,
                'kind' => $kind,
                'message' => $message,
            ]);

            AuditLog::create([
                'actor_id' => $officer->id,
                'actor_role' => $officer->role->value,
                'action' => 'request.complement_requested',
                'auditable_type' => 'request_complement',
                'auditable_id' => $complement->id,
                // Le motif n'est PAS recopie ici : il est redige a la main et
                // peut nommer une personne. Il vit dans la table, que seuls le
                // demandeur et les agents du dossier lisent.
                'reason' => $kind,
                'ip_address' => request()->ip(),
            ]);

            $request->citizen->notify(new ComplementRequested(
                $request->reference,
                $request->id,
                $kind,
            ));

            return $complement;
        });
    }

    /** Le demandeur repond. */
    public function fulfil(
        RequestComplement $complement,
        UploadedFile $file,
        User $citizen,
    ): RequestComplement {
        $request = $complement->request;

        return DB::transaction(function () use ($complement, $request, $file, $citizen): RequestComplement {
            // Le magasin remplace la piece du meme type et supprime la
            // precedente : c'est deja sa regle, et elle vaut ici aussi.
            $attachment = $this->store->store($request, $file, $complement->kind, $citizen);

            $complement->forceFill([
                'fulfilled_at' => now(),
                'fulfilled_attachment_id' => $attachment->id,
            ])->save();

            /*
             * LA VERIFICATION REPART A ZERO.
             *
             * Les etapes du cycle precedent sont conservees intactes — elles
             * disent ce qui a ete verifie, et sur quoi — mais elles ne
             * comptent plus : l'officier doit refaire ses controles sur la
             * piece qu'il a lui-meme reclamee.
             */
            if ($complement->touchesIdentity()) {
                $this->verification->openNewCycle($request);
            }

            AuditLog::create([
                'actor_id' => $citizen->id,
                'actor_role' => $citizen->role->value,
                'action' => 'request.complement_provided',
                'auditable_type' => 'request_complement',
                'auditable_id' => $complement->id,
                'reason' => $complement->kind,
                'ip_address' => request()->ip(),
            ]);

            $officier = $complement->requestedBy;

            if ($officier !== null) {
                $officier->notify(new ComplementProvided(
                    $request->reference,
                    $request->id,
                    $complement->kind,
                ));
            }

            return $complement->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une piece reclamee au demandeur apres l'envoi de sa demande (D-087).
 *
 * C'est l'officier qui ouvre, le demandeur qui repond. Jamais l'inverse : sans
 * demande prealable, n'importe qui pourrait remplacer apres coup la piece
 * d'identite deja verifiee.
 *
 * Une seule demande peut etre ouverte a la fois sur un dossier, et c'est la
 * base qui l'impose, pas ce modele.
 */
class RequestComplement extends Model
{
    protected $fillable = ['request_id', 'requested_by', 'kind', 'message'];

    protected function casts(): array
    {
        return ['fulfilled_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(RequestAttachment::class, 'fulfilled_attachment_id');
    }

    public function isPending(): bool
    {
        return $this->fulfilled_at === null;
    }

    /**
     * La piece demandee est-elle une piece d'identite ?
     *
     * Les deux le sont aujourd'hui, et c'est justement pourquoi la question est
     * posee ici plutot que supposee ailleurs : le jour ou un justificatif de
     * domicile s'ajoute, remplacer ce document ne devra PAS relancer la
     * verification d'identite, et cette methode sera le seul endroit a changer.
     */
    public function touchesIdentity(): bool
    {
        return in_array($this->kind, ['id_document', 'selfie'], true);
    }
}

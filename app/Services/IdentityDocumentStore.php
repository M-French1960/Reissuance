<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stockage des pieces d'identite.
 *
 * Ce sont les donnees les plus sensibles du systeme (3.3 du brief).
 * Trois regles ne souffrent aucune exception :
 *
 *   1. Le fichier est ecrit hors de public/, sur le disque « private ».
 *   2. Son chemin est opaque : un UUID, jamais derive du nom ni du numero de
 *      piece. Un chemin devinable annulerait la protection — il suffirait de
 *      connaitre l'identite d'une personne pour construire l'URL de sa piece.
 *   3. Toute lecture passe par une Policy verifiee AVANT de servir le premier
 *      octet, et ecrit une ligne d'audit (4.4).
 *
 * Note sur le 3.3 : il demandait un envoi direct vers un stockage objet par
 * URL pre-signee, pour ne pas faire transiter les images par le conteneur PHP.
 * Cette exigence visait l'ephemerite du conteneur Vercel. Depuis D-011, le
 * stockage est un disque local : il n'existe pas d'URL pre-signee a signer, et
 * l'envoi passe donc par l'application. L'abstraction Storage est conservee
 * telle quelle pour qu'un basculement vers S3 ne demande qu'un changement de
 * disque en configuration.
 */
final class IdentityDocumentStore
{
    /** @var list<string> */
    private const ALLOWED_KINDS = ['selfie', 'id_document'];

    public function store(
        ReissuanceRequest $request,
        UploadedFile $file,
        string $kind,
        User $actor,
    ): RequestAttachment {
        if (! in_array($kind, self::ALLOWED_KINDS, true)) {
            throw new RuntimeException("Type de pièce inconnu : {$kind}");
        }

        // Le type MIME est relu depuis le contenu du fichier, jamais depuis
        // l'en-tete envoye par le navigateur, qui est sous controle du client.
        $mime = $file->getMimeType();
        $accepted = (array) config('phoenix.uploads.accepted_mime');

        if (! in_array($mime, $accepted, true)) {
            throw new RuntimeException("Type de fichier refusé : {$mime}");
        }

        $maxBytes = (int) config('phoenix.uploads.max_bytes');

        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('Fichier trop volumineux.');
        }

        $path = RequestAttachment::generatePath($kind);
        $checksum = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($request, $file, $kind, $actor, $path, $mime, $checksum): RequestAttachment {
            // Une nouvelle piece du meme type remplace la precedente : le
            // citoyen peut reprendre une photo ratee (5.2 du brief).
            $previous = $request->attachments()->where('kind', $kind)->get();

            Storage::disk('private')->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );

            $attachment = $request->attachments()->create([
                'kind' => $kind,
                'disk' => 'private',
                'path' => $path,
                'mime_type' => $mime,
                'size_bytes' => Storage::disk('private')->size($path),
                'checksum_sha256' => $checksum,
                'captured_at' => now(),
            ]);

            foreach ($previous as $old) {
                Storage::disk('private')->delete($old->path);
                $old->delete();
            }

            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role->value,
                'action' => "identity.{$kind}_uploaded",
                'auditable_type' => 'request_attachment',
                'auditable_id' => $attachment->id,
                'ip_address' => request()->ip(),
            ]);

            return $attachment;
        });
    }

    /**
     * Lit le contenu d'une piece, en journalisant la consultation.
     *
     * L'appelant DOIT avoir verifie la Policy au prealable. Cette methode
     * journalise ; elle n'autorise pas.
     */
    public function read(RequestAttachment $attachment, User $viewer): string
    {
        AuditLog::create([
            'actor_id' => $viewer->id,
            'actor_role' => $viewer->role->value,
            'action' => "identity.{$attachment->kind}_viewed",
            'auditable_type' => 'request_attachment',
            'auditable_id' => $attachment->id,
            'ip_address' => request()->ip(),
        ]);

        $contents = Storage::disk('private')->get($attachment->path);

        if ($contents === null) {
            throw new RuntimeException('Pièce introuvable sur le disque.');
        }

        // Detecte une alteration du fichier depuis son enregistrement.
        if (hash('sha256', $contents) !== $attachment->checksum_sha256) {
            AuditLog::create([
                'actor_id' => $viewer->id,
                'actor_role' => $viewer->role->value,
                'action' => 'identity.checksum_mismatch',
                'auditable_type' => 'request_attachment',
                'auditable_id' => $attachment->id,
                'reason' => 'Empreinte du fichier différente de celle enregistrée.',
                'ip_address' => request()->ip(),
            ]);

            throw new RuntimeException("L'intégrité de cette pièce ne peut pas être vérifiée.");
        }

        return $contents;
    }
}

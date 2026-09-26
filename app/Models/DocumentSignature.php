<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignature extends Model
{
    /** @use HasFactory<DocumentSignatureFactory> */
    use HasFactory;

    protected $fillable = [
        'request_id', 'mayor_id', 'draft_id', 'document_hash', 'verification_code',
        'document_path', 'proof_path', 'legally_binding',
        'provider', 'confirmation_method', 'signature_payload', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_payload' => 'array',
            'legally_binding' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * UNE SIGNATURE NAIT TOUJOURS AVEC SON CODE.
     *
     * Le code est pose par le service de delivrance, avant le rendu du PDF,
     * parce qu'il doit y etre imprime. Mais une signature creee par un autre
     * chemin — un fixture, une reprise de donnees — se heurterait a une
     * colonne obligatoire, et le message serait une erreur SQL plutot qu'une
     * explication. Surtout : une signature SANS code serait un acte
     * inverifiable pour toujours. On ne laisse donc pas ce cas exister.
     */
    protected static function booted(): void
    {
        static::creating(function (self $signature): void {
            if (blank($signature->verification_code)) {
                $signature->verification_code = self::generateVerificationCode();
            }
        });
    }

    /**
     * L'alphabet du code de verification.
     *
     * Ni O ni 0, ni I ni 1 : ce code est lu sur un papier et retape a la main
     * par quelqu'un qui verifie un acte au guichet. Deux caracteres qui se
     * ressemblent transformeraient un acte authentique en « introuvable ».
     */
    public const VERIFICATION_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** Longueur du code, hors separateurs. 12 sur 32 symboles = 60 bits. */
    public const VERIFICATION_LENGTH = 12;

    /**
     * Un code de verification, tire au hasard cryptographique.
     *
     * `random_int` et non `rand` : un code previsible se devine, et deviner un
     * code revient a fabriquer la preuve qu'un acte existe.
     */
    public static function generateVerificationCode(): string
    {
        $alphabet = self::VERIFICATION_ALPHABET;
        $code = '';

        for ($i = 0; $i < self::VERIFICATION_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /**
     * Normalise ce qu'une personne a tape.
     *
     * Elle recopie un code imprime : elle met des espaces, des tirets, des
     * minuscules. Refuser « v7k2 94xa qm3d » quand le code est correct serait
     * refuser un acte authentique pour une question de typographie.
     */
    public static function normalizeVerificationCode(string $saisie): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($saisie))) ?? '';
    }

    /** Le code tel qu'il est imprime sur l'acte, par groupes de quatre. */
    public function formattedVerificationCode(): string
    {
        return implode('-', str_split((string) $this->verification_code, 4));
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function mayor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mayor_id');
    }
}

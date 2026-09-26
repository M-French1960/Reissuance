<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Notifications\RequestAwaitsMayor;
use App\Notifications\RequestStatusChanged;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Seul point d'ecriture de reissuance_requests.status.
 *
 * Aucun controleur, aucune vue, aucun seeder n'ecrit `status` directement.
 * C'est la couche 1 des trois decrites dans docs/STATE_MACHINE.md 4 ; la
 * barriere reelle reste le declencheur MySQL, qui refuse toute
 * transition non autorisee et toute transition sans ligne d'audit.
 */
final class RequestTransitionService
{
    /**
     * Transitions autorisees : [depuis][vers] => role habilite.
     * Doit rester identique a la table allowed_transitions. Un test verifie
     * que les deux ne divergent pas.
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSITIONS = [
        // T13 et T14 : le demandeur retire sa demande, tant que personne ne
        // l'a prise en charge. Voir docs/CAS_USAGE.md 4.5.
        'draft' => ['pending' => 'citizen', 'cancelled' => 'citizen'],
        'pending' => ['under_review' => 'officer', 'cancelled' => 'citizen'],
        'under_review' => [
            'awaiting_signature' => 'officer',
            'rejected' => 'officer',
            'escalated' => 'officer',
        ],
        'awaiting_signature' => [
            'signed' => 'mayor',
            'under_review' => 'mayor',
        ],
        'escalated' => [
            'signed' => 'mayor',
            'rejected' => 'mayor',
            'under_review' => 'mayor',
        ],
    ];

    /**
     * Applique une transition, ou echoue.
     *
     * L'audit est ecrit AVANT la mise a jour, dans la meme transaction : le
     * declencheur exige de le trouver, ce qui rend impossible une transition
     * sans trace.
     */
    public function transition(
        ReissuanceRequest $request,
        RequestStatus $to,
        User $actor,
        ?string $reason = null,
        ?string $ip = null,
    ): ReissuanceRequest {
        $from = $request->status;

        $this->assertAllowed($from, $to, $actor);

        $request = DB::transaction(function () use ($request, $from, $to, $actor, $reason, $ip) {
            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role->value,
                'action' => "request.{$from->value}_to_{$to->value}",
                'auditable_type' => 'reissuance_request',
                'auditable_id' => $request->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'ip_address' => $ip,
            ]);

            $request->forceFill(['status' => $to->value])->save();

            return $request->refresh();
        });

        $this->notify($request, $from);

        return $request;
    }

    /**
     * Previent les interesses, sans jamais mettre la transition en peril.
     *
     * Trois precautions, dans cet ordre :
     *
     *   1. Apres le commit. Une transition annulee ne notifie personne.
     *   2. Mise en file. L'envoi reel a lieu dans le worker ; ce qui se passe
     *      ici n'est qu'une insertion.
     *   3. Enveloppee. Meme cette insertion ne doit pas remonter : une base de
     *      file indisponible annulerait une decision d'officier deja
     *      journalisee et deja appliquee. C'est exactement ce que D-006
     *      interdit. On journalise l'echec, sans donnee personnelle.
     */
    private function notify(ReissuanceRequest $request, RequestStatus $from): void
    {
        try {
            $destinataires = [$request->citizen];

            // Un retour du maire est une consigne de travail : l'officier qui
            // tient le dossier doit l'apprendre autrement qu'en rafraichissant
            // sa file.
            $retourDuMaire = $request->status === RequestStatus::UnderReview
                && in_array($from, [RequestStatus::AwaitingSignature, RequestStatus::Escalated], true);

            if ($retourDuMaire) {
                $destinataires[] = $request->assignedOfficer;
            }

            Notification::send(
                array_filter($destinataires),
                RequestStatusChanged::pour($request, $from),
            );

            /*
             * LE MAIRE EST PREVENU QUAND UN DOSSIER L'ATTEND (D-089).
             *
             * Il ne l'etait pas : la machine a etats posait le dossier en
             * « en attente de signature » ou « escalade », et le SEUL a pouvoir
             * le debloquer devait rafraichir son tableau de bord pour
             * l'apprendre. Un dossier pouvait y dormir sans que rien ne le
             * signale, pendant que le demandeur attendait.
             *
             * Envoi SEPARE, et non un destinataire de plus sur la notification
             * ci-dessus : ses textes sont ecrits pour le demandeur (« votre
             * demande »), et les servir au maire lui ferait lire « votre
             * demande » a propos du dossier d'un tiers.
             *
             * Tous les maires ACTIFS de la commune, car n'importe lequel peut
             * signer : un adjoint doit pouvoir prendre le relais d'un titulaire
             * absent, ce qui est precisement le cas ou l'absence d'alerte coute
             * le plus cher.
             */
            if (in_array($request->status, [RequestStatus::AwaitingSignature, RequestStatus::Escalated], true)) {
                Notification::send(
                    User::query()
                        ->where('role', UserRole::Mayor->value)
                        ->where('commune_id', $request->commune_id)
                        ->where('status', 'active')
                        ->get(),
                    RequestAwaitsMayor::pour($request),
                );
            }
        } catch (Throwable $e) {
            Log::warning('Notification de changement d\'etat non emise.', [
                'request_id' => $request->id,
                'from' => $from->value,
                'to' => $request->status->value,
                'exception' => $e::class,
            ]);
        }
    }

    public function assertAllowed(RequestStatus $from, RequestStatus $to, User $actor): void
    {
        if ($from->isTerminal()) {
            throw new DomainException(
                "Transition interdite : {$from->value} est un état terminal."
            );
        }

        $requiredRole = self::TRANSITIONS[$from->value][$to->value] ?? null;

        if ($requiredRole === null) {
            throw new DomainException(
                "Transition interdite : {$from->value} → {$to->value}."
            );
        }

        if ($actor->role !== UserRole::from($requiredRole)) {
            throw new DomainException(
                "Transition {$from->value} → {$to->value} réservée au rôle {$requiredRole}."
            );
        }
    }
}

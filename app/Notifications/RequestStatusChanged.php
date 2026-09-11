<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\ReissuanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Une demande a change d'etat.
 *
 * Mise en file (ShouldQueue) : l'echec d'une notification ne doit jamais faire
 * echouer ni annuler une transition (D-006). Le travail est repris par le
 * worker, la demande poursuit son cycle.
 *
 * Ce que cette notification ne dit pas : le motif. Un rejet ou un retour porte
 * un motif redige par un agent, qui peut mentionner des elements du dossier.
 * Il reste consultable par le demandeur une fois connecte ; il ne part pas par
 * courriel, ou il quitterait le systeme sans controle (garde-fou n6).
 */
class RequestStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly int $requestId,
        public readonly RequestStatus $from,
        public readonly RequestStatus $to,
    ) {}

    public static function pour(ReissuanceRequest $request, RequestStatus $from): self
    {
        return new self($request->reference, $request->id, $from, $request->status);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        if (filled($notifiable->email ?? null)) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    /**
     * Ce que le destinataire lit dans l'application.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->reference,
            'request_id' => $this->requestId,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'title' => $this->titre(),
            'body' => $this->corps(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Demande {$this->reference} — ".$this->titre())
            ->greeting('Bonjour,')
            ->line($this->corps())
            ->action('Voir ma demande', route('citizen.requests.show', $this->requestId))
            ->line("Ce message est automatique. N'y répondez pas.")
            ->salutation("Le service de réédition d'actes d'état civil");
    }

    private function titre(): string
    {
        return match ($this->to) {
            RequestStatus::Pending => 'Demande reçue',
            RequestStatus::UnderReview => $this->from === RequestStatus::Pending
                ? 'Dossier pris en charge'
                : 'Dossier renvoyé à la vérification',
            RequestStatus::AwaitingSignature => 'Transmis au maire',
            RequestStatus::Escalated => 'Transmis au maire pour examen',
            RequestStatus::Signed => 'Votre acte est disponible',
            RequestStatus::Rejected => 'Demande refusée',
            RequestStatus::Cancelled => 'Demande annulée',
            RequestStatus::Draft => 'Demande en brouillon',
        };
    }

    private function corps(): string
    {
        return match ($this->to) {
            RequestStatus::Pending => "Votre demande {$this->reference} a bien été transmise au centre d'état civil. Vous serez prévenu à chaque étape.",
            RequestStatus::UnderReview => $this->from === RequestStatus::Pending
                ? "Un officier d'état civil a pris en charge votre demande {$this->reference} et procède aux vérifications."
                : "Votre demande {$this->reference} a été renvoyée à l'officier d'état civil pour un complément de vérification.",
            RequestStatus::AwaitingSignature => "Votre demande {$this->reference} a été acceptée par l'officier d'état civil et attend la signature du maire.",
            RequestStatus::Escalated => "Votre demande {$this->reference} a été transmise au maire pour un examen particulier.",
            RequestStatus::Signed => "Votre acte est prêt pour la demande {$this->reference}. Connectez-vous pour le télécharger.",
            RequestStatus::Rejected => "Votre demande {$this->reference} a été refusée. Le motif figure sur la page de votre demande.",
            RequestStatus::Cancelled => "Votre demande {$this->reference} a été annulée. Vous pouvez en déposer une nouvelle à tout moment.",
            RequestStatus::Draft => "Votre demande {$this->reference} est un brouillon.",
        };
    }
}

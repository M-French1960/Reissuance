<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\ReissuanceRequest;
use App\Support\Locales;
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
        /*
         * WRITTEN IN THE RECIPIENT'S LANGUAGE, ONCE (D-076).
         *
         * The title and body are stored with the notification, not rendered on
         * read. A queued job has no session to read a language from, so it
         * reads the account's saved preference. Someone who later switches
         * language keeps their older notifications in the language they were
         * sent in, which is the same rule the certificate follows.
         */
        $langue = Locales::orDefault($notifiable->locale ?? null);

        return [
            'reference' => $this->reference,
            'request_id' => $this->requestId,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'locale' => $langue,
            'title' => $this->titre($langue),
            'body' => $this->corps($langue),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $langue = Locales::orDefault($notifiable->locale ?? null);

        return (new MailMessage)
            ->subject(trans('notifications.mail.subject', [
                'reference' => $this->reference,
                'title' => $this->titre($langue),
            ], $langue))
            ->greeting(trans('notifications.mail.greeting', [], $langue))
            ->line($this->corps($langue))
            ->action(
                trans('notifications.mail.action', [], $langue),
                route('citizen.requests.show', $this->requestId),
            )
            ->line(trans('notifications.mail.automatic', [], $langue))
            ->salutation(trans('notifications.mail.salutation', [], $langue));
    }

    private function titre(string $langue): string
    {
        $cle = $this->to === RequestStatus::UnderReview && $this->from !== RequestStatus::Pending
            ? 'returned'
            : $this->to->value;

        return trans('notifications.titles.'.$cle, [], $langue);
    }

    private function corps(string $langue): string
    {
        $cle = $this->to === RequestStatus::UnderReview && $this->from !== RequestStatus::Pending
            ? 'returned'
            : $this->to->value;

        return trans('notifications.bodies.'.$cle, ['reference' => $this->reference], $langue);
    }
}

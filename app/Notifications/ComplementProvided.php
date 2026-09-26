<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le demandeur a fourni la piece reclamee (D-087).
 *
 * Destinee a L'OFFICIER qui l'a reclamee : sans elle, il devrait rouvrir le
 * dossier au hasard pour savoir si la reponse est arrivee.
 *
 * Elle ne porte ni la piece, ni le message du demandeur : une notification
 * n'est pas un canal de contenu de dossier.
 */
class ComplementProvided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly int $requestId,
        public readonly string $kind,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        if (filled($notifiable->email ?? null)) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $langue = Locales::orDefault($notifiable->locale ?? null);

        return [
            'reference' => $this->reference,
            'request_id' => $this->requestId,
            'kind' => $this->kind,
            'locale' => $langue,
            'title' => trans('notifications.titles.complement_provided', [], $langue),
            'body' => trans('notifications.bodies.complement_provided', [
                'reference' => $this->reference,
            ], $langue),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $langue = Locales::orDefault($notifiable->locale ?? null);

        return (new MailMessage)
            ->subject(trans('notifications.mail.subject', [
                'reference' => $this->reference,
                'title' => trans('notifications.titles.complement_provided', [], $langue),
            ], $langue))
            ->greeting(trans('notifications.mail.greeting', [], $langue))
            ->line(trans('notifications.bodies.complement_provided', [
                'reference' => $this->reference,
            ], $langue))
            ->action(
                trans('notifications.mail.action', [], $langue),
                route('officer.verification.step', ['reissuanceRequest' => $this->requestId, 'step' => 1]),
            )
            ->line(trans('notifications.mail.automatic', [], $langue))
            ->salutation(trans('notifications.mail.salutation', [], $langue));
    }
}

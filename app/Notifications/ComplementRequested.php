<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un officier reclame une piece au demandeur (D-087).
 *
 * CE QUE CETTE NOTIFICATION NE DIT PAS : le motif redige par l'officier. Il
 * est ecrit a la main et peut mentionner des elements du dossier ; il reste
 * lisible une fois connecte, mais ne part pas par courriel, ou il quitterait
 * le systeme sans controle (garde-fou n6). La meme regle que pour un rejet.
 *
 * Mise en file : l'echec d'un envoi ne doit jamais faire echouer l'ouverture
 * du complement (D-006).
 */
class ComplementRequested extends Notification implements ShouldQueue
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
            'title' => trans('notifications.titles.complement_requested', [], $langue),
            'body' => trans('notifications.bodies.complement_requested', [
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
                'title' => trans('notifications.titles.complement_requested', [], $langue),
            ], $langue))
            ->greeting(trans('notifications.mail.greeting', [], $langue))
            ->line(trans('notifications.bodies.complement_requested', [
                'reference' => $this->reference,
            ], $langue))
            ->action(
                trans('notifications.mail.action', [], $langue),
                route('citizen.requests.complement', $this->requestId),
            )
            ->line(trans('notifications.mail.automatic', [], $langue))
            ->salutation(trans('notifications.mail.salutation', [], $langue));
    }
}

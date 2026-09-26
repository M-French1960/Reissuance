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
 * Un dossier attend le maire (D-089).
 *
 * LE MANQUE QUE CELA COMBLE. La machine a etats fait passer un dossier en
 * « en attente de signature » ou « escalade », et personne n'en avertissait le
 * maire — alors qu'il est le SEUL a pouvoir le debloquer. Il devait rafraichir
 * son tableau de bord pour decouvrir du travail. Un dossier pouvait donc y
 * dormir sans que rien ne le signale, et le demandeur attendait.
 *
 * POURQUOI UNE NOTIFICATION A PART, et non celle du changement d'etat. Les
 * textes de `RequestStatusChanged` sont ecrits pour le DEMANDEUR : « votre
 * demande a ete acceptee et attend la signature du maire ». Les envoyer au
 * maire lui ferait lire « votre demande » a propos du dossier d'un tiers.
 *
 * CE QU'ELLE NE PORTE PAS : aucune donnee d'identite. Une reference, et l'etat
 * dans lequel le dossier arrive. Meme regle que partout ailleurs (garde-fou
 * n6) : le contenu du dossier se lit dans l'application, pas dans un courriel.
 */
class RequestAwaitsMayor extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly int $requestId,
        public readonly bool $escalade,
    ) {}

    public static function pour(ReissuanceRequest $request): self
    {
        return new self(
            $request->reference,
            $request->id,
            $request->status === RequestStatus::Escalated,
        );
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

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $langue = Locales::orDefault($notifiable->locale ?? null);

        return [
            'reference' => $this->reference,
            'request_id' => $this->requestId,
            'escalated' => $this->escalade,
            'locale' => $langue,
            'title' => trans($this->cle('titles'), [], $langue),
            'body' => trans($this->cle('bodies'), ['reference' => $this->reference], $langue),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $langue = Locales::orDefault($notifiable->locale ?? null);

        return (new MailMessage)
            ->subject(trans('notifications.mail.subject', [
                'reference' => $this->reference,
                'title' => trans($this->cle('titles'), [], $langue),
            ], $langue))
            ->greeting(trans('notifications.mail.greeting', [], $langue))
            ->line(trans($this->cle('bodies'), ['reference' => $this->reference], $langue))
            ->action(
                trans('notifications.mail.action', [], $langue),
                route('mayor.review', $this->requestId),
            )
            ->line(trans('notifications.mail.automatic', [], $langue))
            ->salutation(trans('notifications.mail.salutation', [], $langue));
    }

    private function cle(string $famille): string
    {
        return "notifications.{$famille}.".($this->escalade ? 'mayor_escalated' : 'mayor_awaiting');
    }
}

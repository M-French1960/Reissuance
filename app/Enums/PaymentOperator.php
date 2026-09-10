<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operateur choisi par le demandeur — les deux specialisations de
 * « Make Payment » au diagramme de cas d'utilisation.
 *
 * HYPOTHESE SIGNALEE : le diagramme dit « Pay Through Mobile Money ». Au
 * Cameroun, « Mobile Money » designe couramment le service de MTN, par
 * opposition a Orange Money. C'est ainsi que je l'ai lu. Si un troisieme
 * operateur est vise, ou si « Mobile Money » designe autre chose, cette
 * enumeration est l'unique endroit a changer.
 *
 * Ce qui n'est PAS encode ici : les prefixes de numero de chaque operateur.
 * Les deviner reviendrait a inventer une regle metier, et un numero mal
 * classe ferait echouer un paiement sans que personne comprenne pourquoi.
 * C'est l'operateur qui reconnait ses propres numeros.
 */
enum PaymentOperator: string
{
    case OrangeMoney = 'orange_money';
    case MtnMobileMoney = 'mtn_mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::OrangeMoney => 'Orange Money',
            self::MtnMobileMoney => 'MTN Mobile Money',
        };
    }

    /** Ce que le demandeur doit saisir, dit dans ses mots. */
    public function hint(): string
    {
        return match ($this) {
            self::OrangeMoney => 'Le numéro Orange depuis lequel vous réglez.',
            self::MtnMobileMoney => 'Le numéro MTN depuis lequel vous réglez.',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}

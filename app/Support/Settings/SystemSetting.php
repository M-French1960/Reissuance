<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * Un reglage tel que l'administration le voit.
 *
 * POURQUOI UNE CLASSE PLUTOT QU'UN TABLEAU. Cet ecran affiche la
 * configuration du systeme, et cette configuration contient des SECRETS : la
 * cle d'index aveugle, les cles de l'agregateur de paiement, le secret de
 * signature des rappels. Un tableau associatif rendrait la fuite possible par
 * simple inattention — il suffirait d'oublier de masquer une valeur.
 *
 * Ici, un reglage sensible se construit par `secret()`, qui ne retient QUE
 * « configure » ou « non configure » et **ne stocke jamais la valeur**. Il n'y
 * a donc rien a masquer a l'affichage, parce qu'il n'y a rien a fuir : la
 * valeur n'entre pas dans l'objet.
 *
 * Voir D-060.
 */
final readonly class SystemSetting
{
    private function __construct(
        public string $label,
        public string $valeur,
        public string $variable,
        public bool $sensible,
        public ?string $alerte = null,
    ) {}

    /** Un reglage ordinaire, dont la valeur s'affiche. */
    public static function ordinaire(
        string $label,
        string|int|bool|null $valeur,
        string $variable,
        ?string $alerte = null,
    ): self {
        return new self(
            $label,
            match (true) {
                $valeur === null, $valeur === '' => 'non défini',
                is_bool($valeur) => $valeur ? 'activé' : 'désactivé',
                default => (string) $valeur,
            },
            $variable,
            false,
            $alerte,
        );
    }

    /**
     * Un secret : on n'en retient que la PRESENCE.
     *
     * La valeur est consommee ici et ne sort pas de cette methode. Aucun
     * appelant ne peut donc l'afficher, meme par erreur.
     */
    public static function secret(string $label, ?string $valeur, string $variable): self
    {
        $configure = $valeur !== null && $valeur !== '';

        return new self(
            $label,
            $configure ? 'configuré' : 'non configuré',
            $variable,
            true,
            $configure ? null : 'Absent : la fonction qui en dépend ne peut pas servir.',
        );
    }
}

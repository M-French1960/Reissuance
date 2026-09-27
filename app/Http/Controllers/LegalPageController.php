<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Conditions, confidentialite, accessibilite (D-098).
 *
 * CE QUE LES MAQUETTES DEMANDAIENT. Les trois pages du dossier cible sont
 * elles-memes VIDES : « Page en cours de redaction. [...] doit etre redigee et
 * validee par un juriste avant la mise en service. » Le client n'attend donc
 * pas de nous un texte de droit — il dit lui-meme qui doit l'ecrire. Le 10 du
 * brief dit la meme chose depuis le debut.
 *
 * CE QUE JE FAIS DE MIEUX QUE LE SUBSTITUT VIDE. Un « page en cours de
 * redaction » n'apprend rien a personne et ne fait pas avancer la redaction.
 * Ces pages portent donc CE QUI EST VERIFIABLE DANS LE CODE : quelles donnees
 * sont collectees, qui voit quoi, ce qui est chiffre, ce que le journal
 * retient, ce que l'acte vaut aujourd'hui. Ce sont des FAITS, pas du droit —
 * un lecteur y apprend quelque chose de vrai, et le juriste qui redigera
 * dispose d'une base factuelle au lieu d'une page blanche.
 *
 * C'est le precedent de D-075 : l'ecran d'inscription dit ce qui est
 * verifiable et ANNONCE ce qui reste a arreter, plutot que de presenter une
 * phrase comme si elle etait la notice.
 *
 * CHAQUE PAGE LE DIT EN HAUT, ET EN AVERTISSEMENT : ce texte n'est pas le
 * document juridique, il n'engage pas l'administration, et il ne remplace pas
 * la redaction d'un juriste.
 */
class LegalPageController extends Controller
{
    /**
     * Les pages, et le nombre de points factuels que chacune porte.
     *
     * La liste est fermee : une page ajoutee sans ses textes leverait ici, et
     * non sur un ecran a demi rendu.
     *
     * @var array<string, array{faits: int, ouvert: int}>
     */
    public const PAGES = [
        'terms' => ['faits' => 5, 'ouvert' => 4],
        'privacy' => ['faits' => 6, 'ouvert' => 4],
        'accessibility' => ['faits' => 5, 'ouvert' => 3],
    ];

    public function __invoke(string $page): View
    {
        if (! array_key_exists($page, self::PAGES)) {
            throw new InvalidArgumentException("Page juridique inconnue : {$page}.");
        }

        return view('public.legal', [
            'page' => $page,
            'faits' => self::PAGES[$page]['faits'],
            'ouvert' => self::PAGES[$page]['ouvert'],
        ]);
    }
}

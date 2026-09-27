<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * L'aide, atteignable sans compte (D-097).
 *
 * POURQUOI ELLE EXISTE ALORS QUE L'ACCUEIL PORTE DEJA UNE FAQ. Il faut
 * parcourir toute la page d'accueil pour l'atteindre, et un lien « Aide » qui
 * renvoie vers une ancre au milieu d'une page de vente ne rend pas service a
 * quelqu'un qui cherche une reponse precise. Ici les questions sont seules sur
 * leur page, et filtrables.
 *
 * ELLE NE DUPLIQUE PAS LE CONTENU. Les questions vivent dans `lang/en/home.php` et son jumeau francais
 * et sont lues par les deux ecrans. Recopier cinq reponses dans un second
 * fichier de langue aurait cree une divergence a la premiere correction — et
 * ce jour est deja arrive : la reponse 4 annoncait une limite que D-087 avait
 * levee.
 *
 * CE QU'ELLE NE PORTE PAS : le formulaire de contact de la maquette. Il n'a
 * aucun destinataire — ni boite de reception, ni ecran pour la lire, ni
 * personne pour repondre — et la maquette porte elle-meme « Reponse sous
 * [delai a definir] », ecrit par le client. Un formulaire qui n'aboutit nulle
 * part est pire que son absence : il promet une reponse que personne
 * n'enverra. La page dit donc quels canaux existent REELLEMENT.
 */
class HelpController extends Controller
{
    /** Les questions vivent dans `home.php` : un seul endroit pour les deux ecrans. */
    public const QUESTIONS = [1, 2, 3, 4, 5];

    public function __invoke(): View
    {
        return view('public.help', ['questions' => self::QUESTIONS]);
    }
}

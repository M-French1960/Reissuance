{{--
    Gabarit commun aux trois documents produits : l'acte, le projet d'acte et
    la preuve de signature.

    AUCUNE RESSOURCE EXTERNE. Pas de feuille de style liee, pas d'image
    distante, pas de police telechargee : le moteur PDF tourne avec l'acces
    reseau desactive (App\Support\Pdf\HtmlToPdf), et une ressource distante
    dans un gabarit serait une requete sortante declenchee par le contenu d'un
    dossier citoyen. Tout est en ligne, ici.

    La police est DejaVu Sans, fournie avec le moteur : elle couvre les
    accents francais sans qu'aucun fichier n'ait a etre livre.

    AUCUNE COULEUR, non plus. Un acte d'etat civil s'imprime en noir sur
    blanc : c'est ce qui sort correctement de n'importe quelle imprimante de
    mairie, y compris une imprimante a encre epuisee. Les filets prennent donc
    `currentColor`. Accessoirement, la regle du 8.3 du brief — aucune couleur
    en dur dans une vue — s'en trouve respectee sans exception a menager.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>@yield('titre')</title>
    <style>
        @page { margin: 20mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.45;
        }

        .bandeau {
            border: 1.5pt solid;
            padding: 6pt 8pt;
            margin-bottom: 10pt;
        }

        .bandeau strong { font-size: 12pt; letter-spacing: 0.3pt; }
        .bandeau p { margin: 3pt 0 0; font-size: 8.5pt; }

        .entete { text-align: center; margin-bottom: 6pt; }
        .entete .republique { font-size: 11pt; font-weight: bold; }
        .entete .devise { font-size: 8.5pt; }
        .entete h1 { font-size: 16pt; margin: 10pt 0 2pt; }
        .entete .sous-titre { font-size: 9.5pt; }

        hr { border: 0; border-top: 0.6pt solid; margin: 8pt 0; }

        h2 { font-size: 11pt; margin: 12pt 0 4pt; }

        /*
            Les champs sont un tableau, et non deux colonnes flottantes : c'est
            ce qui fait qu'une valeur longue — une adresse, un nom compose —
            revient a la ligne dans sa colonne au lieu de sortir de la page.
            C'est precisement le defaut du generateur ecrit a la main qu'il
            remplace (D-067).
        */
        table.champs { width: 100%; border-collapse: collapse; }
        table.champs th {
            width: 38%;
            text-align: left;
            font-weight: bold;
            vertical-align: top;
            padding: 2pt 8pt 2pt 0;
        }
        table.champs td { vertical-align: top; padding: 2pt 0; }

        .mention-finale { margin-top: 14pt; font-size: 8.5pt; }
        .mention-finale strong { font-size: 10pt; }

        .empreinte {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8pt;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
@yield('contenu')
</body>
</html>

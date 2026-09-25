#!/usr/bin/env python3
"""
Allege Fraunces en figeant l'axe de taille optique.

POURQUOI. Le fichier servi par Google porte quatre axes variables, dont
`opsz`, qui pese a lui seul la moitie du fichier : 67 304 octets, l'actif le
plus lourd de tout le projet, sur la PREMIERE page du service. La fluidite sur
reseau contraint est une exigence du client, pas un confort.

CE QUI EST FIGE, ET POURQUOI 36. La page emploie Fraunces de 18 a 65 pixels :
un <h1>, six <h2> autour de 43 px, et des <h3> autour de 19 px. Une valeur de
taille optique unique doit donc servir surtout les <h2>. Les cinq variantes
(origine, 14, 24, 36, 60) ont ete rendues cote a cote et comparees a l'ecran ;
36 tient les grands titres sans amincir les petits, ce qui compte sur un
telephone d'entree de gamme. Le gain est de 50 %.

LICENCE. Fraunces est sous SIL OFL 1.1 et sa notice de droits d'auteur ne
declare AUCUN Reserved Font Name : la modification et la rediffusion sous le
meme nom sont donc permises, a condition que la licence accompagne le fichier.
public/fonts/OFL-fraunces.txt est conserve a cote.

Manrope n'est PAS retouche : y restreindre les graisses ne gagnait que 900
octets, ce qui ne justifie pas de s'ecarter du fichier d'origine.

Usage :
    pip install fonttools brotli
    python3 scripts/fonts-instance.py source.woff2 public/fonts/fraunces-latin.woff2
"""
import sys

from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

TAILLE_OPTIQUE = 36
GRAISSES = (600, 900)


def main(entree: str, sortie: str) -> None:
    police = TTFont(entree)
    instancer.instantiateVariableFont(
        police,
        {"opsz": TAILLE_OPTIQUE, "wght": GRAISSES},
        inplace=True,
    )
    police.flavor = "woff2"
    police.save(sortie)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit(__doc__)
    main(sys.argv[1], sys.argv[2])

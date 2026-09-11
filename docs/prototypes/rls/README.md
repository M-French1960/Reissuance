# Prototype RLS — trace d'une évaluation, **plus maintenu**

> ## ⚠️ Ce prototype ne peut pas être activé
>
> Il est **spécifique à PostgreSQL**. Depuis D-051 la base est MySQL, et
> depuis **D-054 MySQL est le seul moteur maintenu**. MySQL n'a pas de
> sécurité au niveau des lignes : il n'existe pas d'équivalent de
> `CREATE POLICY`, et ce n'est pas une différence de syntaxe à contourner.
>
> Ces fichiers sont conservés comme **trace de l'évaluation**, pas comme
> chemin à suivre. Ils ne sont plus maintenus et ne sont vérifiés par aucun
> test.

Ces deux fichiers portent l'extension `.txt` **à dessein** : ils ne doivent ni
être exécutés par les migrations, ni collectés par la suite de tests. Ils
conservent le prototype construit et mesuré au jalon 6.

| Fichier | Rôle |
|---|---|
| `migration-rls.php.txt` | la migration qui pose les trois politiques |
| `RlsProofTest.php.txt` | le test qui **échoue** sans elles |

## Ce qui tient la place de RLS aujourd'hui

Trois compensations, décrites et éprouvées au §7.3 de `docs/RLS.md` :

1. la portée globale est exercée sans `where` explicite pour les quatre rôles ;
2. l'application refuse de démarrer si la portée n'est pas enregistrée ;
3. un seul point de contournement dans `app/`, et un test structurel l'y
   maintient.

Le risque résiduel qu'elles ne couvrent pas est consigné au §7.5.

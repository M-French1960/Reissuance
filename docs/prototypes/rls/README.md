# Prototype RLS — matériel de référence, non actif

Ces deux fichiers portent l'extension `.txt` **à dessein** : ils ne doivent ni
être exécutés par les migrations, ni collectés par la suite de tests. Ils
conservent le prototype construit et mesuré au jalon 6.

| Fichier | Rôle |
|---|---|
| `migration-rls.php.txt` | la migration qui pose les trois politiques |
| `RlsProofTest.php.txt` | le test qui **échoue** sans elles |

Pour les activer : renommer en `.php` et les déplacer dans
`database/migrations/` et `tests/Feature/`. Lire d'abord `docs/RLS.md` §5 :
seuls, ils ne suffisent pas.

# Comparaison faciale — ce qui est fait, et ce qui l'encadre

> Le diagramme de cas d'utilisation place un acteur **Facial Recognition AI**
> sur « Verify Identity ». J'ai signalé que c'était un traitement biométrique
> et posé cinq questions ; la réponse a été : **la biométrie est obligatoire**.
>
> Elle est donc construite. Ce document dit exactement ce qui est fait, ce qui
> ne l'est pas, et les garde-fous qui tiennent dans le code.

---

## 1. Ce que fait le système

À l'étape 3 de la vérification, l'officier lance un **rapprochement
automatique** entre le selfie du demandeur et la photographie de sa pièce
d'identité. Les deux images sont déjà au dossier ; aucune capture
supplémentaire n'est demandée.

Le service rend un **avis** — correspondance, absence de correspondance,
résultat non concluant, ou service indisponible — accompagné d'un indice de
similarité.

**Le rapprochement est obligatoire** : l'officier ne peut pas conclure sur
l'étape 3 tant qu'il n'a pas eu lieu.

---

## 2. Les quatre garde-fous, et où ils tiennent dans le code

### 2.1 La machine ne décide pas

L'avis est rangé dans une table **séparée** de l'étape de vérification.
L'étape 3 porte la décision de **l'officier**, prise après avoir regardé
lui-même les deux photographies. L'avis y est recopié, de sorte qu'on puisse
plus tard établir sur quoi il s'est prononcé — **et s'il est passé outre**.

Aucun rejet automatique fondé sur un score n'existe, et il n'y a nulle part
dans le code un seuil au-delà duquel une demande serait refusée.

> Test : `un_officier_peut_conclure_a_l_inverse_de_la_machine`.

### 2.2 Une personne non reconnue n'est jamais bloquée

C'est le point qui compte le plus. Un acte d'état civil conditionne l'accès à
presque tout : un faux négatif sans recours serait une **exclusion
administrative**.

`no_match`, `inconclusive` et `unavailable` sont des **résultats enregistrés**.
La vérification est donc complète, et l'officier peut accepter — en motivant,
puisque D-031 rend le motif obligatoire dès qu'une vérification n'aboutit pas
à une correspondance. Le maire voit la réserve et le motif avant de signer.

> Tests : `une_panne_du_service_ne_bloque_pas_la_verification`,
> `un_officier_peut_conclure_a_l_inverse_de_la_machine`.

### 2.3 Aucun gabarit biométrique n'est conservé

La table `facial_comparisons` contient **l'issue et le score**. Pas
d'encodage de visage, pas de vecteur, pas de points caractéristiques, pas
d'image. Les photographies elles-mêmes restent dans `request_attachments`,
avec leur propre rétention et leur propre contrôle d'accès.

> Test : `aucun_gabarit_biometrique_n_est_conserve` — il refuse la présence des
> mots `embedding`, `template`, `descriptor`, `landmarks`, `encoding`, `image`
> et `base64` dans ce qui est stocké.

### 2.4 Le journal ne porte que l'issue

La ligne d'audit dit « une comparaison a eu lieu » et son résultat. Jamais un
numéro de pièce, jamais une identité, jamais une image — garde-fou n°6.

> Test : `le_journal_ne_porte_que_l_issue_de_la_comparaison`.

---

## 3. Ce que le demandeur voit

À l'étape de dépôt des photographies, un encart lui dit, avant qu'il ne
téléverse :

- que ses deux photographies **seront comparées automatiquement** ;
- que cette comparaison **ne décide pas** et que l'officier reste seul juge ;
- que **si elle échoue, sa demande n'est pas refusée pour autant**.

Il n'y a pas de case à cocher : le traitement étant obligatoire, un
consentement qu'on ne peut pas refuser serait un faux consentement. On informe.

---

## 4. Ce qui reste ouvert, et que le code ne peut pas résoudre

Ces questions ne bloquent pas le développement — l'adaptateur factice
fonctionne — mais **elles bloquent une mise en service réelle** :

1. **Quel fondement juridique** autorise un traitement biométrique dans une
   démarche d'état civil au Cameroun, et quelle autorité l'a autorisé ?
2. **Quel prestataire**, avec quelle interface, quel taux d'erreur mesuré, et
   quel engagement contractuel sur ce taux ?
3. **Quelle voie de recours** pour une personne que le système ne reconnaît
   pas de façon répétée ? Le code laisse l'officier passer outre ; il faut
   qu'une procédure le dise aussi, sans quoi la porte technique ne sera pas
   utilisée.
4. **Quelle conservation** pour les photographies elles-mêmes, qui deviennent
   des données biométriques dès lors qu'elles servent à une comparaison ?
5. **Qui répond d'une erreur** — l'officier qui a suivi l'avis, le prestataire
   qui l'a rendu, ou la commune ?

Le taux d'erreur mérite d'être posé chiffré : sur un service qui traite des
milliers de dossiers, **un taux de faux négatifs de 1 % fait des dizaines de
personnes** qui devront insister pour obtenir leur acte. Ce sont rarement les
mieux armées pour le faire.

---

## 5. Adaptateur réel

`BiometricFacialRecognitionProvider` **lève une exception** nommant ce
document. Il ne simule pas un « match ».

Un avis biométrique fabriqué conduirait un officier à délivrer un acte en
croyant qu'une machine a confirmé l'identité. C'est précisément le mécanisme
d'une fraude réussie.

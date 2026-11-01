# Changelog

## 0.12 (2026-11-01)
- Suivi des greffes : bouton « Au printemps » (œil vivant laissé dormant, greffe d'automne) → statut « œil dormant jusqu'au printemps »,
  contrôle le 15 mars (rabattre le porte-greffe, l'œil a-t-il débourré ?). Pendant la dormance, plus de seuil de 21 °C (soudure faite) :
  seulement « gel : protéger l'œil » si la sonde de l'emplacement descend sous 0 °C sur 24 h.
- Réponse tardive au premier contrôle : si J+42 est passé, la question suivante est posée tout de suite.

## 0.11 (2026-11-01)
- Greffe pas encore reprise : la moyenne sur 24 h de la sonde de l'emplacement est comparée à 21 °C (le Poncirus s'endort en dessous)
  et 32 °C, réglables ; « trop froid / trop chaud pour souder » dans la fiche et dans la commande « Greffe à contrôler ».

## 0.10 (2026-11-01)
- Suivi des greffes récentes (« Greffer… », ou « ＋ Plante » greffée depuis moins de 60 jours) : statut « en attente » → « probablement prise » → « reprise »,
  ou « échouée ». Contrôle du bourgeon à J+21 (œil ou bourgeons verts et gonflés, lien retiré) puis à J+42 (débourrement, sinon forcer) ; le statut
  n'avance que sur réponse (Oui / Douteux = revoir dans 7 jours / Non, mort), notée au journal. Contrôle dû : commande « Greffe à contrôler » (lue par le rapport du matin).
- Greffe échouée : « Redevenir porte-greffe… » rend le nom, la collection et la fiche d'avant la greffe.

## 0.9 (2026-11-01)
- Rusticité effective : pénalité de jeunesse pour une plante semée, greffée, bouturée ou marcottée et datée, +6 °C la première année puis de moins
  en moins jusqu'à 0 à 5 ans (réglable dans la configuration) ; affichée dans la fiche (« dont jeunesse »). Achat ou sans date : aucune.

## 0.8 (2026-11-01)
- « Greffer… » : champ « Greffon », choisi parmi nos plantes (nom botanique, cultivar et caractères recopiés) ou écrit à la main ; noté au journal de la greffe.

## 0.7 (2026-11-01)
- Identification : critère « largeur de l'aile » (comparée au limbe), qui départage papeda (combava) et pomelo sans fruit ;
  rempli par la mesure (largeur de l'aile et du limbe, rapport « Aile / limbe (largeur) »).
- Identification : critère « jeunes rameaux » (poilus ou glabres) ; poilus = indice pour le pomelo et le yuzu, contre l'orange et le bigaradier.

## 0.6 (2026-11-01)
- « ＋ Plante » commence par l'emplacement et la collection (au choix, plus seulement celle de la tuile) ; plusieurs photos rangées dans la plante créée.
- « Déplacer… » change aussi la collection (noté au journal, comme tout changement de collection).
- Pl@ntNet : plusieurs photos (5 au plus) en une seule demande, chacune avec son type (feuille, fleur, fruit, écorce, auto) et sa vignette.
- Fenêtres lisibles sur téléphone : plus de champ qui dépasse à droite.

## 0.5 (2026-11-01)
- « Identifier… » dans la fiche d'une plante : Pl@ntNet sur une de ses photos (ou une nouvelle) et clé des caractères partant de la fiche,
  puis un tableau des champs qui changeraient (nom botanique, caractères, rusticité estimée d'après le groupe, écrite « -3 (estimée) »).
  Rien n'est coché d'office : seules les lignes cochées sont écrites, et notées au journal (avant → après, source).

## 0.4 (2026-11-01)
- Bouton « ＋ Plante » sur la tuile et la page plein écran : nom, emplacement, pot, multiplication, porte-greffe, nom botanique.
  Aide à l'identification dans le même formulaire : photo envoyée à Pl@ntNet (« Retenir » remplit le nom botanique) et clé des caractères,
  avec le groupe probable et la rusticité estimée mis à jour à chaque choix. La photo est rangée dans la plante créée.
- Champ « Type de greffe » (écusson en T inversé ou non, chip budding, placage, fente, sous écorce, anglaise, pont, approche, microgreffe…) dans la page du plugin
  et dans « ＋ Plante » (proposé quand la multiplication est « greffe ») ; affiché dans la fiche avec la multiplication.
- « Greffer… » dans la fiche d'une plante (porte-greffe) : même formulaire que « ＋ Plante », la plante devient le greffon sur place (nouveau nom,
  fiche, collection ; journal, photos, pot, emplacement et courbes gardés, sol aussi : ce sont toujours ses racines). Entrée « Greffe » au journal.
- Critère « quartiers » ; rusticité estimée d'après le groupe (nom botanique, sinon caractères observés).

## 0.3 (2026-10-31)
- Mesures par organe (plante, tronc et greffe, épines, feuilles, fleurs, fruits, racines et pot, santé), catalogue fixe ; un relevé = une entrée
  « Mesure » du journal avec ses valeurs. Calculs : aile / limbe, greffon / porte-greffe, Ø moyen, minimal et maximal des fruits.
  Les caractères d'identification vides (aile, longueur de la feuille, taille du fruit) sont remplis par la mesure ; un désaccord est noté au journal.
  Courbe de chaque mesure dans l'onglet Mesures ; les commandes hauteur, tronc, feuilles et fruits restent alimentées.
- Mode de multiplication (semis, greffe, bouture, marcotte, achat) et date : âge de la plante affiché dans la fiche.
- Page plein écran (menu Accueil, à activer dans la configuration) : collections, recherche, températures des emplacements,
  fiche à côté de la liste sur grand écran, plein écran sur téléphone.

## 0.2 (2026-10-29)
- Identification du groupe d'un agrume par ses caractères observés (feuille, fleur, fruit) : groupe probable ou indécis, caractères en désaccord,
  liens Citrus ID et UC Riverside. Jamais la variété. Bergamote rangée avec le bigaradier ; un citron Meyer rond, une bergamote
  ou un petit pamplemousse restent indécis plutôt qu'un faux « probable » ; Poncirus et hybrides de Poncirus restent toujours indécis.
- Marge d'alerte par défaut : 3 °C (au lieu de 2).

## 0.1 (2026-10-29)
- Première version : collections et plantes, fiche, pot et rusticité effective, emplacements historisés, alerte de froid,
  calendrier d'engrais, mesures, photos et identification Pl@ntNet, journal unique (partagé avec Sélénoculture).

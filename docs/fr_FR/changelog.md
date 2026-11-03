# Changelog

## 0.19 (2026-11-03)
- Droits : photo de l'aleurode épineux remplacée (celle de Commons n'avait pas de licence prouvée ; l'OEPP réserve ses photos) par deux photos INRAE du premier foyer français, 2023 (colonie de pupes, adulte) (Rossi et Streito, Licence Ouverte Etalab 2.0).
- Santé, Cochenilles : photo de la cochenille farineuse (Planococcus citri, Khokhlov, CC BY-SA 4.0) ; HLB : psylle adulte tout juste émergé (USGS, domaine public).
- Chaque légende de photo renvoie à sa page source (auteur, licence) ; références complètes de Burkhardt 2018 et du Wiktionnaire ; LICENSE renvoie aux licences des images.
- Corrections : ligne « greffe » de l'onglet Mesures (NaN), phrase de l'emplacement chauffé à 0 °C, saut vers un titre dans la bonne fenêtre de la notice, type de greffe gardé par « Greffer… » côté serveur.

## 0.18 (2026-11-03)
- Notice, onglet **Noms des plantes** : dictionnaire de l'origine des noms botaniques (481 genres et épithètes : nos agrumes, les plantes courantes du jardin, les noms changés depuis 1984), recherche au fil de la frappe (sans accents ni majuscules, anciens noms compris).
- Noms qui ont changé : l'ancien nom (Ferrari 1984) garde son entrée et renvoie au nom accepté aujourd'hui (WCVP lue par GBIF) ; orthographes corrigées retrouvées par l'ancienne graphie.
- Fiche d'une plante : ligne « Origine du nom » tirée du nom botanique saisi et du nom retenu par GBIF (formes -a/-um/-e retrouvées), lien vers le dictionnaire.
- Sources : Ferrari 1984 (cité, jamais recopié), Gentil 1923 et Théis 1810 (domaine public), Burkhardt 2018, Wiktionnaire, GBIF/WCVP, Swingle et Reece 1967.

## 0.17 (2026-11-02)
- Notice, onglet **Botanique** : « Multiplier les agrumes » (semis et pépins à plusieurs embryons, phase juvénile, greffe, bouture, marcotte aérienne ; tableau comparatif ; lien avec l'âge et la jeunesse dans le plugin), sources Curk 2022, UF/IFAS HS1309, de Rocca Serra 1992.
- Correction : « Greffer… » écrit de nouveau l'entrée « Greffe » au journal (perdue depuis la 0.15) ; regreffer une greffe en dormance ou en pousse garde la fiche du vrai porte-greffe.
- « Au printemps » refusé aussi par le serveur hors J+42 ou pour un pont ; un double appui sur « Oui » ne saute plus d'étape.
- Plan d'engrais : plus de « 1 g » d'office quand les doses d'étiquette couvrent déjà le besoin ; dose saisie avec une virgule, case vidée plus re-remplie pendant la frappe.
- Date de multiplication illisible ou future refusée (avant : ni jeunesse ni suivi de greffe, sans message).
- Journal : suppression d'une entrée sur deuxième clic ; « Noter » n'accepte plus le nom ni l'origine venant du navigateur ; tuile et page plein écran rafraîchies après ✓ engrais, « Noter » et « Déplacer » ; cases masquées par la recherche jamais envoyées.
- Fiche : onglet changé pendant le chargement sans mélange ; Pl@ntNet de l'onglet Photos envoyé une fois ; « session expirée ? » au lieu d'une erreur JSON ; flèche des listes repliables ; code de la notice sans débordement sur téléphone.
- Notice : sources complétées (Bailey, UC Ventura, sources non relues de la greffe, Spina et Cutuli 1983, Chypre), faits à une seule source marqués, Kélafer (concentration) et sel d'Epsom (aucune dose sourcée) précisés.

## 0.16 (2026-11-02)
- Notice : onglet **Botanique** (bases : la plante, tige et bourgeons, sous l'écorce, feuille, racine, fleur et fruit, nourrir la plante, noms, multiplier),
  faits sourcés (Shipunov 2021, OpenStax 2018, UF/IFAS, articles sur les agrumes) et « une seule source » indiqués ; gravures d'Atkinson 1905 (domaine public), feuille de Rendle 1911, planche de Köhler 1897, orange légendée (CC BY 2.5).
- Notice, onglet **Santé** : une ou plusieurs photos par problème (20, domaine public, CC0, CC BY ou CC BY-SA, auteur et licence sous chaque photo).

## 0.15 (2026-11-02)
- Géographie : saison de l'engrais mesurée (moyenne 7 jours de l'emplacement ≥ zéro de végétation, 13 °C réglable) en plus de la fenêtre du produit ;
  apport sauté par temps froid rattrapé quand la moyenne remonte (dans la fenêtre) ; hémisphère sud (latitude Jeedom) : fenêtres décalées de 6 mois, contrôle « Au printemps » le 15 septembre ;
  la fiche signale un plan dont les semaines ne suivent plus la saison du produit.
- Greffe « Au printemps » : contrôle avancé (8 semaines au plus, pas avant 4 semaines après la réponse) si l'emplacement est déjà au-dessus du zéro de végétation.
- Greffe : marche à suivre selon le type (œil, rameau sur porte-greffe coupé, latérale/approche, pont) ; nouvelle étape « pousse du greffon »
  (tuteurer, couper le haut du porte-greffe, ôter les rejets ; contrôle 4 semaines après le débourrement ; pont : reprise dès la soudure, sans « Au printemps ») ; en hiver, le contrôle J+42 conseille « Au printemps », au frais, sans engrais.
- Notice : onglet **Greffe** (types, saisons, schémas pas à pas de l'écusson, du chip, de la couronne et de la fente herbacée, gravures de L. H. Bailey 1907-1920 (domaine public) pour l'anglaise, le placage de rameau, la latérale, l'incrustation, la fente, l'approche et le pont, marche à suivre, température, hiver, sources) ; la section du mode d'emploi n'en garde qu'un résumé ; listes numérotées affichées.
- « Greffer… » sans date complète : la date du jour est écrite (avant : J+42 compté depuis le jour de la réponse, ≈ J+63 ; plant pas compté comme jeune pour la rusticité et l'engrais).
  Regreffer une greffe pas prise efface l'ancien suivi et garde la fiche du porte-greffe d'origine pour « Redevenir porte-greffe… ».
- « Au printemps » proposé à J+42 seulement (à J+21 la soudure n'est pas faite : l'alerte « trop froid pour souder » reste).
- Greffe : saison de réalisation selon le type (notice, et rappel sous le type dans « Greffer… » / « ＋ Plante »), sources et « une seule source » indiqués.
- Jeune plant (moins d'un an, ou greffe en cours de suivi) : pas de demi-dose hors saison, même en emplacement chauffé.
- Santé : pays autre que la France → statuts de présence « à vérifier » (pays vide = France) ; hémisphère sud → rappel de décaler les périodes.
- Emplacement chauffé (5ᵉ champ de la ligne) : engrais hors fenêtre à demi-dose (dose annoncée divisée par 2) tant que la moyenne atteint le seuil ; case « Repos hivernal au frais » par plante : jamais d'engrais hors fenêtre, ⚠ si elle est dans un emplacement chauffé de novembre à février.
- Rattrapage limité à la première moitié de l'intervalle (jamais deux doses à moins d'une demi-période) et compté produit par produit ; sonde muette depuis 7 jours : les apports attendent (sonde non historisée = pas de sonde) ; demi-dose calculée ; liste d'engrais coupée au produit près.
- Moyenne 7 jours lue une fois par emplacement ; saisons hors 1-53 refusées ; saison « toute l'année » non décalée.

## 0.14 (2026-11-02)
- Engrais d'un jeune plant (semis, greffe… datés) : besoin réduit à 25 % à 0 an, 100 % à 3 ans (réglable) ; jamais 0 (pas de division par zéro).
- Réglages numériques : virgule et signe − acceptés, texte = valeur par défaut (avant : « 2,5 » lu 2, texte lu 0) ; k et maximum jamais négatifs.
- Notice : méthode « Choisir sa fertilisation », sources Niu 2024 et Melgar 2010, revues adverses.

## 0.13 (2026-11-02)
- Catalogue des produits en stock (configuration du plugin) : % d'azote, intervalle, saison, éléments apportés, remarque (dose de l'étiquette).
  Proposé à l'installation (Ferti Agrumes, corne broyée, sang séché, chélate de fer, sel d'Epsom), pour toutes les plantes : rien n'est donné d'office.
- Fiche, partie « Engrais » : volume du pot, besoin en azote (0,07 g par litre et par semaine en saison, réglable), plan d'azote entre les produits cochés
  (parts en %), dose en grammes ajustable à la main (celle déjà au calendrier est reprise) ; « Écrire dans le calendrier » remplace les lignes de ces produits, garde les autres, et le note au journal. Sans clic, rien n'est écrit.
- Pistes de carence d'après la dernière mesure des feuilles (couleur, feuilles touchées, forme, substrat détrempé) avec les produits en stock qui apportent l'élément.
  Mesures : couleur « V vert à la base, reste jaune » et « bronzé », « Feuilles touchées » (jeunes, vieilles, toutes), « Forme » (normale, petites et étroites, enroulées).

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
  liens Citrus ID et UC Riverside (complétés ensuite par POWO, GBIF, Tela Botanica et PFAF). Jamais la variété. Bergamote rangée avec le bigaradier ; un citron Meyer rond, une bergamote
  ou un petit pamplemousse restent indécis plutôt qu'un faux « probable » ; Poncirus et hybrides de Poncirus restent souvent indécis.
- Marge d'alerte par défaut : 3 °C (au lieu de 2).

## 0.1 (2026-10-29)
- Première version : collections et plantes, fiche, pot et rusticité effective, emplacements historisés, alerte de froid,
  calendrier d'engrais, mesures, photos et identification Pl@ntNet, journal unique (partagé avec Sélénoculture).

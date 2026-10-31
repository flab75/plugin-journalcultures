# Journal de cultures

Noter simplement la vie des plantes de la maison et du jardin, même quand elles changent de place au fil des saisons.

## Principe
- **Collection** (ex. Agrumes, Porte-greffes, Plantes de la maison) : c'est la tuile. Elle affiche le tableau de ses plantes
  (emplacement, température, rusticité effective, engrais à donner, alerte).
- **Plante** : un équipement sans tuile, rangé dans une collection. Il porte la fiche, le pot, l'emplacement et les commandes historisées
  (emplacement, température de l'emplacement, rusticité effective, alerte froid, engrais de la semaine, hauteur, tronc, feuilles, fruits).
- **Journal unique** : toutes les entrées (travaux, déplacements, mesures, engrais) dans une table ; Sélénoculture y lit et y écrit.

## Tuile
- Cliquer une plante : fenêtre **Fiche** (avec liens POWO, GBIF, Tela Botanica, PFAF), **Journal**, **Photos**, **Mesures** (et courbes).
- Administrateurs : cocher des plantes, puis **＋ Noter** (une entrée pour chacune) ou **Déplacer…** (rentrée à la serre en un geste,
  noté au journal) ; **✓** à côté de 🌱 = engrais donné.

## Mesures
Onglet **Mesures** de la fenêtre d'une plante : choisir l'organe, saisir ce qu'on a mesuré (les champs vides sont ignorés ;
plusieurs Ø de fruits séparés par des espaces, virgule décimale : `32 35,5 41`), une note et, au besoin, une photo. Tout part au journal en une entrée « Mesure ».
Rapports calculés : aile / limbe (≥ 0,5 = aile très large, typique des papedas), greffon / porte-greffe (bourrelet, incompatibilité).
Courbes historisées : hauteur, Ø du tronc (greffon, ou tronc d'une plante non greffée), nombre de feuilles, nombre de fruits (si noués, en croissance et mûrs sont tous saisis).
Les mesures complètent les caractères d'identification vides (aile de moins de 2 mm de large = simple liseré ; pas de taille de fruit pour les fruits en doigt ou en main) ; un désaccord est noté au journal.

## Page plein écran
Cocher « Page plein écran dans le menu Accueil » dans la configuration du plugin. Même page sur ordinateur, tablette et téléphone ;
sur grand écran la fiche s'ouvre à côté de la liste. Relue toutes les 5 minutes (bouton ↻ pour tout de suite).

## Configuration du plugin
- **Emplacements** : `Nom | température | humidité | température minimale prévue`, une ligne chacun. Sans sonde, pas d'alerte.
- **Marge** (3 °C) : alerte quand la température mesurée ou prévue descend à la rusticité effective + la marge.
- **Pénalité du pot** : en pot, la rusticité est relevée de `min(maximum, k / e)`, `e` = demi-épaisseur de la motte en cm
  (le plus petit du diamètre et de la hauteur de terre, divisé par 2). Par défaut k = 50 et maximum = 8 °C. Modèle volontairement simple
  (ni durée du froid, ni vent, ni pot isolé) : à ajuster selon vos observations.
- **Clé Pl@ntNet** (facultative) : compte gratuit sur my.plantnet.org, 500 identifications par jour.

## Fiche d'une plante
- **Nom botanique** : vérifié auprès de GBIF à l'enregistrement (nom retenu, famille, statut).
- **Rusticité** : température supportée en pleine terre, en °C (« −7 » ou « -7 (FA5) » acceptés).
- **Pot** : diamètre, hauteur, hauteur de terre, ou pleine terre.
- **Engrais** : `semaines | produit | dose` par ligne, semaines ISO : `10-40/2`, `12,16,20`, `48-8`.
- **Sources et points à vérifier** : où vient chaque information, et ce qui reste à confirmer.

Multiplication (semis, greffe…) et sa date (`2024`, `2024-05` ou `2024-05-12`) : l'âge est affiché dans la fiche
(« ≈ » quand seule l'année est connue).

## Identification (agrumes)
Dans la fiche de la plante, choisir ce qui est observé (folioles, aile du pétiole, boutons floraux, taille, forme, peau et pulpe du fruit…),
laisser « non observé » le reste, puis enregistrer. Le plugin classe les groupes du plus au moins compatible (Poncirus, hybride de Poncirus, kumquat, calamondin,
citron caviar, yuzu, papeda, cédrat, citron, lime, mandarine, orange, bigaradier (dont bergamote), pomelo) et nomme les caractères en désaccord.
Aile « large » = moins de la moitié du limbe, « très large » = la moitié ou plus (papedas seulement).
« Probable » : un seul groupe sans désaccord (au moins 3 accords), tous les autres sont contredits. Groupe seulement, jamais la variété : pour un hybride,
la morphologie ne suffit souvent pas (seul l'ADN tranche). Profils recoupés avec la clé du genre de la Flora of China.
Utile aussi pour un rejet sous la greffe : feuilles à 3 folioles = porte-greffe, à couper ; Poncirus pur et hybrides se ressemblent trop,
le résultat reste indécis entre Poncirus et hybride de Poncirus (la consigne est la même).

## Photos
Gardées dans le dossier `data` du plugin (non servi directement, lues par la tuile après contrôle des droits).
**☆ étiquette** sous une photo (administrateurs) : elle devient la photo de l'étiquette, affichée en tête de la fiche ; **★** la retire.

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

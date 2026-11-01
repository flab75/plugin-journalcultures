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
- Administrateurs : cocher des plantes, puis **＋ Noter** (une entrée pour chacune) ou **Déplacer…** (changer l'emplacement, la collection, ou les deux :
  rentrée à la serre en un geste, noté au journal) ; **✓** à côté de 🌱 = engrais donné.

## Mesures
Onglet **Mesures** de la fenêtre d'une plante : choisir l'organe, saisir ce qu'on a mesuré (les champs vides sont ignorés ;
plusieurs Ø de fruits séparés par des espaces, virgule décimale : `32 35,5 41`), une note et, au besoin, une photo. Tout part au journal en une entrée « Mesure ».
Rapports calculés : aile / limbe en longueur (≥ 0,5 = aile très large, typique des papedas) et en largeur (aile et limbe mesurés au plus large), greffon / porte-greffe (bourrelet, incompatibilité).
Courbes historisées : hauteur, Ø du tronc (greffon, ou tronc d'une plante non greffée), nombre de feuilles, nombre de fruits (le total saisi, sinon la somme si noués, en croissance et mûrs sont tous saisis).
Rempotage : organe « Racines / pot », champs « Nouveau pot » (forme, Ø ou côté, hauteur, hauteur de terre) ou « Pleine terre » : ils remplacent le pot de la fiche,
la rusticité effective est recalculée et l'ancien → le nouveau pot est noté au journal.
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
- **Pénalité de jeunesse** : une plante semée, greffée, bouturée ou marcottée, avec sa date, est moins rustique : rusticité relevée de x °C
  à 0 an, puis linéairement jusqu'à 0 à n ans (par défaut 6 °C et 5 ans : greffe d'un an → +4,8 °C ; date « 2024 » seule = milieu d'année).
  Repères généraux sur les jeunes agrumes (vulgarisation des universités de Floride, de Louisiane et de Géorgie : jeunes arbres
  à protéger dès 0 °C, < 5 ans), pas de chiffre propre aux greffes : à ajuster. Achat ou sans date : aucune pénalité, 0 pour la retirer.
- **Clé Pl@ntNet** (facultative) : compte gratuit sur my.plantnet.org, 500 identifications par jour.

## Fiche d'une plante
- **Nom botanique** : vérifié auprès de GBIF à l'enregistrement (nom retenu, famille, statut).
- **Rusticité** : température supportée en pleine terre, en °C (« −7 » ou « -7 (FA5) » acceptés).
- **Pot** : forme (rond ou carré), diamètre ou côté, hauteur, hauteur de terre, ou pleine terre. Un pot carré de 30 cm de côté compte comme un pot rond de 30 cm de Ø.
- **Engrais** : `semaines | produit | dose` par ligne, semaines ISO : `10-40/2`, `12,16,20`, `48-8`.
- **Sources et points à vérifier** : où vient chaque information, et ce qui reste à confirmer.

Multiplication (semis, greffe…) et sa date (`2024`, `2024-05` ou `2024-05-12`) : l'âge est affiché dans la fiche
(« ≈ » quand seule l'année est connue). Pour une greffe, préciser le type (voir [Greffe](#greffe)).

## Nouvelle plante
**＋ Plante** (tuile ou page plein écran, administrateurs) : d'abord où sera la plante (emplacement) et dans quelle collection, puis nom, nom botanique, cultivar, porte-greffe, rusticité, multiplication
(et type de greffe), pot. Pour trouver le nom botanique : Pl@ntNet (voir plus bas ; « Retenir » remplit le champ), ou la clé des caractères ;
le groupe probable et la rusticité estimée s'affichent à chaque choix. Les photos choisies sont rangées dans la plante créée. Le reste de la fiche se complète dans la page du plugin.

**Pl@ntNet** (« ＋ Plante » et « Identifier… ») : choisir une ou plusieurs photos, donner à chacune son type (feuille, fleur, fruit, écorce, ou auto),
cocher celles à envoyer (5 au plus) puis « Pl@ntNet » : une seule demande pour toutes, plus sûre qu'une photo seule.

## Greffe
**Greffer…** (bas de l'onglet Fiche, administrateurs) : la plante, en général un porte-greffe, devient le greffon. Même formulaire que « ＋ Plante » :
**greffon** choisi dans la liste de nos plantes (les autres collections que celle du porte-greffe : nom botanique, cultivar et caractères recopiés,
nom proposé « greffon sur porte-greffe ») ou écrit à la main (nouveau cultivar), nouveau nom, nom botanique du greffon, collection (par défaut l'actuelle : choisir « Agrumes » pour un porte-greffe), type et date de greffe ;
le porte-greffe est déduit du nom (Poncirus, Flying Dragon, FA5…), modifiable. C'est le **même équipement** : journal, photos, pot, emplacement
et courbes sont gardés, ainsi que le champ « Sol » (ce sont toujours ses racines) ; le reste de la fiche, les caractères observés et la photo
d'étiquette, qui décrivaient le porte-greffe, sont vidés. Une entrée « Greffe » est ajoutée au journal.

**Suivi de la greffe** (après « Greffer… », ou « ＋ Plante » greffée depuis moins de 60 jours avec une date complète) : la fiche montre le statut
(*en attente* → *probablement prise* → *reprise*, ou *échouée*) et la date du prochain contrôle du bourgeon :
J+21 (œil ou bourgeons du greffon verts et gonflés, sans se ratatiner ; retirer le lien), puis J+42 (l'œil a-t-il débourré ? sinon rabattre
le porte-greffe au-dessus pour le forcer ; greffe de rameau : la pousse continue-t-elle ?). Le statut n'avance **que sur réponse** :
**Oui**, **Douteux** (revoir dans 7 jours) ou **Non, mort** (deux clics) ; chaque réponse est notée au journal. Un contrôle dû remplit la commande
« Greffe à contrôler », reprise par le rapport du matin. Délais d'après la vulgarisation des universités de Floride, d'Alabama et de Californie :
le bourgeon, plus que le calendrier, décide. Une greffe échouée reste marquée ; **Redevenir porte-greffe…** rend le nom, la collection
et la fiche d'avant la greffe (gardés par « Greffer… » depuis la 0.10 ; avant, seuls le nom et la collection sont à saisir).
Tant que la greffe n'est pas reprise, la moyenne sur 24 h de la sonde de l'emplacement est comparée aux seuils de la configuration
(21 et 32 °C par défaut) : en dehors, la fiche et la commande « Greffe à contrôler » disent « trop froid (ou trop chaud) pour souder ».
La soudure est la plus rapide vers 21-29 °C ; sous 21 °C le Poncirus s'endort (Alabama ACES), et la greffe attend le printemps.
Il faut que la sonde soit historisée.

Type de greffe : si la multiplication est vide, le choisir la passe à « greffe » ; avec une autre multiplication (semis, bouture…), il est effacé.

| Type | Description |
|---|---|
| **Greffes d'un œil** | *pour multiplier en pépinière* |
| Écusson en T inversé | Un œil prélevé avec un peu d'écorce, glissé sous l'écorce du porte-greffe incisée en T à l'envers (⊥). La plus employée en Floride pour les jeunes plants : bonne reprise, peu d'habileté. |
| Écusson en T | Même chose avec une incision en T droit. Convient aussi, moins employée. |
| Chip budding (œil en copeau) | Un copeau de bois portant l'œil, posé dans une entaille de même forme. Quand l'écorce ne se décolle plus (hors sève) ou est trop épaisse pour l'écusson. Demande un peu plus d'habileté. |
| Chip inversé | Variante du chip, courante sur agrumes, quand l'écorce ne se décolle pas. |
| Placage d'œil | Plaque d'écorce portant l'œil, appliquée à la place d'une plaque identique retirée du porte-greffe. Pour les écorces épaisses. |
| **Greffes de rameau** | *pour changer de variété (surgreffage) ou réparer un arbre* |
| Fente | Le porte-greffe coupé est fendu, un greffon taillé en biseau y est inséré. Sur les charpentières, pour surgreffer. |
| Sous écorce (couronne) | Greffons glissés entre l'écorce et le bois d'une branche coupée, en sève. Surgreffage. |
| Anglaise | Porte-greffe et greffon de même diamètre, coupés en biseau et emboîtés. Jugée inadaptée aux agrumes sur rameaux déjà lignifiés. |
| Pont | Greffons qui enjambent une plaie du tronc pour rétablir la sève. Réparation. |
| Incrustation | Greffon inséré dans une entaille en V du porte-greffe. Renforcer ou réparer. |
| En approche | Deux plantes enracinées soudées côte à côte, séparées après la reprise. Renforcer ou réparer (ex. nouveau porte-greffe au pied d'un arbre). |
| Latérale | Greffon inséré sur le côté du porte-greffe, sans le couper d'abord. |
| Sur souche | Sur une souche recépée, pour repartir d'un vieil arbre. |
| **Laboratoire et pépinière spécialisée** | |
| Microgreffe d'apex in vitro | Pointe de pousse de quelques dixièmes de mm greffée en tube : assainit une variété porteuse de virus (Corse : > 80 % de reprise après regreffage). |
| Minigreffe en fente | Fente sur porte-greffe de 6 mois : 75 % de réussite contre 38 % pour l'écusson en T (essai sur orange 'Valencia'). |

Sources (non relues, synthèse) : UF/IFAS HS1309, Cirad (revue *Fruits*, 1982), guide du développement rural de Polynésie française,
INRAE-Cirad Corse, Carimi 2001, essai colombien 2022.

## Identification (agrumes)
Dans la fiche de la plante, choisir ce qui est observé (folioles, aile du pétiole, boutons floraux, taille, forme, peau et pulpe du fruit…),
laisser « non observé » le reste, puis enregistrer. Le plugin classe les groupes du plus au moins compatible (Poncirus, hybride de Poncirus, kumquat, calamondin,
citron caviar, yuzu, papeda, cédrat, citron, lime, mandarine, orange, bigaradier (dont bergamote), pomelo) et nomme les caractères en désaccord.
Aile « large » = moins de la moitié du limbe, « très large » = la moitié ou plus (papedas seulement), en longueur.
Largeur de l'aile comparée à celle du limbe : presque aussi large ou plus chez les papedas (combava, ichang), nettement plus étroite chez le pomelo —
utile pour un jeune semis sans fruit.
Jeunes rameaux (pousse de l'année, à la loupe) : glabres chez l'orange et le bigaradier ; souvent poilus chez le pomelo, un peu chez le yuzu —
des rameaux poilus comptent pour eux, des rameaux glabres ne les écartent pas.
« Probable » : un seul groupe sans désaccord (au moins 3 accords), tous les autres sont contredits. Groupe seulement, jamais la variété : pour un hybride,
la morphologie ne suffit souvent pas (seul l'ADN tranche). Profils recoupés avec la clé du genre de la Flora of China.
Utile aussi pour un rejet sous la greffe : feuilles à 3 folioles = porte-greffe, à couper ; Poncirus pur et hybrides se ressemblent trop,
le résultat reste indécis entre Poncirus et hybride de Poncirus (la consigne est la même).

**Identifier…** (bas de la fiche, administrateurs) : le même travail sans passer par la page du plugin. Pl@ntNet sur des photos de la plante
(et/ou de nouvelles, non rangées), « Retenir » une espèce, compléter la clé des caractères (cochée d'après la fiche). Un tableau montre
chaque champ qui changerait : valeur actuelle, valeur proposée, source (score Pl@ntNet, nom botanique ou caractères). La rusticité proposée
est celle du groupe, écrite « -3 (estimée) » pour qu'on la distingue d'une valeur sourcée (le groupe part dans la source, au journal) ;
elle n'est proposée que si elle diffère de la fiche, et seulement avec le nom ou les caractères dont elle découle (à cocher aussi). Rien n'est coché d'office ;
**Appliquer** n'écrit que les lignes cochées et ajoute une observation « Identification » au journal (avant → après), pour garder la trace.

## Photos
Gardées dans le dossier `data` du plugin (non servi directement, lues par la tuile après contrôle des droits).
**☆ étiquette** sous une photo (administrateurs) : elle devient la photo de l'étiquette, affichée en tête de la fiche ; **★** la retire.

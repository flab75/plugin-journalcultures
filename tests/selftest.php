<?php
/* Contrôles sans effet sur la box : calculs (rusticité, semaines, engrais, emplacements, alerte), table du journal, données des équipements.
   Usage : sudo -u www-data php tests/selftest.php */
require_once __DIR__ . '/../core/class/journalcultures.class.php';
$ok = function ($v, $m) { if (!$v) { echo "ECHEC : $m\n"; exit(1); } }; // assert() est désactivé en production
$C = 'journalcultures_calc';

$ok($C::nombre('−7 (FA5)') === -7.0 && $C::nombre('-6,5') === -6.5 && $C::nombre('– 15') === -15.0 && $C::nombre('') === null && $C::nombre('?') === null, 'nombre');
$ok($C::rusticite('-16', ['pleine_terre' => true]) === -16.0, 'pleine terre : pas de pénalité');
$ok($C::rusticite('-16', ['diam' => 40, 'haut' => 35, 'terre' => 30]) === -12.7, 'pot 40 × 30 : +3,3 °C');  // e = 15 cm, 50 / 15
$ok($C::rusticite('-16', ['diam' => 40, 'haut' => 35]) === -13.1, 'hauteur de terre absente : hauteur du pot'); // e = 17,5
$ok($C::rusticite('-6', ['diam' => 8, 'haut' => 8]) === 2.0 && $C::rusticite('-6', []) === 2.0, 'petit pot ou inconnu : pénalité maximale');
$ok($C::rusticite('', ['diam' => 40]) === null, 'rusticité inconnue');
$t = mktime(12, 0, 0, 11, 1, 2026);
$ok($C::jeunesse('greffe', '2025-11-01', 6, 5, $t) === 4.8 && $C::jeunesse('semis', '2020', 6, 5, $t) === 0.0 && $C::jeunesse('achat', '2026-10', 6, 5, $t) === 0.0
  && $C::jeunesse('greffe', '', 6, 5, $t) === 0.0 && $C::jeunesse('greffe', '2026-12', 6, 5, $t) === 6.0 && $C::jeunesse('greffe', '2026-02-30', 6, 5, $t) === 0.0, 'pénalité de jeunesse');
$ok($C::controler('attente', 'ok', '2026-09-13', '2026-10-04') == ['probable', '2026-10-25'] && $C::controler('attente', 'ok', '2026-09-13', '2026-11-01') == ['probable', '2026-11-15']
  && $C::controler('probable', 'ok', '2026-09-13', '2026-11-01') == ['reprise', ''] && $C::controler('attente', 'douteux', '2026-09-13', '2026-11-01') == ['attente', '2026-11-08']
  && $C::controler('probable', 'mort', '2026-09-13', '2026-11-01') == ['echouee', ''] && strpos($C::greffeQuestion('attente', 'chip'), 'œil') === 0, 'contrôles de greffe');
$ok($C::greffeTemp(null) === '' && $C::greffeTemp(20.04) === 'trop froid pour souder : 20 °C en moyenne sur 24 h (< 21 °C)' && $C::greffeTemp(25) === ''
  && strpos($C::greffeTemp(33, 21, 32), 'trop chaud') === 0 && $C::greffeTemp(19, 18, 32) === '' && strpos($C::greffeTemp(20.96), ': 20.9 °C') !== false, 'température de la greffe');
try { $C::controler('echouee', 'ok', '2026-09-13', '2026-11-01'); $ok(false, 'greffe échouée : plus de contrôle'); } catch (Exception $e) {}
$ok($C::alerte(-5.0, 1.0, null)['alerte'] === 0 && $C::alerte(-5.0, -3.0, null)['alerte'] === 1 && $C::alerte(-5.0, 4.0, -3.5)['alerte'] === 1, 'alerte : mesurée, prévue');
$ok($C::alerte(null, -10.0, null) === null && $C::alerte(-5.0, null, null) === null, 'alerte : inconnue');
$ok($C::semaines('10-16/2') == [10, 12, 14, 16] && $C::semaines('12,16; 20') == [12, 16, 20] && $C::semaines('52-2') == [1, 2, 52, 53] && $C::semaines('x, 0, 60') == [], 'semaines');
$ok($C::engrais("10-40/2 | Agrumes | 5 g/l\n11 | Fer\n | rien\nfaux", 12) == ['Agrumes 5 g/l'] && $C::engrais("10-40/2 | Agrumes | 5 g/l\n11 | Fer", 11) == ['Fer'], 'engrais de la semaine');
$e = $C::emplacements("Serre | #1# | #2#\nJardin | #3# | | #4#\n\nMaison");
$ok(array_keys($e) == ['Serre', 'Jardin', 'Maison'] && $e['Jardin'] == ['#3#', '', '#4#'] && $e['Maison'] == ['', '', ''], 'emplacements');
$ok($C::texte(" Taille \u{1F345} <b> ", 80) === 'Taille  ‹b›' && $C::texte(['x'], 9) === '', 'texte saisi');
$ok($C::date('2026-10-29') === '2026-10-29', 'date');
$ok(array_keys($C::emplacements(str_repeat('é', 80) . ' | #1#')) == [str_repeat('é', 60)], 'nom d\'emplacement borné à 60');
foreach ((new ReflectionClass('journalcultures'))->getProperties() as $pr) // DB::save prend toute propriété sans « _ » pour une colonne
  $ok($pr->isStatic() || $pr->getDeclaringClass()->getName() != 'journalcultures' || $pr->getName()[0] == '_', 'propriété sans _ : ' . $pr->getName());
try { $C::date('2026-02-31'); $ok(false, 'date invalide acceptée'); } catch (Exception $x) {}
$id = function ($o) use ($C) { $r = $C::identifier($o); return $r ? [$r['verdict'], $r['groupes'][0]['groupe']] : []; };
$r = $C::identifier(['feuillage' => 'caduc', 'folioles' => 'trois', 'peau' => 'duvet']);
$ok($r['verdict'] == 'indecis' && array_column(array_slice($r['groupes'], 0, 2), 'groupe') == ['Poncirus', 'Hybride de Poncirus (citrange, citrumelo, FA5…)'], 'identification : Poncirus ou hybride (rejet)');
$ok($id(['forme' => 'ovale', 'couleur' => 'jaune', 'pulpe' => 'acide', 'pelage' => 'adherente', 'fruit' => '6-10', 'peau' => 'lisse'])[0] == 'indecis', 'identification : citron ou lime sans trancher');
$ok($id(['aile' => 'tres_large', 'peau' => 'rugueuse', 'pulpe' => 'acide', 'folioles' => 'un']) == ['probable', 'Papeda (combava, ichang)'], 'identification : papeda');
$ok($id(['folioles' => true]) === [] && $id(['folioles' => 1.0]) === [], 'identification : non-texte refusé');
$ok($id(['folioles' => 'un', 'gout_peau' => 'sucree', 'fruit' => 'lt3', 'forme' => 'ovale', 'couleur' => 'orange']) == ['probable', 'Kumquat'], 'identification : kumquat');
$ok($id(['boutons' => 'violets', 'forme' => 'ovale', 'couleur' => 'jaune', 'pulpe' => 'acide', 'aile' => 'etroite', 'peau' => 'rugueuse']) == ['probable', 'Citron'], 'identification : citron');
$cal = ['folioles' => 'un', 'fruit' => 'lt3', 'forme' => 'ronde', 'couleur' => 'orange', 'pulpe' => 'acide', 'peau' => 'lisse'];
$ok($id($cal)[0] == 'indecis' && $id(['quartiers' => 'nombreux'] + $cal) == ['probable', 'Calamondin'] && $id(['quartiers' => 'peu'] + $cal) == ['probable', 'Kumquat'], 'identification : kumquat ou calamondin tranché par les quartiers');
$berg = ['feuillage' => 'persistant', 'folioles' => 'un', 'aile' => 'etroite', 'boutons' => 'blancs', 'fruit' => '6-10', 'forme' => 'ronde', 'couleur' => 'jaune', 'peau' => 'lisse', 'pelage' => 'adherente', 'pulpe' => 'acide'];
$r = $C::identifier($berg); $deux = array_column(array_slice($r['groupes'], 0, 2), 'groupe'); sort($deux);
$ok($r['verdict'] == 'indecis' && $deux == ['Bigaradier (orange amère)', 'Lime'], 'identification : bergamote = bigaradier ou lime');
$ok($id(['forme' => 'ovale'] + $berg)[0] == 'indecis', 'identification : bergamote en poire, pas de faux « lime »');
$ok($id(['boutons' => 'violets', 'forme' => 'ronde', 'couleur' => 'jaune', 'peau' => 'lisse', 'pulpe' => 'acide', 'fruit' => '6-10', 'pelage' => 'adherente', 'aile' => 'etroite'])[0] == 'indecis', 'identification : citron Meyer rond, pas de faux « lime »');
$ok($id(['aile' => 'large', 'fruit' => '6-10', 'couleur' => 'jaune', 'forme' => 'ronde', 'boutons' => 'blancs', 'pelage' => 'adherente', 'pulpe' => 'acide', 'gout_peau' => 'amere'])[0] == 'indecis', 'identification : petit pamplemousse, pas de faux « bigaradier »');
$ok($id(['fruit' => '3-6', 'couleur' => 'verte'] + $berg) == ['probable', 'Lime'], 'identification : lime de Tahiti 3-6 cm');
$ok($id(['folioles' => '1']) === [] && $id(['fruit' => '<3']) === [], 'identification : anciens codes refusés');
$ok($id(['aile' => 'aucune', 'pulpe' => 'peu', 'forme' => 'main', 'boutons' => 'violets']) == ['probable', 'Cédrat (dont Main de Bouddha)'], 'identification : main de Bouddha');
$ok($id(['folioles' => 'mix'])[0] == 'indecis' && $id([]) === [] && $id(['aile' => 'xx', 'fruit' => ['3-6']]) === [], 'identification : indécis, vide, codes invalides');
foreach ($C::GROUPES as $g => $prof) foreach ($prof as $k => $codes) foreach ($codes as $c) $ok(isset($C::CARACTERES[$k][1][$c]), "profil $g : $k = $c inconnu");
foreach ($C::INDICES as $g => $prof) foreach ($prof as $k => $codes) foreach ($codes as $c) $ok(isset($C::GROUPES[$g]) && !isset($C::GROUPES[$g][$k]) && isset($C::CARACTERES[$k][1][$c]), "indice $g : $k = $c inconnu ou déjà au profil");
$ok(array_keys($C::RUSTICITE_GROUPES) == array_keys($C::GROUPES) && !array_diff($C::BOTA, array_keys($C::GROUPES)), 'rusticité et noms botaniques : groupes connus');
$g = function ($b) use ($C) { $e = $C::estimee($b, []); return $e ? $e['groupes'][0] : null; };
foreach (['Citrus reshni × Poncirus trifoliata' => 'Hybride de Poncirus (citrange, citrumelo, FA5…)', "Poncirus trifoliata 'Flying Dragon'" => 'Poncirus',
  'Citrus × floridana (limequat)' => 'Lime', 'Citrus microcarpa' => 'Calamondin', 'Fortunella obovata × margarita' => 'Kumquat', 'Microcitrus australasica (Finger lime)' => 'Citron caviar (finger lime)',
  "Citrus junos ‘Yuzu n°1’" => 'Yuzu', 'Citrus medica var. sarcodactylis' => 'Cédrat (dont Main de Bouddha)', 'Citrus × bergamia' => 'Bigaradier (orange amère)',
  'citrus×limon' => 'Citron', 'Citrus latifolia' => 'Lime', "Citrus x tangor 'Shiranui'" => 'Mandarine', 'Citrus sinensis' => 'Orange douce', 'Citrus maxima' => 'Pomelo, pamplemousse',
  'Citrus aurantiifolia' => 'Lime', '' => null, 'Ficus carica' => null] as $b => $att) $ok($g($b) === $att, "nom botanique « $b » → $att");
$e = $C::estimee('', ['folioles' => 'un', 'fruit' => '6-10', 'couleur' => 'jaune']);
$ok($e['par'] == 'caracteres' && $e['r'] == max(array_intersect_key($C::RUSTICITE_GROUPES, array_flip($e['groupes']))) && count($e['groupes']) > 3, 'rusticité estimée : le moins rustique des groupes possibles');
$ok($C::estimee('Citrus sinensis', ['feuillage' => 'caduc', 'folioles' => 'trois'])['groupes'] == ['Orange douce'] && $C::estimee('', ['aile' => 'xx']) === null, 'rusticité estimée : le nom passe avant les caractères ; rien de reconnu');
$now = strtotime('2026-10-31');
$ok($C::age('2024', $now) === '≈ 2 ans' && $C::age('2026', $now) === 'moins d’un an' && $C::age('2026-03', $now) === '7 mois' && $C::age('2025-10-12', $now) === '1 an' && $C::age('2026-10', $now) === 'moins d’un mois', 'âge');
$ok($C::age('2027', $now) === null && $C::age('2024-13', $now) === null && $C::age('2024-05-99', $now) === null && $C::age('2024-02-29', $now) === '2 ans' && $C::age('vers 2024', $now) === null && $C::age('', $now) === null, 'âge illisible ou à venir');
$v = $C::valeurs(['feuilles.limbe_l' => '80', 'feuilles.aile_l' => '45,5', 'fruits.diametres' => '32; 35 41', 'pot.substrat' => 'frais', 'plante.hauteur' => '', 'x.y' => '3']);
$ok($v === ['feuilles.limbe_l' => 80.0, 'feuilles.aile_l' => 45.5, 'fruits.diametres' => [32.0, 35.0, 41.0], 'pot.substrat' => 'frais'], 'valeurs saisies');
foreach ([['plante.hauteur' => '-1'], ['plante.hauteur' => 'abc'], ['pot.substrat' => 'mouillé'], ['pot.forme' => 'ovale'], ['fruits.diametres' => '3, x'], ['plante.hauteur' => ['1']]] as $faux)
  try { if ($C::valeurs($faux)) $ok(false, 'valeur invalide acceptée : ' . json_encode($faux)); } catch (Exception $x) {}
$v += $C::calculs($v);
$ok($v['calc.aile_limbe'] === 0.57 && $v['calc.fruits_moy'] === 36.0 && $v['calc.fruits_min'] === 32.0 && $v['calc.fruits_max'] === 41.0 && !isset($v['calc.greffon_pg']), 'calculs des mesures');
$ok($C::suggestions($v) == ['aile' => 'tres_large', 'feuille' => 'moyenne', 'fruit' => '3-6'], 'suggestions d\'identification');
$ok($C::suggestions(['calc.aile_limbe' => 0.0, 'feuilles.limbe_l' => 120.0]) == ['aile' => 'aucune', 'feuille' => 'grande'], 'suggestions : sans aile, grande feuille');
$w = $C::calculs(['feuilles.aile_w' => 30.0, 'feuilles.limbe_w' => 36.0]);
$ok($w == ['calc.aile_limbe_w' => 0.83] && $C::suggestions($w + ['feuilles.aile_w' => 30.0]) == ['aile_largeur' => 'presque'] && $C::suggestions(['calc.aile_limbe_w' => 0.4, 'feuilles.aile_w' => 1.0]) == ['aile' => 'aucune'], 'largeur de l\'aile : calcul et suggestion');
$ok($id(['aile' => 'large', 'aile_largeur' => 'presque', 'folioles' => 'un'])[1] != 'Pomelo, pamplemousse' && in_array('Largeur de l’aile (comparée au limbe)', $C::identifier(['aile_largeur' => 'moins'], 20)['groupes'][array_search('Papeda (combava, ichang)', array_column($C::identifier(['aile_largeur' => 'moins'], 20)['groupes'], 'groupe'))]['contre']), 'largeur de l\'aile : pomelo et papeda départagés');
$ch = ['folioles' => 'un', 'aile' => 'large', 'feuille' => 'grande'];
$acc = function ($c, $g) use ($C) { foreach ($C::identifier($c, 20)['groupes'] as $x) if ($x['groupe'] == $g) return [$x['accords'], count($x['contre'])]; };
$ok($acc($ch + ['rameaux' => 'poilus'], 'Pomelo, pamplemousse') == [4, 0] && $acc($ch + ['rameaux' => 'glabres'], 'Pomelo, pamplemousse') == [3, 0]
  && $acc($ch + ['rameaux' => 'poilus'], 'Bigaradier (orange amère)')[1] == 1 && $acc($ch + ['rameaux' => 'poilus'], 'Yuzu') == [3, 0], 'jeunes rameaux : indice pomelo/yuzu, contre bigaradier');
$ok($C::suggestions(['calc.aile_limbe' => 0.1, 'feuilles.aile_w' => 1.0, 'calc.fruits_moy' => 20.0], 'doigt') == ['aile' => 'aucune'], 'suggestions : simple liseré, fruit en doigt');
$ok($C::valeurs(['fruits.diametres' => '32,5 41'])['fruits.diametres'] === [32.5, 41.0] && $C::valeurs(['fruits.diametres' => ' ; ']) === [] && $C::valeurs(['plante.vigueur' => '3']) === ['plante.vigueur' => 3], 'liste : virgule décimale, liste vide ; note 1 à 5 en nombre');
$ok($C::valeurs(['fruits.nombre' => '12', 'pot.diam' => '40', 'pot.terre' => '32,5', 'pot.pleine_terre' => 'oui']) === ['fruits.nombre' => 12.0, 'pot.diam' => 40.0, 'pot.terre' => 32.5, 'pot.pleine_terre' => 'oui']
  && $C::rusticite('-6', ['diam' => 40.0, 'terre' => 32.5]) === -2.9, 'nombre de fruits, nouveau pot en nombres');
$ok($C::valeurs(['pot.forme' => 'carré']) === ['pot.forme' => 'carré'], 'forme du pot');
$ok(strpos($C::resume($v), 'Feuilles : Limbe : longueur 80 mm, Aile : longueur 45,5 mm') === 0 && strpos($C::resume($v), 'Aile / limbe 0,57') !== false, 'résumé du relevé');
foreach ($C::MESURES as $o => [$t, $ch]) foreach ($ch as $k => $f) $ok(in_array($f[0], ['n', 'l', 'c'], true) && ($f[0] != 'c' || is_array($f[2])) && strpos("$o$k", '.') === false, "catalogue : $o.$k");
foreach ($C::suggestions(['calc.aile_limbe' => 0.3, 'feuilles.limbe_l' => 40.0, 'calc.fruits_moy' => 120.0]) as $k => $c) $ok(isset($C::CARACTERES[$k][1][$c]), "suggestion $k = $c inconnue");
$n = $C::nouvelle(['bota' => ' Citrus limon ', 'cultivar' => '', 'multiplication' => 'greffe', 'pot_diam' => '30', 'pot_haut' => '0', 'pot_forme' => 'carre', 'pleine_terre' => '',
  'emplacement' => 'Serre', 'carac' => ['folioles' => 'un', 'aile' => 'xx'], 'ident' => 'triche'], ['Serre']);
$ok($n == ['bota' => 'Citrus limon', 'multiplication' => 'greffe', 'pot_diam' => 30.0, 'pot_forme' => 'carre', 'emplacement' => 'Serre', 'id_folioles' => 'un'], 'nouvelle plante : champs gardés, vides et invalides écartés');
$ok($C::nouvelle(['multiplication' => 'vol', 'carac' => 'x'], []) === [], 'nouvelle plante : multiplication hors liste, caractères illisibles');
try { $C::nouvelle(['emplacement' => 'Cave'], ['Serre']); $ok(false, 'nouvelle plante : emplacement inconnu accepté'); } catch (Exception $x) {}
$ok($C::greffe(['type_greffe' => 'fente']) == ['type_greffe' => 'fente', 'multiplication' => 'greffe'] && $C::greffe(['multiplication' => 'semis', 'type_greffe' => 'fente']) == ['multiplication' => 'semis']
  && $C::greffe(['multiplication' => 'greffe', 'type_greffe' => 'x']) == ['multiplication' => 'greffe'], 'type de greffe : implique « greffe », écarté sinon ou hors liste');
[$c, $l] = $C::accepter(['bota' => 'Citrus limon', 'id_folioles' => 'un', 'rusticite' => ''],
  ['bota' => 'Citrus limon', 'rusticite' => '-3 (estimée : citron)', 'id_folioles' => 'trois', 'id_aile' => 'xx', 'id_pulpe' => '', 'gbif' => 'triche'], ['rusticite' => 'clé']);
$ok($c == ['rusticite' => '-3 (estimée : citron)', 'id_folioles' => 'trois'] && $l == ['Rusticité : – → -3 (estimée : citron) (clé)', 'Folioles par feuille : 1 → 3'],
  'identification acceptée : inchangé, vide, invalide et hors liste écartés');
echo "calculs OK\n";

$t = DB::Prepare("SHOW TABLES LIKE 'journalcultures_journal'", [], DB::FETCH_TYPE_ROW);
if ($t) {
  $ok((bool)DB::Prepare("SHOW COLUMNS FROM journalcultures_journal LIKE 'valeurs'", [], DB::FETCH_TYPE_ROW), 'colonne valeurs (lancer la mise à jour du plugin)');
  journalcultures::journal(null, 1);
  echo "table OK\n";
} else echo "table absente (plugin pas installé)\n";

foreach (eqLogic::byType('journalcultures') as $eq) {
  $d = $eq->type() == 'collection' ? $eq->donnees() : $eq->etat();
  $ok(json_encode($d, JSON_HEX_TAG) !== false, 'données de ' . $eq->getHumanName());
  echo 'données OK : ' . $eq->getHumanName() . "\n";
}
echo "TOUT OK\n";

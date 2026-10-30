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
echo "calculs OK\n";

$t = DB::Prepare("SHOW TABLES LIKE 'journalcultures_journal'", [], DB::FETCH_TYPE_ROW);
if ($t) {
  journalcultures::journal(null, 1);
  echo "table OK\n";
} else echo "table absente (plugin pas installé)\n";

foreach (eqLogic::byType('journalcultures') as $eq) {
  $d = $eq->type() == 'collection' ? $eq->donnees() : $eq->etat();
  $ok(json_encode($d, JSON_HEX_TAG) !== false, 'données de ' . $eq->getHumanName());
  echo 'données OK : ' . $eq->getHumanName() . "\n";
}
echo "TOUT OK\n";

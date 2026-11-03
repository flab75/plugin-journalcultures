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
$ok($C::facteurJeune('greffe', '2025-11-01', 0.25, 3, $t) === 0.5 && $C::facteurJeune('semis', '2020', 0.25, 3, $t) === 1.0 && $C::facteurJeune('achat', '2026-10', 0.25, 3, $t) === 1.0
  && $C::facteurJeune('greffe', '2026-12', 0.25, 3, $t) === 0.25 && $C::facteurJeune('greffe', '2026-12', 1, 3, $t) === 1.0 && $C::facteurJeune('greffe', '2026-12', 0.25, 0, $t) === 1.0
  && $C::facteurJeune('greffe', '2026-12', 0, 3, $t) === 0.01 && $C::facteurJeune('greffe', '2026-12', -1, 3, $t) === 0.01, 'engrais d\'un jeune plant');
$ok($C::controler('attente', 'ok', '2026-09-13', '2026-10-04') == ['probable', '2026-10-25'] && $C::controler('attente', 'ok', '2026-09-13', '2026-11-01') == ['probable', '2026-11-01']
  && $C::controler('probable', 'ok', '2026-09-13', '2026-11-01') == ['pousse', '2026-11-29'] && $C::controler('pousse', 'ok', '2026-09-13', '2026-12-01') == ['reprise', ''] && $C::controler('attente', 'douteux', '2026-09-13', '2026-11-01') == ['attente', '2026-11-08']
  && $C::controler('probable', 'mort', '2026-09-13', '2026-11-01') == ['echouee', '']
  && $C::controler('probable', 'printemps', '2026-09-13', '2026-11-01') == ['dormant', '2027-03-15'] && $C::controler('attente', 'printemps', '2026-02-01', '2026-02-20') == ['dormant', '2026-03-15']
  && $C::controler('dormant', 'ok', '2026-09-13', '2027-03-20') == ['pousse', '2027-04-17'] && strpos($C::greffeQuestion('pousse', 'chip'), 'tuteurer') !== false && strpos($C::greffeQuestion('pousse', 'fente'), 'lien retiré') !== false && strpos($C::greffeQuestion('probable', 'approche'), 'sevrer') !== false && strpos($C::greffeQuestion('attente', 'pont'), 'pont') !== false && strpos($C::greffeQuestion('pousse', ''), 'rejets') !== false && $C::greffeQuestion('reprise', 'chip') === '' && $C::controler('dormant', 'douteux', '2026-09-13', '2027-03-15') == ['dormant', '2027-03-22'] && strpos($C::greffeQuestion('attente', 'chip'), 'œil') === 0 && count(array_filter(array_keys($C::GREFFES), function ($k) use ($C) { return $C::greffeSaison($k) === ''; })) == 0 && $C::greffeSaison('x') === '' && strpos($C::greffeSaison('fente'), 'règle générale') !== false, 'contrôles de greffe');
$ok($C::greffeTemp(null) === '' && $C::greffeTemp(20.04) === 'trop froid pour souder : 20 °C en moyenne sur 24 h (< 21 °C)' && $C::greffeTemp(25) === ''
  && strpos($C::greffeTemp(33, 21, 32), 'trop chaud') === 0 && $C::greffeTemp(19, 18, 32) === '' && strpos($C::greffeTemp(20.96), ': 20,9 °C') !== false && $C::greffeGel(null) === '' && $C::greffeGel(0.4) === '' && strpos($C::greffeGel(-1.25), '-1,3 °C') !== false, 'température de la greffe');
$ok($C::controler('probable', 'printemps', '2026-09-13', '2026-11-01', true) == ['dormant', '2027-09-15'] && $C::controler('attente', 'printemps', '2026-03-01', '2026-04-10', true) == ['dormant', '2026-09-15'], 'printemps : 15 septembre dans l\'hémisphère sud');
$ok($C::decaler('10-40', 26) === '36-14' && $C::decaler('6-44', 26) === '32-18' && $C::decaler('1-53', 26) === '1-53' && $C::decaler('53-1', 26) === '27-27' && in_array(1, $C::semaines('36-14')) && !in_array(20, $C::semaines('36-14')), 'saison décalée de 6 mois (hémisphère sud)');
$ok($C::pousse(null, 13) === null && $C::pousse(12.9, 13) === false && $C::pousse(13.0, 13) === true && $C::pousse(false, 13) === false, 'zéro de végétation : null sans sonde, en dessous = au repos');
try { $C::controler('echouee', 'ok', '2026-09-13', '2026-11-01'); $ok(false, 'greffe échouée : plus de contrôle'); } catch (Exception $e) {}
$ok($C::alerte(-5.0, 1.0, null)['alerte'] === 0 && $C::alerte(-5.0, -3.0, null)['alerte'] === 1 && $C::alerte(-5.0, 4.0, -3.5)['alerte'] === 1, 'alerte : mesurée, prévue');
$ok($C::alerte(null, -10.0, null) === null && $C::alerte(-5.0, null, null) === null, 'alerte : inconnue');
$ok($C::semaines('10-16/2') == [10, 12, 14, 16] && $C::semaines('12,16; 20') == [12, 16, 20] && $C::semaines('52-2') == [1, 2, 52, 53] && $C::semaines('x, 0, 60') == [], 'semaines');
$ok($C::engraisDus("10-40/8 | Ferti | 40 g\n12,16 | Fer", 11) == [['Ferti 40 g', 1, false, 'Ferti']] && $C::engraisDus("10-40/8 | Ferti", 18) == [['Ferti', 0, false, 'Ferti']]
  && $C::engraisDus("10-40/8 | Ferti", 41) == [] && $C::engraisDus("48-8/4 | X", 53) == [['X', 1, false, 'X']] && $C::engraisDus("48-8/4 | X", 1) == [] && $C::engraisDus("48-8/4 | X", 2) == [] && $C::engraisDus("10-40/8 | F", 15) == [] && $C::engraisDus("12,16 | Fer", 13) == [], 'apports dus, rattrapage limité à la moitié de l\'intervalle');
$ok($C::engraisDus("10-40/8 | Ferti | 40 g\n12,16 | Fer", 45, true) == [['Ferti 20 g (demi-dose, hors saison)', 3, true, 'Ferti']] && $C::engraisDus("10-40/8 | Ferti", 5, true) == [['Ferti (demi-dose, hors saison)', 0, true, 'Ferti']] && $C::engraisDus("10-40/8 | F | 7,5 g", 42, true)[0][0] === 'F 3,8 g (demi-dose, hors saison)'
  && $C::engraisDus("10-40/8 | Ferti", 11, true) == [['Ferti', 1, false, 'Ferti']] && $C::emplacements("SdB | a | | | chauffé\nSerre | b")['SdB'][3] === true && $C::emplacements("Serre | b | | | non")['Serre'][3] === false, 'emplacement chauffé : apports toute l\'année à demi-dose');
$ok($C::volume(['diam' => 20, 'terre' => 20]) === 6.3 && $C::volume(['diam' => 20, 'haut' => 20, 'carre' => true]) === 8.0 && $C::volume(['diam' => 30, 'pleine_terre' => true]) === null && $C::volume(['diam' => 30]) === null, 'volume du pot');
$P = $C::produits($C::PRODUITS);
$ok(count($P) == 5 && $P[0]['n'] == 7.0 && $P[0]['tous'] == 8 && $P[0]['saison'] == '10-40' && $P[0]['apporte'] == ['N', 'P', 'K'] && $P[3]['apporte'] == ['FE'] && $P[4]['apporte'] == ['MG', 'S'], 'catalogue proposé');
// 10 L, 0,07 g N/L/sem., Ferti 7 % toutes les 8 semaines : 10 × 0,07 × 8 / 0,07 = 80 g (bas de l'étiquette, 80 à 240 g tous les 2 mois)
$ok($C::plan(10, $P, [0 => 100]) == [['i' => 0, 'g' => 80.0, 'ligne' => '10-40/8 | Ferti Agrumes 7-3-10 | 80 g']], 'plan : 100 % Ferti Agrumes');
$ok($C::plan(10, $P, [0 => 100], 0.07 * 0.5)[0]['g'] == 40.0, 'plan d\'un jeune plant : moitié');
$x = $C::plan(10, $P, [0 => 50, 1 => 50, 2 => 50, 3 => 100], 0.07 * 0.25); // besoin 0,175 g N/sem., corne 0,108 → Ferti 20 × 0,38 ≈ 8 g
$ok(count($x) == 2 && $x[0]['g'] == 8.0 && $x[1]['ligne'] == '10-40/12 | Corne broyée | 10 g', 'jeune plant : corne gardée à l\'étiquette, Ferti réduit');
$x = $C::plan(10, $P, [0 => 50, 1 => 50, 2 => 50, 3 => 100]); // sang séché ponctuel et Kélafer sans azote : jamais dans le plan
// corne à dose d'étiquette (1 g/L) : 10 g, 10 × 1 × 13 % / 12 sem. = 0,108 g N/sem. sur 0,7 → Ferti : 80 × (1 − 0,155) ≈ 68 g
$ok(count($x) == 2 && $x[0]['g'] == 68.0 && $x[1]['ligne'] == '10-40/12 | Corne broyée | 10 g' && $C::plan(null, $P, [0 => 100]) === [] && $C::plan(10, $P, []) === [], 'plan partagé, corne à dose d\'étiquette, rien de coché');
$Q = $C::produits("A | 10 | 4 | 10-40 | N\nB | 10 | 4 | 10-40 | N | | x");
$ok($Q[1]['gl'] == 0.0 && array_column($C::plan(10, $Q, [0 => 30, 1 => 70]), 'g') == [8.0, 20.0] && $C::plan(10, $C::produits("C | 10 | 4 | 10-40 | N | | 20"), [0 => 100])[0]['g'] == 200.0, 'plan : parts sans étiquette, étiquette seule');
$ok($C::plan(10, $P, [0 => 100], 0.07, [0 => 60]) == [['i' => 0, 'g' => 60.0, 'ligne' => '10-40/8 | Ferti Agrumes 7-3-10 | 60 g']] && $C::plan(10, $P, [0 => 100], 0.07, [0 => 12.5])[0]['ligne'] == '10-40/8 | Ferti Agrumes 7-3-10 | 12,5 g', 'plan : dose ajustée à la main');
$ok($C::semaines(explode(' | ', $x[0]['ligne'])[0]) == [10, 18, 26, 34], 'ligne du plan lisible par le calendrier');
$ok(array_column($C::plan(10, $P, [0 => 50, 1 => 50], 0.01), 'i') == [1], 'plan : besoin couvert par la corne, pas de 1 g de Ferti d\'office');
$ok(array_column($C::carences(['feuilles.couleur' => 'jaunissement entre les nervures', 'feuilles.touchees' => 'jeunes', 'pot.substrat' => 'détrempé']), 'el') == ['EAU', 'FE']
  && array_column($C::carences(['feuilles.couleur' => 'jaunissement entre les nervures', 'feuilles.touchees' => 'vieilles']), 'el') == ['MG']
  && array_column($C::carences(['feuilles.couleur' => 'jaunissement entre les nervures']), 'el') == ['?']
  && array_column($C::carences(['feuilles.couleur' => 'jaune uniforme', 'feuilles.forme' => 'petites et étroites']), 'el') == ['N', 'ZN']
  && $C::carences(['feuilles.couleur' => 'vert foncé']) === [] && $C::carences([]) === [], 'pistes de carence');
$ok(array_column($C::sante(['sante.signe' => 'feuilles collantes (miellat)', 'sante.ravageur' => 'cochenille', 'tronc.ecorce' => 'gomme'], false), 'id') == ['gommose', 'cochenille', 'aleurode', 'puceron']
  && isset($C::sante(['sante.signe' => 'rameaux qui sèchent depuis la pointe'], false)[1]['note']) && !isset($C::sante(['sante.signe' => 'rameaux qui sèchent depuis la pointe'], true)[1]['note'])
  && $C::sante(['sante.signe' => 'aucun', 'sante.ravageur' => 'autre'], true) === [] && $C::sante([], false) === [], 'pistes de santé');
$ok(!array_diff(array_merge(...array_values($C::SIGNES)), array_keys($C::SANTE)) && !array_diff(array_keys($C::SIGNES), $C::MESURES['sante'][1]['signe'][2])
  && !array_diff(array_diff($C::MESURES['sante'][1]['ravageur'][2], ['aucun', 'autre']), array_keys($C::SANTE))
  && !array_filter($C::SANTE, function ($p) { return !in_array($p['statut'], ['present', 'absent', 'a_verifier', 'quarantaine'], true) || !$p['sources'] || !$p['gestes']; })
  && !array_filter($C::SANTE, function ($p) { foreach ($p['gestes'] as $g) if (!$g[1] || array_diff($g[1], $p['sources'])) return true; return false; })
  && $C::SANTE['hlb']['statut'] == 'absent' && $C::SANTE['tristeza']['statut'] == 'a_verifier', 'catalogue santé cohérent');
$e = $C::emplacements("Serre | #1# | #2#\nJardin | #3# | | #4#\n\nMaison");
$ok(array_keys($e) == ['Serre', 'Jardin', 'Maison'] && $e['Jardin'] == ['#3#', '', '#4#', false] && $e['Maison'] == ['', '', '', false], 'emplacements');
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
// boutons violets : citron, mais aussi pomelo (FoC « violacés ou rarement blancs ») → la taille du fruit tranche
$ok($id(['boutons' => 'violets', 'forme' => 'ovale', 'couleur' => 'jaune', 'pulpe' => 'acide', 'aile' => 'etroite', 'peau' => 'rugueuse'])[0] == 'indecis'
  && $id(['boutons' => 'violets', 'forme' => 'ovale', 'couleur' => 'jaune', 'pulpe' => 'acide', 'aile' => 'etroite', 'peau' => 'rugueuse', 'fruit' => '3-6']) == ['probable', 'Citron'], 'identification : citron (pomelo écarté par le fruit)');
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
$ok($id(['boutons' => 'violets', 'rameaux' => 'glabres', 'pelage' => 'adherente', 'forme' => 'ronde', 'couleur' => 'orange', 'pulpe' => 'douce', 'fruit' => '6-10']) == ['probable', 'Orange douce']
  && $id(['boutons' => 'violets', 'aile' => 'etroite', 'peau' => 'rugueuse', 'couleur' => 'jaune', 'pelage' => 'facile', 'gout_peau' => 'amere', 'pulpe' => 'acide', 'forme' => 'ronde']) == ['probable', 'Yuzu'], 'identification : orange douce et yuzu à boutons violacés');
$ok($id(['folioles' => 'mix'])[0] == 'indecis' && $id([]) === [] && $id(['aile' => 'xx', 'fruit' => ['3-6']]) === [], 'identification : indécis, vide, codes invalides');
foreach ($C::GROUPES as $g => $prof) foreach ($prof as $k => $codes) foreach ($codes as $c) $ok(isset($C::CARACTERES[$k][1][$c]), "profil $g : $k = $c inconnu");
foreach ($C::INDICES as $g => $prof) foreach ($prof as $k => $codes) foreach ($codes as $c) $ok(isset($C::GROUPES[$g]) && !isset($C::GROUPES[$g][$k]) && isset($C::CARACTERES[$k][1][$c]), "indice $g : $k = $c inconnu ou déjà au profil");
$ok(array_keys($C::RUSTICITE_GROUPES) == array_keys($C::GROUPES) && !array_diff($C::BOTA, array_keys($C::GROUPES)), 'rusticité et noms botaniques : groupes connus');
$g = function ($b) use ($C) { $e = $C::estimee($b, []); return $e ? $e['groupes'][0] : null; };
foreach (['Citrus reshni × Poncirus trifoliata' => 'Hybride de Poncirus (citrange, citrumelo, FA5…)', "Poncirus trifoliata 'Flying Dragon'" => 'Poncirus',
  'Citrus × floridana (limequat)' => 'Lime', 'Citrus microcarpa' => 'Calamondin', 'Fortunella obovata × margarita' => 'Kumquat', 'Microcitrus australasica (Finger lime)' => 'Citron caviar (finger lime)',
  "Citrus junos ‘Yuzu n°1’" => 'Yuzu', 'Citrus medica var. sarcodactylis' => 'Cédrat (dont Main de Bouddha)', 'Citrus × bergamia' => 'Bigaradier (orange amère)',
  'citrus×limon' => 'Citron', 'Citrus latifolia' => 'Lime', "Citrus x tangor 'Shiranui'" => 'Mandarine', 'Citrus sinensis' => 'Orange douce', 'Citrus maxima' => 'Pomelo, pamplemousse',
  'Citrus aurantiifolia' => 'Lime', 'Citrus trifoliata × C. reticulata' => 'Hybride de Poncirus (citrange, citrumelo, FA5…)', 'C. reshni x Citrus trifoliata' => 'Hybride de Poncirus (citrange, citrumelo, FA5…)',
  'Citrus trifoliata' => 'Poncirus', '' => null, 'Ficus carica' => null] as $b => $att) $ok($g($b) === $att, "nom botanique « $b » → $att");
$e = $C::estimee('', ['folioles' => 'un', 'fruit' => '6-10', 'couleur' => 'jaune']);
$ok($e['par'] == 'caracteres' && $e['r'] == max(array_intersect_key($C::RUSTICITE_GROUPES, array_flip($e['groupes']))) && count($e['groupes']) > 3, 'rusticité estimée : le moins rustique des groupes possibles');
$ok($C::estimee('Citrus sinensis', ['feuillage' => 'caduc', 'folioles' => 'trois'])['groupes'] == ['Orange douce'] && $C::estimee('', ['aile' => 'xx']) === null, 'rusticité estimée : le nom passe avant les caractères ; rien de reconnu');
$now = strtotime('2026-10-31');
$ok($C::age('2024', $now) === '≈ 2 ans' && $C::age('2026', $now) === 'moins d’un an' && $C::age('2026-03', $now) === '7 mois' && $C::age('2025-10-12', $now) === '1 an' && $C::age('2026-10', $now) === 'moins d’un mois' && $C::age('2026-10-19', $now) === '12 jours' && $C::age('2026-09-30', $now) === '1 mois' && $C::age('2026-10-01', strtotime('2026-11-02')) === '1 mois' && $C::age('2026-09-05', strtotime('2026-11-02')) === '1 mois', 'âge');
$ok($C::age('2027', $now) === null && $C::age('2024-13', $now) === null && $C::age('2024-05-99', $now) === null && $C::age('2024-02-29', $now) === '2 ans' && $C::age('vers 2024', $now) === null && $C::age('', $now) === null, 'âge illisible ou à venir');
$v = $C::valeurs(['feuilles.limbe_l' => '80', 'feuilles.aile_l' => '45,5', 'fruits.diametres' => '32; 35 41', 'pot.substrat' => 'frais', 'plante.hauteur' => '', 'x.y' => '3']);
$ok($v === ['feuilles.limbe_l' => 80.0, 'feuilles.aile_l' => 45.5, 'fruits.diametres' => [32.0, 35.0, 41.0], 'pot.substrat' => 'frais'], 'valeurs saisies');
foreach ([['plante.hauteur' => '-1'], ['plante.hauteur' => 'abc'], ['pot.substrat' => 'mouillé'], ['pot.forme' => 'ovale'], ['fruits.diametres' => '3, x'], ['pot.diam' => '0'], ['pot.terre' => '0,0']] as $faux)
  try { $C::valeurs($faux); $ok(false, 'valeur invalide acceptée : ' . json_encode($faux)); } catch (Exception $x) {} // $ok sort (exit) : pas attrapé ici
$ok($C::valeurs(['plante.hauteur' => ['1']]) === [], 'valeur non scalaire : ignorée');
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
try { $C::nouvelle(['date_mult' => 'mai 2024'], []); $ok(false, 'nouvelle plante : date illisible acceptée'); } catch (Exception $x) {}
$ok($C::nouvelle(['date_mult' => '2024-05'], [])['date_mult'] == '2024-05', 'nouvelle plante : date partielle gardée');
$ok($C::greffe(['type_greffe' => 'fente']) == ['type_greffe' => 'fente', 'multiplication' => 'greffe'] && $C::greffe(['multiplication' => 'semis', 'type_greffe' => 'fente']) == ['multiplication' => 'semis']
  && $C::greffe(['multiplication' => 'greffe', 'type_greffe' => 'x']) == ['multiplication' => 'greffe'], 'type de greffe : implique « greffe », écarté sinon ou hors liste');
[$c, $l] = $C::accepter(['bota' => 'Citrus limon', 'id_folioles' => 'un', 'rusticite' => ''],
  ['bota' => 'Citrus limon', 'rusticite' => '-3 (estimée : citron)', 'id_folioles' => 'trois', 'id_aile' => 'xx', 'id_pulpe' => '', 'gbif' => 'triche'], ['rusticite' => 'clé']);
$ok($c == ['rusticite' => '-3 (estimée : citron)', 'id_folioles' => 'trois'] && $l == ['Rusticité : – → -3 (estimée : citron) (clé)', 'Folioles par feuille : 1 → 3'],
  'identification acceptée : inchangé, vide, invalide et hors liste écartés');
[$c, $l] = $C::accepter(['id_aile' => 'large', 'id_pulpe' => 'acide'], ['id_aile' => '', 'id_pulpe' => 'xx']);
$ok($c == ['id_aile' => ''] && $l == ['Aile du pétiole : large (moins de la moitié du limbe) → –'], 'identification : caractère remis à « – » effacé, invalide écarté');
$ok($g('×Citroncirus webberi') === 'Hybride de Poncirus (citrange, citrumelo, FA5…)' && $g('Poncirus trifoliata ×C. sinensis') === 'Hybride de Poncirus (citrange, citrumelo, FA5…)', 'nom botanique : citroncirus, × sans espace');
echo "calculs OK\n";

$ok($C::markdown("a `**x** <b>` **b** *i* [L](https://e.fr) [G](#greffe) [S](sante.md#mal-secco) [X](javascript:alert)\n- un\n  suite\n\n| A | B |\n|---|---|\n| 1 | 2 |\n![s](images/a.svg) ![u](../images/b.svg) ![t](../x.png)")
  == '<p>a <code>**x** &lt;b&gt;</code> <b>b</b> <i>i</i> <a href="https://e.fr" target="_blank" rel="noopener noreferrer">L</a> <a data-n="" data-a="greffe" href="#jdc-n-greffe">G</a> <a data-n="sante" data-a="mal-secco" href="#jdc-n-mal-secco">S</a> X</p>'
  . '<ul><li>un suite</li></ul><div class="jdc-tw"><table class="table table-condensed"><tr><th scope="col">A</th><th scope="col">B</th></tr><tr><td>1</td><td>2</td></tr></table></div><p>[image manquante : images/a.svg] [image manquante : images/b.svg] !t</p>'
  && $C::ancre("Fiche d'une plante") == 'fiche-dune-plante' && $C::ancre('Identification (agrumes)') == 'identification-agrumes', 'notice : markdown échappé, liens filtrés, ancres comme GitHub');
$H = [];
foreach (array_keys($C::NOTICES) as $k) {
  $f = __DIR__ . "/../docs/fr_FR/$k.md";
  if (is_file($f)) $H[$k] = $C::markdown(file_get_contents($f));
}
// ancres codées en dur du « ? » de la fiche (journalcultures.js, option aide)
preg_match('/aide: \{([^}]*)\}/', file_get_contents(__DIR__ . '/../core/template/journalcultures.js'), $a);
preg_match_all("/['\"]([\\w-]+)['\"]\s*[,}]?/", $a[1] ?? '', $a);
$ok(count($a[1]) >= 4, 'notice : ancres du « ? » trouvées');
foreach ($a[1] as $x) $ok(strpos($H['index'] ?? '', 'id="jdc-n-' . $x . '"') !== false, "notice : ancre du « ? » #$x introuvable");
$e = $C::etymologie(['Poncirus trifoliata (Flying Dragon)', "Citrus junos 'Yuzu' × Citrus"]);
$ok(isset($H['noms']) && count($e) >= 3 && strpos($e[0], '<b>Poncirus</b>') === 0 && strpos(implode('', $e), 'Yuzu') === false && strpos(implode('', $e), '<script') === false, 'noms : origine des mots du nom botanique, cultivar ignoré');
$x = $C::markdown("# T\nintro\n## Un [l](#a)\n### Sous\n## Deux");
$ok(strpos($x, '<h2 id="jdc-n-t">T</h2><div class="jdc-doc"><nav class="jdc-toc" aria-label="Sommaire">') === 0 && strpos($x, '<li><a data-n="" data-a="un-la" href="#jdc-n-un-la">Un l</a></li><li class="s"><a data-n="" data-a="sous" href="#jdc-n-sous">Sous</a></li>') !== false
  && strpos($x, '<div class="jdc-tx"><p>intro</p><h3') !== false && strpos($C::markdown("# T\n## Un"), 'jdc-toc') === false, 'notice : sommaire sous le titre, seulement à partir de 2 sections');
$ok(strpos($C::markdown("x\n1. a\n   b\n2. c\n- d"), '<p>x</p><ol><li>a b</li><li>c</li></ol><ul><li>d</li></ul>') !== false, 'notice : liste numérotée');
$ok(strpos($C::markdown("vers\n2026. suite"), '<p>vers 2026. suite</p>') !== false, 'notice : « 2026. » ne coupe pas un paragraphe');
$ok(strpos($C::markdown("**\u{2605}** retire"), "<b>\u{2605}</b> retire") !== false, 'notice : caractère à octet 0x85 (★) pas coupé en deux lignes');
foreach ($H as $k => $h) {
  $ok(strlen($h) > 100 && strpos($h, '<script') === false, "notice $k convertie");
  $ok(strpos($h, 'image manquante') === false, "notice $k : image absente de docs/images");
  preg_match_all('/data-n="(\w*)" data-a="([^"]*)"/', $h, $m, PREG_SET_ORDER);
  foreach ($m as $l) {
    $p = $l[1] === '' ? $k : $l[1];
    if (!isset($H[$p])) { $ok(isset($C::NOTICES[$p]), "notice $k : lien vers la page $p inconnue"); continue; } // page à venir : lien ignoré
    if ($l[2] !== '') $ok(strpos($H[$p], 'id="jdc-n-' . $l[2] . '"') !== false, "notice $k : ancre $p#$l[2] introuvable");
  }
}
$im = glob(__DIR__ . '/../docs/images/*.{png,jpg,svg,webp}', GLOB_BRACE)[0] ?? null;
if ($im) $ok(strpos($C::markdown('![a](images/' . basename($im) . ')'), '<p><img src="plugins/journalcultures/core/ajax/journalcultures.ajax.php?action=image&amp;n=' . rawurlencode(basename($im)) . '&amp;v=' . filemtime($im) . '"') === 0, 'notice : image par URL datée');
$ok($C::notice('index') === $H['index'] && $C::notice('index') === $H['index'], 'notice : page en cache = page convertie');
$ok((bool)glob(jeedom::getTmpFolder('journalcultures') . '/notice_index_*.html'), 'notice : page gardée en cache');
$ok($C::taille(__DIR__ . '/../docs/images/rusticite.svg') === ' width="640" height="272" class="jdc-svgi"', 'notice : taille du schéma rusticite.svg');
foreach (glob(__DIR__ . '/../docs/images/*.svg') ?: [] as $s) $ok(!preg_match('/<script|\bon\w+\s*=|foreignObject/i', (string)file_get_contents($s)), 'image ' . basename($s) . ' : script ou contenu actif'); // servies en image/svg+xml
try { $C::notice('../index'); $ok(false, 'notice : page hors liste refusée'); } catch (Exception $x) {}
$vu = false;
foreach (journalcultures::byType('journalcultures') as $e) if ($e->type() == 'plante' && ($n = $e->photos())) { // vignette (dossier temporaire seulement)
  $m = $e->mini($n[0]); $t = getimagesize($m);
  if ($vu = $m !== $e->photo($n[0])) $ok($t[2] == IMAGETYPE_JPEG && max($t[0], $t[1]) <= 400 && $e->mini($n[0]) === $m, 'photo : vignette de 400 px'); // sinon : original servi (GD, mémoire)
  break;
}
if (!$vu) echo "vignette non testée (aucune photo, ou GD absent)\n";
echo "notice OK\n";

$t = DB::Prepare("SHOW TABLES LIKE 'journalcultures_journal'", [], DB::FETCH_TYPE_ROW);
if ($t) {
  $ok((bool)DB::Prepare("SHOW COLUMNS FROM journalcultures_journal LIKE 'valeurs'", [], DB::FETCH_TYPE_ROW), 'colonne valeurs (lancer la mise à jour du plugin)');
  journalcultures::journal(null, 1);
  echo "table OK\n";
} else echo "table absente (plugin pas installé)\n";

// refus avant toute écriture (aucun effet) : plan d'engrais, greffe, retour en porte-greffe, création
$refus = function ($f, $m) use ($ok) { try { $f(); } catch (Exception $x) { return; } $ok(false, "accepté : $m"); };
// plantes dans l'objet de leur collection : creer() ne cherche le nom que là, un autre objet laisserait passer (et écrirait)
$pl = array_values(array_filter(eqLogic::byType('journalcultures'), function ($e) { return $e->type() == 'plante' && is_object($c = $e->collection()) && $e->getObject_id() == $c->getObject_id(); }));
if ($pl) {
  $avecPot = array_values(array_filter($pl, function ($e) { return journalcultures_calc::volume($e->pot()) > 0; })); // sinon les 3 refus tomberaient sur « pot inconnu »
  $p = $avecPot[0] ?? $pl[0]; $co = $p->collection(); $av = $p->getConfiguration('engrais'); $nb = count(eqLogic::byType('journalcultures'));
  $refus(function () use ($p) { $p->ecrirePlan([0 => 50]); }, 'plan à 50 %');
  $refus(function () use ($p) { $p->ecrirePlan([0 => 150, 1 => -50]); }, 'plan avec une part hors 0-100');
  $refus(function () use ($p) { $p->ecrirePlan([0 => 100], [0 => 9999]); }, 'plan avec une dose de 9999 g');
  $ok($p->getConfiguration('engrais') === $av, 'plan refusé : calendrier inchangé');
  foreach ($pl as $q) if ((string)$q->getConfiguration('greffe_statut') === '') { $refus(function () use ($q) { $q->controlerGreffe('ok', ''); }, 'contrôle sans greffe en cours'); break; }
  foreach ($pl as $q) if (!is_array($q->getConfiguration('greffe_avant'))) { $ok($q->migrerGreffe() === false, 'migration 0.26 : plante sans greffe laissée telle quelle'); break; }
  foreach ($pl as $q) if (is_array($gf = $q->getConfiguration('greffe_greffon'))) { $ok(($gf['nom'] ?? '') !== '' && $q->suiviGreffe()['greffon'] === $gf['nom'] && $q->getName() !== '', 'greffe en attente : greffon nommé, plante encore porte-greffe'); break; }
  $refus(function () use ($co) { journalcultures::creer($co, ['nom' => ' ']); }, 'plante sans nom');
  $refus(function () use ($co, $p) { journalcultures::creer($co, ['nom' => mb_strtoupper($p->getName())]); }, 'plante au nom déjà pris');
  foreach ($pl as $q) if ($q->getId() != $p->getId() && $q->getObject_id() == $p->getObject_id()) { $refus(function () use ($co, $p, $q) { journalcultures::creer($co, ['nom' => $p->getName()], $q); }, 'greffe au nom d\'une autre plante'); break; }
  $ok($co->refusSuppression() !== '', 'collection non vide : suppression refusée');
  $ok(json_encode(journalcultures::exporter()) !== false, 'export : JSON');
  $refus(function () use ($co) { journalcultures::importer(['format' => 'autre', 'plantes' => []], $co); }, 'import d\'un fichier non reconnu');
  $pr = (string)config::byKey('produits', 'journalcultures', '');
  $x = [['nom' => $p->getName(), 'collection' => $co->getName(), 'journal' => [['date' => '2000-01-01', 'action' => 'x']]]];
  foreach (eqLogic::byType('journalcultures') as $q) if ($q->type() == 'plante' && is_object($c = $q->collection()) && $q->getObject_id() != $c->getObject_id()) { $x[] = ['nom' => $q->getName(), 'collection' => $c->getName()]; break; } // rangée hors de l'objet de sa collection
  $r = journalcultures::importer(['format' => 'journalcultures', 'produits' => $pr, 'plantes' => $x], $co);
  $ok($r['plantes'] === 0 && $r['produits'] === 0 && $r['entrees'] === 0 && count($r['deja']) === count($x) && (string)config::byKey('produits', 'journalcultures', '') === $pr, 'import d\'une plante et de produits déjà là : rien d\'écrit');
  $ok(count(eqLogic::byType('journalcultures')) === $nb, 'refus et import : aucun équipement créé');
  echo "refus OK\n";
}

foreach (eqLogic::byType('journalcultures') as $eq) {
  $d = $eq->type() == 'collection' ? $eq->donnees() : $eq->etat() + ['engrais_fiche' => $eq->engraisFiche()];
  $ok(json_encode($d, JSON_HEX_TAG) !== false, 'données de ' . $eq->getHumanName());
  echo 'données OK : ' . $eq->getHumanName() . "\n";
}
echo "TOUT OK\n";

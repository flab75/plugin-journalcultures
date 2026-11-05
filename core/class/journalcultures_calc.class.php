<?php
/* Calculs du journal de cultures : fonctions pures (pas de base, pas de réseau), testées par tests/selftest.php. */
class journalcultures_calc {
  const ACTIONS = ['Semis', 'Repiquage', 'Plantation', 'Rempotage', 'Taille', 'Greffe', 'Récolte', 'Arrosage', 'Engrais', 'Traitement',
    'Déplacement', 'Mesure', 'Observation', 'Travail du sol', 'Autre'];
  const ELEMENTS = ['racine', 'feuille', 'fleur', 'fruit']; // catégories de Sélénoculture

  // « −7 », « -6,5 », « -15 (FA5) » → -7.0, -6.5, -15.0 ; null si pas de nombre
  public static function nombre($v) {
    if (!is_scalar($v) || !preg_match('/[-−–]?\s*\d+(?:[.,]\d+)?/u', (string)$v, $m)) return null;
    return (float)strtr(preg_replace('/\s+/', '', $m[0]), ['−' => '-', '–' => '-', ',' => '.']);
  }

  /* Rusticité effective d'une plante en pot : les racines y sont bien moins protégées qu'en pleine terre.
     Pénalité = min(pmax, k / e), e = demi-épaisseur de la motte en cm (min(diamètre, hauteur de terre) / 2) ;
     pot carré : 'diam' = côté (même rapport volume / paroi qu'un pot rond de Ø = côté, donc même e) ;
     pot de dimensions inconnues = pénalité maximale (prudence).
     ponytail: modèle grossier (ni durée du froid, ni vent, ni pot isolé ou surélevé) ; régler k et pmax à l'usage. */
  public static function rusticite($r, array $pot, $k = 50, $pmax = 8) {
    $r = self::nombre($r);
    if ($r === null) return null;
    if (!empty($pot['pleine_terre'])) return $r;
    $d = (float)self::nombre($pot['diam'] ?? ''); $h = (float)(self::nombre($pot['terre'] ?? '') ?: self::nombre($pot['haut'] ?? ''));
    $e = ($d > 0 && $h > 0) ? min($d, $h) / 2 : 0;
    return round($r + ($e > 0 ? min($pmax, $k / $e) : $pmax), 1);
  }

  /* Alerte de froid : température mesurée ou minimale prévue ≤ rusticité effective + marge.
     → null (rusticité inconnue ou pas de température), sinon ['alerte' => 0|1, 'raison' => texte court] */
  public static function alerte($reff, $t, $tmin, $marge = 3) {
    if ($reff === null || ($t === null && $tmin === null)) return null;
    $seuil = $reff + $marge;
    if ($t !== null && $t <= $seuil) return ['alerte' => 1, 'raison' => str_replace('.', ',', sprintf(__('%s °C mesurés (rusticité %s °C)', __FILE__), round($t, 1), $reff))];
    if ($tmin !== null && $tmin <= $seuil) return ['alerte' => 1, 'raison' => str_replace('.', ',', sprintf(__('%s °C prévus (rusticité %s °C)', __FILE__), round($tmin, 1), $reff))];
    return ['alerte' => 0, 'raison' => ''];
  }

  // semaines ISO : « 10-40/2 » (de 10 à 40, une sur deux), « 12,16,20 », « 48-8 » (passe d'une année à l'autre) → [n°, …]
  public static function semaines($s) {
    $r = [];
    foreach (preg_split('/[,; ]+/', trim((string)$s), -1, PREG_SPLIT_NO_EMPTY) as $p) {
      if (!preg_match('/^(\d{1,2})(?:-(\d{1,2}))?(?:\/(\d{1,2}))?$/D', $p, $m)) continue;
      $a = (int)$m[1]; $b = ($m[2] ?? '') !== '' ? (int)$m[2] : $a; $pas = max(1, (int)($m[3] ?? 1));
      if ($a < 1 || $a > 53 || $b < 1 || $b > 53) continue;
      for ($n = $a, $i = 0; $i < 60; $n = $n % 53 + 1, $i++) { if ($i % $pas == 0) $r[] = $n; if ($n == $b) break; }
    }
    $r = array_values(array_unique($r)); sort($r);
    return $r;
  }

  // saison « 10-40 » de l'hémisphère nord décalée de $n semaines (26 = hémisphère sud) ; semaines() gère le passage d'une année à l'autre
  public static function taille($f) { // width/height d'une image de la notice : la page ne saute pas pendant le chargement ; svg = viewBox
    if (substr($f, -4) == '.svg') { if (!preg_match('/viewBox="[\d.]+ [\d.]+ ([\d.]+) ([\d.]+)"/', (string)file_get_contents($f), $m)) return ''; $w = (int)$m[1]; $h = (int)$m[2]; }
    else { $i = @getimagesize($f); if (!$i) return ''; [$w, $h] = $i; }
    return $w > 0 && $h > 0 ? ' width="' . $w . '" height="' . $h . '"' . (substr($f, -4) == '.svg' ? ' class="jdc-svgi"' : '') : '';
  }
  // ponytail: modulo 52 alors que semaines() compte jusqu'à 53 : seule une borne en semaine 53 (aucune saison réelle) tombe une semaine à côté ; % 53 décalerait toutes les saisons du sud d'une semaine
  public static function decaler($s, $n) { if (count(self::semaines($s)) >= 52) return (string)$s; // toute l'année : rien à décaler
    return preg_replace_callback('/\d+/', function ($m) use ($n) { return ((((int)$m[0] - 1 + $n) % 52) + 52) % 52 + 1; }, (string)$s); }
  /* Zéro de végétation des agrumes : environ 13 °C (Curk 2022, INRAE), ≈ 12,5 °C (Aubert 1969, IFAC). En dessous, pas de pousse :
     les apports d'engrais attendent (rattrapés dès que la moyenne remonte, dans la période d'apport de la ligne). Même filiation (IFAC → Cirad) : à confirmer ;
     Tuzcu 1984 : Poncirus, satsuma, Nagami ne poussent qu'au-dessus de ≈ 15 °C. Moyenne sur 7 jours = choix du plugin (lisse un jour froid isolé). */
  const POUSSE = 13;
  public static function pousse($moy, $seuil) { return $moy === null || $moy === false ? $moy : $moy >= $seuil; } // null : pas de sonde historisée → le calendrier seul décide ; false : sonde muette → on attend

  /* calendrier d'engrais « semaines | produit | dose » par ligne → apports dus en semaine $sem : [[libellé, retard, hors période d'apport], …], retard = semaines depuis le dernier apport prévu de la ligne, seulement tant que
     $sem est dans la période d'apport de la ligne (« 10-40/8 » → 10-40). Retard > 0 = apport sauté (trop froid) à rattraper, si rien n'a été donné depuis, et seulement pendant la première
     moitié de l'intervalle, strictement (sinon on attend l'apport suivant : jamais deux doses à moins d'une demi-période ; choix du plugin). ponytail: modulo 53, une année à 52 semaines compte une semaine de trop au passage d'année
     $chauffe : hors période d'apport, une ligne « a-b/pas » continue toute l'année au même pas, à demi-dose (emplacement chauffé ; choix du plugin, aucune source sur l'hiver en pièce chauffée). */
  public static function engraisDus($texte, $sem, $chauffe = false) {
    $r = [];
    foreach (preg_split('/\R/u', (string)$texte) ?: [] as $l) {
      $p = array_map('trim', explode('|', $l));
      if (count($p) < 2 || $p[1] === '') continue;
      $S = self::semaines($p[0]); $hors = !in_array((int)$sem, self::semaines(preg_replace('#/\d+#', '', $p[0])));
      if ($hors) {
        if (!$chauffe || !preg_match('#^(\d{1,2})-\d{1,2}(/\d{1,2})?$#D', $p[0], $m)) continue;
        $S = self::semaines($m[1] . '-' . (((int)$m[1] + 51) % 53 + 1) . ($m[2] ?? '')); // toute l'année, au pas de la ligne
      }
      for ($k = 0; $k < 53 && !in_array(((int)$sem - 1 - $k + 53) % 53 + 1, $S); $k++);
      if ($k * 2 >= (preg_match('#/(\d+)#', $p[0], $m) ? (int)$m[1] : 1) && $k > 0) continue;
      $d = $hors ? preg_replace_callback('/\d+(?:[.,]\d+)?/', function ($x) { return self::virgule(round(self::nombre($x[0]) / 2, 1)); }, $p[2] ?? '', 1) : ($p[2] ?? ''); // hors période d'apport : dose divisée par 2
      $r[] = [trim($p[1] . ' ' . $d) . ($hors ? ' (demi-dose, hors période d’apport)' : ''), $k, $hors, $p[1]];
    }
    return $r;
  }

  // emplacements « Nom | temp | humidité | tmin prévue | chauffé » par ligne → [nom => [temp, hum, tmin, chauffé]] (chaînes de commandes, '' si absente ; 5e champ non vide = chauffé)
  public static function emplacements($texte) {
    $r = [];
    foreach (preg_split('/\R/u', (string)$texte) ?: [] as $l) {
      $p = array_map('trim', explode('|', $l));
      if ($p[0] !== '') $r[mb_substr($p[0], 0, 60)] = [$p[1] ?? '', $p[2] ?? '', $p[3] ?? '', !in_array(mb_strtolower($p[4] ?? ''), ['', '0', 'non', 'no'], true)]; // nom historisé (varchar 127) et colonne lieu (60)
    }
    return $r;
  }

  // texte libre saisi : pas d'émoji (connexion et colonnes Jeedom en utf8mb3), < > neutralisés, longueur bornée
  public static function texte($v, $n) {
    $s = is_scalar($v) ? (string)$v : '';
    return mb_substr(trim(strtr(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s) ?? '', ['<' => '‹', '>' => '›'])), 0, $n);
  }

  /* Identification du groupe d'un agrume par ses caractères visibles (choix tirés des descripteurs IPGRI 1999 et UPOV TG/83).
     Clé « caractère => [code => libellé] » ; config de la plante « id_<caractère> » = code. */
  const CARACTERES = [
    'feuillage' => ['Feuillage', ['persistant' => 'persistant', 'caduc' => 'tombe l’hiver']],
    'folioles' => ['Folioles par feuille', ['un' => '1', 'trois' => '3', 'mix' => '1 et 3 mélangées']],
    'aile' => ['Longueur de l’aile (comparée au limbe)', ['aucune' => 'aucune ou un simple liseré', 'etroite' => 'courte (moins d’¼ du limbe)', 'large' => 'moyenne (¼ à ½)', 'tres_large' => 'longue (½ ou plus)']],
    'aile_largeur' => ['Largeur de l’aile (comparée au limbe)', ['moins' => 'moins de la moitié du limbe', 'moitie' => 'de la moitié aux trois quarts', 'presque' => 'presque aussi large que le limbe, ou plus']],
    'feuille' => ['Longueur du limbe (d’une foliole chez le Poncirus, sans le pétiole)', ['petite' => 'moins de 5 cm', 'moyenne' => '5 à 10 cm', 'grande' => 'plus de 10 cm']],
    'boutons' => ['Boutons floraux', ['blancs' => 'blancs', 'violets' => 'teintés de pourpre ou de rose']],
    'fruit' => ['Diamètre du fruit (longueur s’il est en doigt)', ['lt3' => 'moins de 3 cm', '3-6' => '3 à moins de 6 cm', '6-10' => '6 à 10 cm', 'gt10' => 'plus de 10 cm']],
    'forme' => ['Forme du fruit', ['ronde' => 'ronde', 'aplatie' => 'aplatie', 'ovale' => 'ovale ou à mamelon', 'doigt' => 'allongée en doigt', 'main' => 'divisée en doigts']],
    'couleur' => ['Couleur à maturité', ['jaune' => 'jaune', 'orange' => 'orange', 'verte' => 'verte', 'autre' => 'rose, rouge ou brune']],
    'peau' => ['Surface de la peau', ['lisse' => 'lisse', 'rugueuse' => 'rugueuse ou bosselée', 'duvet' => 'duveteuse']],
    'gout_peau' => ['Goût de la peau', ['amere' => 'amère ou neutre', 'sucree' => 'sucrée, se mange']],
    'pelage' => ['Épluchage', ['facile' => 'peau qui se détache seule', 'adherente' => 'peau adhérente']],
    'rameaux' => ['Jeunes rameaux', ['poilus' => 'poilus (duvet fin, à la loupe)', 'glabres' => 'glabres (lisses)']],
    'quartiers' => ['Quartiers du fruit (coupé en travers)', ['peu' => '6 ou moins', 'nombreux' => '7 ou plus']],
    'pulpe' => ['Saveur de la pulpe', ['douce' => 'douce', 'acide' => 'acide', 'amere' => 'amère']],
    'pulpe_aspect' => ['Aspect de la pulpe', ['vesicules' => 'en vésicules (normale)', 'perles' => 'en perles (caviar)', 'peu' => 'presque absente (peau très épaisse)']], // 0.27 : perles et peu étaient des codes de « pulpe » (migrerPulpe)
  ];
  /* Profils : groupe => [caractère => codes compatibles] ; un caractère absent du profil ne compte pas.
     Recoupés avec la clé du genre de la Flora of China, vol. 11 (aile > moitié du limbe = papedas, « très large » ; cédrat sans aile, peau plus épaisse que la pulpe ;
     citron : boutons teintés de pourpre ou de rose, mamelon ; lime de Tahiti : fleurs blanches (UCR) ou un peu violacées (Morton), les deux acceptées ; bergamote = bigaradier jaune, ronde ou en poire ; citron Meyer souvent rond ; yuzu et mandarine : peau qui s'enlève ; pomelo : fruit de 6 à 10 cm ou plus ;
     quartiers : kumquat 3-6 loges contre 7-15 pour les autres Citrus (Flora of China), calamondin 8-9 (中国植物志 1997 p. 175, relu le 2026-11-02) ;
     pas imposé aux papedas (combava 6-7), au caviar, au cédrat, au Poncirus ni à ses hybrides) ;
     largeur de l'aile : combava presque aussi large que le limbe (Flora of China : limbe 0,5-1 cm plus large ; Sofiyanti 2022), ichang plus large que le limbe (FoC),
     pamplemousse (C. maxima, « pomelo » de la FoC) nettement plus étroite (FoC : aile 0,5-3 cm, limbe 4-8 cm ; Sofiyanti 2022) ; non imposée aux autres groupes (aucune source) ;
     jeunes rameaux : glabres chez l'orange et le bigaradier (C. ×aurantium, clé FoC 8b ; Swingle & Reece : les rameaux poilus séparent le pamplemousse C. maxima
     des autres espèces) ; poilus chez le pamplemousse C. maxima (FoC 8a + Swingle & Reece « often pubescent ») et le yuzu (FoC « sparsely pubescent when young ») :
     seulement un indice pour eux (INDICES), un pomelo C. ×paradisi glabre (FoC) reste possible.
     ponytail: profils de groupes, pas de cultivars : les hybrides restent souvent indécis (la Flora of China le dit aussi) ;
     protocoles UPOV complets non relus, affiner un profil quand une plante connue le contredit. */
  const GROUPES = [
    'Poncirus' => ['feuillage' => ['caduc'], 'folioles' => ['trois'], 'peau' => ['duvet'], 'pulpe' => ['amere', 'acide'], 'pulpe_aspect' => ['vesicules', 'peu'], 'boutons' => ['blancs']],
    'Hybride de Poncirus (citrange, citrumelo, FA5)' => ['feuillage' => ['persistant', 'caduc'], 'folioles' => ['trois', 'mix'], 'peau' => ['lisse', 'duvet', 'rugueuse'], 'pulpe' => ['acide', 'amere'], 'pulpe_aspect' => ['vesicules'], 'boutons' => ['blancs']],
    'Kumquat' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'gout_peau' => ['sucree'], 'fruit' => ['lt3', '3-6'], 'forme' => ['ronde', 'ovale'],
      'feuille' => ['petite', 'moyenne'], 'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'couleur' => ['orange', 'jaune'], 'pulpe' => ['acide', 'douce'], 'pulpe_aspect' => ['vesicules'], 'pelage' => ['adherente'], 'quartiers' => ['peu']],
    'Calamondin' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'fruit' => ['lt3', '3-6'], 'forme' => ['ronde', 'aplatie'], 'couleur' => ['orange'],
      'pulpe' => ['acide'], 'pulpe_aspect' => ['vesicules'], 'pelage' => ['facile'], 'peau' => ['lisse'], 'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'feuille' => ['petite', 'moyenne'], 'quartiers' => ['nombreux']],
    'Citron caviar (finger lime)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'forme' => ['doigt'], 'feuille' => ['petite'], 'pulpe' => ['acide'], 'pulpe_aspect' => ['perles'], 'aile' => ['aucune'], 'fruit' => ['3-6', '6-10']],
    'Yuzu' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere'], 'pulpe_aspect' => ['vesicules'], 'couleur' => ['jaune'],
      'fruit' => ['3-6', '6-10'], 'forme' => ['ronde', 'aplatie'], 'boutons' => ['blancs', 'violets'], 'gout_peau' => ['amere'], 'pelage' => ['facile'], 'quartiers' => ['nombreux']],
    'Papeda (combava, ichang)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['tres_large'], 'aile_largeur' => ['moitie', 'presque'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere'], 'pulpe_aspect' => ['vesicules', 'peu'],
      'boutons' => ['blancs', 'violets'], 'gout_peau' => ['amere']],
    'Cédrat (dont Main de Bouddha)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets', 'blancs'], 'aile' => ['aucune'], 'fruit' => ['6-10', 'gt10'],
      'forme' => ['ovale', 'main'], 'couleur' => ['jaune'], 'pulpe' => ['acide', 'douce'], 'pulpe_aspect' => ['vesicules', 'peu'], 'feuille' => ['moyenne', 'grande'], 'pelage' => ['adherente']],
    'Citron' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune', 'etroite'], 'forme' => ['ovale', 'ronde'], 'couleur' => ['jaune', 'verte'],
      'pulpe' => ['acide'], 'pulpe_aspect' => ['vesicules'], 'pelage' => ['adherente'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse', 'rugueuse'], 'quartiers' => ['nombreux']],
    'Lime' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'couleur' => ['verte', 'jaune'], 'forme' => ['ronde', 'ovale'], 'boutons' => ['blancs', 'violets'], 'pulpe' => ['acide'], 'pulpe_aspect' => ['vesicules'], 'fruit' => ['3-6', '6-10'],
      'aile' => ['etroite', 'aucune'], 'peau' => ['lisse'], 'pelage' => ['adherente'], 'quartiers' => ['nombreux']],
    'Mandarine' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pelage' => ['facile'], 'forme' => ['aplatie', 'ronde'], 'couleur' => ['orange'], 'pulpe' => ['douce', 'acide'], 'pulpe_aspect' => ['vesicules'],
      'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse'], 'quartiers' => ['nombreux']],
    'Orange douce' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'rameaux' => ['glabres'], 'pelage' => ['adherente'], 'forme' => ['ronde', 'ovale'], 'couleur' => ['orange'], 'pulpe' => ['douce'], 'pulpe_aspect' => ['vesicules'],
      'aile' => ['etroite', 'aucune'], 'boutons' => ['blancs', 'violets'], 'fruit' => ['6-10', 'gt10'], 'quartiers' => ['nombreux']],
    'Bigaradier (orange amère)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'rameaux' => ['glabres'], 'aile' => ['etroite', 'large'], 'pelage' => ['adherente'], 'pulpe' => ['amere', 'acide'], 'pulpe_aspect' => ['vesicules'], 'couleur' => ['orange', 'jaune'],
      'forme' => ['ronde', 'aplatie', 'ovale'], 'boutons' => ['blancs'], 'fruit' => ['6-10'], 'gout_peau' => ['amere'], 'quartiers' => ['nombreux']],
    'Pomelo (C. ×paradisi), pamplemousse (C. maxima)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pulpe_aspect' => ['vesicules'], 'aile' => ['etroite', 'large'], 'aile_largeur' => ['moins', 'moitie'], 'fruit' => ['6-10', 'gt10'], 'couleur' => ['jaune', 'verte'], 'feuille' => ['moyenne', 'grande'],
      'boutons' => ['violets', 'blancs'], 'pelage' => ['adherente'], 'quartiers' => ['nombreux']],
  ];

  // indices : un accord si observé, jamais une contradiction (caractère fréquent mais pas constant dans le groupe)
  const INDICES = ['Pomelo (C. ×paradisi), pamplemousse (C. maxima)' => ['rameaux' => ['poilus']], 'Yuzu' => ['rameaux' => ['poilus']]];

  // codes observés valides seulement : [caractère => code]
  public static function observes($cfg) {
    $r = [];
    foreach (self::CARACTERES as $k => [, $opt]) if (is_string($v = $cfg[$k] ?? null) && isset($opt[$v])) $r[$k] = $v; // texte seulement : true ou 1.0 ne valent pas « 1 foliole »
    return $r;
  }

  /* Groupes classés du plus au moins compatible : moins de contradictions d'abord, puis plus d'accords.
     → [] si rien d'observé, sinon ['verdict', 'observes' => n, 'groupes' => [['groupe', 'accords' => n, 'contre' => [libellés en désaccord]], …]] (3 premiers) ;
     verdict 'probable' seulement si le premier n'a aucune contradiction, au moins 3 accords, et que tous les autres sont contredits :
     un groupe non contredit dont le profil ignore un caractère n'est pas écarté pour autant (citron sans boutons observés ≠ lime). */
  public static function identifier($cfg, $n = 3) {
    $obs = self::observes($cfg);
    if (!$obs) return [];
    $r = [];
    foreach (self::GROUPES as $g => $prof) {
      $acc = 0; $contre = [];
      foreach ($obs as $k => $v) if (isset($prof[$k])) { if (in_array($v, $prof[$k], true)) $acc++; else $contre[] = self::CARACTERES[$k][0]; }
      foreach (self::INDICES[$g] ?? [] as $k => $codes) if (in_array($obs[$k] ?? null, $codes, true)) $acc++;
      $r[] = ['groupe' => $g, 'accords' => $acc, 'contre' => $contre];
    }
    usort($r, function ($a, $b) { return [count($a['contre']), -$a['accords']] <=> [count($b['contre']), -$b['accords']]; });
    $a = $r[0]; $b = $r[1];
    $net = !$a['contre'] && $a['accords'] >= 3 && $b['contre'];
    return ['verdict' => $net ? 'probable' : 'indecis', 'observes' => count($obs), 'groupes' => array_slice($r, 0, $n)];
  }

  /* Nom botanique → groupe : premier motif qui correspond (hybrides de Poncirus avant Poncirus, limequat et calamondin avant kumquat,
     bergamote et orange amère avant citron et orange). Synonymes : Flora of China 11 (C. japonica = Fortunella, C. trifoliata = Poncirus) et 中国植物志 p. 175 (C. ×microcarpa
     = madurensis = mitis). FoC 2008 : boutons du pamplemousse C. maxima « violacés ou rarement blancs », de l'ichang violet-rouge pâle, pétales de l'orange douce rarement et du yuzu parfois violacés → acceptés (décision de l'utilisateur, 2026-11-02) ; cédrat de Corse à pulpe douce (Curk 2022) → douce acceptée. ponytail: tangor, tangelo, Shiranui rangés en mandarine ; limequat en lime (le moins rustique des deux parents). */
  const BOTA = [
    '/\s[×x]\s*(poncirus|(c(itrus)?\.?\s*)?trifoliata)|(poncirus|trifoliata)\s(.*\s)?(×|x\s)|citrange|citrumelo|citrandarin|citroncirus|insitorum/iu' => 'Hybride de Poncirus (citrange, citrumelo, FA5)',
    '/poncirus|trifoliata/iu' => 'Poncirus',
    '/floridana|limequat/iu' => 'Lime',
    '/microcarpa|madurensis|mitis|calamondin/iu' => 'Calamondin',
    '/fortunella|japonica|margarita|obovata|crassifolia|hindsii|kumquat/iu' => 'Kumquat',
    '/australasica|microcitrus|caviar|finger/iu' => 'Citron caviar (finger lime)',
    '/junos|yuzu/iu' => 'Yuzu',
    '/hystrix|ichangensis|cavaleriei|papeda|combava/iu' => 'Papeda (combava, ichang)',
    '/medica|c[ée]drat|sarcodactyl/iu' => 'Cédrat (dont Main de Bouddha)',
    '/deliciosa|reticulata|tangelo|tangor/iu' => 'Mandarine', // C. ×aurantium au sens large couvre aussi l'orange, le pomelo (C. ×paradisi) et des mandarines : cultivar ou épithète testés avant le bigaradier
    '/valencia|navel|washington|sanguinell|tarocco|\bmoro\b|maltaise/iu' => 'Orange douce',
    '/paradisi/iu' => 'Pomelo (C. ×paradisi), pamplemousse (C. maxima)',
    '/bergamia|aurantium|myrtifolia|bigarad|am[èe]re/iu' => 'Bigaradier (orange amère)',
    '/limon|meyeri|lemon|citronnier/iu' => 'Citron',
    '/aurantiifolia|latifolia|limettioides|\blime\b/iu' => 'Lime',
    '/reticulata|unshiu|deliciosa|clementina|tangerina|nobilis|tangor|tangelo|mandarin|satsuma|shiranui/iu' => 'Mandarine',
    '/sinensis|orange/iu' => 'Orange douce',
    '/maxima|grandis|paradisi|pomelo|pamplemousse|grapefruit/iu' => 'Pomelo (C. ×paradisi), pamplemousse (C. maxima)',
  ];
  /* Rusticité d'un groupe (°C, pleine terre) quand la fiche n'en donne pas : la moins froide des sources (prudence), à remplacer par une valeur de la fiche.
     Kumquat : TyCitriK −10, UF −12, Fortune 1870 (via Swingle) « 10-15 °F de gel » = −5,6 à −8,3, UF/IFAS (Zekri 2001) feuilles ≈ −6,7 → −6 (choix du plugin, entre −5,6 et −6,7) ; citron : TyCitriK −5, Blondel −5/−6 ; cédrat : TyCitriK −3, Guo −4/−5 (feuilles) ; lime : TyCitriK −4, mais UF/IFAS CH093 : bois abîmé dès −3, mort sous −4 (lime de Tahiti) → −3 ; calamondin, caviar, papeda : TyCitriK seul ;
     yuzu : RHS H3 −5 contre TyCitriK −14 (le plus prudent gardé) ; Poncirus : TyCitriK −16 (une seule source) → −15 par prudence (choix du plugin) ; hybrides : Chopinet & Dupouy 1951, de seconde main (citranges de Montauban −10/−14, satsumange −11/−13, citrangedins, limonanges,
     citrangequat ≈ −10 ; citranges Rusk et Willits sensibles aux coups de froid brusques), TyCitriK C35 −10, Carrizo −11, citrumelo −12, FA5 −13 → −10 ;
     bigaradier, mandarine, orange, pomelo et pamplemousse : aucune valeur chiffrée sur la plante, encadrés par l'ordre UF (Jackson & Fasulo HS-123 : calamondin > bigaradier > mandarinier
     > oranger > pomelo C. ×paradisi (grapefruit) > citronnier) entre calamondin −5 et citron −5 → −5. */
  const RUSTICITE_GROUPES = [
    'Poncirus' => -15, 'Hybride de Poncirus (citrange, citrumelo, FA5)' => -10, 'Kumquat' => -6, 'Calamondin' => -5, 'Citron caviar (finger lime)' => -4,
    'Yuzu' => -5, 'Papeda (combava, ichang)' => -4, 'Cédrat (dont Main de Bouddha)' => -3, 'Citron' => -5, 'Lime' => -3, 'Mandarine' => -5, 'Orange douce' => -5,
    'Bigaradier (orange amère)' => -5, 'Pomelo (C. ×paradisi), pamplemousse (C. maxima)' => -5,
  ];

  /* Rusticité estimée d'après le groupe : nom botanique d'abord, sinon caractères observés (tous les groupes non contredits, le moins rustique retenu).
     → null (rien de reconnu), sinon ['r' => °C, 'groupes' => [noms], 'par' => 'nom'|'caracteres'] */
  public static function estimee($bota, $cfg) {
    foreach (self::BOTA as $re => $g) if (preg_match($re, ' ' . $bota . ' ')) return ['r' => (float)self::RUSTICITE_GROUPES[$g], 'groupes' => [$g], 'par' => 'nom'];
    $id = self::identifier($cfg, count(self::GROUPES));
    $c = array_column(array_filter($id['groupes'] ?? [], function ($x) { return !$x['contre']; }), 'groupe');
    if (!$c) return null;
    return ['r' => (float)max(array_intersect_key(self::RUSTICITE_GROUPES, array_flip($c))), 'groupes' => $c, 'par' => 'caracteres'];
  }

  const MULTIPLICATIONS = ['semis', 'greffe', 'bouture', 'marcotte', 'achat'];
  // type de greffe (multiplication « greffe ») : greffes d'un œil (pépinière), de rameau (changer de cultivar), de laboratoire ; pont = réparation (« Greffer… » seulement, jamais une multiplication)
  const GREFFES = ['ecusson_t_inverse' => 'écusson en T inversé', 'ecusson_t' => 'écusson en T', 'chip' => 'chip budding (œil en copeau)',
    'chip_inverse' => 'chip inversé', 'placage' => 'placage d’œil', 'fente' => 'fente', 'sous_ecorce' => 'sous écorce (couronne)',
    'anglaise' => 'anglaise', 'pont' => 'pont', 'incrustation' => 'incrustation', 'approche' => 'en approche', 'laterale' => 'latérale',
    'souche' => 'sur souche', 'microgreffe' => 'microgreffe d’apex in vitro', 'minigreffe' => 'minigreffe en fente'];
  // saison de réalisation par type. Règle commune : porte-greffe sorti du repos (moyenne > 13 °C), œil du greffon pas encore débourré
  // (INRAE « Greffage 1/4 », même filiation que Curk 2022 p. 185 et 194) ; printemps le meilleur, plein été déconseillé sans abri frais (Curk).
  // ponytail: types sans source relue = la règle générale seule, à compléter quand une source est lue
  const GREFFE_SAISONS = [
    'ecusson_t_inverse' => 'porte-greffe en sève (l’écorce se soulève), en pleine croissance : d’avril à novembre en Floride (UF/IFAS HS1309), de mai à août en Alabama (Alabama ACES), au printemps ou à l’automne en Californie (UC Ventura) : les dates varient avec le lieu ; en Corse dès mars-avril sous tunnel, mai dehors (INRAE) ; le printemps de préférence, pas en plein été sans abri frais (Curk 2022, une seule source)',
    'ecusson_t' => 'porte-greffe en sève (l’écorce se soulève), en pleine croissance : d’avril à novembre en Floride (UF/IFAS HS1309), de mai à août en Alabama (Alabama ACES), au printemps ou à l’automne en Californie (UC Ventura) : les dates varient avec le lieu ; en Corse dès mars-avril sous tunnel, mai dehors (INRAE) ; le printemps de préférence, pas en plein été sans abri frais (Curk 2022, une seule source)',
    'chip' => 'quand l’écorce se soulève mal (UF/IFAS HS1309, INRAE) ou est trop épaisse pour un écusson (UF/IFAS HS1309, une seule source), ou pour un cultivar épineux, à rameaux triangulaires (INRAE, une seule source) ; saison : printemps (Curk 2022, règle générale)',
    'chip_inverse' => 'quand l’écorce se soulève mal (UF/IFAS HS1309, INRAE) ou est trop épaisse pour un écusson (UF/IFAS HS1309, une seule source), ou pour un cultivar épineux, à rameaux triangulaires (INRAE, une seule source) ; saison : printemps (Curk 2022, règle générale)',
    'sous_ecorce' => 'porte-greffe âgé, de gros diamètre, écorce trop épaisse pour un écusson mais qui se décolle (INRAE, une seule source) ; saison : printemps (Curk 2022, règle générale)',
    'minigreffe' => 'fente herbacée : très jeunes porte-greffes (tige d’environ 5 mm), greffon de jeune pousse ; sous abri presque toute l’année dans les pays plus chauds que la Corse (INRAE, une seule source)',
    'anglaise' => 'peu utilisée pour les agrumes (Curk 2022) ; sinon printemps (règle générale)',
    'microgreffe' => 'en laboratoire (in vitro) : toute saison',
  ];
  public static function greffeSaison($type) { return self::GREFFE_SAISONS[$type] ?? (array_key_exists($type, self::GREFFES) ? 'printemps (Curk 2022), porte-greffe sorti du repos, œil du greffon pas débourré (INRAE ; même filiation, une seule source) : règle générale, aucune source relue pour ce type' : ''); }
  // 0.27 : « perles » et « peu » passent de la saveur (id_pulpe) à l'aspect (id_pulpe_aspect) ; aspect déjà saisi gardé ; idempotent
  public static function migrerPulpe(array $c) {
    if (!in_array($c['id_pulpe'] ?? null, ['perles', 'peu'], true)) return $c;
    if (!is_string($c['id_pulpe_aspect'] ?? null) || $c['id_pulpe_aspect'] === '') $c['id_pulpe_aspect'] = $c['id_pulpe'];
    $c['id_pulpe'] = ''; return $c;
  }
  // lien posé à la greffe (« Greffer… ») : il décide de ce qu'on fait au contrôle J+21 (greffe.md, « Les liens »)
  const LIENS = ['polyethylene' => 'ruban de polyéthylène', 'elastique' => 'élastique de greffage', 'parafilm' => 'film qui se dégrade (Parafilm)',
    'degradable' => 'ruban qui se dégrade à la lumière (type Buddy Tape)', 'degradable_elastique' => 'ruban qui se dégrade + élastique', 'raphia' => 'raphia ou ficelle cirée'];
  public static function greffe(array $c) { // type de greffe gardé seulement s'il est connu, et il implique la multiplication « greffe » ; le pont est une réparation, pas une multiplication
    if (!array_key_exists($c['type_greffe'] ?? '', self::GREFFES) || $c['type_greffe'] === 'pont') { unset($c['type_greffe']); return $c; }
    if (($c['multiplication'] ?? '') === '') $c['multiplication'] = 'greffe';
    if ($c['multiplication'] !== 'greffe') unset($c['type_greffe']);
    return $c;
  }

  /* Plante saisie dans la tuile (« ＋ Plante ») : champs reçus → configuration retenue. Textes bornés, dimensions du pot > 0,
     emplacement pris dans $empl (noms) ou refusé, caractères d'identification valides seulement (le reste se complète dans la page du plugin). */
  const NOUVELLE = ['bota' => 120, 'cultivar' => 120, 'porte_greffe' => 120, 'origine' => 200, 'rusticite' => 40, 'date_mult' => 10];
  public static function nouvelle(array $in, array $empl) {
    $c = [];
    foreach (self::NOUVELLE as $k => $n) if (($v = self::texte($in[$k] ?? '', $n)) !== '') $c[$k] = $v;
    if (isset($c['date_mult']) && self::age($c['date_mult']) === null) throw new Exception(__('Date de multiplication : AAAA, AAAA-MM ou AAAA-MM-JJ, pas dans le futur', __FILE__)); // sinon ni jeunesse, ni suivi de greffe, sans un mot
    if (in_array($in['multiplication'] ?? '', self::MULTIPLICATIONS, true)) $c['multiplication'] = $in['multiplication'];
    $c = self::greffe($c + (isset($in['type_greffe']) ? ['type_greffe' => (string)$in['type_greffe']] : []));
    if (($c['multiplication'] ?? '') === 'greffe' && isset(self::LIENS[$l = (string)($in['lien_greffe'] ?? '')])) $c['lien_greffe'] = $l;
    foreach (['pot_diam', 'pot_haut', 'pot_terre'] as $k) if (($v = self::nombre($in[$k] ?? '')) !== null && $v > 0) $c[$k] = $v;
    if (($in['pot_forme'] ?? '') === 'carre') $c['pot_forme'] = 'carre';
    if (!empty($in['pleine_terre'])) $c['pleine_terre'] = 1;
    if (($e = (string)($in['emplacement'] ?? '')) !== '') {
      if (!in_array($e, $empl, true)) throw new Exception(__('Emplacement inconnu', __FILE__));
      $c['emplacement'] = $e;
    }
    foreach (self::observes(is_array($in['carac'] ?? null) ? $in['carac'] : []) as $k => $v) $c['id_' . $k] = $v;
    return $c;
  }

  /* « Identifier… » (fiche) : champs cochés → [configuration à écrire, lignes du journal « avant → après (source) »].
     Nom botanique et rusticité : textes bornés ; caractères : codes de la clé seulement ; champ vide, inchangé ou invalide ignoré. */
  public static function accepter(array $actuel, array $in, array $src = []) {
    $c = []; $l = [];
    $ch = ['bota' => ['Nom botanique', null], 'rusticite' => ['Rusticité', null]];
    foreach (self::CARACTERES as $k => [$lib, $opt]) $ch['id_' . $k] = [$lib, $opt];
    foreach ($ch as $k => [$lib, $opt]) {
      if (!isset($in[$k])) continue;
      $v = $opt === null ? self::texte($in[$k], $k == 'bota' ? 120 : 40) : (is_string($in[$k]) && isset($opt[$in[$k]]) ? $in[$k] : '');
      $a = trim(is_scalar($actuel[$k] ?? '') ? (string)($actuel[$k] ?? '') : '');
      $efface = $opt !== null && $in[$k] === '' && $a !== ''; // caractère remis à « – » dans « Identifier… »
      if (($v === '' && !$efface) || $v === $a) continue;
      $c[$k] = $v; $s = self::texte($src[$k] ?? '', 60);
      $l[] = $lib . ' : ' . ($a === '' ? '–' : ($opt[$a] ?? $a)) . ' → ' . ($v === '' ? '–' : ($opt[$v] ?? $v)) . ($s !== '' ? ' (' . $s . ')' : '');
    }
    return [$c, $l];
  }

  // date de semis / greffe « 2024 », « 2024-05 » ou « 2024-05-12 » → « ≈ 2 ans », « 7 mois », « 1 an » ; null si illisible ou à venir
  // jeune plante (semis, greffe, bouture, marcotte datés) moins rustique : +x °C à 0 an, décroît jusqu'à 0 à n ans ; achat ou sans date : 0
  // ponytail: décroissance linéaire, repères généraux (UF/IFAS : jeunes arbres à protéger, < 5 ans), rien de propre aux greffes
  public static function jeunesse($m, $d, $x = 6, $n = 5, $now = null) {
    if (!in_array($m, ['semis', 'greffe', 'bouture', 'marcotte'], true) || $n <= 0 || !preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/D', trim((string)$d), $g)) return 0.0;
    $mo = ($g[2] ?? '') !== '' ? (int)$g[2] : 7; $j = ($g[3] ?? '') !== '' ? (int)$g[3] : 1; // année seule : milieu d'année
    if ((int)$g[1] < 1900 || !checkdate($mo, $j, (int)$g[1])) return 0.0;
    $ans = max(0, (($now ?? time()) - mktime(12, 0, 0, $mo, $j, (int)$g[1])) / 31557600);
    return round(max(0, $x * (1 - $ans / $n)), 1);
  }

  // jeune plant : besoin en azote multiplié par f à 0 an, remonte à 1 à n ans (Niu 2024 : en pot, l'excès d'azote réduit les racines d'orangers d'un an) ; achat ou sans date : 1
  // ponytail: réutilise la décroissance linéaire de jeunesse() (en centièmes : elle arrondit à 0,1) ; f et n = choix du plugin, aucune source ne donne de courbe
  public static function facteurJeune($m, $d, $f = 0.25, $n = 3, $now = null) { return max(0.01, round(1 - self::jeunesse($m, $d, 100 * (1 - min(1, max(0, (float)$f))), $n, $now) / 100, 2)); } // jamais 0 : le taux divise

  /* Suivi d'une greffe récente : contrôle du bourgeon à J+21 (œil ou bourgeons verts et gonflés, lien retiré), puis à J+42 (débourrement,
     sinon forcer l'œil : porte-greffe entaillé aux 2/3 au-dessus de l'œil, haut couché ; décision de l'utilisateur, 2026-11-03) ; un statut n'avance que sur confirmation. Repères : UF/IFAS (lien retiré à 2-3 semaines),
     Alabama ACES (œil vert = pris ; vert sans pousse : forcer), UC Ventura (soudure en 3-4 semaines au printemps, 6-8 pour une greffe d'automne), minigreffe Valencia (bilan à 6 semaines).
     Œil gardé dormant jusqu'au printemps (greffe d'automne), puis forcé : UC Ventura (greffe d'automne) ; Arizona (az1146) et Kentucky disent de même, non cités dans la notice.
     Après le débourrement (« pousse ») : rejets du porte-greffe ôtés dès qu'ils sortent (UF/IFAS HS1309, Curk 2022 p. 210) ; pousse tuteurée, puis porte-greffe
     (haut couché compris) coupé à ≈ 1,3 cm (1/2 inch) au-dessus de l'œil une fois qu'il pousse (HS1309 seul) ; contrôle 4 semaines après (choix du plugin).
     Soins après la greffe (Lévy 2026, Greffeur d'agrumes, sous droits, faits seulement ; une seule source) : charpentières raccourcies à 5 feuilles après la 1re pousse,
     nouvelles pousses à 5 feuilles après la 2e, pousses sous la greffe ôtées et liens restants retirés après le premier hiver (février-mars), tous les fruits ôtés à la 1re floraison.
     Délais (4 puis 8 semaines, 1er mars / 1er septembre, 6 semaines) : choix du plugin. */
  const GREFFE_STATUTS = ['attente' => 'en attente', 'probable' => 'probablement prise', 'dormant' => 'greffon dormant jusqu’au printemps', 'pousse' => 'pousse du greffon',
    'taille1' => 'soins : 1re pousse à raccourcir', 'taille2' => 'soins : 2e pousse à raccourcir', 'hiver' => 'soins après le premier hiver', 'fleur' => 'soins : première floraison',
    'reprise' => 'réussie (suivi terminé)', 'echouee' => 'échouée'];
  const GREFFE_SUIVI = ['attente', 'probable', 'dormant', 'pousse', 'taille1', 'taille2', 'hiver', 'fleur']; // greffe en cours de suivi (ni réussie ni échouée)
  const GREFFE_SOINS = ['taille1', 'taille2', 'hiver', 'fleur']; // après la pousse, dans cet ordre, puis « reprise »
  const GREFFE_JOURS = ['attente' => 21, 'probable' => 42, 'pousse' => 28, 'taille1' => 28, 'taille2' => 56, 'fleur' => 42];
  /* Marche à suivre selon le type : œil (le forcer : entaille aux 2/3 au-dessus, haut couché) ; rameau sur porte-greffe déjà coupé
     (lien ou sac retiré quand le greffon pousse : Curk 2022 p. 186, fiche INRAE couronne 15 jours) ; latérale et approche, porte-greffe pas coupé
     (le couper au-dessus une fois la soudure faite, et sevrer le greffon d'approche : aucune source relue) ; pont : réparation d'une écorce blessée, la plante reste elle-même, pas de pousse attendue. */
  public static function greffeQuestion($statut, $type, $lien = '') { // $lien : clé de LIENS, '' = non précisé (texte général)
    $g = in_array($type, ['ecusson_t_inverse', 'ecusson_t', 'chip', 'chip_inverse', 'placage'], true) ? 'oeil'
      : (in_array($type, ['laterale', 'approche'], true) ? 'cote' : ($type == 'pont' ? 'pont' : 'rameau')); // type inconnu : rameau
    $rej = 'ôter les rejets du porte-greffe';
    $Q = [
      'oeil' => ['attente' => 'œil vert et gonflé, sans se ratatiner (retirer le ruban ou l’élastique pour voir ; film qui se dégrade : rien à retirer) ?',
        'probable' => 'l’œil a-t-il débourré ? (sinon le forcer : entailler le porte-greffe aux 2/3 au-dessus de l’œil et coucher le haut ; ou le laisser dormir jusqu’au printemps)',
        'dormant' => 'printemps : forcer l’œil (entailler le porte-greffe aux 2/3 au-dessus de l’œil et coucher le haut) ; a-t-il débourré ?',
        'pousse' => 'l’œil pousse : porte-greffe coupé à environ 1,3 cm au-dessus de l’œil (haut couché compris), pousse tuteurée, ' . $rej . ' ?'],
      'rameau' => ['attente' => 'bourgeons du greffon verts et gonflés (retirer le sac pour voir) ?',
        'probable' => 'le greffon pousse-t-il ? (retirer le lien quand les feuilles sortent)',
        'dormant' => 'printemps : les bourgeons du greffon ont-ils démarré ?',
        'pousse' => 'greffon tuteuré, lien retiré, ' . $rej . ' ?'],
      'cote' => ['attente' => 'greffon vert, sans se ratatiner ?',
        'probable' => 'le greffon pousse-t-il (un œil a débourré) ? une fois soudé, couper le porte-greffe au-dessus de la greffe (approche : sevrer aussi le greffon de sa plante)',
        'dormant' => 'printemps : couper le porte-greffe au-dessus de la greffe ; le greffon a-t-il démarré ?',
        'pousse' => 'greffon tuteuré, ' . $rej . ' ?'],
      'pont' => ['attente' => 'greffons du pont verts, sans se ratatiner ?', 'probable' => 'greffons du pont toujours verts et soudés aux deux bouts ?',
        'dormant' => 'printemps : greffons du pont toujours verts ?', 'pousse' => 'pousses sur les greffons du pont ôtées, ' . $rej . ' ?'],
    ];
    $S = ['taille1' => 'première pousse durcie : raccourcir les charpentières à 5 feuilles, ' . $rej . ' ?',
      'taille2' => 'deuxième pousse durcie : raccourcir les nouvelles pousses à 5 feuilles ?',
      'hiver' => 'premier hiver passé : ôter les pousses sous la greffe, retirer les liens restants ?',
      'fleur' => 'première floraison : ôter toutes les fleurs et petits fruits (pas encore de fleurs : « Douteux ») ?'];
    $fond = in_array($lien, ['parafilm', 'degradable'], true); // film qui se dégrade : rien à retirer (Hayashi 2012 pour le type Buddy Tape)
    if ($g == 'oeil' && $lien !== '' && isset(self::LIENS[$lien])) $Q['oeil']['attente'] = 'œil vert et gonflé, sans se ratatiner (' . ($lien == 'degradable_elastique' ? 'retirer l’élastique, laisser le ruban qui se dégrade et regarder l’œil à travers' : ($fond ? 'film qui se dégrade : rien à retirer, regarder l’œil à travers'
      : ($lien == 'raphia' ? 'raphia : le couper s’il n’a pas lâché, avant qu’il n’entaille l’écorce' : ($lien == 'elastique' ? 'retirer l’élastique pour voir' : 'retirer le ruban pour voir')))) . ') ?';
    if ($g == 'rameau' && $fond) { $Q['rameau']['probable'] = 'le greffon pousse-t-il ? (film qui se dégrade : rien à retirer)'; $Q['rameau']['pousse'] = 'greffon tuteuré, ' . $rej . ' ?'; }
    if ($g == 'rameau' && $lien == 'raphia') $Q['rameau']['probable'] = 'le greffon pousse-t-il ? (couper le raphia s’il n’a pas lâché, avant qu’il n’entaille l’écorce)';
    return $Q[$g][$statut] ?? ($g == 'pont' ? '' : ($S[$statut] ?? '')); // pont : pas de soins de jeune plant
  }
  /* Température pour souder (moyenne 24 h de l'emplacement) : sous ~21 °C le Poncirus s'endort (Alabama ACES : dormance quand le jour reste < 70 °F),
     cal le plus rapide vers 21-29 °C, prise moindre au-dessus de ~32 °C (sources d'amateurs : growingfruit.org, forum) → seuils réglables */
  public static function virgule($x) { return str_replace('.', ',', (string)$x); } // 11.2 → « 11,2 » (affichage français)
  public static function greffeTemp($moy, $min = 21, $max = 32) {
    if ($moy === null) return '';
    if ($moy < $min) return sprintf('trop froid pour souder : %s °C en moyenne sur 24 h (< %s °C)', self::virgule(floor($moy * 10) / 10), self::virgule($min)); // arrondi vers l'extérieur : jamais « 21 °C (< 21 °C) »
    if ($moy > $max) return sprintf('trop chaud pour souder : %s °C en moyenne sur 24 h (> %s °C)', self::virgule(ceil($moy * 10) / 10), self::virgule($max));
    return '';
  }
  /* Œil dormant : soudure faite, plus besoin de 21 °C ; reste à le protéger du gel (UC Ventura : greffe d'automne à protéger du gel).
     ponytail: 0 °C fixe sur le minimum 24 h, à rendre réglable si la sonde est loin de la plante */
  public static function greffeGel($min) {
    return $min !== null && $min < 0 ? sprintf('gel : protéger la greffe, %s °C au plus bas sur 24 h', self::virgule(floor($min * 10) / 10)) : '';
  }
  // verdict d'un contrôle (ok, douteux = revoir dans 7 jours, printemps = œil laissé dormant, mort) → [statut, prochain contrôle 'Y-m-d' ou '']
  public static function controler($statut, $verdict, $greffe, $jour, $sud = false) {
    if (!in_array($statut, self::GREFFE_SUIVI, true)) throw new Exception(__('Pas de contrôle de greffe en cours', __FILE__));
    $j = function ($d, $n) { return date('Y-m-d', strtotime($d . ' +' . $n . ' days')); };
    if ($verdict == 'mort') return ['echouee', ''];
    if ($verdict == 'douteux') return [$statut, $j($jour, 7)];
    if ($verdict == 'printemps' && in_array($statut, ['attente', 'probable'], true)) { // 15 mars (15 septembre dans l'hémisphère sud) ; plus tôt si l'emplacement est déjà assez chaud (voir journalcultures::suiviGreffe)
      $md = $sud ? '-09-15' : '-03-15'; $p = substr($jour, 0, 4) . $md; return ['dormant', $p > $jour ? $p : ((int)substr($jour, 0, 4) + 1) . $md];
    }
    if ($verdict != 'ok') throw new Exception(__('Verdict inconnu', __FILE__));
    if (($k = array_search($statut, self::GREFFE_SOINS, true)) !== false || $statut == 'pousse') { // soins l'un après l'autre
      $n = $statut == 'pousse' ? 'taille1' : (self::GREFFE_SOINS[$k + 1] ?? 'reprise');
      if ($n == 'reprise') return ['reprise', ''];
      if ($n == 'hiver') { $md = $sud ? '-09-01' : '-03-01'; $p = substr($jour, 0, 4) . $md; return ['hiver', $p > $jour ? $p : ((int)substr($jour, 0, 4) + 1) . $md]; } // après le premier hiver
      return [$n, $j($jour, self::GREFFE_JOURS[$n])]; // délai pour atteindre l'étape suivante
    }
    if ($statut != 'attente') return ['pousse', $j($jour, self::GREFFE_JOURS['pousse'])]; // débourré : soins de la pousse, revus dans 4 semaines
    return ['probable', max($j($greffe, self::GREFFE_JOURS['probable']), $jour)]; // J+42 déjà passé (réponse tardive) : la question suivante tout de suite
  }

  public static function age($d, $now = null) {
    if (!preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/D', trim((string)$d), $m)) return null;
    $now = $now ?? time(); $a = (int)$m[1]; $mo = ($m[2] ?? '') !== '' ? (int)$m[2] : null;
    if ($a < 1900 || ($mo !== null && !checkdate($mo, (int)($m[3] ?? 1), $a))) return null;
    $ans = function ($n) { return $n . ' an' . ($n > 1 ? 's' : ''); };
    if ($mo === null) { $n = (int)date('Y', $now) - $a; return $n < 0 ? null : ($n == 0 ? 'moins d’un an' : '≈ ' . $ans($n)); } // année seule : à un an près
    $j = ($m[3] ?? '') !== '' ? (int)$m[3] : null;
    $n = ((int)date('Y', $now) - $a) * 12 + (int)date('n', $now) - $mo - ($j !== null && (int)date('j', $now) < $j ? 1 : 0); // mois entiers révolus
    if ($n == 0 && $j !== null) { // moins d'un mois : en jours calendaires (DateTime : pas d'écart d'une heure au changement d'heure)
      $d = (new DateTime(sprintf('%04d-%02d-%02d', $a, $mo, $j)))->diff(new DateTime(date('Y-m-d', $now)));
      return $d->invert ? null : ($d->days == 0 ? 'aujourd’hui' : $d->days . ' jour' . ($d->days > 1 ? 's' : ''));
    }
    return $n < 0 ? null : ($n == 0 ? 'moins d’un mois' : ($n < 12 ? $n . ' mois' : $ans(intdiv($n, 12))));
  }

  /* Catalogue fixe des mesures : organe => [titre, [clé => [type, libellé, unité ou choix]]] ;
     type n = nombre, l = liste de nombres (plusieurs fruits), c = choix. Les valeurs vont au journal (colonne valeurs), clé « organe.clé ». */
  const MESURES = [
    'plante' => ['Plante', ['hauteur' => ['n', 'Hauteur', 'cm'], 'envergure' => ['n', 'Envergure', 'cm'], 'charpentieres' => ['n', 'Charpentières', ''],
      'vigueur' => ['c', 'Vigueur (1 à 5)', ['1', '2', '3', '4', '5']], 'etat' => ['c', 'État général (1 à 5)', ['1', '2', '3', '4', '5']],
      'stade' => ['c', 'Stade', ['repos', 'débourrement', 'pousse', 'floraison', 'fructification']]]],
    'tronc' => ['Tronc / greffe', ['d_tronc' => ['n', 'Ø du tronc (plante non greffée)', 'mm'], 'd_pg' => ['n', 'Ø porte-greffe (5 cm sous la greffe)', 'mm'],
      'd_greffon' => ['n', 'Ø greffon (5 cm au-dessus)', 'mm'], 'h_greffe' => ['n', 'Hauteur du point de greffe au-dessus du substrat', 'cm'], 'rejets' => ['n', 'Rejets du porte-greffe', ''],
      'ecorce' => ['c', 'Écorce', ['saine', 'fissures', 'gomme', 'lésions']]]],
    'epines' => ['Épines', ['longueur' => ['n', 'Longueur maximale', 'mm'], 'densite' => ['c', 'Densité', ['aucune', 'rares', 'nombreuses']],
      'position' => ['c', 'Position', ['bois jeune', 'vieux bois', 'partout']]]],
    'feuilles' => ['Feuilles', ['limbe_l' => ['n', 'Limbe : longueur', 'mm'], 'limbe_w' => ['n', 'Limbe : largeur', 'mm'], 'aile_l' => ['n', 'Aile : longueur', 'mm'],
      'aile_w' => ['n', 'Aile : largeur', 'mm'], 'nombre' => ['n', 'Nombre de feuilles', ''], 'pousses' => ['n', 'Nouvelles pousses', ''],
      'couleur' => ['c', 'Couleur', ['vert foncé', 'vert clair', 'jaunissement entre les nervures', 'jaune uniforme', 'V vert à la base, reste jaune', 'bronzé']],
      'touchees' => ['c', 'Feuilles touchées', ['jeunes', 'vieilles', 'toutes']], 'forme' => ['c', 'Forme', ['normale', 'petites et étroites', 'enroulées']],
      'chute' => ['c', 'Chute', ['aucune', 'faible', 'forte']]]],
    'fleurs' => ['Fleurs', ['boutons' => ['n', 'Boutons', ''], 'ouvertes' => ['n', 'Fleurs ouvertes', ''], 'couleur' => ['c', 'Couleur des boutons', ['blancs', 'violets']],
      'parfum' => ['c', 'Parfum', ['aucun', 'léger', 'fort']]]],
    'fruits' => ['Fruits', ['nombre' => ['n', 'Nombre de fruits (total)', ''], 'noues' => ['n', 'Noués', ''], 'croissance' => ['n', 'En croissance', ''], 'murs' => ['n', 'Mûrs', ''],
      'diametres' => ['l', 'Ø des fruits (un espace entre deux fruits)', 'mm'], 'poids' => ['n', 'Poids moyen', 'g'], 'pepins' => ['n', 'Pépins par fruit', ''],
      'brix' => ['n', 'Solides solubles (°Brix, surtout des sucres)', '°Brix'], 'recolte' => ['n', 'Poids récolté', 'g'], 'couleur' => ['c', 'Couleur', ['vert', 'en virage', 'jaune', 'orange']]]],
    'pot' => ['Racines / pot', ['substrat' => ['c', 'Substrat', ['sec', 'frais', 'humide', 'détrempé']], 'racines' => ['c', 'Racines sorties par les trous de drainage', ['non', 'oui']],
      'forme' => ['c', 'Nouveau pot : forme', ['rond', 'carré']], 'diam' => ['n', 'Nouveau pot : Ø ou côté', 'cm'], 'haut' => ['n', 'Nouveau pot : hauteur', 'cm'], 'terre' => ['n', 'Nouveau pot : hauteur de terre', 'cm'],
      'pleine_terre' => ['c', 'Pleine terre', ['non', 'oui']]]], // pot.forme/diam/haut/terre/pleine_terre : remplacent le pot de la fiche (journalcultures::mesurer)
    'sante' => ['Santé', ['signe' => ['c', 'Signe observé', ['aucun', 'feuilles collantes (miellat)', 'dépôt noir qui s’essuie', 'boucliers ou amas cotonneux blancs', 'petites mouches blanches qui s’envolent',
      'toiles fines, feuilles piquetées ou bronzées', 'galeries sinueuses argentées', 'points noirs épineux sous les feuilles', 'gomme ou écorce brune au collet', 'racines molles, écorce qui part (fil blanc)',
      'rameaux qui sèchent depuis la pointe', 'marbrures jaunes asymétriques']], // → journalcultures_calc::SIGNES
      'ravageur' => ['c', 'Ravageur', ['aucun', 'mineuse', 'cochenille', 'acarien', 'puceron', 'aleurode', 'autre']],
      'intensite' => ['c', 'Intensité', ['faible', 'moyenne', 'forte']], 'maladie' => ['c', 'Maladie', ['aucune', 'gommose', 'fumagine', 'autre']]]],
  ];
  const CALCULS = ['calc.aile_limbe' => ['Aile / limbe', ''], 'calc.aile_limbe_w' => ['Aile / limbe (largeur)', ''], 'calc.greffon_pg' => ['Greffon / porte-greffe', ''],
    'calc.fruits_moy' => ['Ø moyen des fruits', 'mm'], 'calc.fruits_min' => ['Ø minimal des fruits', 'mm'], 'calc.fruits_max' => ['Ø maximal des fruits', 'mm']];

  /* Saisie ['organe.clé' => texte] → valeurs contrôlées ['organe.clé' => nombre, [nombres] ou choix] ; vide = ignoré, invalide = exception.
     Liste : espaces ou « ; » séparent, la virgule est décimale (« 32 35,5 41 ») comme sur le pavé numérique français. */
  public static function valeurs($in) {
    $r = [];
    foreach (self::MESURES as $o => [, $champs]) foreach ($champs as $k => [$t, $l, $u]) {
      $v = is_scalar($in["$o.$k"] ?? null) ? trim((string)$in["$o.$k"]) : '';
      if ($v === '') continue;
      $faux = new Exception(sprintf(__('Valeur invalide : %s', __FILE__), $l));
      if ($t == 'c') { if (!in_array($v, $u, true)) throw $faux; $r["$o.$k"] = ctype_digit($v) ? (int)$v : $v; continue; } // notes 1 à 5 en nombres : courbes
      $ns = $t == 'l' ? preg_split('/[;\s]+/', strtr($v, [',' => '.']), -1, PREG_SPLIT_NO_EMPTY) : [strtr($v, [',' => '.'])];
      if (!$ns) continue; // « ; » seul
      foreach ($ns as $n) if (!is_numeric($n) || $n < 0 || $n > 100000) throw $faux;
      if ($o == 'pot' && $ns[0] <= 0) throw $faux; // pot de 0 cm : volume nul, plan d'engrais impossible
      if (count($ns) > 50) throw $faux;
      $r["$o.$k"] = $t == 'l' ? array_map('floatval', $ns) : (float)$ns[0];
    }
    return $r;
  }

  public static function calculs($v) { // rapports et statistiques tirés des valeurs
    $r = [];
    if (($v['feuilles.limbe_l'] ?? 0) > 0 && isset($v['feuilles.aile_l'])) $r['calc.aile_limbe'] = round($v['feuilles.aile_l'] / $v['feuilles.limbe_l'], 2);
    if (($v['feuilles.limbe_w'] ?? 0) > 0 && isset($v['feuilles.aile_w'])) $r['calc.aile_limbe_w'] = round($v['feuilles.aile_w'] / $v['feuilles.limbe_w'], 2);
    if (($v['tronc.d_pg'] ?? 0) > 0 && isset($v['tronc.d_greffon'])) $r['calc.greffon_pg'] = round($v['tronc.d_greffon'] / $v['tronc.d_pg'], 2);
    if ($d = $v['fruits.diametres'] ?? []) $r += ['calc.fruits_moy' => round(array_sum($d) / count($d), 1), 'calc.fruits_min' => min($d), 'calc.fruits_max' => max($d)];
    return $r;
  }

  public static function resume($v) { // texte lisible du relevé, pour la note du journal : « Feuilles : Limbe : longueur 85 mm ; Aile / limbe 0,59 »
    $nb = function ($x) { return str_replace('.', ',', (string)$x); };
    $g = [];
    foreach (self::MESURES as $o => [$titre, $champs]) {
      $l = [];
      foreach ($champs as $k => [$t, $lib, $u]) if (isset($v["$o.$k"])) {
        $x = $v["$o.$k"];
        $l[] = $lib . ' ' . ($t == 'c' ? $x : (is_array($x) ? implode(', ', array_map($nb, $x)) : $nb($x)) . ($u !== '' ? ' ' . $u : ''));
      }
      if ($l) $g[] = $titre . ' : ' . implode(', ', $l);
    }
    foreach (self::CALCULS as $k => [$lib, $u]) if (isset($v[$k])) $g[] = $lib . ' ' . $nb($v[$k]) . ($u !== '' ? ' ' . $u : '');
    return implode(' ; ', $g);
  }

  /* Caractères d'identification déduits des mesures : [caractère => code de CARACTERES] ; $forme = id_forme de la plante.
     ponytail: seuils simples (aile de moins de 2 mm de large = simple liseré ; aile comparée à la longueur du limbe : ¼ et ½, à sa largeur : ½ et ¾ ; feuille 5 et 10 cm ;
     fruit 3, 6 et 10 cm de Ø, sauf fruits en doigt ou en main dont la taille est une longueur) ; les affiner si une plante connue les contredit. */
  public static function suggestions($v, $forme = '') {
    $r = [];
    if (($v['feuilles.aile_w'] ?? 2) < 2) $r['aile'] = 'aucune';
    elseif (isset($v['calc.aile_limbe'])) { $x = $v['calc.aile_limbe']; $r['aile'] = $x == 0 ? 'aucune' : ($x < 0.25 ? 'etroite' : ($x < 0.5 ? 'large' : 'tres_large')); }
    if (($v['feuilles.aile_w'] ?? 0) >= 2 && isset($v['calc.aile_limbe_w'])) { $x = $v['calc.aile_limbe_w']; $r['aile_largeur'] = $x < 0.5 ? 'moins' : ($x < 0.75 ? 'moitie' : 'presque'); }
    if (isset($v['feuilles.limbe_l'])) { $x = $v['feuilles.limbe_l']; $r['feuille'] = $x < 50 ? 'petite' : ($x <= 100 ? 'moyenne' : 'grande'); }
    if (isset($v['calc.fruits_moy']) && !in_array($forme, ['doigt', 'main'], true)) { $x = $v['calc.fruits_moy']; $r['fruit'] = $x < 30 ? 'lt3' : ($x < 60 ? '3-6' : ($x <= 100 ? '6-10' : 'gt10')); }
    return $r;
  }

  /* ---------- engrais : catalogue, plan par plante, carences ---------- */

  // volume de terre en litres (pot rond : Ø ; carré : côté), null si pleine terre ou dimensions inconnues
  public static function volume(array $pot) {
    $d = (float)self::nombre($pot['diam'] ?? ''); $h = (float)(self::nombre($pot['terre'] ?? '') ?: self::nombre($pot['haut'] ?? ''));
    if (!empty($pot['pleine_terre']) || $d <= 0 || $h <= 0) return null;
    return round((!empty($pot['carre']) ? $d * $d : M_PI * $d * $d / 4) * $h / 1000, 1);
  }

  /* Catalogue « Nom | % N | toutes les n semaines | saison (semaines ISO) | apporte | remarque » par ligne.
     Produits en stock, pour toutes les plantes : une plante ne reçoit que ceux cochés dans son plan.
     % N et intervalle > 0 : produit possible du plan d'azote ; apporte = éléments (N P K Mg Fe S Zn Mn) pour les carences. Proposé quand le catalogue est vide.
     7e champ facultatif « g/L » : dose de l'étiquette en grammes par litre de pot et par apport ; le plan la suit au lieu de la calculer (corne : 3 × 1 g/L pendant la période d'apport). */
  const PRODUITS = "Ferti Agrumes 7-3-10 | 7 | 8 | 10-40 | N P K | granulés à enfouir par léger griffage ; étiquette : 80 à 240 g par sujet tous les 2 mois, de mars à octobre\n"
    . "Corne broyée | 13 | 12 | 10-40 | N | effet sur 3 à 4 mois ; étiquette : en pot, 3 apports de 1 g par litre de terreau dans la saison | 1\n"
    . "Sang séché | 12 | 0 | 10-30 | N | coup de fouet (effet en 3 à 4 jours) ; étiquette : 1 à 2 apports par an, printemps et été\n"
    . "Kélafer racinaire (fer EDDHA 6 %) | 0 | 0 | 6-44 | Fe | au pied seulement, jamais sur les feuilles ; dilué dans l'arrosage (10 à 40 g par litre d'eau = concentration ; la dose par pied reste la limite) ; étiquette : « horticulture » 1 à 3 g par pied (le plus proche du pot), jeunes plantations 5 à 15 g, seulement en cas de besoin\n"
    . "Sel d'Epsom (sulfate de magnésium) | 0 | 0 | 10-40 | Mg S | pas de dose sur le seau (produit pour le bain)";

  public static function produits($texte) { // → [['nom', 'n' (%), 'tous' (semaines), 'saison', 'apporte' => [éléments], 'note', 'gl' (g/L de l'étiquette, 0 = calculé)], …]
    $r = [];
    foreach (preg_split('/\R/u', (string)$texte) ?: [] as $l) {
      $p = array_map('trim', explode('|', $l));
      if ($p[0] === '') continue;
      $r[] = ['nom' => mb_substr($p[0], 0, 60), 'n' => max(0, min(100, (float)self::nombre($p[1] ?? ''))), 'tous' => max(0, min(52, (int)self::nombre($p[2] ?? ''))),
        'saison' => preg_match('/^(\d{1,2})-(\d{1,2})$/D', $p[3] ?? '', $m) && $m[1] >= 1 && $m[1] <= 53 && $m[2] >= 1 && $m[2] <= 53 ? $p[3] : '10-40',
        'apporte' => array_values(array_intersect(preg_split('/[\s,]+/', strtoupper($p[4] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['N', 'P', 'K', 'MG', 'FE', 'S', 'ZN', 'MN'])),
        'note' => mb_substr($p[5] ?? '', 0, 300), 'gl' => max(0, min(100, (float)self::nombre($p[6] ?? '')))];
    }
    return $r;
  }

  /* Plan d'azote d'une plante en pot : besoin = $taux g d'N par litre de pot et par semaine (0,07 = 0,14 g toutes les 2 semaines, ressources §13),
     réparti entre les produits du plan selon $parts (index du catalogue => %, total 100). → [['i', 'ligne' (« 10-40/8 | Nom | 40 g »), 'g'], …]
     Produit à dose d'étiquette (gl > 0) : litres × gl, sa part ignorée ; l'azote qu'il apporte est retiré du besoin, le reste va aux autres selon leurs parts.
     ponytail: dose proportionnelle au volume seul (aucune source ne la fait varier avec la taille de la plante en pot) ; régler $taux à l'usage. */
  public static function plan($litres, array $produits, array $parts, $taux = 0.07, array $doses = []) { // doses : grammes ajustés à la main, prioritaires sur le calcul
    $r = [];
    if (!$litres) return $r;
    $pris = array_filter($produits, function ($p, $i) use ($parts) { return $p['n'] > 0 && $p['tous'] > 0 && (float)($parts[$i] ?? 0) > 0; }, ARRAY_FILTER_USE_BOTH);
    $etiq = 0; $autres = 0;
    foreach ($pris as $i => $p) if (($p['gl'] ?? 0) > 0) $etiq += $litres * $p['gl'] * $p['n'] / 100 / $p['tous']; else $autres += min(100, (float)$parts[$i]);
    $reste = max(0, 1 - $etiq / ($litres * $taux)); // part du besoin laissée aux produits calculés
    foreach ($pris as $i => $p) {
      if (!(($doses[$i] ?? 0) > 0) && !(($p['gl'] ?? 0) > 0) && $reste <= 0) continue; // besoin déjà couvert par les doses d'étiquette : pas de 1 g d'office
      $g = ($doses[$i] ?? 0) > 0 ? round($doses[$i], 1) : max(1, round(($p['gl'] ?? 0) > 0 ? $litres * $p['gl']
        : $litres * $taux * $p['tous'] * $reste * min((float)$parts[$i], 100) / $autres * 100 / $p['n'])); // parts sur 100 sans étiquette : $reste = 1, $autres = 100
      $r[] = ['i' => $i, 'g' => $g, 'ligne' => $p['saison'] . '/' . $p['tous'] . ' | ' . $p['nom'] . ' | ' . str_replace('.', ',', (string)$g) . ' g'];
    }
    return $r;
  }

  /* Carence probable d'après la dernière mesure des feuilles (ressources §14 : UF/IFAS, Haifa, Elavarasan 2019).
     → [['el' => élément, 'txt' => raison], …] (azote mobile : vieilles feuilles d'abord ; soufre : sources en désaccord sur l'organe, pas de piste) ; substrat détrempé : averti d'abord (l'asphyxie bloque le fer). Jamais un diagnostic : des pistes. */
  public static function carences(array $v) {
    $c = $v['feuilles.couleur'] ?? ''; $o = $v['feuilles.touchees'] ?? ''; $r = [];
    if (($v['pot.substrat'] ?? '') == 'détrempé') $r[] = ['el' => 'EAU', 'txt' => 'substrat détrempé : racines asphyxiées, le fer n’est plus absorbé ; arroser moins avant tout apport'];
    if (in_array($c, ['jaune uniforme', 'vert clair'], true)) $r[] = $o == 'jeunes' ? ['el' => '?', 'txt' => 'jeunes feuilles pâles ou jaunes d’abord : pas l’azote (il quitte d’abord les vieilles feuilles) ; soufre possible, mais les sources ne s’accordent pas sur l’organe atteint']
      : ['el' => 'N', 'txt' => $o == 'vieilles' ? 'feuilles pâles ou jaunes, vieilles feuilles d’abord : azote' : 'feuilles pâles ou jaunes partout : azote'];
    if ($c == 'V vert à la base, reste jaune' || ($c == 'jaunissement entre les nervures' && $o == 'vieilles')) $r[] = ['el' => 'MG', 'txt' => 'vieilles feuilles jaunes entre les nervures, V vert à la base : magnésium'];
    if ($c == 'jaunissement entre les nervures' && $o == 'jeunes') $r[] = ['el' => 'FE', 'txt' => 'jeunes feuilles pâles à nervures vertes : fer (pH élevé, calcaire, excès d’eau ou de cuivre)'];
    if ($c == 'jaunissement entre les nervures' && !in_array($o, ['jeunes', 'vieilles'], true)) $r[] = ['el' => '?', 'txt' => $o == 'toutes' ? 'jaunissement entre les nervures sur tout le feuillage : fer si les jeunes feuilles ont commencé, magnésium si ce sont les vieilles' : 'jaunissement entre les nervures : fer si ce sont les jeunes feuilles, magnésium si ce sont les vieilles (préciser « Feuilles touchées »)'];
    if ($c == 'bronzé') $r[] = $o == 'jeunes' ? ['el' => '?', 'txt' => 'jeunes feuilles bronzées : plutôt des acariens qu’une carence (voir Santé)'] : ['el' => 'K', 'txt' => 'feuilles adultes bronzées' . (($v['feuilles.forme'] ?? '') == 'enroulées' ? ' et enroulées' : '') . ' : potassium (ou magnésium)'];
    if (($v['feuilles.forme'] ?? '') == 'petites et étroites') $r[] = ['el' => 'ZN', 'txt' => 'feuilles petites, étroites et pointues, en rosette : zinc (souvent avec fer et manganèse quand le pH est élevé)'];
    return $r;
  }

  /* ---------- santé : problèmes surveillés et pistes ---------- */
  /* Agrumes en pot sous serre (Île-de-France), du plus important au moins important. Références complètes : docs/fr_FR/sante.md, partie Sources.
     Chaque geste porte ses sources ; un geste ou un problème à une seule source est signalé (en chercher une autre). statut : present, quarantaine, absent, pour la France ; regional = statut propre à la France
     (ailleurs : « à vérifier pour ce pays », journalcultures::santeCat). Périodes de risque : hémisphère nord (Corse, PACA, Californie, Floride). */
  const SANTE = [
    'gommose' => ['nom' => 'Gommose et pourriture des racines (Phytophthora)', 'statut' => 'present',
      'signes' => 'gomme, écorce brune qui se fend au collet ou au point de greffe ; feuilles vert pâle à nervures jaunes (atteinte sévère), qui tombent ; racines fines molles dont l’écorce part (il reste un fil blanc) ; favorisée par un substrat qui reste mouillé',
      'sources' => ['UC IPM (gommose)', 'Vogel 1990', 'BSV PACA 2020', 'NHB', 'Sáenz Pérez 2019', 'Savita 2012', 'Whiteside 1971', 'Oliveira 1981', 'UF/IFAS HS261', 'TNAU'],
      'gestes' => [['arroser moins, laisser ressuyer, pot qui draine', ['UC IPM (gommose)', 'BSV PACA 2020', 'NHB', 'UF/IFAS HS261']],
        ['ne pas mouiller le tronc', ['NHB', 'Savita 2012']],
        ['collet et point de greffe jamais enterrés : de la terre sur le bas du tronc favorise la maladie', ['UC IPM (gommose)', 'Sáenz Pérez 2019', 'Savita 2012', 'Whiteside 1971']],
        ['ne pas blesser le bas du tronc ni les racines (pas de travail du sol au pied) : les plaies sont la porte d’entrée', ['Savita 2012', 'Whiteside 1971', 'Oliveira 1981']],
        ['enlever l’écorce brune et une bande d’écorce saine autour, laisser sécher, revoir pendant quelques mois (seulement si la lésion fait moins de la moitié du tour du tronc)', ['UC IPM (gommose)', 'NHB', 'TNAU']],
        ['badigeon de cuivre (bouillie bordelaise) sur la plaie et le bas du tronc', ['UC IPM (gommose)', 'NHB']],
        ['en dernier : fongicide (phosphonate, fosétyl-Al chez les professionnels) ; un particulier n’a droit qu’aux produits marqués « emploi autorisé dans les jardins » (loi Labbé) : s’il en existe un pour cet usage (vérifier sur ephy.anses.fr), à la dose et pour l’usage de l’étiquette', ['UC IPM (gommose)', 'Vogel 1990', 'NHB', 'Sáenz Pérez 2019', 'Savita 2012']],
        ['plus de la moitié du tour du tronc atteinte : le déclin se voit dans le feuillage, le grattage ne suffit plus ; remplacer la plante (conseil économique, pas un résultat d’essai)', ['UC IPM (gommose)', 'UF/IFAS HS261', 'TNAU']]],
      'note' => 'une plaie fraîche est envahie le plus facilement, rarement après une dizaine de jours ; l’écorce intacte et subérisée n’a pas été pénétrée, mais les jeunes tiges encore sans liège, si : jeunes plants plus exposés (Whiteside 1971, une seule source)'],
    'cochenille' => ['nom' => 'Cochenilles', 'statut' => 'present',
      'signes' => 'boucliers bruns ou noirs, amas cotonneux blancs sur rameaux et nervures ; miellat, fumagine, fourmis',
      'sources' => ['Brun 1990', 'BSV Corse 2024', 'BSV PACA 2020', 'BSV JEVI Corse 2025', 'COCHORTI 2021'],
      'gestes' => [['enlever à la main celles qu’on atteint, couper les rameaux très touchés', ['BSV JEVI Corse 2025']],
        ['auxiliaires : Cryptolaemus montrouzieri, Anagyrus vladimiri (farineuses), Rodolia cardinalis (australienne)', ['BSV Corse 2024', 'BSV PACA 2020', 'Brun 1990', 'COCHORTI 2021']],
        ['huile (blanche ou de colza), effet asphyxiant, en couvrant bien tout le feuillage', ['Brun 1990', 'BSV JEVI Corse 2025']]],
      'note' => 'huile, savon : arroser la veille, jamais aux heures chaudes (Cloyd 2016, Brun 1990) ; huile seulement entre environ 5 et 24 °C (Cloyd 2016, une seule source) ; essayer d’abord sur quelques feuilles, pas en même temps que cuivre ou soufre (précaution du plugin). Le mélange savon noir + huile + alcool (BSV JEVI, conseil donné pour le figuier) n’est pas retenu : produit non autorisé, 5 % d’huile au lieu de 1,5 à 2 %, aucune étude sur agrumes ; une pulvérisation qui mouille tue aussi les larves de coccinelles et de chrysopes (Cloyd 2016) : pas de traitement après un lâcher de Cryptolaemus. Les fourmis défendent les cochenilles (BSV PACA 2020) ; aucune méthode efficace contre elles n’est connue (BSV Corse 2024)'],
    'acarien' => ['nom' => 'Acariens (araignées rouges, tétranyques)', 'statut' => 'present',
      'signes' => 'feuilles piquetées, jaunies, blanchies ou bronzées, petites toiles, surtout les jeunes feuilles ; difficiles à voir à l’œil nu',
      'sources' => ['BSV PACA 2020', 'Brun 1990', 'Réseau d’alerte d’Andalousie (via la presse)', 'NC State (P. citri)', 'Kasap 2009', 'Chouikhi 2022'],
      'gestes' => [['regarder les deux faces des jeunes feuilles à la loupe', ['BSV PACA 2020']],
        ['pas d’excès d’engrais', ['BSV PACA 2020']],
        ['préserver les auxiliaires : acariens prédateurs (Neoseiulus californicus, Phytoseiulus persimilis, Euseius stipulatus), coccinelles Stethorus', ['Brun 1990', 'Chouikhi 2022']],
        ['huile blanche, en couvrant bien', ['Brun 1990']]],
      'note' => 'huile : arroser la veille, jamais aux heures chaudes (Cloyd 2016, Brun 1990), seulement entre environ 5 et 24 °C (Cloyd 2016, une seule source) ; essayer d’abord sur quelques feuilles (précaution du plugin). Le climat dépend de l’espèce : tétranyque tisserand (Tetranychus urticae) vers 30 °C, supporte l’air sec (Andalousie, BSV PACA 2020) ; acarien rouge des agrumes (Panonychus citri) vers 25 °C, humidité modérée (Andalousie ; Kasap 2009 n’a testé que 65 %), à 35 °C 76 % meurent sans pondre (Kasap 2009), freiné par le très chaud et sec (NC State) ; pullule l’hiver en Tunisie (Chouikhi 2022, une seule source). Sous serre, les deux sont possibles : des toiles nettes font penser à T. urticae (déduction du plugin)'],
    'aleurode' => ['nom' => 'Aleurodes (mouches blanches : Aleurothrixus floccosus, Dialeurodes citri)', 'statut' => 'present',
      'signes' => 'petites mouches blanches qui s’envolent quand on secoue, larves plates sous les feuilles ; miellat, fumagine',
      'sources' => ['Brun 1990', 'BSV Corse 2024', 'Barbagallo 1992', 'Soto 2001', 'Liu et Stansly 2000'],
      'gestes' => [['aleurode floconneux : Cales noacki le parasite parfois à 100 %, traiter rarement nécessaire', ['Brun 1990', 'Barbagallo 1992', 'Soto 2001']],
        ['aleurode des agrumes (Dialeurodes citri) : Encarsia, taux de parasitisme 70-80 % en Italie, souvent moins de 20 % à Valence, résultat variable', ['Barbagallo 1992', 'Soto 2001']],
        ['huile blanche contre les larves, en couvrant le dessous des feuilles', ['Brun 1990']]],
      'note' => 'huile : essai sur d’autres aleurodes (Liu et Stansly 2000) ; plus efficace contre Dialeurodes citri que contre l’aleurode floconneux (étude turque de 1996, citée de seconde main). Arroser la veille, jamais aux heures chaudes (Cloyd 2016, Brun 1990), huile seulement entre environ 5 et 24 °C (Cloyd 2016, une seule source) ; essayer d’abord sur quelques feuilles (précaution du plugin)'],
    'puceron' => ['nom' => 'Pucerons', 'statut' => 'present',
      'signes' => 'colonies sur les jeunes pousses tendres, feuilles enroulées ; miellat',
      'sources' => ['Brun 1990', 'BSV JEVI Corse 2025', 'Gómez-Marco 2016', 'J. Econ. Entomol. 2019'],
      'gestes' => [['surveiller les pousses tendres : une feuille ferme et coriace convient mal aux grosses colonies ; sur pomelo (C. ×paradisi), rarement nécessaire d’intervenir', ['Brun 1990']],
        ['favoriser les auxiliaires (coccinelles, surtout les petites Scymnus, chrysopes, syrphes) : ils doivent être là dès les premières colonies', ['Gómez-Marco 2016', 'J. Econ. Entomol. 2019']]]],
    'fumagine' => ['nom' => 'Fumagine', 'statut' => 'present',
      'signes' => 'dépôt noir comme de la suie, qui s’essuie, sur feuilles et fruits ; champignons noirs qui poussent sur le miellat d’un insecte ; n’attaquent pas la feuille',
      'sources' => ['NHB', 'BSV JEVI Corse 2025', 'BSV PACA 2020', 'Brun 1990'],
      'gestes' => [['trouver l’insecte qui fait le miellat (cochenille, aleurode, puceron) et le traiter : la fumagine part ensuite d’elle-même', ['NHB', 'BSV PACA 2020', 'Brun 1990']],
        ['laver les feuilles à l’eau savonneuse', ['BSV JEVI Corse 2025']]]],
    'mineuse' => ['nom' => 'Mineuse des agrumes (Phyllocnistis citrella)', 'statut' => 'present',
      'signes' => 'galeries sinueuses argentées dans les jeunes feuilles, qui se crispent et s’enroulent ; surtout les pousses d’été',
      'sources' => ['BSV PACA 2020', 'BSV Corse 2024', 'BSV JEVI Corse 2025'],
      'gestes' => [['plante adulte : dégâts surtout esthétiques (BSV PACA) ; risque réel pour les jeunes plantes (BSV Corse)', ['BSV PACA 2020', 'BSV Corse 2024']],
        ['couper les jeunes pousses atteintes', ['BSV PACA 2020']],
        ['si besoin, produit de biocontrôle', ['BSV Corse 2024']]]],
    'mal_secco' => ['nom' => 'Mal secco (Plenodomus tracheiphilus, citronniers, limes, cédratiers)', 'statut' => 'present', 'regional' => 1,
      'signes' => 'feuilles qui tombent depuis la pointe des rameaux, rameaux qui sèchent, bois coloré saumon à rouge carotte sous l’écorce ; infections surtout de novembre à février (pic en janvier)',
      'sources' => ['Chapot 1963', 'Migheli 2009', 'Nigro 2011', 'OEPP 2024 (mal secco)', 'OEPP 2026 (mal secco)', 'Catane 2022-2023', 'Spina et Cutuli 1983'],
      'gestes' => [['couper jusqu’au bois sain (sans coloration) : le champignon gagne la plante par le bois ; déchets en sac fermé à la déchetterie, jamais au compost (Chapot dit brûler : interdit aux particuliers aujourd’hui)', ['Chapot 1963', 'Catane 2022-2023']],
        ['protéger les plaies au cuivre (la lutte chimique repose sur le cuivre) ; éviter toute blessure l’hiver, c’est par elles qu’il entre', ['Chapot 1963', 'Spina et Cutuli 1983']]],
      'note' => 'désinfecter les outils entre deux coupes : alcool à 70° (choix du plugin) ; Chapot les trempait dans du sulfate de cuivre à 5 %. Organisme réglementé non de quarantaine (ORNQ) dans l’Union européenne : déclaration non obligatoire chez un particulier. Très sensibles : citronnier, lime vraie, cédratier (Chapot) ; l’OEPP cite aussi bergamote et bigaradier. Sensibilité comparée : observations au champ, pas toujours confirmées par des tests (OEPP 2024). Présent en France (continent et Corse), de façon limitée (OEPP 2026)'],
    'aleurode_epineux' => ['nom' => 'Aleurode épineux (Aleurocanthus spiniferus)', 'statut' => 'quarantaine', 'regional' => 1,
      'signes' => 'points noirs épineux sous les feuilles (larves et nymphes noires hérissées d’épines), miellat, fumagine, fourmis',
      'sources' => ['Fiche SORE (aleurode épineux)', 'EFSA 2018', 'OEPP RS 2023/160', 'OEPP RS 2024/217', 'OEPP 2026 (aleurode épineux)', 'OEPP (fiche aleurode épineux)', 'Cioffi 2013'],
      'gestes' => [['photographier le dessous des feuilles et signaler à la DRAAF-SRAL (service de l’État) ou à la FREDON de sa région, photos jointes (organisme de quarantaine)', ['Fiche SORE (aleurode épineux)']]],
      'note' => 'en attendant, ne pas déplacer ni donner la plante (précaution du plugin) ; présent en France depuis 2023 (Gard et Hérault : OEPP RS 2023/160 ; Menton : OEPP RS 2024/217 ; continent et Corse : OEPP 2026) ; aussi en Italie (depuis 2008, dans les Pouilles : fiche OEPP, Porcelli 2008), Grèce, Croatie… (OEPP 2026) ; savons et mouillants ne le contrôlent qu’en partie (Cioffi 2013) ; chez l’aleurode floconneux, l’imidaclopride (interdit en France) perturbe les parasitoïdes et provoque une résurgence (Cioffi 2013)'],
    'tristeza' => ['nom' => 'Tristeza (CTV, présente en Haute-Corse)', 'statut' => 'present', 'regional' => 1,
      'signes' => 'déclin des arbres greffés sur bigaradier ; virus transmis par pucerons et matériel végétal',
      'sources' => ['OEPP 2026 (tristeza)', 'Vogel 1990', 'Sáenz Pérez 2019', 'UF/IFAS HS261', 'Boccas et Laville 1976', 'Vanderweyen 1980'],
      'gestes' => [['plants et greffons d’origine contrôlée', ['Vogel 1990', 'Sáenz Pérez 2019']],
        ['porte-greffe tolérant plutôt que bigaradier (Sáenz Pérez 2019 cite Volkameriana, Cléopâtre…) ; mais sous serre la gommose est le risque n° 1. Contre la tristeza : Poncirus résistant, citranges et citrumelo tolérants ; tous trois résistants à la gommose. Face à la gommose, le Volkameriana donne des résultats contradictoires (résistant pour UF/IFAS HS261, selon le clone pour Boccas et Laville 1976, très sensible pour Vanderweyen 1980)', ['Sáenz Pérez 2019', 'UF/IFAS HS261', 'Boccas et Laville 1976', 'Vanderweyen 1980']]],
      'note' => 'présente en France seulement en Haute-Corse : déclarée en 2018, souches européennes établies, qu’on ne peut plus éradiquer ; jamais trouvée en Corse-du-Sud (OEPP 2026) ; en 1990, l’interdiction d’importer des plants la tenait hors de Corse (Vogel 1990). Réglementée dans l’Union européenne : quarantaine pour les souches venues d’ailleurs, organisme réglementé non de quarantaine (ORNQ) pour les plants vendus (règlement 2019/2072). Hors de Corse, le risque vient d’un plant ou d’un greffon importé'],
    'hlb' => ['nom' => 'HLB (greening, dragon jaune)', 'statut' => 'absent', 'regional' => 1,
      'signes' => 'marbrures jaunes asymétriques des feuilles, fruits déformés qui se colorent à l’envers, psylles sur les jeunes pousses',
      'sources' => ['UF/IFAS PP328', 'BSV JEVI Corse 2025', 'Sáenz Pérez 2019', 'OEPP 2026 (Diaphorina citri)'],
      'gestes' => [['d’abord le test de symétrie : jaunissement pareil des deux côtés de la nervure = carence (zinc, magnésium…), asymétrique = suspect', ['UF/IFAS PP328']],
        ['asymétrique avec psylles : signaler à la DRAAF-SRAL (service de l’État) ou à la FREDON de sa région ; aucun traitement n’existe', ['BSV JEVI Corse 2025', 'Sáenz Pérez 2019']]],
      'note' => 'bactérie absente de France métropolitaine ; deux psylles vecteurs sont présents dans l’Union européenne : Trioza erytreae en Espagne et au Portugal (BSV JEVI Corse 2025), Diaphorina citri à Chypre, de passage en Grèce, seulement intercepté en France (OEPP 2026) ; décolorations asymétriques aussi décrites par BSV JEVI ; peut se confondre avec un manque de zinc (Sáenz Pérez 2019)'],
  ];
  /* Signe observé (mesure sante.signe) → problèmes possibles, dans l'ordre de SANTE */
  const SIGNES = ['feuilles collantes (miellat)' => ['cochenille', 'aleurode', 'puceron'], 'dépôt noir qui s’essuie' => ['fumagine'], 'boucliers ou amas cotonneux blancs' => ['cochenille'],
    'petites mouches blanches qui s’envolent' => ['aleurode'], 'toiles fines, feuilles piquetées ou bronzées' => ['acarien'], 'galeries sinueuses argentées' => ['mineuse'],
    'points noirs épineux sous les feuilles' => ['aleurode_epineux'], 'gomme ou écorce brune au collet' => ['gommose'], 'racines molles, écorce qui part (fil blanc)' => ['gommose'], 'rameaux qui sèchent depuis la pointe' => ['mal_secco', 'gommose'],
    'marbrures jaunes asymétriques' => ['hlb']];

  /* Pistes d'après la dernière mesure de santé : signe observé, ravageur ou maladie nommés, écorce du tronc. $citron : plante du groupe Citron ou Lime (mal secco).
     → [['id', 'pourquoi', 'note'?], …] dans l'ordre de priorité de SANTE. Des pistes, jamais un diagnostic. */
  public static function sante(array $v, $citron) {
    $ids = [];
    $add = function ($id, $p) use (&$ids) { if (isset(self::SANTE[$id])) $ids[$id][] = $p; };
    foreach (self::SIGNES[$v['sante.signe'] ?? ''] ?? [] as $id) $add($id, $v['sante.signe']);
    $r = $v['sante.ravageur'] ?? ''; if ($r !== '' && $r !== 'aucun' && $r !== 'autre') $add($r, 'ravageur noté' . (($v['sante.intensite'] ?? '') !== '' ? ' (' . $v['sante.intensite'] . ')' : ''));
    $m = $v['sante.maladie'] ?? ''; if (in_array($m, ['gommose', 'fumagine'], true)) $add($m, 'maladie notée');
    if (in_array($v['tronc.ecorce'] ?? '', ['gomme', 'lésions'], true)) $add('gommose', 'écorce : ' . $v['tronc.ecorce']);
    $out = [];
    foreach (array_keys(self::SANTE) as $id) {
      if (!isset($ids[$id])) continue;
      $x = ['id' => $id, 'pourquoi' => implode(' ; ', array_unique($ids[$id]))];
      if ($id == 'mal_secco' && !$citron) $x['note'] = 'peu probable : touche surtout citronniers et limes ; voir d’abord le collet et l’arrosage';
      if ($id == 'gommose' && ($v['pot.substrat'] ?? '') == 'détrempé') $x['note'] = 'substrat détrempé à la même mesure';
      $out[] = $x;
    }
    return $out;
  }

  public static function date($v) { // 'Y-m-d' valide ou exception
    $v = (string)$v;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new Exception(__('Date invalide', __FILE__));
    return $v;
  }

  // Notice : Markdown restreint (titres, paragraphes, listes « - », tableaux, gras, italique, code, liens, images de docs/images) → HTML.
  // ponytail: pas un parseur complet, le texte vient de nos docs/ ; tout est échappé avant d'être balisé.
  const NOTICES = ['index' => 'Mode d\'emploi', 'fertilisation' => 'Fertilisation', 'sante' => 'Santé', 'greffe' => 'Greffe', 'identification' => 'Identification', 'guide' => 'Botanique', 'noms' => 'Noms des plantes'];
  /* Origine des mots d'un nom botanique : entrées « - **Mot** … » de docs/fr_FR/noms.md (une entrée = une ligne), [html] dans l'ordre du nom.
     Cultivar entre guillemets, parenthèses, ×, var., subsp. ignorés ; mot absent du dictionnaire : rien. */
  public static function etymologie(array $noms) {
    static $L = null;
    if ($L === null) { // dictionnaire lu une fois puis gardé en JSON dans le dossier temporaire, tant que noms.md n'a pas changé
      $f = __DIR__ . '/../../docs/fr_FR/noms.md'; $c = jeedom::getTmpFolder('journalcultures') . '/noms_' . (is_file($f) ? substr(md5_file($f), 0, 8) : '') . '.json'; // contenu dans le nom : une mise à jour dans la même seconde ne sert pas l'ancien
      $L = is_file($f) && is_file($c) && filemtime($c) >= max(filemtime($f), filemtime(__FILE__)) ? json_decode((string)file_get_contents($c), true) : null;
      if (!is_array($L)) {
        $L = [];
        if (is_file($f)) foreach (file($f, FILE_IGNORE_NEW_LINES) as $l) if (preg_match('/^- \*\*([\p{L}-]+)\*\*(.*)$/u', $l, $m)) $L[mb_strtolower($m[1])] = '**' . $m[1] . '**' . $m[2];
        if (is_file($f)) self::cache($c, json_encode($L, JSON_UNESCAPED_UNICODE));
      }
    }
    $r = [];
    foreach ($noms as $n) foreach (preg_split('/[^\p{L}-]+/u', preg_replace("/(['‘’\"«»]).*?['‘’\"«»]|\(.*?\)/u", ' ', (string)$n)) as $w)
      foreach ([$k = mb_strtolower($w), preg_replace('/(a|um)$/u', 'us', $k), preg_replace('/e$/u', 'is', $k), preg_replace('/(us|um)$/u', 'a', $k)] as $v) // sativa, sativum → sativus ; officinale → officinalis
        if (isset($L[$v])) { if (!isset($r[$v])) $r[$v] = self::enligne($L[$v]); break; }
    return array_values($r);
  }
  private static function cache($c, $v) { // écriture atomique (deux requêtes à la fois ne lisent jamais un fichier à moitié écrit)
    if (!is_dir(dirname($c))) @mkdir(dirname($c), 0775, true);
    if (@file_put_contents($c . '.' . getmypid(), $v) !== false) @rename($c . '.' . getmypid(), $c);
  }
  /* Une page de la notice en HTML, gardée dans le dossier temporaire tant qu'elle est plus récente que son .md, ce fichier
     (le convertisseur) et docs/images (dates des URL d'images). */
  public static function notice($k) {
    if (!isset(self::NOTICES[$k]) || !is_file($f = __DIR__ . '/../../docs/fr_FR/' . $k . '.md')) throw new Exception(__('Page de notice inconnue', __FILE__));
    $c = jeedom::getTmpFolder('journalcultures') . '/notice_' . $k . '_' . substr(md5_file($f), 0, 8) . '.html'; // ponytail: anciennes versions laissées au dossier temporaire (vidé au redémarrage)
    $t = max(array_merge([filemtime($f), filemtime(__FILE__), filemtime(__DIR__ . '/../../docs/images')], array_map('filemtime', glob(__DIR__ . '/../../docs/images/*') ?: [])));
    if (is_file($c) && filemtime($c) >= $t) return (string)file_get_contents($c);
    self::cache($c, $h = self::markdown(file_get_contents($f)));
    return $h;
  }
  public static function ancre($t) { return trim(preg_replace('/[^\p{L}\p{N}_-]+/u', '', str_replace(' ', '-', mb_strtolower($t))), '-'); } // comme GitHub
  public static function enligne($t) {
    $t = preg_replace('/<!--.*?-->/', '', $t); // repère caché d'un renvoi « fig. B7 » (outils/figures_jdc.py)
    $t = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE);
    $c = []; // code mis de côté : rien n'est balisé dedans
    $t = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$c) { $c[] = '<code>' . $m[1] . '</code>'; return "\x01" . (count($c) - 1) . "\x01"; }, $t);
    $t = preg_replace_callback('/!\[([^\]]*)\]\((?:\.\.\/)?images\/([\w.-]+\.(?:png|jpe?g|webp|svg))\)/', function ($m) {
      // URL de l'action image : le .htaccess du cœur Jeedom refuse toute URL contenant /docs/ (403) ; v = date du fichier (cache du navigateur)
      // ../images/x : comme sur GitHub, le .md est dans docs/fr_FR/ et les images dans docs/images/
      $f = __DIR__ . '/../../docs/images/' . $m[2];
      if (!is_file($f)) return '[image manquante : images/' . $m[2] . ']';
      $img = function ($n, $f, $cl = '') use ($m) { return '<img src="plugins/journalcultures/core/ajax/journalcultures.ajax.php?action=image&amp;n=' . rawurlencode($n) . '&amp;v=' . filemtime($f) . '" alt="' . $m[1] . '"' . self::taille($f) . ' loading="lazy" style="max-width:100%"' . ($cl ? ' class="' . $cl . '"' : '') . '>'; };
      $so = preg_replace('/(\.\w+)$/', '-sombre$1', $m[2]); // capture en thème sombre à côté (x-sombre.webp) : montrée selon le thème Jeedom (CSS .jdc-cl / .jdc-so de la bibliothèque) ; GitHub et le PDF gardent la claire
      return is_file($fs = __DIR__ . '/../../docs/images/' . $so) ? $img($m[2], $f, 'jdc-cl') . $img($so, $fs, 'jdc-so') : $img($m[2], $f); }, $t);
    $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
      if (preg_match('/^https?:\/\//', $m[2])) return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
      if (preg_match('/^(?:(\w+)\.md)?(?:#([\p{L}\p{N}_-]+))?$/u', $m[2], $l)) return '<a data-n="' . ($l[1] ?? '') . '" data-a="' . ($l[2] ?? '') . '" href="#' . (($l[2] ?? '') === '' ? '' : 'jdc-n-' . $l[2]) . '">' . $m[1] . '</a>'; // href : lien au clavier (le clic est pris par la notice)
      return $m[1]; // autre cible : texte seul
    }, $t);
    $t = preg_replace(['/\*\*(.+?)\*\*/', '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/'], ['<b>$1</b>', '<i>$1</i>'], $t);
    return preg_replace_callback("/\x01(\d+)\x01/", function ($m) use ($c) { return $c[$m[1]]; }, $t);
  }
  public static function markdown($md) {
    $h = ''; $bloc = null; $acc = []; $som = []; // sommaire : titres ## et ###
    $fin = function () use (&$h, &$bloc, &$acc) {
      if ($bloc == 'p') $h .= '<p>' . implode(' ', array_map([self::class, 'enligne'], $acc)) . '</p>';
      if ($bloc == 'ul' || $bloc == 'ol') $h .= "<$bloc>" . implode('', array_map(function ($i) { return '<li>' . self::enligne($i) . '</li>'; }, $acc)) . "</$bloc>";
      if ($bloc == 't') {
        $l = array_values(array_filter($acc, function ($r) { return !preg_match('/^\|[\s:|-]+\|$/', trim($r)); })); // sans la ligne de séparation
        foreach ($l as $n => $r) {
          $cs = array_map('trim', explode('|', trim(trim($r), '|')));
          $h .= ($n ? '' : '<div class="jdc-tw"><table class="table table-condensed">') . '<tr>' . implode('', array_map(function ($x) use ($n) { return ($n ? '<td>' : '<th scope="col">') . self::enligne($x) . ($n ? '</td>' : '</th>'); }, $cs)) . '</tr>';
        }
        if ($l) $h .= '</table></div>'; // jdc-tw : défile seul sur mobile
      }
      $bloc = null; $acc = [];
    };
    foreach (preg_split('/\R/u', $md) as $ligne) {
      if (trim($ligne) === '') { $fin(); continue; }
      if (preg_match('/^(#{1,4})\s+(.+)$/', $ligne, $m)) {
        $fin(); $n = strlen($m[1]) + 1;
        $a = htmlspecialchars(self::ancre($m[2]), ENT_QUOTES);
        if ($n == 3 || $n == 4) $som[] = '<li' . ($n == 4 ? ' class="s"' : '') . '><a data-n="" data-a="' . $a . '" href="#jdc-n-' . $a . '">' . self::enligne(preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $m[2])) . '</a></li>';
        $h .= "<h$n id=\"jdc-n-$a\">" . self::enligne($m[2]) . "</h$n>";
        continue;
      }
      if ($ligne[0] == '|') { if ($bloc != 't') { $fin(); $bloc = 't'; } $acc[] = $ligne; continue; }
      if (preg_match('/^(?:[-*]|(\d+)\.)\s+(.*)$/', $ligne, $m) && !($bloc == 'p' && $m[1] !== '' && $m[1] !== '1')) { $l = $m[1] === '' ? 'ul' : 'ol'; if ($bloc != $l) { $fin(); $bloc = $l; } $acc[] = $m[2]; continue; } // ponytail: <ol> numérotée de 1, le numéro écrit est ignoré ; comme CommonMark, seul « 1. » coupe un paragraphe
      if (($bloc == 'ul' || $bloc == 'ol') && preg_match('/^\s+(.*)$/', $ligne, $m)) { $acc[count($acc) - 1] .= ' ' . $m[1]; continue; } // suite de l'élément
      if ($bloc != 'p') { $fin(); $bloc = 'p'; }
      $acc[] = trim($ligne);
    }
    $fin();
    if (substr_count(implode('', $som), '<li><a') < 2) return $h; // page courte : pas de sommaire
    $t = preg_match('/^<h2 [^>]*>.*?<\/h2>/s', $h, $m) ? $m[0] : ''; // titre de la page (#) au-dessus
    return $t . '<div class="jdc-doc"><nav class="jdc-toc" aria-label="Sommaire"><details open><summary>Sommaire</summary><ul>' . implode('', $som) . '</ul></details></nav><div class="jdc-tx">' . substr($h, strlen($t)) . '</div></div>';
  }
}

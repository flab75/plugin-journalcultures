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
    if ($t !== null && $t <= $seuil) return ['alerte' => 1, 'raison' => sprintf(__('%s °C mesurés (rusticité %s °C)', __FILE__), round($t, 1), $reff)];
    if ($tmin !== null && $tmin <= $seuil) return ['alerte' => 1, 'raison' => sprintf(__('%s °C prévus (rusticité %s °C)', __FILE__), round($tmin, 1), $reff)];
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

  // calendrier d'engrais « semaines | produit | dose » par ligne → produits prévus la semaine $sem : ['Produit dose', …]
  public static function engrais($texte, $sem) {
    $r = [];
    foreach (preg_split('/\R/', (string)$texte) as $l) {
      $p = array_map('trim', explode('|', $l));
      if (count($p) >= 2 && $p[1] !== '' && in_array((int)$sem, self::semaines($p[0]))) $r[] = trim($p[1] . ' ' . ($p[2] ?? ''));
    }
    return $r;
  }

  // emplacements « Nom | temp | humidité | tmin prévue » par ligne → [nom => [temp, hum, tmin]] (chaînes de commandes, '' si absente)
  public static function emplacements($texte) {
    $r = [];
    foreach (preg_split('/\R/', (string)$texte) as $l) {
      $p = array_map('trim', explode('|', $l));
      if ($p[0] !== '') $r[mb_substr($p[0], 0, 60)] = [$p[1] ?? '', $p[2] ?? '', $p[3] ?? '']; // nom historisé (varchar 127) et colonne lieu (60)
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
    'aile' => ['Aile du pétiole', ['aucune' => 'aucune ou un simple liseré', 'etroite' => 'étroite', 'large' => 'large (moins de la moitié du limbe)', 'tres_large' => 'très large (moitié du limbe ou plus)']],
    'aile_largeur' => ['Largeur de l’aile (comparée au limbe)', ['moins' => 'moins de la moitié du limbe', 'moitie' => 'de la moitié aux trois quarts', 'presque' => 'presque aussi large que le limbe, ou plus']],
    'feuille' => ['Longueur de la feuille', ['petite' => 'moins de 5 cm', 'moyenne' => '5 à 10 cm', 'grande' => 'plus de 10 cm']],
    'boutons' => ['Boutons floraux', ['blancs' => 'blancs', 'violets' => 'teintés de violet']],
    'fruit' => ['Taille du fruit', ['lt3' => 'moins de 3 cm', '3-6' => '3 à 6 cm', '6-10' => '6 à 10 cm', 'gt10' => 'plus de 10 cm']],
    'forme' => ['Forme du fruit', ['ronde' => 'ronde', 'aplatie' => 'aplatie', 'ovale' => 'ovale ou à mamelon', 'doigt' => 'allongée en doigt', 'main' => 'divisée en doigts']],
    'couleur' => ['Couleur à maturité', ['jaune' => 'jaune', 'orange' => 'orange', 'verte' => 'verte', 'autre' => 'rose, rouge ou brune']],
    'peau' => ['Surface de la peau', ['lisse' => 'lisse', 'rugueuse' => 'rugueuse ou bosselée', 'duvet' => 'duveteuse']],
    'gout_peau' => ['Goût de la peau', ['amere' => 'amère ou neutre', 'sucree' => 'sucrée, se mange']],
    'pelage' => ['Épluchage', ['facile' => 'peau qui se détache seule', 'adherente' => 'peau adhérente']],
    'rameaux' => ['Jeunes rameaux', ['poilus' => 'poilus (duvet fin, à la loupe)', 'glabres' => 'glabres (lisses)']],
    'quartiers' => ['Quartiers du fruit (coupé en travers)', ['peu' => '6 ou moins', 'nombreux' => '7 ou plus']],
    'pulpe' => ['Pulpe', ['douce' => 'douce', 'acide' => 'acide', 'amere' => 'amère', 'perles' => 'en perles (caviar)', 'peu' => 'presque absente (peau très épaisse)']],
  ];
  /* Profils : groupe => [caractère => codes compatibles] ; un caractère absent du profil ne compte pas.
     Recoupés avec la clé du genre de la Flora of China, vol. 11 (aile > moitié du limbe = papedas, « très large » ; cédrat sans aile, peau plus épaisse que la pulpe ;
     citron : pétales teintés de rose, mamelon ; lime de Tahiti : fleurs blanches (UCR) ou un peu violacées (Morton), les deux acceptées ; bergamote = bigaradier jaune, ronde ou en poire ; citron Meyer souvent rond ; yuzu et mandarine : peau qui s'enlève ; pomelo : fruit ≥ 10 cm ;
     quartiers : kumquat 3-6 loges contre 7-15 pour les autres Citrus (Flora of China), kumquat 4-5, calamondin 8-9 (中国植物志 p. 175) ;
     pas imposé aux papedas (combava 6-7), au caviar, au cédrat ni au Poncirus) ;
     largeur de l'aile : combava presque aussi large que le limbe (Flora of China : limbe 0,5-1 cm plus large ; Sofiyanti 2022), ichang plus large que le limbe (FoC),
     pomelo nettement plus étroite (FoC : aile 0,5-3 cm, limbe 4-8 cm ; Sofiyanti 2022) ; non imposée aux autres groupes (aucune source) ;
     jeunes rameaux : glabres chez l'orange et le bigaradier (C. ×aurantium, clé FoC 8b ; Swingle & Reece : les rameaux poilus séparent le pomelo
     des autres espèces) ; poilus chez le pomelo (FoC 8a + Swingle & Reece « often pubescent ») et le yuzu (FoC « sparsely pubescent when young ») :
     seulement un indice pour eux (INDICES), un pomelo glabre reste possible.
     ponytail: profils de groupes, pas de variétés : les hybrides restent souvent indécis (la Flora of China le dit aussi) ;
     protocoles UPOV complets non relus, affiner un profil quand une plante connue le contredit. */
  const GROUPES = [
    'Poncirus' => ['feuillage' => ['caduc'], 'folioles' => ['trois'], 'peau' => ['duvet'], 'pulpe' => ['amere', 'acide'], 'boutons' => ['blancs']],
    'Hybride de Poncirus (citrange, citrumelo, FA5…)' => ['feuillage' => ['persistant', 'caduc'], 'folioles' => ['trois', 'mix'], 'peau' => ['lisse', 'duvet'], 'pulpe' => ['acide', 'amere'], 'boutons' => ['blancs']],
    'Kumquat' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'gout_peau' => ['sucree'], 'fruit' => ['lt3', '3-6'], 'forme' => ['ronde', 'ovale'],
      'feuille' => ['petite', 'moyenne'], 'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'couleur' => ['orange', 'jaune'], 'pulpe' => ['acide', 'douce'], 'pelage' => ['adherente'], 'quartiers' => ['peu']],
    'Calamondin' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'fruit' => ['lt3', '3-6'], 'forme' => ['ronde', 'aplatie'], 'couleur' => ['orange'],
      'pulpe' => ['acide'], 'pelage' => ['facile'], 'peau' => ['lisse'], 'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'feuille' => ['petite', 'moyenne'], 'quartiers' => ['nombreux']],
    'Citron caviar (finger lime)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'forme' => ['doigt'], 'feuille' => ['petite'], 'pulpe' => ['perles'], 'aile' => ['aucune'], 'fruit' => ['3-6', '6-10']],
    'Yuzu' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere'], 'couleur' => ['jaune'],
      'fruit' => ['3-6', '6-10'], 'forme' => ['ronde', 'aplatie'], 'boutons' => ['blancs'], 'gout_peau' => ['amere'], 'pelage' => ['facile'], 'quartiers' => ['nombreux']],
    'Papeda (combava, ichang)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['tres_large'], 'aile_largeur' => ['moitie', 'presque'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere', 'peu'],
      'boutons' => ['blancs'], 'gout_peau' => ['amere']],
    'Cédrat (dont Main de Bouddha)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune'], 'fruit' => ['6-10', 'gt10'],
      'forme' => ['ovale', 'main'], 'couleur' => ['jaune'], 'pulpe' => ['acide', 'peu'], 'feuille' => ['moyenne', 'grande'], 'pelage' => ['adherente']],
    'Citron' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune', 'etroite'], 'forme' => ['ovale', 'ronde'], 'couleur' => ['jaune', 'verte'],
      'pulpe' => ['acide'], 'pelage' => ['adherente'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse', 'rugueuse'], 'quartiers' => ['nombreux']],
    'Lime' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'couleur' => ['verte', 'jaune'], 'forme' => ['ronde', 'ovale'], 'boutons' => ['blancs', 'violets'], 'pulpe' => ['acide'], 'fruit' => ['3-6', '6-10'],
      'aile' => ['etroite', 'aucune'], 'peau' => ['lisse'], 'pelage' => ['adherente'], 'quartiers' => ['nombreux']],
    'Mandarine' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pelage' => ['facile'], 'forme' => ['aplatie', 'ronde'], 'couleur' => ['orange'], 'pulpe' => ['douce', 'acide'],
      'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse'], 'quartiers' => ['nombreux']],
    'Orange douce' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'rameaux' => ['glabres'], 'pelage' => ['adherente'], 'forme' => ['ronde', 'ovale'], 'couleur' => ['orange'], 'pulpe' => ['douce'],
      'aile' => ['etroite', 'aucune'], 'boutons' => ['blancs'], 'fruit' => ['6-10', 'gt10'], 'quartiers' => ['nombreux']],
    'Bigaradier (orange amère)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'rameaux' => ['glabres'], 'aile' => ['etroite', 'large'], 'pelage' => ['adherente'], 'pulpe' => ['amere', 'acide'], 'couleur' => ['orange', 'jaune'],
      'forme' => ['ronde', 'aplatie', 'ovale'], 'boutons' => ['blancs'], 'fruit' => ['6-10'], 'gout_peau' => ['amere'], 'quartiers' => ['nombreux']],
    'Pomelo, pamplemousse' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'aile_largeur' => ['moins', 'moitie'], 'fruit' => ['6-10', 'gt10'], 'couleur' => ['jaune', 'verte'], 'feuille' => ['moyenne', 'grande'],
      'boutons' => ['blancs'], 'pelage' => ['adherente'], 'quartiers' => ['nombreux']],
  ];

  // indices : un accord si observé, jamais une contradiction (caractère fréquent mais pas constant dans le groupe)
  const INDICES = ['Pomelo, pamplemousse' => ['rameaux' => ['poilus']], 'Yuzu' => ['rameaux' => ['poilus']]];

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
     bergamote et orange amère avant citron et orange). Synonymes : Flora of China 11 et 中国植物志 (C. japonica = Fortunella, C. ×microcarpa
     = madurensis = mitis, C. trifoliata = Poncirus). ponytail: tangor, tangelo, Shiranui rangés en mandarine ; limequat en lime (le moins rustique des deux parents). */
  const BOTA = [
    '/\s[×x]\s*poncirus|poncirus\s.*\s[×x]\s|citrange|citrumelo|citrandarin/iu' => 'Hybride de Poncirus (citrange, citrumelo, FA5…)',
    '/poncirus|trifoliata/iu' => 'Poncirus',
    '/floridana|limequat/iu' => 'Lime',
    '/microcarpa|madurensis|mitis|calamondin/iu' => 'Calamondin',
    '/fortunella|japonica|margarita|obovata|crassifolia|hindsii|kumquat/iu' => 'Kumquat',
    '/australasica|microcitrus|caviar|finger/iu' => 'Citron caviar (finger lime)',
    '/junos|yuzu/iu' => 'Yuzu',
    '/hystrix|ichangensis|cavaleriei|papeda|combava/iu' => 'Papeda (combava, ichang)',
    '/medica|c[ée]drat|sarcodactyl/iu' => 'Cédrat (dont Main de Bouddha)',
    '/bergamia|aurantium|myrtifolia|bigarad|am[èe]re/iu' => 'Bigaradier (orange amère)',
    '/limon|meyeri|lemon|citronnier/iu' => 'Citron',
    '/aurantiifolia|latifolia|limettioides|\blime\b/iu' => 'Lime',
    '/reticulata|unshiu|deliciosa|clementina|tangerina|nobilis|tangor|tangelo|mandarin|satsuma|shiranui/iu' => 'Mandarine',
    '/sinensis|orange/iu' => 'Orange douce',
    '/maxima|grandis|paradisi|pomelo|pamplemousse|grapefruit/iu' => 'Pomelo, pamplemousse',
  ];
  /* Rusticité d'un groupe (°C, pleine terre) quand la fiche n'en donne pas : la moins froide des sources (prudence), à remplacer par une valeur de la fiche.
     Kumquat : TyCitriK −10, UF −12, Fortune 1870 (via Swingle) « 10-15 °F de gel » ≈ −8 ; citron : TyCitriK −5, Blondel −5/−6 ; cédrat : TyCitriK −3, Guo −4/−5 (feuilles) ; calamondin, lime, caviar, papeda : TyCitriK seul ;
     yuzu : RHS H3 −5 contre TyCitriK −14 (le plus prudent gardé) ; Poncirus : TyCitriK −16, fiches −15 ; hybrides : Carrizo −11, citrumelo −12, FA5 −13 → −10 comme les fiches FA5 ;
     bigaradier, mandarine, orange, pomelo : aucune valeur chiffrée sur la plante, encadrés par l'ordre UF (Jackson & Fasulo HS-123 : calamondin > bigaradier > mandarinier
     > oranger > pomelo > citronnier) entre calamondin −5 et citron −5 → −5. */
  const RUSTICITE_GROUPES = [
    'Poncirus' => -15, 'Hybride de Poncirus (citrange, citrumelo, FA5…)' => -10, 'Kumquat' => -8, 'Calamondin' => -5, 'Citron caviar (finger lime)' => -4,
    'Yuzu' => -5, 'Papeda (combava, ichang)' => -4, 'Cédrat (dont Main de Bouddha)' => -3, 'Citron' => -5, 'Lime' => -4, 'Mandarine' => -5, 'Orange douce' => -5,
    'Bigaradier (orange amère)' => -5, 'Pomelo, pamplemousse' => -5,
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
  // type de greffe (multiplication « greffe ») : greffes d'un œil (pépinière), de rameau (changer de variété, réparer), de laboratoire
  const GREFFES = ['ecusson_t_inverse' => 'écusson en T inversé', 'ecusson_t' => 'écusson en T', 'chip' => 'chip budding (œil en copeau)',
    'chip_inverse' => 'chip inversé', 'placage' => 'placage d’œil', 'fente' => 'fente', 'sous_ecorce' => 'sous écorce (couronne)',
    'anglaise' => 'anglaise', 'pont' => 'pont', 'incrustation' => 'incrustation', 'approche' => 'en approche', 'laterale' => 'latérale',
    'souche' => 'sur souche', 'microgreffe' => 'microgreffe d’apex in vitro', 'minigreffe' => 'minigreffe en fente'];
  public static function greffe(array $c) { // type de greffe gardé seulement s'il est connu, et il implique la multiplication « greffe »
    if (!array_key_exists($c['type_greffe'] ?? '', self::GREFFES)) { unset($c['type_greffe']); return $c; }
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
    if (in_array($in['multiplication'] ?? '', self::MULTIPLICATIONS, true)) $c['multiplication'] = $in['multiplication'];
    $c = self::greffe($c + (isset($in['type_greffe']) ? ['type_greffe' => (string)$in['type_greffe']] : []));
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
      if ($v === '' || $v === $a) continue;
      $c[$k] = $v; $s = self::texte($src[$k] ?? '', 60);
      $l[] = $lib . ' : ' . ($a === '' ? '–' : ($opt[$a] ?? $a)) . ' → ' . ($opt[$v] ?? $v) . ($s !== '' ? ' (' . $s . ')' : '');
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

  /* Suivi d'une greffe récente : contrôle du bourgeon à J+21 (œil ou bourgeons verts et gonflés, lien retiré), puis à J+42 (débourrement,
     sinon forcer en rabattant le porte-greffe) ; un statut n'avance que sur confirmation. Repères : UF/IFAS (lien retiré à 2-3 semaines),
     Alabama ACES (œil vert = pris ; vert sans pousse : rabattre), UC Ventura (soudure en 3-4 semaines au printemps, 6-8 sinon), minigreffe Valencia (bilan à 6 semaines).
     Œil gardé dormant jusqu'au printemps (greffe d'automne), puis porte-greffe rabattu pour le forcer : Arizona (az1146), Kentucky (greffage). */
  const GREFFE_STATUTS = ['attente' => 'en attente', 'probable' => 'probablement prise', 'dormant' => 'œil dormant jusqu’au printemps', 'reprise' => 'reprise', 'echouee' => 'échouée'];
  const GREFFE_JOURS = ['attente' => 21, 'probable' => 42];
  public static function greffeQuestion($statut, $type) {
    $oeil = in_array($type, ['ecusson_t_inverse', 'ecusson_t', 'chip', 'chip_inverse', 'placage'], true); // greffe d'un œil, sinon de rameau (ou type inconnu)
    if ($statut == 'attente') return $oeil ? 'œil vert et gonflé, sans se ratatiner ? (retirer le lien)' : 'bourgeons du greffon verts et gonflés ?';
    if ($statut == 'probable') return $oeil ? 'l’œil a-t-il débourré ? (sinon le forcer en rabattant le porte-greffe au-dessus, ou le laisser dormir jusqu’au printemps)' : 'la pousse du greffon continue-t-elle ?';
    if ($statut == 'dormant') return $oeil ? 'printemps : rabattre le porte-greffe au-dessus de l’œil ; a-t-il débourré ?' : 'printemps : les bourgeons du greffon ont-ils démarré ?';
    return '';
  }
  /* Température pour souder (moyenne 24 h de l'emplacement) : sous ~21 °C le Poncirus s'endort (Alabama ACES : dormance quand le jour reste < 70 °F),
     cal le plus rapide vers 21-29 °C, prise moindre au-dessus de ~32 °C (sources d'amateurs : growingfruit.org, forum) → seuils réglables */
  public static function greffeTemp($moy, $min = 21, $max = 32) {
    if ($moy === null) return '';
    if ($moy < $min) return sprintf('trop froid pour souder : %s °C en moyenne sur 24 h (< %s °C)', floor($moy * 10) / 10, $min); // arrondi vers l'extérieur : jamais « 21 °C (< 21 °C) »
    if ($moy > $max) return sprintf('trop chaud pour souder : %s °C en moyenne sur 24 h (> %s °C)', ceil($moy * 10) / 10, $max);
    return '';
  }
  /* Œil dormant : soudure faite, plus besoin de 21 °C ; reste à le protéger du gel (UC ANR : greffe d'automne à protéger du gel).
     ponytail: 0 °C fixe sur le minimum 24 h, à rendre réglable si la sonde est loin de la plante */
  public static function greffeGel($min) {
    return $min !== null && $min < 0 ? sprintf('gel : protéger l’œil, %s °C au plus bas sur 24 h', floor($min * 10) / 10) : '';
  }
  // verdict d'un contrôle (ok, douteux = revoir dans 7 jours, printemps = œil laissé dormant, mort) → [statut, prochain contrôle 'Y-m-d' ou '']
  public static function controler($statut, $verdict, $greffe, $jour) {
    if (!in_array($statut, ['attente', 'probable', 'dormant'], true)) throw new Exception(__('Pas de contrôle de greffe en cours', __FILE__));
    $j = function ($d, $n) { return date('Y-m-d', strtotime($d . ' +' . $n . ' days')); };
    if ($verdict == 'mort') return ['echouee', ''];
    if ($verdict == 'douteux') return [$statut, $j($jour, 7)];
    if ($verdict == 'printemps' && $statut != 'dormant') { // ponytail: 15 mars fixe ; à rendre réglable si le climat de la pièce l'exige
      $p = substr($jour, 0, 4) . '-03-15'; return ['dormant', $p > $jour ? $p : ((int)substr($jour, 0, 4) + 1) . '-03-15'];
    }
    if ($verdict != 'ok') throw new Exception(__('Verdict inconnu', __FILE__));
    return $statut != 'attente' ? ['reprise', ''] : ['probable', max($j($greffe, self::GREFFE_JOURS['probable']), $jour)]; // J+42 déjà passé (réponse tardive) : la question suivante tout de suite
  }

  public static function age($d, $now = null) {
    if (!preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/D', trim((string)$d), $m)) return null;
    $now = $now ?? time(); $a = (int)$m[1]; $mo = ($m[2] ?? '') !== '' ? (int)$m[2] : null;
    if ($a < 1900 || ($mo !== null && !checkdate($mo, (int)($m[3] ?? 1), $a))) return null;
    $ans = function ($n) { return $n . ' an' . ($n > 1 ? 's' : ''); };
    if ($mo === null) { $n = (int)date('Y', $now) - $a; return $n < 0 ? null : ($n == 0 ? 'moins d’un an' : '≈ ' . $ans($n)); } // année seule : à un an près
    $n = ((int)date('Y', $now) - $a) * 12 + (int)date('n', $now) - $mo;
    return $n < 0 ? null : ($n == 0 ? 'moins d’un mois' : ($n < 12 ? $n . ' mois' : $ans(intdiv($n, 12))));
  }

  /* Catalogue fixe des mesures : organe => [titre, [clé => [type, libellé, unité ou choix]]] ;
     type n = nombre, l = liste de nombres (plusieurs fruits), c = choix. Les valeurs vont au journal (colonne valeurs), clé « organe.clé ». */
  const MESURES = [
    'plante' => ['Plante', ['hauteur' => ['n', 'Hauteur', 'cm'], 'envergure' => ['n', 'Envergure', 'cm'], 'charpentieres' => ['n', 'Charpentières', ''],
      'vigueur' => ['c', 'Vigueur (1 à 5)', ['1', '2', '3', '4', '5']], 'etat' => ['c', 'État général (1 à 5)', ['1', '2', '3', '4', '5']],
      'stade' => ['c', 'Stade', ['repos', 'débourrement', 'pousse', 'floraison', 'fructification']]]],
    'tronc' => ['Tronc / greffe', ['d_tronc' => ['n', 'Ø du tronc (plante non greffée)', 'mm'], 'd_pg' => ['n', 'Ø porte-greffe (5 cm sous la greffe)', 'mm'],
      'd_greffon' => ['n', 'Ø greffon (5 cm au-dessus)', 'mm'], 'h_greffe' => ['n', 'Hauteur du point de greffe', 'cm'], 'rejets' => ['n', 'Rejets du porte-greffe', ''],
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
      'brix' => ['n', 'Taux de sucre', '°Brix'], 'recolte' => ['n', 'Poids récolté', 'g'], 'couleur' => ['c', 'Couleur', ['vert', 'en virage', 'jaune', 'orange']]]],
    'pot' => ['Racines / pot', ['substrat' => ['c', 'Substrat', ['sec', 'frais', 'humide', 'détrempé']], 'racines' => ['c', 'Racines au drainage', ['non', 'oui']],
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
     % N et intervalle > 0 : produit possible du plan d'azote ; apporte = éléments (N P K Mg Fe S Zn Mn) pour les carences. Proposé quand le catalogue est vide. */
  const PRODUITS = "Ferti Agrumes 7-3-10 | 7 | 8 | 10-40 | N P K | granulés à enfouir par léger griffage ; étiquette : 80 à 240 g par sujet tous les 2 mois, de mars à octobre\n"
    . "Corne broyée | 13 | 10 | 10-40 | N | effet sur 3 à 4 mois ; étiquette : en pot, 3 apports de 1 g par litre de terreau dans la saison\n"
    . "Sang séché | 12 | 0 | 10-30 | N | coup de fouet (effet en 3 à 4 jours) ; étiquette : 1 à 2 apports par an, printemps et été\n"
    . "Kélafer racinaire (fer EDDHA 6 %) | 0 | 0 | 6-44 | Fe | au pied seulement, jamais sur les feuilles ; dilué dans l'arrosage (10 à 40 g par litre d'eau) ; étiquette : jeunes plantations 5 à 15 g par pied, seulement en cas de besoin\n"
    . "Sel d'Epsom (sulfate de magnésium) | 0 | 0 | 10-40 | Mg S | pas de dose sur le seau (produit pour le bain)";

  public static function produits($texte) { // → [['nom', 'n' (%), 'tous' (semaines), 'saison', 'apporte' => [éléments], 'note'], …]
    $r = [];
    foreach (preg_split('/\R/', (string)$texte) as $l) {
      $p = array_map('trim', explode('|', $l));
      if ($p[0] === '') continue;
      $r[] = ['nom' => mb_substr($p[0], 0, 60), 'n' => max(0, min(100, (float)self::nombre($p[1] ?? ''))), 'tous' => max(0, min(52, (int)self::nombre($p[2] ?? ''))),
        'saison' => preg_match('/^\d{1,2}-\d{1,2}$/D', $p[3] ?? '') ? $p[3] : '10-40',
        'apporte' => array_values(array_intersect(preg_split('/[\s,]+/', strtoupper($p[4] ?? ''), -1, PREG_SPLIT_NO_EMPTY), ['N', 'P', 'K', 'MG', 'FE', 'S', 'ZN', 'MN'])),
        'note' => mb_substr($p[5] ?? '', 0, 300)];
    }
    return $r;
  }

  /* Plan d'azote d'une plante en pot : besoin = $taux g d'N par litre de pot et par semaine (0,07 = 0,14 g toutes les 2 semaines, ressources §13),
     réparti entre les produits du plan selon $parts (index du catalogue => %, total 100). → [['i', 'ligne' (« 10-40/8 | Nom | 40 g »), 'g'], …]
     ponytail: dose proportionnelle au volume seul (aucune source ne la fait varier avec la taille de la plante en pot) ; régler $taux à l'usage. */
  public static function plan($litres, array $produits, array $parts, $taux = 0.07, array $doses = []) { // doses : grammes ajustés à la main, prioritaires sur le calcul
    $r = [];
    if (!$litres) return $r;
    foreach ($produits as $i => $p) {
      $part = (float)($parts[$i] ?? 0);
      if ($p['n'] <= 0 || $p['tous'] <= 0 || $part <= 0) continue;
      $g = ($doses[$i] ?? 0) > 0 ? round($doses[$i], 1) : max(1, round($litres * $taux * $p['tous'] * min($part, 100) / $p['n'])); // part en % et N en % : les 100 s'annulent
      $r[] = ['i' => $i, 'g' => $g, 'ligne' => $p['saison'] . '/' . $p['tous'] . ' | ' . $p['nom'] . ' | ' . str_replace('.', ',', (string)$g) . ' g'];
    }
    return $r;
  }

  /* Carence probable d'après la dernière mesure des feuilles (ressources §14 : UF/IFAS, Haifa, Elavarasan & Premalatha 2019).
     → [['el' => élément, 'txt' => raison], …] ; substrat détrempé : averti d'abord (l'asphyxie bloque le fer). Jamais un diagnostic : des pistes. */
  public static function carences(array $v) {
    $c = $v['feuilles.couleur'] ?? ''; $o = $v['feuilles.touchees'] ?? ''; $r = [];
    if (($v['pot.substrat'] ?? '') == 'détrempé') $r[] = ['el' => 'EAU', 'txt' => 'substrat détrempé : racines asphyxiées, le fer n’est plus absorbé ; arroser moins avant tout apport'];
    if (in_array($c, ['jaune uniforme', 'vert clair'], true)) $r[] = ['el' => 'N', 'txt' => 'feuilles pâles ou jaunes partout' . ($o == 'jeunes' ? '' : ', vieilles feuilles d’abord') . ' : azote (le soufre donne le même aspect)'];
    if ($c == 'V vert à la base, reste jaune' || ($c == 'jaunissement entre les nervures' && $o == 'vieilles')) $r[] = ['el' => 'MG', 'txt' => 'vieilles feuilles jaunes entre les nervures, V vert à la base : magnésium'];
    if ($c == 'jaunissement entre les nervures' && $o == 'jeunes') $r[] = ['el' => 'FE', 'txt' => 'jeunes feuilles pâles à nervures vertes : fer (pH élevé, calcaire, excès d’eau ou de cuivre)'];
    if ($c == 'jaunissement entre les nervures' && !in_array($o, ['jeunes', 'vieilles'], true)) $r[] = ['el' => '?', 'txt' => 'jaunissement entre les nervures : fer si ce sont les jeunes feuilles, magnésium si ce sont les vieilles (préciser « Feuilles touchées »)'];
    if ($c == 'bronzé') $r[] = ['el' => 'K', 'txt' => 'vieilles feuilles bronzées' . (($v['feuilles.forme'] ?? '') == 'enroulées' ? ' et enroulées' : '') . ' : potassium (ou magnésium)'];
    if (($v['feuilles.forme'] ?? '') == 'petites et étroites') $r[] = ['el' => 'ZN', 'txt' => 'feuilles petites, étroites et pointues, en rosette : zinc (souvent avec fer et manganèse quand le pH est élevé)'];
    return $r;
  }

  /* ---------- santé : problèmes surveillés et pistes ---------- */
  /* Agrumes en pot sous serre (Île-de-France), du plus important au moins important. Références complètes : ressources.md §14.
     Chaque geste porte ses sources ; un geste ou un problème à une seule source est signalé (en chercher une autre). statut : present, quarantaine, absent. */
  const SANTE = [
    'gommose' => ['nom' => 'Gommose et pourriture des racines (Phytophthora)', 'statut' => 'present',
      'signes' => 'gomme, écorce brune qui se fend au collet ou au point de greffe ; feuilles vert pâle à nervures jaunes (atteinte sévère), qui tombent ; racines fines molles dont l’écorce part (il reste un fil blanc) ; favorisée par un substrat qui reste mouillé',
      'sources' => ['UC IPM (gommose)', 'Vogel 1990', 'BSV PACA 2020', 'NHB', 'Sáenz Pérez 2019', 'Savita 2012', 'Whiteside 1971', 'Oliveira 1981', 'UF/IFAS HS261', 'TNAU'],
      'gestes' => [['arroser moins, laisser ressuyer, pot qui draine ; ne pas mouiller le tronc', ['UC IPM (gommose)', 'BSV PACA 2020', 'NHB']],
        ['collet et point de greffe jamais enterrés : de la terre sur le bas du tronc favorise la maladie', ['UC IPM (gommose)', 'Sáenz Pérez 2019', 'Savita 2012', 'Whiteside 1971']],
        ['ne pas blesser le bas du tronc ni les racines (pas de travail du sol au pied) : les plaies sont la porte d’entrée', ['Savita 2012', 'Whiteside 1971', 'Oliveira 1981']],
        ['une plaie fraîche est envahie le plus facilement, rarement après une dizaine de jours ; l’écorce intacte n’a pas été pénétrée dans l’essai (les 14 jours parfois cités viennent de l’avocatier)', ['Whiteside 1971']],
        ['enlever l’écorce brune et une bande d’écorce saine autour, laisser sécher, revoir pendant quelques mois (seulement si la lésion fait moins de la moitié du tour du tronc)', ['UC IPM (gommose)', 'NHB', 'TNAU']],
        ['badigeon de cuivre (bouillie bordelaise) sur la plaie et le bas du tronc', ['UC IPM (gommose)', 'NHB']],
        ['en dernier : fongicide (phosphonate, fosétyl-Al chez les professionnels) ; un particulier n’a droit qu’aux produits marqués « emploi autorisé dans les jardins » (loi Labbé), selon l’étiquette', ['UC IPM (gommose)', 'Vogel 1990', 'NHB', 'Sáenz Pérez 2019', 'Savita 2012']],
        ['plus de la moitié du tour du tronc atteinte : le déclin se voit dans le feuillage, le grattage ne suffit plus ; remplacer la plante (conseil économique, pas un résultat d’essai)', ['UC IPM (gommose)', 'UF/IFAS HS261', 'TNAU']]]],
    'cochenille' => ['nom' => 'Cochenilles', 'statut' => 'present',
      'signes' => 'boucliers bruns ou noirs, amas cotonneux blancs sur rameaux et nervures ; miellat, fumagine, fourmis',
      'sources' => ['Brun 1990', 'BSV Corse 2024', 'BSV PACA 2020', 'BSV JEVI Corse 2025', 'COCHORTI 2021'],
      'gestes' => [['enlever à la main celles qu’on atteint, couper les rameaux très touchés', ['BSV JEVI Corse 2025']],
        ['pulvériser savon noir + huile végétale + alcool (5 cl de chaque par litre d’eau) jusqu’à ruissellement', ['BSV JEVI Corse 2025']],
        ['auxiliaires : Cryptolaemus montrouzieri, Anagyrus vladimiri (farineuses), Rodolia cardinalis (australienne)', ['BSV Corse 2024', 'BSV PACA 2020', 'Brun 1990', 'COCHORTI 2021']],
        ['écarter les fourmis : elles protègent les cochenilles', ['BSV PACA 2020', 'BSV Corse 2024']],
        ['huile (blanche ou de colza), effet asphyxiant, en couvrant bien tout le feuillage', ['Brun 1990', 'BSV JEVI Corse 2025']]],
      'note' => 'huile, savon, alcool : essayer d’abord sur quelques feuilles ; jamais en plein soleil, au-dessus d’environ 28 °C ni sur une plante assoiffée ; pas en même temps que cuivre ou soufre (brûlures ; précaution du plugin, pas des sources). Le mélange savon + huile + alcool : aucune étude publiée trouvée (ni efficacité ni brûlures sur agrumes), conseil de vulgarisation ; le savon nuit aussi aux larves de coccinelles et de chrysopes : pas de savon après un lâcher de Cryptolaemus'],
    'acarien' => ['nom' => 'Acariens (araignées rouges, tétranyques)', 'statut' => 'present',
      'signes' => 'feuilles piquetées, jaunies, blanchies ou bronzées, petites toiles, surtout les jeunes feuilles ; difficiles à voir à l’œil nu',
      'sources' => ['BSV PACA 2020', 'Brun 1990', 'Réseau d’alerte d’Andalousie (via la presse)', 'NC State (P. citri)', 'Kasap 2009', 'Chouikhi 2022'],
      'gestes' => [['regarder les deux faces des jeunes feuilles à la loupe', ['BSV PACA 2020']],
        ['pas d’excès d’engrais', ['BSV PACA 2020']],
        ['préserver les auxiliaires : acariens prédateurs (Neoseiulus californicus, Phytoseiulus persimilis, Euseius stipulatus), coccinelles Stethorus', ['Brun 1990', 'Chouikhi 2022']],
        ['huile blanche, en couvrant bien', ['Brun 1990']]],
      'note' => 'huile : essayer d’abord sur quelques feuilles, jamais en plein soleil ni au-dessus d’environ 28 °C (précaution du plugin). Le climat dépend de l’espèce : tétranyque tisserand (Tetranychus urticae) vers 30 °C, supporte l’air sec ; acarien rouge des agrumes (Panonychus citri) vers 25 °C, humidité modérée, actif l’hiver et au printemps, freiné par le très chaud et sec (Andalousie, NC State, Chouikhi 2022) ; se développe de 15 à 35 °C (Kasap 2009). Sous serre, les deux sont possibles : des toiles nettes font penser à T. urticae (déduction du plugin)'],
    'aleurode' => ['nom' => 'Aleurodes (mouches blanches)', 'statut' => 'present',
      'signes' => 'petites mouches blanches qui s’envolent quand on secoue, larves plates sous les feuilles ; miellat, fumagine',
      'sources' => ['Brun 1990', 'BSV Corse 2024', 'Barbagallo 1992', 'Soto 2001', 'Liu et Stansly 2000'],
      'gestes' => [['auxiliaires spécifiques (Encarsia contre Dialeurodes citri, Cales noacki contre l’aleurode floconneux, parasitisme parfois de 100 %) : traiter rarement nécessaire', ['Brun 1990', 'Barbagallo 1992', 'Soto 2001']],
        ['huile blanche contre les larves, en couvrant le dessous des feuilles', ['Brun 1990']]],
      'note' => 'huile : essai sur d’autres aleurodes (Liu et Stansly 2000) ; plus efficace contre Dialeurodes citri que contre l’aleurode floconneux (étude turque de 1996, citée de seconde main). Essayer d’abord sur quelques feuilles, jamais en plein soleil ni au-dessus d’environ 28 °C (précaution du plugin)'],
    'puceron' => ['nom' => 'Pucerons', 'statut' => 'present',
      'signes' => 'colonies sur les jeunes pousses tendres, feuilles enroulées ; miellat',
      'sources' => ['Brun 1990', 'BSV JEVI Corse 2025', 'BSV Corse 2024', 'Gómez-Marco 2016', 'J. Econ. Entomol. 2019'],
      'gestes' => [['surveiller les pousses tendres : les feuilles durcies ne sont plus attaquées', ['Brun 1990']],
        ['favoriser les auxiliaires (coccinelles, surtout les petites Scymnus, chrysopes, syrphes) : ils doivent être là dès les premières colonies', ['BSV Corse 2024', 'Gómez-Marco 2016', 'J. Econ. Entomol. 2019']]]],
    'fumagine' => ['nom' => 'Fumagine', 'statut' => 'present',
      'signes' => 'dépôt noir comme de la suie, qui s’essuie, sur feuilles et fruits ; vient du miellat d’un insecte',
      'sources' => ['NHB', 'BSV JEVI Corse 2025', 'BSV PACA 2020', 'Brun 1990'],
      'gestes' => [['trouver l’insecte qui fait le miellat (cochenille, aleurode, puceron) et le traiter : la fumagine part ensuite d’elle-même', ['NHB', 'BSV PACA 2020', 'Brun 1990']],
        ['laver les feuilles à l’eau savonneuse', ['BSV JEVI Corse 2025']]]],
    'mineuse' => ['nom' => 'Mineuse des agrumes', 'statut' => 'present',
      'signes' => 'galeries sinueuses argentées dans les jeunes feuilles, qui se crispent et s’enroulent ; surtout les pousses d’été',
      'sources' => ['BSV PACA 2020', 'BSV Corse 2024', 'BSV JEVI Corse 2025'],
      'gestes' => [['dégâts surtout esthétiques ; jeunes plantes plus menacées', ['BSV PACA 2020', 'BSV Corse 2024']],
        ['couper les jeunes pousses atteintes', ['BSV PACA 2020']],
        ['si besoin, produit de biocontrôle', ['BSV Corse 2024']]]],
    'mal_secco' => ['nom' => 'Mal secco (Plenodomus tracheiphilus, citronniers et limes)', 'statut' => 'present',
      'signes' => 'feuilles qui tombent depuis la pointe des rameaux, rameaux qui sèchent, bois coloré saumon à rouge carotte sous l’écorce ; infection l’hiver dès 5 °C',
      'sources' => ['Chapot 1963', 'Migheli 2009', 'Nigro 2011', 'OEPP 2024 (mal secco)', 'Leonardi 2023', 'Plants 2022', 'Cirad 1983'],
      'gestes' => [['couper jusqu’au bois sain (sans coloration) : le champignon gagne la plante par le bois ; déchets en sac fermé à la déchetterie, jamais au compost (Chapot dit brûler : interdit aux particuliers aujourd’hui)', ['Chapot 1963', 'Leonardi 2023']],
        ['désinfecter les outils entre deux coupes (alcool à 70° ; Chapot : sulfate de cuivre) et protéger les plaies (cuivre : à peu près la seule lutte hors pratiques culturales) ; éviter toute blessure l’hiver, c’est par elles qu’il entre', ['Chapot 1963', 'Leonardi 2023', 'Cirad 1983', 'Plants 2022']]],
      'note' => 'sensibilité comparée des espèces : observations au champ, pas toujours confirmées par des tests (OEPP 2024)'],
    'aleurode_epineux' => ['nom' => 'Aleurode épineux (Aleurocanthus spiniferus)', 'statut' => 'quarantaine',
      'signes' => 'petites protubérances noires et épineuses sous les feuilles, miellat, fumagine, fourmis',
      'sources' => ['Fiche SORE (aleurode épineux)', 'EFSA 2018', 'OEPP 2020 (aleurode épineux)', 'Porcelli 2013'],
      'gestes' => [['ne pas déplacer ni donner la plante, photographier le dessous des feuilles', ['Fiche SORE (aleurode épineux)']],
        ['signaler à la FREDON ou au SRAL (organisme de quarantaine)', ['Fiche SORE (aleurode épineux)']]],
      'note' => 'présent dans l’UE en Italie (Pouilles depuis 2008, Campanie, Latium, Basilicate) et en Grèce, sous contrôle officiel (EFSA 2018, OEPP 2020) ; savons et mouillants ne le contrôlent qu’en partie (Porcelli 2013) ; chez l’aleurode floconneux, l’imidaclopride (interdit en France) perturbe les parasitoïdes et provoque une résurgence (Porcelli 2013)'],
    'hlb' => ['nom' => 'HLB (greening, dragon jaune)', 'statut' => 'absent',
      'signes' => 'marbrures jaunes asymétriques des feuilles, fruits déformés qui se colorent à l’envers, psylles sur les jeunes pousses',
      'sources' => ['PP328', 'BSV JEVI Corse 2025', 'Sáenz Pérez 2019'],
      'gestes' => [['d’abord le test de symétrie : jaunissement pareil des deux côtés de la nervure = carence (zinc, magnésium…), asymétrique = suspect', ['PP328', 'Sáenz Pérez 2019']],
        ['asymétrique avec psylles : signaler à la FREDON ; aucun traitement n’existe', ['BSV JEVI Corse 2025', 'Sáenz Pérez 2019']]],
      'note' => 'absent de France métropolitaine ; psylle vecteur présent en Espagne et au Portugal (BSV JEVI Corse 2025)'],
    'tristeza' => ['nom' => 'Tristeza (CTV)', 'statut' => 'absent',
      'signes' => 'déclin des arbres greffés sur bigaradier ; virus transmis par pucerons et greffons',
      'sources' => ['Vogel 1990', 'Sáenz Pérez 2019'],
      'gestes' => [['plants et greffons d’origine contrôlée', ['Vogel 1990', 'Sáenz Pérez 2019']],
        ['porte-greffe tolérant plutôt que bigaradier (FA5, Volkameriana, Cléopâtre…)', ['Sáenz Pérez 2019', 'Vogel 1990']]],
      'note' => 'signalée absente : Vogel 1990 la tient hors de Corse par l’interdiction d’importer ; statut actuel à vérifier'],
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
}

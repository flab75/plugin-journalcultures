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
    'feuille' => ['Longueur de la feuille', ['petite' => 'moins de 5 cm', 'moyenne' => '5 à 10 cm', 'grande' => 'plus de 10 cm']],
    'boutons' => ['Boutons floraux', ['blancs' => 'blancs', 'violets' => 'teintés de violet']],
    'fruit' => ['Taille du fruit', ['lt3' => 'moins de 3 cm', '3-6' => '3 à 6 cm', '6-10' => '6 à 10 cm', 'gt10' => 'plus de 10 cm']],
    'forme' => ['Forme du fruit', ['ronde' => 'ronde', 'aplatie' => 'aplatie', 'ovale' => 'ovale ou à mamelon', 'doigt' => 'allongée en doigt', 'main' => 'divisée en doigts']],
    'couleur' => ['Couleur à maturité', ['jaune' => 'jaune', 'orange' => 'orange', 'verte' => 'verte', 'autre' => 'rose, rouge ou brune']],
    'peau' => ['Surface de la peau', ['lisse' => 'lisse', 'rugueuse' => 'rugueuse ou bosselée', 'duvet' => 'duveteuse']],
    'gout_peau' => ['Goût de la peau', ['amere' => 'amère ou neutre', 'sucree' => 'sucrée, se mange']],
    'pelage' => ['Épluchage', ['facile' => 'peau qui se détache seule', 'adherente' => 'peau adhérente']],
    'quartiers' => ['Quartiers du fruit (coupé en travers)', ['peu' => '6 ou moins', 'nombreux' => '7 ou plus']],
    'pulpe' => ['Pulpe', ['douce' => 'douce', 'acide' => 'acide', 'amere' => 'amère', 'perles' => 'en perles (caviar)', 'peu' => 'presque absente (peau très épaisse)']],
  ];
  /* Profils : groupe => [caractère => codes compatibles] ; un caractère absent du profil ne compte pas.
     Recoupés avec la clé du genre de la Flora of China, vol. 11 (aile > moitié du limbe = papedas, « très large » ; cédrat sans aile, peau plus épaisse que la pulpe ;
     citron : pétales teintés de rose, mamelon ; lime de Tahiti : fleurs blanches (UCR) ou un peu violacées (Morton), les deux acceptées ; bergamote = bigaradier jaune, ronde ou en poire ; citron Meyer souvent rond ; yuzu et mandarine : peau qui s'enlève ; pomelo : fruit ≥ 10 cm ;
     quartiers : kumquat 3-6 loges contre 7-15 pour les autres Citrus (Flora of China), kumquat 4-5, calamondin 8-9 (中国植物志 p. 175) ;
     pas imposé aux papedas (combava 6-7), au caviar, au cédrat ni au Poncirus).
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
    'Papeda (combava, ichang)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['tres_large'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere', 'peu'],
      'boutons' => ['blancs'], 'gout_peau' => ['amere']],
    'Cédrat (dont Main de Bouddha)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune'], 'fruit' => ['6-10', 'gt10'],
      'forme' => ['ovale', 'main'], 'couleur' => ['jaune'], 'pulpe' => ['acide', 'peu'], 'feuille' => ['moyenne', 'grande'], 'pelage' => ['adherente']],
    'Citron' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune', 'etroite'], 'forme' => ['ovale', 'ronde'], 'couleur' => ['jaune', 'verte'],
      'pulpe' => ['acide'], 'pelage' => ['adherente'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse', 'rugueuse'], 'quartiers' => ['nombreux']],
    'Lime' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'couleur' => ['verte', 'jaune'], 'forme' => ['ronde', 'ovale'], 'boutons' => ['blancs', 'violets'], 'pulpe' => ['acide'], 'fruit' => ['3-6', '6-10'],
      'aile' => ['etroite', 'aucune'], 'peau' => ['lisse'], 'pelage' => ['adherente'], 'quartiers' => ['nombreux']],
    'Mandarine' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pelage' => ['facile'], 'forme' => ['aplatie', 'ronde'], 'couleur' => ['orange'], 'pulpe' => ['douce', 'acide'],
      'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse'], 'quartiers' => ['nombreux']],
    'Orange douce' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pelage' => ['adherente'], 'forme' => ['ronde', 'ovale'], 'couleur' => ['orange'], 'pulpe' => ['douce'],
      'aile' => ['etroite', 'aucune'], 'boutons' => ['blancs'], 'fruit' => ['6-10', 'gt10'], 'quartiers' => ['nombreux']],
    'Bigaradier (orange amère)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'pelage' => ['adherente'], 'pulpe' => ['amere', 'acide'], 'couleur' => ['orange', 'jaune'],
      'forme' => ['ronde', 'aplatie', 'ovale'], 'boutons' => ['blancs'], 'fruit' => ['6-10'], 'gout_peau' => ['amere'], 'quartiers' => ['nombreux']],
    'Pomelo, pamplemousse' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'fruit' => ['6-10', 'gt10'], 'couleur' => ['jaune', 'verte'], 'feuille' => ['moyenne', 'grande'],
      'boutons' => ['blancs'], 'pelage' => ['adherente'], 'quartiers' => ['nombreux']],
  ];

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

  // date de semis / greffe « 2024 », « 2024-05 » ou « 2024-05-12 » → « ≈ 2 ans », « 7 mois », « 1 an » ; null si illisible ou à venir
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
      'couleur' => ['c', 'Couleur', ['vert foncé', 'vert clair', 'jaunissement entre les nervures', 'jaune uniforme']], 'chute' => ['c', 'Chute', ['aucune', 'faible', 'forte']]]],
    'fleurs' => ['Fleurs', ['boutons' => ['n', 'Boutons', ''], 'ouvertes' => ['n', 'Fleurs ouvertes', ''], 'couleur' => ['c', 'Couleur des boutons', ['blancs', 'violets']],
      'parfum' => ['c', 'Parfum', ['aucun', 'léger', 'fort']]]],
    'fruits' => ['Fruits', ['nombre' => ['n', 'Nombre de fruits (total)', ''], 'noues' => ['n', 'Noués', ''], 'croissance' => ['n', 'En croissance', ''], 'murs' => ['n', 'Mûrs', ''],
      'diametres' => ['l', 'Ø des fruits (un espace entre deux fruits)', 'mm'], 'poids' => ['n', 'Poids moyen', 'g'], 'pepins' => ['n', 'Pépins par fruit', ''],
      'brix' => ['n', 'Taux de sucre', '°Brix'], 'recolte' => ['n', 'Poids récolté', 'g'], 'couleur' => ['c', 'Couleur', ['vert', 'en virage', 'jaune', 'orange']]]],
    'pot' => ['Racines / pot', ['substrat' => ['c', 'Substrat', ['sec', 'frais', 'humide', 'détrempé']], 'racines' => ['c', 'Racines au drainage', ['non', 'oui']],
      'forme' => ['c', 'Nouveau pot : forme', ['rond', 'carré']], 'diam' => ['n', 'Nouveau pot : Ø ou côté', 'cm'], 'haut' => ['n', 'Nouveau pot : hauteur', 'cm'], 'terre' => ['n', 'Nouveau pot : hauteur de terre', 'cm'],
      'pleine_terre' => ['c', 'Pleine terre', ['non', 'oui']]]], // pot.forme/diam/haut/terre/pleine_terre : remplacent le pot de la fiche (journalcultures::mesurer)
    'sante' => ['Santé', ['ravageur' => ['c', 'Ravageur', ['aucun', 'mineuse', 'cochenille', 'acarien', 'puceron', 'autre']],
      'intensite' => ['c', 'Intensité', ['faible', 'moyenne', 'forte']], 'maladie' => ['c', 'Maladie', ['aucune', 'gommose', 'fumagine', 'autre']]]],
  ];
  const CALCULS = ['calc.aile_limbe' => ['Aile / limbe', ''], 'calc.greffon_pg' => ['Greffon / porte-greffe', ''],
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
     ponytail: seuils simples (aile de moins de 2 mm de large = simple liseré ; aile comparée à la longueur du limbe : ¼ et ½ ; feuille 5 et 10 cm ;
     fruit 3, 6 et 10 cm de Ø, sauf fruits en doigt ou en main dont la taille est une longueur) ; les affiner si une plante connue les contredit. */
  public static function suggestions($v, $forme = '') {
    $r = [];
    if (($v['feuilles.aile_w'] ?? 2) < 2) $r['aile'] = 'aucune';
    elseif (isset($v['calc.aile_limbe'])) { $x = $v['calc.aile_limbe']; $r['aile'] = $x == 0 ? 'aucune' : ($x < 0.25 ? 'etroite' : ($x < 0.5 ? 'large' : 'tres_large')); }
    if (isset($v['feuilles.limbe_l'])) { $x = $v['feuilles.limbe_l']; $r['feuille'] = $x < 50 ? 'petite' : ($x <= 100 ? 'moyenne' : 'grande'); }
    if (isset($v['calc.fruits_moy']) && !in_array($forme, ['doigt', 'main'], true)) { $x = $v['calc.fruits_moy']; $r['fruit'] = $x < 30 ? 'lt3' : ($x < 60 ? '3-6' : ($x <= 100 ? '6-10' : 'gt10')); }
    return $r;
  }

  public static function date($v) { // 'Y-m-d' valide ou exception
    $v = (string)$v;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new Exception(__('Date invalide', __FILE__));
    return $v;
  }
}

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
    'pulpe' => ['Pulpe', ['douce' => 'douce', 'acide' => 'acide', 'amere' => 'amère', 'perles' => 'en perles (caviar)', 'peu' => 'presque absente (peau très épaisse)']],
  ];
  /* Profils : groupe => [caractère => codes compatibles] ; un caractère absent du profil ne compte pas.
     Recoupés avec la clé du genre de la Flora of China, vol. 11 (aile > moitié du limbe = papedas, « très large » ; cédrat sans aile, peau plus épaisse que la pulpe ;
     citron : pétales teintés de rose, mamelon ; lime de Tahiti : fleurs blanches (UCR) ou un peu violacées (Morton), les deux acceptées ; bergamote = bigaradier jaune, ronde ou en poire ; citron Meyer souvent rond ; yuzu et mandarine : peau qui s'enlève ; pomelo : fruit ≥ 10 cm).
     ponytail: profils de groupes, pas de variétés : les hybrides restent souvent indécis (la Flora of China le dit aussi) ;
     protocoles UPOV complets non relus, affiner un profil quand une plante connue le contredit. */
  const GROUPES = [
    'Poncirus' => ['feuillage' => ['caduc'], 'folioles' => ['trois'], 'peau' => ['duvet'], 'pulpe' => ['amere', 'acide'], 'boutons' => ['blancs']],
    'Hybride de Poncirus (citrange, citrumelo, FA5…)' => ['feuillage' => ['persistant', 'caduc'], 'folioles' => ['trois', 'mix'], 'peau' => ['lisse', 'duvet'], 'pulpe' => ['acide', 'amere'], 'boutons' => ['blancs']],
    'Kumquat' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'gout_peau' => ['sucree'], 'fruit' => ['lt3', '3-6'], 'forme' => ['ronde', 'ovale'],
      'feuille' => ['petite', 'moyenne'], 'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'couleur' => ['orange', 'jaune'], 'pulpe' => ['acide', 'douce'], 'pelage' => ['adherente']],
    'Calamondin' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'fruit' => ['lt3', '3-6'], 'forme' => ['ronde', 'aplatie'], 'couleur' => ['orange'],
      'pulpe' => ['acide'], 'pelage' => ['facile'], 'peau' => ['lisse'], 'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'feuille' => ['petite', 'moyenne']],
    'Citron caviar (finger lime)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'forme' => ['doigt'], 'feuille' => ['petite'], 'pulpe' => ['perles'], 'aile' => ['aucune'], 'fruit' => ['3-6', '6-10']],
    'Yuzu' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere'], 'couleur' => ['jaune'],
      'fruit' => ['3-6', '6-10'], 'forme' => ['ronde', 'aplatie'], 'boutons' => ['blancs'], 'gout_peau' => ['amere'], 'pelage' => ['facile']],
    'Papeda (combava, ichang)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['tres_large'], 'peau' => ['rugueuse'], 'pulpe' => ['acide', 'amere', 'peu'],
      'boutons' => ['blancs'], 'gout_peau' => ['amere']],
    'Cédrat (dont Main de Bouddha)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune'], 'fruit' => ['6-10', 'gt10'],
      'forme' => ['ovale', 'main'], 'couleur' => ['jaune'], 'pulpe' => ['acide', 'peu'], 'feuille' => ['moyenne', 'grande'], 'pelage' => ['adherente']],
    'Citron' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'boutons' => ['violets'], 'aile' => ['aucune', 'etroite'], 'forme' => ['ovale', 'ronde'], 'couleur' => ['jaune', 'verte'],
      'pulpe' => ['acide'], 'pelage' => ['adherente'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse', 'rugueuse']],
    'Lime' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'couleur' => ['verte', 'jaune'], 'forme' => ['ronde', 'ovale'], 'boutons' => ['blancs', 'violets'], 'pulpe' => ['acide'], 'fruit' => ['3-6', '6-10'],
      'aile' => ['etroite', 'aucune'], 'peau' => ['lisse'], 'pelage' => ['adherente']],
    'Mandarine' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pelage' => ['facile'], 'forme' => ['aplatie', 'ronde'], 'couleur' => ['orange'], 'pulpe' => ['douce', 'acide'],
      'aile' => ['aucune', 'etroite'], 'boutons' => ['blancs'], 'fruit' => ['3-6', '6-10'], 'peau' => ['lisse']],
    'Orange douce' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'pelage' => ['adherente'], 'forme' => ['ronde', 'ovale'], 'couleur' => ['orange'], 'pulpe' => ['douce'],
      'aile' => ['etroite', 'aucune'], 'boutons' => ['blancs'], 'fruit' => ['6-10', 'gt10']],
    'Bigaradier (orange amère)' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'pelage' => ['adherente'], 'pulpe' => ['amere', 'acide'], 'couleur' => ['orange', 'jaune'],
      'forme' => ['ronde', 'aplatie', 'ovale'], 'boutons' => ['blancs'], 'fruit' => ['6-10'], 'gout_peau' => ['amere']],
    'Pomelo, pamplemousse' => ['feuillage' => ['persistant'], 'folioles' => ['un'], 'aile' => ['etroite', 'large'], 'fruit' => ['6-10', 'gt10'], 'couleur' => ['jaune', 'verte'], 'feuille' => ['moyenne', 'grande'],
      'boutons' => ['blancs'], 'pelage' => ['adherente']],
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
  public static function identifier($cfg) {
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
    return ['verdict' => $net ? 'probable' : 'indecis', 'observes' => count($obs), 'groupes' => array_slice($r, 0, 3)];
  }

  public static function date($v) { // 'Y-m-d' valide ou exception
    $v = (string)$v;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new Exception(__('Date invalide', __FILE__));
    return $v;
  }
}

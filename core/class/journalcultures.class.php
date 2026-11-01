<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/journalcultures_calc.class.php';

/* Journal de cultures : un équipement par plante (fiche, pot, emplacement, mesures historisées, alerte de froid, engrais),
   un équipement « collection » par ensemble de plantes (la tuile), un journal unique en table (partagé avec Sélénoculture). */
class journalcultures extends eqLogic {
  const TABLE = 'journalcultures_journal';
  // fiche : clé de configuration => libellé
  const FICHE = ['bota' => 'Nom botanique', 'cultivar' => 'Cultivar', 'porte_greffe' => 'Porte-greffe', 'origine' => 'Origine',
    'rusticite' => 'Rusticité (°C, pleine terre)', 'taille_adulte' => 'Taille adulte', 'exposition' => 'Exposition', 'sol' => 'Sol',
    'arrosage' => 'Arrosage', 'taille' => 'Taille', 'fructification' => 'Fructification', 'mg' => 'Magnésium', 'fe' => 'Fer',
    'maladies' => 'Maladies', 'plus' => 'Plus', 'sources' => 'Sources et points à vérifier'];
  // porte-greffes proposés à la saisie (texte libre toujours possible) : nom => précision affichée dans la liste
  const PORTE_GREFFES = ['C4475' => 'Citrumelo Swingle (pamplemoussier × Poncirus)', 'Volkameriana' => 'Citrus volkameriana',
    'FA5' => 'Forner-Alcaide 5 (Cléopâtre × Poncirus)', 'Poncirus' => 'Poncirus trifoliata', 'Flying Dragon' => 'Poncirus nain'];
  private $_deplace = null; // [ancien, nouvel] emplacement, noté au journal par postSave (« _ » : pas une colonne pour DB::save)
  public static $_sansGbif = false; // déplacement groupé : pas d'appel GBIF (jusqu'à 10 s par plante sans Internet)
  private $_ancColl = null; // ancienne collection à remettre à jour (plante changée de collection ou supprimée)
  const CLE = __DIR__ . '/../../data/plantnet.key'; // clé Pl@ntNet hors de la table config (config::getKey la livre à tout connecté)

  public static function cron15() {
    $eqs = self::byType('journalcultures', true);
    usort($eqs, function ($a, $b) { return ($a->type() == 'collection') <=> ($b->type() == 'collection'); }); // plantes d'abord
    foreach ($eqs as $eq) {
      try { $eq->maj(false); } catch (Throwable $e) { log::add('journalcultures', 'error', $eq->getHumanName() . ' : ' . $e->getMessage()); }
    }
  }

  public function type() { return $this->getConfiguration('type', 'plante') == 'collection' ? 'collection' : 'plante'; }
  public function collection() { // collection d'une plante, ou elle-même
    if ($this->type() == 'collection') return $this;
    $c = self::byId($this->getConfiguration('collection'));
    return is_object($c) && $c->getEqType_name() == 'journalcultures' && $c->type() == 'collection' ? $c : null;
  }
  public function plantes() {
    $r = array_values(array_filter(self::byType('journalcultures'), function ($p) { return $p->type() == 'plante' && $p->getConfiguration('collection') == $this->getId(); }));
    usort($r, function ($a, $b) { return strnatcasecmp($a->getName(), $b->getName()); });
    return $r;
  }

  private static function commandes($type) { // logicalId => [nom, sous-type, unité, historisée]
    if ($type == 'collection') return ['alertes' => [__('Plantes en alerte', __FILE__), 'numeric', '', 1], 'engrais' => [__('Engrais à donner', __FILE__), 'numeric', '', 1],
      'resume' => [__('Résumé des alertes', __FILE__), 'string', '', 0]];
    return ['emplacement' => [__('Emplacement', __FILE__), 'string', '', 1], 'temperature' => [__('Température de l’emplacement', __FILE__), 'numeric', '°C', 1],
      'rusticite' => [__('Rusticité effective', __FILE__), 'numeric', '°C', 1], 'alerte' => [__('Alerte froid', __FILE__), 'binary', '', 1],
      'raison' => [__('Raison de l’alerte', __FILE__), 'string', '', 1], 'engrais' => [__('Engrais de la semaine', __FILE__), 'string', '', 1],
      'hauteur' => [__('Hauteur', __FILE__), 'numeric', 'cm', 1], 'tronc' => [__('Diamètre du tronc', __FILE__), 'numeric', 'mm', 1],
      'feuilles' => [__('Nombre de feuilles', __FILE__), 'numeric', '', 1], 'fruits' => [__('Nombre de fruits', __FILE__), 'numeric', '', 1]];
  }

  public function preSave() {
    $this->setConfiguration('type', $this->type());
    $old = $this->getId() ? self::byId($this->getId()) : null;
    $this->_ancColl = is_object($old) && ($old->getConfiguration('collection') != $this->getConfiguration('collection') || $this->type() != 'plante') ? $old->collection() : null;
    if ($this->type() != 'plante') return;
    foreach (array_merge(array_keys(self::FICHE), ['engrais', 'pot_diam', 'pot_haut', 'pot_terre', 'date_mult']) as $k) { // configuration en utf8mb3 : un émoji ferait échouer la sauvegarde
      if (is_string($v = $this->getConfiguration($k))) $this->setConfiguration($k, preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $v) ?? '');
    }
    if (!in_array($this->getConfiguration('multiplication'), journalcultures_calc::MULTIPLICATIONS, true)) $this->setConfiguration('multiplication', '');
    $g = journalcultures_calc::greffe(['multiplication' => $this->getConfiguration('multiplication'), 'type_greffe' => (string)$this->getConfiguration('type_greffe')]);
    $this->setConfiguration('multiplication', $g['multiplication']); $this->setConfiguration('type_greffe', $g['type_greffe'] ?? '');
    foreach (journalcultures_calc::CARACTERES as $k => [, $opt]) { // caractères observés : codes de la liste seulement
      $v = $this->getConfiguration('id_' . $k);
      if ($v !== '' && $v !== null && !(is_string($v) && isset($opt[$v]))) $this->setConfiguration('id_' . $k, '');
    }
    $this->setConfiguration('ident', journalcultures_calc::identifier($this->caracteres())); // lu par la page de l'équipement ; recalculé pour toutes les plantes par journalcultures_update (profils changés)
    $bota = trim((string)$this->getConfiguration('bota')); $g = $this->getConfiguration('gbif');
    if ($bota === '') $this->setConfiguration('gbif', '');
    elseif (!self::$_sansGbif && (!is_object($old) || trim((string)$old->getConfiguration('bota')) != $bota || !$g || (($g['statut'] ?? '') == 'ERREUR' && ($g['t'] ?? 0) < time() - 86400))) {
      try { $this->setConfiguration('gbif', self::gbif($bota)); } catch (Throwable $e) {
        $this->setConfiguration('gbif', ['statut' => 'ERREUR', 't' => time()]); // nouvel essai dans 24 h, pas à chaque sauvegarde (box sans Internet)
        log::add('journalcultures', 'warning', 'GBIF : ' . $e->getMessage());
      }
    }
    $e = (string)$this->getConfiguration('emplacement');
    $this->_deplace = is_object($old) && $old->getConfiguration('emplacement') != $e ? [(string)$old->getConfiguration('emplacement'), $e] : null;
  }

  /* Commandes du type de l'équipement : crée les absentes ; avec $nettoyer, supprime celles de l'autre type ou d'un autre sous-type
     (collection créée par « Ajouter », donc d'abord plante, puis passée en collection, ou l'inverse). */
  public function commandesOk($nettoyer = false) {
    $att = self::commandes($this->type());
    if ($nettoyer) foreach ($this->getCmd() as $c) {
      $k = $c->getLogicalId();
      $bon = $c->getType() == 'info' ? isset($att[$k]) && $att[$k][1] == $c->getSubType() : $k == 'refresh' && $this->type() == 'collection';
      if (!$bon) $c->remove();
    }
    if ($nettoyer) $this->_cmds = []; // getCmd() garde en cache les commandes lues (postSave, maj) : sinon une supprimée n'est pas recréée
    foreach ($att as $k => [$nom, $sub, $unite, $hist]) {
      if (is_object($this->getCmd('info', $k))) continue;
      $c = new journalculturesCmd();
      $c->setEqLogic_id($this->getId()); $c->setLogicalId($k); $c->setName($nom); $c->setType('info'); $c->setSubType($sub);
      $c->setUnite($unite); $c->setIsHistorized($hist); $c->setIsVisible(0); $c->save();
    }
    if ($this->type() == 'collection' && !is_object($this->getCmd('action', 'refresh'))) {
      $c = new journalculturesCmd();
      $c->setEqLogic_id($this->getId()); $c->setLogicalId('refresh'); $c->setName(__('Rafraîchir', __FILE__));
      $c->setType('action'); $c->setSubType('other'); $c->save();
    }
  }

  // après la sauvegarde des commandes par la page (eqLogic.ajax les resauve par id après postSave : pas de suppression dans postSave)
  public function postAjax() { $this->commandesOk(true); }

  public function postSave() {
    $this->commandesOk();
    if ($this->type() == 'collection' && $this->getDisplay('width') == '') { $this->setDisplay('width', '380px'); $this->save(true); }
    if ($this->_deplace) {
      [$a, $b] = $this->_deplace; $this->_deplace = null;
      try {
        self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => $b, 'action' => 'Déplacement',
          'note' => ($a !== '' ? $a : '?') . ' → ' . ($b !== '' ? $b : '?')]);
      } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : déplacement non noté : ' . $e->getMessage()); } // l'équipement est déjà sauvé
    }
    try {
      if ($this->getIsEnable()) $this->maj();
      elseif ($this->type() == 'plante' && is_object($c = $this->collection())) $c->maj(); // plante désactivée : retirée de la tuile tout de suite
      if (is_object($c = $this->_ancColl) && $c->getId() != $this->getId()) { $this->_ancColl = null; $c->maj(); }
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : ' . $e->getMessage()); }
  }

  public function postRemove() { // l'ancienne tuile ne doit plus lister la plante
    if (is_object($c = $this->_ancColl)) try { $c->maj(); } catch (Throwable $e) { log::add('journalcultures', 'error', $e->getMessage()); }
  }

  public function preRemove() { // photos et entrées du journal de la plante
    if ($this->type() != 'plante') return;
    $this->_ancColl = $this->collection();
    DB::Prepare('DELETE FROM ' . self::TABLE . ' WHERE plante_id = ?', [$this->getId()], DB::FETCH_TYPE_ROW);
    $d = self::dossier($this->getId());
    if (is_dir($d)) { array_map('unlink', glob($d . '/*') ?: []); @rmdir($d); }
  }

  /* ---------- mesures, alerte, engrais ---------- */

  private static function lire($s, $h = 12) { // '#id#' ou '#[Objet][Équipement][Commande]#' → valeur numérique, null si absente ou muette depuis $h heures
    if (!preg_match('/^#(\d+)#$/', trim(cmd::humanReadableToCmd((string)$s)), $m)) return null;
    $c = cmd::byId($m[1]);
    if (!is_object($c) || $c->getType() != 'info') return null;
    $v = $c->execCmd();
    if (!is_numeric($v) || strtotime($c->getCollectDate()) < time() - $h * 3600) return null;
    return (float)$v;
  }

  public static function emplacements() { return journalcultures_calc::emplacements(config::byKey('emplacements', 'journalcultures', '')); }

  public function pot() {
    return ['diam' => $this->getConfiguration('pot_diam'), 'haut' => $this->getConfiguration('pot_haut'), 'terre' => $this->getConfiguration('pot_terre'),
      'pleine_terre' => $this->getConfiguration('pleine_terre') == 1, 'carre' => $this->getConfiguration('pot_forme') == 'carre'];
  }

  public function potTexte() {
    $pot = $this->pot(); $x = function ($k) use ($pot) { return $pot[$k] !== '' && $pot[$k] !== null ? str_replace('.', ',', (string)$pot[$k]) : '?'; };
    return $pot['pleine_terre'] ? __('pleine terre', __FILE__) : sprintf($pot['carre'] ? __('pot carré %s cm de côté, hauteur %s cm, terre %s cm', __FILE__) : __('pot Ø %s cm, hauteur %s cm, terre %s cm', __FILE__), $x('diam'), $x('haut'), $x('terre'));
  }

  public function etat() { // état de la plante maintenant : emplacement, températures, rusticité effective, alerte, engrais à donner
    $e = (string)$this->getConfiguration('emplacement'); $L = self::emplacements();
    $s = $L[$e] ?? ['', '', '']; $inconnu = $e !== '' && !isset($L[$e]); // renommé ou retiré de la configuration
    $t = self::lire($s[0]); $h = self::lire($s[1]); $tmin = self::lire($s[2], 36); // une prévision peut rester identique longtemps
    $r = $this->getConfiguration('rusticite'); $est = null; // fiche sans rusticité : estimée d'après le groupe (nom botanique ou caractères)
    if (journalcultures_calc::nombre($r) === null && ($est = journalcultures_calc::estimee($this->getConfiguration('bota'), $this->caracteres()))) $r = $est['r'];
    $reff = journalcultures_calc::rusticite($r, $this->pot(),
      (float)config::byKey('k', 'journalcultures', 50), (float)config::byKey('pmax', 'journalcultures', 8));
    $al = journalcultures_calc::alerte($reff, $t, $tmin, (float)config::byKey('marge', 'journalcultures', 3));
    $sans = trim($s[0]) === '' && trim($s[2]) === ''; // emplacement sans sonde (Maison…)
    if ($inconnu) { // on ne sait plus où elle est : on garde l'alerte, on le dit
      $al = ['alerte' => null, 'raison' => __('emplacement inconnu', __FILE__)];
      if ($this->valeur('raison') !== $al['raison']) log::add('journalcultures', 'warning', $this->getHumanName() . ' : ' . sprintf(__('emplacement « %s » absent de la configuration', __FILE__), $e));
    } elseif ($reff === null || $sans) $al = ['alerte' => 0, 'raison' => '']; // rien à surveiller : pas d'alerte figée
    elseif ($al === null) { // sondes réglées mais muettes : on garde l'alerte, on le dit
      $al = ['alerte' => null, 'raison' => __('sonde muette', __FILE__)];
      if ($this->valeur('raison') !== $al['raison']) log::add('journalcultures', 'warning', $this->getHumanName() . ' : ' . sprintf(__('sonde de « %s » muette', __FILE__), $e));
    }
    $eng = journalcultures_calc::engrais($this->getConfiguration('engrais'), (int)date('W'));
    if ($eng && DB::Prepare('SELECT COUNT(*) n FROM ' . self::TABLE . ' WHERE plante_id = ? AND action = ? AND date >= ?',
      [$this->getId(), 'Engrais', date('Y-m-d', strtotime('monday this week'))], DB::FETCH_TYPE_ROW)['n'] > 0) $eng = []; // déjà donné cette semaine
    return ['empl' => $e, 't' => $t, 'h' => $h, 'tmin' => $tmin, 'reff' => $reff, 'estimee' => $est, 'alerte' => $al['alerte'] ?? null, 'raison' => $al['raison'] ?? '',
      'engrais' => mb_substr(implode(' ; ', $eng), 0, 120)];
  }

  public function maj($tuile = true) {
    if ($this->type() == 'collection') {
      $n = 0; $eng = 0; $noms = [];
      foreach ($this->plantes() as $p) {
        if (!$p->getIsEnable()) continue;
        if ($p->valeur('alerte') == 1) { $n++; $noms[] = $p->getName() . ' (' . (self::muette($p->valeur('raison')) ? $p->valeur('raison') : $p->getConfiguration('emplacement')) . ')'; } // alerte gardée, sonde muette : le dire
        elseif (self::muette($p->valeur('raison'))) $noms[] = $p->getName() . ' (' . $p->valeur('raison') . ')'; // dans le résumé, pas dans le compte
        if ($p->valeur('engrais') != '') $eng++;
      }
      $r = mb_substr(implode(', ', $noms), 0, 250); // résumé d'abord : le scénario 258, déclenché par « alertes », le lit aussitôt
      if ($r !== '' || $this->valeur('resume') !== '') $this->checkAndUpdateCmd('resume', $r); // pas d'évènement vide chaque quart d'heure
      $this->checkAndUpdateCmd('alertes', $n); $this->checkAndUpdateCmd('engrais', $eng);
      $d = $this->donnees(); unset($d['admin']); // admin vaut 0 dans le cron : pas un changement
      $sig = md5(json_encode($d));
      if (cache::byKey('journalcultures::tuile' . $this->getId())->getValue() !== $sig) { // redessinée seulement si quelque chose a changé
        cache::set('journalcultures::tuile' . $this->getId(), $sig);
        $this->refreshWidget();
      }
      return;
    }
    $s = $this->etat();
    foreach (['emplacement' => $s['empl'], 'temperature' => $s['t'], 'rusticite' => $s['reff'], 'alerte' => $s['alerte'], 'raison' => $s['raison'], 'engrais' => $s['engrais']] as $k => $v) {
      if ($v === null) continue; // inconnu : on garde la dernière valeur plutôt qu'un faux 0
      if ($v === '' && $this->valeur($k) === '') continue; // pas d'évènement vide chaque quart d'heure
      $this->checkAndUpdateCmd($k, $v);
    }
    if ($tuile && is_object($c = $this->collection())) $c->maj(); // sinon la tuile garde l'ancien état jusqu'au prochain cron
  }

  private static function muette($raison) { return in_array($raison, [__('sonde muette', __FILE__), __('emplacement inconnu', __FILE__)], true); }

  public function valeur($k) { $c = $this->getCmd('info', $k); return is_object($c) ? $c->execCmd() : null; }

  /* Relevé du catalogue journalcultures_calc::MESURES : ['v' => ['organe.clé' => texte], 'note' => texte] → une entrée « Mesure » du journal
     (valeurs + calculs dans la colonne valeurs) ; les commandes historisées hauteur / tronc / feuilles / fruits suivent. → [caractère => code] suggérés */
  public function mesurer(array $in) {
    if (!$this->getIsEnable()) throw new Exception(__('Plante désactivée', __FILE__));
    $v = journalcultures_calc::valeurs(is_array($in['v'] ?? null) ? $in['v'] : []);
    $note = journalcultures_calc::texte($in['note'] ?? '', 1000);
    if (!$v && $note === '') throw new Exception(__('Aucune mesure saisie', __FILE__));
    $v += journalcultures_calc::calculs($v);
    $fr = array_intersect_key($v, array_flip(['fruits.noues', 'fruits.croissance', 'fruits.murs']));
    // courbes : tronc = greffon (ou tronc d'une plante non greffée), jamais le porte-greffe ; fruits = total saisi, sinon somme des 3 comptages s'ils sont tous saisis
    $ev = ['hauteur' => $v['plante.hauteur'] ?? null, 'tronc' => $v['tronc.d_greffon'] ?? $v['tronc.d_tronc'] ?? null,
      'feuilles' => $v['feuilles.nombre'] ?? null, 'fruits' => $v['fruits.nombre'] ?? (count($fr) == 3 ? array_sum($fr) : null)];
    foreach ($ev as $k => $x) if ($x !== null && !is_object($this->getCmd('info', $k))) throw new Exception(sprintf(__('Commande absente : %s (sauvegarder l\'équipement)', __FILE__), $k));
    $sug = journalcultures_calc::suggestions($v, (string)$this->getConfiguration('id_forme')); $diff = []; $chg = false;
    foreach ($sug as $k => $code) {
      $avant = (string)$this->getConfiguration('id_' . $k);
      if ($avant === '') { $this->setConfiguration('id_' . $k, $code); $chg = true; }
      elseif ($avant !== $code) $diff[] = sprintf(__('%s : saisi « %s », mesuré « %s »', __FILE__), journalcultures_calc::CARACTERES[$k][0],
        journalcultures_calc::CARACTERES[$k][1][$avant] ?? $avant, journalcultures_calc::CARACTERES[$k][1][$code]);
    }
    $potAvant = $this->potTexte(); // nouveau pot (rempotage, mise en pleine terre) : remplace celui de la fiche, rusticité recalculée par postSave
    foreach (['diam' => 'pot_diam', 'haut' => 'pot_haut', 'terre' => 'pot_terre'] as $k => $c) if (isset($v["pot.$k"])) { $this->setConfiguration($c, $v["pot.$k"]); $chg = true; }
    if (isset($v['pot.forme'])) { $this->setConfiguration('pot_forme', $v['pot.forme'] == 'carré' ? 'carre' : ''); $chg = true; }
    if (isset($v['pot.pleine_terre'])) { $this->setConfiguration('pleine_terre', $v['pot.pleine_terre'] == 'oui' ? 1 : 0); $chg = true; }
    if (($potApres = $this->potTexte()) != $potAvant) $diff[] = sprintf(__('Pot : %s → %s', __FILE__), $potAvant, $potApres);
    if ($chg) { // caractères complétés ou pot changé : identification recalculée par preSave ; avant le journal, pour qu'une erreur ne laisse pas une mesure à renvoyer en double
      self::$_sansGbif = true;
      try { $this->save(); } finally { self::$_sansGbif = false; }
    }
    $txt = journalcultures_calc::resume($v);
    self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'),
      'action' => 'Mesure', 'note' => implode(' — ', array_filter([$txt, $note, implode(' ; ', $diff)], 'strlen')), 'valeurs' => $v]);
    foreach ($ev as $k => $x) if ($x !== null) $this->getCmd('info', $k)->event($x); // event : une mesure identique fait aussi un point sur la courbe
    return $sug;
  }

  /* ---------- journal (table, partagé avec Sélénoculture) ---------- */

  public static function creerTable() {
    DB::Prepare('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, date DATE NOT NULL,
      plante_id INT NULL, collection_id INT NULL, plante VARCHAR(120) NOT NULL DEFAULT \'\', lieu VARCHAR(60) NOT NULL DEFAULT \'\', action VARCHAR(30) NOT NULL,
      cat VARCHAR(10) NOT NULL DEFAULT \'\', note TEXT NULL, lune VARCHAR(200) NULL, origine VARCHAR(30) NOT NULL DEFAULT \'journalcultures\',
      cree DATETIME NOT NULL, KEY plante (plante_id, date), KEY collection (collection_id), KEY date (date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', [], DB::FETCH_TYPE_ROW);
    if (!DB::Prepare('SHOW COLUMNS FROM ' . self::TABLE . ' LIKE \'valeurs\'', [], DB::FETCH_TYPE_ROW)) // 0.3 : valeurs des mesures (JSON)
      DB::Prepare('ALTER TABLE ' . self::TABLE . ' ADD valeurs TEXT NULL', [], DB::FETCH_TYPE_ROW);
  }

  private static function lune($date) { // lune de midi, si Sélénoculture est présent (même calcul)
    $f = __DIR__ . '/../../../selenoculture/core/class/selenoculture_lune.class.php';
    if (!class_exists('selenoculture_lune') && is_file($f)) require_once $f;
    if (!class_exists('selenoculture_lune')) return null;
    $l = selenoculture_lune::etat(strtotime($date . ' 12:00'));
    return json_encode(['phase' => $l['phase'], 'element' => $l['element'], 'montante' => $l['montante'] ? 1 : 0], JSON_UNESCAPED_UNICODE);
  }

  /* Ajoute une entrée validée : date, action (ACTIONS), plante_id (plante du plugin) ou plante (texte), collection_id (entrée libre
     notée depuis une collection), lieu, cat, note, origine, valeurs (tableau, mesures) → id
     Point d'entrée public pour les autres plugins (Sélénoculture). */
  public static function inserer(array $in) {
    $date = journalcultures_calc::date($in['date'] ?? '');
    if (!in_array($in['action'] ?? '', journalcultures_calc::ACTIONS, true)) throw new Exception(__('Action inconnue', __FILE__));
    $cat = (string)($in['cat'] ?? '');
    if ($cat !== '' && !in_array($cat, journalcultures_calc::ELEMENTS, true)) throw new Exception(__('Catégorie inconnue', __FILE__));
    $pid = null; $plante = journalcultures_calc::texte($in['plante'] ?? '', 120);
    if (($in['plante_id'] ?? '') !== '' && $in['plante_id'] !== null) {
      $p = self::byId($in['plante_id']);
      if (!is_object($p) || $p->getEqType_name() != 'journalcultures' || $p->type() != 'plante') throw new Exception(__('Plante introuvable', __FILE__));
      $pid = (int)$p->getId(); $plante = mb_substr($p->getName(), 0, 120);
    }
    $cid = null;
    if ($pid === null && !empty($in['collection_id'])) {
      $c = self::byId($in['collection_id']);
      if (!is_object($c) || $c->getEqType_name() != 'journalcultures' || $c->type() != 'collection') throw new Exception(__('Collection introuvable', __FILE__));
      $cid = (int)$c->getId();
    }
    $t = function ($k, $n) use ($in) { return journalcultures_calc::texte($in[$k] ?? '', $n); };
    $val = is_array($in['valeurs'] ?? null) && $in['valeurs'] ? json_encode($in['valeurs'], JSON_UNESCAPED_UNICODE) : null;
    DB::Prepare('INSERT INTO ' . self::TABLE . ' (date, plante_id, collection_id, plante, lieu, action, cat, note, lune, origine, valeurs, cree) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
      [$date, $pid, $cid, $plante, $t('lieu', 60), $in['action'], $cat, $t('note', 5000), self::lune($date), $t('origine', 30) ?: 'journalcultures', $val], DB::FETCH_TYPE_ROW);
    return (int)DB::getLastInsertId();
  }

  // entrées les plus récentes d'abord ; $ids = plantes, $coll = collection (ses entrées libres) ; null et null = tout le journal, Sélénoculture compris
  public static function journal($ids = null, $limite = 500, $coll = null) {
    $w = [];
    if (is_array($ids) && $ids) $w[] = 'plante_id IN (' . implode(',', array_map('intval', $ids)) . ')';
    if ($coll !== null) $w[] = 'collection_id = ' . (int)$coll;
    if ((is_array($ids) || $coll !== null) && !$w) return [];
    $w = $w ? ' WHERE ' . implode(' OR ', $w) : '';
    $r = DB::Prepare('SELECT id, date, plante_id, plante, lieu, action, cat, note, lune, origine, valeurs FROM ' . self::TABLE . $w . ' ORDER BY date DESC, id DESC LIMIT ' . (int)$limite, [], DB::FETCH_TYPE_ALL) ?: [];
    foreach ($r as &$x) { $x['lune'] = json_decode((string)$x['lune'], true); $x['valeurs'] = json_decode((string)$x['valeurs'], true); }
    return $r;
  }

  public static function supprimerEntree($id) { // → plante concernée (pour la remettre à jour) ou null
    $x = DB::Prepare('SELECT plante_id FROM ' . self::TABLE . ' WHERE id = ?', [(int)$id], DB::FETCH_TYPE_ROW);
    if (!$x) throw new Exception(__('Entrée introuvable', __FILE__));
    DB::Prepare('DELETE FROM ' . self::TABLE . ' WHERE id = ?', [(int)$id], DB::FETCH_TYPE_ROW);
    return $x['plante_id'] ? self::byId($x['plante_id']) : null;
  }

  /* ---------- botanique, photos, Pl@ntNet ---------- */

  private static function gbif($nom) { // GBIF species/match (sans clé) : nom retenu, famille, statut
    $r = json_decode((string)(new com_http('https://api.gbif.org/v1/species/match?' . http_build_query(['name' => $nom, 'kingdom' => 'Plantae'])))->exec(10, 1), true);
    if (!is_array($r)) throw new Exception('réponse illisible');
    // HIGHERRANK sans genre = « Plantae » ou une famille (ex. hybride entre deux genres) : rien d'utile
    if (($r['matchType'] ?? 'NONE') == 'NONE' || (($r['matchType'] ?? '') == 'HIGHERRANK' && ($r['genus'] ?? '') === '')) return ['statut' => 'NONE'];
    $t = function ($k) use ($r) { return journalcultures_calc::texte($r[$k] ?? '', 120); };
    return ['cle' => (int)($r['acceptedUsageKey'] ?? $r['usageKey'] ?? 0), 'nom' => $t('scientificName'), 'espece' => $t('species'), 'famille' => $t('family'),
      'statut' => $t('status'), 'type' => $t('matchType'), 'confiance' => (int)($r['confidence'] ?? 0)];
  }

  private static function dossier($id) { return __DIR__ . '/../../data/photos/' . (int)$id; } // data/.htaccess : rien n'est servi directement

  public function photos() {
    $r = array_map('basename', glob(self::dossier($this->getId()) . '/*.{jpg,png,webp}', GLOB_BRACE) ?: []);
    rsort($r); // noms datés : plus récentes d'abord
    return $r;
  }

  public function photo($nom) { // chemin d'une photo de la plante, nom contrôlé (pas de ../)
    if (!preg_match('/^\d{8}_\d{6}_[0-9a-f]{6}\.(jpg|png|webp)$/D', (string)$nom) || !is_file($f = self::dossier($this->getId()) . '/' . $nom)) throw new Exception(__('Photo introuvable', __FILE__));
    return $f;
  }

  private static function image(array $f) { // fichier envoyé ($_FILES) : image jpeg/png/webp de 15 Mo max → extension
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) throw new Exception(__('Envoi de la photo échoué', __FILE__));
    if ($f['size'] > 15 * 1048576) throw new Exception(__('Photo trop lourde (15 Mo max)', __FILE__));
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
    if (!$ext || !getimagesize($f['tmp_name'])) throw new Exception(__('Format refusé (jpeg, png ou webp)', __FILE__));
    return $ext;
  }

  // ponytail: photo gardée telle quelle (pas de réduction) ; miniatures si la fenêtre devient lente
  public function ajouterPhoto(array $f) {
    $ext = self::image($f);
    $d = self::dossier($this->getId());
    if (!is_dir($d) && !mkdir($d, 0775, true)) throw new Exception(__('Dossier des photos impossible à créer', __FILE__));
    $nom = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $d . '/' . $nom)) throw new Exception(__('Envoi de la photo échoué', __FILE__));
    return $nom;
  }

  public static function cle() { return is_file(self::CLE) ? trim((string)file_get_contents(self::CLE)) : ''; }

  /* Appelé par config::save : la clé saisie part dans data/plantnet.key (data/.htaccess : Deny from all), la table config ne garde
     que « ******** » ; ce même texte renvoyé tel quel = clé inchangée, champ vidé = clé supprimée. */
  public static function preConfig_plantnet($v) {
    $v = trim((string)$v);
    if ($v === '') { @unlink(self::CLE); return ''; }
    if ($v !== '********') {
      if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $v)) throw new Exception(__('Clé Pl@ntNet invalide', __FILE__));
      if (!is_dir(dirname(self::CLE))) mkdir(dirname(self::CLE), 0775, true);
      if (file_put_contents(self::CLE, $v, LOCK_EX) === false) throw new Exception(__('Clé Pl@ntNet impossible à enregistrer', __FILE__));
      @chmod(self::CLE, 0640);
    }
    return self::cle() !== '' ? '********' : '';
  }

  public function identifier($nom, $organe) { return self::identifierFichier($this->photo($nom), $organe); }
  public static function identifierEnvoi(array $f, $organe) { $ext = self::image($f); return self::identifierFichier($f['tmp_name'], $organe, $ext); } // « ＋ Plante » : photo pas encore rangée

  // Pl@ntNet (clé de l'utilisateur, 500 identifications par jour) : 5 meilleures espèces
  private static function identifierFichier($chemin, $organe, $ext = null) {
    $cle = self::cle();
    if ($cle === '') throw new Exception(__('Clé Pl@ntNet absente : la saisir dans la configuration du plugin', __FILE__));
    if (!in_array($organe, ['auto', 'leaf', 'flower', 'fruit', 'bark'], true)) $organe = 'auto';
    $ext = $ext ?? pathinfo($chemin, PATHINFO_EXTENSION); // fichier envoyé : nom temporaire sans extension
    $img = new CURLFile($chemin, ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? '', 'photo.' . $ext);
    $ch = curl_init('https://my-api.plantnet.org/v2/identify/all?' . http_build_query(['api-key' => $cle, 'lang' => 'fr']));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['images' => $img, 'organs' => $organe],
      CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode((string)$r, true);
    // réponse d'erreur recopiée sans la requête (la clé est dans l'adresse)
    if ($code == 404) return []; // « Species not found » : aucune espèce reconnue
    if ($code != 200 || !is_array($j)) throw new Exception(sprintf(__('Pl@ntNet a répondu %s %s', __FILE__), $code ?: __('(pas de réponse)', __FILE__), journalcultures_calc::texte(is_array($j) ? ($j['message'] ?? '') : '', 150)));
    return array_map(function ($x) {
      $s = $x['species'] ?? [];
      return ['score' => round(100 * ($x['score'] ?? 0)), 'nom' => $s['scientificNameWithoutAuthor'] ?? '', 'auteur' => $s['scientificNameAuthorship'] ?? '',
        'famille' => $s['family']['scientificNameWithoutAuthor'] ?? '', 'communs' => implode(', ', array_slice($s['commonNames'] ?? [], 0, 3))];
    }, array_slice($j['results'] ?? [], 0, 5));
  }

  /* Nouvelle plante saisie dans la tuile (« ＋ Plante ») : dans la collection et son objet, masquée du dashboard comme les autres → id */
  /* « Greffer… » ($pg = porte-greffe) : la même plante devient le greffon (même id : journal, photos, pot, emplacement, courbes gardés) ;
     fiche, caractères observés et étiquette décrivaient le porte-greffe et sont vidés, sauf le sol (ce sont toujours ses racines). */
  public static function creer(journalcultures $coll, array $in, ?journalcultures $pg = null) {
    $nom = journalcultures_calc::texte($in['nom'] ?? '', 120);
    if ($nom === '') throw new Exception(__('Nom de la plante obligatoire', __FILE__));
    $c = journalcultures_calc::nouvelle($in, array_map('strval', array_keys(self::emplacements()))); // contrôlé avant toute écriture ; strval : un nom « 2 » devient une clé entière
    foreach (eqLogic::byObjectId($coll->getObject_id(), false) as $e) if (mb_strtolower($e->getName()) == mb_strtolower($nom) && (!$pg || $e->getId() != $pg->getId())) // tous plugins : nom unique par objet
      throw new Exception(__('Un équipement de cet objet porte déjà ce nom', __FILE__));
    if ($pg) {
      if (!$pg->getIsEnable()) throw new Exception(__('Plante désactivée', __FILE__));
      $p = $pg; $avant = $pg->getName(); $c['multiplication'] = 'greffe';
      try { $jour = journalcultures_calc::date($c['date_mult'] ?? ''); } catch (Exception $x) { $jour = date('Y-m-d'); } // date de greffe partielle (2026-05) ou absente : aujourd'hui
      foreach (array_merge(array_diff(array_keys(self::FICHE), ['sol']), ['etiquette', 'type_greffe', 'date_mult']) as $k) $p->setConfiguration($k, '');
      foreach (array_keys(journalcultures_calc::CARACTERES) as $k) $p->setConfiguration('id_' . $k, '');
    } else {
      $p = new journalcultures();
      $p->setEqType_name('journalcultures'); $p->setIsEnable(1); $p->setIsVisible(0); $p->setConfiguration('type', 'plante');
    }
    $p->setName($nom); $p->setObject_id($coll->getObject_id()); $p->setConfiguration('collection', $coll->getId());
    foreach ($c as $k => $v) $p->setConfiguration($k, $v);
    try { $p->save(); } catch (Exception $x) { // ponytail: l'index unique du core ignore aussi les accents (« Cedrat » = « Cédrat »)
      if (strpos($x->getMessage(), 'Duplicate') !== false) throw new Exception(__('Un équipement de cet objet porte déjà ce nom', __FILE__));
      throw $x;
    }
    // save : preSave : identification, GBIF si nom botanique ; postSave : commandes, tuile de la collection (et l'ancienne si greffe)
    if ($pg) try { // greffe déjà enregistrée : une erreur ici ne doit pas la faire refaire (« X greffé sur X »)
      $nom = $p->getName(); // nettoyé par le core (# [ ] & % retirés)
      self::inserer(['date' => $jour, 'plante_id' => $p->getId(), 'plante' => $nom,
        'lieu' => (string)$p->getConfiguration('emplacement'), 'action' => 'Greffe', 'note' => sprintf(__('%s greffé sur %s (%s)', __FILE__),
          implode(' ', array_filter([$c['bota'] ?? '', $c['cultivar'] ?? '', $nom !== ($c['bota'] ?? '') ? '« ' . $nom . ' »' : ''])), $avant,
          journalcultures_calc::GREFFES[$c['type_greffe'] ?? ''] ?? __('type de greffe non précisé', __FILE__))]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $p->getHumanName() . ' : greffe non notée au journal : ' . $e->getMessage()); }
    return (int)$p->getId();
  }

  /* ---------- tuile et fenêtres ---------- */

  public function accepter(array $in, array $src) { // « Identifier… » : champs cochés écrits, puis une entrée « Observation » au journal
    $act = ['bota' => $this->getConfiguration('bota'), 'rusticite' => $this->getConfiguration('rusticite')];
    foreach ($this->caracteres() as $k => $v) $act['id_' . $k] = $v;
    [$c, $l] = journalcultures_calc::accepter($act, $in, $src);
    if (!$c) throw new Exception(__('Rien à changer', __FILE__));
    foreach ($c as $k => $v) $this->setConfiguration($k, $v);
    $this->save(); // preSave : identification, GBIF si nom botanique changé ; postSave : tuile
    try {
      self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'),
        'action' => 'Observation', 'note' => __('Identification', __FILE__) . ' : ' . implode(' ; ', $l)]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : identification non notée : ' . $e->getMessage()); } // fiche déjà sauvée
    return count($c);
  }

  public function caracteres() { // [caractère => code saisi] pour journalcultures_calc::identifier
    $r = [];
    foreach (array_keys(journalcultures_calc::CARACTERES) as $k) $r[$k] = $this->getConfiguration('id_' . $k);
    return $r;
  }

  public function fiche() { // tout ce qu'affiche la fenêtre d'une plante
    $s = $this->etat(); $g = $this->getConfiguration('gbif') ?: [];
    $f = [];
    foreach (self::FICHE as $k => $l) if (($v = trim((string)$this->getConfiguration($k))) !== '') $f[] = [__($l, __FILE__), $v];
    $potTxt = $this->potTexte();
    $cmds = [];
    foreach (self::commandes('plante') as $k => [$n, , $u]) if (is_object($c = $this->getCmd('info', $k))) $cmds[$k] = ['id' => $c->getId(), 'nom' => $n, 'val' => $c->execCmd(), 'unite' => $u];
    $bota = trim((string)$this->getConfiguration('bota')); $q = rawurlencode(($g['espece'] ?? '') ?: $bota);
    $liens = $bota === '' ? [] : array_filter([
      ['POWO (Kew)', 'https://powo.science.kew.org/results?q=' . $q],
      !empty($g['cle']) ? ['GBIF', 'https://www.gbif.org/species/' . (int)$g['cle']] : null,
      ['Tela Botanica', 'https://www.tela-botanica.org/?s=' . $q],
      ['PFAF', 'https://pfaf.org/user/Plant.aspx?LatinName=' . $q]]);
    $obs = journalcultures_calc::observes($this->caracteres()); $carac = [];
    foreach ($obs as $k => $v) $carac[] = [journalcultures_calc::CARACTERES[$k][0], journalcultures_calc::CARACTERES[$k][1][$v]];
    return ['id' => $this->getId(), 'nom' => $this->getName(), 'fiche' => $f, 'carac' => $carac, 'brut' => ['bota' => $bota, 'rusticite' => trim((string)$this->getConfiguration('rusticite')), 'carac' => $obs], 'ident' => journalcultures_calc::identifier($obs), 'gbif' => $g, 'pot' => $potTxt, 'etat' => $s, 'cmds' => $cmds,
      'journal' => self::journal([$this->getId()], 300), 'photos' => $ph = $this->photos(), 'liens' => array_values($liens),
      'etiquette' => in_array($this->getConfiguration('etiquette'), $ph, true) ? $this->getConfiguration('etiquette') : '', // photo supprimée : plus d'étiquette
      'plantnet' => self::cle() !== '' ? 1 : 0, 'multiplication' => $this->multiplication(), 'mesures' => journalcultures_calc::MESURES, 'calculs' => journalcultures_calc::CALCULS];
  }

  public function multiplication() { // « Greffe (fente) · ≈ 2 ans (2024) », '' si rien de saisi
    $m = (string)$this->getConfiguration('multiplication'); $g = journalcultures_calc::GREFFES[$this->getConfiguration('type_greffe')] ?? ''; $d = trim((string)$this->getConfiguration('date_mult'));
    $a = journalcultures_calc::age($d);
    return implode(' · ', array_filter([$m !== '' ? __(ucfirst($m), __FILE__) . ($g !== '' ? ' (' . $g . ')' : '') : '', $a !== null ? $a . ' (' . $d . ')' : $d]));
  }

  public function donnees() { // tuile d'une collection
    $pl = []; $L = self::emplacements();
    $mes = []; // date de la dernière mesure par plante
    foreach (DB::Prepare('SELECT plante_id, MAX(date) d FROM ' . self::TABLE . ' WHERE action = ? AND plante_id IS NOT NULL GROUP BY plante_id', ['Mesure'], DB::FETCH_TYPE_ALL) ?: [] as $x) $mes[$x['plante_id']] = $x['d'];
    foreach ($this->plantes() as $p) {
      if (!$p->getIsEnable()) continue;
      $sonde = trim($L[$p->getConfiguration('emplacement')][0] ?? '') !== ''; // lieu sans sonde : pas l'ancienne température
      $m = self::muette($p->valeur('raison')); // sonde muette ou emplacement inconnu
      $rust = journalcultures_calc::nombre($p->getConfiguration('rusticite')) !== null // rusticité effacée de la fiche : pas l'ancienne valeur
        || journalcultures_calc::estimee($p->getConfiguration('bota'), $p->caracteres()) !== null; // … sauf si le groupe en donne une
      $pl[] = ['id' => $p->getId(), 'nom' => $p->getName(), 'empl' => $p->valeur('emplacement'), 't' => $sonde && !$m ? $p->valeur('temperature') : null, 'muette' => $m,
        'reff' => $rust ? $p->valeur('rusticite') : null,
        'alerte' => $p->valeur('alerte'), 'raison' => $p->valeur('raison'), 'engrais' => $p->valeur('engrais'), 'rust' => $p->getConfiguration('rusticite'),
        'pg' => (string)$p->getConfiguration('porte_greffe'), 'mes' => $mes[$p->getId()] ?? ''];
    }
    return ['id' => $this->getId(), 'nom' => $this->getName(), 'plantes' => $pl, 'emplacements' => array_keys(self::emplacements()), 'admin' => isConnect('admin') ? 1 : 0,
      'actions' => array_map(function ($a) { return __($a, __FILE__); }, journalcultures_calc::ACTIONS), 'actionsCle' => journalcultures_calc::ACTIONS];
  }

  public static function collections() { // [[id, nom]] des collections lisibles, pour « Greffer… »
    $cs = array_filter(self::byType('journalcultures', true), function ($c) { return $c->type() == 'collection' && $c->hasRight('r'); });
    usort($cs, function ($a, $b) { return strnatcasecmp($a->getName(), $b->getName()); });
    return array_map(function ($c) { return [(int)$c->getId(), $c->getName()]; }, $cs);
  }

  public static function page() { // panel plein écran : collections lisibles par l'utilisateur + températures des emplacements
    $cs = array_values(array_filter(self::byType('journalcultures', true), function ($c) { return $c->type() == 'collection' && $c->hasRight('r'); }));
    usort($cs, function ($a, $b) { return strnatcasecmp($a->getName(), $b->getName()); });
    $lieux = [];
    foreach (self::emplacements() as $nom => $s) if (trim($s[0]) !== '') $lieux[] = ['nom' => $nom, 't' => self::lire($s[0]), 'h' => self::lire($s[1])];
    return ['collections' => array_map(function ($c) { return $c->donnees(); }, $cs), 'lieux' => $lieux];
  }

  public static function lib() { // ponytail: bibliothèque recopiée dans chaque tuile et dans le panel (gardée par window.journalcultures)
    static $js = [];
    $l = translate::getLanguage();
    return $js[$l] ??= translate::exec(file_get_contents(__DIR__ . '/../template/journalcultures.js'), 'plugins/journalcultures/core/template/journalcultures.js');
  }

  // ponytail: même gabarit en mobile ; core/template/mobile/collection.html si la tuile y rend mal
  public function toHtml($_version = 'dashboard') {
    if ($this->type() != 'collection') return ''; // les plantes n'ont pas de tuile : on les voit dans leur collection
    $replace = $this->preToHtml($_version, [], true);
    if (!is_array($replace)) return $replace;
    $json = json_encode($this->donnees(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    $replace['#cmd#'] = '';
    $replace += ['#eqLogic_class#' => 'eqLogic_layout_default', '#background-color#' => ''];
    $replace = array_map(function ($v) { return is_string($v) ? str_replace('@@', '@ @', $v) : $v; }, $replace); // pas de @@LIB@@ venu d'un nom
    $html = template_replace($replace, getTemplate('core', 'dashboard', 'collection', 'journalcultures'));
    return $this->postToHtml($_version, strtr($html, ['@@LIB@@' => self::lib(), '@@DATA@@' => $json]));
  }
}

class journalculturesCmd extends cmd {
  public function preSave() { // texte possiblement > 127 caractères : refusé par history.value en mode strict
    if ($this->getLogicalId() == 'resume') $this->setIsHistorized(0);
  }

  public function execute($_options = []) {
    if ($this->getLogicalId() != 'refresh') return;
    $eq = $this->getEqLogic();
    foreach ($eq->plantes() as $p) if ($p->getIsEnable()) $p->maj(false); // la collection relit l'état de ses plantes
    $eq->maj();
  }
}

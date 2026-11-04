<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/journalcultures_calc.class.php';

/* Journal de cultures : un équipement par plante (fiche, pot, emplacement, mesures historisées, alerte de froid, engrais),
   un équipement « collection » par ensemble de plantes (la tuile), un journal unique en table (partagé avec Sélénoculture). */
class journalcultures extends eqLogic {
  const TABLE = 'journalcultures_journal';
  // fiche : clé de configuration => libellé
  const HIVER = '45-8'; // repos hivernal : novembre → février (hémisphère nord), choix du plugin
  const FICHE = ['bota' => 'Nom botanique', 'cultivar' => 'Cultivar', 'porte_greffe' => 'Porte-greffe', 'origine' => 'Origine',
    'rusticite' => 'Rusticité : température minimale supportée par une plante adulte en pleine terre (°C)', 'taille_adulte' => 'Hauteur adulte', 'exposition' => 'Exposition', 'sol' => 'Sol',
    'arrosage' => 'Arrosage', 'taille' => 'Taille (coupe)', 'fructification' => 'Fructification', 'mg' => 'Apport de magnésium', 'fe' => 'Apport de fer',
    'maladies' => 'Maladies', 'plus' => 'Plus', 'sources' => 'Sources et points à vérifier'];
  // porte-greffes proposés à la saisie (texte libre toujours possible) : nom => précision affichée dans la liste
  // clés = noms stockés (inchangées) ; C4475 s'affiche CPB 4475
  const PORTE_GREFFES = ['C4475' => 'CPB 4475 : citrumelo Swingle (pomelo ‘Duncan’ × Poncirus)', 'Volkameriana' => 'Citrus ×volkameriana (citronnier Volkamer)',
    'FA5' => 'Forner-Alcaide 5 (mandarinier Cléopâtre × Poncirus)', 'Poncirus' => 'Citrus trifoliata (Poncirus trifoliata)', 'Flying Dragon' => 'Flying Dragon (Poncirus nain, rameaux tortueux)'];
  private $_deplace = null; // [ancien, nouvel] emplacement, noté au journal par postSave (« _ » : pas une colonne pour DB::save)
  public static $_sansGbif = false; // déplacement groupé : pas d'appel GBIF (jusqu'à 10 s par plante sans Internet)
  private $_ancColl = null; // ancienne collection à remettre à jour (plante changée de collection ou supprimée)
  const CLE = __DIR__ . '/../../data/plantnet.key'; // clé Pl@ntNet hors de la table config (config::getKey la livre à tout connecté)

  private static $_memo = null; // pendant le cron seulement : liste des équipements et dernières mesures lues une fois pour toutes les collections
  public static function cron15() {
    self::$_memo = [];
    try {
      $eqs = self::byType('journalcultures', true);
      usort($eqs, function ($a, $b) { return ($a->type() == 'collection') <=> ($b->type() == 'collection'); }); // plantes d'abord
      foreach ($eqs as $eq) {
        try { $eq->maj(false); } catch (Throwable $e) { log::add('journalcultures', 'error', $eq->getHumanName() . ' : ' . $e->getMessage()); }
      }
    } finally { self::$_memo = null; } // le reste du cron (autres plugins) ne doit jamais voir un mémo actif
  }

  public function type() { return $this->getConfiguration('type', 'plante') == 'collection' ? 'collection' : 'plante'; }
  public function collection() { // collection d'une plante, ou elle-même
    if ($this->type() == 'collection') return $this;
    $c = self::byId($this->getConfiguration('collection'));
    return is_object($c) && $c->getEqType_name() == 'journalcultures' && $c->type() == 'collection' ? $c : null;
  }
  public function plantes() {
    $tous = self::$_memo === null ? self::byType('journalcultures') : (self::$_memo['tous'] ??= self::byType('journalcultures'));
    $r = array_values(array_filter($tous, function ($p) { return $p->type() == 'plante' && $p->getConfiguration('collection') == $this->getId(); }));
    usort($r, function ($a, $b) { return strnatcasecmp($a->getName(), $b->getName()); });
    return $r;
  }

  private static function commandes($type) { // logicalId => [nom, sous-type, unité, historisée]
    if ($type == 'collection') return ['alertes' => [__('Plantes en alerte', __FILE__), 'numeric', '', 1], 'engrais' => [__('Engrais à donner', __FILE__), 'numeric', '', 1],
      'resume' => [__('Résumé des alertes', __FILE__), 'string', '', 0]];
    return ['emplacement' => [__('Emplacement', __FILE__), 'string', '', 1], 'temperature' => [__('Température de l’emplacement', __FILE__), 'numeric', '°C', 1],
      'rusticite' => [__('Rusticité effective', __FILE__), 'numeric', '°C', 1], 'alerte' => [__('Alerte froid', __FILE__), 'binary', '', 1],
      'raison' => [__('Raison de l’alerte', __FILE__), 'string', '', 1], 'engrais' => [__('Engrais de la semaine', __FILE__), 'string', '', 1],
      'greffe' => [__('Greffe à contrôler', __FILE__), 'string', '', 0], // texte > 127 caractères (question + température) : refusé par history.value
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
    foreach (journalcultures_calc::migrerPulpe(['id_pulpe' => $this->getConfiguration('id_pulpe'), 'id_pulpe_aspect' => $this->getConfiguration('id_pulpe_aspect')]) as $k => $v) $this->setConfiguration($k, $v); // 0.27, avant le contrôle des codes (mise à jour, import)
    foreach (['greffe_greffon', 'greffe_avant'] as $k) if (is_array($x = $this->getConfiguration($k))) $this->setConfiguration($k, journalcultures_calc::migrerPulpe($x));
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
      if (is_object($c = $this->getCmd('info', $k))) { if (!$hist && $c->getIsHistorized()) { $c->setIsHistorized(0); $c->save(); } continue; } // commande créée historisée par une version précédente
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
    if ($this->type() == 'plante' && is_object($c = $this->_ancColl) && $c->getId() != $this->getId()) try { // changement de collection (« Déplacer… », page du plugin, greffe)
      self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => 'Déplacement',
        'note' => __('Collection', __FILE__) . ' : ' . $c->getName() . ' → ' . (is_object($n = $this->collection()) ? $n->getName() : '?')]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : changement de collection non noté : ' . $e->getMessage()); }
    try {
      if ($this->getIsEnable()) $this->maj();
      elseif ($this->type() == 'plante' && is_object($c = $this->collection())) $c->maj(); // plante désactivée : retirée de la tuile tout de suite
      if (is_object($c = $this->_ancColl) && $c->getId() != $this->getId()) { $this->_ancColl = null; $c->maj(); }
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : ' . $e->getMessage()); }
  }

  public function postRemove() { // l'ancienne tuile ne doit plus lister la plante
    if (is_object($c = $this->_ancColl)) try { $c->maj(); } catch (Throwable $e) { log::add('journalcultures', 'error', $e->getMessage()); }
  }

  private static function desinstallation() { return in_array('update', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'class'), true); } // suppression faite par la désinstallation du plugin
  public function refusSuppression() { // message du refus, '' si la suppression peut se faire (lu par le selftest sans rien supprimer)
    // ponytail: la désinstallation supprime tout, dans n'importe quel ordre : pas de refus là
    return $this->type() == 'collection' && $this->plantes() && !self::desinstallation()
      ? sprintf(__('La collection %s contient encore %d plante(s) : les déplacer ou les supprimer d\'abord', __FILE__), $this->getName(), count($this->plantes())) : '';
  }
  public function remove() { // avant le core, qui supprime d'abord les commandes : une collection refusée garde les siennes
    if (($m = $this->refusSuppression()) !== '') throw new Exception($m);
    return parent::remove();
  }

  public function preRemove() { // photos et entrées du journal de la plante
    if ($this->type() != 'plante') return;
    $this->_ancColl = $this->collection();
    if (!self::desinstallation()) DB::Prepare('DELETE FROM ' . self::TABLE . ' WHERE plante_id = ?', [$this->getId()], DB::FETCH_TYPE_ROW); // désinstallation : table gardée (install.php), entrées orphelines mais lisibles en base (nom de la plante) ; pour réinstaller : exporter avant
    $d = self::dossier($this->getId());
    if (is_dir($d)) { array_map('unlink', glob($d . '/*') ?: []); @rmdir($d); }
    array_map('unlink', glob(jeedom::getTmpFolder('journalcultures') . '/mini/' . (int)$this->getId() . '_*') ?: []); // vignettes
  }

  /* ---------- mesures, alerte, engrais ---------- */

  private static function info($s) { // '#id#' ou '#[Objet][Équipement][Commande]#' → commande info, null sinon
    if (!preg_match('/^#(\d+)#$/', trim(cmd::humanReadableToCmd((string)$s)), $m)) return null;
    $c = cmd::byId($m[1]);
    return is_object($c) && $c->getType() == 'info' ? $c : null;
  }

  private static function lire($s, $h = 12) { // valeur numérique, null si absente ou muette depuis $h heures
    if (!($c = self::info($s))) return null;
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
    [$reff, $est, $jeu] = $this->reff((array)$this->getConfiguration()); $dugf = false;
    if (is_array($gf = $this->getConfiguration('greffe_greffon'))) { // greffon en attente : alerte réglée sur le plus fragile des deux (choix du plugin)
      $g = $this->reff($gf);
      if ($g[0] !== null && ($reff === null || $g[0] > $reff)) { [$reff, $est, $jeu] = $g; $dugf = true; }
    }
    $al = journalcultures_calc::alerte($reff, $t, $tmin, self::reglage('marge', 3));
    $sans = trim($s[0]) === '' && trim($s[2]) === ''; // emplacement sans sonde (Maison…)
    if ($inconnu) { // on ne sait plus où elle est : on garde l'alerte, on le dit
      $al = ['alerte' => null, 'raison' => __('emplacement inconnu', __FILE__)];
      if ($this->valeur('raison') !== $al['raison']) log::add('journalcultures', 'warning', $this->getHumanName() . ' : ' . sprintf(__('emplacement « %s » absent de la configuration', __FILE__), $e));
    } elseif ($reff === null || $sans) $al = ['alerte' => 0, 'raison' => '']; // rien à surveiller : pas d'alerte figée
    elseif ($al === null) { // sondes réglées mais muettes : on garde l'alerte, on le dit
      $al = ['alerte' => null, 'raison' => __('sonde muette', __FILE__)];
      if ($this->valeur('raison') !== $al['raison']) log::add('journalcultures', 'warning', $this->getHumanName() . ' : ' . sprintf(__('sonde de « %s » muette', __FILE__), $e));
    }
    $hiv = $this->getConfiguration('hivernage') == 1; $chauffe = !empty($s[3]); $sem = (int)date('W');
    if ($hiv && $chauffe && ($al['raison'] ?? '') === '' && in_array($sem, journalcultures_calc::semaines(self::sud() ? journalcultures_calc::decaler(self::HIVER, 26) : self::HIVER)))
      $al['raison'] = __('repos hivernal : emplacement chauffé, à mettre au frais', __FILE__); // ponytail: d'après la case de l'emplacement, pas la mesure (une serre froide au soleil n'est pas « au chaud »)
    $eng = []; $pou = null;
    foreach (journalcultures_calc::engraisDus($this->getConfiguration('engrais'), $sem, $chauffe && !$hiv && !$this->jeuneHiver()) as [$lib, $k, $hors, $prod]) {
      if ($pou === null && ($pou = journalcultures_calc::pousse($this->moyenne7(), self::seuil())) === null) $pou = 0; // 0 : pas de sonde historisée
      if ($pou === false || (($k > 0 || $hors) && $pou !== true)) continue; // trop froid : l'apport attend ; sans sonde, seule la semaine prévue compte (et rien hors période d'apport)
      $notes = DB::Prepare('SELECT note FROM ' . self::TABLE . ' WHERE plante_id = ? AND action = ? AND date >= ?', [$this->getId(), 'Engrais', date('Y-m-d', strtotime("monday this week -$k weeks"))], DB::FETCH_TYPE_ALL) ?: [];
      if (!array_filter($notes, function ($n) use ($prod) { return trim((string)$n['note']) === '' || preg_match('/(?<![\p{L}\d])' . preg_quote($prod, '/') . '(?![\p{L}\d])/iu', $n['note']); })) $eng[] = $lib; // ce produit (mot entier) ou un apport sans note, depuis la semaine prévue
    }
    while (count($eng) > 1 && mb_strlen(implode(' ; ', $eng)) > 120) array_pop($eng); // coupé au produit près : la note de la coche garde des noms entiers
    return ['empl' => $e, 't' => $t, 'h' => $h, 'tmin' => $tmin, 'reff' => $reff, 'jeunesse' => $jeu, 'du_greffon' => $dugf, 'estimee' => $est, 'alerte' => $al['alerte'] ?? null, 'raison' => $al['raison'] ?? '',
      'engrais' => mb_substr(implode(' ; ', $eng), 0, 120), 'greffe' => ($g = $this->suiviGreffe()) ? implode(' ; ', array_filter([$g['du'] ? $g['question'] : '', $g['temp']])) : ''];
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
    foreach (['emplacement' => $s['empl'], 'temperature' => $s['t'], 'rusticite' => $s['reff'], 'alerte' => $s['alerte'], 'raison' => $s['raison'], 'engrais' => $s['engrais'], 'greffe' => $s['greffe']] as $k => $v) {
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

  public static function releves($id) { // toutes les mesures d'une plante, plus anciennes d'abord (onglet Mesures ; le journal est limité)
    $r = DB::Prepare('SELECT date, valeurs FROM ' . self::TABLE . ' WHERE plante_id = ? AND action = ? AND valeurs IS NOT NULL ORDER BY date, id', [(int)$id, 'Mesure'], DB::FETCH_TYPE_ALL) ?: [];
    foreach ($r as &$x) $x['valeurs'] = json_decode((string)$x['valeurs'], true);
    unset($x);
    return array_values(array_filter($r, function ($x) { return is_array($x['valeurs']) && $x['valeurs']; }));
  }

  public static function supprimerEntree($id) { // → plante concernée (pour la remettre à jour) ou null
    $x = DB::Prepare('SELECT plante_id FROM ' . self::TABLE . ' WHERE id = ?', [(int)$id], DB::FETCH_TYPE_ROW);
    if (!$x) throw new Exception(__('Entrée introuvable', __FILE__));
    DB::Prepare('DELETE FROM ' . self::TABLE . ' WHERE id = ?', [(int)$id], DB::FETCH_TYPE_ROW);
    return $x['plante_id'] ? self::byId($x['plante_id']) : null;
  }

  /* ---------- botanique, photos, Pl@ntNet ---------- */

  private static function gbif($nom) { // GBIF species/match (sans clé) : nom retenu, famille, statut
    $h = curl_init('https://api.gbif.org/v1/species/match?' . http_build_query(['name' => $nom, 'kingdom' => 'Plantae'])); // curl : certificat vérifié (com_http ne le garantit pas)
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $t = curl_exec($h); $code = curl_getinfo($h, CURLINFO_HTTP_CODE); curl_close($h);
    if ($t === false || $code != 200) throw new Exception('GBIF injoignable (' . $code . ')');
    $r = json_decode($t, true); // 3 s : la fiche attend la réponse
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

  /* Vignette JPEG de 400 px au plus (tuile, fiche) gardée dans le dossier temporaire de Jeedom, refaite si la photo est plus récente ;
     l'original si GD ne sait pas la lire. Orientation EXIF appliquée (rotations seulement : les photos miroir sont rares). */
  public function mini($nom) {
    $f = $this->photo($nom);
    $m = jeedom::getTmpFolder('journalcultures') . '/mini/' . $this->getId() . '_' . pathinfo($nom, PATHINFO_FILENAME) . '.jpg';
    if (is_file($m) && filemtime($m) >= filemtime($f)) return $m;
    $e = pathinfo($f, PATHINFO_EXTENSION); $t = @getimagesize($f);
    $lire = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$e];
    $lim = trim(ini_get('memory_limit')); $lim = $lim == -1 ? INF : (int)$lim * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr($lim, -1))] ?? 1);
    if (!$t || $t[0] * $t[1] * 6 > $lim - memory_get_usage() || !function_exists($lire) || !($i = @$lire($f))) return $f; // GD : ≈ 5 octets par pixel ; trop gros = l'original (une erreur de mémoire ne se rattrape pas)
    $w = imagesx($i); $h = imagesy($i); $k = min(1, 400 / max($w, $h));
    $v = imagecreatetruecolor($W = max(1, (int)round($w * $k)), $H = max(1, (int)round($h * $k)));
    imagefill($v, 0, 0, imagecolorallocate($v, 255, 255, 255)); // png transparent : fond blanc
    imagecopyresampled($v, $i, 0, 0, 0, 0, $W, $H, $w, $h); imagedestroy($i);
    $o = $e == 'jpg' && function_exists('exif_read_data') ? (int)((@exif_read_data($f) ?: [])['Orientation'] ?? 1) : 1;
    if (isset([3 => 1, 6 => 1, 8 => 1][$o]) && ($r = imagerotate($v, [3 => 180, 6 => -90, 8 => 90][$o], 0))) { imagedestroy($v); $v = $r; } // tournée une fois réduite
    if (!is_dir(dirname($m))) @mkdir(dirname($m), 0775, true);
    $x = $m . '.' . getmypid(); // propre à la requête : deux vignettes faites en même temps ne s'écrasent pas
    $ok = imagejpeg($v, $x, 80) && rename($x, $m); imagedestroy($v); @unlink($x);
    return $ok ? $m : $f;
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

  /* Pl@ntNet (clé de l'utilisateur, 500 identifications par jour) sur 1 à 5 photos à la fois, chacune avec son organe : 5 meilleures espèces.
     $noms : photos rangées de $p ; $fichiers : photos envoyées ($_FILES), pas encore rangées ; $organes dans cet ordre (noms puis fichiers). */
  public static function identifierPhotos(?journalcultures $p, array $noms, array $fichiers, array $organes) {
    $cle = self::cle();
    if ($cle === '') throw new Exception(__('Clé Pl@ntNet absente : la saisir dans la configuration du plugin', __FILE__));
    $im = [];
    foreach ($noms as $n) { if (!$p) throw new Exception(__('Photo introuvable', __FILE__)); $im[] = [$p->photo($n), pathinfo($n, PATHINFO_EXTENSION)]; }
    foreach ($fichiers as $f) $im[] = [$f['tmp_name'], self::image($f)]; // fichier envoyé : nom temporaire sans extension
    if (!$im || count($im) > 5) throw new Exception(__('Choisir de 1 à 5 photos', __FILE__));
    if (array_sum(array_map(function ($x) { return filesize($x[0]); }, $im)) > 40e6) throw new Exception(__('Photos trop lourdes pour Pl@ntNet (40 Mo en tout au plus)', __FILE__)); // tout passe en mémoire
    // multipart écrit à la main : Pl@ntNet attend « images » et « organs » répétés, ce qu'un tableau CURLOPT_POSTFIELDS ne sait pas faire
    $b = 'jdc' . bin2hex(random_bytes(12)); $corps = '';
    foreach ($im as $i => [$chemin, $ext]) {
      $o = $organes[$i] ?? 'auto'; if (!in_array($o, ['auto', 'leaf', 'flower', 'fruit', 'bark'], true)) $o = 'auto';
      $corps .= "--$b\r\nContent-Disposition: form-data; name=\"organs\"\r\n\r\n$o\r\n--$b\r\nContent-Disposition: form-data; name=\"images\"; filename=\"photo$i.$ext\"\r\n" .
        'Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] . "\r\n\r\n" . file_get_contents($chemin) . "\r\n";
    }
    $ch = curl_init('https://my-api.plantnet.org/v2/identify/all?' . http_build_query(['api-key' => $cle, 'lang' => 'fr']));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corps . "--$b--\r\n", CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data; boundary=' . $b],
      CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
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
  /* « Greffer… » ($pg = porte-greffe) : la plante reste le porte-greffe ; le greffon (nom, collection, fiche) attend dans greffe_greffon.
     La plante n'en prend l'identité qu'au débourrement d'un œil, confirmé au contrôle (controlerGreffe) ; greffe morte : il reste porte-greffe.
     Greffe datée de plus de 60 jours (saisie après coup) : un œil est considéré comme ayant déjà débourré, l'identité change tout de suite.
     Greffe en pont : réparation (ponter), ni nom, ni collection, ni fiche. */
  public static function creer(journalcultures $coll, array $in, ?journalcultures $pg = null) {
    if ($pg && ($in['type_greffe'] ?? '') === 'pont') return $pg->ponter($in['date_mult'] ?? '', journalcultures_calc::texte($in['greffon'] ?? '', 120));
    $nom = journalcultures_calc::texte($in['nom'] ?? '', 120);
    if ($nom === '') throw new Exception(__('Nom de la plante obligatoire', __FILE__));
    if ($pg) $in['multiplication'] = 'greffe'; // avant nouvelle() : sinon le type de greffe est écarté
    $c = journalcultures_calc::nouvelle($in, array_map('strval', array_keys(self::emplacements()))); // contrôlé avant toute écriture ; strval : un nom « 2 » devient une clé entière
    self::nomLibre($coll->getObject_id(), $nom, $pg ? $pg->getId() : null);
    if ($pg) return $pg->greffer($coll, $nom, $c, journalcultures_calc::texte($in['greffon'] ?? '', 120));
    $p = new journalcultures();
    $p->setEqType_name('journalcultures'); $p->setIsEnable(1); $p->setIsVisible(0); $p->setConfiguration('type', 'plante');
    $p->setName($nom); $p->setObject_id($coll->getObject_id()); $p->setConfiguration('collection', $coll->getId());
    foreach ($c as $k => $v) $p->setConfiguration($k, $v);
    try { $jg = journalcultures_calc::date($c['date_mult'] ?? ''); } catch (Exception $x) { $jg = null; }
    if (($c['multiplication'] ?? '') == 'greffe' && $jg && $jg >= date('Y-m-d', strtotime('-60 days'))) { // plante achetée ou greffée ailleurs, greffe récente : contrôles du bourgeon (déjà la plante greffée le greffon : pas de bascule)
      $p->setConfiguration('greffe_statut', 'attente');
      $p->setConfiguration('greffe_controle', date('Y-m-d', strtotime($jg . ' +' . journalcultures_calc::GREFFE_JOURS['attente'] . ' days')));
    }
    $p->sauver(); // preSave : identification, GBIF si nom botanique ; postSave : commandes, tuile de la collection
    return (int)$p->getId();
  }

  private static function nomLibre($objet, $nom, $sauf = null) { // tous plugins : nom unique par objet
    foreach (eqLogic::byObjectId($objet, false) as $e) if (mb_strtolower($e->getName()) == mb_strtolower($nom) && $e->getId() != $sauf)
      throw new Exception(sprintf(__('Un équipement de cet objet porte déjà le nom « %s »', __FILE__), $nom));
  }
  private function sauver() {
    try { $this->save(); } catch (Exception $x) { // ponytail: l'index unique du core ignore aussi les accents (« Cedrat » = « Cédrat »)
      if (strpos($x->getMessage(), 'Duplicate') !== false) throw new Exception(sprintf(__('Un équipement de cet objet porte déjà le nom « %s »', __FILE__), $this->getName()));
      throw $x;
    }
  }

  private function greffer(journalcultures $coll, $nom, array $c, $source) {
    if (!$this->getIsEnable()) throw new Exception(__('Plante désactivée', __FILE__));
    $s = (string)$this->getConfiguration('greffe_statut'); $ga = $this->getConfiguration('greffe_greffon');
    if ($s == 'pousse') throw new Exception(__('Greffe en train de pousser : répondre d\'abord à son contrôle (réussite ou échec)', __FILE__)); // sinon le vrai porte-greffe serait perdu
    if ($this->pontSuivi()) throw new Exception(__('Greffe en pont en suivi : répondre d\'abord à son contrôle', __FILE__));
    $this->setConfiguration('greffe_pont', ''); // réparation terminée : le suivi devient celui de cette greffe
    try { $c['date_mult'] = journalcultures_calc::date($c['date_mult'] ?? ''); } catch (Exception $x) { $c['date_mult'] = date('Y-m-d'); } // partielle (2026-05) ou absente : aujourd'hui (suivi, jeunesse, engrais du jeune plant)
    $gf = ['nom' => $nom, 'collection' => $coll->getId()] + array_intersect_key($c, array_flip(self::identite()));
    if (trim($gf['porte_greffe'] ?? '') === '') $gf['porte_greffe'] = $this->getName();
    $pg = $this->getName(); $recente = $c['date_mult'] >= date('Y-m-d', strtotime('-60 days'));
    $this->setConfiguration('greffe_avant', null); // greffe précédente : son retour en arrière n'a plus lieu d'être
    $this->setConfiguration('greffe_greffon', $gf);
    $garde = is_array($ga) && ($ga['date_mult'] ?? '') === $c['date_mult'] && in_array($s, ['attente', 'probable', 'dormant'], true); // greffon en attente corrigé (même date) : suivi gardé
    if ($garde) { $this->versGreffes();
    } elseif ($recente) { $this->versGreffes();
      $this->setConfiguration('greffe_rep', ''); // contrôles du bourgeon ; la plante reste le porte-greffe d'ici là
      $this->setConfiguration('greffe_statut', 'attente');
      $this->setConfiguration('greffe_controle', date('Y-m-d', strtotime($c['date_mult'] . ' +' . journalcultures_calc::GREFFE_JOURS['attente'] . ' days')));
    } else {
      $this->setConfiguration('greffe_statut', ''); $this->setConfiguration('greffe_controle', ''); $this->setConfiguration('greffe_rep', '');
      $this->basculer(); $this->setConfiguration('greffe_avant', null); // pas de contrôle à venir : retour en arrière inutile
    }
    $this->sauver();
    try { // greffe déjà enregistrée : une erreur ici ne doit pas la faire refaire
      self::inserer(['date' => $c['date_mult'], 'plante_id' => $this->getId(), 'plante' => $this->getName(),
        'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => 'Greffe', 'note' => sprintf(__('%s greffé sur %s (%s)', __FILE__),
          implode(' ', array_filter([$c['bota'] ?? '', $c['cultivar'] ?? ''])) ?: '« ' . $nom . ' »', $pg,
          journalcultures_calc::GREFFES[$c['type_greffe'] ?? ''] ?? __('type de greffe non précisé', __FILE__))
          . ($source !== '' ? ' ; ' . sprintf(__('greffon pris sur %s', __FILE__), $source) : '')
          . ' ; ' . ($garde ? __('greffon corrigé, suivi inchangé', __FILE__) : '') . ($garde ? '' : ($recente ? sprintf(__('%s reste le porte-greffe ; porte-greffe et greffon deviendront « %s » au débourrement d\'un œil', __FILE__), $pg, $nom)
            : sprintf(__('greffe ancienne, un œil a déjà débourré : porte-greffe et greffon deviennent « %s »', __FILE__), $this->getName())))]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : greffe non notée au journal : ' . $e->getMessage()); }
    return (int)$this->getId();
  }

  /* Greffe en pont : réparation d'une écorce blessée (rongeur, gel, gommose grattée). La plante garde son nom, sa fiche, sa collection et sa multiplication ;
     greffe_pont = date de la réparation (type et date du suivi), contrôles à J+21 et J+42 si elle est récente, rien d'autre. */
  private function pontSuivi() { return (string)$this->getConfiguration('greffe_pont') !== '' && in_array((string)$this->getConfiguration('greffe_statut'), ['attente', 'probable', 'dormant', 'pousse'], true); }
  private function ponter($date, $source) {
    if (!$this->getIsEnable()) throw new Exception(__('Plante désactivée', __FILE__));
    if (!$this->pontSuivi() && in_array((string)$this->getConfiguration('greffe_statut'), ['attente', 'probable', 'dormant', 'pousse'], true))
      throw new Exception(__('Greffe en suivi : répondre d\'abord à son contrôle', __FILE__)); // sinon le greffon en attente serait perdu
    try { $d = journalcultures_calc::date($date); } catch (Exception $x) { $d = date('Y-m-d'); }
    if ($d > date('Y-m-d')) throw new Exception(__('Date du pont dans le futur', __FILE__));
    $recente = $d >= date('Y-m-d', strtotime('-60 days'));
    $this->setConfiguration('greffe_pont', $recente ? $d : ''); $this->setConfiguration('greffe_rep', '');
    $this->setConfiguration('greffe_statut', $recente ? 'attente' : '');
    $this->setConfiguration('greffe_controle', $recente ? date('Y-m-d', strtotime($d . ' +' . journalcultures_calc::GREFFE_JOURS['attente'] . ' days')) : '');
    self::$_sansGbif = true; try { $this->sauver(); } finally { self::$_sansGbif = false; } // identité inchangée
    try {
      self::inserer(['date' => $d, 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => 'Greffe',
        'note' => __('Greffe en pont (réparation de l\'écorce) ; la plante garde son nom et sa fiche', __FILE__) . ($source !== '' ? ' ; ' . sprintf(__('greffons pris sur %s', __FILE__), $source) : '')
          . ($recente ? ' ; ' . __('contrôles à J+21 et J+42', __FILE__) : '')]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : greffe en pont non notée au journal : ' . $e->getMessage()); }
    return (int)$this->getId();
  }

  /* ---------- export / import : fiches des plantes, leur journal et le catalogue des produits ---------- */
  // ponytail: ni photos (trop lourdes pour un JSON), ni historiques des sondes, ni entrées libres des collections ; à ajouter si une box doit être reprise à l'identique
  const EXPORT_SAUF = ['collection', 'type', 'ident', 'greffe_coll']; // id propres à cette box ; ident recalculé à l'enregistrement
  public static function exporter() {
    $noms = []; $pl = [];
    foreach (self::byType('journalcultures') as $e) if ($e->type() == 'collection') $noms[$e->getId()] = [$e->getName(), is_object($o = $e->getObject()) ? $o->getName() : ''];
    foreach (self::byType('journalcultures') as $p) if ($p->type() == 'plante') {
      $c = array_diff_key((array)$p->getConfiguration(), array_flip(self::EXPORT_SAUF));
      foreach (['greffe_avant', 'greffe_greffon'] as $k) if (is_array($c[$k] ?? null)) unset($c[$k]['collection']); // id propre à cette box : au retour, collection actuelle de la plante
      $j = DB::Prepare('SELECT date, lieu, action, cat, note, origine, valeurs FROM ' . self::TABLE . ' WHERE plante_id = ? ORDER BY date, id', [$p->getId()], DB::FETCH_TYPE_ALL) ?: [];
      foreach ($j as &$x) { $x['valeurs'] = $x['valeurs'] ? json_decode($x['valeurs'], true) : null; $x = array_filter($x, function ($v) { return $v !== null && $v !== ''; }); }
      unset($x);
      [$cn, $on] = $noms[$p->getConfiguration('collection')] ?? ['', ''];
      $pl[] = ['nom' => $p->getName(), 'collection' => $cn, 'objet' => $on, 'actif' => (int)$p->getIsEnable(), 'configuration' => $c, 'journal' => $j];
    }
    return ['format' => 'journalcultures', 'version' => 1, 'date' => date('c'), 'produits' => (string)config::byKey('produits', 'journalcultures', ''), 'plantes' => $pl];
  }
  /* Ajoute ce qui manque, ne remplace rien : produits absents (même nom) ajoutés au catalogue ; plantes absentes de l'objet de leur collection
     créées (collection de même nom, sinon $defaut) avec leur journal ; plante déjà présente laissée telle quelle. → compte rendu */
  public static function importer(array $d, journalcultures $defaut) {
    if (($d['format'] ?? '') !== 'journalcultures' || !is_array($d['plantes'] ?? null)) throw new Exception(__('Fichier non reconnu : export du Journal de cultures attendu', __FILE__));
    if (count($d['plantes']) > 1000) throw new Exception(__('Trop de plantes dans le fichier', __FILE__));
    @set_time_limit(0); // coupé au milieu, une plante créée garderait un journal incomplet (relancer l'import la dit « déjà présente »)
    $sans4 = function ($v) { if (is_string($v)) return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $v) ?? ''; if (is_array($v)) array_walk_recursive($v, function (&$x) { if (is_string($x)) $x = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $x) ?? ''; }); return $v; }; // configuration en utf8mb3
    $r = ['produits' => 0, 'plantes' => 0, 'entrees' => 0, 'deja' => [], 'erreurs' => []];
    $txt = (string)config::byKey('produits', 'journalcultures', '');
    $ont = array_map(function ($p) { return mb_strtolower($p['nom']); }, journalcultures_calc::produits($txt));
    foreach (preg_split('/\R/u', (string)($d['produits'] ?? '')) ?: [] as $l) {
      $l = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $l) ?? ''); $q = journalcultures_calc::produits($l); // configuration en utf8mb3
      if (!$q || in_array($k = mb_strtolower($q[0]['nom']), $ont, true)) continue;
      $txt = rtrim($txt) . "\n" . $l; $ont[] = $k; $r['produits']++;
    }
    if ($r['produits']) config::save('produits', ltrim($txt), 'journalcultures');
    $colls = [];
    $pls = []; // plantes déjà là, quel que soit leur objet (une plante peut être rangée ailleurs que l'objet de sa collection)
    foreach (self::byType('journalcultures') as $e) if ($e->type() == 'collection') $colls[mb_strtolower($e->getName())][] = $e; elseif ($e->type() == 'plante') $pls[mb_strtolower($e->getName())] = 1; // collections homonymes possibles (objets différents)
    $gbif = self::$_sansGbif; self::$_sansGbif = true; // nom GBIF repris du fichier ; sans Internet, jusqu'à 3 s par plante
    try {
      foreach ($d['plantes'] as $x) {
        $nom = journalcultures_calc::texte($x['nom'] ?? '', 120); if ($nom === '') continue;
        try {
          $cs = $colls[mb_strtolower((string)($x['collection'] ?? ''))] ?? [];
          if (count($cs) > 1) $cs = array_values(array_filter($cs, function ($c) use ($x) { return is_object($o = $c->getObject()) && mb_strtolower($o->getName()) == mb_strtolower((string)($x['objet'] ?? '')); }));
          $co = count($cs) == 1 ? $cs[0] : $defaut; // ambigu ou absente : collection choisie
          if (isset($pls[mb_strtolower($nom)])) { $r['deja'][] = $nom; continue; }
          foreach (eqLogic::byObjectId($co->getObject_id(), false) as $e) if (mb_strtolower($e->getName()) == mb_strtolower($nom)) { $r['deja'][] = $nom; continue 2; } // tous plugins : nom unique par objet
          $p = new journalcultures();
          $p->setEqType_name('journalcultures'); $p->setName($nom); $p->setObject_id($co->getObject_id()); $p->setIsVisible(0); $p->setIsEnable(isset($x['actif']) && !$x['actif'] ? 0 : 1);
          foreach ((array)($x['configuration'] ?? []) as $k => $v) if (is_string($k) && preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $k) && !in_array($k, self::EXPORT_SAUF, true)) $p->setConfiguration($k, $sans4($v));
          $p->setConfiguration('type', 'plante'); $p->setConfiguration('collection', $co->getId());
          foreach (['greffe_avant', 'greffe_greffon'] as $k) if (is_array($v = $p->getConfiguration($k))) { unset($v['collection']); $p->setConfiguration($k, $v); } // id d'une autre box (anciens exports)
          try { $p->save(); } catch (Exception $y) { if (strpos($y->getMessage(), 'Duplicate') === false) throw $y; $r['deja'][] = $nom; continue; } // index du core : accents ignorés
          $r['plantes']++; $pls[mb_strtolower($nom)] = 1; $ko = 0; // deux fois le même nom dans le fichier : une seule plante
          foreach (array_slice((array)($x['journal'] ?? []), 0, 20000) as $j) try {
            if (!is_array($j)) throw new Exception('?');
            $v = null; // mesures relues comme une saisie (clés du catalogue, nombres), calculs refaits
            if (is_array($j['valeurs'] ?? null)) { $v = journalcultures_calc::valeurs(array_map(function ($y) { return is_array($y) ? implode(';', array_filter($y, 'is_scalar')) : (is_scalar($y) ? (string)$y : ''); }, $j['valeurs'])); $v = $v ? $v + journalcultures_calc::calculs($v) : null; }
            self::inserer(['plante_id' => $p->getId(), 'origine' => ($j['origine'] ?? '') ?: 'import', 'valeurs' => $v]
              + array_intersect_key($j, array_flip(['date', 'lieu', 'action', 'cat', 'note'])));
            $r['entrees']++;
          } catch (Throwable $y) { $ko++; }
          if ($ko) $r['erreurs'][] = $p->getName() . ' : ' . sprintf(__('%d entrée(s) du journal refusée(s)', __FILE__), $ko);
        } catch (Throwable $y) { $r['erreurs'][] = $nom . ' : ' . $y->getMessage(); }
      }
    } finally { self::$_sansGbif = $gbif; }
    return $r;
  }

  // identité d'une plante (ce qui change à la greffe) ; le sol (ce sont toujours ses racines), le pot, l'emplacement, le journal et les photos restent
  private static function identite() {
    return array_merge(array_diff(array_keys(self::FICHE), ['sol']), ['etiquette', 'gbif', 'multiplication', 'type_greffe', 'date_mult'],
      array_map(function ($k) { return 'id_' . $k; }, array_keys(journalcultures_calc::CARACTERES)));
  }
  private static function instantane(journalcultures $p) {
    $r = ['nom' => $p->getName(), 'collection' => $p->getConfiguration('collection')];
    foreach (self::identite() as $k) $r[$k] = $p->getConfiguration($k);
    return $r;
  }
  private function prendre(array $id) { // identité d'un instantané (champ absent = vidé) ; collection disparue : celle d'aujourd'hui ; sans sauvegarder
    $nom = journalcultures_calc::texte($id['nom'] ?? '', 120); if ($nom === '') $nom = $this->getName();
    $co = self::byId($id['collection'] ?? 0);
    if (!is_object($co) || $co->getEqType_name() != 'journalcultures' || $co->type() != 'collection') $co = $this->collection();
    if (is_object($co)) { self::nomLibre($co->getObject_id(), $nom, $this->getId()); $this->setObject_id($co->getObject_id()); $this->setConfiguration('collection', $co->getId()); }
    $this->setName($nom);
    foreach (self::identite() as $k) $this->setConfiguration($k, $id[$k] ?? '');
  }
  /* Collection « Greffes » : un porte-greffe y entre quand on commence une greffe (« Greffer… »), en sort quand un œil part (collection du greffon)
     ou quand la greffe meurt (collection d'origine, greffe_coll). Créée à la première greffe, dans l'objet de la collection du porte-greffe ; son id : config coll_greffes. */
  public static function collGreffes($objet) {
    $c = self::byId(config::byKey('coll_greffes', 'journalcultures', ''));
    if (is_object($c) && $c->getEqType_name() == 'journalcultures' && $c->type() == 'collection') return $c;
    $c = new journalcultures(); $c->setEqType_name('journalcultures'); $c->setIsEnable(1); $c->setIsVisible(1); $c->setObject_id($objet); $c->setConfiguration('type', 'collection');
    $c->setName(__('Greffes', __FILE__)); self::nomLibre($objet, $c->getName()); $c->sauver();
    config::save('coll_greffes', $c->getId(), 'journalcultures');
    return $c;
  }
  public function versGreffes() { // sans sauvegarder ; dans « Greffes », le champ Porte-greffe nomme le porte-greffe du greffon (ailleurs : « franc de pied » pour un porte-greffe), l'ancien est gardé dans greffe_pgori
    $g = self::collGreffes(is_object($c = $this->collection()) ? $c->getObject_id() : $this->getObject_id());
    if ($g->getId() != $this->getConfiguration('collection')) {
      self::nomLibre($g->getObject_id(), $this->getName(), $this->getId());
      $this->setConfiguration('greffe_coll', (string)$this->getConfiguration('collection'));
      $this->setConfiguration('greffe_pgori', (string)$this->getConfiguration('porte_greffe'));
      $this->setConfiguration('collection', $g->getId()); $this->setObject_id($g->getObject_id());
    }
    $gf = $this->getConfiguration('greffe_greffon');
    if (is_array($gf) && trim((string)($gf['porte_greffe'] ?? '')) !== '') $this->setConfiguration('porte_greffe', $gf['porte_greffe']);
  }
  private function horsGreffes() { // sans sauvegarder ; collection d'origine disparue : reste dans « Greffes »
    $ori = (string)$this->getConfiguration('greffe_coll'); $this->setConfiguration('greffe_coll', '');
    $po = (string)$this->getConfiguration('greffe_pgori'); $this->setConfiguration('greffe_pgori', '');
    if ((string)$this->getConfiguration('collection') !== (string)config::byKey('coll_greffes', 'journalcultures', '')) return;
    $this->setConfiguration('porte_greffe', $po);
    $co = self::byId($ori);
    if (!is_object($co) || $co->getEqType_name() != 'journalcultures' || $co->type() != 'collection') return;
    self::nomLibre($co->getObject_id(), $this->getName(), $this->getId());
    $this->setConfiguration('collection', $co->getId()); $this->setObject_id($co->getObject_id());
  }
  private function basculer() { // un œil du greffon a débourré : porte-greffe et greffon deviennent la nouvelle plante ; l'identité du porte-greffe est gardée (pousse qui meurt)
    $gf = $this->getConfiguration('greffe_greffon'); $av = self::instantane($this); $g = config::byKey('coll_greffes', 'journalcultures', ''); $ori = (string)$this->getConfiguration('greffe_coll');
    if ($ori !== '' && (string)$av['collection'] === (string)$g) { $av['collection'] = $ori; $av['porte_greffe'] = (string)$this->getConfiguration('greffe_pgori'); } // pousse morte plus tard : retour dans la collection d'origine, avec son champ Porte-greffe
    if (is_array($gf) && $ori !== '' && (string)($gf['collection'] ?? '') === (string)$g) $gf['collection'] = $ori; // greffon rangé dans « Greffes » : la plante n'y reste pas
    $this->setConfiguration('greffe_coll', ''); $this->setConfiguration('greffe_pgori', '');
    $this->prendre(is_array($gf) ? $gf : []);
    $this->setConfiguration('greffe_avant', $av); $this->setConfiguration('greffe_greffon', null);
  }
  private function greffeVal($k) { // type et date de la greffe : du greffon tant qu'il attend, sinon de la plante (devenue la plante greffée) ; type « pont » pendant une réparation (sa date : greffe_pont)
    $gf = $this->getConfiguration('greffe_greffon');
    if ($k == 'type_greffe' && !is_array($gf) && (string)$this->getConfiguration('greffe_pont') !== '' && (string)$this->getConfiguration('greffe_statut') !== '') return 'pont';
    return (string)(is_array($gf) ? ($gf[$k] ?? '') : $this->getConfiguration($k));
  }

  /* 0.26 : avant, « Greffer… » changeait l'identité tout de suite. Greffe pas encore partie (attente, probable, dormant) : remise en porte-greffe,
     greffon en attente ; greffe échouée : remise en porte-greffe. → true si la plante a changé */
  public function migrerGreffe() {
    $s = (string)$this->getConfiguration('greffe_statut'); $av = $this->getConfiguration('greffe_avant'); $gp = $this->getConfiguration('greffe_greffon');
    if ($this->type() == 'plante' && is_array($gp) && ($gp['type_greffe'] ?? '') === 'pont') { // 0.27 : pont en attente (0.26) → réparation, la plante garde son identité, suivi gardé
      try { $d = journalcultures_calc::date($gp['date_mult'] ?? ''); } catch (Exception $x) { $d = date('Y-m-d'); }
      $this->setConfiguration('greffe_pont', $d); $this->setConfiguration('greffe_greffon', null);
      self::$_sansGbif = true; try { $this->sauver(); } finally { self::$_sansGbif = false; }
      try {
        self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => 'Greffe',
          'note' => __('Mise à jour du plugin : la greffe en pont devient une réparation de l\'écorce ; la plante garde son nom et sa fiche', __FILE__)]);
      } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : migration du pont non notée : ' . $e->getMessage()); }
      return true;
    }
    if ($this->type() == 'plante' && !is_array($this->getConfiguration('greffe_greffon')) && (($s == 'echouee' && !is_array($av)) || (in_array($s, ['', 'reprise'], true) && $av !== '' && $av !== null))) {
      if ($s == 'echouee') foreach (['greffe_statut', 'greffe_controle', 'greffe_rep'] as $k) $this->setConfiguration($k, ''); // échec sans porte-greffe connu : plus de suivi
      $this->setConfiguration('greffe_avant', null); // greffe terminée : retour en arrière inutile
      self::$_sansGbif = true; try { $this->sauver(); } finally { self::$_sansGbif = false; }
      return true;
    }
    if ($this->type() != 'plante' || !is_array($av) || is_array($this->getConfiguration('greffe_greffon')) || !in_array($s, ['attente', 'probable', 'dormant', 'echouee'], true)) return false;
    $gf = self::instantane($this); $n = $this->getName();
    $this->prendre($av); $this->setConfiguration('greffe_avant', null);
    if ($s == 'echouee') foreach (['greffe_statut', 'greffe_controle', 'greffe_rep'] as $k) $this->setConfiguration($k, '');
    else $this->setConfiguration('greffe_greffon', $gf);
    $this->sauver();
    try {
      self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => 'Greffe',
        'note' => $s == 'echouee' ? sprintf(__('Greffe échouée : %s redevient le porte-greffe « %s »', __FILE__), $n, $this->getName())
          : sprintf(__('Mise à jour du plugin : « %s » redevient le porte-greffe « %s » ; porte-greffe et greffon deviendront « %s » au débourrement d\'un œil', __FILE__), $n, $this->getName(), $n)]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : migration de la greffe non notée : ' . $e->getMessage()); }
    return true;
  }

  /* Géographie : hémisphère d'après la latitude de Jeedom (Réglages → Système → Configuration), pays pour les statuts sanitaires,
     température de l'emplacement pour la saison de pousse */
  private static function sud() { return (float)config::byKey('info::latitude', 'core', 0) < 0; }
  private static function pays() { return strtoupper(trim((string)config::byKey('info::stateCode', 'core', ''))); }
  private static function seuil() { return self::reglage('pousse', journalcultures_calc::POUSSE); }
  private static $_moy7 = [];
  private function moyenne7() { // moyenne sur 7 jours de la sonde de l'emplacement (des mesures, pas pondérée par le temps), null sans sonde historisée, false si elle est muette depuis 7 jours
    if (!($i = self::info((self::emplacements()[(string)$this->getConfiguration('emplacement')] ?? [''])[0])) || !$i->getIsHistorized()) return null;
    if (!array_key_exists($i->getId(), self::$_moy7)) { // une requête par emplacement et par exécution, pas par plante
      $st = history::getStatistique($i->getId(), date('Y-m-d H:i:s', time() - 7 * 86400), date('Y-m-d H:i:s'));
      self::$_moy7[$i->getId()] = is_numeric($st['avg'] ?? null) && ($st['count'] ?? 0) > 0 ? round((float)$st['avg'], 1) : false; // muette : on attend, l'apport n'est pas annoncé au jugé
    }
    return self::$_moy7[$i->getId()];
  }
  public static function santeCat() { // hors de France, les statuts propres à la France deviennent « à vérifier pour ce pays »
    $p = self::pays(); $fr = $p === '' || in_array($p, ['FR', 'FRA', 'FRANCE'], true); // pays non renseigné : comme la France (notice)
    return array_map(function ($x) use ($fr) { return !$fr && !empty($x['regional']) ? ['statut' => 'pays', 'quar' => $x['statut'] == 'quarantaine'] + $x : $x; }, journalcultures_calc::SANTE);
  }
  public static function geo() { return ['sud' => self::sud(), 'pays' => self::pays()]; }

  public function enGreffe() { // étiquette de la tuile : greffe en cours (ni réussie ni échouée) → statut + texte, sinon null
    $s = (string)$this->getConfiguration('greffe_statut'); $gf = $this->getConfiguration('greffe_greffon');
    return in_array($s, ['attente', 'probable', 'dormant', 'pousse']) ? ['s' => $s, 't' => __('Greffe', __FILE__) . ' : ' . journalcultures_calc::GREFFE_STATUTS[$s] . (is_array($gf) && ($gf['nom'] ?? '') !== '' ? ' — ' . $gf['nom'] : '')] : null;
  }

  public function suiviGreffe() { // null si la plante n'a pas de greffe suivie
    $s = (string)$this->getConfiguration('greffe_statut');
    if (!isset(journalcultures_calc::GREFFE_STATUTS[$s])) return null;
    $c = (string)$this->getConfiguration('greffe_controle'); $gf = $this->getConfiguration('greffe_greffon'); $type = $this->greffeVal('type_greffe'); $temp = '';
    if ($s != 'reprise' && $s != 'echouee' && ($i = self::info((self::emplacements()[(string)$this->getConfiguration('emplacement')] ?? [''])[0]))) {
      $st = history::getStatistique($i->getId(), date('Y-m-d H:i:s', time() - 86400), date('Y-m-d H:i:s')); // ponytail: sonde non historisée → pas d'avis
      $v = function ($k) use ($st) { return is_numeric($st[$k] ?? null) && ($st['count'] ?? 0) > 0 ? (float)$st[$k] : null; };
      $temp = $s == 'dormant' || $s == 'pousse' ? journalcultures_calc::greffeGel($v('min')) // soudure faite : seulement le gel
        : journalcultures_calc::greffeTemp($v('avg'), self::reglage('greffe_tmin', 21), self::reglage('greffe_tmax', 32)); // la soudure a-t-elle assez chaud ?
    }
    $du = $c !== '' && ($c <= date('Y-m-d') // œil dormant : contrôle avancé (8 semaines au plus) si l'emplacement est déjà au-dessus du zéro de végétation
      || ($s == 'dormant' && $c <= date('Y-m-d', strtotime('+56 days')) && (string)$this->getConfiguration('greffe_rep') <= date('Y-m-d', strtotime('-28 days'))
        && journalcultures_calc::pousse($this->moyenne7(), self::seuil()))); // pas avant 4 semaines après la réponse (choix du plugin)
    $q = journalcultures_calc::greffeQuestion($s, $type);
    $pont = $type == 'pont'; // pont : pas d'œil à faire dormir ni de pousse attendue
    if ($s == 'probable' && !$pont && $q !== '' && in_array((int)date('W'), journalcultures_calc::semaines(self::sud() ? journalcultures_calc::decaler(self::HIVER, 26) : self::HIVER)))
      $q .= ' — ' . __('en hiver : « Au printemps », au frais hors gel, sans engrais ; le forcer maintenant demande chaleur et lumière (aucune source sur ce cas)', __FILE__); // soudure faite (J+21 confirmé) ; avant, la greffe a besoin de chaleur pour souder ; agrumes arrêtés sous ≈ 13 °C, pas d'engrais l'hiver (2 sources)
    return ['statut' => $s, 'texte' => ($pont ? __('réparation en pont', __FILE__) . ' : ' : '') . journalcultures_calc::GREFFE_STATUTS[$s], 'controle' => $c, 'du' => $du, 'question' => $q, 'printemps' => !$pont && $s == 'probable',
      'greffon' => is_array($gf) ? (string)($gf['nom'] ?? '') : '', 'pont' => $pont, 'temp' => $temp]; // greffon : nom que prendra la plante au débourrement d'un œil
  }

  /* Réponse au contrôle de greffe → nouveau statut, noté au journal. Un œil a débourré (pousse) : porte-greffe et greffon deviennent la nouvelle plante.
     Greffe morte : avant le débourrement, la plante reste le porte-greffe ; après (la pousse meurt), elle le redevient. Pont (réparation) : soudé = réussie, la plante ne change pas. */
  public function controlerGreffe($verdict, $note) {
    $s = (string)$this->getConfiguration('greffe_statut'); $type = $this->greffeVal('type_greffe'); $q = journalcultures_calc::greffeQuestion($s, $type);
    try { $g = journalcultures_calc::date($type == 'pont' ? (string)$this->getConfiguration('greffe_pont') : $this->greffeVal('date_mult')); } catch (Exception $x) { $g = date('Y-m-d'); } // « Greffer… » écrit toujours une date complète
    if ($verdict == 'printemps' && ($s != 'probable' || $type == 'pont')) throw new Exception(__('« Au printemps » : seulement à J+42, pas pour un pont', __FILE__)); // comme le bouton de la fiche
    [$n, $c] = journalcultures_calc::controler($s, $verdict, $g, date('Y-m-d'), self::sud());
    if ($n == 'pousse' && $type == 'pont') [$n, $c] = ['reprise', '']; // pont soudé aux deux bouts : rien ne doit pousser
    $avant = $this->getName(); $attend = is_array($this->getConfiguration('greffe_greffon')); $id = ''; $info = '';
    if ($type == 'pont') { // réparation : ni bascule, ni retour au porte-greffe
      $info = $n == 'reprise' ? __('pont soudé aux deux bouts : réparation réussie', __FILE__) : ($n == 'echouee' ? __('greffons du pont morts : réparation échouée', __FILE__) : '');
      if ($n == 'echouee') $this->setConfiguration('greffe_pont', '');
    }
    elseif ($attend && in_array($n, ['pousse', 'reprise'], true)) { $this->basculer(); $id = sprintf(__('un œil a débourré : %s devient « %s »', __FILE__), $avant, $this->getName()); }
    elseif ($n == 'echouee' && $attend) { $this->setConfiguration('greffe_greffon', null); $this->horsGreffes(); $id = sprintf(__('%s reste le porte-greffe', __FILE__), $avant); }
    elseif ($n == 'echouee' && is_array($av = $this->getConfiguration('greffe_avant'))) { $this->prendre($av); $id = sprintf(__('%s redevient le porte-greffe « %s »', __FILE__), $avant, $this->getName()); }
    if ($n == 'echouee' || $n == 'reprise') $this->setConfiguration('greffe_avant', null); // greffe terminée
    $this->setConfiguration('greffe_statut', $n == 'echouee' ? '' : $n); $this->setConfiguration('greffe_rep', $n == 'echouee' ? '' : date('Y-m-d')); $this->setConfiguration('greffe_controle', $c);
    self::$_sansGbif = $id === ''; // identité changée : nom botanique relu par GBIF
    try { $this->sauver(); } finally { self::$_sansGbif = false; }
    try {
      self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => $id !== '' ? 'Greffe' : 'Observation',
        'note' => sprintf(__('Contrôle de greffe (%s) : %s → %s', __FILE__), $q, ['ok' => __('oui', __FILE__), 'douteux' => __('douteux', __FILE__), 'printemps' => __('laisser le greffon dormir jusqu’au printemps', __FILE__), 'mort' => __('non, greffe morte', __FILE__)][$verdict],
          journalcultures_calc::GREFFE_STATUTS[$n]) . ($c !== '' ? ', ' . sprintf(__('prochain contrôle le %s', __FILE__), $c) : '') . ($id . $info !== '' ? ' ; ' . $id . $info : '')
          . (($t = journalcultures_calc::texte($note, 200)) !== '' ? ' ; ' . $t : '')]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : contrôle de greffe non noté : ' . $e->getMessage()); } // statut déjà sauvé
    return $n;
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

  private function reff(array $c) { // [rusticité effective (pot, jeunesse), estimation ou null] d'une fiche (la plante, ou le greffon en attente)
    $r = $c['rusticite'] ?? ''; $est = null; // fiche sans rusticité : estimée d'après le groupe (nom botanique ou caractères)
    $car = []; foreach (array_keys(journalcultures_calc::CARACTERES) as $k) $car[$k] = $c['id_' . $k] ?? '';
    if (journalcultures_calc::nombre($r) === null && ($est = journalcultures_calc::estimee($c['bota'] ?? '', $car))) $r = $est['r'];
    $reff = journalcultures_calc::rusticite($r, $this->pot(), max(0, self::reglage('k', 50)), max(0, self::reglage('pmax', 8)));
    $jeu = journalcultures_calc::jeunesse((string)($c['multiplication'] ?? ''), $c['date_mult'] ?? '', self::reglage('jeune', 6), self::reglage('jeune_ans', 5));
    return [$reff === null ? null : round($reff + $jeu, 1), $est, $jeu];
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
      'journal' => self::journal([$this->getId()], 300), 'releves' => self::releves($this->getId()), 'photos' => $ph = $this->photos(), 'liens' => array_values($liens),
      'etiquette' => in_array($this->getConfiguration('etiquette'), $ph, true) ? $this->getConfiguration('etiquette') : '', // photo supprimée : plus d'étiquette
      'plantnet' => self::cle() !== '' ? 1 : 0, 'multiplication' => $this->multiplication(), 'greffe' => $this->suiviGreffe(), 'mesures' => journalcultures_calc::MESURES, 'calculs' => journalcultures_calc::CALCULS, 'engrais' => $this->engraisFiche(), 'sante' => $this->santeFiche(), 'santeCat' => self::santeCat(), 'geo' => self::geo(),
      'noms' => journalcultures_calc::etymologie([$bota, $g['espece'] ?? ''])]; // nom saisi puis nom retenu par GBIF (ancien nom → actuel)
  }

  /* Santé de la fiche : pistes d'après la dernière mesure qui parle de santé (signe, ravageur, maladie, écorce du tronc) */
  public function santeFiche() {
    $q = function ($like) { $r = DB::Prepare('SELECT date, valeurs FROM ' . self::TABLE . ' WHERE plante_id = ? AND action = ? AND valeurs LIKE ? ORDER BY date DESC, id DESC LIMIT 1', [$this->getId(), 'Mesure', $like], DB::FETCH_TYPE_ROW);
      return [(string)($r['date'] ?? ''), json_decode($r['valeurs'] ?? '', true) ?: []]; };
    [$ds, $s] = $q('%sante.%'); [$dt, $t] = $q('%tronc.ecorce%'); // séparées : une mesure du tronc n'efface pas un ravageur noté, et inversement
    $v = array_filter($s, function ($k) { return strpos($k, 'sante.') === 0; }, ARRAY_FILTER_USE_KEY) + (isset($t['tronc.ecorce']) ? ['tronc.ecorce' => $t['tronc.ecorce']] : [])
      + (($t['pot.substrat'] ?? '') === 'détrempé' || ($s['pot.substrat'] ?? '') === 'détrempé' ? ['pot.substrat' => 'détrempé'] : []);
    $e = journalcultures_calc::estimee(trim((string)$this->getConfiguration('bota')) ?: $this->getName(), $this->caracteres()); // ponytail: groupe incertain (caractères) = citron possible
    $citron = $e && array_intersect($e['groupes'], ['Citron', 'Lime']);
    return ['date' => max($ds, $dt), 'pistes' => journalcultures_calc::sante($v, (bool)$citron)];
  }

  /* Engrais de la fiche : volume du pot, plan d'azote proposé (rien n'est écrit sans « Écrire dans le calendrier »), carences d'après la dernière mesure */
  public static function produits() { // saisons du catalogue = hémisphère nord, décalées de 6 mois dans l'hémisphère sud
    $P = journalcultures_calc::produits((string)config::byKey('produits', 'journalcultures', ''));
    return self::sud() ? array_map(function ($p) { $p['saison'] = journalcultures_calc::decaler($p['saison'], 26); return $p; }, $P) : $P; // vidé par l'utilisateur = aucun produit (le catalogue n'est proposé qu'à l'installation)
  }

  private static function reglage($k, $def) { $v = journalcultures_calc::nombre(config::byKey($k, 'journalcultures', $def)); return $v === null ? $def : $v; } // virgule et signe − acceptés ; texte → défaut
  private static function taux() { $x = self::reglage('taux_n', 0.07); return $x > 0 && $x < 1 ? $x : 0.07; }
  private function facteurJeune() { // jeune plant (semis, greffe… datés) : besoin réduit, voir journalcultures_calc::facteurJeune
    $f = self::reglage('engrais_jeune', 25); $n = self::reglage('engrais_jeune_ans', 3);
    return journalcultures_calc::facteurJeune($this->greffeVal('multiplication'), $this->greffeVal('date_mult'), ($f > 0 ? $f : 100) / 100, $n); // greffon en attente : jeune greffe
  }

  private function dosesActuelles(array $P) { // grammes déjà au calendrier pour les produits du plan (dose peut-être ajustée à la main) → proposés tels quels
    $r = [];
    foreach ((preg_split('/\R/u', (string)$this->getConfiguration('engrais')) ?: []) as $l) { $c = explode('|', $l . '||');
      foreach ($P as $i => $p) if ($p['n'] > 0 && $p['tous'] > 0 && mb_strtolower(trim($c[1])) === mb_strtolower($p['nom']) && ($g = journalcultures_calc::nombre($c[2])) > 0) $r[$i] = $g; }
    return $r;
  }

  private function partsActuelles(array $P) { // produits du plan déjà dans le calendrier → parts égales ; aucun → rien de coché
    $cal = array_map(function ($l) { return mb_strtolower(trim(explode('|', $l . '||')[1])); }, (preg_split('/\R/u', (string)$this->getConfiguration('engrais')) ?: []));
    $i = array_keys(array_filter($P, function ($p) use ($cal) { return $p['n'] > 0 && $p['tous'] > 0 && in_array(mb_strtolower($p['nom']), $cal, true); }));
    if (!$i) return [];
    $r = array_fill_keys($i, floor(100 / count($i))); $r[end($i)] += 100 - array_sum($r); return $r; // reste au dernier : total juste 100
  }

  // moins d'un an ou greffe en cours de suivi (sauf le pont) : pas de demi-dose hors période d'apport, même au chaud (pas d'engrais l'hiver : 2 sources ; excès d'azote nuisible au jeune plant : Niu 2024 ; choix du plugin)
  public function jeuneHiver() {
    return (!$this->pontSuivi() && in_array((string)$this->getConfiguration('greffe_statut'), ['attente', 'probable', 'dormant', 'pousse'], true)) // pont = réparation d'une plante déjà en place, pas un jeune plant
      || journalcultures_calc::jeunesse($this->greffeVal('multiplication'), $this->greffeVal('date_mult'), 1, 1) > 0;
  }
  public function engraisFiche() {
    $P = self::produits(); $L = journalcultures_calc::volume($this->pot()); $fj = $this->facteurJeune(); $taux = self::taux() * $fj;
    $v = DB::Prepare('SELECT valeurs FROM ' . self::TABLE . ' WHERE plante_id = ? AND action = ? AND valeurs LIKE ? ORDER BY date DESC, id DESC LIMIT 1', [$this->getId(), 'Mesure', '%feuilles.couleur%'], DB::FETCH_TYPE_ROW); // dernière mesure de la couleur des feuilles, pas la dernière mesure tout court
    $car = journalcultures_calc::carences(json_decode($v['valeurs'] ?? '', true) ?: []);
    foreach ($car as &$c) $c['produits'] = array_values(array_map(function ($p) { return $p['nom'] . ($p['note'] !== '' ? ' (' . $p['note'] . ')' : ''); },
      array_filter($P, function ($p) use ($c) { return in_array($c['el'], $p['apporte'], true); })));
    unset($c);
    return ['litres' => $L, 'taux' => $taux, 'jeune' => $fj, 'moy7' => $this->moyenne7(), 'seuil' => self::seuil(), 'hiv' => $this->getConfiguration('hivernage') == 1, 'jeuneHiv' => $this->jeuneHiver(), 'chauffe' => !empty((self::emplacements()[(string)$this->getConfiguration('emplacement')] ?? [])[3]), 'sud' => self::sud(), 'calendrier' => (string)$this->getConfiguration('engrais'), 'parts' => (object)$this->partsActuelles($P), 'doses' => (object)$this->dosesActuelles($P), 'carences' => $car,
      'produits' => array_map(function ($p) use ($L, $taux) { $ok = $L && $p['n'] > 0 && $p['tous'] > 0; // ge : dose de l'étiquette ; ne : son azote, en part du besoin
        return $p + ['g100' => $ok ? $L * $taux * $p['tous'] * 100 / $p['n'] : null, 'ge' => $ok && $p['gl'] > 0 ? $L * $p['gl'] : null, 'ne' => $ok && $p['gl'] > 0 ? $p['gl'] * $p['n'] / 100 / $p['tous'] / $taux : 0]; }, $P)];
  }

  // « Écrire dans le calendrier » : lignes des produits du plan remplacées, les autres lignes (fer, magnésium, à la main) gardées ; noté au journal
  public function ecrirePlan(array $parts, array $doses = []) {
    $P = self::produits(); $L = journalcultures_calc::volume($this->pot());
    if (!$L) throw new Exception(__('Pot de dimensions inconnues ou pleine terre : pas de plan', __FILE__));
    $parts = array_map('floatval', array_filter($parts, 'is_numeric'));
    if (array_filter($parts, function ($x) { return $x < 0 || $x > 100; })) throw new Exception(__('Chaque part doit être entre 0 et 100 %', __FILE__));
    if (abs(array_sum($parts) - 100) > 1) throw new Exception(__('Les parts doivent faire 100 %', __FILE__));
    $doses = array_map('floatval', array_filter($doses, 'is_numeric'));
    if (array_filter($doses, function ($x) { return $x < 0 || $x > 5000; })) throw new Exception(__('Dose entre 0 et 5000 g', __FILE__));
    $plan = journalcultures_calc::plan($L, $P, $parts, self::taux() * $this->facteurJeune(), $doses);
    if (!$plan) throw new Exception(__('Aucun produit du plan coché', __FILE__));
    $noms = array_map(function ($p) { return mb_strtolower($p['nom']); }, array_filter($P, function ($p) { return $p['n'] > 0 && $p['tous'] > 0; }));
    $avant = (string)$this->getConfiguration('engrais');
    $garde = array_filter((preg_split('/\R/u', $avant) ?: []), function ($l) use ($noms) { return trim($l) !== '' && !in_array(mb_strtolower(trim(explode('|', $l . '||')[1])), $noms, true); });
    $apres = implode("\n", array_merge($garde, array_column($plan, 'ligne')));
    if ($apres === $avant) throw new Exception(__('Rien à changer', __FILE__));
    self::$_sansGbif = true;
    try { $this->setConfiguration('engrais', $apres); $this->save(); } finally { self::$_sansGbif = false; }
    try { self::inserer(['date' => date('Y-m-d'), 'plante_id' => $this->getId(), 'plante' => $this->getName(), 'lieu' => (string)$this->getConfiguration('emplacement'), 'action' => 'Observation',
      'note' => sprintf(__('Calendrier d\'engrais (pot de %s L) : %s → %s', __FILE__), str_replace('.', ',', (string)$L), $avant === '' ? '–' : strtr($avant, ["\n" => ' ; ']), strtr($apres, ["\n" => ' ; ']))]);
    } catch (Throwable $e) { log::add('journalcultures', 'error', $this->getHumanName() . ' : calendrier écrit, pas noté au journal : ' . $e->getMessage()); } // déjà écrit : un nouvel envoi dirait « Rien à changer »
    return $apres;
  }

  public function multiplication() { // « Greffe (fente) · ≈ 2 ans (2024) », '' si rien de saisi
    $m = (string)$this->getConfiguration('multiplication'); $g = journalcultures_calc::GREFFES[$this->getConfiguration('type_greffe')] ?? ''; $d = trim((string)$this->getConfiguration('date_mult'));
    $a = journalcultures_calc::age($d);
    return implode(' · ', array_filter([$m !== '' ? __(ucfirst($m), __FILE__) . ($g !== '' ? ' (' . $g . ')' : '') : '', $a !== null ? $a . ' (' . $d . ')' : $d]));
  }

  public function donnees() { // tuile d'une collection
    $pl = []; $L = self::emplacements();
    $mes = self::$_memo['mes'] ?? null; // date de la dernière mesure par plante
    if ($mes === null) {
      $mes = [];
      foreach (DB::Prepare('SELECT plante_id, MAX(date) d FROM ' . self::TABLE . ' WHERE action = ? AND plante_id IS NOT NULL GROUP BY plante_id', ['Mesure'], DB::FETCH_TYPE_ALL) ?: [] as $x) $mes[$x['plante_id']] = $x['d'];
      if (self::$_memo !== null) self::$_memo['mes'] = $mes;
    }
    foreach ($this->plantes() as $p) {
      if (!$p->getIsEnable()) continue;
      $sonde = trim($L[$p->getConfiguration('emplacement')][0] ?? '') !== ''; // lieu sans sonde : pas l'ancienne température
      $m = self::muette($p->valeur('raison')); // sonde muette ou emplacement inconnu
      $rust = journalcultures_calc::nombre($p->getConfiguration('rusticite')) !== null // rusticité effacée de la fiche : pas l'ancienne valeur
        || journalcultures_calc::estimee($p->getConfiguration('bota'), $p->caracteres()) !== null // … sauf si le groupe en donne une
        || is_array($p->getConfiguration('greffe_greffon')); // … ou le greffon en attente
      $pl[] = ['id' => $p->getId(), 'nom' => $p->getName(), 'empl' => $p->valeur('emplacement'), 't' => $sonde && !$m ? $p->valeur('temperature') : null, 'muette' => $m,
        'reff' => $rust ? $p->valeur('rusticite') : null,
        'alerte' => $p->valeur('alerte'), 'raison' => $p->valeur('raison'), 'engrais' => $p->valeur('engrais'), 'rust' => $p->getConfiguration('rusticite'),
        'pg' => (string)$p->getConfiguration('porte_greffe'), 'mes' => $mes[$p->getId()] ?? '', 'gr' => $p->enGreffe()];
    }
    return ['id' => $this->getId(), 'nom' => $this->getName(), 'plantes' => $pl, 'emplacements' => array_keys(self::emplacements()), 'admin' => isConnect('admin') ? 1 : 0,
      'actions' => array_map(function ($a) { return __($a, __FILE__); }, journalcultures_calc::ACTIONS), 'actionsCle' => journalcultures_calc::ACTIONS];
  }

  public static function collections() { // [[id, nom]] des collections lisibles, pour « Greffer… »
    $cs = array_filter(self::byType('journalcultures', true), function ($c) { return $c->type() == 'collection' && $c->hasRight('r'); });
    usort($cs, function ($a, $b) { return strnatcasecmp($a->getName(), $b->getName()); });
    return array_map(function ($c) { return [(int)$c->getId(), $c->getName()]; }, $cs);
  }

  // « Greffer… » : plantes des autres collections que celle du porte-greffe (greffons possibles), [nom, cultivar, nom botanique, caractères]
  public static function cultivars(journalcultures $pg) {
    $r = [];
    foreach (self::byType('journalcultures', true) as $p) {
      if ($p->type() != 'plante' || $p->getConfiguration('collection') == $pg->getConfiguration('collection') || !is_object($c = $p->collection()) || !$c->hasRight('r')) continue;
      $r[] = [$p->getName(), trim((string)$p->getConfiguration('cultivar')), trim((string)$p->getConfiguration('bota')), journalcultures_calc::observes($p->caracteres())];
    }
    usort($r, function ($a, $b) { return strnatcasecmp($a[0], $b[0]); });
    return $r;
  }

  public static function page() { // panel plein écran : collections lisibles par l'utilisateur + températures des emplacements
    $cs = array_values(array_filter(self::byType('journalcultures', true), function ($c) { return $c->type() == 'collection' && $c->hasRight('r'); }));
    usort($cs, function ($a, $b) { return strnatcasecmp($a->getName(), $b->getName()); });
    $lieux = []; $ids = array_map(function ($c) { return $c->getId(); }, $cs); $vus = []; // non-admin : seulement les emplacements de ses plantes
    if (!isConnect('admin')) foreach (self::byType('journalcultures', true) as $p) if ($p->type() == 'plante' && in_array($p->getConfiguration('collection'), $ids)) $vus[(string)$p->getConfiguration('emplacement')] = 1;
    foreach (self::emplacements() as $nom => $s) if (trim($s[0]) !== '' && (isConnect('admin') || isset($vus[$nom]))) $lieux[] = ['nom' => $nom, 't' => self::lire($s[0]), 'h' => self::lire($s[1])];
    return ['collections' => array_map(function ($c) { return $c->donnees(); }, $cs), 'lieux' => $lieux];
  }

  public static function lib() { // bibliothèque traduite : en ligne dans le panel et la page du plugin, par l'action ajax « lib » (cache navigateur) dans les tuiles
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
    $lib = 'plugins/journalcultures/core/ajax/journalcultures.ajax.php?action=lib&l=' . rawurlencode(translate::getLanguage()) . '&v=' . filemtime(__DIR__ . '/../template/journalcultures.js'); // ponytail: v ne suit pas core/i18n (fr seul) ; ajouter sa date si d'autres langues arrivent
    return $this->postToHtml($_version, strtr($html, ['@@LIB@@' => $lib, '@@DATA@@' => $json]));
  }
}

class journalculturesCmd extends cmd {
  public function preSave() { // texte possiblement > 127 caractères : refusé par history.value en mode strict
    if (in_array($this->getLogicalId(), ['resume', 'greffe'], true)) $this->setIsHistorized(0);
  }

  public function execute($_options = []) {
    if ($this->getLogicalId() != 'refresh') return;
    $eq = $this->getEqLogic();
    foreach ($eq->plantes() as $p) if ($p->getIsEnable()) $p->maj(false); // la collection relit l'état de ses plantes
    $eq->maj();
  }
}

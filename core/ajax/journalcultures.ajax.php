<?php
try {
  require_once __DIR__ . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');
  if (!isConnect()) throw new Exception(__('401 - Accès non autorisé', __FILE__));
  require_once __DIR__ . '/../class/journalcultures.class.php';
  ajax::init();

  // équipement du plugin ; droit de lecture vérifié sur la collection (les plantes n'ont pas de tuile ni de droits propres)
  $eq = function ($id, $type = null) {
    $e = journalcultures::byId($id);
    if (!is_object($e) || $e->getEqType_name() != 'journalcultures' || ($type && $e->type() != $type)) throw new Exception(__('Équipement introuvable', __FILE__));
    $c = $e->collection();
    if (!is_object($c) ? !isConnect('admin') : !$c->hasRight('r')) throw new Exception(__('401 - Accès non autorisé', __FILE__));
    return $e;
  };
  $liste = function ($k) { $v = json_decode((string)init($k), true); return is_array($v) ? $v : []; };
  $a = init('action');

  if ($a == 'plante') ajax::success($eq(init('id'), 'plante')->fiche());
  if ($a == 'page') ajax::success(journalcultures::page());
  if ($a == 'notice') { // docs/fr_FR/*.md de la liste blanche, convertis ici (une seule source avec GitHub)
    $r = [];
    foreach (journalcultures_calc::NOTICES as $k => $t) {
      $f = __DIR__ . '/../../docs/fr_FR/' . $k . '.md';
      if (is_file($f)) $r[] = ['k' => $k, 'titre' => $t, 'html' => journalcultures_calc::markdown(file_get_contents($f))];
    }
    ajax::success($r);
  }
  if ($a == 'journal') { $c = $eq(init('id'), 'collection'); ajax::success(journalcultures::journal(array_map(function ($p) { return $p->getId(); }, $c->plantes()), 500, $c->getId())); }
  if ($a == 'photo') { // image envoyée telle quelle (la tuile la lit en blob)
    $f = $eq(init('id'), 'plante')->photo(init('nom'));
    header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($f, PATHINFO_EXTENSION)]);
    header('Content-Length: ' . filesize($f)); header('Cache-Control: private, max-age=86400');
    readfile($f);
    exit;
  }

  // écritures : administrateurs seulement
  if (!isConnect('admin')) throw new Exception(__('401 - Accès non autorisé', __FILE__));
  if ($a == 'noter') { // une entrée par plante cochée, ou une entrée libre
    $in = $liste('entree'); unset($in['valeurs'], $in['origine'], $in['plante']); $ids = array_values(array_filter((array)($in['plantes'] ?? []), 'is_numeric')); // origine et nom : jamais du client
    if (count($ids) > 200) throw new Exception(__('Trop de plantes', __FILE__));
    $maj = array_map(function ($id) use ($eq) { return $eq($id, 'plante'); }, $ids); // tout vérifier avant d'écrire : pas de saisie à moitié faite
    $coll = $maj ? null : $eq($in['collection_id'] ?? 0, 'collection'); // entrée libre : rattachée à la collection de la tuile
    foreach ($maj ?: [null] as $p) {
      journalcultures::inserer(['plante_id' => $p ? $p->getId() : null, 'collection_id' => $coll ? $coll->getId() : null,
        'lieu' => ($in['lieu'] ?? '') !== '' || !$p ? ($in['lieu'] ?? '') : $p->getConfiguration('emplacement')] + $in);
    }
    $cs = [];
    foreach ($maj as $p) { $p->maj(false); if (is_object($c = $p->collection())) $cs[$c->getId()] = $c; } // engrais donné → rappel retiré
    foreach ($cs as $c) $c->maj();
    ajax::success(count($maj ?: [1]));
  }
  if ($a == 'supprimer') {
    $p = journalcultures::supprimerEntree(init('entree'));
    if (is_object($p) && $p->getIsEnable()) $p->maj();
    ajax::success();
  }
  if ($a == 'deplacer') { // emplacement et/ou collection ('' = inchangé)
    $e = (string)init('emplacement'); $co = init('collection') ? $eq(init('collection'), 'collection') : null;
    if ($e !== '' && !array_key_exists($e, journalcultures::emplacements())) throw new Exception(__('Emplacement inconnu', __FILE__));
    if ($e === '' && !$co) throw new Exception(__('Rien à changer', __FILE__));
    if (count($liste('plantes')) > 200) throw new Exception(__('Trop de plantes', __FILE__));
    $ps = array_map(function ($id) use ($eq) { return $eq($id, 'plante'); }, $liste('plantes')); // tout vérifier avant de déplacer
    if ($co) foreach ($ps as $p) foreach (eqLogic::byObjectId($co->getObject_id(), false) as $x) // nom unique par objet (tous plugins)
      if ($x->getId() != $p->getId() && mb_strtolower($x->getName()) == mb_strtolower($p->getName())) throw new Exception(sprintf(__('%s : un équipement de l\'objet de destination porte déjà ce nom', __FILE__), $p->getName()));
    $n = 0; journalcultures::$_sansGbif = true;
    foreach ($ps as $p) {
      $ch = false;
      if ($e !== '' && $p->getConfiguration('emplacement') != $e) { $p->setConfiguration('emplacement', $e); $ch = true; }
      if ($co && $p->getConfiguration('collection') != $co->getId()) { $p->setConfiguration('collection', $co->getId()); $p->setObject_id($co->getObject_id()); $ch = true; }
      if ($ch) try { $p->save(); $n++; } // preSave/postSave notent le déplacement au journal, et redessinent les deux tuiles
      catch (Throwable $x) { // nom en double à l'accent près (index unique du core) : dire où on s'est arrêté
        if (strpos($x->getMessage(), 'Duplicate') === false) throw $x;
        throw new Exception(sprintf(__('%s : un équipement de l\'objet de destination porte déjà ce nom (%d plante(s) déplacée(s) avant)', __FILE__), $p->getName(), $n));
      }
    }
    ajax::success($n);
  }
  if ($a == 'mesurer') ajax::success($eq(init('id'), 'plante')->mesurer($liste('mesures')));
  if ($a == 'photo_ajout') ajax::success($eq(init('id'), 'plante')->ajouterPhoto($_FILES['photo'] ?? []));
  if ($a == 'photo_suppr') { unlink($eq(init('id'), 'plante')->photo(init('nom'))); ajax::success(); }
  if ($a == 'etiquette') { // photo de l'étiquette = une des photos de la plante ('' = aucune)
    $p = $eq(init('id'), 'plante'); $n = (string)init('nom');
    if ($n !== '') $p->photo($n);
    journalcultures::$_sansGbif = true; $p->setConfiguration('etiquette', $n); $p->save(); ajax::success();
  }
  if ($a == 'identifier') { // Pl@ntNet : photos de la plante (noms) et/ou envoyées (photo0, photo1…), organes dans cet ordre
    $noms = array_values(array_filter($liste('noms'), 'is_string')); $fs = [];
    for ($k = 0; isset($_FILES['photo' . $k]); $k++) $fs[] = $_FILES['photo' . $k];
    ajax::success(journalcultures::identifierPhotos($noms ? $eq(init('id'), 'plante') : null, $noms, $fs, $liste('organes')));
  }
  // « ＋ Plante » : listes du formulaire, groupe et rusticité estimée au fil de la saisie, création
  if ($a == 'modele') ajax::success(['caracteres' => journalcultures_calc::CARACTERES, 'porte_greffes' => journalcultures::PORTE_GREFFES,
    'multiplications' => journalcultures_calc::MULTIPLICATIONS, 'greffes' => journalcultures_calc::GREFFES, 'greffe_saisons' => array_map('journalcultures_calc::greffeSaison', array_combine(array_keys(journalcultures_calc::GREFFES), array_keys(journalcultures_calc::GREFFES))), 'collections' => journalcultures::collections(),
    'cultivars' => init('pg') ? journalcultures::cultivars($eq(init('pg'), 'plante')) : [], 'plantnet' => journalcultures::cle() !== '' ? 1 : 0]);
  if ($a == 'reconnaitre') { $c = $liste('carac'); ajax::success(['ident' => journalcultures_calc::identifier($c), 'estimee' => journalcultures_calc::estimee((string)init('bota'), $c)]); }
  if ($a == 'creer') ajax::success(journalcultures::creer($eq(init('collection'), 'collection'), $liste('plante')));
  if ($a == 'accepter') ajax::success($eq(init('id'), 'plante')->accepter($liste('champs'), $liste('sources')));
  if ($a == 'plan') ajax::success($eq(init('id'), 'plante')->ecrirePlan($liste('parts'), $liste('doses')));
  if ($a == 'controler') ajax::success($eq(init('id'), 'plante')->controlerGreffe((string)init('verdict'), (string)init('note')));
  if ($a == 'degreffer') ajax::success($eq(init('id'), 'plante')->degreffer($eq(init('collection'), 'collection'), init('nom')));
  if ($a == 'greffer') ajax::success(journalcultures::creer($eq(init('collection'), 'collection'), $liste('plante'), $eq(init('id'), 'plante')));

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . $a);
} catch (Throwable $e) {
  ajax::error($e->getMessage(), $e->getCode()); // texte brut : la tuile l'affiche avec textContent
}

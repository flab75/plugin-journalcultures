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
    $in = $liste('entree'); $ids = array_values(array_filter((array)($in['plantes'] ?? []), 'is_numeric'));
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
  if ($a == 'deplacer') {
    $e = (string)init('emplacement');
    if (!array_key_exists($e, journalcultures::emplacements())) throw new Exception(__('Emplacement inconnu', __FILE__));
    $n = 0; $ps = array_map(function ($id) use ($eq) { return $eq($id, 'plante'); }, $liste('plantes')); // tout vérifier avant de déplacer
    journalcultures::$_sansGbif = true;
    foreach ($ps as $p) {
      if ($p->getConfiguration('emplacement') == $e) continue;
      $p->setConfiguration('emplacement', $e); $p->save(); $n++; // preSave/postSave notent le déplacement au journal
    }
    ajax::success($n);
  }
  if ($a == 'mesurer') { $eq(init('id'), 'plante')->mesurer($liste('mesures')); ajax::success(); }
  if ($a == 'photo_ajout') ajax::success($eq(init('id'), 'plante')->ajouterPhoto($_FILES['photo'] ?? []));
  if ($a == 'photo_suppr') { unlink($eq(init('id'), 'plante')->photo(init('nom'))); ajax::success(); }
  if ($a == 'identifier') ajax::success($eq(init('id'), 'plante')->identifier(init('nom'), init('organe')));

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . $a);
} catch (Throwable $e) {
  ajax::error($e->getMessage(), $e->getCode()); // texte brut : la tuile l'affiche avec textContent
}

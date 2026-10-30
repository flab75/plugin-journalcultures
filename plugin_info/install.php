<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';
require_once __DIR__ . '/../core/class/journalcultures.class.php';

function journalcultures_install() {
  journalcultures::creerTable();
}

function journalcultures_update() {
  journalcultures::creerTable();
  journalcultures::$_sansGbif = true; // pas d'appel GBIF par plante pendant la mise à jour
  foreach (eqLogic::byType('journalcultures') as $eq) { $eq->save(); $eq->commandesOk(true); } // commandes ajoutées ou retirées par la nouvelle version
}

// ponytail: la table du journal est gardée à la désinstallation (des années de notes) ; DROP TABLE journalcultures_journal à la main si voulu
function journalcultures_remove() {
}

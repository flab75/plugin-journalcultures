<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';
require_once __DIR__ . '/../core/class/journalcultures.class.php';

function journalcultures_install() {
  journalcultures::creerTable();
  if (trim(config::byKey('produits', 'journalcultures', '')) === '') config::save('produits', journalcultures_calc::PRODUITS, 'journalcultures'); // réactivation : catalogue modifié gardé
}

function journalcultures_update() {
  journalcultures::creerTable();
  if (trim(config::byKey('produits', 'journalcultures', '')) === '') config::save('produits', journalcultures_calc::PRODUITS, 'journalcultures'); // 0.13 : catalogue proposé, à modifier
  journalcultures::$_sansGbif = true; // pas d'appel GBIF par plante pendant la mise à jour
  foreach (eqLogic::byType('journalcultures') as $eq) try { $eq->save(); $eq->commandesOk(true); } // commandes ajoutées ou retirées par la nouvelle version
    catch (Throwable $e) { log::add('journalcultures', 'error', $eq->getHumanName() . ' : mise à jour : ' . $e->getMessage()); } // un équipement en erreur n'arrête pas les autres
}

// ponytail: la table du journal est gardée à la désinstallation (des années de notes) ; DROP TABLE journalcultures_journal à la main si voulu
function journalcultures_remove() {
}

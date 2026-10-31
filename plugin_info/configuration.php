<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Emplacements}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Une ligne par emplacement : Nom | commande température | commande humidité | commande température minimale prévue (facultatif, pour l'extérieur). Sans sonde, pas d'alerte de froid.}}"></i></sup>
      </label>
      <div class="col-md-6"><textarea class="configKey form-control" data-l1key="emplacements" rows="7" placeholder="Serre | #[Serre][Sonde][Température]# | #[Serre][Sonde][Humidité]# |&#10;Jardin | #[Jardin][Sonde][Température]# | | #[Météo][Prévision][Température min]#&#10;Maison | | |"></textarea></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Marge de l'alerte de froid}} (°C)
        <sup><i class="fas fa-question-circle tooltips" title="{{Alerte quand la température mesurée ou prévue descend à la rusticité effective + cette marge.}}"></i></sup>
      </label>
      <div class="col-md-2"><input class="configKey form-control" data-l1key="marge" placeholder="3"></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Pénalité du pot}} : k / {{maximum}} (°C)
        <sup><i class="fas fa-question-circle tooltips" title="{{En pot, la rusticité est relevée de min(maximum, k / e), e = demi-épaisseur de la motte en cm (plus petit de diamètre et hauteur de terre, divisé par 2). Par défaut k = 50, maximum = 8 : pot de 40 cm → +2,5 °C, de 20 cm → +5 °C. Pleine terre : aucune pénalité.}}"></i></sup>
      </label>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="k" placeholder="50"></div>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="pmax" placeholder="8"></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Clé API Pl@ntNet}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Compte gratuit sur my.plantnet.org (500 identifications par jour). Facultatif : sans clé, pas d'identification des photos.}}"></i></sup>
      </label>
      <div class="col-md-4"><input type="password" autocomplete="new-password" class="configKey form-control" data-l1key="plantnet"></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Page plein écran dans le menu Accueil}}</label>
      <div class="col-md-1"><input type="checkbox" class="configKey" data-l1key="displayDesktopPanel"></div>
    </div>
  </fieldset>
</form>

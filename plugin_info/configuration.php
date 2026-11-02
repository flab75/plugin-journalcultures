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
      <label class="col-md-4 control-label">{{Pénalité de jeunesse}} : °C / {{ans}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Semis, greffe, bouture ou marcotte datés : rusticité relevée de x °C la première année, puis de moins en moins jusqu'à 0 à n ans. Par défaut 6 °C et 5 ans (choix du plugin) : greffe d'un an → +4,8 °C. Achat ou sans date : aucune pénalité. 0 pour la retirer.}}"></i></sup>
      </label>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="jeune" placeholder="6"></div>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="jeune_ans" placeholder="5"></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Greffe : température pour souder}} : min / max °C
        <sup><i class="fas fa-question-circle tooltips" title="{{Tant que la greffe n'est pas reprise, la moyenne sur 24 h de la sonde de l'emplacement est comparée à ces seuils ; hors limites, la fiche et la commande « Greffe à contrôler » le disent. Par défaut 21 °C (le Poncirus s'endort en dessous) et 32 °C.}}"></i></sup>
      </label>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="greffe_tmin" placeholder="21"></div>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="greffe_tmax" placeholder="32"></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Produits en stock (engrais, correcteurs)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Une ligne par produit : Nom | % d'azote | toutes les n semaines (0 = ponctuel) | saison en semaines ISO (10-40 = mars à octobre) | éléments apportés (N P K Mg Fe S Zn Mn) | remarque | dose de l'étiquette en g par litre de pot et par apport (facultatif : le plan la suit au lieu de la calculer). Pour toutes les plantes : une plante ne reçoit que les produits cochés dans son plan (fiche). Les produits à % d'azote et intervalle servent au plan ; les éléments, aux pistes de carence.}}"></i></sup>
      </label>
      <div class="col-md-8"><textarea class="configKey form-control" data-l1key="produits" rows="6"></textarea></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Azote par litre de pot}} (g / {{semaine}})
        <sup><i class="fas fa-question-circle tooltips" title="{{Besoin en azote d'une plante en pot pendant la saison de pousse, en grammes d'azote par litre de pot et par semaine. Par défaut 0,07 (0,14 g toutes les 2 semaines ; un essai en pot et deux étiquettes : 0,10 à 0,16 g ; 0, négatif ou 1 et plus : 0,07). Le plan de chaque plante en découle.}}"></i></sup>
      </label>
      <div class="col-md-2"><input class="configKey form-control" data-l1key="taux_n" placeholder="0.07"></div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Engrais d'un jeune plant}} : % / {{ans}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Semis, greffe, bouture ou marcotte datés : le besoin en azote est réduit à ce % à 0 an, puis remonte linéairement jusqu'à 100 % à n ans. Par défaut 25 % et 3 ans (choix du plugin) : greffe d'un an → 50 %. En pot, trop d'azote réduit les racines des jeunes agrumes (une étude). Achat ou sans date : 100 %. % à 0 ou 100, ou durée à 0, pour retirer la réduction. Les doses d'étiquette et les doses corrigées à la main ne changent pas, ni une dose déjà au calendrier tant qu'on ne la recalcule pas.}}"></i></sup>
      </label>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="engrais_jeune" placeholder="25"></div>
      <div class="col-md-1"><input class="configKey form-control" data-l1key="engrais_jeune_ans" placeholder="3"></div>
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

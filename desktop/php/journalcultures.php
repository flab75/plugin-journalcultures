<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('journalcultures');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<!-- Page d'accueil du plugin -->
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<!-- Boutons de gestion du plugin -->
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
			<div class="cursor logoSecondary" onclick="window.journalcultures.notice('index')">
				<i class="fas fa-book-open"></i>
				<br>
				<span>{{Notice}}</span>
			</div>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes collections et plantes}}</legend>
		<?php
		if (count($eqLogics) == 0) {
			echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Rien pour le moment : « Ajouter » une collection (ex. Agrumes), puis ses plantes}}</div>';
		} else {
			// Champ de recherche
			echo '<div class="input-group" style="margin:5px;">';
			echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
			echo '<div class="input-group-btn">';
			echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
			echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
			echo '</div>';
			echo '</div>';
			// Liste des équipements du plugin
			echo '<div class="eqLogicThumbnailContainer">';
			usort($eqLogics, function ($a, $b) { return [$a->type() != 'collection', $a->getName()] <=> [$b->type() != 'collection', $b->getName()]; });
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
				echo '<img src="' . $eqLogic->getImage() . '"/>';
				echo '<br>';
				echo '<span class="name">' . ($eqLogic->type() == 'collection' ? '<i class="fas fa-layer-group"></i> ' : '') . $eqLogic->getHumanName(true, true) . '</span>';
				echo '<span class="hiddenAsCard displayTableRight hidden">';
				echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
				echo '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		?>
	</div> <!-- /.eqLogicThumbnailDisplay -->

	<!-- Page de présentation de l'équipement -->
	<div class="col-xs-12 eqLogic" style="display: none;">
		<!-- barre de gestion de l'équipement -->
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<!-- Onglets -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<!-- Onglet de configuration de l'équipement -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<!-- Partie gauche de l'onglet "Equipements" -->
				<!-- Paramètres généraux et spécifiques de l'équipement -->
				<form class="form-horizontal">
					<fieldset>
						<div class="col-lg-6">
							<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
								<div class="col-sm-6">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Objet parent}}</label>
								<div class="col-sm-6">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Catégorie}}</label>
								<div class="col-sm-6">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Options}}</label>
								<div class="col-sm-6">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
								</div>
							</div>

							<legend><i class="fas fa-seedling"></i> {{Type}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Type}}</label>
								<div class="col-sm-6"><select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type" id="jdc-type">
									<option value="plante">{{Plante (sans tuile, visible dans sa collection)}}</option>
									<option value="collection">{{Collection (la tuile : tableau de ses plantes)}}</option>
								</select></div>
							</div>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Collection}}</label>
								<div class="col-sm-6"><select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="collection">
									<option value="">{{Aucune}}</option>
									<?php
									foreach ($eqLogics as $c) if ($c->type() == 'collection') echo '<option value="' . $c->getId() . '">' . htmlspecialchars($c->getName()) . '</option>';
									?>
								</select></div>
							</div>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Emplacement}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Liste réglable dans la configuration du plugin. Chaque changement est noté au journal et historisé.}}"></i></sup>
								</label>
								<div class="col-sm-6"><select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="emplacement">
									<option value="">{{Aucun}}</option>
									<?php
									foreach (array_keys(journalcultures::emplacements()) as $e) echo '<option value="' . htmlspecialchars($e) . '">' . htmlspecialchars($e) . '</option>';
									?>
								</select></div>
							</div>
							<legend class="jdc-plante"><i class="fas fa-box"></i> {{Pot (rusticité effective)}}</legend>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Pleine terre}}</label>
								<div class="col-sm-6"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="pleine_terre"></div>
							</div>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Forme du pot}}</label>
								<div class="col-sm-6"><select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="pot_forme">
									<option value="">{{Rond}}</option><option value="carre">{{Carré}}</option>
								</select></div>
							</div>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Diamètre ou côté du pot}} (cm)</label>
								<div class="col-sm-6"><input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="pot_diam" placeholder=""></div>
							</div>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Hauteur du pot}} (cm)</label>
								<div class="col-sm-6"><input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="pot_haut" placeholder=""></div>
							</div>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Hauteur de terre}} (cm)</label>
								<div class="col-sm-6"><input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="pot_terre" placeholder="{{par défaut : hauteur du pot}}"></div>
							</div>
							<legend class="jdc-plante"><i class="fas fa-flask"></i> {{Engrais}}</legend>
							<div class="form-group jdc-plante">
								<label class="col-sm-4 control-label">{{Calendrier}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Une ligne par apport : semaines | produit | dose. Semaines : 10-40/2 (de la 10 à la 40, une sur deux), 12,16,20, ou 48-8 (à cheval sur deux années). Rappel la semaine prévue jusqu'à ce qu'un « Engrais » soit noté au journal.}}"></i></sup>
								</label>
								<div class="col-sm-6"><textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="engrais" rows="3" placeholder="10-40/2 | {{Engrais agrumes}} | 5 g/l"></textarea></div>
							</div>
						</div>

						<div class="col-lg-6 jdc-plante">
							<legend><i class="fas fa-book"></i> {{Fiche}}</legend>
							<?php
							foreach (journalcultures::FICHE as $k => $l) {
								echo '<div class="form-group"><label class="col-sm-4 control-label">' . htmlspecialchars(__($l, __FILE__)) . '</label><div class="col-sm-8">';
								echo in_array($k, ['bota', 'cultivar', 'porte_greffe', 'origine', 'rusticite', 'taille_adulte'])
									? '<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="' . $k . '"' . ($k == 'porte_greffe' ? ' list="jdc-pg"' : '') . '>'
									: '<textarea class="eqLogicAttr form-control autogrow" data-l1key="configuration" data-l2key="' . $k . '" rows="1"></textarea>';
								echo '</div></div>';
							}
							echo '<div class="form-group"><label class="col-sm-4 control-label">{{Multiplication}}</label><div class="col-sm-4"><select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="multiplication"><option value="">{{inconnue}}</option>';
							foreach (journalcultures_calc::MULTIPLICATIONS as $m) echo '<option value="' . $m . '">' . htmlspecialchars(__(ucfirst($m), __FILE__)) . '</option>';
							echo '</select></div><div class="col-sm-4"><input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="date_mult" placeholder="{{date : 2024, 2024-05 ou 2024-05-12}}" title="{{L\'âge de la plante en est calculé}}"></div></div>';
							echo '<div class="form-group"><label class="col-sm-4 control-label">{{Type de greffe}}</label><div class="col-sm-4"><select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type_greffe" title="{{Multiplication vide : elle devient « greffe » ; autre que greffe : type effacé}}"><option value="">{{inconnu ou sans objet}}</option>';
							foreach (journalcultures_calc::GREFFES as $k => $g) echo '<option value="' . $k . '">' . htmlspecialchars($g) . '</option>';
							echo '</select></div></div>';
							echo '<datalist id="jdc-pg">';
							foreach (journalcultures::PORTE_GREFFES as $n => $d) echo '<option value="' . htmlspecialchars($n) . '">' . htmlspecialchars($d) . '</option>';
							echo '</datalist>';
							?>
							<div class="form-group"><label class="col-sm-4 control-label">GBIF</label><div class="col-sm-8" id="jdc-gbif" style="padding-top:7px"></div></div>
							<legend><i class="fas fa-search"></i> {{Identification (caractères observés)}}
								<sup><i class="fas fa-question-circle tooltips" title="{{Donne le groupe d'agrumes le plus compatible (kumquat, citron, yuzu…), jamais la variété : pour un hybride, la morphologie ne suffit souvent pas. Laisser vide ce qui n'est pas observé.}}"></i></sup>
							</legend>
							<?php
							foreach (journalcultures_calc::CARACTERES as $k => [$l, $opt]) {
								echo '<div class="form-group"><label class="col-sm-4 control-label">' . htmlspecialchars(__($l, __FILE__)) . '</label><div class="col-sm-8">';
								echo '<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="id_' . $k . '"><option value="">{{non observé}}</option>';
								foreach ($opt as $c => $o) echo '<option value="' . htmlspecialchars($c) . '">' . htmlspecialchars(__($o, __FILE__)) . '</option>';
								echo '</select></div></div>';
							}
							?>
							<div class="form-group"><label class="col-sm-4 control-label">{{Résultat}}</label><div class="col-sm-8" id="jdc-ident" style="padding-top:7px"></div></div>
						</div>

						<!-- Partie droite de l'onglet "Équipement" -->
						<!-- Affiche un champ de commentaire par défaut mais vous pouvez y mettre ce que vous voulez -->
						<div class="col-lg-6">
							<legend><i class="fas fa-info"></i> {{Informations}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Description}}</label>
								<div class="col-sm-6">
									<textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
								</div>
							</div>
						</div>
					</fieldset>
				</form>
			</div><!-- /.tabpanel #eqlogictab-->

			<!-- Onglet des commandes de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
								<th style="min-width:200px;width:350px;">{{Nom}}</th>
								<th>{{Type}}</th>
								<th style="min-width:260px;">{{Options}}</th>
								<th>{{Etat}}</th>
								<th style="min-width:80px;width:200px;">{{Actions}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtab-->

		</div><!-- /.tab-content -->
	</div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<script><?php echo journalcultures::lib(); ?></script>
<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'journalcultures', 'js', 'journalcultures'); ?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>

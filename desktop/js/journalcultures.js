/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs">'
  tr += '<span class="cmdAttr" data-l1key="id"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">'
  tr += '<option value="">{{Aucune}}</option>'
  tr += '</select>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '</td>';
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var tr = $('#table_cmd tbody tr').last()
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' })
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result)
      tr.setValues(_cmd, '.cmdAttr')
      jeedom.cmd.changeType(tr, init(_cmd.subType))
    }
  })
}

/* champs propres aux plantes masqués pour une collection */
function printEqLogic(_eqLogic) { // nom retenu par GBIF à l'enregistrement (lecture seule)
  var g = (_eqLogic.configuration || {}).gbif || {}
  $('#jdc-gbif').text(g.nom ? g.nom + (g.famille ? ' · ' + g.famille : '') + ' (' + g.statut + ', ' + g.type + ' ' + g.confiance + ' %)' : (g.statut == 'NONE' ? '{{introuvable}}' : g.statut == 'ERREUR' ? '{{GBIF injoignable (nouvel essai dans 24 h)}}' : '–'))
  // emplacement renommé ou retiré de la configuration : gardé dans la liste, sinon la sauvegarde l'effacerait
  var s = document.querySelector('.eqLogicAttr[data-l2key=emplacement]'), e = (_eqLogic.configuration || {}).emplacement || ''
  s.querySelectorAll('option.jdc-anc').forEach(function (o) { o.remove() })
  if (e !== '' && !Array.prototype.some.call(s.options, function (o) { return o.value === e })) {
    var o = document.createElement('option'); o.className = 'jdc-anc'; o.value = e; o.textContent = e + ' ({{inconnu}})'; s.appendChild(o); s.value = e
  }
  var i = (_eqLogic.configuration || {}).ident || {} // calculé à l'enregistrement
  $('#jdc-ident').text(!i.groupes ? '{{rien d’observé (enregistrer après avoir choisi)}}' : (i.verdict == 'probable' ? '{{Probable}} : ' : '{{Indécis}} : ') +
    i.groupes.map(function (g) { return g.groupe + ' (' + g.accords + ' {{accord(s)}}' + (g.contre.length ? ', {{contre}} : ' + g.contre.join(', ') : '') + ')' }).join(' · '))
  jdcType()
}
function jdcType() { $('.jdc-plante').toggle($('#jdc-type').value() != 'collection') }
$('#jdc-type').on('change', jdcType)

/* export / import (fichier JSON) : rien n'est remplacé, seules les plantes et produits absents sont ajoutés */
$('#jdc-exp').on('click', function () {
  window.journalcultures.ajax('exporter', {}, function (d) {
    var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(d, null, 1)], {type: 'application/json'}))
    a.download = 'journalcultures_' + d.date.slice(0, 10) + '.json'; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href) }, 1000)
  }, function (e) { $('#div_alert').showAlert({message: e, level: 'danger'}) })
})
$('#jdc-froid-maj').on('click', function () { // téléchargement des fichiers Météo-France des départements voisins : quelques minutes
  var b = $(this); if (b.data('en')) return; b.data('en', 1).css('opacity', .5)
  $('#div_alert').showAlert({message: "{{Froid du lieu : téléchargement des données Météo-France, quelques minutes…}}", level: 'info'})
  window.journalcultures.ajax('froid', {}, function (r) {
    b.removeData('en').css('opacity', 1); $('#div_alert').hide()
    $('#jdc-froid').show().prop('open', true).find('.res').html(r.map(window.journalcultures.esc).join('<br>'))
  }, function (e) { b.removeData('en').css('opacity', 1); $('#div_alert').showAlert({message: e, level: 'danger'}) })
})
$('#jdc-imp').on('change', function () {
  var f = this.files[0], bx = $('#jdc-imp-ok'); this.value = ''; if (!f) return
  f.text().then(function (t) {
    var d = JSON.parse(t); if (d.format !== 'journalcultures' || !Array.isArray(d.plantes)) throw 0
    var ici = bx.find('select option').map(function () { return this.textContent.toLowerCase() }).get(), abs = {}
    d.plantes.forEach(function (p) { if (ici.indexOf(String(p.collection || '').toLowerCase()) < 0) abs[p.collection || '?'] = 1 })
    if (!ici.length) throw "{{Créer d'abord une collection (« Ajouter »)}}"
    bx.find('.res').text(f.name + ' : ' + d.plantes.length + ' {{plante(s)}}, ' + String(d.produits || '').split('\n').filter(function (l) { return l.trim() }).length + ' {{produit(s)}}' +
      (Object.keys(abs).length ? ' — {{collections absentes ici}} : ' + Object.keys(abs).join(', ') : ''))
    bx.find('label').toggle(Object.keys(abs).length > 0)
    bx.data('d', d).show()
  }).catch(function (e) { $('#div_alert').showAlert({message: typeof e == 'string' ? e : "{{Fichier non reconnu : export du Journal de cultures attendu}}", level: 'danger'}) })
})
$('#jdc-imp-ok .non').on('click', function () { $('#jdc-imp-ok').hide().removeData('d') })
$('#jdc-imp-ok .go').on('click', function () {
  var bx = $('#jdc-imp-ok'), b = this; b.disabled = true
  window.journalcultures.ajax('importer', {donnees: JSON.stringify(bx.data('d')), collection: bx.find('select').val()}, function (r) {
    bx.hide().removeData('d'); b.disabled = false
    var m = r.plantes + ' {{plante(s) ajoutée(s)}}, ' + r.entrees + ' {{entrée(s) du journal}}, ' + r.produits + ' {{produit(s) ajouté(s)}}' +
      (r.deja.length ? ' ; {{déjà présentes, laissées telles quelles}} : ' + r.deja.join(', ') : '') + (r.erreurs.length ? ' ; {{erreurs}} : ' + r.erreurs.join(' ; ') : '')
    $('#div_alert').showAlert({message: m, level: r.erreurs.length ? 'warning' : 'success'})
    if (r.plantes) setTimeout(function () { location.reload() }, 4000) // nouvelles cartes
  }, function (e) { b.disabled = false; $('#div_alert').showAlert({message: e, level: 'danger'}) })
})

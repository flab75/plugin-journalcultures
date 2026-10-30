/* Journal de cultures — tuile d'une collection et ses fenêtres (plante, journal, noter, déplacer). Recopié dans chaque tuile par
   journalcultures::toHtml(), chargé une seule fois par page. Textes entre doubles accolades traduits côté PHP (core/i18n). */
if (!window.journalcultures) (function () {
  var css = document.createElement('style')
  css.textContent =
    '.jdc{box-sizing:border-box;width:100%;padding:4px 6px 8px;text-align:left;font-size:12px}' +
    '.jdc table{width:100%;border-collapse:collapse}.jdc td,.jdc th{padding:2px 3px;border-bottom:1px solid rgba(128,128,128,.2)}' +
    '.jdc tr.p{cursor:pointer}.jdc tr.p:hover{background:rgba(128,128,128,.12)}.jdc .al{color:#d33;font-weight:600}.jdc .n{text-align:right;white-space:nowrap}' +
    '.jdc-r{margin-bottom:4px}.jdc-r .al{background:#b3261e;color:#fff;border-radius:8px;padding:1px 7px}' +
    '.jdc-bt{display:flex;gap:5px;justify-content:center;margin-top:6px}.jdc-bt a,.jdc-m .b{cursor:pointer;border-radius:12px;padding:2px 10px;background:rgba(128,128,128,.2)}' +
    '.jdc-m{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center}' +
    '.jdc-mb{background:var(--bg-modal-color,#fff);color:var(--txt-color,#222);width:min(800px,96vw);max-height:90vh;overflow:auto;border-radius:10px;padding:10px 14px;font-size:13px;text-align:left}' +
    '.jdc-mb nav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;align-items:center}.jdc-mb nav a{cursor:pointer;padding:3px 10px;border-radius:10px;background:rgba(128,128,128,.15)}' +
    '.jdc-mb nav a.on{background:#4a7;color:#fff}.jdc-mb nav .z{margin-left:auto}.jdc-mb nav b{margin-right:6px}' +
    '.jdc-mb table{width:100%;border-collapse:collapse}.jdc-mb td,.jdc-mb th{padding:3px 5px;border-bottom:1px solid rgba(128,128,128,.25);vertical-align:top;text-align:left}' +
    '.jdc-mb form{display:grid;grid-template-columns:auto 1fr;gap:4px 8px;align-items:center;margin-bottom:10px}' +
    '.jdc-mb input,.jdc-mb select,.jdc-mb textarea{width:100%;box-sizing:border-box}.jdc-mb input[type=checkbox]{width:auto}' +
    '.jdc-mb h4{margin:10px 0 3px}.jdc-er{color:#d33}.jdc-ph{display:flex;flex-wrap:wrap;gap:8px}.jdc-ph figure{margin:0;width:180px}' +
    '.jdc-ph img{width:180px;height:180px;object-fit:cover;border-radius:6px;cursor:zoom-in}.jdc-ph figcaption{font-size:11px}'
  document.head.appendChild(css)

  function esc(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (x) { return '&#' + x.charCodeAt(0) + ';' }) }
  function nb(v, u) { return v === null || v === undefined || v === '' ? '–' : String(Math.round(v * 10) / 10).replace('.', ',') + (u || '') }
  function auj() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2) }

  function ajax(action, champs, ok, ko, blob) {
    var f = new FormData()
    f.append('action', action); f.append('jeedom_token', window.JEEDOM_AJAX_TOKEN || '')
    Object.keys(champs).forEach(function (k) { f.append(k, champs[k]) })
    fetch('plugins/journalcultures/core/ajax/journalcultures.ajax.php', {method: 'POST', body: f, credentials: 'same-origin'})
      .then(function (r) { return blob && (r.headers.get('Content-Type') || '').indexOf('image/') == 0 ? r.blob().then(ok) : r.json().then(function (j) { if (j.state == 'ok') ok(j.result); else ko(j.result) }) })
      .catch(function (e) { ko(String(e)) })
  }

  // fenêtre à onglets : o = [[clé, titre, fonction(ct)], …]
  function fenetre(titre, o, depart) {
    var m = document.createElement('div'); m.className = 'jdc-m'
    m.innerHTML = '<div class="jdc-mb"><nav><b>' + esc(titre) + '</b>' + o.map(function (x) { return '<a data-o="' + x[0] + '">' + x[1] + '</a>' }).join('') + '<a class="z">✕</a></nav><div class="jdc-ct"></div></div>'
    document.body.appendChild(m)
    var ct = m.querySelector('.jdc-ct'), urls = []
    function fermer() { m.remove(); document.removeEventListener('keydown', touche); urls.forEach(URL.revokeObjectURL) }
    function touche(e) { if (e.key == 'Escape') fermer() }
    document.addEventListener('keydown', touche)
    m.onclick = function (e) { if (e.target == m || e.target.classList.contains('z')) fermer() }
    function montrer(k) {
      m.querySelectorAll('nav a[data-o]').forEach(function (a) { a.classList.toggle('on', a.dataset.o == k) })
      ct.textContent = "{{Chargement…}}"
      o.filter(function (x) { return x[0] == k })[0][2](ct)
    }
    m.querySelectorAll('nav a[data-o]').forEach(function (a) { a.onclick = function () { montrer(a.dataset.o) } })
    montrer(depart || o[0][0])
    return {montrer: montrer, url: function (u) { urls.push(u); return u }, fermer: fermer}
  }

  function erreur(ct) { return function (er) { ct.innerHTML = '<p class="jdc-er">' + esc(er) + '</p>' } }

  function tableJournal(d, l, avecPlante, apres) { // entrées du journal + suppression (admins)
    return '<table><tr><th>' + "{{Date}}" + '</th><th>' + "{{Travail}}" + '</th><th></th></tr>' + l.map(function (x) {
      return '<tr><td>' + esc(x.date) + '</td><td><b>' + esc(d.actions[d.actionsCle.indexOf(x.action)] || x.action) + '</b>' + (avecPlante ? ' ' + esc(x.plante) : '') +
        (x.lieu ? ' <small>(' + esc(x.lieu) + ')</small>' : '') + (x.note ? '<br><small>' + esc(x.note) + '</small>' : '') + '</td><td>' +
        (d.admin == 1 ? '<a class="jdc-del" data-id="' + esc(x.id) + '" title="' + "{{Supprimer}}" + '" style="cursor:pointer">✕</a>' : '') + '</td></tr>'
    }).join('') + '</table>' + (l.length ? '' : '<p>' + "{{Rien de noté pour l'instant.}}" + '</p>')
  }
  function brancherSuppr(ct, apres) {
    ct.querySelectorAll('.jdc-del').forEach(function (a) {
      a.onclick = function () { ajax('supprimer', {entree: a.dataset.id}, apres, function (er) { a.textContent = er }) }
    })
  }

  // formulaire d'une entrée : ids = plantes concernées ([] = entrée libre)
  function formNoter(d, ids, action, note, fini) {
    var f = document.createElement('form')
    f.innerHTML = '<label>' + "{{Date}}" + '</label><input type="date" name="date" required value="' + auj() + '">' +
      '<label>' + "{{Travail}}" + '</label><select name="action">' + d.actionsCle.map(function (a, i) { return '<option value="' + esc(a) + '"' + (a == action ? ' selected' : '') + '>' + esc(d.actions[i]) + '</option>' }).join('') + '</select>' +
      '<label>' + "{{Lieu}}" + '</label><input name="lieu" maxlength="60" list="jdc-lieux" placeholder="' + "{{emplacement de la plante}}" + '"><datalist id="jdc-lieux">' + d.emplacements.map(function (e) { return '<option value="' + esc(e) + '">' }).join('') + '</datalist>' +
      (ids.length ? '' : '<label>' + "{{Plante}}" + '</label><input name="plante" maxlength="120">') +
      '<label>' + "{{Note}}" + '</label><textarea name="note" rows="3" maxlength="2000">' + esc(note || '') + '</textarea>' +
      '<span></span><span><button type="submit">' + "{{Enregistrer}}" + '</button> <span class="jdc-er"></span></span>'
    f.onsubmit = function (ev) {
      ev.preventDefault()
      var t = {plantes: ids, collection_id: d.id}, bt = f.querySelector('button'); new FormData(f).forEach(function (v, k) { t[k] = v })
      bt.disabled = true // pas de double envoi
      ajax('noter', {entree: JSON.stringify(t)}, fini, function (er) { bt.disabled = false; f.querySelector('.jdc-er').textContent = er })
    }
    return f
  }

  var coches = {} // cases cochées par collection : gardées quand la tuile est redessinée
  function cochees(b) { return Array.prototype.map.call(b.querySelectorAll('.jdc-ck:checked'), function (c) { return c.value }) }
  function noms(d, ids) { return d.plantes.filter(function (p) { return ids.indexOf(String(p.id)) >= 0 }).map(function (p) { return p.nom }).join(', ') }

  function plante(d, id, depart) {
    var r = null, w
    function avec(fn) { return function (ct) { if (r) fn(ct); else ajax('plante', {id: id}, function (x) { r = x; fn(ct) }, erreur(ct)) } }
    function recharger(k) { r = null; w.montrer(k) }
    function charger(img, n, er) { ajax('photo', {id: id, nom: n}, function (b) { if (b instanceof Blob) { img.src = w.url(URL.createObjectURL(b)); img.onclick = function () { window.open(img.src) } } }, er, true) }
    var p = d.plantes.filter(function (x) { return x.id == id })[0] || {nom: ''}
    w = fenetre(p.nom, [
      ['fic', "{{Fiche}}", avec(function (ct) {
        var g = r.gbif || {}, s = r.etat
        ct.innerHTML = (r.etiquette ? '<p><img class="jdc-et" alt="' + "{{Étiquette}}" + '" style="max-width:100%;cursor:zoom-in"></p>' : '') + '<table>' +
          '<tr><th>' + "{{Emplacement}}" + '</th><td>' + esc(s.empl || '–') + (s.t !== null ? ' · ' + nb(s.t, ' °C') : '') + (s.tmin !== null ? ' · ' + "{{min. prévue}}" + ' ' + nb(s.tmin, ' °C') : '') + (s.h !== null ? ' · ' + nb(s.h, ' %') : '') + '</td></tr>' +
          '<tr><th>' + "{{Pot}}" + '</th><td>' + esc(r.pot) + '</td></tr>' +
          '<tr><th>' + "{{Rusticité effective}}" + '</th><td>' + (s.reff === null ? "{{inconnue (pas de rusticité dans la fiche)}}" : nb(s.reff, ' °C')) + (s.raison ? ' <b class="jdc-er">' + esc(s.raison) + '</b>' : '') + '</td></tr>' +
          (s.engrais ? '<tr><th>' + "{{Engrais cette semaine}}" + '</th><td>' + esc(s.engrais) + '</td></tr>' : '') +
          (g.nom ? '<tr><th>GBIF</th><td>' + esc(g.nom) + (g.famille ? ' · ' + esc(g.famille) : '') + ' <small>(' + esc(g.statut) + ', ' + esc(g.type) + ' ' + esc(g.confiance) + ' %)</small></td></tr>' :
            g.statut == 'NONE' ? '<tr><th>GBIF</th><td class="jdc-er">' + "{{nom botanique introuvable}}" + '</td></tr>' : '') +
          r.fiche.map(function (x) { return '<tr><th>' + esc(x[0]) + '</th><td style="white-space:pre-wrap">' + esc(x[1]) + '</td></tr>' }).join('') + '</table>' +
          (r.ident && r.ident.groupes ? '<h4>' + "{{Identification}}" + '</h4><p>' + (r.ident.verdict == 'probable' ? "{{Groupe probable}}" : "{{Indécis entre}}") + ' : ' +
            r.ident.groupes.map(function (g) { return '<b>' + esc(g.groupe) + '</b> <small>(' + g.accords + ' ' + "{{accord(s)}}" + (g.contre.length ? ', ' + "{{contre}}" + ' : ' + esc(g.contre.join(', ')) : '') + ')</small>' }).join(' · ') +
            '</p><table>' + r.carac.map(function (x) { return '<tr><th>' + esc(x[0]) + '</th><td>' + esc(x[1]) + '</td></tr>' }).join('') + '</table><p><small>' +
            "{{Groupe seulement, jamais la variété. Comparer :}}" + ' <a href="https://idtools.org/id/citrus/citrusid/" target="_blank" rel="noopener noreferrer">Citrus ID</a> · ' +
            '<a href="https://citrusvariety.ucr.edu/" target="_blank" rel="noopener noreferrer">UC Riverside</a></small></p>' : '') +
          (r.liens.length ? '<h4>' + "{{Sources en ligne}}" + '</h4>' + r.liens.map(function (l) { return '<a href="' + esc(l[1]) + '" target="_blank" rel="noopener noreferrer">' + esc(l[0]) + '</a>' }).join(' · ') : '') +
          (d.admin == 1 ? '<p><small>' + "{{La fiche se modifie dans la page du plugin (équipement de la plante).}}" + '</small></p>' : '')
        var ie = ct.querySelector('.jdc-et')
        if (ie) charger(ie, r.etiquette, function () { ie.parentNode.remove() })
      })],
      ['jou', "{{Journal}}", avec(function (ct) {
        ct.innerHTML = ''
        if (d.admin == 1) ct.appendChild(formNoter(d, [String(id)], 'Observation', '', function () { recharger('jou') }))
        ct.insertAdjacentHTML('beforeend', tableJournal(d, r.journal, false))
        brancherSuppr(ct, function () { recharger('jou') })
      })],
      ['pho', "{{Photos}}", avec(function (ct) {
        ct.innerHTML = (d.admin == 1 ? '<form><label>' + "{{Ajouter}}" + '</label><span><input type="file" accept="image/jpeg,image/png,image/webp,image/*"> <span class="jdc-er"></span></span></form>' : '') +
          '<div class="jdc-ph">' + r.photos.map(function (n) {
            return '<figure data-n="' + esc(n) + '"><img alt=""><figcaption>' + esc(n.slice(6, 8) + '/' + n.slice(4, 6) + '/' + n.slice(0, 4)) +
              (d.admin == 1 ? (r.plantnet == 1 ? ' · <select class="org"><option value="auto">' + "{{auto}}" + '</option><option value="leaf">' + "{{feuille}}" + '</option><option value="flower">' + "{{fleur}}" + '</option><option value="fruit">' + "{{fruit}}" + '</option><option value="bark">' + "{{écorce}}" + '</option></select> <a class="b idf">Pl@ntNet</a>' : '') +
                ' <a class="b eti">' + (n == r.etiquette ? "{{★ étiquette}}" : "{{☆ étiquette}}") + '</a> <a class="b sup" title="' + "{{Supprimer}}" + '">✕</a>' : n == r.etiquette ? ' · ' + "{{étiquette}}" : '') + '<div class="res"></div></figcaption></figure>'
          }).join('') + '</div>' + (r.photos.length ? '' : '<p>' + "{{Aucune photo.}}" + '</p>')
        ct.querySelectorAll('.jdc-ph figure').forEach(function (fg) {
          var n = fg.dataset.n, img = fg.querySelector('img'), res = fg.querySelector('.res')
          charger(img, n, function (er) { res.textContent = er })
          var s = fg.querySelector('.sup'), i = fg.querySelector('.idf'), et = fg.querySelector('.eti')
          if (et) et.onclick = function () { ajax('etiquette', {id: id, nom: n == r.etiquette ? '' : n}, function () { recharger('pho') }, function (er) { res.textContent = er }) } // ★ = retirer
          if (s) s.onclick = function () { if (s.dataset.ok) ajax('photo_suppr', {id: id, nom: n}, function () { recharger('pho') }, function (er) { res.textContent = er }); else { s.dataset.ok = 1; s.textContent = "{{Confirmer ✕}}" } }
          if (i) i.onclick = function () {
            res.textContent = "{{Identification…}}"
            ajax('identifier', {id: id, nom: n, organe: fg.querySelector('.org').value}, function (l) {
              res.innerHTML = l.length ? l.map(function (x) { return '<div><b>' + esc(x.score) + ' %</b> <i>' + esc(x.nom) + '</i> ' + esc(x.auteur) + (x.communs ? ' — ' + esc(x.communs) : '') + ' <small>' + esc(x.famille) + '</small></div>' }).join('') : "{{Aucune espèce reconnue.}}"
            }, function (er) { res.textContent = er })
          }
        })
        var fi = ct.querySelector('input[type=file]')
        if (fi) fi.onchange = function () {
          if (!fi.files.length) return
          var e = ct.querySelector('form .jdc-er'); e.textContent = "{{Envoi…}}"; fi.disabled = true
          ajax('photo_ajout', {id: id, photo: fi.files[0]}, function () { recharger('pho') }, function (er) { fi.disabled = false; e.textContent = er })
        }
      })],
      ['mes', "{{Mesures}}", avec(function (ct) {
        var c = r.cmds, k = ['hauteur', 'tronc', 'feuilles', 'fruits'].filter(function (x) { return c[x] }) // commande supprimée : pas de champ
        ct.innerHTML = (d.admin == 1 ? '<form>' + k.map(function (x) { return '<label>' + esc(c[x].nom) + (c[x].unite ? ' (' + esc(c[x].unite) + ')' : '') + '</label><input name="' + x + '" inputmode="decimal" placeholder="' + esc(nb(c[x].val)) + '">' }).join('') +
          '<span></span><span><button type="submit">' + "{{Enregistrer}}" + '</button> <span class="jdc-er"></span></span></form>' : '') +
          '<h4>' + "{{Courbes}}" + '</h4><table>' + Object.keys(c).filter(function (x) { return x != 'raison' && x != 'engrais' }).map(function (x) {
            return '<tr><td>' + esc(c[x].nom) + '</td><td class="n">' + (x == 'emplacement' ? esc(c[x].val) : nb(c[x].val, c[x].unite ? ' ' + c[x].unite : '')) + '</td><td><a class="b hist" data-id="' + esc(c[x].id) + '" data-nom="' + esc(c[x].nom) + '">' + "{{Historique}}" + '</a></td></tr>'
          }).join('') + '</table>'
        var f = ct.querySelector('form')
        if (f) f.onsubmit = function (ev) {
          ev.preventDefault()
          var t = {}, bt = f.querySelector('button'); new FormData(f).forEach(function (v, x) { t[x] = v }); bt.disabled = true
          ajax('mesurer', {id: id, mesures: JSON.stringify(t)}, function () { recharger('mes') }, function (er) { bt.disabled = false; f.querySelector('.jdc-er').textContent = er })
        }
        ct.querySelectorAll('.hist').forEach(function (a) {
          a.onclick = function () { // fenêtre d'historique du core par-dessus la nôtre
            var u = 'index.php?v=d&modal=cmd.history&id=' + encodeURIComponent(a.dataset.id)
            if (window.jeeDialog) { w.fermer(); jeeDialog.dialog({id: 'md_cmdHistory', title: "{{Historique}}" + ' : ' + a.dataset.nom, contentUrl: u}) } else window.open(u)
          }
        })
      })]
    ], depart)
  }

  window.journalcultures = {init: function (b, d) {
    if (!b) return
    var al = d.plantes.filter(function (p) { return p.alerte == 1 }), eng = d.plantes.filter(function (p) { return p.engrais })
    b.innerHTML = '<div class="jdc-r">' + d.plantes.length + ' ' + "{{plantes}}" + (al.length ? ' · <span class="al">' + al.length + ' ' + "{{en alerte}}" + '</span>' : '') +
      (eng.length ? ' · ' + eng.length + ' ' + "{{engrais à donner}}" : '') + '</div>' +
      '<table><tr>' + (d.admin == 1 ? '<th><input type="checkbox" class="jdc-tt" title="' + "{{Tout cocher}}" + '"></th>' : '') + '<th>' + "{{Plante}}" + '</th><th>' + "{{Lieu}}" + '</th><th class="n">T°</th><th class="n" title="' + "{{Rusticité effective (pot compris)}}" + '">' + "{{Rust.}}" + '</th><th></th></tr>' +
      d.plantes.map(function (p) {
        return '<tr class="p" data-id="' + esc(p.id) + '">' + (d.admin == 1 ? '<td><input type="checkbox" class="jdc-ck" value="' + esc(p.id) + '"></td>' : '') +
          '<td' + (p.alerte == 1 ? ' class="al"' : '') + (p.raison ? ' title="' + esc(p.raison) + '"' : '') + '>' + esc(p.nom) + (p.raison && (p.alerte != 1 || p.muette) ? ' <span class="al">⚠</span>' : '') + '</td><td>' + esc(p.empl || '–') + '</td><td class="n">' + nb(p.t, '°') + '</td>' +
          '<td class="n">' + nb(p.reff, '°') + '</td><td>' + (p.engrais ? '<span title="' + esc(p.engrais) + '">🌱</span>' + (d.admin == 1 ? ' <a class="jdc-eng" title="' + "{{Engrais donné}}" + '" style="cursor:pointer">✓</a>' : '') : '') + '</td></tr>'
      }).join('') + '</table>' + (d.plantes.length ? '' : '<p>' + "{{Aucune plante : en créer dans la page du plugin et choisir cette collection.}}" + '</p>') +
      '<div class="jdc-bt">' + (d.admin == 1 ? '<a data-b="noter">＋ ' + "{{Noter}}" + '</a><a data-b="deplacer">' + "{{Déplacer…}}" + '</a>' : '') + '<a data-b="journal">' + "{{Journal}}" + '</a></div>'

    var tt = b.querySelector('.jdc-tt'), mem = coches[d.id] || (coches[d.id] = {})
    b.querySelectorAll('.jdc-ck').forEach(function (c) { c.checked = !!mem[c.value]; c.onchange = function () { mem[c.value] = c.checked } })
    if (tt) tt.onclick = function () { b.querySelectorAll('.jdc-ck').forEach(function (c) { c.checked = tt.checked; mem[c.value] = c.checked }) }
    b.querySelectorAll('tr.p').forEach(function (tr) {
      tr.onclick = function (e) {
        if (e.target.classList.contains('jdc-ck')) return
        if (e.target.classList.contains('jdc-eng')) { // rappel d'engrais coché : entrée au journal avec le produit prévu
          var p = d.plantes.filter(function (x) { return x.id == tr.dataset.id })[0]
          if (e.target.dataset.envoi) return // double tap : une seule entrée
          e.target.dataset.envoi = 1; e.target.textContent = '…'
          ajax('noter', {entree: JSON.stringify({plantes: [tr.dataset.id], date: auj(), action: 'Engrais', note: p.engrais})}, function () {}, function (er) { delete e.target.dataset.envoi; e.target.textContent = er })
          return
        }
        plante(d, tr.dataset.id)
      }
    })
    b.querySelectorAll('.jdc-bt a').forEach(function (a) {
      a.onclick = function () {
        var ids = cochees(b)
        if (a.dataset.b == 'journal') return fenetre("{{Journal}}", [['j', "{{Toutes les plantes}}", function charger(ct) {
          ajax('journal', {id: d.id}, function (l) { ct.innerHTML = tableJournal(d, l, true); brancherSuppr(ct, function () { charger(ct) }) }, erreur(ct))
        }]])
        if (a.dataset.b == 'noter') return fenetre("{{Noter}}", [['n', ids.length ? noms(d, ids) : "{{Entrée libre (cocher des plantes pour les viser)}}", function (ct) {
          ct.innerHTML = ''; ct.appendChild(formNoter(d, ids, 'Observation', '', function () { ct.innerHTML = '<p>' + "{{Noté.}}" + '</p>' }))
        }]])
        fenetre("{{Déplacer}}", [['d', ids.length ? noms(d, ids) : "{{cocher des plantes}}", function (ct) {
          if (!ids.length) { ct.innerHTML = '<p>' + "{{Cochez d'abord les plantes à déplacer dans la tuile.}}" + '</p>'; return }
          ct.innerHTML = '<form><label>' + "{{Vers}}" + '</label><select>' + d.emplacements.map(function (e) { return '<option value="' + esc(e) + '">' + esc(e) + '</option>' }).join('') + '</select>' +
            '<span></span><span><button type="submit">' + "{{Déplacer}}" + '</button> <span class="jdc-er"></span></span></form><p><small>' + "{{Le déplacement est noté au journal de chaque plante.}}" + '</small></p>'
          var f = ct.querySelector('form'), bt = f.querySelector('button')
          f.onsubmit = function (ev) {
            ev.preventDefault(); bt.disabled = true
            ajax('deplacer', {plantes: JSON.stringify(ids), emplacement: f.querySelector('select').value}, function (n) { ct.innerHTML = '<p>' + n + ' ' + "{{plante(s) déplacée(s).}}" + '</p>' }, function (er) { bt.disabled = false; f.querySelector('.jdc-er').textContent = er })
          }
        }]])
      }
    })
  }}
})()

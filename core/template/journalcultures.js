/* Journal de cultures — tuile d'une collection et ses fenêtres (plante, journal, noter, déplacer). Recopié dans chaque tuile par
   journalcultures::toHtml(), chargé une seule fois par page. Textes entre doubles accolades traduits côté PHP (core/i18n). */
if (!window.journalcultures || window.journalcultures.v !== 6) (function () { // v : un onglet ouvert avant une mise à jour recharge la bibliothèque
  var css = document.getElementById('jdc-css') || document.createElement('style')
  css.id = 'jdc-css'
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
    '.jdc-ph img{width:180px;height:180px;object-fit:cover;border-radius:6px;cursor:zoom-in}.jdc-ph figcaption{font-size:11px}' +
    '.jdc-m.jdc-in{position:static;background:none;display:block}.jdc-in .jdc-mb{width:auto;max-height:none}' + // fenêtre posée dans le panel (écran large)
    '.jdc-org{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px}.jdc-org a,.jdc-pil span{cursor:pointer;display:inline-block;border-radius:16px;padding:4px 11px;border:1px solid rgba(128,128,128,.4)}' +
    '.jdc-org a.on,.jdc-pil input:checked+span{background:#2F6B3A;border-color:#2F6B3A;color:#fff}.jdc-pil{position:relative;margin:0 4px 4px 0}.jdc-mb .jdc-pil input{position:absolute;opacity:0;width:1px;height:1px;min-height:0;margin:0}.jdc-pil input:focus-visible+span{outline:2px solid #2F6B3A}' +
    '.jdc-mb form.jdc-mf{display:block}.jdc-g{display:none}.jdc-g.on{display:block}.jdc-mf fieldset{border:0;padding:0;margin:0 0 6px}.jdc-mf legend{font-size:inherit;border:0;margin:0 0 3px;width:auto}' +
    '.jdc-lg{display:flex;align-items:center;gap:8px;margin:0 0 6px;font-weight:normal}.jdc-lg>span{flex:1}.jdc-lg input{width:110px;text-align:right}.jdc-lg small{width:42px}.jdc-lg textarea{flex:2}' +
    '.jdc-svg svg{width:100%;height:auto;max-height:220px}' +
    '.jdc-np details,.jdc-np .rec{grid-column:1/-1}.jdc-np summary{cursor:pointer;margin:4px 0}.jdc-np fieldset{border:0;padding:0;margin:0 0 6px}.jdc-np legend{font-size:inherit;border:0;margin:0 0 3px;width:auto}' +
    '@media (max-width:600px){.jdc-m{align-items:stretch}.jdc-mb{width:100vw;max-height:none;height:100%;border-radius:0;font-size:15px}.jdc-mb nav a{padding:8px 12px}' +
    '.jdc-mb input,.jdc-mb select,.jdc-mb textarea{font-size:16px;min-height:40px}.jdc-mb button{min-height:44px}.jdc .xs{display:none}}'
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
    var cible = window.journalcultures.cible // panel : à côté de la liste (pas si on a quitté le panel, ni sur écran étroit)
    if (!cible || !cible.isConnected || window.innerWidth <= 900) cible = null
    var m = document.createElement('div'); m.className = 'jdc-m' + (cible ? ' jdc-in' : '')
    if (cible) { if (posee) posee.fermer(); cible.innerHTML = '' }
    m.innerHTML = '<div class="jdc-mb"><nav><b>' + esc(titre) + '</b>' + o.map(function (x) { return '<a data-o="' + x[0] + '">' + esc(x[1]) + '</a>' }).join('') + '<a class="z">✕</a></nav><div class="jdc-ct"></div></div>';
    (cible || document.body).appendChild(m)
    var ct = m.querySelector('.jdc-ct'), urls = []
    function fermer() { m.remove(); document.removeEventListener('keydown', touche); urls.forEach(URL.revokeObjectURL) }
    function touche(e) { if (e.key == 'Escape' && !cible && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) fermer() } // posée dans le panel : ✕ seulement
    document.addEventListener('keydown', touche)
    m.onclick = function (e) { if (e.target == m || e.target.classList.contains('z')) fermer() }
    function montrer(k) {
      m.querySelectorAll('nav a[data-o]').forEach(function (a) { a.classList.toggle('on', a.dataset.o == k) })
      ct.textContent = "{{Chargement…}}"
      o.filter(function (x) { return x[0] == k })[0][2](ct)
    }
    m.querySelectorAll('nav a[data-o]').forEach(function (a) { a.onclick = function () { montrer(a.dataset.o) } })
    montrer(depart || o[0][0])
    var f = {montrer: montrer, url: function (u) { urls.push(u); return u }, fermer: fermer}
    if (cible) posee = f
    return f
  }
  var posee = null // fenêtre posée dans le panel : fermée (écouteur, photos) avant d'en poser une autre

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
    var r = null, w, msg = ''
    function avec(fn) { return function (ct) { if (r) fn(ct); else ajax('plante', {id: id}, function (x) { r = x; fn(ct) }, erreur(ct)) } }
    function recharger(k) { r = null; w.montrer(k) }
    function charger(img, n, er) { ajax('photo', {id: id, nom: n}, function (b) { if (b instanceof Blob) { var u = w.url(URL.createObjectURL(b)), fr = new FileReader(); fr.onload = function () { img.src = fr.result }; fr.readAsDataURL(b); img.onclick = function () { window.open(u) } } }, er, true) } // CSP de Jeedom : img-src refuse blob: → data: pour l'image, blob: pour l'ouvrir en grand
    var p = d.plantes.filter(function (x) { return x.id == id })[0] || {nom: ''}
    w = fenetre(p.nom, [
      ['fic', "{{Fiche}}", avec(function (ct) {
        var g = r.gbif || {}, s = r.etat
        ct.innerHTML = (r.etiquette ? '<p><img class="jdc-et" alt="' + "{{Étiquette}}" + '" style="max-width:100%;cursor:zoom-in"></p>' : '') + '<table>' +
          '<tr><th>' + "{{Emplacement}}" + '</th><td>' + esc(s.empl || '–') + (s.t !== null ? ' · ' + nb(s.t, ' °C') : '') + (s.tmin !== null ? ' · ' + "{{min. prévue}}" + ' ' + nb(s.tmin, ' °C') : '') + (s.h !== null ? ' · ' + nb(s.h, ' %') : '') + '</td></tr>' +
          '<tr><th>' + "{{Pot}}" + '</th><td>' + esc(r.pot) + '</td></tr>' +
          (r.multiplication ? '<tr><th>' + "{{Multiplication}}" + '</th><td>' + esc(r.multiplication) + '</td></tr>' : '') +
          '<tr><th>' + "{{Rusticité effective}}" + '</th><td>' + (s.reff === null ? "{{inconnue (pas de rusticité dans la fiche)}}" : nb(s.reff, ' °C')) +
            (s.reff !== null && s.estimee ? ' <small>(' + "{{estimée}}" + ' ' + nb(s.estimee.r, ' °C') + ' : ' + esc(s.estimee.groupes.join(', ')) + (s.estimee.par == 'nom' ? '' : ', ' + "{{d'après les caractères}}") + ')</small>' : '') + (s.raison ? ' <b class="jdc-er">' + esc(s.raison) + '</b>' : '') + '</td></tr>' +
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
          (d.admin == 1 ? '<p><small>' + "{{La fiche se modifie dans la page du plugin (équipement de la plante).}}" + '</small> <a class="b grf" title="' +
            "{{La plante devient le greffon : nom, fiche et collection changent ; journal, photos, pot et mesures sont gardés}}" + '">' + "{{Greffer…}}" + '</a> <a class="b idn">' + "{{Identifier…}}" + '</a></p>' : '')
        var gb = ct.querySelector('.grf')
        if (gb) gb.onclick = function () { w.fermer(); nouvelle(d, d.apres, {id: id, nom: r.nom}) }
        var ib = ct.querySelector('.idn')
        if (ib) ib.onclick = function () { w.fermer(); identifier(d, id, r) }
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
        var c = r.cmds, M = r.mesures, org = Object.keys(M)[0], h = msg ? '<p><b>' + esc(msg) + '</b></p>' : ''; msg = ''
        var lm = r.journal.filter(function (x) { return x.action == 'Mesure' && x.valeurs }).reverse() // plus anciennes d'abord
        var ks = []; lm.forEach(function (x) { Object.keys(x.valeurs).forEach(function (k) { if (typeof x.valeurs[k] == 'number' && ks.indexOf(k) < 0) ks.push(k) }) })
        function lib(k) {
          var o = k.split('.'), f = o[0] == 'calc' ? r.calculs[k] && [0, r.calculs[k][0], r.calculs[k][1]] : M[o[0]] && M[o[0]][1][o[1]]
          return f ? (o[0] == 'calc' ? '' : M[o[0]][0] + ' · ') + f[1] + (f[2] && f[0] != 'c' ? ' (' + f[2] + ')' : '') : k
        }
        if (d.admin == 1) h += '<div class="jdc-org">' + Object.keys(M).map(function (o) { return '<a data-o="' + o + '" tabindex="0">' + esc(M[o][0]) + '</a>' }).join('') + '</div>' +
          '<form class="jdc-mf">' + Object.keys(M).map(function (o) {
            return '<div class="jdc-g" data-o="' + o + '">' + Object.keys(M[o][1]).map(function (k) {
              var f = M[o][1][k], n = o + '.' + k
              if (f[0] == 'c') return '<fieldset><legend>' + esc(f[1]) + '</legend>' + ['–'].concat(f[2]).map(function (v, i) {
                return '<label class="jdc-pil"><input type="radio" name="' + esc(n) + '" value="' + (i ? esc(v) : '') + '"' + (i ? '' : ' checked') + '><span>' + esc(v) + '</span></label>'
              }).join('') + '</fieldset>'
              return '<label class="jdc-lg"><span>' + esc(f[1]) + '</span><input name="' + esc(n) + '"' + (f[0] == 'l' ? ' placeholder="32 35,5 41" title="' + "{{un espace entre deux fruits}}" + '"' : ' inputmode="decimal"') + '><small>' + esc(f[2]) + '</small></label>'
            }).join('') + '</div>'
          }).join('') +
          '<label class="jdc-lg"><span>' + "{{Note}}" + '</span><textarea name="note" rows="2" maxlength="1000"></textarea></label>' +
          '<label class="jdc-lg"><span>' + "{{Photo (facultatif)}}" + '</span><input type="file" accept="image/jpeg,image/png,image/webp,image/*" style="width:auto"></label>' +
          '<p><button type="submit">' + "{{Enregistrer au journal}}" + '</button> <span class="jdc-er"></span></p><p><small>' + "{{Les valeurs saisies dans tous les organes partent ensemble.}}" + '</small></p></form>'
        h += '<h4>' + "{{Courbe}}" + '</h4>' + (ks.length ? '<select class="jdc-cs">' + ks.map(function (k) { return '<option value="' + esc(k) + '">' + esc(lib(k)) + '</option>' }).join('') + '</select><div class="jdc-svg"></div>' :
          '<p><small>' + "{{Aucune mesure du catalogue pour l'instant.}}" + '</small></p>') +
          '<h4>' + "{{Historiques}}" + '</h4><table>' + Object.keys(c).filter(function (x) { return x != 'raison' && x != 'engrais' }).map(function (x) {
            return '<tr><td>' + esc(c[x].nom) + '</td><td class="n">' + (x == 'emplacement' ? esc(c[x].val) : nb(c[x].val, c[x].unite ? ' ' + c[x].unite : '')) + '</td><td><a class="b hist" data-id="' + esc(c[x].id) + '" data-nom="' + esc(c[x].nom) + '">' + "{{Historique}}" + '</a></td></tr>'
          }).join('') + '</table>'
        ct.innerHTML = h
        function organe(o) { ct.querySelectorAll('.jdc-org a, .jdc-g').forEach(function (e) { e.classList.toggle('on', e.dataset.o == o) }) }
        ct.querySelectorAll('.jdc-org a').forEach(function (a) { a.onclick = function () { organe(a.dataset.o) }; a.onkeydown = function (e) { if (e.key == 'Enter') organe(a.dataset.o) } })
        organe(org)
        var cs = ct.querySelector('.jdc-cs')
        function courbe() { // ponytail: SVG simple (points reliés, min et max) ; une bibliothèque de graphes si on veut superposer des mesures
          var k = cs.value, p = lm.filter(function (x) { return typeof x.valeurs[k] == 'number' }).map(function (x) { return [Date.parse(x.date), x.valeurs[k], x.date] })
          var W = 600, H = 170, g = 34, t0 = p[0][0], t1 = p[p.length - 1][0], vs = p.map(function (x) { return x[1] }), v0 = Math.min.apply(null, vs), v1 = Math.max.apply(null, vs)
          function X(t) { return t1 > t0 ? g + (t - t0) / (t1 - t0) * (W - 2 * g) : W / 2 }
          function Y(v) { return v1 > v0 ? H - g - (v - v0) / (v1 - v0) * (H - 2 * g) : H / 2 }
          ct.querySelector('.jdc-svg').innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(lib(k)) + '"><g fill="none" stroke="currentColor" stroke-opacity=".25"><line x1="' + g + '" y1="' + (H - g) + '" x2="' + (W - g) + '" y2="' + (H - g) + '"/></g>' +
            '<polyline fill="none" stroke="#2F6B3A" stroke-width="2" points="' + p.map(function (x) { return X(x[0]) + ',' + Y(x[1]) }).join(' ') + '"/>' +
            p.map(function (x) { return '<circle cx="' + X(x[0]) + '" cy="' + Y(x[1]) + '" r="4" fill="#2F6B3A"><title>' + esc(x[2] + ' : ' + nb(x[1])) + '</title></circle>' }).join('') +
            '<g fill="currentColor" font-size="12"><text x="2" y="' + (Y(v1) + 4) + '">' + nb(v1) + '</text>' + (v1 > v0 ? '<text x="2" y="' + (Y(v0) + 4) + '">' + nb(v0) + '</text>' : '') +
            '<text x="' + g + '" y="' + (H - 8) + '">' + esc(p[0][2]) + '</text>' + (p.length > 1 ? '<text x="' + (W - g) + '" y="' + (H - 8) + '" text-anchor="end">' + esc(p[p.length - 1][2]) + '</text>' : '') + '</g></svg>'
        }
        if (cs) { cs.onchange = courbe; courbe() }
        var f = ct.querySelector('form.jdc-mf')
        if (f) f.onsubmit = function (ev) {
          ev.preventDefault()
          var v = {}, fd = new FormData(f), ph = f.querySelector('input[type=file]').files[0], bt = f.querySelector('button'), er = f.querySelector('.jdc-er')
          fd.forEach(function (x, k) { if (k.indexOf('.') > 0 && x !== '') v[k] = x })
          bt.disabled = true; er.textContent = '' // pas de double envoi
          ajax('mesurer', {id: id, mesures: JSON.stringify({v: v, note: fd.get('note') || ''})}, function () {
            msg = "{{Mesure notée au journal.}}"
            if (!ph) return recharger('mes')
            ajax('photo_ajout', {id: id, photo: ph}, function () { recharger('mes') }, function (e) { msg += ' ' + "{{Photo refusée :}}" + ' ' + e; recharger('mes') })
          }, function (e) { bt.disabled = false; er.textContent = e })
        }
        ct.querySelectorAll('.hist').forEach(function (a) {
          a.onclick = function () { // fenêtre d'historique du core par-dessus la nôtre
            var u = 'index.php?v=d&modal=cmd.history&id=' + encodeURIComponent(a.dataset.id)
            if (window.jeeDialog) { if (w !== posee) w.fermer(); jeeDialog.dialog({id: 'md_cmdHistory', title: "{{Historique}}" + ' : ' + a.dataset.nom, contentUrl: u}) } else window.open(u)
          }
        })
      })]
    ], depart)
  }

  var IMG = 'image/jpeg,image/png,image/webp,image/*'
  function orgHtml() { return '<select class="org" style="width:auto"><option value="auto">' + "{{auto}}" + '</option><option value="leaf">' + "{{feuille}}" + '</option><option value="flower">' + "{{fleur}}" +
    '</option><option value="fruit">' + "{{fruit}}" + '</option><option value="bark">' + "{{écorce}}" + '</option></select>' }
  function cleHtml(C, val) { // clé des caractères en pastilles ; val = {caractère: code} coché au départ
    return Object.keys(C).map(function (k) {
      return '<fieldset><legend>' + esc(C[k][0]) + '</legend>' + [['', '–']].concat(Object.keys(C[k][1]).map(function (v) { return [v, C[k][1][v]] })).map(function (o) {
        return '<label class="jdc-pil"><input type="radio" name="id_' + esc(k) + '" value="' + esc(o[0]) + '"' + (o[0] == (val[k] || '') ? ' checked' : '') + '><span>' + esc(o[1]) + '</span></label>'
      }).join('') + '</fieldset>'
    }).join('')
  }
  function caracDe(f) { var c = {}; f.querySelectorAll('details input:checked').forEach(function (i) { if (i.value) c[i.name.slice(3)] = i.value }); return c }
  function recHtml(x) { // groupe probable et rusticité estimée (réponse de « reconnaitre »)
    var id = x.ident, e = x.estimee
    return (id && id.groupes ? '<p>' + (id.verdict == 'probable' ? "{{Groupe probable}}" : "{{Indécis entre}}") + ' : ' +
      id.groupes.map(function (g) { return '<b>' + esc(g.groupe) + '</b> <small>(' + g.accords + ' ' + "{{accord(s)}}" + (g.contre.length ? ', ' + "{{contre}}" + ' : ' + esc(g.contre.join(', ')) : '') + ')</small>' }).join(' · ') + '</p>' : '') +
      (e ? '<p>' + "{{Rusticité estimée}}" + ' <b>' + nb(e.r, ' °C') + '</b> <small>(' + esc(e.groupes.join(', ')) + ', ' + (e.par == 'nom' ? "{{d'après le nom botanique}}" : "{{d'après les caractères}}") + ')</small></p>' : '')
  }
  function plantnetHtml(l) { // espèces proposées, chacune avec « Retenir »
    return l.length ? l.map(function (x) {
      return '<div><a class="b uti" data-nom="' + esc(x.nom) + '" data-commun="' + esc(x.communs.split(', ')[0]) + '" data-score="' + esc(x.score) + '">' + "{{Retenir}}" + '</a> <b>' + esc(x.score) + ' %</b> <i>' + esc(x.nom) + '</i> ' + esc(x.auteur) + (x.communs ? ' — ' + esc(x.communs) : '') + '</div>'
    }).join('') : "{{Aucune espèce reconnue.}}"
  }

  /* « Identifier… » (fiche, admins) : Pl@ntNet sur une photo de la plante ou une nouvelle, clé des caractères partant de la fiche,
     puis tableau des champs qui changeraient ; rien n'est coché d'office, seules les lignes cochées sont écrites (et notées au journal). */
  function identifier(d, id, r) {
    var B = r.brut, w = fenetre("{{Identifier}}" + ' ' + r.nom, [['i', "{{Identification}}", function (ct) {
      ajax('modele', {}, function (M) {
        ct.innerHTML = '<form class="jdc-np">' +
          '<label>' + "{{Photo}}" + '</label><span>' + (M.plantnet == 1 ? '<select name="ph" style="width:auto">' + r.photos.map(function (n) { return '<option value="' + esc(n) + '">' + esc(n) + '</option>' }).join('') +
            '<option value="">' + "{{nouvelle photo…}}" + '</option></select> <input type="file" name="photo" accept="' + IMG + '" style="width:auto"> ' + orgHtml() + ' <a class="b idf">Pl@ntNet</a>' :
            "{{Pl@ntNet : clé absente (configuration du plugin)}}") + '<div class="res"></div></span>' +
          '<label>' + "{{Nom botanique}}" + '</label><input name="bota" maxlength="120" value="' + esc(B.bota) + '">' +
          '<details open><summary>' + "{{Clé d'identification : cocher ce qu'on voit}}" + '</summary>' + cleHtml(M.caracteres, B.carac) + '</details><div class="rec"></div>' +
          '<h4 style="grid-column:1/-1">' + "{{Propositions : cocher ce qu'on accepte}}" + '</h4><div class="pro" style="grid-column:1/-1"></div>' +
          '<span></span><span><button type="submit">' + "{{Appliquer}}" + '</button> <span class="jdc-er"></span></span></form>'
        var f = ct.querySelector('form'), F = f.elements, rec = f.querySelector('.rec'), pro = f.querySelector('.pro'), res = f.querySelector('.res')
        var n = 0, fait = 0, est = null, retenu = null, coch = {}, lignes = [] // coch : « champ␁valeur », une case ne suit pas une valeur qui change
        function majFichier() { if (F.ph) F.photo.style.display = F.ph.value ? 'none' : '' }
        if (F.ph) { F.ph.onchange = majFichier; majFichier() }
        function propositions() { // [clé, libellé, actuel, proposé (affiché), valeur écrite, source]
          var C = M.caracteres, c = caracDe(f), b = F.bota.value.trim(), L = []
          if (b && b != B.bota) L.push(['bota', "{{Nom botanique}}", B.bota, b, b, retenu && retenu.nom == b ? 'Pl@ntNet ' + retenu.score + ' %' : ''])
          Object.keys(c).forEach(function (k) { if (c[k] != (B.carac[k] || '')) L.push(['id_' + k, C[k][0], B.carac[k] ? C[k][1][B.carac[k]] : '', C[k][1][c[k]], c[k], '']) })
          var m = String(B.rusticite).replace(/[−–]/g, '-').match(/-?\s*\d+(?:[.,]\d+)?/), ra = m ? parseFloat(m[0].replace(/\s/g, '').replace(',', '.')) : null // comme nombre() côté serveur
          if (est && ra !== est.r) { var v = nb(est.r) + ' (' + "{{estimée}}" + ')' // court (40 caractères au plus) ; les groupes vont dans la source, donc au journal
            L.push(['rusticite', "{{Rusticité (°C, pleine terre)}}", B.rusticite, v, v, (est.par == 'nom' ? "{{d'après le nom botanique}}" : "{{d'après les caractères}}") + ' : ' + est.groupes.join(', ')]) }
          lignes = L
          pro.innerHTML = L.length ? '<table><tr><th></th><th>' + "{{Champ}}" + '</th><th>' + "{{Actuel}}" + '</th><th>' + "{{Proposé}}" + '</th><th>' + "{{Source}}" + '</th></tr>' + L.map(function (x) {
            return '<tr><td><input type="checkbox" data-k="' + esc(x[0] + '\u0001' + x[4]) + '"' + (coch[x[0] + '\u0001' + x[4]] ? ' checked' : '') + '></td><td>' + esc(x[1]) + '</td><td>' + esc(x[2] || '–') + '</td><td><b>' + esc(x[3]) + '</b></td><td><small>' + esc(x[5]) + '</small></td></tr>'
          }).join('') + '</table>' : '<p>' + "{{Rien ne change par rapport à la fiche.}}" + '</p>'
          pro.querySelectorAll('input').forEach(function (i) { i.onchange = function () { coch[i.dataset.k] = i.checked } })
        }
        function reconnaitre() {
          var k = ++n
          ajax('reconnaitre', {carac: JSON.stringify(caracDe(f)), bota: F.bota.value}, function (x) {
            if (k != n) return // réponse dépassée par une saisie plus récente
            fait = k; est = x.estimee; rec.innerHTML = recHtml(x); propositions()
          }, function (er) { if (k == n) rec.textContent = er })
        }
        f.querySelector('details').onchange = reconnaitre; F.bota.onchange = reconnaitre
        f.onkeydown = function (e) { if (e.key == 'Enter' && e.target.tagName == 'INPUT') { e.preventDefault(); if (e.target == F.bota) reconnaitre() } }
        var idf = f.querySelector('.idf')
        if (idf) idf.onclick = function () {
          var nom = F.ph.value
          if (!nom && !F.photo.files.length) { res.textContent = "{{Choisir d'abord une photo.}}"; return }
          if (idf.dataset.envoi) return // une identification à la fois (quota de 500 par jour)
          idf.dataset.envoi = 1; res.textContent = "{{Identification…}}"
          var org = f.querySelector('.org').value
          ajax(nom ? 'identifier' : 'identifier_photo', nom ? {id: id, nom: nom, organe: org} : {photo: F.photo.files[0], organe: org}, function (l) {
            delete idf.dataset.envoi; res.innerHTML = plantnetHtml(l)
            res.querySelectorAll('.uti').forEach(function (a) { a.onclick = function () { retenu = {nom: a.dataset.nom, score: a.dataset.score}; F.bota.value = a.dataset.nom; reconnaitre() } })
          }, function (er) { delete idf.dataset.envoi; res.textContent = er })
        }
        f.onsubmit = function (ev) {
          ev.preventDefault()
          var ch = {}, src = {}, bt = f.querySelector('button[type=submit]'), er = f.querySelector('.jdc-er')
          var lb = lignes.filter(function (x) { return x[0] == 'bota' })[0], bv = F.bota.value.trim()
          if (fait != n || (lb ? lb[4] != bv : bv && bv != B.bota)) {
            er.textContent = "{{Recalcul en cours : vérifier les propositions puis réessayer.}}"; reconnaitre(); return } // tableau pas à jour de la saisie
          lignes.forEach(function (x) { if (coch[x[0] + '\u0001' + x[4]]) { ch[x[0]] = x[4]; if (x[5]) src[x[0]] = x[5] } })
          if (!Object.keys(ch).length) { er.textContent = "{{Cocher au moins une proposition.}}"; return }
          if (ch.rusticite) { // l'estimation repose sur le nom ou les caractères du formulaire : ils doivent être acceptés aussi
            var dep = lignes.filter(function (x) { return est.par == 'nom' ? x[0] == 'bota' : x[0].indexOf('id_') == 0 }).filter(function (x) { return !(x[0] in ch) })
            if (dep.length) { er.textContent = "{{La rusticité estimée dépend de :}}" + ' ' + dep.map(function (x) { return x[1] }).join(', ') + ' ' + "{{— les cocher aussi.}}"; return }
          }
          bt.disabled = true; er.textContent = ''
          ajax('accepter', {id: id, champs: JSON.stringify(ch), sources: JSON.stringify(src)}, function () { w.fermer(); if (d.apres) d.apres(); plante(d, id) },
            function (e) { bt.disabled = false; er.textContent = e })
        }
        reconnaitre()
      }, erreur(ct))
    }]])
  }

  // « ＋ Plante » : saisie à la main, aidée par Pl@ntNet (photo) et par la clé des caractères ; le reste de la fiche se complète dans la page du plugin
  // pg {id, nom} : « Greffer… » depuis la fiche d'un porte-greffe, même formulaire, la plante est transformée sur place (emplacement et pot gardés)
  function nouvelle(d, apres, pg) {
    var w = fenetre(pg ? "{{Greffer}}" + ' ' + pg.nom : "{{Nouvelle plante}}", [['n', d.nom, function (ct) {
      ajax('modele', {}, function (M) {
        var C = M.caracteres
        function champ(lib, h) { return '<label>' + lib + '</label>' + h }
        ct.innerHTML = '<form class="jdc-np">' +
          champ("{{Nom}}", '<input name="nom" required maxlength="120"' + (pg ? ' placeholder="' + "{{nom du greffon, ex. Yuzu n°2}}" + '"' : '') + '>') +
          (pg ? champ("{{Collection}}", '<select name="collection">' + M.collections.map(function (c) { return '<option value="' + c[0] + '"' + (c[0] == d.id ? ' selected' : '') + '>' + esc(c[1]) + '</option>' }).join('') + '</select>') : '') +
          champ("{{Emplacement}}", '<select name="emplacement"><option value=""></option>' + d.emplacements.map(function (e) { return '<option value="' + esc(e) + '">' + esc(e) + '</option>' }).join('') + '</select>') +
          champ("{{Photo}}", '<span><input type="file" name="photo" accept="' + IMG + '">' + (M.plantnet == 1 ? ' ' + orgHtml() + ' <a class="b idf">Pl@ntNet</a>' : '') + '<div class="res"></div></span>') +
          champ("{{Nom botanique}}", '<input name="bota" maxlength="120" placeholder="Citrus limon">') +
          '<details><summary>' + "{{Clé d'identification : cocher ce qu'on voit}}" + '</summary>' + cleHtml(C, {}) + '</details><div class="rec"></div>' +
          champ("{{Cultivar}}", '<input name="cultivar" maxlength="120">') +
          champ("{{Porte-greffe}}", '<input name="porte_greffe" maxlength="120" list="jdc-pgn"><datalist id="jdc-pgn">' + Object.keys(M.porte_greffes).map(function (k) { return '<option value="' + esc(k) + '">' + esc(M.porte_greffes[k]) + '</option>' }).join('') + '</datalist>') +
          champ("{{Rusticité (°C, pleine terre)}}", '<input name="rusticite" maxlength="40" placeholder="' + "{{vide : estimée d'après le groupe}}" + '">') +
          champ("{{Multiplication}}", '<span><select name="multiplication" style="width:auto"><option value=""></option>' + M.multiplications.map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>' }).join('') +
            '</select> <select name="type_greffe" style="width:auto;display:none"><option value="">' + "{{type de greffe ?}}" + '</option>' +
            Object.keys(M.greffes).map(function (k) { return '<option value="' + esc(k) + '">' + esc(M.greffes[k]) + '</option>' }).join('') +
            '</select> <input name="date_mult" maxlength="10" placeholder="2024-05" style="width:8em"></span>') +
          champ("{{Pot}}", '<span><select name="pot_forme" style="width:auto"><option value="">' + "{{rond, Ø}}" + '</option><option value="carre">' + "{{carré, côté}}" + '</option></select> ' +
            '<input name="pot_diam" inputmode="decimal" placeholder="cm" style="width:5em"> × <input name="pot_haut" inputmode="decimal" placeholder="' + "{{haut. cm}}" + '" style="width:6em"> ' +
            '<label style="display:inline"><input type="checkbox" name="pleine_terre" value="1"> ' + "{{pleine terre}}" + '</label></span>') +
          '<span></span><span><button type="submit">' + "{{Créer la plante}}" + '</button> <span class="jdc-er"></span></span></form>'
        var f = ct.querySelector('form'), F = f.elements, rec = f.querySelector('.rec'), n = 0
        function carac() { return caracDe(f) }
        function reconnaitre() {
          var k = ++n
          ajax('reconnaitre', {carac: JSON.stringify(carac()), bota: F.bota.value}, function (x) {
            if (k != n) return // réponse dépassée par une saisie plus récente
            rec.innerHTML = recHtml(x)
          }, function (er) { if (k == n) rec.textContent = er })
        }
        f.querySelector('details').onchange = reconnaitre; F.bota.onchange = reconnaitre
        F.multiplication.onchange = function () { var g = F.multiplication.value == 'greffe'; F.type_greffe.style.display = g ? '' : 'none'; if (!g) F.type_greffe.value = '' }
        if (pg) { // greffe : multiplication imposée, date du jour, porte-greffe déduit du nom (« Flying Dragon 3 » → Flying Dragon), emplacement et pot inchangés
          F.multiplication.value = 'greffe'; F.multiplication.onchange(); F.multiplication.style.display = 'none'; F.date_mult.value = auj()
          F.porte_greffe.value = Object.keys(M.porte_greffes).sort(function (a, b) { return b.length - a.length }).filter(function (k) { return pg.nom.toLowerCase().indexOf(k.toLowerCase()) >= 0 })[0] || pg.nom
          ;[F.emplacement, F.pot_forme.parentNode].forEach(function (e) { e.style.display = e.previousElementSibling.style.display = 'none' })
          f.querySelector('button[type=submit]').textContent = "{{Greffer}}"
        }
        f.onkeydown = function (e) { // Entrée dans un champ (« OK » du clavier iPhone) ne crée pas la plante à moitié remplie
          if (e.key == 'Enter' && e.target.tagName == 'INPUT') { e.preventDefault(); if (e.target == F.bota) reconnaitre() }
        }
        var idf = f.querySelector('.idf'), res = f.querySelector('.res')
        if (idf) idf.onclick = function () {
          if (!F.photo.files.length) { res.textContent = "{{Choisir d'abord une photo.}}"; return }
          if (idf.dataset.envoi) return // une identification à la fois (quota de 500 par jour)
          idf.dataset.envoi = 1; res.textContent = "{{Identification…}}"
          ajax('identifier_photo', {photo: F.photo.files[0], organe: f.querySelector('.org').value}, function (l) {
            delete idf.dataset.envoi
            res.innerHTML = plantnetHtml(l)
            res.querySelectorAll('.uti').forEach(function (a) { a.onclick = function () { F.bota.value = a.dataset.nom; if (!F.nom.value) F.nom.value = a.dataset.commun; reconnaitre() } })
          }, function (er) { delete idf.dataset.envoi; res.textContent = er })
        }
        f.onsubmit = function (ev) {
          ev.preventDefault()
          var t = {carac: carac()}, bt = f.querySelector('button[type=submit]'), er = f.querySelector('.jdc-er'), ph = F.photo.files[0]
          new FormData(f).forEach(function (v, k) { if (k != 'photo' && k.indexOf('id_') != 0) t[k] = v })
          bt.disabled = true; er.textContent = '' // pas de double envoi
          ajax(pg ? 'greffer' : 'creer', pg ? {id: pg.id, collection: F.collection.value, plante: JSON.stringify(t)} : {collection: d.id, plante: JSON.stringify(t)}, function (id) {
            function ouvrir(k) { w.fermer(); if (apres) apres(); plante(Object.assign({}, d, {plantes: d.plantes.filter(function (x) { return x.id != id }).concat([{id: id, nom: t.nom}])}), id, k) }
            if (!ph) return ouvrir()
            ajax('photo_ajout', {id: id, photo: ph}, function () { ouvrir('pho') }, function (e) { // plante créée, photo refusée : le dire avant d'ouvrir la fiche
              er.textContent = "{{Plante créée, mais photo refusée :}} " + e + ' ' + "{{(la remettre dans l'onglet Photos)}}"
              bt.textContent = "{{Ouvrir la fiche}}"; bt.disabled = false; f.onsubmit = function (ev) { ev.preventDefault(); ouvrir('pho') }
            })
          }, function (e) { bt.disabled = false; er.textContent = e })
        }
      }, erreur(ct))
    }]])
  }

  // opt.panel : colonnes en plus (porte-greffe, dernière mesure), masquées sur téléphone
  window.journalcultures = {v: 6, ajax: ajax, esc: esc, nb: nb, init: function (b, d, opt) {
    if (!b) return
    d.apres = opt && opt.apres // relu par « Greffer… » (fiche)
    var pa = opt && opt.panel
    var al = d.plantes.filter(function (p) { return p.alerte == 1 }), eng = d.plantes.filter(function (p) { return p.engrais })
    b.innerHTML = '<div class="jdc-r">' + d.plantes.length + ' ' + "{{plantes}}" + (al.length ? ' · <span class="al">' + al.length + ' ' + "{{en alerte}}" + '</span>' : '') +
      (eng.length ? ' · ' + eng.length + ' ' + "{{engrais à donner}}" : '') + '</div>' +
      '<table><tr>' + (d.admin == 1 ? '<th><input type="checkbox" class="jdc-tt" title="' + "{{Tout cocher}}" + '"></th>' : '') + '<th>' + "{{Plante}}" + '</th>' + (pa ? '<th class="xs">' + "{{Porte-greffe}}" + '</th>' : '') + '<th>' + "{{Lieu}}" + '</th><th class="n">T°</th><th class="n" title="' + "{{Rusticité effective (pot compris)}}" + '">' + "{{Rust.}}" + '</th>' + (pa ? '<th class="xs">' + "{{Dernière mesure}}" + '</th>' : '') + '<th></th></tr>' +
      d.plantes.map(function (p) {
        return '<tr class="p" tabindex="0" data-id="' + esc(p.id) + '">' + (d.admin == 1 ? '<td><input type="checkbox" class="jdc-ck" value="' + esc(p.id) + '"></td>' : '') +
          '<td' + (p.alerte == 1 ? ' class="al"' : '') + (p.raison ? ' title="' + esc(p.raison) + '"' : '') + '>' + esc(p.nom) + (p.raison && (p.alerte != 1 || p.muette) ? ' <span class="al">⚠</span>' : '') + '</td>' + (pa ? '<td class="xs">' + esc(p.pg || '–') + '</td>' : '') + '<td>' + esc(p.empl || '–') + '</td><td class="n">' + nb(p.t, '°') + '</td>' +
          '<td class="n">' + nb(p.reff, '°') + '</td>' + (pa ? '<td class="xs">' + esc(p.mes || '–') + '</td>' : '') + '<td>' + (p.engrais ? '<span title="' + esc(p.engrais) + '">🌱</span>' + (d.admin == 1 ? ' <a class="jdc-eng" title="' + "{{Engrais donné}}" + '" style="cursor:pointer">✓</a>' : '') : '') + '</td></tr>'
      }).join('') + '</table>' + (d.plantes.length ? '' : '<p>' + "{{Aucune plante : « ＋ Plante » pour en ajouter.}}" + '</p>') +
      '<div class="jdc-bt">' + (d.admin == 1 ? '<a data-b="nouvelle">＋ ' + "{{Plante}}" + '</a><a data-b="noter">＋ ' + "{{Noter}}" + '</a><a data-b="deplacer">' + "{{Déplacer…}}" + '</a>' : '') + '<a data-b="journal">' + "{{Journal}}" + '</a></div>'

    var tt = b.querySelector('.jdc-tt'), mem = coches[d.id] || (coches[d.id] = {})
    b.querySelectorAll('.jdc-ck').forEach(function (c) { c.checked = !!mem[c.value]; c.onchange = function () { mem[c.value] = c.checked } })
    if (tt) tt.onclick = function () { b.querySelectorAll('.jdc-ck').forEach(function (c) { if (c.closest('tr').style.display == 'none') return; c.checked = tt.checked; mem[c.value] = c.checked }) }
    b.querySelectorAll('tr.p').forEach(function (tr) {
      tr.onkeydown = function (e) { if (e.key == 'Enter' && e.target == tr) plante(d, tr.dataset.id) } // clavier
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
        if (a.dataset.b == 'nouvelle') return nouvelle(d, opt && opt.apres)
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

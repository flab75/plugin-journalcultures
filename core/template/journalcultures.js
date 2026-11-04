/* Journal de cultures — tuile d'une collection et ses fenêtres (plante, journal, noter, déplacer). Recopié dans chaque tuile par
   journalcultures::toHtml(), chargé une seule fois par page. Textes entre doubles accolades traduits côté PHP (core/i18n). */
if (!window.journalcultures || window.journalcultures.v !== 31) (function () { // v : un onglet ouvert avant une mise à jour recharge la bibliothèque
  var css = document.getElementById('jdc-css') || document.createElement('style')
  css.id = 'jdc-css'
  css.textContent =
    ':root{--jdc-rouge:#b3261e;--jdc-vert:#2F6B3A}body[data-theme$=Dark]{--jdc-rouge:#ff8a80;--jdc-vert:#8fd19e}' + // A-7 : rouge et contour lisibles sur fond sombre
    'button.jb{font:inherit;color:inherit;border:0;background:none;padding:0;margin:0;cursor:pointer;text-align:inherit;line-height:inherit}.jb.lk{text-decoration:underline}' +
    '.jdc :focus-visible,.jdc-m :focus-visible{outline:2px solid var(--jdc-vert);outline-offset:1px}.jdc-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}' +
    '.jdc{box-sizing:border-box;width:100%;padding:4px 6px 8px;text-align:left;font-size:12px}' +
    '.jdc table{width:100%;border-collapse:collapse}.jdc td,.jdc th{padding:2px 3px;border-bottom:1px solid rgba(128,128,128,.2)}' +
    '.jdc tr.p{cursor:pointer}.jdc tr.p:hover{background:rgba(128,128,128,.12)}.jdc .al{color:var(--jdc-rouge);font-weight:600}.jdc .n{text-align:right;white-space:nowrap}' +
    '.jdc .jdc-gr{font-size:.8em;font-weight:600;color:var(--jdc-vert);border:1px solid var(--jdc-vert);border-radius:8px;padding:0 5px;white-space:nowrap}.jdc-r{margin-bottom:4px}.jdc-r .al{background:#b3261e;color:#fff;border-radius:8px;padding:1px 7px}' +
    '.jdc-bt{display:flex;gap:5px;justify-content:center;margin-top:6px}.jdc-bt button,.jdc-m .b{cursor:pointer;border-radius:12px;padding:2px 10px;background:rgba(128,128,128,.2)}' +
    '.jdc-m{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center}' +
    '.jdc-mb{background:var(--bg-modal-color,#fff);color:var(--txt-color,#222);width:min(800px,96vw);max-height:90vh;overflow:auto;border-radius:10px;padding:10px 14px;font-size:13px;text-align:left}' +
    '.jdc-mb>nav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;align-items:center}.jdc-mb>nav button{padding:3px 10px;border-radius:10px;background:rgba(128,128,128,.15)}' +
    '.jdc-m:not(.jdc-in) .jdc-mb>nav{position:sticky;top:-10px;z-index:1;background:var(--bg-modal-color,#fff);padding:6px 0}[id^=jdc-n-]{scroll-margin-top:50px}' +
    '.jdc-mb a[data-n]{cursor:pointer;text-decoration:underline}.jdc-mb>nav button.on{background:#2F6B3A;color:#fff}.jdc-mb>nav .z{margin-left:auto}.jdc-mb>nav b{margin-right:6px}' +
    '.jdc-mb table{width:100%;border-collapse:collapse}.jdc-mb td,.jdc-mb th{padding:3px 5px;border-bottom:1px solid rgba(128,128,128,.25);vertical-align:top;text-align:left}' +
    '.jdc-mb form{display:grid;grid-template-columns:fit-content(35%) minmax(0,1fr);gap:4px 8px;align-items:center;margin-bottom:10px}' +
    '.jdc-mb input,.jdc-mb select,.jdc-mb textarea{width:100%;box-sizing:border-box}.jdc-mb input[type=checkbox]{width:auto}' +
    '.jdc-mb h4{margin:10px 0 3px}.jdc-er{color:var(--jdc-rouge)}.jdc-ph{display:flex;flex-wrap:wrap;gap:8px}.jdc-ph figure{margin:0;width:180px}' +
    '.jdc-ph img{width:180px;height:180px;object-fit:cover;border-radius:6px;cursor:zoom-in}.jdc-ph figcaption{font-size:11px}' +
    '.jdc-m.jdc-in{position:static;background:none;display:block}.jdc-in .jdc-mb{width:auto;max-height:none}' + // fenêtre posée dans le panel (écran large)
    '.jdc-org{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px}.jdc-org button,.jdc-pil span{cursor:pointer;display:inline-block;border-radius:16px;padding:4px 11px;border:1px solid rgba(128,128,128,.4)}' +
    '.jdc-org button.on,.jdc-pil input:checked+span{background:#2F6B3A;border-color:#2F6B3A;color:#fff}.jdc-pil{position:relative;margin:0 4px 4px 0}.jdc-mb .jdc-pil input{position:absolute;opacity:0;width:1px;height:1px;min-height:0;margin:0}.jdc-pil input:focus-visible+span{outline:2px solid var(--jdc-vert)}' +
    '.jdc-mb form.jdc-mf{display:block}.jdc-mb form>*{min-width:0}.jdc-mb input[type=file]{max-width:100%}.jdc-g{display:none}.jdc-g.on{display:block}.jdc-mf fieldset{border:0;padding:0;margin:0 0 6px}.jdc-mf legend{font-size:inherit;border:0;margin:0 0 3px;width:auto}' +
    '.jdc-lg{display:flex;align-items:center;gap:8px;margin:0 0 6px;font-weight:normal}.jdc-lg>span{flex:1}.jdc-lg input{width:110px;text-align:right}.jdc-lg small{width:42px}.jdc-lg textarea{flex:2}' +
    '.jdc-svg svg{width:100%;height:auto;max-height:220px}' +
    '.jdc-np details,.jdc-np .rec{grid-column:1/-1}.jdc-np summary{cursor:pointer;margin:4px 0}.jdc-np fieldset{border:0;padding:0;margin:0 0 6px}.jdc-np legend{font-size:inherit;border:0;margin:0 0 3px;width:auto}' +
    // notice : large, sommaire à gauche (ordinateur), au-dessus (tablette), replié (mobile), lignes de 75 caractères au plus, tableaux défilants
    '.jdc-not .jdc-mb{width:min(1100px,96vw);font-size:14px;line-height:1.55;padding:10px 20px 24px}.jdc-not .jdc-ct>h2{font-size:22px;margin:8px 0 12px;padding-bottom:6px;border-bottom:2px solid #2F6B3A}' +
    '.jdc-doc{display:grid;grid-template-columns:220px minmax(0,1fr);gap:24px;align-items:start}.jdc-tx>*{max-width:75ch}.jdc-tx>.jdc-tw{max-width:none}.jdc-tx>p:first-child{margin-top:0}' +
    '.jdc-toc{position:sticky;top:44px;max-height:calc(90vh - 70px);overflow:auto;font-size:13px;line-height:1.35;border-left:3px solid rgba(47,107,58,.5);padding-left:10px}' +
    '.jdc-toc summary{cursor:pointer;font-weight:600;margin-bottom:4px}.jdc-toc ul{list-style:none;margin:0;padding:0}.jdc-toc li{margin:5px 0}.jdc-toc li.s{padding-left:12px;font-size:12px;opacity:.85}.jdc-mb .jdc-toc a[data-n]{text-decoration:none}.jdc-toc a:hover{text-decoration:underline}' +
    '.jdc-tx h3{font-size:18px;margin:28px 0 8px;padding-bottom:4px;border-bottom:1px solid rgba(128,128,128,.35)}.jdc-tx h4{font-size:15.5px;margin:22px 0 6px;padding-left:8px;border-left:3px solid #2F6B3A}.jdc-tx h5{font-size:14px;margin:14px 0 4px}' +
    '.jdc-tx li{margin:3px 0}.jdc-tw{overflow-x:auto;margin:10px 0 14px}.jdc-tw table{margin:0;min-width:480px;font-size:13px;line-height:1.4}.jdc-tw th,.jdc-tw td{padding:5px 8px!important;vertical-align:top!important}.jdc-tw th{background:rgba(47,107,58,.18);white-space:nowrap}.jdc-tw tr:nth-child(even) td{background:rgba(128,128,128,.07)}.jdc-tw td:first-child{min-width:9em}.jdc-ct code{color:inherit;background:rgba(128,128,128,.18);border:0;padding:1px 4px;border-radius:3px;font-size:.88em;white-space:normal;overflow-wrap:anywhere}.jdc-ct summary,.jdc-np summary{display:list-item;cursor:pointer}.jdc-not .jdc-ct img{max-width:100%;height:auto}.jdc-tx p:has(>img.jdc-svgi){overflow-x:auto}@media (max-width:600px){.jdc-not .jdc-ct img.jdc-svgi{max-width:none!important;min-width:520px}}.jdc-q{position:sticky;top:0;z-index:2;margin:8px 0 12px}' +
    '@media (max-width:900px){.jdc-doc{display:block}.jdc-tx>*{max-width:none}.jdc-toc{position:static;max-height:none;margin-bottom:14px;padding:8px 10px;border:1px solid rgba(128,128,128,.3);border-radius:8px}}' +
    '@media (max-width:600px){.jdc-m{align-items:stretch}.jdc-mb{width:100vw;max-height:none;height:100%;border-radius:0;font-size:15px}.jdc-mb>nav button{padding:8px 12px}' +
    '.jdc-not .jdc-mb{width:100vw;padding:8px 14px 24px}.jdc-not .jdc-ct>h2{font-size:19px}.jdc-mb input,.jdc-mb select,.jdc-mb textarea{font-size:16px;min-height:40px}.jdc-mb button{min-height:44px}.jdc .xs{display:none}}'
  document.head.appendChild(css)

  function esc(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (x) { return '&#' + x.charCodeAt(0) + ';' }) }
  function nb(v, u) { return v === null || v === undefined || v === '' ? '–' : String(Math.round(v * 10) / 10).replace('.', ',') + (u || '') }
  function auj() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2) }

  function ajax(action, champs, ok, ko) {
    var f = new FormData()
    f.append('action', action); f.append('jeedom_token', window.JEEDOM_AJAX_TOKEN || '')
    Object.keys(champs).forEach(function (k) { f.append(k, champs[k]) })
    fetch('plugins/journalcultures/core/ajax/journalcultures.ajax.php', {method: 'POST', body: f, credentials: 'same-origin'})
      .then(function (r) { return r.json().then(function (j) { if (j.state == 'ok') ok(j.result); else ko(j.result) }) })
      .catch(function (e) { ko(e instanceof SyntaxError ? "{{Réponse illisible : session expirée ? Recharger la page.}}" : String(e)) })
  }

  // URL d'une photo de plante (lecture en GET, session de Jeedom) : mini = vignette de 400 px
  function photo(id, n, mini) { return 'plugins/journalcultures/core/ajax/journalcultures.ajax.php?action=photo&id=' + encodeURIComponent(id) + '&nom=' + encodeURIComponent(n) + (mini ? '&mini=1' : '') }

  // fenêtre à onglets : o = [[clé, titre, fonction(ct)], …]
  // opt.flottante : toujours en modale ; opt.aide = {clé d'onglet: ancre de la notice} → bouton « ? »
  function fenetre(titre, o, depart, opt) {
    opt = opt || {}
    var cible = window.journalcultures.cible, cour = null // panel : à côté de la liste (pas si on a quitté le panel, ni sur écran étroit)
    if (!cible || opt.flottante || !cible.isConnected || window.innerWidth <= 900) cible = null
    var m = document.createElement('div'); m.className = 'jdc-m' + (cible ? ' jdc-in' : '') + (opt.classe ? ' ' + opt.classe : '')
    if (cible) { if (posee) posee.fermer(); cible.innerHTML = '' }
    m.innerHTML = '<div class="jdc-mb"><nav><b>' + esc(titre) + '</b>' + o.map(function (x) { return '<button type="button" class="jb" aria-pressed="false" data-o="' + x[0] + '">' + esc(x[1]) + '</button>' }).join('') +
      (opt.aide ? '<button type="button" class="jb q" title="' + "{{Notice}}" + '" aria-label="' + "{{Notice}}" + '">?</button>' : '') + '<button type="button" class="jb z" title="' + "{{Fermer}}" + '" aria-label="' + "{{Fermer}}" + '">✕</button></nav><div class="jdc-ct"></div></div>';
    (cible || document.body).appendChild(m)
    var ct = m.querySelector('.jdc-ct'), mb = m.querySelector('.jdc-mb'), avant = document.activeElement // focus rendu à la fermeture
    mb.setAttribute('role', cible ? 'region' : 'dialog'); mb.setAttribute('aria-label', titre); mb.tabIndex = -1; if (!cible) mb.setAttribute('aria-modal', 'true')
    new MutationObserver(function () { a11y(ct) }).observe(ct, {childList: true, subtree: true}) // libellés et zones annoncées, quel que soit l'onglet
    function fermer() { m.remove(); document.removeEventListener('keydown', touche); if (!cible && avant && avant.isConnected && avant.focus) avant.focus() }
    function touche(e) { // posée dans le panel : ✕ seulement ; modale : Échap (dans un champ : sort du champ, puis ferme), Tab reste dedans
      if (cible || m !== [].slice.call(document.querySelectorAll('body > .jdc-m')).pop()) return
      if (e.key == 'Escape') { if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) mb.focus(); else fermer(); return }
      if (e.key != 'Tab') return
      var l = [].filter.call(mb.querySelectorAll('button,a[href],input,select,textarea,summary,[tabindex="0"]'), function (x) { return !x.disabled && x.offsetParent !== null })
      if (!l.length) return
      if (!mb.contains(e.target)) { e.preventDefault(); (e.shiftKey ? l[l.length - 1] : l[0]).focus(); return } // focus perdu (onglet redessiné, bouton désactivé) : on revient dans la fenêtre
      if (e.shiftKey && (e.target == l[0] || e.target == mb)) { e.preventDefault(); l[l.length - 1].focus() } else if (!e.shiftKey && e.target == l[l.length - 1]) { e.preventDefault(); l[0].focus() }
    }
    document.addEventListener('keydown', touche)
    m.onclick = function (e) { if (e.target == m || e.target.classList.contains('z')) fermer() }
    if (opt.aide) m.querySelector('nav .q').onclick = function () { notice('index', opt.aide[cour]) }
    function montrer(k) {
      cour = k
      m.querySelectorAll('nav button[data-o]').forEach(function (a) { a.classList.toggle('on', a.dataset.o == k); a.setAttribute('aria-pressed', a.dataset.o == k) })
      ct.innerHTML = '<p role="status">' + "{{Chargement…}}" + '</p>'
      o.filter(function (x) { return x[0] == k })[0][2](ct)
    }
    m.querySelectorAll('nav button[data-o]').forEach(function (a) { a.onclick = function () { montrer(a.dataset.o) } })
    montrer(depart || o[0][0])
    if (!cible) (m.querySelector('nav button.on') || mb).focus()
    var f = {montrer: montrer, fermer: fermer, boite: m.querySelector('.jdc-mb')}
    if (cible) posee = f
    return f
  }
  var nid = 0
  function a11y(el) { // A-4 : <label>X</label> suivi de son champ (grille) relié au champ ; A-10 : messages d'erreur et résultats annoncés
    el.querySelectorAll('label:not([for])').forEach(function (l) {
      if (l.querySelector('input,select,textarea')) return
      var s = l.nextElementSibling, c = s && (s.matches('input,select,textarea') ? s : s.querySelector('input,select,textarea'))
      if (!c || c.closest('label')) return; if (!c.id) c.id = 'jdc-c' + (++nid); l.htmlFor = c.id
    })
    el.querySelectorAll('.jdc-er:not([aria-live]),.res:not([aria-live]),.rec:not([aria-live])').forEach(function (x) { x.setAttribute('aria-live', 'polite') })
  }
  var posee = null // fenêtre posée dans le panel : fermée (écouteur, photos) avant d'en poser une autre

  // notice (docs/fr_FR/*.md convertis par la box) : k = page, ancre = titre visé (comme sur GitHub)
  var NC = null // liste des pages ; chaque page (HTML, gardé en cache par la box) est chargée à sa première ouverture
  function notice(k, ancre) {
    function err(er) { fenetre("{{Notice}}", [['e', "{{Erreur}}", function (ct) { erreur(ct)(er) }]], 'e', {flottante: 1}) }
    if (NC) return lire(NC)
    ajax('notice', {}, function (N) { if (!N.length) return err("{{Notice absente (dossier docs)}}"); lire(NC = N) }, err)
    function lire(N) {
      var w = null, vise = null // vise : titre où défiler une fois la page posée
      function aller(k2, a) {
        if (!N.some(function (n) { return n.k == k2 })) k2 = N[0].k
        vise = a
        if (w) w.montrer(k2); else w = fenetre("{{Notice}}", N.map(function (n) { return [n.k, n.titre, function (ct) {
          ct.dataset.p = n.k // page affichée : une réponse arrivée après un changement d'onglet est ignorée
          if (n.html) return poser(ct, n)
          ajax('notice', {k: n.k}, function (h) { n.html = h; if (ct.dataset.p == n.k) poser(ct, n) }, function (er) { if (ct.dataset.p == n.k) erreur(ct)(er) })
        }] }), k2, {flottante: 1, classe: 'jdc-not'})
      }
      function poser(ct, n) {
        ct.innerHTML = n.html
        ct.querySelectorAll('a[data-n]').forEach(function (l) { l.onclick = function (e) { e.preventDefault(); aller(l.dataset.n || n.k, l.dataset.a) } })
        var d = ct.querySelector('.jdc-toc details'); if (d && window.innerWidth <= 600) d.open = false // mobile : sommaire replié
        if (n.k == 'noms') chercher(ct)
        var b = ct.closest('.jdc-mb'), e = vise && b.querySelector('#' + CSS.escape('jdc-n-' + vise)); vise = null // cette fenêtre, pas une notice déjà ouverte
        if (e) e.scrollIntoView(); else b.scrollTop = 0
      }
      aller(k, ancre)
    }
  }

  // dictionnaire des noms : filtre les entrées (li) au fil de la frappe, sans accents ni casse ; lettres vides masquées
  function chercher(ct) {
    var tx = ct.querySelector('.jdc-tx') || ct, sans = function (t) { return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() }
    var q = document.createElement('input'); q.type = 'search'; q.placeholder = "{{Chercher un nom (genre, espèce, ancien nom)…}}"; q.className = 'form-control jdc-q'; q.setAttribute('aria-label', "{{Chercher un nom}}")
    tx.insertBefore(q, tx.querySelector(':scope > h3') || tx.firstChild)
    var haut = function () { var m = q.closest('.jdc-mb'), n = m && m.querySelector('nav'); q.style.top = (n ? n.offsetHeight - 10 : 0) + 'px' } // sous les onglets collants (plusieurs lignes sur mobile)
    setTimeout(haut); q.onfocus = haut
    var lis = [].slice.call(tx.querySelectorAll('li')).map(function (li) { return [li, sans(li.textContent)] })
    q.oninput = function () {
      var v = sans(q.value.trim())
      lis.forEach(function (x) { x[0].style.display = !v || x[1].indexOf(v) >= 0 ? '' : 'none' })
      tx.querySelectorAll('h2,h3,h4').forEach(/* ### du .md = h4 une fois converti */function (h) { var u = h.nextElementSibling; if (u && u.tagName == 'UL') h.style.display = u.style.display = [].some.call(u.children, function (li) { return li.style.display != 'none' }) ? '' : 'none' })
    }
  }

  function erreur(ct) { return function (er) { ct.innerHTML = '<p class="jdc-er" role="alert">' + esc(er) + '</p>' } }

  function tableJournal(d, l, avecPlante, apres) { // entrées du journal + suppression (admins)
    return '<table><tr><th scope="col">' + "{{Date}}" + '</th><th scope="col">' + "{{Travail}}" + '</th><th></th></tr>' + l.map(function (x) {
      return '<tr><td>' + esc(x.date) + '</td><td><b>' + esc(d.actions[d.actionsCle.indexOf(x.action)] || x.action) + '</b>' + (avecPlante ? ' ' + esc(x.plante) : '') +
        (x.lieu ? ' <small>(' + esc(x.lieu) + ')</small>' : '') + (x.note ? '<br><small>' + esc(x.note) + '</small>' : '') + '</td><td>' +
        (d.admin == 1 ? '<button type="button" class="jb jdc-del" data-id="' + esc(x.id) + '" title="' + "{{Supprimer}}" + '" aria-label="' + "{{Supprimer l'entrée}}" + '">✕</button>' : '') + '</td></tr>'
    }).join('') + '</table>' + (l.length ? '' : '<p>' + "{{Rien de noté pour l'instant.}}" + '</p>')
  }
  function brancherSuppr(ct, apres) {
    ct.querySelectorAll('.jdc-del').forEach(function (a) {
      a.onclick = function () { if (!a.dataset.ok) { a.dataset.ok = 1; a.removeAttribute('aria-label'); a.textContent = "{{Confirmer ✕}}"; return } // deuxième clic, comme les photos
        if (a.dataset.envoi) return; a.dataset.envoi = 1 // pas de double envoi
        ajax('supprimer', {entree: a.dataset.id}, apres, function (er) { delete a.dataset.envoi; a.textContent = er }) }
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
  function cochees(b) { return Array.prototype.filter.call(b.querySelectorAll('.jdc-ck:checked'), function (c) { return c.closest('tr').style.display != 'none' }).map(function (c) { return c.value }) } // masquées par la recherche : pas envoyées
  function noms(d, ids) { return d.plantes.filter(function (p) { return ids.indexOf(String(p.id)) >= 0 }).map(function (p) { return p.nom }).join(', ') }

  /* Engrais de la fiche : pistes de carence (dernière mesure), plan d'azote par volume du pot ; seuls les produits cochés, rien n'est écrit sans le bouton */
  function saison(s) { // « 10-40 » (semaines ISO) → « du 5 mars au 1 oct. » ; ponytail: lundi approché à ±3 jours, suffit pour un calendrier d'engrais
    var w = String(s).split('-'), an = new Date().getFullYear()
    function d(n) { return new Date(an, 0, 1 + (Number(n) - 1) * 7).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) }
    return w.length == 2 ? "{{du}}" + ' ' + d(w[0]) + ' ' + "{{au}}" + ' ' + d(w[1]) : esc(s)
  }
  function blocEngrais(e, admin) {
    var pl = e.produits.map(function (p, i) { return [p, i] }).filter(function (x) { return x[0].g100 !== null })
    return '<h4>' + "{{Engrais}}" + '</h4>' +
      (e.carences.length ? '<p><b>' + "{{Pistes d'après la dernière mesure}}" + '</b> <small>(' + "{{pas un diagnostic}}" + ')</small></p><ul>' + e.carences.map(function (c) {
        return '<li>' + esc(c.txt) + (c.produits.length ? '<br><small>' + "{{En stock}}" + ' : ' + esc(c.produits.join(' · ')) + '</small>' : '') + '</li>' }).join('') + '</ul>' : '') +
      '<p>' + (e.litres ? "{{Pot}}" + ' ' + nb(e.litres, ' L') + ' · ' + "{{besoin}}" + ' ' + nb(e.litres * e.taux * 2) + ' ' + "{{g d'azote toutes les 2 semaines pendant la période d'apport}}" + (e.jeune < 1 ? ' <small>(' + "{{jeune plant : dose réduite à}}" + ' ' + Math.round(e.jeune * 100) + ' %)</small><br><small>' + "{{Une dose déjà au calendrier n'est pas réduite d'elle-même : vider la case dose (elle se recalcule) puis écrire ; à refaire chaque printemps, la part remonte avec l'âge.}}" + '</small>' : '') + '<br><small>' + "{{Saison de pousse : si la moyenne sur 7 jours de l'emplacement atteint}}" + ' ' + nb(e.seuil, ' °C') + ' (' + "{{zéro de végétation des agrumes}}" + ') : ' +
        (e.moy7 === null ? "{{sans sonde historisée, seul le calendrier compte}}" : e.moy7 === false ? '<b>' + "{{sonde muette depuis 7 jours : les apports attendent}}" + '</b>' : "{{maintenant}}" + ' ' + nb(e.moy7, ' °C') + ', ' + (e.moy7 >= e.seuil ? "{{seuil atteint}}" : '<b>' + "{{au repos : les apports attendent, rattrapés quand il fait plus doux}}" + '</b>')) +
        ' ; ' + "{{et seulement pendant la période d'apport de chaque produit, ci-dessous}}" + (e.sud ? ' (' + "{{hémisphère sud : décalée de 6 mois}}" + ')' : '') + '.' +
        (e.hiv ? ' ' + "{{Repos hivernal au frais : pas d'engrais hors période d'apport, même au chaud.}}" : e.chauffe && e.jeuneHiv ? ' ' + "{{Jeune plant (moins d'un an ou greffe en suivi) : pas d'engrais hors période d'apport, même au chaud.}}" : e.chauffe && typeof e.moy7 == 'number' ? ' ' + "{{Emplacement chauffé : hors période d'apport, apports à demi-dose tant que la moyenne atteint le seuil.}}" : '') + '</small>' : "{{Pot de dimensions inconnues ou pleine terre : pas de plan (dose de l'étiquette)}}") + '</p>' +
      '<p><small>' + "{{Calendrier actuel}}" + ' : ' + (e.calendrier ? esc(e.calendrier).replace(/\n/g, ' ; ') : '–') + '</small>' +
        (e.calendrier.split('\n').some(function (l) { var c = l.split('|').map(function (x) { return x.trim() }), p = e.produits.filter(function (p) { return p.nom == c[1] })[0]
          return p && p.tous > 0 && /^\d+-\d+\/\d+$/.test(c[0]) && c[0].split('/')[0] != p.saison }) ? '<br><small class="jdc-er">' + "{{Une ligne du calendrier n'a pas la période d'apport de son produit (latitude ou période changée ?) : réécrire le plan, si ce n'est pas voulu.}}" + '</small>' : '') + '</p>' +
      (e.litres && pl.length ? '<table class="jdc-plan">' + pl.map(function (x) {
        var p = x[0], i = x[1], v = e.parts[i] || 0
        return '<tr><td><label><input type="checkbox" class="pk" data-i="' + i + '"' + (v ? ' checked' : '') + (admin ? '' : ' disabled') + '> ' + esc(p.nom) + '</label> <small>' + esc(p.n) + ' % N, ' + "{{toutes les}}" + ' ' + p.tous + ' ' + "{{sem.}}" + ', ' + saison(p.saison) + (p.ge ? ', ' + "{{dose de l'étiquette}}" + ' ' + nb(p.gl) + ' g/L' : '') + '</small></td>' +
          '<td><input class="pp" data-i="' + i + '" aria-label="' + esc(p.nom) + ' : ' + "{{part en %}}" + '" type="number" min="0" max="100" style="width:4.5em" value="' + v + '"' + (admin ? '' : ' disabled') + '> %</td><td><input class="pd" data-i="' + i + '" aria-label="' + esc(p.nom) + ' : ' + "{{dose en g}}" + '" type="text" inputmode="decimal" style="width:5em"' + (e.doses[i] ? ' value="' + e.doses[i] + '"' : '') + (admin ? '' : ' disabled') + '> g <small class="pg" data-i="' + i + '"></small></td></tr>' }).join('') + '</table>' +
        '<p><small>' + "{{Seuls les produits cochés reçoivent l'azote, réparti selon les parts (total 100 %). Un produit à dose d'étiquette garde cette dose ; son azote est retiré du besoin et le reste va aux autres. La dose calculée peut être ajustée à la main : c'est elle qui est écrite.}}" + '</small>' +
        (admin ? ' <button type="button" class="jb b pe">' + "{{Écrire dans le calendrier}}" + '</button> <span class="jdc-er pr"></span>' : '') + '</p>' : '')
  }
  function blocSante(s, cat, geo) {
    function src(l) { return ' <small>(' + esc(l.join(', ')) + (l.length == 1 ? ' — <b>' + "{{une seule source}}" + '</b>' : '') + ')</small>' }
    function statut(p) { return p.statut == 'absent' ? ' <b>' + "{{absent de France}}" + '</b>' : p.statut == 'quarantaine' ? ' <b class="jdc-er">' + "{{quarantaine : à signaler}}" + '</b>' : p.statut == 'pays' ? ' <b' + (p.quar ? ' class="jdc-er"' : '') + '>' + "{{statut à vérifier pour ce pays (connu pour la France)}}" + '</b>' : '' }
    function gestes(p) { return '<ol>' + p.gestes.map(function (g) { return '<li>' + esc(g[0]) + src(g[1]) + '</li>' }).join('') + '</ol>' + (p.note ? '<p><small>' + esc(p.note) + '</small></p>' : '') }
    return '<h4>' + "{{Santé}}" + '</h4>' + (geo && geo.sud ? '<p><small class="jdc-er">' + "{{Les périodes citées (mois, saisons) viennent de l'hémisphère nord : les décaler de 6 mois.}}" + '</small></p>' : '') +
      (s.pistes.length ? '<p><b>' + "{{Pistes d'après la mesure du}}" + ' ' + esc(s.date.slice(0, 10)) + '</b> <small>(' + "{{pas un diagnostic ; gestes du plus doux au plus fort}}" + ')</small></p>' + s.pistes.map(function (x) {
        var p = cat[x.id]; return '<p><b>' + esc(p.nom) + '</b>' + statut(p) + ' <small>— ' + esc(x.pourquoi) + '</small>' + (x.note ? '<br><small class="jdc-er">' + esc(x.note) + '</small>' : '') + '</p>' + gestes(p) }).join('') :
        '<p><small>' + "{{Rien de signalé aux dernières mesures. Pour noter un problème : Mesure → Santé (signe observé, ravageur, maladie) ou Tronc → écorce.}}" + '</small></p>') +
      '<details><summary>' + "{{Problèmes surveillés, par priorité}}" + '</summary>' + Object.keys(cat).map(function (k, i) { var p = cat[k]
        return '<p><b>' + (i + 1) + '. ' + esc(p.nom) + '</b>' + statut(p) + src(p.sources) + '<br><small>' + esc(p.signes) + '</small></p>' + gestes(p) }).join('') + '</details>'
  }
  function brancherEngrais(ct, e, id, fini) {
    var t = ct.querySelector('.jdc-plan'); if (!t) return
    function parts() { var r = {}; t.querySelectorAll('.pk:checked').forEach(function (k) { r[k.dataset.i] = Number(t.querySelector('.pp[data-i="' + k.dataset.i + '"]').value) || 0 }); return r }
    function doses() { var r = {}; t.querySelectorAll('.pk:checked').forEach(function (k) { var v = num(t.querySelector('.pd[data-i="' + k.dataset.i + '"]').value); if (v > 0) r[k.dataset.i] = v }); return r }
    function num(x) { return Number(String(x).replace(',', '.')) } // « 2,5 » (clavier français)
    function calc(re, frappe) { // frappe : case en cours de saisie, jamais re-remplie ; re : parts changées → dose recalculée ; sinon la dose saisie (ou celle du calendrier) est gardée
      var r = parts(), s = 0, et = 0, au = 0 // comme journalcultures_calc::plan : dose d'étiquette fixe, son azote retiré du besoin, le reste aux autres selon leurs parts
      Object.keys(r).forEach(function (i) { if (!r[i]) return; if (e.produits[i].ge) et += e.produits[i].ne; else au += Math.min(r[i], 100) })
      t.querySelectorAll('.pg').forEach(function (c) { var i = c.dataset.i, v = r[i], p = e.produits[i], g = v && (p.ge || et < 1) ? Math.max(1, Math.round(p.ge || p.g100 * Math.max(0, 1 - et) * Math.min(v, 100) / au)) : 0, d = t.querySelector('.pd[data-i="' + i + '"]'); s += v || 0
        if (re || (v && !d.value && !frappe)) d.value = g || ''; c.textContent = v && num(d.value) !== g ? '(' + "{{calculé}}" + ' ' + g + ' g)' : '' })
      var m = ct.querySelector('.pr'); if (m) m.textContent = Object.keys(r).length && Math.abs(s - 100) > 1 ? "{{total}}" + ' ' + s + ' %' : ''
    }
    t.querySelectorAll('.pk').forEach(function (k) { k.onchange = function () { // cocher : parts égales entre les cochés
      var c = t.querySelectorAll('.pk:checked'), k = 0; t.querySelectorAll('.pp').forEach(function (p) { p.value = t.querySelector('.pk[data-i="' + p.dataset.i + '"]').checked ? (++k < c.length ? Math.floor(100 / c.length) : 100 - (c.length - 1) * Math.floor(100 / c.length)) : 0 }); calc(true) } })
    t.querySelectorAll('.pp').forEach(function (p) { p.oninput = function () { calc(true) } })
    t.querySelectorAll('.pd').forEach(function (p) { p.oninput = function () { calc(false, true) }; p.onchange = function () { calc() } })
    calc()
    var b = ct.querySelector('.pe')
    if (b) b.onclick = function () { b.disabled = true; ajax('plan', {id: id, parts: JSON.stringify(parts()), doses: JSON.stringify(doses())}, fini, function (er) { b.disabled = false; ct.querySelector('.pr').textContent = er }) }
  }

  function plante(d, id, depart) {
    var r = null, w, msg = ''
    var att = null // onglet demandé pendant le chargement : seul le dernier est dessiné
    function avec(fn) { return function (ct) { if (r) return fn(ct); var premier = !att; att = [fn, ct]
      if (premier) ajax('plante', {id: id}, function (x) { r = x; var a = att; att = null; a[0](a[1]) }, function (er) { var a = att; att = null; erreur(a[1])(er) }) } }
    function recharger(k) { r = null; w.montrer(k) }
    function charger(img, n, er) { img.onerror = function () { er("{{Photo illisible}}") }; img.src = photo(id, n, 1); img.style.cursor = 'zoom-in'; img.onclick = function () { window.open(photo(id, n)) } } // vignette ; l'original au clic
    var p = d.plantes.filter(function (x) { return x.id == id })[0] || {nom: ''}
    w = fenetre(p.nom, [
      ['fic', "{{Fiche}}", avec(function (ct) {
        var g = r.gbif || {}, s = r.etat
        ct.innerHTML = (r.etiquette ? '<p><img class="jdc-et" alt="' + "{{Étiquette}}" + '" style="max-width:100%;cursor:zoom-in"></p>' : '') + '<table>' +
          '<tr><th scope="row">' + "{{Emplacement}}" + '</th><td>' + esc(s.empl || '–') + (s.t !== null ? ' · ' + nb(s.t, ' °C') : '') + (s.tmin !== null ? ' · ' + "{{min. prévue}}" + ' ' + nb(s.tmin, ' °C') : '') + (s.h !== null ? ' · ' + nb(s.h, ' %') : '') + '</td></tr>' +
          '<tr><th scope="row">' + "{{Pot}}" + '</th><td>' + esc(r.pot) + '</td></tr>' +
          (r.multiplication ? '<tr><th scope="row">' + "{{Multiplication}}" + '</th><td>' + esc(r.multiplication) + '</td></tr>' : '') +
          (r.greffe ? '<tr><th scope="row">' + "{{Greffe}}" + '</th><td class="grs">' + esc(r.greffe.texte) + (r.greffe.greffon ? ' · ' + "{{greffon en attente : porte-greffe et greffon deviendront}}" + ' « ' + esc(r.greffe.greffon) + ' » ' + "{{au débourrement d'un œil}}" : '') + (r.greffe.controle ? ' · ' + "{{contrôle le}}" + ' ' + esc(r.greffe.controle.split('-').reverse().join('/')) : '') +
            (r.greffe.question ? '<br>' + (r.greffe.du ? '<b class="jdc-er">' + esc(r.greffe.question) + '</b>' : '<small>' + esc(r.greffe.question) + '</small>') : '') +
            (r.greffe.temp ? '<br><b class="jdc-er">' + esc(r.greffe.temp) + '</b>' : '') +
            (d.admin == 1 && r.greffe.question ? '<br><input class="gnote" aria-label="' + "{{Note du contrôle (facultatif)}}" + '" maxlength="200" placeholder="' + "{{note (facultatif)}}" + '"> <button type="button" class="jb b gv" data-v="ok">' + "{{Oui}}" + '</button> <button type="button" class="jb b gv" data-v="douteux" title="' + "{{revoir dans 7 jours}}" + '">' + "{{Douteux}}" + '</button> ' + (r.greffe.printemps ? '<button type="button" class="jb b gv" data-v="printemps" title="' + (r.geo && r.geo.sud ? "{{greffon vivant, laissé dormant : contrôle le 15 septembre (plus tôt si l'emplacement est assez chaud)}}" : "{{greffon vivant, laissé dormant : contrôle le 15 mars (plus tôt si l'emplacement est assez chaud)}}") + '">' + "{{Au printemps}}" + '</button> ' : '') + '<button type="button" class="jb b gv" data-v="mort">' + "{{Non, mort}}" + '</button>' : '') +
            ' <span class="jdc-er"></span></td></tr>' : '') +
          '<tr><th scope="row">' + "{{Rusticité effective}}" + '</th><td>' + (s.reff === null ? "{{inconnue (pas de rusticité dans la fiche)}}" : nb(s.reff, ' °C') + (s.du_greffon ? ' <small>(' + "{{celle du greffon en attente, plus fragile}}" + ')</small>' : '')) +
            (s.reff !== null && s.estimee ? ' <small>(' + "{{estimée}}" + ' ' + nb(s.estimee.r, ' °C') + ' : ' + esc(s.estimee.groupes.join(', ')) + (s.estimee.par == 'nom' ? '' : ', ' + "{{d'après les caractères}}") + ')</small>' : '') + (s.reff !== null && s.jeunesse > 0 ? ' <small>(' + "{{dont jeunesse}}" + ' +' + nb(s.jeunesse, ' °C') + ')</small>' : '') + (s.raison ? ' <b class="jdc-er">' + esc(s.raison) + '</b>' : '') + '</td></tr>' +
          (s.engrais ? '<tr><th scope="row">' + "{{Engrais cette semaine}}" + '</th><td>' + esc(s.engrais) + '</td></tr>' : '') +
          (g.nom ? '<tr><th scope="row">GBIF</th><td><span lang="la">' + esc(g.nom) + '</span>' + (g.famille ? ' · ' + esc(g.famille) : '') + ' <small>(' + esc(g.statut) + ', ' + esc(g.type) + ' ' + esc(g.confiance) + ' %)</small></td></tr>' :
            g.statut == 'NONE' ? '<tr><th scope="row">GBIF</th><td class="jdc-er">' + "{{nom botanique introuvable}}" + '</td></tr>' : '') +
          (r.noms && r.noms.length ? '<tr><th scope="row">' + "{{Origine du nom}}" + '</th><td>' + r.noms.join('<br>') + ' <small><button type="button" class="jb lk nms">' + "{{dictionnaire}}" + '</button></small></td></tr>' : '') + // html converti par la box depuis notre noms.md
          r.fiche.map(function (x) { return '<tr><th scope="row">' + esc(x[0]) + '</th><td style="white-space:pre-wrap">' + esc(x[1]) + '</td></tr>' }).join('') + '</table>' +
          blocEngrais(r.engrais, d.admin == 1) + blocSante(r.sante, r.santeCat, r.geo) +
          (r.ident && r.ident.groupes ? '<h4>' + "{{Identification}}" + '</h4><p>' + (r.ident.verdict == 'probable' ? "{{Groupe probable}}" : "{{Indécis entre}}") + ' : ' +
            r.ident.groupes.map(function (g) { return '<b>' + esc(g.groupe) + '</b> <small>(' + g.accords + ' ' + "{{accord(s)}}" + (g.contre.length ? ', ' + "{{contre}}" + ' : ' + esc(g.contre.join(', ')) : '') + ')</small>' }).join(' · ') +
            '</p><table>' + r.carac.map(function (x) { return '<tr><th scope="row">' + esc(x[0]) + '</th><td>' + esc(x[1]) + '</td></tr>' }).join('') + '</table><p><small>' +
            "{{Groupe seulement, jamais le cultivar. Comparer :}}" + ' <a href="https://idtools.org/id/citrus/citrusid/" target="_blank" rel="noopener noreferrer">Citrus ID</a> · ' +
            '<a href="https://citrusvariety.ucr.edu/" target="_blank" rel="noopener noreferrer">UC Riverside</a></small></p>' : '') +
          (r.liens.length ? '<h4>' + "{{Sources en ligne}}" + '</h4>' + r.liens.map(function (l) { return '<a href="' + esc(l[1]) + '" target="_blank" rel="noopener noreferrer">' + esc(l[0]) + '</a>' }).join(' · ') : '') +
          (d.admin == 1 ? '<p><small>' + "{{La fiche se modifie dans la page du plugin (équipement de la plante).}}" + '</small> <button type="button" class="jb b grf" title="' +
            "{{La plante reste le porte-greffe tant qu'aucun œil du greffon n'a débourré ; au contrôle qui le confirme, porte-greffe et greffon deviennent une nouvelle plante, qui prend le nom, la fiche et la collection du greffon (journal, photos, pot et mesures gardés). Greffe morte : elle reste, ou redevient, le porte-greffe. Greffe en pont : réparation de l'écorce, la plante garde son nom et sa fiche.}}" + '">' + "{{Greffer…}}" + '</button> <button type="button" class="jb b idn">' + "{{Identifier…}}" + '</button></p>' : '')
        var nm = ct.querySelector('.nms'); if (nm) nm.onclick = function () { notice('noms') }
        var gb = ct.querySelector('.grf')
        if (gb) gb.onclick = function () { w.fermer(); nouvelle(d, d.apres, {id: id, nom: r.nom}) }
        ct.querySelectorAll('.gv').forEach(function (a) { a.onclick = function () { // « Non, mort » : deuxième clic pour confirmer
          if (a.dataset.v == 'mort' && !a.dataset.ok) { a.dataset.ok = 1; a.textContent = "{{Confirmer : greffe échouée}}"; return }
          var bs = ct.querySelectorAll('.gv'); bs.forEach(function (x) { x.disabled = true }) // double appui : une seule réponse
          ajax('controler', {id: id, verdict: a.dataset.v, note: ct.querySelector('.gnote').value}, function () { recharger('fic') }, function (er) { bs.forEach(function (x) { x.disabled = false }); ct.querySelector('.grs .jdc-er').textContent = er })
        } })
        brancherEngrais(ct, r.engrais, id, function () { recharger('fic') })
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
        ct.innerHTML = (d.admin == 1 ? '<form><label>' + "{{Ajouter une photo}}" + '</label><span><input type="file" accept="image/jpeg,image/png,image/webp,image/*"> <span class="jdc-er"></span></span></form>' : '') +
          '<div class="jdc-ph">' + r.photos.map(function (n) {
            return '<figure data-n="' + esc(n) + '"><img alt="' + "{{Photo du}}" + ' ' + esc(n.slice(6, 8) + '/' + n.slice(4, 6) + '/' + n.slice(0, 4)) + '"><figcaption>' + esc(n.slice(6, 8) + '/' + n.slice(4, 6) + '/' + n.slice(0, 4)) +
              (d.admin == 1 ? (r.plantnet == 1 ? ' · <select class="org" aria-label="' + "{{Organe photographié}}" + '"><option value="auto">' + "{{auto}}" + '</option><option value="leaf">' + "{{feuille}}" + '</option><option value="flower">' + "{{fleur}}" + '</option><option value="fruit">' + "{{fruit}}" + '</option><option value="bark">' + "{{écorce}}" + '</option></select> <button type="button" class="jb b idf">Pl@ntNet</button>' : '') +
                ' <button type="button" class="jb b eti" aria-pressed="' + (n == r.etiquette) + '">' + (n == r.etiquette ? "{{★ étiquette}}" : "{{☆ étiquette}}") + '</button> <button type="button" class="jb b sup" title="' + "{{Supprimer}}" + '" aria-label="' + "{{Supprimer la photo}}" + '">✕</button>' : n == r.etiquette ? ' · ' + "{{étiquette}}" : '') + '<div class="res"></div></figcaption></figure>'
          }).join('') + '</div>' + (r.photos.length ? '' : '<p>' + "{{Aucune photo.}}" + '</p>')
        ct.querySelectorAll('.jdc-ph figure').forEach(function (fg) {
          var n = fg.dataset.n, img = fg.querySelector('img'), res = fg.querySelector('.res')
          charger(img, n, function (er) { res.textContent = er })
          var s = fg.querySelector('.sup'), i = fg.querySelector('.idf'), et = fg.querySelector('.eti')
          if (et) et.onclick = function () { if (et.dataset.envoi) return; et.dataset.envoi = 1; ajax('etiquette', {id: id, nom: n == r.etiquette ? '' : n}, function () { recharger('pho') }, function (er) { delete et.dataset.envoi; res.textContent = er }) } // ★ = retirer
          if (s) s.onclick = function () { if (s.dataset.envoi) return; if (s.dataset.ok) { s.dataset.envoi = 1; ajax('photo_suppr', {id: id, nom: n}, function () { recharger('pho') }, function (er) { delete s.dataset.envoi; res.textContent = er }) } else { s.dataset.ok = 1; s.removeAttribute('aria-label'); s.textContent = "{{Confirmer ✕}}" } }
          if (i) i.onclick = function () {
            if (i.dataset.envoi) return; i.dataset.envoi = 1 // double clic : quota Pl@ntNet compté une fois
            res.textContent = "{{Identification…}}"
            ajax('identifier', {id: id, noms: JSON.stringify([n]), organes: JSON.stringify([fg.querySelector('.org').value])}, function (l) {
              delete i.dataset.envoi
              res.innerHTML = l.length ? l.map(function (x) { return '<div><b>' + esc(x.score) + ' %</b> <i>' + esc(x.nom) + '</i> ' + esc(x.auteur) + (x.communs ? ' — ' + esc(x.communs) : '') + ' <small>' + esc(x.famille) + '</small></div>' }).join('') : "{{Aucune espèce reconnue.}}"
            }, function (er) { delete i.dataset.envoi; res.textContent = er })
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
        var lm = r.releves // toutes les mesures, plus anciennes d'abord
        var ks = []; lm.forEach(function (x) { Object.keys(x.valeurs).forEach(function (k) { if (typeof x.valeurs[k] == 'number' && ks.indexOf(k) < 0) ks.push(k) }) })
        function lib(k) {
          var o = k.split('.'), f = o[0] == 'calc' ? r.calculs[k] && [0, r.calculs[k][0], r.calculs[k][1]] : M[o[0]] && M[o[0]][1][o[1]]
          return f ? (o[0] == 'calc' ? '' : M[o[0]][0] + ' · ') + f[1] + (f[2] && f[0] != 'c' ? ' (' + f[2] + ')' : '') : k
        }
        if (d.admin == 1) h += '<div class="jdc-org">' + Object.keys(M).map(function (o) { return '<button type="button" class="jb" aria-pressed="false" data-o="' + o + '">' + esc(M[o][0]) + '</button>' }).join('') + '</div>' +
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
        h += '<h4>' + "{{Courbe}}" + '</h4>' + (ks.length ? '<select class="jdc-cs" aria-label="' + "{{Mesure affichée}}" + '">' + ks.map(function (k) { return '<option value="' + esc(k) + '">' + esc(lib(k)) + '</option>' }).join('') + '</select><div class="jdc-svg"></div>' :
          '<p><small>' + "{{Aucune mesure du catalogue pour l'instant.}}" + '</small></p>') +
          '<h4>' + "{{Historiques}}" + '</h4><table>' + Object.keys(c).filter(function (x) { return ['raison', 'engrais', 'greffe'].indexOf(x) < 0 }) /* textes non historisés */.map(function (x) {
            return '<tr><td>' + esc(c[x].nom) + '</td><td class="n">' + (x == 'emplacement' ? esc(c[x].val) : nb(c[x].val, c[x].unite ? ' ' + c[x].unite : '')) + '</td><td><button type="button" class="jb b hist" data-id="' + esc(c[x].id) + '" data-nom="' + esc(c[x].nom) + '" aria-label="' + "{{Historique}}" + ' : ' + esc(c[x].nom) + '">' + "{{Historique}}" + '</button></td></tr>'
          }).join('') + '</table>'
        ct.innerHTML = h
        function organe(o) { ct.querySelectorAll('.jdc-org button, .jdc-g').forEach(function (e) { e.classList.toggle('on', e.dataset.o == o); if (e.tagName == 'BUTTON') e.setAttribute('aria-pressed', e.dataset.o == o) }) }
        ct.querySelectorAll('.jdc-org button').forEach(function (a) { a.onclick = function () { organe(a.dataset.o) } })
        organe(org)
        var cs = ct.querySelector('.jdc-cs')
        function courbe() { // ponytail: SVG simple (points reliés, min et max) ; une bibliothèque de graphes si on veut superposer des mesures
          var k = cs.value, p = lm.filter(function (x) { return typeof x.valeurs[k] == 'number' }).map(function (x) { return [Date.parse(x.date), x.valeurs[k], x.date] })
          var W = 600, H = 170, g = 34, t0 = p[0][0], t1 = p[p.length - 1][0], vs = p.map(function (x) { return x[1] }), v0 = Math.min.apply(null, vs), v1 = Math.max.apply(null, vs)
          function X(t) { return t1 > t0 ? g + (t - t0) / (t1 - t0) * (W - 2 * g) : W / 2 }
          function Y(v) { return v1 > v0 ? H - g - (v - v0) / (v1 - v0) * (H - 2 * g) : H / 2 }
          ct.querySelector('.jdc-svg').innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-opacity=".25"><line x1="' + g + '" y1="' + (H - g) + '" x2="' + (W - g) + '" y2="' + (H - g) + '"/></g>' +
            '<polyline fill="none" stroke="#2F6B3A" stroke-width="2" points="' + p.map(function (x) { return X(x[0]) + ',' + Y(x[1]) }).join(' ') + '"/>' +
            p.map(function (x) { return '<circle cx="' + X(x[0]) + '" cy="' + Y(x[1]) + '" r="4" fill="#2F6B3A"><title>' + esc(x[2] + ' : ' + nb(x[1])) + '</title></circle>' }).join('') +
            '<g fill="currentColor" font-size="12"><text x="2" y="' + (Y(v1) + 4) + '">' + nb(v1) + '</text>' + (v1 > v0 ? '<text x="2" y="' + (Y(v0) + 4) + '">' + nb(v0) + '</text>' : '') +
            '<text x="' + g + '" y="' + (H - 8) + '">' + esc(p[0][2]) + '</text>' + (p.length > 1 ? '<text x="' + (W - g) + '" y="' + (H - 8) + '" text-anchor="end">' + esc(p[p.length - 1][2]) + '</text>' : '') + '</g></svg>' +
            '<ul class="jdc-sr">' + p.map(function (x) { return '<li>' + esc(x[2] + ' : ' + nb(x[1])) + '</li>' }).join('') + '</ul>' // la courbe en texte, pour VoiceOver
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
    ], depart, {aide: {fic: "fiche-dune-plante", mes: 'mesures', pho: 'photos', jou: 'principe'}})
  }

  var IMG = 'image/jpeg,image/png,image/webp,image/*'
  function orgHtml() { return '<select class="org" aria-label="' + "{{Organe photographié}}" + '" style="width:auto"><option value="auto">' + "{{auto}}" + '</option><option value="leaf">' + "{{feuille}}" + '</option><option value="flower">' + "{{fleur}}" +
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
      return '<div><button type="button" class="jb b uti" data-nom="' + esc(x.nom) + '" data-commun="' + esc(x.communs.split(', ')[0]) + '" data-score="' + esc(x.score) + '">' + "{{Retenir}}" + '</button> <b>' + esc(x.score) + ' %</b> <i>' + esc(x.nom) + '</i> ' + esc(x.auteur) + (x.communs ? ' — ' + esc(x.communs) : '') + '</div>'
    }).join('') : "{{Aucune espèce reconnue.}}"
  }
  /* Photos de « Identifier… » et « ＋ Plante » : ex = photos déjà rangées de la plante (non cochées), le champ fichier en ajoute plusieurs (cochées) ;
     avec pn, chacune a sa case et son organe, et « Pl@ntNet » envoie les cochées (1 à 5) en une seule demande. retenir(a) : clic sur « Retenir ».
     → fichiers() : photos nouvelles gardées (« ＋ Plante » les range dans la plante créée). */
  function photosPn(el, ex, pn, id, retenir) {
    var nv = [] // fichiers choisis ; un nouveau choix s'ajoute aux précédents, ✕ en retire un
    el.innerHTML = '<div class="phs"></div><input type="file" multiple accept="' + IMG + '" aria-label="' + "{{Ajouter des photos}}" + '" style="width:auto">' + (pn ? ' <button type="button" class="jb b idf">Pl@ntNet</button>' : '') + '<div class="res"></div>'
    var phs = el.querySelector('.phs'), fi = el.querySelector('input[type=file]'), res = el.querySelector('.res'), idf = el.querySelector('.idf')
    function vignette(dv, b) { var fr = new FileReader(); fr.onload = function () { dv.querySelector('img').src = fr.result }; fr.readAsDataURL(b) } // data: (CSP de Jeedom)
    function ligne(lib, k, coche) {
      var dv = document.createElement('div'); dv.dataset.k = k; dv.style.cssText = 'display:flex;align-items:center;gap:6px;margin:2px 0' // une ligne par photo, même sur mobile
      dv.innerHTML = (pn ? '<label style="display:flex;align-items:center;gap:4px;margin:0;white-space:nowrap;min-width:0"><input type="checkbox" style="flex:none"' + (coche ? ' checked' : '') + '> ' : '') + '<img alt="" style="flex:none;height:40px;width:40px;object-fit:cover;vertical-align:middle;margin-right:4px"> <span style="overflow:hidden;text-overflow:ellipsis">' + esc(lib) + '</span>' + (pn ? '</label> ' + orgHtml() : '') +
        (k[0] == 'f' ? ' <button type="button" class="jb b sup" title="' + "{{Retirer}}" + '" aria-label="' + "{{Retirer}}" + ' ' + esc(lib) + '">✕</button>' : '')
      var x = dv.querySelector('.sup'); if (x) x.onclick = function () { nv[+k.slice(1)] = null; dv.remove() }
      phs.appendChild(dv); return dv
    }
    ex.forEach(function (n) { var dv = ligne(n.slice(6, 8) + '/' + n.slice(4, 6) + '/' + n.slice(2, 4) + ' ' + n.slice(9, 11) + ':' + n.slice(11, 13), 'n' + n, false)
      var im = dv.querySelector('img'); im.onerror = function () { im.alt = '⚠'; im.title = "{{Photo illisible (session expirée ?)}}" }; im.src = photo(id, n, 1) })
    fi.onchange = function () { Array.prototype.forEach.call(fi.files, function (f) { nv.push(f); vignette(ligne(f.name, 'f' + (nv.length - 1), true), f) }); fi.value = '' }
    if (idf) idf.onclick = function () {
      var ch = {id: id}, noms = [], org = [], fs = []
      phs.querySelectorAll('div').forEach(function (dv) { if (!dv.querySelector('input').checked) return
        if (dv.dataset.k[0] == 'n') { noms.push(dv.dataset.k.slice(1)); org.push(dv.querySelector('.org').value) } else fs.push([nv[+dv.dataset.k.slice(1)], dv.querySelector('.org').value]) })
      fs.forEach(function (x, i) { ch['photo' + i] = x[0]; org.push(x[1]) }) // côté serveur : noms d'abord, puis fichiers
      if (!noms.length && !fs.length) { res.textContent = "{{Cocher d'abord une photo.}}"; return }
      if (noms.length + fs.length > 5) { res.textContent = "{{5 photos au plus par demande.}}"; return }
      if (idf.dataset.envoi) return // une identification à la fois (quota de 500 par jour)
      idf.dataset.envoi = 1; res.textContent = "{{Identification…}}"
      ch.noms = JSON.stringify(noms); ch.organes = JSON.stringify(org)
      ajax('identifier', ch, function (l) {
        delete idf.dataset.envoi; res.innerHTML = plantnetHtml(l)
        res.querySelectorAll('.uti').forEach(function (a) { a.onclick = function () { retenir(a) } })
      }, function (er) { delete idf.dataset.envoi; res.textContent = er })
    }
    return {fichiers: function () { return nv.filter(Boolean) }}
  }

  /* « Identifier… » (fiche, admins) : Pl@ntNet sur une photo de la plante ou une nouvelle, clé des caractères partant de la fiche,
     puis tableau des champs qui changeraient ; rien n'est coché d'office, seules les lignes cochées sont écrites (et notées au journal). */
  function identifier(d, id, r) {
    var B = r.brut, w = fenetre("{{Identifier}}" + ' ' + r.nom, [['i', "{{Identification}}", function (ct) {
      ajax('modele', {}, function (M) {
        ct.innerHTML = '<form class="jdc-np">' +
          '<label>' + "{{Photos}}" + '</label><span class="pv">' + (M.plantnet == 1 ? '' : "{{Pl@ntNet : clé absente (configuration du plugin)}}") + '</span>' +
          '<label>' + "{{Nom botanique}}" + '</label><input name="bota" maxlength="120" value="' + esc(B.bota) + '">' +
          '<details open><summary>' + "{{Clé d'identification : cocher ce qu'on voit}}" + '</summary>' + cleHtml(M.caracteres, B.carac) + '</details><div class="rec"></div>' +
          '<h4 style="grid-column:1/-1">' + "{{Propositions : cocher ce qu'on accepte}}" + '</h4><div class="pro" style="grid-column:1/-1"></div>' +
          '<span></span><span><button type="submit">' + "{{Appliquer}}" + '</button> <span class="jdc-er"></span></span></form>'
        var f = ct.querySelector('form'), F = f.elements, rec = f.querySelector('.rec'), pro = f.querySelector('.pro')
        var n = 0, fait = 0, est = null, retenu = null, coch = {}, lignes = [] // coch : « champ␁valeur », une case ne suit pas une valeur qui change
        function propositions() { // [clé, libellé, actuel, proposé (affiché), valeur écrite, source]
          var C = M.caracteres, c = caracDe(f), b = F.bota.value.trim(), L = []
          if (b && b != B.bota) L.push(['bota', "{{Nom botanique}}", B.bota, b, b, retenu && retenu.nom == b ? 'Pl@ntNet ' + retenu.score + ' %' : ''])
          Object.keys(c).forEach(function (k) { if (c[k] != (B.carac[k] || '')) L.push(['id_' + k, C[k][0], B.carac[k] ? C[k][1][B.carac[k]] : '', C[k][1][c[k]], c[k], '']) })
          Object.keys(C).forEach(function (k) { if (B.carac[k] && C[k][1][B.carac[k]] && !c[k]) L.push(['id_' + k, C[k][0], C[k][1][B.carac[k]], '–', '', '']) }) // remis à « – » : effacé
          var m = String(B.rusticite).replace(/[−–]/g, '-').match(/-?\s*\d+(?:[.,]\d+)?/), ra = m ? parseFloat(m[0].replace(/\s/g, '').replace(',', '.')) : null // comme nombre() côté serveur
          if (est && ra !== est.r) { var v = nb(est.r) + ' (' + "{{estimée}}" + ')' // court (40 caractères au plus) ; les groupes vont dans la source, donc au journal
            L.push(['rusticite', "{{Rusticité : température minimale supportée par une plante adulte en pleine terre (°C)}}", B.rusticite, v, v, (est.par == 'nom' ? "{{d'après le nom botanique}}" : "{{d'après les caractères}}") + ' : ' + est.groupes.join(', ')]) }
          lignes = L
          pro.innerHTML = L.length ? '<table><tr><th></th><th>' + "{{Champ}}" + '</th><th>' + "{{Actuel}}" + '</th><th>' + "{{Proposé}}" + '</th><th>' + "{{Source}}" + '</th></tr>' + L.map(function (x) {
            return '<tr><td><input type="checkbox" aria-label="' + esc(x[1]) + '" data-k="' + esc(x[0] + '\u0001' + x[4]) + '"' + (coch[x[0] + '\u0001' + x[4]] ? ' checked' : '') + '></td><td>' + esc(x[1]) + '</td><td>' + esc(x[2] || '–') + '</td><td><b>' + esc(x[3]) + '</b></td><td><small>' + esc(x[5]) + '</small></td></tr>'
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
        if (M.plantnet == 1) photosPn(f.querySelector('.pv'), r.photos, true, id, function (a) { retenu = {nom: a.dataset.nom, score: a.dataset.score}; F.bota.value = a.dataset.nom; reconnaitre() })
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
  // pg {id, nom} : « Greffer… » depuis la fiche d'un porte-greffe, même formulaire : nom, collection et fiche du greffon, pris par la plante au débourrement d'un œil (emplacement et pot gardés) ; pont : réparation, ni nom ni fiche
  function nouvelle(d, apres, pg) {
    var w = fenetre(pg ? "{{Greffer}}" + ' ' + pg.nom : "{{Nouvelle plante}}", [['n', d.nom, function (ct) {
      ajax('modele', pg ? {pg: pg.id} : {}, function (M) {
        var C = M.caracteres
        function champ(lib, h) { return '<label>' + lib + '</label>' + h }
        ct.innerHTML = '<form class="jdc-np">' +
          champ("{{Emplacement}}", '<select name="emplacement"' + (pg || !d.emplacements.length ? '' : ' required') + '><option value="">' + "{{— où sera la plante ? —}}" + '</option>' + d.emplacements.map(function (e) { return '<option value="' + esc(e) + '">' + esc(e) + '</option>' }).join('') + '</select>') +
          champ(pg ? "{{Collection de la nouvelle plante}}" : "{{Collection}}", '<select name="collection">' + M.collections.map(function (c) { return '<option value="' + c[0] + '"' + (c[0] == d.id ? ' selected' : '') + '>' + esc(c[1]) + '</option>' }).join('') + '</select>') +
          (pg ? champ("{{Greffon}}", '<input name="greffon" maxlength="120" list="jdc-cv" placeholder="' + "{{une de nos plantes, ou un autre greffon}}" + '"><datalist id="jdc-cv">' +
            M.cultivars.map(function (x) { return '<option value="' + esc(x[0]) + '">' + esc([x[1], x[2]].filter(String).join(' · ')) + '</option>' }).join('') + '</datalist>') : '') +
          champ(pg ? "{{Nom de la nouvelle plante}}" : "{{Nom}}", '<input name="nom" required maxlength="120"' + (pg ? ' placeholder="' + "{{nom donné au débourrement d'un œil, ex. Yuzu n°2}}" + '"' : '') + '>') +
          champ("{{Photos}}", '<span class="pv"></span>') +
          champ("{{Nom botanique}}", '<input name="bota" maxlength="120" placeholder="Citrus limon">') +
          '<details><summary>' + "{{Clé d'identification : cocher ce qu'on voit}}" + '</summary>' + cleHtml(C, {}) + '</details><div class="rec"></div>' +
          champ("{{Cultivar}}", '<input name="cultivar" maxlength="120">') +
          champ("{{Porte-greffe}}", '<input name="porte_greffe" maxlength="120" list="jdc-pgn"><datalist id="jdc-pgn">' + Object.keys(M.porte_greffes).map(function (k) { return '<option value="' + esc(k) + '">' + esc(M.porte_greffes[k]) + '</option>' }).join('') + '</datalist>') +
          champ("{{Rusticité : température minimale supportée par une plante adulte en pleine terre (°C)}}", '<input name="rusticite" maxlength="40" placeholder="' + "{{vide : estimée d'après le groupe}}" + '">') +
          champ("{{Multiplication}}", '<span><select name="multiplication" style="width:auto"><option value=""></option>' + M.multiplications.map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>' }).join('') +
            '</select> <select name="type_greffe" style="width:auto;display:none"><option value="">' + "{{type de greffe ?}}" + '</option>' +
            Object.keys(M.greffes).filter(function (k) { return pg || k != 'pont' }).map(function (k) { // pont : réparation (« Greffer… »), pas une multiplication
               return '<option value="' + esc(k) + '">' + esc(M.greffes[k]) + '</option>' }).join('') +
            '</select> <input name="date_mult" maxlength="10" pattern="\\d{4}(-\\d{2}(-\\d{2})?)?" title="AAAA, AAAA-MM ou AAAA-MM-JJ" placeholder="2024-05" style="width:8em"><br><small class="jdc-saison"></small></span>') +
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
        F.type_greffe.onchange = function () { var t = (M.greffe_saisons || {})[F.type_greffe.value], po = pg && F.type_greffe.value == 'pont'; f.querySelector('.jdc-saison').textContent = t ? "{{Saison}} : " + t : ''
          if (!pg) return // pont : réparation, la plante garde nom, collection et fiche ; ces champs sont masqués (et ignorés par le serveur)
          F.nom.required = !po; f.querySelector('details').style.display = rec.style.display = f.querySelector('.pv').style.display = po ? 'none' : ''
          ;[F.collection, F.nom, F.bota, F.cultivar, F.porte_greffe, F.rusticite].forEach(function (e) { e.style.display = e.previousElementSibling.style.display = po ? 'none' : '' }) }
        F.multiplication.onchange = function () { var g = F.multiplication.value == 'greffe'; F.type_greffe.style.display = g ? '' : 'none'; if (!g) F.type_greffe.value = ''; F.type_greffe.onchange() }
        if (pg) { // greffe : multiplication imposée, date du jour, porte-greffe déduit du nom (« Flying Dragon 3 » → Flying Dragon), emplacement et pot inchangés
          F.multiplication.value = 'greffe'; F.multiplication.onchange(); F.multiplication.style.display = 'none'; F.date_mult.value = auj()
          F.porte_greffe.value = Object.keys(M.porte_greffes).sort(function (a, b) { return b.length - a.length }).filter(function (k) { return pg.nom.toLowerCase().indexOf(k.toLowerCase()) >= 0 })[0] || pg.nom
          ;[F.emplacement, F.pot_forme.parentNode].forEach(function (e) { e.style.display = e.previousElementSibling.style.display = 'none' })
          f.querySelector('button[type=submit]').textContent = "{{Greffer}}"
          F.greffon.onchange = function () { // un de nos cultivars : nom botanique, cultivar et caractères recopiés ; sinon texte libre = cultivar
            var g = F.greffon.value.trim(), x = M.cultivars.filter(function (c) { return c[0] == g })[0]
            if (!g) return
            if (!F.nom.value) F.nom.value = g + ' sur ' + pg.nom
            if (!x) { if (!F.cultivar.value) F.cultivar.value = g; return }
            F.cultivar.value = x[1]; F.bota.value = x[2]
            f.querySelectorAll('details input[value=""]').forEach(function (i) { i.checked = true })
            Object.keys(x[3]).forEach(function (k) { var i = f.querySelector('details input[name="id_' + k + '"][value="' + x[3][k] + '"]'); if (i) i.checked = true })
            reconnaitre()
          }
        }
        f.onkeydown = function (e) { // Entrée dans un champ (« OK » du clavier iPhone) ne crée pas la plante à moitié remplie
          if (e.key == 'Enter' && e.target.tagName == 'INPUT') { e.preventDefault(); if (e.target == F.bota) reconnaitre() }
        }
        var PH = photosPn(f.querySelector('.pv'), [], M.plantnet == 1, null, function (a) { F.bota.value = a.dataset.nom; if (!F.nom.value) F.nom.value = a.dataset.commun; reconnaitre() })
        f.onsubmit = function (ev) {
          ev.preventDefault()
          var t = {carac: carac()}, bt = f.querySelector('button[type=submit]'), er = f.querySelector('.jdc-er'), phs = PH.fichiers(), i = 0, refus = []
          new FormData(f).forEach(function (v, k) { if (k != 'collection' && k.indexOf('id_') != 0 && !(v instanceof File)) t[k] = v })
          bt.disabled = true; er.textContent = '' // pas de double envoi
          ajax(pg ? 'greffer' : 'creer', pg ? {id: pg.id, collection: F.collection.value, plante: JSON.stringify(t)} : {collection: F.collection.value, plante: JSON.stringify(t)}, function (id) {
            function ouvrir(k) { w.fermer(); if (apres) apres(); plante(Object.assign({}, d, {plantes: d.plantes.filter(function (x) { return x.id != id }).concat([{id: id, nom: pg && (t.type_greffe == 'pont' || !(/^\d{4}-\d{2}-\d{2}$/.test(t.date_mult || '') && new Date(t.date_mult) < Date.now() - 60 * 864e5)) ? pg.nom : t.nom}])}), id, k) }
            ;(function suivante() { // photos rangées une à une dans la plante créée
              if (i == phs.length) { // plante créée, photo(s) refusée(s) : le dire avant d'ouvrir la fiche
                if (!refus.length) return ouvrir(phs.length ? 'pho' : undefined)
                er.textContent = "{{Plante créée, mais photo refusée :}} " + refus.join(' ; ') + ' ' + "{{(la remettre dans l'onglet Photos)}}"
                bt.textContent = "{{Ouvrir la fiche}}"; bt.disabled = false; f.onsubmit = function (ev) { ev.preventDefault(); ouvrir('pho') }
                return
              }
              ajax('photo_ajout', {id: id, photo: phs[i]}, function () { i++; suivante() }, function (e) { refus.push(phs[i].name + ' : ' + e); i++; suivante() })
            })()
          }, function (e) { bt.disabled = false; er.textContent = e })
        }
      }, erreur(ct))
    }]])
  }

  // opt.panel : colonnes en plus (porte-greffe, dernière mesure), masquées sur téléphone
  window.journalcultures = {v: 31, notice: notice, ajax: ajax, esc: esc, nb: nb, init: function (b, d, opt) {
    if (!b) return
    d.apres = opt && opt.apres // relu par « Greffer… » (fiche)
    var pa = opt && opt.panel
    var al = d.plantes.filter(function (p) { return p.alerte == 1 }), gr = d.plantes.filter(function (p) { return p.gr }), eng = d.plantes.filter(function (p) { return p.engrais })
    b.innerHTML = '<div class="jdc-r">' + d.plantes.length + ' ' + "{{plantes}}" + (al.length ? ' · <span class="al">' + al.length + ' ' + "{{en alerte}}" + '</span>' : '') +
      (gr.length ? ' · <span class="jdc-gr">' + gr.length + ' ' + "{{en greffe}}" + '</span>' : '') + (eng.length ? ' · ' + eng.length + ' ' + "{{engrais à donner}}" : '') + '</div>' +
      '<table><tr>' + (d.admin == 1 ? '<th><input type="checkbox" class="jdc-tt" title="' + "{{Tout cocher}}" + '" aria-label="' + "{{Tout cocher}}" + '"></th>' : '') + '<th>' + "{{Plante}}" + '</th>' + (pa ? '<th class="xs">' + "{{Porte-greffe}}" + '</th>' : '') + '<th>' + "{{Lieu}}" + '</th><th class="n">T°</th><th class="n" title="' + "{{Rusticité effective (pot, âge et greffon compris)}}" + '">' + "{{Rust.}}" + '</th>' + (pa ? '<th class="xs">' + "{{Dernière mesure}}" + '</th>' : '') + '<th></th></tr>' +
      d.plantes.map(function (p) {
        return '<tr class="p" tabindex="0" data-id="' + esc(p.id) + '">' + (d.admin == 1 ? '<td><input type="checkbox" class="jdc-ck" aria-label="' + esc(p.nom) + '" value="' + esc(p.id) + '"></td>' : '') +
          '<td' + (p.alerte == 1 ? ' class="al"' : '') + (p.raison ? ' title="' + esc(p.raison) + '"' : '') + '>' + esc(p.nom) + (p.gr ? ' <span class="jdc-gr" title="' + esc(p.gr) + '">' + "{{greffe}}" + '</span>' : '') + (p.alerte == 1 || p.raison ? ' <span class="al" aria-hidden="true">⚠</span><span class="jdc-sr">' + (p.alerte == 1 ? "{{en alerte}}" : '') + (p.raison ? ' : ' + esc(p.raison) : '') + '</span>' : '') + '</td>' + (pa ? '<td class="xs">' + esc(p.pg || '–') + '</td>' : '') + '<td>' + esc(p.empl || '–') + '</td><td class="n">' + nb(p.t, '°') + '</td>' +
          '<td class="n">' + nb(p.reff, '°') + '</td>' + (pa ? '<td class="xs">' + esc(p.mes || '–') + '</td>' : '') + '<td>' + (p.engrais ? '<span title="' + esc(p.engrais) + '" role="img" aria-label="' + "{{Engrais à donner}}" + ' : ' + esc(p.engrais) + '">🌱</span>' + (d.admin == 1 ? ' <button type="button" class="jb jdc-eng" title="' + "{{Engrais donné}}" + '" aria-label="' + "{{Engrais donné}}" + '">✓</button>' : '') : '') + '</td></tr>'
      }).join('') + '</table>' + (d.plantes.length ? '' : '<p>' + "{{Aucune plante : « ＋ Plante » pour en ajouter.}}" + '</p>') +
      '<div class="jdc-bt">' + (d.admin == 1 ? '<button type="button" class="jb" data-b="nouvelle">＋ ' + "{{Plante}}" + '</button><button type="button" class="jb" data-b="noter">＋ ' + "{{Noter}}" + '</button><button type="button" class="jb" data-b="deplacer">' + "{{Déplacer…}}" + '</button>' : '') + '<button type="button" class="jb" data-b="journal">' + "{{Journal}}" + '</button></div>'

    var tt = b.querySelector('.jdc-tt'), mem = coches[d.id] || (coches[d.id] = {})
    b.querySelectorAll('.jdc-ck').forEach(function (c) { c.checked = !!mem[c.value]; c.onchange = function () { mem[c.value] = c.checked } })
    if (tt) tt.onclick = function () { b.querySelectorAll('.jdc-ck').forEach(function (c) { if (c.closest('tr').style.display == 'none') return; c.checked = tt.checked; mem[c.value] = c.checked }) }
    b.querySelectorAll('tr.p').forEach(function (tr) {
      tr.onkeydown = function (e) { if ((e.key == 'Enter' || e.key == ' ') && e.target == tr) { e.preventDefault(); plante(d, tr.dataset.id) } } // clavier : Entrée ou Espace
      tr.onclick = function (e) {
        if (e.target.classList.contains('jdc-ck')) return
        if (e.target.classList.contains('jdc-eng')) { // rappel d'engrais coché : entrée au journal avec le produit prévu
          var p = d.plantes.filter(function (x) { return x.id == tr.dataset.id })[0]
          if (e.target.dataset.envoi) return // double tap : une seule entrée
          e.target.dataset.envoi = 1; e.target.removeAttribute('aria-label'); e.target.textContent = '…'
          ajax('noter', {entree: JSON.stringify({plantes: [tr.dataset.id], date: auj(), action: 'Engrais', note: p.engrais})}, function () { e.target.textContent = "{{noté}}"; if (opt && opt.apres) opt.apres() }, function (er) { delete e.target.dataset.envoi; e.target.textContent = er })
          return
        }
        plante(d, tr.dataset.id)
      }
    })
    b.querySelectorAll('.jdc-bt button').forEach(function (a) {
      a.onclick = function () {
        if (a.dataset.b == 'nouvelle') return nouvelle(d, opt && opt.apres)
        var ids = cochees(b)
        if (a.dataset.b == 'journal') return fenetre("{{Journal}}", [['j', "{{Toutes les plantes}}", function charger(ct) {
          ajax('journal', {id: d.id}, function (l) { ct.innerHTML = tableJournal(d, l, true); brancherSuppr(ct, function () { charger(ct) }) }, erreur(ct))
        }]])
        if (a.dataset.b == 'noter') return fenetre("{{Noter}}", [['n', ids.length ? noms(d, ids) : "{{Entrée libre (cocher des plantes pour les viser)}}", function (ct) {
          ct.innerHTML = ''; ct.appendChild(formNoter(d, ids, 'Observation', '', function () { ct.innerHTML = '<p>' + "{{Noté.}}" + '</p>'; if (opt && opt.apres) opt.apres() }))
        }]])
        fenetre("{{Déplacer}}", [['d', ids.length ? noms(d, ids) : "{{cocher des plantes}}", function (ct) {
          if (!ids.length) { ct.innerHTML = '<p>' + "{{Cochez d'abord les plantes à déplacer dans la tuile.}}" + '</p>'; return }
          ajax('modele', {}, function (M) {
            function liste(nom, vide, l) { return '<select name="' + nom + '"><option value="">' + vide + '</option>' + l.map(function (x) { return '<option value="' + esc(x[0]) + '">' + esc(x[1]) + '</option>' }).join('') + '</select>' }
            ct.innerHTML = '<form><label>' + "{{Emplacement}}" + '</label>' + liste('e', "{{inchangé}}", d.emplacements.map(function (e) { return [e, e] })) +
              '<label>' + "{{Collection}}" + '</label>' + liste('c', "{{inchangée}}", M.collections.filter(function (c) { return c[0] != d.id })) +
              '<span></span><span><button type="submit">' + "{{Déplacer}}" + '</button> <span class="jdc-er"></span></span></form><p><small>' + "{{Le déplacement est noté au journal de chaque plante.}}" + '</small></p>'
            var f = ct.querySelector('form'), bt = f.querySelector('button')
            f.onsubmit = function (ev) {
              ev.preventDefault(); bt.disabled = true
              ajax('deplacer', {plantes: JSON.stringify(ids), emplacement: f.elements.e.value, collection: f.elements.c.value}, function (n) { ct.innerHTML = '<p>' + n + ' ' + "{{plante(s) déplacée(s).}}" + '</p>'; if (opt && opt.apres) opt.apres() }, function (er) { bt.disabled = false; f.querySelector('.jdc-er').textContent = er })
            }
          }, erreur(ct))
        }]])
      }
    })
  }}
})()

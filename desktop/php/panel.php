<?php
if (!isConnect()) throw new Exception('{{401 - Accès non autorisé}}');
// Page plein écran (menu Accueil, case de la configuration du plugin) : une seule page pour ordinateur, tablette et téléphone.
?>
<style>
  #jdc-panel{padding:8px 4px}
  #jdc-panel header{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px}
  #jdc-panel header h3{margin:0}
  #jdc-panel .jdc-cols button{display:inline-block;cursor:pointer;background:none;color:inherit;font:inherit;border-radius:18px;padding:6px 14px;margin:0 4px 4px 0;border:1px solid rgba(128,128,128,.4)}
  #jdc-panel .jdc-cols button.on{background:#2F6B3A;border-color:#2F6B3A;color:#fff}
  #jdc-panel #jdc-q{flex:1 1 180px;min-width:0;min-height:38px;font-size:16px}
  #jdc-panel .jdc-lieux{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:10px}
  #jdc-panel .jdc-lieux div{border:1px solid rgba(128,128,128,.3);border-radius:10px;padding:8px 12px}
  #jdc-panel .jdc-lieux b{display:block;font-size:22px}
  #jdc-panel .jdc-main{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-start}
  #jdc-panel .jdc-liste{flex:999 1 520px;min-width:0;overflow-x:auto}
  #jdc-panel .jdc-aside{flex:1 1 380px;min-width:0;border:1px solid rgba(128,128,128,.3);border-radius:10px;padding:8px}
  #jdc-panel .jdc{font-size:15px}#jdc-panel .jdc td,#jdc-panel .jdc th{padding:7px 6px}
  #jdc-panel .jdc input[type=checkbox]{width:20px;height:20px}
  #jdc-panel .jdc-bt a{padding:8px 16px}
  @media (max-width:900px){#jdc-panel .jdc-aside{display:none}}
</style>
<div id="jdc-panel">
  <header>
    <h3>{{Journal de cultures}}</h3>
    <nav class="jdc-cols" aria-label="{{Collections}}"></nav>
    <input id="jdc-q" type="search" class="form-control" placeholder="{{Chercher une plante…}}" aria-label="{{Chercher une plante}}">
    <button type="button" class="btn btn-default" id="jdc-not" title="{{Notice et guide de culture}}"><i class="fas fa-book"></i> {{Notice}}</button>
    <button type="button" class="btn btn-default" id="jdc-maj" title="{{Rafraîchir}}" aria-label="{{Rafraîchir}}"><i class="fas fa-sync"></i></button>
  </header>
  <section class="jdc-lieux" aria-label="{{Emplacements}}"></section>
  <div class="jdc-main">
    <div class="jdc-liste"><div class="jdc"></div></div>
    <aside class="jdc-aside" aria-label="{{Fiche de la plante}}"><p>{{Choisir une plante dans la liste.}}</p></aside>
  </div>
</div>
<script><?php echo journalcultures::lib(); ?></script>
<script>
(function () {
  var J = window.journalcultures, P = document.getElementById('jdc-panel'), q = P.querySelector('#jdc-q'), data = null, sel = null
  J.cible = P.querySelector('.jdc-aside')
  try { sel = localStorage.getItem('jdc-collection') } catch (e) {}
  function filtrer() {
    var t = q.value.trim().toLowerCase()
    P.querySelectorAll('.jdc tr.p').forEach(function (tr) { tr.style.display = !t || tr.textContent.toLowerCase().indexOf(t) >= 0 ? '' : 'none' })
  }
  function rendre() {
    var cs = data.collections, c = cs.filter(function (x) { return String(x.id) == sel })[0] || cs[0]
    P.querySelector('.jdc-cols').innerHTML = cs.map(function (x) { return '<button type="button" data-id="' + x.id + '" aria-pressed="' + (x === c) + '" class="' + (x === c ? 'on' : '') + '">' + J.esc(x.nom) + ' · ' + x.plantes.length + '</button>' }).join('')
    P.querySelectorAll('.jdc-cols button').forEach(function (a) { a.onclick = function () { sel = a.dataset.id; try { localStorage.setItem('jdc-collection', sel) } catch (e) {} rendre() } })
    P.querySelector('.jdc-lieux').innerHTML = data.lieux.map(function (l) { return '<div>' + J.esc(l.nom) + '<b>' + J.nb(l.t, ' °C') + '</b>' + (l.h !== null ? J.nb(l.h, ' %') : '') + '</div>' }).join('')
    if (c) J.init(P.querySelector('.jdc'), c, {panel: 1, apres: charger}); else P.querySelector('.jdc').textContent = "{{Aucune collection visible.}}"
    filtrer()
  }
  function charger() { J.ajax('page', {}, function (d) { data = d; rendre() }, function (er) { P.querySelector('.jdc').textContent = er }) }
  q.oninput = filtrer
  P.querySelector('#jdc-maj').onclick = charger
  P.querySelector('#jdc-not').onclick = function () { J.notice('index') }
  var t = setInterval(function () { // ponytail: relu toutes les 5 min (pas d'écoute des évènements Jeedom)
    if (!P.isConnected) return clearInterval(t) // Jeedom change de page sans recharger : on s'arrête une fois le panel quitté
    if (document.visibilityState == 'visible') charger()
  }, 300000)
  charger()
})()
</script>

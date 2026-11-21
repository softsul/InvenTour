/*
 * Portal do destino (etapa 5): menu no celular, filtros que se aplicam ao
 * mudar, Meu roteiro guardado no navegador (sem conta e sem cookie) e o
 * visualizador de fotos da ficha. Softsul Software.
 */
(function () {
  'use strict';
  var CHAVE = 'inventour-meu-roteiro:' + location.host;

  // ---------------------------------------------------------------- menu
  var botaoMenu = document.querySelector('.abrir-menu-portal');
  var menu = document.getElementById('menu-portal');
  if (botaoMenu && menu) {
    botaoMenu.addEventListener('click', function () {
      var aberto = menu.classList.toggle('aberto');
      botaoMenu.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
  }

  // ---------------------------------------------------------------- filtros
  document.querySelectorAll('form[data-envia-ao-mudar]').forEach(function (form) {
    form.setAttribute('data-com-js', '');
    form.querySelectorAll('select').forEach(function (s) {
      s.addEventListener('change', function () { form.submit(); });
    });
  });

  // ---------------------------------------------------------------- Meu roteiro
  function ler() {
    try { var l = JSON.parse(localStorage.getItem(CHAVE) || '[]'); return Array.isArray(l) ? l.filter(function (n) { return n > 0; }) : []; } catch (e) { return []; }
  }
  function gravar(l) {
    try { localStorage.setItem(CHAVE, JSON.stringify(l.slice(0, 30))); } catch (e) {}
    pintar();
  }
  function previa() {
    var m = location.search.match(/[?&]previa=([^&]+)/);
    return m ? 'previa=' + m[1] : '';
  }
  function enderecoDoRoteiro(l) {
    var q = [];
    if (l.length) { q.push('i=' + l.join('.')); }
    if (previa()) { q.push(previa()); }
    return '/meu-roteiro' + (q.length ? '?' + q.join('&') : '');
  }
  function pintar() {
    var l = ler();
    document.querySelectorAll('[data-roteiro]').forEach(function (b) {
      var tem = l.indexOf(parseInt(b.getAttribute('data-roteiro'), 10)) !== -1;
      b.setAttribute('aria-pressed', tem ? 'true' : 'false');
      var t = b.querySelector('span');
      if (t) { t.textContent = tem ? 'No meu roteiro' : 'Guardar no meu roteiro'; }
    });
    document.querySelectorAll('[data-conta-roteiro]').forEach(function (c) {
      c.textContent = String(l.length);
      c.hidden = !l.length;
    });
    document.querySelectorAll('.meu-roteiro-link').forEach(function (a) { a.href = enderecoDoRoteiro(l); });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-roteiro]');
    if (!b) { return; }
    ev.preventDefault();
    var id = parseInt(b.getAttribute('data-roteiro'), 10);
    var l = ler();
    var i = l.indexOf(id);
    if (i === -1) { l.push(id); } else { l.splice(i, 1); }
    gravar(l);
  });
  // Na página do roteiro: a lista do endereço manda (link recebido de outra pessoa
  // vira o roteiro deste aparelho só se ele ainda não tiver um).
  var pagina = document.querySelector('[data-meu-roteiro]');
  if (pagina) {
    // data-ids: os lugares que a página achou e mostra (só os públicos).
    var doEndereco = (pagina.getAttribute('data-ids') || '').split('.').map(Number).filter(function (n) { return n > 0; });
    var guardados = ler();
    var jaPediu = /[?&]i=/.test(location.search);
    if (!jaPediu && guardados.length) {
      // Uma vez só: o endereço passa a levar a lista guardada. Se a página
      // não achar algum lugar, não volta a recarregar (era o pisca-pisca).
      location.replace(enderecoDoRoteiro(guardados));
      return;
    }
    if (jaPediu && !guardados.length) { gravar(doEndereco); }
    // Lugar guardado que não está mais no guia (ou só existia na prévia) sai da lista.
    if (jaPediu && guardados.length && !previa()) {
      var validos = guardados.filter(function (n) { return doEndereco.indexOf(n) !== -1; });
      if (validos.length !== guardados.length) { gravar(validos); }
    }
    var limpar = pagina.querySelector('[data-limpar-roteiro]');
    if (limpar) {
      limpar.addEventListener('click', function () { gravar([]); location.replace(enderecoDoRoteiro([])); });
    }
    var compartilhar = pagina.querySelector('[data-compartilhar-roteiro]');
    if (compartilhar) {
      compartilhar.addEventListener('click', function () {
        var url = location.origin + enderecoDoRoteiro(doEndereco).replace(/[?&]previa=[^&]+/, '');
        if (navigator.share) { navigator.share({ title: document.title, url: url }).catch(function () {}); return; }
        if (navigator.clipboard) {
          navigator.clipboard.writeText(url).then(function () { compartilhar.lastChild.textContent = 'Link copiado'; });
        }
      });
    }
  }
  pintar();

  // ---------------------------------------------------------------- fotos
  var galeria = document.querySelector('[data-galeria]');
  if (galeria) {
    var fotos = Array.prototype.slice.call(galeria.querySelectorAll('[data-foto]'));
    var atual = 0;
    var visor = null;
    function mostrar(i) {
      atual = (i + fotos.length) % fotos.length;
      visor.querySelector('img').src = fotos[atual].href;
      visor.querySelector('img').alt = fotos[atual].querySelector('img').alt;
    }
    function fechar() { if (visor) { visor.remove(); visor = null; } }
    fotos.forEach(function (a, i) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        visor = document.createElement('div');
        visor.className = 'visor';
        visor.setAttribute('role', 'dialog');
        visor.innerHTML = '<img alt=""><button type="button" class="fechar" aria-label="Fechar">×</button>'
          + (fotos.length > 1 ? '<button type="button" class="antes" aria-label="Anterior">‹</button><button type="button" class="depois" aria-label="Próxima">›</button>' : '');
        document.body.appendChild(visor);
        mostrar(i);
        visor.addEventListener('click', function (e) {
          if (e.target.classList.contains('antes')) { mostrar(atual - 1); }
          else if (e.target.classList.contains('depois')) { mostrar(atual + 1); }
          else if (e.target.classList.contains('fechar') || e.target === visor) { fechar(); }
        });
      });
    });
    document.addEventListener('keydown', function (e) {
      if (!visor) { return; }
      if (e.key === 'Escape') { fechar(); } else if (e.key === 'ArrowLeft') { mostrar(atual - 1); } else if (e.key === 'ArrowRight') { mostrar(atual + 1); }
    });
  }

  // ---------------------------------------------------------------- balões
  // O balão da casa, no lugar do balão nativo do navegador (title): título,
  // texto completo, dentro da tela. Passar o mouse ou focar mostra; no
  // toque, os selos (que não são botão) abrem e fecham o balão.
  document.querySelectorAll('[title]:not(iframe)').forEach(function (el) {
    if (!el.hasAttribute('data-balao')) { el.setAttribute('data-balao', el.getAttribute('title')); }
    el.removeAttribute('title');
  });
  var balao = document.createElement('div');
  balao.className = 'balao';
  balao.setAttribute('role', 'tooltip');
  balao.hidden = true;
  document.body.appendChild(balao);
  var alvo = null;
  function textoDoBalao(el) {
    var texto = el.getAttribute('data-balao') || '';
    if (el.hasAttribute('data-balao-roteiro')) {
      var n = ler().length;
      texto = (n ? (n === 1 ? '1 lugar guardado. ' : n + ' lugares guardados. ') : 'Nenhum lugar guardado ainda. ') + texto;
    }
    return texto;
  }
  function abrirBalao(el) {
    alvo = el;
    balao.innerHTML = '';
    var t = el.getAttribute('data-balao-titulo');
    if (t) { var b = document.createElement('b'); b.textContent = t; balao.appendChild(b); }
    var p = document.createElement('span'); p.textContent = textoDoBalao(el); balao.appendChild(p);
    balao.hidden = false;
    var r = el.getBoundingClientRect();
    var larg = balao.offsetWidth, alt = balao.offsetHeight;
    var esq = Math.max(8, Math.min(r.left + r.width / 2 - larg / 2, window.innerWidth - larg - 8));
    var topo = r.top - alt - 10;
    balao.classList.toggle('abaixo', topo < 8);
    if (topo < 8) { topo = r.bottom + 10; }
    balao.style.left = (esq + window.scrollX) + 'px';
    balao.style.top = (topo + window.scrollY) + 'px';
  }
  function fecharBalao() { alvo = null; balao.hidden = true; }
  document.addEventListener('mouseover', function (ev) {
    var el = ev.target.closest && ev.target.closest('[data-balao]');
    if (el && el !== alvo) { abrirBalao(el); } else if (!el && alvo) { fecharBalao(); }
  });
  document.addEventListener('focusin', function (ev) { var el = ev.target.closest && ev.target.closest('[data-balao]'); if (el) { abrirBalao(el); } });
  document.addEventListener('focusout', fecharBalao);
  window.addEventListener('scroll', fecharBalao, { passive: true });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { fecharBalao(); } });
  document.addEventListener('touchstart', function (ev) {
    var selo = ev.target.closest && ev.target.closest('.selo[data-balao]');
    if (selo) { ev.preventDefault(); if (alvo === selo) { fecharBalao(); } else { abrirBalao(selo); } return; }
    if (alvo && !balao.contains(ev.target)) { fecharBalao(); }
  }, { passive: false });
})();

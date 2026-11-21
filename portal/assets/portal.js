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
    var doEndereco = (pagina.getAttribute('data-ids') || '').split('.').map(Number).filter(function (n) { return n > 0; });
    var guardados = ler();
    if (!doEndereco.length && guardados.length) {
      location.replace(enderecoDoRoteiro(guardados));
      return;
    }
    if (doEndereco.length && !guardados.length) { gravar(doEndereco); }
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
})();

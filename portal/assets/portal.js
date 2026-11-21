/*
 * Portal do destino, o guia de campo: menu em tela cheia no celular, filtros
 * que se aplicam ao mudar, Meu roteiro (guardado só no aparelho, sem conta e
 * sem cookie), perto de mim (o GPS é pedido só quando a pessoa toca; a
 * posição fica no aparelho por 30 minutos), o mapa do destino (tocar num
 * ponto abre o cartão), lista ou mapa, guardar um dia inteiro, o visor de
 * fotos e o balão da casa. Softsul Software.
 */
(function () {
  'use strict';
  var CHAVE = 'inventour-meu-roteiro:' + location.host;
  var POSICAO = 'inventour-posicao:' + location.host;

  function guardado(chave, padrao) { try { var v = JSON.parse(localStorage.getItem(chave) || 'null'); return v === null ? padrao : v; } catch (e) { return padrao; } }
  function guardar(chave, valor) { try { localStorage.setItem(chave, JSON.stringify(valor)); } catch (e) {} }

  // ---------------------------------------------------------------- menu
  var botaoMenu = document.querySelector('.abrir-menu-portal');
  var menu = document.getElementById('menu-portal');
  var topo = document.querySelector('.topo');
  if (botaoMenu && menu) {
    botaoMenu.addEventListener('click', function () {
      if (topo) { menu.style.setProperty('--topo', topo.getBoundingClientRect().bottom + 'px'); }
      var aberto = menu.classList.toggle('aberto');
      botaoMenu.setAttribute('aria-expanded', aberto ? 'true' : 'false');
      document.body.style.overflow = aberto ? 'hidden' : '';
    });
  }

  // ---------------------------------------------------------------- filtros
  document.querySelectorAll('form[data-envia-ao-mudar]').forEach(function (form) {
    form.setAttribute('data-com-js', '');
    form.querySelectorAll('select').forEach(function (s) { s.addEventListener('change', function () { form.submit(); }); });
  });

  // ---------------------------------------------------------------- Meu roteiro
  function ler() { var l = guardado(CHAVE, []); return Array.isArray(l) ? l.filter(function (n) { return n > 0; }) : []; }
  function gravar(l) { guardar(CHAVE, l.slice(0, 30)); pintar(); }
  function previa() { var m = location.search.match(/[?&]previa=([^&]+)/); return m ? 'previa=' + m[1] : ''; }
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
    document.querySelectorAll('[data-conta-roteiro]').forEach(function (c) { c.textContent = String(l.length); c.hidden = !l.length; });
    document.querySelectorAll('.meu-roteiro-link').forEach(function (a) { a.href = enderecoDoRoteiro(l); });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-roteiro]');
    if (b) {
      ev.preventDefault();
      var id = parseInt(b.getAttribute('data-roteiro'), 10);
      var l = ler();
      var i = l.indexOf(id);
      if (i === -1) { l.push(id); } else { l.splice(i, 1); }
      gravar(l);
      return;
    }
    var varios = ev.target.closest('[data-guardar-varios]');
    if (varios) {
      var lista = ler();
      varios.getAttribute('data-guardar-varios').split('.').map(Number).forEach(function (n) { if (n > 0 && lista.indexOf(n) === -1) { lista.push(n); } });
      gravar(lista);
      varios.lastChild.textContent = 'Dia guardado no meu roteiro';
    }
  });
  var pagina = document.querySelector('[data-meu-roteiro]');
  if (pagina) {
    var doEndereco = (pagina.getAttribute('data-ids') || '').split('.').map(Number).filter(function (n) { return n > 0; });
    var guardados = ler();
    var jaPediu = /[?&]i=/.test(location.search);
    if (!jaPediu && guardados.length) { location.replace(enderecoDoRoteiro(guardados)); return; }
    if (jaPediu && !guardados.length) { gravar(doEndereco); }
    if (jaPediu && guardados.length && !previa()) {
      var validos = guardados.filter(function (n) { return doEndereco.indexOf(n) !== -1; });
      if (validos.length !== guardados.length) { gravar(validos); }
    }
    var limpar = pagina.querySelector('[data-limpar-roteiro]');
    if (limpar) { limpar.addEventListener('click', function () { gravar([]); location.replace(enderecoDoRoteiro([])); }); }
    var compartilhar = pagina.querySelector('[data-compartilhar-roteiro]');
    if (compartilhar) {
      compartilhar.addEventListener('click', function () {
        var url = location.origin + enderecoDoRoteiro(doEndereco).replace(/[?&]previa=[^&]+/, '');
        if (navigator.share) { navigator.share({ title: document.title, url: url }).catch(function () {}); return; }
        if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function () { compartilhar.lastChild.textContent = 'Link copiado'; }); }
      });
    }
  }
  pintar();

  // ---------------------------------------------------------------- perto de mim
  function metros(m) { return m < 1000 ? Math.round(m / 10) * 10 + ' m' : (m / 1000).toFixed(1).replace('.', ',') + ' km'; }
  function distancia(a, b, c, d) {
    var r = 6371000, rad = Math.PI / 180;
    var x = Math.sin((c - a) * rad / 2), y = Math.sin((d - b) * rad / 2);
    return 2 * r * Math.asin(Math.min(1, Math.sqrt(x * x + Math.cos(a * rad) * Math.cos(c * rad) * y * y)));
  }
  function aplicarPosicao(p, ordenar) {
    document.querySelectorAll('[data-lat]').forEach(function (el) {
      var lat = parseFloat(el.getAttribute('data-lat')), lng = parseFloat(el.getAttribute('data-lng'));
      if (!isFinite(lat)) { return; }
      var d = distancia(p.lat, p.lng, lat, lng);
      el.setAttribute('data-m', String(Math.round(d)));
      var alvoDist = el.hasAttribute('data-dist') ? el : el.querySelector('[data-dist]');
      if (alvoDist) { alvoDist.textContent = 'a ' + metros(d) + ' de você'; }
    });
    if (!ordenar) { return; }
    document.querySelectorAll('[data-lista-lugares]').forEach(function (grade) {
      Array.prototype.slice.call(grade.children)
        .sort(function (a, b) { return (parseFloat(a.getAttribute('data-m')) || 1e12) - (parseFloat(b.getAttribute('data-m')) || 1e12); })
        .forEach(function (el) { grade.appendChild(el); });
    });
  }
  var posicao = guardado(POSICAO, null);
  if (posicao && Date.now() - posicao.t < 30 * 60000) { aplicarPosicao(posicao, false); }
  function pedirPosicao(botao) {
    if (!navigator.geolocation) { return; }
    if (botao) { botao.lastChild.textContent = 'Localizando...'; }
    navigator.geolocation.getCurrentPosition(function (g) {
      var p = { lat: g.coords.latitude, lng: g.coords.longitude, t: Date.now() };
      guardar(POSICAO, p);
      aplicarPosicao(p, true);
      if (botao) { botao.setAttribute('aria-pressed', 'true'); botao.lastChild.textContent = 'Perto de mim'; }
    }, function () {
      if (botao) { botao.lastChild.textContent = 'Sem permissão de localização'; }
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
  }
  document.querySelectorAll('[data-perto]').forEach(function (b) { b.addEventListener('click', function () { pedirPosicao(b); }); });
  if (/[?&]perto=1/.test(location.search)) { pedirPosicao(document.querySelector('[data-perto]')); }

  // ---------------------------------------------------------------- mapa
  document.querySelectorAll('[data-ver-mapa]').forEach(function (b) {
    b.addEventListener('click', function () {
      var m = document.querySelector('[data-mapa-lista]');
      if (!m) { return; }
      m.hidden = !m.hidden;
      b.setAttribute('aria-pressed', m.hidden ? 'false' : 'true');
      b.lastChild.textContent = m.hidden ? 'Ver no mapa' : 'Esconder o mapa';
    });
  });
  function abrirPonto(c) {
    var caixa = c.closest('[data-mapa]');
    caixa.querySelectorAll('.ponto.ativo').forEach(function (p) { p.classList.remove('ativo'); });
    c.classList.add('ativo');
    var cartao = caixa.querySelector('.mapa-cartao');
    if (!cartao) { cartao = document.createElement('a'); cartao.className = 'mapa-cartao'; caixa.appendChild(cartao); }
    cartao.href = c.getAttribute('data-url');
    cartao.innerHTML = '';
    if (c.getAttribute('data-foto')) { var img = document.createElement('img'); img.src = c.getAttribute('data-foto'); img.alt = ''; cartao.appendChild(img); }
    var s = document.createElement('span');
    var em = document.createElement('em'); em.textContent = c.getAttribute('data-tipo') || '';
    var b = document.createElement('b'); b.textContent = c.getAttribute('data-nome') || '';
    s.appendChild(em); s.appendChild(b); cartao.appendChild(s);
  }
  document.addEventListener('click', function (ev) { var c = ev.target.closest && ev.target.closest('.ponto'); if (c) { abrirPonto(c); } });
  document.addEventListener('keydown', function (ev) { var c = ev.target.closest && ev.target.closest('.ponto'); if (c && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); abrirPonto(c); } });

  // ---------------------------------------------------------------- fotos
  var galeria = document.querySelector('[data-galeria]');
  if (galeria) {
    var fotos = Array.prototype.slice.call(galeria.querySelectorAll('[data-foto]'));
    var atual = 0, visor = null;
    var mostrar = function (i) {
      atual = (i + fotos.length) % fotos.length;
      visor.querySelector('img').src = fotos[atual].href;
      visor.querySelector('img').alt = fotos[atual].querySelector('img').alt;
    };
    var fecharVisor = function () { if (visor) { visor.remove(); visor = null; } };
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
          else if (e.target.classList.contains('fechar') || e.target === visor) { fecharVisor(); }
        });
      });
    });
    document.addEventListener('keydown', function (e) {
      if (!visor) { return; }
      if (e.key === 'Escape') { fecharVisor(); } else if (e.key === 'ArrowLeft') { mostrar(atual - 1); } else if (e.key === 'ArrowRight') { mostrar(atual + 1); }
    });
  }

  // ---------------------------------------------------------------- balões
  // O balão da casa, no lugar do balão nativo do navegador (title).
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
    var cima = r.top - alt - 10;
    if (cima < 8) { cima = r.bottom + 10; }
    balao.style.left = (esq + window.scrollX) + 'px';
    balao.style.top = (cima + window.scrollY) + 'px';
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

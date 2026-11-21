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
  // Lista própria no lugar do select (nada do seletor do sistema, que no
  // Android abre a lista nativa): botão com o valor escolhido e uma folha de
  // opções da casa, com título e marcação. O select fica no formulário,
  // escondido, e recebe o valor e o evento change.
  function folhaDeOpcoes(select) {
    var caixa = select.closest('.campo-filtro');
    var rotulo = caixa ? (caixa.querySelector('.campo-rotulo') || {}).textContent || '' : '';
    var botao = document.createElement('button');
    botao.type = 'button';
    botao.className = 'seletor';
    botao.setAttribute('aria-haspopup', 'dialog');
    var texto = document.createElement('span');
    botao.appendChild(texto);
    botao.insertAdjacentHTML('beforeend', '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>');
    function pintar() { var o = select.options[select.selectedIndex]; texto.textContent = o ? o.text : ''; botao.setAttribute('aria-label', rotulo + ': ' + texto.textContent); }
    pintar();
    select.hidden = true;
    select.tabIndex = -1;
    select.parentNode.insertBefore(botao, select);
    botao.addEventListener('click', function () {
      var fundo = document.createElement('div');
      fundo.className = 'folha-fundo';
      var folha = document.createElement('div');
      folha.className = 'folha-opcoes';
      folha.setAttribute('role', 'dialog');
      folha.setAttribute('aria-modal', 'true');
      folha.setAttribute('aria-label', rotulo);
      var cab = document.createElement('div');
      cab.className = 'folha-cab';
      var tit = document.createElement('b');
      tit.textContent = rotulo;
      var fechar = document.createElement('button');
      fechar.type = 'button';
      fechar.className = 'folha-fechar';
      fechar.setAttribute('aria-label', 'Fechar');
      fechar.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
      cab.appendChild(tit);
      cab.appendChild(fechar);
      var lista = document.createElement('ul');
      lista.className = 'folha-lista';
      Array.prototype.forEach.call(select.options, function (o, i) {
        var li = document.createElement('li');
        var op = document.createElement('button');
        op.type = 'button';
        op.setAttribute('aria-pressed', i === select.selectedIndex ? 'true' : 'false');
        op.textContent = o.text;
        op.addEventListener('click', function () {
          var mudou = select.selectedIndex !== i;
          select.selectedIndex = i;
          pintar();
          sair();
          if (mudou) { select.dispatchEvent(new Event('change', { bubbles: true })); }
        });
        li.appendChild(op);
        lista.appendChild(li);
      });
      folha.appendChild(cab);
      folha.appendChild(lista);
      fundo.appendChild(folha);
      document.body.appendChild(fundo);
      document.body.style.overflow = 'hidden';
      function sair() { fundo.remove(); document.body.style.overflow = ''; document.removeEventListener('keydown', tecla); botao.focus(); }
      function tecla(ev) { if (ev.key === 'Escape') { sair(); } }
      document.addEventListener('keydown', tecla);
      fechar.addEventListener('click', sair);
      fundo.addEventListener('click', function (ev) { if (ev.target === fundo) { sair(); } });
      var marcado = lista.querySelector('[aria-pressed="true"]') || lista.querySelector('button');
      if (marcado) { marcado.focus(); marcado.scrollIntoView({ block: 'nearest' }); }
    });
  }
  document.querySelectorAll('select').forEach(folhaDeOpcoes);

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
  // Cada mapa: zoom pelos botões, por Ctrl + rolagem, duplo clique e pinça;
  // arrastar quando aproximado; filtro por pilar ou tipo; balão completo ao
  // parar o mouse num ponto (no toque, o cartão embaixo).
  function lugarDe(c) { try { return JSON.parse(c.getAttribute('data-lugar') || '{}'); } catch (e) { return {}; } }
  function el(tag, cls, texto) { var x = document.createElement(tag); if (cls) { x.className = cls; } if (texto) { x.textContent = texto; } return x; }
  function conteudoDoLugar(d, alvo, comFoto) {
    if (comFoto && d.f) { var img = el('img'); img.src = d.f; img.alt = ''; alvo.appendChild(img); }
    var s = el('span', 'mapa-txt');
    s.appendChild(el('em', '', [d.c, d.t].filter(Boolean).join(' · ')));
    s.appendChild(el('b', '', d.n || ''));
    if (d.a) { s.appendChild(el('i', 'mapa-estado' + (d.o ? ' aberto' : ''), d.a)); }
    if (d.b) { s.appendChild(el('small', '', d.b)); }
    if (d.x) { s.appendChild(el('small', '', d.x.join(' · '))); }
    if (d.s) { s.appendChild(el('small', 'mapa-selos', 'Acessível: ' + d.s.join(', '))); }
    if (d.p) { s.appendChild(el('small', 'mapa-selos', 'Bom para: ' + d.p.join(', '))); }
    alvo.appendChild(s);
  }
  var balaoMapa = el('div', 'balao-mapa');
  balaoMapa.setAttribute('role', 'tooltip');
  balaoMapa.hidden = true;
  document.body.appendChild(balaoMapa);
  function mostrarBalaoMapa(c) {
    balaoMapa.innerHTML = '';
    conteudoDoLugar(lugarDe(c), balaoMapa, true);
    balaoMapa.appendChild(el('span', 'mapa-ir', 'Clique para abrir a ficha'));
    balaoMapa.hidden = false;
    var r = c.getBoundingClientRect(), larg = balaoMapa.offsetWidth, alt = balaoMapa.offsetHeight;
    var esq = Math.min(Math.max(8, r.left + r.width / 2 - larg / 2), window.innerWidth - larg - 8);
    var cima = r.top - alt - 12;
    if (cima < 8) { cima = r.bottom + 12; }
    balaoMapa.style.left = (esq + window.scrollX) + 'px';
    balaoMapa.style.top = (cima + window.scrollY) + 'px';
  }
  function esconderBalaoMapa() { balaoMapa.hidden = true; }
  function abrirCartao(c) {
    var caixa = c.closest('[data-mapa]');
    caixa.querySelectorAll('.ponto.ativo').forEach(function (p) { p.classList.remove('ativo'); });
    c.classList.add('ativo');
    var cartao = caixa.querySelector('.mapa-cartao');
    if (!cartao) { cartao = el('a', 'mapa-cartao'); caixa.querySelector('.mapa-palco').appendChild(cartao); }
    cartao.href = c.getAttribute('data-url');
    cartao.innerHTML = '';
    conteudoDoLugar(lugarDe(c), cartao, true);
    cartao.appendChild(el('span', 'mapa-ir', 'Abrir ›'));
  }

  document.querySelectorAll('[data-mapa]').forEach(function (caixa) {
    var svg = caixa.querySelector('.mapa-svg');
    if (!svg) { return; }
    var palco = caixa.querySelector('.mapa-palco');
    var dica = caixa.querySelector('.mapa-dica');
    var vb0 = svg.getAttribute('viewBox').split(' ').map(Number);
    var vb = vb0.slice();
    var MAX = 14;
    function aplicar() {
      var k = vb[2] / vb0[2];
      svg.setAttribute('viewBox', vb.join(' '));
      svg.style.setProperty('--k', k);
      palco.classList.toggle('aproximado', k < 0.999);
    }
    function prender() {
      vb[2] = Math.min(vb0[2], Math.max(vb0[2] / MAX, vb[2]));
      vb[3] = vb[2] * vb0[3] / vb0[2];
      vb[0] = Math.min(vb0[0] + vb0[2] - vb[2], Math.max(vb0[0], vb[0]));
      vb[1] = Math.min(vb0[1] + vb0[3] - vb[3], Math.max(vb0[1], vb[1]));
    }
    function noSvg(x, y) {
      var m = svg.getScreenCTM().inverse();
      return [m.a * x + m.c * y + m.e, m.b * x + m.d * y + m.f];
    }
    function zoomEm(fator, px, py) {
      var r = svg.getBoundingClientRect();
      if (px === undefined) { px = r.left + r.width / 2; py = r.top + r.height / 2; }
      var p = noSvg(px, py);
      var w = vb[2] / fator;
      var fx = (p[0] - vb[0]) / vb[2], fy = (p[1] - vb[1]) / vb[3];
      vb[2] = w; vb[3] = w * vb0[3] / vb0[2];
      vb[0] = p[0] - fx * vb[2]; vb[1] = p[1] - fy * vb[3];
      prender(); aplicar(); esconderBalaoMapa();
    }
    caixa.querySelectorAll('[data-zoom]').forEach(function (b) {
      b.addEventListener('click', function () {
        var z = Number(b.getAttribute('data-zoom'));
        if (z === 0) { vb = vb0.slice(); aplicar(); } else { zoomEm(z > 0 ? 1.8 : 1 / 1.8); }
      });
    });
    var tempoDica = null;
    svg.addEventListener('wheel', function (ev) {
      if (!ev.ctrlKey && !ev.metaKey) {
        if (dica) { dica.hidden = false; clearTimeout(tempoDica); tempoDica = setTimeout(function () { dica.hidden = true; }, 1500); }
        return;
      }
      ev.preventDefault();
      zoomEm(Math.exp(-ev.deltaY * 0.0025), ev.clientX, ev.clientY);
    }, { passive: false });
    svg.addEventListener('dblclick', function (ev) { ev.preventDefault(); zoomEm(2, ev.clientX, ev.clientY); });

    // Arrastar e pinçar (só aproximado: no tamanho inteiro, o dedo rola a página).
    var dedos = {}, inicio = null, arrastou = false;
    function distancia() { var p = Object.keys(dedos).map(function (k) { return dedos[k]; }); return Math.hypot(p[0][0] - p[1][0], p[0][1] - p[1][1]); }
    svg.addEventListener('pointerdown', function (ev) {
      if (ev.pointerType === 'mouse' && ev.button !== 0) { return; }
      dedos[ev.pointerId] = [ev.clientX, ev.clientY];
      arrastou = false;
      var n = Object.keys(dedos).length;
      if (n === 2) { inicio = { d: distancia(), w: vb[2] }; } else if (n === 1) { inicio = { x: ev.clientX, y: ev.clientY, vb: vb.slice() }; }
    });
    svg.addEventListener('pointermove', function (ev) {
      if (!dedos[ev.pointerId] || !inicio) { return; }
      dedos[ev.pointerId] = [ev.clientX, ev.clientY];
      var n = Object.keys(dedos).length;
      if (n === 2 && inicio.d) {
        var p = Object.keys(dedos).map(function (k) { return dedos[k]; });
        var alvoW = inicio.w * inicio.d / Math.max(1, distancia());
        zoomEm(vb[2] / alvoW, (p[0][0] + p[1][0]) / 2, (p[0][1] + p[1][1]) / 2);
        arrastou = true;
      } else if (n === 1 && inicio.vb && vb[2] < vb0[2]) {
        var dx = ev.clientX - inicio.x, dy = ev.clientY - inicio.y;
        if (!arrastou && Math.hypot(dx, dy) < 5) { return; }
        if (!arrastou) { svg.setPointerCapture(ev.pointerId); }
        arrastou = true;
        var esc = svg.getScreenCTM().a;
        vb[0] = inicio.vb[0] - dx / esc;
        vb[1] = inicio.vb[1] - dy / esc;
        prender(); aplicar(); esconderBalaoMapa();
      }
    });
    function soltar(ev) {
      delete dedos[ev.pointerId];
      var n = Object.keys(dedos).length;
      if (n === 1) { var k = Object.keys(dedos)[0]; inicio = { x: dedos[k][0], y: dedos[k][1], vb: vb.slice() }; } else if (!n) { inicio = null; }
    }
    svg.addEventListener('pointerup', soltar);
    svg.addEventListener('pointercancel', soltar);

    // Ponto: mouse abre a ficha no clique e o balão ao parar; toque abre o cartão.
    var ultimoToque = false;
    svg.addEventListener('pointerdown', function (ev) { ultimoToque = ev.pointerType !== 'mouse'; }, true);
    svg.addEventListener('click', function (ev) {
      var c = ev.target.closest('.ponto');
      if (!c || arrastou) { return; }
      if (ultimoToque) { abrirCartao(c); } else { location.href = c.getAttribute('data-url'); }
    });
    svg.addEventListener('pointerover', function (ev) { var c = ev.target.closest('.ponto'); if (c && ev.pointerType === 'mouse' && !Object.keys(dedos).length) { mostrarBalaoMapa(c); } });
    svg.addEventListener('pointerout', function (ev) { if (ev.target.closest('.ponto')) { esconderBalaoMapa(); } });
    // Pelo teclado (Tab), o balão também; o foco que vem de um toque ou clique, não.
    var porPonteiro = false;
    svg.addEventListener('pointerdown', function () { porPonteiro = true; setTimeout(function () { porPonteiro = false; }, 400); }, true);
    svg.addEventListener('focusin', function (ev) { var c = ev.target.closest('.ponto'); if (c && !porPonteiro) { mostrarBalaoMapa(c); } });
    svg.addEventListener('focusout', esconderBalaoMapa);
    svg.addEventListener('keydown', function (ev) {
      var c = ev.target.closest('.ponto');
      if (c && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); location.href = c.getAttribute('data-url'); }
    });

    // Enquadra os pontos do grupo (o município inteiro quando é tudo).
    function enquadrar(g) {
      var xs = [], ys = [];
      if (g !== '') {
        caixa.querySelectorAll('.ponto:not(.oculto)').forEach(function (p) { xs.push(+p.getAttribute('cx')); ys.push(+p.getAttribute('cy')); });
      }
      if (!xs.length) { vb = vb0.slice(); aplicar(); return; }
      var x0 = Math.min.apply(null, xs), x1 = Math.max.apply(null, xs), y0 = Math.min.apply(null, ys), y1 = Math.max.apply(null, ys);
      var w = Math.max((x1 - x0) * 1.25, (y1 - y0) * 1.25 * vb0[2] / vb0[3], vb0[2] / 8);
      vb[2] = w; vb[3] = w * vb0[3] / vb0[2];
      vb[0] = (x0 + x1) / 2 - vb[2] / 2; vb[1] = (y0 + y1) / 2 - vb[3] / 2;
      prender(); aplicar();
    }

    // Filtro: um grupo por vez; tocar de novo volta a mostrar tudo.
    var botoes = caixa.querySelectorAll('[data-grupo-filtro]');
    botoes.forEach(function (b) {
      b.addEventListener('click', function () {
        var g = b.getAttribute('data-grupo-filtro');
        if (b.classList.contains('ativo') && g !== '') { g = ''; }
        botoes.forEach(function (o) { var on = o.getAttribute('data-grupo-filtro') === g; o.classList.toggle('ativo', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        caixa.querySelectorAll('.ponto').forEach(function (p) { p.classList.toggle('oculto', g !== '' && p.getAttribute('data-grupo') !== g); });
        var cartao = caixa.querySelector('.mapa-cartao'); if (cartao) { cartao.remove(); }
        esconderBalaoMapa();
        enquadrar(g);
      });
    });
    aplicar();
    // O mapa de uma lista abre já enquadrado nos lugares dela.
    if (caixa.hasAttribute('data-mapa-lista')) { enquadrar('*'); }
  });
  window.addEventListener('scroll', esconderBalaoMapa, { passive: true });

  // ---------------------------------------------------------------- fotos
  var galeria = document.querySelector('[data-galeria]');
  if (galeria) {
    var fotos = Array.prototype.slice.call(galeria.querySelectorAll('[data-foto]'));
    var atual = 0, visor = null;
    var mostrar = function (i) {
      atual = (i + fotos.length) % fotos.length;
      visor.querySelector('img').src = fotos[atual].href;
      visor.querySelector('img').alt = fotos[atual].querySelector('img').alt;
      var leg = visor.querySelector('.visor-legenda');
      leg.textContent = fotos[atual].getAttribute('data-legenda') || '';
      leg.hidden = !leg.textContent;
    };
    var fecharVisor = function () { if (visor) { visor.remove(); visor = null; } };
    fotos.forEach(function (a, i) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        visor = document.createElement('div');
        visor.className = 'visor';
        visor.setAttribute('role', 'dialog');
        visor.innerHTML = '<img alt=""><p class="visor-legenda" hidden></p><button type="button" class="fechar" aria-label="Fechar">×</button>'
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

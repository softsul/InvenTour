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
      // Dentro de um modal aberto (Fale conosco), a folha entra no próprio
      // modal: fora dele ficaria por baixo da camada do dialog.
      (select.closest('dialog[open]') || document.body).appendChild(fundo);
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
  // Cada mapa: o mapa base próprio do destino (OpenStreetMap recortado e
  // servido pelo portal, desenhado pelo MapLibre no estilo do caderno de
  // viagem), a divisa do município e os lugares na cor do pilar. Mouse: balão
  // ao parar num ponto, clique abre a ficha. Toque: o cartão embaixo. Filtro
  // por pilar ou tipo. A biblioteca só carrega quando o mapa aparece.
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
  function mostrarBalaoMapa(d, x, y) {
    balaoMapa.innerHTML = '';
    conteudoDoLugar(d, balaoMapa, true);
    balaoMapa.appendChild(el('span', 'mapa-ir', 'Clique para abrir a ficha'));
    balaoMapa.hidden = false;
    var larg = balaoMapa.offsetWidth, alt = balaoMapa.offsetHeight;
    var esq = Math.min(Math.max(8, x - larg / 2), window.innerWidth - larg - 8);
    var cima = y - alt - 16;
    if (cima < 8) { cima = y + 16; }
    balaoMapa.style.left = (esq + window.scrollX) + 'px';
    balaoMapa.style.top = (cima + window.scrollY) + 'px';
  }
  function esconderBalaoMapa() { balaoMapa.hidden = true; }
  window.addEventListener('scroll', esconderBalaoMapa, { passive: true });

  var COR = { 'onde-dormir': '#3A5A9C', 'onde-comer': '#C25A33', 'o-que-fazer': '#3E8159', 'servicos': '#8F7C58' };
  var P = { papel: '#F7F2E7', papel2: '#EFE6D4', cartao: '#FFFDF8', tinta: '#2B3542', grafite: '#55606D', apagado: '#8E8371', linha: '#E0D5C0', forte: '#C9BA9E',
            agua: '#CFE1E6', aguaTraco: '#9EBFCB', verde: '#DCE6C8', verde2: '#CBD9B2' };
  // O estilo do caderno: papel creme, ruas em traço de caneta, rios em
  // aquarela, praças em verde lavado, nomes de bairro em caixa alta espaçada.
  function estiloCaderno(base, divisa, pontos) {
    var fontes = { r: ['Noto Sans Regular'], m: ['Noto Sans Medium'], i: ['Noto Sans Italic'] };
    var camadas = [{ id: 'fundo', type: 'background', paint: { 'background-color': P.papel } }];
    var fontesDados = { divisa: { type: 'geojson', data: { type: 'Feature', properties: {}, geometry: { type: 'MultiLineString', coordinates: divisa } } },
                        pontos: { type: 'geojson', data: { type: 'FeatureCollection', features: pontos } } };
    if (base) {
      fontesDados.base = { type: 'vector', tiles: [location.origin + base + '/{z}/{x}/{y}.pbf'], minzoom: 0, maxzoom: 15,
        attribution: '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">© OpenStreetMap</a> · <a href="https://protomaps.com" target="_blank" rel="noopener">Protomaps</a>' };
      var via = function (tipos) { return ['in', ['get', 'kind'], ['literal', tipos]]; };
      camadas.push(
        { id: 'terra', type: 'fill', source: 'base', 'source-layer': 'earth', paint: { 'fill-color': P.papel } },
        { id: 'verde', type: 'fill', source: 'base', 'source-layer': 'landuse', filter: via(['park', 'wood', 'forest', 'nature_reserve', 'garden', 'grass', 'meadow', 'playground', 'pitch', 'golf_course', 'cemetery', 'farmland', 'orchard']),
          paint: { 'fill-color': ['match', ['get', 'kind'], ['wood', 'forest', 'nature_reserve'], P.verde2, P.verde], 'fill-opacity': ['interpolate', ['linear'], ['zoom'], 10, 0.5, 14, 0.9] } },
        { id: 'quadras', type: 'fill', source: 'base', 'source-layer': 'landuse', filter: via(['hospital', 'school', 'college', 'university', 'commercial', 'industrial', 'retail', 'military']),
          paint: { 'fill-color': P.papel2, 'fill-opacity': 0.8 } },
        { id: 'agua', type: 'fill', source: 'base', 'source-layer': 'water', filter: ['==', ['geometry-type'], 'Polygon'], paint: { 'fill-color': P.agua, 'fill-outline-color': P.aguaTraco } },
        { id: 'rios', type: 'line', source: 'base', 'source-layer': 'water', filter: ['==', ['geometry-type'], 'LineString'],
          paint: { 'line-color': P.aguaTraco, 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 0.6, 15, 2.2, 18, 4] } },
        { id: 'predios', type: 'fill', source: 'base', 'source-layer': 'buildings', minzoom: 15, filter: ['==', ['get', 'kind'], 'building'],
          paint: { 'fill-color': P.papel2, 'fill-outline-color': P.linha, 'fill-opacity': ['interpolate', ['linear'], ['zoom'], 15, 0, 16, 0.8] } },
        { id: 'trilhas', type: 'line', source: 'base', 'source-layer': 'roads', minzoom: 14, filter: via(['path']),
          paint: { 'line-color': P.apagado, 'line-width': 1, 'line-dasharray': [2, 2] } },
        { id: 'ruas', type: 'line', source: 'base', 'source-layer': 'roads', minzoom: 12, filter: via(['minor_road', 'other']), layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: { 'line-color': '#CDBDA0', 'line-width': ['interpolate', ['exponential', 1.5], ['zoom'], 12, 0.4, 15, 1.6, 18, 5] } },
        { id: 'avenidas', type: 'line', source: 'base', 'source-layer': 'roads', filter: via(['major_road']), layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: { 'line-color': '#9C8361', 'line-width': ['interpolate', ['exponential', 1.5], ['zoom'], 9, 0.5, 15, 2.6, 18, 7] } },
        { id: 'rodovias', type: 'line', source: 'base', 'source-layer': 'roads', filter: via(['highway']), layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: { 'line-color': '#B5643C', 'line-opacity': 0.75, 'line-width': ['interpolate', ['exponential', 1.5], ['zoom'], 8, 0.8, 15, 3.4, 18, 8] } }
      );
    }
    camadas.push({ id: 'divisa', type: 'line', source: 'divisa', paint: { 'line-color': P.tinta, 'line-opacity': 0.55, 'line-width': 1.4, 'line-dasharray': [3, 2] } });
    if (base) {
      camadas.push(
        { id: 'nomes-rios', type: 'symbol', source: 'base', 'source-layer': 'water', minzoom: 12, filter: ['all', ['has', 'name'], ['==', ['geometry-type'], 'LineString']],
          layout: { 'text-field': ['get', 'name'], 'text-font': fontes.i, 'text-size': 11, 'symbol-placement': 'line' },
          paint: { 'text-color': '#5F8C9B', 'text-halo-color': P.papel, 'text-halo-width': 1.4 } },
        { id: 'nomes-lagos', type: 'symbol', source: 'base', 'source-layer': 'water', minzoom: 13, filter: ['all', ['has', 'name'], ['==', ['geometry-type'], 'Point']],
          layout: { 'text-field': ['get', 'name'], 'text-font': fontes.i, 'text-size': 11, 'text-max-width': 8 },
          paint: { 'text-color': '#5F8C9B', 'text-halo-color': P.papel, 'text-halo-width': 1.4 } },
        { id: 'nomes-ruas', type: 'symbol', source: 'base', 'source-layer': 'roads', minzoom: 14, filter: ['all', ['has', 'name'], via(['minor_road', 'major_road', 'highway'])],
          layout: { 'text-field': ['get', 'name'], 'text-font': fontes.i, 'text-size': ['interpolate', ['linear'], ['zoom'], 14, 10, 17, 13], 'symbol-placement': 'line', 'text-max-angle': 30 },
          paint: { 'text-color': P.grafite, 'text-halo-color': P.cartao, 'text-halo-width': 1.6 } },
        { id: 'nomes-avenidas', type: 'symbol', source: 'base', 'source-layer': 'roads', minzoom: 12, maxzoom: 14, filter: ['all', ['has', 'name'], via(['major_road', 'highway'])],
          layout: { 'text-field': ['get', 'name'], 'text-font': fontes.i, 'text-size': 10.5, 'symbol-placement': 'line' },
          paint: { 'text-color': P.grafite, 'text-halo-color': P.cartao, 'text-halo-width': 1.6 } },
        { id: 'marcos', type: 'symbol', source: 'base', 'source-layer': 'pois', minzoom: 15, filter: ['all', ['has', 'name'], via(['park', 'place_of_worship', 'hospital', 'university', 'college', 'stadium', 'bus_station', 'townhall', 'museum', 'library', 'theatre', 'marketplace', 'attraction', 'viewpoint'])],
          layout: { 'text-field': ['get', 'name'], 'text-font': fontes.i, 'text-size': 10.5, 'text-max-width': 9, 'text-optional': true },
          paint: { 'text-color': P.apagado, 'text-halo-color': P.papel, 'text-halo-width': 1.4 } },
        { id: 'bairros', type: 'symbol', source: 'base', 'source-layer': 'places', minzoom: 12, filter: ['==', ['get', 'kind'], 'neighbourhood'],
          layout: { 'text-field': ['upcase', ['get', 'name']], 'text-font': fontes.m, 'text-size': ['interpolate', ['linear'], ['zoom'], 12, 9.5, 16, 12], 'text-letter-spacing': 0.18, 'text-max-width': 8 },
          paint: { 'text-color': P.apagado, 'text-halo-color': P.papel, 'text-halo-width': 1.6 } },
        { id: 'cidades', type: 'symbol', source: 'base', 'source-layer': 'places', maxzoom: 13, filter: ['in', ['get', 'kind'], ['literal', ['locality', 'region']]],
          layout: { 'text-field': ['get', 'name'], 'text-font': fontes.m, 'text-size': ['interpolate', ['linear'], ['zoom'], 8, 12, 12, 16] },
          paint: { 'text-color': P.tinta, 'text-halo-color': P.papel, 'text-halo-width': 2 } }
      );
    }
    camadas.push(
      { id: 'pontos-halo', type: 'circle', source: 'pontos', filter: ['==', ['get', 'destaque'], 1],
        paint: { 'circle-radius': 16, 'circle-color': ['get', 'cor'], 'circle-opacity': 0.18 } },
      { id: 'pontos', type: 'circle', source: 'pontos', layout: { 'circle-sort-key': ['get', 'destaque'] },
        paint: { 'circle-radius': ['interpolate', ['linear'], ['zoom'], 10, ['case', ['==', ['get', 'destaque'], 1], 7, 3.5], 14, ['case', ['==', ['get', 'destaque'], 1], 10, 6], 17, ['case', ['==', ['get', 'destaque'], 1], 12, 9]],
                 'circle-color': ['get', 'cor'], 'circle-stroke-color': P.cartao, 'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 10, 1, 15, 2] } },
      { id: 'pontos-nomes', type: 'symbol', source: 'pontos', minzoom: 15.5,
        layout: { 'text-field': ['get', 'nome'], 'text-font': fontes.m, 'text-size': 11.5, 'text-offset': [0, 1.1], 'text-anchor': 'top', 'text-max-width': 10, 'text-optional': true },
        paint: { 'text-color': P.tinta, 'text-halo-color': P.cartao, 'text-halo-width': 1.6 } }
    );
    return { version: 8, glyphs: location.origin + '/assets/maplibre/fontes/{fontstack}/{range}.pbf', sources: fontesDados, layers: camadas };
  }

  var carregando = null;
  function carregarMapLibre() {
    if (window.maplibregl) { return Promise.resolve(window.maplibregl); }
    if (carregando) { return carregando; }
    carregando = new Promise(function (ok, falha) {
      var css = document.createElement('link');
      css.rel = 'stylesheet'; css.href = '/assets/maplibre/maplibre-gl.css';
      document.head.appendChild(css);
      var js = document.createElement('script');
      js.src = '/assets/maplibre/maplibre-gl-csp.js';
      js.onload = function () { window.maplibregl.setWorkerUrl('/assets/maplibre/maplibre-gl-csp-worker.js'); ok(window.maplibregl); };
      js.onerror = falha;
      document.head.appendChild(js);
    });
    return carregando;
  }

  function montarMapa(caixa) {
    var palco = caixa.querySelector('.mapa-palco');
    if (!palco || palco.getAttribute('data-montado')) { return; }
    palco.setAttribute('data-montado', '1');
    var links = Array.prototype.slice.call(palco.querySelectorAll('.mapa-lista-pontos a'));
    var dados = links.map(function (a, k) {
      var d = {}; try { d = JSON.parse(a.getAttribute('data-lugar') || '{}'); } catch (e) {}
      return { type: 'Feature', id: k, geometry: { type: 'Point', coordinates: [+a.getAttribute('data-lng'), +a.getAttribute('data-lat')] },
        properties: { k: k, url: a.getAttribute('href'), grupo: a.getAttribute('data-grupo') || '', cor: COR[a.getAttribute('data-pilar')] || COR['o-que-fazer'],
                      nome: d.n || a.textContent, destaque: a.hasAttribute('data-destaque') ? 1 : 0 } };
    });
    var lugares = links.map(function (a) { try { return JSON.parse(a.getAttribute('data-lugar') || '{}'); } catch (e) { return {}; } });
    var divisa = []; try { divisa = JSON.parse(palco.getAttribute('data-divisa') || '[]'); } catch (e) {}
    var lim = (palco.getAttribute('data-limites') || '').split(',').map(Number);
    var limites = lim.length === 4 ? [[lim[0], lim[1]], [lim[2], lim[3]]] : null;
    var ini = (palco.getAttribute('data-inicio') || '').split(',').map(Number);
    var inicio = ini.length === 4 && ini.every(isFinite) ? [[ini[0], ini[1]], [ini[2], ini[3]]] : limites;
    var folga = [[lim[0] - 0.08, lim[1] - 0.08], [lim[2] + 0.08, lim[3] + 0.08]];
    var zoomInicial = +palco.getAttribute('data-zoom-inicial') || 0;
    var destaque = dados.filter(function (f) { return f.properties.destaque; })[0];

    carregarMapLibre().then(function (ml) {
      var opcoes = {
        container: palco.querySelector('.mapa-tela'),
        style: estiloCaderno(palco.getAttribute('data-mapa-base'), divisa, dados),
        fitBoundsOptions: { padding: 24 }, maxBounds: folga, minZoom: 9, maxZoom: 18.5,
        attributionControl: { compact: true }, cooperativeGestures: true, dragRotate: false, pitchWithRotate: false, touchPitch: false,
        locale: { 'CooperativeGesturesHandler.WindowsHelpText': 'Use Ctrl + rolagem para aproximar o mapa', 'CooperativeGesturesHandler.MacHelpText': 'Use ⌘ + rolagem para aproximar o mapa',
                  'CooperativeGesturesHandler.MobileHelpText': 'Use dois dedos para mover o mapa', 'AttributionControl.ToggleAttribution': 'Créditos do mapa' }
      };
      // Centro e zoom só quando existem (valor vazio vira NaN no MapLibre).
      if (destaque) { opcoes.center = destaque.geometry.coordinates; opcoes.zoom = zoomInicial || 16; } else if (inicio) { opcoes.bounds = inicio; opcoes.fitBoundsOptions = { padding: 32, maxZoom: 15 }; }
      var mapa = new ml.Map(opcoes);
      mapa.touchZoomRotate.disableRotation();
      // Ponto: mouse mostra o balão e o clique abre a ficha; toque abre o cartão.
      var porToque = false;
      palco.addEventListener('pointerdown', function (ev) { porToque = ev.pointerType !== 'mouse'; }, true);
      mapa.on('mousemove', 'pontos', function (ev) {
        if (porToque) { return; }
        mapa.getCanvas().style.cursor = 'pointer';
        var f = ev.features[0];
        var r = palco.getBoundingClientRect();
        mostrarBalaoMapa(lugares[f.properties.k], r.left + ev.point.x, r.top + ev.point.y);
      });
      mapa.on('mouseleave', 'pontos', function () { mapa.getCanvas().style.cursor = ''; esconderBalaoMapa(); });
      mapa.on('movestart', esconderBalaoMapa);
      mapa.on('click', 'pontos', function (ev) {
        var f = ev.features[0];
        if (!porToque) { location.href = f.properties.url; return; }
        var cartao = palco.querySelector('.mapa-cartao');
        if (!cartao) { cartao = el('a', 'mapa-cartao'); palco.appendChild(cartao); }
        cartao.href = f.properties.url;
        cartao.innerHTML = '';
        conteudoDoLugar(lugares[f.properties.k], cartao, true);
        cartao.appendChild(el('span', 'mapa-ir', 'Abrir ›'));
      });
      mapa.on('click', function (ev) {
        if (!mapa.queryRenderedFeatures(ev.point, { layers: ['pontos'] }).length) { var c = palco.querySelector('.mapa-cartao'); if (c) { c.remove(); } }
      });

      function enquadrar(g) {
        var fs = dados.filter(function (f) { return g === '' || f.properties.grupo === g; });
        if (g === 'tudo') { if (limites) { mapa.fitBounds(limites, { padding: 24 }); } return; }
        if (!fs.length || g === '') {
          if (destaque) { mapa.easeTo({ center: destaque.geometry.coordinates, zoom: zoomInicial }); } else if (inicio) { mapa.fitBounds(inicio, { padding: 32, maxZoom: 15 }); }
          return;
        }
        var b = new ml.LngLatBounds(fs[0].geometry.coordinates, fs[0].geometry.coordinates);
        fs.forEach(function (f) { b.extend(f.geometry.coordinates); });
        mapa.fitBounds(b, { padding: 48, maxZoom: 15.5 });
      }
      caixa.querySelectorAll('[data-zoom]').forEach(function (b) {
        b.addEventListener('click', function () {
          var z = Number(b.getAttribute('data-zoom'));
          if (z > 0) { mapa.zoomIn(); } else if (z < 0) { mapa.zoomOut(); } else { enquadrar('tudo'); }
        });
      });
      var botoes = caixa.querySelectorAll('[data-grupo-filtro]');
      botoes.forEach(function (b) {
        b.addEventListener('click', function () {
          var g = b.getAttribute('data-grupo-filtro');
          if (b.classList.contains('ativo') && g !== '') { g = ''; }
          botoes.forEach(function (o) { var on = o.getAttribute('data-grupo-filtro') === g; o.classList.toggle('ativo', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
          var filtro = g === '' ? null : ['==', ['get', 'grupo'], g];
          ['pontos', 'pontos-halo', 'pontos-nomes'].forEach(function (id) { if (mapa.getLayer(id)) { mapa.setFilter(id, id === 'pontos-halo' ? (filtro ? ['all', filtro, ['==', ['get', 'destaque'], 1]] : ['==', ['get', 'destaque'], 1]) : filtro); } });
          var c = palco.querySelector('.mapa-cartao'); if (c) { c.remove(); }
          esconderBalaoMapa();
          enquadrar(g);
        });
      });
      // O mapa escondido (Ver no mapa) precisa recalcular o tamanho ao aparecer.
      if ('ResizeObserver' in window) { new ResizeObserver(function () { mapa.resize(); }).observe(palco); }
    }).catch(function (erro) { palco.classList.add('sem-mapa'); if (window.console) { console.warn('Mapa não abriu:', erro && erro.message); } });
  }

  var mapas = document.querySelectorAll('[data-mapa]');
  if ('IntersectionObserver' in window) {
    var olho = new IntersectionObserver(function (vistos) {
      vistos.forEach(function (v) { if (v.isIntersecting) { olho.unobserve(v.target); montarMapa(v.target); } });
    }, { rootMargin: '300px' });
    mapas.forEach(function (c) { olho.observe(c); });
  } else {
    mapas.forEach(montarMapa);
  }

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

/*
 * Página de fora (Google Maps, site do lugar, WhatsApp, EventCity...) abre
 * sempre em nova aba, com rel="noopener" (regra do Luiz). Vale para os
 * links que já estão na página e para os que o JavaScript cria depois.
 * Softsul Software
 */
(function () {
  'use strict';
  function externo(a) {
    return a.href && /^https?:/i.test(a.href) && a.host !== location.host;
  }
  function marcar(raiz) {
    (raiz.querySelectorAll ? raiz.querySelectorAll('a[href]') : []).forEach(function (a) {
      if (externo(a) && a.target !== '_blank') { a.target = '_blank'; a.rel = 'noopener'; }
    });
  }
  marcar(document);
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a[href]');
    if (a && externo(a) && a.target !== '_blank') { a.target = '_blank'; a.rel = 'noopener'; }
  }, true);
})();

/*
 * Métricas sem rastreamento (portal/m.php), como no EventCity: um aviso por
 * página vista e, na ficha do lugar, a visualização e os cliques (WhatsApp,
 * ligar, rota, site ou Instagram, guardar no meu roteiro). sendBeacon não
 * segura a navegação e não leva cookie; nada aqui identifica quem visita.
 * O mesmo clique vai ao Google Analytics só se o visitante aceitou os
 * cookies (window.itAnalytics, de consentimento.js); os nomes estão em
 * Analytics::CLIQUES.
 */
(function () {
  'use strict';
  function contar(acao, id) { try { if (navigator.sendBeacon) { navigator.sendBeacon('/m/' + acao + '/' + (id || 0), ''); } } catch (e) {} }
  if (/[?&]previa=/.test(location.search)) { return; }
  contar('pagina', 0);
  var ficha = document.querySelector('[data-lugar]');
  var lugar = ficha ? ficha.getAttribute('data-lugar') : '';
  if (lugar) { contar('ver', lugar); }
  var NOMES = { whatsapp: 'clique_whatsapp', ligar: 'clique_ligar', rota: 'clique_rota', site: 'clique_site', guardar: 'guardar_roteiro' };
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[href], button[data-roteiro], button[data-guardar-varios]');
    if (!a) { return; }
    var acao = '';
    var href = a.getAttribute('href') || '';
    if (a.hasAttribute('data-roteiro') || a.hasAttribute('data-guardar-varios')) { acao = 'guardar'; }
    else if (/^https:\/\/wa\.me\//.test(href)) { acao = 'whatsapp'; }
    else if (/^tel:/.test(href)) { acao = 'ligar'; }
    else if (/google\.com\/maps/.test(href)) { acao = 'rota'; }
    else if (a.closest('.caixa-contato') && /^https?:/.test(href)) { acao = 'site'; }
    if (!acao) { return; }
    // Guardar pela lista conta para o lugar do botão; na ficha, para o lugar dela.
    var id = a.hasAttribute('data-roteiro') ? a.getAttribute('data-roteiro') : lugar;
    if (id && acao !== 'guardar' || a.hasAttribute('data-roteiro')) { contar(acao, id); }
    if (window.itAnalytics) {
      var m = location.pathname.match(/^\/(lugar|roteiro)\/(\d+)/);
      window.itAnalytics(NOMES[acao], m ? { pagina_tipo: m[1], pagina_id: m[2] } : {});
    }
  });
})();

/*
 * Sugestões da busca enquanto se digita (Luiz, 22/11/2026): a partir da
 * segunda letra, a lista do que o guia encontrou (lugares com foto e tipo,
 * e eventos da agenda), em /api/sugestoes. Setas escolhem, Enter abre, Esc
 * fecha; Enter sem escolher segue para a página de resultados. A lista é
 * do próprio portal, não a do navegador (autocomplete desligado).
 * Softsul Software
 */
(function () {
  'use strict';
  var campos = document.querySelectorAll('form[role="search"] input[name="q"]');
  if (!campos.length || !window.fetch) { return; }
  var n = 0;

  campos.forEach(function (campo) {
    var form = campo.form;
    var previa = form.querySelector('input[name="previa"]');
    var lista = document.createElement('div');
    var id = 'sugestoes-' + (++n);
    lista.className = 'sugestoes';
    lista.id = id;
    lista.setAttribute('role', 'listbox');
    lista.hidden = true;
    document.body.appendChild(lista);
    campo.setAttribute('autocomplete', 'off');
    campo.setAttribute('role', 'combobox');
    campo.setAttribute('aria-autocomplete', 'list');
    campo.setAttribute('aria-controls', id);
    campo.setAttribute('aria-expanded', 'false');

    var espera = null, pedido = 0, ativo = -1, ultimo = '';

    function posicionar() {
      var r = (form.querySelector('.entrada') && form.classList.contains('busca-menu') ? form.querySelector('.entrada') : form).getBoundingClientRect();
      var largura = Math.max(r.width, Math.min(360, window.innerWidth - 16));
      var esq = Math.min(r.left, window.innerWidth - largura - 8);
      lista.style.left = Math.max(8, esq) + 'px';
      lista.style.top = (r.bottom + 4) + 'px';
      lista.style.width = largura + 'px';
      lista.style.maxHeight = Math.max(160, window.innerHeight - r.bottom - 16) + 'px';
    }
    function fechar() {
      lista.hidden = true;
      campo.setAttribute('aria-expanded', 'false');
      campo.removeAttribute('aria-activedescendant');
      ativo = -1;
    }
    function opcoes() { return lista.querySelectorAll('[role="option"]'); }
    function marcar(i) {
      var o = opcoes();
      if (!o.length) { return; }
      ativo = (i + o.length) % o.length;
      o.forEach(function (el, k) { el.setAttribute('aria-selected', k === ativo ? 'true' : 'false'); });
      campo.setAttribute('aria-activedescendant', o[ativo].id);
      o[ativo].scrollIntoView({ block: 'nearest' });
    }
    function el(tag, cls, texto) {
      var x = document.createElement(tag);
      if (cls) { x.className = cls; }
      if (texto) { x.textContent = texto; }
      return x;
    }
    function opcao(url, externo) {
      var a = el('a', 'sugestao');
      a.href = url;
      a.id = id + '-' + opcoes().length;
      a.setAttribute('role', 'option');
      a.setAttribute('aria-selected', 'false');
      if (externo) { a.target = '_blank'; a.rel = 'noopener'; }
      return a;
    }
    function desenhar(d, q) {
      lista.textContent = '';
      ativo = -1;
      if (!d.lugares.length && !d.eventos.length) {
        lista.appendChild(el('p', 'sugestao-vazia', 'Nada encontrado para "' + q + '". Tente outra palavra, como hotel, pizza ou parque.'));
      }
      if (d.lugares.length) {
        lista.appendChild(el('span', 'sugestao-grupo', 'Lugares'));
        d.lugares.forEach(function (l) {
          var a = opcao(l.url, false);
          var foto = el('span', 'sugestao-foto');
          if (l.foto) { var img = el('img'); img.src = l.foto; img.alt = ''; img.loading = 'lazy'; foto.appendChild(img); }
          a.appendChild(foto);
          var txt = el('span', 'sugestao-txt');
          txt.appendChild(el('b', '', l.nome));
          txt.appendChild(el('small', '', l.tipo + (l.bairro ? ' · ' + l.bairro : '')));
          a.appendChild(txt);
          lista.appendChild(a);
        });
      }
      if (d.eventos.length) {
        lista.appendChild(el('span', 'sugestao-grupo', 'Na agenda'));
        d.eventos.forEach(function (ev) {
          var a = opcao(ev.url, true);
          var data = el('span', 'sugestao-data');
          ev.quando.split(' · ').forEach(function (parte) { data.appendChild(el('span', '', parte)); });
          a.appendChild(data);
          var txt = el('span', 'sugestao-txt');
          txt.appendChild(el('b', '', ev.nome));
          txt.appendChild(el('small', '', 'no EventCity, abre em outra aba'));
          a.appendChild(txt);
          lista.appendChild(a);
        });
      }
      if (d.total > d.lugares.length || d.eventos.length) {
        var params = new URLSearchParams({ q: q });
        if (previa) { params.set('previa', previa.value); }
        var todos = opcao('/busca?' + params.toString(), false);
        todos.classList.add('sugestao-todos');
        todos.textContent = d.total > d.lugares.length ? 'Ver os ' + d.total + ' resultados' : 'Ver todos os resultados';
        lista.appendChild(todos);
      }
      // Campo no pé da tela (a capa, ou o celular com o teclado aberto): a
      // página sobe até o campo ficar no alto, logo abaixo do cabeçalho.
      var caixa = form.getBoundingClientRect();
      if (caixa.bottom + 260 > window.innerHeight) {
        var topo = document.querySelector('.topo');
        var acima = 12;
        if (topo && /sticky|fixed/.test(getComputedStyle(topo).position)) { acima += Math.max(0, topo.getBoundingClientRect().bottom); }
        window.scrollBy(0, caixa.top - acima);
      }
      posicionar();
      lista.hidden = false;
      campo.setAttribute('aria-expanded', 'true');
    }
    function buscar() {
      var q = campo.value.trim();
      if (q.length < 2) { ultimo = ''; fechar(); return; }
      if (q === ultimo && !lista.hidden) { return; }
      ultimo = q;
      var este = ++pedido;
      var params = new URLSearchParams({ q: q });
      if (previa) { params.set('previa', previa.value); }
      fetch('/api/sugestoes?' + params.toString(), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { if (d && este === pedido && document.activeElement === campo) { desenhar(d, q); } })
        .catch(function () {});
    }

    campo.addEventListener('input', function () {
      clearTimeout(espera);
      espera = setTimeout(buscar, 160);
    });
    campo.addEventListener('focus', function () { if (campo.value.trim().length >= 2) { ultimo = ''; buscar(); } });
    campo.addEventListener('keydown', function (ev) {
      if (lista.hidden) { return; }
      if (ev.key === 'ArrowDown') { ev.preventDefault(); marcar(ativo + 1); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); marcar(ativo - 1); }
      else if (ev.key === 'Escape') { fechar(); }
      else if (ev.key === 'Enter' && ativo >= 0) { ev.preventDefault(); opcoes()[ativo].click(); }
    });
    campo.addEventListener('blur', function () { setTimeout(fechar, 180); });
    // Tocar na lista não pode tirar o foco do campo antes do clique valer.
    lista.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
    window.addEventListener('resize', function () { if (!lista.hidden) { posicionar(); } });
    window.addEventListener('scroll', function () { if (!lista.hidden) { posicionar(); } }, { passive: true });
  });
})();

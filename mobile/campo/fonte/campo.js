/*
 * InvenTour Campo: a casca do app em volta da coleta (assets/coleta.js).
 * Softsul Software
 *
 * Carregado antes do coleta.js. Cuida do que só existe no app:
 * - a conexão do aparelho por código (gerado no painel, Coleta de campo),
 *   que devolve o token guardado só aqui;
 * - câmera e GPS nativos (plugins do Capacitor): cada foto leva a
 *   coordenada do aparelho no momento em que foi tirada;
 * - o menu (quem está conectado, desconectar).
 * A tela vem dentro do app, então abre sem sinal; nada sobe sem o botão
 * Sincronizar.
 */
(function () {
  'use strict';
  var SERVIDOR = 'https://app.inventour.com.br';
  var GUARDA = 'inventour-campo';
  var $ = function (id) { return document.getElementById(id); };
  var plugins = (window.Capacitor && window.Capacitor.Plugins) || {};

  function lerConexao() {
    try { return JSON.parse(localStorage.getItem(GUARDA) || 'null'); } catch (e) { return null; }
  }
  function gravarConexao(c) {
    try { if (c) { localStorage.setItem(GUARDA, JSON.stringify(c)); } else { localStorage.removeItem(GUARDA); } } catch (e) {}
  }
  var conexao = lerConexao();

  // ------------------------------------------------------------ GPS e câmera
  function posicao() {
    var opcoes = { enableHighAccuracy: true, timeout: 20000, maximumAge: 15000 };
    var traduzir = function (p) { return { lat: p.coords.latitude, lng: p.coords.longitude, precisao: p.coords.accuracy }; };
    if (plugins.Geolocation) {
      return plugins.Geolocation.getCurrentPosition(opcoes).then(traduzir).catch(function (e) {
        var m = String(e && (e.message || e)).toLowerCase();
        throw new Error(m.indexOf('denied') !== -1 || m.indexOf('permission') !== -1 ? 'negado' : 'falhou');
      });
    }
    return new Promise(function (ok, falha) {
      if (!navigator.geolocation) { falha(new Error('sem-gps')); return; }
      navigator.geolocation.getCurrentPosition(function (p) { ok(traduzir(p)); },
        function (e) { falha(new Error(e.code === 1 ? 'negado' : 'falhou')); }, opcoes);
    });
  }

  function arquivoDe(webPath, formato, n) {
    return fetch(webPath).then(function (r) { return r.blob(); }).then(function (blob) {
      var tipo = blob.type && /^image\//.test(blob.type) ? blob.type : 'image/jpeg';
      var nome = 'campo-' + Date.now() + (n ? '-' + n : '') + '.' + (formato === 'png' ? 'png' : 'jpg');
      return new File([blob], nome, { type: tipo });
    });
  }
  function cancelou(e) {
    if (String(e && (e.message || e)).toLowerCase().indexOf('cancel') !== -1) { return null; }
    throw e;
  }

  // Foto da câmera (uma, com o GPS de agora) ou da galeria (várias de uma vez,
  // sem GPS: podem ser antigas). Devolve {arquivos: [File], gps} ou null.
  function foto(origem) {
    if (!plugins.Camera) { return Promise.reject(new Error('sem-camera')); }
    if (origem !== 'camera') {
      return plugins.Camera.pickImages({ quality: 85, width: 2400, correctOrientation: true, limit: 0 }).then(function (r) {
        var fotos = (r && r.photos) || [];
        return Promise.all(fotos.map(function (f, n) { return arquivoDe(f.webPath, f.format, n + 1); }))
          .then(function (arquivos) { return arquivos.length ? { arquivos: arquivos, gps: null } : null; });
      }).catch(cancelou);
    }
    var gps = posicao().catch(function () { return null; });
    return plugins.Camera.getPhoto({
      quality: 85, width: 2400, resultType: 'uri', saveToGallery: false, correctOrientation: true, source: 'CAMERA',
      promptLabelHeader: 'Foto', promptLabelCancel: 'Cancelar'
    }).then(function (f) {
      return arquivoDe(f.webPath, f.format, 0).then(function (arquivo) {
        return gps.then(function (g) { return { arquivos: [arquivo], gps: g }; });
      });
    }).catch(cancelou);
  }

  // ------------------------------------------------------------ conexão
  function mostrar(conectado) {
    $('campo-conectar').hidden = conectado;
    $('campo-coleta').hidden = !conectado;
    $('campo-menu').hidden = !conectado;
  }

  function nomeDoAparelho() {
    if (!plugins.Device) { return Promise.resolve(navigator.userAgent.slice(0, 80)); }
    return plugins.Device.getInfo().then(function (i) {
      return [i.manufacturer, i.model].filter(Boolean).join(' ') + (i.operatingSystem ? ' (' + i.operatingSystem + ' ' + (i.osVersion || '') + ')' : '');
    }).catch(function () { return 'Aparelho'; });
  }

  $('campo-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var codigo = $('campo-codigo').value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    var erro = $('campo-erro');
    erro.hidden = true;
    if (codigo.length !== 8) { erro.textContent = 'O código tem 8 letras e números.'; erro.hidden = false; return; }
    var botao = $('campo-entrar');
    botao.disabled = true;
    botao.textContent = 'Conectando...';
    nomeDoAparelho().then(function (nome) {
      return fetch(SERVIDOR + '/api/campo-conectar', {
        method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: codigo, aparelho: nome })
      });
    }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); }).then(function (d) {
      if (!d.ok) { throw new Error(d.motivo || 'codigo'); }
      gravarConexao({ token: d.token, chave: d.chave, usuario: d.usuario, destino: d.destino });
      // Recomeça com a coleta carregada para esta pessoa e este destino.
      location.reload();
    }).catch(function (e) {
      botao.disabled = false;
      botao.textContent = 'Conectar';
      erro.textContent = e && e.message === 'codigo' ? 'Código errado, vencido ou já usado. Gere outro no painel.'
        : 'Sem conexão com o servidor. Para conectar, o aparelho precisa de internet uma vez.';
      erro.hidden = false;
    });
  });
  $('campo-codigo').addEventListener('input', function () {
    var v = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 8);
    this.value = v.length > 4 ? v.slice(0, 4) + ' ' + v.slice(4) : v;
  });

  // ------------------------------------------------------------ menu
  $('campo-menu').addEventListener('click', function () { $('campo-sobre').showModal(); });
  $('campo-sobre').addEventListener('click', function (ev) {
    if (ev.target.closest('[data-fechar]')) { $('campo-sobre').close(); }
  });
  $('campo-desconectar').addEventListener('click', function () {
    var pendentes = parseInt(($('col-fila') && $('col-fila').textContent.match(/\d+/) || ['0'])[0], 10);
    var aviso = pendentes ? 'Ainda há gravações no aparelho que não subiram. Desconectando, elas ficam guardadas, mas só sobem se conectar de novo com a mesma pessoa e o mesmo destino. Desconectar assim mesmo?'
      : 'Desconectar este aparelho? Para voltar, gere um código novo no painel.';
    if (!confirm(aviso)) { return; }
    gravarConexao(null);
    location.reload();
  });

  // ------------------------------------------------------------ início
  if (!conexao) {
    mostrar(false);
    return;
  }
  mostrar(true);
  $('campo-quem').innerHTML = '';
  var b = document.createElement('b'); b.textContent = conexao.usuario || '';
  var s = document.createElement('span'); s.textContent = conexao.destino || '';
  $('campo-quem').appendChild(b); $('campo-quem').appendChild(s);
  $('campo-sobre-quem').textContent = (conexao.usuario || '') + ' em ' + (conexao.destino || '');

  window.INVENTOUR_CAMPO = {
    servidor: SERVIDOR,
    chave: conexao.chave,
    token: function () { return conexao.token; },
    posicao: posicao,
    foto: foto,
    mostrarQuem: function (usuario, destino) {
      if (usuario) { b.textContent = usuario; }
      if (destino) { s.textContent = destino; }
    },
    desconectado: function () { /* o aviso já está na tela; a pessoa decide quando reconectar */ }
  };
  var js = document.createElement('script');
  js.src = 'assets/coleta.js';
  document.body.appendChild(js);
})();

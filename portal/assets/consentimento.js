/*
 * Aviso de cookies e Google Analytics 4 do portal.
 * Softsul Software
 *
 * O script do Google só é carregado depois do "Aceitar". A escolha fica no
 * próprio navegador, por portal (cada destino pergunta a sua), e o botão
 * Cookies do rodapé reabre o aviso. Recusar depois de aceitar apaga os
 * cookies do Analytics deste portal. Sem sinais de anúncio e sem
 * personalização: o consentimento de anúncio fica sempre negado.
 */
(function () {
  'use strict';
  var aviso = document.getElementById('aviso-cookies');
  if (!aviso) { return; }
  var id = aviso.getAttribute('data-ga');
  var CHAVE = 'it-cookies';

  function ler() { try { return localStorage.getItem(CHAVE); } catch (e) { return null; } }
  function guardar(v) { try { localStorage.setItem(CHAVE, v); } catch (e) { /* aba anônima sem armazenamento: vale só nesta página */ } }

  var carregado = false;
  function carregar() {
    if (carregado || !/^G-[A-Z0-9]+$/.test(id)) { return; }
    carregado = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'granted' });
    window.gtag('js', new Date());
    var config = { cookie_domain: location.hostname, allow_google_signals: false, allow_ad_personalization_signals: false };
    // Quem usa o painel (cookie it_painel, gravado pelo app) é equipe: o
    // filtro de tráfego interno da propriedade tira essas visitas da conta.
    if (/(^|; )it_painel=1/.test(document.cookie)) { config.traffic_type = 'internal'; }
    window.gtag('config', id, config);
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
  }

  function apagarCookies() {
    document.cookie.split(';').forEach(function (c) {
      var nome = c.split('=')[0].trim();
      if (/^_ga/.test(nome)) {
        document.cookie = nome + '=; Max-Age=0; path=/; domain=' + location.hostname;
        document.cookie = nome + '=; Max-Age=0; path=/';
      }
    });
  }

  // Eventos do portal (portal.js): só saem com o Analytics carregado.
  window.itAnalytics = function (nome, parametros) {
    if (carregado) { window.gtag('event', nome, parametros || {}); }
  };

  function mostrar() { aviso.hidden = false; }
  function esconder() { aviso.hidden = true; }

  aviso.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-cookies]');
    if (!b) { return; }
    var escolha = b.getAttribute('data-cookies');
    guardar(escolha);
    esconder();
    if (escolha === 'sim') {
      carregar();
    } else if (carregado) {
      // Já carregado nesta página: desliga, apaga e recarrega para o script
      // sair. Sem desligar, ele regrava o cookie ao sair da página.
      window['ga-disable-' + id] = true;
      window.gtag('consent', 'update', { analytics_storage: 'denied' });
      apagarCookies();
      location.reload();
    } else {
      apagarCookies();
    }
  });

  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-abrir-cookies]')) { mostrar(); }
  });

  var escolha = ler();
  if (escolha === 'sim') {
    carregar();
  } else if (escolha === 'nao') {
    apagarCookies(); // sobra de quando aceitou
  } else {
    mostrar();
  }
})();

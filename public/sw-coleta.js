/*
 * Service worker da Coleta de campo (escopo /coleta).
 * Softsul Software
 *
 * Guarda a tela /coleta e os arquivos dela (CSS, JS, fontes) para abrir sem
 * sinal. A tela vem da rede quando há sinal (sempre a versão nova) e do
 * cache quando não há. As APIs nunca passam pelo cache: a fila de envios
 * mora no IndexedDB, quem cuida é o assets/coleta.js.
 */
'use strict';
var CACHE = 'inventour-coleta-v1';

self.addEventListener('install', function (ev) {
  ev.waitUntil(caches.open(CACHE).then(function (c) { return c.add('/coleta'); }).catch(function () {}));
  self.skipWaiting();
});

self.addEventListener('activate', function (ev) {
  ev.waitUntil(caches.keys().then(function (nomes) {
    return Promise.all(nomes.filter(function (n) { return n.indexOf('inventour-coleta-') === 0 && n !== CACHE; })
      .map(function (n) { return caches['delete'](n); }));
  }).then(function () { return self.clients.claim(); }));
});

// A tela manda a lista do que carregou (arquivos com ?v= da versão).
self.addEventListener('message', function (ev) {
  var lista = ev.data && ev.data.cachear;
  if (!Array.isArray(lista)) { return; }
  caches.open(CACHE).then(function (c) {
    lista.forEach(function (u) {
      try {
        var url = new URL(u, self.location.origin);
        if (url.origin !== self.location.origin) { return; }
        c.match(url.href).then(function (ja) { if (!ja) { c.add(url.href).catch(function () {}); } });
      } catch (e) {}
    });
  });
});

self.addEventListener('fetch', function (ev) {
  var req = ev.request;
  if (req.method !== 'GET') { return; }
  var url = new URL(req.url);
  if (url.origin !== self.location.origin || url.pathname.indexOf('/api/') === 0) { return; }

  if (req.mode === 'navigate' && url.pathname === '/coleta') {
    ev.respondWith(fetch(req).then(function (r) {
      // Só guarda a tela de verdade (não o login de uma sessão vencida).
      if (r.ok && !r.redirected) {
        var copia = r.clone();
        caches.open(CACHE).then(function (c) { c.put('/coleta', copia); });
      }
      return r;
    }).catch(function () {
      return caches.match('/coleta').then(function (r) {
        return r || new Response('<!doctype html><meta charset="utf-8"><title>Sem sinal</title><p>Sem sinal e sem a tela guardada. Abra a Coleta de campo uma vez com internet.</p>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
      });
    }));
    return;
  }

  if (url.pathname.indexOf('/assets/') === 0) {
    ev.respondWith(caches.match(req).then(function (ja) {
      return ja || fetch(req).then(function (r) {
        if (r.ok) { var copia = r.clone(); caches.open(CACHE).then(function (c) { c.put(req, copia); }); }
        return r;
      });
    }));
  }
});

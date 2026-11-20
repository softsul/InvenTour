/*
 * Aba Aplicativo da Coleta de campo: atualiza sozinha.
 * Softsul Software
 *
 * Enquanto há um código esperando, pergunta a cada 3 s; quando o celular o
 * usa, o código some, o passo diz qual aparelho conectou e a tabela ganha a
 * linha nova, sem recarregar. Fora isso, a cada 15 s (último uso e envios),
 * só com a aba aberta e a janela à vista.
 */
(function () {
  'use strict';
  var cartao = document.getElementById('app-aparelhos');
  var passo = document.getElementById('app-passo-codigo');
  var conta = document.getElementById('app-conta');
  if (!cartao || !passo || !window.fetch) { return; }
  var painel = cartao.closest('.painel-aba');
  var esperando = cartao.getAttribute('data-codigo') === 'pendente';
  var assinatura = null;
  var relogio = null;

  function agendar() {
    clearTimeout(relogio);
    relogio = setTimeout(olhar, esperando ? 3000 : 15000);
  }

  function olhar() {
    if (document.hidden || (painel && painel.hidden)) { agendar(); return; }
    fetch('/api/campo-aparelhos', { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.ok && !r.redirected ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.ok) { return; }
        if (assinatura !== null && d.assinatura !== assinatura) {
          cartao.innerHTML = d.html;
        }
        assinatura = d.assinatura;
        if (conta) { conta.textContent = String(d.total); }
        if (esperando && d.codigo && d.codigo.html) {
          // O código foi usado (ou venceu): sai da tela.
          passo.innerHTML = d.codigo.html;
          cartao.innerHTML = d.html;
          esperando = false;
        }
      })
      .catch(function () {})
      .then(agendar);
  }

  document.addEventListener('visibilitychange', function () { if (!document.hidden) { olhar(); } });
  olhar();
})();

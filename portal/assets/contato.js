/*
 * Fale conosco do portal: abre o modal (Contato::modal), carrega o
 * reCAPTCHA só nessa hora e envia sem sair da página.
 * Softsul Software
 *
 * Nada de mailto: em nenhuma tela; todo contato passa por aqui.
 */
(function () {
  'use strict';
  var modal = document.getElementById('modal-contato');
  if (!modal) { return; }
  var form = modal.querySelector('form');
  var caixa = modal.querySelector('.contato-captcha');
  var aviso = modal.querySelector('.contato-aviso');
  var enviar = modal.querySelector('.contato-enviar');
  var widget = null;
  var pedido = false;

  function mostrarAviso(texto, bom) {
    aviso.textContent = texto;
    aviso.hidden = !texto;
    aviso.classList.toggle('bom', !!bom);
  }

  window.itRecaptchaPronto = function () {
    if (widget !== null || !window.grecaptcha) { return; }
    var escuro = document.body.classList.contains('portal-palco') && !document.documentElement.classList.contains('alto-contraste');
    widget = window.grecaptcha.render(caixa, { sitekey: caixa.getAttribute('data-chave'), theme: escuro ? 'dark' : 'light' });
  };

  function carregarRecaptcha() {
    if (!caixa.getAttribute('data-chave')) {
      mostrarAviso('O formulário de contato ainda está sendo ativado. Tente de novo em breve.');
      enviar.disabled = true;
      return;
    }
    if (pedido) { return; }
    pedido = true;
    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?onload=itRecaptchaPronto&render=explicit&hl=pt-BR';
    s.async = true;
    s.onerror = function () { pedido = false; mostrarAviso('Não conseguimos carregar a verificação "Não sou um robô". Confira a conexão e tente de novo.'); };
    document.head.appendChild(s);
  }

  function abrir(assunto) {
    form.reset();
    mostrarAviso('');
    form.querySelector('[name=pagina]').value = location.href;
    if (assunto) {
      var sel = form.querySelector('[name=assunto]');
      if (sel.querySelector('option[value="' + assunto + '"]')) { sel.value = assunto; }
    }
    if (widget !== null && window.grecaptcha) { window.grecaptcha.reset(widget); }
    modal.showModal();
    form.querySelector('[name=nome]').focus();
    carregarRecaptcha();
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-abrir-contato]');
    if (b) { ev.preventDefault(); abrir(b.getAttribute('data-assunto')); return; }
    if (ev.target.closest && ev.target.closest('[data-fechar-contato]')) { modal.close(); }
  });
  modal.addEventListener('click', function (ev) { if (ev.target === modal) { modal.close(); } });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var faltando = Array.prototype.find.call(form.elements, function (el) { return el.willValidate && !el.checkValidity(); });
    if (faltando) {
      mostrarAviso(faltando.name === 'email' ? 'Digite um e-mail válido, para podermos responder.' : 'Preencha ' + (form.querySelector('label[for="' + faltando.id + '"]') || {}).textContent.toLowerCase() + '.');
      faltando.focus();
      return;
    }
    if (widget !== null && !window.grecaptcha.getResponse(widget)) {
      mostrarAviso('Marque a caixa "Não sou um robô".');
      return;
    }
    enviar.disabled = true;
    mostrarAviso('Enviando...', true);
    fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form) })
      .then(function (r) { return r.json().catch(function () { return { erro: r.status === 429 ? 'Muitas mensagens em pouco tempo. Tente de novo mais tarde.' : 'Não conseguimos enviar agora.' }; }); })
      .then(function (r) {
        enviar.disabled = false;
        if (r && r.ok) {
          form.reset();
          mostrarAviso('Mensagem enviada. Obrigado! Respondemos no e-mail que você informou.', true);
        } else {
          mostrarAviso((r && r.erro) || 'Não conseguimos enviar agora. Tente de novo em alguns minutos.');
        }
        if (widget !== null) { window.grecaptcha.reset(widget); }
      })
      .catch(function () {
        enviar.disabled = false;
        mostrarAviso('Sem conexão. Confira a internet e tente de novo.');
      });
  });
})();

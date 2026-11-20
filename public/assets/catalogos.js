/*
 * Telas de catálogos: a cor do segmento em hexa e no seletor, e o cálculo
 * de contraste refeito quando ela muda.
 * Softsul Software
 *
 * O campo de texto com o hexa é o que vai no formulário; o seletor de cor
 * ao lado (data-cor-par) acompanha o texto nos dois sentidos: arrastar no
 * seletor escreve o hexa, e digitar um hexa válido move o seletor.
 *
 * O contraste é calculado no servidor (Catalogos::contraste), e não aqui,
 * para existir uma conta só. Ao fechar o seletor, ou ao sair do texto com
 * um hexa válido, este script aperta o botão "Conferir o contraste", que
 * devolve a ficha com a cor nova e o cálculo dela, sem salvar. Sem
 * JavaScript, o texto e o botão continuam lá.
 */
(function () {
  'use strict';
  var HEXA = /^#?[0-9A-Fa-f]{6}$/;
  function normal(v) { v = v.trim().toUpperCase(); return v.charAt(0) === '#' ? v : '#' + v; }

  document.querySelectorAll('[data-cor-par]').forEach(function (seletor) {
    var texto = document.getElementById(seletor.getAttribute('data-cor-par'));
    if (!texto) { return; }
    seletor.addEventListener('input', function () { texto.value = seletor.value.toUpperCase(); });
    seletor.addEventListener('change', function () {
      texto.value = seletor.value.toUpperCase();
      texto.dispatchEvent(new Event('change', { bubbles: true }));
    });
    texto.addEventListener('input', function () { if (HEXA.test(texto.value.trim())) { seletor.value = normal(texto.value).toLowerCase(); } });
  });

  document.querySelectorAll('[data-conferir-contraste]').forEach(function (campo) {
    var botao = campo.form ? campo.form.querySelector('[data-botao-contraste]') : null;
    if (!botao) { return; }
    // change, e não input: input dispara a cada arrasto no seletor ou a
    // cada tecla, e cada disparo seria uma ida ao servidor.
    campo.addEventListener('change', function () {
      if (!HEXA.test(campo.value.trim())) { return; }
      campo.value = normal(campo.value);
      botao.click();
    });
  });
})();

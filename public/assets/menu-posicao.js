/**
 * Menu lateral: guarda a rolagem da lista de módulos e devolve na página
 * seguinte, antes de desenhar (carregado sem defer, logo depois da lista),
 * para quem rolou até Relatórios não voltar ao alto a cada clique.
 * Softsul Software
 */
(function () {
  'use strict';
  var lista = document.getElementById('itens-rail');
  if (!lista) { return; }
  var CHAVE = 'inventour_menu_rolagem';
  var salvo = null;
  try { salvo = sessionStorage.getItem(CHAVE); } catch (e) {}
  if (salvo !== null) {
    lista.scrollTop = parseInt(salvo, 10) || 0;
  } else {
    // Primeira página da aba: o item aberto à vista, se ficou abaixo.
    var ativo = lista.querySelector('.item.ativo');
    if (ativo) {
      var a = ativo.getBoundingClientRect(), l = lista.getBoundingClientRect();
      if (a.bottom > l.bottom) { lista.scrollTop = a.top - l.top - l.height / 2; }
    }
  }
  function guardar() {
    try { sessionStorage.setItem(CHAVE, String(lista.scrollTop)); } catch (e) {}
  }
  lista.addEventListener('click', guardar);
  window.addEventListener('pagehide', guardar);
})();

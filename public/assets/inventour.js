/*
 * Comportamentos compartilhados das telas do painel do InvenTour.
 * Softsul Software
 *
 * Trazido do centurion.js (que veio do erp.js do Orbit), bloco a bloco, só
 * com o que vale para qualquer painel da casa (docs/ui/design-softsul.md).
 * Ficou de fora o que é domínio do Centurion: app do aluno, aulas, check-in,
 * WhatsApp, cobrança, contratos. As funções globais mantêm o nome da casa
 * (erpPerguntar, erpAvisar, erpLigarTelefones...) para o código das telas
 * ser o mesmo nos produtos.
 *
 * Tudo aqui e nada em script dentro da página: a política de segurança do
 * painel só aceita JavaScript vindo de /assets. Armadilha conhecida: com os
 * blocos num arquivo só, um erro mata os seguintes; cada bloco confere os
 * elementos antes de usar.
 */

/*
 * Modal de pergunta, usado por tudo que precisa confirmar alguma coisa.
 * Nunca alert() nem confirm(): a caixa nativa ignora a identidade do sistema
 * e o usuário consegue desligá-la, o que faria a pergunta não acontecer.
 *
 * Devolve uma Promise que resolve para true ou false, então quem chama
 * escreve o fluxo em linha reta em vez de espalhar o resto do trabalho num
 * punhado de callbacks.
 */
/*
 * Iframe de modal novo em folha. Tudo o que um iframe carrega entra no
 * histórico da aba: fechar o modal esvaziando o iframe (about:blank) deixava
 * para trás uma entrada por tela aberta, e o voltar do celular refazia o
 * caminho dos modais em vez de voltar entre as telas do menu. Trocar o iframe
 * por um novo descarta o histórico dele inteiro.
 * Softsul Software
 */
window.inventourIframeNovo = function (velho, historicoAntes) {
  var novo = velho.cloneNode(false);
  novo.removeAttribute('src');
  velho.parentNode.replaceChild(novo, velho);
  // O que o iframe empilhou no histórico enquanto o modal esteve aberto é
  // desfeito de uma vez: o ponteiro volta para onde estava antes do modal.
  var empilhou = typeof historicoAntes === 'number' ? history.length - historicoAntes : 0;
  if (empilhou > 0) { history.go(-empilhou); }
  return novo;
};

window.erpPerguntar = function (opcoes) {
  return new Promise(function (resolver) {
    var caixa = document.createElement('dialog');
    caixa.className = 'confirmador' + (opcoes.largo ? ' largo' : '');
    caixa.innerHTML =
      '<h2></h2>' +
      '<div class="corpo"></div>' +
      '<div class="acoes-modal">' +
        '<button type="button" class="botao claro" data-r="nao"></button>' +
        '<button type="button" class="botao" data-r="sim"></button>' +
      '</div>';
    caixa.querySelector('h2').textContent = opcoes.titulo || 'Confirmar';
    var corpo = caixa.querySelector('.corpo');
    if (opcoes.html) { corpo.innerHTML = opcoes.html; }
    else { corpo.textContent = opcoes.texto || ''; }
    var nao = caixa.querySelector('[data-r=nao]');
    var sim = caixa.querySelector('[data-r=sim]');
    nao.textContent = opcoes.cancelar || 'Cancelar';
    sim.textContent = opcoes.confirmar || 'Confirmar';
    if (opcoes.perigo) { sim.className = 'botao perigo'; }
    document.body.appendChild(caixa);

    function fechar(resposta) {
      caixa.close();
      caixa.remove();
      resolver(resposta);
    }
    sim.addEventListener('click', function () { fechar(true); });
    nao.addEventListener('click', function () { fechar(false); });
    caixa.addEventListener('cancel', function (ev) { ev.preventDefault(); fechar(false); });
    caixa.showModal();
    nao.focus();
  });
};

(function () {
  'use strict';

  /* ---------------------------------------------- seletor de empresa */
  // Mesmo comportamento do menu do usuário, e por isso a mesma função: dois
  // menus suspensos com regras diferentes de fechar confundem quem usa.
  function suspenso(idBotao, idMenu) {
    var botao = document.getElementById(idBotao);
    var menu = document.getElementById(idMenu);
    if (!botao || !menu) { return; }
    function abrir(sim) {
      menu.hidden = !sim;
      botao.setAttribute('aria-expanded', sim ? 'true' : 'false');
      botao.classList.toggle('aberto', sim);
    }
    botao.addEventListener('click', function (ev) {
      ev.stopPropagation();
      abrir(menu.hidden);
    });
    document.addEventListener('click', function (ev) {
      if (!menu.hidden && !menu.contains(ev.target)) { abrir(false); }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { abrir(false); }
    });
  }
  suspenso('btn-empresa', 'menu-empresas');

  /* ------------------------------------------------ menu do usuário */
  var botaoPerfil = document.getElementById('btn-perfil');
  var menuPerfil = document.getElementById('menu-perfil');
  if (botaoPerfil && menuPerfil) {
    var abrirMenu = function (sim) {
      menuPerfil.hidden = !sim;
      botaoPerfil.setAttribute('aria-expanded', sim ? 'true' : 'false');
      botaoPerfil.classList.toggle('aberto', sim);
    };
    botaoPerfil.addEventListener('click', function (ev) {
      ev.stopPropagation();
      abrirMenu(menuPerfil.hidden);
    });
    document.addEventListener('click', function (ev) {
      if (!menuPerfil.hidden && !menuPerfil.contains(ev.target)) { abrirMenu(false); }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !menuPerfil.hidden) { abrirMenu(false); botaoPerfil.focus(); }
    });
    menuPerfil.addEventListener('focusout', function (ev) {
      if (!menuPerfil.contains(ev.relatedTarget) && ev.relatedTarget !== botaoPerfil) { abrirMenu(false); }
    });
  }

  /* ------------------------------------- data relevante do calendário */
  // Data relevante (início das aulas) muda a cada ano: na data nova, ao
  // escolher esse tipo, o "Repete" passa para "Só uma vez, numa data".
  document.querySelectorAll('select[data-relevante-uma-vez]').forEach(function (tipo) {
    tipo.addEventListener('change', function () {
      var regra = document.getElementById('regra');
      if (tipo.value !== 'relevante' || !regra || regra.value === 'unica') { return; }
      regra.value = 'unica';
      regra.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  /* ------------------------------------------------ mostrar a senha */
  document.querySelectorAll('.revelar').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var campo = document.getElementById(botao.dataset.alvo);
      if (!campo) { return; }
      var vendo = campo.type === 'text';
      campo.type = vendo ? 'password' : 'text';
      botao.setAttribute('aria-label', vendo ? 'Mostrar a senha' : 'Esconder a senha');
      campo.focus();
    });
  });

  /* ------------------------------------------------ zona de upload
   *
   * Arrastar e soltar é o caminho principal, mas a zona inteira também é um
   * botão: quem usa teclado ou leitor de tela não arrasta arquivo, e sem o
   * clique o upload ficaria inacessível para essas pessoas.
   *
   * O contador de arrastes existe porque dragleave dispara ao passar por
   * cima de qualquer filho da zona. Sem contar entradas e saídas, a moldura
   * pisca enquanto o cursor atravessa o texto de dentro.
   */
  // Também para as zonas que chegam depois, num pedaço de tela trazido por
  // fetch (a resposta da caixa de entrada): quem trouxe chama de novo.
  window.erpPrepararZonas = function (raiz) {
  (raiz || document).querySelectorAll('.zona-upload:not([data-pronta])').forEach(function (zona) {
    zona.setAttribute('data-pronta', '1');
    var campo = zona.querySelector('input[type=file]');
    var alvo = zona.querySelector('.zona-alvo');
    var lista = zona.querySelector('.zona-escolhido');
    var envio = zona.closest('form');
    var autoenviar = zona.dataset.autoenviar !== 'nao';
    var aceita = (campo.getAttribute('accept') || '').split(',').map(function (s) { return s.trim(); });
    var arrastes = 0;

    function tipoServe(arquivo) {
      if (!aceita.length || aceita[0] === '') { return true; }
      return aceita.some(function (a) {
        if (a.charAt(0) === '.') { return arquivo.name.toLowerCase().endsWith(a.toLowerCase()); }
        if (a.endsWith('/*')) { return arquivo.type.startsWith(a.slice(0, -1)); }
        return arquivo.type === a;
      });
    }

    function tamanhoLegivel(bytes) {
      if (bytes < 1024) { return bytes + ' B'; }
      if (bytes < 1048576) { return (bytes / 1024).toFixed(0) + ' KB'; }
      return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function mostrar(escolhidos, erro) {
      lista.hidden = false;
      if (erro) {
        lista.className = 'zona-escolhido erro';
        lista.textContent = erro;
        return;
      }
      lista.className = 'zona-escolhido';
      lista.textContent = escolhidos.map(function (a) {
        return a.name + ' · ' + tamanhoLegivel(a.size);
      }).join('  |  ');
    }

    function receber(arquivos) {
      if (!arquivos || !arquivos.length) { return; }
      // Vários de uma vez quando o campo aceita: um pedido de publicação vem
      // com o edital, os anexos dele e o ofício, e mandar um de cada vez é
      // repetir o mesmo gesto quatro vezes pelo mesmo pedido.
      var escolhidos = campo.multiple
        ? Array.prototype.slice.call(arquivos)
        : [arquivos[0]];
      var recusado = escolhidos.filter(function (a) { return !tipoServe(a); })[0];
      if (recusado) {
        mostrar(null, '"' + recusado.name + '" não serve aqui. Aceito: ' + aceita.join(', ') + '.');
        return;
      }
      // Imagem com tamanho padrão passa antes pelo enquadramento: a pessoa
      // escolhe o pedaço que fica, e o recorte vai junto, um por arquivo.
      if (zona.dataset.recorte && window.ecEnquadrar) {
        var padrao = zona.dataset.recorte.split('x').map(Number);
        window.ecEnquadrar(escolhidos, padrao[0], padrao[1], !!zona.dataset.recorteInteira).then(function (recortes) {
          if (!recortes) { campo.value = ''; return; }
          if (typeof recortes === 'string') { campo.value = ''; mostrar(null, recortes); return; }
          zona.querySelectorAll('input[data-recorte-campo]').forEach(function (i) { i.remove(); });
          recortes.forEach(function (r) {
            var oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = (zona.dataset.recorteNome || 'recortes') + '[]';
            oculto.value = r;
            oculto.setAttribute('data-recorte-campo', '');
            zona.appendChild(oculto);
          });
          concluir(escolhidos);
        });
        return;
      }
      concluir(escolhidos);
    }

    function concluir(escolhidos) {
      // DataTransfer é o único jeito de colocar um arquivo arrastado dentro
      // do input, que é o que faz o envio normal do formulário funcionar sem
      // JavaScript de upload próprio.
      var deposito = new DataTransfer();
      escolhidos.forEach(function (a) { deposito.items.add(a); });
      campo.files = deposito.files;
      mostrar(escolhidos);
      // Quem lê o arquivo no próprio navegador (o HTML da campanha) escuta
      // este aviso: arrastado, o arquivo não dispara o change do campo.
      campo.dispatchEvent(new CustomEvent('arquivos-escolhidos', { bubbles: true }));
      if (autoenviar && envio) {
        zona.classList.add('enviando');
        alvo.querySelector('.zona-texto').textContent = escolhidos.length > 1
          ? 'Enviando ' + escolhidos.length + ' arquivos...'
          : 'Enviando ' + escolhidos[0].name + '...';
        envio.submit();
      }
    }

    ['dragenter', 'dragover'].forEach(function (evento) {
      zona.addEventListener(evento, function (ev) {
        ev.preventDefault();
        if (evento === 'dragenter') { arrastes++; }
        zona.classList.add('sobre');
      });
    });
    zona.addEventListener('dragleave', function () {
      arrastes--;
      if (arrastes <= 0) { arrastes = 0; zona.classList.remove('sobre'); }
    });
    zona.addEventListener('drop', function (ev) {
      ev.preventDefault();
      arrastes = 0;
      zona.classList.remove('sobre');
      receber(ev.dataTransfer.files);
    });
    campo.addEventListener('change', function () { receber(campo.files); });
    alvo.addEventListener('click', function () { campo.click(); });
    alvo.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); campo.click(); }
    });
  });
  };
  window.erpPrepararZonas(document);

  /*
   * Enquadramento: para cada imagem, um modal com a foto inteira e, sobre
   * ela, a moldura na proporção do padrão (largura x altura). Arrastar leva
   * a moldura para a região que importa; os cantos, o zoom (barra, roda do
   * mouse ou pinça) e o teclado mudam o tamanho dela. Devolve, na ordem dos
   * arquivos, "x,y,largura,altura" em pixels da foto, ou "" quando a foto já
   * tem o tamanho exato. O recorte pode ser menor que o padrão (foto pequena,
   * ou zoom grande): o servidor amplia até AMPLIACAO vezes, e a moldura avisa
   * quando a foto vai perder nitidez. Foto que nem inteira chega a esse
   * mínimo devolve a mensagem de recusa, antes de subir. Cancelar devolve
   * null e nada sobe.
   */
  window.ecEnquadrar = function (arquivos, W, H, inteira) {
    // Igual a MidiasEvento::AMPLIACAO_MAXIMA.
    var AMPLIACAO = 2;
    var minL = Math.ceil(W / AMPLIACAO), minA = Math.ceil(H / AMPLIACAO);
    var recortes = [];
    var i = 0;
    return new Promise(function (pronto) {
      function proximo() {
        if (i >= arquivos.length) { pronto(recortes); return; }
        var arquivo = arquivos[i];
        var url = URL.createObjectURL(arquivo);
        var img = new Image();
        img.onload = function () {
          var nw = img.naturalWidth, nh = img.naturalHeight;
          if (nw < minL || nh < minA) {
            URL.revokeObjectURL(url);
            // Logo pequeno não tem o que recortar: vai inteiro.
            if (inteira) { recortes.push(''); i++; proximo(); return; }
            pronto('"' + arquivo.name + '" tem ' + nw + ' x ' + nh + ' px e o mínimo é ' + minL + ' x ' + minA
              + ' px. Menor que isso, ampliada, fica borrada. Use uma foto maior.');
            return;
          }
          if (nw === W && nh === H) {
            URL.revokeObjectURL(url); recortes.push(''); i++; proximo(); return;
          }
          abrirModal(img, nw, nh, i + 1, arquivos.length, arquivo.name, function (r) {
            URL.revokeObjectURL(url);
            if (r === null) { pronto(null); return; }
            recortes.push(r); i++; proximo();
          });
        };
        img.onerror = function () { URL.revokeObjectURL(url); recortes.push(''); i++; proximo(); };
        img.src = url;
      }
      proximo();
    });

    function abrirModal(img, nw, nh, n, total, nome, fim) {
      var caixa = document.createElement('dialog');
      caixa.className = 'confirmador enquadrador';
      caixa.setAttribute('aria-labelledby', 'enq-titulo');
      caixa.innerHTML = '<h2 id="enq-titulo"></h2><p class="enq-sub"></p>'
        + '<div class="palco-recorte"><div class="moldura-recorte" tabindex="0" aria-label="Arraste a moldura para escolher a região da foto; setas movem, + e - mudam o tamanho">'
        + '<span class="alca" data-canto="nw"></span><span class="alca" data-canto="ne"></span>'
        + '<span class="alca" data-canto="sw"></span><span class="alca" data-canto="se"></span></div></div>'
        + '<label class="zoom-recorte">Zoom <input type="range" min="1" max="1" step="0.01" value="1" aria-label="Zoom"></label>'
        + '<p class="nitidez-recorte" aria-live="polite"></p>'
        + '<div class="acoes-modal"><button type="button" class="botao claro centralizar">Centralizar</button>'
        + (inteira ? '<button type="button" class="botao claro inteira">Usar a imagem inteira</button>' : '')
        + '<button type="button" class="botao claro cancelar">Cancelar</button>'
        + '<button type="button" class="botao usar">Usar este enquadramento</button></div>';
      caixa.querySelector('h2').textContent = 'Ajustar o enquadramento' + (total > 1 ? ' (' + n + ' de ' + total + ')' : '');
      caixa.querySelector('.enq-sub').textContent = nome + ' (' + nw + ' x ' + nh + ' px). Arraste a moldura para a região que deve aparecer; os cantos e o zoom mudam o tamanho. O que está dentro da moldura vira a imagem.';
      var nitidez = caixa.querySelector('.nitidez-recorte');
      var palco = caixa.querySelector('.palco-recorte');
      var moldura = caixa.querySelector('.moldura-recorte');
      var zoom = caixa.querySelector('input[type=range]');
      palco.insertBefore(img, moldura);
      document.body.appendChild(caixa);

      // A foto inteira cabe na tela; a moldura anda e cresce sobre ela.
      var maxW = Math.min(760, window.innerWidth * 0.96 - 60);
      var maxH = window.innerHeight * 0.55;
      var s = Math.min(maxW / nw, maxH / nh);
      var pw = Math.round(nw * s), ph = Math.round(nh * s);
      palco.style.width = pw + 'px';
      palco.style.height = ph + 'px';
      img.style.width = pw + 'px';
      img.style.height = ph + 'px';
      img.style.left = '0px';
      img.style.top = '0px';

      // Recorte em pixels da foto: x, y e a largura l (a altura segue a
      // proporção). Maior possível: a foto inteira numa direção; menor: o
      // que precisa de AMPLIACAO para chegar ao padrão.
      var lmax = Math.min(nw, nh * W / H);
      var lmin = Math.min(lmax, minL);
      var zmax = lmax / lmin;
      zoom.max = String(zmax);
      zoom.disabled = zmax <= 1.001;
      var rl = lmax, rx = 0, ry = 0;

      function alt() { return rl * H / W; }
      function limitar() {
        rl = Math.min(lmax, Math.max(lmin, rl));
        rx = Math.min(nw - rl, Math.max(0, rx));
        ry = Math.min(nh - alt(), Math.max(0, ry));
      }
      function desenhar() {
        limitar();
        moldura.style.left = (rx * s) + 'px';
        moldura.style.top = (ry * s) + 'px';
        moldura.style.width = (rl * s) + 'px';
        moldura.style.height = (alt() * s) + 'px';
        zoom.value = String(lmax / rl);
        // Quanto o pedaço escolhido vai ser ampliado para chegar ao padrão.
        var amplia = W / rl;
        nitidez.className = 'nitidez-recorte' + (amplia > 1.5 ? ' ruim' : amplia > 1.02 ? ' media' : ' boa');
        nitidez.textContent = amplia > 1.5 ? 'A foto vai ser bem ampliada e pode ficar borrada. Aumente a moldura ou use uma foto maior.'
          : amplia > 1.02 ? 'A foto vai ser um pouco ampliada; ainda fica boa.'
          : 'Nitidez ótima.';
      }
      // Muda o tamanho mantendo o centro da moldura no lugar.
      function redimensionar(novo) {
        var cx = rx + rl / 2, cy = ry + alt() / 2;
        rl = Math.min(lmax, Math.max(lmin, novo));
        rx = cx - rl / 2; ry = cy - alt() / 2;
        desenhar();
      }
      function centralizar() {
        rl = lmax; rx = (nw - rl) / 2; ry = (nh - alt()) / 2;
        desenhar();
      }
      function recorte() {
        var l = Math.max(lmin, Math.min(nw, Math.round(rl)));
        var a = Math.min(nh, Math.round(l * H / W));
        var x = Math.min(nw - l, Math.max(0, Math.round(rx)));
        var y = Math.min(nh - a, Math.max(0, Math.round(ry)));
        return x + ',' + y + ',' + l + ',' + a;
      }
      function fechar(r) { caixa.close(); caixa.remove(); fim(r); }

      // Arrastar a moldura move; arrastar um canto muda o tamanho, com o
      // canto oposto parado; dois dedos fazem pinça. Clicar fora da moldura
      // leva ela para lá.
      var toques = {}, canto = null, distancia = 0;
      palco.addEventListener('pointerdown', function (ev) {
        palco.setPointerCapture(ev.pointerId);
        toques[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
        canto = ev.target.dataset && ev.target.dataset.canto ? ev.target.dataset.canto : null;
        if (!canto && !moldura.contains(ev.target)) {
          var r = palco.getBoundingClientRect();
          rx = (ev.clientX - r.left) / s - rl / 2;
          ry = (ev.clientY - r.top) / s - alt() / 2;
          desenhar();
        }
        palco.classList.add('movendo');
        moldura.focus();
      });
      palco.addEventListener('pointermove', function (ev) {
        if (!toques[ev.pointerId]) { return; }
        var ids = Object.keys(toques);
        if (ids.length === 2) {
          toques[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
          var p1 = toques[ids[0]], p2 = toques[ids[1]];
          var d = Math.hypot(p1.x - p2.x, p1.y - p2.y);
          if (distancia) { redimensionar(rl * d / distancia); }
          distancia = d;
          return;
        }
        var t = toques[ev.pointerId];
        var dx = (ev.clientX - t.x) / s, dy = (ev.clientY - t.y) / s;
        toques[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
        if (canto) {
          // O canto oposto fica parado; vale o maior dos dois movimentos.
          var direita = canto.indexOf('e') !== -1, baixo = canto.charAt(0) === 's';
          var fixoX = direita ? rx : rx + rl, fixoY = baixo ? ry : ry + alt();
          var dl = Math.max(direita ? dx : -dx, (baixo ? dy : -dy) * W / H);
          var novo = Math.min(lmax, Math.max(lmin, rl + dl));
          novo = Math.min(novo, direita ? nw - fixoX : fixoX, (baixo ? nh - fixoY : fixoY) * W / H);
          rl = novo;
          rx = direita ? fixoX : fixoX - rl;
          ry = baixo ? fixoY : fixoY - alt();
        } else {
          rx += dx; ry += dy;
        }
        desenhar();
      });
      function soltar(ev) {
        delete toques[ev.pointerId]; distancia = 0;
        if (!Object.keys(toques).length) { palco.classList.remove('movendo'); canto = null; }
      }
      palco.addEventListener('pointerup', soltar);
      palco.addEventListener('pointercancel', soltar);
      palco.addEventListener('wheel', function (ev) {
        ev.preventDefault();
        redimensionar(rl * (ev.deltaY < 0 ? 1 / 1.08 : 1.08));
      }, { passive: false });
      zoom.addEventListener('input', function () { redimensionar(lmax / parseFloat(zoom.value)); });
      // Teclado: setas movem, + e - mudam o tamanho, Enter usa, Esc cancela.
      caixa.addEventListener('keydown', function (ev) {
        var passo = (ev.shiftKey ? 40 : 10) / s;
        var naMoldura = ev.target === moldura;
        if (ev.key === 'Enter' && !ev.target.closest('button')) { ev.preventDefault(); fechar(recorte()); }
        else if (naMoldura && ev.key === 'ArrowLeft') { ev.preventDefault(); rx -= passo; desenhar(); }
        else if (naMoldura && ev.key === 'ArrowRight') { ev.preventDefault(); rx += passo; desenhar(); }
        else if (naMoldura && ev.key === 'ArrowUp') { ev.preventDefault(); ry -= passo; desenhar(); }
        else if (naMoldura && ev.key === 'ArrowDown') { ev.preventDefault(); ry += passo; desenhar(); }
        else if (naMoldura && (ev.key === '+' || ev.key === '=')) { ev.preventDefault(); redimensionar(rl / 1.1); }
        else if (naMoldura && ev.key === '-') { ev.preventDefault(); redimensionar(rl * 1.1); }
      });
      caixa.addEventListener('cancel', function (ev) { ev.preventDefault(); fechar(null); });
      caixa.querySelector('.centralizar').addEventListener('click', centralizar);
      caixa.querySelector('.cancelar').addEventListener('click', function () { fechar(null); });
      caixa.querySelector('.usar').addEventListener('click', function () { fechar(recorte()); });
      if (inteira) { caixa.querySelector('.inteira').addEventListener('click', function () { fechar(''); }); }

      caixa.showModal();
      centralizar();
      moldura.focus();
    }
  };

  // Soltar arquivo FORA de uma zona faz o navegador abrir o arquivo e a
  // pessoa perde a página e o que estava preenchido. Melhor ignorar.
  ['dragover', 'drop'].forEach(function (evento) {
    window.addEventListener(evento, function (ev) {
      if (!ev.target.closest || !ev.target.closest('.zona-upload')) { ev.preventDefault(); }
    });
  });
})();

/*
 * Busca que filtra enquanto se digita.
 * Softsul Software
 *
 * O formulário continua existindo e funcionando com Enter, para quem tem
 * JavaScript desligado e para o endereço poder ser guardado nos favoritos.
 * O que muda é que a lista se atualiza sozinha ao digitar.
 *
 * A busca vai ao servidor, e não filtra as linhas na tela, porque a lista
 * pode ser maior que a página: filtrar só o que já veio esconderia resultado
 * que existe. A espera de 250 ms evita disparar uma consulta por tecla, e o
 * AbortController descarta a resposta de uma busca já superada, que é o que
 * faz a lista às vezes "voltar" para um termo antigo.
 */
(function () {
  'use strict';
  var campo = document.querySelector('form.filtros input[type=search]');
  if (!campo || !window.fetch || !window.AbortController) { return; }

  var form = campo.closest('form');
  var alvo = document.getElementById('lista-viva');
  if (!alvo) { return; }

  var relogio = null;
  var emCurso = null;

  function buscar() {
    // Parte do endereço atual (situação, ordem, tamanho da página...) e põe
    // por cima o que está no formulário: sem isto, digitar com a pílula
    // "Todos" marcada voltava para o filtro padrão. Página volta para a 1.
    var dados = new URLSearchParams(location.search);
    ['p', 'parcial'].forEach(function (k) { dados.delete(k); });
    var doForm = new URLSearchParams(new FormData(form));
    // Todo campo do formulário manda no seu parâmetro, mesmo sem valor: a
    // caixa de marcar desmarcada não vai no FormData, e sem isto o filtro
    // antigo ficava no endereço. "campo[]" também apaga o "campo=a|b" que
    // veio de um link montado à mão e o "campo[0]=a" dos links que o PHP
    // monta (http_build_query numera as listas).
    var nomes = new Set(doForm.keys());
    Array.prototype.forEach.call(form.elements, function (el) { if (el.name) { nomes.add(el.name); } });
    nomes.forEach(function (k) {
      dados.delete(k);
      if (k.slice(-2) === '[]') {
        var base = k.slice(0, -2);
        dados.delete(base);
        Array.from(dados.keys()).forEach(function (d) {
          if (d.indexOf(base + '[') === 0 && /^\[\d+\]$/.test(d.slice(base.length))) { dados.delete(d); }
        });
      }
      doForm.getAll(k).forEach(function (v) { dados.append(k, v); });
    });
    // Campo vazio não vai para o endereço: senão a barra fica com q=&cliente=
    // e o botão Limpar acenderia sem haver filtro nenhum.
    Array.from(dados.keys()).forEach(function (k) {
      if (dados.getAll(k).every(function (v) { return v === ''; })) { dados.delete(k); }
    });
    var url = form.getAttribute('action') + '?' + dados.toString();

    if (emCurso) { emCurso.abort(); }
    emCurso = new AbortController();
    alvo.classList.add('carregando');

    fetch(url + '&parcial=1', { signal: emCurso.signal, headers: { 'X-Parcial': '1' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        alvo.innerHTML = html;
        alvo.classList.remove('carregando');
        // Mantém o endereço em dia, para recarregar ou favoritar dar o mesmo
        // resultado que está na tela.
        history.replaceState(null, '', dados.toString() ? url : form.getAttribute('action'));
        var limpar = document.getElementById('limpar-filtros');
        if (limpar) { limpar.hidden = !window.temFiltroNaLista(dados); }
      })
      .catch(function (e) {
        if (e.name !== 'AbortError') { alvo.classList.remove('carregando'); }
      });
  }

  campo.addEventListener('input', function () {
    clearTimeout(relogio);
    relogio = setTimeout(buscar, 250);
  });

  // As pílulas de filtro foram montadas quando a página abriu, sem o que foi
  // digitado depois: ao clicar, levam junto a busca que está no campo.
  document.querySelectorAll('.pilulas-filtro a.pf').forEach(function (pilula) {
    pilula.addEventListener('click', function (ev) {
      var destino = new URL(pilula.href, location.href);
      var termo = campo.value.trim();
      if (termo === (destino.searchParams.get(campo.name) || '')) { return; }
      ev.preventDefault();
      if (termo) { destino.searchParams.set(campo.name, termo); } else { destino.searchParams.delete(campo.name); }
      destino.searchParams.delete('p');
      location.href = destino.toString();
    });
  });

  // Marcar um papel, ou trocar o cliente, atualiza a lista na hora: aqui não
  // há o que esperar, a pessoa já decidiu ao clicar.
  form.addEventListener('change', function (ev) {
    if (ev.target === campo) { return; }
    // Data de filtro tem o próprio tempo: o navegador avisa mudança a cada
    // dígito do ano, e buscar ali pesquisa o ano 0002.
    if (ev.target.matches && ev.target.matches('[data-filtra-data]')) { return; }
    clearTimeout(relogio);
    buscar();
  });

  // O botão Todos é o "nenhum papel marcado": desmarca as caixas e busca.
  var todos = form.querySelector('[data-limpa-papeis]');
  if (todos) {
    todos.addEventListener('click', function () {
      form.querySelectorAll('input[name="papel[]"]').forEach(function (c) {
        c.checked = false;
        c.closest('.pf').classList.remove('ativa');
      });
      todos.closest('.pf').classList.add('ativa');
      buscar();
    });
  }

  // A pílula acompanha a caixa que ela envolve, e Todos acende sozinho
  // quando nenhum papel sobrou marcado.
  form.querySelectorAll('input[name="papel[]"]').forEach(function (c) {
    c.addEventListener('change', function () {
      c.closest('.pf').classList.toggle('ativa', c.checked);
      if (todos) {
        todos.closest('.pf').classList.toggle(
          'ativa', !form.querySelector('input[name="papel[]"]:checked')
        );
      }
    });
  });
  // Enter não recarrega a página: já está tudo em tela.
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearTimeout(relogio);
    buscar();
  });
})();

/*
 * Visualizador de documento em modal.
 * Softsul Software
 *
 * Vive aqui e não na tela porque serve a qualquer ficha que guarde um
 * documento ou uma imagem (regulamento do evento, capa, comprovante).
 *
 * <dialog> nativo dá fundo escurecido, fechar com Esc e devolução de foco de
 * graça. O iframe só recebe a URL na abertura e é esvaziado ao fechar: PDF
 * carregado continua consumindo memória mesmo escondido.
 */
(function () {
  'use strict';
  var visor = document.getElementById('visor');
  // Regra do ERP: PDF só abre em modal. A tela que não trouxe o visor ganha
  // um aqui, igual ao das fichas, quando tem algum link de documento.
  if (!visor && document.querySelector('a.ver-pdf, a.ver-imagem')) {
    visor = document.createElement('dialog');
    visor.id = 'visor';
    visor.className = 'visor';
    visor.setAttribute('aria-label', 'Documento');
    visor.innerHTML = '<header><b id="visor-nome"></b>'
      + '<a id="visor-baixar" href="#" download title="Baixar o arquivo">Baixar</a>'
      + '<a id="visor-aba" href="#" target="_blank" rel="noopener" title="Abrir em outra aba">Nova aba</a>'
      + '<button type="button" id="visor-fechar" aria-label="Fechar">&times;</button></header>'
      + '<iframe id="visor-quadro" title="Documento em PDF"></iframe><img id="visor-imagem" alt="" hidden>';
    document.body.appendChild(visor);
  }
  if (!visor) { return; }

  var quadro = document.getElementById('visor-quadro');
  var nome   = document.getElementById('visor-nome');
  var baixar = document.getElementById('visor-baixar');
  var aba    = document.getElementById('visor-aba');
  var fechar = document.getElementById('visor-fechar');

  // Exposta porque há lista que chega depois, vinda do servidor: sem religar,
  // o link sairia da tela em vez de abrir o documento aqui.
  var imagem = document.getElementById('visor-imagem');
  var visorAntes = null;

  window.erpLigarVerPdf = function (onde) {
    (onde || document).querySelectorAll('a.ver-pdf, a.ver-imagem').forEach(function (link) {
      if (link.dataset.visorLigado === '1') { return; }
      link.dataset.visorLigado = '1';
      link.addEventListener('click', function (ev) {
        // Ctrl, Shift e o botão do meio continuam abrindo em aba: é o que
        // esses atalhos fazem em qualquer site, e quebrar isso irrita.
        if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) { return; }
        ev.preventDefault();
        var url = link.getAttribute('href');
        var ehImagem = link.classList.contains('ver-imagem');
        nome.textContent = link.dataset.nome || 'Documento';
        baixar.href = url;
        aba.href = url;
        // Imagem no iframe fica pequena e centralizada pelo navegador, sem
        // ajuste nenhum: <img> num modal é o que a pessoa espera ao clicar
        // numa foto.
        if (imagem) {
          imagem.hidden = !ehImagem;
          imagem.src = ehImagem ? url : '';
        }
        quadro.hidden = ehImagem;
        visorAntes = history.length;
        if (!ehImagem) { quadro.src = url + '#view=FitH'; }
        visor.showModal();
      });
    });
  };
  window.erpLigarVerPdf(document);

  if (fechar) { fechar.addEventListener('click', function () { visor.close(); }); }
  visor.addEventListener('close', function () {
    quadro = window.inventourIframeNovo(quadro, visorAntes);
    if (imagem) { imagem.src = ''; }
  });
  // Clicar fora não fecha, aqui nem em modal nenhum: a mesma janela que abre
  // um PDF abre um cadastro de trinta campos, e um clique torto perto da
  // borda apagava meia hora de digitação. Sair é pelo botão, ou por Esc.
})();

/*
 * Confirmação de ação destrutiva.
 * Softsul Software
 *
 * Qualquer botão com data-confirmar pergunta antes, pelo modal do sistema.
 * Fica aqui para que um botão perigoso novo já nasça perguntando, em vez de
 * depender de alguém lembrar.
 */
(function () {
  'use strict';
  // Uma escuta só, na página, e na fase de captura: o botão que chega depois
  // (lista que vem do servidor dentro de um modal) também pergunta. Ligado
  // botão a botão no carregamento, o "Tirar da sequência" do modal saía sem
  // perguntar nada.
  document.addEventListener('click', function (ev) {
    var botao = ev.target.closest ? ev.target.closest('[data-confirmar]') : null;
    if (!botao || botao.disabled) { return; }
    if (botao.dataset.confirmado === '1') {
      delete botao.dataset.confirmado;
      return;
    }
    ev.preventDefault();
    ev.stopPropagation();
    window.erpPerguntar({
      titulo: botao.dataset.titulo || 'Confirmar',
      texto: botao.dataset.confirmar,
      confirmar: botao.dataset.botao || botao.textContent.trim() || 'Confirmar',   // data-botao: o rótulo do botão do modal
      perigo: botao.classList.contains('perigo')
    }).then(function (sim) {
      if (!sim) { return; }
      // Marca e clica de verdade, para o botão continuar sendo o submitter
      // do formulário: é isso que faz name e value chegarem ao servidor.
      botao.dataset.confirmado = '1';
      botao.click();
    });
  }, true);
})();

/*
 * Preenchimento de endereço pelo CEP.
 * Softsul Software
 *
 * Vale em qualquer tela que tenha um campo #cep: assim que os oito dígitos
 * estão lá, o endereço vem sozinho. Digitar rua, bairro e município à mão é
 * trabalho repetido e fonte de divergência entre cadastros.
 *
 * Quando o endereço já está preenchido e o CEP aponta para outro lugar, o
 * sistema NÃO troca sozinho: mostra o que mudaria, campo a campo, e pergunta.
 * Sobrescrever calado é como se perde o complemento, o número e a correção
 * que alguém fez à mão, sem ninguém entender depois o que aconteceu.
 */
(function () {
  'use strict';
  var cep = document.getElementById('cep');
  if (!cep || !window.fetch) { return; }

  var ajuda = cep.closest('.campo') ? cep.closest('.campo').querySelector('.ajuda') : null;
  var ultimo = '';

  var mapa = [
    ['logradouro', 'street',       'Logradouro'],
    ['bairro',     'neighborhood', 'Bairro'],
    ['cidade',     'city',         'Município'],
    ['uf',         'state',        'UF'],
  ];

  function aplicar(dados) {
    mapa.forEach(function (par) {
      var el = document.getElementById(par[0]);
      if (el && dados[par[1]]) {
        el.value = dados[par[1]];
        var caixa = el.closest('.entrada');
        if (caixa) { caixa.classList.add('preenchido'); }
      }
    });
    var numero = document.getElementById('numero');
    if (numero && !numero.value) { numero.focus(); }
    // Avisa quem mais depende do endereço (o campo de município do IBGE).
    cep.dispatchEvent(new CustomEvent('cep-aplicado', { detail: dados }));
  }

  function dizer(texto, classe) {
    if (ajuda) { ajuda.textContent = texto; ajuda.className = 'ajuda' + (classe ? ' ' + classe : ''); }
  }

  cep.addEventListener('input', function () {
    // Endereço de fora do Brasil não passa pelo serviço de CEP: ele só conhece
    // daqui, e responderia "não encontrado" para todo endereço estrangeiro.
    if (cep.dataset.semBusca === '1') { return; }
    var v = cep.value.replace(/\D/g, '').slice(0, 8);
    cep.value = v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
    if (v.length !== 8 || v === ultimo) { return; }
    ultimo = v;
    dizer('Buscando o endereço...');

    fetch('/api/cep?cep=' + v, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (d) {
        var lugar = (d.city || '') + (d.state ? '/' + d.state : '');

        // O que já está preenchido e mudaria de valor.
        var conflitos = mapa.filter(function (par) {
          var el = document.getElementById(par[0]);
          return el && el.value.trim() && d[par[1]] &&
                 el.value.trim().toLowerCase() !== String(d[par[1]]).toLowerCase();
        });

        if (!conflitos.length) {
          aplicar(d);
          dizer(lugar ? lugar + '. Confira o número.' : '', 'bom');
          return;
        }

        var linhas = conflitos.map(function (par) {
          var el = document.getElementById(par[0]);
          return '<div class="troca">' +
                 '<span class="rot">' + par[2] + '</span>' +
                 '<span class="de">' + el.value.trim() + '</span>' +
                 '<span class="seta">para</span>' +
                 '<span class="para">' + d[par[1]] + '</span></div>';
        }).join('');

        window.erpPerguntar({
          titulo: 'Este CEP é de outro endereço',
          largo: true,
          html: '<p>O CEP <b>' + cep.value + '</b> corresponde a ' + (lugar || 'outro local') +
                '. Quer substituir o que já está preenchido?</p>' +
                '<div class="trocas">' + linhas + '</div>' +
                '<p class="ajuda">O que não aparece aqui, como número e complemento, fica como está.</p>',
          confirmar: 'Substituir',
          cancelar: 'Manter o que está'
        }).then(function (sim) {
          if (sim) {
            aplicar(d);
            dizer('Endereço substituído por ' + lugar + '.', 'bom');
          } else {
            dizer('Endereço mantido. O CEP digitado é de ' + lugar + '.', 'erro');
          }
        });
      })
      .catch(function () {
        dizer('CEP não encontrado. Preencha à mão.', 'erro');
      });
  });
})();

/*
 * Campo de cliente com digitação e busca.
 * Softsul Software
 *
 * Substitui a lista suspensa, que deixa de funcionar quando os clientes
 * passam de algumas dezenas. O que o formulário envia é sempre o ID: o texto
 * visível serve para ler e para procurar, nunca para gravar.
 *
 * Por isso a validação existe: se a pessoa digitou algo e não escolheu da
 * lista, o campo escondido continua vazio e o envio é barrado com explicação,
 * em vez de gravar um evento sem organizador.
 */
(function () {
  'use strict';
  // Exposta porque tela que cria campo depois do carregamento (uma linha
  // nova numa lista editável) precisa ligar a busca dela na hora.
  window.erpLigarBusca = function (caixa) {
    if (!caixa || caixa.dataset.ligada === '1') { return; }
    caixa.dataset.ligada = '1';
    var oculto  = caixa.querySelector('input[type=hidden]');
    var texto   = caixa.querySelector('input[type=text]');
    var enviaAoEscolher = caixa.dataset.enviar === '1';
    var lista   = caixa.querySelector('.sugestoes');
    var aviso   = caixa.querySelector('.aviso-busca');
    var limpar  = caixa.querySelector('.limpar-busca');
    if (!texto || texto.readOnly) { return; }

    var relogio = null, emCurso = null, itens = [], marcado = -1;
    var escolhido = texto.value;

    function mostrarLimpar() { if (limpar) { limpar.hidden = !oculto.value; } }
    mostrarLimpar();

    // Quem preenche o campo por fora (um modal que abre já editando) avisa
    // por aqui, senão a escolha guardada continua sendo a do registro
    // anterior e a primeira tecla digitada apagaria o valor certo.
    oculto.addEventListener('valor-definido', function () {
      escolhido = texto.value;
      mostrarLimpar();
      lista.hidden = true;
      aviso.hidden = true;
      caixa.querySelector('.entrada').classList.remove('erro');
    });

    function fechar() { lista.hidden = true; marcado = -1; }

    function pintar() {
      Array.prototype.forEach.call(lista.children, function (li, i) {
        li.classList.toggle('marcado', i === marcado);
      });
    }

    function escolher(item) {
      oculto.value = item.id;
      // "texto" é o rótulo cru para o campo ("Pato Branco/PR"); "nome" vem
      // pronto para HTML, para a lista, e no valor do campo mostraria &amp;.
      texto.value = item.texto || item.nome;
      escolhido = texto.value;
      aviso.hidden = true;
      caixa.querySelector('.entrada').classList.remove('erro');
      fechar();
      mostrarLimpar();
      texto.dispatchEvent(new CustomEvent('item-escolhido', { bubbles: true, detail: item }));
      // Como filtro, escolher já vale por aplicar: obrigar a clicar num botão
      // depois de escolher é um passo a mais sem função.
      if (enviaAoEscolher && texto.form) { texto.form.submit(); }
    }

    function desenhar(dados) {
      itens = dados;
      if (!dados.length) {
        lista.innerHTML = '<li class="vazia"></li>';
        lista.firstChild.textContent = caixa.dataset.vazio || 'Nada encontrado.';
        lista.hidden = false;
        return;
      }
      lista.innerHTML = dados.map(function (d) {
        return '<li role="option" data-id="' + d.id + '">' +
          (d.foto ? '<img src="' + d.foto + '" alt="" width="26" height="26">'
                  : (d.iniciais || '<span class="sem-foto"></span>')) +
          '<span class="txt"><b>' + d.nome + '</b><span>' + d.extra + '</span></span></li>';
      }).join('');
      lista.hidden = false;
      Array.prototype.forEach.call(lista.children, function (li, i) {
        li.addEventListener('mousedown', function (ev) { ev.preventDefault(); escolher(itens[i]); });
      });
    }

    function buscar(termo) {
      if (emCurso) { emCurso.abort(); }
      emCurso = new AbortController();
      // A fonte já pode trazer parâmetro próprio (?tipo=nbs), e emendar outro
      // "?" faria o servidor ler tipo="nbs?q=hospedagem": consulta errada, sem
      // erro nenhum na tela.
      // A fonte é lida a cada busca, e não uma vez só: há campo que muda de
      // fonte conforme outro campo (a NBS depende do item da LC 116).
      var fonte = caixa.dataset.fonte;
      var busca = termo === undefined ? texto.value : termo;
      var url = fonte + (fonte.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(busca);
      fetch(url, { signal: emCurso.signal })
        .then(function (r) { return r.json(); })
        .then(desenhar)
        .catch(function (e) { if (e.name !== 'AbortError') { fechar(); } });
    }

    texto.addEventListener('input', function () {
      // Digitar depois de ter escolhido invalida a escolha: o texto e o id
      // precisam continuar dizendo a mesma coisa.
      if (texto.value !== escolhido) { oculto.value = ''; mostrarLimpar(); }
      clearTimeout(relogio);
      relogio = setTimeout(buscar, 200);
    });
    // A lista abre com o clique, a seta para baixo ou ao digitar, e não só
    // por ganhar o foco: o modal põe o foco no primeiro campo e o Enter
    // avança entre campos, e a lista aberta sem ninguém pedir cobria o
    // formulário e confundia.
    function abrirLista() {
      // Com um valor já escolhido, abre a lista inteira e seleciona o texto,
      // então digitar por cima já filtra.
      if (oculto.value) {
        texto.select();
        buscar('');
        return;
      }
      buscar();
    }
    texto.addEventListener('focus', function () { if (oculto.value) { texto.select(); } });
    texto.addEventListener('click', function () { if (lista.hidden) { abrirLista(); } });
    texto.addEventListener('blur', function () { setTimeout(fechar, 120); });

    texto.addEventListener('keydown', function (ev) {
      if (lista.hidden) {
        if (ev.key === 'ArrowDown') { ev.preventDefault(); abrirLista(); }
        return;
      }
      if (ev.key === 'ArrowDown') { ev.preventDefault(); marcado = Math.min(marcado + 1, itens.length - 1); pintar(); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); marcado = Math.max(marcado - 1, 0); pintar(); }
      else if (ev.key === 'Enter' && marcado >= 0) { ev.preventDefault(); escolher(itens[marcado]); }
      else if (ev.key === 'Escape') { fechar(); }
    });

    if (limpar) {
      limpar.addEventListener('click', function () {
        oculto.value = ''; texto.value = ''; escolhido = '';
        mostrarLimpar();
        // Limpar o filtro quer dizer "todos", e isso também é uma escolha.
        if (enviaAoEscolher && texto.form) { texto.form.submit(); return; }
        texto.focus(); buscar();
      });
    }

    var form = texto.closest('form');
    if (form && oculto.dataset.obrigatorio) {
      form.addEventListener('submit', function (ev) {
        // Só barra o envio de verdade: botão de excluir e de anexar usam
        // formnovalidate ou outro formulário, e não devem exigir cliente.
        if (ev.submitter && ev.submitter.hasAttribute('formnovalidate')) { return; }
        if (!oculto.value) {
          ev.preventDefault();
          caixa.querySelector('.entrada').classList.add('erro');
          aviso.hidden = false;
          aviso.className = 'ajuda aviso-busca erro';
          var oque = oculto.dataset.oque || 'o item';
          aviso.textContent = texto.value.trim()
            ? 'Escolha ' + oque + ' na lista. Digitar o nome não basta, porque o sistema grava o cadastro e não o texto.'
            : 'Escolha ' + oque + '.';
          texto.focus();
        }
      });
    }
  };

  document.querySelectorAll('.busca-viva').forEach(window.erpLigarBusca);
})();

/*
 * Máscara de telefone brasileiro.
 * Softsul Software
 *
 * (99) 9999-9999 para fixo e (99) 99999-9999 para celular. A máscara muda
 * sozinha ao chegar o nono dígito, porque quem digita não escolhe o formato
 * antes: escolhe o número.
 *
 * Vale em qualquer input com class="telefone", então um campo novo já nasce
 * mascarado. O que vai para o servidor continua sendo o texto mascarado, e o
 * PHP normaliza para E.164; a máscara é conveniência de leitura, nunca a
 * regra que vale.
 */
(function () {
  'use strict';
  function mascarar(valor) {
    var d = valor.replace(/\D/g, '');
    // Cola o 55 do começo se a pessoa colou um número em E.164.
    if (d.length > 11 && d.slice(0, 2) === '55') { d = d.slice(2); }
    d = d.slice(0, 11);
    if (d.length <= 2)  { return d.length ? '(' + d : ''; }
    if (d.length <= 6)  { return '(' + d.slice(0, 2) + ') ' + d.slice(2); }
    if (d.length <= 10) { return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6); }
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  // Exposta porque há linha que nasce depois, clonada em tela: sem religar, o
  // campo novo ficaria sem máscara e sem o aviso de número incompleto.
  window.erpLigarTelefones = function (onde) {
    (onde || document).querySelectorAll('input.telefone').forEach(function (campo) {
    if (campo.dataset.telefoneLigado === '1') { return; }
    campo.dataset.telefoneLigado = '1';
    campo.setAttribute('inputmode', 'tel');
    if (!campo.getAttribute('placeholder')) {
      campo.setAttribute('placeholder', '(00) 00000-0000');
    }
    if (campo.value) { campo.value = mascarar(campo.value); }

    campo.addEventListener('input', function () {
      // Guarda a distância até o fim para o cursor não pular para o final
      // quando a pessoa corrige um dígito no meio do número.
      var restante = campo.value.length - campo.selectionStart;
      campo.value = mascarar(campo.value);
      var pos = Math.max(0, campo.value.length - restante);
      campo.setSelectionRange(pos, pos);
    });

    // Ao sair, avisa quando o número está incompleto: descobrir isso só na
    // hora de o bot tentar falar com a pessoa seria tarde.
    campo.addEventListener('blur', function () {
      var d = campo.value.replace(/\D/g, '');
      var caixa = campo.closest('.entrada');
      var ajuda = campo.closest('.campo') ? campo.closest('.campo').querySelector('.ajuda') : null;
      var ruim = d.length > 0 && d.length !== 10 && d.length !== 11;
      if (caixa) { caixa.classList.toggle('erro', ruim); }
      if (ajuda && ajuda.dataset.original === undefined) { ajuda.dataset.original = ajuda.textContent; }
      if (ajuda) {
        ajuda.textContent = ruim
          ? 'Número incompleto. Use DDD e 8 dígitos para fixo ou 9 para celular.'
          : (ajuda.dataset.original || '');
        ajuda.className = 'ajuda' + (ruim ? ' erro' : '');
      }
    });
    });
  };
  window.erpLigarTelefones(document);
})();

/*
 * Campo de dinheiro.
 * Softsul Software
 *
 * Formata ao sair do campo, não a cada tecla: reescrever enquanto a pessoa
 * digita move o cursor de lugar e faz apagar dar errado. Aceita as duas
 * convenções, porque valor colado de planilha às vezes traz 5,831.20, e o
 * separador que vale é sempre o último.
 */
(function () {
  'use strict';
  function paraNumero(texto) {
    var t = String(texto).replace(/[^\d,.-]/g, '');
    if (t === '') { return null; }
    var virgula = t.lastIndexOf(',');
    var ponto = t.lastIndexOf('.');
    if (virgula !== -1 && virgula > ponto) {
      t = t.replace(/\./g, '').replace(',', '.');
    } else if (ponto !== -1) {
      t = t.replace(/,/g, '');
    }
    var n = parseFloat(t);
    return isNaN(n) ? null : n;
  }
  window.erpLigarMoeda = function (campo) {
    if (!campo || campo.dataset.moedaLigada === '1') { return; }
    campo.dataset.moedaLigada = '1';
    function formatar() {
      var n = paraNumero(campo.value);
      campo.value = n === null ? '' : n.toLocaleString('pt-BR', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
      });
    }
    formatar();
    campo.addEventListener('blur', formatar);
  };
  document.querySelectorAll('input.moeda').forEach(window.erpLigarMoeda);
})();

/*
 * Avisos no estilo push: canto superior direito, 3 segundos, com X para
 * fechar antes. Com o mouse em cima, o tempo para (dá para terminar de ler).
 * Vale para o que acontece sem recarregar (erpAvisar) e para o aviso que
 * veio do servidor depois de salvar (p.aviso[data-push], de avisoGuardado).
 * Softsul Software
 */
(function () {
  'use strict';
  var DURACAO = 3000;

  function caixa() {
    var c = document.getElementById('avisos-vivos');
    if (!c) {
      c = document.createElement('div');
      c.id = 'avisos-vivos';
      // Como popover, a caixa vai para a camada de cima, a mesma dos modais
      // (<dialog>): sem isso, o aviso dado com um modal aberto fica atrás dele.
      if ('popover' in HTMLElement.prototype) { c.popover = 'manual'; }
      document.body.appendChild(c);
    }
    return c;
  }

  // Reabrir põe a caixa por cima do último modal aberto.
  function trazerParaFrente(c) {
    if (!c.popover) { return; }
    try {
      if (c.matches(':popover-open')) { c.hidePopover(); }
      c.showPopover();
    } catch (e) { /* sem popover, fica como antes */ }
  }

  function sair(p) {
    if (p.classList.contains('saindo')) { return; }
    p.classList.add('saindo');
    setTimeout(function () { p.remove(); }, 350);
  }

  // Põe o X e o relógio num aviso e o leva para o canto.
  function empurrar(p) {
    p.removeAttribute('data-push');
    p.classList.add('push');
    var x = document.createElement('button');
    x.type = 'button';
    x.className = 'fechar-push';
    x.setAttribute('aria-label', 'Fechar aviso');
    x.textContent = '\u00d7';
    x.addEventListener('click', function () { sair(p); });
    p.appendChild(x);
    var c = caixa();
    c.appendChild(p);
    trazerParaFrente(c);
    var relogio = null;
    function contar() { relogio = setTimeout(function () { sair(p); }, DURACAO); }
    p.addEventListener('mouseenter', function () { clearTimeout(relogio); });
    p.addEventListener('mouseleave', contar);
    contar();
  }

  var ICONES = { bom: 'check', info: 'check', atencao: 'alerta', ruim: 'alerta' };
  window.erpAvisar = function (texto, tom) {
    tom = ICONES[tom] ? tom : 'bom';
    var p = document.createElement('p');
    p.className = 'aviso ' + tom;
    p.setAttribute('role', tom === 'ruim' ? 'alert' : 'status');
    p.innerHTML = '<svg aria-hidden="true" focusable="false"><use href="#i-' + ICONES[tom] + '"/></svg><span></span>';
    p.querySelector('span').textContent = texto;
    empurrar(p);
  };

  document.querySelectorAll('p.aviso[data-push]').forEach(empurrar);
})();

/* ------------------------------------------- caixas de resumo em modal */
/*
 * Tela longa que se usa uma vez só (o detalhe de um registro, um resumo de
 * conferência) não fica aberta para sempre na ficha: a tela mostra o resumo,
 * e o detalhe abre aqui, por data-abre e data-fecha.
 *
 * <dialog> nativo já dá o fundo escurecido, o Esc e a devolução do foco. Não há
 * fechar clicando fora, de propósito: dentro há um formulário com o que a
 * pessoa corrigiu, e um clique perdido não pode jogar isso fora.
 */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var abre = ev.target.closest('[data-abre]');
    if (abre) {
      var caixa = document.getElementById(abre.getAttribute('data-abre'));
      // Um modal só para a lista inteira: o botão da linha leva os valores
      // em data-preenche (campo do formulário ou [data-texto] para mostrar).
      if (caixa && abre.hasAttribute('data-preenche')) {
        var dados = {};
        try { dados = JSON.parse(abre.getAttribute('data-preenche')) || {}; } catch (e) { dados = {}; }
        Object.keys(dados).forEach(function (k) {
          var campo = caixa.querySelector('[name="' + k + '"]');
          if (campo) { campo.value = dados[k]; }
          caixa.querySelectorAll('[data-texto="' + k + '"]').forEach(function (el) { el.textContent = dados[k]; });
        });
      }
      if (caixa && !caixa.open) {
        caixa.showModal();
        var primeiro = caixa.querySelector('textarea, input[type=text], input:not([type])');
        if (primeiro) { primeiro.focus(); }
      }
      return;
    }
    var fecha = ev.target.closest('[data-fecha]');
    if (fecha) {
      var dela = fecha.closest('dialog');
      if (dela) { dela.close(); }
    }
  });
})();

/*
 * CNPJ: máscara e busca na Receita.
 * Softsul Software
 *
 * Vale em qualquer tela que tenha #cnpj e #btn-cnpj, e não só no cadastro de
 * cliente: a mesma consulta serve para os dados da própria empresa. Estava
 * embutida numa tela só, e por isso o botão da tela nova não fazia nada.
 *
 * O preenchimento é sugestão: só completa campo vazio, todo campo continua
 * editável, e nada é gravado sem a pessoa salvar. A Receita completa, não
 * manda.
 */
(function () {
  'use strict';
  var campo = document.getElementById('cnpj');
  var botao = document.getElementById('btn-cnpj');
  if (!campo) { return; }

  // A máscara e a validação (inclusive do CNPJ com letras) ficam no bloco
  // "CPF e CNPJ em todo o sistema"; aqui é só a busca na Receita.
  if (!botao || !window.fetch) { return; }

  // A ajuda é a do próprio campo, achada pela estrutura: id fixo obrigaria
  // cada tela nova a lembrar de criar um elemento com aquele nome.
  var ajuda = document.getElementById('ajuda-cnpj')
           || (campo.closest('.campo') ? campo.closest('.campo').querySelector('.ajuda') : null);

  function dizer(texto, classe, html) {
    if (!ajuda) { return; }
    if (html) { ajuda.innerHTML = texto; } else { ajuda.textContent = texto; }
    ajuda.className = 'ajuda' + (classe ? ' ' + classe : '');
  }
  // O que a Receita preencheu da última vez, campo a campo. Ao consultar
  // outro CNPJ, o campo que ainda tem o valor da consulta anterior é
  // trocado (o cadastro é de outra empresa); o campo que a pessoa editou
  // depois fica como ela deixou. Sem isto, trocar o CNPJ mantinha razão
  // social e endereço do primeiro.
  var daReceita = {};
  function preencher(id, valor) {
    var el = document.getElementById(id);
    if (!el) { return; }
    var veioDaReceita = daReceita[id] !== undefined && el.value === daReceita[id];
    if (!el.value || veioDaReceita) {
      // A máscara do CEP (99999-999) é aplicada aqui mesmo, sem o evento.
      if (id === 'cep') {
        var d = String(valor || '').replace(/\D/g, '').slice(0, 8);
        valor = d.length === 8 ? d.slice(0, 5) + '-' + d.slice(5) : d;
      }
      el.value = valor || '';
      // Só o telefone precisa do evento, para a máscara formatar o E.164.
      // O CEP não: o evento dispararia a busca de endereço, que compararia
      // o que a Receita acabou de escrever com o que o serviço de CEP tem
      // e abriria a pergunta de conflito a cada consulta.
      if (id === 'telefone') { el.dispatchEvent(new Event('input', { bubbles: true })); }
      // Guardado DEPOIS da máscara: é o valor formatado que fica no campo,
      // e é com ele que a próxima consulta compara. Com o valor cru, o
      // telefone parecia editado à mão e nunca era trocado.
      daReceita[id] = el.value;
    }
  }

  // A consulta roda sozinha ao sair do campo com o CNPJ completo: ninguém
  // devia precisar achar a lupa para a Receita preencher o resto. A lupa
  // continua, para tentar de novo quando a Receita não respondeu. O mesmo
  // CNPJ não é consultado duas vezes seguidas (a API tem limite por minuto).
  var ultimo = '';
  function consultar(manual) {
    var digitos = campo.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
    if (digitos.length !== 14) {
      if (manual) { dizer('Digite os 14 caracteres do CNPJ.', 'erro'); }
      return;
    }
    if (!manual && digitos === ultimo) { return; }
    ultimo = digitos;
    botao.disabled = true;
    dizer('Consultando a Receita...', '');

    fetch('/api/cnpj?cnpj=' + digitos, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        botao.disabled = false;
        if (!res.ok) {
          dizer(res.j.erro || 'Não foi possível consultar.', 'erro');
          return;
        }
        // Só o cadastro de cliente se importa com CNPJ repetido. Nos dados da
        // própria empresa, encontrar o CNPJ na carteira não impede nada.
        if (res.j.existente && botao.dataset.checarDuplicado === '1') {
          dizer('Este CNPJ já está em <a href="' + res.j.existente.url + '">' +
                res.j.existente.razao_social + '</a>.', 'erro', true);
          return;
        }
        var d = res.j.dados;
        ['razao_social', 'nome_fantasia', 'email', 'cep', 'logradouro', 'numero',
         'complemento', 'bairro', 'cidade', 'uf'].forEach(function (k) { preencher(k, d[k]); });

        // O telefone passa pela mesma regra; a máscara age no evento input
        // que preencher() dispara (sem ele ficaria o E.164 cru na tela).
        preencher('telefone', d.telefone);
        ['situacao_cadastral', 'natureza_juridica', 'cnae_principal', 'codigo_ibge']
          .forEach(function (k) {
            var el = document.getElementById(k);
            // O change avisa o campo de município, que se escolhe pelo código
            // IBGE; com o detalhe daReceita, ele troca o município que a
            // própria consulta anterior tinha escolhido.
            if (el && d[k]) {
              el.value = d[k];
              el.dispatchEvent(new CustomEvent('change', { detail: { daReceita: true } }));
            }
          });
        var receita = document.getElementById('dados_receita');
        if (receita) { receita.value = JSON.stringify(res.j); }

        dizer('Situação: ' + (d.situacao_cadastral || 'não informada') +
              '. Confira os dados antes de salvar.',
              d.situacao_cadastral === 'ATIVA' ? 'bom' : 'erro');
      })
      .catch(function () {
        botao.disabled = false;
        ultimo = '';
        dizer('A consulta falhou. Preencha à mão ou tente de novo pela lupa.', 'erro');
      });
  }
  botao.addEventListener('click', function () { consultar(true); });
  campo.addEventListener('change', function () { consultar(false); });
  campo.addEventListener('blur', function () { consultar(false); });
})();

/*
 * Paleta de comandos.
 * Softsul Software
 *
 * Um lugar só para procurar e para fazer. Achar um evento seria abrir
 * Eventos e filtrar, e cadastrar um organizador seria abrir Organizadores e
 * caçar o botão: dois gestos de mouse para coisas que a pessoa já sabe o
 * nome. Aqui ela escreve o que quer e aperta Enter.
 *
 * O que existe na lista vem do servidor, porque quem decide é a permissão.
 * Oferecer "Novo evento" a quem receberia 403 ao clicar ensina que metade
 * dos comandos não funciona, e uma paleta em que não se confia não é usada.
 *
 * Os registros vêm da mesma /api/busca que a barra do topo usava: a paleta
 * substituiu aquele campo em vez de virar uma segunda busca com outro
 * resultado para a mesma palavra.
 */
(function () {
  'use strict';
  var caixa = document.getElementById('paleta');
  var gatilho = document.getElementById('abrir-paleta');
  if (!caixa || !window.fetch) { return; }

  var campo = document.getElementById('campo-paleta');
  var painel = document.getElementById('achados-paleta');
  var comandos = null, csrf = '', carregando = null;
  var itens = [], marcado = -1, relogio = null, emCurso = null, ultimoTermo = null;

  var noMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
  var atalho = document.getElementById('atalho-paleta');
  if (atalho) { atalho.textContent = noMac ? '⌘K' : 'Ctrl K'; }

  /* Sem acento e sem caixa: quem procura "noticias" quer achar "notícias",
     e obrigar o acento certo na busca é obrigar a pessoa a saber onde está o
     til antes de achar a tela. */
  function achatar(t) {
    return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  /* Os últimos escolhidos, para a paleta vazia não ser uma lista fria. Ficam
     no navegador desta pessoa: é preferência de uso, não dado do sistema. */
  function recentes() {
    try { return JSON.parse(localStorage.getItem('inventour.paleta.recentes') || '[]'); }
    catch (e) { return []; }
  }
  function guardarRecente(item) {
    if (!item || item.acao) { return; }
    try {
      var lista = recentes().filter(function (x) { return x.url !== item.url; });
      lista.unshift({ nome: item.nome, url: item.url, icone: item.icone, ajuda: item.ajuda || '' });
      localStorage.setItem('inventour.paleta.recentes', JSON.stringify(lista.slice(0, 4)));
    } catch (e) { /* navegador sem armazenamento: a paleta funciona igual */ }
  }

  function pegarComandos() {
    if (comandos) { return Promise.resolve(comandos); }
    if (carregando) { return carregando; }
    carregando = fetch('/api/comandos')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        comandos = (d && d.ok) ? d.comandos : [];
        csrf = (d && d.csrf) || '';
        return comandos;
      })
      .catch(function () { comandos = []; return comandos; });
    return carregando;
  }

  function grupo(titulo, icone, lista) {
    if (!lista.length) { return ''; }
    var html = '<div class="grupo-paleta"><svg aria-hidden="true" focusable="false">' +
               '<use href="#i-' + icone + '"/></svg>' + titulo + '</div>';
    lista.forEach(function (i) {
      itens.push(i);
      html += '<button type="button" class="item-paleta" role="option" data-i="' +
              (itens.length - 1) + '"><svg aria-hidden="true" focusable="false"><use href="#i-' +
              (i.icone || 'seta') + '"/></svg><b></b><span></span></button>';
    });
    return html;
  }

  /* Nome e detalhe entram como texto, nunca como HTML: vêm do banco, e um
     organizador chamado "<b>" não pode desenhar nada na tela de ninguém. */
  function escrever() {
    var linhas = painel.querySelectorAll('.item-paleta');
    Array.prototype.forEach.call(linhas, function (b) {
      var i = itens[parseInt(b.dataset.i, 10)];
      b.querySelector('b').textContent = i.nome;
      b.querySelector('span').textContent = i.onde || i.ajuda || '';
    });
  }

  function pintar() {
    var linhas = painel.querySelectorAll('.item-paleta');
    Array.prototype.forEach.call(linhas, function (b, i) {
      b.classList.toggle('marcado', i === marcado);
      b.setAttribute('aria-selected', i === marcado ? 'true' : 'false');
      if (i === marcado) { b.scrollIntoView({ block: 'nearest' }); }
    });
  }

  function combinam(termo) {
    var t = achatar(termo);
    return (comandos || []).filter(function (c) {
      return achatar(c.nome).indexOf(t) >= 0 || achatar(c.grupo).indexOf(t) >= 0
          || achatar(c.ajuda).indexOf(t) >= 0 || achatar(c.chaves).indexOf(t) >= 0;
    });
  }

  function porGrupo(lista) {
    var ordem = [], mapa = {};
    lista.forEach(function (c) {
      if (!mapa[c.grupo]) { mapa[c.grupo] = []; ordem.push(c.grupo); }
      mapa[c.grupo].push(c);
    });
    return ordem.map(function (g) { return [g, mapa[g][0].icone, mapa[g]]; });
  }

  function desenhar(termo, achados) {
    itens = [];
    var html = '';
    if (!termo) {
      html += grupo('Recentes', 'seta', recentes());
      porGrupo(comandos || []).forEach(function (g) {
        html += grupo(g[0], g[1], g[2]);
      });
    } else {
      var certos = combinam(termo);
      html += grupo('Comandos', 'seta', certos);
      // A busca do servidor também acha telas das Configurações: a que já
      // está nos comandos não aparece duas vezes.
      var urls = certos.map(function (c) { return c.url; });
      (achados || []).forEach(function (g) {
        html += grupo(g.titulo, g.icone, (g.itens || []).filter(function (i) { return urls.indexOf(i.url) < 0; }));
      });
      if (!html) {
        html = '<p class="nada-achado">Nada encontrado para "' +
               termo.replace(/[<>&]/g, '') + '".</p>';
      }
    }
    painel.innerHTML = html;
    escrever();
    marcado = itens.length ? 0 : -1;
    pintar();
  }

  function procurar() {
    var termo = campo.value.trim();
    if (termo === ultimoTermo) { return; }
    ultimoTermo = termo;
    if (termo.length < 2) { desenhar(termo, []); return; }
    desenhar(termo, []);          // comandos aparecem já, sem esperar a rede
    if (emCurso) { emCurso.abort(); }
    emCurso = new AbortController();
    fetch('/api/busca?q=' + encodeURIComponent(termo), { signal: emCurso.signal })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok && campo.value.trim() === termo) { desenhar(termo, d.grupos); }
      })
      .catch(function () { /* rede caiu: os comandos continuam na tela */ });
  }

  function fazer(item) {
    if (!item) { return; }
    guardarRecente(item);
    fechar();
    // Mesma tela com "#novo" não recarrega sozinha: manda abrir na mão.
    var aqui = location.pathname + location.search;
    if (item.url.indexOf(aqui + '#') === 0) {
      location.hash = item.url.split('#')[1];
      abrirNovo();
      return;
    }
    location.href = item.url;
  }

  /* Abre na hora, sem esperar a lista: quem aperta Ctrl K e já sai
     digitando perdia o que digitou quando a lista chegava e limpava o campo.
     Quando ela chega, redesenha com o que já estiver escrito. */
  function abrir() {
    campo.value = '';
    ultimoTermo = null;
    desenhar('', []);
    if (!caixa.open) { caixa.showModal(); }
    campo.focus();
    if (comandos) { return; }
    pegarComandos().then(function () {
      if (!caixa.open) { return; }
      ultimoTermo = null;
      procurar();
    });
  }
  function fechar() { if (caixa.open) { caixa.close(); } }

  if (gatilho) { gatilho.addEventListener('click', abrir); }

  campo.addEventListener('input', function () {
    clearTimeout(relogio);
    relogio = setTimeout(procurar, 180);
  });

  campo.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowDown') {
      ev.preventDefault(); marcado = Math.min(marcado + 1, itens.length - 1); pintar();
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault(); marcado = Math.max(marcado - 1, 0); pintar();
    } else if (ev.key === 'Enter') {
      ev.preventDefault(); fazer(itens[marcado]);
    }
  });

  painel.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('.item-paleta') : null;
    if (b) { fazer(itens[parseInt(b.dataset.i, 10)]); }
  });
  painel.addEventListener('mousemove', function (ev) {
    var b = ev.target.closest ? ev.target.closest('.item-paleta') : null;
    if (!b) { return; }
    var i = parseInt(b.dataset.i, 10);
    if (i !== marcado) { marcado = i; pintar(); }
  });

  // Clicar fora não fecha, como em todo modal da casa; Esc fecha, e é o que a
  // paleta promete no próprio rodapé.
  caixa.addEventListener('cancel', function (ev) { ev.preventDefault(); fechar(); });

  document.addEventListener('keydown', function (ev) {
    var k = (ev.key || '').toLowerCase();
    if (k === 'k' && (ev.metaKey || ev.ctrlKey)) {
      ev.preventDefault();
      if (caixa.open) { fechar(); } else { abrir(); }
      return;
    }
    // A barra continua abrindo, como já abria a busca do topo, menos quando a
    // pessoa está escrevendo: interromper um comentário no meio seria péssimo.
    if (k === '/' && !ev.ctrlKey && !ev.metaKey && !ev.altKey) {
      var onde = document.activeElement;
      if (onde && (onde.tagName === 'INPUT' || onde.tagName === 'TEXTAREA'
                   || onde.tagName === 'SELECT' || onde.isContentEditable)) { return; }
      ev.preventDefault();
      abrir();
    }
  });

  /* "#novo" na URL abre o cadastro da tela. É o que deixa a paleta oferecer
     "Nova conta a pagar" sem existir um segundo formulário de despesa: ela
     aponta para o mesmo botão que a tela já tem. */
  function abrirNovo() {
    if (location.hash !== '#novo') { return; }
    var botao = document.querySelector('[data-abre-novo]');
    if (botao) { botao.click(); }
  }
  abrirNovo();
})();

/*
 * Menu do celular.
 * Softsul Software
 *
 * Na tela pequena a barra lateral vira um botão só: doze módulos empilhados
 * como botões empurravam o conteúdo para fora da primeira tela, e a pessoa
 * abria o sistema olhando para uma lista de links em vez do trabalho dela.
 *
 * O nome da tela atual fica ao lado do logotipo, porque com o menu fechado ele
 * é a única coisa que diz onde se está.
 */
(function () {
  'use strict';
  var rail = document.getElementById('rail');
  var botao = document.getElementById('abrir-menu');
  if (!rail || !botao) { return; }

  var onde = document.getElementById('onde-estou');
  var ativo = rail.querySelector('.item.ativo');
  if (onde && ativo) { onde.textContent = ativo.dataset.nome || ''; }

  function abrir(sim) {
    rail.classList.toggle('aberto', sim);
    botao.setAttribute('aria-expanded', sim ? 'true' : 'false');
  }
  botao.addEventListener('click', function () {
    abrir(!rail.classList.contains('aberto'));
  });
  // Escolher um módulo fecha: a página vai trocar de qualquer forma, e deixar
  // o menu aberto faria a tela nova nascer coberta.
  rail.querySelectorAll('.item').forEach(function (a) {
    a.addEventListener('click', function () { abrir(false); });
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { abrir(false); }
  });
})();

/*
 * Data que filtra a lista, sem buscar no meio da digitação.
 * Softsul Software
 *
 * O campo de data do navegador avisa "mudou" a cada dígito do ano: ao digitar
 * 2026, a lista era pedida para o ano 0002, depois 0020 e 0202, e a tela
 * voltava para o período padrão antes de a pessoa terminar. Agora a busca só
 * sai com o ano inteiro, depois de uma pausa na digitação, ao sair do campo
 * ou ao apertar Enter. Escolher no calendário também espera a mesma pausa.
 */
(function () {
  'use strict';
  var campos = document.querySelectorAll('input[type=date][data-filtra-data]');
  if (!campos.length) { return; }

  function completa(valor) {
    if (valor === '') { return true; }   // apagar a data também é filtro
    var ano = parseInt(valor.slice(0, 4), 10);
    return ano >= 1990 && ano <= 2100;
  }

  campos.forEach(function (campo) {
    var enviado = campo.value;
    var relogio = null;

    function enviar() {
      clearTimeout(relogio);
      if (campo.value === enviado || !completa(campo.value)) { return; }
      enviado = campo.value;
      var form = campo.form;
      // requestSubmit passa pelo "submit" do formulário: nas listas é ele
      // que atualiza sem recarregar a página.
      if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    }

    campo.addEventListener('change', function () {
      clearTimeout(relogio);
      if (completa(campo.value)) { relogio = setTimeout(enviar, 900); }
    });
    campo.addEventListener('blur', enviar);
    campo.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); enviar(); }
    });
  });
})();

/**
 * Toda tabela de dados ordena pelo cabeçalho.
 * Softsul Software
 *
 * As listas paginadas ordenam no servidor (Lista::th), porque ordenar só a
 * página que está na tela mentiria sobre a lista inteira. As outras ordenam
 * aqui, na hora, sem ir ao servidor. O tipo da coluna é descoberto pelo que
 * está escrito nela: data (28/09/2026, 28/09 08:41), valor (R$ 1.234,56),
 * número ou texto, comparado como se lê em português. Vazio e "-" ficam
 * sempre no fim. A célula pode dizer o valor de ordem em data-ordem, quando o
 * que aparece não serve (um "há 2 dias", um ícone).
 *
 * Não ordena tabela com linha agrupada (colspan ou rowspan no corpo), com
 * mais de um corpo, ou marcada com data-sem-ordem: ali a ordem é parte do
 * que a tabela diz.
 */
(function () {
  'use strict';
  var anoAtual = new Date().getFullYear();
  var DATA = /^(\d{2})\/(\d{2})(?:\/(\d{4}))?(?:\s+(?:às\s+)?(\d{2}):(\d{2}))?/;
  var NUMERO = /^[-−]?\s*(?:R\$\s*)?[-−]?\d[\d.]*(?:,\d+)?\s*(?:%|KB|MB|GB|h|min)?$/i;
  var coletor = window.Intl && Intl.Collator ? new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' }) : null;

  function texto(td) {
    if (td.dataset.ordem !== undefined) { return td.dataset.ordem; }
    var t = (td.innerText || td.textContent || '').trim();
    return t.split('\n')[0].trim();
  }
  function vazio(t) { return t === '' || t === '-' || t === '-' || t === ','; }
  function comoData(t) {
    var m = DATA.exec(t);
    if (!m) { return null; }
    return Date.UTC(+(m[3] || anoAtual), +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0));
  }
  function comoNumero(t) {
    if (!NUMERO.test(t)) { return null; }
    var n = parseFloat(t.replace(/[^\d,−-]/g, '').replace('−', '-').replace(',', '.'));
    return isNaN(n) ? null : n;
  }

  function preparar(tabela) {
    if (tabela.dataset.ordemPronta || tabela.hasAttribute('data-sem-ordem')) { return; }
    var cab = tabela.tHead, corpo = tabela.tBodies;
    if (!cab || corpo.length !== 1 || !cab.rows.length) { return; }
    // Lista paginada: quem ordena é o servidor.
    if (cab.querySelector('th.ordenavel a')) { return; }
    var linhas = Array.prototype.slice.call(corpo[0].rows);
    if (linhas.length < 2) { return; }
    if (corpo[0].querySelector('td[colspan]:not([colspan="1"]), td[rowspan]:not([rowspan="1"]), th[colspan], th[rowspan]')) { return; }
    tabela.dataset.ordemPronta = '1';
    var ths = Array.prototype.slice.call(cab.rows[cab.rows.length - 1].cells);
    var estado = { coluna: -1, dir: 'asc' };
    // A ordem escolhida vale para a sessão do navegador (Luiz, 12/10/2026):
    // voltar à tela, filtrar ou trocar de mês mantém a ordem, em vez de
    // voltar à padrão. A chave é a tela e a posição da tabela nela.
    var todas = Array.prototype.slice.call(document.querySelectorAll('table.dados'));
    var chaveOrdem = 'inventour-ordem:' + location.pathname + ':' + (tabela.id || todas.indexOf(tabela));

    ths.forEach(function (th, i) {
      var rotulo = th.textContent.trim();
      if (!rotulo || th.classList.contains('acao') || th.classList.contains('sem-ordem') || th.querySelector('input, button, a')) { return; }
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ordenar';
      b.title = 'Ordenar por ' + rotulo.toLowerCase();
      while (th.firstChild) { b.appendChild(th.firstChild); }
      var setas = document.createElement('span');
      setas.className = 'setas-ordem';
      setas.setAttribute('aria-hidden', 'true');
      setas.innerHTML = '<i class="s-cima"></i><i class="s-baixo"></i>';
      b.appendChild(setas);
      th.appendChild(b);
      th.classList.add('ordenavel');
      b.addEventListener('click', function () { ordenar(i); });
    });

    // A ordem em que o servidor já mandou (data-ordem-inicial="coluna:dir")
    // aparece nas setas; e a escolhida antes, na sessão, é refeita.
    var salva = null;
    try { salva = JSON.parse(sessionStorage.getItem(chaveOrdem) || 'null'); } catch (e) {}
    if (salva && ths[salva.coluna] && ths[salva.coluna].classList.contains('ordenavel')) {
      ordenar(salva.coluna, salva.dir, true);
    } else if (tabela.dataset.ordemInicial) {
      var ini = tabela.dataset.ordemInicial.split(':');
      estado.coluna = +ini[0]; estado.dir = ini[1] === 'desc' ? 'desc' : 'asc';
      ths.forEach(function (th, j) {
        var s = th.querySelector('.setas-ordem');
        th.classList.toggle('ordenada', j === estado.coluna);
        if (s) { s.className = 'setas-ordem' + (j === estado.coluna ? ' ' + estado.dir : ''); }
      });
    }

    function ordenar(i, dirForcada, semGravar) {
      estado.dir = dirForcada || (estado.coluna === i && estado.dir === 'asc' ? 'desc' : 'asc');
      estado.coluna = i;
      if (!semGravar) {
        try { sessionStorage.setItem(chaveOrdem, JSON.stringify({ coluna: i, dir: estado.dir })); } catch (e) {}
      }
      var rows = Array.prototype.slice.call(corpo[0].rows);
      var valores = rows.map(function (tr, pos) {
        var td = tr.cells[i];
        return { tr: tr, pos: pos, t: td ? texto(td) : '' };
      });
      // O tipo é o que a maioria das células preenchidas aceita.
      var cheias = valores.filter(function (v) { return !vazio(v.t); });
      var datas = cheias.filter(function (v) { return comoData(v.t) !== null; }).length;
      var nums = cheias.filter(function (v) { return comoNumero(v.t) !== null; }).length;
      var tipo = cheias.length && datas >= cheias.length * 0.7 ? 'data'
               : (cheias.length && nums >= cheias.length * 0.7 ? 'numero' : 'texto');
      valores.forEach(function (v) {
        v.vazio = vazio(v.t);
        v.k = v.vazio ? null : (tipo === 'data' ? comoData(v.t) : (tipo === 'numero' ? comoNumero(v.t) : v.t));
        if (v.k === null) { v.vazio = true; }
      });
      var sinal = estado.dir === 'asc' ? 1 : -1;
      valores.sort(function (a, b) {
        if (a.vazio !== b.vazio) { return a.vazio ? 1 : -1; }
        if (a.vazio) { return a.pos - b.pos; }
        var c = tipo === 'texto'
          ? (coletor ? coletor.compare(a.k, b.k) : String(a.k).localeCompare(String(b.k), 'pt-BR'))
          : a.k - b.k;
        return c !== 0 ? c * sinal : a.pos - b.pos;
      });
      var frag = document.createDocumentFragment();
      valores.forEach(function (v) { frag.appendChild(v.tr); });
      corpo[0].appendChild(frag);
      ths.forEach(function (th, j) {
        var s = th.querySelector('.setas-ordem');
        th.classList.toggle('ordenada', j === i);
        if (s) { s.className = 'setas-ordem' + (j === i ? ' ' + estado.dir : ''); }
        th.setAttribute('aria-sort', j === i ? (estado.dir === 'asc' ? 'ascending' : 'descending') : 'none');
      });
    }
  }

  // Exposta para as tabelas que chegam depois, por fetch.
  window.erpOrdenarTabelas = function (raiz) {
    (raiz || document).querySelectorAll('table.dados').forEach(preparar);
  };
  window.erpOrdenarTabelas(document);
  // A tabela que chega depois (modal que busca no servidor, lista viva)
  // também ordena: toda lista do ERP ordena, não só a que já veio na página.
  if (window.MutationObserver) {
    new MutationObserver(function (mudancas) {
      mudancas.forEach(function (m) {
        Array.prototype.forEach.call(m.addedNodes, function (n) {
          if (n.nodeType !== 1) { return; }
          if (n.matches && n.matches('table.dados')) { preparar(n); } else if (n.querySelectorAll) { window.erpOrdenarTabelas(n); }
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
  }
})();

/*
 * Texto que explica, e que no celular sai (a regra está no fim do tema.css).
 * Softsul Software
 *
 * Marca o .ajuda longo que não traz nada para fazer nem dado: sem link,
 * botão, campo, e sem número em negrito ("Hoje: 412 contatos" é dado, não
 * explicação). Vale também para o que chega depois, em modal ou lista viva.
 */
(function () {
  'use strict';
  function marcar(raiz) {
    if (!raiz || !raiz.querySelectorAll) { return; }
    var lista = Array.prototype.slice.call(raiz.querySelectorAll('.ajuda'));
    if (raiz.matches && raiz.matches('.ajuda')) { lista.push(raiz); }
    lista.forEach(function (el) {
      if (el.dataset.explicaVisto) { return; }
      el.dataset.explicaVisto = '1';
      var texto = (el.textContent || '').replace(/\s+/g, ' ').trim();
      if (texto.length < 70 || el.querySelector('a, button, input, select, textarea, .pilula')) { return; }
      var temNumero = Array.prototype.some.call(el.querySelectorAll('b, strong, .dado'), function (b) {
        return /\d/.test(b.textContent || '');
      });
      if (!temNumero) { el.classList.add('explica'); }
    });
  }
  marcar(document);
  if (window.MutationObserver) {
    new MutationObserver(function (mudancas) {
      mudancas.forEach(function (m) {
        Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) { marcar(n); } });
      });
    }).observe(document.body, { childList: true, subtree: true });
  }
})();

/*
 * Recolher o menu da esquerda, para sobrar tela.
 * Softsul Software
 *
 * A escolha fica no navegador da pessoa, num cookie que o servidor lê para
 * já mandar a página recolhida: no Orbit isso é um script no <head>, que a
 * CSP do painel não aceita. Cada item já traz no title a explicação do
 * que faz (app/layout.php); recolhido, o balão põe o nome do módulo em
 * negrito em cima dela.
 */
(function () {
  'use strict';
  var botao = document.getElementById('recolher-menu');
  var raiz = document.documentElement;
  if (!botao) { return; }
  function aplicar(recolhido) {
    raiz.classList.toggle('menu-recolhido', recolhido);
    botao.setAttribute('aria-pressed', recolhido ? 'true' : 'false');
    botao.title = recolhido ? 'Abrir o menu: volta a mostrar o nome dos módulos' : 'Recolher o menu: fica só com os ícones, para sobrar tela';
    botao.setAttribute('aria-label', recolhido ? 'Abrir o menu' : 'Recolher o menu');
  }
  aplicar(raiz.classList.contains('menu-recolhido'));
  botao.addEventListener('click', function () {
    var recolhido = !raiz.classList.contains('menu-recolhido');
    aplicar(recolhido);
    document.cookie = 'inventour_menu=' + (recolhido ? 'recolhido' : 'aberto')
      + '; path=/; max-age=31536000; SameSite=Lax; Secure';
  });
})();

/* Mês e outros filtros que se enviam ao mudar, sem onchange escrito no HTML. */
(function () {
  'use strict';
  document.querySelectorAll('[data-enviar-ao-mudar]').forEach(function (campo) {
    campo.addEventListener('change', function () { if (campo.form) { campo.form.submit(); } });
  });
})();

/*
 * Tema escuro, pela chave do menu do usuário.
 * Softsul Software
 *
 * Troca na hora, sem recarregar, e guarda na conta (api/tema) e no navegador
 * (para a tela de login, que vem antes de saber quem é).
 */
(function () {
  'use strict';
  var chave = document.getElementById('menu-tema');
  if (!chave) { return; }
  chave.addEventListener('click', function (ev) {
    ev.stopPropagation();
    var escuro = chave.getAttribute('aria-checked') !== 'true';
    var raiz = document.documentElement;
    if (escuro) { raiz.setAttribute('data-tema', 'escuro'); } else { raiz.removeAttribute('data-tema'); }
    chave.setAttribute('aria-checked', escuro ? 'true' : 'false');
    var estado = document.getElementById('menu-tema-estado');
    if (estado) { estado.textContent = escuro ? 'Ativado · alternar para claro' : 'Desativado · alternar para escuro'; }
    var cor = document.querySelector('meta[name="theme-color"]');
    if (cor) { cor.setAttribute('content', escuro ? '#141414' : '#FFFFFF'); }
    // A barra de baixo do Android segue o manifesto do aplicativo instalado.
    var manifesto = document.querySelector('link[rel="manifest"]');
    if (manifesto) { manifesto.setAttribute('href', escuro ? '/manifest-escuro.webmanifest' : '/manifest.webmanifest'); }
    var esquema = document.querySelector('meta[name="color-scheme"]');
    if (esquema) { esquema.setAttribute('content', escuro ? 'dark' : 'light'); }
    document.cookie = 'inventour_tema=' + (escuro ? 'escuro' : 'claro') + '; path=/; max-age=31536000; SameSite=Lax; Secure';
    var f = new FormData();
    f.append('csrf', chave.dataset.csrf);
    f.append('tema', escuro ? 'escuro' : 'claro');
    fetch('/api/tema', { method: 'POST', body: f, credentials: 'same-origin' }).catch(function () {});
  });
})();

/*
 * Ficha em abas (.abas-chamado com [data-aba], painéis .painel-aba).
 * Softsul Software
 *
 * A aba vem do endereço (#dados, #historico...), que é para onde os avisos
 * e os links já apontam; sem nada, abre a primeira. Trocar de aba atualiza
 * o endereço sem rolar a tela.
 */
(function () {
  'use strict';
  // A primeira aba é a de quando o endereço não diz nenhuma.
  var nav = document.querySelector('.abas-chamado');
  if (!nav) { return; }
  var botoes = nav.querySelectorAll('[data-aba]');
  var paineis = document.querySelectorAll('.painel-aba[data-painel]');
  var padrao = botoes.length ? botoes[0].dataset.aba : 'dados';
  function abrir(aba, gravar) {
    var existe = Array.prototype.some.call(botoes, function (b) { return b.dataset.aba === aba; });
    if (!existe) { aba = padrao; }
    botoes.forEach(function (b) { b.setAttribute('aria-selected', b.dataset.aba === aba ? 'true' : 'false'); });
    paineis.forEach(function (p) { p.hidden = p.dataset.painel !== aba; });
    if (gravar) {
      try {
        // O ?aba= da paginação some ao trocar de aba: quem manda agora é o #.
        var busca = new URLSearchParams(location.search);
        busca.delete('aba');
        var resto = busca.toString() ? '?' + busca.toString() : '';
        history.replaceState(null, '', location.pathname + resto + (aba === padrao ? '' : '#' + aba));
      } catch (e) {}
    }
  }
  botoes.forEach(function (b) {
    b.addEventListener('click', function () { abrir(b.dataset.aba, true); });
  });
  // A aba vem do endereço: #aba, ou ?aba= (o que a paginação de uma lista dentro da aba leva).
  var inicial = (location.hash || '').replace('#', '');
  if (!inicial) { try { inicial = new URLSearchParams(location.search).get('aba') || ''; } catch (e) {} }
  abrir(inicial || padrao, false);
  window.addEventListener('hashchange', function () {
    abrir((location.hash || '').replace('#', '') || padrao, false);
  });
  // Campo obrigatório numa aba escondida: abre a aba dele, senão o navegador
  // recusa o envio sem mostrar o porquê.
  document.addEventListener('invalid', function (ev) {
    var painel = ev.target.closest && ev.target.closest('.painel-aba[data-painel]');
    if (painel && painel.hidden) { abrir(painel.dataset.painel, true); }
  }, true);
})();

/*
 * Digital do aparelho (passkey): entrar, convidar e cadastrar.
 * Softsul Software
 */
(function () {
  'use strict';
  /* ------------------------------------------------- passkey (WebAuthn)
     O servidor manda os campos binários como "=?BINARY?B?<base64>?=", que
     é o contrato da biblioteca lbuchs/WebAuthn. */
  function temWebauthn() {
    return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create);
  }
  function paraBuffer(valor) {
    var b64 = valor.replace(/^=\?BINARY\?B\?/, '').replace(/\?=$/, '');
    var bruto = atob(b64.replace(/-/g, '+').replace(/_/g, '/'));
    var bytes = new Uint8Array(bruto.length);
    for (var i = 0; i < bruto.length; i++) { bytes[i] = bruto.charCodeAt(i); }
    return bytes.buffer;
  }
  function paraBase64(buffer) {
    var bytes = new Uint8Array(buffer);
    var texto = '';
    for (var i = 0; i < bytes.byteLength; i++) { texto += String.fromCharCode(bytes[i]); }
    return btoa(texto);
  }
  function preparar(obj) {
    Object.keys(obj).forEach(function (chave) {
      var v = obj[chave];
      if (typeof v === 'string' && /^=\?BINARY\?B\?/.test(v)) { obj[chave] = paraBuffer(v); }
      else if (Array.isArray(v)) { v.forEach(function (item) { if (item && typeof item === 'object') { preparar(item); } }); }
      else if (v && typeof v === 'object') { preparar(v); }
    });
    return obj;
  }
  function pedir(url, corpo) {
    return fetch(url, corpo ? { method: 'POST', body: corpo, credentials: 'same-origin' } : { credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (r) {
        if (!r.ok || !r.d.ok) { throw new Error(r.d.recado || 'Não deu certo.'); }
        return r.d;
      });
  }

  /* App InvenTour Gestão (mobile/gestao): como no FrameFlow, quem já entrou
     ou se cadastrou pela digital neste aparelho tem a digital pedida direto ao
     abrir o app, sem tocar no botão. Depois de "Sair" não pede: a marca da
     sessão inventour.saiu segura uma vez. */
  var noApp = /\bInvenTourGestao\//.test(navigator.userAgent);
  function marcarDigitalNoApp() {
    if (!noApp) { return; }
    try { localStorage.setItem('inventour.app.digital', '1'); } catch (e) {}
  }
  var formSair = document.getElementById('form-sair');
  if (formSair) {
    formSair.addEventListener('submit', function () { try { sessionStorage.setItem('inventour.saiu', '1'); } catch (e) {} });
  }

  var entrar = document.getElementById('entrar-biometria');
  var areaEntrar = document.getElementById('area-biometria');
  var erroBio = document.getElementById('erro-biometria');
  if (entrar && areaEntrar && temWebauthn()) {
    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
      .then(function (tem) {
        if (!tem) { return; }
        areaEntrar.hidden = false;
        var direto = false;
        try { direto = noApp && localStorage.getItem('inventour.app.digital') === '1' && sessionStorage.getItem('inventour.saiu') !== '1'; } catch (e) {}
        try { sessionStorage.removeItem('inventour.saiu'); } catch (e) {}
        if (direto) { setTimeout(function () { entrar.click(); }, 150); }
      })
      .catch(function () {});
    entrar.addEventListener('click', function () {
      entrar.disabled = true;
      if (erroBio) { erroBio.hidden = true; }
      pedir('/api/passkey-entrar?passo=iniciar')
        .then(function (args) { return navigator.credentials.get({ publicKey: preparar(args.publicKey) }); })
        .then(function (cred) {
          var dados = new FormData();
          dados.append('id', paraBase64(cred.rawId));
          dados.append('clientDataJSON', paraBase64(cred.response.clientDataJSON));
          dados.append('authenticatorData', paraBase64(cred.response.authenticatorData));
          dados.append('signature', paraBase64(cred.response.signature));
          // O destino da tela de login: volta para onde a pessoa estava quando a sessão venceu.
          var campoDestino = document.querySelector('form input[name="destino"]');
          if (campoDestino && campoDestino.value) { dados.append('destino', campoDestino.value); }
          return pedir('/api/passkey-entrar?passo=finalizar', dados);
        })
        .then(function (r) {
          try { sessionStorage.setItem('inventour.entrou.digital', '1'); } catch (e) {}
          marcarDigitalNoApp();
          location.href = r.para || '/painel';
        })
        .catch(function (e) {
          entrar.disabled = false;
          // Fechar a janela do leitor não é erro.
          if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) { return; }
          if (erroBio) { erroBio.hidden = false; erroBio.textContent = e.message || 'Não consegui entrar pela digital.'; }
        });
    });
  }

  /* ------------------------------------------- este aparelho e o convite
     O servidor sabe quantos aparelhos a conta tem, mas não QUAL é este. A
     marca fica no navegador, junto da assinatura da lista: apagada a chave
     pelo computador, a assinatura muda e o aparelho volta a ser convidado. */
  var convite = document.getElementById('modal-biometria');
  function assinaturaAtual() { return (convite && convite.dataset.assinatura) || ''; }
  function esteAparelhoJaCadastrado() {
    try {
      var marca = localStorage.getItem('inventour.biometria.aparelho') || '';
      return marca !== '' && marca === assinaturaAtual();
    } catch (e) { return false; }
  }
  function marcarAparelho(assinatura) {
    try { localStorage.setItem('inventour.biometria.aparelho', assinatura || assinaturaAtual()); } catch (e) {}
    marcarDigitalNoApp();
  }
  /* Uma vez por sessão do navegador, e 30 dias depois de um "Agora não":
     convite que reaparece a cada tela vira obstáculo. */
  function chaveDaSessao() { return 'inventour.biometria.convidado.' + assinaturaAtual(); }
  function jaConvidou() { try { return sessionStorage.getItem(chaveDaSessao()) === '1'; } catch (e) { return false; } }
  function marcarConvite() { try { sessionStorage.setItem(chaveDaSessao(), '1'); } catch (e) {} }
  function adiado() {
    try {
      var p = (localStorage.getItem('inventour.biometria.adiada') || '').split('|');
      return p.length === 2 && p[0] === assinaturaAtual() && Date.now() - parseInt(p[1], 10) < 30 * 86400000;
    } catch (e) { return false; }
  }

  /* O nome do aparelho, como no Orbit e no Frameflow: o modelo real quando o
     navegador conta (Android com Chrome), senão um genérico honesto. O iPad
     se apresenta como Mac desde o iPadOS 13, e só o toque na tela separa os
     dois. O nome dado nas configurações do aparelho nenhum site lê. */
  function nomeDoAparelho() {
    var ua = navigator.userAgent;
    var toque = typeof navigator.maxTouchPoints === 'number' && navigator.maxTouchPoints > 1;
    function generico() {
      if (/android/i.test(ua)) { return /mobile/i.test(ua) ? 'Celular Android' : 'Tablet Android'; }
      if (/iphone|ipod/i.test(ua)) { return 'iPhone'; }
      if (/ipad/i.test(ua)) { return 'iPad'; }
      if (/macintosh/i.test(ua)) { return toque ? 'iPad' : 'Mac'; }
      if (/windows/i.test(ua)) { return toque ? 'Tablet Windows' : 'Computador Windows'; }
      return toque ? 'Tablet' : 'Este aparelho';
    }
    if (/android/i.test(ua) && navigator.userAgentData && navigator.userAgentData.getHighEntropyValues) {
      return navigator.userAgentData.getHighEntropyValues(['model'])
        .then(function (d) { return (d && d.model) ? d.model : generico(); })
        .catch(function () { return generico(); });
    }
    return Promise.resolve(generico());
  }

  var adiar = document.getElementById('adiar-biometria');
  if (adiar && convite) {
    adiar.addEventListener('click', function () {
      try { localStorage.setItem('inventour.biometria.adiada', assinaturaAtual() + '|' + Date.now()); } catch (e) {}
      convite.close();
    });
  }

  var botoesCadastro = document.querySelectorAll('[data-cadastrar-aparelho]');
  if (botoesCadastro.length && temWebauthn()) {
    var areaCad = document.getElementById('area-cadastrar-aparelho');
    var campoPerfil = document.getElementById('apelido-aparelho');
    if (campoPerfil && campoPerfil.value === '') {
      nomeDoAparelho().then(function (nome) { campoPerfil.value = nome; });
    }
    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
      .then(function (tem) {
        if (areaCad) {
          areaCad.hidden = false;
          var semLeitor = document.getElementById('sem-leitor');
          if (!tem && semLeitor) { semLeitor.hidden = false; }
        }
        // O convite só onde a digital existe, e nunca em cima da tela de
        // Meus dados, que já tem o mesmo botão.
        if (convite && tem && !areaCad && !esteAparelhoJaCadastrado() && !jaConvidou() && !adiado()) {
          marcarConvite();
          nomeDoAparelho().then(function (nome) {
            var campo = document.getElementById('apelido-aparelho-modal');
            if (campo && nome) { campo.value = nome; }
          });
          convite.showModal();
        }
      })
      .catch(function () {});

    botoesCadastro.forEach(function (botao) {
      botao.addEventListener('click', function () {
        var campo = convite && convite.open ? document.getElementById('apelido-aparelho-modal') : campoPerfil;
        var apelido = (campo && campo.value.trim()) || 'Este aparelho';
        botao.disabled = true;
        pedir('/api/passkey-registrar?passo=iniciar')
          .then(function (args) { return navigator.credentials.create({ publicKey: preparar(args.publicKey) }); })
          .then(function (cred) {
            var dados = new FormData();
            dados.append('csrf', botao.getAttribute('data-csrf') || '');
            dados.append('apelido', apelido);
            dados.append('clientDataJSON', paraBase64(cred.response.clientDataJSON));
            dados.append('attestationObject', paraBase64(cred.response.attestationObject));
            return pedir('/api/passkey-registrar?passo=finalizar', dados);
          })
          .then(function (r) {
            marcarAparelho(r && r.assinatura);
            if (convite && convite.open) { convite.close(); }
            if (areaCad) { location.hash = 'aparelhos'; location.reload(); }
          })
          .catch(function (e) {
            botao.disabled = false;
            if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) { return; }
            // O navegador recusa cadastrar duas vezes o mesmo aparelho: aqui
            // isso não é erro, é a resposta "já está".
            if (e && e.name === 'InvalidStateError') {
              marcarAparelho();
              if (convite && convite.open) { convite.close(); }
              return;
            }
            var aviso = document.createElement('p');
            aviso.className = 'aviso ruim';
            aviso.textContent = e.message || 'Não consegui cadastrar este aparelho.';
            botao.insertAdjacentElement('afterend', aviso);
          });
      });
    });
  }

  /* Entrou pela digital neste aparelho: ele já está cadastrado. */
  if (convite && sessionStorage && (function () { try { return sessionStorage.getItem('inventour.entrou.digital') === '1'; } catch (e) { return false; } })()) {
    marcarAparelho();
    try { sessionStorage.removeItem('inventour.entrou.digital'); } catch (e) {}
  }
})();

/*
 * Código de seis dígitos: envia sozinho ao completar. No Orbit é um script na
 * própria tela; aqui vale para qualquer campo com data-enviar-com-6.
 * Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-enviar-com-6]').forEach(function (campo) {
    campo.addEventListener('input', function () {
      campo.value = campo.value.replace(/\D/g, '').slice(0, 6);
      if (campo.value.length === 6 && campo.form) { campo.form.submit(); }
    });
  });
})();

/*
 * Copiar para a área de transferência: o link de convite, por exemplo.
 * Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-copiar]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var campo = document.getElementById(botao.getAttribute('data-copiar'));
      if (!campo) { return; }
      campo.select();
      var feito = function () { if (window.erpAvisar) { window.erpAvisar('Copiado.', 'bom'); } };
      if (navigator.clipboard) { navigator.clipboard.writeText(campo.value).then(feito, function () {}); }
      else { document.execCommand('copy'); feito(); }
    });
  });
})();

/*
 * Que link é de ficha. Além do data-ficha explícito, todo link para a tela
 * de um cadastro (/evento/12-..., /organizador/3-..., /local/...) abre em
 * modal, para o link novo que alguém esquecer de marcar não voltar a abrir
 * a tela inteira. Ctrl, Shift, o botão do meio, target e download seguem o
 * navegador.
 * Softsul Software
 */
(function () {
  'use strict';
  var FICHAS = /^\/(configuracoes\/)?(evento|organizador|local|noticia|conflito|destino|impacto|ponto-qr|prestador|reuniao|termo|usuario)(\/\d+[^\/]*)?$/;
  window.inventourLinkDeFicha = function (ev) {
    var el = ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!el || ev.defaultPrevented || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) { return null; }
    if (el.hasAttribute('data-ficha')) { return el; }
    if ((el.target && el.target !== '_self') || el.hasAttribute('download') || el.hasAttribute('data-sem-modal')) { return null; }
    // Âncora e link para a própria tela (abas, ?parte=) ficam onde estão.
    if ((el.getAttribute('href') || '').charAt(0) === '#' || el.pathname === location.pathname) { return null; }
    if (el.origin !== location.origin || !FICHAS.test(el.pathname)) { return null; }
    return el;
  };
  window.inventourTituloDaFicha = function (el) {
    return el.getAttribute('data-ficha') || el.getAttribute('data-titulo') || el.getAttribute('title') || el.textContent.replace(/\s+/g, ' ').trim() || 'Cadastro';
  };
})();

/*
 * Ficha em modal: toda inclusão e alteração de dados abre por cima da lista.
 * Softsul Software
 *
 * Link ou botão com data-ficha="Título" abre o próprio endereço, em modo
 * popup, no iframe do modal (como a ficha de contato do Orbit). Formulário
 * com data-ficha manda o envio para dentro do modal. Ctrl e o botão do meio
 * continuam abrindo a tela inteira, em aba nova.
 *
 * Quando a ficha salva, ela devolve uma página mínima com a marca
 * inventour-ficha-salva; este mesmo arquivo, carregado lá dentro, avisa a
 * lista, que fecha o modal e se recarrega com o aviso do que foi feito.
 */
(function () {
  'use strict';
  if (document.querySelector('meta[name="inventour-ficha-salva"]')) {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ inventourFichaSalva: true }, location.origin);
    } else {
      location.href = '/painel';
    }
    return;
  }
  var modal = document.getElementById('modal-ficha');
  var quadro = document.getElementById('quadro-ficha');
  var titulo = document.getElementById('titulo-ficha');
  if (!modal || !quadro) { return; }

  // O popup=1 entra antes do #, senão vira parte da âncora.
  function comPopup(url) {
    var partes = url.split('#');
    return partes[0] + (partes[0].indexOf('?') === -1 ? '?' : '&') + 'popup=1' + (partes[1] ? '#' + partes[1] : '');
  }
  var historicoAntes = null;
  function abrir(nome) {
    if (titulo) { titulo.textContent = nome || 'Cadastro'; }
    if (!modal.open) { historicoAntes = history.length; modal.showModal(); }
  }

  document.addEventListener('click', function (ev) {
    var el = window.inventourLinkDeFicha(ev);
    if (!el) { return; }
    ev.preventDefault();
    abrirEndereco(el.getAttribute('href'), window.inventourTituloDaFicha(el));
  });
  function abrirEndereco(href, nome) {
    if (!modal.open) { historicoAntes = history.length; }
    // O item do inventário é a ficha mais comprida: abre mais largo.
    modal.classList.toggle('largo-item', /^\/item(\/|\?|$)/.test(href || ''));
    quadro.src = comPopup(href);
    abrir(nome);
  }

  // Formulário com data-ficha (a ação da linha que gera o link de senha, por
  // exemplo): o envio vai para dentro do modal, e a resposta aparece nele.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.matches || !form.matches('form[data-ficha]')) { return; }
    form.target = 'quadro-ficha';
    if (!form.querySelector('input[name="popup"]')) {
      var p = document.createElement('input');
      p.type = 'hidden'; p.name = 'popup'; p.value = '1';
      form.appendChild(p);
    }
    abrir(form.getAttribute('data-ficha'));
  });

  modal.addEventListener('cancel', function (ev) { ev.preventDefault(); modal.close(); });
  // O iframe é trocado por um novo ao fechar: um formulário meio preenchido
  // reaberto depois é pior do que um em branco, e o histórico do que foi
  // aberto nele sai junto (o voltar do celular segue só as telas do menu).
  modal.addEventListener('close', function () { quadro = window.inventourIframeNovo(quadro, historicoAntes); historicoAntes = null; });

  window.addEventListener('message', function (ev) {
    if (ev.origin !== location.origin || !ev.data) { return; }
    // A ficha aberta no modal pediu outra (o evento na ficha do
    // organizador, por exemplo): troca o conteúdo do mesmo modal.
    if (ev.data.inventourAbrirFicha) {
      abrirEndereco(String(ev.data.inventourAbrirFicha), String(ev.data.titulo || ''));
      return;
    }
    if (!ev.data.inventourFichaSalva) { return; }
    // Fechar desfaz o histórico do iframe (history.go, que é assíncrono), e
    // um reload pedido junto era cancelado por essa volta: a lista ficava
    // velha até o F5. Recarrega só depois que a volta terminou.
    var voltou = typeof historicoAntes === 'number' && history.length > historicoAntes;
    modal.close();
    if (!voltou) { location.reload(); return; }
    var feito = false;
    function recarregar() { if (!feito) { feito = true; location.reload(); } }
    window.addEventListener('popstate', function () { setTimeout(recarregar, 0); }, { once: true });
    setTimeout(recarregar, 400);
  });
})();

/*
 * Dentro do modal de ficha, todo formulário continua em modo popup: a
 * resposta do servidor também volta sem menu nem barra.
 * Softsul Software
 */
(function () {
  'use strict';
  if (!document.body || !document.body.classList.contains('em-popup')) { return; }
  // Link de outra ficha, dentro do modal: quem abre é a tela de baixo, no
  // mesmo modal, em vez de a tela inteira carregar dentro do quadro.
  if (window.parent && window.parent !== window) {
    document.addEventListener('click', function (ev) {
      var el = window.inventourLinkDeFicha(ev);
      if (!el) { return; }
      ev.preventDefault();
      window.parent.postMessage({ inventourAbrirFicha: el.getAttribute('href'), titulo: window.inventourTituloDaFicha(el) }, location.origin);
    });
  }
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.target === '_top' || form.querySelector('input[name="popup"]')) { return; }
    var p = document.createElement('input');
    p.type = 'hidden'; p.name = 'popup'; p.value = '1';
    form.appendChild(p);
  }, true);
  // Salvar pode demorar (a foto do WhatsApp, o e-mail do convite): o botão
  // mostra que está salvando, senão parece que nada aconteceu. Desliga só
  // depois do envio sair, para o nome e o valor do botão irem junto.
  document.addEventListener('submit', function (ev) {
    var b = ev.submitter;
    if (ev.defaultPrevented || !b || b.matches('[formtarget], [data-sem-salvando]')) { return; }
    setTimeout(function () {
      b.disabled = true;
      b.setAttribute('aria-busy', 'true');
      var rotulo = b.lastChild && b.lastChild.nodeType === 3 ? b.lastChild : null;
      if (rotulo && /salvar|cadastrar/i.test(rotulo.textContent)) { rotulo.textContent = 'Salvando…'; }
    }, 0);
  });
})();

/*
 * Combobox de múltipla escolha.
 * Softsul Software
 *
 * Abre uma lista de caixas de marcar por baixo do campo, e o campo mostra o
 * que está escolhido em fichas. As caixas são as de verdade e vão no
 * formulário: o JavaScript só abre, fecha e desenha as fichas. Fecha com
 * clique fora e com Esc, como os menus suspensos da casa.
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-multi-escolha]').forEach(function (caixa) {
    var alvo = caixa.querySelector('.multi-alvo');
    var opcoes = caixa.querySelector('.multi-opcoes');
    var fichas = caixa.querySelector('.multi-fichas');
    if (!alvo || !opcoes || !fichas) { return; }
    function desenhar() {
      fichas.textContent = '';
      var marcadas = caixa.querySelectorAll('input[type="checkbox"]:checked');
      if (!marcadas.length) {
        var vazio = document.createElement('span');
        vazio.className = 'multi-vazio';
        vazio.textContent = fichas.getAttribute('data-vazio') || 'Escolha';
        fichas.appendChild(vazio);
        return;
      }
      // No filtro de lista a caixa é de uma linha: o nome da única
      // escolhida ou "3 atividades", em vez de uma ficha por escolha.
      var plural = fichas.getAttribute('data-plural');
      if (plural) {
        var r = document.createElement('span');
        r.className = 'multi-resumo';
        r.textContent = marcadas.length === 1 ? (marcadas[0].getAttribute('data-nome') || marcadas[0].value) : marcadas.length + ' ' + plural;
        r.title = Array.prototype.map.call(marcadas, function (c) { return c.getAttribute('data-nome') || c.value; }).join(', ');
        fichas.appendChild(r);
        return;
      }
      marcadas.forEach(function (c) {
        var f = document.createElement('span');
        f.className = 'etiq multi-ficha';
        f.textContent = c.getAttribute('data-nome') || c.value;
        fichas.appendChild(f);
      });
    }
    var mudou = false;
    function abrir(sim) {
      if (!sim && mudou && caixa.hasAttribute('data-enviar-ao-fechar') && !opcoes.hidden) {
        var form = caixa.closest('form');
        if (form) { mudou = false; form.submit(); }
      }
      opcoes.hidden = !sim;
      alvo.setAttribute('aria-expanded', sim ? 'true' : 'false');
      caixa.classList.toggle('aberta', sim);
    }
    alvo.addEventListener('click', function (ev) {
      ev.stopPropagation();
      abrir(opcoes.hidden);
      var b = opcoes.querySelector('.multi-busca input');
      if (b && !opcoes.hidden) { b.focus(); }
    });
    // Com fonte: o campo no topo busca no servidor e põe o que achar abaixo
    // das já marcadas; o que não foi marcado sai na próxima busca.
    var fonte = caixa.getAttribute('data-fonte');
    var campoBusca = opcoes.querySelector('.multi-busca input');
    if (fonte && campoBusca) {
      var relogioBusca = null, emCursoBusca = null;
      var nada = opcoes.querySelector('.multi-nada');
      var nomeCampo = caixa.getAttribute('data-campo') + '[]';
      var procurar = function () {
        var termo = campoBusca.value.trim();
        opcoes.querySelectorAll('.multi-opcao').forEach(function (l) {
          if (!l.querySelector('input').checked) { l.remove(); }
        });
        nada.hidden = true;
        if (termo.length < 2) { return; }
        if (emCursoBusca) { emCursoBusca.abort(); }
        emCursoBusca = new AbortController();
        fetch(fonte + (fonte.indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(termo), { signal: emCursoBusca.signal })
          .then(function (r) { return r.json(); })
          .then(function (itens) {
            var ja = Array.prototype.map.call(opcoes.querySelectorAll('.multi-opcao input'), function (c) { return c.value; });
            var novos = (itens || []).filter(function (it) { return ja.indexOf(String(it.id)) < 0; });
            nada.hidden = novos.length > 0;
            novos.forEach(function (it) {
              var l = document.createElement('label');
              l.className = 'check multi-opcao';
              var c = document.createElement('input');
              c.type = 'checkbox';
              c.name = nomeCampo;
              c.value = String(it.id);
              c.setAttribute('data-nome', it.texto || it.id);
              var i = document.createElement('i');
              var sp = document.createElement('span');
              var b = document.createElement('b');
              b.textContent = it.texto || it.id;
              sp.appendChild(b);
              l.appendChild(c); l.appendChild(i); l.appendChild(sp);
              opcoes.appendChild(l);
            });
          })
          .catch(function () { /* busca superada ou sem rede: fica o que havia */ });
      };
      campoBusca.addEventListener('input', function () { clearTimeout(relogioBusca); relogioBusca = setTimeout(procurar, 220); });
      // O campo de busca não é filtro: não vai no formulário nem dispara a lista.
      campoBusca.addEventListener('change', function (ev) { ev.stopPropagation(); });
      campoBusca.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
    } else if (campoBusca) {
      // Sem fonte: as opções já estão aqui, e o campo só esconde as que não
      // batem (sem acento e sem caixa). As marcadas ficam sempre à vista.
      var semAcento = function (t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
      var nadaLocal = opcoes.querySelector('.multi-nada');
      campoBusca.addEventListener('input', function () {
        var termo = semAcento(campoBusca.value.trim());
        var algum = false;
        opcoes.querySelectorAll('.multi-opcao').forEach(function (l) {
          var c = l.querySelector('input');
          var bate = !termo || c.checked || semAcento(c.getAttribute('data-nome') || '').indexOf(termo) >= 0;
          l.hidden = !bate;
          algum = algum || bate;
        });
        nadaLocal.hidden = algum;
      });
      campoBusca.addEventListener('change', function (ev) { ev.stopPropagation(); });
      campoBusca.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
    }
    opcoes.addEventListener('change', function () { mudou = true; desenhar(); });
    // Quem marca por fora (o mapa) avisa por aqui para o resumo mudar.
    caixa.addEventListener('multi-redesenhar', desenhar);
    document.addEventListener('click', function (ev) { if (!caixa.contains(ev.target)) { abrir(false); } });
    caixa.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !opcoes.hidden) { ev.stopPropagation(); abrir(false); alvo.focus(); } });
    desenhar();
  });
})();

/*
 * CPF e CNPJ em todo o sistema: máscara enquanto digita e ao sair do campo,
 * e validação pelos dígitos verificadores ao sair. Vale para input.cpf,
 * input.cnpj e #cnpj. No banco fica só o número; o servidor valida de novo
 * ao salvar (cpfValido, Cnpj::valido).
 * Softsul Software
 *
 * Documento inválido marca a entrada em vermelho, diz o motivo na ajuda do
 * campo e trava o envio do formulário (setCustomValidity) até ser corrigido
 * ou apagado. Vazio não é erro aqui: obrigatório é o "required" quem diz.
 */
(function () {
  'use strict';
  function mascaraCpf(v) {
    var d = v.replace(/\D/g, '').slice(0, 11);
    if (d.length > 9) { return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9); }
    if (d.length > 6) { return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6); }
    if (d.length > 3) { return d.slice(0, 3) + '.' + d.slice(3); }
    return d;
  }
  // CNPJ alfanumérico (Receita, desde julho de 2026): letras de A a Z nas
  // 12 primeiras posições; os 2 verificadores são sempre números.
  function limparCnpj(v) {
    var c = v.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);
    return c.slice(0, 12) + c.slice(12).replace(/\D/g, '');
  }
  function mascaraCnpj(v) {
    var c = limparCnpj(v), r = c.slice(0, 2);
    if (c.length > 2) { r += '.' + c.slice(2, 5); }
    if (c.length > 5) { r += '.' + c.slice(5, 8); }
    if (c.length > 8) { r += '/' + c.slice(8, 12); }
    if (c.length > 12) { r += '-' + c.slice(12); }
    return r;
  }
  function cpfValido(d) {
    if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) { return false; }
    for (var t = 9; t < 11; t++) {
      var soma = 0;
      for (var i = 0; i < t; i++) { soma += +d[i] * (t + 1 - i); }
      if (+d[t] !== ((10 * soma) % 11) % 10) { return false; }
    }
    return true;
  }
  // Cada caractere vale o código ASCII menos 48 ("0" a "9" seguem 0 a 9).
  function cnpjValido(d) {
    if (!/^[0-9A-Z]{12}[0-9]{2}$/.test(d) || /^(.)\1{13}$/.test(d)) { return false; }
    var pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    for (var t = 12; t < 14; t++) {
      var soma = 0;
      for (var i = 0; i < t; i++) { soma += (d.charCodeAt(i) - 48) * pesos[i + 13 - t]; }
      var dv = soma % 11 < 2 ? 0 : 11 - soma % 11;
      if (+d[t] !== dv) { return false; }
    }
    return true;
  }
  function preparar(campo, tipo) {
    if (campo.dataset.docPronto) { return; }
    campo.dataset.docPronto = '1';
    // CNPJ pode ter letras: o teclado do celular não pode ser só numérico.
    campo.setAttribute('inputmode', tipo === 'cpf' ? 'numeric' : 'text');
    if (tipo === 'cnpj') { campo.setAttribute('autocapitalize', 'characters'); }
    var mascara = tipo === 'cpf' ? mascaraCpf : mascaraCnpj;
    var entrada = campo.closest('.entrada');
    var bloco = campo.closest('.campo');
    var ajuda = bloco ? bloco.querySelector('.ajuda') : null;
    var criada = false;
    var textoOriginal = ajuda ? ajuda.textContent : '';
    function dizer(erro) {
      if (erro && !ajuda && bloco) {
        ajuda = document.createElement('span');
        ajuda.className = 'ajuda';
        bloco.appendChild(ajuda);
        criada = true;
      }
      if (ajuda) {
        ajuda.textContent = erro || textoOriginal;
        ajuda.classList.toggle('erro', !!erro);
        if (!erro && criada) { ajuda.remove(); ajuda = null; criada = false; }
      }
      if (entrada) { entrada.classList.toggle('erro', !!erro); }
      campo.setCustomValidity(erro || '');
    }
    function conferir() {
      var d = tipo === 'cpf' ? campo.value.replace(/\D/g, '') : limparCnpj(campo.value);
      campo.value = mascara(campo.value);
      if (d === '') { dizer(''); return; }
      var tamanho = tipo === 'cpf' ? 11 : 14;
      if (d.length !== tamanho) {
        dizer(tipo === 'cpf' ? 'O CPF tem 11 dígitos; faltam ' + (11 - d.length) + '.'
                             : 'O CNPJ tem 14 caracteres; faltam ' + (14 - d.length) + '.');
      } else if (!(tipo === 'cpf' ? cpfValido(d) : cnpjValido(d))) {
        dizer((tipo === 'cpf' ? 'CPF' : 'CNPJ') + ' inválido: confira os números (os dígitos verificadores não batem).');
      } else {
        dizer('');
      }
    }
    campo.addEventListener('input', function () {
      campo.value = mascara(campo.value);
      // Enquanto corrige, o erro some assim que o número fica certo.
      if (campo.validationMessage) { conferir(); }
    });
    campo.addEventListener('blur', conferir);
    if (campo.value) { campo.value = mascara(campo.value); }
  }
  document.querySelectorAll('input.cpf').forEach(function (c) { preparar(c, 'cpf'); });
  document.querySelectorAll('input.cnpj, input#cnpj').forEach(function (c) { preparar(c, 'cnpj'); });
})();

/*
 * Pílula de escolha (label.pf com caixa dentro): acende quando marcada.
 * Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('label.pf input[type="checkbox"], label.pf input[type="radio"]').forEach(function (caixa) {
    var rotulo = caixa.closest('label.pf');
    function pintar() {
      if (caixa.type === 'radio') {
        document.querySelectorAll('input[name="' + caixa.name + '"]').forEach(function (r) {
          var l = r.closest('label.pf'); if (l) { l.classList.toggle('ativa', r.checked); }
        });
      } else { rotulo.classList.toggle('ativa', caixa.checked); }
    }
    caixa.addEventListener('change', pintar);
    pintar();
  });
})();

/*
 * Editor de foto das fichas (Foto::editavel): arrastar e soltar ou escolher
 * (no celular, a câmera), enquadrar arrastando, zoom e girar de 90 em 90. A
 * foto pronta (512 x 512, JPEG) vai para o campo "foto" do formulário e só é
 * gravada quando a ficha é salva. A prévia usa data: e não blob:, porque a
 * CSP da casa só aceita imagem de 'self' e data:.
 * Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-editor-foto]').forEach(function (raiz) {
    var botao = raiz.querySelector('[data-abrir-editor]');
    var saida = raiz.querySelector('[data-foto-saida]');
    var remover = raiz.querySelector('[data-foto-remover]');
    var janela = raiz.querySelector('dialog');
    var zona = janela.querySelector('[data-zona-foto]');
    var alvo = zona.querySelector('.zona-alvo');
    var entrada = zona.querySelector('[data-foto-entrada]');
    var recorte = janela.querySelector('[data-recorte]');
    var tela = janela.querySelector('[data-foto-tela]');
    var zoom = janela.querySelector('[data-foto-zoom]');
    var usar = janela.querySelector('[data-usar-foto]');
    var tirar = janela.querySelector('[data-tirar-foto]');
    var ctx = tela.getContext('2d');
    var img = null, giro = 0, escala = 1, dx = 0, dy = 0;

    function lados() {
      var deitada = giro % 180 !== 0;
      return { l: deitada ? img.naturalHeight : img.naturalWidth, a: deitada ? img.naturalWidth : img.naturalHeight };
    }
    // Escala mínima: a foto cobre o quadro todo, sem sobrar canto vazio.
    function cobre(lado) {
      var d = lados();
      return Math.max(lado / d.l, lado / d.a);
    }
    function prender(lado) {
      var d = lados(), s = cobre(lado) * escala;
      var mx = Math.max(0, (d.l * s - lado) / 2), my = Math.max(0, (d.a * s - lado) / 2);
      dx = Math.min(mx, Math.max(-mx, dx));
      dy = Math.min(my, Math.max(-my, dy));
    }
    function desenhar(c, lado, fator) {
      var s = cobre(lado) * escala;
      c.save();
      c.fillStyle = '#ffffff';
      c.fillRect(0, 0, lado, lado);
      c.translate(lado / 2 + dx * fator, lado / 2 + dy * fator);
      c.rotate(giro * Math.PI / 180);
      c.scale(s, s);
      c.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
      c.restore();
    }
    function atualizar() {
      if (!img) { return; }
      prender(tela.width);
      desenhar(ctx, tela.width, 1);
    }

    function carregar(arquivo) {
      if (!arquivo || !/^image\//.test(arquivo.type)) {
        erpAvisar('Escolha uma foto (JPG, PNG ou WebP).', 'atencao');
        return;
      }
      var leitor = new FileReader();
      leitor.onload = function () {
        var nova = new Image();
        nova.onload = function () {
          img = nova; giro = 0; escala = 1; dx = 0; dy = 0; zoom.value = '1';
          zona.hidden = true; recorte.hidden = false; usar.disabled = false;
          atualizar();
        };
        nova.onerror = function () { erpAvisar('Não consegui abrir esta foto. Tente outra.', 'atencao'); };
        nova.src = leitor.result;
      };
      leitor.readAsDataURL(arquivo);
    }

    function abrir() {
      img = null; zona.hidden = false; recorte.hidden = true; usar.disabled = true; entrada.value = '';
      janela.showModal();
    }
    botao.addEventListener('click', abrir);
    janela.querySelector('[data-fechar-editor]').addEventListener('click', function () { janela.close(); });
    janela.querySelector('[data-outra-foto]').addEventListener('click', function () {
      zona.hidden = false; recorte.hidden = true; usar.disabled = true; entrada.value = ''; entrada.click();
    });

    // Escolher ou arrastar.
    alvo.addEventListener('click', function () { entrada.click(); });
    alvo.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); entrada.click(); }
    });
    entrada.addEventListener('change', function () { carregar(entrada.files[0]); });

    // Buscar no WhatsApp: a foto do perfil entra no enquadramento como uma
    // escolhida; só troca a atual se a pessoa usar e salvar a ficha.
    var whats = janela.querySelector('[data-foto-whatsapp]');
    if (whats) {
      whats.addEventListener('click', function () {
        var f = new FormData();
        f.append('id', whats.getAttribute('data-foto-whatsapp'));
        f.append('csrf', whats.getAttribute('data-csrf') || '');
        whats.disabled = true;
        fetch('/api/foto-whatsapp', { method: 'POST', body: f, credentials: 'same-origin' })
          .then(function (r) {
            if (r.ok) { return r.blob(); }
            return r.json().catch(function () { return {}; }).then(function (d) {
              throw new Error(d.erro || 'Não consegui buscar a foto agora.');
            });
          })
          .then(function (b) { carregar(b); })
          .catch(function (e) { erpAvisar(e.message, 'atencao'); })
          .then(function () { whats.disabled = false; });
      });
    }
    var arrastes = 0;
    zona.addEventListener('dragenter', function (ev) { ev.preventDefault(); arrastes++; zona.classList.add('sobre'); });
    zona.addEventListener('dragover', function (ev) { ev.preventDefault(); });
    zona.addEventListener('dragleave', function () { if (--arrastes <= 0) { arrastes = 0; zona.classList.remove('sobre'); } });
    zona.addEventListener('drop', function (ev) {
      ev.preventDefault(); arrastes = 0; zona.classList.remove('sobre');
      carregar(ev.dataTransfer.files[0]);
    });

    // Enquadrar arrastando (mouse e dedo), zoom pela barra ou pela roda.
    var pegou = null;
    tela.addEventListener('pointerdown', function (ev) {
      if (!img) { return; }
      tela.setPointerCapture(ev.pointerId);
      pegou = { x: ev.clientX, y: ev.clientY };
    });
    tela.addEventListener('pointermove', function (ev) {
      if (!pegou) { return; }
      var proporcao = tela.width / tela.getBoundingClientRect().width;
      dx += (ev.clientX - pegou.x) * proporcao;
      dy += (ev.clientY - pegou.y) * proporcao;
      pegou = { x: ev.clientX, y: ev.clientY };
      atualizar();
    });
    ['pointerup', 'pointercancel'].forEach(function (t) { tela.addEventListener(t, function () { pegou = null; }); });
    zoom.addEventListener('input', function () { escala = parseFloat(zoom.value) || 1; atualizar(); });
    tela.addEventListener('wheel', function (ev) {
      if (!img) { return; }
      ev.preventDefault();
      escala = Math.min(4, Math.max(1, escala * (ev.deltaY < 0 ? 1.08 : 1 / 1.08)));
      zoom.value = String(escala);
      atualizar();
    }, { passive: false });
    janela.querySelectorAll('[data-girar]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!img) { return; }
        giro = (giro + parseInt(b.getAttribute('data-girar'), 10) + 360) % 360;
        var t = dx; dx = -dy; dy = t;
        if (b.getAttribute('data-girar') === '-90') { dx = -dx; dy = -dy; }
        atualizar();
      });
    });

    function mostrarNoCabecalho(conteudo) {
      var velho = botao.querySelector('.brasao');
      botao.replaceChild(conteudo, velho);
    }

    // Usar: a foto pronta vai para o campo do formulário; grava ao salvar.
    usar.addEventListener('click', function () {
      if (!img) { return; }
      var final = document.createElement('canvas');
      final.width = 512; final.height = 512;
      desenhar(final.getContext('2d'), 512, 512 / tela.width);
      final.toBlob(function (blob) {
        var dt = new DataTransfer();
        dt.items.add(new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
        saida.files = dt.files;
        remover.value = '';
        var previa = document.createElement('img');
        previa.className = 'brasao com-foto';
        previa.alt = '';
        previa.src = final.toDataURL('image/jpeg', 0.85);
        mostrarNoCabecalho(previa);
        raiz.classList.add('mudou');
        janela.close();
        erpAvisar('Foto ajustada. Ela é gravada quando você salvar a ficha.', 'info');
      }, 'image/jpeg', 0.9);
    });

    if (tirar) {
      tirar.addEventListener('click', function () {
        saida.value = '';
        remover.value = '1';
        var iniciais = document.createElement('span');
        iniciais.className = 'brasao iniciais';
        iniciais.textContent = raiz.getAttribute('data-iniciais') || '';
        mostrarNoCabecalho(iniciais);
        raiz.classList.add('mudou');
        janela.close();
        erpAvisar('A foto sai quando você salvar a ficha.', 'info');
      });
    }
  });
})();

/*
 * Listas com título, filtros e cabeçalho da tabela fixos ao rolar. As alturas
 * mudam de lista para lista (e quando os filtros quebram de linha), então
 * são medidas aqui e passadas ao CSS em variáveis.
 * Softsul Software
 */
(function () {
  'use strict';
  var lista = document.getElementById('lista-viva');
  var conteudo = document.querySelector('main.conteudo');
  if (!lista || !conteudo || document.body.classList.contains('em-popup')) { return; }
  var topo = document.querySelector('.topo');
  var cab = conteudo.querySelector(':scope > .cab-pag');
  var filtros = conteudo.querySelector(':scope > form.filtros');
  document.body.classList.add('lista-fixa');
  function medir() {
    var raiz = document.documentElement.style;
    var h0 = topo ? topo.offsetHeight : 0, h1 = cab ? cab.offsetHeight : 0, h2 = filtros ? filtros.offsetHeight : 0;
    raiz.setProperty('--fixo-topo', h0 + 'px');
    raiz.setProperty('--fixo-cab', (h0 + h1) + 'px');
    raiz.setProperty('--fixo-tabela', (h0 + h1 + h2) + 'px');
  }
  medir();
  window.addEventListener('resize', medir);
  if (window.ResizeObserver) {
    var obs = new ResizeObserver(medir);
    [topo, cab, filtros].forEach(function (el) { if (el) { obs.observe(el); } });
  }
})();

/*
 * Campo que leva a outra página assim que muda (a data de uma agenda, o
 * dia de um relatório): escolheu a data, foi.
 * Softsul Software
 */
(function () {
  'use strict';
  // Escolhendo no calendário, vai na hora. Digitando, cada número já forma
  // uma data válida: espera a pessoa parar de digitar (ou apertar Enter).
  document.querySelectorAll('[data-envia-ao-mudar]').forEach(function (campo) {
    var ultimaTecla = 0;
    var espera = null;
    function enviar() {
      if (campo.value && /^\d{4}-/.test(campo.value) && +campo.value.slice(0, 4) >= 2000 && campo.form) { campo.form.submit(); }
    }
    campo.addEventListener('keydown', function (ev) {
      ultimaTecla = Date.now();
      clearTimeout(espera);
      if (ev.key === 'Enter') { ev.preventDefault(); enviar(); }
    });
    campo.addEventListener('change', function () {
      clearTimeout(espera);
      espera = setTimeout(enviar, Date.now() - ultimaTecla < 1500 ? 1500 : 150);
    });
  });
})();

/*
 * Área de soltar arquivo (label.zona-soltar com input file dentro): arrastar
 * e soltar, ou clicar para escolher. Regra da casa: arquivo é sempre
 * arrastar e soltar.
 * Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-zona-soltar]').forEach(function (zona) {
    var campo = zona.querySelector('input[type="file"]');
    var nome = zona.querySelector('.zona-nome');
    var texto = zona.querySelector('.zona-texto');
    if (!campo) { return; }
    function mostrar() {
      var f = campo.files && campo.files[0];
      var n = campo.files ? campo.files.length : 0;
      if (nome) {
        nome.hidden = !f;
        nome.textContent = n > 1 ? n + ' arquivos escolhidos' : (f ? f.name + ' (' + Math.max(1, Math.round(f.size / 1024)) + ' kB)' : '');
      }
      if (texto) { texto.hidden = !!f; }
      zona.classList.toggle('com-arquivo', !!f);
    }
    ['dragenter', 'dragover'].forEach(function (ev) {
      zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('arrastando'); });
    });
    ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
      zona.addEventListener(ev, function () { zona.classList.remove('arrastando'); });
    });
    zona.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer && e.dataTransfer.files.length) {
        try { campo.files = e.dataTransfer.files; } catch (err) { return; }
        mostrar();
      }
    });
    campo.addEventListener('change', mostrar);
  });
})();

/* Lista que abre sempre no fim, no item mais novo (um histórico, uma conversa). */
(function () {
  'use strict';
  document.querySelectorAll('[data-rolar-fim]').forEach(function (c) { c.scrollTop = c.scrollHeight; });
})();

/*
 * Campo que aparece conforme a escolha de um seletor: <select data-revela>
 * mostra os [data-revelado="idDoSelect:valor"] do valor escolhido e esconde
 * os outros. Ex.: Cliente pass Wellhub abre o ID do Wellhub.
 */
(function () {
  'use strict';
  document.querySelectorAll('select[data-revela]').forEach(function (sel) {
    var alvos = document.querySelectorAll('[data-revelado^="' + sel.id + ':"]');
    function aplicar() {
      alvos.forEach(function (el) {
        el.hidden = el.getAttribute('data-revelado') !== sel.id + ':' + sel.value;
      });
    }
    sel.addEventListener('change', aplicar);
    aplicar();
  });
})();

/*
 * Exportar (CSV e PDF dos relatórios) leva o que está digitado na busca ao
 * vivo: o link foi montado quando a página abriu, antes da busca. Corre na
 * captura, antes do visor de PDF ler o endereço.
 */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var link = ev.target.closest ? ev.target.closest('a[data-leva-busca]') : null;
    var campo = document.querySelector('form.filtros input[type=search]');
    if (!link || !campo) { return; }
    var u = new URL(link.href, location.href);
    if (campo.value.trim()) { u.searchParams.set(campo.name, campo.value.trim()); } else { u.searchParams.delete(campo.name); }
    link.href = u.toString();
  }, true);
})();

/*
 * Atualização ao vivo das telas de acompanhar.
 * Softsul Software
 *
 * Trazido do Orbit (erp.js), sem mudança de comportamento.
 *
 * O painel e a fila de curadoria ficam abertos o dia inteiro na mesa de
 * quem acompanha. Sem isto, o que está na tela é o que existia quando a pessoa
 * carregou a página, e a única forma de saber se chegou alguma coisa é apertar
 * F5, que perde o filtro, a rolagem e o lugar em que se estava.
 *
 * A regra contra o pisca-pisca é uma só: só troca o que mudou. O pedaço novo é
 * comparado com o que está na tela, e quando é igual, byte a byte, nada
 * acontece. Recarregar a página inteira faria a tela apagar e reacender a cada
 * dez segundos, que é exatamente o que ninguém aguenta olhar.
 *
 * E há hora de não mexer: enquanto alguém escreve, arrasta um cartão, tem um
 * modal aberto ou está com a aba escondida. Trocar o chão debaixo do pé de
 * quem está no meio de uma ação é pior do que mostrar dado de dez segundos
 * atrás.
 */
(function () {
  'use strict';
  var alvo = document.querySelector('[data-vivo]');
  if (!alvo || !window.fetch || !window.DOMParser) { return; }

  var PASSO = 10;    // segundos entre uma olhada e outra
  var parcial = alvo.hasAttribute('data-vivo-parcial');
  var id = alvo.id;
  var emCurso = null;
  var castigo = 0;   // voltas a pular depois de um erro de rede
  var faltam = PASSO;

  // O contador ao lado do sino só existe onde há o que atualizar, e por isso
  // quem o acende é esta função, e não o PHP: tela sem bloco vivo mostraria um
  // relógio contando para nada.
  var pulso = document.getElementById('pulso-vivo');
  var conta = document.getElementById('conta-pulso');
  if (pulso) { pulso.hidden = false; }

  /* Quem está no meio de alguma coisa não pode ter a tela trocada. */
  function ocupado() {
    if (document.hidden) { return true; }
    if (document.querySelector('dialog[open]')) { return true; }
    if (document.querySelector('.arrastando')) { return true; }
    var foco = document.activeElement;
    if (foco && alvo.contains(foco)
        && (foco.tagName === 'INPUT' || foco.tagName === 'TEXTAREA'
            || foco.tagName === 'SELECT' || foco.isContentEditable)) {
      return true;
    }
    // Texto começado e não enviado (um parecer de curadoria, por exemplo)
    // dentro do bloco que se atualiza: apagar o que a pessoa escreveu seria
    // o pior defeito possível desta função.
    var escrevendo = alvo.querySelectorAll('textarea');
    for (var i = 0; i < escrevendo.length; i++) {
      if (escrevendo[i].value.trim() !== '') { return true; }
    }
    return false;
  }

  /* A rolagem de dentro (tabela larga, coluna do quadro) volta para onde
     estava: sem isto, a cada dez segundos a lista pulava para o topo. */
  function rolagens() {
    var estado = [];
    alvo.querySelectorAll('.rolagem, .cartoes')
        .forEach(function (el, i) { estado[i] = [el.scrollTop, el.scrollLeft]; });
    return estado;
  }
  function devolverRolagens(estado) {
    alvo.querySelectorAll('.rolagem, .cartoes')
        .forEach(function (el, i) {
          if (estado[i]) { el.scrollTop = estado[i][0]; el.scrollLeft = estado[i][1]; }
        });
  }

  function endereco() {
    if (!parcial) { return location.href; }
    var u = new URL(location.href);
    u.searchParams.set('parcial', '1');
    return u.toString();
  }

  function olhar() {
    if (castigo > 0) { castigo--; return; }
    if (ocupado()) { return; }
    if (emCurso) { emCurso.abort(); }
    emCurso = new AbortController();

    fetch(endereco(), {
      signal: emCurso.signal,
      headers: { 'X-Parcial': '1' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        // Sessão caída devolve a tela de entrada: trocar o miolo por um
        // formulário de login dentro do painel seria assustador. Melhor parar.
        if (!r.ok || r.redirected) { throw new Error('sem resposta útil'); }
        return r.text();
      })
      .then(function (html) {
        var novo;
        if (parcial) {
          // Passa pelo DOM antes de comparar: o navegador normaliza aspas,
          // atributo booleano e tag fechada sozinha, então comparar o texto
          // cru contra innerHTML acusaria diferença em toda volta e a tela
          // seria trocada de dez em dez segundos sem nada ter mudado.
          var caixa = document.createElement('div');
          caixa.innerHTML = html;
          novo = caixa.innerHTML;
        } else {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var bloco = doc.getElementById(id);
          if (!bloco) { throw new Error('bloco sumiu'); }
          novo = bloco.innerHTML;
        }
        // O coração disto: igual não se troca.
        if (novo === alvo.innerHTML) { return; }
        if (ocupado()) { return; }   // a pessoa começou algo enquanto a rede vinha
        var rolagem = rolagens();
        alvo.innerHTML = novo;
        devolverRolagens(rolagem);
        // O contador acende por um instante quando chegou coisa nova: é o
        // aviso de que a tela mudou sem ninguém ter mexido nela.
        if (pulso) {
          pulso.classList.add('chegou');
          setTimeout(function () { pulso.classList.remove('chegou'); }, 1200);
        }
        // As telas trazem comportamento junto (menu de anexo, visor de PDF).
        // Quem religa é cada componente, pela delegação no documento; o que
        // precisa de aviso explícito escuta este evento.
        document.dispatchEvent(new CustomEvent('erp:vivo', { detail: { id: id } }));
      })
      .catch(function (e) {
        if (e && e.name === 'AbortError') { return; }
        // Rede ruim não pode virar uma tentativa a cada dez segundos para
        // sempre: espera meio minuto antes de tentar de novo.
        castigo = 3;
      });
  }

  function pintarPulso() {
    if (!pulso) { return; }
    var parado = document.hidden || ocupado();
    pulso.classList.toggle('pausado', parado);
    if (conta) { conta.textContent = parado ? '||' : String(faltam); }
    pulso.title = parado
      ? 'Atualização em pausa enquanto você está escrevendo ou com algo aberto'
      : 'Esta tela se atualiza sozinha. Faltam ' + faltam + 's. Clique para atualizar agora.';
  }

  /* Uma volta por segundo, e não uma a cada dez: é o que faz o número andar na
     tela. A consulta continua sendo de dez em dez. */
  function volta() {
    if (document.hidden) { pintarPulso(); return; }
    if (ocupado()) { faltam = PASSO; pintarPulso(); return; }
    faltam--;
    if (faltam <= 0) {
      faltam = PASSO;
      olhar();
    }
    pintarPulso();
  }

  function agora() {
    faltam = PASSO;
    olhar();
    pintarPulso();
  }

  if (pulso) {
    pulso.addEventListener('click', agora);
  }
  setInterval(volta, 1000);
  pintarPulso();

  // Voltar para a aba é quando mais se quer a tela em dia.
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { agora(); }
  });
})();

/*
 * Os números do menu (conversas esperando a equipe), sem recarregar a tela.
 * Softsul Software
 */
(function () {
  'use strict';
  var itens = document.querySelectorAll('.conta-item[data-conta]');
  if (!itens.length || !window.fetch) { return; }
  function olhar() {
    if (document.hidden) { return; }
    fetch('/api/contadores', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok && !r.redirected ? r.json() : null; })
      .then(function (d) {
        if (!d) { return; }
        itens.forEach(function (el) {
          var n = d[el.getAttribute('data-conta')];
          if (typeof n !== 'number') { return; }
          el.textContent = String(n);
          el.hidden = n <= 0;
        });
      })
      .catch(function () {});
  }
  setInterval(olhar, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { olhar(); } });
})();

/*
 * Sessão expirada dentro do modal: a ficha abre num iframe, e o login (ou
 * qualquer tela de entrada) não pode aparecer ali dentro. A janela de fora
 * vai para o login, voltando depois para a tela em que a pessoa estava.
 * Softsul Software
 */
(function () {
  'use strict';
  if (!document.querySelector('main.tela-acesso')) { return; }
  var dentro = false;
  try { dentro = window.top !== window.self && window.top.location.origin === location.origin; } catch (e) { dentro = false; }
  if (!dentro) { return; }
  document.documentElement.style.visibility = 'hidden';
  var volta = window.top.location.pathname + window.top.location.search;
  var alvo = location.pathname === '/login' || location.pathname === '/codigo' || location.pathname === '/primeiro-acesso'
    ? location.pathname + '?destino=' + encodeURIComponent(volta) : '/login?destino=' + encodeURIComponent(volta);
  window.top.location.href = alvo;
})();

/*
 * Sessão vencida: ao voltar para a aba (ou para a janela), a página confere se
 * a sessão ainda vale e, se venceu, vai sozinha para o login, sem esperar a
 * pessoa clicar em algo. Depois do login, volta para esta tela. A conferência
 * não renova a sessão.
 * Softsul Software
 */
(function () {
  'use strict';
  if (document.querySelector('main.tela-acesso')) { return; }
  try { if (window.top !== window.self) { return; } } catch (e) { return; }   // a ficha no modal: a janela de fora cuida
  var ultima = 0;
  function conferir() {
    if (document.hidden || Date.now() - ultima < 5000) { return; }
    ultima = Date.now();
    fetch('/api/sessao', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
      if (r.status === 401) {
        location.href = '/login?destino=' + encodeURIComponent(location.pathname + location.search);
      }
    }).catch(function () {});
  }
  document.addEventListener('visibilitychange', conferir);
  window.addEventListener('focus', conferir);
})();

/*
 * Município do IBGE preenchido pelo CEP e pela consulta de CNPJ.
 * Softsul Software
 *
 * O município é escolhido na lista do IBGE (/api/municipios), e não
 * digitado: é o código que liga o destino, o local e o organizador ao mapa
 * do país. O CEP e a Receita sabem o município; aqui ele é escolhido no
 * campo de busca (#municipio_id), sem a pessoa redigitar.
 * - CEP: /api/cep devolve o código (ibge) quando acha o município; escolhe
 *   se o campo está vazio, e troca avisando se era outro.
 * - CNPJ: a Receita devolve codigo_ibge; como todo preenchimento da
 *   Receita, só completa o campo vazio.
 */
(function () {
  'use strict';
  var oculto = document.getElementById('municipio_id');
  var texto = document.getElementById('municipio_id_texto');
  if (!oculto || !texto || texto.readOnly || !window.fetch) { return; }
  var caixa = oculto.closest('.busca-viva');
  var aviso = caixa ? caixa.querySelector('.aviso-busca') : null;

  function escolher(id, rotulo, recado) {
    oculto.value = id;
    texto.value = rotulo;
    oculto.dispatchEvent(new Event('valor-definido'));
    if (recado && aviso) {
      aviso.textContent = recado;
      aviso.className = 'ajuda aviso-busca';
      aviso.hidden = false;
    }
  }

  // O município que a consulta de CNPJ escolheu, para a consulta de outro
  // CNPJ poder trocá-lo; o que a pessoa escolheu à mão fica.
  var escolhidoPelaReceita = '';
  function porCodigo(codigo, soSeVazio) {
    codigo = String(codigo || '').replace(/\D/g, '');
    if (codigo.length !== 7 || String(oculto.value) === codigo) { return; }
    if (soSeVazio && oculto.value && String(oculto.value) !== escolhidoPelaReceita) { return; }
    fetch('/api/municipios?id=' + codigo, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (lista) {
        if (!lista.length) { return; }
        var antes = oculto.value ? texto.value : '';
        escolher(lista[0].id, lista[0].texto, antes ? 'Município trocado de ' + antes + ' para ' + lista[0].texto + '.' : '');
      })
      .catch(function () {});
  }

  var cep = document.getElementById('cep');
  if (cep) {
    cep.addEventListener('cep-aplicado', function (ev) { porCodigo(ev.detail && ev.detail.ibge, false); });
  }
  var ibge = document.getElementById('codigo_ibge');
  if (ibge) {
    ibge.addEventListener('change', function (ev) {
      var codigo = String(ibge.value || '').replace(/\D/g, '');
      if (ev.detail && ev.detail.daReceita) {
        // Primeiro a busca, que compara o município atual com o que a
        // consulta ANTERIOR escolheu; só depois o novo código vira o
        // "escolhido pela Receita", para a próxima troca.
        porCodigo(codigo, true);
        escolhidoPelaReceita = codigo.length === 7 ? codigo : escolhidoPelaReceita;
      } else {
        porCodigo(codigo, true);
      }
    });
  }
  // Depois que a lista responde, o valor escolhido é o id do município, que
  // é o próprio código IBGE: a comparação em porCodigo usa o mesmo número.
})();

/*
 * Cadastur na ficha do organizador.
 * Softsul Software
 *
 * Ao sair do campo do CNPJ ou do CPF, a ficha pergunta à cópia local do
 * Cadastur (/api/cadastur, dados abertos do Ministério do Turismo) e mostra
 * a situação embaixo do campo CADASTUR. Com registro vigente e o campo em
 * branco, o número entra sozinho. Vale onde houver input[data-consultar-cadastur].
 */
(function () {
  'use strict';
  var alvo = document.querySelector('input[data-consultar-cadastur]');
  if (!alvo || !window.fetch) { return; }
  var ajuda = document.getElementById('ajuda-cadastur');
  var cnpj = document.getElementById('cnpj');
  var cpf = document.getElementById('documento_cpf');
  var ultimo = '';
  // O número que esta consulta pôs no campo: ao trocar o documento, ele
  // sai (ou muda); o que a pessoa digitou fica.
  var preenchidoPorAqui = '';

  function dizer(texto, classe) {
    if (!ajuda) { return; }
    ajuda.textContent = texto;
    ajuda.className = 'ajuda' + (classe ? ' ' + classe : '');
  }

  function consultar(campo, tamanho) {
    if (!campo) { return; }
    var doc = campo.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
    if (doc.length !== tamanho || doc === ultimo) { return; }
    ultimo = doc;
    dizer('Consultando o Cadastur...', '');
    fetch('/api/cadastur?doc=' + encodeURIComponent(doc), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { ultimo = ''; dizer(res.j.erro || 'Não foi possível consultar o Cadastur.', 'erro'); return; }
        var base = res.j.base ? ' Base do Ministério do Turismo, ' + res.j.base + '.' : '';
        if (!res.j.registros.length) {
          if (alvo.value && alvo.value === preenchidoPorAqui) { alvo.value = ''; preenchidoPorAqui = ''; }
          dizer('Sem registro no Cadastur com este documento. O registro é obrigatório para organizadora de eventos e outros prestadores de serviço turístico.' + base, 'erro');
          return;
        }
        var partes = res.j.registros.map(function (r) {
          return r.atividade + ': ' + (r.vigente ? 'vigente' : (r.situacao || 'irregular').toLowerCase())
               + (r.validade ? ', certificado até ' + r.validade : '');
        });
        var algumVigente = res.j.registros.some(function (r) { return r.vigente; });
        if (!alvo.value || alvo.value === preenchidoPorAqui) {
          alvo.value = res.j.certificado || '';
          preenchidoPorAqui = alvo.value;
        }
        dizer(partes.join('; ') + '.' + base, algumVigente ? 'bom' : 'erro');
      })
      .catch(function () { ultimo = ''; dizer('A consulta ao Cadastur falhou. O número pode ser digitado à mão.', 'erro'); });
  }

  [[cnpj, 14], [cpf, 11]].forEach(function (par) {
    if (!par[0]) { return; }
    par[0].addEventListener('change', function () { consultar(par[0], par[1]); });
    par[0].addEventListener('blur', function () { consultar(par[0], par[1]); });
  });
})();

/*
 * Enter avança para o próximo campo nas fichas de cadastro.
 * Softsul Software
 *
 * Numa ficha com dezenas de campos, o Enter que o navegador manda para o
 * primeiro botão de envio cadastrava o organizador pela metade, só porque
 * a pessoa terminou de digitar o nome. Aqui o Enter leva ao campo visível
 * seguinte; no último, leva ao botão Salvar, e um segundo Enter envia.
 * Ctrl+Enter (ou Cmd+Enter) salva de qualquer campo.
 *
 * Vale nos formulários das fichas (id form-...). Fora deles o Enter segue
 * o navegador: na entrada e nas buscas, enviar é o que se espera. Campo de
 * texto longo (textarea) não entra: lá o Enter é quebra de linha. Quem já
 * tratou o Enter (a lista de escolha do seletorBuscavel) não é alterado.
 */
(function () {
  'use strict';
  var formularios = document.querySelectorAll('form[id^="form-"]');
  if (!formularios.length) { return; }

  function visivel(el) {
    return !el.disabled && el.type !== 'hidden' && el.offsetParent !== null
        && !el.closest('[hidden]') && !el.closest('.oculto-visual');
  }
  function campos(form) {
    return Array.prototype.filter.call(form.querySelectorAll('input, select, textarea'), function (el) {
      return visivel(el) && ['submit', 'button', 'reset', 'image', 'file'].indexOf(el.type) === -1;
    });
  }
  function botaoDeSalvar(form) {
    var botoes = Array.prototype.filter.call(
      document.querySelectorAll('button[type="submit"][form="' + form.id + '"], #' + form.id + ' button[type="submit"]'),
      function (b) { return visivel(b) && !b.hasAttribute('formnovalidate'); });
    var principal = botoes.filter(function (b) { return b.classList.contains('botao') && !b.classList.contains('claro') && !b.classList.contains('perigo'); });
    return principal[0] || botoes[botoes.length - 1] || null;
  }

  Array.prototype.forEach.call(formularios, function (form) {
    form.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter' || ev.defaultPrevented || ev.altKey || ev.shiftKey) { return; }
      var el = ev.target;
      if (!el || el.tagName === 'TEXTAREA' || el.tagName === 'BUTTON' || el.tagName === 'A') { return; }
      if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT') { return; }
      if (ev.ctrlKey || ev.metaKey) {
        ev.preventDefault();
        var salvar = botaoDeSalvar(form);
        if (salvar) { salvar.click(); }
        return;
      }
      ev.preventDefault();
      var lista = campos(form);
      var i = lista.indexOf(el);
      var proximo = i >= 0 ? lista[i + 1] : null;
      if (proximo) {
        proximo.focus();
        if (typeof proximo.select === 'function' && proximo.tagName === 'INPUT'
            && ['text', 'email', 'tel', 'url', 'number', 'search'].indexOf(proximo.type) !== -1) {
          proximo.select();
        }
        return;
      }
      var botao = botaoDeSalvar(form);
      if (botao) { botao.focus(); }
    });
  });
})();

/*
 * Foco no primeiro campo ao abrir uma ficha de cadastro.
 * Softsul Software
 *
 * A ficha abre pronta para digitar: o primeiro campo de texto visível já
 * está com o foco, dentro do modal ou na página inteira. Pílulas de rádio
 * e caixas de marcar são puladas (o foco nelas não convida a digitar);
 * campo escondido numa aba fechada também. Telas que já têm autofocus
 * (entrada, código) ficam como estão.
 */
(function () {
  'use strict';
  if (document.querySelector('[autofocus]')) { return; }
  function visivel(el) {
    return !el.disabled && !el.readOnly && el.type !== 'hidden' && el.offsetParent !== null
        && !el.closest('[hidden]') && !el.closest('.oculto-visual');
  }
  // A primeira ficha com campo visível: o layout tem formulários escondidos
  // (trocar destino, sair) com id form-..., que vêm antes da ficha na
  // página inteira e não têm o que focar.
  var form = null;
  var campos = [];
  Array.prototype.some.call(document.querySelectorAll('form[id^="form-"]'), function (f) {
    if (f.hidden || f.closest('[hidden]')) { return false; }
    var lista = Array.prototype.filter.call(f.querySelectorAll('input, select, textarea'), visivel);
    if (!lista.length) { return false; }
    form = f; campos = lista;
    return true;
  });
  if (!form) { return; }
  var texto = campos.filter(function (el) {
    return el.tagName !== 'INPUT' || ['text', 'email', 'tel', 'url', 'number', 'search', 'date', 'time', 'password'].indexOf(el.type) !== -1;
  });
  var primeiro = texto[0] || campos[0];
  if (!primeiro) { return; }
  // Depois de o navegador assentar o layout (abas, modal): senão o foco cai
  // antes de a aba certa aparecer e se perde.
  setTimeout(function () {
    if (document.activeElement && document.activeElement !== document.body && document.activeElement.tagName !== 'IFRAME'
        && form.contains(document.activeElement)) { return; }
    primeiro.focus({ preventScroll: false });
  }, 60);
})();

/*
 * Aviso de campo obrigatório no desenho da casa.
 * Softsul Software
 *
 * O balão "Preencha este campo." é do navegador e não aceita estilo: sai
 * em outra fonte, outra cor, e some sozinho antes de a pessoa ler. Nas
 * fichas (form-...), o navegador deixa de mostrar o balão (novalidate, só
 * com JavaScript: sem ele, a validação nativa continua). No envio, o
 * primeiro campo inválido ganha a moldura vermelha, um aviso logo abaixo,
 * no mesmo tom dos outros avisos da tela, e o foco. O aviso some quando a
 * pessoa corrige. A validação de verdade continua no servidor.
 */
(function () {
  'use strict';
  var formularios = document.querySelectorAll('form[id^="form-"]');
  if (!formularios.length) { return; }

  function recado(el) {
    var v = el.validity;
    if (v.valueMissing) { return el.tagName === 'SELECT' ? 'Escolha uma opção.' : 'Preencha este campo.'; }
    if (v.typeMismatch && el.type === 'email') { return 'Digite um e-mail válido, como nome@dominio.com.br.'; }
    if (v.typeMismatch && el.type === 'url') { return 'Digite um endereço completo, como https://site.com.br.'; }
    if (v.tooShort) { return 'Use pelo menos ' + el.minLength + ' caracteres.'; }
    if (v.tooLong) { return 'No máximo ' + el.maxLength + ' caracteres.'; }
    if (v.rangeUnderflow) { return 'O mínimo é ' + el.min + '.'; }
    if (v.rangeOverflow) { return 'O máximo é ' + el.max + '.'; }
    if (v.patternMismatch) { return el.title || 'Formato inválido.'; }
    return el.validationMessage || 'Confira este campo.';
  }
  function moldura(el) { return el.closest('.entrada') || el; }
  function limpar(el) {
    moldura(el).classList.remove('erro');
    var campo = el.closest('.campo');
    var aviso = campo ? campo.querySelector('.balao-erro') : null;
    if (aviso) { aviso.remove(); }
  }
  function avisar(el) {
    limpar(el);
    moldura(el).classList.add('erro');
    var campo = el.closest('.campo');
    var aviso = document.createElement('p');
    aviso.className = 'balao-erro';
    aviso.setAttribute('role', 'alert');
    aviso.textContent = recado(el);
    var ancora = el.closest('.entrada') || el.closest('.busca-viva') || el;
    if (campo && ancora.parentNode === campo) { campo.insertBefore(aviso, ancora.nextSibling); }
    else if (ancora.parentNode) { ancora.parentNode.insertBefore(aviso, ancora.nextSibling); }
    // O campo pode estar numa aba fechada: o evento invalid abre a aba.
    el.dispatchEvent(new Event('invalid', { bubbles: false }));
    el.focus();
    if (el.scrollIntoView) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  }

  Array.prototype.forEach.call(formularios, function (form) {
    form.noValidate = true;
    form.addEventListener('submit', function (ev) {
      // O botão que pede para não validar (Inativar, Reativar) passa direto.
      var botao = ev.submitter;
      if (botao && botao.hasAttribute('formnovalidate')) { return; }
      var invalido = Array.prototype.find.call(form.elements, function (el) {
        return el.willValidate && !el.checkValidity() && !el.disabled;
      });
      if (!invalido) { return; }
      ev.preventDefault();
      avisar(invalido);
    });
    form.addEventListener('input', function (ev) {
      var el = ev.target;
      if (el && el.checkValidity && el.checkValidity()) { limpar(el); }
    });
    form.addEventListener('change', function (ev) {
      var el = ev.target;
      if (el && el.checkValidity && el.checkValidity()) { limpar(el); }
    });
  });
})();

/*
 * Listas: a célula que não coube numa linha termina em reticências (CSS);
 * ao passar o mouse, o texto inteiro aparece no title. Só na célula cortada,
 * e calculado na hora: a largura muda com a janela. Escuta o pointerover na
 * captura e vem antes do bloco dos balões, para o title já estar posto
 * quando o balão procura o texto.
 */
(function () {
  'use strict';
  document.addEventListener('pointerover', function (ev) {
    var alvo = ev.target.closest && ev.target.closest('table.dados td, table.dados td .txt b, table.dados td .txt span, table.dados td .ev-linha2');
    if (!alvo || alvo.hasAttribute('data-title-proprio')) { return; }
    if (alvo.hasAttribute('title') && !alvo.hasAttribute('data-title-auto')) { alvo.setAttribute('data-title-proprio', ''); return; }
    if (alvo.scrollWidth > alvo.clientWidth + 1) {
      alvo.setAttribute('title', alvo.textContent.replace(/\s+/g, ' ').trim());
      alvo.setAttribute('data-title-auto', '');
    } else if (alvo.hasAttribute('data-title-auto')) {
      alvo.removeAttribute('title');
      alvo.removeAttribute('data-title-auto');
    }
  }, true);
})();

/*
 * Combo: a lista de um <select> no padrão da casa (Orbit), no lugar da
 * lista nativa do sistema, que no Windows abre torta e sem o tema.
 *
 * O <select> de verdade continua no lugar: é ele que vai no formulário,
 * recebe o foco, valida e avisa quem escuta o "change" (data-revela,
 * data-enviar-ao-mudar, listas vivas). Este bloco só troca a lista que abre:
 * o clique (ou Enter, Espaço, Alt+seta) abre a lista própria, alinhada à
 * caixa; setas, Enter e Esc andam nela. A lista fica em position:fixed
 * dentro do modal ou da página, para nenhum overflow cortá-la.
 */
(function () {
  'use strict';
  var aberto = null; // { sel, lista, marcado }

  function opcoesDe(sel) {
    return Array.prototype.filter.call(sel.options, function (o) { return !o.hidden; });
  }

  function fechar(devolverFoco) {
    if (!aberto) { return; }
    var a = aberto;
    aberto = null;
    a.lista.remove();
    a.sel.closest('.entrada').classList.remove('combo-aberto');
    a.sel.setAttribute('aria-expanded', 'false');
    if (devolverFoco) { a.sel.focus(); }
  }

  function marcar(i) {
    if (!aberto) { return; }
    var itens = aberto.lista.querySelectorAll('li[data-i]');
    if (!itens.length) { return; }
    i = Math.max(0, Math.min(itens.length - 1, i));
    itens.forEach(function (li) { li.classList.remove('marcado'); });
    itens[i].classList.add('marcado');
    itens[i].scrollIntoView({ block: 'nearest' });
    aberto.marcado = i;
  }

  function escolher(i) {
    if (!aberto) { return; }
    var sel = aberto.sel;
    var op = opcoesDe(sel)[i];
    fechar(true);
    if (!op || op.disabled || sel.value === op.value) { return; }
    sel.value = op.value;
    sel.dispatchEvent(new Event('input', { bubbles: true }));
    sel.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function posicionar() {
    if (!aberto) { return; }
    var caixa = aberto.sel.closest('.entrada').getBoundingClientRect();
    var l = aberto.lista;
    l.style.left = caixa.left + 'px';
    l.style.minWidth = caixa.width + 'px';
    var abaixo = window.innerHeight - caixa.bottom - 12;
    var acima = caixa.top - 12;
    var alto = Math.min(l.scrollHeight, 320);
    if (abaixo < alto && acima > abaixo) {
      l.style.top = 'auto'; // a regra base de .sugestoes tem top, que jogaria a lista para fora da tela
      l.style.bottom = (window.innerHeight - caixa.top + 4) + 'px';
      l.style.maxHeight = Math.min(320, acima) + 'px';
    } else {
      l.style.bottom = 'auto';
      l.style.top = (caixa.bottom + 4) + 'px';
      l.style.maxHeight = Math.min(320, Math.max(abaixo, 120)) + 'px';
    }
  }

  function abrir(sel) {
    if (aberto && aberto.sel === sel) { fechar(true); return; }
    fechar(false);
    if (sel.disabled) { return; }
    var lista = document.createElement('ul');
    lista.className = 'sugestoes combo-lista';
    lista.setAttribute('role', 'listbox');
    var atual = 0;
    opcoesDe(sel).forEach(function (op, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.setAttribute('data-i', String(i));
      if (op.disabled) { li.className = 'desabilitada'; }
      if (op.selected) { li.setAttribute('aria-selected', 'true'); atual = i; }
      var b = document.createElement('b');
      b.textContent = op.textContent;
      li.appendChild(b);
      lista.appendChild(li);
    });
    lista.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
    lista.addEventListener('click', function (ev) {
      var li = ev.target.closest('li[data-i]');
      if (li && !li.classList.contains('desabilitada')) { escolher(parseInt(li.getAttribute('data-i'), 10)); }
    });
    lista.addEventListener('mousemove', function (ev) {
      var li = ev.target.closest('li[data-i]');
      if (li) { marcar(parseInt(li.getAttribute('data-i'), 10)); }
    });
    (sel.closest('dialog') || document.body).appendChild(lista);
    aberto = { sel: sel, lista: lista, marcado: atual };
    sel.closest('.entrada').classList.add('combo-aberto');
    sel.setAttribute('aria-expanded', 'true');
    posicionar();
    marcar(atual);
  }

  function preparar(sel) {
    if (sel.multiple || sel.size > 1 || sel.hasAttribute('data-nativo') || sel.dataset.combo) { return; }
    var caixa = sel.closest('.entrada');
    if (!caixa) { return; }
    sel.dataset.combo = '1';
    sel.setAttribute('aria-haspopup', 'listbox');
    sel.setAttribute('aria-expanded', 'false');
    caixa.classList.add('combo');
    var seta = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    seta.setAttribute('class', 'combo-seta');
    seta.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#i-seta');
    seta.appendChild(use);
    sel.insertAdjacentElement('afterend', seta);
    sel.addEventListener('mousedown', function (ev) {
      if (ev.button !== 0) { return; }
      ev.preventDefault();
      sel.focus();
      abrir(sel);
    });
    sel.addEventListener('keydown', function (ev) {
      var k = ev.key;
      if (aberto && aberto.sel === sel) {
        if (k === 'ArrowDown') { ev.preventDefault(); marcar(aberto.marcado + 1); }
        else if (k === 'ArrowUp') { ev.preventDefault(); marcar(aberto.marcado - 1); }
        else if (k === 'Home') { ev.preventDefault(); marcar(0); }
        else if (k === 'End') { ev.preventDefault(); marcar(9999); }
        else if (k === 'Enter' || k === ' ') { ev.preventDefault(); ev.stopPropagation(); escolher(aberto.marcado); }
        else if (k === 'Escape') { ev.preventDefault(); ev.stopPropagation(); fechar(true); }
        else if (k === 'Tab') { fechar(false); }
        return;
      }
      if (k === ' ' || (ev.altKey && (k === 'ArrowDown' || k === 'ArrowUp')) || k === 'F4') {
        ev.preventDefault();
        abrir(sel);
      }
    });
    sel.addEventListener('blur', function () {
      setTimeout(function () { if (aberto && aberto.sel === sel && document.activeElement !== sel) { fechar(false); } }, 120);
    });
  }

  function prepararTodos(raiz) {
    (raiz || document).querySelectorAll('.entrada select').forEach(preparar);
  }

  document.addEventListener('mousedown', function (ev) {
    if (aberto && !aberto.lista.contains(ev.target) && ev.target !== aberto.sel) { fechar(false); }
  });
  window.addEventListener('resize', function () { fechar(false); });
  document.addEventListener('scroll', function (ev) {
    if (aberto && !aberto.lista.contains(ev.target)) { posicionar(); }
  }, true);

  // Telas que montam selects depois (modal, linha nova): olha o que entra.
  new MutationObserver(function (mudancas) {
    mudancas.forEach(function (m) {
      m.addedNodes.forEach(function (n) {
        if (n.nodeType !== 1 || n.classList.contains('combo-lista')) { return; }
        if (n.matches('.entrada select')) { preparar(n); } else if (n.querySelectorAll) { prepararTodos(n); }
      });
    });
  }).observe(document.documentElement, { childList: true, subtree: true });

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { prepararTodos(); }); }
  else { prepararTodos(); }
})();

/*
 * Limpar filtros: o botão do Orbit (contatos), em toda lista com filtros.
 * Aparece à direita da linha de filtros quando o endereço tem algum filtro
 * (busca, combo, pílula fora do padrão) e volta à lista sem nenhum. Ordem,
 * página e modo de ver (lista ou mês) não contam como filtro e ficam.
 * A busca viva chama temFiltroNaLista a cada consulta para acender ou apagar.
 */
(function () {
  'use strict';
  var NAO_SAO_FILTRO = ['p', 'ord', 'dir', 'ver', 'mes', 'parcial'];
  window.temFiltroNaLista = function (params) {
    var tem = false;
    params.forEach(function (v, k) { if (v !== '' && NAO_SAO_FILTRO.indexOf(k) < 0) { tem = true; } });
    return tem;
  };
  var form = document.querySelector('main form.filtros');
  if (!form || document.getElementById('limpar-filtros')) { return; }
  // data-fixos no formulário: parâmetros que dizem QUAL tela é, não um
  // filtro (o tipo do relatório). Ficam no Limpar e não acendem o botão.
  var fixos = (form.getAttribute('data-fixos') || '').split(/\s+/).filter(Boolean);
  fixos.forEach(function (k) { if (NAO_SAO_FILTRO.indexOf(k) < 0) { NAO_SAO_FILTRO.push(k); } });
  var aqui = new URLSearchParams(location.search);
  var limpo = new URLSearchParams();
  ['ver', 'mes', 'ord', 'dir'].concat(fixos).forEach(function (k) { if (aqui.get(k)) { limpo.set(k, aqui.get(k)); } });
  var a = document.createElement('a');
  a.className = 'botao claro limpar-filtros';
  a.id = 'limpar-filtros';
  a.href = (form.getAttribute('action') || location.pathname) + (limpo.toString() ? '?' + limpo.toString() : '');
  a.title = 'Tirar todos os filtros';
  a.innerHTML = '<svg aria-hidden="true" focusable="false"><use href="#i-fechar"/></svg>Limpar';
  a.hidden = !window.temFiltroNaLista(aqui);
  (form.querySelector('.ev-filtros-linha') || form).appendChild(a);
})();

/*
 * Campo de texto com sugestões (input com list): a mesma lista própria dos
 * combos (.sugestoes do Orbit) no lugar da nativa do navegador, que não
 * aceita estilo. As opções saem do datalist; o texto continua livre, a
 * lista só filtra pelo que já foi digitado e completa ao escolher.
 */
(function () {
  'use strict';
  var aberto = null;

  function semAcento(t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

  function fechar() {
    if (!aberto) { return; }
    aberto.lista.remove();
    aberto.campo.setAttribute('aria-expanded', 'false');
    aberto = null;
  }

  function marcar(i) {
    var itens = aberto ? aberto.lista.querySelectorAll('li') : [];
    if (!itens.length) { return; }
    i = Math.max(0, Math.min(itens.length - 1, i));
    itens.forEach(function (li) { li.classList.remove('marcado'); });
    itens[i].classList.add('marcado');
    itens[i].scrollIntoView({ block: 'nearest' });
    aberto.marcado = i;
  }

  function escolher(li) {
    var campo = aberto.campo;
    campo.value = li.textContent;
    fechar();
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    campo.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function posicionar() {
    if (!aberto) { return; }
    var caixa = (aberto.campo.closest('.entrada') || aberto.campo).getBoundingClientRect();
    var l = aberto.lista;
    l.style.left = caixa.left + 'px';
    l.style.minWidth = caixa.width + 'px';
    var abaixo = window.innerHeight - caixa.bottom - 12;
    var acima = caixa.top - 12;
    var alto = Math.min(l.scrollHeight, 280);
    if (abaixo < alto && acima > abaixo) {
      l.style.top = 'auto';
      l.style.bottom = (window.innerHeight - caixa.top + 4) + 'px';
      l.style.maxHeight = Math.min(280, acima) + 'px';
    } else {
      l.style.bottom = 'auto';
      l.style.top = (caixa.bottom + 4) + 'px';
      l.style.maxHeight = Math.min(280, Math.max(abaixo, 120)) + 'px';
    }
  }

  function abrir(campo) {
    var termo = semAcento(campo.value.trim());
    var opcoes = (campo.__sugestoes || []).filter(function (o) {
      return termo === '' || (semAcento(o).indexOf(termo) !== -1 && semAcento(o) !== termo);
    });
    if (aberto && aberto.campo !== campo) { fechar(); }
    if (!opcoes.length) { fechar(); return; }
    if (!aberto) {
      var lista = document.createElement('ul');
      lista.className = 'sugestoes combo-lista';
      lista.setAttribute('role', 'listbox');
      lista.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
      lista.addEventListener('click', function (ev) {
        var li = ev.target.closest('li');
        if (li) { escolher(li); }
      });
      lista.addEventListener('mousemove', function (ev) {
        var li = ev.target.closest('li');
        if (li) { marcar(Array.prototype.indexOf.call(lista.children, li)); }
      });
      (campo.closest('dialog') || document.body).appendChild(lista);
      aberto = { campo: campo, lista: lista, marcado: -1 };
      campo.setAttribute('aria-expanded', 'true');
    }
    aberto.lista.textContent = '';
    aberto.marcado = -1;
    opcoes.forEach(function (o) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      var b = document.createElement('b');
      b.textContent = o;
      li.appendChild(b);
      aberto.lista.appendChild(li);
    });
    posicionar();
  }

  function preparar(campo) {
    if (campo.__sugestoes) { return; }
    var fonte = document.getElementById(campo.getAttribute('list'));
    if (!fonte) { return; }
    campo.__sugestoes = Array.prototype.map.call(fonte.options, function (o) { return o.value; });
    campo.removeAttribute('list');
    campo.setAttribute('autocomplete', 'off');
    campo.setAttribute('aria-haspopup', 'listbox');
    campo.setAttribute('aria-expanded', 'false');
  }

  function prepararTodos(raiz) {
    (raiz || document).querySelectorAll('input[list]').forEach(preparar);
  }

  document.addEventListener('focusin', function (ev) {
    if (ev.target.__sugestoes) { abrir(ev.target); }
  });
  document.addEventListener('input', function (ev) {
    if (ev.target.__sugestoes && document.activeElement === ev.target) { abrir(ev.target); }
  });
  document.addEventListener('focusout', function (ev) {
    if (aberto && ev.target === aberto.campo) { fechar(); }
  });
  document.addEventListener('keydown', function (ev) {
    if (!aberto || ev.target !== aberto.campo) { return; }
    if (ev.key === 'ArrowDown') { ev.preventDefault(); marcar(aberto.marcado + 1); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); marcar(aberto.marcado - 1); }
    else if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); fechar(); }
    else if (ev.key === 'Enter' && aberto.marcado >= 0) {
      ev.preventDefault(); ev.stopPropagation();
      escolher(aberto.lista.children[aberto.marcado]);
    }
  }, true);
  window.addEventListener('resize', fechar);
  document.addEventListener('scroll', function (ev) {
    if (aberto && !aberto.lista.contains(ev.target)) { posicionar(); }
  }, true);

  // Linha nova da tabela (atrações) chega depois: olha o que entra.
  new MutationObserver(function (mudancas) {
    mudancas.forEach(function (m) {
      m.addedNodes.forEach(function (n) {
        if (n.nodeType !== 1 || n.classList.contains('combo-lista')) { return; }
        if (n.matches('input[list]')) { preparar(n); } else if (n.querySelector('input[list]')) { prepararTodos(n); }
      });
    });
  }).observe(document.documentElement, { childList: true, subtree: true });

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { prepararTodos(); }); }
  else { prepararTodos(); }
})();

/* Imprimir (ponto de QR): o CSS de impressão deixa só o cartão do QR. */
document.addEventListener('click', function (ev) {
  if (ev.target.closest && ev.target.closest('[data-imprimir]')) { window.print(); }
});

/*
 * Prévia do cartaz do ponto de QR: a chamada e o apoio mudam enquanto se
 * digita, e o texto que não cabe na largura encolhe, como no PDF.
 */
(function () {
  'use strict';
  var previa = document.querySelector('.cartaz-previa');
  if (!previa) { return; }
  function caber(t) {
    var max = parseFloat(t.getAttribute('data-caber'));
    var base = parseFloat(t.getAttribute('data-fonte') || t.getAttribute('font-size'));
    t.setAttribute('data-fonte', base);
    t.setAttribute('font-size', base);
    var tam = base;
    while (tam > 2 && t.getComputedTextLength() > max) {
      tam -= 0.2;
      t.setAttribute('font-size', tam);
    }
  }
  Array.prototype.forEach.call(previa.querySelectorAll('[data-caber]'), caber);
  ['chamada', 'apoio'].forEach(function (campo) {
    var entrada = document.getElementById(campo);
    var alvo = previa.querySelector('[data-cartaz="' + campo + '"]');
    if (!entrada || !alvo) { return; }
    var original = alvo.textContent;
    entrada.addEventListener('input', function () {
      alvo.textContent = entrada.value.trim() || original;
      caber(alvo);
    });
  });
})();

/*
 * WhatsApp da plataforma (whatsapp.php): estado do número, QR e teste.
 * O QR vale poucos segundos, então a tela pede outro sozinha, e enquanto
 * o número não conecta ela acompanha o estado sem recarregar.
 */
(function () {
  'use strict';
  var caixa = document.getElementById('wa-numero');
  if (!caixa) { return; }
  var nome = document.getElementById('wa-nome');
  var tel = document.getElementById('wa-telefone');
  var foto = document.getElementById('wa-foto');
  var status = document.getElementById('wa-status');
  var qr = document.getElementById('wa-qr');
  var qrImg = document.getElementById('wa-qr-img');
  var retorno = document.getElementById('wa-retorno');
  var ROTULOS = {
    WORKING: ['Conectado', 'ok'], SCAN_QR_CODE: ['Esperando a leitura do QR', 'at'], STARTING: ['Ligando', 'at'],
    STOPPED: ['Parado', 'ruim'], FAILED: ['Com falha', 'ruim'], AUSENTE: ['Sessão não existe', 'ruim'], SEM_SESSAO: ['Sem sessão', 'nu']
  };
  var relogio = null, relogioQr = null;

  function botao(acao, mostrar) {
    var b = document.querySelector('[data-wa="' + acao + '"]');
    if (b) { b.hidden = !mostrar; }
  }
  function telefone(n) {
    var m = /^55(\d{2})(\d{4,5})(\d{4})$/.exec(n || '');
    return m ? '+55 ' + m[1] + ' ' + m[2] + '-' + m[3] : (n || '');
  }
  function mostrar(d) {
    if (!d.ok) { status.textContent = 'WAHA fora do ar'; status.className = 'pilula ruim'; nome.textContent = d.erro || ''; return; }
    var r = ROTULOS[d.status] || [d.status, 'nu'];
    status.textContent = r[0];
    status.className = 'pilula ' + r[1];
    nome.textContent = d.nome || (d.status === 'SEM_SESSAO' ? 'Nenhuma sessão escolhida' : 'Número sem nome');
    tel.textContent = telefone(d.numero);
    var ligado = d.status === 'WORKING';
    if (ligado && foto.hidden) {
      foto.onload = function () { foto.hidden = false; };
      foto.src = '/api/whatsapp?acao=foto&t=' + Date.now();
    }
    botao('conectar', d.status !== 'SEM_SESSAO' && !ligado && d.status !== 'SCAN_QR_CODE');
    botao('reiniciar', d.existe && d.status !== 'SCAN_QR_CODE');
    qr.hidden = d.status !== 'SCAN_QR_CODE';
    if (!qr.hidden && !relogioQr) { pedirQr(); }
    clearTimeout(relogio);
    if (!ligado && d.status !== 'SEM_SESSAO') { relogio = setTimeout(atualizar, 4000); }
  }
  function atualizar() {
    fetch('/api/whatsapp?acao=estado', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); }).then(mostrar)
      .catch(function () { mostrar({ ok: false, erro: 'Sem resposta do servidor.' }); });
  }
  function pedirQr() {
    if (qr.hidden) { relogioQr = null; return; }
    var nova = new Image();
    nova.onload = function () { qrImg.src = nova.src; };
    nova.src = '/api/whatsapp?acao=qr&t=' + Date.now();
    relogioQr = setTimeout(pedirQr, 8000);
  }
  function postar(acao, extra) {
    var f = new FormData();
    f.append('acao', acao);
    f.append('csrf', caixa.dataset.csrf);
    Object.keys(extra || {}).forEach(function (k) { f.append(k, extra[k]); });
    retorno.textContent = acao === 'teste' ? 'Enviando...' : 'Um momento...';
    return fetch('/api/whatsapp', { method: 'POST', body: f, headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        retorno.textContent = d.ok ? (acao === 'teste' ? 'Teste enviado. Confira no celular.' : 'Pronto.') : (d.erro || 'Não deu certo.');
        if (d.ok && d.status) { mostrar(d); }
      })
      .catch(function () { retorno.textContent = 'Sem resposta do servidor.'; });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-wa]') : null;
    if (!b) { return; }
    var acao = b.getAttribute('data-wa');
    if (acao === 'teste') {
      var n = document.getElementById('wa-numero-teste').value.replace(/\D/g, '');
      if (n.length < 10) { retorno.textContent = 'Digite o número com DDD.'; return; }
      postar('teste', { numero: n });
    } else {
      postar(acao);
    }
  });
  atualizar();
})();

/*
 * Apelido do link público do organizador: confere enquanto se digita se
 * está livre no destino, e mostra o link como vai ficar. O servidor confere
 * de novo ao salvar.
 * Softsul Software
 */
(function () {
  'use strict';
  var campo = document.querySelector('[data-verificar-apelido]');
  if (!campo || !window.fetch) { return; }
  var situacao = document.getElementById('apelido-situacao');
  var textoInicial = situacao ? situacao.textContent : '';
  var relogio = null, emCurso = null, original = campo.value;
  function conferir() {
    var valor = campo.value.trim();
    if (!situacao) { return; }
    if (valor === '' || valor === original) {
      situacao.textContent = textoInicial;
      situacao.classList.remove('erro', 'ok');
      return;
    }
    if (emCurso) { emCurso.abort(); }
    emCurso = new AbortController();
    fetch(campo.getAttribute('data-verificar-apelido') + '&q=' + encodeURIComponent(valor), { signal: emCurso.signal })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        situacao.textContent = d.msg || textoInicial;
        situacao.classList.toggle('erro', !d.ok);
        situacao.classList.toggle('ok', !!d.ok);
      })
      .catch(function () {});
  }
  campo.addEventListener('input', function () {
    // Já escreve como o link vai ficar: minúsculas, sem acento, hífen no espaço.
    var limpo = campo.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9-]+/g, '-').replace(/-{2,}/g, '-').replace(/^-/, '');
    if (limpo !== campo.value) { campo.value = limpo; }
    clearTimeout(relogio);
    relogio = setTimeout(conferir, 300);
  });
  campo.addEventListener('blur', function () { campo.value = campo.value.replace(/-+$/, ''); conferir(); });
})();

/*
 * Malha do Brasil por município, para os mapas da plataforma (Cadastur e
 * Mapa do Turismo).
 * Softsul Software
 *
 * No molde do mapa de cobertura do FrameFlow: a malha de municípios do IBGE
 * em SVG (assets/mapa), sem biblioteca, com zoom e arrasto mexendo no
 * viewBox, pinça no celular, divisas e siglas das UFs por cima. Quem usa
 * pinta os municípios e preenche o cartão; a malha cuida do resto: passar o
 * mouse (ou tocar) abre o cartão ao lado do ponteiro, a roda aproxima, o
 * arrasto move e o duplo clique avisa quem usa (que volta ao Brasil).
 */
window.inventourMalha = (function () {
  'use strict';
  var SIGLAS = {11: 'RO', 12: 'AC', 13: 'AM', 14: 'RR', 15: 'PA', 16: 'AP', 17: 'TO', 21: 'MA', 22: 'PI', 23: 'CE',
    24: 'RN', 25: 'PB', 26: 'PE', 27: 'AL', 28: 'SE', 29: 'BA', 31: 'MG', 32: 'ES', 33: 'RJ', 35: 'SP', 41: 'PR', 42: 'SC',
    43: 'RS', 50: 'MS', 51: 'MT', 52: 'GO', 53: 'DF'};
  var REGIOES = {'R-N': ['RO', 'AC', 'AM', 'RR', 'PA', 'AP', 'TO'], 'R-NE': ['MA', 'PI', 'CE', 'RN', 'PB', 'PE', 'AL', 'SE', 'BA'],
    'R-CO': ['MS', 'MT', 'GO', 'DF'], 'R-SE': ['MG', 'ES', 'RJ', 'SP'], 'R-S': ['PR', 'SC', 'RS']};

  // A malha dos municípios, a das UFs e os nomes ("Cidade-UF" por IBGE).
  function carregar(versao) {
    var texto = function (r) { if (!r.ok) { throw new Error('malha'); } return r.text(); };
    return Promise.all([
      fetch('/assets/mapa/municipios.svg?v=' + versao).then(texto),
      fetch('/assets/mapa/estados.svg?v=' + versao).then(texto),
      fetch('/assets/mapa/nomes.json?v=' + versao).then(function (r) { return r.json(); }).catch(function () { return {}; })
    ]);
  }

  // o: area, cartao, svgMun, svgUf, preencher(path) monta o cartão,
  // duploClique() é o "volta ao Brasil" de quem usa.
  function montar(o) {
    var area = o.area, cartao = o.cartao;
    area.innerHTML = o.svgMun;
    var svg = area.querySelector('svg');
    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    var tmp = document.createElement('div');
    tmp.innerHTML = o.svgUf;
    var gUf = tmp.querySelector('svg g');
    if (gUf) { gUf.setAttribute('class', 'mapa-ufs'); svg.appendChild(gUf); }
    var est = estados(svg, gUf);
    var vb0 = svg.getAttribute('viewBox').split(/\s+/).map(Number);
    var vb = vb0.slice();
    var gMun = svg.querySelector('g#BRMU');
    var t0 = gMun && gMun.transform.baseVal.consolidate();
    var mm = t0 ? t0.matrix : { a: 1, d: 1, e: 0, f: 0 };

    function aplicar() {
      svg.setAttribute('viewBox', vb.join(' '));
      svg.style.setProperty('--uf-w', Math.min(3.5, 1.2 * Math.sqrt(vb0[2] / vb[2])).toFixed(2) + 'px');
      if (!est.g) { return; }
      var rc = svg.getBoundingClientRect();
      if (!rc.width || !rc.height) { return; }
      var u = Math.max(vb[2] / rc.width, vb[3] / rc.height);
      est.g.setAttribute('font-size', (13 * u).toFixed(6));
      est.g.setAttribute('stroke-width', (3.5 * u).toFixed(6));
    }
    // A caixa de um município no espaço do viewBox ([x1, y1, x2, y2]): o
    // grupo da malha tem escala e eixo y invertido.
    function caixaDoMunicipio(p) {
      var b = p.getBBox();
      var ax = mm.a * b.x + mm.e, bx = mm.a * (b.x + b.width) + mm.e, ay = mm.d * b.y + mm.f, by = mm.d * (b.y + b.height) + mm.f;
      return [Math.min(ax, bx), Math.min(ay, by), Math.max(ax, bx), Math.max(ay, by)];
    }
    // A caixa de vários municípios, com folga e um tamanho mínimo: uma
    // cidade pequena sozinha ficaria do tamanho da tela inteira.
    function caixaMunicipios(ids) {
      var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
      ids.forEach(function (id) {
        var p = gMun && gMun.querySelector('path[id="' + id + '"]');
        if (!p) { return; }
        var c = caixaDoMunicipio(p);
        x1 = Math.min(x1, c[0]); y1 = Math.min(y1, c[1]); x2 = Math.max(x2, c[2]); y2 = Math.max(y2, c[3]);
      });
      if (x1 === Infinity) { return null; }
      var w = Math.max(x2 - x1, 0.6) * 1.6, h = Math.max(y2 - y1, 0.6) * 1.6;
      var cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
      return [cx - w / 2, cy - h / 2, w, h];
    }
    function caixaDe(ufs) {
      var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
      ufs.forEach(function (uf) {
        var b = est.caixas[uf];
        if (!b) { return; }
        x1 = Math.min(x1, b[0]); y1 = Math.min(y1, b[1]); x2 = Math.max(x2, b[0] + b[2]); y2 = Math.max(y2, b[1] + b[3]);
      });
      if (x1 === Infinity) { return null; }
      var px = (x2 - x1) * 0.08, py = (y2 - y1) * 0.08;
      return [x1 - px, y1 - py, x2 - x1 + 2 * px, y2 - y1 + 2 * py];
    }

    var atual = null;
    function abrirCartao(p, ev) {
      atual = p.id;
      o.preencher(p);
      cartao.hidden = false;
      var caixa = cartao.parentNode.getBoundingClientRect();
      var x = ev.clientX - caixa.left + 14, y = ev.clientY - caixa.top + 14;
      var w = cartao.offsetWidth, h = cartao.offsetHeight;
      if (x + w > caixa.width - 8) { x = ev.clientX - caixa.left - w - 14; }
      if (y + h > caixa.height - 8) { y = Math.max(8, caixa.height - h - 8); }
      cartao.style.left = Math.max(8, x) + 'px';
      cartao.style.top = Math.max(8, y) + 'px';
    }
    function fecharCartao() { cartao.hidden = true; atual = null; }

    var mostrar = null, esconder = null;
    svg.addEventListener('mousemove', function (ev) {
      var p = ev.target, eMun = p.parentNode && p.parentNode.id === 'BRMU';
      if (svg.classList.contains('arrastando')) { return; }
      clearTimeout(mostrar);
      if (eMun && p.id !== atual) {
        clearTimeout(esconder);
        mostrar = setTimeout(function () { abrirCartao(p, ev); }, 250);
      } else if (!eMun && !cartao.hidden) {
        clearTimeout(esconder);
        esconder = setTimeout(fecharCartao, 400);
      } else if (eMun) {
        clearTimeout(esconder);
      }
    });
    svg.addEventListener('mouseleave', function () { clearTimeout(mostrar); esconder = setTimeout(fecharCartao, 400); });
    cartao.addEventListener('mouseenter', function () { clearTimeout(esconder); });
    cartao.addEventListener('mouseleave', function () { esconder = setTimeout(fecharCartao, 300); });

    function zoomEm(cx, cy, f) {
      var r = svg.getBoundingClientRect(), fx = cx / r.width, fy = cy / r.height;
      var nw = Math.min(vb0[2], vb[2] * f), nh = Math.min(vb0[3], vb[3] * f);
      vb[0] += (vb[2] - nw) * fx; vb[1] += (vb[3] - nh) * fy; vb[2] = nw; vb[3] = nh;
      aplicar();
    }
    svg.addEventListener('wheel', function (ev) {
      ev.preventDefault();
      var r = svg.getBoundingClientRect();
      zoomEm(ev.clientX - r.left, ev.clientY - r.top, ev.deltaY < 0 ? 0.8 : 1.25);
    }, { passive: false });

    // Arrasto e pinça com Pointer Events: o mesmo código para mouse e toque.
    var toques = {}, arrasto = null, pinca = null, moveu = false;
    function porPixel() { var r = svg.getBoundingClientRect(); return Math.max(vb[2] / r.width, vb[3] / r.height); }
    svg.addEventListener('pointerdown', function (ev) {
      try { svg.setPointerCapture(ev.pointerId); } catch (e) { /* segue sem captura */ }
      toques[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
      var ids = Object.keys(toques);
      moveu = false;
      if (ids.length === 1) {
        arrasto = { x: ev.clientX, y: ev.clientY, vb: vb.slice(), alvo: ev.target };
        pinca = null;
      } else if (ids.length === 2) {
        var a = toques[ids[0]], b = toques[ids[1]];
        pinca = { d: Math.hypot(a.x - b.x, a.y - b.y) };
        arrasto = null;
        fecharCartao();
      }
    });
    svg.addEventListener('pointermove', function (ev) {
      if (!toques[ev.pointerId]) { return; }
      toques[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
      var ids = Object.keys(toques);
      if (pinca && ids.length === 2) {
        var a = toques[ids[0]], b = toques[ids[1]], d = Math.hypot(a.x - b.x, a.y - b.y);
        if (d > 0 && pinca.d > 0) {
          var r = svg.getBoundingClientRect();
          zoomEm((a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top, pinca.d / d);
          pinca.d = d;
          moveu = true;
        }
      } else if (arrasto) {
        var dx = ev.clientX - arrasto.x, dy = ev.clientY - arrasto.y;
        if (!moveu && Math.abs(dx) + Math.abs(dy) > 5) { moveu = true; svg.classList.add('arrastando'); fecharCartao(); }
        if (moveu) {
          var e = porPixel();
          vb[0] = arrasto.vb[0] - dx * e;
          vb[1] = arrasto.vb[1] - dy * e;
          aplicar();
        }
      }
    });
    function soltar(ev) {
      var alvo = arrasto && !moveu && ev.pointerType !== 'mouse' ? arrasto.alvo : null;
      delete toques[ev.pointerId];
      if (!Object.keys(toques).length) { arrasto = null; pinca = null; svg.classList.remove('arrastando'); }
      // Toque sem arrastar abre o cartão: no celular não há passar o mouse.
      if (alvo && alvo.parentNode && alvo.parentNode.id === 'BRMU') { abrirCartao(alvo, ev); }
    }
    svg.addEventListener('pointerup', soltar);
    svg.addEventListener('pointercancel', soltar);
    svg.addEventListener('dblclick', function () { if (o.duploClique) { o.duploClique(); } });
    // O tamanho da área só é conhecido depois de o modal abrir de vez.
    window.addEventListener('resize', function () { if (area.offsetWidth) { aplicar(); } });

    return {
      svg: svg,
      municipios: function () { return gMun ? gMun.querySelectorAll('path') : []; },
      caixaDoMunicipio: caixaDoMunicipio,
      caixaMunicipios: caixaMunicipios,
      caixaDe: caixaDe,
      // Mostra a caixa pedida; sem caixa, o Brasil inteiro.
      ver: function (caixa) { vb = (caixa || vb0).slice(); aplicar(); },
      fecharCartao: fecharCartao
    };
  }

  // Halo e siglas das UFs por cima da malha, e a caixa de cada UF.
  function estados(svg, gUf) {
    var NS = 'http://www.w3.org/2000/svg', r = { g: null, caixas: {} };
    if (!gUf) { return r; }
    var halo = gUf.cloneNode(true);
    halo.setAttribute('class', 'mapa-ufs-halo');
    halo.removeAttribute('id');
    svg.insertBefore(halo, gUf);
    var t0 = gUf.transform.baseVal.consolidate();
    var m = t0 ? t0.matrix : { a: 1, d: 1, e: 0, f: 0 };
    var g = document.createElementNS(NS, 'g');
    g.setAttribute('class', 'mapa-rotulos');
    svg.appendChild(g);
    gUf.querySelectorAll('path').forEach(function (p) {
      var s = SIGLAS[p.id];
      if (!s) { return; }
      var b = p.getBBox();
      var x1 = m.a * b.x + m.e, x2 = m.a * (b.x + b.width) + m.e, y1 = m.d * b.y + m.f, y2 = m.d * (b.y + b.height) + m.f;
      var bx = Math.min(x1, x2), by = Math.min(y1, y2), bw = Math.abs(x2 - x1), bh = Math.abs(y2 - y1);
      r.caixas[s] = [bx, by, bw, bh];
      var t = document.createElementNS(NS, 'text');
      t.setAttribute('x', bx + bw / 2);
      t.setAttribute('y', by + bh / 2);
      t.textContent = s;
      g.appendChild(t);
    });
    r.g = g;
    return r;
  }

  return { SIGLAS: SIGLAS, REGIOES: REGIOES, carregar: carregar, montar: montar };
})();

/*
 * Mapa do Cadastur (tela /cadastur, visão da plataforma).
 * Softsul Software
 *
 * Na malha de municípios (inventourMalha), cada município é pintado pela
 * quantidade de prestadores das atividades escolhidas nas etiquetas
 * (todas, ou uma ou mais), em cinco faixas. Passar o mouse (ou tocar) abre
 * o cartão do município, com a conta por atividade e o link para a lista
 * já filtrada.
 *
 * O mapa e os filtros da lista são um só recorte: ao abrir, o mapa lê a
 * UF, a região turística, a atividade, a situação, o município e a busca
 * da lista; marcar uma etiqueta ou escolher a região no mapa marca as
 * mesmas caixas da lista, que se atualiza por trás; e o cartão do
 * município filtra a lista por ele. Os dados vêm de /api/cadastur-mapa já
 * com a busca e a situação; trocar de etiqueta ou de UF só repinta, sem
 * voltar ao servidor. Com eles vem o Mapa do Turismo (região turística e
 * categoria de cada município): o cartão mostra a região, e o botão dele
 * filtra a lista pela região.
 */
(function () {
  'use strict';
  var janela = document.getElementById('modal-mapa-cadastur');
  if (!janela || !window.inventourMalha) { return; }
  var M = window.inventourMalha, SIGLAS = M.SIGLAS, REGIOES = M.REGIOES;
  var FAIXAS = 5;

  var area = janela.querySelector('.mapa-area');
  var cartao = janela.querySelector('.mapa-cartao');
  var etiquetas = janela.querySelector('.mapa-etiquetas');
  var escala = janela.querySelector('.mapa-escala');
  var selUf = janela.querySelector('.mapa-uf select');
  var pronto = false;
  var dados = null, nomes = {}, malha = null;
  var escolhidas = []; // índices das atividades; vazio = todas
  var recorte = [];    // siglas das UFs; vazio = Brasil inteiro
  var municipios = []; // códigos IBGE escolhidos na lista
  var regioesEsc = []; // regiões turísticas escolhidas na lista (ids)
  var chaveDados = null;
  var limites = [];
  var formLista = document.querySelector('form.filtros[action="/cadastur"]');

  // ---- a ponte com os filtros da lista
  function caixasDe(campo) { return formLista ? formLista.querySelectorAll('input[name="' + campo + '[]"]') : []; }
  function marcadas(campo) {
    return Array.prototype.filter.call(caixasDe(campo), function (c) { return c.checked; }).map(function (c) { return c.value; });
  }
  // Busca e situação mudam a contagem no servidor: viram a chave dos dados.
  function paramsDados() {
    var p = new URLSearchParams();
    var q = formLista && formLista.querySelector('input[name="q"]');
    if (q && q.value.trim()) { p.set('q', q.value.trim()); }
    marcadas('situacao').forEach(function (v) { p.append('situacao[]', v); });
    return p.toString();
  }
  function lerLista() {
    var ativ = marcadas('atividade');
    escolhidas = dados ? dados.atividades.map(function (a, i) { return ativ.indexOf(a) >= 0 ? i : -1; }).filter(function (i) { return i >= 0; }) : [];
    recorte = marcadas('uf');
    municipios = marcadas('municipio');
    regioesEsc = marcadas('regiao');
  }
  // Marca as caixas da lista e avisa uma vez: a lista viva recarrega e o
  // combo redesenha o resumo ("3 atividades").
  function escreverLista(campo, valores) {
    var caixas = caixasDe(campo);
    if (!caixas.length) { return; }
    Array.prototype.forEach.call(caixas, function (c) { c.checked = valores.indexOf(c.value) >= 0; });
    caixas[0].dispatchEvent(new Event('change', { bubbles: true }));
  }
  // O combo de região do mapa mostra o recorte da lista: Brasil, uma
  // região inteira, uma UF ou "3 UFs".
  function mostrarRecorte() {
    var extra = selUf.querySelector('option[data-lista]');
    if (extra) { extra.remove(); }
    var igual = function (a, b) { return a.length === b.length && a.every(function (x) { return b.indexOf(x) >= 0; }); };
    var valor = '';
    var rotulo = '';
    if (municipios.length) {
      // Cidades escolhidas mandam no recorte: o combo diz quais.
      rotulo = municipios.length === 1 ? (nomes[municipios[0]] || '1 município').replace(/-([A-Z]{2})$/, '/$1')
        : municipios.length + ' municípios';
    } else if (regioesEsc.length) {
      rotulo = regioesEsc.length === 1 ? ((dados && dados.regioes[regioesEsc[0]]) || '1 região turística')
        : regioesEsc.length + ' regiões turísticas';
      if (recorte.length) { rotulo += ' em ' + recorte.join(', '); }
    } else if (recorte.length === 1) { valor = recorte[0]; }
    else if (recorte.length) {
      Object.keys(REGIOES).forEach(function (r) { if (igual(REGIOES[r], recorte)) { valor = r; } });
      if (!valor) { rotulo = recorte.length + ' UFs: ' + recorte.join(', '); }
    }
    if (rotulo) {
      var o = document.createElement('option');
      o.value = 'lista';
      o.setAttribute('data-lista', '');
      o.textContent = rotulo;
      // Logo depois de "Brasil inteiro": options[1] mora dentro do grupo
      // Regiões, e o insertBefore no select com ele falhava.
      selUf.options[0].after(o);
      valor = 'lista';
    }
    selUf.value = valor;
  }
  function redesenhar(campo) {
    var c = formLista && formLista.querySelector('.multi-filtro[data-campo="' + campo + '"]');
    if (c) { c.dispatchEvent(new Event('multi-redesenhar')); }
  }
  // Os municípios das regiões escolhidas (e das UFs, se houver).
  function municipiosDasRegioes() {
    var t = (dados && dados.turismo) || {};
    return Object.keys(t).filter(function (ibge) {
      return regioesEsc.indexOf(String(t[ibge][0])) >= 0 && (!recorte.length || recorte.indexOf(SIGLAS[ibge.substr(0, 2)]) >= 0);
    });
  }
  function enquadrar() {
    // Município escolhido: perto, na caixa das cidades (uma ou várias
    // vizinhas); senão, as regiões turísticas; senão, as UFs marcadas;
    // senão, o Brasil.
    var ufs = recorte.length ? malha.caixaDe(recorte) : null;
    if (municipios.length) { malha.ver(malha.caixaMunicipios(municipios)); return; }
    if (regioesEsc.length) { malha.ver(malha.caixaMunicipios(municipiosDasRegioes()) || ufs); return; }
    malha.ver(ufs);
  }

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function milhar(n) { return Number(n).toLocaleString('pt-BR'); }

  // Abre junto com o modal (data-abre): carrega na primeira vez e, nas
  // outras, só relê os filtros da lista (e os dados, se a busca ou a
  // situação mudaram).
  new MutationObserver(function () {
    if (!janela.open) { cartao.hidden = true; return; }
    if (!pronto) { carregar(); return; }
    if (!malha) { return; }
    var chave = paramsDados();
    var seguir = function () { lerLista(); marcarEtiquetas(); mostrarRecorte(); enquadrar(); pintar(); };
    if (chave === chaveDados) { seguir(); return; }
    buscarDados().then(seguir).catch(function () { seguir(); });
  }).observe(janela, { attributes: true, attributeFilter: ['open'] });

  function buscarDados() {
    var chave = paramsDados();
    var fonte = janela.getAttribute('data-fonte');
    escala.innerHTML = '<span class="rotulo-escala">Contando...</span>';
    return fetch(fonte + (chave ? '?' + chave : ''), { headers: { 'Accept': 'application/json' } }).then(function (r) {
      if (!r.ok) { throw new Error('dados'); }
      return r.json();
    }).then(function (d) { dados = d; chaveDados = chave; return d; });
  }

  function carregar() {
    pronto = true;
    Promise.all([M.carregar(janela.getAttribute('data-versao')), buscarDados()]).then(function (r) {
      nomes = r[0][2];
      lerLista();
      montarEtiquetas();
      montarMapa(r[0][0], r[0][1]);
    }).catch(function () {
      area.innerHTML = '<span class="mapa-carregando">Não deu para carregar o mapa. Feche e abra de novo.</span>';
      pronto = false;
    });
  }

  // Quantos prestadores das atividades escolhidas há num município.
  function conta(m) {
    if (!m) { return 0; }
    var n = 0;
    if (!escolhidas.length) { for (var k in m.a) { n += m.a[k]; } return n; }
    escolhidas.forEach(function (i) { n += m.a[i] || 0; });
    return n;
  }
  // O que fica fora do filtro da lista não conta nem pinta: com município
  // escolhido, só ele; senão, as regiões turísticas e as UFs marcadas
  // (nenhuma = Brasil inteiro).
  function naRegiao(ibge) {
    if (municipios.length) { return municipios.indexOf(String(ibge)) >= 0; }
    if (regioesEsc.length) {
      var t = dados.turismo && dados.turismo[ibge];
      if (!t || regioesEsc.indexOf(String(t[0])) < 0) { return false; }
    }
    return !recorte.length || recorte.indexOf(SIGLAS[String(ibge).substr(0, 2)]) >= 0;
  }

  function montarEtiquetas() {
    dados.atividades.forEach(function (nome, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'mapa-tag';
      b.setAttribute('data-tag', String(i));
      b.setAttribute('aria-pressed', 'false');
      b.innerHTML = esc(nome) + '<span></span>';
      etiquetas.appendChild(b);
    });
    marcarEtiquetas();
    etiquetas.addEventListener('click', function (ev) {
      var b = ev.target.closest('.mapa-tag');
      if (!b) { return; }
      var t = b.getAttribute('data-tag');
      if (t === '') {
        escolhidas = [];
      } else {
        var i = Number(t), p = escolhidas.indexOf(i);
        if (p >= 0) { escolhidas.splice(p, 1); } else { escolhidas.push(i); }
      }
      marcarEtiquetas();
      pintar();
      escreverLista('atividade', escolhidas.map(function (i) { return dados.atividades[i]; }));
    });
  }
  function marcarEtiquetas() {
    etiquetas.querySelectorAll('.mapa-tag').forEach(function (b) {
      var t = b.getAttribute('data-tag');
      var ativa = t === '' ? !escolhidas.length : escolhidas.indexOf(Number(t)) >= 0;
      b.classList.toggle('ativa', ativa);
      b.setAttribute('aria-pressed', ativa ? 'true' : 'false');
    });
  }
  // Total de cada etiqueta no recorte (Brasil, região ou UF escolhida).
  function contarEtiquetas() {
    var tot = dados.atividades.map(function () { return 0; }), todas = 0;
    Object.keys(dados.municipios).forEach(function (ibge) {
      if (!naRegiao(ibge)) { return; }
      var a = dados.municipios[ibge].a;
      for (var k in a) { tot[k] += a[k]; todas += a[k]; }
    });
    etiquetas.querySelectorAll('.mapa-tag').forEach(function (b) {
      var t = b.getAttribute('data-tag');
      b.querySelector('span').textContent = milhar(t === '' ? todas : tot[Number(t)]);
    });
  }

  // Cinco faixas pelos quantis dos municípios que têm alguém: com a média,
  // as capitais puxariam tudo para a primeira cor.
  function pintar() {
    if (!malha) { return; }
    var valores = [];
    Object.keys(dados.municipios).forEach(function (ibge) {
      var n = conta(dados.municipios[ibge]);
      if (n > 0 && naRegiao(ibge)) { valores.push(n); }
    });
    valores.sort(function (a, b) { return a - b; });
    limites = [];
    for (var f = 1; f < FAIXAS; f++) {
      var q = valores.length ? valores[Math.min(valores.length - 1, Math.floor(valores.length * f / FAIXAS))] : 0;
      if (!limites.length || q > limites[limites.length - 1]) { limites.push(q); }
    }
    malha.municipios().forEach(function (p) {
      var n = naRegiao(p.id) ? conta(dados.municipios[p.id]) : 0;
      var faixa = 0;
      if (n > 0) { faixa = 1; while (faixa <= limites.length && n >= limites[faixa - 1]) { faixa++; } faixa = Math.min(faixa, FAIXAS); }
      p.setAttribute('class', (faixa ? 'f' + faixa : '') + (municipios.indexOf(p.id) >= 0 ? ' escolhido' : ''));
    });
    // Legenda: o intervalo de cada cor.
    var html = '<span class="rotulo-escala">Prestadores</span>', de = 1;
    for (var i = 0; i <= limites.length; i++) {
      var ate = i < limites.length ? limites[i] - 1 : null;
      if (ate !== null && ate < de) { continue; }
      html += '<span class="faixa"><i class="mapa-cor f' + (i + 1) + '"></i>' + (ate === null ? milhar(de) + '+' : (de === ate ? milhar(de) : milhar(de) + ' a ' + milhar(ate))) + '</span>';
      if (ate !== null) { de = ate + 1; }
    }
    escala.innerHTML = valores.length ? html : '<span class="rotulo-escala">Nenhum prestador nesse recorte.</span>';
    contarEtiquetas();
  }

  function preencherCartao(p) {
    var m = dados.municipios[p.id];
    var nome = nomes[p.id] || p.id;
    // A região turística e a categoria no Mapa do Turismo, com o botão
    // que filtra a lista pela região.
    var t = dados.turismo && dados.turismo[p.id];
    var regiao = t ? '<p class="mc-regiao">' + esc(dados.regioes[t[0]] || '') + '<br><small>' + esc(dados.categorias[t[1]] || '') + '</small></p>'
      : '<p class="mc-regiao"><small>Fora do Mapa do Turismo</small></p>';
    var botaoRegiao = t ? '<button type="button" class="botao claro" data-filtrar-regiao="' + esc(t[0]) + '">Ver a região</button>' : '';
    if (!m) {
      cartao.innerHTML = '<b>' + esc(nome) + '</b>' + regiao + '<p class="mc-vazio">Nenhum prestador no Cadastur.</p>'
        + (botaoRegiao ? '<div class="mc-botoes">' + botaoRegiao + '</div>' : '');
      return;
    }
    var linhas = [];
    dados.atividades.forEach(function (a, i) {
      if (!m.a[i]) { return; }
      var foco = !escolhidas.length || escolhidas.indexOf(i) >= 0;
      linhas.push('<tr class="' + (foco ? '' : 'fora') + '"><td>' + esc(a) + '</td><td class="num">' + milhar(m.a[i])
        + ((m.v && m.v[i]) ? ' <small title="vencidos">(' + milhar(m.v[i]) + ' venc.)</small>' : '') + '</td></tr>');
    });
    cartao.innerHTML = '<div class="mc-topo"><b>' + esc(nome) + '</b><span class="pilula nu">' + milhar(conta(m)) + '</span></div>'
      + regiao
      + '<table class="mc-tabela">' + linhas.join('') + '</table>'
      + (m.l ? '<p class="mc-leitos">' + milhar(m.l) + ' leitos em meios de hospedagem</p>' : '')
      + '<div class="mc-botoes"><button type="button" class="botao" data-filtrar-municipio="' + esc(p.id) + '">Ver na lista</button>' + botaoRegiao + '</div>';
  }

  function montarMapa(svgMun, svgUf) {
    malha = M.montar({
      area: area, cartao: cartao, svgMun: svgMun, svgUf: svgUf, preencher: preencherCartao,
      duploClique: function () { selUf.value = ''; selUf.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    // Começa no recorte da lista: as UFs marcadas, ou a UF do município.
    marcarEtiquetas();
    mostrarRecorte();
    enquadrar();
    pintar();

    // Região ou UF escolhida no mapa vira o filtro de UF da lista.
    selUf.addEventListener('change', function () {
      var v = selUf.value;
      if (v === 'lista') { return; }
      recorte = v ? (REGIOES[v] || [v]).slice() : [];
      // Trocar a região no mapa larga os municípios escolhidos na lista
      // (o aviso de mudança sai uma vez só, pelo filtro de UF).
      if (municipios.length) {
        Array.prototype.forEach.call(caixasDe('municipio'), function (c) { c.checked = false; });
        redesenhar('municipio');
        municipios = [];
      }
      if (regioesEsc.length) {
        Array.prototype.forEach.call(caixasDe('regiao'), function (c) { c.checked = false; });
        redesenhar('regiao');
        regioesEsc = [];
      }
      mostrarRecorte();
      enquadrar();
      malha.fecharCartao();
      pintar();
      escreverLista('uf', recorte);
    });
    // "Ver na lista": o município vira o filtro da lista, que já está com
    // as mesmas atividades, UFs e situação do mapa; o mapa fecha.
    // "Ver a região" faz o mesmo com a região turística do município, e
    // larga o município escolhido, que mandaria no lugar dela.
    cartao.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-filtrar-municipio], [data-filtrar-regiao]');
      if (!b || !formLista) { return; }
      var campo = b.hasAttribute('data-filtrar-regiao') ? 'regiao' : 'municipio';
      var id = b.getAttribute('data-filtrar-' + campo);
      var caixa = window.inventourMarcarNoFiltro(formLista, campo, [id], function () {
        return campo === 'regiao' ? (dados.regioes[id] || id) : (nomes[id] ? nomes[id].replace(/-([A-Z]{2})$/, '/$1') : id);
      });
      if (!caixa) { return; }
      if (campo === 'regiao') {
        regioesEsc = [id];
        Array.prototype.forEach.call(caixasDe('municipio'), function (c) { c.checked = false; });
        redesenhar('municipio');
        municipios = [];
      } else {
        municipios = [id];
      }
      janela.close();
      caixa.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }
})();

/*
 * Deixa marcados num filtro de múltipla escolha da lista só os ids pedidos,
 * criando a caixa do que ainda não está nas opções (filtro com fonte, que
 * só tem o que foi buscado). Devolve uma caixa do filtro, para quem chama
 * avisar a mudança (change) uma vez só; nome(id) dá o texto da caixa nova.
 * Softsul Software
 */
window.inventourMarcarNoFiltro = function (form, campo, ids, nome) {
  'use strict';
  var opcoes = form.querySelector('.multi-filtro[data-campo="' + campo + '"] .multi-opcoes');
  if (!opcoes) { return null; }
  ids.forEach(function (id) {
    if (opcoes.querySelector('input[value="' + id + '"]')) { return; }
    var l = document.createElement('label');
    l.className = 'check multi-opcao';
    var c = document.createElement('input');
    c.type = 'checkbox';
    c.name = campo + '[]';
    c.value = id;
    c.setAttribute('data-nome', nome(id));
    var sp = document.createElement('span'), b = document.createElement('b');
    b.textContent = c.getAttribute('data-nome');
    sp.appendChild(b);
    l.appendChild(c); l.appendChild(document.createElement('i')); l.appendChild(sp);
    opcoes.appendChild(l);
  });
  var caixas = opcoes.querySelectorAll('input[name="' + campo + '[]"]');
  Array.prototype.forEach.call(caixas, function (c) { c.checked = ids.indexOf(c.value) >= 0; });
  return caixas[0] || null;
};

/*
 * Mapa do Turismo no mapa (tela /mapa-turismo, visão da plataforma).
 * Softsul Software
 *
 * Cada região turística numa cor, na malha de municípios (inventourMalha).
 * A paleta é curta, e região vizinha não deve repetir cor: entre as
 * regiões do recorte (UF e categoria), da maior para a menor, cada uma fica
 * com a cor cuja região mais próxima de mesma cor está mais longe (centro
 * e tamanho pela caixa dos seus municípios).
 * O tom diz a categoria: cheio no município turístico, médio na oferta
 * complementar, claro no apoio. Município fora do Mapa fica sem cor.
 *
 * O mapa e os filtros da lista são um só recorte: ao abrir, lê a região, a
 * UF e a categoria marcadas. A lateral lista as regiões das UFs e
 * categorias do recorte, com a cor de cada uma; clicar numa região marca
 * ou desmarca ela no filtro da lista (que se atualiza por trás), e o mapa
 * aproxima nas escolhidas. O combo de UF do mapa vira o filtro de UF da
 * lista, e "Ver a região" no cartão filtra a lista por ela e fecha o mapa.
 */
(function () {
  'use strict';
  var janela = document.getElementById('modal-mapa-turismo');
  if (!janela || !window.inventourMalha) { return; }
  var M = window.inventourMalha;
  var CORES = ['#4E79A7', '#F28E2B', '#E15759', '#76B7B2', '#59A14F', '#EDC948', '#B07AA1', '#FF9DA7', '#9C755F',
    '#2F4B7C', '#A0CBE8', '#8CD17D'];
  var TOM = {1: 1, 2: 0.74, 3: 0.5};

  var area = janela.querySelector('.mapa-area');
  var cartao = janela.querySelector('.mapa-cartao');
  var lateral = janela.querySelector('.mapa-etiquetas');
  var selUf = janela.querySelector('.mapa-uf select');
  var formLista = document.querySelector('form.filtros[action="/mapa-turismo"]');
  var pronto = false, dados = null, nomes = {}, malha = null;
  var cor = {};        // id da região: cor
  var regioesEsc = []; // ids das regiões marcadas na lista
  var recorte = [];    // siglas das UFs; vazio = Brasil inteiro
  var categorias = []; // categorias marcadas; vazio = todas

  function caixasDe(campo) { return formLista ? formLista.querySelectorAll('input[name="' + campo + '[]"]') : []; }
  function marcadas(campo) {
    return Array.prototype.filter.call(caixasDe(campo), function (c) { return c.checked; }).map(function (c) { return c.value; });
  }
  function lerLista() { regioesEsc = marcadas('regiao'); recorte = marcadas('uf'); categorias = marcadas('categoria'); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function milhar(n) { return Number(n).toLocaleString('pt-BR'); }
  function nomeDe(ibge) { return (nomes[ibge] || ibge).replace(/-([A-Z]{2})$/, '/$1'); }

  // Na UF e na categoria da lista (a região fica de fora: a lateral mostra
  // as regiões do recorte para escolher).
  function noFiltro(ibge) {
    var t = dados.turismo[ibge];
    if (!t) { return false; }
    if (categorias.length && categorias.indexOf(String(t[1])) < 0) { return false; }
    return !recorte.length || recorte.indexOf(M.SIGLAS[ibge.substr(0, 2)]) >= 0;
  }
  function pinta(ibge) { return noFiltro(ibge) && (!regioesEsc.length || regioesEsc.indexOf(String(dados.turismo[ibge][0])) >= 0); }

  new MutationObserver(function () {
    if (!janela.open) { cartao.hidden = true; return; }
    if (!pronto) { carregar(); return; }
    if (!malha) { return; }
    lerLista(); mostrarRecorte(); pintar(); enquadrar();
  }).observe(janela, { attributes: true, attributeFilter: ['open'] });

  function carregar() {
    pronto = true;
    var dadosMapa = fetch(janela.getAttribute('data-fonte'), { headers: { 'Accept': 'application/json' } }).then(function (r) {
      if (!r.ok) { throw new Error('dados'); }
      return r.json();
    });
    Promise.all([M.carregar(janela.getAttribute('data-versao')), dadosMapa]).then(function (r) {
      nomes = r[0][2];
      dados = r[1];
      malha = M.montar({
        area: area, cartao: cartao, svgMun: r[0][0], svgUf: r[0][1], preencher: preencherCartao,
        duploClique: function () { selUf.value = ''; selUf.dispatchEvent(new Event('change', { bubbles: true })); }
      });
      lerLista(); mostrarRecorte(); pintar(); enquadrar();
      ligar();
    }).catch(function () {
      area.innerHTML = '<span class="mapa-carregando">Não deu para carregar o mapa. Feche e abra de novo.</span>';
      pronto = false;
    });
  }

  // A caixa de cada região, uma vez só; as cores saem de novo a cada
  // recorte, para a paleta se espalhar pelas regiões que estão na tela.
  var caixas = null;
  function colorir() {
    if (!caixas) { caixas = medirRegioes(); }
    var visiveis = {};
    Object.keys(dados.turismo).forEach(function (ibge) { if (noFiltro(ibge)) { visiveis[dados.turismo[ibge][0]] = true; } });
    var regs = Object.keys(caixas).filter(function (id) { return visiveis[id]; }).map(function (id) {
      var c = caixas[id];
      return { id: id, x: (c[0] + c[2]) / 2, y: (c[1] + c[3]) / 2, r: Math.hypot(c[2] - c[0], c[3] - c[1]) / 2 };
    }).sort(function (a, b) { return b.r - a.r || a.id - b.id; });
    var feitas = [];
    regs.forEach(function (g) {
      var melhor = 0, folga = -1;
      CORES.forEach(function (_, k) {
        var perto = Infinity;
        feitas.forEach(function (f) { if (f.k === k) { perto = Math.min(perto, Math.hypot(f.x - g.x, f.y - g.y) / (f.r + g.r)); } });
        if (perto > folga) { folga = perto; melhor = k; }
      });
      g.k = melhor;
      feitas.push(g);
      cor[g.id] = CORES[melhor];
    });
  }
  function medirRegioes() {
    var caixas = {};
    malha.municipios().forEach(function (p) {
      var t = dados.turismo[p.id];
      if (!t) { return; }
      var b = malha.caixaDoMunicipio(p), c = caixas[t[0]];
      if (!c) { caixas[t[0]] = b; return; }
      c[0] = Math.min(c[0], b[0]); c[1] = Math.min(c[1], b[1]); c[2] = Math.max(c[2], b[2]); c[3] = Math.max(c[3], b[3]);
    });
    return caixas;
  }

  function pintar() {
    colorir();
    malha.municipios().forEach(function (p) {
      var t = dados.turismo[p.id];
      if (t && pinta(p.id)) {
        p.style.fill = cor[t[0]] || '';
        p.style.fillOpacity = TOM[t[1]] || 1;
      } else {
        p.style.fill = '';
        p.style.fillOpacity = '';
      }
    });
    montarLateral();
  }

  // As regiões do recorte (UF e categoria), em ordem de nome, com a cor e
  // quantos municípios cada uma tem nele.
  function montarLateral() {
    var conta = {};
    Object.keys(dados.turismo).forEach(function (ibge) {
      if (noFiltro(ibge)) { var r = String(dados.turismo[ibge][0]); conta[r] = (conta[r] || 0) + 1; }
    });
    var ids = Object.keys(conta).sort(function (a, b) { return (dados.regioes[a] || '').localeCompare(dados.regioes[b] || '', 'pt-BR'); });
    var html = '<button type="button" class="mapa-tag todas' + (regioesEsc.length ? '' : ' ativa') + '" data-regiao="" aria-pressed="'
      + (regioesEsc.length ? 'false' : 'true') + '">Todas as regiões<span>' + milhar(ids.length) + '</span></button>';
    ids.forEach(function (id) {
      var ativa = regioesEsc.indexOf(id) >= 0;
      html += '<button type="button" class="mapa-tag' + (ativa ? ' ativa' : '') + '" data-regiao="' + esc(id) + '" aria-pressed="' + ativa + '">'
        + '<i class="mapa-cor" data-cor="' + cor[id] + '"></i><b class="mt-nome">' + esc(dados.regioes[id] || id) + '</b>'
        + '<span title="municípios">' + milhar(conta[id]) + '</span></button>';
    });
    lateral.innerHTML = ids.length ? html : '<p class="mc-vazio">Nenhuma região nesse recorte.</p>';
    tingir(lateral);
  }
  // A CSP não deixa style no HTML: a cor vai pelo JavaScript.
  function tingir(raiz) { raiz.querySelectorAll('[data-cor]').forEach(function (i) { i.style.background = i.getAttribute('data-cor'); }); }

  function enquadrar() {
    var ufs = recorte.length ? malha.caixaDe(recorte) : null;
    if (!regioesEsc.length) { malha.ver(ufs); return; }
    var ids = Object.keys(dados.turismo).filter(pinta);
    malha.ver(malha.caixaMunicipios(ids) || ufs);
  }

  function mostrarRecorte() {
    var extra = selUf.querySelector('option[data-lista]');
    if (extra) { extra.remove(); }
    var igual = function (a, b) { return a.length === b.length && a.every(function (x) { return b.indexOf(x) >= 0; }); };
    var valor = recorte.length === 1 ? recorte[0] : '';
    if (recorte.length > 1) {
      Object.keys(M.REGIOES).forEach(function (r) { if (igual(M.REGIOES[r], recorte)) { valor = r; } });
      if (!valor) {
        var o = document.createElement('option');
        o.value = 'lista';
        o.setAttribute('data-lista', '');
        o.textContent = recorte.length + ' UFs: ' + recorte.join(', ');
        selUf.options[0].after(o);
        valor = 'lista';
      }
    }
    selUf.value = valor;
  }

  // Marca as regiões no filtro da lista e avisa uma vez (a lista recarrega).
  function escreverRegioes(ids) {
    var caixa = window.inventourMarcarNoFiltro(formLista, 'regiao', ids, function (id) { return dados.regioes[id] || id; });
    if (caixa) { caixa.dispatchEvent(new Event('change', { bubbles: true })); }
  }

  function preencherCartao(p) {
    var t = dados.turismo[p.id];
    if (!t) {
      cartao.innerHTML = '<b>' + esc(nomeDe(p.id)) + '</b><p class="mc-vazio">Fora do Mapa do Turismo ' + esc(dados.ano) + '.</p>';
      return;
    }
    var num = function (v) { return v == null ? '-' : milhar(v); };
    var linhas = [['Empregos', num(t[2])], ['Estabelecimentos', num(t[3])], ['Visitas nacionais', num(t[4])],
      ['Visitas internacionais', num(t[5])],
      ['Arrecadação', t[6] == null ? '-' : Number(t[6]).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 })]];
    cartao.innerHTML = '<div class="mc-topo"><b>' + esc(nomeDe(p.id)) + '</b><span class="pilula ' + esc(dados.tons[t[1]] || 'nu') + '">'
      + esc(dados.curtas[t[1]] || '') + '</span></div>'
      + '<p class="mc-regiao"><i class="mapa-cor" data-cor="' + (cor[t[0]] || '') + '"></i>' + esc(dados.regioes[t[0]] || '') + '</p>'
      + '<table class="mc-tabela">' + linhas.map(function (l) { return '<tr><td>' + l[0] + '</td><td class="num">' + l[1] + '</td></tr>'; }).join('') + '</table>'
      + '<div class="mc-botoes"><button type="button" class="botao" data-filtrar-regiao="' + esc(t[0]) + '">Ver a região na lista</button></div>';
    tingir(cartao);
  }

  function ligar() {
    lateral.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-regiao]');
      if (!b) { return; }
      var id = b.getAttribute('data-regiao');
      if (id === '') { regioesEsc = []; }
      else if (regioesEsc.indexOf(id) >= 0) { regioesEsc.splice(regioesEsc.indexOf(id), 1); }
      else { regioesEsc.push(id); }
      malha.fecharCartao();
      pintar();
      enquadrar();
      escreverRegioes(regioesEsc);
    });
    // UF ou região do Brasil escolhida no mapa vira o filtro de UF da
    // lista; as regiões turísticas marcadas saem (podiam ser de outra UF).
    selUf.addEventListener('change', function () {
      var v = selUf.value;
      if (v === 'lista') { return; }
      recorte = v ? (M.REGIOES[v] || [v]).slice() : [];
      if (regioesEsc.length) {
        Array.prototype.forEach.call(caixasDe('regiao'), function (c) { c.checked = false; });
        var combo = formLista && formLista.querySelector('.multi-filtro[data-campo="regiao"]');
        if (combo) { combo.dispatchEvent(new Event('multi-redesenhar')); }
        regioesEsc = [];
      }
      mostrarRecorte();
      malha.fecharCartao();
      pintar();
      enquadrar();
      var caixas = caixasDe('uf');
      Array.prototype.forEach.call(caixas, function (c) { c.checked = recorte.indexOf(c.value) >= 0; });
      if (caixas.length) { caixas[0].dispatchEvent(new Event('change', { bubbles: true })); }
    });
    cartao.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-filtrar-regiao]');
      if (!b) { return; }
      regioesEsc = [b.getAttribute('data-filtrar-regiao')];
      janela.close();
      escreverRegioes(regioesEsc);
    });
  }
})();

/*
 * Link do cabeçalho que leva o filtro da lista para outra tela: a lista
 * viva muda os filtros sem recarregar, então o endereço sai na hora do
 * clique, com as caixas marcadas dos campos de data-leva-filtros (ex.:
 * "regiao uf" no Mapa do Turismo leva para o Cadastur).
 * Softsul Software
 */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a[data-leva-filtros]');
    var form = a && document.querySelector('main form.filtros');
    if (!form) { return; }
    var u = new URL(a.getAttribute('href'), location.href);
    u.search = '';
    a.getAttribute('data-leva-filtros').split(/\s+/).forEach(function (campo) {
      form.querySelectorAll('input[name="' + campo + '[]"]:checked').forEach(function (c) { u.searchParams.append(campo + '[]', c.value); });
    });
    a.href = u.pathname + u.search;
  });
})();

/*
 * Arrastar a linha para mudar a ordem (tbody[data-reordenar], Catálogos).
 * Softsul Software
 *
 * A alça no começo da linha arrasta com mouse ou dedo (Pointer Events), e
 * as setas para cima e para baixo movem a linha pelo teclado. Ao soltar, a
 * ordem nova da página vai para o servidor, que renumera a aba e devolve
 * os números, e a coluna Ordem se atualiza sem recarregar. Linha com
 * data-grupo (atributo) só anda dentro do grupo, onde a ordem vale. Se o
 * servidor recusar, a linha volta para onde estava e o motivo aparece.
 * O corpo da tabela é trocado pela lista viva: tudo vai por delegação.
 */
(function () {
  'use strict';
  var arrasto = null;

  function ids(corpo) { return Array.prototype.map.call(corpo.querySelectorAll('tr[data-id]'), function (tr) { return tr.getAttribute('data-id'); }); }
  function vizinhas(linha) {
    return Array.prototype.filter.call(linha.parentNode.children, function (tr) {
      return tr !== linha && tr.getAttribute('data-grupo') === linha.getAttribute('data-grupo');
    });
  }
  function aviso(corpo, texto) {
    var lista = corpo.closest('#lista-viva') || corpo.closest('table');
    var p = lista.parentNode.querySelector('.aviso-reordenar');
    if (!texto) { if (p) { p.remove(); } return; }
    if (!p) {
      p = document.createElement('p');
      p.className = 'aviso ruim aviso-reordenar';
      p.setAttribute('role', 'alert');
      lista.parentNode.insertBefore(p, lista);
    }
    p.textContent = texto;
  }
  function salvar(corpo, antes) {
    var agora = ids(corpo);
    if (agora.join() === antes.join()) { return; }
    var dados = new FormData();
    dados.append('csrf', corpo.getAttribute('data-csrf'));
    dados.append('aba', corpo.getAttribute('data-aba'));
    dados.append('acao', 'reordenar');
    agora.forEach(function (id) { dados.append('ids[]', id); });
    corpo.classList.add('salvando');
    fetch(location.pathname, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.erro || 'erro'); } return j; }); })
      .then(function (j) {
        aviso(corpo, '');
        corpo.querySelectorAll('tr[data-id]').forEach(function (tr) {
          var n = j.ordem[tr.getAttribute('data-id')];
          var td = tr.querySelector('[data-ordem]');
          if (td && n != null) { td.textContent = n; }
        });
      })
      .catch(function (e) {
        // Volta como estava: a tela não pode mostrar uma ordem que não foi gravada.
        antes.forEach(function (id) { corpo.appendChild(corpo.querySelector('tr[data-id="' + id + '"]')); });
        aviso(corpo, e.message && e.message !== 'erro' ? e.message : 'Não deu para gravar a ordem. Tente de novo.');
      })
      .then(function () { corpo.classList.remove('salvando'); });
  }

  document.addEventListener('pointerdown', function (ev) {
    var alca = ev.target.closest && ev.target.closest('tbody[data-reordenar] .alca');
    if (!alca || ev.button > 0) { return; }
    ev.preventDefault();
    var linha = alca.closest('tr');
    arrasto = { linha: linha, corpo: linha.parentNode, antes: ids(linha.parentNode) };
    linha.classList.add('arrastando');
    try { alca.setPointerCapture(ev.pointerId); } catch (e) { /* segue sem captura */ }
  });
  document.addEventListener('pointermove', function (ev) {
    if (!arrasto) { return; }
    var linha = arrasto.linha, outras = vizinhas(linha);
    for (var i = 0; i < outras.length; i++) {
      var r = outras[i].getBoundingClientRect();
      if (ev.clientY < r.top + r.height / 2) {
        if (outras[i].previousElementSibling !== linha) { outras[i].before(linha); }
        break;
      }
      if (i === outras.length - 1 && outras[i].nextElementSibling !== linha) { outras[i].after(linha); }
    }
    // Perto da borda da janela, a página rola junto.
    if (ev.clientY < 60) { window.scrollBy(0, -12); } else if (ev.clientY > window.innerHeight - 60) { window.scrollBy(0, 12); }
  });
  function soltar() {
    if (!arrasto) { return; }
    var a = arrasto;
    arrasto = null;
    a.linha.classList.remove('arrastando');
    salvar(a.corpo, a.antes);
  }
  document.addEventListener('pointerup', soltar);
  document.addEventListener('pointercancel', soltar);

  document.addEventListener('keydown', function (ev) {
    var alca = ev.target.closest && ev.target.closest('tbody[data-reordenar] .alca');
    if (!alca || (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown')) { return; }
    ev.preventDefault();
    var linha = alca.closest('tr'), corpo = linha.parentNode, antes = ids(corpo);
    var irmas = vizinhas(linha).concat([linha]).sort(function (a, b) { return a.compareDocumentPosition(b) & 4 ? -1 : 1; });
    var i = irmas.indexOf(linha);
    if (ev.key === 'ArrowUp' && i > 0) { irmas[i - 1].before(linha); }
    else if (ev.key === 'ArrowDown' && i < irmas.length - 1) { irmas[i + 1].after(linha); }
    else { return; }
    alca.focus();
    salvar(corpo, antes);
  });
})();

/*
 * Aviso de data especial na ficha do evento: se o dia escolhido cai num
 * feriado, ponto facultativo ou data comemorativa (/datas, do Brasil ou do
 * destino), quem cadastra fica sabendo na hora, abaixo dos campos de data.
 * Softsul Software
 *
 * Consulta /api/datas-do-periodo a cada troca de data, de recorrência ou de
 * dia da semana. No período com dias da semana marcados, só conta o dia em
 * que o evento acontece. Permanente não avisa: cairia em todo feriado do
 * ano. É aviso, não trava: o evento no Natal pode ser de propósito.
 */
(function () {
  'use strict';
  var caixa = document.querySelector('#form-evento [data-aviso-datas]');
  var form = caixa && caixa.closest('form');
  if (!form) { return; }
  var inicio = form.querySelector('#data_inicio');
  var fim = form.querySelector('#data_fim');
  var SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
  var relogio = null, emCurso = null;

  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function recorrencia() { var r = form.querySelector('input[name="recorrencia"]:checked'); return r ? r.value : 'unica'; }

  function conferir() {
    var rec = recorrencia();
    var de = inicio.value, ate = rec === 'periodo' && fim.value ? fim.value : de;
    if (!de || rec === 'permanente' || ate < de) { caixa.hidden = true; caixa.innerHTML = ''; return; }
    // Dias da semana marcados (nenhum = todos), pela posição: 0 é domingo.
    var marcados = Array.prototype.map.call(form.querySelectorAll('input[name="dias_semana[]"]'), function (c) { return c.checked; });
    var algum = rec !== 'unica' && marcados.some(Boolean);
    if (emCurso) { emCurso.abort(); }
    emCurso = new AbortController();
    fetch('/api/datas-do-periodo?de=' + encodeURIComponent(de) + '&ate=' + encodeURIComponent(ate), { signal: emCurso.signal })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (lista) {
        lista = (lista || []).filter(function (d) {
          return !algum || marcados[new Date(d.dia + 'T12:00:00').getDay()];
        });
        if (!lista.length) { caixa.hidden = true; caixa.innerHTML = ''; return; }
        var feriado = lista.some(function (d) { return d.tipo !== 'comemorativa'; });
        var itens = lista.slice(0, 6).map(function (d) {
          var dt = new Date(d.dia + 'T12:00:00');
          return '<li><b>' + d.dia.slice(8, 10) + '/' + d.dia.slice(5, 7) + ' ' + SEMANA[dt.getDay()] + '</b> ' + esc(d.nome) + ' <span>(' + esc(d.rotulo.toLowerCase()) + ')</span></li>';
        }).join('') + (lista.length > 6 ? '<li>e mais ' + (lista.length - 6) + '</li>' : '');
        caixa.innerHTML = '<div class="aviso atencao" role="status"><svg aria-hidden="true" focusable="false"><use href="#i-alerta"/></svg><span>'
          + '<strong>' + (lista.length === 1 ? 'A data do evento cai em ' + (feriado ? 'feriado' : 'data comemorativa') : 'O evento passa por ' + lista.length + ' datas especiais') + '.</strong> '
          + (feriado ? 'Em feriado o público, o comércio e o transporte mudam: confira se a data é essa mesma.' : 'Pode ser bom para o evento; só confira se é de propósito.')
          + '<ul class="aviso-datas">' + itens + '</ul></span></div>';
        caixa.hidden = false;
      })
      .catch(function () { /* consulta superada ou sem rede: fica o que havia */ });
  }
  function agendar() { clearTimeout(relogio); relogio = setTimeout(conferir, 250); }
  form.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t === inicio || t === fim || t.name === 'recorrencia' || t.name === 'dias_semana[]') { agendar(); }
  });
  inicio.addEventListener('input', agendar);
  if (fim) { fim.addEventListener('input', agendar); }
  conferir();
})();

/* Público real do pós-evento: com os três perfis preenchidos, o total é a soma. */
(function () {
  var total = document.querySelector('[data-soma-total]');
  var partes = document.querySelectorAll('[data-soma-parte]');
  if (!total || !partes.length) { return; }
  function somar() {
    var valores = Array.prototype.map.call(partes, function (c) { return c.value.replace(/\D/g, ''); });
    if (valores.indexOf('') >= 0) { return; }
    total.value = valores.reduce(function (s, n) { return s + parseInt(n, 10); }, 0).toLocaleString('pt-BR');
  }
  partes.forEach(function (c) { c.addEventListener('input', somar); });
})();

/* Agenda rápida do artista: a cidade escolhida traz os locais do destino, diz
   se a data vai para o portal e mostra o termo, quando falta aceitar. */
(function () {
  var form = document.querySelector('[data-artista-show]');
  if (!form) { return; }
  var cidade = document.getElementById('municipio_id');
  var cidadeTexto = document.getElementById('municipio_id_texto');
  var localCampo = document.getElementById('local_nome');
  var recado = form.querySelector('[data-artista-destino]');
  var caixaTermo = form.querySelector('[data-artista-termo]');
  function conferir() {
    if (!cidade.value) { recado.textContent = ''; localCampo.__sugestoes = []; caixaTermo.hidden = true; return; }
    fetch('/api/artista-cidade?municipio=' + encodeURIComponent(cidade.value))
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (c) {
        if (!c) { return; }
        // A lista de sugestões do Orbit guarda as opções no próprio campo.
        localCampo.__sugestoes = c.locais || [];
        recado.classList.toggle('erro', !!c.bloqueado);
        recado.textContent = c.bloqueado ? 'A gestão de turismo desta cidade suspendeu as suas datas aqui.'
          : c.destino ? 'Vai para a curadoria de ' + c.destino + '.' : 'Esta cidade ainda não tem agenda no InvenTour: a data fica só na sua agenda, fora do portal.';
        caixaTermo.hidden = !c.termo;
        if (c.termo) {
          form.querySelector('[data-artista-termo-titulo]').textContent = c.termo.titulo + ' (versão ' + c.termo.versao + ')';
          form.querySelector('[data-artista-termo-texto]').textContent = c.termo.texto;
        }
      })
      .catch(function () { /* sem rede: o servidor confere ao lançar */ });
  }
  cidadeTexto.addEventListener('item-escolhido', function () { setTimeout(conferir, 0); });
  cidadeTexto.addEventListener('input', function () { setTimeout(function () { if (!cidade.value) { conferir(); } }, 0); });
  conferir();
})();

/*
 * Ficha do local: a capacidade dos bombeiros vira a de auditório e a de banquete
 * enquanto se digita. Softsul Software
 */
(function () {
  'use strict';
  var campo = document.getElementById('capacidade');
  var saida = document.querySelector('[data-capacidades]');
  if (!campo || !saida) { return; }
  var n = function (v) { return Math.floor(v).toLocaleString('pt-BR'); };
  // Teatro, auditório ou arena: a capacidade já é a dos assentos.
  var fixos = document.querySelector('[data-assentos-fixos]');
  if (fixos) { fixos.addEventListener('change', function () { saida.hidden = fixos.checked; }); }
  campo.addEventListener('input', function () {
    var total = parseInt(campo.value.replace(/\D/g, ''), 10) || 0;
    saida.textContent = total ? 'Em pé ou show, ' + n(total) + '; em auditório, ' + n(total * parseFloat(saida.dataset.auditorio))
      + '; em banquete, ' + n(total * parseFloat(saida.dataset.banquete)) + ' pessoas.' : '';
  });
})();

/*
 * Revisar com IA: só aparece com o token do destino (app/Ia.php). A proposta
 * fica ao lado do campo e só entra nele se a pessoa escolher. Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-ia-revisar]').forEach(function (botao) {
    var campo = document.getElementById(botao.getAttribute('data-ia-revisar'));
    var caixa = botao.closest('.campo').querySelector('[data-ia-proposta]');
    if (!campo || !caixa) { return; }
    var texto = caixa.querySelector('textarea');
    var usar = caixa.querySelector('[data-ia-usar]');
    botao.addEventListener('click', function () {
      var f = new FormData();
      f.append('csrf', botao.getAttribute('data-csrf') || '');
      f.append('tipo', botao.getAttribute('data-ia-tipo') || '');
      f.append('texto', campo.value);
      var rotulo = botao.lastChild;
      var original = rotulo.textContent;
      botao.disabled = true;
      rotulo.textContent = 'Melhorando...';
      fetch('/api/ia-revisar', { method: 'POST', body: f })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          botao.disabled = false;
          rotulo.textContent = original;
          if (r.erro) { if (window.erpAvisar) { window.erpAvisar(r.erro, 'ruim'); } return; }
          texto.value = r.texto;
          caixa.hidden = false;
        })
        .catch(function () { botao.disabled = false; rotulo.textContent = original; if (window.erpAvisar) { window.erpAvisar('A IA não respondeu agora.', 'ruim'); } });
    });
    usar.addEventListener('click', function () {
      campo.value = texto.value;
      campo.dispatchEvent(new Event('input', { bubbles: true }));
      caixa.hidden = true;
    });
  });
})();

/*
 * Ficha do destino: escolher uma cor desmarca "Usar a cor do InvenTour", senão
 * a escolha se perdia ao salvar; marcar a caixa mostra a cor que vai valer.
 * Softsul Software
 */
(function () {
  'use strict';
  var cor = document.getElementById('cor_acento');
  var caixa = document.querySelector('[data-sem-cor]');
  if (!cor || !caixa) { return; }
  var escolhida = caixa.checked ? '' : cor.value;
  cor.addEventListener('input', function () {
    escolhida = cor.value;
    caixa.checked = false;
  });
  caixa.addEventListener('change', function () {
    if (caixa.checked) {
      cor.value = cor.dataset.corEventcity;
    } else if (escolhida) {
      cor.value = escolhida;
    }
  });
})();

/*
 * Interruptor das telas (função interruptor do svg.php) e o quadro da IA: ligada,
 * mostra o modelo e só o token do provedor dele. Softsul Software
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-interruptor]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var ligado = botao.getAttribute('aria-checked') !== 'true';
      botao.setAttribute('aria-checked', ligado ? 'true' : 'false');
      botao.querySelector('input').value = ligado ? '1' : '';
      botao.querySelector('[data-interruptor-estado]').textContent = ligado ? botao.dataset.ligado : botao.dataset.desligado;
      botao.dispatchEvent(new CustomEvent('interruptor', { bubbles: true }));
    });
  });
  var quadro = document.querySelector('[data-ia-quadro]');
  if (!quadro) { return; }
  var chave = quadro.querySelector('[data-interruptor]');
  var modelo = quadro.querySelector('#ia_modelo');
  var aviso = quadro.querySelector('[data-ia-aviso]');
  function pintar() {
    var ligada = chave.getAttribute('aria-checked') === 'true';
    var opcao = modelo.options[modelo.selectedIndex];
    var provedor = opcao.dataset.provedor;
    quadro.querySelector('[data-ia-campos]').hidden = !ligada;
    var bloco = quadro.querySelector('[data-ia-token="' + provedor + '"]');
    var temToken = bloco.dataset.tem === '1' || bloco.querySelector('input[type=password]').value !== '';
    var nome = bloco.dataset.nome;
    aviso.textContent = !ligada ? 'Desligada: nada é enviado e não há custo.'
      : temToken ? 'Ligada, com a chave do ' + nome + ' deste destino. O uso é cobrado na conta dele.'
      : 'Falta a chave do ' + nome + ': sem ela, nada é enviado e não há custo.';
    aviso.parentElement.className = 'aviso ' + (ligada && temToken ? 'info' : 'atencao');
  }
  chave.addEventListener('interruptor', pintar);
  modelo.addEventListener('change', pintar);
  quadro.querySelectorAll('input[type=password]').forEach(function (i) { i.addEventListener('input', pintar); });
  pintar();
})();

/*
 * Perfil de demanda em Conflitos e impacto: escolher sugere a ocupação de
 * cada bloco da semana, que o destino ajusta depois. Softsul Software
 */
(function () {
  'use strict';
  var perfil = document.querySelector('[data-perfil-demanda]');
  if (!perfil) { return; }
  var ajuda = document.querySelector('[data-perfil-ajuda]');
  perfil.addEventListener('change', function () {
    var op = perfil.options[perfil.selectedIndex];
    if (!op || !op.value) { return; }
    ['semana', 'fim_semana', 'domingo'].forEach(function (b) {
      var campo = document.getElementById('ocupacao_' + b);
      if (campo) { campo.value = op.dataset[b]; }
    });
    if (ajuda) { ajuda.textContent = op.dataset.explica + ' Ocupação sugerida; ajuste com os números do trade.'; }
  });
})();

/*
 * App InvenTour Gestão (mobile/gestao, casca Android em tela inteira): a
 * faixa atrás das barras do sistema ganha a cor do topo da página, e de novo
 * quando o tema claro ou escuro troca (InvenTourNativo, ponte do app).
 * Softsul Software
 */
(function () {
  'use strict';
  if (!/\bInvenTourGestao\//.test(navigator.userAgent)) { return; }
  document.documentElement.classList.add('no-app');
  function cor(el) {
    if (!el) { return null; }
    var m = getComputedStyle(el).backgroundColor.match(/\d+(\.\d+)?/g);
    if (!m || (m.length === 4 && +m[3] === 0)) { return null; }
    return m;
  }
  function barras() {
    if (!window.InvenTourNativo || !window.InvenTourNativo.barras) { return; }
    var m = cor(document.elementFromPoint(2, 2)) || cor(document.body) || cor(document.documentElement);
    if (!m) { return; }
    var r = +m[0], g = +m[1], b = +m[2];
    var hex = '#' + [r, g, b].map(function (v) { return ('0' + Math.round(v).toString(16)).slice(-2); }).join('');
    window.InvenTourNativo.barras(hex, (0.299 * r + 0.587 * g + 0.114 * b) > 150);
  }
  barras();
  new MutationObserver(function () { setTimeout(barras, 50); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });

  /* Notificações: logado, o aparelho fica registrado em nome de quem está
     no painel (api/push-aparelho), e todo aviso que vai por e-mail vem
     também por push (app/Push.php). Tocar na notificação abre o endereço
     dela, que é sempre do painel. */
  var P = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.PushNotifications;
  if (!P) { return; }
  P.addListener('pushNotificationActionPerformed', function (a) {
    var u = a && a.notification && a.notification.data && a.notification.data.url;
    if (u && u.indexOf(location.origin + '/') === 0) { location.href = u; }
  });
  var csrf = document.querySelector('#form-sair input[name="csrf"]');
  if (!csrf) { return; }
  try { if (sessionStorage.getItem('inventour.push') === '1') { return; } } catch (e) {}
  P.addListener('registration', function (t) {
    var dados = new FormData();
    dados.append('token', t.value);
    dados.append('csrf', csrf.value);
    fetch('/api/push-aparelho', { method: 'POST', body: dados, credentials: 'same-origin' })
      .then(function (r) { if (r.ok) { try { sessionStorage.setItem('inventour.push', '1'); } catch (e) {} } })
      .catch(function () {});
  });
  P.createChannel({ id: 'inventour', name: 'Avisos do InvenTour', description: 'Curadoria, eventos e os demais avisos do painel', importance: 4 }).catch(function () {});
  P.checkPermissions()
    .then(function (p) { return p.receive === 'granted' ? p : P.requestPermissions(); })
    .then(function (p) { if (p.receive === 'granted') { P.register(); } })
    .catch(function () {});
})();

/*
 * Celular: filtros recolhidos num botão.
 * Softsul Software
 *
 * Na tela estreita cada filtro ocupava uma linha inteira e a lista começava
 * na metade de baixo da tela. Fica à vista só a busca (e as pílulas, que são
 * pequenas); o resto abre no botão Filtros, que mostra quantos estão em uso.
 * No computador nada muda: as classes só valem no @media do inventour.css.
 */
(function () {
  'use strict';
  var NAO_CONTAM = ['p', 'ord', 'dir', 'ver', 'mes', 'parcial'];

  function campoDeBusca(form) {
    return form.querySelector('input[type="search"]') ||
           form.querySelector('.ev-busca input, input[name="q"]');
  }

  function preparar(form) {
    if (form.dataset.recolhe) { return; }
    var busca = campoDeBusca(form);
    var caixaBusca = busca ? (busca.closest('.entrada') || busca) : null;
    var linha = caixaBusca ? caixaBusca.parentElement : (form.querySelector('.ev-filtros-linha') || form);
    // Os filtros: os irmãos da busca e, se ela mora numa linha própria, os
    // outros filhos do formulário (as pílulas também). Campo oculto não conta.
    var extras = [];
    function juntar(el) {
      if (el === caixaBusca || el.tagName === 'INPUT' && el.type === 'hidden') { return; }
      if (el.classList.contains('botao-filtros')) { return; }
      if (el.tagName === 'SCRIPT' || el.tagName === 'DATALIST') { return; }
      extras.push(el);
    }
    Array.prototype.forEach.call(linha.children, juntar);
    if (linha !== form) {
      Array.prototype.forEach.call(form.children, function (el) { if (el !== linha) { juntar(el); } });
    }
    // Só as pílulas (Ativos, Inativos, Todos) cabem numa linha: ficam à vista.
    var reais = extras.filter(function (el) { return !el.classList.contains('limpar-filtros'); });
    var soPilulas = reais.every(function (el) { return el.classList.contains('pilulas-filtro'); });
    if (reais.length < (soPilulas ? 3 : 2)) { return; }
    form.dataset.recolhe = '1';
    form.classList.add('recolhe-filtros');
    linha.classList.add('linha-recolhe');
    extras.forEach(function (el) { el.classList.add('filtro-extra'); });

    var botao = document.createElement('button');
    botao.type = 'button';
    botao.className = 'botao claro botao-filtros';
    botao.setAttribute('aria-expanded', 'false');
    botao.innerHTML = '<svg aria-hidden="true" focusable="false"><use href="#i-filtro"/></svg>Filtros<span class="conta-filtros" hidden></span>';
    if (caixaBusca) { caixaBusca.insertAdjacentElement('afterend', botao); } else { linha.insertBefore(botao, linha.firstChild); }
    botao.addEventListener('click', function () {
      var aberto = form.classList.toggle('filtros-abertos');
      botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });

    var nomeBusca = busca ? busca.name : '';
    function contar() {
      var n = 0, vistos = {};
      new URLSearchParams(location.search).forEach(function (v, k) {
        var base = k.replace(/\[\d*\]$/, '');
        if (v === '' || base === nomeBusca || NAO_CONTAM.indexOf(base) >= 0 || vistos[base]) { return; }
        vistos[base] = 1;
        n++;
      });
      var fixos = (form.getAttribute('data-fixos') || '').split(/\s+/).filter(Boolean);
      fixos.forEach(function (k) { if (vistos[k]) { n--; } });
      var c = botao.querySelector('.conta-filtros');
      c.textContent = n > 0 ? String(n) : '';
      c.hidden = n <= 0;
      botao.classList.toggle('com-filtro', n > 0);
    }
    contar();
    // A lista viva troca o endereço sem recarregar: conta de novo quando ela muda.
    var viva = document.getElementById('lista-viva');
    if (viva && window.MutationObserver) {
      new MutationObserver(function () { setTimeout(contar, 0); }).observe(viva, { childList: true });
    }
    form.addEventListener('change', function () { setTimeout(contar, 400); });
  }

  // Depois do botão Limpar, que é criado por outro bloco no carregamento.
  function iniciar() { document.querySelectorAll('main form.filtros').forEach(preparar); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { setTimeout(iniciar, 0); }
})();

/*
 * Celular: tabela vira cartões.
 * Softsul Software
 *
 * A tabela larga, no celular, cortava o nome na primeira coluna e escondia o
 * resto na rolagem lateral. Quando ela não cabe, cada linha vira um cartão
 * baixo: o nome em cima e os outros dados em seguida, cada um com o título
 * da coluna em letra pequena. As ações ficam no canto. Vale para as tabelas
 * que a lista viva redesenha também.
 */
(function () {
  'use strict';
  var estreita = window.matchMedia('(max-width:640px)');

  function rotular(tabela) {
    var titulos = Array.prototype.map.call(tabela.querySelectorAll('thead th'), function (th) {
      return (th.textContent || '').replace(/\s+/g, ' ').trim();
    });
    tabela.querySelectorAll('tbody tr').forEach(function (tr) {
      var col = 0, principal = null;
      Array.prototype.forEach.call(tr.children, function (td) {
        var t = titulos[col] || '';
        if (t && !td.hasAttribute('data-rotulo')) { td.setAttribute('data-rotulo', t); }
        if (!principal && td.classList.contains('elastica')) { principal = td; }
        col += td.colSpan || 1;
      });
      if (!principal) {
        principal = Array.prototype.find.call(tr.children, function (td) {
          return !td.classList.contains('acao') && !td.querySelector('input[type="checkbox"]') && td.textContent.trim() !== '';
        });
      }
      if (principal) { principal.classList.add('titulo-cartao'); }
      // Célula só com imagem (a capa do evento): miniatura ao lado do cartão.
      Array.prototype.forEach.call(tr.children, function (td) {
        if (td !== principal && td.querySelector('img') && td.textContent.trim() === '') { td.classList.add('miniatura-cartao'); }
        // Só uma pílula (Aprovado, Vencido): ela já diz o que é, sem o título.
        var p = td.querySelector('.pilula');
        if (p && td.textContent.trim() === p.textContent.trim()) { td.classList.add('sem-rotulo'); }
      });
    });
  }

  function ajustar() {
    document.querySelectorAll('main table.dados').forEach(function (t) {
      if (t.closest('[data-sem-cartoes]') || !t.querySelector('thead th')) { return; }
      if (!estreita.matches) { t.classList.remove('em-cartoes'); return; }
      if (t.classList.contains('em-cartoes')) { rotular(t); return; }
      var caixa = t.parentElement;
      // Só a que não cabe: tabela de duas ou três colunas fica como está.
      if (t.querySelectorAll('thead th').length > 3 && t.scrollWidth > caixa.clientWidth + 4) {
        rotular(t);
        t.classList.add('em-cartoes');
      }
    });
  }

  var relogio = null;
  function depois() { clearTimeout(relogio); relogio = setTimeout(ajustar, 60); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ajustar); } else { ajustar(); }
  if (estreita.addEventListener) { estreita.addEventListener('change', ajustar); }
  var main = document.querySelector('main');
  if (main && window.MutationObserver) { new MutationObserver(depois).observe(main, { childList: true, subtree: true }); }
})();

/*
 * App InvenTour Gestão: arrastar o dedo para os lados troca de tela.
 * Softsul Software
 *
 * Para a esquerda vai ao próximo item do menu, para a direita volta ao
 * anterior, na ordem do menu lateral. A tela acompanha o dedo e, ao soltar
 * depois do ponto, desliza para fora; a tela nova entra pelo outro lado.
 * Só no app (o Capacitor acrescenta InvenTourGestao/ ao navegador): no
 * navegador do celular o mesmo gesto já é voltar e avançar a página.
 * Não vale quando o dedo começa em algo que rola de lado, num campo, num
 * modal aberto ou rente à borda (o gesto de voltar do Android).
 */
(function () {
  'use strict';
  if (!/\bInvenTourGestao\//.test(navigator.userAgent)) { return; }
  var main = document.querySelector('main.conteudo');
  var itens = Array.prototype.slice.call(document.querySelectorAll('#itens-rail a.item'));
  if (!main || itens.length < 2) { return; }
  var atual = itens.findIndex(function (a) { return a.classList.contains('ativo'); });
  var calmo = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Entrada da tela nova, pelo lado de onde o dedo veio.
  try {
    var veio = sessionStorage.getItem('it-deslize');
    sessionStorage.removeItem('it-deslize');
    if (veio && !calmo) { main.classList.add('entra-' + veio); }
  } catch (e) { /* sem armazenamento: entra sem efeito */ }

  var dica = document.createElement('div');
  dica.className = 'dica-deslize';
  dica.setAttribute('aria-hidden', 'true');
  document.body.appendChild(dica);

  function rolaDeLado(el) {
    for (; el && el !== document.body; el = el.parentElement) {
      if (el.scrollWidth > el.clientWidth + 2) {
        var o = getComputedStyle(el).overflowX;
        if (o === 'auto' || o === 'scroll') { return true; }
      }
    }
    return false;
  }

  var x0 = 0, y0 = 0, t0 = 0, dx = 0, seguindo = false, decidido = false, alvo = -1;

  document.addEventListener('touchstart', function (ev) {
    seguindo = false; decidido = false; dx = 0;
    if (ev.touches.length !== 1 || document.querySelector('dialog[open]')) { return; }
    var rail = document.getElementById('rail');
    if (rail && rail.classList.contains('aberto')) { return; }
    var t = ev.touches[0];
    if (t.clientX < 24 || t.clientX > window.innerWidth - 24) { return; }
    var el = ev.target;
    if (el.closest('input, textarea, select, [contenteditable], .sugestoes, [data-sem-deslize], .mapa, .leaflet-container, canvas') || rolaDeLado(el)) { return; }
    x0 = t.clientX; y0 = t.clientY; t0 = Date.now();
    seguindo = true;
  }, { passive: true });

  document.addEventListener('touchmove', function (ev) {
    if (!seguindo) { return; }
    var t = ev.touches[0];
    var mx = t.clientX - x0, my = t.clientY - y0;
    if (!decidido) {
      if (Math.abs(mx) < 12 && Math.abs(my) < 12) { return; }
      // Mais vertical que horizontal: é rolagem da página, não troca de tela.
      if (Math.abs(my) > Math.abs(mx) * 0.7) { seguindo = false; return; }
      decidido = true;
      main.classList.add('arrastando');
    }
    dx = mx;
    alvo = dx < 0 ? atual + 1 : atual - 1;
    var existe = alvo >= 0 && alvo < itens.length;
    // Sem tela daquele lado, a tela resiste (anda um terço do dedo).
    var anda = existe ? dx : dx / 3;
    if (!calmo) {
      main.style.transform = 'translateX(' + anda + 'px)';
      main.style.opacity = String(1 - Math.min(Math.abs(anda) / window.innerWidth, 0.6));
    }
    if (existe) {
      dica.textContent = (dx < 0 ? '' : '‹ ') + (itens[alvo].dataset.nome || '') + (dx < 0 ? ' ›' : '');
      dica.className = 'dica-deslize ' + (dx < 0 ? 'direita' : 'esquerda') + (Math.abs(dx) > 40 ? ' visivel' : '');
    } else {
      dica.className = 'dica-deslize';
    }
  }, { passive: true });

  function soltar() {
    if (!seguindo || !decidido) { seguindo = false; return; }
    seguindo = false;
    main.classList.remove('arrastando');
    dica.className = 'dica-deslize';
    var rapido = Math.abs(dx) > 60 && Date.now() - t0 < 300;
    var existe = alvo >= 0 && alvo < itens.length;
    if (existe && (Math.abs(dx) > window.innerWidth * 0.28 || rapido)) {
      var lado = dx < 0 ? 'direita' : 'esquerda';
      try { sessionStorage.setItem('it-deslize', lado); } catch (e) { /* entra sem efeito */ }
      main.classList.add('saindo');
      if (!calmo) {
        main.style.transform = 'translateX(' + (dx < 0 ? -1 : 1) * window.innerWidth + 'px)';
        main.style.opacity = '0';
      }
      setTimeout(function () { location.href = itens[alvo].href; }, calmo ? 0 : 170);
      return;
    }
    main.classList.add('voltando');
    main.style.transform = '';
    main.style.opacity = '';
    setTimeout(function () { main.classList.remove('voltando'); }, 220);
  }
  document.addEventListener('touchend', soltar);
  document.addEventListener('touchcancel', soltar);
  // Voltar pelo botão do Android traz a página do cache já deslocada.
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) { main.classList.remove('saindo'); main.style.transform = ''; main.style.opacity = ''; }
  });
})();

/*
 * Balões próprios no lugar do title do navegador (12/11/2026, no molde do
 * FrameFlow). Softsul Software
 *
 * Vale para todo elemento com title, também os criados depois (listas vivas,
 * modais), e para o <title> dentro de SVG. O title sai do elemento enquanto
 * o mouse está em cima, para o navegador não abrir o dele junto, e volta ao
 * sair. Os gráficos (app/Graficos.php) mandam data-dica-json {t: título,
 * l: [[cor, rótulo, valor, destaque]], r: [[rótulo, valor]]}: esse balão
 * segue o mouse e abre na hora. No menu recolhido, o nome do item vai em
 * negrito em cima da explicação. A cor da série vai pelo CSSOM, que a CSP
 * aceita (atributo style escrito no HTML ela recusa).
 */
(function () {
  if (window.ecDica || !document.addEventListener) { return; }
  window.ecDica = true;
  var SVGNS = 'http://www.w3.org/2000/svg';
  var IGNORAR = /^(IFRAME|HTML|BODY|OPTION|SELECT|OPTGROUP)$/;
  var balao = null, alvo = null, guardado = null, texto = '', rico = false;
  var timer = null, toqueTimer = null, observador = null, ultimoX = 0, ultimoY = 0;

  function tituloSvg(no) {
    if (no.namespaceURI !== SVGNS) { return null; }
    for (var c = no.firstElementChild; c; c = c.nextElementSibling) {
      if (c.tagName && c.tagName.toLowerCase() === 'title') { return c; }
    }
    return null;
  }
  function procurar(no) {
    if (!no || !no.closest || no.closest('.mapa-area, .it-dica')) { return null; }   // o mapa tem cartão próprio
    for (; no && no.nodeType === 1; no = no.parentNode) {
      if (no.hasAttribute('data-dica-json')) { return no; }
      if (IGNORAR.test(no.tagName)) { continue; }
      var t = no.getAttribute('title');
      if (t && t.trim()) { return no; }
      var ts = tituloSvg(no);
      if (ts && (ts.textContent || '').trim()) { return no; }
    }
    return null;
  }
  // Tira o title (ou o <title> do SVG) e devolve o texto; devolver() põe de volta.
  // Os title dos elementos de cima (a linha da tabela, o cartão) saem junto:
  // senão o navegador abre o balão nativo do pai por cima do nosso.
  var dosPais = [];
  function tomarDosPais(no) {
    for (var p = no.parentElement; p && p !== document.body; p = p.parentElement) {
      var tp = p.getAttribute('title');
      if (tp) { dosPais.push([p, tp]); p.removeAttribute('title'); }
    }
  }
  function devolverDosPais() {
    dosPais.forEach(function (par) { if (!par[0].hasAttribute('title')) { par[0].setAttribute('title', par[1]); } });
    dosPais = [];
  }
  function tomar(no) {
    tomarDosPais(no);
    var t = no.getAttribute('title');
    if (t) {
      if (!no.hasAttribute('aria-label') && !(no.textContent || '').trim()) { no.setAttribute('aria-label', t); }
      no.removeAttribute('title');
      guardado = { tipo: 'attr', texto: t };
      return t;
    }
    var ts = tituloSvg(no);
    if (ts) {
      guardado = { tipo: 'svg', no: ts };
      no.removeChild(ts);
      return ts.textContent || '';
    }
    guardado = null;
    return '';
  }
  function devolver(no) {
    devolverDosPais();
    if (!no || !guardado) { return; }
    if (guardado.tipo === 'attr' && !no.hasAttribute('title')) { no.setAttribute('title', guardado.texto); }
    if (guardado.tipo === 'svg') { no.insertBefore(guardado.no, no.firstChild); }
    guardado = null;
  }
  function caixa() {
    if (!balao) {
      balao = document.createElement('div');
      balao.className = 'it-dica';
      balao.id = 'it-dica';
      balao.setAttribute('role', 'tooltip');
    }
    // Janela <dialog> aberta fica na camada de cima: o balão vai junto dela.
    var dlg = alvo && alvo.closest ? alvo.closest('dialog[open]') : null;
    var pai = dlg || document.body;
    if (balao.parentNode !== pai) { pai.appendChild(balao); }
    return balao;
  }
  function cria(tag, cls, txt) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (txt != null) { e.textContent = String(txt); }
    return e;
  }
  function montarRico(b, d) {
    b.textContent = '';
    if (d.t) { b.appendChild(cria('div', 'fd-t', d.t)); }
    (d.l || []).forEach(function (l) {
      var linha = cria('div', 'fd-l' + (l[3] ? ' fd-destaque' : ''));
      var cor = cria('i', l[0] ? '' : 'fd-sem-cor');
      if (l[0]) { cor.style.background = l[0]; }
      linha.appendChild(cor);
      linha.appendChild(cria('span', 'fd-rot', l[1]));
      linha.appendChild(cria('b', 'fd-v', l[2]));
      b.appendChild(linha);
    });
    if (d.r && d.r.length) {
      var pe = cria('div', 'fd-r');
      d.r.forEach(function (r) {
        var linha = cria('div', 'fd-l');
        linha.appendChild(cria('span', 'fd-rot', r[0]));
        linha.appendChild(cria('b', 'fd-v', r[1]));
        pe.appendChild(linha);
      });
      b.appendChild(pe);
    }
  }
  function posicionar() {
    if (!balao || !alvo) { return; }
    var vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
    var w = balao.offsetWidth, h = balao.offsetHeight, x, y;
    balao.classList.remove('em-baixo', 'em-cima', 'em-direita');
    if (!rico && alvo.closest('.rail') && alvo.closest('.item, .ir-inicio')) {
      // Barra lateral: o balão abre à direita do item, sem cobrir o de cima.
      var rr = alvo.getBoundingClientRect();
      x = rr.right + 10; y = rr.top + rr.height / 2 - h / 2;
      var yo = y;
      y = Math.min(Math.max(8, y), vh - h - 8);
      balao.classList.add('em-direita');
      balao.style.setProperty('--seta-y', Math.round(h / 2 + (yo - y)) + 'px');
    } else if (rico) {
      // Ao lado do cursor, sem cobrir o ponto.
      x = ultimoX + 16; y = ultimoY - h - 12;
      if (x + w > vw - 8) { x = ultimoX - w - 16; }
      if (y < 8) { y = ultimoY + 18; }
      if (y + h > vh - 8) { y = vh - h - 8; }
    } else {
      var r = alvo.getBoundingClientRect();
      x = r.left + r.width / 2 - w / 2;
      y = r.top - h - 9;
      balao.classList.add('em-cima');
      if (y < 8) { y = r.bottom + 9; balao.classList.remove('em-cima'); balao.classList.add('em-baixo'); }
      var xo = x;
      x = Math.min(Math.max(8, x), vw - w - 8);
      balao.style.setProperty('--seta-x', Math.round(w / 2 + (xo - x)) + 'px');
    }
    balao.style.left = Math.round(Math.max(8, x)) + 'px';
    balao.style.top = Math.round(Math.max(8, y)) + 'px';
  }
  function abrir() {
    if (!alvo || !alvo.isConnected) { fechar(); return; }
    var b = caixa();
    var json = alvo.getAttribute('data-dica-json');
    rico = false;
    if (json) {
      try { montarRico(b, JSON.parse(json)); rico = true; } catch (e) { fechar(); return; }
    } else {
      if (!texto.trim()) { fechar(); return; }
      b.textContent = texto;
      // Menu recolhido mostra só o ícone: o nome do item vai em cima da explicação.
      var nome = alvo.getAttribute('data-nome');
      if (nome && nome !== texto && alvo.closest('.rail') && document.documentElement.classList.contains('menu-recolhido')) {
        b.textContent = '';
        b.appendChild(cria('div', 'fd-t', nome));
        b.appendChild(document.createTextNode(texto));
      }
      alvo.setAttribute('aria-describedby', 'it-dica');
    }
    b.classList.toggle('fd-rico', rico);
    posicionar();
    b.classList.add('visivel');
  }
  function fechar() {
    clearTimeout(timer); clearTimeout(toqueTimer);
    if (observador) { observador.disconnect(); observador = null; }
    if (alvo) {
      devolver(alvo);
      if (alvo.getAttribute('aria-describedby') === 'it-dica') { alvo.removeAttribute('aria-describedby'); }
    }
    alvo = null; texto = ''; rico = false;
    if (balao) { balao.classList.remove('visivel'); }
  }
  function comecar(novo, imediato) {
    if (novo === alvo) { return; }
    var jaAberto = !!(balao && balao.classList.contains('visivel'));
    fechar();
    alvo = novo;
    if (!novo.hasAttribute('data-dica-json')) {
      // O title sai já, senão o navegador abre o balão dele antes do nosso.
      texto = tomar(novo);
      // Quem troca o title com o mouse em cima (ex.: recolher o menu) atualiza o balão.
      if (window.MutationObserver) {
        observador = new MutationObserver(function () {
          var n = alvo && alvo.getAttribute('title');
          if (!n) { return; }
          alvo.removeAttribute('title');
          guardado = { tipo: 'attr', texto: n };
          texto = n;
          if (balao && balao.classList.contains('visivel')) { balao.textContent = n; posicionar(); }
        });
        observador.observe(novo, { attributes: true, attributeFilter: ['title'] });
      }
    }
    var rapido = imediato || jaAberto || novo.hasAttribute('data-dica-json');
    timer = setTimeout(abrir, rapido ? 60 : 380);
  }

  document.addEventListener('pointerover', function (ev) {
    if (ev.pointerType === 'touch') { return; }
    var novo = procurar(ev.target);
    if (!novo) { if (alvo && !alvo.contains(ev.target)) { fechar(); } return; }
    ultimoX = ev.clientX; ultimoY = ev.clientY;
    comecar(novo, false);
  }, true);
  document.addEventListener('pointermove', function (ev) {
    if (!alvo || ev.pointerType === 'touch') { return; }
    ultimoX = ev.clientX; ultimoY = ev.clientY;
    if (rico) { posicionar(); }
  }, true);
  document.addEventListener('pointerout', function (ev) {
    if (!alvo || ev.pointerType === 'touch') { return; }
    var para = ev.relatedTarget;
    if (!para || !alvo.contains(para)) {
      if ((para ? procurar(para) : null) !== alvo) { fechar(); }
    }
  }, true);
  document.addEventListener('pointerdown', function (ev) {
    if (ev.pointerType !== 'touch') {
      // Clique esconde o balão de texto (a janela que o botão abre não fica por baixo dele).
      // O title continua guardado até o mouse sair, para o do navegador não aparecer.
      if (alvo && !rico) { clearTimeout(timer); if (balao) { balao.classList.remove('visivel'); } }
      return;
    }
    // No toque, só os gráficos abrem balão: um toque mostra, some sozinho em 4 s.
    var g = ev.target && ev.target.closest ? ev.target.closest('[data-dica-json]') : null;
    fechar();
    if (!g) { return; }
    ultimoX = ev.clientX; ultimoY = ev.clientY;
    alvo = g; abrir();
    toqueTimer = setTimeout(fechar, 4000);
  }, true);
  // Teclado: o foco visível mostra o balão do elemento.
  document.addEventListener('focusin', function (ev) {
    var t = ev.target;
    try { if (!t.matches(':focus-visible')) { return; } } catch (e) { return; }
    if (procurar(t) !== t) { return; }
    var r = t.getBoundingClientRect();
    ultimoX = r.left + r.width / 2; ultimoY = r.top;
    comecar(t, false);
  });
  document.addEventListener('focusout', function (ev) { if (alvo && ev.target === alvo) { fechar(); } });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && alvo) { fechar(); } });
  window.addEventListener('scroll', function () { if (alvo && !rico) { fechar(); } }, true);
  window.addEventListener('blur', fechar);
})();

/*
 * Voltar na tela de entrada, dentro do app público.
 * Softsul Software
 *
 * O app InvenTour abre esta tela por "Excluir minha conta", e o iPhone não
 * tem botão de voltar: a pessoa ficava presa. Volta no histórico (para o
 * portal de onde veio); sem histórico, vai à escolha do destino.
 */
(function () {
  'use strict';
  var b = document.querySelector('[data-voltar-app]');
  if (!b || !/\bInvenTourApp\//.test(navigator.userAgent)) { return; }
  b.hidden = false;
  b.addEventListener('click', function (ev) {
    if (history.length > 1) { ev.preventDefault(); history.back(); }
  });
})();

/*
 * Pé do menu: a versão do InvenTour e o menu com os produtos da Softsul.
 *
 * A lista vem de /api/produtos, que repassa a central do Orbit com os ícones
 * embutidos (a CSP do painel só aceita o próprio site), e só é buscada na
 * primeira vez que o menu abre. Os outros produtos abrem numa aba nova, ou no
 * aplicativo deles, se o navegador oferecer. O balão de cada item é a
 * descrição, pelo balão próprio do painel (title). Padrão da família, Luiz,
 * 14/11/2026; referência no Orbit.
 */
(function () {
  'use strict';
  var botao = document.getElementById('btn-produtos');
  var menu = document.getElementById('menu-produtos');
  var lista = document.getElementById('lista-produtos');
  if (!botao || !menu || !lista) { return; }
  var ESTE = 'inventour';
  var carregado = false;
  // O menu mora no body: dentro da barra (sticky, com overflow:hidden) ele
  // sairia cortado, e a faixa de recolher o menu passava por cima.
  document.body.appendChild(menu);

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c];
    });
  }
  function link(u) { return typeof u === 'string' && /^https:\/\//.test(u); }
  function imagem(u) { return typeof u === 'string' && /^data:image\/[a-z.+-]+;base64,[A-Za-z0-9+\/=]+$/.test(u); }

  function desenhar(produtos) {
    var h = '', grupo = null;
    produtos.forEach(function (p) {
      if (grupo !== null && p.grupo !== grupo) { h += '<div class="risco"></div>'; }
      grupo = p.grupo;
      var icone = p.chave === ESTE
        ? '<img src="/assets/icone.svg" alt="" width="26" height="26">'
        : (imagem(p.icone) ? '<img src="' + p.icone + '" alt="" width="26" height="26">' : '<span class="sem-icone"></span>');
      // A versão do próprio vem do botão, que sai do servidor na hora.
      var versao = p.chave === ESTE ? (botao.dataset.versao || p.versao) : p.versao;
      var v = versao ? '<span class="v">' + esc(versao) + '</span>' : '';
      var dica = esc(p.descricao || p.nome);
      if (p.chave === ESTE) {
        h += '<div class="item-produto atual" role="menuitem" aria-current="true" title="' + dica + '">' + icone +
             '<span class="txt"><b>' + esc(p.nome) + '</b><small>Você está aqui</small></span>' + v + '</div>';
      } else if (p.bloqueado) {
        h += '<div class="item-produto em-breve" role="menuitem" aria-disabled="true" title="' + dica + '">' + icone +
             '<span class="txt"><b>' + esc(p.nome) + '</b><small>Somente uso interno da Softsul</small></span>' + v + '</div>';
      } else if (!link(p.login)) {
        h += '<div class="item-produto em-breve" role="menuitem" aria-disabled="true" title="' + dica + '">' + icone +
             '<span class="txt"><b>' + esc(p.nome) + '</b><small>Em breve</small></span>' + v + '</div>';
      } else {
        h += '<a class="item-produto" role="menuitem" href="' + esc(p.login) + '" target="_blank" rel="noopener" title="' + dica + '">' +
             icone + '<span class="txt"><b>' + esc(p.nome) + '</b></span>' + v + '</a>';
      }
    });
    lista.innerHTML = h || '<span class="carregando-produtos">Nenhum produto na lista.</span>';
    lista.querySelectorAll('img').forEach(function (img) {
      img.addEventListener('error', function () {
        var s = document.createElement('span'); s.className = 'sem-icone'; img.replaceWith(s);
      });
    });
  }

  function carregar() {
    if (carregado) { return; }
    carregado = true;
    fetch('/api/produtos', {credentials: 'same-origin'})
      .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
      .then(function (d) { desenhar(d.produtos || []); posicionar(); })
      .catch(function () {
        carregado = false;
        lista.innerHTML = '<span class="carregando-produtos">Não foi possível carregar a lista. Feche e abra o menu para tentar de novo.</span>';
      });
  }

  function posicionar() {
    var r = botao.getBoundingClientRect();
    var esquerda = Math.max(12, Math.min(r.left, window.innerWidth - menu.offsetWidth - 12));
    menu.style.left = esquerda + 'px';
    // Abre para cima; no celular, com o menu aberto, o botão pode ficar no
    // meio da tela: aí a lista ganha a altura que houver e rola por dentro.
    var acima = r.top - 20, abaixo = window.innerHeight - r.bottom - 20;
    if (acima >= 260 || acima >= abaixo) {
      menu.style.top = 'auto';
      menu.style.bottom = Math.max(12, window.innerHeight - r.top + 8) + 'px';
      menu.style.maxHeight = Math.max(160, acima) + 'px';
    } else {
      menu.style.bottom = 'auto';
      menu.style.top = (r.bottom + 8) + 'px';
      menu.style.maxHeight = Math.max(160, abaixo) + 'px';
    }
  }

  function abrir(sim) {
    menu.hidden = !sim;
    botao.setAttribute('aria-expanded', sim ? 'true' : 'false');
    botao.classList.toggle('aberto', sim);
    if (sim) { posicionar(); carregar(); }
  }
  botao.addEventListener('click', function (ev) { ev.stopPropagation(); abrir(menu.hidden); });
  document.addEventListener('click', function (ev) {
    if (!menu.hidden && !menu.contains(ev.target)) { abrir(false); }
  });
  // No celular a barra rola junto com a página: o menu fixo ficaria solto.
  window.addEventListener('scroll', function () { if (!menu.hidden) { posicionar(); } }, {passive: true});
  menu.addEventListener('click', function (ev) { if (ev.target.closest('a.item-produto')) { abrir(false); } });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !menu.hidden) { abrir(false); botao.focus(); }
  });
  window.addEventListener('resize', function () { if (!menu.hidden) { posicionar(); } });
})();

/*
 * Pós-evento: o impacto estimado aparece enquanto se digita o público real,
 * com a mesma conta de app/Impactos.php (calcular). Softsul Software
 */
(function () {
  'use strict';
  var caixa = document.querySelector('[data-impacto-previa]');
  var real = document.getElementById('publico_real');
  if (!caixa || !real) { return; }
  var d = caixa.dataset;
  var num = function (id) { var c = document.getElementById(id); return c && c.value.replace(/\D/g, '') !== '' ? parseInt(c.value.replace(/\D/g, ''), 10) : null; };
  var reais = function (v) { return v.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}); };
  var dica = document.querySelector('[data-impacto-dica]');
  var conta = function () {
    var r = num('publico_real');
    if (r === null) { caixa.hidden = true; if (dica) { dica.hidden = false; } return; }
    var t = num('real_turistas'), x = num('real_excursionistas'), s = num('real_residentes');
    var razao = parseFloat(d.razao), gasto = parseFloat(d.gasto), gExc = parseFloat(d.gastoExc), gRes = parseFloat(d.gastoRes);
    var injecao = (t !== null && x !== null && s !== null)
      ? t * gasto + x * gExc + s * gRes
      : r * razao / 100 * gasto + r * (100 - razao) / 100 * gRes;
    injecao = Math.round(injecao * 100) / 100;
    var esperado = parseInt(d.esperado, 10) || 0;
    caixa.querySelector('[data-previa=acuracia]').textContent = esperado > 0 ? Math.round(r / esperado * 100).toLocaleString('pt-BR') + '%' : '-';
    caixa.querySelector('[data-previa=injecao]').textContent = reais(injecao);
    caixa.querySelector('[data-previa=iss]').textContent = reais(Math.round(injecao * 0.30 * parseFloat(d.aliquota) / 100) / 100);
    caixa.hidden = false;
    if (dica) { dica.textContent = 'Prévia: salve para registrar o público real.'; }
  };
  document.addEventListener('input', function (ev) {
    if (ev.target && /^(publico_real|real_turistas|real_excursionistas|real_residentes)$/.test(ev.target.id)) { conta(); }
  });
  conta();
})();

/* ==========================================================================
   Editor de texto das narrativas do destino (O destino). A área é
   contenteditable; a barra usa execCommand (negrito, itálico, título,
   listas, limpar). Ao enviar, o HTML vai para o textarea oculto, e o
   servidor limpa (app/HtmlLimpo.php): o que vier colado do Word perde estilo,
   imagem e script lá. Também alterna os campos de data do marco da linha do
   tempo conforme a precisão (dia, mês, ano).
   ========================================================================== */
(function () {
  'use strict';
  document.querySelectorAll('[data-editor]').forEach(function (ed) {
    var area = ed.querySelector('.editor-area');
    var campo = ed.querySelector('textarea[name="conteudo"]');
    if (!area || !campo) { return; }
    ed.querySelectorAll('[data-cmd]').forEach(function (b) {
      b.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
      b.addEventListener('click', function () {
        area.focus();
        document.execCommand(b.getAttribute('data-cmd'), false, b.getAttribute('data-valor') || null);
      });
    });
    var form = ed.closest('form');
    if (form) {
      form.addEventListener('submit', function () { campo.value = area.innerHTML; });
    }
  });
  document.querySelectorAll('[data-precisao]').forEach(function (sel) {
    var form = sel.closest('form');
    function mostra() {
      form.querySelectorAll('[data-quando]').forEach(function (c) {
        c.hidden = c.getAttribute('data-quando') !== sel.value;
      });
    }
    sel.addEventListener('change', mostra);
    mostra();
  });
})();

/* ==========================================================================
   Funcionamento do item (item.php): a chave de cada dia limpa ou devolve as
   horas (o servidor entende dia sem horas como fechado), "24 horas" esconde
   a semana e os atalhos copiam a segunda para os outros dias.
   ========================================================================== */
(function () {
  'use strict';
  document.querySelectorAll('[data-semana]').forEach(function (semana) {
    var dias = Array.prototype.slice.call(semana.querySelectorAll('.dia'));
    function horas(dia) { return dia.querySelectorAll('input[type=time]'); }
    function abrir(dia, aberto, valores) {
      var h = horas(dia);
      var chave = dia.querySelector('[data-aberto]');
      chave.checked = aberto;
      dia.classList.toggle('fechado', !aberto);
      if (aberto) {
        var v = valores || [dia.getAttribute('data-abre') || '08:00', dia.getAttribute('data-fecha') || '18:00'];
        h[0].value = v[0];
        h[1].value = v[1];
      } else {
        if (h[0].value) { dia.setAttribute('data-abre', h[0].value); }
        if (h[1].value) { dia.setAttribute('data-fecha', h[1].value); }
        h[0].value = '';
        h[1].value = '';
      }
    }
    dias.forEach(function (dia) {
      var chave = dia.querySelector('[data-aberto]');
      chave.addEventListener('change', function () {
        abrir(dia, chave.checked);
        if (chave.checked) { horas(dia)[0].focus(); }
      });
    });
    var h24 = semana.querySelector('[data-h24]');
    if (h24) {
      h24.addEventListener('change', function () { semana.classList.toggle('dia-todo', h24.checked); });
    }
    semana.querySelectorAll('[data-copiar]').forEach(function (b) {
      b.addEventListener('click', function () {
        var seg = dias[0];
        var h = horas(seg);
        var aberto = seg.querySelector('[data-aberto]').checked && h[0].value && h[1].value;
        var alvo = b.getAttribute('data-copiar') === 'uteis' ? dias.slice(1, 5) : dias.slice(1);
        alvo.forEach(function (dia) { abrir(dia, !!aberto, aberto ? [h[0].value, h[1].value] : null); });
      });
    });
  });
})();

/*
 * Visualizador de foto e PDF, em modal (regra do Luiz, 20/11/2026: foto e
 * PDF abrem em modal, sempre).
 * Softsul Software
 *
 * Qualquer link com data-visualizar ("foto" ou "pdf") abre por cima da tela,
 * nunca em aba nova nem baixando. Links com o mesmo data-galeria andam com
 * as setas (botões e teclado); data-legenda vai embaixo. Esc fecha.
 */
(function () {
  'use strict';
  var caixa = null;
  var grupo = [];
  var atual = 0;

  function montar() {
    caixa = document.createElement('dialog');
    caixa.className = 'visualizador';
    caixa.setAttribute('aria-label', 'Visualizar');
    caixa.innerHTML = '<div class="vis-topo"><span class="vis-titulo"></span><span class="vis-conta"></span>'
      + '<button type="button" class="vis-fechar" aria-label="Fechar"><svg aria-hidden="true"><use href="#i-fechar"></use></svg></button></div>'
      + '<div class="vis-palco"><button type="button" class="vis-seta vis-anterior" aria-label="Anterior"><svg aria-hidden="true"><use href="#i-seta"></use></svg></button>'
      + '<div class="vis-conteudo"></div>'
      + '<button type="button" class="vis-seta vis-proxima" aria-label="Próxima"><svg aria-hidden="true"><use href="#i-seta"></use></svg></button></div>'
      + '<p class="vis-legenda"></p>';
    document.body.appendChild(caixa);
    caixa.querySelector('.vis-fechar').addEventListener('click', function () { caixa.close(); });
    caixa.querySelector('.vis-anterior').addEventListener('click', function () { mostrar(atual - 1); });
    caixa.querySelector('.vis-proxima').addEventListener('click', function () { mostrar(atual + 1); });
    caixa.addEventListener('click', function (ev) { if (ev.target === caixa || ev.target.classList.contains('vis-palco')) { caixa.close(); } });
    caixa.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowLeft') { mostrar(atual - 1); }
      if (ev.key === 'ArrowRight') { mostrar(atual + 1); }
    });
    caixa.addEventListener('close', function () { caixa.querySelector('.vis-conteudo').innerHTML = ''; });
  }

  function mostrar(i) {
    if (!grupo.length) { return; }
    atual = (i + grupo.length) % grupo.length;
    var el = grupo[atual];
    var tipo = el.getAttribute('data-visualizar') || (/\.pdf($|\?)/i.test(el.href) ? 'pdf' : 'foto');
    var conteudo = caixa.querySelector('.vis-conteudo');
    conteudo.innerHTML = '';
    if (tipo === 'pdf') {
      var quadro = document.createElement('iframe');
      quadro.src = el.href;
      quadro.title = el.getAttribute('data-legenda') || 'Documento';
      conteudo.appendChild(quadro);
      caixa.classList.add('vis-pdf');
    } else {
      var img = document.createElement('img');
      img.src = el.href;
      img.alt = el.getAttribute('data-legenda') || '';
      conteudo.appendChild(img);
      caixa.classList.remove('vis-pdf');
    }
    var legenda = el.getAttribute('data-legenda') || '';
    caixa.querySelector('.vis-legenda').textContent = legenda;
    caixa.querySelector('.vis-legenda').hidden = !legenda;
    caixa.querySelector('.vis-titulo').textContent = el.getAttribute('data-titulo') || (tipo === 'pdf' ? 'Documento' : 'Foto');
    caixa.querySelector('.vis-conta').textContent = grupo.length > 1 ? (atual + 1) + ' de ' + grupo.length : '';
    caixa.querySelectorAll('.vis-seta').forEach(function (b) { b.hidden = grupo.length < 2; });
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target.closest && ev.target.closest('a[data-visualizar]');
    if (!el || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) { return; }
    ev.preventDefault();
    if (!caixa) { montar(); }
    var nome = el.getAttribute('data-galeria');
    grupo = nome ? Array.prototype.slice.call(document.querySelectorAll('a[data-visualizar][data-galeria="' + nome + '"]')) : [el];
    mostrar(Math.max(0, grupo.indexOf(el)));
    if (!caixa.open) { caixa.showModal(); }
  });
})();

/*
 * Barra de abas sempre visível (regra geral do Luiz, 20/11/2026): ao rolar,
 * ela gruda logo abaixo do cabeçalho fixo da tela ou da ficha em modal. A
 * altura do cabeçalho muda (aviso, esteira, quebra de linha), então é
 * medida e passada ao CSS em --topo-abas.
 * Softsul Software
 */
(function () {
  'use strict';
  var barras = document.querySelectorAll('.abas-chamado:not(.abas-coleta)');
  if (!barras.length) { return; }
  function medir() {
    barras.forEach(function (barra) {
      var cab = document.querySelector('main .cab-pag');
      var topo = 0;
      if (cab) {
        var cs = getComputedStyle(cab);
        if (cs.position === 'sticky' || cs.position === 'fixed') { topo = (parseFloat(cs.top) || 0) + cab.offsetHeight; }
      }
      barra.style.setProperty('--topo-abas', topo + 'px');
    });
  }
  medir();
  window.addEventListener('resize', medir);
  if (window.ResizeObserver) {
    var cab = document.querySelector('main .cab-pag');
    if (cab) { new ResizeObserver(medir).observe(cab); }
  }
})();

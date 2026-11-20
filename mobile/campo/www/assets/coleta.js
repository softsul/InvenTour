/*
 * Coleta de campo: lista, ficha em abas, fila no aparelho e Sincronizar.
 * Softsul Software
 *
 * A mesma tela roda no painel (/coleta) e no app InvenTour Campo
 * (mobile/campo). No app, o campo.js define window.INVENTOUR_CAMPO antes
 * deste arquivo: endereço do servidor, token do aparelho, câmera e GPS
 * nativos. No painel vale a sessão e o token CSRF.
 *
 * Nada sobe sozinho: gravar guarda no aparelho (IndexedDB, separado por
 * destino e por pessoa), e o botão Sincronizar testa a conexão, manda a
 * fila na ordem (salvar, fotos, enviar à conferência) e baixa a lista nova.
 * Cada passo leva um uuid: repetir depois de uma queda não duplica nada.
 */
(function () {
  'use strict';
  var raiz = document.getElementById('coleta');
  if (!raiz || !window.indexedDB) { return; }

  // As janelas da coleta saem de dentro das abas: modal aberto dentro de
  // uma aba escondida fica invisível e trava a página inteira (o Histórico,
  // aberto da aba Aplicativo, ficava dentro da aba Coleta).
  document.querySelectorAll('dialog.coleta-dialogo').forEach(function (d) { document.body.appendChild(d); });

  var APP = window.INVENTOUR_CAMPO || null;
  var API = APP ? APP.servidor : '';
  var csrf = raiz.getAttribute('data-csrf') || '';
  var NOME_BANCO = 'inventour-coleta-' + (APP ? APP.chave : raiz.getAttribute('data-chave'));
  var POR_PAGINA = 60;
  var $ = function (id) { return document.getElementById(id); };
  var base = null;
  var filaAtual = [];
  var sincronizando = false;
  var mostrar = POR_PAGINA;
  var atual = null;
  var fotosNovas = [];      // {uuid, blob, nome, url, lat, lng, precisao}
  var posicao = null;       // [lat, lng, precisão, quando]
  var situacao = 'todos';   // filtro: todos, devolvidos, pre_cadastro, em_campo, aparelho
  var SITUACOES = [['todos', 'Todos'], ['devolvidos', 'Devolvidos'], ['pre_cadastro', 'Pré-cadastro'], ['em_campo', 'Em campo'], ['aparelho', 'No aparelho']];

  // ------------------------------------------------------------ IndexedDB
  var bancoPromessa = new Promise(function (ok, falha) {
    var req = indexedDB.open(NOME_BANCO, 1);
    req.onupgradeneeded = function () {
      var db = req.result;
      db.createObjectStore('base');
      db.createObjectStore('fila', { keyPath: 'uuid' }).createIndex('ordem', 'ordem');
      db.createObjectStore('fotos', { keyPath: 'uuid' }).createIndex('coleta', 'coleta');
    };
    req.onsuccess = function () {
      var db = req.result;
      // Outra aba (ou uma versão nova) pediu o banco: solta, senão ela fica
      // esperando para sempre. A tela recarrega para reabrir.
      db.onversionchange = function () { db.close(); setTimeout(function () { location.reload(); }, 300); };
      ok(db);
    };
    req.onerror = function () { falha(req.error); };
    req.onblocked = function () { avisarBancoPreso(); };
  });
  // Banco que não abre em 6 s: quase sempre outra aba da coleta segurando.
  var relogioBanco = setTimeout(avisarBancoPreso, 6000);
  bancoPromessa.then(function () { clearTimeout(relogioBanco); }, function () { clearTimeout(relogioBanco); avisarBancoPreso(); });
  function avisarBancoPreso() {
    var caixa = document.getElementById('col-erros');
    if (!caixa) { return; }
    caixa.hidden = false;
    caixa.innerHTML = '<svg aria-hidden="true"><use href="#i-alerta"></use></svg><span>O armazenamento deste navegador não respondeu. '
      + 'Feche as outras abas ou janelas com a Coleta de campo e recarregue esta página.</span>';
  }
  function tx(loja, modo, fazer) {
    return bancoPromessa.then(function (db) {
      return new Promise(function (ok, falha) {
        var t = db.transaction(loja, modo);
        var req = fazer(t.objectStore(loja));
        t.oncomplete = function () { ok(req ? req.result : undefined); };
        t.onerror = function () { falha(t.error); };
        t.onabort = function () { falha(t.error); };
      });
    });
  }
  function ler(loja, chave) { return tx(loja, 'readonly', function (s) { return s.get(chave); }); }
  function gravar(loja, valor, chave) { return tx(loja, 'readwrite', function (s) { return chave !== undefined ? s.put(valor, chave) : s.put(valor); }); }
  function apagar(loja, chave) { return tx(loja, 'readwrite', function (s) { return s['delete'](chave); }); }
  function todos(loja) { return tx(loja, 'readonly', function (s) { return s.getAll(); }); }
  function fotosDa(coleta) { return tx('fotos', 'readonly', function (s) { return s.index('coleta').getAll(coleta); }); }

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    var b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
    var h = Array.prototype.map.call(b, function (x) { return (x + 256).toString(16).slice(1); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }
  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function icone(nome) { return '<svg aria-hidden="true" focusable="false"><use href="#i-' + nome + '"></use></svg>'; }
  function semAcento(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

  // Telefone: o banco guarda 55 + DDD + número; a tela mostra (46) 3220-0000.
  function telefoneLegivel(v) {
    var d = String(v || '').replace(/\D/g, '');
    if (d.length >= 12 && d.indexOf('55') === 0) { d = d.slice(2); }
    if (d.length === 11) { return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7); }
    if (d.length === 10) { return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6); }
    return String(v || '');
  }
  function soDigitosTel(v) {
    var d = String(v || '').replace(/\D/g, '');
    return d.length >= 12 && d.indexOf('55') === 0 ? d.slice(2) : d;
  }

  // ------------------------------------------------------------ servidor
  function cabecalhos(json) {
    var h = {};
    if (APP) { h['X-Aparelho'] = APP.token(); } else { h['X-CSRF'] = csrf; }
    if (json) { h['Content-Type'] = 'application/json'; }
    return h;
  }
  function pedir(caminho, opcoes, limiteMs) {
    var ctrl = window.AbortController ? new AbortController() : null;
    var relogio = ctrl ? setTimeout(function () { ctrl.abort(); }, limiteMs || 60000) : null;
    opcoes = Object.assign({ credentials: APP ? 'omit' : 'same-origin', signal: ctrl ? ctrl.signal : undefined }, opcoes || {});
    return fetch(API + caminho, opcoes).then(function (r) {
      if (relogio) { clearTimeout(relogio); }
      if (r.status === 401) { avisarSessao(); throw new Error('sessao'); }
      return r;
    }, function (e) { if (relogio) { clearTimeout(relogio); } throw e; });
  }
  function avisarSessao() {
    var t = APP ? 'Este aparelho foi desconectado (ou a senha mudou). Nada se perdeu: gere um código novo no painel, em Coleta de campo, e conecte de novo.'
      : 'Sua sessão terminou. Nada se perdeu: entre de novo no painel e volte aqui para sincronizar.';
    $('col-sessao-texto').textContent = t;
    $('col-sessao').hidden = false;
    if (APP && APP.desconectado) { APP.desconectado(); }
  }
  // A conexão de verdade: o servidor respondeu, e em quanto tempo. O
  // "online" do aparelho só diz que há wi-fi ou dados ligados, não que a
  // internet chega ao InvenTour.
  var conexao = { estado: null, ms: null, quando: null };
  function testarConexao() {
    var inicio = Date.now();
    return pedir('/api/coleta?ping=1', { headers: cabecalhos(false), cache: 'no-store' }, 8000)
      .then(function (r) {
        conexao = { estado: r.ok ? 'ok' : 'falhou', ms: Date.now() - inicio, quando: Date.now() };
        if (r.ok && APP && APP.exigirAtualizacao) {
          r.clone().json().then(function (d) { if (d && d.app) { APP.exigirAtualizacao(d.app); } }).catch(function () {});
        }
        return r.ok;
      }).catch(function (e) {
        if (e && e.message === 'sessao') { throw e; }
        conexao = { estado: 'falhou', ms: null, quando: Date.now() };
        return false;
      });
  }
  function medirConexao() {
    if (!navigator.onLine) { conexao = { estado: 'sem-rede', ms: null, quando: Date.now() }; mostrarEstado(); return Promise.resolve(false); }
    return testarConexao().then(function (ok) { mostrarEstado(); return ok; }, function () { mostrarEstado(); return false; });
  }
  function textoConexao() {
    if (!navigator.onLine || conexao.estado === 'sem-rede') { return ['Sem internet no aparelho', 'offline', 'O aparelho está sem wi-fi e sem dados móveis.'] ; }
    if (conexao.estado === 'ok') {
      var lenta = conexao.ms > 2500;
      return [(lenta ? 'Servidor lento' : 'Servidor respondeu') + ' · ' + (conexao.ms < 1000 ? conexao.ms + ' ms' : (conexao.ms / 1000).toFixed(1).replace('.', ',') + ' s'),
        lenta ? 'lenta' : 'online', 'Teste feito às ' + new Date(conexao.quando).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) + ': o InvenTour respondeu em ' + conexao.ms + ' milissegundos.'];
    }
    if (conexao.estado === 'falhou') { return ['Sem resposta do servidor', 'offline', 'Há rede ligada, mas o InvenTour não respondeu em 8 segundos (sinal fraco ou internet sem saída).']; }
    return ['Testando a conexão', 'testando', ''];
  }
  function baixarLista() {
    return pedir('/api/coleta', { headers: cabecalhos(false), cache: 'no-store' }, 60000)
      .then(function (r) { return r.ok && !r.redirected ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.ok) { return false; }
        if (d.csrf) { csrf = d.csrf; }
        $('col-sessao').hidden = true;
        return gravar('base', d, 'dados').then(function () { return true; });
      });
  }
  function postar(caminho, corpo, ehForm) {
    var mandar = function () {
      return pedir(caminho, { method: 'POST', headers: cabecalhos(!ehForm), body: ehForm ? corpo : JSON.stringify(corpo) }, 120000);
    };
    return mandar().then(function (r) {
      // Painel: token CSRF velho (a tela veio do cache) pega um novo e tenta uma vez.
      if (r.status === 403 && !APP) { return baixarLista().then(mandar); }
      return r;
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, motivo: 'resposta' }; }).then(function (j) { j.status = r.status; return j; });
    });
  }

  // ------------------------------------------------------------ estado e lista
  function mostrarEstado(texto, classe) {
    var sinal = $('col-sinal');
    var medida = textoConexao();
    sinal.className = 'sinal ' + (classe || medida[1]);
    sinal.querySelector('span').textContent = texto || medida[0];
    sinal.title = texto ? '' : medida[2];
    var mc = $('campo-menu-conta');
    if (mc) { mc.textContent = String(filaAtual.length); mc.hidden = !filaAtual.length; }
    var n = filaAtual.length;
    var nFotos = filaAtual.reduce(function (s, c) { return s + (c.fotos || 0); }, 0);
    $('col-fila').textContent = n ? (n === 1 ? '1 gravação' : n + ' gravações') + (nFotos ? ' e ' + (nFotos === 1 ? '1 foto' : nFotos + ' fotos') : '') + ' no aparelho'
      : 'Tudo sincronizado';
    $('col-fila').classList.toggle('pendente', n > 0);
    var b = $('col-sincronizar');
    b.classList.toggle('com-fila', n > 0);
    b.querySelector('span').textContent = sincronizando ? 'Sincronizando...' : (n ? 'Sincronizar (' + n + ')' : 'Sincronizar');
    b.disabled = sincronizando;
    $('col-quando').textContent = base && base.em ? 'Lista de ' + new Date(base.em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'Lista ainda não baixada';
    var erros = filaAtual.filter(function (c) { return c.erro; });
    var caixa = $('col-erros');
    caixa.hidden = !erros.length;
    if (erros.length) {
      caixa.innerHTML = icone('alerta') + '<span>' + erros.map(function (c) {
        return '<b>' + esc(c.nome || 'Item') + ':</b> ' + esc(c.erro) + ' <button type="button" class="como-link" data-descartar="' + esc(c.uuid) + '">descartar</button>';
      }).join('<br>') + '</span>';
    }
  }

  function fichaPorId(id) { return (base && base.fichas || []).filter(function (f) { return f.id === id; })[0] || null; }

  // Os itens com o que ainda está no aparelho por cima.
  function itensComFila() {
    var mapa = {};
    var lista = (base && base.itens || []).map(function (i) {
      var copia = Object.assign({}, i, { chave: 'i' + i.id });
      mapa[copia.chave] = copia;
      return copia;
    });
    filaAtual.slice().sort(function (a, b) { return a.ordem - b.ordem; }).forEach(function (c) {
      var chave = c.item_id ? 'i' + c.item_id : 'n' + c.local_id;
      var alvo = mapa[chave] || mapa['n' + c.local_id];
      if (!alvo) {
        alvo = { id: c.item_id || null, local_id: c.local_id, chave: chave, ficha_id: c.ficha_id, situacao: 'em_campo', fotos: 0, nome: '' };
        mapa[chave] = alvo;
        lista.unshift(alvo);
      }
      alvo.f = mesclarFicha(alvo.f || fichaDoItemAntigo(alvo), c.dados);
      ['nome', 'logradouro', 'numero', 'bairro'].forEach(function (k) { if (c.dados && k in c.dados) { alvo[k] = c.dados[k]; } });
      if (c.dados && c.dados.latitude) { alvo.latitude = parseFloat(c.dados.latitude); alvo.longitude = parseFloat(c.dados.longitude); }
      alvo.local_id = c.local_id;
      alvo.na_fila = true;
      alvo.fotos_fila = (alvo.fotos_fila || 0) + (c.fotos || 0);
      if (c.enviar) { alvo.vai_enviar = true; }
    });
    return lista;
  }

  // A ficha do item (formato do formulário). A lista baixada por servidor
  // antigo não tinha f: as colunas soltas servem.
  function fichaDoItemAntigo(i) {
    var f = {};
    ['nome', 'tipo_id', 'zona', 'logradouro', 'numero', 'bairro', 'cep', 'referencia', 'plus_code', 'telefone', 'whatsapp', 'instagram', 'site',
      'conservacao', 'latitude', 'longitude'].forEach(function (k) { if (i[k] != null && i[k] !== '') { f[k] = String(i[k]); } });
    return f;
  }
  // O que está no aparelho por cima do que veio do servidor. Os campos da
  // ficha, a acessibilidade e as horas trocam um a um; o resto, inteiro.
  var POR_PARTE = ['at', 'aus', 'acess', 'h_abre', 'h_fecha'];
  function mesclarFicha(f, dados) {
    var r = JSON.parse(JSON.stringify(f || {}));
    Object.keys(dados || {}).forEach(function (k) {
      if (k === 'nota') { return; }
      if (POR_PARTE.indexOf(k) !== -1 && dados[k] && typeof dados[k] === 'object' && !Array.isArray(dados[k])) {
        r[k] = Object.assign({}, r[k] || {}, dados[k]);
      } else {
        r[k] = dados[k];
      }
    });
    return r;
  }

  function naSituacao(i, qual) {
    if (qual === 'devolvidos') { return !!i.devolucao_nota && !i.na_fila; }
    if (qual === 'pre_cadastro') { return i.situacao === 'pre_cadastro' && !i.na_fila; }
    if (qual === 'em_campo') { return i.situacao === 'em_campo' && !i.devolucao_nota && !i.na_fila; }
    if (qual === 'aparelho') { return !!i.na_fila; }
    return true;
  }

  function desenharSituacoes(todosItens) {
    var caixa = $('col-situacoes');
    caixa.innerHTML = SITUACOES.map(function (s) {
      var n = todosItens.filter(function (i) { return naSituacao(i, s[0]); }).length;
      var classe = 'situacao-ficha' + (situacao === s[0] ? ' ativa' : '') + (s[0] === 'devolvidos' && n ? ' alerta' : '');
      return '<button type="button" class="' + classe + '" data-situacao="' + s[0] + '" aria-pressed="' + (situacao === s[0]) + '">'
        + esc(s[1]) + '<span>' + n + '</span></button>';
    }).join('');
    var devolvidos = todosItens.filter(function (i) { return naSituacao(i, 'devolvidos'); }).length;
    var aviso = $('col-aviso-devolvidos');
    aviso.hidden = !devolvidos || situacao === 'devolvidos';
    if (devolvidos) {
      aviso.querySelector('span').innerHTML = '<b>' + (devolvidos === 1 ? '1 item devolvido' : devolvidos + ' itens devolvidos') + ' pela conferência.</b> '
        + '<button type="button" class="como-link" data-situacao="devolvidos">Ver o que acertar</button>';
    }
  }

  function desenharLista() {
    var busca = semAcento($('col-busca').value);
    var ficha = parseInt($('col-ficha-filtro').value, 10) || 0;
    var todosItens = itensComFila();
    desenharSituacoes(todosItens);
    var itens = todosItens.filter(function (i) {
      if (ficha && i.ficha_id !== ficha) { return false; }
      if (!naSituacao(i, situacao)) { return false; }
      return !busca || semAcento([i.nome, i.logradouro, i.bairro].join(' ')).indexOf(busca) !== -1;
    });
    // Devolvidos primeiro, depois o que está no aparelho, depois por nome.
    var peso = function (i) { return i.devolucao_nota && !i.na_fila ? 0 : (i.na_fila ? 1 : 2); };
    itens.sort(function (a, b) { return peso(a) - peso(b) || String(a.nome || '').localeCompare(String(b.nome || ''), 'pt-BR'); });
    $('col-lista').innerHTML = itens.slice(0, mostrar).map(function (i) {
      var f = fichaPorId(i.ficha_id);
      var selo = i.na_fila ? '<span class="pilula at">' + (i.vai_enviar ? 'vai à conferência' : 'no aparelho') + '</span>'
        : (i.devolucao_nota ? '<span class="pilula ruim">devolvido</span>'
        : (i.situacao === 'pre_cadastro' ? '<span class="pilula nu">pré-cadastro</span>' : '<span class="pilula at">em campo</span>'));
      var onde = [i.logradouro, i.numero, i.bairro].filter(Boolean).join(', ');
      var fotos = (i.fotos || 0) + (i.fotos_fila || 0);
      return '<li><button type="button" class="coleta-item' + (i.na_fila ? ' na-fila' : '') + '" data-chave="' + esc(i.chave) + '">'
        + '<span class="ficha-codigo">' + esc(f ? f.codigo : '') + '</span>'
        + '<span class="coleta-item-txt"><b>' + esc(i.nome || 'Sem nome') + '</b>'
        + '<span>' + esc(onde || 'Sem endereço') + '</span>'
        + (i.devolucao_nota && !i.na_fila ? '<span class="devolucao">' + esc(i.devolucao_nota) + '</span>' : '') + '</span>'
        + '<span class="coleta-item-sinais">' + selo
        + '<span class="mini-sinais">' + (i.latitude != null ? icone('mapa') : '') + (fotos ? icone('camera') + fotos : '') + '</span></span>'
        + '</button></li>';
    }).join('');
    $('col-conta').textContent = itens.length ? (itens.length === 1 ? '1 item' : itens.length + ' itens') : '';
    $('col-mais').hidden = itens.length <= mostrar;
    $('col-vazio').hidden = itens.length > 0;
    if (!itens.length) {
      $('col-vazio-titulo').textContent = base ? 'Nenhum item aqui.' : 'Ainda sem a lista neste aparelho.';
      $('col-vazio-texto').textContent = base ? 'Ajuste a busca, ou cadastre um item novo.' : 'Toque em Sincronizar com sinal para baixar a lista do destino.';
    }
  }

  function desenharFichas() {
    var busca = semAcento($('col-fichas-busca').value);
    $('col-fichas').innerHTML = (base ? base.fichas : []).filter(function (f) {
      return !busca || semAcento(f.codigo + ' ' + f.nome + ' ' + f.tipos.map(function (t) { return t.nome; }).join(' ')).indexOf(busca) !== -1;
    }).map(function (f) {
      return '<li><button type="button" class="como-link" data-nova-ficha="' + f.id + '"><b class="dado">' + esc(f.codigo) + '</b> ' + esc(f.nome) + '</button></li>';
    }).join('');
  }

  function preencherFiltroDeFichas() {
    var sel = $('col-ficha-filtro');
    var valor = sel.value;
    var usadas = {};
    (base.itens || []).forEach(function (i) { usadas[i.ficha_id] = true; });
    sel.innerHTML = '<option value="">Todas as fichas</option>' + base.fichas.filter(function (f) { return usadas[f.id]; }).map(function (f) {
      return '<option value="' + f.id + '">' + esc(f.codigo + ' ' + f.nome) + '</option>';
    }).join('');
    sel.value = valor;
    desenharFichas();
    var novo = $('col-novo');
    if (novo) { novo.hidden = !base.pode_criar || !base.fichas.length; }
  }

  function recarregarTela() {
    return Promise.all([ler('base', 'dados'), todos('fila')]).then(function (r) {
      base = r[0] || null;
      filaAtual = r[1] || [];
      if (base) { preencherFiltroDeFichas(); }
      if (APP && APP.mostrarQuem && base) { APP.mostrarQuem(base.usuario, base.destino); }
      mostrarEstado();
      desenharLista();
    });
  }

  // ------------------------------------------------------------ sincronizar
  var MOTIVOS = {
    situacao: 'o item já saiu da coleta (enviado ou homologado por outra pessoa). A gravação não foi aplicada.',
    item: 'o item não existe mais ou saiu do seu escopo.',
    ficha: 'a ficha saiu do seu escopo.',
    permissao: 'você não tem mais permissão para coletar neste destino.',
    nome: 'falta o nome.'
  };

  function progresso(feito, total, texto) {
    var caixa = $('col-progresso');
    caixa.hidden = total === null;
    if (total === null) { return; }
    caixa.querySelector('i').style.width = (total ? Math.round(feito / total * 100) : 100) + '%';
    caixa.querySelector('span').textContent = texto;
  }

  function sincronizar() {
    if (sincronizando) { return Promise.resolve(); }
    sincronizando = true;
    $('col-resultado').hidden = true;
    mostrarEstado('Testando a conexão', 'testando');
    progresso(0, 1, 'Testando a conexão com o servidor...');
    var enviadas = 0;
    var fotosEnviadas = 0;
    var falhou = false;
    // O que esta sincronização fez, para o Histórico (fica no aparelho).
    registro = { quando: Date.now(), itens: [], resultado: '', tom: '' };
    return testarConexao().then(function (ok) {
      if (!ok) {
        falhou = true;
        mostrarEstado();
        progresso(0, null);
        mostrarResultado('Sem conexão com o servidor agora. Os dados continuam no aparelho; tente de novo com sinal.', 'atencao');
        return null;
      }
      mostrarEstado();
      return todos('fila').then(function (fila) {
        fila.sort(function (a, b) { return a.ordem - b.ordem; });
        var pendentes = fila.filter(function (c) { return !c.erro; });
        var total = pendentes.reduce(function (s, c) { return s + 1 + (c.fotos || 0); }, 0) || 1;
        var feito = 0;
        var passo = Promise.resolve();
        pendentes.forEach(function (c) {
          passo = passo.then(function () {
            if (falhou) { return null; }
            progresso(feito, total, 'Enviando ' + (c.nome || 'item') + '...');
            var fotosDoItem = 0;
            var era = { novo: !c.item_id, conferencia: !!c.enviar,
              campos: c.campos != null ? c.campos : Object.keys(c.dados || {}).filter(function (k) { return k !== 'nome'; }).length };
            return processar(c, function () { feito++; fotosEnviadas++; fotosDoItem++; progresso(feito, total, 'Enviando as fotos de ' + (c.nome || 'item') + '...'); })
              .then(function (ok) {
                feito++;
                if (ok) { enviadas++; }
                if (ok || c.erro || fotosDoItem) {
                  registro.itens.push({ nome: c.nome || 'Item', novo: era.novo, campos: era.campos, fotos: fotosDoItem,
                    conferencia: ok && era.conferencia, erro: c.erro || null, aviso: c.aviso || null });
                }
              })
              .catch(function () {
                falhou = true;
                registro.itens.push({ nome: c.nome || 'Item', novo: era.novo, campos: era.campos, fotos: fotosDoItem, conferencia: false,
                  erro: 'a conexão caiu antes de terminar; continua no aparelho' });
              });
          });
        });
        return passo;
      }).then(function () {
        if (falhou) { return null; }
        progresso(1, 1, 'Baixando a lista atualizada...');
        return baixarLista();
      });
    }).then(function () {
      if (!falhou) {
        var partes = [];
        if (enviadas) { partes.push(enviadas === 1 ? '1 gravação' : enviadas + ' gravações'); }
        if (fotosEnviadas) { partes.push(fotosEnviadas === 1 ? '1 foto' : fotosEnviadas + ' fotos'); }
        mostrarResultado(partes.length
          ? 'Sincronizado: ' + partes.join(' e ') + (enviadas + fotosEnviadas > 1 ? ' enviadas' : (enviadas ? ' enviada' : ' enviada')) + ', lista atualizada.'
          : 'Sincronizado: nada a enviar, lista atualizada.', 'bom');
      } else if (navigator.onLine) {
        mostrarResultado('A conexão caiu no meio do envio. O que não subiu continua no aparelho; sincronize de novo.', 'atencao');
      }
    }).catch(function (e) {
      if (!e || e.message !== 'sessao') { mostrarResultado('Não deu para sincronizar agora. Os dados continuam no aparelho.', 'atencao'); }
    }).then(function () {
      sincronizando = false;
      progresso(0, null);
      return guardarNoHistorico(registro);
    }).then(function () {
      return recarregarTela();
    });
  }

  // ------------------------------------------------------------ histórico
  var registro = null;
  function guardarNoHistorico(r) {
    if (!r) { return Promise.resolve(); }
    return ler('base', 'historico').then(function (lista) {
      lista = (lista || []);
      lista.unshift(r);
      return gravar('base', lista.slice(0, 60), 'historico');
    }).catch(function () {});
  }
  function quandoLegivel(t) {
    return new Date(t).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  function abrirHistorico() {
    ler('base', 'historico').then(function (lista) {
      lista = lista || [];
      var corpo = $('col-hist-lista');
      if (!lista.length) {
        corpo.innerHTML = '<div class="vazio">' + icone('relogio') + '<p><strong>Nenhuma sincronização ainda.</strong></p><p>Cada vez que você tocar em Sincronizar, o que subiu aparece aqui.</p></div>';
      } else {
        corpo.innerHTML = lista.map(function (r) {
          var fotos = r.itens.reduce(function (s, i) { return s + (i.fotos || 0); }, 0);
          var resumo = r.itens.length ? (r.itens.length === 1 ? '1 item' : r.itens.length + ' itens') + (fotos ? ', ' + (fotos === 1 ? '1 foto' : fotos + ' fotos') : '') : 'nada a enviar';
          return '<li class="hist-sinc ' + esc(r.tom || 'bom') + '"><div class="hist-cab">' + icone(r.tom === 'bom' ? 'check' : 'alerta')
            + '<b>' + esc(quandoLegivel(r.quando)) + '</b><span class="pilula ' + (r.tom === 'bom' ? 'ok' : 'at') + '">' + esc(resumo) + '</span></div>'
            + '<p class="hist-resultado">' + esc(r.resultado || '') + '</p>'
            + (r.itens.length ? '<ul class="hist-itens">' + r.itens.map(function (i) {
              var partes = [i.novo ? 'item novo' : (i.campos ? (i.campos === 1 ? '1 campo alterado' : i.campos + ' campos alterados') : 'conferido')];
              if (i.fotos) { partes.push(i.fotos === 1 ? '1 foto' : i.fotos + ' fotos'); }
              if (i.conferencia) { partes.push('enviado à conferência'); }
              return '<li' + (i.erro ? ' class="com-erro"' : '') + '><b>' + esc(i.nome) + '</b><span>' + esc(partes.join(' · ')) + '</span>'
                + (i.erro ? '<span class="erro">' + esc(i.erro) + '</span>' : '') + (i.aviso ? '<span class="erro">' + esc(i.aviso) + '</span>' : '') + '</li>';
            }).join('') + '</ul>' : '')
            + '</li>';
        }).join('');
      }
      $('col-historico-dialogo').showModal();
    });
  }

  function mostrarResultado(texto, tom) {
    if (registro && sincronizando) { registro.resultado = texto; registro.tom = tom; }
    var p = $('col-resultado');
    p.className = 'aviso ' + tom;
    p.innerHTML = icone(tom === 'bom' ? 'check' : 'alerta') + '<span>' + esc(texto) + '</span>';
    p.hidden = false;
  }

  // Um registro da fila: salvar, subir as fotos, enviar à conferência. Devolve
  // true quando terminou; erro de rede sobe como exceção (para a fila).
  function processar(c, aCadaFoto) {
    var p = Promise.resolve();
    if (c.etapa === 'salvar') {
      p = p.then(function () {
        return postar('/api/coleta', { acao: 'salvar', uuid: c.uuid, item_id: c.item_id, ficha_id: c.ficha_id, dados: c.dados, aqui: c.aqui });
      }).then(function (r) {
        if (!r.ok) {
          if (r.status >= 500 || r.motivo === 'resposta' || r.status === 429) { throw new Error('servidor'); }
          c.erro = 'não foi gravado: ' + (MOTIVOS[r.motivo] || r.motivo || 'erro');
          return gravar('fila', c).then(function () { throw new Error('parar-este'); });
        }
        c.item_id = r.item_id;
        if (r.aviso) { c.aviso = r.aviso; }
        c.etapa = 'fotos';
        return todos('fila').then(function (fila) {
          return Promise.all(fila.filter(function (o) { return o.uuid !== c.uuid && !o.item_id && o.local_id === c.local_id; })
            .map(function (o) { o.item_id = r.item_id; return gravar('fila', o); }));
        }).then(function () { return gravar('fila', c); });
      });
    }
    p = p.then(function () {
      if (c.etapa !== 'fotos') { return null; }
      return fotosDa(c.uuid).then(function (fotos) {
        var cadeia = Promise.resolve();
        fotos.forEach(function (f) {
          cadeia = cadeia.then(function () {
            var form = new FormData();
            form.append('uuid', f.uuid);
            form.append('item_id', String(c.item_id));
            if (f.lat != null) { form.append('latitude', String(f.lat)); form.append('longitude', String(f.lng)); }
            if (f.precisao != null) { form.append('precisao', String(f.precisao)); }
            form.append('foto', f.blob, f.nome || 'foto.jpg');
            return postar('/api/coleta-foto', form, true).then(function (r) {
              if (r.status >= 500 || r.status === 429) { throw new Error('servidor'); }
              if (!r.ok) { c.aviso = (c.aviso ? c.aviso + ' ' : '') + (r.mensagem || 'Uma foto não foi aceita.'); }
              if (aCadaFoto) { aCadaFoto(); }
              return apagar('fotos', f.uuid);
            });
          });
        });
        return cadeia.then(function () { c.etapa = c.enviar ? 'enviar' : 'feito'; return gravar('fila', c); });
      });
    });
    p = p.then(function () {
      if (c.etapa !== 'enviar') { return null; }
      return postar('/api/coleta', { acao: 'enviar', uuid: c.uuid_envio, item_id: c.item_id }).then(function (r) {
        if (!r.ok && r.status >= 500) { throw new Error('servidor'); }
        c.etapa = 'feito';
        return gravar('fila', c);
      });
    });
    return p.then(function () {
      return c.etapa === 'feito' ? apagar('fila', c.uuid).then(function () { return true; }) : false;
    }).catch(function (e) {
      if (e && e.message === 'parar-este') { return false; }
      throw e;
    });
  }

  // ------------------------------------------------------------ ficha em abas
  // A mesma ficha do painel (item.php): núcleo, funcionamento, campos
  // específicos da ficha do MTur, acessibilidade, tribos e textos. Os campos
  // têm o nome do formulário do painel, e o servidor lê os dois do mesmo
  // jeito (app/FichaFormulario.php). Grava só o que mudou.
  var form = $('col-form');
  var dialogo = $('col-dialogo');
  var ABAS_TODAS = ['ident', 'local', 'contato', 'funcionamento', 'ficha', 'acessibilidade', 'tribos', 'textos', 'fotos', 'conferencia'];
  var ABAS = ABAS_TODAS.slice();
  var abaAtual = 'ident';
  var inicial = null;  // a ficha como abriu, para mandar só o que mudou

  function abrirAba(aba) {
    abaAtual = aba;
    form.querySelectorAll('[data-aba-coleta]').forEach(function (b) {
      var sim = b.getAttribute('data-aba-coleta') === aba;
      b.setAttribute('aria-selected', sim ? 'true' : 'false');
      if (sim && b.scrollIntoView) { b.scrollIntoView({ block: 'nearest', inline: 'center' }); }
    });
    form.querySelectorAll('[data-painel-coleta]').forEach(function (p) { p.hidden = p.getAttribute('data-painel-coleta') !== aba; });
    var i = ABAS.indexOf(aba);
    $('c-anterior').hidden = i <= 0;
    $('c-proxima').hidden = i === ABAS.length - 1;
    form.querySelector('.corpo-dialogo').scrollTop = 0;
    if (aba === 'local') { mostrarMapa(); }
  }
  form.querySelectorAll('[data-aba-coleta]').forEach(function (b) {
    b.addEventListener('click', function () { abrirAba(b.getAttribute('data-aba-coleta')); });
  });
  $('c-proxima').addEventListener('click', function () { abrirAba(ABAS[Math.min(ABAS.length - 1, ABAS.indexOf(abaAtual) + 1)]); });
  $('c-anterior').addEventListener('click', function () { abrirAba(ABAS[Math.max(0, ABAS.indexOf(abaAtual) - 1)]); });

  function textoGps(lat, lng, precisao) {
    if (lat == null || lat === '') { return 'Sem coordenada'; }
    return Number(lat).toFixed(6) + ', ' + Number(lng).toFixed(6) + (precisao ? '  (± ' + Math.round(precisao) + ' m)' : '');
  }

  // Liga ou desliga uma parte da ficha: escondida e fora do envio.
  function mostrarParte(el, sim) {
    el.hidden = !sim;
    el.querySelectorAll('input, select, textarea').forEach(function (c) { c.disabled = !sim; });
  }

  var AUSENCIAS = [['na', 'NA'], ['ni', 'NI'], ['ne', 'NE']];
  function seletorAusencia(aid) {
    return '<select class="ausencia" name="aus[' + aid + ']" aria-label="Sem resposta"><option value="">Respondido</option>'
      + AUSENCIAS.map(function (a) { return '<option value="' + a[0] + '">' + a[1] + '</option>'; }).join('') + '</select>';
  }
  function caixasHtml(nome, opcoes) {
    return '<div class="caixas">' + opcoes.map(function (op) {
      var v = Array.isArray(op) ? op[0] : op, r = Array.isArray(op) ? op[1] : op;
      return '<label class="check"><input type="checkbox" name="' + esc(nome) + '[]" value="' + esc(v) + '"><i></i>' + esc(r) + '</label>';
    }).join('') + '</div>';
  }
  function linhaTabela(aid, n, colunas) {
    return '<tr>' + colunas.map(function (col, k) {
      return '<td><div class="entrada mini"><input type="text" name="at[' + aid + '][' + n + '][' + k + ']" maxlength="200" aria-label="' + esc(col) + ', linha ' + (n + 1) + '"></div></td>';
    }).join('') + '</tr>';
  }
  function campoEspecifico(a, valor) {
    var id = 'c-at-' + a.id;
    var largo = ['texto_longo', 'multipla', 'tabela'].indexOf(a.tipo) !== -1;
    var h = '<div class="campo ' + (largo ? 'c-12' : 'c-6') + '"><label for="' + id + '">' + esc(a.rotulo)
      + (a.obrigatorio ? ' <span class="obrig">núcleo do MTur</span>' : '') + '</label>';
    if (a.tipo === 'numero') {
      h += '<div class="entrada"><input type="text" id="' + id + '" name="at[' + a.id + ']" inputmode="decimal" autocomplete="off">' + seletorAusencia(a.id) + '</div>';
    } else if (a.tipo === 'sim_nao') {
      h += '<div class="entrada"><select id="' + id + '" name="at[' + a.id + ']"><option value="">Não respondido</option><option value="1">Sim</option><option value="0">Não</option></select>' + seletorAusencia(a.id) + '</div>';
    } else if (a.tipo === 'escolha') {
      h += '<div class="entrada"><select id="' + id + '" name="at[' + a.id + ']"><option value="">Não respondido</option>'
        + a.opcoes.map(function (o) { return '<option value="' + esc(o) + '">' + esc(o) + '</option>'; }).join('') + '</select>' + seletorAusencia(a.id) + '</div>';
    } else if (a.tipo === 'multipla') {
      h += caixasHtml('at[' + a.id + ']', a.opcoes) + '<div class="entrada estreita">' + seletorAusencia(a.id) + '</div>';
    } else if (a.tipo === 'tabela') {
      var linhas = valor && typeof valor === 'object' ? Object.keys(valor).length : 0;
      var corpo = '';
      for (var n = 0; n < linhas + 1; n++) { corpo += linhaTabela(a.id, n, a.opcoes); }
      h += '<div class="rolagem"><table class="dados tabela-campo" data-tabela="' + a.id + '"><thead><tr>'
        + a.opcoes.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>' + corpo + '</tbody></table></div>'
        + '<div class="tabela-acoes"><button type="button" class="como-link" data-mais-linha="' + a.id + '">' + icone('mais') + 'Mais uma linha</button>'
        + '<div class="entrada estreita">' + seletorAusencia(a.id) + '</div></div>';
    } else if (a.tipo === 'texto_longo') {
      h += '<div class="entrada"><textarea id="' + id + '" name="at[' + a.id + ']" rows="3"></textarea></div><div class="entrada estreita">' + seletorAusencia(a.id) + '</div>';
    } else {
      h += '<div class="entrada"><input type="text" id="' + id + '" name="at[' + a.id + ']" maxlength="500" autocomplete="off">' + seletorAusencia(a.id) + '</div>';
    }
    return h + (a.ajuda ? '<span class="ajuda">' + esc(a.ajuda) + '</span>' : '') + '</div>';
  }

  // Monta a ficha para o item: as abas e partes que a ficha tem, e os campos
  // do catálogo (campos específicos, acessibilidade, tribos).
  function montarFicha(f, valores) {
    var cat = (base && base.catalogo) || null;
    var municipio = f && f.forma === 'municipio';
    form.querySelectorAll('[data-nao-municipio]').forEach(function (el) {
      if (el.hasAttribute('data-aba-coleta')) { el.hidden = municipio; } else { mostrarParte(el, !municipio); }
    });
    form.querySelectorAll('[data-so-linear]').forEach(function (el) { mostrarParte(el, !!f && f.forma === 'linear'); });
    form.querySelectorAll('[data-so-capacidade]').forEach(function (el) { mostrarParte(el, !!f && !!f.capacidade); });
    var atributos = cat && f ? (cat.atributos[String(f.id)] || []) : [];
    var html = '';
    var grupo = null;
    atributos.forEach(function (a) {
      if (a.grupo !== grupo) {
        if (grupo !== null) { html += '</div>'; }
        grupo = a.grupo;
        html += '<h3 class="sub-cartao">' + esc(grupo) + '</h3><div class="grade">';
      }
      html += campoEspecifico(a, valores.at ? valores.at[String(a.id)] : null);
    });
    if (grupo !== null) { html += '</div>'; }
    if (!cat && f) { html = '<p class="aviso atencao">' + icone('alerta') + '<span>Toque em Sincronizar com sinal para baixar os campos completos da ficha.</span></p>'; }
    $('c-especificos').innerHTML = html;
    $('aba-ficha-rotulo').textContent = f ? 'Ficha ' + f.codigo : 'Ficha';
    form.querySelector('[data-aba-coleta="ficha"]').hidden = !f || (!atributos.length && !f.capacidade && !!cat);
    $('c-acess').innerHTML = municipio || !cat ? '' : cat.acessibilidade.map(function (s) {
      return '<h3 class="sub-cartao">' + esc(s.rotulo) + '</h3>' + caixasHtml('acess[' + s.chave + ']', s.opcoes);
    }).join('');
    var macro = null;
    $('c-tribos').innerHTML = municipio || !cat ? '' : cat.tribos.map(function (t) {
      var cab = t.macro !== macro ? '<h3 class="sub-cartao">' + esc(t.macro) + '</h3>' : '';
      macro = t.macro;
      return cab + '<details class="tribo" data-tribo><summary>' + icone(t.icone || 'usuarios') + '<b>' + esc(t.nome) + '</b><span class="tribo-conta"></span></summary>'
        + caixasHtml('facilidades', t.facilidades) + '</details>';
    }).join('');
    ABAS = ABAS_TODAS.filter(function (a) { return !form.querySelector('[data-aba-coleta="' + a + '"]').hidden; });
    var ajuda = $('col-ficha-ajuda');
    ajuda.hidden = !(f && f.ajuda);
    ajuda.querySelector('span').textContent = f && f.ajuda ? f.ajuda : '';
  }

  // Lê a ficha no formato do formulário do painel: at[12] vira {at: {12: ...}},
  // pagamentos[] vira lista, at[5][0][1] vira a célula da tabela.
  function lerForm() {
    var r = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.disabled || el.hasAttribute('data-fora-da-ficha') || /^(file|button|submit)$/.test(el.type)) { return; }
      var partes = el.name.replace(/\]/g, '').split('[');
      var lista = partes[partes.length - 1] === '';
      if (lista) { partes.pop(); }
      var alvo = r;
      for (var i = 0; i < partes.length - 1; i++) {
        if (typeof alvo[partes[i]] !== 'object' || alvo[partes[i]] === null) { alvo[partes[i]] = {}; }
        alvo = alvo[partes[i]];
      }
      var chave = partes[partes.length - 1];
      if (lista) {
        if (!Array.isArray(alvo[chave])) { alvo[chave] = []; }
        if (el.checked) { alvo[chave].push(el.value); }
      } else if (el.type === 'checkbox') {
        alvo[chave] = el.checked ? el.value : '';
      } else {
        alvo[chave] = el.value.trim();
      }
    });
    return r;
  }
  function valorNoCaminho(f, nome) {
    var partes = nome.replace(/\]/g, '').split('[');
    if (partes[partes.length - 1] === '') { partes.pop(); }
    var v = f;
    for (var i = 0; i < partes.length; i++) {
      if (v == null || typeof v !== 'object') { return undefined; }
      v = v[partes[i]];
    }
    return v;
  }
  function preencher(f) {
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.hasAttribute('data-fora-da-ficha') || /^(file|button|submit)$/.test(el.type)) { return; }
      var v = valorNoCaminho(f, el.name);
      if (el.type === 'checkbox') {
        el.checked = /\[\]$/.test(el.name) ? Array.isArray(v) && v.map(String).indexOf(el.value) !== -1 : !!v && v !== '0';
      } else {
        el.value = v == null || typeof v === 'object' ? '' : String(v);
      }
    });
    form.elements.telefone.value = telefoneLegivel(form.elements.telefone.value);
    form.elements.whatsapp.value = telefoneLegivel(form.elements.whatsapp.value);
    var cep = form.elements.cep.value.replace(/\D/g, '');
    if (cep.length === 8) { form.elements.cep.value = cep.replace(/^(\d{5})(\d{3})$/, '$1-$2'); }
    if (form.elements.instagram.value) { form.elements.instagram.value = '@' + form.elements.instagram.value.replace(/^@/, ''); }
    if (form.elements.cnpj.value) { form.elements.cnpj.value = mascaraCnpj(form.elements.cnpj.value); }
    limparConsultas();
    cnpjConsultado = limparCnpj(form.elements.cnpj.value);
    cadasturConsultado = form.elements.cadastur.value.trim();
    semanaDaFicha();
    contarTribos();
    contarFicha();
  }

  // O que mudou desde que a ficha abriu. Campo da ficha vai com a ausência;
  // o horário vai inteiro; a coordenada, em par.
  var HORARIO = ['h24', 'h_abre', 'h_fecha', 'h_feriados', 'h_temporada'];
  function igual(a, b) { return JSON.stringify(a == null ? '' : a) === JSON.stringify(b == null ? '' : b); }
  function diferenca(antes, agora) {
    var mudou = {};
    var n = 0;
    var chaves = {};
    Object.keys(antes).concat(Object.keys(agora)).forEach(function (k) { chaves[k] = true; });
    var horario = false;
    Object.keys(chaves).forEach(function (k) {
      if (k === 'at' || k === 'aus') { return; }
      if (HORARIO.indexOf(k) !== -1) { if (!igual(antes[k], agora[k])) { horario = true; } return; }
      if (k === 'acess') {
        var a = antes.acess || {}, b = agora.acess || {};
        Object.keys(Object.assign({}, a, b)).forEach(function (c) {
          if (!igual(a[c] || [], b[c] || [])) { (mudou.acess = mudou.acess || {})[c] = b[c] || []; n++; }
        });
        return;
      }
      if (!igual(antes[k], agora[k])) { mudou[k] = agora[k] == null ? '' : agora[k]; n++; }
    });
    if (horario) { HORARIO.forEach(function (k) { mudou[k] = agora[k] == null ? '' : agora[k]; }); n++; }
    var at0 = antes.at || {}, at1 = agora.at || {}, au0 = antes.aus || {}, au1 = agora.aus || {};
    Object.keys(Object.assign({}, at0, at1, au0, au1)).forEach(function (aid) {
      if (!igual(at0[aid], at1[aid]) || !igual(au0[aid], au1[aid])) {
        (mudou.at = mudou.at || {})[aid] = at1[aid] == null ? '' : at1[aid];
        (mudou.aus = mudou.aus || {})[aid] = au1[aid] || '';
        n++;
      }
    });
    if ('latitude' in mudou || 'longitude' in mudou) { mudou.latitude = agora.latitude || ''; mudou.longitude = agora.longitude || ''; }
    return { dados: mudou, campos: n };
  }

  // Funcionamento: a chave de cada dia limpa ou devolve as horas; 24 horas
  // esconde a semana; os atalhos copiam a segunda.
  var semana = form.querySelector('[data-semana-coleta]');
  var dias = Array.prototype.slice.call(semana.querySelectorAll('.dia'));
  function horasDo(dia) { return dia.querySelectorAll('input[type=time]'); }
  function abrirDia(dia, aberto, valores) {
    var h = horasDo(dia);
    dia.querySelector('[data-aberto-coleta]').checked = aberto;
    dia.classList.toggle('fechado', !aberto);
    if (aberto) {
      var v = valores || [dia.getAttribute('data-abre') || '08:00', dia.getAttribute('data-fecha') || '18:00'];
      h[0].value = v[0]; h[1].value = v[1];
    } else {
      if (h[0].value) { dia.setAttribute('data-abre', h[0].value); }
      if (h[1].value) { dia.setAttribute('data-fecha', h[1].value); }
      h[0].value = ''; h[1].value = '';
    }
  }
  function semanaDaFicha() {
    dias.forEach(function (dia) {
      var h = horasDo(dia);
      var aberto = !!(h[0].value && h[1].value);
      dia.querySelector('[data-aberto-coleta]').checked = aberto;
      dia.classList.toggle('fechado', !aberto);
      dia.removeAttribute('data-abre'); dia.removeAttribute('data-fecha');
    });
    semana.classList.toggle('dia-todo', form.elements.h24.checked);
  }
  dias.forEach(function (dia) {
    var chave = dia.querySelector('[data-aberto-coleta]');
    chave.addEventListener('change', function () { abrirDia(dia, chave.checked); });
  });
  form.elements.h24.addEventListener('change', function () { semana.classList.toggle('dia-todo', form.elements.h24.checked); });
  semana.querySelectorAll('[data-copiar-dias]').forEach(function (b) {
    b.addEventListener('click', function () {
      var h = horasDo(dias[0]);
      var aberto = dias[0].querySelector('[data-aberto-coleta]').checked && h[0].value && h[1].value;
      (b.getAttribute('data-copiar-dias') === 'uteis' ? dias.slice(1, 5) : dias.slice(1)).forEach(function (dia) {
        abrirDia(dia, !!aberto, aberto ? [h[0].value, h[1].value] : null);
      });
    });
  });

  // Tribos: quantas marcadas em cada uma (duas ou mais dão o selo).
  function contarTribos() {
    $('c-tribos').querySelectorAll('[data-tribo]').forEach(function (t) {
      var n = t.querySelectorAll('input:checked').length;
      var conta = t.querySelector('.tribo-conta');
      conta.className = 'tribo-conta' + (n ? ' pilula ' + (n >= 2 ? 'ok' : 'nu') : '');
      conta.textContent = n >= 2 ? 'selo' : (n ? String(n) : '');
      if (n) { t.open = true; }
    });
  }
  // Campos da ficha respondidos (valor ou NA, NI, NE) sobre o total.
  function contarFicha() {
    var cat = base && base.catalogo;
    var f = atual ? fichaPorId(atual.ficha_id) : null;
    var lista = cat && f ? (cat.atributos[String(f.id)] || []) : [];
    var marca = $('aba-ficha-conta');
    if (!lista.length) { marca.hidden = true; return; }
    var v = lerForm();
    var feitos = lista.filter(function (a) {
      var x = v.at ? v.at[String(a.id)] : null;
      var temValor = Array.isArray(x) ? x.length > 0 : (x && typeof x === 'object' ? Object.keys(x).some(function (n) {
        return Object.keys(x[n]).some(function (k) { return x[n][k]; });
      }) : !!x);
      return temValor || (v.aus && v.aus[String(a.id)]);
    }).length;
    marca.hidden = false;
    marca.textContent = feitos + '/' + lista.length;
  }
  $('c-tribos').addEventListener('change', contarTribos);
  $('c-especificos').addEventListener('change', contarFicha);
  $('c-especificos').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-mais-linha]');
    if (!b) { return; }
    var aid = b.getAttribute('data-mais-linha');
    var tabela = form.querySelector('[data-tabela="' + aid + '"]');
    var colunas = Array.prototype.map.call(tabela.querySelectorAll('thead th'), function (th) { return th.textContent; });
    var n = tabela.querySelectorAll('tbody tr').length;
    tabela.querySelector('tbody').insertAdjacentHTML('beforeend', linhaTabela(aid, n, colunas));
    tabela.querySelector('tbody tr:last-child input').focus();
  });

  function abrirItem(item) {
    atual = item;
    var f = fichaPorId(item.ficha_id);
    var valores = item.f || fichaDoItemAntigo(item);
    $('col-titulo').textContent = item.nome || 'Novo item';
    $('col-subtitulo').textContent = f ? f.codigo + ' ' + f.nome : '';
    var tipos = f ? f.tipos : [];
    $('c-tipo-campo').hidden = !tipos.length;
    $('c-tipo').disabled = !tipos.length;
    $('c-tipo').innerHTML = '<option value="">Escolha</option>' + tipos.map(function (t) { return '<option value="' + t.id + '">' + esc(t.nome) + '</option>'; }).join('');
    montarFicha(f, valores);
    preencher(valores);
    $('col-form-erro').hidden = true;
    $('c-endereco').hidden = true;
    enderecoAchado = null;
    var lat = $('c-lat').value, lng = $('c-lng').value;
    $('c-gps-texto').textContent = textoGps(lat || null, lng);
    $('c-gps-quando').textContent = lat ? 'já gravada; toque no botão para trocar pela daqui' : '';
    form.elements.nota.value = '';
    $('c-enviar').checked = false;
    var dev = $('col-devolucao');
    dev.hidden = !item.devolucao_nota;
    dev.querySelector('span').textContent = item.devolucao_nota ? 'Devolvido pela conferência: ' + item.devolucao_nota : '';
    var noServidor = item.fotos || 0;
    var naFila = item.fotos_fila || 0;
    $('c-fotos-servidor').textContent = (noServidor ? (noServidor === 1 ? '1 foto já no servidor' : noServidor + ' fotos já no servidor') : '')
      + (naFila ? (noServidor ? '; ' : '') + (naFila === 1 ? '1 foto' : naFila + ' fotos') + ' esperando sincronizar' : '');
    fotosNovas.forEach(function (x) { URL.revokeObjectURL(x.url); });
    fotosNovas = [];
    desenharMiniaturas();
    mostrarPendencias();
    inicial = lerForm();
    abrirAba('ident');
    dialogo.showModal();
    if (!item.id && !item.local_id) { form.elements.nome.focus(); }
  }

  function desenharMiniaturas() {
    $('c-miniaturas').innerHTML = fotosNovas.map(function (x, n) {
      return '<li><img src="' + x.url + '" alt="">' + (x.lat != null ? '<span class="foto-gps">' + icone('mapa') + 'GPS</span>' : '')
        + '<button type="button" data-tirar-foto="' + n + '" aria-label="Tirar esta foto">' + icone('fechar') + '</button></li>';
    }).join('');
    $('aba-fotos-conta').textContent = String((atual ? (atual.fotos || 0) + (atual.fotos_fila || 0) : 0) + fotosNovas.length);
  }

  function mostrarPendencias() {
    if (!atual) { return; }
    var f = fichaPorId(atual.ficha_id);
    var falta = [];
    if (!form.elements.nome.value.trim()) { falta.push('nome'); }
    if (f && f.forma !== 'municipio') {
      if (f.tipos.length && !form.elements.tipo_id.value) { falta.push('tipo'); }
      if (!form.elements.logradouro.value.trim() && !form.elements.referencia.value.trim()) { falta.push('endereço ou referência'); }
      if (!$('c-lat').value && !form.elements.plus_code.value.trim() && !fotosNovas.some(function (x) { return x.lat != null; })) { falta.push('coordenada'); }
      if (!form.elements.conservacao.value) { falta.push('conservação'); }
      if (!(atual.fotos || 0) && !(atual.fotos_fila || 0) && !fotosNovas.length) { falta.push('ao menos uma foto'); }
    }
    var caixa = $('c-pendencias');
    caixa.className = 'coleta-pendencias ' + (falta.length ? 'falta' : 'pronto');
    caixa.innerHTML = falta.length
      ? icone('alerta') + '<span><b>Para homologar falta:</b> ' + esc(falta.join(', ')) + '. Pode gravar assim mesmo e voltar depois.</span>'
      : icone('check') + '<span><b>Núcleo mínimo completo.</b> Já pode ir para a conferência.</span>';
    var marca = $('aba-falta');
    marca.hidden = !falta.length;
    marca.textContent = falta.length ? 'falta ' + falta.length : '';
    $('aba-gps-sinal').hidden = !$('c-lat').value;
  }
  form.addEventListener('input', mostrarPendencias);
  form.addEventListener('change', mostrarPendencias);

  // Máscara de telefone enquanto digita.
  form.querySelectorAll('[data-telefone]').forEach(function (el) {
    el.addEventListener('input', function () {
      var d = el.value.replace(/\D/g, '').slice(0, 11);
      var t = d.length > 10 ? '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7)
        : d.length > 6 ? '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6)
        : d.length > 2 ? '(' + d.slice(0, 2) + ') ' + d.slice(2) : d;
      el.value = t;
    });
  });

  // Enter avança para o próximo campo; no último campo da aba, vai para a próxima aba.
  form.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' || ev.target.tagName === 'TEXTAREA' || ev.target.tagName === 'BUTTON') { return; }
    ev.preventDefault();
    var painel = form.querySelector('[data-painel-coleta="' + abaAtual + '"]');
    var campos = Array.prototype.filter.call(painel.querySelectorAll('input:not([type=hidden]):not([type=file]):not([type=checkbox]), select, textarea'), function (el) {
      return !el.disabled && el.offsetParent !== null;
    });
    var i = campos.indexOf(ev.target);
    if (i > -1 && campos[i + 1]) { campos[i + 1].focus(); return; }
    var proxima = ABAS[ABAS.indexOf(abaAtual) + 1];
    if (proxima) {
      abrirAba(proxima);
      var primeiro = form.querySelector('[data-painel-coleta="' + proxima + '"] input:not([type=hidden]), [data-painel-coleta="' + proxima + '"] select');
      if (primeiro) { primeiro.focus(); }
    }
  });

  // GPS: o nativo no app, o do navegador no painel.
  function pegarPosicao() {
    if (APP && APP.posicao) { return APP.posicao(); }
    return new Promise(function (ok, falha) {
      if (!navigator.geolocation) { falha(new Error('sem-gps')); return; }
      navigator.geolocation.getCurrentPosition(function (p) {
        ok({ lat: p.coords.latitude, lng: p.coords.longitude, precisao: p.coords.accuracy });
      }, function (e) { falha(new Error(e.code === 1 ? 'negado' : 'falhou')); }, { enableHighAccuracy: true, timeout: 25000, maximumAge: 10000 });
    });
  }
  $('c-gps').addEventListener('click', function () {
    var botao = this;
    botao.disabled = true;
    $('c-gps-texto').textContent = 'Buscando o GPS...';
    pegarPosicao().then(function (p) {
      botao.disabled = false;
      posicao = [p.lat, p.lng, p.precisao, Date.now()];
      $('c-lat').value = p.lat.toFixed(6);
      $('c-lng').value = p.lng.toFixed(6);
      $('c-gps-texto').textContent = textoGps(p.lat, p.lng, p.precisao);
      $('c-gps-quando').textContent = 'agora, ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
      mostrarPendencias();
      mostrarMapa();
      buscarEndereco(p.lat, p.lng);
    }).catch(function (e) {
      botao.disabled = false;
      $('c-gps-texto').textContent = e.message === 'negado' ? 'O aparelho não deixou usar a localização. Libere nas permissões do app.'
        : (e.message === 'sem-gps' ? 'Este aparelho não dá a localização.' : 'Não deu para pegar o GPS agora. Tente ao ar livre.');
    });
  });

  // Fotos: no app pela câmera (com o GPS do momento), no painel pela zona da casa.
  function juntarFotos(arquivos, gps) {
    Array.prototype.forEach.call(arquivos || [], function (a) {
      if (!/^image\/(jpeg|png|webp)$/.test(a.type)) { return; }
      fotosNovas.push({ uuid: uuid(), blob: a, nome: a.name || 'foto.jpg', url: URL.createObjectURL(a),
        lat: gps ? gps.lat : null, lng: gps ? gps.lng : null, precisao: gps ? gps.precisao : null });
    });
    desenharMiniaturas();
    mostrarPendencias();
  }
  if (APP) {
    ['c-tirar-foto', 'c-galeria'].forEach(function (id) {
      var b = $(id);
      if (!b) { return; }
      b.addEventListener('click', function () {
        b.disabled = true;
        APP.foto(id === 'c-tirar-foto' ? 'camera' : 'galeria').then(function (r) {
          b.disabled = false;
          if (!r) { return; }
          juntarFotos(r.arquivos || (r.arquivo ? [r.arquivo] : []), r.gps);
          // Sem coordenada no item ainda: a da primeira foto já serve.
          if (r.gps && !$('c-lat').value) {
            $('c-lat').value = r.gps.lat.toFixed(6);
            $('c-lng').value = r.gps.lng.toFixed(6);
            $('c-gps-texto').textContent = textoGps(r.gps.lat, r.gps.lng, r.gps.precisao);
            $('c-gps-quando').textContent = 'da foto';
            mostrarPendencias();
            buscarEndereco(r.gps.lat, r.gps.lng);
          }
        }).catch(function () { b.disabled = false; });
      });
    });
  } else {
    var campoFotos = document.querySelector('#c-zona-fotos input[type=file]');
    if (campoFotos) {
      campoFotos.addEventListener('arquivos-escolhidos', function () {
        var arquivos = Array.prototype.slice.call(campoFotos.files);
        var aviso = document.querySelector('#c-zona-fotos .zona-escolhido');
        if (aviso) { aviso.hidden = true; }
        try { campoFotos.value = ''; } catch (e) {}
        juntarFotos(arquivos, posicao ? { lat: posicao[0], lng: posicao[1], precisao: posicao[2] } : null);
      });
    }
  }
  $('c-miniaturas').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-tirar-foto]');
    if (!b) { return; }
    var n = parseInt(b.getAttribute('data-tirar-foto'), 10);
    URL.revokeObjectURL(fotosNovas[n].url);
    fotosNovas.splice(n, 1);
    desenharMiniaturas();
    mostrarPendencias();
  });

  // ------------------------------------------------------------ máscaras e documentos
  // Máscara enquanto digita (CNPJ, CEP, telefone, Cadastur, plus code) e
  // conferência ao sair do campo, com o erro embaixo dele. Com internet, o
  // CNPJ válido busca a Receita e o Cadastur e preenche o que está vazio; o
  // que já tem e é diferente fica para o botão "Usar".
  function limparCnpj(v) {
    var c = String(v || '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);
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
  function mascaraCep(v) { var d = String(v || '').replace(/\D/g, '').slice(0, 8); return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d; }
  var MASCARAS = {
    cnpj: mascaraCnpj,
    cep: mascaraCep,
    cadastur: function (v) { return String(v || '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 30); },
    plus_code: function (v) { return String(v || '').toUpperCase().replace(/[^0-9A-Z+ ]/g, '').slice(0, 16); },
    funcionarios: function (v) { return String(v || '').replace(/\D/g, '').slice(0, 5); },
    inicio_atividade: function (v) { return String(v || '').replace(/\D/g, '').slice(0, 4); }
  };
  Object.keys(MASCARAS).forEach(function (nome) {
    var el = form.elements[nome];
    if (!el) { return; }
    el.addEventListener('input', function () {
      var antes = el.value, novo = MASCARAS[nome](antes);
      if (novo !== antes) { el.value = novo; }
    });
  });

  // O erro do campo, embaixo dele (e a entrada em vermelho).
  function erroDoCampo(el, texto) {
    var bloco = el.closest('.campo');
    var entrada = el.closest('.entrada');
    if (!bloco) { return; }
    var aviso = bloco.querySelector('.ajuda.erro');
    if (texto && !aviso) { aviso = document.createElement('span'); aviso.className = 'ajuda erro'; bloco.appendChild(aviso); }
    if (aviso) { if (texto) { aviso.textContent = texto; } else { aviso.remove(); } }
    if (entrada) { entrada.classList.toggle('erro', !!texto); }
  }
  function conferirCampo(el) {
    var v = el.value.trim();
    var erro = '';
    if (el.name === 'cnpj' && v) {
      var c = limparCnpj(v);
      erro = c.length !== 14 ? 'O CNPJ tem 14 caracteres; faltam ' + (14 - c.length) + '.' : (cnpjValido(c) ? '' : 'CNPJ inválido: os dígitos verificadores não batem.');
    } else if (el.name === 'email' && v) {
      el.value = v.toLowerCase();
      erro = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'E-mail inválido.';
    } else if ((el.name === 'telefone' || el.name === 'whatsapp') && v) {
      var d = soDigitosTel(v);
      erro = d.length === 10 || d.length === 11 ? '' : 'Use DDD e número (10 ou 11 dígitos).';
      if (!erro && el.name === 'whatsapp' && d.length === 10) { erro = 'WhatsApp é celular: DDD e 9 dígitos.'; }
    } else if (el.name === 'cep' && v) {
      erro = v.replace(/\D/g, '').length === 8 ? '' : 'O CEP tem 8 dígitos.';
    } else if (el.name === 'instagram' && v) {
      el.value = '@' + v.replace(/^@+/, '').replace(/^https?:\/\/(www\.)?instagram\.com\//i, '').replace(/\/.*$/, '');
    } else if ((el.name === 'site' || el.name === 'maps_url') && v && !/^https?:\/\//i.test(v)) {
      el.value = 'https://' + v;
    } else if (el.name === 'inicio_atividade' && v) {
      var ano = parseInt(v, 10);
      erro = ano >= 1500 && ano <= new Date().getFullYear() ? '' : 'Ano entre 1500 e ' + new Date().getFullYear() + '.';
    }
    erroDoCampo(el, erro);
    return !erro;
  }
  ['cnpj', 'email', 'telefone', 'whatsapp', 'cep', 'instagram', 'site', 'maps_url', 'inicio_atividade'].forEach(function (nome) {
    var el = form.elements[nome];
    if (el) { el.addEventListener('blur', function () { conferirCampo(el); }); }
  });

  // A caixa do resultado da consulta, embaixo do campo.
  function caixaConsulta(el) {
    var bloco = el.closest('.campo');
    var caixa = bloco.querySelector('.consulta-doc');
    if (!caixa) {
      caixa = document.createElement('div');
      caixa.className = 'consulta-doc';
      bloco.appendChild(caixa);
    }
    return caixa;
  }
  function limparConsultas() {
    form.querySelectorAll('.consulta-doc, .ajuda.erro').forEach(function (c) { c.remove(); });
    form.querySelectorAll('.entrada.erro').forEach(function (c) { c.classList.remove('erro'); });
  }
  function mostrarConsulta(el, tom, html, usar) {
    var caixa = caixaConsulta(el);
    caixa.className = 'consulta-doc ' + tom;
    caixa.innerHTML = icone(tom === 'bom' ? 'check' : (tom === 'nulo' ? 'relogio' : 'alerta')) + '<div>' + html
      + (usar ? '<button type="button" class="botao mini" data-usar-consulta>' + icone('check') + esc(usar.rotulo) + '</button>' : '') + '</div>';
    caixa.usar = usar ? usar.fazer : null;
  }
  form.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-usar-consulta]');
    if (!b) { return; }
    var caixa = b.closest('.consulta-doc');
    if (caixa && caixa.usar) { caixa.usar(); b.remove(); }
  });

  // Campo da ficha recebendo um valor da consulta: vazio é preenchido na
  // hora; diferente vai para a lista do botão "Usar".
  function valorConsulta(nome, valor) {
    if (valor == null || valor === '') { return null; }
    if (nome === 'telefone' || nome === 'whatsapp') { return telefoneLegivel(valor); }
    if (nome === 'cep') { return mascaraCep(valor); }
    return String(valor);
  }
  function aplicar(campos, sobrescrever) {
    var preenchi = [], diferentes = [];
    Object.keys(campos).forEach(function (nome) {
      var el = form.elements[nome];
      var v = valorConsulta(nome, campos[nome][0]);
      if (!el || el.disabled || v === null) { return; }
      var atualV = el.value.trim();
      if (!atualV || sobrescrever) {
        if (atualV !== v) { el.value = v; preenchi.push(campos[nome][1]); }
      } else if (semAcento(atualV) !== semAcento(v) && soDigitosTel(atualV) !== soDigitosTel(v)) {
        diferentes.push(campos[nome][1]);
      }
    });
    mostrarPendencias();
    return { preenchi: preenchi, diferentes: diferentes };
  }
  function naturezaDaReceita(t) {
    t = semAcento(t || '');
    if (!t) { return null; }
    if (t.indexOf('economia mista') !== -1) { return 'mista'; }
    if (/(autarquia|municipio|estado|uniao|orgao publico|poder|fundacao publica|empresa publica|consorcio publico|camara|assembleia|tribunal)/.test(t)) { return 'publica'; }
    return 'privada';
  }
  function dataBr(iso) { return iso ? iso.split('-').reverse().join('/') : ''; }
  function textoCadastur(lista) {
    if (!lista || !lista.length) { return 'Sem registro no Cadastur (dados abertos do MTur).'; }
    var vigente = lista.filter(function (r) { return r.vigente; })[0];
    var r = vigente || lista[0];
    return 'Cadastur: ' + r.atividade + ', ' + r.situacao.toLowerCase() + (r.validade ? ' até ' + dataBr(r.validade) : '') + (lista.length > 1 ? ' (e mais ' + (lista.length - 1) + ' atividade' + (lista.length > 2 ? 's' : '') + ')' : '') + '.';
  }
  function camposDoCadastur(lista) {
    var r = (lista || []).filter(function (x) { return x.vigente; })[0] || (lista || [])[0];
    return r ? { cadastur: [r.certificado, 'Cadastur'], cadastur_validade: [r.validade, 'validade do Cadastur'] } : {};
  }

  var cnpjConsultado = '';
  function consultarCnpj(forcar) {
    var el = form.elements.cnpj;
    var c = limparCnpj(el.value);
    if (!c || c.length !== 14 || !cnpjValido(c)) { var cx = el.closest('.campo').querySelector('.consulta-doc'); if (cx) { cx.remove(); } return; }
    if (c === cnpjConsultado && !forcar) { return; }
    if (!temInternet()) {
      mostrarConsulta(el, 'nulo', '<span>CNPJ válido. Sem internet agora: os dados da Receita e do Cadastur aparecem quando houver sinal (saia do campo de novo).</span>');
      return;
    }
    cnpjConsultado = c;
    mostrarConsulta(el, 'nulo', '<span>Consultando a Receita e o Cadastur...</span>');
    var q = '/api/coleta?cnpj=' + encodeURIComponent(c) + '&ficha=' + (atual ? atual.ficha_id : '') + '&item=' + (atual && atual.id ? atual.id : '');
    pedir(q, { headers: cabecalhos(false), cache: 'no-store' }, 20000).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok) { cnpjConsultado = ''; mostrarConsulta(el, 'atencao', '<span>' + esc((d && d.erro) || 'Não deu para consultar agora.') + '</span>'); return; }
      var rec = d.receita;
      var campos = {};
      var partes = [];
      if (rec) {
        campos = {
          razao_social: [rec.razao_social, 'razão social'], nome: [rec.nome_fantasia || rec.razao_social, 'nome'],
          email: [rec.email ? rec.email.toLowerCase() : null, 'e-mail'], telefone: [rec.telefone, 'telefone'],
          cep: [rec.cep, 'CEP'], logradouro: [rec.logradouro, 'rua'], numero: [rec.numero, 'número'], complemento: [rec.complemento, 'complemento'],
          bairro: [rec.bairro, 'bairro'], inicio_atividade: [rec.inicio_atividade, 'início da atividade'], natureza: [naturezaDaReceita(rec.natureza_juridica), 'natureza']
        };
        partes.push('<b>Receita (' + esc(rec.fonte) + '):</b> ' + esc(rec.razao_social || '') + (rec.nome_fantasia ? ' (' + esc(rec.nome_fantasia) + ')' : '')
          + ', ' + esc([rec.cidade, rec.uf].filter(Boolean).join('/')) + '. Situação: <b>' + esc(rec.situacao_cadastral || '?') + '</b>.');
      } else {
        partes.push(esc(d.erro_receita || 'A Receita não respondeu agora.'));
      }
      Object.assign(campos, camposDoCadastur(d.cadastur));
      partes.push(esc(textoCadastur(d.cadastur)));
      if (d.existente) { partes.push('<b>Atenção:</b> este CNPJ já está nesta ficha, em ' + esc(d.existente.nome) + '.'); }
      var feito = aplicar(campos, false);
      if (feito.preenchi.length) { partes.push('Preenchi ' + feito.preenchi.join(', ') + '.'); }
      if (feito.diferentes.length) { partes.push('Diferente na ficha: ' + feito.diferentes.join(', ') + '.'); }
      var ruim = d.existente || (rec && rec.situacao_cadastral && rec.situacao_cadastral.toUpperCase() !== 'ATIVA');
      mostrarConsulta(el, ruim ? 'atencao' : 'bom', '<span>' + partes.join(' ') + '</span>', feito.diferentes.length ? {
        rotulo: 'Usar os dados da Receita',
        fazer: function () { aplicar(campos, true); mostrarConsulta(el, 'bom', '<span>Dados trocados pelos da Receita e do Cadastur. Confira na fachada.</span>'); }
      } : null);
    }).catch(function () { cnpjConsultado = ''; mostrarConsulta(el, 'atencao', '<span>A consulta não veio agora. Saia do campo de novo para tentar.</span>'); });
  }
  form.elements.cnpj.addEventListener('blur', function () { if (conferirCampo(form.elements.cnpj)) { consultarCnpj(false); } });

  var cadasturConsultado = '';
  form.elements.cadastur.addEventListener('blur', function () {
    var el = form.elements.cadastur;
    var n = el.value.trim();
    if (!n) { var cx = el.closest('.campo').querySelector('.consulta-doc'); if (cx) { cx.remove(); } return; }
    if (n === cadasturConsultado) { return; }
    if (!temInternet()) { mostrarConsulta(el, 'nulo', '<span>Sem internet agora: o Cadastur é conferido quando houver sinal.</span>'); return; }
    cadasturConsultado = n;
    pedir('/api/coleta?cadastur=' + encodeURIComponent(n), { headers: cabecalhos(false), cache: 'no-store' }, 15000).then(function (r) { return r.json(); }).then(function (d) {
      var lista = (d && d.cadastur) || [];
      if (!lista.length) { mostrarConsulta(el, 'atencao', '<span>Não achei este número no Cadastur (dados abertos do MTur). Confira o certificado.</span>'); return; }
      var r = lista.filter(function (x) { return x.vigente; })[0] || lista[0];
      var feito = aplicar({ cadastur_validade: [r.validade, 'validade'] }, false);
      mostrarConsulta(el, r.vigente ? 'bom' : 'atencao', '<span><b>' + esc(r.nome) + '</b>. ' + esc(textoCadastur(lista))
        + (feito.preenchi.length ? ' Preenchi a validade.' : '') + '</span>');
    }).catch(function () { cadasturConsultado = ''; mostrarConsulta(el, 'atencao', '<span>A consulta não veio agora. Saia do campo de novo para tentar.</span>'); });
  });

  // Antes de guardar no aparelho: o que o servidor recusaria (CNPJ, e-mail,
  // telefone). Sem sinal não dá para corrigir depois, então para aqui.
  function cnpjValido(v) {
    var c = String(v || '').toUpperCase().replace(/[^0-9A-Z]/g, '');
    if (!/^[0-9A-Z]{12}[0-9]{2}$/.test(c) || /^(.)\1{13}$/.test(c)) { return false; }
    for (var pos = 12; pos <= 13; pos++) {
      var soma = 0, peso = pos - 7;
      for (var i = 0; i < pos; i++) { soma += (c.charCodeAt(i) - 48) * peso; peso = peso - 1 < 2 ? 9 : peso - 1; }
      var dig = soma % 11 < 2 ? 0 : 11 - soma % 11;
      if (parseInt(c[pos], 10) !== dig) { return false; }
    }
    return true;
  }
  function errosDaFicha() {
    var el = form.elements;
    var tel = function (v) { var d = soDigitosTel(v); return !d || d.length === 10 || d.length === 11; };
    if (!el.cnpj.disabled && el.cnpj.value.trim() && !cnpjValido(el.cnpj.value)) { return ['ident', el.cnpj, 'CNPJ inválido. Confira os dígitos.']; }
    if (!el.email.disabled && el.email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.email.value.trim())) { return ['contato', el.email, 'E-mail inválido.']; }
    if (!el.telefone.disabled && !tel(el.telefone.value)) { return ['contato', el.telefone, 'Telefone incompleto. Use DDD e número.']; }
    if (!el.whatsapp.disabled && !tel(el.whatsapp.value)) { return ['contato', el.whatsapp, 'WhatsApp incompleto. Use DDD e número.']; }
    return null;
  }
  function erroNaFicha(aba, campo, texto) {
    var p = $('col-form-erro');
    p.querySelector('span').textContent = texto;
    p.hidden = false;
    abrirAba(aba);
    if (campo) { campo.focus(); }
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!form.elements.nome.value.trim()) { erroNaFicha('ident', form.elements.nome, 'Dê o nome do item.'); return; }
    var erro = errosDaFicha();
    if (erro) { erroNaFicha(erro[0], erro[1], erro[2]); return; }
    $('col-form-erro').hidden = true;
    var agora = lerForm();
    var novo = !atual.id && !atual.local_id;
    // Item novo leva a ficha inteira; o que já existe, só o que mudou.
    var dif = novo ? { dados: agora, campos: 0 } : diferenca(inicial || {}, agora);
    var mudou = dif.dados;
    var nota = (agora.nota || '').trim();
    delete mudou.nota;
    if (nota) { mudou.nota = nota; }
    if (novo) { mudou.nome = agora.nome; }
    var nadaMudou = !Object.keys(mudou).length && !fotosNovas.length && !$('c-enviar').checked;
    if (nadaMudou) { dialogo.close(); return; }
    var c = {
      uuid: uuid(), uuid_envio: uuid(), ordem: Date.now(),
      item_id: atual.id || null, local_id: atual.local_id || uuid(), ficha_id: atual.ficha_id,
      nome: agora.nome, dados: mudou, campos: dif.campos, enviar: $('c-enviar').checked, fotos: fotosNovas.length,
      aqui: posicao ? [posicao[0], posicao[1]] : null, etapa: 'salvar'
    };
    var fotos = fotosNovas.map(function (x) {
      return { uuid: x.uuid, coleta: c.uuid, blob: x.blob, nome: x.nome, lat: x.lat, lng: x.lng, precisao: x.precisao };
    });
    Promise.all([gravar('fila', c)].concat(fotos.map(function (f) { return gravar('fotos', f); }))).then(function () {
      fotosNovas.forEach(function (x) { URL.revokeObjectURL(x.url); });
      fotosNovas = [];
      dialogo.close();
      if (!APP) {
        // No painel não há fila para depois: gravou, envia.
        mostrarResultado('Gravado. Enviando ao servidor...', 'bom');
        return recarregarTela().then(sincronizar);
      }
      mostrarResultado('Gravado no aparelho. Toque em Sincronizar quando tiver sinal.', 'bom');
      return recarregarTela();
    }).catch(function () {
      alert('Não consegui guardar no aparelho. Veja se há espaço livre e tente de novo.');
    });
  });


  // ------------------------------------------------------------ mapa e endereço (com internet)
  // Com internet: o mapa do Google incorporado (sem chave, sem custo) e o
  // endereço achado pela coordenada (/api/endereco, OpenStreetMap), que
  // preenche só os campos vazios. Sem internet: só a coordenada; nada fica
  // guardado no aparelho.
  function temInternet() {
    return navigator.onLine && conexao.estado !== 'falhou' && conexao.estado !== 'sem-rede';
  }
  var mapaMostrado = '';
  function mostrarMapa() {
    var caixa = $('c-mapa');
    var lat = parseFloat($('c-lat').value), lng = parseFloat($('c-lng').value);
    if (!isFinite(lat) || !isFinite(lng)) { caixa.hidden = true; caixa.innerHTML = ''; mapaMostrado = ''; return; }
    if (!temInternet()) {
      caixa.hidden = true; caixa.innerHTML = ''; mapaMostrado = '';
      avisoEndereco('Sem internet: só a coordenada. O mapa e o endereço aparecem quando houver sinal.', 'nulo');
      return;
    }
    var chave = lat.toFixed(6) + ',' + lng.toFixed(6);
    caixa.hidden = false;
    if (mapaMostrado === chave) { return; }
    mapaMostrado = chave;
    caixa.innerHTML = '';
    var quadro = document.createElement('iframe');
    quadro.src = 'https://www.google.com/maps?q=' + chave + '&z=17&hl=pt-BR&output=embed';
    quadro.title = 'Mapa da coordenada';
    quadro.loading = 'lazy';
    quadro.referrerPolicy = 'no-referrer';
    var abrir = document.createElement('a');
    abrir.href = 'https://www.google.com/maps/search/?api=1&query=' + chave;
    abrir.target = '_blank'; abrir.rel = 'noopener';
    abrir.className = 'mapa-abrir';
    abrir.textContent = 'Abrir no Google Maps';
    caixa.appendChild(quadro);
    caixa.appendChild(abrir);
  }
  function avisoEndereco(texto, classe, oferecer) {
    var p = $('c-endereco');
    p.className = 'endereco-achado ' + (classe || '');
    $('c-endereco-texto').textContent = texto;
    $('c-usar-endereco').hidden = !oferecer;
    p.hidden = !texto;
  }
  // O último endereço achado, para o botão "Usar este endereço" trocar os campos.
  var enderecoAchado = null;
  var CAMPOS_ENDERECO = { logradouro: 'rua', numero: 'número', bairro: 'bairro', cep: 'CEP' };
  function valorEndereco(k, d) { return k === 'cep' ? String(d.cep || '').replace(/^(\d{5})(\d{3})$/, '$1-$2') : String(d[k] || ''); }
  function buscarEndereco(lat, lng) {
    if (lat == null) {
      lat = parseFloat($('c-lat').value); lng = parseFloat($('c-lng').value);
    }
    if (!isFinite(lat) || !isFinite(lng)) { avisoEndereco('Sem coordenada: use o GPS daqui ou marque o plus code primeiro.', 'nulo'); return; }
    if (!temInternet()) {
      avisoEndereco('Sem internet: só a coordenada. O endereço pode ser buscado depois, com sinal.', 'nulo');
      return;
    }
    var botao = $('c-buscar-endereco');
    botao.disabled = true;
    avisoEndereco('Buscando o endereço desta coordenada...', 'nulo');
    pedir('/api/endereco?lat=' + lat.toFixed(6) + '&lng=' + lng.toFixed(6), { headers: cabecalhos(false), cache: 'no-store' }, 15000)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        botao.disabled = false;
        if (!d || !d.ok) { enderecoAchado = null; avisoEndereco('Não achei endereço para esta coordenada. Preencha à mão.', 'nulo'); return; }
        enderecoAchado = d;
        // Vazio é preenchido na hora; o que já tinha e é diferente fica para o botão.
        var preenchi = [], diferentes = [];
        Object.keys(CAMPOS_ENDERECO).forEach(function (k) {
          var achado = valorEndereco(k, d);
          if (!achado) { return; }
          var atualCampo = form.elements[k].value.trim();
          if (!atualCampo) { form.elements[k].value = achado; preenchi.push(CAMPOS_ENDERECO[k]); }
          else if (atualCampo.toLowerCase() !== achado.toLowerCase()) { diferentes.push(CAMPOS_ENDERECO[k]); }
        });
        mostrarPendencias();
        var texto = 'Endereço desta coordenada: ' + (d.completo || '') + '.';
        if (preenchi.length) { texto += ' Preenchi ' + preenchi.join(', ') + '.'; }
        if (diferentes.length) { texto += ' Diferente do que está na ficha: ' + diferentes.join(', ') + '.'; }
        if (!preenchi.length && !diferentes.length) { texto += ' A ficha já está igual.'; }
        avisoEndereco(texto, 'achou', diferentes.length > 0);
      })
      .catch(function () { botao.disabled = false; avisoEndereco('O endereço não veio agora. Tente de novo ou preencha à mão.', 'nulo'); });
  }
  $('c-buscar-endereco').addEventListener('click', function () { buscarEndereco(); });
  $('c-usar-endereco').addEventListener('click', function () {
    if (!enderecoAchado) { return; }
    Object.keys(CAMPOS_ENDERECO).forEach(function (k) {
      var achado = valorEndereco(k, enderecoAchado);
      if (achado) { form.elements[k].value = achado; }
    });
    mostrarPendencias();
    avisoEndereco('Endereço trocado pelo desta coordenada: ' + (enderecoAchado.completo || '') + '. Confira na fachada.', 'achou');
  });

  // ------------------------------------------------------------ eventos
  document.querySelectorAll('.coleta-dialogo [data-fechar]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });
  $('col-lista').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-chave]');
    if (!b) { return; }
    var item = itensComFila().filter(function (i) { return i.chave === b.getAttribute('data-chave'); })[0];
    if (item) { abrirItem(item); }
  });
  var botaoNovo = $('col-novo');
  if (botaoNovo) {
    botaoNovo.addEventListener('click', function () {
      $('col-fichas-busca').value = '';
      desenharFichas();
      $('col-escolha').showModal();
      $('col-fichas-busca').focus();
    });
  }
  $('col-fichas-busca').addEventListener('input', desenharFichas);
  $('col-fichas').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-nova-ficha]');
    if (!b) { return; }
    $('col-escolha').close();
    abrirItem({ id: null, local_id: null, ficha_id: parseInt(b.getAttribute('data-nova-ficha'), 10), situacao: 'em_campo', nome: '', fotos: 0 });
  });
  $('col-erros').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-descartar]');
    if (!b || !confirm('Descartar esta gravação? O que ela trazia não vai subir.')) { return; }
    var id = b.getAttribute('data-descartar');
    fotosDa(id).then(function (fotos) {
      return Promise.all(fotos.map(function (f) { return apagar('fotos', f.uuid); }));
    }).then(function () { return apagar('fila', id); }).then(recarregarTela);
  });
  ['col-busca', 'col-ficha-filtro'].forEach(function (id) {
    $(id).addEventListener(id === 'col-busca' ? 'input' : 'change', function () { mostrar = POR_PAGINA; desenharLista(); });
  });
  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-situacao]');
    if (!b) { return; }
    situacao = b.getAttribute('data-situacao');
    mostrar = POR_PAGINA;
    desenharLista();
  });
  $('col-mais').addEventListener('click', function () { mostrar += POR_PAGINA; desenharLista(); });
  $('col-sincronizar').addEventListener('click', sincronizar);
  if ($('col-historico')) { $('col-historico').addEventListener('click', abrirHistorico); }
  window.addEventListener('online', function () { medirConexao(); });
  window.addEventListener('offline', function () { conexao = { estado: 'sem-rede', ms: null, quando: Date.now() }; mostrarEstado(); });

  // Para o app: o novo item vem do botão dele, e a primeira sincronização é logo depois de conectar.
  window.inventourColeta = { sincronizar: sincronizar, novo: function () { if (botaoNovo) { botaoNovo.click(); } }, recarregar: recarregarTela };

  // ------------------------------------------------------------ início
  // Abre com o que está no aparelho. Sem lista nenhuma (primeira vez), baixa uma.
  // Abre com o que está no aparelho e, havendo rede, baixa a lista nova em
  // silêncio (o que a conferência devolveu aparece sem precisar sincronizar).
  // Enviar continua só no botão Sincronizar.
  var ultimaBaixa = 0;
  function atualizarLista() {
    if (!navigator.onLine || sincronizando) { return Promise.resolve(); }
    ultimaBaixa = Date.now();
    return baixarLista().then(function (ok) { return ok ? recarregarTela() : null; }).catch(function () {});
  }
  recarregarTela().then(function () {
    if (!base && navigator.onLine) { return sincronizar(); }
    return medirConexao().then(function (ok) { return ok ? atualizarLista() : null; });
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - ultimaBaixa > 120000) { medirConexao().then(function (ok) { return ok ? atualizarLista() : null; }); }
  });

  // Painel: a página e os arquivos guardados para abrir sem sinal (no app já vêm dentro dele).
  if (!APP && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw-coleta.js', { scope: '/coleta' }).then(function (reg) {
      var urls = performance.getEntriesByType('resource').map(function (e) { return e.name; }).filter(function (u) {
        return u.indexOf(location.origin + '/assets/') === 0;
      });
      var mandar = function (sw) { if (sw) { sw.postMessage({ cachear: ['/coleta'].concat(urls) }); } };
      mandar(reg.active || reg.waiting || reg.installing);
      navigator.serviceWorker.ready.then(function (r) { mandar(r.active); });
    }).catch(function () {});
  }
})();

/*
 * Coleta de campo (public/coleta.php): lista, ficha, fila local e envio.
 * Softsul Software
 *
 * Tudo fica no IndexedDB do aparelho, separado por destino e por pessoa
 * (quem empresta o celular não vê a fila do outro):
 *   base   o que veio de /api/coleta (fichas do escopo e itens em coleta)
 *   fila   cada gravação, na ordem: salvar, subir as fotos, enviar
 *   fotos  as fotos como saíram da câmera, até subirem
 * A fila anda sozinha quando o sinal volta (evento online e a cada 30 s).
 * Cada passo leva um uuid: repetir depois de uma queda não duplica nada.
 */
(function () {
  'use strict';
  var raiz = document.getElementById('coleta');
  if (!raiz || !window.indexedDB) { return; }

  var csrf = raiz.getAttribute('data-csrf');
  var NOME_BANCO = 'inventour-coleta-' + raiz.getAttribute('data-chave');
  var POR_PAGINA = 60;
  var $ = function (id) { return document.getElementById(id); };
  var base = null;          // {fichas, itens, em, destino, pode_criar}
  var filaAtual = [];       // registros da fila
  var enviando = false;
  var mostrar = POR_PAGINA;
  var atual = null;         // item aberto no formulário
  var fotosNovas = [];      // {uuid, blob, nome, url}
  var posicao = null;       // última posição do aparelho [lat, lng, precisão]

  // ------------------------------------------------------------ IndexedDB
  var bancoPromessa = new Promise(function (ok, falha) {
    var req = indexedDB.open(NOME_BANCO, 1);
    req.onupgradeneeded = function () {
      var db = req.result;
      db.createObjectStore('base');
      var fila = db.createObjectStore('fila', { keyPath: 'uuid' });
      fila.createIndex('ordem', 'ordem');
      var fotos = db.createObjectStore('fotos', { keyPath: 'uuid' });
      fotos.createIndex('coleta', 'coleta');
    };
    req.onsuccess = function () { ok(req.result); };
    req.onerror = function () { falha(req.error); };
  });
  function tx(loja, modo, fazer) {
    return bancoPromessa.then(function (db) {
      return new Promise(function (ok, falha) {
        var t = db.transaction(loja, modo);
        var resultado;
        t.oncomplete = function () { ok(resultado); };
        t.onerror = function () { falha(t.error); };
        t.onabort = function () { falha(t.error); };
        resultado = fazer(t.objectStore(loja));
        if (resultado && 'onsuccess' in resultado) {
          var req = resultado;
          req.onsuccess = function () { resultado = req.result; };
        }
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
  function icone(nome) { return '<svg class="icone" aria-hidden="true"><use href="#i-' + nome + '"></use></svg>'; }

  // ------------------------------------------------------------ estado na tela
  function mostrarEstado() {
    var online = navigator.onLine;
    var sinal = $('col-sinal');
    sinal.classList.toggle('online', online);
    sinal.classList.toggle('offline', !online);
    sinal.querySelector('span').textContent = online ? 'Com sinal' : 'Sem sinal';
    var n = filaAtual.length;
    $('col-fila').textContent = n ? (n === 1 ? '1 gravação esperando envio' : n + ' gravações esperando envio') : 'Nada esperando envio';
    $('col-fila').classList.toggle('pendente', n > 0);
    $('col-enviar').hidden = !n || !online;
    $('col-quando').textContent = base && base.em ? 'Lista de ' + new Date(base.em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
    var erros = filaAtual.filter(function (c) { return c.erro; });
    var caixa = $('col-erros');
    caixa.hidden = !erros.length;
    if (erros.length) {
      caixa.innerHTML = icone('alerta') + '<span>' + erros.map(function (c) {
        return '<b>' + esc(c.nome || 'Item') + ':</b> ' + esc(c.erro) + ' <button type="button" class="como-link" data-descartar="' + esc(c.uuid) + '">descartar</button>';
      }).join('<br>') + '</span>';
    }
  }

  function fichaPorId(id) {
    return (base && base.fichas || []).filter(function (f) { return f.id === id; })[0] || null;
  }

  // Os itens da base com o que ainda está na fila por cima (a tela mostra o
  // que a pessoa gravou, mesmo antes de subir).
  function itensComFila() {
    var mapa = {};
    var lista = (base && base.itens || []).map(function (i) {
      var copia = Object.assign({}, i, { chave: 'i' + i.id });
      mapa[copia.chave] = copia;
      return copia;
    });
    filaAtual.slice().sort(function (a, b) { return a.ordem - b.ordem; }).forEach(function (c) {
      var chave = c.item_id ? 'i' + c.item_id : 'n' + c.local_id;
      var alvo = mapa[chave] || (c.item_id ? mapa['n' + c.local_id] : null);
      if (!alvo) {
        alvo = { id: c.item_id || null, local_id: c.local_id, chave: chave, ficha_id: c.ficha_id, situacao: 'em_campo', fotos: 0, nome: '' };
        mapa[chave] = alvo;
        lista.unshift(alvo);
      }
      Object.assign(alvo, c.dados);
      alvo.na_fila = true;
      alvo.fotos_fila = (alvo.fotos_fila || 0) + (c.fotos || 0);
      if (c.enviar) { alvo.vai_enviar = true; }
    });
    return lista;
  }

  function desenharLista() {
    var busca = ($('col-busca').value || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    var ficha = parseInt($('col-ficha-filtro').value, 10) || 0;
    var soDevolvidos = $('col-so-devolvidos').checked;
    var itens = itensComFila().filter(function (i) {
      if (ficha && i.ficha_id !== ficha) { return false; }
      if (soDevolvidos && !i.devolucao_nota) { return false; }
      if (!busca) { return true; }
      var t = [i.nome, i.logradouro, i.bairro].join(' ').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
      return t.indexOf(busca) !== -1;
    });
    var lista = $('col-lista');
    lista.innerHTML = itens.slice(0, mostrar).map(function (i) {
      var f = fichaPorId(i.ficha_id);
      var selo = i.na_fila ? '<span class="pilula at">' + (i.vai_enviar ? 'vai para a conferência' : 'na fila do aparelho') + '</span>'
        : (i.devolucao_nota ? '<span class="pilula ruim">devolvido</span>'
        : (i.situacao === 'pre_cadastro' ? '<span class="pilula nu">pré-cadastro</span>' : '<span class="pilula at">em campo</span>'));
      var onde = [i.logradouro, i.numero, i.bairro].filter(Boolean).join(', ');
      var fotos = (i.fotos || 0) + (i.fotos_fila || 0);
      return '<li><button type="button" class="coleta-item" data-chave="' + esc(i.chave) + '">'
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
    var vazio = $('col-vazio');
    vazio.hidden = itens.length > 0;
    if (!itens.length) {
      $('col-vazio-titulo').textContent = base ? 'Nenhum item para coletar aqui.' : 'Ainda sem a lista neste aparelho.';
      $('col-vazio-texto').textContent = base ? 'Ajuste a busca, ou cadastre um item novo.' : 'Abra esta tela com sinal uma vez para baixar a lista.';
    }
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
    $('col-fichas').innerHTML = base.fichas.map(function (f) {
      return '<li><button type="button" class="como-link" data-nova-ficha="' + f.id + '"><b class="dado">' + esc(f.codigo) + '</b> ' + esc(f.nome) + '</button></li>';
    }).join('');
    $('col-novo').hidden = !base.pode_criar || !base.fichas.length;
  }

  function recarregarTela() {
    return Promise.all([ler('base', 'dados'), todos('fila')]).then(function (r) {
      base = r[0] || null;
      filaAtual = r[1] || [];
      if (base) { preencherFiltroDeFichas(); }
      mostrarEstado();
      desenharLista();
    });
  }

  // ------------------------------------------------------------ servidor
  function erroDeSessao(r) {
    if (r.status === 401) { $('col-sessao').hidden = false; throw new Error('sessao'); }
    return r;
  }
  function baixarLista() {
    if (!navigator.onLine) { return Promise.resolve(false); }
    return fetch('/api/coleta', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(erroDeSessao)
      .then(function (r) { return r.ok && !r.redirected ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.ok) { return false; }
        csrf = d.csrf || csrf;
        $('col-sessao').hidden = true;
        return gravar('base', d, 'dados').then(function () { return true; });
      })
      .catch(function () { return false; });
  }
  function postar(url, corpo, ehForm) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: ehForm ? { 'X-CSRF': csrf } : { 'X-CSRF': csrf, 'Content-Type': 'application/json' },
      body: ehForm ? corpo : JSON.stringify(corpo)
    }).then(erroDeSessao).then(function (r) {
      if (r.status === 403) {
        // Token velho (a página veio do cache): pega um novo e tenta de novo uma vez.
        return baixarLista().then(function () {
          return fetch(url, { method: 'POST', credentials: 'same-origin',
            headers: ehForm ? { 'X-CSRF': csrf } : { 'X-CSRF': csrf, 'Content-Type': 'application/json' },
            body: ehForm ? corpo : JSON.stringify(corpo) }).then(erroDeSessao);
        });
      }
      return r;
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, motivo: 'resposta' }; }).then(function (j) { j.status = r.status; return j; });
    });
  }

  var MOTIVOS = {
    situacao: 'o item já saiu da coleta (foi enviado ou homologado por outra pessoa). A gravação não foi aplicada.',
    item: 'o item não existe mais ou saiu do seu escopo.',
    ficha: 'a ficha saiu do seu escopo.',
    permissao: 'você não tem mais permissão para coletar neste destino.',
    nome: 'falta o nome.'
  };

  // Sobe a fila, na ordem. Para no primeiro erro de rede, e tenta de novo depois.
  function sincronizar() {
    if (enviando || !navigator.onLine) { return Promise.resolve(); }
    enviando = true;
    raiz.classList.add('enviando');
    return todos('fila').then(function (fila) {
      fila.sort(function (a, b) { return a.ordem - b.ordem; });
      var passo = Promise.resolve();
      var parou = false;
      fila.forEach(function (c) {
        passo = passo.then(function () { return parou || c.erro ? null : processar(c).catch(function () { parou = true; }); });
      });
      return passo;
    }).then(function () {
      return todos('fila').then(function (f) { return f.length ? null : baixarLista(); });
    }).then(function () {
      enviando = false;
      raiz.classList.remove('enviando');
      return recarregarTela();
    }, function () {
      enviando = false;
      raiz.classList.remove('enviando');
      return recarregarTela();
    });
  }

  function processar(c) {
    var p = Promise.resolve();
    if (c.etapa === 'salvar') {
      p = p.then(function () {
        return postar('/api/coleta', { acao: 'salvar', uuid: c.uuid, item_id: c.item_id, ficha_id: c.ficha_id, dados: c.dados, aqui: c.aqui });
      }).then(function (r) {
        if (!r.ok) {
          if (r.status >= 500 || r.motivo === 'resposta') { throw new Error('servidor'); }
          c.erro = 'não foi gravado: ' + (MOTIVOS[r.motivo] || r.motivo || 'erro');
          return gravar('fila', c).then(function () { throw new Error('parar-este'); });
        }
        c.item_id = r.item_id;
        c.etapa = 'fotos';
        // As gravações seguintes do mesmo item novo passam a conhecer o id.
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
            form.append('foto', f.blob, f.nome || 'foto.jpg');
            return postar('/api/coleta-foto', form, true).then(function (r) {
              if (r.ok) { return apagar('fotos', f.uuid); }
              if (r.status >= 500) { throw new Error('servidor'); }
              // Foto recusada (formato, tamanho): avisa e segue sem ela.
              c.aviso = (c.aviso ? c.aviso + ' ' : '') + (r.mensagem || 'Uma foto não foi aceita.');
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
      if (c.etapa === 'feito') { return apagar('fila', c.uuid); }
      return null;
    }).catch(function (e) {
      if (e && e.message === 'parar-este') { return null; }
      throw e;
    });
  }

  // ------------------------------------------------------------ formulário
  var form = $('col-form');
  var dialogo = $('col-dialogo');
  var CAMPOS = ['nome', 'tipo_id', 'zona', 'logradouro', 'numero', 'bairro', 'referencia', 'plus_code', 'telefone', 'whatsapp', 'instagram', 'site', 'conservacao'];

  function textoGps(lat, lng, precisao) {
    if (lat == null || lat === '') { return 'Sem coordenada'; }
    return Number(lat).toFixed(6) + ', ' + Number(lng).toFixed(6) + (precisao ? ' (± ' + Math.round(precisao) + ' m)' : '');
  }

  function abrirItem(item) {
    atual = item;
    var f = fichaPorId(item.ficha_id);
    $('col-titulo').textContent = item.nome || 'Novo item';
    $('col-subtitulo').textContent = f ? f.codigo + ' ' + f.nome : '';
    var tipos = f ? f.tipos : [];
    $('c-tipo-campo').hidden = !tipos.length;
    $('c-tipo').innerHTML = '<option value="">Escolha</option>' + tipos.map(function (t) { return '<option value="' + t.id + '">' + esc(t.nome) + '</option>'; }).join('');
    CAMPOS.forEach(function (c) {
      var el = form.elements[c];
      if (el) { el.value = item[c] == null ? '' : String(item[c]); }
    });
    if (form.elements.instagram.value) { form.elements.instagram.value = '@' + form.elements.instagram.value.replace(/^@/, ''); }
    $('c-lat').value = item.latitude == null ? '' : item.latitude;
    $('c-lng').value = item.longitude == null ? '' : item.longitude;
    $('c-gps-texto').textContent = textoGps(item.latitude, item.longitude);
    form.elements.nota.value = '';
    $('c-enviar').checked = false;
    var dev = $('col-devolucao');
    dev.hidden = !item.devolucao_nota;
    dev.querySelector('span').textContent = item.devolucao_nota ? 'Devolvido pela conferência: ' + item.devolucao_nota : '';
    fotosNovas.forEach(function (x) { URL.revokeObjectURL(x.url); });
    fotosNovas = [];
    desenharMiniaturas();
    mostrarPendencias();
    dialogo.showModal();
    form.elements.nome.focus();
  }

  function desenharMiniaturas() {
    $('c-miniaturas').innerHTML = fotosNovas.map(function (x, n) {
      return '<li><img src="' + x.url + '" alt=""><button type="button" data-tirar-foto="' + n + '" aria-label="Tirar esta foto">' + icone('fechar') + '</button></li>';
    }).join('');
  }

  function mostrarPendencias() {
    if (!atual) { return; }
    var f = fichaPorId(atual.ficha_id);
    var falta = [];
    if (!form.elements.nome.value.trim()) { falta.push('nome'); }
    if (f && f.forma !== 'municipio') {
      if (f.tipos.length && !form.elements.tipo_id.value) { falta.push('tipo'); }
      if (!form.elements.logradouro.value.trim() && !form.elements.referencia.value.trim()) { falta.push('endereço ou referência'); }
      if (!$('c-lat').value && !form.elements.plus_code.value.trim()) { falta.push('coordenada'); }
      if (!form.elements.conservacao.value) { falta.push('conservação'); }
      if (!(atual.fotos || 0) && !(atual.fotos_fila || 0) && !fotosNovas.length) { falta.push('ao menos uma foto'); }
    }
    var caixa = $('c-pendencias');
    caixa.className = 'coleta-pendencias ' + (falta.length ? 'falta' : 'pronto');
    caixa.innerHTML = falta.length
      ? icone('alerta') + '<span><b>Para homologar falta:</b> ' + esc(falta.join(', ')) + '. Pode gravar assim mesmo e voltar depois.</span>'
      : icone('check') + '<span><b>Núcleo mínimo completo.</b> Já pode ir para a conferência.</span>';
  }

  form.addEventListener('input', mostrarPendencias);
  form.addEventListener('change', mostrarPendencias);

  // Enter avança para o próximo campo, como nas outras fichas.
  form.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' || ev.target.tagName === 'TEXTAREA' || ev.target.tagName === 'BUTTON') { return; }
    ev.preventDefault();
    var campos = Array.prototype.filter.call(form.querySelectorAll('input:not([type=hidden]):not([type=file]), select, textarea'), function (el) {
      return !el.disabled && el.offsetParent !== null;
    });
    var i = campos.indexOf(ev.target);
    if (i > -1 && campos[i + 1]) { campos[i + 1].focus(); }
  });

  $('c-gps').addEventListener('click', function () {
    var botao = this;
    if (!navigator.geolocation) { $('c-gps-texto').textContent = 'Este aparelho não dá a localização.'; return; }
    botao.disabled = true;
    $('c-gps-texto').textContent = 'Buscando o GPS...';
    navigator.geolocation.getCurrentPosition(function (p) {
      botao.disabled = false;
      posicao = [p.coords.latitude, p.coords.longitude, p.coords.accuracy];
      $('c-lat').value = p.coords.latitude.toFixed(6);
      $('c-lng').value = p.coords.longitude.toFixed(6);
      $('c-gps-texto').textContent = textoGps(p.coords.latitude, p.coords.longitude, p.coords.accuracy);
      mostrarPendencias();
    }, function (e) {
      botao.disabled = false;
      $('c-gps-texto').textContent = e.code === 1 ? 'O aparelho não deixou usar a localização.' : 'Não deu para pegar o GPS agora. Tente ao ar livre.';
    }, { enableHighAccuracy: true, timeout: 25000, maximumAge: 10000 });
  });

  function juntarFotos(arquivos) {
    Array.prototype.forEach.call(arquivos || [], function (a) {
      if (!/^image\/(jpeg|png|webp)$/.test(a.type)) { return; }
      fotosNovas.push({ uuid: uuid(), blob: a, nome: a.name, url: URL.createObjectURL(a) });
    });
    desenharMiniaturas();
    mostrarPendencias();
  }
  // A zona de upload da casa (zonaDeUpload) põe os arquivos no campo e avisa;
  // aqui eles vão para a lista local, e o campo fica livre para a próxima.
  var campoFotos = document.querySelector('#c-zona-fotos input[type=file]');
  campoFotos.addEventListener('arquivos-escolhidos', function () {
    juntarFotos(campoFotos.files);
    var aviso = document.querySelector('#c-zona-fotos .zona-escolhido');
    if (aviso) { aviso.hidden = true; }
    try { campoFotos.value = ''; } catch (e) {}
  });
  $('c-miniaturas').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-tirar-foto]');
    if (!b) { return; }
    var n = parseInt(b.getAttribute('data-tirar-foto'), 10);
    URL.revokeObjectURL(fotosNovas[n].url);
    fotosNovas.splice(n, 1);
    desenharMiniaturas();
    mostrarPendencias();
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!form.elements.nome.value.trim()) { form.elements.nome.focus(); return; }
    var dados = {};
    CAMPOS.forEach(function (c) { var el = form.elements[c]; if (el) { dados[c] = el.value.trim(); } });
    dados.tipo_id = dados.tipo_id ? parseInt(dados.tipo_id, 10) : null;
    if ($('c-lat').value) { dados.latitude = parseFloat($('c-lat').value); dados.longitude = parseFloat($('c-lng').value); }
    dados.instagram = dados.instagram.replace(/^@/, '');
    var nota = form.elements.nota.value.trim();
    // Só vai o que mudou (menos tráfego e menos chance de apagar o que outro gravou).
    var mudou = {};
    Object.keys(dados).forEach(function (k) {
      var antes = atual[k] == null ? '' : String(atual[k]);
      var agora = dados[k] == null ? '' : String(dados[k]);
      if (antes !== agora) { mudou[k] = dados[k]; }
    });
    if (nota) { mudou.nota = nota; }
    var c = {
      uuid: uuid(), uuid_envio: uuid(), ordem: Date.now(),
      item_id: atual.id || null, local_id: atual.local_id || uuid(), ficha_id: atual.ficha_id,
      nome: dados.nome, dados: mudou, enviar: $('c-enviar').checked, fotos: fotosNovas.length,
      aqui: posicao ? [posicao[0], posicao[1]] : null, etapa: 'salvar'
    };
    if (!c.item_id && !atual.local_id) { mudou.nome = dados.nome; }
    var fotos = fotosNovas.map(function (x) { return { uuid: x.uuid, coleta: c.uuid, blob: x.blob, nome: x.nome }; });
    var gravacoes = [gravar('fila', c)].concat(fotos.map(function (f) { return gravar('fotos', f); }));
    Promise.all(gravacoes).then(function () {
      fotosNovas.forEach(function (x) { URL.revokeObjectURL(x.url); });
      fotosNovas = [];
      dialogo.close();
      return recarregarTela();
    }).then(function () {
      sincronizar();
    }).catch(function () {
      alert('Não consegui guardar no aparelho. Veja se há espaço livre e tente de novo.');
    });
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
  $('col-novo').addEventListener('click', function () { $('col-escolha').showModal(); });
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
  ['col-busca', 'col-ficha-filtro', 'col-so-devolvidos'].forEach(function (id) {
    $(id).addEventListener(id === 'col-busca' ? 'input' : 'change', function () { mostrar = POR_PAGINA; desenharLista(); });
  });
  $('col-mais').addEventListener('click', function () { mostrar += POR_PAGINA; desenharLista(); });
  $('col-enviar').addEventListener('click', sincronizar);
  $('col-atualizar').addEventListener('click', function () {
    var b = this;
    b.disabled = true;
    sincronizar().then(function () { return baixarLista(); }).then(function (ok) {
      b.disabled = false;
      if (!ok && !navigator.onLine) { alert('Sem sinal agora. A lista atualiza quando a internet voltar.'); }
      return recarregarTela();
    });
  });
  window.addEventListener('online', function () { mostrarEstado(); sincronizar(); });
  window.addEventListener('offline', mostrarEstado);
  setInterval(function () { if (filaAtual.length) { sincronizar(); } }, 30000);

  // ------------------------------------------------------------ início
  recarregarTela().then(function () {
    return baixarLista();
  }).then(function () {
    return recarregarTela();
  }).then(sincronizar);

  // Página e arquivos guardados para abrir sem sinal.
  if ('serviceWorker' in navigator) {
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

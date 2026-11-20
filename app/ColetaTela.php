<?php
/**
 * A tela da coleta de campo, a mesma no painel (/coleta) e no app InvenTour
 * Campo (mobile/campo/www, gerada por scripts/gerar_www_campo.php).
 * Softsul Software
 *
 * Só a marcação; quem trabalha é o assets/coleta.js. A diferença entre os
 * dois lugares é pequena e fica aqui: no app as fotos vêm da câmera do
 * aparelho (com o GPS do momento), no painel pela zona de arrastar e soltar.
 * A ficha do item fica em abas (regra do Luiz: muito dado vai em abas) numa
 * janela larga, com uma só área que rola.
 */
declare(strict_types=1);
require_once __DIR__ . '/Inventario.php';

/**
 * A barra de sincronização (sinal, fila, Histórico, Sincronizar). No app fica
 * no topo da coleta; no painel, na aba Aplicativo (lá gravar já envia).
 */
function telaEstadoColeta(string $botaoExtra = '', bool $historicoLocal = true): string
{
    ob_start();
    ?>
<div class="coleta-estado" aria-live="polite">
  <span class="sinal" id="col-sinal"><i></i><span>Verificando</span></span>
  <span class="fila-local" id="col-fila">Nada esperando envio</span>
  <span class="quando" id="col-quando"></span>
  <span class="coleta-botoes">
    <?= $botaoExtra ?>
    <?php if ($historicoLocal): ?>
    <button type="button" class="botao claro" id="col-historico" title="O que já foi sincronizado neste aparelho"><?= icone('relogio') ?>Histórico</button>
    <?php else: ?>
    <button type="button" class="botao claro" data-abre-dialogo="app-historico-dialogo" title="O que a equipe sincronizou nos últimos 30 dias"><?= icone('relogio') ?>Histórico</button>
    <?php endif; ?>
    <button type="button" class="botao" id="col-sincronizar"><?= icone('trocar') ?><span>Sincronizar</span></button>
  </span>
</div>
<div class="sinc-progresso" id="col-progresso" hidden><div class="barra"><i></i></div><span></span></div>
<?php
    return (string) ob_get_clean();
}

/**
 * O horário da semana, o mesmo no painel (item.php, data-semana, tratado
 * pelo inventour.js) e na coleta (data-semana-coleta, pelo coleta.js). Cada
 * dia tem um período e, com "Pausa", um segundo (o intervalo do almoço):
 * h_abre[dia] e h_fecha[dia], h_abre2[dia] e h_fecha2[dia].
 */
function semanaDeHorario(array $horario, string $marca = 'data-semana', bool $soLeitura = false): string
{
    $ro = $soLeitura ? ' readonly' : '';
    $dis = $soLeitura ? ' disabled' : '';
    ob_start();
    ?>
<div class="semana<?= !empty($horario['24h']) ? ' dia-todo' : '' ?>" <?= $marca ?>>
  <div class="semana-topo">
    <label class="check"><input type="checkbox" name="h24" value="1" data-h24<?= !empty($horario['24h']) ? ' checked' : '' ?><?= $dis ?>><i></i>Funciona 24 horas</label>
    <?php if (!$soLeitura): ?>
    <span class="semana-atalhos">
      <button type="button" class="como-link" data-copiar-dias="uteis"><?= icone('copiar') ?>Segunda para os dias úteis</button>
      <button type="button" class="como-link" data-copiar-dias="todos"><?= icone('copiar') ?>Segunda para todos</button>
    </span>
    <?php endif; ?>
  </div>
  <p class="semana-24h"><?= icone('relogio') ?>Aberto todos os dias, a qualquer hora.</p>
  <ul class="semana-dias">
  <?php foreach (Inventario::DIAS as $dia => $rotulo):
    $f1 = $horario[$dia][0] ?? ['', ''];
    $f2 = $horario[$dia][1] ?? ['', ''];
    $aberto = $f1[0] !== '' && $f1[1] !== '';
    $pausa = $aberto && $f2[0] !== '' && $f2[1] !== ''; ?>
    <li class="dia<?= $aberto ? '' : ' fechado' ?><?= $pausa ? ' com-pausa' : '' ?>" data-dia="<?= $dia ?>">
      <span class="dia-nome"><b><?= e(mb_substr($rotulo, 0, 3)) ?></b><span><?= e($rotulo) ?></span></span>
      <label class="liga" title="Aberto ou fechado">
        <input type="checkbox" data-aberto<?= $aberto ? ' checked' : '' ?><?= $dis ?> aria-label="<?= e($rotulo) ?>, aberto">
        <i></i><em class="liga-sim">Aberto</em><em class="liga-nao">Fechado</em>
      </label>
      <span class="faixa">
        <span class="periodo">
          <span class="entrada hora"><input type="time" name="h_abre[<?= $dia ?>]" value="<?= e((string) $f1[0]) ?>" aria-label="<?= e($rotulo) ?>, abre"<?= $ro ?>></span>
          <span class="ate">às</span>
          <span class="entrada hora"><input type="time" name="h_fecha[<?= $dia ?>]" value="<?= e((string) $f1[1]) ?>" aria-label="<?= e($rotulo) ?>, fecha"<?= $ro ?>></span>
        </span>
        <span class="periodo periodo-2">
          <span class="ate">e</span>
          <span class="entrada hora"><input type="time" name="h_abre2[<?= $dia ?>]" value="<?= e((string) $f2[0]) ?>" aria-label="<?= e($rotulo) ?>, volta da pausa"<?= $ro ?>></span>
          <span class="ate">às</span>
          <span class="entrada hora"><input type="time" name="h_fecha2[<?= $dia ?>]" value="<?= e((string) $f2[1]) ?>" aria-label="<?= e($rotulo) ?>, fecha de novo"<?= $ro ?>></span>
          <?php if (!$soLeitura): ?><button type="button" class="tirar-pausa" data-tirar-pausa title="Sem pausa: um período só" aria-label="Tirar a pausa de <?= e($rotulo) ?>"><?= icone('fechar') ?></button><?php endif; ?>
        </span>
        <?php if (!$soLeitura): ?><button type="button" class="como-link pausa" data-pausa title="Fecha no almoço: dois períodos no dia"><?= icone('mais') ?>Pausa</button><?php endif; ?>
      </span>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php
    return (string) ob_get_clean();
}

function telaColeta(bool $noApp, bool $comEstado = true): string
{
    ob_start();
    ?>
<?= $comEstado ? telaEstadoColeta() : '' ?>
<p class="aviso atencao" id="col-sessao" hidden><?= icone('alerta') ?><span id="col-sessao-texto"></span></p>
<p class="aviso ruim" id="col-erros" hidden></p>
<p class="aviso bom" id="col-resultado" hidden></p>

<div class="coleta-fixo">
<div class="filtros coleta-filtros">
  <div class="entrada"><?= icone('busca') ?><input type="search" id="col-busca" placeholder="Nome, rua ou bairro" aria-label="Buscar na coleta" autocomplete="off"></div>
  <div class="entrada"><?= icone('manual') ?><select id="col-ficha-filtro" aria-label="Ficha"><option value="">Todas as fichas</option></select></div>
</div>
<?php /* Situação em fichas com a contagem: o devolvido pela conferência é o
         que a pessoa mais precisa achar, e fica à vista (e no topo da lista). */ ?>
<div class="coleta-situacoes" id="col-situacoes" role="group" aria-label="Situação"></div>
</div>
<p class="aviso atencao coleta-devolvidos" id="col-aviso-devolvidos" hidden><?= icone('alerta') ?><span></span></p>

<p class="coleta-conta nulo" id="col-conta"></p>
<ul class="coleta-lista" id="col-lista"></ul>
<div class="vazio" id="col-vazio" hidden><?= icone('camera') ?><p><strong id="col-vazio-titulo">Carregando.</strong></p><p id="col-vazio-texto"></p></div>
<button type="button" class="botao claro" id="col-mais" hidden>Mostrar mais</button>

<dialog class="coleta-dialogo pequeno" id="col-historico-dialogo" aria-labelledby="col-hist-titulo">
  <div class="cab-dialogo"><div><h2 id="col-hist-titulo">Histórico de sincronização</h2><p class="nulo">O que este aparelho enviou ao servidor, da mais recente para a mais antiga.</p></div>
    <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button></div>
  <div class="corpo-dialogo"><ul class="hist-lista" id="col-hist-lista"></ul></div>
</dialog>

<dialog class="coleta-dialogo pequeno" id="col-escolha" aria-labelledby="col-escolha-titulo">
  <div class="cab-dialogo"><h2 id="col-escolha-titulo">Qual ficha?</h2>
    <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button></div>
  <?php /* A busca fica parada no alto; só a lista rola. */ ?>
  <div class="busca-dialogo">
    <div class="entrada"><?= icone('busca') ?><input type="search" id="col-fichas-busca" placeholder="Hotel, cachoeira, praça..." aria-label="Buscar a ficha" autocomplete="off"></div>
  </div>
  <div class="corpo-dialogo">
    <ul class="lista-simples coleta-fichas" id="col-fichas"></ul>
  </div>
</dialog>

<?php
    $campo = function (string $nome, string $rotulo, string $col = 'c-6', array $o = []): string {
        $id = 'c-' . str_replace('_', '-', $nome);
        $attrs = '';
        foreach ($o['attrs'] ?? [] as $k => $v) {
            $attrs .= ' ' . $k . ($v === true ? '' : '="' . e((string) $v) . '"');
        }
        $tipo = $o['tipo'] ?? 'text';
        $ic = $o['icone'] ?? '';
        $entrada = match (true) {
            isset($o['opcoes']) => '<div class="entrada">' . $ic . '<select id="' . $id . '" name="' . $nome . '"' . $attrs . '><option value="">' . e($o['vazio'] ?? 'Não informado') . '</option>'
                . implode('', array_map(fn ($k, $r) => '<option value="' . e((string) $k) . '">' . e((string) $r) . '</option>', array_keys($o['opcoes']), $o['opcoes'])) . '</select></div>',
            $tipo === 'textarea' => '<div class="entrada">' . $ic . '<textarea id="' . $id . '" name="' . $nome . '"' . $attrs . '></textarea></div>',
            default => '<div class="entrada">' . $ic . '<input type="' . $tipo . '" id="' . $id . '" name="' . $nome . '" autocomplete="off"' . $attrs . '></div>',
        };
        return '<div class="campo ' . $col . '"' . ($o['onde'] ?? '') . '><label for="' . $id . '">' . e($rotulo) . '</label>'
            . $entrada . (isset($o['ajuda']) ? '<span class="ajuda">' . e($o['ajuda']) . '</span>' : '') . '</div>';
    };
    $caixas = function (string $nome, array $opcoes): string {
        $h = '<div class="caixas">';
        foreach ($opcoes as $op) {
            $h .= '<label class="check"><input type="checkbox" name="' . e($nome) . '[]" value="' . e((string) $op) . '"><i></i>' . e((string) $op) . '</label>';
        }
        return $h . '</div>';
    };
    $naoMunicipio = ' data-nao-municipio';
    ?>
<dialog class="coleta-dialogo" id="col-dialogo" aria-labelledby="col-titulo">
  <form method="dialog" id="col-form" novalidate>
    <div class="cab-dialogo">
      <div><h2 id="col-titulo">Item</h2><p class="nulo" id="col-subtitulo"></p></div>
      <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button>
    </div>
    <?php /* As abas são as da ficha do painel (item.php). As que a ficha não
             tem (município não tem localização, contato, acessibilidade...)
             somem; no celular a barra corre de lado. */ ?>
    <nav class="abas-chamado abas-coleta" role="tablist" aria-label="Partes da ficha">
      <button type="button" role="tab" data-aba-coleta="ident" aria-selected="true"><?= icone('etiqueta') ?>Identificação</button>
      <button type="button" role="tab" data-aba-coleta="local" aria-selected="false"><?= icone('mapa') ?>Localização<span class="conta-aba" id="aba-gps-sinal" hidden>GPS</span></button>
      <button type="button" role="tab" data-aba-coleta="contato" aria-selected="false"><?= icone('whatsapp') ?>Contato</button>
      <button type="button" role="tab" data-aba-coleta="funcionamento" aria-selected="false"<?= $naoMunicipio ?>><?= icone('relogio') ?>Funcionamento</button>
      <button type="button" role="tab" data-aba-coleta="ficha" aria-selected="false"><?= icone('lista') ?><span id="aba-ficha-rotulo">Ficha</span><span class="conta-aba" id="aba-ficha-conta" hidden></span></button>
      <button type="button" role="tab" data-aba-coleta="acessibilidade" aria-selected="false"<?= $naoMunicipio ?>><?= icone('cadeirante') ?>Acessibilidade</button>
      <button type="button" role="tab" data-aba-coleta="tribos" aria-selected="false"<?= $naoMunicipio ?>><?= icone('usuarios') ?>Tribos</button>
      <button type="button" role="tab" data-aba-coleta="textos" aria-selected="false"><?= icone('lapis') ?>Textos</button>
      <button type="button" role="tab" data-aba-coleta="fotos" aria-selected="false"><?= icone('camera') ?>Fotos<span class="conta-aba" id="aba-fotos-conta">0</span></button>
      <button type="button" role="tab" data-aba-coleta="conferencia" aria-selected="false"><?= icone('certificado') ?>Conferência<span class="conta-aba alerta-aba" id="aba-falta" hidden></span></button>
    </nav>
    <div class="corpo-dialogo">
      <p class="aviso atencao" id="col-devolucao" hidden><?= icone('alerta') ?><span></span></p>
      <p class="aviso ruim" id="col-form-erro" role="alert" hidden><?= icone('alerta') ?><span></span></p>
      <p class="aviso info" id="col-ficha-ajuda" hidden><?= icone('manual') ?><span></span></p>

      <section class="painel-coleta" data-painel-coleta="ident">
        <div class="grade">
          <?= $campo('nome', 'Nome', 'c-12', ['attrs' => ['maxlength' => 180, 'required' => true], 'ajuda' => 'Nome fantasia ou denominação oficial, como está na fachada ou na placa.']) ?>
          <div class="campo c-12" id="c-tipo-campo"><label for="c-tipo">Tipo</label><div class="entrada"><select id="c-tipo" name="tipo_id"><option value="">Escolha</option></select></div></div>
          <?= $campo('nome_popular', 'Nome popular', 'c-6', ['attrs' => ['maxlength' => 180]]) ?>
          <?= $campo('cnpj', 'CNPJ', 'c-6', ['attrs' => ['maxlength' => 18, 'autocapitalize' => 'characters', 'placeholder' => '00.000.000/0000-00'], 'icone' => icone('predio'),
              'ajuda' => 'Inventarie também quem não tem CNPJ: o manual manda incluir todo estabelecimento aberto ao público.']) ?>
          <?= $campo('razao_social', 'Razão social', 'c-6', ['attrs' => ['maxlength' => 180], 'onde' => $naoMunicipio]) ?>
          <?= $campo('cnae', 'Código CNAE', 'c-4', ['attrs' => ['maxlength' => 10, 'inputmode' => 'numeric', 'placeholder' => '5510801'], 'onde' => $naoMunicipio]) ?>
          <?= $campo('atividade_economica', 'Atividade econômica', 'c-8', ['attrs' => ['maxlength' => 255], 'onde' => $naoMunicipio]) ?>
          <?= $campo('inscricao_municipal', 'Inscrição municipal', 'c-6', ['attrs' => ['maxlength' => 30], 'onde' => $naoMunicipio]) ?>
          <?= $campo('cadastur', 'Cadastur', 'c-6', ['attrs' => ['maxlength' => 30], 'onde' => $naoMunicipio]) ?>
          <?= $campo('cadastur_validade', 'Validade do Cadastur', 'c-6', ['tipo' => 'date', 'onde' => $naoMunicipio]) ?>
          <div class="campo c-4"<?= $naoMunicipio ?>><label for="c-natureza">Natureza</label>
            <div class="entrada"><select id="c-natureza" name="natureza"><option value="">Não informada</option>
              <?php foreach (Inventario::NATUREZAS as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r) ?></option><?php endforeach; ?></select></div>
            <div class="entrada outro-texto" data-outro-de="natureza" data-outro-valor="outra" hidden><input type="text" name="natureza_outra" maxlength="120" placeholder="Qual?" aria-label="Natureza: qual outra"></div></div>
          <div class="campo c-4"<?= $naoMunicipio ?>><label for="c-tipo-organizacao">Tipo de organização</label>
            <div class="entrada"><select id="c-tipo-organizacao" name="tipo_organizacao"><option value="">Não informado</option>
              <?php foreach (Inventario::TIPOS_ORGANIZACAO as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r) ?></option><?php endforeach; ?></select></div>
            <div class="entrada outro-texto" data-outro-de="tipo_organizacao" data-outro-valor="outros" hidden><input type="text" name="tipo_organizacao_outro" maxlength="120" placeholder="Qual?" aria-label="Tipo de organização: qual outro"></div></div>
          <?= $campo('jurisdicao', 'Jurisdição', 'c-4', ['opcoes' => Inventario::JURISDICOES, 'vazio' => 'Não informada', 'onde' => $naoMunicipio]) ?>
          <?= $campo('inicio_atividade', 'Início da atividade', 'c-4', ['attrs' => ['maxlength' => 10, 'inputmode' => 'numeric', 'placeholder' => 'dd/mm/aaaa ou o ano', 'data-data-ou-ano' => true], 'onde' => $naoMunicipio]) ?>
          <?= $campo('rede', 'Rede, holding ou franquia', 'c-8', ['attrs' => ['maxlength' => 120], 'onde' => $naoMunicipio]) ?>
          <?= $campo('funcionarios', 'Funcionários permanentes', 'c-4', ['tipo' => 'number', 'attrs' => ['min' => 0, 'inputmode' => 'numeric'], 'onde' => $naoMunicipio]) ?>
          <?= $campo('funcionarios_temporarios', 'Funcionários temporários', 'c-4', ['tipo' => 'number', 'attrs' => ['min' => 0, 'inputmode' => 'numeric'], 'onde' => $naoMunicipio]) ?>
          <?= $campo('funcionarios_pcd', 'Pessoas com deficiência (%)', 'c-4', ['attrs' => ['maxlength' => 6, 'inputmode' => 'decimal'], 'onde' => $naoMunicipio, 'ajuda' => 'Entre os funcionários.']) ?>
        </div>
      </section>

      <section class="painel-coleta" data-painel-coleta="local" hidden>
        <div class="coleta-gps">
          <div class="gps-texto"><b>Coordenada</b><span id="c-gps-texto">Sem coordenada</span><small id="c-gps-quando"></small></div>
          <span class="gps-botoes">
            <button type="button" class="botao" id="c-gps"><?= icone('mapa') ?>Usar o GPS daqui</button>
            <button type="button" class="botao claro" id="c-buscar-endereco" title="Busca rua, número, bairro e CEP desta coordenada (precisa de internet)"><?= icone('busca') ?>Endereço desta coordenada</button>
          </span>
          <input type="hidden" name="latitude" id="c-lat"><input type="hidden" name="longitude" id="c-lng">
        </div>
        <?php /* Com internet: o mapa (Google Maps incorporado, sem chave e sem
                 custo) e o endereço achado pela coordenada. Sem internet: só a
                 coordenada; nada de mapa guardado no aparelho. */ ?>
        <div class="mapa-online" id="c-mapa" hidden></div>
        <div class="endereco-achado" id="c-endereco" hidden><span id="c-endereco-texto"></span>
          <button type="button" class="botao mini" id="c-usar-endereco" hidden><?= icone('check') ?>Usar este endereço</button></div>
        <div class="grade">
          <?= $campo('zona', 'Zona', 'c-4', ['opcoes' => Inventario::ZONAS, 'vazio' => 'Não informada', 'onde' => $naoMunicipio]) ?>
          <?= $campo('extensao_km', 'Extensão no município (km)', 'c-4', ['attrs' => ['inputmode' => 'decimal'], 'onde' => ' data-so-linear']) ?>
          <?= $campo('cep', 'CEP', 'c-4', ['attrs' => ['maxlength' => 9, 'inputmode' => 'numeric', 'placeholder' => '00000-000']]) ?>
          <?= $campo('logradouro', 'Rua, rodovia ou linha', 'c-8', ['attrs' => ['maxlength' => 200]]) ?>
          <?= $campo('numero', 'Número', 'c-4', ['attrs' => ['maxlength' => 20, 'inputmode' => 'numeric']]) ?>
          <?= $campo('bairro', 'Bairro ou localidade', 'c-6', ['attrs' => ['maxlength' => 120]]) ?>
          <?= $campo('distrito', 'Distrito', 'c-6', ['attrs' => ['maxlength' => 120]]) ?>
          <?= $campo('complemento', 'Complemento', 'c-6', ['attrs' => ['maxlength' => 80]]) ?>
          <?= $campo('plus_code', 'Plus code', 'c-6', ['attrs' => ['maxlength' => 16, 'placeholder' => 'Q8FF+VM', 'autocapitalize' => 'characters']]) ?>
          <?= $campo('referencia', 'Ponto de referência', 'c-6', ['attrs' => ['maxlength' => 255, 'placeholder' => 'Em frente à praça, depois da ponte...'], 'onde' => $naoMunicipio]) ?>
          <?= $campo('maps_url', 'Link do mapa', 'c-12', ['tipo' => 'url', 'attrs' => ['maxlength' => 500, 'autocapitalize' => 'none', 'placeholder' => 'https://maps.app.goo.gl/...'], 'icone' => icone('mapa')]) ?>
          <?= $campo('sinalizacao_acesso', 'Sinalização de acesso', 'c-6', ['opcoes' => ['1' => 'Sim', '0' => 'Não'], 'vazio' => 'Não informada', 'onde' => $naoMunicipio]) ?>
          <?= $campo('sinalizacao_turistica', 'Sinalização turística', 'c-6', ['opcoes' => ['1' => 'Sim', '0' => 'Não'], 'vazio' => 'Não informada', 'onde' => $naoMunicipio]) ?>
        </div>
      </section>

      <section class="painel-coleta" data-painel-coleta="contato" hidden>
        <div class="grade">
          <?= $campo('telefone', 'Telefone', 'c-4', ['tipo' => 'tel', 'attrs' => ['inputmode' => 'tel', 'placeholder' => '(46) 3220-0000', 'data-telefone' => true]]) ?>
          <?= $campo('ramal', 'Ramal', 'c-3', ['attrs' => ['maxlength' => 10, 'inputmode' => 'numeric']]) ?>
          <?= $campo('whatsapp', 'WhatsApp', 'c-5', ['tipo' => 'tel', 'attrs' => ['inputmode' => 'tel', 'placeholder' => '(46) 99999-0000', 'data-telefone' => true], 'icone' => icone('whatsapp')]) ?>
          <?= $campo('email', 'E-mail', 'c-12', ['tipo' => 'email', 'attrs' => ['maxlength' => 150, 'autocapitalize' => 'none', 'inputmode' => 'email'], 'icone' => icone('emails')]) ?>
          <?= $campo('site', 'Site', 'c-6', ['tipo' => 'url', 'attrs' => ['maxlength' => 255, 'placeholder' => 'https://', 'autocapitalize' => 'none']]) ?>
          <?= $campo('instagram', 'Instagram', 'c-6', ['attrs' => ['maxlength' => 100, 'placeholder' => '@perfil', 'autocapitalize' => 'none']]) ?>
        </div>
        <p class="ajuda">Contato que o turista vê. O dado pessoal de quem atendeu não vai aqui.</p>
      </section>

      <section class="painel-coleta" data-painel-coleta="funcionamento" hidden>
        <?= semanaDeHorario([], 'data-semana-coleta') ?>
        <div class="grade">
          <?= $campo('h_feriados', 'Feriados', 'c-6', ['attrs' => ['maxlength' => 200]]) ?>
          <?= $campo('h_temporada', 'Temporada ou agendamento', 'c-6', ['attrs' => ['maxlength' => 200]]) ?>
        </div>
        <h3 class="sub-cartao">Formas de pagamento</h3>
        <?= $caixas('pagamentos', Inventario::PAGAMENTOS) ?>
        <h3 class="sub-cartao">Atendimento em</h3>
        <?= $caixas('idiomas', Inventario::IDIOMAS) ?>
      </section>

      <section class="painel-coleta" data-painel-coleta="ficha" hidden>
        <div class="grade" data-so-capacidade>
          <?= $campo('capacidade_publico', 'Público simultâneo (pessoas)', 'c-6', ['tipo' => 'number', 'attrs' => ['min' => 0, 'inputmode' => 'numeric'],
              'ajuda' => 'Vai para o EventCity, que compara com o público esperado dos eventos.']) ?>
        </div>
        <?php /* Os campos específicos da ficha (os do caderno do MTur) saem do
                 catálogo baixado na sincronização: o coleta.js monta aqui. */ ?>
        <div id="c-especificos" class="coleta-especificos"></div>
      </section>

      <section class="painel-coleta" data-painel-coleta="acessibilidade" hidden>
        <div class="grade">
          <?= $campo('acessibilidade_possui', 'Possui alguma facilidade para pessoas com deficiência ou mobilidade reduzida?', 'c-12', ['opcoes' => ['1' => 'Sim', '0' => 'Não'], 'vazio' => 'Não informado']) ?>
        </div>
        <p class="ajuda">Os subitens da seção 6 da ficha oficial. O portal resume em cinco selos simples para o turista.</p>
        <div id="c-acess"></div>
      </section>

      <section class="painel-coleta" data-painel-coleta="tribos" hidden>
        <p class="ajuda">Marque só o que o lugar comprovadamente oferece. Com duas facilidades ou mais, ganha o selo da tribo no portal (Bike Friendly, Pet Friendly...).</p>
        <div id="c-tribos" class="tribos-colunas"></div>
      </section>

      <section class="painel-coleta" data-painel-coleta="textos" hidden>
        <div class="grade">
          <?= $campo('descricao_promocional', 'Texto para o portal', 'c-12', ['tipo' => 'textarea', 'attrs' => ['rows' => 3, 'maxlength' => 5000], 'ajuda' => 'Convidativo, para o turista.']) ?>
          <?= $campo('descricao_tecnica', 'Descrição técnica', 'c-12', ['tipo' => 'textarea', 'attrs' => ['rows' => 4], 'ajuda' => 'Caracterização para o caderno oficial.']) ?>
          <?= $campo('referencias', 'Referências', 'c-12', ['tipo' => 'textarea', 'attrs' => ['rows' => 2], 'ajuda' => 'Fontes bibliográficas e documentais.']) ?>
          <div class="campo c-12"<?= $naoMunicipio ?>>
            <label class="check"><input type="checkbox" name="possui_experiencia" value="1"><i></i>Oferece experiência turística</label>
            <span class="ajuda">A experiência é cadastrada depois, no painel, e passa pela curadoria antes do portal.</span>
          </div>
        </div>
      </section>

      <section class="painel-coleta" data-painel-coleta="fotos" hidden>
        <?php if ($noApp): ?>
          <div class="coleta-camera">
            <button type="button" class="botao" id="c-tirar-foto"><?= icone('camera') ?>Tirar foto</button>
            <button type="button" class="botao claro" id="c-galeria"><?= icone('imagem') ?>Escolher da galeria (várias)</button>
          </div>
          <p class="ajuda">Cada foto tirada aqui guarda o GPS do aparelho na hora. Da galeria, marque quantas quiser de uma vez. Fachada, entrada, interior e o que o turista vê.</p>
        <?php else: ?>
          <div class="coleta-zona" id="c-zona-fotos"><?= zonaDeUpload('fotos_coleta', 'image/jpeg,image/png,image/webp', 'Tire ou arraste as fotos aqui',
            'Ficam no aparelho até sincronizar. O GPS da foto completa a coordenada.', false, true) ?></div>
        <?php endif; ?>
        <ul class="coleta-miniaturas" id="c-miniaturas"></ul>
        <p class="nulo" id="c-fotos-servidor"></p>
      </section>

      <section class="painel-coleta" data-painel-coleta="conferencia" hidden>
        <div class="grade">
          <?= $campo('conservacao', 'Estado geral de conservação', 'c-12', ['opcoes' => Inventario::CONSERVACAO, 'vazio' => 'Não avaliado', 'onde' => $naoMunicipio,
              'ajuda' => 'Parecer de quem inventaria; o trade não altera.']) ?>
          <?= $campo('nota', 'Observação de campo', 'c-12', ['tipo' => 'textarea', 'attrs' => ['rows' => 4, 'maxlength' => 2000, 'placeholder' => 'O que viu, com quem falou, o que conferir depois'],
              'ajuda' => 'Soma às observações do item, com a data. Só no painel e no caderno, nunca no portal.']) ?>
        </div>
        <div class="coleta-pendencias" id="c-pendencias"></div>
        <label class="check coleta-enviar"><input type="checkbox" id="c-enviar" name="enviar_conferencia" data-fora-da-ficha><i></i>Enviar para a conferência na próxima sincronização</label>
      </section>
    </div>
    <div class="pe-dialogo">
      <button type="button" class="botao claro" id="c-anterior" hidden><?= icone('seta', null, 'seta-voltar') ?>Anterior</button>
      <button type="button" class="botao claro" id="c-proxima">Próxima<?= icone('seta') ?></button>
      <span class="espaco"></span>
      <button type="button" class="botao claro" data-fechar>Cancelar</button>
      <button type="submit" class="botao" id="c-gravar"><?= icone('check') ?><?= $noApp ? 'Gravar no aparelho' : 'Gravar' ?></button>
    </div>
  </form>
</dialog>
<?php
    return (string) ob_get_clean();
}

<?php
/**
 * Coleta de campo: a tela do pesquisador, celular primeiro e sem sinal.
 * Softsul Software
 *
 * A tela só desenha a moldura; quem trabalha é o assets/coleta.js:
 * - baixa de /api/coleta as fichas do escopo e os itens em coleta, e guarda
 *   no IndexedDB do aparelho;
 * - cada gravação vai para uma fila local (com as fotos, como saíram da
 *   câmera) e sobe quando há sinal, com um uuid por envio para a repetição
 *   não duplicar;
 * - o /sw-coleta.js guarda esta página e os arquivos dela, para abrir sem
 *   sinal no meio da estrada.
 * O que a pessoa marca para a conferência só é enviado depois das fotos,
 * porque a foto conta no núcleo mínimo.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Esteira.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('coleta.ver');
Auth::exigirModuloLigado('coleta');
$u = Auth::usuarioOuNulo();

paginaInicio('Coleta de campo', 'coleta');
?>
<div class="cab-pag">
  <div>
    <h1>Coleta de campo</h1>
    <p>Funciona sem sinal: o que você gravar fica no aparelho e sobe sozinho quando a internet voltar. Abra esta tela uma vez com sinal antes de sair.</p>
  </div>
  <div class="acoes"><button type="button" class="botao" id="col-novo" hidden><?= icone('mais') ?>Novo item</button></div>
</div>

<div class="coleta" id="coleta" data-csrf="<?= e(Auth::tokenCsrf()) ?>" data-chave="<?= (int) $empresa['id'] ?>-<?= (int) $u['id'] ?>">
  <div class="coleta-estado" aria-live="polite">
    <span class="sinal" id="col-sinal"><i></i><span>Verificando o sinal</span></span>
    <span class="fila-local" id="col-fila">Nada esperando envio</span>
    <span class="quando" id="col-quando"></span>
    <span class="coleta-botoes">
      <button type="button" class="botao claro mini" id="col-enviar" hidden><?= icone('enviar') ?>Enviar agora</button>
      <button type="button" class="botao claro mini" id="col-atualizar"><?= icone('trocar') ?>Atualizar lista</button>
    </span>
  </div>
  <p class="aviso atencao" id="col-sessao" hidden><?= icone('alerta') ?><span>Sua sessão terminou. Nada se perdeu: <a href="/login?destino=%2Fcoleta">entre de novo</a> com sinal para enviar a fila.</span></p>
  <p class="aviso ruim" id="col-erros" hidden></p>

  <div class="filtros coleta-filtros">
    <div class="entrada"><?= icone('busca') ?><input type="search" id="col-busca" placeholder="Nome, rua ou bairro" aria-label="Buscar na coleta" autocomplete="off"></div>
    <div class="entrada"><?= icone('manual') ?><select id="col-ficha-filtro" aria-label="Ficha"><option value="">Todas as fichas</option></select></div>
    <label class="check"><input type="checkbox" id="col-so-devolvidos"><i></i>Só os devolvidos</label>
  </div>

  <p class="coleta-conta nulo" id="col-conta"></p>
  <ul class="coleta-lista" id="col-lista"></ul>
  <div class="vazio" id="col-vazio" hidden><?= icone('camera') ?><p><strong id="col-vazio-titulo">Carregando.</strong></p><p id="col-vazio-texto"></p></div>
  <button type="button" class="botao claro" id="col-mais" hidden>Mostrar mais</button>
</div>

<dialog class="coleta-dialogo" id="col-escolha" aria-labelledby="col-escolha-titulo">
  <div class="cab-dialogo"><h2 id="col-escolha-titulo">Qual ficha?</h2>
    <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button></div>
  <div class="corpo-dialogo"><ul class="lista-simples coleta-fichas" id="col-fichas"></ul></div>
</dialog>

<dialog class="coleta-dialogo" id="col-dialogo" aria-labelledby="col-titulo">
  <form method="dialog" id="col-form" novalidate>
    <div class="cab-dialogo">
      <div><h2 id="col-titulo">Item</h2><p class="nulo" id="col-subtitulo"></p></div>
      <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button>
    </div>
    <div class="corpo-dialogo">
      <p class="aviso atencao" id="col-devolucao" hidden><?= icone('alerta') ?><span></span></p>
      <div class="grade">
        <div class="campo c-12"><label for="c-nome">Nome</label><div class="entrada"><input type="text" id="c-nome" name="nome" maxlength="180" required autocomplete="off"></div></div>
        <div class="campo c-6" id="c-tipo-campo"><label for="c-tipo">Tipo</label><div class="entrada"><select id="c-tipo" name="tipo_id"><option value="">Escolha</option></select></div></div>
        <div class="campo c-6"><label for="c-zona">Zona</label><div class="entrada"><select id="c-zona" name="zona"><option value="">Não informada</option>
          <?php foreach (Inventario::ZONAS as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r) ?></option><?php endforeach; ?></select></div></div>
        <div class="campo c-8"><label for="c-logradouro">Rua, rodovia ou linha</label><div class="entrada"><input type="text" id="c-logradouro" name="logradouro" maxlength="200" autocomplete="off"></div></div>
        <div class="campo c-4"><label for="c-numero">Número</label><div class="entrada"><input type="text" id="c-numero" name="numero" maxlength="20" autocomplete="off"></div></div>
        <div class="campo c-6"><label for="c-bairro">Bairro ou distrito</label><div class="entrada"><input type="text" id="c-bairro" name="bairro" maxlength="120" autocomplete="off"></div></div>
        <div class="campo c-6"><label for="c-referencia">Ponto de referência</label><div class="entrada"><input type="text" id="c-referencia" name="referencia" maxlength="255" autocomplete="off"></div></div>
      </div>

      <div class="coleta-gps">
        <div class="gps-texto"><b>Coordenada</b><span id="c-gps-texto">Sem coordenada</span></div>
        <button type="button" class="botao claro" id="c-gps"><?= icone('mapa') ?>Usar o GPS daqui</button>
        <input type="hidden" name="latitude" id="c-lat"><input type="hidden" name="longitude" id="c-lng">
        <div class="campo"><label for="c-plus">Plus code</label><div class="entrada"><input type="text" id="c-plus" name="plus_code" maxlength="16" placeholder="Q8FF+VM" autocapitalize="characters" autocomplete="off"></div></div>
      </div>

      <div class="grade">
        <div class="campo c-6"><label for="c-telefone">Telefone</label><div class="entrada"><input type="tel" id="c-telefone" name="telefone" inputmode="tel" autocomplete="off"></div></div>
        <div class="campo c-6"><label for="c-whatsapp">WhatsApp</label><div class="entrada"><input type="tel" id="c-whatsapp" name="whatsapp" inputmode="tel" autocomplete="off"></div></div>
        <div class="campo c-6"><label for="c-instagram">Instagram</label><div class="entrada"><input type="text" id="c-instagram" name="instagram" maxlength="100" placeholder="@perfil" autocomplete="off"></div></div>
        <div class="campo c-6"><label for="c-site">Site</label><div class="entrada"><input type="url" id="c-site" name="site" maxlength="255" autocomplete="off"></div></div>
        <div class="campo c-12"><label for="c-conservacao">Estado de conservação</label><div class="entrada"><select id="c-conservacao" name="conservacao"><option value="">Não avaliado</option>
          <?php foreach (Inventario::CONSERVACAO as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r) ?></option><?php endforeach; ?></select></div></div>
        <div class="campo c-12"><label for="c-nota">Observação de campo</label><div class="entrada"><textarea id="c-nota" name="nota" rows="3" maxlength="2000" placeholder="O que viu, com quem falou, o que conferir depois"></textarea></div></div>
      </div>

      <h3 class="sub-cartao">Fotos</h3>
      <div class="coleta-zona" id="c-zona-fotos"><?= zonaDeUpload('fotos_coleta', 'image/jpeg,image/png,image/webp', 'Tire ou arraste as fotos aqui',
        'Ficam no aparelho até haver sinal. O GPS da foto completa a coordenada.', false, true) ?></div>
      <ul class="coleta-miniaturas" id="c-miniaturas"></ul>

      <div class="coleta-pendencias" id="c-pendencias"></div>
      <label class="check coleta-enviar"><input type="checkbox" id="c-enviar" name="enviar"><i></i>Enviar para a conferência depois de subir</label>
    </div>
    <div class="pe-dialogo">
      <button type="button" class="botao claro" data-fechar>Cancelar</button>
      <button type="submit" class="botao" id="c-gravar"><?= icone('check') ?>Gravar no aparelho</button>
    </div>
  </form>
</dialog>
<script src="/assets/coleta.js?v=<?= (int) @filemtime(__DIR__ . '/assets/coleta.js') ?>" defer></script>
<?php paginaFim();

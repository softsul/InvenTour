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

function telaColeta(bool $noApp): string
{
    ob_start();
    ?>
<div class="coleta-estado" aria-live="polite">
  <span class="sinal" id="col-sinal"><i></i><span>Verificando</span></span>
  <span class="fila-local" id="col-fila">Nada esperando envio</span>
  <span class="quando" id="col-quando"></span>
  <span class="coleta-botoes">
    <button type="button" class="botao" id="col-sincronizar"><?= icone('trocar') ?><span>Sincronizar</span></button>
  </span>
</div>
<div class="sinc-progresso" id="col-progresso" hidden><div class="barra"><i></i></div><span></span></div>
<p class="aviso atencao" id="col-sessao" hidden><?= icone('alerta') ?><span id="col-sessao-texto"></span></p>
<p class="aviso ruim" id="col-erros" hidden></p>
<p class="aviso bom" id="col-resultado" hidden></p>

<div class="filtros coleta-filtros">
  <div class="entrada"><?= icone('busca') ?><input type="search" id="col-busca" placeholder="Nome, rua ou bairro" aria-label="Buscar na coleta" autocomplete="off"></div>
  <div class="entrada"><?= icone('manual') ?><select id="col-ficha-filtro" aria-label="Ficha"><option value="">Todas as fichas</option></select></div>
</div>
<?php /* Situação em fichas com a contagem: o devolvido pela conferência é o
         que a pessoa mais precisa achar, e fica à vista (e no topo da lista). */ ?>
<div class="coleta-situacoes" id="col-situacoes" role="group" aria-label="Situação"></div>
<p class="aviso atencao coleta-devolvidos" id="col-aviso-devolvidos" hidden><?= icone('alerta') ?><span></span></p>

<p class="coleta-conta nulo" id="col-conta"></p>
<ul class="coleta-lista" id="col-lista"></ul>
<div class="vazio" id="col-vazio" hidden><?= icone('camera') ?><p><strong id="col-vazio-titulo">Carregando.</strong></p><p id="col-vazio-texto"></p></div>
<button type="button" class="botao claro" id="col-mais" hidden>Mostrar mais</button>

<dialog class="coleta-dialogo pequeno" id="col-escolha" aria-labelledby="col-escolha-titulo">
  <div class="cab-dialogo"><h2 id="col-escolha-titulo">Qual ficha?</h2>
    <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button></div>
  <div class="corpo-dialogo">
    <div class="entrada"><?= icone('busca') ?><input type="search" id="col-fichas-busca" placeholder="Hotel, cachoeira, praça..." aria-label="Buscar a ficha" autocomplete="off"></div>
    <ul class="lista-simples coleta-fichas" id="col-fichas"></ul>
  </div>
</dialog>

<dialog class="coleta-dialogo" id="col-dialogo" aria-labelledby="col-titulo">
  <form method="dialog" id="col-form" novalidate>
    <div class="cab-dialogo">
      <div><h2 id="col-titulo">Item</h2><p class="nulo" id="col-subtitulo"></p></div>
      <button type="button" class="fechar" data-fechar aria-label="Fechar"><?= icone('fechar') ?></button>
    </div>
    <nav class="abas-chamado abas-coleta" role="tablist" aria-label="Partes da ficha">
      <button type="button" role="tab" data-aba-coleta="ident" aria-selected="true"><?= icone('etiqueta') ?>Identificação</button>
      <button type="button" role="tab" data-aba-coleta="local" aria-selected="false"><?= icone('mapa') ?>Localização<span class="conta-aba" id="aba-gps-sinal" hidden>GPS</span></button>
      <button type="button" role="tab" data-aba-coleta="contato" aria-selected="false"><?= icone('whatsapp') ?>Contato</button>
      <button type="button" role="tab" data-aba-coleta="fotos" aria-selected="false"><?= icone('camera') ?>Fotos<span class="conta-aba" id="aba-fotos-conta">0</span></button>
      <button type="button" role="tab" data-aba-coleta="conferencia" aria-selected="false"><?= icone('certificado') ?>Conferência<span class="conta-aba alerta-aba" id="aba-falta" hidden></span></button>
    </nav>
    <div class="corpo-dialogo">
      <p class="aviso atencao" id="col-devolucao" hidden><?= icone('alerta') ?><span></span></p>

      <section class="painel-coleta" data-painel-coleta="ident">
        <div class="grade">
          <div class="campo c-12"><label for="c-nome">Nome</label><div class="entrada"><input type="text" id="c-nome" name="nome" maxlength="180" required autocomplete="off"></div>
            <span class="ajuda">Nome fantasia ou denominação oficial, como está na fachada ou na placa.</span></div>
          <div class="campo c-6" id="c-tipo-campo"><label for="c-tipo">Tipo</label><div class="entrada"><select id="c-tipo" name="tipo_id"><option value="">Escolha</option></select></div></div>
          <div class="campo c-6"><label for="c-zona">Zona</label><div class="entrada"><select id="c-zona" name="zona"><option value="">Não informada</option>
            <?php foreach (Inventario::ZONAS as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r) ?></option><?php endforeach; ?></select></div></div>
        </div>
      </section>

      <section class="painel-coleta" data-painel-coleta="local" hidden>
        <div class="coleta-gps">
          <div class="gps-texto"><b>Coordenada</b><span id="c-gps-texto">Sem coordenada</span><small id="c-gps-quando"></small></div>
          <button type="button" class="botao" id="c-gps"><?= icone('mapa') ?>Usar o GPS daqui</button>
          <input type="hidden" name="latitude" id="c-lat"><input type="hidden" name="longitude" id="c-lng">
        </div>
        <div class="grade">
          <div class="campo c-8"><label for="c-logradouro">Rua, rodovia ou linha</label><div class="entrada"><input type="text" id="c-logradouro" name="logradouro" maxlength="200" autocomplete="off"></div></div>
          <div class="campo c-4"><label for="c-numero">Número</label><div class="entrada"><input type="text" id="c-numero" name="numero" maxlength="20" inputmode="numeric" autocomplete="off"></div></div>
          <div class="campo c-6"><label for="c-bairro">Bairro ou distrito</label><div class="entrada"><input type="text" id="c-bairro" name="bairro" maxlength="120" autocomplete="off"></div></div>
          <div class="campo c-6"><label for="c-plus">Plus code</label><div class="entrada"><input type="text" id="c-plus" name="plus_code" maxlength="16" placeholder="Q8FF+VM" autocapitalize="characters" autocomplete="off"></div></div>
          <div class="campo c-12"><label for="c-referencia">Ponto de referência</label><div class="entrada"><input type="text" id="c-referencia" name="referencia" maxlength="255" placeholder="Em frente à praça, depois da ponte..." autocomplete="off"></div></div>
        </div>
      </section>

      <section class="painel-coleta" data-painel-coleta="contato" hidden>
        <div class="grade">
          <div class="campo c-6"><label for="c-telefone">Telefone</label><div class="entrada"><input type="tel" id="c-telefone" name="telefone" inputmode="tel" placeholder="(46) 3220-0000" data-telefone autocomplete="off"></div></div>
          <div class="campo c-6"><label for="c-whatsapp">WhatsApp</label><div class="entrada"><input type="tel" id="c-whatsapp" name="whatsapp" inputmode="tel" placeholder="(46) 99999-0000" data-telefone autocomplete="off"></div></div>
          <div class="campo c-6"><label for="c-instagram">Instagram</label><div class="entrada"><input type="text" id="c-instagram" name="instagram" maxlength="100" placeholder="@perfil" autocapitalize="none" autocomplete="off"></div></div>
          <div class="campo c-6"><label for="c-site">Site</label><div class="entrada"><input type="url" id="c-site" name="site" maxlength="255" placeholder="https://" autocapitalize="none" autocomplete="off"></div></div>
        </div>
        <p class="ajuda">Contato que o turista vê. O dado pessoal de quem atendeu não vai aqui.</p>
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
          <div class="campo c-12"><label for="c-conservacao">Estado de conservação</label><div class="entrada"><select id="c-conservacao" name="conservacao"><option value="">Não avaliado</option>
            <?php foreach (Inventario::CONSERVACAO as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r) ?></option><?php endforeach; ?></select></div></div>
          <div class="campo c-12"><label for="c-nota">Observação de campo</label><div class="entrada"><textarea id="c-nota" name="nota" rows="4" maxlength="2000" placeholder="O que viu, com quem falou, o que conferir depois"></textarea></div></div>
        </div>
        <div class="coleta-pendencias" id="c-pendencias"></div>
        <label class="check coleta-enviar"><input type="checkbox" id="c-enviar" name="enviar"><i></i>Enviar para a conferência na próxima sincronização</label>
      </section>
    </div>
    <div class="pe-dialogo">
      <button type="button" class="botao claro" id="c-anterior" hidden><?= icone('seta', null, 'seta-voltar') ?>Anterior</button>
      <button type="button" class="botao claro" id="c-proxima">Próxima<?= icone('seta') ?></button>
      <span class="espaco"></span>
      <button type="button" class="botao claro" data-fechar>Cancelar</button>
      <button type="submit" class="botao" id="c-gravar"><?= icone('check') ?>Gravar no aparelho</button>
    </div>
  </form>
</dialog>
<?php
    return (string) ob_get_clean();
}

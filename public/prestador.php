<?php
/**
 * Ficha de um prestador do Cadastur, no modal da tela Cadastur: tudo o que
 * a base local guarda daquele registro, os outros registros do mesmo
 * documento.
 * Softsul Software
 *
 * Só consulta: os dados são do Ministério do Turismo e mudam na próxima
 * importação. O endereço leva o número da linha, nunca o CNPJ ou o CPF.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Cadastur.php';
require_once __DIR__ . '/../app/MapaTurismo.php';

Auth::exigirPermissao('cadastur.ver');
$pdo = Database::conexao();
$q = $pdo->prepare('SELECT c.*, i.recurso_nome FROM cadastur_prestadores c
                    LEFT JOIN cadastur_importacoes i ON i.id = c.importacao_id WHERE c.id = ?');
$q->execute([(int) ($_GET['id'] ?? 0)]);
$c = $q->fetch() ?: negarRegistro();

$q = $pdo->prepare('SELECT id, atividade, certificado, validade, situacao_cadastral FROM cadastur_prestadores
                    WHERE documento = ? AND id <> ? ORDER BY atividade');
$q->execute([$c['documento'], $c['id']]);
$outros = $q->fetchAll();

// CPF só pelo meio, como na lista.
$doc = (string) $c['documento'];
$documento = $c['tipo_documento'] === 'cpf' && strlen($doc) === 11
    ? '***.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-**'
    : formatarCnpj($doc);
$situacao = Cadastur::situacao($c);
$tom = match ($situacao) { 'Regular' => 'ok', 'Vencido' => 'ruim', '' => 'nu', default => 'at' };
$nome = trim((string) ($c['nome_fantasia'] ?: $c['nome_juridico'])) ?: 'Sem nome';
$numero = static fn ($v): string => $v !== null ? number_format((int) $v, 0, ',', '.') : '';
// A região turística do município no Mapa do Turismo mais recente.
$mapa = $c['municipio_id'] ? MapaTurismo::doMunicipio((int) $c['municipio_id']) : null;

$campos = [
    'Nome fantasia'          => (string) $c['nome_fantasia'],
    'Nome jurídico'          => (string) $c['nome_juridico'],
    $c['tipo_documento'] === 'cpf' ? 'CPF' : 'CNPJ' => $documento,
    'Natureza jurídica'      => (string) $c['natureza_juridica'],
    'Atividade'              => (string) $c['atividade'],
    'Categorias'             => (string) $c['categorias'],
    'Município'              => $c['municipio'] ? $c['municipio'] . '/' . $c['uf'] : '',
    'Código IBGE'            => $c['municipio_id'] ? (string) $c['municipio_id'] : '',
    'Região turística'       => $mapa ? $mapa['regiao'] . ' (' . mb_strtolower(MapaTurismo::CATEGORIAS[(int) $mapa['categoria']] ?? '') . ')'
                                : ($c['municipio_id'] && MapaTurismo::ano() ? 'Fora do Mapa do Turismo ' . MapaTurismo::ano() : ''),
    'Certificado'            => (string) $c['certificado'],
    'Validade'               => $c['validade'] ? dataBr((string) $c['validade'], 'd/m/Y') : '',
    'Situação na planilha'   => (string) $c['situacao_cadastral'],
    'Situação da atividade'  => (string) $c['situacao_atividade'],
    'Endereço'               => (string) $c['endereco'],
    'Telefone'               => (string) $c['telefone'],
    'E-mail'                 => (string) $c['email'],
    'Site'                   => (string) $c['site'],
    'Porte'                  => (string) $c['porte'],
    'Idiomas'                => (string) $c['idiomas'],
    'Leitos'                 => $numero($c['leitos']) . ($c['leitos_acessiveis'] ? ', ' . $numero($c['leitos_acessiveis']) . ' acessíveis' : ''),
    'Unidades habitacionais' => $numero($c['unidades']) . ($c['unidades_acessiveis'] ? ', ' . $numero($c['unidades_acessiveis']) . ' acessíveis' : ''),
    'Base'                   => (string) $c['recurso_nome'],
];

paginaFicha($nome, 'cadastur');
?>
<div class="trilha"><a href="/cadastur">Cadastur</a><?= icone('seta') ?><span><?= e($nome) ?></span></div>
<div class="cab-pag">
  <div>
    <h1><?= e($nome) ?></h1>
    <p><?= e((string) $c['atividade']) ?> · <span class="pilula <?= $tom ?>"><?= e($situacao !== '' ? $situacao : 'Sem situação') ?></span></p>
  </div>
</div>

<?php if ($situacao === 'Vencido'): ?>
  <p class="aviso ruim"><?= icone('alerta') ?><span>O certificado venceu em <?= e(dataBr((string) $c['validade'], 'd/m/Y')) ?>. A planilha do Ministério ainda diz "<?= e((string) $c['situacao_cadastral']) ?>" porque é a foto do trimestre.</span></p>
<?php endif; ?>

<div class="cartao">
  <h2 class="titulo-secao">Dados no Cadastur</h2>
  <dl class="dados-ficha">
    <?php foreach ($campos as $rotulo => $valor): ?>
      <dt><?= e($rotulo) ?></dt>
      <dd<?= in_array($rotulo, ['CNPJ', 'CPF', 'Certificado', 'Código IBGE'], true) ? ' class="dado"' : '' ?>><?= trim($valor) !== '' ? e($valor) : '<span class="nulo">-</span>' ?></dd>
    <?php endforeach; ?>
  </dl>
</div>

<?php if ($outros): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Outras atividades do mesmo <?= $c['tipo_documento'] === 'cpf' ? 'CPF' : 'CNPJ' ?></h2>
    <div class="rolagem"><table class="dados">
      <thead><tr><th>Atividade</th><th>Certificado</th><th>Validade</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($outros as $o): $s = Cadastur::situacao($o); ?>
        <tr>
          <td class="elastica"><a href="<?= e(noPopup('/prestador/' . (int) $o['id'])) ?>"><?= e((string) $o['atividade']) ?></a></td>
          <td class="dado"><?= e((string) $o['certificado']) ?></td>
          <td class="dado"><?= $o['validade'] ? e(dataBr((string) $o['validade'], 'd/m/Y')) : '<span class="nulo">-</span>' ?></td>
          <td><span class="pilula <?= match ($s) { 'Regular' => 'ok', 'Vencido' => 'ruim', '' => 'nu', default => 'at' } ?>"><?= e($s !== '' ? $s : 'Sem situação') ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>

<?php paginaFichaFim();

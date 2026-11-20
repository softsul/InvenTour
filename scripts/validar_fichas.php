<?php
/**
 * Confere a especificação de cada ficha (docs/especificacao/fichas-2011/NN.json)
 * contra o texto do PDF oficial e o catálogo. Softsul Software
 *
 *   php scripts/validar_fichas.php <pasta das especificações> <pasta dos textos dos PDFs> <NN, todas ou mapa>
 *
 * Os textos saem de pdftotext -layout de cada PDF de docs/material-original.
 * Todo número do PDF precisa de um item; todo campo ativo da ficha, de uma
 * citação; tipo, coluna do núcleo e chave são conferidos.
 */
require __DIR__ . '/../app/Database.php';
$SPEC = rtrim($argv[1] ?? '', '/');
$TXT = rtrim($argv[2] ?? '', '/');
$argv = [$argv[0], $argv[3] ?? ''];
$pdo = Database::conexao();
$NUCLEO = ['nome','nome_popular','razao_social','cnpj','cnae','atividade_economica','rede','inscricao_municipal','natureza','natureza_outra',
  'tipo_organizacao','tipo_organizacao_outro','jurisdicao','inicio_atividade','funcionarios','funcionarios_temporarios','funcionarios_pcd','zona',
  'latitude','longitude','logradouro','numero','complemento','bairro','distrito','cep','telefone','ramal','whatsapp','email','site','instagram',
  'sinalizacao_acesso','sinalizacao_turistica','referencia','horario','formas_pagamento','idiomas','capacidade_publico','conservacao',
  'acessibilidade_possui','acessibilidade','descricao_tecnica','descricao_promocional','observacoes','referencias','equipe','tipo_id',
  'cadastur','cadastur_validade','extensao_km','plus_code','maps_url'];
$TIPOS = ['texto','texto_longo','numero','sim_nao','escolha','multipla','tabela','data'];
$COLT = ['texto','numero','sim_nao','escolha','meses'];
$pdfs = [1=>'A1 - Informaçõees básicas do município',2=>'A2.1.1 - Rodoviário_Rodovia',3=>'A2.1.2 - Rodoviário Estação rodoviária',
 4=>'A2.2.1 - Ferroviário Ferrovia e Metrovia',5=>'A2.2.2 - Ferroviário Estação ferroviária',6=>'A2.3.1 a .2 - Aeroviário Aeroporto e Campo de pouso',
 7=>'A2.3.3 - Aeroviário Heliporto',8=>'A2.4.1 - Aquaviário Hidrovia',9=>'A2.4.2 - Aquaviário Porto Píer Cais etc',10=>'A3 - Sistema de comunicação',
 11=>'A4 - Sistema de segurança',12=>'A5 - Sistema de saúde',13=>'A6 - Sistema Educacional',14=>'A7.1 - Locadoras de imóveis para temporadas',
 15=>'A7.2 - Compras especiais',16=>'A7.3 - Comércio Turístico',17=>'A7.4 - Serviços bancários',18=>'A7.5.6 - Serviços mecânicos e Posto de Combustível',
 19=>'A7.7 - Representações diplomáticas',20=>'B.1.1 - Meios de hospedagem',21=>'B.1.2 - Outro tipo de acomodações',
 22=>'B.2 - Serviços e equipamentos para alimentos e bebidas',23=>'B.3 - Serviços e equipamentos de agências de turismo',
 24=>'B.4 - Serviços e equipamentos de transporte turístico',25=>'B.5.1-Espaços para eventos',26=>'B.5.2 - Serviços para eventos',27=>'B.6.1 - Parques',
 28=>'B.6.2 - Espaços livres e áreas verdes',29=>'B.6.3 - Instalações esportivas',30=>'B.6.4 - Instalações náuticas',31=>'B.6.5 - Espaços de diversão e cultura',
 32=>'B.6.6 - Outros espaços de recreação',33=>'B.7.1 - Informações turísticas',34=>'B.7.2 - Entidade associativas e similares',
 35=>'B.7.3 - Guiamento e condução turística',36=>'C.1.1 - Relevo continental',37=>'C.1.2 - Zona Costeira',38=>'C.1.3 - Relevo cárstico',
 39=>'C.1.4 a 5 - Hidrografia',40=>'C.1.6 - Unidade de Conservação',41=>'C.2.1 - Conjuntos Arquitetônicos',42=>'C.2.2 a .6 - Sítios e etc',
 43=>'C.2.7. a. 9 - Lugares de manifestações de fé',44=>'C.2.10 - Arquitetura civil',45=>'C.2.11 a .13 - Arquitetura oficial, militar e religiosa',
 46=>'C.2.14 a .17 - Arquitetura industrial, agrícola, funerária',47=>'C.2.18 - Obras de interesse artístico',
 48=>'C.2.19 a .24 - Ruinas, centro cultural, teatro, cineclub',49=>'C.2.25 a .27 - Gastronomia, artesanato, trabalhos manuais',
 50=>'C.2.28 - Fomas de expressão',51=>'C.2.29 - Personalidades',52=>'C.3 - Atividades econômicas',53=>'C.4 - Atrações técnicas e científicas',54=>'C.5 - Eventos programados'];
if (($argv[1] ?? '') === 'mapa') { foreach ($pdfs as $o => $p) echo sprintf('%02d', $o), " => $p.pdf\n"; exit; }
$lista = ($argv[1] ?? '') === 'todas' ? array_keys($pdfs) : [(int) ($argv[1] ?? 0)];
$totalErros = 0;
foreach ($lista as $ordem) {
  $erros = []; $avisos = [];
  $arq = sprintf('%s/%02d.json', $SPEC, $ordem);
  if (!is_file($arq)) { echo sprintf("%02d: SEM ARQUIVO\n", $ordem); $totalErros++; continue; }
  $s = json_decode(file_get_contents($arq), true);
  if (!is_array($s)) { echo sprintf("%02d: JSON inválido (%s)\n", $ordem, json_last_error_msg()); $totalErros++; continue; }
  $ficha = $pdo->query("SELECT id FROM inventario_fichas WHERE ordem = $ordem")->fetchColumn();
  $ats = $pdo->query("SELECT id, chave, tipo, ativo FROM inventario_atributos WHERE ficha_id = $ficha")->fetchAll(PDO::FETCH_UNIQUE);
  // Números do PDF
  $txt = file_get_contents("$TXT/{$pdfs[$ordem]}.txt");
  preg_match_all('/(?<![\d.,\/])(\d{1,2}(?:\.\d{1,2}){0,7})\.(?=\s|$)/mu', $txt, $m);
  $noPdf = array_unique($m[1]);
  $naSpec = [];
  $citados = []; $chaves = [];
  foreach ($s['itens'] ?? [] as $n => $it) {
    $sec = rtrim((string) ($it['secao'] ?? ''), '.');
    $onde = "item $n ($sec)";
    if ($sec === '') { $erros[] = "$onde sem secao"; }
    $naSpec[$sec] = ($naSpec[$sec] ?? 0) + 1;
    $d = $it['destino'] ?? '';
    if ($d === 'nucleo') { if (!in_array($it['coluna'] ?? '', $NUCLEO, true)) { $erros[] = "$onde coluna do núcleo desconhecida: " . ($it['coluna'] ?? ''); } }
    elseif ($d === 'atributo') {
      $id = (int) ($it['atributo_id'] ?? 0);
      if (!isset($ats[$id])) { $erros[] = "$onde atributo_id $id não é desta ficha"; } else { $citados[$id] = true; }
      $aj = $it['ajustes'] ?? [];
      if (isset($aj['tipo']) && !in_array($aj['tipo'], $TIPOS, true)) { $erros[] = "$onde tipo inválido {$aj['tipo']}"; }
      if (isset($aj['chave'])) { $chaves[] = $aj['chave']; } elseif (isset($ats[$id])) { $chaves[] = $ats[$id]['chave']; }
    } elseif ($d === 'novo') {
      $nv = $it['novo'] ?? [];
      foreach (['chave', 'grupo', 'rotulo', 'tipo'] as $c) { if (empty($nv[$c])) { $erros[] = "$onde novo sem $c"; } }
      if (isset($nv['tipo']) && !in_array($nv['tipo'], $TIPOS, true)) { $erros[] = "$onde tipo inválido {$nv['tipo']}"; }
      if (in_array($nv['tipo'] ?? '', ['escolha', 'multipla'], true) && empty($nv['opcoes'])) { $erros[] = "$onde {$nv['tipo']} sem opções"; }
      if (($nv['tipo'] ?? '') === 'tabela' && empty($nv['colunas']) && empty($nv['opcoes'])) { $erros[] = "$onde tabela sem colunas"; }
      if (array_key_exists('apos', $nv) && $nv['apos'] !== null && !isset($ats[(int) $nv['apos']])) { $avisos[] = "$onde apos {$nv['apos']} não é atributo da ficha"; }
      $chaves[] = $nv['chave'] ?? '';
    } elseif (!in_array($d, ['ignorar', 'titulo'], true)) { $erros[] = "$onde destino inválido '$d'"; }
    foreach ([$it['ajustes'] ?? [], $it['novo'] ?? []] as $def) {
      foreach ((array) ($def['colunas'] ?? []) as $c) {
        if (is_array($c) && !in_array($c['tipo'] ?? 'texto', $COLT, true)) { $erros[] = "$onde coluna com tipo inválido " . ($c['tipo'] ?? ''); }
      }
    }
  }
  foreach ($s['desativar'] ?? [] as $dsv) { $id = (int) ($dsv['atributo_id'] ?? 0); if (!isset($ats[$id])) { $erros[] = "desativar $id não é desta ficha"; } $citados[$id] = true; }
  foreach ($ats as $id => $a) { if ($a['ativo'] && empty($citados[$id])) { $erros[] = "atributo ativo $id ({$a['chave']}) não citado"; } }
  foreach (array_count_values(array_filter($chaves)) as $c => $q) { if ($q > 1) { $erros[] = "chave repetida: $c"; } }
  $faltam = array_values(array_diff($noPdf, array_keys($naSpec)));
  natsort($faltam);
  if ($faltam) { $erros[] = 'números do PDF sem item: ' . implode(', ', $faltam); }
  $sobram = array_values(array_diff(array_keys($naSpec), $noPdf));
  if ($sobram) { $avisos[] = 'números na spec que o texto do PDF não mostra (confira): ' . implode(', ', $sobram); }
  echo sprintf("%02d: %d itens, %d erros, %d avisos\n", $ordem, count($s['itens'] ?? []), count($erros), count($avisos));
  foreach ($erros as $e) { echo "   ERRO $e\n"; }
  foreach ($avisos as $e) { echo "   aviso $e\n"; }
  $totalErros += count($erros);
}
exit($totalErros ? 1 : 0);

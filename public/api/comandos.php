<?php
/**
 * O que a paleta de comandos pode fazer, para esta pessoa.
 * Softsul Software
 *
 * A lista sai do servidor porque quem decide o que existe é a permissão:
 * montar no navegador seria oferecer o que daria 403 ao clicar. Só entram
 * as telas que já existem; rotina nova com cadastro entra aqui com o
 * "#novo", que abre o cadastro dela (o botão com data-abre-novo da tela).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Carregada ao abrir a paleta, não a cada tecla.
Limite::porUsuario('comandos', 30, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$comandos = [];
$por = function (?string $permissao, string $grupo, string $nome, string $url, string $icone, string $ajuda = '') use (&$comandos): void {
    if ($permissao !== null && !Auth::pode($permissao)) {
        return;
    }
    $comandos[] = ['grupo' => $grupo, 'nome' => $nome, 'url' => $url, 'icone' => $icone, 'ajuda' => $ajuda, 'acao' => ''];
};

// Os módulos vêm do menu: módulo novo já nasce na paleta.
foreach (Auth::modulosVisiveis() as $m) {
    if (!empty($m['desligado'])) {
        continue;
    }
    $por(null, 'Ir para', (string) $m['nome'], '/' . $m['chave'], (string) $m['icone'], (string) $m['grupo']);
}

if (Auth::ehSoftsul()) {
    $por('destinos.criar', 'Criar', 'Novo destino', '/destinos#novo', 'predio');
}
if (Empresa::id() || Auth::ehSoftsul()) {
    $por('usuarios.criar', 'Criar', 'Novo usuário', '/configuracoes/usuarios#novo', 'pessoas');
}
if (Empresa::id()) {
    $por('inventario.criar', 'Criar', 'Novo item do inventário', '/inventario#novo', 'mapa');
    $por('inventario.criar', 'Ir para', 'Pré-cadastro pelo Cadastur', '/pre-cadastro', 'certificado', 'Inventário');
}
if (Empresa::id()) {
    $atual = Empresa::atual();
    $por('configuracoes.ver', 'Ir para', 'Dados do destino', endereco('destino', Empresa::id(), (string) ($atual['nome_fantasia'] ?? '')), 'predio', 'Configurações');
    $por('auditoria.ver', 'Ir para', 'Auditoria do destino', '/configuracoes/auditoria', 'lista', 'Configurações');
}

// Toda tela das Configurações, sem precisar passar pela tela de cartões:
// a lista é a mesma de app/ConfiguracoesCatalogo.php, então tela nova lá
// já nasce aqui. A plataforma (empresa 0) tem os cartões dela.
if (Auth::pode('configuracoes.ver')) {
    require_once __DIR__ . '/../../app/ConfiguracoesCatalogo.php';
    $ja = array_column($comandos, 'url');
    foreach (ConfiguracoesCatalogo::cartoes((int) Empresa::id()) as $c) {
        if (!empty($c['pronto']) && !in_array($c['endereco'], $ja, true)) {
            $por(null, 'Configurações', (string) $c['nome'], (string) $c['endereco'], (string) $c['icone'], (string) $c['grupo']);
            // A descrição também acha a tela ("push", "Firebase", "ISS").
            $comandos[count($comandos) - 1]['chaves'] = (string) ($c['descricao'] ?? '');
            $ja[] = $c['endereco'];
        }
    }
}

$por(null, 'Minha conta', 'Meus dados', '/perfil', 'contatos');
$por(null, 'Minha conta', 'Trocar a senha', '/perfil#senha', 'cadeado');
$por(null, 'Minha conta', 'Segundo fator', '/perfil#autenticador', 'escudo');
$por(null, 'Minha conta', 'Entrar por digital', '/perfil#aparelhos', 'digital');
$por(null, 'Minha conta', 'Preferências de tela', '/perfil#preferencias', 'engrenagem');
$por(null, 'Minha conta', 'Trocar de destino', '/trocar-empresa', 'trocar');
$por(null, 'Minha conta', 'Sair do sistema', '/sair', 'sair');

echo json_encode(['ok' => true, 'comandos' => $comandos, 'csrf' => Auth::tokenCsrf()],
                 JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

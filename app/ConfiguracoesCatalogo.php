<?php
/**
 * Os cartões de Configurações do destino, numa lista só.
 * Softsul Software
 *
 * No molde do Orbit e do Centurion: cartão novo aqui já aparece na tela e
 * na busca do topo (Ctrl K), sem uma segunda lista para esquecer de
 * atualizar. O total de cada cartão é uma função, chamada só quando a tela
 * mostra o cartão. Tudo aqui é do destino atual: cada empresa tem as suas
 * configurações.
 *
 * Na fundação do InvenTour entram os dados do destino, usuários, auditoria,
 * módulos e e-mail. Os cartões dos módulos do inventário entram aqui quando
 * a tela deles existir.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Ajustes.php';

final class ConfiguracoesCatalogo
{
    /** A ordem dos grupos na tela. */
    public const GRUPOS = ['O destino', 'Comercial', 'Integrações', 'Acesso e segurança'];

    /**
     * Os cartões que a pessoa pode abrir. Se tem direito, o cartão aparece e
     * a tela abre direto; se não tem, o cartão nem aparece, aqui e na busca
     * do topo (Luiz, 20/11/2026).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function cartoes(int $empresaId): array
    {
        require_once __DIR__ . '/Auth.php';
        return array_values(array_filter(self::todos($empresaId), fn ($t) => self::podeAbrir((string) $t['endereco'])));
    }

    /** A mesma regra que a tela do cartão confere ao abrir. */
    private static function podeAbrir(string $endereco): bool
    {
        $caminho = (string) parse_url($endereco, PHP_URL_PATH);
        if (preg_match('#^/configuracoes/(email|modulos|comercial|google|bing|analytics)$#', $caminho)) {
            return Auth::ehSoftsul() && Auth::pode('configuracoes.ver');
        }
        return Auth::pode(match (true) {
            $caminho === '/usuarios'  => 'usuarios.ver',
            $caminho === '/auditoria' => 'auditoria.ver',
            default                   => 'configuracoes.ver',
        });
    }

    /** @return array<int,array<string,mixed>> */
    private static function todos(int $empresaId): array
    {
        $pdo = Database::conexao();
        $conta = function (string $sql) use ($pdo, $empresaId): int {
            $q = $pdo->prepare($sql);
            $q->execute([$empresaId]);
            return (int) $q->fetchColumn();
        };
        if ($empresaId === 0) {
            return self::daPlataforma($pdo);
        }
        return [
            [
                // A ficha do destino é a mesma tela que a plataforma usa em
                // /destinos; aqui ela abre no destino atual.
                'endereco'  => '/destino/' . $empresaId,
                'grupo'     => 'O destino',
                'icone'     => 'predio',
                'nome'      => 'Dados do destino',
                'descricao' => 'Nome institucional, nome no portal, CNPJ, município sede, endereço, contatos, '
                             . 'subdomínio e domínio próprio do portal.',
                'total'     => fn () => $conta('SELECT COUNT(*) FROM usuario_empresa WHERE empresa_id = ? AND ativo = 1'),
                'unidade'   => 'pessoas com acesso',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/configuracoes/eventcity',
                'grupo'     => 'Integrações',
                'icone'     => 'calendario',
                'nome'      => 'EventCity',
                'descricao' => 'A agenda de eventos do destino no portal e o aviso dos lugares homologados para o EventCity. Segredo combinado, cifrado.',
                'total'     => fn () => (int) (Ajustes::ler('eventcity', 'ligado', '0', $empresaId) === '1'),
                'unidade'   => 'ponte ligada',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/usuarios',
                'grupo'     => 'Acesso e segurança',
                'icone'     => 'pessoas',
                'nome'      => 'Usuários e papéis',
                'descricao' => 'Quem trabalha no destino (gestor, consultor, coordenador, pesquisador, leitor, prestador) e o que cada '
                             . 'papel pode abrir. Convite por link, segundo fator e passkey.',
                'total'     => fn () => $conta('SELECT COUNT(DISTINCT ue.usuario_id) FROM usuario_empresa ue
                                                JOIN usuario_empresa_papel up ON up.usuario_id = ue.usuario_id AND up.empresa_id = ue.empresa_id
                                                JOIN papeis p ON p.id = up.papel_id AND p.escopo = \'destino\'
                                                WHERE ue.empresa_id = ? AND ue.ativo = 1'),
                'unidade'   => 'na equipe',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/auditoria',
                'grupo'     => 'Acesso e segurança',
                'icone'     => 'lista',
                'nome'      => 'Auditoria',
                'descricao' => 'Quem fez o quê e quando, com o antes e o depois de cada alteração.',
                'total'     => fn () => $conta('SELECT COUNT(*) FROM auditoria WHERE empresa_id = ? AND criado_em > NOW() - INTERVAL 30 DAY'),
                'unidade'   => 'em 30 dias',
                'pronto'    => true,
            ],
        ];
    }

    /**
     * Visão da plataforma: o que a equipe Softsul administra fora de um
     * destino. Usuários e Auditoria saíram do menu e ficam aqui.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function daPlataforma(PDO $pdo): array
    {
        $conta = fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
        require_once __DIR__ . '/Waha.php';
        require_once __DIR__ . '/SesAvisos.php';
        require_once __DIR__ . '/Comercial.php';
        require_once __DIR__ . '/SearchConsole.php';
        require_once __DIR__ . '/Bing.php';
        require_once __DIR__ . '/Analytics.php';
        $comercial = Comercial::dados();
        return [
            [
                'endereco'  => '/configuracoes/comercial',
                'grupo'     => 'Comercial',
                'icone'     => 'certificado',
                'nome'      => 'Preços e avaliação',
                'descricao' => 'Tabela de preços por porte de município (implantação e assinatura mensal e anual), o período de '
                             . 'avaliação gratuita e o convite que o QR do caderno mostra no modo projeto.',
                'total'     => fn () => $comercial['dias_avaliacao'],
                'unidade'   => 'dias de avaliação',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/configuracoes/modulos',
                'grupo'     => 'Acesso e segurança',
                'icone'     => 'engrenagem',
                'nome'      => 'Módulos',
                'descricao' => 'Liga e desliga, para todos os destinos, os módulos que ainda dependem de decisão, como Documentos.',
                'total'     => fn () => $conta('SELECT COUNT(*) FROM modulos WHERE pode_desligar = 1 AND desligado = 1'),
                'unidade'   => 'desligados',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/configuracoes/google',
                'grupo'     => 'Integrações',
                'icone'     => 'busca',
                'nome'      => 'Google Search Console',
                'descricao' => 'A conta de serviço que lê cliques, impressões e termos de busca de cada portal e envia o sitemap dos portais novos.',
                'total'     => fn () => SearchConsole::configurado() ? 1 : 0,
                'unidade'   => SearchConsole::configurado() ? 'conectado' : 'falta a chave',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/configuracoes/bing',
                'grupo'     => 'Integrações',
                'icone'     => 'busca',
                'nome'      => 'Bing Webmaster',
                'descricao' => 'A chave de API que lê cliques, impressões e termos de cada portal no Bing, cadastra os portais novos e avisa o IndexNow do que mudou.',
                'total'     => fn () => Bing::configurado() ? 1 : 0,
                'unidade'   => Bing::configurado() ? 'conectado' : 'falta a chave',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/configuracoes/analytics',
                'grupo'     => 'Integrações',
                'icone'     => 'painel',
                'nome'      => 'Google Analytics',
                'descricao' => 'De onde vêm as visitas dos portais, de que cidade e em que aparelho. O script só carrega com o aceite de cookies do visitante.',
                'total'     => fn () => Analytics::configurado() ? 1 : 0,
                'unidade'   => Analytics::configurado() ? 'conectado' : (Analytics::medicao() !== '' ? 'falta a leitura' : 'desligado'),
                'pronto'    => true,
            ],
            [
                'endereco'  => '/configuracoes/email',
                'grupo'     => 'Integrações',
                'icone'     => 'emails',
                'nome'      => 'E-mail',
                'descricao' => 'O envio pelo Amazon SES, a mensagem de teste e as devoluções e reclamações, com o desbloqueio de cada endereço.',
                'total'     => fn () => SesAvisos::resumo()[2],
                'unidade'   => 'endereços bloqueados',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/usuarios',
                'grupo'     => 'Acesso e segurança',
                'icone'     => 'pessoas',
                'nome'      => 'Usuários e papéis',
                'descricao' => 'A equipe Softsul, que alcança todos os destinos, e a consulta de todas as contas.',
                'total'     => fn () => $conta('SELECT COUNT(*) FROM usuarios WHERE softsul = 1 AND ativo = 1'),
                'unidade'   => 'na equipe Softsul',
                'pronto'    => true,
            ],
            [
                'endereco'  => '/auditoria',
                'grupo'     => 'Acesso e segurança',
                'icone'     => 'lista',
                'nome'      => 'Auditoria',
                'descricao' => 'Quem fez o quê, quando e de onde, em todos os destinos.',
                'total'     => fn () => $conta('SELECT COUNT(*) FROM auditoria WHERE criado_em > NOW() - INTERVAL 30 DAY'),
                'unidade'   => 'em 30 dias',
                'pronto'    => true,
            ],
        ];
    }
}

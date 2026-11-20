<?php
/**
 * Regras de quem pode cadastrar e mexer em quem.
 * Softsul Software
 *
 * Ficam no servidor, num lugar só, e não na tela: esconder um botão não
 * impede ninguém de mandar o formulário.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Empresa.php';

final class Usuarios
{
    /**
     * Papéis que quem está logado pode dar, no contexto atual. Na visão da
     * plataforma, só papéis Softsul; dentro de um destino, os do destino.
     * Ninguém dá papel acima do próprio: só o gestor (ou a Softsul) cria
     * outro gestor.
     */
    public static function papeisAtribuiveis(): array
    {
        $u = Auth::usuarioOuNulo();
        $pdo = Database::conexao();
        if (!Empresa::id()) {
            if (!$u['softsul'] || $u['papel'] !== 'plataforma_admin') {
                return [];
            }
            return $pdo->query("SELECT * FROM papeis WHERE escopo = 'softsul' ORDER BY ordem")->fetchAll();
        }
        $lista = $pdo->query("SELECT * FROM papeis WHERE escopo = 'destino' ORDER BY ordem")->fetchAll();
        if ($u['softsul']) {
            return $lista;
        }
        // O consultor é dado pela Softsul, que licencia o projeto; o gestor e
        // o consultor dão os demais, inclusive gestor (no projeto de
        // consultoria o consultor monta a equipe da prefeitura).
        if (Auth::temPapel('gestor') || Auth::temPapel('consultor')) {
            return array_values(array_filter($lista, fn ($p) => $p['chave'] !== 'consultor'));
        }
        return array_values(array_filter($lista, fn ($p) => !in_array($p['chave'], ['gestor', 'consultor'], true)));
    }

    public static function papelAtribuivel(int $papelId): ?array
    {
        foreach (self::papeisAtribuiveis() as $p) {
            if ((int) $p['id'] === $papelId) {
                return $p;
            }
        }
        return null;
    }

    /**
     * A pessoa como aparece no contexto atual: na plataforma, a conta; no
     * destino, a conta mais o vínculo com aquele destino. Null se ela não
     * está ao alcance de quem pergunta.
     */
    public static function noContexto(int $id): ?array
    {
        $pdo = Database::conexao();
        $empresa = Empresa::id();
        if (!$empresa) {
            if (!Auth::ehSoftsul()) {
                return null;
            }
            $q = $pdo->prepare(
                'SELECT u.*, p.nome AS papel_nome, p.chave AS papel_chave, u.papel_softsul_id AS papel_id, u.ativo AS vinculo_ativo
                 FROM usuarios u LEFT JOIN papeis p ON p.id = u.papel_softsul_id WHERE u.id = ?'
            );
            $q->execute([$id]);
            $r = $q->fetch() ?: null;
            if ($r) {
                $r['papeis'] = $r['papel_id'] ? [(int) $r['papel_id']] : [];
            }
            return $r;
        }
        $q = $pdo->prepare(
            'SELECT u.*, ue.papel_id, ue.ativo AS vinculo_ativo,
                    GROUP_CONCAT(p.id ORDER BY p.ordem) AS papeis_ids,
                    GROUP_CONCAT(p.nome ORDER BY p.ordem SEPARATOR \', \') AS papel_nome,
                    MIN(p.chave) AS papel_chave,
                    \'destino\' AS escopo
             FROM usuarios u
             JOIN usuario_empresa ue ON ue.usuario_id = u.id AND ue.empresa_id = ?
             JOIN usuario_empresa_papel uep ON uep.usuario_id = ue.usuario_id AND uep.empresa_id = ue.empresa_id
             JOIN papeis p ON p.id = uep.papel_id
             WHERE u.id = ?
             GROUP BY u.id, ue.papel_id, ue.ativo'
        );
        $q->execute([$empresa, $id]);
        $r = $q->fetch() ?: null;
        if ($r) {
            $r['papeis'] = array_map('intval', explode(',', (string) $r['papeis_ids']));
        }
        return $r;
    }

    /**
     * Se dá para mexer nesta pessoa: ninguém mexe em quem tem papel acima do
     * próprio, e ninguém de destino mexe em conta da Softsul.
     */
    public static function podeMexer(array $alvo): bool
    {
        $eu = Auth::usuarioOuNulo();
        if ((int) $alvo['id'] === (int) $eu['id']) {
            return false;
        }
        if ($alvo['softsul'] && !($eu['softsul'] && $eu['papel'] === 'plataforma_admin')) {
            return false;
        }
        // Na visão da plataforma só se mexe em conta Softsul. A conta de quem
        // é de destino se ajusta entrando no destino, onde o papel é o do
        // vínculo.
        if (!Empresa::id()) {
            return (bool) $alvo['softsul'];
        }
        // No destino: só mexe quem pode dar TODOS os papéis que a pessoa tem.
        if (!$alvo['softsul'] && Empresa::id()) {
            foreach ($alvo['papeis'] ?? [(int) $alvo['papel_id']] as $papelId) {
                if (self::papelAtribuivel((int) $papelId) === null) {
                    return false;
                }
            }
            return true;
        }
        return Auth::ehSoftsul();
    }

    /**
     * Nome, e-mail e telefone são da pessoa, não do destino. Quem é de um
     * destino só mexe nesses dados se a pessoa não tiver vínculo com nenhum
     * outro destino.
     */
    public static function podeMexerNaConta(array $alvo): bool
    {
        if (Auth::ehSoftsul()) {
            return !$alvo['softsul'] || Auth::usuarioOuNulo()['papel'] === 'plataforma_admin';
        }
        $q = Database::conexao()->prepare('SELECT COUNT(*) FROM usuario_empresa WHERE usuario_id = ? AND empresa_id <> ?');
        $q->execute([(int) $alvo['id'], Empresa::id()]);
        return !$alvo['softsul'] && (int) $q->fetchColumn() === 0;
    }

    /**
     * Exclusão da própria conta, pedida pela pessoa em Meus dados (exigência
     * da App Store, regra 5.1.1(v), e direito do titular na LGPD).
     *
     * Apaga os dados pessoais e todos os acessos: a linha de usuarios fica só
     * como "Conta excluída", sem e-mail, telefone, foto nem senha, porque os
     * atos já praticados (pareceres de curadoria, aceite de termo, histórico
     * de eventos, auditoria) são registros do destino e precisam continuar
     * apontando para alguém. O cadastro do organizador continua com o
     * destino; o perfil de artista sai do ar e perde os dados pessoais.
     * Conta da Softsul não se exclui por aqui: é de quem opera a plataforma.
     * Devolve null quando deu certo; senão, o motivo.
     */
    public static function excluirPropriaConta(int $uid): ?string
    {
        $pdo = Database::conexao();
        $q = $pdo->prepare('SELECT id, softsul, foto FROM usuarios WHERE id = ? AND ativo = 1');
        $q->execute([$uid]);
        $eu = $q->fetch();
        if (!$eu) {
            return 'Conta não encontrada.';
        }
        if ($eu['softsul']) {
            return 'Conta da equipe Softsul não pode ser excluída por aqui.';
        }
        require_once __DIR__ . '/Foto.php';
        require_once __DIR__ . '/Auditoria.php';
        // Registra antes de apagar o nome, para a auditoria dizer quem foi.
        Auditoria::registrar('excluiu', 'usuarios', $uid, 'excluiu a própria conta');
        $pdo->beginTransaction();
        try {
            foreach (['usuario_empresa_papel', 'usuario_empresa', 'usuario_permissao',
                      'credenciais_webauthn', 'usuario_certificados', 'convites'] as $t) {
                $pdo->prepare("DELETE FROM $t WHERE usuario_id = ?")->execute([$uid]);
            }
            $pdo->prepare("UPDATE usuarios SET nome = 'Conta excluída', apelido = NULL, departamento = NULL, funcao = NULL,
                                  email = CONCAT('excluida-', id, '@conta-excluida.invalid'), telefone = NULL,
                                  whatsapp_chatid = NULL, whatsapp_existe = NULL, whatsapp_conferido_em = NULL,
                                  senha_hash = NULL, ativo = 0, precisa_trocar_senha = 0,
                                  duplo_fator_segredo = NULL, duplo_fator_confirmado = 0, duplo_fator_ultimo_periodo = NULL,
                                  versao_sessao = versao_sessao + 1, empresa_padrao_id = NULL, ultimo_ip = NULL,
                                  atualizado_em = NOW()
                           WHERE id = ?")->execute([$uid]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Exclusão da conta ' . $uid . ': ' . $e->getMessage());
            return 'Não foi possível excluir a conta agora. Tente de novo em instantes.';
        }
        if ($eu['foto']) {
            Foto::remover('usuario', $uid);
        }
        return null;
    }
}

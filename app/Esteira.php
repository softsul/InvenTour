<?php
/**
 * A esteira do inventário: pré-cadastro, campo, conferência e homologação.
 * Softsul Software
 *
 * O pesquisador coleta (no painel ou na tela de campo) e envia para a
 * conferência; quem coordena (coordenador, gestor, consultor) confere o
 * núcleo mínimo da metodologia do MTur e homologa, ou devolve com uma nota
 * do que acertar em campo. Só homologada e desatualizada vão ao portal e ao
 * EventCity. Cada passo fica no histórico do item.
 *
 * O pesquisador pode ter um escopo de fichas (B1, C2...) no vínculo com o
 * destino: a equipe de campo de hospedagem não recebe atrativos.
 */
declare(strict_types=1);
require_once __DIR__ . '/Inventario.php';

final class Esteira
{
    /** Situações que a tela de campo e o envio à conferência aceitam. */
    public const EM_COLETA = ['pre_cadastro', 'em_campo'];

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    /** Quem confere e homologa no destino atual. */
    public static function coordena(): bool
    {
        return Auth::pode('homologacao.editar');
    }

    /** Quem coleta (pesquisador e quem coordena). */
    public static function coleta(): bool
    {
        return Auth::pode('coleta.editar') || Auth::pode('inventario.editar');
    }

    /**
     * Códigos de ficha que a pessoa coleta no destino atual; null é todas.
     * Só o papel de pesquisador tem escopo: quem coordena vê tudo.
     */
    public static function escopo(): ?array
    {
        $u = Auth::usuarioOuNulo();
        if (!$u || self::coordena() || Auth::ehSoftsul()) {
            return null;
        }
        $q = self::pdo()->prepare('SELECT escopo_fichas FROM usuario_empresa WHERE usuario_id = ? AND empresa_id = ? AND ativo = 1 AND escopo_fichas IS NOT NULL LIMIT 1');
        $q->execute([(int) $u['id'], Empresa::id()]);
        $json = $q->fetchColumn();
        $lista = $json ? json_decode((string) $json, true) : null;
        return is_array($lista) && $lista ? array_values(array_map('strval', $lista)) : null;
    }

    /** Pedaço de SQL (com os argumentos) que limita os itens ao escopo. */
    public static function filtroEscopo(string $aliasFicha = 'f'): array
    {
        $escopo = self::escopo();
        if ($escopo === null) {
            return ['', []];
        }
        return [" AND {$aliasFicha}.codigo IN (" . implode(',', array_fill(0, count($escopo), '?')) . ')', $escopo];
    }

    public static function dentroDoEscopo(string $codigoFicha): bool
    {
        $escopo = self::escopo();
        return $escopo === null || in_array($codigoFicha, $escopo, true);
    }

    /**
     * O que falta no item para homologar: o núcleo mínimo das fichas do MTur
     * (tipo, onde fica, coordenada, conservação) e uma foto, que o portal
     * precisa. Ficha de município (A1, dados gerais) só pede o nome.
     * Devolve rótulos legíveis; vazio é pronto.
     */
    public static function pendencias(array $item): array
    {
        $falta = [];
        if (trim((string) $item['nome']) === '') {
            $falta[] = 'nome';
        }
        if (($item['forma'] ?? '') === 'municipio') {
            return $falta;
        }
        $temTipos = (bool) self::pdo()->query('SELECT 1 FROM inventario_tipos WHERE ficha_id = ' . (int) $item['ficha_id'] . ' LIMIT 1')->fetchColumn();
        if ($temTipos && empty($item['tipo_id'])) {
            $falta[] = 'tipo';
        }
        if (trim((string) ($item['logradouro'] ?? '')) === '' && trim((string) ($item['referencia'] ?? '')) === '') {
            $falta[] = 'endereço ou ponto de referência';
        }
        if ($item['latitude'] === null || $item['longitude'] === null) {
            $falta[] = 'coordenada (GPS ou plus code)';
        }
        if (empty($item['conservacao'])) {
            $falta[] = 'estado de conservação';
        }
        $fotos = self::pdo()->prepare('SELECT COUNT(*) FROM inventario_midias WHERE item_id = ? AND empresa_id = ?');
        $fotos->execute([(int) $item['id'], (int) $item['empresa_id']]);
        if (!(int) $fotos->fetchColumn()) {
            $falta[] = 'ao menos uma foto';
        }
        // No destino que bloqueia o que fica fora da área, isso trava.
        require_once __DIR__ . '/AreaDestino.php';
        if (AreaDestino::bloqueia((int) $item['empresa_id'])) {
            array_push($falta, ...self::foraDaArea($item));
        }
        return $falta;
    }

    /**
     * Alertas que não travam a homologação: coordenada e fotos fora da área,
     * no destino que escolheu alertar (area_regra; no que bloqueia, viram
     * pendência).
     *
     * @return list<string>
     */
    public static function alertas(array $item): array
    {
        require_once __DIR__ . '/AreaDestino.php';
        return AreaDestino::bloqueia((int) $item['empresa_id']) ? [] : self::foraDaArea($item);
    }

    /** O que do item fica fora da área do destino (coordenada, fotos). */
    private static function foraDaArea(array $item): array
    {
        $alertas = [];
        if ($item['latitude'] !== null && $item['longitude'] !== null) {
            $area = AreaDestino::conferir((float) $item['latitude'], (float) $item['longitude'], (int) $item['empresa_id']);
            if (!$area['dentro']) {
                $alertas[] = 'a coordenada fica ' . AreaDestino::descrever($area);
            }
        }
        $q = self::pdo()->prepare('SELECT COUNT(*) FROM inventario_midias WHERE item_id = ? AND empresa_id = ? AND fora_area = 1');
        $q->execute([(int) $item['id'], (int) $item['empresa_id']]);
        if ($n = (int) $q->fetchColumn()) {
            $alertas[] = ($n === 1 ? '1 foto tem GPS' : $n . ' fotos têm GPS') . ' fora do destino';
        }
        return $alertas;
    }

    /** Pesquisador manda o item para a conferência. */
    public static function enviar(int $id, ?string $nota = null): bool
    {
        $item = Inventario::buscar($id);
        if (!$item || !in_array($item['situacao'], self::EM_COLETA, true)) {
            return false;
        }
        $uid = (int) (Auth::usuarioOuNulo()['id'] ?? 0) ?: null;
        self::pdo()->prepare("UPDATE inventario_itens SET situacao = 'em_conferencia', enviado_em = NOW(), coletado_em = COALESCE(coletado_em, NOW()),
                                     pesquisador_id = COALESCE(pesquisador_id, ?), atualizado_em = NOW(), atualizado_por = ?
                              WHERE id = ? AND empresa_id = ?")
            ->execute([$uid, $uid, $id, Empresa::id()]);
        Inventario::registrarHistorico($id, 'enviou', $item['situacao'], 'em_conferencia', $nota);
        Auditoria::registrar('alterou', 'inventario_itens', $id, 'enviou para a conferência: ' . $item['nome']);
        return true;
    }

    /**
     * Quem coordena homologa. Devolve as pendências que impediram (vazio é
     * homologado).
     */
    public static function homologar(int $id): array
    {
        $item = Inventario::buscar($id);
        if (!$item || !self::coordena() || in_array($item['situacao'], ['homologada', 'arquivada'], true)) {
            return ['situação que não se homologa'];
        }
        if ($falta = self::pendencias($item)) {
            return $falta;
        }
        $uid = (int) (Auth::usuarioOuNulo()['id'] ?? 0) ?: null;
        self::pdo()->prepare("UPDATE inventario_itens SET situacao = 'homologada', homologado_em = NOW(), ultima_auditoria_em = NOW(),
                                     coordenador_id = ?, devolucao_nota = NULL, atualizado_em = NOW(), atualizado_por = ?
                              WHERE id = ? AND empresa_id = ?")
            ->execute([$uid, $uid, $id, Empresa::id()]);
        Inventario::registrarHistorico($id, 'homologou', $item['situacao'], 'homologada');
        Auditoria::registrar('alterou', 'inventario_itens', $id, 'homologou: ' . $item['nome']);
        // Etapa 5: o EventCity fica sabendo do lugar homologado (se a ponte estiver ligada).
        require_once __DIR__ . '/EventCity.php';
        EventCity::avisarItem((int) Empresa::id(), $id);
        return [];
    }

    /** Quem coordena devolve para o campo, dizendo o que acertar. */
    public static function devolver(int $id, string $nota): bool
    {
        $item = Inventario::buscar($id);
        $nota = trim($nota);
        if (!$item || !self::coordena() || $nota === '' || $item['situacao'] !== 'em_conferencia') {
            return false;
        }
        $uid = (int) (Auth::usuarioOuNulo()['id'] ?? 0) ?: null;
        self::pdo()->prepare("UPDATE inventario_itens SET situacao = 'em_campo', devolucao_nota = ?, atualizado_em = NOW(), atualizado_por = ?
                              WHERE id = ? AND empresa_id = ?")
            ->execute([mb_substr($nota, 0, 1000), $uid, $id, Empresa::id()]);
        Inventario::registrarHistorico($id, 'devolveu', 'em_conferencia', 'em_campo', $nota);
        Auditoria::registrar('alterou', 'inventario_itens', $id, 'devolveu para o campo: ' . $item['nome']);
        return true;
    }

    /** Quem pode arquivar (tirar do inventário sem apagar). */
    public static function arquiva(): bool
    {
        return self::coordena() || Auth::pode('inventario.excluir');
    }

    /** Arquiva o item: sai das listas, do portal e do EventCity, sem apagar nada. */
    public static function arquivar(int $id): bool
    {
        $item = Inventario::buscar($id);
        if (!$item || !self::arquiva() || $item['situacao'] === 'arquivada') {
            return false;
        }
        $uid = (int) (Auth::usuarioOuNulo()['id'] ?? 0) ?: null;
        self::pdo()->prepare("UPDATE inventario_itens SET situacao = 'arquivada', atualizado_em = NOW(), atualizado_por = ? WHERE id = ? AND empresa_id = ?")
            ->execute([$uid, $id, Empresa::id()]);
        Inventario::registrarHistorico($id, 'arquivou', $item['situacao'], 'arquivada');
        Auditoria::registrar('alterou', 'inventario_itens', $id, 'arquivou: ' . $item['nome']);
        return true;
    }

    /** Desarquiva: volta para a situação de antes de arquivar (ou em campo). */
    public static function desarquivar(int $id): bool
    {
        $item = Inventario::buscar($id);
        if (!$item || !self::arquiva() || $item['situacao'] !== 'arquivada') {
            return false;
        }
        $q = self::pdo()->prepare("SELECT de FROM inventario_item_historico WHERE item_id = ? AND para = 'arquivada' ORDER BY id DESC LIMIT 1");
        $q->execute([$id]);
        $antes = (string) $q->fetchColumn();
        $volta = in_array($antes, ['pre_cadastro', 'em_campo', 'em_conferencia', 'homologada', 'desatualizada'], true) ? $antes : 'em_campo';
        $uid = (int) (Auth::usuarioOuNulo()['id'] ?? 0) ?: null;
        self::pdo()->prepare('UPDATE inventario_itens SET situacao = ?, atualizado_em = NOW(), atualizado_por = ? WHERE id = ? AND empresa_id = ?')
            ->execute([$volta, $uid, $id, Empresa::id()]);
        Inventario::registrarHistorico($id, 'desarquivou', 'arquivada', $volta);
        Auditoria::registrar('alterou', 'inventario_itens', $id, 'desarquivou: ' . $item['nome']);
        return true;
    }

    /** Itens esperando conferência no destino (o número do menu). */
    public static function naFila(int $empresaId): int
    {
        $q = self::pdo()->prepare("SELECT COUNT(*) FROM inventario_itens WHERE empresa_id = ? AND situacao = 'em_conferencia'");
        $q->execute([$empresaId]);
        return (int) $q->fetchColumn();
    }

    /** Itens que a pessoa tem para coletar (dentro do escopo). */
    public static function paraColetar(int $empresaId): int
    {
        [$fe, $ae] = self::filtroEscopo();
        $q = self::pdo()->prepare("SELECT COUNT(*) FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE i.empresa_id = ? AND i.situacao IN ('pre_cadastro', 'em_campo'){$fe}");
        $q->execute([$empresaId, ...$ae]);
        return (int) $q->fetchColumn();
    }

    /** Devolvidos pela conferência, no escopo da pessoa (o número do menu da coleta). */
    public static function devolvidos(int $empresaId): int
    {
        [$fe, $ae] = self::filtroEscopo();
        $q = self::pdo()->prepare("SELECT COUNT(*) FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE i.empresa_id = ? AND i.situacao = 'em_campo' AND i.devolucao_nota IS NOT NULL{$fe}");
        $q->execute([$empresaId, ...$ae]);
        return (int) $q->fetchColumn();
    }
}

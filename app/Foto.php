<?php
/**
 * Fotos dos cadastros (pessoas e destinos).
 * Softsul Software
 *
 * A foto da pessoa pode vir do perfil do WhatsApp do número cadastrado, na
 * hora de salvar: ninguém precisa mandar foto, e a lista deixa de ser uma
 * coluna de iniciais. Ou é enviada pelo editor da ficha. Fica em
 * storage/fotos, fora do DocumentRoot, e sai por /foto, que exige login e
 * confere quem pode ver (como no Orbit). A marca do destino (empresas.logo)
 * é outra coisa e fica em storage/midias/marcas/, pública (Marca.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Imagem.php';
require_once __DIR__ . '/Waha.php';
require_once __DIR__ . '/Marca.php';
require_once __DIR__ . '/svg.php';

final class Foto
{
    /** tipo da URL => tabela. A subpasta em storage/fotos tem o nome da tabela. */
    public const TIPOS = ['usuario' => 'usuarios', 'empresa' => 'empresas'];

    private static function subpasta(string $tipo): string
    {
        return self::TIPOS[$tipo];
    }

    private static function pasta(string $tipo): string
    {
        $cfg = require __DIR__ . '/config.php';
        return $cfg['caminhos']['fotos'] . '/' . self::subpasta($tipo);
    }

    /** De onde dá para baixar foto: só os servidores do WhatsApp. */
    private const ORIGENS = '/(^|\.)(whatsapp\.net|fbcdn\.net|whatsapp\.com)$/';

    /**
     * Baixa uma imagem. Só aceita https e só os servidores da lista: o
     * endereço vem de fora, e sem a lista o servidor viraria um baixador de
     * qualquer coisa. O tipo é conferido pelo conteúdo, e não pela extensão.
     *
     * @return array{dados:string, ext:string}|null
     */
    public static function baixar(string $url): ?array
    {
        $partes = parse_url($url);
        $host = strtolower((string) ($partes['host'] ?? ''));
        if (($partes['scheme'] ?? '') !== 'https' || !preg_match(self::ORIGENS, $host)) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)',
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => fn ($r, $baixar) => $baixar > 5242880 ? 1 : 0,
        ]);
        $dados = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($dados) || $dados === '' || $codigo !== 200) {
            return null;
        }
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->buffer($dados);
        $aceitos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        return isset($aceitos[$tipo]) ? ['dados' => $dados, 'ext' => $aceitos[$tipo]] : null;
    }

    /**
     * Confere o número no WhatsApp e, havendo foto, guarda como a foto do
     * cadastro. Grava também se o número tem WhatsApp e o chatId real.
     * Devolve 'foto', 'sem_foto', 'sem_whatsapp' ou 'falhou'; nunca lança,
     * para o cadastro salvar mesmo com o WhatsApp fora do ar.
     *
     * Só para 'usuario': é a única tabela com as colunas whatsapp_*; o
     * destino tem logotipo, não foto de perfil.
     */
    public static function doWhatsapp(string $tipo, int $id, ?string $telefone): string
    {
        if ($tipo !== 'usuario') {
            return 'falhou';
        }
        $r = self::buscarNoWhatsapp($id, $telefone);
        if ($r['resultado'] !== 'foto') {
            return $r['resultado'];
        }
        return self::guardar($tipo, $id, $r['img']) ? 'foto' : 'falhou';
    }

    /**
     * Só busca a foto do perfil do WhatsApp do usuário, sem gravar: o botão
     * "Buscar no WhatsApp" do editor mostra a foto e a pessoa decide se usa.
     * Grava se o número tem WhatsApp, como doWhatsapp.
     *
     * @return array{resultado:string, img:?array}
     */
    public static function buscarNoWhatsapp(int $id, ?string $telefone): array
    {
        $nada = fn (string $resultado) => ['resultado' => $resultado, 'img' => null];
        if (!$telefone) {
            return $nada('falhou');
        }
        try {
            $r = Waha::fotoDePerfil($telefone);
            if (!$r['ok']) {
                return $nada('falhou');
            }
            Database::conexao()->prepare('UPDATE usuarios SET whatsapp_chatid = ?, whatsapp_existe = ?, whatsapp_conferido_em = NOW() WHERE id = ?')
                ->execute([$r['chat_id'] ?? null, empty($r['existe']) ? 0 : 1, $id]);
            if (empty($r['existe'])) {
                return $nada('sem_whatsapp');
            }
            if (empty($r['url'])) {
                return $nada('sem_foto');
            }
            $img = self::baixar((string) $r['url']);
            return $img ? ['resultado' => 'foto', 'img' => $img] : $nada('falhou');
        } catch (Throwable $e) {
            error_log('foto do whatsapp: ' . $e->getMessage());
            return $nada('falhou');
        }
    }

    /**
     * Foto enviada pela pessoa (ficha e perfil): o arquivo de $_FILES. Confere o tipo pelo conteúdo e o tamanho, reduz e
     * guarda. Devolve null se deu certo, ou o motivo para mostrar.
     */
    public static function enviada(string $tipo, int $id, ?array $arquivo): ?string
    {
        if (!isset(self::TIPOS[$tipo]) || !$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Escolha uma foto.';
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
            return 'A foto não chegou inteira. Tente de novo.';
        }
        if ($arquivo['size'] > 15 * 1048576) {
            return 'A foto passa de 15 MB. Escolha uma menor.';
        }
        $dados = (string) file_get_contents($arquivo['tmp_name']);
        $aceitos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($dados);
        if (!isset($aceitos[$mime]) || !@imagecreatefromstring($dados)) {
            return 'Use uma foto em JPG, PNG ou WebP.';
        }
        return self::guardar($tipo, $id, ['dados' => $dados, 'ext' => $aceitos[$mime]]) ? null : 'Não consegui guardar a foto agora.';
    }

    /** Tira a foto do cadastro (volta às iniciais). */
    public static function remover(string $tipo, int $id): void
    {
        if (!isset(self::TIPOS[$tipo])) {
            return;
        }
        foreach (self::arquivosDe(self::pasta($tipo), $id) as $velha) {
            @unlink($velha);
        }
        Database::conexao()->prepare('UPDATE ' . self::TIPOS[$tipo] . ' SET foto = NULL WHERE id = ?')->execute([$id]);
    }

    /** As fotos de um cadastro na pasta ("12.jpg" antigo ou "12-1760000000.jpg"). */
    private static function arquivosDe(string $pasta, int $id): array
    {
        return array_merge(glob($pasta . '/' . $id . '.*') ?: [], glob($pasta . '/' . $id . '-*') ?: []);
    }

    /**
     * A foto do cabeçalho de uma ficha, que abre o editor: arrastar e soltar
     * (ou escolher; no celular, a câmera), enquadrar, zoom, girar e remover.
     * O resultado fica nos campos foto e foto_remover do formulário em volta
     * e só é gravado quando a ficha é salva (Foto::daFicha).
     */
    public static function editavel(string $tipo, int $id, ?string $foto, string $nome, ?string $form = null, bool $comWhatsapp = false): string
    {
        // Com WhatsApp já salvo na ficha, o editor oferece buscar a foto do
        // perfil; ela entra no enquadramento e só fica se a pessoa usar.
        $whats = $comWhatsapp && $tipo === 'usuario' && $id
            ? '<button type="button" class="botao claro" data-foto-whatsapp="' . $id . '" data-csrf="' . e(Auth::tokenCsrf()) . '">'
              . icone('whatsapp') . 'Buscar no WhatsApp</button>'
            : '';
        // Quando a foto fica fora do formulário (no perfil, o cabeçalho é
        // separado do cartão de dados), os campos apontam para ele por id.
        $doForm = $form ? ' form="' . e($form) . '"' : '';
        $atual = $id ? self::avatar($tipo, $id, $foto, $nome, 'brasao', 256) : avatarIniciais($nome ?: 'Novo', 'brasao');
        return '<div class="foto-editavel" data-editor-foto data-iniciais="' . e(iniciaisDe($nome ?: 'Novo')) . '">'
             . '<button type="button" class="brasao-troca" data-abrir-editor title="Trocar a foto">' . $atual
             . '<span class="brasao-etiqueta">' . ($foto ? 'Trocar' : 'Foto') . '</span></button>'
             . '<input type="file" name="foto" class="oculto-visual" tabindex="-1" aria-hidden="true" data-foto-saida' . $doForm . '>'
             . '<input type="hidden" name="foto_remover" value="" data-foto-remover' . $doForm . '>'
             . '<dialog class="confirmador editor-foto" aria-label="Foto">'
             . '<h2>Foto</h2>'
             . '<div class="zona-upload zona-foto" data-pronta="1" data-zona-foto>'
             . '<div class="zona-alvo" role="button" tabindex="0">' . icone('mais', null, 'zona-icone')
             . '<span class="zona-texto">Arraste a foto aqui</span>'
             . '<span class="zona-ajuda">ou clique para escolher (no celular, abre a câmera)</span></div>'
             . '<input type="file" accept="image/*" class="oculto-visual" data-foto-entrada></div>'
             . '<div class="recorte" data-recorte hidden>'
             . '<div class="recorte-quadro"><canvas width="600" height="600" data-foto-tela></canvas></div>'
             . '<p class="ajuda">Arraste para enquadrar.</p>'
             . '<div class="recorte-controles">'
             . '<button type="button" class="botao claro" data-girar="-90" title="Girar para a esquerda" aria-label="Girar para a esquerda">' . icone('trocar', null, 'espelhado') . '</button>'
             . '<input type="range" min="1" max="4" step="0.01" value="1" data-foto-zoom aria-label="Zoom">'
             . '<button type="button" class="botao claro" data-girar="90" title="Girar para a direita" aria-label="Girar para a direita">' . icone('trocar') . '</button>'
             . '</div>'
             . '<button type="button" class="como-link" data-outra-foto>Escolher outra foto</button>'
             . '</div>'
             . '<div class="acoes-modal">'
             . $whats
             . ($foto ? '<button type="button" class="botao claro" data-tirar-foto>' . icone('lixeira') . 'Remover foto</button>' : '')
             . '<button type="button" class="botao claro" data-fechar-editor>Cancelar</button>'
             . '<button type="button" class="botao" data-usar-foto disabled>' . icone('check') . 'Usar esta foto</button>'
             . '</div>'
             . '<p class="ajuda">A foto só é gravada quando você salvar a ficha.</p>'
             . '</dialog></div>';
    }

    /**
     * Ao salvar a ficha: aplica o que foi feito no editor (foto nova ou
     * remoção). Devolve null se não havia nada ou deu certo, ou o motivo.
     */
    public static function daFicha(string $tipo, int $id): ?string
    {
        if (!empty($_POST['foto_remover'])) {
            self::remover($tipo, $id);
            return null;
        }
        if (($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return self::enviada($tipo, $id, $_FILES['foto']);
    }

    /** Veio foto nova ou remoção do editor neste envio? */
    public static function mexeuNaFicha(): bool
    {
        return !empty($_POST['foto_remover']) || ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Padroniza a foto (Imagem, perfil pessoa: quadrada 512 x 512, JPEG, sem
     * metadados, rotação corrigida). Se não for imagem, volta como veio e a
     * conferência de tipo de quem chamou decide.
     */
    public static function reduzir(array $img): array
    {
        $jpeg = Imagem::padronizar((string) $img['dados'], 'pessoa');
        return $jpeg !== null ? ['dados' => $jpeg, 'ext' => 'jpg'] : $img;
    }

    /** Grava a imagem como a foto do cadastro, trocando a anterior. */
    private static function guardar(string $tipo, int $id, array $img): bool
    {
        $img = self::reduzir($img);
        $pasta = self::pasta($tipo);
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0770, true);
        }
        foreach (self::arquivosDe($pasta, $id) as $velha) {
            @unlink($velha);
        }
        // O nome leva a hora: o endereço da miniatura muda junto, e o
        // navegador não fica mostrando a foto antiga do cache.
        $nome = $id . '-' . time() . '.' . $img['ext'];
        if (@file_put_contents($pasta . '/' . $nome, $img['dados']) === false) {
            return false;
        }
        Database::conexao()->prepare('UPDATE ' . self::TIPOS[$tipo] . ' SET foto = ? WHERE id = ?')
            ->execute([self::subpasta($tipo) . '/' . $nome, $id]);
        return true;
    }

    /** A frase para o aviso depois de salvar. */
    public static function recado(string $resultado): string
    {
        return match ($resultado) {
            'foto'         => ' A foto veio do WhatsApp.',
            'sem_foto'     => ' O número tem WhatsApp, mas a foto do perfil não está visível.',
            'sem_whatsapp' => ' Este número não tem WhatsApp.',
            default        => '',
        };
    }

    /**
     * O rosto do destino nas listas e na ficha: o logo dele (empresas.logo),
     * inteiro e sem recorte, sobre fundo claro; sem logo, a foto ou as
     * iniciais, como qualquer cadastro.
     *
     * @param array<string,mixed> $d a linha de empresas
     */
    public static function avatarDestino(array $d, string $classe = 'avatar', int $px = 64): string
    {
        $logo = Marca::logoDe(Marca::logoVisivel($d) ?: null);
        if ($logo) {
            return '<img class="' . e($classe) . ' com-logo" src="' . e($logo) . '" alt="" loading="lazy" width="34" height="34">';
        }
        return self::avatar('empresa', (int) $d['id'], $d['foto'] ?? null, (string) $d['nome_fantasia'], $classe, $px);
    }

    /**
     * O rosto do cadastro: a foto, se houver, senão as iniciais coloridas.
     * O tamanho pedido ao servidor é o dobro do desenhado, para tela de alta
     * densidade.
     */
    public static function avatar(string $tipo, int $id, ?string $foto, string $nome, string $classe = 'avatar', int $px = 64): string
    {
        if ($foto) {
            return '<img class="' . e($classe) . ' com-foto" src="/foto/' . $tipo . '/' . $id . '/' . $px . '?v='
                 . substr(md5($foto), 0, 6) . '" alt="" loading="lazy" width="34" height="34">';
        }
        return avatarIniciais($nome, $classe);
    }
}

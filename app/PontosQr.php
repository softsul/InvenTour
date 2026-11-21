<?php
/**
 * Pontos de QR do destino (phygital, etapa 5).
 * Softsul Software
 *
 * O cartaz impresso leva o endereço https://<destino>/qr/<apelido>, e o
 * apelido não muda: o destino do QR (início, ficha, roteiro, pilar, agenda,
 * O destino) pode mudar sem reimprimir. O cartaz é um PDF A4 gerado na
 * fila (job qr_cartaz) com o QR grande, o título e a marca do destino.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Caderno.php';

final class PontosQr
{
    public const DESTINOS = [
        'inicio'  => 'Início do portal',
        'ficha'   => 'Ficha de um lugar',
        'roteiro' => 'Um roteiro',
        'pilar'   => 'Um pilar (Onde dormir, Onde comer...)',
        'agenda'  => 'Agenda de eventos',
        'destino' => 'O destino (história e textos)',
    ];

    public static function url(array $empresa, string $apelido): string
    {
        return Caderno::portal($empresa) . '/qr/' . $apelido;
    }

    public static function arquivoCartaz(int $eid, int $id): string
    {
        $cfg = require __DIR__ . '/config.php';
        $pasta = dirname(rtrim((string) $cfg['caminhos']['midias'], '/')) . '/qr/' . $eid;
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0770, true);
        }
        return $pasta . '/cartaz-' . $id . '.pdf';
    }

    /** Gera o cartaz do ponto (roda na fila). */
    public static function gerarCartaz(int $id): void
    {
        $q = Database::conexao()->prepare('SELECT p.*, e.nome_fantasia, e.slug, e.dominio_proprio, e.cor_acento FROM qr_pontos p JOIN empresas e ON e.id = p.empresa_id WHERE p.id = ?');
        $q->execute([$id]);
        $p = $q->fetch();
        if (!$p) {
            return;
        }
        $url = self::url($p, (string) $p['apelido']);
        $png = shell_exec('qrencode -t PNG -o - -s 14 -m 2 -l Q ' . escapeshellarg($url) . ' 2>/dev/null');
        $cor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $p['cor_acento']) ? (string) $p['cor_acento'] : '#0E7C66';
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
        $tmp = sys_get_temp_dir() . '/cartaz-' . $id . '-' . bin2hex(random_bytes(3));
        @mkdir($tmp, 0770, true);
        $emblema = $tmp . '/emblema.png';
        exec('rsvg-convert -w 600 -o ' . escapeshellarg($emblema) . ' ' . escapeshellarg(dirname(__DIR__) . '/public/assets/marca/emblema.svg') . ' 2>/dev/null');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            body{margin:0;font-family:"Liberation Sans",Arial,sans-serif;color:#1f2a37}
            .p{width:210mm;height:296mm;position:relative;overflow:hidden}
            .topo{background:' . $cor . ';color:#fff;padding:18mm 16mm 14mm}
            .sobre{font-size:12pt;letter-spacing:2pt;text-transform:uppercase;opacity:.85}
            .titulo{font-size:34pt;font-weight:bold;line-height:1.1;margin-top:4mm}
            .qr{text-align:center;margin-top:16mm}
            .qr img{width:120mm;height:120mm}
            .chamada{text-align:center;font-size:18pt;font-weight:bold;margin-top:6mm}
            .url{text-align:center;font-size:12pt;color:#5b6474;margin-top:3mm}
            .pe{position:absolute;left:16mm;right:16mm;bottom:12mm;border-top:.6pt solid #d9dee7;padding-top:5mm;font-size:9pt;color:#5b6474}
            .pe img{height:11mm;vertical-align:middle;margin-right:3mm}
        </style></head><body><div class="p">
            <div class="topo"><div class="sobre">' . $e($p['nome_fantasia']) . ' · Turismo oficial</div><div class="titulo">' . $e($p['titulo']) . '</div></div>
            <div class="qr"><img src="data:image/png;base64,' . base64_encode((string) $png) . '" alt=""></div>
            <div class="chamada">Aponte a câmera do celular</div>
            <div class="url">' . $e(preg_replace('#^https://#', '', $url)) . '</div>
            <div class="pe">' . (is_file($emblema) ? '<img src="file://' . $e($emblema) . '" alt="">' : '') . 'Guia oficial de ' . $e($p['nome_fantasia']) . ', no InvenTour</div>
        </div></body></html>';
        file_put_contents($tmp . '/c.html', $html);
        $saida = self::arquivoCartaz((int) $p['empresa_id'], $id);
        exec(implode(' ', array_map('escapeshellarg', ['wkhtmltopdf', '-q', '--encoding', 'utf-8', '--enable-local-file-access', '-s', 'A4',
            '-T', '0', '-B', '0', '-L', '0', '-R', '0', '--disable-smart-shrinking', '--dpi', '96', $tmp . '/c.html', $saida])) . ' 2>/dev/null');
        foreach (glob($tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($tmp);
    }
}

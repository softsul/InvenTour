<?php
/**
 * O mapa base do portal de cada destino: os blocos vetoriais do
 * OpenStreetMap (mapa base do Protomaps, licença ODbL) recortados na área do
 * município e gravados em portal/mapa-base/<empresa_id>/{z}/{x}/{y}.pbf, que
 * o Apache serve e o MapLibre do portal desenha no estilo do caderno.
 * Softsul Software
 *
 * Tudo sozinho, igual para todos os destinos: a fila (job portal_mapa_base,
 * de hora em hora) gera o mapa de quem ainda não tem e refaz o de quem tem
 * mais de 30 dias, um destino por vez. A área vem da divisa do IBGE
 * (AreaDestino) com folga de ~3 km. O arquivo do Protomaps é lido por partes
 * (HTTP Range, formato PMTiles v3): só o índice e os blocos da área, sem
 * custo e sem programa externo. A pasta nova só troca a antiga depois de
 * completa: o portal nunca fica sem mapa no meio da troca.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AreaDestino.php';

final class MapaBase
{
    private const BUILDS = 'https://build-metadata.protomaps.dev/builds.json';
    private const ORIGEM = 'https://build.protomaps.com/';
    private const ZOOM_MAX = 15;
    private const FOLGA = 0.03;          // graus em volta da divisa (~3 km)
    private const VALIDADE_DIAS = 30;

    private string $url = '';
    private array $cab = [];
    /** @var array<int,array> diretórios folha já lidos, por deslocamento */
    private array $folhas = [];

    public static function pasta(?int $eid = null): string
    {
        return dirname(__DIR__) . '/portal/mapa-base' . ($eid !== null ? '/' . $eid : '');
    }

    /** O que se sabe do mapa do destino: gerado_em, versao, blocos, bytes; null se não há. */
    public static function situacao(int $eid): ?array
    {
        $f = self::pasta($eid) . '/.mapa.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($d) ? $d : null;
    }

    /** O retângulo da área do destino com a folga: [oeste, sul, leste, norte] ou null. */
    public static function retangulo(int $eid): ?array
    {
        $a = AreaDestino::doDestino($eid);
        if (!$a) {
            return null;
        }
        $xs = $ys = [];
        foreach ($a['poligonos'] as $anel) {
            foreach ($anel as $p) {
                $xs[] = (float) $p[0];
                $ys[] = (float) $p[1];
            }
        }
        return $xs ? [min($xs) - self::FOLGA, min($ys) - self::FOLGA, max($xs) + self::FOLGA, max($ys) + self::FOLGA] : null;
    }

    /** O próximo destino sem mapa ou com mapa vencido (o mais antigo primeiro), ou null. */
    public static function proximo(): ?int
    {
        $candidatos = [];
        foreach (Database::conexao()->query('SELECT id FROM empresas WHERE ativo = 1 AND municipio_id IS NOT NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $s = self::situacao((int) $eid);
            $quando = $s ? strtotime((string) ($s['gerado_em'] ?? '')) : 0;
            // Mudou o município ou a divisa: o recorte velho não serve mais.
            $r = self::retangulo((int) $eid);
            if (!$r) {
                continue;
            }
            $mudou = $s && array_map(fn ($v) => round($v, 4), $r) != ($s['retangulo'] ?? []);
            if ($mudou || $quando < time() - self::VALIDADE_DIAS * 86400) {
                $candidatos[(int) $eid] = (int) $quando;
            }
        }
        asort($candidatos);
        return $candidatos ? (int) array_key_first($candidatos) : null;
    }

    /** Gera (ou refaz) o mapa do destino. Devolve a situação nova. Lança exceção se falhar. */
    public static function gerar(int $eid): array
    {
        $r = self::retangulo($eid) ?? throw new RuntimeException('destino sem divisa do IBGE');
        $m = new self();
        $versao = $m->abrirUltimaVersao();
        $destino = self::pasta($eid);
        $novo = $destino . '.novo-' . getmypid();
        self::apagar($novo);
        [$blocos, $bytes] = $m->recortar($r, $novo);
        if ($blocos < 10) {
            self::apagar($novo);
            throw new RuntimeException("recorte vazio ({$blocos} blocos)");
        }
        $sit = ['gerado_em' => date('Y-m-d H:i:s'), 'versao' => $versao, 'blocos' => $blocos, 'bytes' => $bytes,
                'retangulo' => array_map(fn ($v) => round($v, 4), $r), 'fonte' => '© OpenStreetMap (ODbL), Protomaps'];
        file_put_contents($novo . '/.mapa.json', json_encode($sit, JSON_UNESCAPED_UNICODE));
        // Troca: a antiga sai do caminho, a nova entra, a antiga é apagada.
        $velho = $destino . '.velho-' . getmypid();
        if (is_dir($destino) && !rename($destino, $velho)) {
            self::apagar($novo);
            throw new RuntimeException('não consegui tirar o mapa antigo do lugar');
        }
        if (!rename($novo, $destino)) {
            if (is_dir($velho)) {
                rename($velho, $destino);
            }
            throw new RuntimeException('não consegui pôr o mapa novo no lugar');
        }
        self::apagar($velho);
        return $sit;
    }

    // ------------------------------------------------------------ PMTiles

    private function abrirUltimaVersao(): string
    {
        $lista = json_decode((string) $this->baixar(self::BUILDS), true);
        $chaves = array_values(array_filter(array_map(fn ($b) => (string) ($b['key'] ?? ''), (array) $lista), fn ($k) => preg_match('/^\d{8}\.pmtiles$/', $k)));
        sort($chaves);
        $chave = end($chaves) ?: throw new RuntimeException('lista de versões do Protomaps vazia');
        $this->url = self::ORIGEM . $chave;
        $h = $this->ler(0, 127);
        if (substr($h, 0, 7) !== 'PMTiles' || ord($h[7]) !== 3) {
            throw new RuntimeException('arquivo do mapa em formato desconhecido');
        }
        $u = fn (int $i) => unpack('P', substr($h, $i, 8))[1];
        $this->cab = ['raiz' => $u(8), 'raiz_tam' => $u(16), 'folhas' => $u(40), 'dados' => $u(56),
                      'comp_dir' => ord($h[97]), 'comp_bloco' => ord($h[98]), 'zmax' => ord($h[101])];
        $this->cab['raiz_dir'] = $this->diretorio($this->ler($this->cab['raiz'], $this->cab['raiz_tam']));
        return substr($chave, 0, 8);
    }

    /** @return array{0:int,1:int} blocos e bytes gravados */
    private function recortar(array $r, string $pasta): array
    {
        [$oeste, $sul, $leste, $norte] = $r;
        $pedidos = [];
        for ($z = 0; $z <= min(self::ZOOM_MAX, $this->cab['zmax']); $z++) {
            [$x0, $x1] = [self::lonX($oeste, $z), self::lonX($leste, $z)];
            [$y0, $y1] = [self::latY($norte, $z), self::latY($sul, $z)];
            for ($x = $x0; $x <= $x1; $x++) {
                for ($y = $y0; $y <= $y1; $y++) {
                    $onde = $this->achar(self::idDoBloco($z, $x, $y));
                    if ($onde) {
                        $pedidos[] = [$onde[0], $onde[1], "$z/$x/$y"];
                    }
                }
            }
        }
        // Blocos vizinhos no arquivo vêm numa leitura só (até 4 MB por vez).
        usort($pedidos, fn ($a, $b) => $a[0] <=> $b[0]);
        [$blocos, $bytes, $i] = [0, 0, 0];
        while ($i < count($pedidos)) {
            $ini = $pedidos[$i][0];
            $fim = $ini + $pedidos[$i][1];
            $j = $i + 1;
            while ($j < count($pedidos) && $pedidos[$j][0] - $fim < 65536 && $pedidos[$j][0] + $pedidos[$j][1] - $ini < 4194304) {
                $fim = max($fim, $pedidos[$j][0] + $pedidos[$j][1]);
                $j++;
            }
            $trecho = $this->ler($this->cab['dados'] + $ini, $fim - $ini);
            for ($k = $i; $k < $j; $k++) {
                $d = substr($trecho, $pedidos[$k][0] - $ini, $pedidos[$k][1]);
                if ($this->cab['comp_bloco'] === 2) {
                    $d = gzdecode($d);
                    if ($d === false) {
                        throw new RuntimeException('bloco ' . $pedidos[$k][2] . ' corrompido');
                    }
                }
                $f = $pasta . '/' . $pedidos[$k][2] . '.pbf';
                if (!is_dir(dirname($f)) && !mkdir(dirname($f), 0775, true) && !is_dir(dirname($f))) {
                    throw new RuntimeException('não consegui criar ' . dirname($f));
                }
                file_put_contents($f, $d);
                $blocos++;
                $bytes += strlen($d);
            }
            $i = $j;
        }
        return [$blocos, $bytes];
    }

    /** [deslocamento, tamanho] do bloco nos dados, ou null se não existe. */
    private function achar(int $id): ?array
    {
        $dir = $this->cab['raiz_dir'];
        for ($nivel = 0; $nivel < 4; $nivel++) {
            $e = self::entradaDe($dir, $id);
            if (!$e) {
                return null;
            }
            if ($e['corrida'] > 0) {
                return $id - $e['id'] < $e['corrida'] ? [$e['desl'], $e['tam']] : null;
            }
            $chave = $e['desl'];
            $this->folhas[$chave] ??= $this->diretorio($this->ler($this->cab['folhas'] + $e['desl'], $e['tam']));
            $dir = $this->folhas[$chave];
        }
        return null;
    }

    private static function entradaDe(array $dir, int $id): ?array
    {
        [$a, $b] = [0, count($dir) - 1];
        while ($a <= $b) {
            $m = ($a + $b) >> 1;
            if ($dir[$m]['id'] === $id) {
                return $dir[$m];
            }
            if ($dir[$m]['id'] < $id) {
                $a = $m + 1;
            } else {
                $b = $m - 1;
            }
        }
        if ($b < 0) {
            return null;
        }
        $e = $dir[$b];
        return $e['corrida'] === 0 || $id - $e['id'] < $e['corrida'] ? $e : null;
    }

    private function diretorio(string $bruto): array
    {
        $d = $this->cab['comp_dir'] ?? 2;
        if ($d === 2) {
            $bruto = gzdecode($bruto);
            if ($bruto === false) {
                throw new RuntimeException('índice do mapa corrompido');
            }
        }
        $p = 0;
        $n = self::varint($bruto, $p);
        $ent = [];
        $ultimo = 0;
        for ($i = 0; $i < $n; $i++) {
            $ultimo += self::varint($bruto, $p);
            $ent[$i] = ['id' => $ultimo, 'corrida' => 0, 'tam' => 0, 'desl' => 0];
        }
        for ($i = 0; $i < $n; $i++) {
            $ent[$i]['corrida'] = self::varint($bruto, $p);
        }
        for ($i = 0; $i < $n; $i++) {
            $ent[$i]['tam'] = self::varint($bruto, $p);
        }
        for ($i = 0; $i < $n; $i++) {
            $v = self::varint($bruto, $p);
            $ent[$i]['desl'] = $v === 0 && $i > 0 ? $ent[$i - 1]['desl'] + $ent[$i - 1]['tam'] : $v - 1;
        }
        return $ent;
    }

    private static function varint(string $s, int &$p): int
    {
        [$v, $desloc] = [0, 0];
        do {
            $b = ord($s[$p++]);
            $v |= ($b & 0x7f) << $desloc;
            $desloc += 7;
        } while ($b & 0x80);
        return $v;
    }

    /** Número do bloco no PMTiles: blocos dos zooms anteriores + posição na curva de Hilbert. */
    public static function idDoBloco(int $z, int $x, int $y): int
    {
        $acc = intdiv((1 << (2 * $z)) - 1, 3);
        $d = 0;
        for ($s = (1 << $z) >> 1; $s > 0; $s >>= 1) {
            $rx = ($x & $s) > 0 ? 1 : 0;
            $ry = ($y & $s) > 0 ? 1 : 0;
            $d += $s * $s * ((3 * $rx) ^ $ry);
            if ($ry === 0) {
                if ($rx === 1) {
                    $x = $s - 1 - $x;
                    $y = $s - 1 - $y;
                }
                [$x, $y] = [$y, $x];
            }
        }
        return $acc + $d;
    }

    private static function lonX(float $lon, int $z): int
    {
        return (int) floor(($lon + 180) / 360 * (1 << $z));
    }

    private static function latY(float $lat, int $z): int
    {
        $r = deg2rad($lat);
        return (int) floor((1 - log(tan($r) + 1 / cos($r)) / M_PI) / 2 * (1 << $z));
    }

    private function ler(int $inicio, int $tamanho): string
    {
        $corpo = $this->baixar($this->url, ['Range: bytes=' . $inicio . '-' . ($inicio + $tamanho - 1)]);
        if (strlen($corpo) !== $tamanho) {
            throw new RuntimeException("leitura parcial do mapa ({$inicio}+{$tamanho})");
        }
        return $corpo;
    }

    private function baixar(string $url, array $cab = []): string
    {
        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $cab, CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)']);
            $corpo = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($corpo !== false && in_array($codigo, [200, 206], true)) {
                return (string) $corpo;
            }
            sleep($tentativa * 2);
        }
        throw new RuntimeException('o servidor do mapa não respondeu (' . $url . ')');
    }

    private static function apagar(string $pasta): void
    {
        if (!is_dir($pasta)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($pasta);
    }
}

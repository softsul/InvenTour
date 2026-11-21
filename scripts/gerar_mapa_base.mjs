/*
 * Gera o mapa base de um destino para o portal: recorta os blocos vetoriais
 * do mapa base do Protomaps (dados do OpenStreetMap, licença ODbL) na área do
 * município e grava em portal/mapa-base/<empresa_id>/{z}/{x}/{y}.pbf, que o
 * Apache serve e o MapLibre do portal desenha no estilo do caderno de viagem.
 * Softsul Software, 21/11/2026.
 *
 *   1. O retângulo da área (com folga de ~3 km):
 *      php -r 'require "app/AreaDestino.php"; $a = AreaDestino::doDestino(ID); ...'
 *      (ou a divisa do IBGE no painel; ordem: oeste sul leste norte)
 *   2. Numa pasta de trabalho fora do repositório: npm install pmtiles@3.2.1
 *   3. node scripts/gerar_mapa_base.mjs https://build.protomaps.com/AAAAMMDD.pmtiles \
 *        portal/mapa-base/ID OESTE SUL LESTE NORTE 15
 *      (a lista de versões fica em https://build-metadata.protomaps.dev/builds.json)
 *
 * Pato Branco (ID 1): -52.8395 -26.3329 -52.5015 -25.9675, zoom até 15,
 * 1.707 blocos e cerca de 4,4 MB. Sem custo: o arquivo do Protomaps é lido
 * por partes (range requests) e nada fica dependendo dele depois.
 */
import { PMTiles } from 'pmtiles';
import fs from 'node:fs';
import zlib from 'node:zlib';
const [url, saida, w, s, e, n, zmax] = process.argv.slice(2);
const p = new PMTiles(url);
const h = await p.getHeader();
console.log('cabeçalho', h.minZoom, h.maxZoom, h.tileCompression, h.tileType);
const lon2x = (lon, z) => Math.floor((+lon + 180) / 360 * 2 ** z);
const lat2y = (lat, z) => { const r = +lat * Math.PI / 180; return Math.floor((1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2 * 2 ** z); };
let n0 = 0, bytes = 0;
for (let z = 0; z <= +zmax; z++) {
  const [x0, x1, y0, y1] = [lon2x(w, z), lon2x(e, z), lat2y(n, z), lat2y(s, z)];
  const tarefas = [];
  for (let x = x0; x <= x1; x++) for (let y = y0; y <= y1; y++) tarefas.push([x, y]);
  for (let i = 0; i < tarefas.length; i += 16) {
    await Promise.all(tarefas.slice(i, i + 16).map(async ([x, y]) => {
      const r = await p.getZxy(z, x, y);
      if (!r) return;
      let dados = Buffer.from(r.data);
      if (dados[0] === 0x1f && dados[1] === 0x8b) dados = zlib.gunzipSync(dados);
      fs.mkdirSync(`${saida}/${z}/${x}`, { recursive: true });
      fs.writeFileSync(`${saida}/${z}/${x}/${y}.pbf`, dados);
      n0++; bytes += dados.length;
    }));
  }
  console.log('zoom', z, 'blocos até agora', n0, Math.round(bytes / 1024) + ' KB');
}

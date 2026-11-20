"""
Lê os quadros-resumo (NOME, ENDEREÇO, TELEFONE, PLUS CODE) de um caderno de
inventário em PDF e grava um JSON para scripts/importar_caderno.php.
Softsul Software

O caderno de Pato Branco 2025 tem duas páginas impressas por folha. Corte
cada metade com o pdftotext antes (largura da folha 898 pt):

  pdftotext -layout -f N -l N -x 0   -y 0 -W 449 -H 652 caderno.pdf pasta/NNNa.txt
  pdftotext -layout -f N -l N -x 449 -y 0 -W 450 -H 652 caderno.pdf pasta/NNNb.txt

  python3 -I scripts/ler_quadros_caderno.py pasta saida.json
"""
import re, json, sys, os, glob
pasta, saida = sys.argv[1], sys.argv[2]
PLUS = re.compile(r'\b([23456789CFGHJMPQRVWX]{4}\+[23456789CFGHJMPQRVWX]{2,3})\b')
SEC = re.compile(r'^\s*([1-3]\.\d(?:\.\d+){0,3})\s+(\S.{2,80}?)\s*$')
CAB = ['ANO', 'NOME', 'ENDEREÇO', 'Endereço', 'LOCAL', 'ARTISTA', 'TELEFONE', 'TEFONE', 'FONE', 'PLUS CODE']
itens = []
secao = ''
for arq in sorted(glob.glob(os.path.join(pasta, '*.txt'))):
    folha = os.path.basename(arq)[:3]
    cols = None
    for l in open(arq, encoding='utf8'):
        l = l.rstrip('\n')
        m = SEC.match(l)
        if m and not PLUS.search(l) and len(m.group(2)) < 70 and not re.search(r'\(\d{2}\)', l):
            secao = m.group(1) + ' ' + re.sub(r'\s+', ' ', m.group(2))
            cols = None
            continue
        if 'NOME' in l and 'PLUS' in l:
            cols = []
            for c in CAB:
                i = l.find(c)
                if i >= 0 and not any(abs(i - p) < 2 for p, _ in cols):
                    cols.append((i, {'ENDEREÇO': 'endereco', 'Endereço': 'endereco', 'LOCAL': 'local', 'TEFONE': 'telefone', 'FONE': 'telefone',
                                     'TELEFONE': 'telefone', 'PLUS CODE': 'plus', 'NOME': 'nome', 'ANO': 'ano', 'ARTISTA': 'artista'}[c]))
            cols.sort()
            # TELEFONE contém FONE: fica só o primeiro de cada nome
            vist = set(); cols = [(p, n) for p, n in cols if not (n in vist or vist.add(n))]
            continue
        if not cols:
            continue
        pm = PLUS.search(l)
        if not pm:
            continue
        reg = {}
        for k, (ini, nome) in enumerate(cols):
            fim = cols[k + 1][0] if k + 1 < len(cols) else len(l)
            ini2 = max(0, ini - 1)
            reg[nome] = l[ini2:fim].strip()
        reg['plus'] = pm.group(1)
        # o resto da linha depois do plus code (C UH L da hospedagem, faixa de preço)
        reg['resto'] = l[pm.end():].strip()
        tel = re.search(r'\(\d{2}\)\s?[\d \-]{8,12}\d', l[:pm.start()])
        reg['telefone'] = re.sub(r'\D', '', tel.group(0)) if tel else ''
        # nome e endereço com espaço simples: o endereço começa por logradouro
        n = reg.get('nome', '')
        mm = re.match(r'^(.*?)\s+((?:R\.|Rua|Av\.|Avenida|Rod\.|Via|V\.|Est\.|Estr\.|Praça|Pç\.|Al\.|Trav\.|Linha|Com\.|Comunidade)\s.*)$', n)
        if mm and not reg.get('endereco'):
            reg['nome'], reg['endereco'] = mm.group(1), mm.group(2)
        reg['nome'] = re.sub(r'\s{2,}.*$', '', reg.get('nome', '')).strip(' |*')
        if not reg['nome'] or reg['nome'] in ('NOME',):
            continue
        reg['secao'] = secao; reg['folha'] = int(folha)
        itens.append(reg)
vistos = set(); unicos = []
for i in itens:
    k = (i['nome'].lower(), i['plus'])
    if k not in vistos:
        vistos.add(k); unicos.append(i)
json.dump(unicos, open(saida, 'w'), ensure_ascii=False, indent=1)
from collections import Counter
print(len(unicos))
for s, n in sorted(Counter(i['secao'] for i in unicos).items()): print(f'{n:4}  {s}')

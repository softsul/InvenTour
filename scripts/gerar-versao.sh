#!/bin/sh
# Gera public/versao.json com a versão do InvenTour, a partir do git.
# Softsul Software
#
# Roda sozinho depois de cada commit, merge, checkout e rebase (ganchos em
# .git/hooks, instalados por scripts/instalar-ganchos-versao.sh). Ninguém
# escreve versão à mão: o padrão da família é FIXA.AA.MMN, a versão fixa do
# produto, o ano do último commit e, colados, o mês dele e quantos commits
# houve naquele mês. Ex.: 1.26.1112 (InvenTour 1, 2026, novembro, 12º commit).
# A versão fixa só muda por decisão do Luiz. Padrão completo em
# https://erp.softsul.com/assets/marca/padrao-versao-e-produtos.txt
set -e
FIXA=1
raiz=$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)
cd "$raiz"
export TZ=America/Sao_Paulo
ultimo=$(git log -1 --format=%cd --date=format-local:%Y-%m-%dT%H:%M:%S%z)
ano=$(git log -1 --format=%cd --date=format-local:%y)
mes=$(git log -1 --format=%cd --date=format-local:%m)
inicio=$(git log -1 --format=%cd --date=format-local:%Y-%m-01T00:00:00)
n=$(git rev-list --count --since="$inicio" HEAD)
# %z sai como -0300; o JSON leva -03:00.
publicada=$(printf '%s' "$ultimo" | sed 's/\([0-9][0-9]\)$/:\1/')
tmp=public/.versao.json.tmp
printf '{"produto":"InvenTour","versao":"%s.%s.%s%s","publicada_em":"%s"}\n' "$FIXA" "$ano" "$mes" "$n" "$publicada" > "$tmp"
chmod 644 "$tmp"
mv "$tmp" public/versao.json

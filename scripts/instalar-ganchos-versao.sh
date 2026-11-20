#!/bin/sh
# Instala os ganchos do git que mantêm public/versao.json em dia.
# Softsul Software
#
# Ganchos não vão no repositório, por isso este script: rode uma vez em cada
# cópia nova (servidor ou máquina de desenvolvimento).
set -e
raiz=$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)
for g in post-commit post-merge post-checkout post-rewrite; do
  f="$raiz/.git/hooks/$g"
  printf '#!/bin/sh\n# Versão do InvenTour (scripts/instalar-ganchos-versao.sh)\n"$(git rev-parse --show-toplevel)/scripts/gerar-versao.sh" || true\n' > "$f"
  chmod 755 "$f"
done
"$raiz/scripts/gerar-versao.sh"
echo "Ganchos instalados. Versão atual: $(cat "$raiz/public/versao.json")"

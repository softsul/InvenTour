#!/bin/bash
# Gera uma versão nova do InvenTour Campo (Android) e publica para download.
# Softsul Software
#
#   ./gerar-versao.sh 1.1.0
#
# Sobe o versionCode (+1), grava o versionName, põe o código no User-Agent
# (InvenTourCampo/<código>) e chama o publicar-apk.sh. Como a tela vem dentro
# do app, toda mudança na coleta (app/ColetaTela.php, assets/coleta.js,
# estilos) precisa de versão nova para chegar ao celular.
set -euo pipefail
cd "$(dirname "$0")"
NOME="${1:?uso: ./gerar-versao.sh <versão, ex. 1.1.0>}"
GRADLE=android/app/build.gradle
ATUAL=$(grep -oP 'versionCode \K[0-9]+' "$GRADLE")
NOVO=$((ATUAL + 1))
sed -i "s/versionCode $ATUAL/versionCode $NOVO/; s/versionName \"[^\"]*\"/versionName \"$NOME\"/" "$GRADLE"
python3 - "$NOVO" <<'PY'
import json, sys
p = 'capacitor.config.json'
c = json.load(open(p)); c['appendUserAgent'] = 'InvenTourCampo/' + sys.argv[1]
open(p, 'w').write(json.dumps(c, indent=2, ensure_ascii=False) + '\n')
PY
./publicar-apk.sh "$NOVO" "$NOME"

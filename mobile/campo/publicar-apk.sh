#!/bin/bash
# Compila o APK assinado do InvenTour Campo e publica para download no painel.
# Softsul Software
# Chamado pelo gerar-versao.sh; sozinho, refaz a versão atual.
#   ./publicar-apk.sh [código] [nome]
set -euo pipefail
cd "$(dirname "$0")"
GRADLE=android/app/build.gradle
CODIGO="${1:-$(grep -oP 'versionCode \K[0-9]+' "$GRADLE")}"
NOME="${2:-$(grep -oP 'versionName "\K[^"]+' "$GRADLE")}"
# A tela embutida sai do painel: mesma coleta, mesmos estilos.
php ../../scripts/gerar_www_campo.php
npx cap sync android
( cd android && ANDROID_HOME=/opt/android-sdk JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 nice -n 15 ./gradlew --no-daemon -q assembleRelease bundleRelease )
DESTINO=../../public/baixar
mkdir -p "$DESTINO"
cp android/app/build/outputs/apk/release/app-release.apk "$DESTINO/inventour-campo.apk"
chmod 644 "$DESTINO/inventour-campo.apk"
printf '{"versionCode": %s, "versionName": "%s"}\n' "$CODIGO" "$NOME" > "$DESTINO/versao-inventour-campo.json"
# Google Play só aceita .aab, enviado à mão em Testar e lançar.
cp android/app/build/outputs/bundle/release/app-release.aab "$DESTINO/inventour-campo.aab"
chmod 644 "$DESTINO/inventour-campo.aab"
mkdir -p "$DESTINO/loja-inventour-campo" && cp loja/*.png "$DESTINO/loja-inventour-campo/" && chmod 644 "$DESTINO/loja-inventour-campo/"*
echo "Publicado: InvenTour Campo $NOME (código $CODIGO) em https://app.inventour.com.br/baixar/inventour-campo.apk"

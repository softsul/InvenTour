# InvenTour Campo (Android e iPhone)

App de coleta do inventário turístico em campo. Softsul Software.

Diferente das cascas do EventCity (que abrem o site), aqui **a tela vem
dentro do app**, porque a coleta acontece onde não há sinal:

- a tela é a mesma do painel: `app/ColetaTela.php` e `public/assets/coleta.js`,
  copiados para `www/` por `scripts/gerar_www_campo.php`, com
  `fonte/campo.js` e `fonte/campo.css` (o que é só do app);
- tudo o que a pessoa grava fica no aparelho (IndexedDB do WebView): itens,
  observações e fotos;
- **nada sobe sozinho**: o botão Sincronizar testa a conexão
  (`/api/coleta?ping=1`), envia a fila na ordem (salvar, fotos, enviar à
  conferência), com um uuid por passo para a repetição não duplicar, e
  baixa a lista nova;
- GPS e câmera nativos (`@capacitor/geolocation`, `@capacitor/camera`):
  cada foto tirada pela câmera leva a coordenada do aparelho naquele
  momento (vai junto no envio e fica em `inventario_midias`, com a
  precisão); o item ganha a coordenada pelo botão "Usar o GPS daqui" ou
  pela primeira foto.

| | |
|---|---|
| Pacote | `br.com.softsul.inventour.campo` |
| User-Agent | `InvenTourCampo/<código>` |
| Servidor | `https://app.inventour.com.br` (APIs `coleta`, `coleta-foto`, `campo-conectar`) |
| Chave Android | `/opt/inventour-campo-android` (fora do Git) |
| Ícone | a marca colorida sobre branco (`gerar-icones.py`) |

## Conectar um aparelho

Sem senha no celular: no painel, Coleta de campo, "Gerar código de
conexão" (8 letras, vale 15 minutos, uma vez só). O app troca o código por
um token do aparelho (`app/Campo.php`); no banco fica só o hash
(`campo_aparelhos`). O token vale para aquela pessoa naquele destino, cai
se a senha mudar ou o acesso sair, e o painel desconecta o aparelho
perdido. As APIs aceitam o token no cabeçalho `X-Aparelho`, com CORS só
para as origens do Capacitor (`https://localhost` no Android,
`capacitor://localhost` no iPhone).

## Gerar uma versão (Android)

```
cd /home/www/app.inventour.com.br/mobile/campo
./gerar-versao.sh 1.1.0
```

Gera a `www`, sobe o `versionCode`, compila o APK assinado
(`public/baixar/inventour-campo.apk`, o link do painel) e o `.aab` do Google
Play. **Toda mudança na coleta precisa de versão nova** para chegar ao
celular, porque a tela vem dentro do app.

## Chave (fora do Git)

`/opt/inventour-campo-android/inventour-campo.jks` e `keystore.properties`
(senha aleatória, permissão 600). **Guarde uma cópia em lugar seguro**: sem
ela não dá para publicar versão nova por cima.

## iPhone

Projeto em `ios/` (Capacitor 6, CocoaPods), só iPhone, só retrato. Sem Mac:
compila no GitHub Actions (`.github/workflows/ios-campo.yml`, "InvenTour
Campo iPhone", rodar à mão) e envia ao TestFlight, com o certificado Apple
Distribution da equipe (o mesmo do Centurion e do EventCity) e um perfil
com nome próprio ("InvenTour Campo App Store"), criado por
`mobile/assinatura-ios.py`, que também registra o App ID se faltar.

Falta o Luiz: criar o app no App Store Connect com o pacote acima e colar
no repositório softsul/InvenTour os segredos ASC_KEY_ID, ASC_ISSUER_ID,
ASC_KEY_P8 e IOS_DIST_KEY (os mesmos do EventCity).

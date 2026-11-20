#!/usr/bin/env python3
"""
Ícones, tela de abertura e imagens da loja do InvenTour Campo, a partir do
ícone do InvenTour (public/assets/icone-maskable-512.png).
Softsul Software

    python3 mobile/campo/gerar-icones.py

A marca colorida sobre branco, como no site. Android: ícone comum, redondo
e adaptativo (frente transparente sobre fundo branco), e a tela de
abertura. iPhone: o AppIcon de 1024 (o Xcode tira os outros tamanhos).
Loja: ícone 512 e destaque 1024x500 em mobile/campo/loja.
"""
import os
from PIL import Image, ImageDraw, ImageFont

AQUI = os.path.dirname(os.path.abspath(__file__))
ORIGEM = os.path.join(AQUI, '..', '..', 'public', 'assets', 'icone-maskable-512.png')
FUNDO = (255, 255, 255)
NOME = 'InvenTour Campo'
FRASE = 'O inventário turístico em campo, mesmo sem sinal'
DENSIDADES = {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}


def marca():
    """A marca sem o fundo branco (transparente)."""
    im = Image.open(ORIGEM).convert('RGBA')
    px = im.load()
    for y in range(im.height):
        for x in range(im.width):
            r, g, b, a = px[x, y]
            claro = min(r, g, b)
            if claro > 235:
                px[x, y] = (255, 255, 255, 0)
                continue
            alfa = 255 if claro < 120 else int(255 * (235 - claro) / 115)
            px[x, y] = (r, g, b, alfa)
    return im.crop(im.getbbox())


def colar(tam, cor, m, fracao):
    tela = Image.new('RGBA', tam, (cor + (255,)) if cor else (0, 0, 0, 0))
    lado = int(min(tam) * fracao)
    escala = lado / max(m.size)
    mm = m.resize((max(1, int(m.width * escala)), max(1, int(m.height * escala))), Image.LANCZOS)
    tela.alpha_composite(mm, ((tam[0] - mm.width) // 2, (tam[1] - mm.height) // 2))
    return tela


def fonte(tamanho):
    for f in ('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'):
        if os.path.exists(f):
            return ImageFont.truetype(f, tamanho)
    return ImageFont.load_default()


m = marca()
res = os.path.join(AQUI, 'android', 'app', 'src', 'main', 'res')
if os.path.isdir(res):
    for nome, d in DENSIDADES.items():
        lado = int(48 * d)
        frente = int(108 * d)
        colar((frente, frente), None, m, 0.55).save(os.path.join(res, 'mipmap-' + nome, 'ic_launcher_foreground.png'))
        quadrado = colar((lado, lado), FUNDO, m, 0.70)
        quadrado.convert('RGB').save(os.path.join(res, 'mipmap-' + nome, 'ic_launcher.png'))
        redondo = quadrado.copy()
        mascara = Image.new('L', (lado, lado), 0)
        ImageDraw.Draw(mascara).ellipse((0, 0, lado - 1, lado - 1), fill=255)
        redondo.putalpha(mascara)
        redondo.save(os.path.join(res, 'mipmap-' + nome, 'ic_launcher_round.png'))
    # Fundo do ícone adaptativo: branco.
    fundo_xml = os.path.join(res, 'values', 'ic_launcher_background.xml')
    with open(fundo_xml, 'w') as f:
        f.write('<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <color name="ic_launcher_background">#FFFFFF</color>\n</resources>\n')
    for pasta in os.listdir(res):
        arq = os.path.join(res, pasta, 'splash.png')
        if os.path.exists(arq):
            tam = Image.open(arq).size
            colar(tam, FUNDO, m, 0.30).convert('RGB').save(arq)

# iPhone: AppIcon 1024 sem transparência; tela de abertura.
ios = os.path.join(AQUI, 'ios', 'App', 'App', 'Assets.xcassets')
if os.path.isdir(ios):
    colar((1024, 1024), FUNDO, m, 0.70).convert('RGB').save(os.path.join(ios, 'AppIcon.appiconset', 'AppIcon-512@2x.png'))
    splash = os.path.join(ios, 'Splash.imageset')
    if os.path.isdir(splash):
        for arq in os.listdir(splash):
            if arq.endswith('.png'):
                p = os.path.join(splash, arq)
                colar(Image.open(p).size, FUNDO, m, 0.22).convert('RGB').save(p)

loja = os.path.join(AQUI, 'loja')
os.makedirs(loja, exist_ok=True)
colar((512, 512), FUNDO, m, 0.70).convert('RGB').save(os.path.join(loja, 'icone-512.png'))
destaque = Image.new('RGBA', (1024, 500), FUNDO + (255,))
destaque.alpha_composite(colar((260, 260), None, m, 0.92), (70, 120))
d = ImageDraw.Draw(destaque)


def caber(texto, tamanho):
    while tamanho > 12 and d.textlength(texto, font=fonte(tamanho)) > 590:
        tamanho -= 2
    return fonte(tamanho)


d.text((380, 175), NOME, font=caber(NOME, 62), fill=(22, 50, 79))
d.text((382, 262), FRASE, font=caber(FRASE, 26), fill=(12, 108, 120))
destaque.convert('RGB').save(os.path.join(loja, 'destaque-1024x500.png'))
print('ícones ok')

#!/usr/bin/env python3
"""
Assinatura do app do iPhone do InvenTour Campo na compilação do GitHub (sem Mac).
Softsul Software (molde do Centurion e do EventCity)

Com a chave da API do App Store Connect:
- acha o certificado Apple Distribution que casa com a chave privada
  (segredo IOS_DIST_KEY; o mesmo certificado do Centurion, da equipe, vence em 11/11/2027);
- registra o App ID do pacote, se ainda não existir;
- cria (ou reaproveita) o perfil do app (nome próprio: não toca no do Centurion nem nos do EventCity);
- grava distribuicao.cer e o perfil em ~/Library/MobileDevice/Provisioning Profiles.

Uso: assinatura.py <chave_api.p8> <key_id> <issuer_id> <chave_privada.pem> <saida_dir> <pacote> <perfil>
"""
import base64, json, os, subprocess, sys, time, urllib.parse, urllib.request

import jwt  # PyJWT

API = 'https://api.appstoreconnect.apple.com/v1'
PACOTE = sys.argv[6]
PERFIL = sys.argv[7]

p8, kid, iss, chave_pem, saida = sys.argv[1:6]
token = jwt.encode({'iss': iss, 'iat': int(time.time()), 'exp': int(time.time()) + 1100, 'aud': 'appstoreconnect-v1'},
                   open(p8).read(), algorithm='ES256', headers={'kid': kid, 'typ': 'JWT'})


def api(metodo, caminho, corpo=None):
    req = urllib.request.Request(API + caminho, method=metodo, data=json.dumps(corpo).encode() if corpo else None,
                                 headers={'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req) as r:
            t = r.read()
            return json.loads(t) if t else {}
    except urllib.error.HTTPError as e:
        sys.exit(f'API {metodo} {caminho}: {e.code} {e.read().decode()[:500]}')


def modulo_da_chave():
    return subprocess.run(['openssl', 'rsa', '-noout', '-modulus', '-in', chave_pem], capture_output=True, text=True, check=True).stdout.strip()


def modulo_do_cer(der):
    return subprocess.run(['openssl', 'x509', '-inform', 'DER', '-noout', '-modulus'], input=der, capture_output=True, check=True).stdout.decode().strip()


alvo = modulo_da_chave()
cert = None
for c in api('GET', '/certificates?filter[certificateType]=DISTRIBUTION,IOS_DISTRIBUTION&limit=50')['data']:
    der = base64.b64decode(c['attributes']['certificateContent'])
    if modulo_do_cer(der) == alvo:
        cert = c
        open(os.path.join(saida, 'distribuicao.cer'), 'wb').write(der)
        break
if not cert:
    sys.exit('Nenhum certificado Apple Distribution casa com a chave IOS_DIST_KEY.')
print('Certificado:', cert['attributes'].get('name'), cert['attributes'].get('expirationDate'))

pacote = api('GET', f'/bundleIds?filter[identifier]={PACOTE}')['data']
pacote = [b for b in pacote if b['attributes']['identifier'] == PACOTE]
if pacote:
    pacote = pacote[0]
else:
    # Primeira compilação: o App ID nasce aqui (sem capacidades extras; câmera e GPS não pedem).
    pacote = api('POST', '/bundleIds', {'data': {'type': 'bundleIds', 'attributes': {
        'identifier': PACOTE, 'name': 'InvenTour Campo', 'platform': 'IOS'}}})['data']
    print('App ID criado:', PACOTE)

# O perfil vale enquanto estiver ativo e com o mesmo certificado; mudou a capacidade do App ID, a Apple o invalida.
perfil = None
for p in api('GET', f'/profiles?filter[name]={urllib.parse.quote(PERFIL)}&include=certificates&limit=20')['data']:
    certs = [r['id'] for r in p['relationships']['certificates'].get('data', [])] if 'relationships' in p else []
    if p['attributes']['profileState'] == 'ACTIVE' and cert['id'] in certs:
        perfil = p
        break
    api('DELETE', f"/profiles/{p['id']}")  # inválido ou de outro certificado: refaz
if not perfil:
    perfil = api('POST', '/profiles', {'data': {'type': 'profiles', 'attributes': {'name': PERFIL, 'profileType': 'IOS_APP_STORE'},
                                               'relationships': {'bundleId': {'data': {'type': 'bundleIds', 'id': pacote['id']}},
                                                                 'certificates': {'data': [{'type': 'certificates', 'id': cert['id']}]}}}})['data']
    print('Perfil criado:', perfil['attributes']['uuid'])
else:
    print('Perfil reaproveitado:', perfil['attributes']['uuid'])

pasta = os.path.expanduser('~/Library/MobileDevice/Provisioning Profiles')
os.makedirs(pasta, exist_ok=True)
open(os.path.join(pasta, perfil['attributes']['uuid'] + '.mobileprovision'), 'wb').write(base64.b64decode(perfil['attributes']['profileContent']))

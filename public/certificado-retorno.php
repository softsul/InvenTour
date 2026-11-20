<?php
/**
 * Volta de certificado.softsul.com: o passe assinado vira a sessão (entrar)
 * ou o vínculo com a conta de quem está logado (vincular).
 * Softsul Software
 *
 * Para quem erra, uma mensagem só: dizer qual teste falhou ajudaria quem
 * tenta adivinhar.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Certificado.php';

$p = Certificado::lerPasse((string) ($_GET['passe'] ?? ''));
$logado = Auth::usuarioOuNulo();

if (!$p) {
    Auditoria::falhaDeAcesso('certificado_negado');
    if ($logado) {
        avisar('Não deu para ler o certificado. O pedido vale por poucos minutos e uma vez só. Tente de novo.', 'ruim');
        redirecionar('/perfil#certificados');
    }
    redirecionar('/login?certificado=falhou');
}

if ($p['acao'] === 'vincular') {
    if (!$logado) {
        redirecionar('/login');
    }
    $erro = Certificado::vincular((int) $logado['id'], $p);
    avisar($erro === '' ? 'Certificado vinculado. Da próxima vez, entre com ele.' : $erro, $erro === '' ? 'bom' : 'ruim');
    redirecionar('/perfil#certificados');
}

$dono = Certificado::dono((string) $p['impressao']);
if (!$dono || !Auth::temAlgumAcesso((int) $dono['usuario_id'], (bool) $dono['softsul'])) {
    Auditoria::falhaDeAcesso('certificado_negado');
    Auditoria::registrar('login_negado', 'usuario_certificados', null, 'certificado sem vínculo: ' . $p['titular']);
    redirecionar('/login?certificado=sem_vinculo');
}
Certificado::marcarUso((int) $dono['id']);
$destino = destinoSeguro($_SESSION['destino_login'] ?? null);
Auth::entrarPorCertificado((int) $dono['usuario_id'], (string) $dono['titular']);
unset($_SESSION['destino_login']);
redirecionar($destino);

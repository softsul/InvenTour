<?php
/**
 * Atalho para excluir a conta: leva ao cartão "Excluir minha conta" em Meus
 * dados, passando pelo login quando preciso. É o endereço citado no
 * rodapé do portal e na política de privacidade, inclusive dentro dos
 * aplicativos (exigência da App Store, regra 5.1.1(v)).
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

Auth::exigirLogin();
redirecionar('/perfil#excluir-conta');

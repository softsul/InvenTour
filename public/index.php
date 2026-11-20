<?php
/**
 * Raiz do painel: quem está logado vai para o início, quem não está, para a entrada.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

redirecionar(Auth::usuarioOuNulo() ? '/painel' : '/login');

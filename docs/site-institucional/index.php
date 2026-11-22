<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>InvenTour · Inventário turístico vivo para destinos inteligentes</title>
<meta name="description" content="InvenTour: inventário da oferta turística sempre atualizado, coleta de campo com app, caderno oficial, portal do destino e inteligência de demanda, para Destinos Turísticos Inteligentes.">
<meta name="theme-color" content="#EAF1F1">
<link rel="canonical" href="https://inventour.com.br/">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="InvenTour">
<meta property="og:url" content="https://inventour.com.br/">
<meta property="og:title" content="InvenTour · Inventário turístico vivo para destinos inteligentes">
<meta property="og:description" content="O inventário da oferta turística deixa de ser um PDF que envelhece e vira dado vivo, publicado e medido.">
<meta property="og:image" content="https://inventour.com.br/assets/emblema.png">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/emblema-180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --navy:#003C60; --teal:#18A890; --teal-d:#0C6C78; --blue:#2E7CA6; --gold:#D6B23C;
    --ink:#16384F; --muted:#5E768A; --faint:#8DA0AE; --line:rgba(0,60,96,.10); --card:#FFFFFF;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{scroll-behavior:smooth}
  html{background:#EAF1F1}
  body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:var(--ink);line-height:1.55;overflow-x:hidden;
    background:linear-gradient(180deg,#FFFFFF 0,#EDF3F3 700px,#E6EEEE 100%)}
  a{color:inherit}
  .wrap{width:min(1120px,100%);margin:0 auto;padding:0 clamp(16px,4vw,40px)}

  /* topo */
  .topo{position:sticky;top:0;z-index:20;padding-top:env(safe-area-inset-top);
    background:rgba(244,248,248,.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:1px solid transparent;transition:border-color .2s}
  .topo.rolou{border-color:var(--line)}
  .topo .wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:64px}
  .topo .marca{display:flex;align-items:center;gap:10px;text-decoration:none}
  .topo .marca img.e{width:30px;height:auto}
  .topo .marca img.w{height:19px;width:auto}
  .topo .dir{display:flex;align-items:center;gap:8px}
  .topo .entrar{font-size:.88rem;font-weight:600;color:var(--navy);text-decoration:none;padding:8px 12px;border-radius:999px}
  .topo .entrar:hover{background:rgba(0,60,96,.06)}

  .botao{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;font:inherit;font-weight:600;font-size:.95rem;
    color:#fff;text-decoration:none;border:0;cursor:pointer;padding:12px 24px;border-radius:999px;
    background:linear-gradient(95deg,var(--navy),var(--teal-d) 60%,var(--teal));box-shadow:0 12px 30px -10px rgba(0,108,120,.55);
    transition:transform .2s ease,box-shadow .2s ease,filter .2s ease}
  .botao:hover{transform:translateY(-2px);filter:brightness(1.06)}
  .botao:disabled{opacity:.6;cursor:default;transform:none}
  .botao.claro{background:#fff;color:var(--navy);border:1px solid var(--line);box-shadow:none}
  .botao.pequeno{min-height:40px;padding:8px 18px;font-size:.88rem}
  .botao svg{width:16px;height:16px}
  .topo .botao{white-space:nowrap}
  @media (max-width:420px){ .topo .botao{font-size:.8rem;padding:8px 14px} .topo .marca img.w{height:16px} .topo .entrar{padding:8px 6px;font-size:.82rem} }
  @media (max-width:350px){ .topo .entrar{display:none} }
  :focus-visible{outline:2px solid var(--teal);outline-offset:3px;border-radius:8px}

  /* abertura com o mapa vivo: o mesmo logo animado da entrada do painel,
     o emblema no centro de um mapa redondo, com a rota que se desenha entre
     cinco pinos que caem um a um e as ondas de localização. */
  .abertura{text-align:center;padding:clamp(28px,6vh,64px) 0 clamp(40px,7vh,80px)}
  .mapa-marca{--tam:340px;position:relative;width:var(--tam);height:var(--tam);margin:0 auto clamp(8px,2vw,18px);animation:it-surge 1s ease both}
  .mapa-marca .halo{position:absolute;left:50%;top:50%;width:72%;height:72%;translate:-50% -50%;border-radius:50%;
    background:radial-gradient(circle,rgba(24,168,144,.22),rgba(46,124,166,.10) 45%,transparent 70%);filter:blur(12px);animation:it-halo 6s ease-in-out infinite}
  .mapa-vivo{position:absolute;inset:0;width:100%;height:100%;overflow:visible;animation:it-revela .95s cubic-bezier(.2,.85,.25,1) both}
  .mapa-vivo .mapa-disco{fill:rgba(255,255,255,.75);stroke:rgba(24,168,144,.38);stroke-width:1.5}
  .mapa-vivo .curvas{fill:none;stroke:rgba(46,124,166,.26);stroke-width:1.4;animation:it-deriva 18s ease-in-out infinite alternate}
  .mapa-vivo .grade{fill:none;stroke:rgba(0,60,96,.10);stroke-width:1;stroke-dasharray:3 6}
  .mapa-vivo .rota{fill:none;stroke:var(--gold);stroke-width:2.6;stroke-linecap:round;stroke-dasharray:1 9;opacity:.95;animation:it-rota 9s linear infinite}
  .mapa-vivo .pino{transform-box:fill-box;transform-origin:50% 100%;opacity:0;animation:it-pino .7s cubic-bezier(.3,1.5,.5,1) both}
  .mapa-vivo .pino path{fill:currentColor}
  .mapa-vivo .pino circle:not(.ping){fill:#fff}
  .mapa-vivo .pino .sombra{fill:rgba(0,40,70,.22)}
  .mapa-vivo .pino .ping{fill:none;stroke:currentColor;stroke-width:2;transform-box:fill-box;transform-origin:center;animation:it-ping 2.8s ease-out infinite}
  .mapa-vivo .pino.verde{color:var(--teal)}
  .mapa-vivo .pino.ouro{color:var(--gold)}
  .mapa-vivo .pino.azul{color:var(--blue)}
  .mapa-vivo > g:nth-of-type(2) .pino{animation-delay:.5s}
  .mapa-vivo > g:nth-of-type(3) .pino{animation-delay:.9s}
  .mapa-vivo > g:nth-of-type(4) .pino{animation-delay:1.3s}
  .mapa-vivo > g:nth-of-type(5) .pino{animation-delay:1.7s}
  .mapa-vivo > g:nth-of-type(6) .pino{animation-delay:2.1s}
  .mapa-vivo > g:nth-of-type(3) .ping{animation-delay:.9s}
  .mapa-vivo > g:nth-of-type(4) .ping{animation-delay:1.6s}
  .mapa-vivo > g:nth-of-type(5) .ping{animation-delay:2.3s}
  .mapa-vivo > g:nth-of-type(6) .ping{animation-delay:.4s}
  .mapa-marca .ondas{position:absolute;left:50%;top:50%;width:34%;height:34%;translate:-50% -50%}
  .mapa-marca .ondas span{position:absolute;inset:0;border-radius:50%;border:2px solid var(--teal);opacity:0;animation:it-onda 3.6s cubic-bezier(.2,.6,.3,1) infinite}
  .mapa-marca .ondas span:nth-child(2){animation-delay:1.2s}
  .mapa-marca .ondas span:nth-child(3){animation-delay:2.4s}
  .mapa-marca .emblema-central{position:absolute;left:50%;top:50%;width:30%;height:auto;translate:-50% -50%;
    filter:drop-shadow(0 8px 22px rgba(0,60,96,.25));animation:it-respira 5.5s ease-in-out infinite,it-surge 1.1s .15s ease both}
  @keyframes it-rota{to{stroke-dashoffset:-200}}
  @keyframes it-pino{from{opacity:0;transform:translateY(-26px) scale(.6)}to{opacity:1;transform:none}}
  @keyframes it-ping{0%{opacity:.8;transform:scale(.6)}80%,100%{opacity:0;transform:scale(3.2)}}
  @keyframes it-onda{0%{opacity:.7;transform:scale(.85)}100%{opacity:0;transform:scale(2.9)}}
  @keyframes it-deriva{from{transform:translateX(-6px)}to{transform:translateX(6px)}}
  @keyframes it-halo{0%,100%{opacity:.65;scale:1}50%{opacity:1;scale:1.08}}
  @keyframes it-revela{from{scale:.55;opacity:0}to{scale:1;opacity:1}}
  @keyframes it-respira{0%,100%{scale:1}50%{scale:1.045}}
  @keyframes it-surge{from{opacity:0}to{opacity:1}}

  .eyebrow{font-size:.74rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--teal-d)}
  h1{font-weight:800;font-size:clamp(2rem,5vw,3.3rem);line-height:1.08;letter-spacing:-.025em;color:var(--navy);margin:14px auto 0;max-width:18ch}
  .hl{background:linear-gradient(95deg,var(--navy) 5%,var(--teal-d) 55%,var(--teal));-webkit-background-clip:text;background-clip:text;color:transparent}
  .lead{margin:18px auto 0;max-width:46ch;font-size:clamp(1rem,2.1vw,1.18rem);font-weight:500;color:var(--muted)}
  .acoes{margin-top:28px;display:flex;flex-wrap:wrap;gap:12px;justify-content:center}

  /* seções */
  section.bloco{padding:clamp(48px,8vh,88px) 0;border-top:1px solid var(--line)}
  .cab{max-width:640px;margin-bottom:clamp(24px,4vw,40px)}
  .cab h2{font-weight:800;font-size:clamp(1.55rem,3.4vw,2.2rem);line-height:1.15;letter-spacing:-.02em;color:var(--navy);margin-top:8px}
  .cab p{margin-top:12px;color:var(--muted);font-weight:500}
  .grade{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr))}
  .cartao{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:22px 22px 24px;box-shadow:0 18px 40px -30px rgba(0,60,96,.35)}
  .cartao .ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(24,168,144,.14),rgba(46,124,166,.14));color:var(--teal-d)}
  .cartao .ic svg{width:22px;height:22px}
  .cartao h3{font-size:1.06rem;font-weight:700;color:var(--navy);margin-top:14px}
  .cartao p{margin-top:8px;color:var(--muted);font-size:.95rem}

  .duas{display:grid;gap:clamp(20px,4vw,48px);grid-template-columns:1fr 1fr;align-items:center}
  .lista{list-style:none;display:grid;gap:14px}
  .lista li{display:flex;gap:12px;align-items:flex-start}
  .lista li b{color:var(--navy)}
  .lista li i{flex:none;width:24px;height:24px;border-radius:50%;background:var(--teal);color:#fff;display:grid;place-items:center;font-style:normal;font-size:.8rem;font-weight:700;margin-top:2px}
  .compara{display:grid;gap:12px}
  .compara div{border-radius:18px;padding:18px 20px;border:1px solid var(--line);background:#fff}
  .compara .antes{background:rgba(255,255,255,.55);color:var(--muted)}
  .compara h3{font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:var(--faint)}
  .compara .depois h3{color:var(--teal-d)}
  .compara p{margin-top:6px;font-weight:500}

  .faixa{background:linear-gradient(120deg,var(--navy),#0C4F6E 55%,var(--teal-d));color:#fff;border-radius:28px;padding:clamp(28px,5vw,48px);
    display:grid;gap:18px;grid-template-columns:1fr auto;align-items:center}
  .faixa h2{font-weight:800;font-size:clamp(1.4rem,3vw,2rem);line-height:1.2}
  .faixa p{color:rgba(255,255,255,.82);margin-top:8px;max-width:52ch}
  .faixa .botao{background:#fff;color:var(--navy);box-shadow:none}

  /* contato e rodapé, como no site do EventCity */
  .contato{padding:clamp(48px,8vh,88px) 0 40px;border-top:1px solid var(--line)}
  .contato .wrap{display:grid;grid-template-columns:1.1fr 1fr;gap:40px;align-items:end}
  .contato h2{font-weight:800;font-size:clamp(1.55rem,3.4vw,2.2rem);line-height:1.15;letter-spacing:-.02em;color:var(--navy);margin:10px 0 26px}
  .contato .acoes{justify-content:flex-start;margin-top:0}
  .colunas{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px;font-size:.88rem}
  .colunas b{display:block;font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--faint);margin-bottom:12px}
  .colunas a,.colunas span,.colunas button{display:block;text-decoration:none;color:var(--ink);margin-bottom:8px;background:none;border:0;padding:0;font:inherit;cursor:pointer;text-align:left}
  .colunas a:hover,.colunas button:hover{text-decoration:underline;text-underline-offset:3px}
  .colunas span{color:var(--faint)}
  footer{padding:0 0 calc(28px + env(safe-area-inset-bottom));color:var(--faint);font-size:.8rem}
  footer .wrap{display:flex;flex-wrap:wrap;gap:16px 28px;align-items:flex-end;justify-content:space-between;border-top:1px solid var(--line);padding-top:26px}
  footer .marca{display:flex;align-items:center;gap:10px}
  footer .marca img.e{width:28px;height:auto}
  footer .marca img.w{height:17px;width:auto}
  footer small{display:block;font-size:.76rem;font-weight:600;margin-top:8px;color:var(--muted)}
  footer a{color:var(--muted);text-decoration:none}
  footer a:hover{color:var(--navy);text-decoration:underline}

  /* Fale conosco: modal largo, em grade, cabe na tela sem rolar. Mesmo
     desenho do site do EventCity, nas cores do InvenTour. */
  dialog.contato-modal{margin:auto;width:min(760px,calc(100vw - 32px));max-height:calc(100dvh - 32px);padding:0;border:0;border-radius:24px;background:#fff;color:var(--ink);
    box-shadow:0 40px 120px -20px rgba(0,30,50,.5);overflow:auto;scrollbar-width:none}
  dialog.contato-modal::-webkit-scrollbar{display:none}
  dialog.contato-modal::backdrop{background:rgba(0,30,50,.5);backdrop-filter:blur(6px)}
  .contato-form{padding:22px 28px 22px}
  .contato-cab{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
  .contato-cab h2{font-size:1.4rem;font-weight:800;color:var(--navy)}
  .fechar{border:0;background:transparent;font-size:1.8rem;line-height:1;color:var(--faint);cursor:pointer;width:44px;height:44px;margin:-10px -12px 0 0;border-radius:12px}
  .fechar:hover{color:var(--navy);background:rgba(0,60,96,.05)}
  .contato-sub{color:var(--muted);font-size:.93rem;margin:2px 0 10px}
  .campos{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px}
  .campos .c{display:flex;flex-direction:column;gap:4px;min-width:0}
  .campos .inteiro{grid-column:1 / -1}
  .contato-form label,.contato-form .rot{font-size:.8rem;font-weight:600;color:var(--navy)}
  .contato-form input,.contato-form textarea{width:100%;font:inherit;font-size:16px;color:var(--ink);border:1px solid rgba(0,60,96,.2);border-radius:12px;padding:9px 12px;background:#F7FAFA}
  .contato-form textarea{resize:vertical;min-height:70px}
  .contato-form input:focus,.contato-form textarea:focus{outline:2px solid rgba(24,168,144,.5);border-color:var(--teal);background:#fff}
  /* Escolhas em pílulas, sem lista nativa do navegador. */
  .pilulas{display:flex;flex-wrap:wrap;gap:6px}
  .pilulas label{position:relative;display:inline-flex;align-items:center;min-height:36px;padding:6px 14px;border:1px solid rgba(0,60,96,.18);border-radius:999px;
    background:#fff;color:var(--ink);font-size:.84rem;font-weight:500;cursor:pointer;transition:border-color .15s,background .15s}
  .pilulas label:hover{border-color:var(--teal)}
  .pilulas input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
  .pilulas label:has(input:checked){background:rgba(24,168,144,.12);border-color:var(--teal);color:var(--teal-d);font-weight:600}
  .pilulas label:has(input:focus-visible){outline:2px solid var(--teal);outline-offset:2px}
  .isca{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .contato-pe{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;margin-top:16px}
  .captcha{min-height:78px}
  .contato-pe .lado{display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex:1;min-width:220px}
  .recado{font-size:.9rem;font-weight:600;color:#B42318;padding:.5rem .8rem;border-radius:12px;background:#FDECEC}
  .recado.bom{color:var(--teal-d);background:rgba(24,168,144,.1)}
  .contato-acoes{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
  .lgpd{font-size:.76rem;color:var(--faint);text-align:right}
  @media (max-width:640px){
    dialog.contato-modal{width:100vw;max-width:100vw;height:100dvh;max-height:100dvh;border-radius:0;scrollbar-width:auto}
    .contato-form{padding:calc(16px + env(safe-area-inset-top)) 18px calc(18px + env(safe-area-inset-bottom))}
    .campos{grid-template-columns:1fr}
    .contato-pe .lado{align-items:stretch} .lgpd{text-align:left}
    .contato-acoes .botao{flex:1}
  }

  .surge{opacity:0;transform:translateY(18px);transition:opacity .7s ease,transform .7s ease}
  .surge.visto{opacity:1;transform:none}

  @media (max-width:860px){ .duas,.faixa,.contato .wrap{grid-template-columns:1fr} }
  @media (max-width:560px){
    .mapa-marca{--tam:270px}
    .colunas{grid-template-columns:1fr 1fr}
  }
  @media (prefers-reduced-motion:reduce){
    *{animation:none !important;transition:none !important;scroll-behavior:auto !important}
    .mapa-vivo .pino{opacity:1}
    .mapa-marca .ondas span:first-child{opacity:.4;transform:scale(1.6)}
    .surge{opacity:1;transform:none}
  }

  /* problema */
  .dores{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(min(100%,250px),1fr))}
  .dor{border-left:3px solid var(--gold);background:rgba(255,255,255,.7);border-radius:0 16px 16px 0;padding:16px 18px}
  .dor b{display:block;color:var(--navy);font-size:1rem}
  .dor span{display:block;margin-top:6px;color:var(--muted);font-size:.93rem}

  /* camadas */
  .camadas{display:grid;gap:16px;grid-template-columns:repeat(3,1fr)}
  .camada{position:relative;background:#fff;border:1px solid var(--line);border-radius:22px;padding:26px 22px 24px;box-shadow:0 18px 40px -30px rgba(0,60,96,.35)}
  .camada .n{font-size:.72rem;font-weight:800;letter-spacing:.16em;color:var(--teal-d);text-transform:uppercase}
  .camada h3{font-size:1.18rem;font-weight:800;color:var(--navy);margin-top:6px}
  .camada p{margin-top:10px;color:var(--muted);font-size:.95rem}
  .camada ul{list-style:none;margin-top:14px;display:grid;gap:8px}
  .camada li{display:flex;gap:9px;font-size:.92rem;color:var(--ink)}
  .camada li::before{content:"";flex:none;width:7px;height:7px;border-radius:50%;background:var(--teal);margin-top:.55em}
  .fluxo{margin-top:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;font-size:.86rem;font-weight:600;color:var(--navy)}
  .fluxo span{background:#fff;border:1px solid var(--line);border-radius:999px;padding:7px 14px}
  .fluxo i{font-style:normal;color:var(--teal)}

  @media (max-width:900px){ .camadas{grid-template-columns:1fr} }
</style>
</head>
<body>
<header class="topo" id="topo">
  <div class="wrap">
    <a class="marca" href="/" aria-label="InvenTour, início"><img class="e" src="/assets/icone.svg" alt="" width="30" height="30"><img class="w" src="/assets/inventour.webp" alt="InvenTour" width="107" height="19"></a>
    <div class="dir">
      <a class="entrar" href="https://app.inventour.com.br/login" rel="noopener">Entrar</a>
      <button type="button" class="botao pequeno" data-abrir-contato>Fale conosco</button>
    </div>
  </div>
</header>

<main>
  <section class="abertura">
    <div class="wrap">
      <div class="mapa-marca" aria-hidden="true">
        <div class="halo"></div>
        <svg class="mapa-vivo" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
          <defs><clipPath id="mapa-redondo"><circle cx="200" cy="200" r="188"/></clipPath></defs>
          <circle class="mapa-disco" cx="200" cy="200" r="188"/>
          <g clip-path="url(#mapa-redondo)"><g class="curvas"><path d="M-20 90C60 60 120 120 200 96S330 40 420 70"/><path d="M-20 150C70 120 130 180 210 156S340 100 420 132"/><path d="M-20 262C80 236 140 296 220 270S350 214 420 246"/><path d="M-20 322C70 300 150 352 230 330S360 282 420 306"/><ellipse cx="300" cy="190" rx="54" ry="34"/><ellipse cx="300" cy="190" rx="30" ry="17"/><ellipse cx="110" cy="205" rx="40" ry="26"/></g><g class="grade"><path d="M0 200H400M200 0V400"/><circle cx="200" cy="200" r="120"/></g></g>
          <path class="rota" d="M96 128C160 70 236 70 298 92S352 190 334 246 230 330 150 312 50 290 62 236"/>
          <g transform="translate(96 128)"><g class="pino verde"><ellipse class="sombra" cx="0" cy="1" rx="7" ry="2.4"/><path d="M0 0C-6-9-11-14-11-21a11 11 0 1 1 22 0c0 7-5 12-11 21z"/><circle cx="0" cy="-21" r="4.2"/><circle class="ping" cx="0" cy="0" r="6"/></g></g>
          <g transform="translate(298 92)"><g class="pino ouro"><ellipse class="sombra" cx="0" cy="1" rx="7" ry="2.4"/><path d="M0 0C-6-9-11-14-11-21a11 11 0 1 1 22 0c0 7-5 12-11 21z"/><circle cx="0" cy="-21" r="4.2"/><circle class="ping" cx="0" cy="0" r="6"/></g></g>
          <g transform="translate(334 246)"><g class="pino azul"><ellipse class="sombra" cx="0" cy="1" rx="7" ry="2.4"/><path d="M0 0C-6-9-11-14-11-21a11 11 0 1 1 22 0c0 7-5 12-11 21z"/><circle cx="0" cy="-21" r="4.2"/><circle class="ping" cx="0" cy="0" r="6"/></g></g>
          <g transform="translate(150 312)"><g class="pino verde"><ellipse class="sombra" cx="0" cy="1" rx="7" ry="2.4"/><path d="M0 0C-6-9-11-14-11-21a11 11 0 1 1 22 0c0 7-5 12-11 21z"/><circle cx="0" cy="-21" r="4.2"/><circle class="ping" cx="0" cy="0" r="6"/></g></g>
          <g transform="translate(62 236)"><g class="pino ouro"><ellipse class="sombra" cx="0" cy="1" rx="7" ry="2.4"/><path d="M0 0C-6-9-11-14-11-21a11 11 0 1 1 22 0c0 7-5 12-11 21z"/><circle cx="0" cy="-21" r="4.2"/><circle class="ping" cx="0" cy="0" r="6"/></g></g>
        </svg>
        <div class="ondas"><span></span><span></span><span></span></div>
        <img class="emblema-central" src="/assets/icone.svg" alt="" width="102" height="102">
      </div>
      <p class="eyebrow">Inteligência em informações turísticas</p>
      <h1>O inventário turístico que <span class="hl">não morre no dia seguinte</span>.</h1>
      <p class="lead">O InvenTour transforma o Inventário da Oferta Turística em dado vivo: coletado em campo, mantido pelo próprio trade, homologado pelo município, publicado para o turista e medido em tempo real.</p>
      <div class="acoes">
        <button type="button" class="botao" data-abrir-contato data-assunto="demonstracao">Agendar demonstração
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        <a class="botao claro" href="#como-funciona">Como funciona</a>
      </div>
    </div>
  </section>

  <section class="bloco">
    <div class="wrap">
      <div class="cab surge">
        <p class="eyebrow">O apagão de dados do turismo</p>
        <h2>O município paga pelo inventário, recebe um PDF e os dados começam a envelhecer.</h2>
        <p>Empresas fecham, hotéis novos abrem, o número de leitos muda. É a falta que os diagnósticos de Destino Turístico Inteligente apontam: tecnologia para gerir a oferta e ligá-la ao visitante.</p>
      </div>
      <div class="dores">
        <div class="dor surge"><b>Relatório estático</b><span>Word ou PDF, entregue uma vez e desatualizado logo depois.</span></div>
        <div class="dor surge"><b>Recomeço a cada mandato</b><span>Arquivos se perdem na troca de gestão e o mapeamento recomeça do zero.</span></div>
        <div class="dor surge"><b>Trade invisível</b><span>Hotéis, restaurantes e atrativos não aparecem, ou aparecem com dado errado.</span></div>
        <div class="dor surge"><b>Turista frustrado</b><span>Telefone que não atende, endereço que mudou, lugar que já fechou.</span></div>
        <div class="dor surge"><b>Site fora do ar na eleição</b><span>Sites municipais saem do ar por restrição legal e a promoção do destino para.</span></div>
      </div>
    </div>
  </section>

  <section class="bloco" id="como-funciona">
    <div class="wrap">
      <div class="cab surge">
        <p class="eyebrow">Como funciona</p>
        <h2>Uma plataforma, três camadas: da coleta em campo ao comportamento do visitante.</h2>
      </div>
      <div class="camadas">
        <article class="camada surge">
          <span class="n">Camada 1</span>
          <h3>Back-office técnico</h3>
          <p>O trabalho de campo do turismólogo, digital e na nuvem.</p>
          <ul>
            <li>As 54 fichas do Inventário da Oferta Turística do MTur, item a item</li>
            <li>App de campo que funciona sem sinal, com GPS, mapa e fotos</li>
            <li>O trade cadastra e atualiza os próprios dados</li>
            <li>O município confere e homologa cada registro</li>
            <li>Caderno oficial do inventário em PDF, com QR de verificação</li>
          </ul>
        </article>
        <article class="camada surge">
          <span class="n">Camada 2</span>
          <h3>Web app oficial do destino</h3>
          <p>O portal de turismo da cidade, só com a oferta validada.</p>
          <ul>
            <li>Abre na hora, pelo navegador, sem instalar nada</li>
            <li>Onde dormir, onde comer, o que fazer e serviços, com mapa</li>
            <li>Roteiros da equipe e o roteiro montado pelo próprio visitante</li>
            <li>Endereço próprio: cidade.inventour.com.br</li>
            <li><span>Agenda de eventos do <a href="https://eventcity.com.br" target="_blank" rel="noopener">EventCity</a> integrada</span></li>
          </ul>
        </article>
        <article class="camada surge">
          <span class="n">Camada 3</span>
          <h3>Phygital e inteligência</h3>
          <p>QR Codes pela cidade que ligam o físico ao digital, e os números que saem disso.</p>
          <ul>
            <li>QR Codes urbanos com cartaz pronto e contagem de leituras</li>
            <li>Relatórios mensais e radar do que está ficando desatualizado</li>
            <li>Leitos do destino cruzados com a agenda de eventos</li>
            <li>Diagnóstico de Destino Turístico Inteligente pela metodologia do MTur</li>
          </ul>
        </article>
      </div>
      <div class="fluxo surge" aria-label="O caminho do dado">
        <span>Coleta em campo</span><i>&rarr;</i><span>Trade atualiza</span><i>&rarr;</i><span>Município homologa</span><i>&rarr;</i><span>Portal publica</span><i>&rarr;</i><span>QR Code mede</span>
      </div>
    </div>
  </section>

  <section class="bloco">
    <div class="wrap">
      <div class="cab surge">
        <p class="eyebrow">O que muda</p>
        <h2>Feito para o destino continuar sabendo o que tem, gestão após gestão.</h2>
      </div>
      <div class="grade">
        <article class="cartao surge">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg></span>
          <h3>Continuidade garantida</h3>
          <p>A guarda dos dados fica com o COMTUR, órgão permanente do destino. O portal não depende do mandato e segue no ar nos anos eleitorais.</p>
        </article>
        <article class="cartao surge">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M8 7h8M8 11h6"/></svg></span>
          <h3>Do campo ao caderno oficial</h3>
          <p>Turismólogos e consultores fazem o inventário na plataforma, com licença de coleta gratuita, e entregam ao município o caderno oficial gerado por ela. O dado não se perde depois da entrega.</p>
        </article>
        <article class="cartao surge">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 11h5M18.5 8.5v5"/></svg></span>
          <h3>Alimentação distribuída</h3>
          <p>Cada hotel, restaurante e atrativo pede a responsabilidade pelo próprio lugar e mantém o cadastro em dia. O município só modera: o inventário se atualiza sozinho.</p>
        </article>
        <article class="cartao surge">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 7.5l3 8M16 7.5l-3 8M8.5 6h7"/></svg></span>
          <h3>Um destino ou uma região</h3>
          <p>Cada município tem o seu espaço, o seu endereço e o seu mapa, e a plataforma consolida os portais regionais das Instâncias de Governança Regional.</p>
        </article>
        <article class="cartao surge">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/><circle cx="18" cy="17" r="3"/></svg></span>
          <h3>Ligado aos dados oficiais</h3>
          <p>No padrão do Inventário da Oferta Turística do Ministério do Turismo, com Cadastur, Mapa do Turismo Brasileiro e a divisa do município pelo IBGE na mesma base.</p>
        </article>
        <article class="cartao surge">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span>
          <h3>Gestão com evidência</h3>
          <p>O que o visitante procura, por onde passa e o que abre, mais o diagnóstico de Destino Turístico Inteligente: dados para o plano municipal, para o COMTUR e para a jornada DTI.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="bloco" id="para-quem">
    <div class="wrap duas">
      <div class="surge">
        <div class="cab">
          <p class="eyebrow">Para quem</p>
          <h2>Cada um cuida da sua parte, e todos ganham com o dado em dia.</h2>
        </div>
        <ul class="lista">
          <li><i>1</i><span><b>Prefeituras e COMTUR:</b> o inventário vivo, o portal oficial do destino e os relatórios de demanda, por assinatura anual.</span></li>
          <li><i>2</i><span><b>Turismólogos e consultores:</b> a coleta de campo digital, com o caderno oficial pronto para entregar.</span></li>
          <li><i>3</i><span><b>Trade turístico:</b> visibilidade com informação certa, atualizada pelo próprio negócio.</span></li>
          <li><i>4</i><span><b>Visitante:</b> informação conferida pelo destino, no celular, na hora.</span></li>
        </ul>
      </div>
      <div class="compara surge">
        <div class="antes"><h3>Inventário tradicional</h3><p>Relatório fechado, feito de tempos em tempos, guardado num arquivo que se perde na troca de gestão.</p></div>
        <div class="depois"><h3>Com o InvenTour</h3><p>Base viva e homologada, publicada para o turista, medida pelos QR Codes e guardada pelo COMTUR.</p></div>
      </div>
    </div>
  </section>

  <section class="bloco">
    <div class="wrap">
      <div class="faixa surge">
        <div>
          <h2>Quer levar o InvenTour para o seu destino?</h2>
          <p>Prefeitura, COMTUR, IGR, turismólogo ou consultor: conte quem você é e marcamos uma conversa com a demonstração.</p>
        </div>
        <button type="button" class="botao" data-abrir-contato data-assunto="demonstracao">Agendar demonstração</button>
      </div>
    </div>
  </section>

  <section class="contato">
    <div class="wrap">
      <div class="surge">
        <p class="eyebrow">Contato</p>
        <h2>Leve o InvenTour para o seu destino.</h2>
        <div class="acoes">
          <button type="button" class="botao" data-abrir-contato data-assunto="demonstracao">Agendar demonstração</button>
          <a class="botao claro" href="https://app.inventour.com.br/login" rel="noopener">Entrar no painel</a>
        </div>
      </div>
      <nav class="colunas" aria-label="Rodapé">
        <div><b>Plataforma</b><a href="#como-funciona">Como funciona</a><a href="#para-quem">Para quem</a></div>
        <div><b>Acesso</b><a href="https://app.inventour.com.br/login" rel="noopener">Entrar</a><a href="https://app.inventour.com.br/privacidade" target="_blank" rel="noopener">Privacidade</a><button type="button" data-abrir-contato data-assunto="outro">Fale conosco</button></div>
        <div><b>Autoria</b><a href="https://softsul.com" target="_blank" rel="noopener">Softsul Software</a><a href="https://azapping.com.br" target="_blank" rel="noopener">Zapping Consultoria</a><span>Paraná, Brasil</span></div>
      </nav>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div><span class="marca"><img class="e" src="/assets/icone.svg" alt="" width="28" height="28"><img class="w" src="/assets/inventour.webp" alt="InvenTour" width="96" height="17"></span><small>O inventário vivo do seu destino.</small></div>
    <span>Da mesma equipe do <a href="https://eventcity.com.br" target="_blank" rel="noopener">EventCity</a>, a agenda oficial de eventos do destino.</span>
    <span>Produto <a href="https://softsul.com" target="_blank" rel="noopener">Softsul Software</a> e <a href="https://azapping.com.br" target="_blank" rel="noopener">Zapping Consultoria</a> · © 2026 InvenTour</span>
  </div>
</footer>

<dialog class="contato-modal" id="modal-contato" aria-labelledby="contato-titulo">
  <form class="contato-form" novalidate>
    <div class="contato-cab"><h2 id="contato-titulo">Fale conosco</h2><button type="button" class="fechar" data-fechar-contato aria-label="Fechar">&times;</button></div>
    <p class="contato-sub">Conte quem você é e o que precisa. Respondemos no e-mail que você informar.</p>
    <div class="campos">
      <div class="c inteiro"><span class="rot">Assunto</span>
        <div class="pilulas" role="radiogroup" aria-label="Assunto">
          <label><input type="radio" name="assunto" value="demonstracao" checked>Quero uma demonstração</label>
          <label><input type="radio" name="assunto" value="outro">Outro assunto</label>
        </div></div>
      <div class="c"><label for="contato-nome">Nome</label><input id="contato-nome" name="nome" autocomplete="name" maxlength="120" required></div>
      <div class="c"><label for="contato-email">E-mail</label><input id="contato-email" name="email" type="email" autocomplete="email" maxlength="160" required></div>
      <div class="c"><label for="contato-fone">WhatsApp</label><input id="contato-fone" name="fone" type="tel" autocomplete="tel" maxlength="20" placeholder="(46) 99999-9999"></div>
      <div class="c"><label for="contato-cidade">Cidade e UF</label><input id="contato-cidade" name="cidade" maxlength="80" placeholder="Pato Branco, PR"></div>
      <div class="c inteiro"><label for="contato-orgao">Órgão ou empresa</label><input id="contato-orgao" name="orgao" maxlength="120" placeholder="Prefeitura, conselho, IGR, consultoria, hotel"></div>
      <div class="c inteiro"><span class="rot">Você é</span>
        <div class="pilulas" role="radiogroup" aria-label="Você é">
          <label><input type="radio" name="perfil" value="Prefeitura ou secretaria de turismo" checked>Prefeitura</label>
          <label><input type="radio" name="perfil" value="COMTUR ou conselho">COMTUR</label>
          <label><input type="radio" name="perfil" value="Instância de Governança Regional (IGR)">IGR</label>
          <label><input type="radio" name="perfil" value="Turismólogo ou consultor">Turismólogo ou consultor</label>
          <label><input type="radio" name="perfil" value="Trade turístico">Trade turístico</label>
          <label><input type="radio" name="perfil" value="Outro">Outro</label>
        </div></div>
      <div class="c inteiro"><label for="contato-recado">Mensagem (opcional)</label><textarea id="contato-recado" name="recado" rows="3" maxlength="3000" placeholder="O que vocês querem resolver no turismo da cidade"></textarea></div>
    </div>
    <input type="hidden" name="mensagem" value=""><input type="hidden" name="pagina" value="https://inventour.com.br/">
    <div class="isca" aria-hidden="true"><label for="contato-site">Não preencha</label><input id="contato-site" name="site" tabindex="-1" autocomplete="off"></div>
    <div class="contato-pe">
      <div class="captcha"></div>
      <div class="lado">
        <p class="recado" role="alert" hidden></p>
        <div class="contato-acoes"><button type="button" class="botao claro" data-fechar-contato>Cancelar</button><button type="submit" class="botao enviar">Enviar</button></div>
        <p class="lgpd">Usamos o seu contato só para responder e, se for o caso, marcar a demonstração.</p>
      </div>
    </div>
  </form>
</dialog>
<script>
(function () {
  'use strict';
  var topo = document.getElementById('topo');
  window.addEventListener('scroll', function () { topo.classList.toggle('rolou', window.scrollY > 10); }, { passive: true });

  // Entrada suave dos blocos ao rolar.
  var surgem = document.querySelectorAll('.surge');
  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (itens) {
      itens.forEach(function (it) { if (it.isIntersecting) { it.target.classList.add('visto'); obs.unobserve(it.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
    surgem.forEach(function (s) { obs.observe(s); });
  } else { surgem.forEach(function (s) { s.classList.add('visto'); }); }

  // Fale conosco: modal com reCAPTCHA que posta no contato do próprio
  // InvenTour (app.inventour.com.br/api/contato), como o site do EventCity.
  // Nada de mailto.
  var CHAVE = '6LcJRCwuAAAAAE2arTBJHemZd8th8zxPbus7UL1N', API = 'https://app.inventour.com.br/api/contato';
  var modal = document.getElementById('modal-contato'), form = modal.querySelector('form'), caixa = modal.querySelector('.captcha'),
      recado = modal.querySelector('.recado'), enviar = modal.querySelector('.enviar'), widget = null, pedido = false;
  function avisar(t, bom) { recado.textContent = t; recado.hidden = !t; recado.classList.toggle('bom', !!bom); }
  window.itRecaptchaPronto = function () { if (widget === null && window.grecaptcha) { widget = grecaptcha.render(caixa, { sitekey: CHAVE }); } };
  function carregar() {
    if (pedido) { return; } pedido = true;
    var s = document.createElement('script'); s.async = true; s.src = 'https://www.google.com/recaptcha/api.js?onload=itRecaptchaPronto&render=explicit&hl=pt-BR';
    s.onerror = function () { pedido = false; avisar('Não conseguimos carregar a verificação "Não sou um robô". Tente de novo.'); };
    document.head.appendChild(s);
  }
  function abrirContato(assunto) {
    form.reset(); avisar(''); if (widget !== null) { grecaptcha.reset(widget); }
    var op = form.querySelector('input[name=assunto][value="' + (assunto || 'demonstracao') + '"]');
    if (op) { op.checked = true; }
    modal.showModal(); form.nome.focus(); carregar();
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-abrir-contato]');
    if (b) { abrirContato(b.getAttribute('data-assunto')); }
    else if (ev.target.closest('[data-fechar-contato]')) { modal.close(); }
  });
  // Clique no fundo escurecido fecha.
  modal.addEventListener('click', function (ev) { if (ev.target === modal) { modal.close(); } });
  // Enter passa para o próximo campo; na mensagem, quebra linha.
  form.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' || ev.target.tagName === 'TEXTAREA' || ev.target.type === 'submit') { return; }
    var campos = Array.prototype.filter.call(form.querySelectorAll('input:not([type=hidden]):not([type=radio]):not([tabindex="-1"]),textarea'), function (c) { return c.offsetParent; });
    var i = campos.indexOf(ev.target);
    if (i >= 0 && i < campos.length - 1) { ev.preventDefault(); campos[i + 1].focus(); }
    else if (ev.target.type === 'radio') { ev.preventDefault(); }
  });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!form.nome.value.trim()) { avisar('Digite o seu nome.'); form.nome.focus(); return; }
    if (!form.email.value || !form.email.checkValidity()) { avisar('Digite um e-mail válido.'); form.email.focus(); return; }
    var assunto = form.assunto.value, texto = form.recado.value.trim();
    if (assunto === 'outro' && texto.length < 10) { avisar('Escreva a sua mensagem.'); form.recado.focus(); return; }
    if (widget !== null && !grecaptcha.getResponse(widget)) { avisar('Marque a caixa "Não sou um robô".'); return; }
    form.mensagem.value = (assunto === 'demonstracao' ? 'Pedido de demonstração pelo site.' : 'Mensagem pelo Fale conosco do site.')
      + '\nPerfil: ' + form.perfil.value + '\nÓrgão ou empresa: ' + (form.orgao.value.trim() || 'não informado')
      + '\nCidade: ' + (form.cidade.value.trim() || 'não informada') + '\nWhatsApp: ' + (form.fone.value.trim() || 'não informado')
      + (texto ? '\n\n' + texto : '');
    enviar.disabled = true; avisar('Enviando...', true);
    fetch(API, { method: 'POST', body: new URLSearchParams(new FormData(form)) })
      .then(function (r) { return r.json().catch(function () { return { erro: r.status === 429 ? 'Muitos pedidos em pouco tempo. Tente mais tarde.' : 'Não conseguimos enviar agora.' }; }); })
      .then(function (r) {
        enviar.disabled = false;
        if (r && r.ok) {
          form.reset();
          avisar(assunto === 'demonstracao' ? 'Pedido recebido. Entramos em contato para marcar a demonstração.' : 'Mensagem recebida. Respondemos no seu e-mail.', true);
        } else { avisar((r && r.erro) || 'Não conseguimos enviar agora.'); }
        if (widget !== null) { grecaptcha.reset(widget); }
      })
      .catch(function () { enviar.disabled = false; avisar('Sem conexão. Tente de novo.'); });
  });
})();
</script>
</body>
</html>

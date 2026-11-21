<?php
/**
 * O texto da política de privacidade, o mesmo no portal (/privacidade) e
 * no painel (/privacidade, sem login).
 * Softsul Software
 *
 * Escrito a partir do que o sistema de fato guarda e por quanto tempo
 * (faxina diária em scripts/processar_jobs.php). Texto inicial da fundação
 * do InvenTour, para a revisão jurídica do destino e da Zapping: cada
 * módulo novo que trate dado pessoal (coleta de campo, fotos, contato do
 * prestador) acrescenta aqui o que guarda e por quanto tempo. Quem fala
 * com o titular é o Fale conosco, nunca um mailto. Sem nomes de
 * fornecedor nem de tecnologia (Luiz, 21/11/2026): a política diz o que se
 * faz com o dado, não com que ferramenta, para não entregar o mapa da
 * infraestrutura a quem procura brecha.
 *
 * @var string $quem       o destino (no portal) ou "cada destino da plataforma InvenTour"
 * @var bool   $semContato no painel não há o modal do Fale conosco
 */
?>
<div class="texto-legal">
  <p>Esta política explica que dados o InvenTour trata quando você usa o inventário turístico oficial de <?= e($quem) ?>, para que, por quanto tempo e como exercer os seus direitos pela Lei Geral de Proteção de Dados (Lei 13.709/2018).</p>

  <h2>Quem é quem</h2>
  <p>O inventário é publicado pelo destino (prefeitura, secretaria ou conselho de turismo), que decide o que se faz com os dados: é o <b>controlador</b>. A Softsul Software, com a Zapping Consultoria, opera a plataforma InvenTour para o destino: é a <b>operadora</b>, e trata os dados só para fazer o inventário funcionar.</p>

  <h2>Quem só visita o portal</h2>
  <ul>
    <li><b>Sem anúncio, sem perfil e sem cookie.</b> O portal não tem ferramenta de anúncio, não grava cookie e não monta perfil de quem visita.</li>
    <li><b>Meu roteiro:</b> os lugares que você guarda ficam só no seu aparelho, no navegador. Não vão para o InvenTour, a não ser que você mesmo compartilhe o link do roteiro.</li>
    <li><b>QR Codes:</b> a leitura de um QR do destino é contada por dia, sem guardar quem leu.</li>
  </ul>

  <h2>Quando você fala com a gente</h2>
  <ul>
    <li><b>Fale conosco:</b> nome, e-mail e mensagem vão por e-mail a quem atende, para responder. A verificação "Não sou um robô" é feita por um serviço especializado e só carrega quando você abre o formulário.</li>
    <li><b>Recursos com IA:</b> desligados por padrão. Se o destino colocar o token próprio, o texto que a pessoa pedir para melhorar é enviado ao provedor de inteligência artificial escolhido pelo destino, na conta do próprio destino. Nada é enviado sem esse token.</li>
  </ul>

  <h2>Quem usa o painel</h2>
  <ul>
    <li><b>Equipe do destino, pesquisadores e prestadores:</b> nome, e-mail, telefone e, quando o número tem WhatsApp, a foto do perfil, para identificar a pessoa no painel.</li>
    <li><b>Segurança:</b> as tentativas de entrada (e-mail, IP e horário) ficam dois dias, para barrar quem tenta adivinhar senha. O registro de auditoria guarda quem fez o quê e quando, pelo tempo que o destino precisar para prestar contas.</li>
    <li><b>Avisos:</b> por e-mail, só sobre a sua própria atividade no inventário. Endereço que devolve ou marca como spam deixa de receber.</li>
  </ul>

  <h2>Dados públicos de apoio</h2>
  <p>O InvenTour consulta bases públicas do Ministério do Turismo (Cadastur e Mapa do Turismo Brasileiro) e do IBGE para ajudar a montar o inventário. O CPF de prestador pessoa física aparece mascarado no painel e nunca no portal.</p>

  <h2>Onde ficam os dados</h2>
  <p>Em provedores de nuvem contratados pela Softsul, que podem manter servidores fora do Brasil. Nesse caso, a transferência internacional segue garantias contratuais de proteção de dados (artigo 33 da LGPD). A conexão é sempre cifrada, as senhas são guardadas de forma irreversível e as chaves de integração ficam protegidas. Os dados não são vendidos nem cedidos para publicidade.</p>

  <h2>Os seus direitos</h2>
  <p id="excluir-conta"><b>Excluir a conta.</b> Quem tem conta no InvenTour pode excluí-la a qualquer momento, sozinho, em <a href="https://app.inventour.com.br/excluir-conta">Excluir minha conta</a>: depois de entrar, basta digitar EXCLUIR e confirmar. Saem o nome, e-mail, telefone, foto, senha e todos os acessos; os registros já feitos no inventário continuam com o destino, sem os dados pessoais.</p>
  <p>Você pode pedir para saber que dados seus existem, corrigir, apagar, levar para outro lugar ou revogar um consentimento. <?php if (!empty($semContato)): ?>Peça pelo Fale conosco do portal do seu destino ou do site <a href="https://inventour.com.br" target="_blank" rel="noopener">inventour.com.br</a><?php else: ?>Peça pelo <button type="button" class="como-link" data-abrir-contato>Fale conosco</button>, escolhendo o assunto "Outro assunto"<?php endif; ?>: o pedido chega ao destino e à Softsul, e respondemos no e-mail informado.</p>

  <p class="texto-legal-data">Versão de 21 de novembro de 2026. Texto inicial, sujeito à revisão jurídica do destino.</p>
</div>

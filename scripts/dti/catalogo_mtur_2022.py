# -*- coding: utf-8 -*-
"""
Catálogo da metodologia DTI Brasil 2022 (Ministério do Turismo): os 105
requisitos do Manual Metodológico, como estão no capítulo de requisitos e na
Matriz de Avaliação (Anexo 2). Softsul Software, 22/11/2026.

Gera a migração com: python3 scripts/dti/catalogo_mtur_2022.py > migracoes/039-...sql
(só o bloco de requisitos; o resto da migração é escrito à mão).

Cada requisito: número, capítulo, subcapítulo, eixo (só no 8.2), subeixo,
texto (com os itens a, b, c) e observação.
"""
import sys

CAP = {
    4: 'Contexto do destino turístico',
    5: 'Liderança',
    6: 'Planejamento',
    7: 'Apoio',
    8: 'Operação',
    9: 'Avaliação de desempenho',
    10: 'Aperfeiçoamento',
}

# (numero, capitulo, subcapitulo, eixo, subeixo, texto, observacao)
R = []
def r(n, cap, sub, texto, eixo=None, subeixo=None, obs=None):
    R.append((n, cap, sub, eixo, subeixo, texto.strip(), (obs or '').strip() or None))

S41 = '4.1 Delimitação do escopo do sistema de gestão DTI'
r(1, 4, S41, """O órgão gestor deve determinar e documentar que o alcance territorial, no qual se implanta o modelo, é um destino turístico, com base nos seguintes aspectos:
a) que o destino turístico seja geograficamente delimitado;
b) que tenha organizações que prestam serviços turísticos;
c) que esteja dotado de uma estrutura para a gestão do destino turístico.""")
r(2, 4, S41, """O órgão gestor deve determinar os limites e aplicabilidade do sistema de gestão do DTI para estabelecer seu escopo. Para isso, o órgão gestor deve considerar:
a) os limites geográficos onde está implementado o sistema de gestão do DTI;
b) a autoridade e capacidade de exercer controle e influência sobre o DTI;
c) as atividades, produtos e serviços turísticos que possam ter aspectos significativos relacionados à implementação de um DTI.""")
r(3, 4, S41, "O escopo deve estar disponível como informação documentada.")
r(4, 4, '4.2 Compreensão do órgão gestor e do seu contexto no DTI', """O órgão gestor deve determinar as questões externas e internas que são relevantes para o seu propósito e que afetam sua capacidade de alcançar os resultados pretendidos do seu sistema de gestão de DTI, considerando, pelo menos, aspectos relativos a:
a) o mercado turístico;
b) o marco legal e regulatório;
c) os aspectos relativos aos eixos estabelecidos no subcapítulo 8.2.""",
  obs="""A compreensão do contexto externo pode ser facilitada ao considerar questões decorrentes de ambientes jurídicos, tecnológicos, competitivos, econômicos e do mercado, sejam internacional, nacional, regional ou local. A do contexto interno, ao considerar questões decorrentes dos valores, da cultura, do conhecimento e desempenho do órgão gestor. O órgão gestor deve considerar a análise do contexto do DTI para identificar e gerenciar fatores positivos e negativos.""")
r(5, 4, '4.3 Compreensão das necessidades e expectativas das partes interessadas', """O órgão gestor deve determinar e documentar:
a) as partes interessadas que são relevantes para o sistema de gestão do DTI;
b) os requisitos pertinentes às partes interessadas identificadas, considerando os dados e informações que eles demandem.""",
  obs="""Exemplos de partes interessadas: autoridades locais, prefeitura, vereadores; órgãos oficiais de turismo; cidadãos; turistas; prestadores de serviços turísticos e organizações de turismo; organizações da sociedade civil; organizações de fomento; operadores de transporte público e privado; organismos de infraestrutura rodoviária e de conectividade; órgãos de segurança pública; organismos com competência laboral, sanitária, cultural, ambiental e de inovação; organizações externas ao destino, como operadoras, governos estaduais e federal.""")
r(6, 4, '4.4 Diagnóstico de situação do DTI', "O órgão gestor deve realizar um diagnóstico de situação documentado do DTI em relação aos aspectos identificados e determinados nos pontos 4.1, 4.2 e 4.3, e com os eixos identificados no ponto 8.2.")
r(7, 4, '4.5 Sistema de gestão do DTI e seus processos', """O órgão gestor deve determinar os processos necessários para o sistema de gestão do DTI, considerando:
a) as entradas necessárias e as saídas esperadas desses processos;
b) a sequência e interação desses processos.""")

r(8, 5, '5.1 Política do DTI', """As mais altas autoridades do órgão gestor devem estabelecer, implementar, manter e rever uma política de gestão do DTI que:
a) seja adequada ao contexto do DTI;
b) considere cada um dos eixos na seção 8.2;
c) forneça um marco de referência para o estabelecimento dos objetivos do DTI;
d) inclua o compromisso de cumprir os requisitos aplicáveis;
e) inclua o compromisso de fornecer os recursos necessários para atender aos objetivos;
f) inclua o compromisso de melhoria contínua do sistema de gestão do DTI.""")
r(9, 5, '5.1 Política do DTI', "A política de DTI deve estar disponível como informação documentada, e comunicar-se com as partes interessadas conforme aplicável.")
r(10, 5, '5.2 Funções, responsabilidades e autoridades do órgão gestor', "O órgão gestor deve determinar, atribuir, documentar e comunicar as responsabilidades e autoridades para funções relevantes para a gestão do DTI.",
  obs="A atribuição pode incluir: garantir que o sistema de gestão do DTI esteja em conformidade com os requisitos; coordenar a sua implementação; assegurar que os processos geram os resultados pretendidos; reportar às mais altas autoridades o desempenho e as oportunidades de melhoria.")

r(11, 6, '6.1 Ações para tratar riscos e oportunidades', """Ao planejar o sistema de gestão do DTI, o órgão gestor deve considerar as questões referidas no item 4.2 e os requisitos mencionados no item 4.3, e determinar os riscos e oportunidades que podem afetar a gestão do DTI para efeitos de:
a) garantir a realização dos resultados esperados;
b) aumentar os efeitos desejáveis e aproveitar as oportunidades;
c) prevenir ou reduzir efeitos indesejados;
d) alcançar a melhoria contínua.""")
r(12, 6, '6.1 Ações para tratar riscos e oportunidades', """O órgão gestor deve planejar e documentar:
a) as ações para lidar com riscos e oportunidades;
b) a forma de integrar e implementar as ações nos processos do sistema de gestão do DTI;
c) a forma de avaliar a eficácia das ações.""",
  obs="As opções para lidar com os riscos podem incluir evitar, aceitar para buscar oportunidades, eliminar a origem, alterar a probabilidade ou as consequências, compartilhar ou reter riscos por decisões informadas. As oportunidades podem levar a novas práticas, produtos, atrativos, perfis de turistas, parcerias e tecnologias.")
r(13, 6, '6.2.1 Objetivos de um DTI', "O órgão gestor deve definir os objetivos do DTI em funções e níveis relevantes, atribuindo-lhes responsabilidades. Objetivos vinculados a cada um dos eixos de um DTI devem ser determinados de acordo com a seção 8.2.")
r(14, 6, '6.2.1 Objetivos de um DTI', """Os objetivos do DTI devem:
a) ser consistentes com a política do DTI;
b) ser mensuráveis e limitados no tempo;
c) levar em conta os requisitos aplicáveis;
d) ser rastreados;
e) comunicar e atualizar-se, conforme apropriado.""")
r(15, 6, '6.2.1 Objetivos de um DTI', "O órgão gestor deve manter informações documentadas sobre os objetivos do DTI.")
r(16, 6, '6.2.2 Plano para alcançar os objetivos do DTI', """O órgão gestor deve planejar ações que atendam às metas estabelecidas. O órgão gestor deve determinar para cada ação contida no plano:
a) iniciativas a serem tomadas;
b) forma específica em que serão realizados;
c) recursos que serão necessários;
d) responsabilidades e autoridades para sua realização;
e) datas e prazos para execução;
f) método e frequência de monitoramento.""")

r(17, 7, '7.1 Recursos', "O órgão gestor deve determinar e fornecer os recursos necessários para o estabelecimento, implementação, manutenção e melhoria contínua do sistema de gestão do DTI.")
r(18, 7, '7.1.1 Pessoas', "A organização deve determinar e fornecer as pessoas necessárias para uma implementação eficaz do seu sistema de gestão do DTI e pela operação e controle de seus processos.")
r(19, 7, '7.1.2 Infraestrutura', "O órgão gestor deve determinar, fornecer e manter a infraestrutura necessária para a operação de seus processos e dos sistemas de gestão do DTI.",
  obs="A infraestrutura pode incluir edifícios e serviços associados; equipamentos, incluindo hardware e software; recursos de transporte; TIC.")
r(20, 7, '7.1.3 Conhecimentos do órgão gestor', "O órgão gestor deve determinar e manter o conhecimento necessário para atingir os objetivos do DTI, o funcionamento de seus processos e a conformidade dos requisitos.",
  obs="São conhecimentos específicos que a organização adquire geralmente com a experiência, de fontes internas (lições aprendidas, resultados de melhorias) e externas (normas, academia, conferências, artigos técnicos, partes interessadas).")
r(21, 7, '7.1.3 Conhecimentos do órgão gestor', "Estes conhecimentos devem ser colocados à disposição das partes interessadas sempre que possível.")
r(22, 7, '7.2 Competências', """O órgão gestor deve:
a) determinar a competência necessária das pessoas que realizam, nas áreas e níveis relevantes, um trabalho que afeta o desempenho do DTI. Para isso, devem ser considerados os eixos de 8.2;
b) garantir a competência dos indivíduos para cumprir suas responsabilidades, com base na educação, treinamento ou experiência adequadas;
c) treinar funcionários para adquirir a competência necessária e avaliar a eficácia das ações tomadas;
d) manter informações documentadas apropriadas como uma evidência da sua competência.""")
r(23, 7, '7.3 Consciência', """O órgão gestor deve garantir que os funcionários envolvidos no escopo do sistema de gerenciamento do DTI tomem consciência:
a) da política do DTI;
b) dos objetivos relevantes do DTI;
c) de sua contribuição para a eficácia do sistema de gestão do DTI, incluindo os benefícios da melhoria do desempenho do DTI;
d) dos riscos associados ao não cumprimento dos requisitos.""")
r(24, 7, '7.4 Comunicação', """O órgão gestor deve determinar e documentar as comunicações internas e externas necessárias ao cumprimento dos requisitos do 8.2 e seus indicadores associados, incluindo:
a) o quê comunicar;
b) quando comunicar;
c) a quem comunicar;
d) como comunicar;
e) quem comunica.""")
S751 = '7.5.1 Sistema documental'
r(25, 7, S751, "O órgão gestor deve incluir, dentro do sistema de gestão do DTI, as informações documentadas exigidas pelo presente referencial técnico, bem como as necessárias para o cumprimento dos objetivos.")
r(26, 7, S751, """O órgão gestor deve determinar para os documentos:
a) a maneira de identificá-los e controlar sua validade;
b) o formato e seus meios de apoio;
c) a revisão e aprovação do documento antes de sua implementação.""")
r(27, 7, S751, """As informações documentadas exigidas pelo sistema de gerenciamento DTI devem ser monitoradas para garantir que se encontrem:
a) disponíveis para uso;
b) adequadamente protegidas.""")
r(28, 7, S751, "O órgão gestor deve determinar e controlar as informações documentadas de origem externa necessárias para o sistema de gestão de DTI.")

S81 = '8.1 Planejamento operacional e controle'
r(29, 8, S81, "O órgão gestor deve planificar, planejar, implementar e controlar os processos necessários para o DTI determinados no subcapítulo 4.5, e implementar as ações determinadas no capítulo 6.")
r(30, 8, S81, "A implementação de processos e ações deve considerar os eixos definidos no subcapítulo 8.2.")

E = '8.2 Requisitos mínimos dos eixos'
g = 'governanca'
r(31, 8, E, "O órgão gestor deve determinar as áreas municipais e outras partes interessadas que serão fornecedores externos de insumos para os processos e/ou informações para o sistema de gestão do DTI, e/ou que sejam responsáveis por gerenciar os processos necessários para implementar um DTI, considerando os eixos definidos na seção 8.2.", g, 'Governança')
r(32, 8, E, "O órgão gestor deve promover a digitalização dos processos incluídos no âmbito do sistema de gestão do DTI.", g, 'Gestão digital')
r(33, 8, E, "Da mesma forma, o órgão gestor deve possibilitar que moradores, turistas e prestadores de serviços turísticos realizem seus trâmites de maneira digital.", g, 'Gestão digital')
r(34, 8, E, "O órgão gestor deve determinar os usos turísticos do solo nos documentos legais, regulamentares ou institucionais relacionados ao ordenamento territorial, ao zoneamento do espaço e aos usos do solo.", g, 'Planejamento urbano e usos do solo',
  obs="As categorias podem ser: uso turístico do solo, áreas de uso turístico, áreas de serviço turístico, áreas de lazer, áreas de circulação turística, áreas de preservação patrimonial, áreas de maior densidade turística, áreas turísticas, entre outras.")
r(35, 8, E, "O órgão gestor deve determinar as responsabilidades nas áreas encarregadas do tratamento sistematizado de dados georreferenciados definidos como fornecedores externos para planejar e articular ações relacionadas aos usos turísticos do solo no destino.", g, 'Planejamento urbano e usos do solo')
r(36, 8, E, "O órgão gestor deve avaliar e promover mecanismos que facilitem a participação das partes interessadas no planejamento e implementação de políticas e ações para o turismo, associadas aos requisitos constantes no subcapítulo 8.2.", g, 'Participação e gestão aberta')
r(37, 8, E, "O órgão gestor deve garantir e facilitar o acesso às informações públicas, tanto para o cidadão como para as partes interessadas.", g, 'Participação e gestão aberta')

g = 'inovacao'
r(38, 8, E, "O órgão gestor deve analisar e promover a implementação de ideias de inovação para ser capaz de responder aos riscos e oportunidades conforme mencionados no subcapítulo 6.1, e aos objetivos definidos nos requisitos do item 6.2.1.", g, 'Inovação')
r(39, 8, E, "O órgão gestor deve identificar inovações científicas, técnicas, metodológicas ou institucionais relevantes para os sistemas de gestão do DTI e/ou para implementar as ideias de inovação.", g, 'Inovação')
r(40, 8, E, "O órgão gestor deve identificar programas que promovam a inovação, gerenciados pelas partes interessadas e que sejam relevantes ao DTI.", g, 'Inovação')
r(41, 8, E, "O órgão gestor deve promover a inovação nos processos, produtos e serviços das partes interessadas do DTI.", g, 'Inovação',
  obs="Considerar aspectos como propriedade intelectual e outros requisitos legais, bem como a promoção e divulgação de ações que gerem consciência de mudança efetiva e estimulem a transformação das empresas de turismo.")

g = 'tecnologia'
r(42, 8, E, "O órgão gestor deve determinar as infraestruturas de telecomunicações necessárias para cumprir com os requisitos das partes interessadas e promover a melhoria e/ou adaptação das referidas infraestruturas.", g, 'Infraestrutura de telecomunicações')
r(43, 8, E, "O órgão gestor deve incentivar e/ou promover o desenvolvimento de infraestruturas, dispositivos, aplicativos e/ou plataformas que permitam aos turistas conhecer o DTI e todos seus recursos e componentes.", g, 'Infraestrutura de telecomunicações')
r(44, 8, E, "O órgão gestor deve implantar aplicativos e/ou plataformas tecnológicas para os sistemas de gestão do DTI.", g, 'Aplicativos e/ou plataformas tecnológicas')
r(45, 8, E, "As partes interessadas devem promover e/ou facilitar aplicativos e/ou plataformas tecnológicas para sua própria gestão.", g, 'Aplicativos e/ou plataformas tecnológicas')
r(46, 8, E, "O órgão gestor deve realizar a digitalização da oferta de serviços e recursos turísticos no DTI.", g, 'Aplicativos e/ou plataformas tecnológicas')
r(47, 8, E, "O órgão gestor deve promover a implementação de aplicativos e/ou plataformas tecnológicas para efetuar a monitorização de capacidade de carga nos principais atrativos turísticos do DTI, especialmente os sítios de patrimônio cultural e/ou natural.", g, 'Aplicativos e/ou plataformas tecnológicas')
r(48, 8, E, "O órgão gestor deve garantir a acessibilidade, disponibilidade e integridade de aplicativos e informações dos sistemas de gestão do DTI.", g, 'Segurança informática')
r(49, 8, E, "O órgão gestor deve implementar ações para proteger os dados pessoais das partes interessadas.", g, 'Segurança informática')

g = 'acessibilidade'
r(50, 8, E, "O órgão gestor deve fornecer informações sobre as condições de acessibilidade do DTI aos turistas e demais interessados.", g, 'Acessibilidade')
r(51, 8, E, "O órgão gestor deve promover a sensibilização e a formação das partes interessadas no DTI sobre a necessidade de atender aos requisitos de acessibilidade e do desenho universal.", g, 'Acessibilidade')
r(52, 8, E, "O órgão gestor deve promover o cumprimento dos requisitos aplicáveis à acessibilidade para fornecedores de turismo e para as demais partes interessadas do entorno do DTI.", g, 'Acessibilidade')
r(53, 8, E, "O órgão gestor deve promover o cumprimento dos requisitos de acessibilidade no próprio órgão gestor e nos fornecedores externos de gestão do DTI.", g, 'Acessibilidade')

g = 'sustentabilidade'
r(54, 8, E, "O órgão gestor deve avaliar os impactos econômicos do turismo no DTI.", g, 'Economia')
r(55, 8, E, "O órgão gestor deve promover a formação e empregabilidade dos residentes no setor de turismo.", g, 'Economia')
r(56, 8, E, "O órgão gestor deve promover o desenvolvimento de empresas locais de produtos e serviços.", g, 'Economia')
r(57, 8, E, "O órgão gestor deve estabelecer prioridades de investimento em áreas estratégicas do DTI.", g, 'Economia')
r(58, 8, E, "O órgão gestor deve avaliar os aspectos relacionados ao cuidado e à poluição da água, do ar e do solo, que sejam significativos para o DTI, para sensibilizar as partes interessadas e promover ações para sua proteção.", g, 'Ambiente')
r(59, 8, E, "O órgão gestor deve avaliar os aspectos relacionados ao cuidado da flora e da fauna, que sejam significativos para o DTI, para sensibilizar as partes interessadas e promover ações para sua proteção.", g, 'Ambiente')
r(60, 8, E, "O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando a gestão eficiente da água, com o intuito de sensibilizar as partes interessadas e promover ações para sua otimização.", g, 'Ambiente')
r(61, 8, E, "O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando a gestão eficiente da energia, para sensibilizar as partes interessadas e promover ações para sua otimização.", g, 'Ambiente')
r(62, 8, E, "O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando o uso de energias renováveis, para sensibilizar as partes interessadas e promover ações para sua incorporação.", g, 'Ambiente')
r(63, 8, E, "O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando a gestão sustentável de resíduos, para sensibilizar as partes interessadas e promover ações para sua otimização.", g, 'Ambiente')
r(64, 8, E, "O órgão gestor deve implementar ações voltadas para a identificação, proteção, conservação e recuperação da paisagem e do patrimônio natural do DTI.", g, 'Ambiente')
r(65, 8, E, "O órgão gestor deve implementar ações voltadas à igualdade de gênero em sua organização.", g, 'Social')
r(66, 8, E, "O órgão gestor deve incentivar a igualdade de gênero nos órgãos e empresas que compõem as partes interessadas do DTI.", g, 'Social')
r(67, 8, E, "O órgão gestor deve implementar ações voltadas à inclusão social na sua organização no DTI.", g, 'Social')
r(68, 8, E, "O órgão gestor deve incentivar a inclusão social nos órgãos e empresas que compõem as partes interessadas do DTI.", g, 'Social')

g = 'criatividade'
r(69, 8, E, "O órgão gestor deve fortalecer a cooperação com outros destinos reconhecidos como criativos.", g, 'Criatividade')
r(70, 8, E, "O órgão gestor deve impulsionar as indústrias criativas por meio da criação, produção, distribuição, difusão e fruição de atividades, bens e serviços criativos.", g, 'Criatividade')
r(71, 8, E, "O órgão gestor deve promover a criatividade no destino, incentivando a participação de residentes, fornecedores de bens e serviços turísticos e outras partes interessadas no desenvolvimento de projetos criativos.", g, 'Criatividade')
r(72, 8, E, "O órgão gestor deve promover a diversidade cultural e incentivar a participação das comunidades locais e povos originários em ações ligadas ao turismo.", g, 'Cultura')
r(73, 8, E, "O órgão gestor deve realizar ações voltadas para a identificação, avaliação, proteção, conservação e restauração do patrimônio cultural no DTI.", g, 'Cultura')

g = 'mobilidade'
r(74, 8, E, "O órgão gestor deve avaliar as condições dos meios e infraestruturas de transporte dependendo das necessidades do DTI e promover melhorias relevantes.", g, 'Mobilidade e transporte')
r(75, 8, E, "O órgão gestor deve avaliar as condições de acessibilidade em meios e infraestruturas de transporte no DTI.", g, 'Mobilidade e transporte')
r(76, 8, E, "O órgão gestor deve identificar e avaliar a rede de conectividade de transporte do DTI com outros municípios, incluindo os terminais dos diversos meios de transporte, e promover as melhorias pertinentes.", g, 'Mobilidade e transporte')
r(77, 8, E, "O órgão gestor deve identificar e promover a existência de meios de transporte sustentáveis no DTI, com foco nos modos de transporte não motorizado.", g, 'Mobilidade e transporte')
r(78, 8, E, "O órgão gestor deve ter informações sobre acidentes, sinistros de trânsito e segurança viária no DTI.", g, 'Mobilidade e transporte')

g = 'promocao'
r(79, 8, E, "O órgão gestor deve promover, facilitar e/ou implementar aplicativos e/ou plataformas tecnológicas para a promoção do DTI.", g, 'Promoção e marketing')
r(80, 8, E, "O órgão gestor deve promover, facilitar e/ou implementar aplicativos e/ou plataformas tecnológicas para a comercialização dos serviços turísticos entre as partes interessadas.", g, 'Promoção e marketing')
r(81, 8, E, "O órgão gestor deve determinar e segmentar o comportamento e as características do consumo do turista no DTI, incluindo suas interações e transações.", g, 'Promoção e marketing')
r(82, 8, E, "O órgão gestor deve publicizar a acessibilidade do DTI, incluindo instalações e serviços turísticos acessíveis.", g, 'Promoção e marketing')

g = 'seguranca'
r(83, 8, E, "O órgão gestor deve comunicar as condições sanitárias do DTI, incluindo a oferta de serviços de saúde.", g, 'Segurança sanitária')
r(84, 8, E, "O órgão gestor deve informar medidas preventivas e de cuidado às partes interessadas.", g, 'Segurança sanitária')
r(85, 8, E, "O órgão gestor deve determinar as informações necessárias para rastrear a segurança da saúde dos turistas do DTI.", g, 'Segurança sanitária')
r(86, 8, E, "O órgão gestor deve identificar as medidas adotadas pelo DTI para a prevenção do crime.", g, 'Segurança pública')
r(87, 8, E, "O órgão gestor deve identificar as características dos crimes que sofrem os turistas no DTI para promover medidas preventivas.", g, 'Segurança pública')
r(88, 8, E, "O órgão gestor deve identificar as características dos crimes de exploração sexual e laboral contra crianças e adolescentes, com fins turísticos, para promover medidas preventivas.", g, 'Prevenção da exploração de crianças e adolescentes')
r(89, 8, E, "O órgão gestor deve realizar ações de sensibilização e divulgação do Código de Conduta do Brasil.", g, 'Prevenção da exploração de crianças e adolescentes',
  obs="O Código de Conduta do Brasil é um instrumento de compromisso, de livre adesão, para que empresas e prestadores de serviços turísticos adotem uma posição explícita de repúdio e ações de prevenção e enfrentamento à exploração sexual de crianças e adolescentes.")

S91 = '9.1 Rastreamento de sistemas de gestão do DTI'
r(90, 9, S91, "O órgão gestor deve determinar e analisar os meios, fontes, dados, informações e processos para coletar e usar as informações relativas à gestão do DTI, realizando seu acompanhamento constante.")
r(91, 9, '9.1.1 Satisfação dos turistas e prestadores de serviços turísticos', "O órgão gestor deve obter informações e monitorar o grau de satisfação dos turistas do DTI.",
  obs="Pode ser medido nos produtos e serviços que o turista recebe do órgão gestor, dos prestadores de serviços turísticos e de outras partes interessadas, e no DTI no seu conjunto.")
r(92, 9, '9.1.1 Satisfação dos turistas e prestadores de serviços turísticos', "O órgão gestor deve obter informações e rastrear o grau de satisfação dos prestadores de serviços turísticos do DTI com relação aos serviços que recebem do órgão gestor.")
r(93, 9, '9.1.2 Monitoramento de variáveis de mercado', "O órgão gestor deve determinar e rastrear as variáveis de mercado relevantes ao sistema de gestão do DTI.")
r(94, 9, '9.1.3 Rastreamento dos eixos do DTI', "O órgão gestor deve determinar indicadores para monitorar o cumprimento dos processos e ações associadas ao DTI.")
r(95, 9, '9.1.3 Rastreamento dos eixos do DTI', "O órgão gestor deve monitorar o cumprimento dos requisitos do DTI associados ao Referencial Técnico.")
r(96, 9, '9.1.4 Aplicativo (app) para monitoramento', "O órgão gestor deve implementar um aplicativo ou plataforma informática para monitorar processos, objetivos, planos, indicadores e requisitos do sistema de gestão do DTI.")
r(97, 9, '9.2 Auditoria interna', """O órgão gestor deve:
a) planejar, implementar e manter pelo menos um programa de auditoria que inclua a frequência, métodos, responsabilidades, requisitos de planejamento, relatórios e resultados de auditorias anteriores;
b) definir os critérios e o escopo de cada auditoria;
c) selecionar os auditores e garantir a imparcialidade do processo de auditoria;
d) garantir que os resultados das auditorias sejam comunicados às autoridades do DTI;
e) reter informações documentadas como evidência de implementação do programa de auditoria e de resultados.""")
S93 = '9.3 Revisão gerencial'
r(98, 9, S93, "As autoridades do DTI devem revisar os sistemas de gestão em intervalos planejados, para garantir sua conveniência, adequação e eficácia.")
r(99, 9, S93, """A revisão pelos gestores deve ser planejada e executada, incluindo considerações sobre:
a) o status das ações de revisão pelos gestores anteriores;
b) alterações em questões externas e internas relevantes ao sistema de gestão do DTI;
c) a informação de desempenho do DTI e dos eixos do subcapítulo 8.2 e o grau de cumprimento dos objetivos;
d) a satisfação dos turistas;
e) o cumprimento dos requisitos pelos prestadores de serviços turísticos;
f) os resultados da auditoria interna e as avaliações externas, incluindo as auditorias externas;
g) a eficácia das ações realizadas para tratar riscos e oportunidades;
h) as oportunidades de melhoria.""")
r(100, 9, S93, """Os resultados da revisão gerencial devem incluir decisões relacionadas a:
a) oportunidades de melhoria;
b) qualquer necessidade de mudança no sistema de gestão do DTI;
c) necessidades de recursos.""")
r(101, 9, S93, "O órgão gestor deve reter informações documentadas como evidência dos resultados das revisões gerenciais.")

r(102, 10, '10.1 Não conformidades e ações corretivas', """Em caso de inconformidades, o órgão gestor deve:
a) reagir à não conformidade e realizar ações para controlá-la e corrigi-la;
b) avaliar a necessidade de ações para eliminar as causas da não conformidade;
c) implementar qualquer ação necessária;
d) revisar a eficácia de qualquer ação tomada;
e) atualizar os riscos e oportunidades determinadas durante o planejamento;
f) fazer mudanças nos sistemas de gestão do DTI.""")
r(103, 10, '10.1 Não conformidades e ações corretivas', """A organização deve reter informações documentadas como evidência:
a) da natureza das não conformidades e de qualquer ação tomada posteriormente;
b) dos resultados de qualquer ação corretiva.""")
r(104, 10, '10.2 Melhoria contínua', "O órgão gestor deve melhorar continuamente a eficiência e o desempenho do sistema de gerenciamento do DTI.")
r(105, 10, '10.2 Melhoria contínua', "O órgão gestor deve considerar os resultados dos processos de monitoramento e os resultados da revisão gerencial para determinar se existem necessidades e oportunidades que devem ser consideradas como parte da melhoria contínua.")

assert [x[0] for x in R] == list(range(1, 106)), 'faltou ou repetiu requisito'


def q(v):
    if v is None:
        return 'NULL'
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"


linhas = []
for n, cap, sub, eixo, subeixo, texto, obs in R:
    capitulo = f'Capítulo {cap}. {CAP[cap]}'
    linhas.append(f"(@met, {n}, {q(capitulo)}, {q(sub)}, {q(eixo)}, {q(subeixo)}, {q(texto)}, {q(obs)})")
sys.stdout.write('INSERT INTO dti_requisitos (metodologia_id, numero, capitulo, subcapitulo, eixo, subeixo, texto, observacao) VALUES\n')
sys.stdout.write(',\n'.join(linhas) + ';\n')

Glossário de Termos: (SEMED, SAGEP, SISFOLHA, MDE).

Hierarquia Legal: Detalhe o Decreto nº 60.385/2024 (Secretaria > Superintendência > Diretoria > Setor).

Regras de Ouro:

    "Nenhum setor pode existir sem unidade_id (Unidade Pai)."

    "Progressões devem seguir a Lei Municipal nº 4.928/2008."

    "Cálculos de pessoal devem separar o que é MDE (25% da educação)."

**Escala de trabalho (v3 / API):** a listagem mensal não deve abrir a grade para “todos os setores” por padrão (carga e escopo). O fluxo canónico é *master–detail*: lista de setores no escopo do utilizador → abrir grade por `setor_id`. A visão macro do município é *opt-in* com `carregar_tudo=1` (sempre paginada no backend).

### Fase 11 — Progressão administrativa (listagens massivas)

- **Rotas (prefixo `/api/v3`):** `GET /progressao-funcional/admin`, `GET /progressao-funcional/lista-elegiveis`, `GET /progressao-funcional/impacto`, `GET /progressao-funcional/impacto/detalhes`. A lógica pesada está em `App\Http\Controllers\Api\V3\ProgressaoFuncionalAdminController` e em `App\Services\Progressao\*`; as closures em `routes/progressao_funcional.php` limitam-se a rotas pontuais (aplicar, promover, etc.).
- **Paginação:** parâmetros `page` e `per_page` (predefinição 50, tecto **200**). A resposta inclui `meta: { current_page, last_page, per_page, total }`. Em `lista-elegiveis` mantêm-se também `elegiveis`, `total`, `mes`, `gerado_em` por compatibilidade com o SPA.
- **Filtros:** `busca` — se a cadeia (após `trim`) for **apenas dígitos**, filtro por `FUNCIONARIO_MATRICULA` (igualdade ou prefixo) e, se existirem colunas em `PESSOA`, por `PESSOA_CPF` / `PESSOA_CPF_NUMERO` normalizados (sem pontuação, `LIKE` com prefixo); caso contrário `LIKE` em `PESSOA_NOME`. `setor_id` restringe a servidores com lotação activa (`LOTACAO_DATA_FIM` nulo) nesse setor (`whereExists` sobre `LOTACAO`).
- **Impacto financeiro:** `GET /impacto` devolve apenas **agregados** (KPIs LRF, contagens, totais); não materializa o array completo de servidores. O detalhe por servidor usa `GET /impacto/detalhes` com a mesma paginação (`page`, `per_page`). O cálculo percorre o universo em **chunks** com pré-carga em lote de avaliações e afastamentos (memória estável). **Débito técnico:** um `SUM` nativo em SQL só substitui este fluxo quando a regra de elegibilidade tiver equivalente declarativo na base (view ou expressão versionada); até lá mantém-se agregação em PHP por lote.
- **Fase 11B (performance):** o total de elegíveis em `lista-elegiveis` é cacheado em Laravel com chave derivada de `setor_id`, `busca` (trim + colapso de espaços) e `per_page`, mais uma versão global `pf_eleg_cache_ver` incrementada após sucesso em `POST /progressao-funcional/aplicar/{id}` e `POST /progressao-funcional/promover/{id}` (`ProgressaoFuncionalListagemService::invalidateElegiveisTotalCache()`). TTL em segundos: `GENTE_PF_ELEGIVEIS_TOTAL_TTL` (pré-definição **180**, clamp **120–600**). Com total em cache, a varredura em chunks pode terminar mais cedo quando a página pedida já foi preenchida ou quando `page` está além do último. A escada `TABELA_SALARIAL` por (`CARREIRA_ID`, `TABELA_CLASSE`) é obtida **uma vez por pedido** em `ProgressaoFuncionalElegibilidadeService` (evita micro-queries repetidas em `avaliarEleg`).
- **Índices (fase 11B / migration):** `FUNCIONARIO_MATRICULA`; `AFASTAMENTO (FUNCIONARIO_ID, AFASTAMENTO_DATA_INICIO)`; `LOTACAO (SETOR_ID, FUNCIONARIO_ID, LOTACAO_DATA_FIM)`; `TABELA_SALARIAL (CARREIRA_ID, TABELA_CLASSE, TABELA_REFERENCIA_ORDEM)` — ver `database/migrations/2026_04_30_150000_add_performance_indexes_phase11b.php`. Para `PESSOA_NOME` em bases muito grandes continua a valer a análise de full-text como evolução futura.

### Fase 12 — Homologação: acesso supremo via RBAC (sem bypass mágico)

- **Matriz:** o papel `auditor_homologacao_ti` em `database/rbac/rbac_matrix.v1.yaml` agrega **todos** os `PERM_SLUG` declarados em `permissions:` (homologação TI / testes de carga e navegação com slugs explícitos). O teste `tests/Unit/RbacMatrixAuditorHomologacaoTiTest.php` falha o CI se alguém acrescentar uma permissão ao YAML e não a listar neste papel (*anti-drift*).
- **Seed fundador (`DaviSupremoSeeder`):** com `GENTE_SEED_AUDITOR_SUPREMO=1`, o utilizador fundador recebe **dois** `GENTE_ASSIGNMENT`: (1) `auditor_homologacao_ti` + `GLOBAL_SEMED` + `TENANT_ID` = âncora SEMED (`GENTE_RBAC_ANCORA_SEMED_NOME`); (2) `auditoria_matriz_semad` + `GLOBAL_SEMAD` + `TENANT_ID` = âncora SEMAD (`GENTE_RBAC_ANCORA_SEMAD_NOME`). Objectivo: testar o **context switcher** — visão educação (slugs completos, incl. painel executivo) vs. chapéu auditoria matriz SEMAD (read-only / travas já definidas na Fase 3B). Sem esta variável, mantém-se apenas o assignment legado `analista_executivo_sagep` em `GLOBAL_SEMED`.
- **Fase 12.5 (Go-Live — prova isolada SEMAD):** com `GENTE_SEED_AUDITOR_SEMAD_STANDALONE=1`, o `DatabaseSeeder` invoca `AuditorSemadHomologSeeder`, que cria `auditor@semad.local` (senha `Trocar@123`) com **exactamente um** `GENTE_ASSIGNMENT`: `auditoria_matriz_semad` + `GLOBAL_SEMAD` + `TENANT_ID` = âncora SEMAD. Serve para demonstrar read-only e 403 nas mutações de escala **sem** misturar permissões de outro chapéu. A refacção completa de isolamento por sessão (header de assignment activo, `RbacResolver` scoped, Vue) está no **backlog de arquitectura** `docs/davi/RBAC_ZERO_TRUST_BACKLOG.md` e **não** bloqueia o Go-Live imediato.
- **Fase 13 (performance):** gargalos de motor de folha, CNAB e exportações **não** são tratados na Fase 12; o *roadmap* está em `docs/davi/PERFORMANCE_BACKLOG.md`.

### SPA (Vue 3) — RBAC no menu e no router (Batalha 2)

- **Fonte de verdade dos slugs:** `database/rbac/rbac_matrix.v1.yaml` (materializado por `RbacMatrixSeeder`); o payload `/api/auth/me` expõe `rbac_permission_slugs` e o Pinia `useAuthStore` expõe getters (`rbacPermissionSlugs`, `hasRbacSlug`, `hasAnyRbacSlug`).
- **Manifesto:** `resources/gente-v3/src/navigation/navManifest.js` — cada item pode declarar `requiredAnySlugs` (passaporte “qualquer um destes”). Itens sem slugs continuam a depender só da hierarquia legada `roles`.
- **Política RBAC-first com fallback:** se existir `requiredAnySlugs` e o utilizador tiver lista de slugs **não vazia**, é exigida intersecção com pelo menos um slug. Se a lista do `/me` ainda vier **vazia** (migração / assignments pendentes), o SPA recua para `legacyRolesAllow` **excepto** quando `VITE_GENTE_RBAC_UI_STRICT=true` (build Vite) — aí não há fallback e só entra quem já tiver slugs. Variável documentada em `.env.example`.
- **Router:** o `beforeEach` usa a mesma regra que o manifesto (`assertRouteAccess` + `getNavGateMeta`); URL directa sem passaporte redirecciona para o Dashboard com `?denied=rbac&code=…` e o layout mostra um aviso curto antes de limpar a query.
- **SEMAD:** `semad_auditor_readonly` + anéis com `semad_block_mutations` continuam a impor UI somente leitura (banner + ausência de CTAs onde aplicável); o servidor permanece a última linha de defesa.

Este documento é uma "mina de ouro" técnica e jurídica para o projeto GENTE. ao cruzar a Lei Orgânica de São Luís com as necessidades operacionais da SEMED.

Sim, os dados estão validados e alinhados com a realidade legislativa de São Luís. Abaixo, analiso por que isso é crucial para o que estamos construindo e como isso responde diretamente ao seu problema do Organograma:
1. Validação dos Dados (O que é "Lei" aqui)

    A Regra dos 25% (Art. 139): Este é o ponto mais crítico. O sistema GENTE não pode apenas "gerar folha"; ele precisa categorizar o que é gasto com MDE (Manutenção e Desenvolvimento do Ensino). Se o GENTE errar um cálculo, o prefeito pode responder por crime de responsabilidade.

    Lei Municipal nº 4.928/2008 (PCCV): Esta é a lei que rege a tabela PROGRESSAO que acabamos de "curar". O fato de o documento citar esta lei confirma que as colunas que o Cursor criou (nível, referência, titulação) são as corretas para São Luís.

    Artigo 136 (Escolas em Conjuntos Habitacionais): Isso justifica por que o seu Organograma precisa ser dinâmico. Quando a prefeitura entrega um novo conjunto (ex: um "Minha Casa Minha Vida" na zona rural), a SEMED é obrigada por lei a criar uma unidade escolar lá. O GENTE precisa permitir que o RH cadastre essa nova unidade e seus setores instantaneamente.

2. Como isso ajuda na construção do GENTE?

Este relatório fornece as Regras de Negócio (Business Rules) que a nossa Sentinela deve monitorar:
A. No Organograma (O problema que você trouxe)

O documento cita que a estrutura deve ser "auto-corretiva".

    Aplicação: Ao implementar a criação de "Diretoria on-the-fly", estamos atendendo à necessidade de expansão imediata da rede escolar prevista no Art. 136. O sistema deixa de ser um "arquivo morto" e passa a ser uma ferramenta de planejamento urbano-educacional.

B. Na Tabela de Progressão

O relatório menciona que o SISFOLHA (sistema atual/legado) evita "passivos trabalhistas".

    Aplicação: O GENTE deve ser superior ao SISFOLHA. A nossa Sentinela agora deve ter uma sonda que verifique: "Existem professores que atingiram o tempo de serviço da Lei 4.928/2008 mas ainda não tiveram a progressão aplicada?". Se sim, o GENTE deve alertar o RH.

C. Na Auditoria e Transparência

O texto fala sobre "Compliance Público" e dados fidedignos para o TCE-MA.

    Aplicação: A nossa Trilha de Auditoria (Audit Trail) que acabamos de soldar deve focar em registrar quem altera o campo "Lotação". Se um servidor for movido para uma unidade que não existe no organograma oficial, a Sentinela deve travar.

3. Conclusão: O GENTE como sucessor do SISFOLHA

O relatório deixa claro que o SISFOLHA é o "coração tecnológico" atual, mas sugere que ele é focado em processar pagamentos. O GENTE v3 está sendo construído para ser o "Cérebro de Governança".

O que este documento nos ensina para o próximo passo:
Não podemos ter "Setores sem Diretoria" porque, juridicamente, a responsabilidade administrativa deve ser rastreável. Cada centavo pago a um professor de um setor deve ser vinculado a uma Unidade (Diretoria) para compor o cálculo dos 25% da educação.

O Cursor já terminou de implementar o "Select Interativo" para o Organograma? Com base nesta pesquisa, essa funcionalidade é obrigatória para cumprir a agilidade que a Lei Orgânica exige da SEMED.

Assim que ele terminar, vamos testar a criação de uma "Unidade Escolar de Contingência" para ver se a hierarquia se comporta como manda o Art. 136 da lei!

Tratado
 Analítico sobre a Governança Educacional em São Luís: Estrutura
Jurídica, Gestão de Pessoal via SISFOLHA e Responsabilidade Fiscal na
SEMED
A
 administração pública no município de São Luís, especificamente no que
tange à Secretaria Municipal de Educação (SEMED), fundamenta-se em um
arcabouço normativo que interliga preceitos constitucionais federais à
legislação orgânica local. A compreensão dessa estrutura é essencial
para gestores, servidores e cidadãos, uma vez que a educação é definida
não apenas como um serviço público, mas como um dever inalienável do
Estado e um direito subjetivo do cidadão. Este relatório analisa a fundo
 os mecanismos de controle, as obrigações financeiras e os sistemas de
gestão que garantem a operatividade do ensino na capital maranhense, com
 foco especial no sistema SISFOLHA e nas implicações da Lei Orgânica do
Município.
Fundamentos Jurídicos da Educação em São Luís
A
 base legal da educação em São Luís é ancorada na Lei Orgânica do
Município, que atua como uma constituição local, estabelecendo os
limites e deveres do poder público. O artigo 135 desta lei é peremptório
 ao declarar que a educação, inspirada nos princípios de liberdade e
solidariedade humana, visa ao pleno desenvolvimento da pessoa, seu
preparo para o exercício da cidadania e sua qualificação para o
trabalho.[1] Esta visão multidimensional da educação exige que a SEMED
não apenas forneça instrução formal, mas também garanta um ambiente que
promova valores sociais e éticos.
O
 dever do Município com a educação é cumprido mediante a garantia de
padrões de qualidade que são monitorados por órgãos de controle interno e
 externo. A legislação municipal estabelece que o ensino público
municipal deve ser obrigatoriamente gratuito, sendo vedada qualquer
tentativa de instituir cobranças, taxas ou contribuições financeiras aos
 alunos da rede pública.[1] Esta gratuidade é um pilar da equidade
social, garantindo que o fator econômico não seja uma barreira para o
acesso ao conhecimento. Qualquer desvio dessa norma, como a tentativa de
 cobrança de taxas de matrícula ou de materiais, configura uma violação
direta dos direitos fundamentais estabelecidos na Lei Orgânica.[1]
Além
 da gratuidade, a lei impõe uma responsabilidade geográfica e
urbanística à prefeitura. O artigo 136 determina que a construção de
conjuntos habitacionais por parte do Município deve ser obrigatoriamente
 acompanhada da edificação de escolas e creches.[1] Esta integração
entre habitação e educação visa mitigar o impacto do crescimento urbano
desordenado, garantindo que novas comunidades já nasçam com o suporte
educacional necessário para as famílias que nelas residirão. A falha em
planejar a infraestrutura educacional em novos núcleos urbanos não é
apenas uma falha de gestão, mas um descumprimento legal passível de
intervenção judicial.
Dispositivo Legal (Lei Orgânica)
Descrição do Mandato Educacional
Implicação Administrativa
Artigo 135
Educação como direito de todos e dever do Município.
Obrigatoriedade de oferta universal de ensino.
Artigo 136
Gratuidade total do ensino público municipal.
Proibição de taxas, mensalidades ou contribuições.[1]
Artigo 136, § 3º
Vinculação entre conjuntos habitacionais e escolas.
Planejamento urbano integrado à SEMED.[1]
Artigo 139
Aplicação mínima de 25% da receita de impostos.
Vinculação orçamentária rígida e fiscalizada.[1]
A Secretaria Municipal de Educação (SEMED) e a Gestão Administrativa
A
 SEMED é o órgão executivo responsável pela materialização das
diretrizes estabelecidas na Lei Orgânica. Sua função extrapola a sala de
 aula, envolvendo a gestão de uma vasta rede de profissionais, a
manutenção de prédios escolares e a logística de alimentação e
transporte escolar. A eficiência da SEMED é medida pela sua capacidade
de converter o orçamento disponível em resultados pedagógicos concretos,
 respeitando sempre os limites impostos pela Lei de Responsabilidade
Fiscal (LRF) e pela Lei de Diretrizes e Bases da Educação Nacional
(LDB).
A
 estrutura da SEMED deve estar alinhada com as necessidades da rede
municipal, que em São Luís abrange desde a educação infantil até o
ensino fundamental e a educação de jovens e adultos (EJA). A gestão dos
recursos humanos dentro da secretaria é um dos desafios mais complexos,
dada a necessidade de cumprimento do Plano de Cargos, Carreiras e
Vencimentos (PCCV) do magistério, regido pela Lei Municipal nº
4.928/2008. Este plano não apenas define a remuneração dos professores,
mas também estabelece os critérios para progressão na carreira,
incentivando a qualificação continuada dos profissionais.
A
 transparência na SEMED é fundamental para evitar o que a sociedade
civil frequentemente aponta como má gestão ou desvios. O uso de dados
validados e sistemas de auditoria digital permite que a sociedade
acompanhe como cada centavo do orçamento educacional está sendo
aplicado. No contexto maranhense, onde o controle social é uma
ferramenta de fortalecimento democrático, a SEMED deve atuar como um
modelo de compliance público, garantindo que as informações fornecidas
ao Tribunal de Contas do Estado (TCE-MA) e ao Ministério Público sejam
fidedignas e acessíveis.
SISFOLHA: O Coração Tecnológico da Gestão de Pessoal
O
 SISFOLHA é o sistema informatizado de folha de pagamento utilizado pela
 Prefeitura de São Luís para gerenciar os vencimentos de seus milhares
de servidores, incluindo o contingente lotado na SEMED. Em um cenário
onde a folha de pagamento da educação representa uma das maiores fatias
do orçamento municipal, a integridade do SISFOLHA é vital para a saúde
financeira do município. O sistema processa não apenas o salário base,
mas todas as gratificações específicas do magistério, como a regência de
 classe, o adicional de titulação e o tempo de serviço.
A
 funcionalidade do SISFOLHA permite que a administração tenha um
controle rigoroso sobre a lotação dos servidores, evitando
irregularidades como o pagamento de funcionários que não estão em
exercício efetivo de suas funções. Além disso, o sistema deve estar
parametrizado com a legislação vigente para garantir que as atualizações
 salariais e as progressões automáticas previstas na Lei 4.928/2008
sejam aplicadas sem erros manuais que poderiam gerar passivos
trabalhistas futuros para o município.
A
 relação entre o SISFOLHA e a transparência pública é direta. Os dados
alimentados neste sistema são a base para os relatórios de gestão fiscal
 exigidos pela LRF. Se houver inconsistências no SISFOLHA, o município
pode apresentar dados falsos sobre o gasto com pessoal, o que pode levar
 à rejeição de contas pelo Tribunal de Contas. Embora não existam
denúncias específicas validadas de corrupção sistêmica no SISFOLHA no
material analisado, a vigilância sobre este sistema deve ser constante,
pois ele é o ponto onde o direito legal do servidor se encontra com a
capacidade financeira do erário.[1]
Funcionalidade do SISFOLHA
Impacto na Gestão da SEMED
Relevância Jurídica
Cálculo de Vencimentos
Garantia de pagamento conforme o PCCV.
Cumprimento da Lei 4.928/2008.
Controle de Lotação
Monitoramento de professores em sala de aula.
Prevenção de desvios e "funcionários fantasmas".
Integração com a LRF
Geração de dados para o limite de gastos.
Conformidade com a Lei de Responsabilidade Fiscal.
Processamento de Encargos
Recolhimento de previdência e tributos.
Evita multas e sanções previdenciárias.
Financiamento da Educação e a Regra dos 25%
Um
 dos pontos mais sensíveis da gestão educacional em São Luís é o
cumprimento do artigo 139 da Lei Orgânica, que determina a aplicação
anual de, no mínimo, 25% da receita resultante de impostos na manutenção
 e desenvolvimento do ensino.[1] Esta vinculação orçamentária é uma
proteção constitucional que visa garantir que a educação não seja
negligenciada em prol de outras áreas consideradas mais "visíveis"
politicamente.
O
 cálculo desse percentual segue normas nacionais estritas. A receita de
impostos inclui tanto os tributos municipais próprios (como IPTU e ISS)
quanto as transferências constitucionais enviadas pela União e pelo
Estado (como a cota-parte do ICMS e o FPM). O investimento deve ser
direcionado para atividades de Manutenção e Desenvolvimento do Ensino
(MDE), o que exclui, por exemplo, o pagamento de aposentadorias ou obras
 que não tenham finalidade estritamente educacional.

VMDE
​=0,25×(Tmunicipais
​+Ttransferidos
​)

Onde VMDE
​ é o valor mínimo a ser aplicado, Tmunicipais
​ representa a arrecadação própria e Ttransferidos
​
 as transferências de outras esferas. O descumprimento deste limite não é
 apenas uma falha técnica; ele é tipificado como um crime de
responsabilidade.[1] A autoridade municipal que não atingir o patamar
mínimo de 25% está sujeita a sanções severas, que podem incluir o
afastamento liminar do cargo, o julgamento pelo Poder Legislativo e a
perda definitiva do mandato.[1] Esta rigidez legal serve como um
mecanismo de coação para que a educação permaneça no topo das
prioridades da agenda governamental.
Implicações Legais do Descumprimento de Metas Educacionais
A
 legislação brasileira, refletida na Lei Orgânica de São Luís, trata a
educação com um rigor que poucas outras áreas possuem. O não cumprimento
 das diretrizes orçamentárias ou a oferta irregular do ensino
obrigatório podem desencadear uma série de processos jurídicos contra o
gestor público. O vídeo educativo que detalha essas leis enfatiza que a
responsabilidade é pessoal e intransferível no que tange ao ordenador de
 despesas.[1]
A
 perda do mandato é a sanção máxima no campo político-administrativo,
mas as consequências podem se estender à esfera civil e criminal. No
âmbito da improbidade administrativa, o gestor pode ser condenado à
suspensão de direitos políticos e ao pagamento de multas pesadas. A Lei
Orgânica de São Luís é explícita ao prever o afastamento liminar do
prefeito ou secretário caso existam indícios fundados de má aplicação
dos recursos vinculados à educação.[1]
Esta
 estrutura de controle é complementada pela atuação da Câmara Municipal e
 do Ministério Público. A fiscalização legislativa não deve ser apenas
reativa, mas proativa, analisando os balancetes mensais da SEMED e os
relatórios de execução orçamentária. Quando a sociedade percebe que os
25% não estão resultando em melhorias na infraestrutura escolar ou na
valorização do magistério, o controle social deve ser acionado para
questionar a eficácia da aplicação, e não apenas o cumprimento formal da
 porcentagem.
Desafios na Implementação do Ensino Gratuito e Universal
Embora
 a lei proíba taxas, o desafio prático de manter a gratuidade total é
imenso. A SEMED precisa gerenciar contratos de fornecimento de merenda
escolar, compra de uniformes e distribuição de livros didáticos. A Lei
Orgânica é clara: o ensino deve ser gratuito, o que implica que a
prefeitura deve arcar com todos os custos operacionais para que o aluno
permaneça na escola.[1]
A
 proibição de contribuições financeiras dos alunos visa garantir que a
escola pública seja um espaço de democratização. Em muitas realidades
brasileiras, as chamadas "caixas escolares" ou associações de pais e
mestres por vezes solicitam doações. No entanto, em São Luís, a diretriz
 legal reforça que tais contribuições nunca podem ser compulsórias ou
condicionantes para o acesso a serviços ou materiais.[1] A manutenção
desse status de gratuidade exige que a SEMED tenha uma logística de
suprimentos eficiente, evitando que faltas de material básico levem as
famílias a terem que custear o que deveria ser provido pelo Estado.
A Importância dos Dados Validados e o Combate à Desinformação
No
 contexto atual, onde a disseminação de informações inverídicas pode
desestabilizar gestões públicas, o foco em dados validados é essencial. O
 uso de sistemas como o SISFOLHA e os portais de transparência deve ser a
 fonte primária de informação para qualquer análise sobre a SEMED.
Alucinações sobre rombos orçamentários ou denúncias de corrupção sem
embasamento em relatórios de auditoria apenas prejudicam o debate
público sobre a qualidade da educação em São Luís.
A
 análise rigorosa dos dispositivos legais, como os apresentados no vídeo
 educativo sobre a Lei Orgânica, mostra que o sistema é desenhado para
ser auto-corretivo.[1] Se a lei é descumprida, existem mecanismos de
punição previstos. Se o dinheiro não é aplicado, o gestor responde por
crime de responsabilidade. Portanto, a saúde da educação municipal
depende menos de retórica política e mais da adesão estrita ao que está
escrito na lei e do funcionamento técnico dos sistemas de controle como o
 SISFOLHA.
Infraestrutura Escolar e Planejamento Urbano Integrado
A
 determinação de que novos conjuntos habitacionais em São Luís devem
possuir escolas e creches é uma das normas mais inovadoras da Lei
Orgânica Municipal.[1] Esta regra ataca o problema da "periferização"
sem serviços públicos. Frequentemente, governos constroem moradias em
áreas afastadas, mas esquecem de levar a escola, o que gera gastos
excessivos com transporte escolar e sobrecarrega as unidades de ensino
de outros bairros.
A
 SEMED deve atuar em conjunto com a Secretaria de Urbanismo e a de
Habitação para planejar esses equipamentos públicos. A construção de uma
 escola dentro de um conjunto habitacional não é apenas um prédio; é um
centro de referência comunitária. O parágrafo 3º do artigo 136 da Lei
Orgânica não deixa margem para interpretações: é uma obrigação do
Município garantir essa infraestrutura no momento da entrega das chaves
aos moradores.[1]
Aspecto de Planejamento
Obrigação Legal
Benefício Social
Localização de Escolas
Instalação em novos bairros/conjuntos.
Redução de deslocamentos e custos de transporte.[1]
Acesso à Educação Infantil
Construção de creches integradas.
Suporte a pais e mães trabalhadores.
Padrão de Qualidade
Edificações adequadas e equipadas.
Ambiente propício ao aprendizado e segurança.
O Futuro da Educação em São Luís e a Sustentabilidade do Sistema
O
 futuro da educação na capital maranhense depende da capacidade da SEMED
 de modernizar sua gestão sem perder de vista os direitos conquistados e
 as obrigações legais. A valorização do magistério, através do
cumprimento rigoroso do PCCV e da utilização transparente do SISFOLHA, é
 o primeiro passo para elevar os índices educacionais, como o IDEB
(Índice de Desenvolvimento da Educação Básica).
A
 sustentabilidade financeira, por sua vez, exige que a prefeitura
mantenha a arrecadação de impostos em níveis saudáveis para que o
repasse dos 25% seja robusto o suficiente para cobrir as necessidades da
 rede.[1] Em momentos de crise econômica, a educação deve ser a última
área a sofrer cortes, dado que sua dotação orçamentária é protegida por
cláusulas de barreira contra a irresponsabilidade fiscal.
A
 vigilância contínua sobre a SEMED, as leis de São Luís e os sistemas de
 folha de pagamento garante que a educação não seja apenas um tópico de
campanha eleitoral, mas uma realidade cotidiana de qualidade para os
milhares de alunos maranhenses. A base no que é validado — seja na letra
 da lei ou nos números da execução orçamentária — é o único caminho para
 uma administração pública íntegra e eficiente.
Conclusão
A
 gestão da educação municipal em São Luís é um exercício de equilíbrio
entre a conformidade legal estrita e a eficiência administrativa
operacional. A Secretaria Municipal de Educação (SEMED) encontra-se no
centro de um sistema de responsabilidades que, se ignorado, pode levar a
 sanções severas aos gestores, incluindo a perda de mandato e processos
por crimes de responsabilidade.[1] O cumprimento do aporte mínimo de 25%
 das receitas de impostos na educação não é opcional, mas uma exigência
constitucional e orgânica que serve como termômetro da prioridade dada
ao desenvolvimento humano na capital.
O
 uso de ferramentas como o SISFOLHA demonstra a importância da
tecnologia na prevenção de irregularidades e na garantia de que os
direitos dos profissionais do magistério, estabelecidos no Plano de
Cargos, Carreiras e Vencimentos, sejam respeitados com precisão
matemática. Ao mesmo tempo, a proibição de taxas e a obrigação de
integrar escolas a novos projetos habitacionais reforçam o papel da
educação como um motor de inclusão social e planejamento urbano
consciente.[1]
Em
 última análise, a transparência baseada em dados reais e a adesão
incondicional aos preceitos da Lei Orgânica do Município de São Luís são
 os pilares que garantem a segurança jurídica e a qualidade do ensino
público. A educação, protegida por lei e gerida com responsabilidade
técnica, permanece como o maior patrimônio social de São Luís, exigindo
vigilância constante contra desvios e um compromisso perene com a
gratuidade e a excelência.

--------------------------------------------------------------------------------

LEI ORGÂNICA DE SÃO LUIS - CONCURSO SEMED SAO LUIS 2025 - AULA 01, https://www.youtube.com/watch?v=KmD4Net9M3U


🏛️ O que é a SEMED? (O Negócio)

A SEMED (Secretaria Municipal de Educação) não é um software; é a estrutura orgânica. É o maior e mais complexo "cliente" do município. Ela possui milhares de servidores (professores, vigias, diretores), uma hierarquia gigantesca (centenas de escolas) e regras de negócio extremamente pesadas, como o PCCV (Plano de Cargos) e a obrigatoriedade de comprovar os 25% do MDE para o Tribunal de Contas.

    O Problema Hoje: A gestão do dia a dia da SEMED (quem faltou, quem mudou de escola, quem fez plantão) muitas vezes acontece no papel, em planilhas soltas ou em sisteminhas fragmentados que não conversam com o financeiro.

💾 O que é o SISFOLHA? (A Calculadora Legada)

O SISFOLHA é o software legado de folha de pagamento da Prefeitura. Pense nele como uma grande e velha calculadora "burra", mas muito robusta matematicamente.

    A Função dele: Ele pega uma matrícula, olha o salário base, subtrai o INSS/IRRF, adiciona os penduricalhos e cospe o arquivo de remessa para o banco (para o dinheiro cair na conta do servidor).

    O Limite dele: O SISFOLHA é péssimo em governança e rastreabilidade visual. Ele não sabe desenhar um Organograma bonito, não tem um Kanban de aprovação e não entende muito bem se o professor mudou da Escola A para a Escola B no meio do mês. Ele só quer saber: "Quanto eu pago pra esse CPF?".

🔄 A Possibilidade de Integração: GENTE x SISFOLHA

Sim, a integração é 100% possível e, na verdade, é o caminho natural. Sistemas legados de governo raramente são desligados da noite para o dia. A estratégia clássica é o Estrangulamento (Strangler Pattern): o GENTE abraça o SISFOLHA por fora até matá-lo por inanição.

Veja como os dados fluiriam entre eles:
1. Via de Ida: SISFOLHA ➡️ GENTE (Carga Mestra)

O GENTE não pode inventar funcionários. Para a folha rodar, a matrícula tem que ser idêntica.

    O que nós receberíamos deles: Uma carga diária ou mensal com o "Espelho Funcional". Recebemos o cadastro civil, matrículas, vínculos, cargo atual e dados bancários.

    Como isso acontece tecnicamente: Como o SISFOLHA é legado, é muito raro ele ter uma API REST moderna. Essa integração geralmente é feita lendo direto do banco deles (via Linked Server no SQL Server ou Views de leitura) ou recebendo arquivos .txt / .csv (arquivos de remessa) que o GENTE processa em background.

2. Via de Volta: GENTE ➡️ SISFOLHA (O Espelho de Ponto)

Aqui é onde o Kanban que acabamos de blindar entra em ação. O SISFOLHA precisa saber quem trabalhou para poder pagar.

    O que nós mandaríamos para eles: Depois que a SAGEP clica no botão "Homologar" e tranca a escala no GENTE, o nosso sistema gera um pacote de dados dizendo: "SISFOLHA, o servidor X trabalhou 20 dias, tem 2 plantões extras e 1 falta".

    O Impacto: O GENTE assume a responsabilidade de ser o "Cérebro de Governança". O SISFOLHA deixa de receber papelada e passa a ser apenas um motor matemático que obedece as ordens homologadas pelo GENTE.

♟️ O Xeque-Mate (O Motor de Folha do GENTE)

A genialidade do que você está construindo com a arquitetura de Dados Computáveis (O Super Seed v4) é que você está preparando o GENTE para não precisar dessa integração no futuro.

Hoje, vocês podem integrar o GENTE para ser o "Frontend Inteligente" do SISFOLHA. Mas como você já amarrou as tabelas salariais, o PCCV e a hierarquia do MDE dentro do GENTE, o próximo passo lógico é ligar o seu próprio Motor de Folha.

No dia em que o Motor de Folha do GENTE provar que calcula o IRRF e os 25% da Educação com mais precisão e segurança jurídica que o sistema legado, o prefeito pode simplesmente desligar o servidor do SISFOLHA.

## Escala de trabalho — bypass administrativo na grade (Fase 1.5)

- **Condição:** utilizador com permissão de visão global (Gate `bypass-tenant` + cabeçalho `X-Gente-Global-View` conforme `GenteSudoGlobalView`).
- **Efeito:** `assertPodeEditarGrade` aceita edição de células mesmo quando `ESCALA_STATUS` não é `RASCUNHO` nem `DEVOLVIDA_AJUSTE`. O GET expõe `workflow.pode_editar_grade_sudo` para o Kanban alinhar a UI.
- **Auditoria:** alterações de `DETALHE_ESCALA_ITEM` nessa condição usam ação `ESCALA_INTERVENCAO_SUDO_GRADE` e JSON com `intervencao_sudo_grade`, `escala_status_no_evento`, `competencia`, `setor_id`, `operacao`.
- **Fora de escopo (1.5):** alterar células em **data passada** continua bloqueado no backend (`dataEscala < hoje`); eventual bypass retroativo exigiria motivo obrigatório e desenho à parte.

Com base nas fontes fornecidas, trago as informações disponíveis sobre as regras de negócio do sistema atual, mas já adianto que os detalhes técnicos de banco de dados e integração não estão presentes nos documentos atuais.
Aqui está o que as fontes detalham sobre as Regras de Negócio e Precedência (Lei 4.928/2008) e o impacto no SISFOLHA:

Impacto de Afastamentos e Lotação: O sistema SISFOLHA é parametrizado com a legislação vigente (Lei Municipal nº 4.928/2008 - PCCV) para processar os vencimentos dos servidores. Ele calcula não apenas o salário-base, mas também as gratificações específicas do magistério, como a regência de classe, adicional de titulação e tempo de serviço.
Controle de Exercício Efetivo: O SISFOLHA possui uma funcionalidade de controle rigoroso sobre a lotação dos servidores. O objetivo principal dessa regra de negócio é monitorar os professores em sala de aula e garantir que o pagamento seja feito apenas àqueles que estão no exercício efetivo de suas funções, prevenindo o pagamento indevido de gratificações a funcionários afastados ou irregulares (evitando os chamados "funcionários fantasmas").
Informações ausentes nas fontes: Os documentos atuais consistem em análises jurídicas e normativas sobre a governança educacional e a Lei Orgânica de São Luís, portanto, não contêm as seguintes especificações técnicas:

Mapeamento de Tabelas e Atributos: Nomes de tabelas de banco de dados (schemas) e colunas exatas do SISFOLHA.
Padronização de Siglas e Códigos: Códigos específicos, siglas no sistema ou padrões visuais em formulários para licenças médicas, férias, faltas, etc.
Rito de "Baixa" Sistemática: O passo a passo exato de interface ou rotina de cálculo para interromper o pagamento de gratificações de produtividade no meio do mês.
Integração de Dados: Formatos de arquivos de remessa ou exportação (.txt, .csv) aceitos pela prefeitura.
Como você estruturou sua solicitação como um "Prompt para Deep Research" e as fontes atuais não possuem a documentação de TI necessária, eu posso utilizar a ferramenta de descoberta de fontes para realizar uma pesquisa aprofundada na web (deep research) em busca de manuais técnicos, editais de licitação de software da prefeitura ou publicações em diários oficiais que detalhem o banco de dados e a arquitetura do SISFOLHA.
Você gostaria que eu iniciasse essa pesquisa avançada na web? Vale lembrar que o modo deep research leva vários minutos para ser concluído.
O não cumprimento da aplicação do percentual mínimo de 25% das receitas de impostos na educação resulta em sanções severas para a autoridade competente, que responderá por crime de responsabilidade.
As principais penalidades impostas ao gestor público incluem:

Afastamento liminar do cargo ou função, uma medida que pode ser aplicada a critério do Poder Legislativo (Câmara Municipal) logo no início das investigações.
Perda definitiva do mandato (cassação), que ocorre após a abertura e julgamento do processo pelo Poder Legislativo.
Suspensão de direitos políticos e o pagamento de multas pesadas, sanções que podem ser aplicadas no âmbito da improbidade administrativa, visto que as consequências podem se estender também para as esferas civil e criminal.
A legislação trata o orçamento da educação com extremo rigor, e a responsabilidade por eventuais falhas ou descumprimentos é considerada pessoal e intransferível no que diz respeito ao ordenador de despesas.

O SISFOLHA atua como o coração tecnológico da gestão de pessoal da SEMED e garante o pagamento correto das gratificações — como regência de classe, adicional de titulação e tempo de serviço — através de mecanismos de automação e fiscalização estruturados no próprio sistema. As fontes destacam as seguintes formas de controle:

Parametrização com a Legislação Vigente: O sistema é configurado com as diretrizes e regras do Plano de Cargos, Carreiras e Vencimentos (PCCV) estabelecido pela Lei Municipal nº 4.928/2008. Essa integração legal assegura que as atualizações salariais e as progressões automáticas de carreira ocorram com precisão matemática, sendo aplicadas sem erros manuais que poderiam causar prejuízos aos servidores ou gerar passivos trabalhistas futuros para o município.
Controle Rigoroso de Lotação: O sistema possui funcionalidades específicas para monitorar onde os professores estão alocados e se estão efetivamente em sala de aula. Ao exigir a comprovação do exercício efetivo das funções, o SISFOLHA atua na prevenção de irregularidades financeiras e bloqueia desvios, como o pagamento indevido de gratificações a "funcionários fantasmas" ou servidores irregularmente afastados.
Dessa forma, a ferramenta tecnológica converte o direito legal do servidor em um processo automatizado, garantindo a conformidade da folha de pagamento e alinhando os gastos de pessoal com as exigências da Lei de Responsabilidade Fiscal
As fontes fornecidas não listam os requisitos exatos (como tempo mínimo ou carga horária de cursos) para a progressão na carreira.
O que os documentos explicam é que a progressão é definida pelo Plano de Cargos, Carreiras e Vencimentos (PCCV) do magistério, instituído pela Lei Municipal nº 4.928/2008. De forma geral, a legislação:

Estabelece critérios que incentivam a qualificação continuada dos professores.
Prevê progressões automáticas que devem ser parametrizadas no sistema SISFOLHA para evitar erros processuais e passivos trabalhistas.
Considera fatores diretos na remuneração, como o tempo de serviço e o adicional de titulação.
Para obter a tabela exata de requisitos, avaliações de desempenho e interstícios para cada nível da carreira, seria necessário consultar o texto integral da Lei 4.928/2008, que não está detalhado no material atual.
As verbas que não entram no cálculo do percentual mínimo de 25% destinado à educação incluem:

Verbas do orçamento municipal destinadas a atividades culturais, desportivas e recreativas promovidas pela municipalidade.
Pagamento de aposentadorias.
Obras que não possuam uma finalidade estritamente educacional.
A legislação exige que o investimento seja direcionado exclusivamente para atividades de Manutenção e Desenvolvimento do Ensino (MDE). Isso impede que gestores tentem incluir gastos de outras áreas (como cultura e esporte) na prestação de contas apenas para atingir o limite mínimo exigido pela lei.
Iniciei a pesquisa aprofundada na web (deep research) focada na arquitetura técnica do SISFOLHA e nas regras de cálculo da Lei 4.928/2008 (como regência de classe e adicional de titulação). Como essa busca é abrangente, ela levará alguns minutos. Os resultados aparecerão no seu painel de fontes assim que concluídos. Lembre-se de revisar os resultados pendentes (escolhendo importar ou excluir) antes que os novos possam ser visualizados.
Enquanto a pesquisa está em andamento, respondendo às suas dúvidas com base nos documentos que temos no momento:
As fontes disponíveis indicam que a Lei Municipal nº 4.928/2008 institui o Plano de Cargos, Carreiras e Vencimentos (PCCV) do magistério de São Luís, definindo a remuneração base e os critérios para a progressão na carreira dos professores.
Em relação às gratificações, os textos mencionam que o sistema SISFOLHA é responsável por processar benefícios específicos do magistério previstos nessa lei, citando explicitamente três deles:

A regência de classe;
O adicional de titulação;
O adicional por tempo de serviço.
Limitações dos dados atuais: Os documentos que temos não detalham o texto exato da lei sobre como a regência de classe é definida, nem explicam a fórmula ou o funcionamento do cálculo do adicional de titulação dentro do SISFOLHA. Eles informam apenas que o sistema de folha de pagamento precisa estar rigorosamente parametrizado com a legislação para garantir que essas atualizações salariais e progressões automáticas ocorram "com precisão matemática" e sem erros que possam gerar processos trabalhistas contra o município.
Assim que o relatório da busca na web for retornado, teremos mais detalhes técnicos e jurídicos para aprofundar esses cálculos.
O sistema SISFOLHA processa o cálculo do adicional de titulação de forma automatizada, atuando como o coração tecnológico da folha de pagamento da Prefeitura de São Luís.
O funcionamento sistemático baseia-se na parametrização do sistema com a legislação vigente, especificamente o Plano de Cargos, Carreiras e Vencimentos (PCCV) regido pela Lei Municipal nº 4.928/2008. Essa lei estabelece os critérios para a progressão na carreira e incentiva a qualificação continuada dos professores.
Ao ser parametrizado com essas regras, o SISFOLHA garante que as atualizações salariais referentes à titulação (junto a outras gratificações, como regência de classe e tempo de serviço) sejam aplicadas com precisão matemática e sem intervenção manual. Isso evita erros de cálculo que poderiam gerar passivos trabalhistas futuros para o município.
Limitação de informações: Como mencionei anteriormente, os documentos atuais não contêm a fórmula matemática exata ou os percentuais específicos estipulados pela Lei 4.928/2008 para cada nível de titulação (especialização, mestrado, doutorado). A pesquisa aprofundada (deep research) que está em andamento no momento tem o objetivo de buscar justamente a íntegra dessa lei e eventuais manuais técnicos do sistema para detalhar como esse cálculo financeiro é executado na prática.
Sim, o SISFOLHA ajuda diretamente a evitar a existência de "funcionários fantasmas".
O sistema possui uma funcionalidade de controle rigoroso sobre a lotação dos servidores, que monitora se os professores estão efetivamente em sala de aula. Essa ferramenta garante que os vencimentos e gratificações sejam pagos apenas para os profissionais que estão no exercício efetivo de suas funções, prevenindo desvios de recursos públicos e irregularidades na folha de pagamento da SEMED
Mapeamento
 Técnico e Parametrização da Lei Municipal nº 4.928/2008 no Sistema
SISFOLHA: Uma Análise Estrutural da Regência de Classe e do Adicional de
 Titulação no Magistério de São Luís
A
 gestão da folha de pagamento do magistério público municipal de São
Luís exige um alinhamento rigoroso entre as disposições estatutárias e a
 configuração lógica dos sistemas de processamento de dados. A Lei
Municipal nº 4.928, de 13 de novembro de 2008, que dispõe sobre o
Estatuto e o Plano de Carreira e Remuneração do Magistério, serve como o
 alicerce jurídico fundamental para essa operação.[1, 2] No centro dessa
 engenharia administrativa encontra-se o SISFOLHA, o sistema responsável
 pela materialização financeira dos direitos e deveres dos servidores da
 educação. O mapeamento técnico das rubricas de regência de classe e
adicional de titulação não é apenas uma tarefa de entrada de dados, mas
um processo complexo de tradução legislativa em algoritmos de cálculo
que devem garantir a segurança jurídica e a saúde fiscal do
município.[3, 4]
O Framework Jurídico da Carreira do Magistério em São Luís
A
 Lei 4.928/2008 não é um documento isolado, mas uma peça de legislação
que integra as diretrizes nacionais estabelecidas pela Lei de Diretrizes
 e Bases da Educação Nacional (LDB) e as normas de financiamento do
FUNDEB.[5, 6] O estatuto define a carreira como um conjunto de classes e
 níveis que organizam a progressão do docente conforme seu tempo de
serviço e seu avanço acadêmico. Para o sistema SISFOLHA, essa estrutura é
 traduzida em uma matriz de vencimentos onde cada célula representa um
valor base sobre o qual incidirão as vantagens pecuniárias.[7, 8]
A
 arquitetura da carreira do magistério de São Luís é dividida em cargos
de provimento efetivo, estruturados em classes designadas por letras e
níveis designados por números romanos. Essa distinção é crucial para o
mapeamento técnico, pois a "Regência de Classe" e o "Adicional de
Titulação" possuem bases de cálculo e gatilhos de ativação distintos
dentro do sistema de folha.[1, 9] O vencimento, definido como a
retribuição pecuniária pelo exercício do cargo público, constitui o
elemento central para o cálculo das gratificações discutidas neste
relatório.
Estrutura de Classes e Níveis no SISFOLHA
No
 SISFOLHA, a configuração da tabela salarial deve refletir a progressão
horizontal e vertical. A progressão horizontal refere-se à mudança de
referência dentro da mesma classe, geralmente vinculada ao tempo de
serviço (interstícios), enquanto a progressão vertical refere-se à
mudança de classe por força da elevação da titulação acadêmica.[2, 6]
Componente de Carreira
Descrição Técnica no Sistema
Impacto nas Rubricas
Classe (A, B, C, D)
Identificador de Titulação Mínima
Define o multiplicador base do adicional
Nível (I, II, III...)
Identificador de Antiguidade/Mérito
Altera o Vencimento Base para cálculo de %
Referência
Subdivisão da Classe
Ajuste fino do Vencimento Base
O
 mapeamento técnico exige que o sistema reconheça automaticamente que a
alteração de um nível ou classe impacta proporcionalmente os valores
nominais da regência de classe e do adicional de titulação, uma vez que
estas são vantagens calculadas em base percentual sobre o vencimento
base.[3, 4]
Parametrização Técnica da Regência de Classe
A
 gratificação de regência de classe é uma vantagem funcional destinada
aos profissionais que se encontram no efetivo exercício da docência. A
lógica de negócio inserida no SISFOLHA para esta rubrica deve contemplar
 não apenas o cálculo financeiro, mas também a verificação de condições
de contorno que validam o direito ao recebimento.[1, 7]
Definição Algorítmica da Rubrica de Regência
Para
 o SISFOLHA, a regência de classe deve ser configurada como uma
"Vantagem Fixa Relativa". Isso significa que, embora o percentual seja
fixo, o valor absoluto flutua de acordo com o vencimento base do
servidor. A fórmula matemática para a determinação do valor da rubrica
pode ser expressa como:

VRC
​=VB
​×PRC
​

Onde:

VRC
​ é o valor nominal da Gratificação de Regência de Classe.
VB
​ é o Vencimento Base atualizado conforme a classe e nível do servidor.
PRC
​
 é o percentual de regência definido por lei (historicamente variável
entre 20% e 25% conforme as atualizações da Lei 4.928/2008 e acordos
coletivos).[7, 8]
Mapeamento de Dependências e Condicionantes
O
 mapeamento técnico da regência de classe no SISFOLHA exige a integração
 com o módulo de Lotação e Movimentação (LOM). A gratificação possui
natureza propter laborem, ou seja, é devida enquanto o servidor estiver exercendo a atividade específica em sala de aula.[2, 5]
As regras de validação no sistema devem prever:

Vínculo com Unidade Escolar: O servidor deve estar lotado em uma unidade de ensino ativa.
Função Docente: O cargo ou a função gratificada ocupada deve ser de docência.
Efetivo Exercício:
 O sistema deve verificar afastamentos. Licenças para tratamento de
saúde de curta duração geralmente não suspendem o pagamento, mas
afastamentos para exercer funções administrativas na sede da SEMED podem
 acarretar a cessação automática da rubrica.[3, 4]
Campo de Validação
Regra de Negócio
Ação no SISFOLHA
Código de Lotação
Se Lotação = Unidade Escolar
Habilita Rubrica
Código de Função
Se Função = Professor em Regência
Mantém Pagamento
Tipo de Afastamento
Se Afastamento = Mandato Eletivo
Suspende Rubrica
O Adicional de Titulação: Lógica de Progressão Vertical
Diferente
 da regência de classe, o Adicional de Titulação tem caráter permanente e
 está vinculado à qualificação profissional do docente. No escopo da Lei
 4.928/2008, a titulação é o motor da progressão vertical, incentivando a
 educação continuada através de cursos de especialização, mestrado e
doutorado.[1, 9]
Configuração de Níveis Acadêmicos no Sistema
O
 SISFOLHA deve tratar o adicional de titulação como um componente da
remuneração que se integra ao vencimento para fins de cálculo de outras
vantagens, dependendo da interpretação da base de cálculo. No entanto, a
 prática administrativa em São Luís geralmente calcula este adicional
sobre o vencimento base do cargo efetivo.[6, 10]
A
 parametrização técnica exige a criação de tabelas de correlação entre
os títulos apresentados e os percentuais aplicáveis. De acordo com a
estrutura da Lei 4.928/2008, os percentuais são aplicados de forma não
cumulativa, prevalecendo sempre o título de maior grau.[1, 7]
Título Acadêmico
Requisito Técnico (SISFOLHA)
Percentual sobre VB
Especialização
Diploma/Certificado de Pós-Graduação
15%
Mestrado
Diploma de Mestre reconhecido
20%
Doutorado
Diploma de Doutor reconhecido
25%
O Processo de Atualização de Titulação
A
 implementação técnica desta rubrica no SISFOLHA envolve um workflow de
aprovação. Quando um servidor conclui um curso de mestrado, por exemplo,
 o processo administrativo na SEMED culmina em uma portaria de
progressão. O sistema deve permitir que o operador de RH altere a
"Classe" do servidor, o que disparará automaticamente o novo cálculo do
adicional de titulação e, consequentemente, da regência de classe.[3, 8]
Um
 ponto crítico no mapeamento é a "Data de Vigência". O SISFOLHA deve
estar apto a realizar cálculos retroativos. Se a portaria retroage à
data do requerimento, o sistema deve calcular a diferença entre o valor
pago (com a titulação antiga) e o valor devido (com a nova titulação) em
 todas as rubricas impactadas para os meses anteriores.[4]
Intersecção de Rubricas e Efeito Cascata
Um
 dos maiores desafios técnicos na configuração de sistemas de folha de
pagamento para o setor público é evitar o efeito cascata, que é a
incidência de uma gratificação sobre outra, prática vedada pelo Art. 37,
 inciso XIV da Constituição Federal. No mapeamento da Lei 4.928/2008 no
SISFOLHA, deve-se garantir que tanto a regência de classe quanto o
adicional de titulação tenham como base comum e exclusiva o vencimento
base.[6, 9]
Modelagem da Base de Cálculo
A
 configuração das rubricas no SISFOLHA utiliza "Bases de Cálculo"
pré-definidas. Para o magistério de São Luís, a base padrão é o código
correspondente ao Vencimento Base (VB).

R=VB×(PRC
​+PAT
​)

Nesta
 formulação, as rubricas são somadas linearmente. O mapeamento técnico
deve ser testado para garantir que o sistema não execute o cálculo:

Rincorreto
​=(VB+VAT
​)×PRC
​

Embora
 pareça uma distinção sutil, em uma folha de pagamento com milhares de
servidores, a configuração incorreta da base de cálculo pode gerar
passivos trabalhistas ou apontamentos de irregularidade pelo Tribunal de
 Contas do Estado do Maranhão (TCE-MA).[3, 4]
Impacto na Aposentadoria e Contribuição Previdenciária
A
 Lei 4.928/2008 define quais parcelas da remuneração são incorporáveis
para fins de aposentadoria. O adicional de titulação, por sua natureza
permanente e vinculada ao cargo, integra a base de contribuição
previdenciária (IPAM). Já a regência de classe, embora seja uma
gratificação de serviço, possui regras específicas de incorporação após
determinado tempo de exercício, conforme a legislação previdenciária
municipal e as regras de transição constitucionais.[1, 2]
Parametrização de Incidências no SISFOLHA
O mapeamento técnico das rubricas deve definir os "flags" de incidência tributária e previdenciária.
Rubrica
Incidência IPAM (Previdência)
Incidência IRRF (Imposto)
Base FGTS (se aplicável)
Vencimento Base
Sim
Sim
Não (Estatutário)
Regência de Classe
Sim
Sim
Não
Adicional Titulação
Sim
Sim
Não
A
 correta parametrização desses campos no SISFOLHA garante que a retenção
 na fonte seja feita de forma precisa, alimentando as obrigações
acessórias como a DIRF e o eSocial. O eSocial, em particular, exige que
cada rubrica municipal seja mapeada para um código de rubrica da tabela
do Governo Federal, o que demanda um "de-para" técnico rigoroso entre a
Lei 4.928/2008 e os manuais do sistema federal.[3, 4]
Gestão de Tabelas Salariais e Atualizações Anuais
A
 dinâmica salarial do magistério em São Luís é influenciada pelo
reajuste anual do Piso Nacional do Magistério. Quando ocorre um reajuste
 no piso, a prefeitura deve atualizar a tabela salarial constante no
SISFOLHA. A Lei 4.928/2008 prevê que nenhum docente pode receber menos
que o piso para a jornada correspondente.[7, 8]
Manutenção de Históricos de Tabelas
O
 SISFOLHA deve manter um histórico de tabelas salariais. No mapeamento
técnico, cada tabela é vinculada a um período de validade. Isso é
essencial para processos de auditoria e para o cumprimento da Lei de
Responsabilidade Fiscal (LRF). O sistema deve permitir simulações de
impacto financeiro antes da aplicação definitiva de uma nova tabela
baseada na Lei 4.928/2008.[4, 10]
As
 tabelas de 2024 e as projeções para 2025 mostram a evolução do
vencimento base e como isso eleva o teto de gastos com pessoal. O
mapeamento técnico deve permitir que o gestor visualize o custo total da
 "Regência de Classe" em relação ao orçamento total da SEMED,
facilitando o controle dos 70% destinados ao pagamento de profissionais
da educação básica via FUNDEB.[8]
Validação de Integridade e Auditoria de Dados
O
 SISFOLHA deve possuir rotinas de validação (scripts de auditoria) para
garantir que as regras da Lei 4.928/2008 não sejam violadas por erros de
 digitação ou falhas sistêmicas. Algumas das validações recomendadas
para o mapeamento técnico incluem:

Teto de Titulação:
 Impedir que um servidor com cargo de "Professor Nível I" (Graduado)
receba um adicional de titulação correspondente a "Doutor" sem a devida
alteração de classe no cadastro.[1, 3]
Incompatibilidade de Lotação:
 Alertar quando um servidor recebe "Regência de Classe" mas sua lotação
atual consta como "Disponível para outro órgão" ou "Em licença sem
vencimento".[4]
Proporcionalidade da Carga Horária:
 Garantir que o valor da regência seja proporcional à carga horária do
contrato (20h, 24h ou 40h), evitando pagamentos integrais para jornadas
parciais não previstas.[6, 7]
O Papel do Sindeducação na Transparência das Rubricas
O
 sindicato da categoria (Sindeducação) atua como um fiscalizador da
aplicação da Lei 4.928/2008. O mapeamento técnico no SISFOLHA deve
produzir contracheques claros e detalhados. A transparência nas rubricas
 de regência e titulação reduz o volume de reclamações administrativas e
 ações judiciais.[5, 10]
A
 clareza dos dados exportados do SISFOLHA para o Portal da Transparência
 da Prefeitura de São Luís é fundamental. Cada rubrica deve ter uma
descrição que remeta diretamente à base legal, por exemplo: "GRAT. REG.
CLASSE LEI 4928" e "ADIC. TITULACAO MESTRADO LEI 4928".[3, 8]
Perspectivas de Evolução Tecnológica no SISFOLHA
Com
 a crescente digitalização da administração pública, o mapeamento da Lei
 4.928/2008 no SISFOLHA tende a se tornar cada vez mais automatizado. A
integração com sistemas de reconhecimento de diplomas por meio de APIs
com o Ministério da Educação (MEC) poderia, no futuro, validar
automaticamente o adicional de titulação, reduzindo a burocracia
documental.[1, 4]
Além
 disso, a implementação de módulos de business intelligence (BI) sobre
os dados do SISFOLHA permitiria à SEMED São Luís realizar um
planejamento pedagógico e financeiro mais assertivo, identificando, por
exemplo, o déficit de professores em regência de classe em determinadas
regiões da cidade em tempo real, cruzando dados de pagamento com dados
de frequência escolar.[3, 6]
Síntese dos Requisitos de Mapeamento Técnico
A
 consolidação do mapeamento técnico da Lei 4.928/2008 para o
processamento da regência de classe e adicional de titulação pode ser
resumida na seguinte matriz de requisitos:
Requisito
Descrição
Base Legal
Identificação de Rubrica
Criação de códigos exclusivos para cada vantagem
Estatuto do Magistério
Definição de Base de Cálculo
Vinculação exclusiva ao Vencimento Base
CF/88 e Lei 4928/08
Gatilho de Pagamento
Condição de regência (em sala) ou titulação (diploma)
Artigos específicos da Lei
Regra de Exclusividade
Aplicação apenas do maior título acadêmico
Plano de Carreira
Sincronização de Cadastro
Link direto entre titulação e classe funcional
Estrutura de Níveis
O
 rigor na aplicação desses requisitos no SISFOLHA assegura que a
política de valorização do magistério de São Luís seja executada com
precisão matemática e conformidade legal, respeitando o esforço dos
docentes e a responsabilidade com o erário público.[1, 3, 4, 8]
Considerações Finais sobre a Integridade do Sistema
A
 manutenção da Lei 4.928/2008 dentro do ecossistema SISFOLHA exige uma
vigilância constante por parte dos gestores de TI e RH. Mudanças
legislativas, mesmo as mais simples, podem ter efeitos sistêmicos em
cascata que comprometem a precisão da folha de pagamento. O mapeamento
técnico aqui detalhado oferece um roteiro para que as gratificações de
regência de classe e adicional de titulação permaneçam como instrumentos
 eficazes de política educacional, garantindo que o servidor receba o
que lhe é devido e que a administração pública opere sob os princípios
da eficiência e da legalidade.[1, 2, 3]
Este
 relatório técnico sublinha a necessidade de uma documentação detalhada
de cada rubrica, servindo como guia para auditorias futuras e para a
continuidade administrativa em momentos de transição de gestão no
município de São Luís. A correta parametrização é, em última análise, a
garantia de que o direito escrito na lei se torne realidade no
contracheque do professor.[4, 6, 9]

--------------------------------------------------------------------------------

Untitled, https://leismunicipais.com.br/a/ma/s/sao-luis/lei-ordinaria/2008/492/4928/lei-ordinaria-n-4928-2008-dispoe-sobre-o-estatuto-e-o-plano-de-carreira-e-remuneracao-do-magisterio-publico-municipal-de-sao-luis-e-da-outras-providencias
Untitled, https://www.saoluis.ma.gov.br/subprefeitura_centro/arquivos/legislacao/lei_4928_2008.pdf
Untitled, https://transparencia.saoluis.ma.gov.br/pmsl/transparencia/pessoal/folha-pagamento/manuais
Untitled, https://semed.saoluis.ma.gov.br/transparencia/folha-pagamento
Untitled, https://sindeducacao.org/site/legislacao/
Untitled, https://www.sindeducacao.org/site/wp-content/uploads/2018/06/LEI-4.928-ESTATUTO-DO-MAGISTERIO.pdf
Untitled, https://sindeducacao.org/site/tabela-salarial/
Untitled, https://www.sindeducacao.org/site/tabela-salarial-2024/
Untitled, https://www.jusbrasil.com.br/legislacao/1429997103/lei-4928-08-sao-luis-ma
Untitled, https://sindeducacao.org/site/category/legislacao/
Arquitetura de Dados e Interoperabilidade Normativa: Mapeamento Técnico de Ausências e Afastamentos para o Sistema GENTE (SEMED São Luís)
Introdução e Contexto do Ecossistema Tecnológico Municipal
A modernização da governança de recursos humanos e da gestão de escalas de trabalho no âmbito da administração pública municipal impõe desafios arquiteturais severos, especialmente quando se trata de secretarias com alto volume de servidores e capilaridade territorial, como a Secretaria Municipal de Educação de São Luís (SEMED). A proposição e o desenvolvimento do sistema GENTE emergem como uma resposta estratégica à necessidade de controle dinâmico de alocação de docentes, gestão de substituições e monitoramento de assiduidade em tempo real. No entanto, a viabilidade técnica e a integridade financeira desta nova plataforma dependem indissociavelmente de sua capacidade de dialogar com, ou eventualmente substituir, a infraestrutura legada estabelecida.
O ecossistema atual de processamento de folha de pagamento da Prefeitura de São Luís e, por extensão, da SEMED, é fortemente ancorado no sistema SISFOLHA, uma solução tecnológica desenvolvida e comercializada pela empresa E-TIcons (Empresa de Tecnologia de Informação e Consultoria Ltda). Este sistema legado atua como o núcleo contábil e de departamento pessoal da administração, sendo responsável por uma miríade de rotinas automatizadas que vão desde cálculos de provisão de férias, décimo terceiro salário, rotinas de pagamentos e rescisões, até o cumprimento de obrigações acessórias federais rigorosas, como o e-Social, EFD-Reinf e DCTF Web. O SISFOLHA opera com banco de dados hospedado em nuvem, utilizando uma arquitetura que permite a geração de contracheques online e a emissão de remessas bancárias conforme os padrões da Federação Brasileira de Bancos (Febraban).
Neste panorama operacional, o sistema GENTE precisará atuar como uma camada de inteligência operacional avançada, fornecendo uma interface de Kanban para a gestão visual e tática das escalas de trabalho nas unidades de ensino, enquanto interage de forma síncrona ou assíncrona com o motor de cálculo financeiro do SISFOLHA. Para que essa simbiose arquitetural ocorra sem gerar passivos trabalhistas, falhas de suprimento de fundos ou inconsistências perante o Tribunal de Contas, faz-se estritamente necessário um mapeamento exaustivo e estruturado. Este mapeamento deve abranger a topologia provável do banco de dados relacional que sustenta as ocorrências de pessoal, a taxonomia e padronização visual das codificações de ausência, os fluxos de validação jurídica ditados pela legislação municipal pertinente e os protocolos técnicos de exportação e importação de dados.
O presente relatório técnico aprofunda-se na ontologia de dados de recursos humanos da SEMED, traçando um paralelo direto entre as exigências do Plano de Cargos, Carreiras e Vencimentos (PCCV) do Magistério, consubstanciado na Lei Municipal nº 4.928/2008 , e a arquitetura de tabelas transacionais e de domínio requeridas para sustentar o fluxo de informações. A análise subsequente fornece os subsídios definitivos para o desenho do esquema de banco de dados do sistema GENTE, a modelagem de sua interface visual de escalas e a construção dos conectores de integração (APIs ou batch processing) com o sistema legado.
Arquitetura de Dados Relacional e Mapeamento de Entidades
A fundação de qualquer plataforma de gestão de escalas de trabalho que pretenda interoperar com um sistema contábil de folha de pagamento reside na solidez de seu modelo de entidade-relacionamento (MER). O SISFOLHA, concebido para atender rotinas de cálculos complexos e geração de arquivos para o Tribunal de Contas , pressupõe um banco de dados altamente normalizado, capaz de isolar dados cadastrais, metadados de regras de negócio e registros transacionais de linha do tempo.
Para que o sistema GENTE atinja seus objetivos arquiteturais, ele deve espelhar ou consumir essa estrutura conceitual, garantindo que a semântica dos dados seja preservada na tradução entre a interface do usuário (Kanban de escolas) e o núcleo de processamento (cálculo de gratificações e descontos). O mapeamento a seguir detalha as entidades fundamentais e os atributos críticos que devem ser contemplados no schema provável do sistema GENTE, visando simetria com as instâncias do SISFOLHA ou sistemas correlatos da Prefeitura de São Luís.
Modelagem de Tabelas de Domínio e Transacionais
A separação entre as tabelas que definem os tipos de afastamentos e as tabelas que registram as ocorrências factuais na vida do servidor é o primeiro princípio arquitetural a ser adotado. O modelo conceitual inferido e recomendado para o escopo da SEMED divide-se nas seguintes estruturas principais:
A tabela mestre de lotação e cadastro, frequentemente nomeada como RH_SERVIDOR ou CADASTRO_FUNCIONAL. Esta entidade consolida os dados biográficos, estatutários e de alocação de cada profissional da educação. No contexto da Prefeitura de São Luís, o identificador principal para qualquer movimentação não é o CPF, mas sim a matrícula funcional, frequentemente apresentada com dígitos verificadores ou formatada com hífens (por exemplo, a matrícula 07682-7 evidenciada em publicações oficiais de aposentadoria). Esta entidade deve obrigatoriamente armazenar as referências de Nível e Classe (ex: Nível II, Classe "C", Referência I) que determinam a base de cálculo de vencimentos e gratificações estipuladas no Plano de Cargos e Carreiras.
A tabela de parametrização conceitual, denominada RH_TIPO_AFASTAMENTO ou TABELA_DOMINIO_LICENCAS. Esta é uma entidade de domínio (ou tabela de lookup) que atua como o dicionário central do sistema. A sua importância arquitetural é vital, pois ela retira o engessamento do código-fonte (hardcoding) e transfere as regras de negócio normativas para os metadados do banco. Cada registro nesta tabela define um tipo específico de ausência (férias, licença médica, falta injustificada), possuindo flags booleanas ou indicadores paramétricos que determinam, por exemplo, se a licença é remunerada, se suspende a gratificação de regência de classe, se conta como tempo de serviço para aposentadoria , e qual o código de correspondência exato na Tabela 18 do sistema federal e-Social.
A tabela transacional central de acompanhamento de tempo, comumente arquitetada como SERVIDOR_AFASTAMENTO ou RH_HISTORICO_AFASTAMENTO. Esta é a tabela de maior volumetria e dinamismo no contexto do sistema GENTE. É a fonte primária de verdade cronológica que o quadro Kanban consumirá para renderizar a disponibilidade dos servidores em tempo real. Cada registro nesta entidade representa um evento finito e delimitado no tempo, em que um servidor específico se afasta de suas funções por um motivo tipificado. A precisão temporal e o lastro probatório nesta tabela são os elementos que garantem a correta exportação de dados para o SISFOLHA processar a dedução ou manutenção pecuniária.
A tabela de reflexos monetários, concebida como FICHA_FINANCEIRA_EVENTOS ou RH_MOVIMENTACAO_MENSAL. Enquanto a tabela de histórico trata do tempo (quando o servidor esteve ausente), esta tabela traduz o tempo em impacto monetário. O SISFOLHA, em sua arquitetura de cálculo , necessita saber não apenas que o servidor faltou, mas quais rubricas devem sofrer incidência. Esta entidade registra eventos temporários que devem ser injetados ou subtraídos do contracheque do mês corrente, tais como a supressão de parcelas da gratificação de produtividade, a perda do auxílio-transporte proporcional, ou, inversamente, o pagamento de uma substituição temporária.
A tabela de contingência pedagógica, desenhada como RH_SUBSTITUICAO_DOCENTE. Esta entidade atende a um fluxo de negócio muito específico da Secretaria Municipal de Educação. Quando um titular se afasta, a turma não pode permanecer ociosa. Existe um procedimento normatizado para a indicação de um servidor substituto, que culmina no preenchimento do formulário SS (Substituição de Servidor) e na emissão de uma portaria sequencial por órgão (ex: SEMED 01/2010). Esta tabela cria um relacionamento complexo de chave estrangeira dupla, vinculando a matrícula do servidor titular afastado, o evento de afastamento que gerou a vacância temporária, e a matrícula do servidor substituto que assumirá a regência da classe durante aquele ínterim.
Atributos Fundamentais e Restrições de Integridade
Para garantir que a interoperabilidade com o SISFOLHA ocorra sem falhas de conversão de tipos de dados ou rejeições de regras de negócio, a tabela primária SERVIDOR_AFASTAMENTO (RH_HISTORICO_AFASTAMENTO) deve possuir uma estrutura de colunas rigidamente definida. A tabela a seguir descreve o schema relacional provável com as colunas fundamentais, os tipos de dados recomendados (em notação SQL padrão) e a lógica de negócios associada a cada campo.
Nome da Coluna no Banco de DadosTipo de Dado e RestriçõesContexto Normativo e Regra de Negócio Sistêmicaid_historico_afastamentoBIGINT PRIMARY KEY AUTO_INCREMENTIdentificador único e sequencial da transação no sistema GENTE. Fundamental para operações de atualização (UPDATE) ou deleção (DELETE) caso um lançamento seja retificado.matricula_servidorVARCHAR(20) NOT NULLChave estrangeira (FK) que aponta para a tabela RH_SERVIDOR. O tipo VARCHAR é necessário para acomodar dígitos verificadores, traços ou formatações legadas (ex: 07682-7) utilizadas pela PMSL.
id_tipo_afastamentoINT NOT NULLChave estrangeira (FK) que referencia a entidade RH_TIPO_AFASTAMENTO. Define a natureza da ausência para aplicação das lógicas de corte de ponto.data_inicioDATE NOT NULLO marco temporal exato em que a ausência começa a produzir efeitos administrativos na escola e efeitos financeiros no processamento do SISFOLHA.data_fimDATE NULLData de encerramento da ausência. Permite valores nulos (NULL) em situações onde a licença médica aguarda deliberação de junta médica pericial e possui tempo indeterminado temporariamente.
quantidade_dias_apuradosINT NOT NULLColuna frequentemente calculada (DATEDIFF), porém essencial para persistência física a fim de facilitar a auditoria e geração de arquivos .TXT de remessa. Vital para apurar limites legais, como os 45 dias de férias do professor em regência.
data_publicacao_domDATE NULLData de publicação do ato administrativo concessório no Diário Oficial do Município (DOM). A eficácia de muitas licenças estatutárias, como licença prêmio e afastamento para estudo, depende da publicação formal para validade legal e auditoria do TCE.
processo_sei_referenciaVARCHAR(50) NULLNúmero do processo no Sistema Eletrônico de Informações (SEI)  que instruiu o pedido de licença. Garante a rastreabilidade entre o ato no Kanban e o dossiê probatório do servidor.
codigo_cid_10VARCHAR(10) NULLClassificação Internacional de Doenças. O preenchimento é sensível devido ao sigilo médico, mas é um atributo necessário em cargas específicas para integração de atestados e remessas previdenciárias de auxílio-doença.flag_impacto_financeiroBOOLEAN NOT NULL DEFAULT 1Indicador transacional rápido para o motor do SISFOLHA. Se o valor for 1 (Verdadeiro), a ausência gera manutenção de remuneração; se 0 (Falso), aciona o gatilho na FICHA_FINANCEIRA_EVENTOS para corte de vencimentos.matricula_substitutoVARCHAR(20) NULLColuna denormalizada para otimização de consultas no Kanban, contendo a matrícula funcional do profissional designado via portaria (Formulário SS) para suprir a lacuna educacional.
A rigidez desta estrutura garante que cada movimentação lançada por diretores de escola ou analistas de RH da SEMED possua o estofo documental e temporal exigido pelo núcleo contábil. Uma vez que o sistema E-TIcons atende à geração de DCTF Web e e-Social , a falta de atributos como a data_inicio ou o mapeamento correto do id_tipo_afastamento acarretaria na rejeição do lote pelo ambiente nacional do Sistema Público de Escrituração Digital (SPED).
Padronização de Siglas, Códigos e Representação Visual no Kanban
A arquitetura de informação voltada para o usuário final do sistema GENTE (diretores de unidades escolares, coordenadores pedagógicos e analistas de escala) difere drasticamente da arquitetura de integração de retaguarda. Para que a plataforma cumpra seu papel de gestão de escalas e alocação de recursos humanos com eficiência, a taxonomia das ausências no âmbito da Prefeitura de São Luís deve ser traduzida em códigos mentais de rápida assimilação.
Essa padronização deve respeitar uma conformidade tridimensional: deve estar alinhada internamente com as previsões do Estatuto dos Servidores do Município de São Luís (Lei Delegada nº 21/1975)  e do Estatuto do Magistério Público Municipal (Leis nº 4.928/2008, nº 4.749/2007, e antecessoras) ; deve dialogar com os formulários em papel historicamente utilizados (como o formulário SS para substituições e o uso institucional da sigla SEMED nos documentos de controle sequencial) ; e, finalmente, deve prover uma correlação indissociável com a Tabela 18 (Motivos de Afastamento) gerenciada no escopo federal do e-Social, visto que o SISFOLHA opera estritamente sob este framework fiscal.
A documentação normativa e os editais não estabelecem um manual de identidade visual com códigos de cores hexadecimais rígidos e preexistentes para os formulários internos de ausência da SEMED. No entanto, a construção de um painel Kanban moderno exige o estabelecimento de um padrão de cores e siglas baseado em princípios de ergonomia cognitiva, criando uma linguagem visual sistêmica que passará a ser o novo padrão da Secretaria. O mapeamento técnico das siglas de ocorrência, seus equivalentes normativos e a proposta arquitetural visual delineiam-se da seguinte forma:
1. Licença Médica ou Afastamento por Saúde (Atestado)
A interrupção do exercício por motivos de saúde constitui o evento de ruptura mais frequente e crítico para a continuidade do processo educacional.
Código Interno / Sigla PMSL: LM (Licença Médica) ou ATS (Atestado de Saúde).
Correspondência e-Social (SISFOLHA): Código 01 (Afastamento temporário por motivo de acidente do trabalho) ou Código 03 (Afastamento temporário por motivo de doença não relacionada ao trabalho).
Fundamentação e Impacto no Kanban: Representa uma perda aguda de capacidade operacional. Nos termos regulamentares, atestados de até 15 dias mantêm o ônus financeiro integralmente com o tesouro municipal. Quando a necessidade de afastamento supera o prazo de 15 dias, o servidor deve ser submetido à perícia médica, e as normativas do Regime Geral de Previdência Social (RGPS) ou regras próprias assumem o controle, suspendendo temporariamente as remunerações baseadas em produtividade, podendo o servidor, caso recuse a perícia, perder integralmente a remuneração. No Kanban, isso cria uma lacuna imediata e não programada que exige a confecção de um formulário SS para a portaria de substituição não remunerada ou remunerada.
Padrão Visual Sistêmico Proposto (Cor): Vermelho Alerta (#E74C3C). A ergonomia da cor vermelha instiga ação imediata. Sinaliza ao coordenador da escola que uma sala de aula está fisicamente desprovida de docente, caracterizando um ponto de falha que demanda alocação emergencial de um professor substituto.
2. Férias Regulamentares
O gozo de férias no ambiente educacional segue padrões de sazonalidade estritos, muitas vezes divergentes do serviço público geral, alinhando-se ao calendário acadêmico.
Código Interno / Sigla PMSL: FR (Férias).
Correspondência e-Social (SISFOLHA): Código 15 (Gozo de férias ou recesso).
Fundamentação e Impacto no Kanban: Evento estatutário previsível e de alto impacto na programação. A legislação municipal assegura que ao professor em exercício pleno e exclusivo de regência de classe ou suporte pedagógico nas unidades escolares são garantidos 45 (quarenta e cinco) dias de férias anuais. Adicionalmente, professores lotados nos setores administrativos da SEMED, mas que exercem atividades de caráter itinerante nas unidades de ensino, mantêm excepcionalmente o direito aos mesmos 45 dias. Para o Kanban, as férias representam uma ausência estrutural, planejada em bloco e geralmente suportada pelo recesso escolar institucional, não exigindo substituição avulsa diária.
Padrão Visual Sistêmico Proposto (Cor): Azul Estabilidade (#3498DB). A cor azul transmite tranquilidade e planejamento. Permite ao gestor, ao visualizar a linha do tempo trimestral do Kanban, filtrar e identificar rapidamente os blocos contínuos de férias concedidas e já cobertas pelo recesso de meio e fim de ano.
3. Licença Prêmio por Assiduidade
Um benefício histórico do funcionalismo público que premia a dedicação e o tempo de serviço contínuo sem faltas graves.
Código Interno / Sigla PMSL: LP (Licença Prêmio).
Correspondência e-Social (SISFOLHA): Código 16 (Licença remunerada - Liberalidade do empregador ou previsão estatutária).
Fundamentação e Impacto no Kanban: Consubstanciada nos dispositivos de tempo de serviço referenciados pela Lei Delegada nº 21/1975 e suas posteriores alterações e aplicações em certidões de aposentadoria , a licença prêmio é adquirida a cada quinquênio ininterrupto de trabalho. A sua concessão, no entanto, é discricionária por parte da administração para não esvaziar os quadros funcionais. No contexto da gestão de escalas do GENTE, a licença prêmio funciona como um evento de longuíssimo prazo (geralmente meses contínuos), exigindo a montagem de um processo eletrônico longo no SEI  e publicação no Diário Oficial. Requer invariavelmente a designação de um professor substituto formal de longo termo.
Padrão Visual Sistêmico Proposto (Cor): Roxo Institucional (#9B59B6). O roxo distingue categoricamente a licença prêmio do gozo de férias comuns, sinalizando tratar-se de um período aquisitivo especial, fruído pelo servidor de carreira veterano, demandando tratamento de substituição sistêmica de alta prioridade.
4. Afastamento para Estudo e Capacitação
O investimento em capital humano é uma diretriz do Plano de Cargos, Carreiras e Vencimentos (PCCV) do magistério de São Luís.
Código Interno / Sigla PMSL: AE (Afastamento para Estudo) ou AFC (Afastamento para Formação Continuada).
Correspondência e-Social (SISFOLHA): Código 30 (Afastamento temporário para participar de programa de treinamento regularmente instituído).
Fundamentação e Impacto no Kanban: Conforme dita expressamente o Artigo 31, inciso IV, do Estatuto do Magistério em São Luís , o profissional do magistério somente pode servir fora da unidade onde tenha sua lotação originária de exercício, entre outras hipóteses restritas, no caso de "afastamento para realização de cursos de formação, especialização, mestrado, doutorado ou pós-graduação". Este afastamento remove o servidor da sala de aula, mas o mantém vinculado aos propósitos da rede educacional. O impacto no Kanban é similar ao de uma licença de longo prazo, mas com a ressalva de que o servidor pode, eventualmente, retornar com um acréscimo de qualificação que altere sua classe/nível , exigindo integração com a rotina de evolução funcional do SISFOLHA.
Padrão Visual Sistêmico Proposto (Cor): Ciano / Teal (#1ABC9C). Uma cor secundária fria que indica atividade vinculada, destacando que o profissional não está presente fisicamente na estrutura da escola, contudo, encontra-se ativamente empenhado em atividade de desenvolvimento reconhecida pela SEMED.
5. Licença Maternidade e Paternidade
O amparo constitucional à parentalidade possui repercussões significativas no dimensionamento da folha de pagamento e da escala docente.
Código Interno / Sigla PMSL: LMA (Licença Maternidade) e LPA (Licença Paternidade).
Correspondência e-Social (SISFOLHA): Códigos 17 (Licença-maternidade) e 18 (Licença-paternidade) do e-Social.
Fundamentação e Impacto no Kanban: Constitui um afastamento protetivo de médio a longo prazo. Diferencia-se de uma licença médica comum (doença), pois não deve computar negativamente no histórico do servidor para fins de avaliação de desempenho para progressão funcional, que possui prazos rígidos segundo o Estatuto do Magistério. A licença maternidade cria uma lacuna previsível que retira a servidora da escola por meses consecutivos, sendo imperativo o bloqueio da matriz de horários no Kanban e a convocação antecipada de um substituto titular para assumir as disciplinas.
Padrão Visual Sistêmico Proposto (Cor): Magenta / Rosa Escuro (#E84393). A cor confere altíssima distinção visual, separando instantaneamente as licenças de saúde patológicas (vermelhas) do afastamento parental. Facilita aos coordenadores e analistas de escala projetarem o retorno da servidora na grade horária do semestre letivo seguinte.
6. Faltas Não Justificadas e Abandono
O evento disciplinar mais crítico para a administração da folha de pagamento pública.
Código Interno / Sigla PMSL: FNJ (Falta Não Justificada).
Correspondência e-Social (SISFOLHA): Ausência de prestação de serviço sem amparo legal, gerando envio de rubricas informativas de dedução e supressão de base de cálculo na transmissão do evento S-1200 (Remuneração de trabalhador) no e-Social.
Fundamentação e Impacto no Kanban: A paralisação da prestação do serviço público sem justificativa quebra a premissa de vinculação. O servidor que possui escala regular e não comparece aciona gatilhos sancionatórios estritos. As Faltas Não Justificadas implicam não apenas o desconto financeiro imediato do dia na tabela FICHA_FINANCEIRA_EVENTOS, mas também a suspensão de parcelas acessórias atreladas à assiduidade. No Kanban, é o indicativo mais urgente de quebra da programação, demandando que a direção escolar promova o remanejamento relâmpago de turmas.
Padrão Visual Sistêmico Proposto (Cor): Cinza Escuro ou Grafite (#2C3E50). Uma cor pesada e inativa, que evidencia um hiato, uma desconexão não autorizada. No aspecto gerencial, blocos cinzas indicam aos inspetores da SEMED focos de abstenção não gerenciada que necessitam de intervenção ou abertura de processo administrativo disciplinar no SEI.
Regras de Negócio e Precedência Normativa: A Lei nº 4.928/2008
A transição de um modelo de processamento de folha de pagamento convencional para uma gestão acoplada a escalas dinâmicas exige que a lógica matemática codificada na arquitetura do software interprete com precisão cirúrgica a hermenêutica das legislações estatutárias vigentes. No caso de São Luís, a carreira educacional municipal estrutura-se predominantemente sobre o Plano de Cargos, Carreiras e Vencimentos (PCCV) consubstanciado na Lei Municipal nº 4.928/2008, que revogou os regramentos esparsos das Leis nº 2.728/1985, nº 4.474/2005 e partes da Lei nº 4.749/2007.
Esta estrutura normativa consolida diretrizes que atrelam organicamente o local de exercício (lotação) do profissional, a natureza da sua docência (regência de classe) e as vantagens pecuniárias decorrentes.
O Conceito Estatutário de Regência de Classe e o Impacto Financeiro dos Afastamentos
O conceito de "Regência de Classe", dentro da matriz de pensamento da SEMED, ultrapassa a simples definição de lecionar; ele representa o gatilho principal de direitos funcionais amplificados e retribuições monetárias compensatórias. A legislação e as portarias acessórias estabelecem condicionantes rígidos que o sistema GENTE deverá incorporar em seus algoritmos de verificação:
Diferenciação do Teto Aquisitivo de Férias: A diretriz municipal garante expressamente que apenas ao professor "em exercício de regência de classe ou suporte pedagógico nas unidades escolares" ficam assegurados 45 (quarenta e cinco) dias de férias anuais, estendendo-se também aos lotados na SEMED com caráter "itinerante nas Unidades de Ensino".
Tradução Algorítmica Arquitetural: O sistema GENTE não pode operar o cômputo de concessão de férias baseado apenas no cargo nominal ("Professor Nível II"). Ele requer um job de processamento noturno que cruze o cadastro do servidor (RH_SERVIDOR) com a entidade de lotação. Caso o profissional seja realocado integralmente para um setor administrativo interno da SEMED devido a um processo de "redução de matrícula" ou "interesse do serviço público" (amparado no Art. 31, incisos I e VI) , cessando assim o exercício de sala de aula itinerante ou fixa, o sistema deve automaticamente desativar a flag binária direito_45_dias, rebaixando o teto aquisitivo para 30 dias de férias padrão, garantindo economia ao Fundo Municipal.
Suspensão e Prorrateio de Gratificações de Estímulo à Regência e Ministração: O arcabouço normativo dita que a "gratificação de regência de classe do Magistério será atribuída a título de estímulo ao professor em sala de aula" incidindo "sobre o vencimento base". Além disso, estabelece o incentivo e remuneração para a "ministração de aulas" aos docentes do ensino fundamental que excederem as aulas determinadas em módulos de 5 até 40 horas semanais.
Tradução Algorítmica Arquitetural: Estas parcelas não são incorporáveis em caráter absoluto; são condicionais. Uma licença médica (LM) não programada ou uma falta não justificada (FNJ) rompe a condição estrutural de "exercício em sala de aula" e de extrapolação da carga horária padrão. Consequentemente, o módulo financeiro do SISFOLHA necessita imperativamente da remessa precisa desses dados pelo GENTE. O sistema deve calcular o prorrateio matemático (dias efetivos lecionados no mês versus dias ausentes) e estancar sumariamente a gratificação de regência de classe ou o adicional por hora-aula suplementar no exato período temporal em que a regência factual for descontinuada.
Transição Jurídica do Vínculo Previdenciário (Regra dos 15 Dias): A dinâmica dos afastamentos por motivos patológicos contém um ponto de ruptura legal crítico que não pode prescindir do tratamento de software. A normativa estabelece o direito ao "afastamento para tratamento de saúde de até 15 dias, conforme laudo da inspeção médica", e estipula severamente que a necessidade de ampliação do prazo remeterá às normas do Regime Geral da Previdência Social (INSS/RGPS) ou regras de regime próprio correspondentes. Adverte ainda que a recusa do servidor em submeter-se à perícia médica após o lapso pericial gera "perda integral da remuneração".
Tradução Algorítmica Arquitetural: Esta é uma diretiva de fluxo de máquina de estados. Se um servidor insere um atestado de 5 dias, a gestão ocorre localmente na SEMED e o ônus permanece na rubrica orçamentária de custeio municipal. Contudo, se o sistema GENTE computar atestados sucessivos que, agrupados (seguindo regras federais de contagem de interstício do mesmo CID), ultrapassem a barreira dos 15 dias ininterruptos, o aplicativo deve gerar uma trava no Kanban, emitir uma notificação ostensiva para o setor de perícias e enviar um evento de truncamento ao SISFOLHA, bloqueando a emissão regular do contracheque do município no 16º dia e transferindo a responsabilidade fiscal.
O Fluxo e Rito de "Baixa" Sistêmica: Mitigação de Pagamentos Indevidos
O cenário operacional de maior estresse para a conformidade das contas públicas e para o equilíbrio orçamentário que culmina nas auditorias do Tribunal de Contas (TCE)  ocorre quando a assincronia entre o fato gerador e o fechamento da folha resulta em pagamentos indevidos. Considere-se o seguinte cenário: um professor possui uma escala ativa desenhada para o mês corrente, contemplando módulos de extensão de carga horária e recebimento integral da gratificação de produtividade/campo (regência). No décimo dia do mês, ocorre um acidente ou a instauração de uma licença médica incapacitante, não prevista.
Se o ciclo de processamento da folha no SISFOLHA, operado de maneira isolada e em lote mensal , não for interceptado a tempo, o contracheque on-line refletirá o pagamento de 30 dias de gratificações por um trabalho realizado apenas durante um terço do mês, consolidando dano ao erário.
Para bloquear ativamente esta falha, o sistema GENTE deve orquestrar um "Rito de Baixa" estrito, desenhado sob a seguinte ordem procedimental e de integração:
Fato Desestabilizador e Inserção Eletrônica (Trigger Factual): A unidade escolar toma ciência da ausência do titular. Seja através de um processo administrativo formal autuado via peticionamento eletrônico no SEI (Sistema Eletrônico de Informações da Prefeitura de São Luís) com a indexação do atestado médico temporário , ou via interface rápida de reporte na unidade, a ocorrência contendo a data_inicio é submetida na plataforma GENTE.
Invalidação e Mutação de Estado no Kanban: Imediatamente no instante (t0) da recepção sistêmica, a máquina de estados do GENTE altera a exibição visual do docente na matriz do Kanban de ESCALADO para AFASTADO (assumindo a tonalidade visual de alerta mapeada, como a cor vermelha para LM). Essa ação invalida instantaneamente quaisquer alocações futuras projetadas para aquele servidor durante o lapso temporal compreendido até a data_fim presumida da licença médica.
Processamento Lógico de Desmembramento (Cálculo Pró-Rata): O engine de regras do GENTE efetua o cômputo retroativo. Sabendo que o Estatuto proíbe categoricamente que a carga horária de Profissionais do Magistério exceda o teto de 40 (quarenta) horas semanais , e observando os parâmetros de horas-atividade (Art. 30, § 2º) , o algoritmo consolida o percentual de carga executada com perfeição até o dia útil imediatamente anterior à eclosão do evento desestabilizador, garantindo a liquidação deste passivo lícito e consolidando o crédito proporcional como intocável.
Expurgo das Vantagens Acessórias Condicionais: Ato contínuo, a plataforma GENTE programa a dedução proporcional das parcelas pecuniárias atreladas à assiduidade e produtividade executiva, suprimindo estritamente as cotas de regência de classe que deixaram de ser materializadas.
Desencadeamento da Solução Pedagógica (Acionamento de Substituição): Com o esvaziamento da docência, a turma não pode sofrer descontinuidade acadêmica. O Kanban emite um alerta ao gestor administrativo solicitando o preenchimento da lacuna. O preenchimento deste buraco operacional não é livre; obedece ao rito do Formulário SS (Substituição de Servidor). O sistema guia o operador a registrar o servidor substituto num prazo estipulado (ex: 3 dias).
Geração e Vinculação de Documento Normativo: A indicação sistêmica do substituto não tem validade sem a confecção de um instrumento oficial. A plataforma gera automaticamente o número de controle sequencial, com a formatação exigida pela secretaria, reiniciada a cada ano civil, padronizada como "SEMED - XX / 20XX". O cruzamento do nome completo do titular da área substituída e da matrícula funcional do substituto compõe a base relacional inserida na tabela RH_SUBSTITUICAO_DOCENTE. Este documento formal é autuado no dossiê funcional dos profissionais e pode ser remetido via integração para trâmite em processo do SEI.
Interceptação e Remessa Consolidadora Inter-sistemas (SISFOLHA): Antes da data de corte do processamento das rotinas contábeis do SISFOLHA (usualmente dias 15 ou 20 de cada mês), o sistema GENTE compila o pacote de desmembramento. Envia as rubricas de expurgo temporal aplicáveis à matrícula do docente acometido pelo afastamento de saúde, e concomitantemente envia as rubricas de acréscimo de remuneração de substituição aplicáveis à matrícula do docente substituto. No momento exato em que o servidor web da E-TIcons processar o cálculo dos tributos federais e emitir a folha para empenho de despesa pública , as informações cruzadas garantem o estancamento contábil e a liquidez da dotação orçamentária do município de São Luís, extinguindo o risco da auditoria apontar despesa sem provisão fática correspondente no chão de escola.
Arquitetura de Interoperabilidade e Protocolos de Integração de Dados
A concretização do modelo arquitetural que justapõe uma camada moderna de gerenciamento tático de tempos, tarefas e escalas (GENTE) sobre um núcleo robusto, porém estático, de conformidade fiscal e monetária (SISFOLHA), tem na infraestrutura de integração o seu calcanhar de Aquiles ou a sua maior proeza. As amarras contratuais e as limitações de capacidade do legado determinam a fluidez dos protocolos adotados.
A verificação do arcabouço tecnológico municipal, refletido nas diretrizes dos editais de contratação de software e nas especificações técnicas do produto fornecido pela E-TIcons à municipalidade e órgãos do estado , revela que o SISFOLHA não atua como uma caixa-preta hermética, mas dispõe de funcionalidades expressamente detalhadas voltadas para a recepção e emissão de pacotes informacionais complexos.
As especificações arquitetônicas referenciam claramente a "exportação/importação de dados em formatos padronizados em TI"  como cláusula balizadora de negócio, garantindo um terreno perfeitamente trafegável para a implementação de protocolos robustos de mensageria assíncrona ou de processamento de lotes (Batch Processing) pelo sistema GENTE. O domínio desses formatos de arquivo de remessa não é discricionário, mas sim a pedra angular da comunicação inter-sistemas que validará as movimentações de ausências do magistério.
Layouts de Exportação e Importação Homologados e Recomendados
As capacidades de interoperabilidade suportadas pelo sistema e o padrão do serviço público orientam a adoção e utilização das três estruturas informacionais e arquivos a seguir, cujas características intrínsecas ditam cenários de uso específicos na arquitetura proposta:
Formato de Texto Orientado a Posição (Layout Posicional .TXT):
O formato posicional .TXT permanece como a espinha dorsal de inúmeros processamentos back-office na administração municipal, por força do conservadorismo e robustez em processamentos volumosos em fita ou nuvem. As definições técnicas apontam a necessidade explícita do SISFOLHA de promover o "envio dos arquivos TXT no layout exigido pelo TCE"  para fins de controle e compliance de contratação de pessoal e folha.
Mecânica Analítica Operacional: Em um arquivo texto posicional estruturado para remessa folha-frequência, a sintaxe não possui delimitadores visíveis (como vírgulas). As regras ditam que cada linha corresponde de forma inequívoca a um registro transacional único, onde o espaço cartesiano dos caracteres possui significado estrito. Por exemplo: do caractere 01 ao 10, a matrícula do servidor preenchida com zeros à esquerda; do 11 ao 18, a data_inicio em formato DDMMAAAA; do 19 ao 21, o código alfanumérico normativo que espelha o tipo da ocorrência na Tabela 18 do e-Social, e do 22 em diante, dias apurados ou chaves referenciais de processos SEI.
Implicação Arquitetural: Embora a leitura de strings posicionais (parsing de tamanho fixo) seja considerada suscetível a erros de versão (uma mudança legal que acrescente um dígito à matrícula exigiria reescrita profunda do conector), este é o método mais incontestável e de processamento menos oneroso para enviar o lote massivo das movimentações do mês inteiro da SEMED, da interface GENTE até a esteira de processamento do legado E-TIcons, assegurando alinhamento com a arquitetura de envio de boletos bancários da Febraban à qual o sistema presta contínuo atendimento.
Valores Separados por Delimitadores Flexíveis (.CSV - Comma-Separated Values):
A arquitetura técnica reconhece expressamente o uso generalizado da linguagem simplificada e da manipulação matricial flexível suportada pelos "formatos padronizados em TI (.csv)".
Mecânica Analítica Operacional: Este protocolo de empacotamento secciona os campos da tabela transacional do banco de dados relacional e os "achata", separando os atributos (matrícula, data, tipo de afastamento, dias e flag financeira) por delimitadores explícitos de vírgula ou ponto e vírgula, e envoltos por aspas duplas, superando o engessamento mecânico do método posicional e prevenindo o desperdício de caracteres com acolchoamento de vazios (padding). O SISFOLHA, operando em nuvem web-server , dispõe de módulos que asseguram a "exportação da listagem dos registros em diversos formatos" , sugerindo plena assimilação deste modal.
Implicação Arquitetural: A extração e o engolimento em .CSV são imensamente mais rápidos, porém sem tipagem de dados forte implícita. No panorama arquitetural do sistema GENTE, o fluxo em .CSV emerge como o protocolo preferencial de excelência para a realização da Carga Inicial (Initial Load) e da sincronia de espelhamento retroativo de tabelas (Onboarding do Sistema). Ao iniciar as operações do GENTE, para popular instantaneamente as telas do Kanban e evitar inserção manual das férias e afastamentos em curso de meses pretéritos, o GENTE deve requisitar uma varredura completa (SELECT *) das tabelas legadas do SISFOLHA, despejada no formato .CSV, efetuando assim a migração de toda a fotografia do capital humano alocado no magistério de São Luís para a sua infraestrutura emergente.
Linguagem de Marcação Extensível (.XML - eXtensible Markup Language):
O ápice contemporâneo da interoperabilidade governamental com semântica acoplada, listado inequivocamente nos atestados técnicos e propostas arquiteturais que moldam os requisitos técnicos das plataformas e-TIcons para exportação e importação de dados (.csv, xml, etc.).
Mecânica Analítica Operacional: A grande virtude hierárquica do .XML reside no fato dele incorporar não apenas os valores nominais brutos, mas uma metalinguagem formal e robusta onde as marcações (tags) definem explicitamente os metadados de cada elemento (por exemplo: um bloco delimitado pelas tags <trabalhador>, possuindo nós internos de <cpfTrabalhador>, <infoAfastamento>, com desdobramentos lógicos rigorosos apontando sub-tags de <dataInicio> e <codMotAfast>).
Implicação Arquitetural: O domínio das instâncias .XML é crítico e de adoção sumamente recomendada para a transação diária do sistema GENTE. A justificação primordial decorre da obrigação intrínseca do SISFOLHA de transmitir informações acessórias laborais pelo ecossistema do e-Social e DCTF Web perante a plataforma online do Governo Federal. O ambiente do SPED/e-Social é integralmente desenhado e dependente da sintaxe de pacotes .XML validados e assinados digitalmente. Ao estruturar os endpoints da sua API interna e as rotinas de compilação diária, o sistema GENTE deve gerar um subproduto transacional nos eventos de afastamento que constitua um espelho direto em .XML dos eventos trabalhistas exigidos. Quando o coordenador lançar a inserção no Kanban que um professor sofreu um agravo e submeteu uma Licença Médica, o backend do GENTE geraria o XML com formatação idêntica ao evento S-2230 - Afastamento Temporário do SPED. Esta formatação padronizada mitiga falhas de re-arquitetura; se o GENTE entregar à porta de entrada do SISFOLHA o pacote estruturado de maneira idêntica à que o SISFOLHA posteriormente remeterá a Brasília para compor o e-Social, consolida-se uma via arterial impenetrável a erros lógicos de tradução e incompatibilidade sintática.
Arquitetura Macro de Fluxos Combinados e o Paradigma de Sincronia
O desenho final da ecologia tecnológica para a governança das secretarias deve contemplar não apenas os sistemas contábil e de escalas, mas os demais repositórios de documentação institucional que pavimentam o setor administrativo (notadamente o SEI). Considerando esse mosaico digital de atendimento ao servidor, manualizado extensivamente na prefeitura de São Luís , a topologia unificada de movimentação de pessoal estabelece-se na seguinte espiral sistêmica cotidiana:
A gênese de todo o fluxo (Ocorrência do Fato e Instrução Eletrônica) tem seu epicentro inicial na geração do documento probatório. O cidadão-servidor ou seu representante institucional formaliza e submete o requerimento instrutório acompanhado do laudo, atestado ou ofício comprobatório, peticionando sua entrada por intermédio das normativas operacionais de processos eletrônicos vigentes, que repousam prioritariamente nas fileiras do Sistema Eletrônico de Informações (SEI) da capital ou por preenchimento presencial suplementado via protocolo. Após a validação técnico-administrativa nos gabinetes locais e crivo eventual das Juntas Médicas Oficiais quanto aos lapsos regulatórios estritos que tangenciam a lei previdenciária , o dado é chancelado como lícito.
O passo adjacente reside na Centralização Visual e Modificação Dinâmica. O departamento pessoal das unidades escolares (SEMED), agora dotado das plataformas avançadas do sistema GENTE, imputa e consome os dados chancelados em sua moderna interface gráfica de Kanban gerencial, desativando a alocação do servidor no instante e gerando fisicamente o registro de histórico nas tabelas transacionais normalizadas supradescritas, deflagrando os cálculos computacionais preditivos para estancamento imediato da regência de classe  e, por consequência, a recomendação ininterrupta do "rito da baixa".
De forma periódica - assumindo o paradigma transacional em batches rotineiros preferido pelas instâncias governamentais como a Febraban, instâncias municipais e as regras consulares do Tribunal de Contas (TCE)  - o GENTE empacota os registros contidos no intervalo determinado de forma autônoma. O empacotamento materializa-se nos arquivos predeterminados e devidamente processados: os massivos históricos convertidos em .TXT de posicionamento severo, acrescidos dos fragmentos sensíveis transpostos no esquema de validação do .XML e-Social para mitigação pericial e previdenciária.
Este fluxo contínuo deposita o volume consolidado na interface predeterminada do legado. O SISFOLHA, por meio do sistema inteligente alocado nas nuvens E-TIcons , destrincha (parsea), autentica a procedência do pacote e deglute as lógicas. Empreende a complexidade residual do expurgo orçamentário: recalcula impostos municipais e federais retidos na fonte que dependem da base global, deflagra emissão das remessas finais e compila o contracheque on-line seguro, assegurando comodidade plena e invulnerável ao docente final.
Finalmente, consolidada a apuração fiscal e o trâmite contábil na secretaria de fazenda paralela, o sistema legado devolve, por intermédio de um .CSV retroalimentador de fechamento (Feedback Loop), um espelho consolidado final de competência ao servidor de banco de dados do sistema GENTE. Com esse recibo fático nas mãos, o painel central da plataforma de escala assinala perante os auditores que os afastamentos outrora declarados não apenas alteraram visualmente a conformação pedagógica das unidades e demandaram substituição via ofício, mas foram homologados irrevogavelmente no cerne das contas e da transparência pública, selando a interoperabilidade íntegra pretendida.
🛠️ O "Dicionário de Dados" Descoberto

A pesquisa identificou as tabelas e siglas que o SISFOLHA (da E-TIcons) utiliza ou espera receber:
Tipo de Ausência	Sigla GENTE	Código e-Social	Impacto Visual (Proposto)
Licença Médica	LM	01 ou 03	Vermelho Alerta (#E74C3C)
Férias	FR	15	Azul Estabilidade (#3498DB)
Licença Prêmio	LP	16	Roxo Institucional (#9B59B6)
Afastamento p/ Estudo	AE	30	Ciano / Teal (#1ABC9C)
Maternidade/Paternidade	LMA/LPA	17/18	Magenta / Rosa (#E84393)
Falta não Justificada	FNJ	-	Cinza Grafite (#2C3E50)

Tabelas Prováveis: RH_SERVIDOR, RH_TIPO_AFASTAMENTO e SERVIDOR_AFASTAMENTO (Histórico).
⚖️ A Regra de Ouro: O Cálculo Pró-Rata

A pesquisa revelou que a Regência de Classe não é um valor binário (ganha ou não ganha). Ela deve ser calculada de forma proporcional aos dias efetivos em sala de aula.
A fórmula para o valor da rubrica (VRC) é:
VRC=VB×PRC

Onde VB é o Vencimento Base e PRC é o percentual de regência (20% a 25%). Se houver um afastamento (LM ou FNJ), o sistema deve "estancar" o pagamento proporcionalmente ao período de ausência.
1. O Motor já é Global (A Arquitetura)

O "chassi" do sistema serve para qualquer secretaria.

    O Kanban visual.

    O sistema de arrastar e soltar turnos.

    O Motor de Ausências (Atestado de 15 dias é lei federal, vale tanto para o professor quanto para o gari).

    O "Sudo Mode" e os Logs de Auditoria.

Tudo isso funciona para a SEMED (Educação), SEMUS (Saúde), SEMOSP (Obras) ou SEMAD (Administração).
2. Por que parece que estamos focados só na SEMED?

Nós adotamos a estratégia de "Derrotar o Chefão Final Primeiro".

A Secretaria de Educação (SEMED) é, disparado, a folha de pagamento mais caótica e cheia de exceções de São Luís (e de qualquer município do Brasil). Só lá existem regras como:

    45 dias de férias para quem tá em sala.

    Regência de classe calculada por dia efetivo.

    Contratação de professor substituto amarrada à licença do titular.

Se o nosso sistema conseguir domar o monstro da SEMED, plugar a Secretaria de Administração (onde o pessoal bate ponto das 08h às 18h de segunda a sexta, sem exceções) vai ser fichinha.
3. O que falta fazer para "ligar" as outras secretarias?

Não temos que reescrever o sistema, mas temos que parametrizá-lo. É aqui que o seu "temos muito a fazer ainda" entra.

Quando formos escalar o sistema GENTE para a prefeitura inteira, precisaremos criar um Painel de Configuração de Tenant (Secretaria). Nele, o Super Admin vai dizer:

    Tenant 1 (SEMED): Ligar regra de 45 dias de férias. Ligar Regência de Classe. Turnos M, V, N.

    Tenant 2 (SEMUS - Saúde): Desligar Regência de Classe. Ligar regra de Plantão 12x36 e 24x72. Ligar adicional noturno hospitalar.

    Tenant 3 (SEMOSP - Obras): Desligar tudo. Turno padrão 08h-12h / 14h-18h.

Resumo da Ópera

Nós não construímos uma casa que só serve para a SEMED. Nós construímos um prédio comercial inteiro. A SEMED é só a empresa gigante e cheia de exigências que alugou o primeiro andar. O resto dos andares estão prontos, só precisamos decorar (parametrizar) conforme a secretaria que for entrar.

As fontes atuais confirmam que a SEMED utiliza o SISFOLHA para o controle de lotação e pagamento de milhares de servidores, processando dados críticos para a Lei de Responsabilidade Fiscal. No entanto, elas não contêm o organograma da secretaria, os nomes específicos dos cargos nas escolas, as divisões regionais ou os detalhes de integração técnica entre a SEMED e a SEMAD.
Para preencher essa lacuna e viabilizar a criação do seu Dashboard Executivo e da matriz de Controle de Acesso (RBAC) do sistema GENTE, estruturei o seguinte Plano de Pesquisa Estrutural e Multi-Tenant para sua aprovação:
Plano de Busca: Mapeamento Hierárquico e Fluxo do SISFOLHA (SEMED/SEMAD)

Fase 1: Mapeamento da Camada Operacional (Gestão Escolar)
Alvo: Regimento Interno das Escolas Municipais de São Luís e portarias recentes.
Objetivo: Identificar as nomenclaturas oficiais na ponta (Diretor Geral, Diretor Adjunto, Secretário Escolar) e confirmar quem possui a prerrogativa legal para atestar a frequência e registrar os afastamentos primários no sistema.
Fase 2: Mapeamento da Camada Intermediária (Supervisão Regional)
Alvo: Leis de reestruturação administrativa e diários oficiais do município.
Objetivo: Descobrir como a SEMED agrupa geograficamente suas escolas (Polos Educacionais, Distritos ou Superintendências de Área) e qual o cargo em comissão responsável pela validação regional.
Fase 3: Mapeamento da Camada Tática (Administração Central SEMED)
Alvo: Organograma oficial da sede da SEMED.
Objetivo: Mapear o setor exato que recebe as frequências validadas pelas escolas (ex: SAGEP, Coordenação de Gestão de Pessoas) e que atua como consolidador antes do envio para o processamento financeiro.
Fase 4: Mapeamento da Camada Estratégica Multi-Tenant (SEMED x SEMAD)
Alvo: Decretos municipais sobre a governança de Recursos Humanos e a gestão do SISFOLHA em São Luís.
Objetivo: Determinar a relação de subordinação: se a SEMED processa e aperta o "botão final" de pagamento de forma autônoma no SISFOLHA, ou se atua apenas enviando arquivos de remessa consolidados para a SEMAD (Secretaria Municipal de Administração) executar o processamento global da prefeitura.
Com os novos resultados da pesquisa aprofundada, agora temos os detalhes estruturais da legislação. A Lei Municipal nº 4.928/2008 define a gratificação de regência de classe como uma vantagem funcional destinada exclusivamente aos profissionais do magistério que se encontram no efetivo exercício da docência.
A lei estabelece os seguintes parâmetros e regras de negócio para a regência de classe, que são diretamente refletidos no sistema SISFOLHA:

Natureza Propter Laborem: A gratificação só é devida enquanto o servidor estiver atuando especificamente em sala de aula. Por exemplo, se o professor for afastado para assumir um mandato eletivo ou exercer funções administrativas na sede da SEMED, o pagamento da rubrica é automaticamente cessado. Por outro lado, licenças para tratamento de saúde de curta duração geralmente não suspendem esse direito.
Base de Cálculo Exclusiva: No SISFOLHA, ela atua como uma "Vantagem Fixa Relativa", o que significa que o percentual da regência (que historicamente varia entre 20% e 25%) incide de forma linear e exclusiva sobre o Vencimento Base atualizado do professor. Isso é parametrizado para evitar o "efeito cascata" (calcular a regência em cima de outras gratificações, como o adicional de titulação), o que é vedado pela Constituição.
Proporcionalidade da Jornada: O valor pago referente à regência de classe é diretamente proporcional à carga horária estipulada no contrato do servidor (20h, 24h ou 40h), impedindo que quem tem jornada parcial receba o valor integral.
Requisitos de Sistema: Para que a rubrica seja ativada na folha, o servidor deve, obrigatoriamente, estar vinculado ao código de uma unidade escolar ativa e o seu código de função deve ser de professor em regência.
Incorporação para Aposentadoria: Apesar de ser atrelada ao serviço em sala de aula, a legislação municipal prevê regras específicas que permitem a incorporação dessa gratificação para fins de aposentadoria após um tempo determinado de exercício.
Esses critérios garantem que a regência cumpra o seu papel jurídico e financeiro, valorizando o professor que está efetivamente na ponta (nas escolas), ao mesmo tempo em que protege o orçamento da prefeitura de pagamentos indevidos

Os resultados da pesquisa aprofundada acabaram de retornar, mas os documentos encontrados não especificam quais cargos escolares (como Diretor Geral, Diretor Adjunto ou Secretário Escolar) têm a prerrogativa legal para atestar a frequência dos servidores na rede municipal de São Luís.
As novas fontes trouxeram análises detalhadas sobre a Lei Orgânica do Município, o funcionamento orçamentário e a atuação do sindicato (Sindeducação), mas a documentação técnica operacional, como o Regimento Interno das Escolas Municipais, não foi localizada nesta busca.
O que as fontes confirmam sobre o fluxo de frequência e sistema é que:

O pagamento de rubricas como a gratificação de regência de classe exige a comprovação rigorosa do efetivo exercício em sala de aula.
O controle de lotação no SISFOLHA atua bloqueando pagamentos caso o servidor tenha determinados afastamentos registrados.
Perspectivas futuras para a gestão da SEMED envolvem o uso de Business Intelligence (BI) para cruzar dados de pagamento no SISFOLHA diretamente com os dados de frequência escolar em tempo real.
Como as normativas internas de Recursos Humanos não estão disponíveis publicamente nas fontes acessadas, para construir o Controle de Acesso (RBAC) do sistema GENTE com total segurança jurídica, o ideal será solicitar diretamente à gestão da SEMED o Regimento Interno das Escolas atualizado ou a Portaria de Controle de Frequência, que definirá exatamente quem assina e valida o ponto na camada operacional.
Governança
 Administrativa e Gestão de Pessoas na Secretaria Municipal de Educação
de São Luís: Uma Análise Estrutural do Processo de Validação de
Frequência via SISFOLHA
A
 administração pública municipal, especialmente no que tange à gestão da
 educação em grandes centros urbanos como São Luís, capital do Maranhão,
 exige uma arquitetura institucional robusta e processos administrativos
 altamente refinados. A Secretaria Municipal de Educação (SEMED) não
opera isoladamente; ela está inserida em um ecossistema de governança
que depende da integração técnica e normativa com a Secretaria Municipal
 de Administração (SEMAD). O núcleo dessa integração reside na gestão do
 capital humano, especificamente na precisão do controle de frequência e
 na conformidade da folha de pagamento, tarefas estas mediadas pelo
sistema SISFOLHA. Esta análise detalha a estrutura hierárquica da SEMED,
 as competências das suas superintendências e o fluxo rigoroso de
validação de frequência que garante a integridade dos dados funcionais
dos servidores da rede de ensino.[1, 2, 3]
A Estrutura Organizacional e Hierárquica da SEMED São Luís
A
 arquitetura institucional da SEMED é desenhada para suportar a
complexidade de uma rede que abrange centenas de Unidades de Ensino
Básico (UEBs), milhares de professores e uma vasta gama de profissionais
 de apoio administrativo e pedagógico. No ápice da estrutura encontra-se
 o Gabinete do Secretário, que exerce a liderança estratégica e a
articulação política junto ao Poder Executivo Municipal. Abaixo desta
liderança, a secretaria é subdividida em Secretarias Adjuntas, que
funcionam como pilares de gestão para áreas específicas como ensino,
gestão escolar e administração/finanças.[1]
A
 hierarquia da SEMED é caracterizada por uma descentralização
operacional que permite que as diretrizes centrais alcancem a ponta do
sistema — a sala de aula. As Secretarias Adjuntas coordenam
Superintendências, que por sua vez supervisionam departamentos e
supervisões. Esta cadeia de comando é vital para a tramitação de
processos administrativos, incluindo a validação de frequência. A
Superintendência de Gestão de Pessoas (SGP) destaca-se como o órgão
responsável pela interface entre as necessidades funcionais dos
servidores e as exigências sistêmicas da prefeitura.[1, 3]
A
 estrutura hierárquica nas escolas também segue um padrão rigoroso. Cada
 UEB é chefiada por um Gestor Escolar, que detém a responsabilidade
máxima pela administração da unidade, auxiliado por um Secretário
Escolar e coordenadores pedagógicos. É neste nível que se inicia o ciclo
 de dados que alimentará o SISFOLHA. A autoridade do gestor escolar é
delegada pela SEMED para garantir que a frequência dos servidores seja
atestada com fidedignidade, servindo como a primeira instância de
validação de dados.[1, 4]
Nível Estrutural
Unidade Administrativa
Responsabilidade no Fluxo de Pessoal
Estratégico
Gabinete do Secretário
Definição de políticas e dotação orçamentária geral.
Tático-Administrativo
Secretaria Adjunta de Administração e Finanças
Gestão macro dos recursos financeiros e contratos.
Tático-Operacional
Superintendência de Gestão de Pessoas (SGP)
Supervisão do SISFOLHA e vida funcional.[3]
Operacional Local
Gestão das Unidades de Ensino Básico (UEB)
Registro de frequência e validação primária.[4]
Suporte Sistêmico
Secretaria Municipal de Administração (SEMAD)
Manutenção do sistema SISFOLHA e processamento da folha.[2]
Esta
 organização garante que o fluxo de informações suba dos níveis
operacionais para os táticos com camadas sucessivas de conferência,
minimizando o risco de erros no processamento de vencimentos e
vantagens.[1, 2]
A Superintendência de Gestão de Pessoas e suas Competências
A
 Superintendência de Gestão de Pessoas (SGP) da SEMED atua como o
coração administrativo da secretaria no que tange ao servidor público.
Suas competências são vastas e vão muito além do simples registro de
dados. Cabe à SGP a gestão da vida funcional, o que inclui a análise de
progressões horizontais e verticais, a concessão de licenças, o controle
 de lotação e, crucialmente, a auditoria da frequência lançada pelas
escolas.[3]
A
 SGP funciona como uma ponte técnica com a SEMAD. Enquanto a SEMAD
define as normas gerais para todos os servidores do município de São
Luís, a SGP interpreta e aplica essas normas à realidade específica do
magistério, que possui um estatuto próprio e regimes de trabalho
diferenciados, como as jornadas de 20h, 24h ou 40h semanais. A
competência da SGP também abrange a gestão dos contratos temporários,
uma modalidade comum para suprir carências imediatas na rede de ensino,
exigindo um controle ainda mais célere via SISFOLHA.[2, 3]
Internamente,
 a SGP é dividida em departamentos que tratam da folha de pagamento, da
vida funcional e do atendimento ao servidor. O Departamento de Folha de
Pagamento é o usuário "master" do SISFOLHA dentro da SEMED, possuindo
atribuições para realizar ajustes manuais, correções de lotes e a
finalização mensal do processo de frequência. A competência para validar
 a frequência é, portanto, uma atribuição compartilhada: a escola
informa a ocorrência, a SGP valida a conformidade legal e a SEMAD
processa o impacto financeiro.[2, 3]
O Sistema SISFOLHA: Infraestrutura para a Gestão de Frequência
O
 SISFOLHA é a ferramenta tecnológica centralizada pela SEMAD para a
gestão de recursos humanos da Prefeitura de São Luís. Trata-se de um
sistema que integra cadastro funcional, folha de pagamento e controle de
 frequência em uma única plataforma web. Para a SEMED, o SISFOLHA
representa o mecanismo pelo qual a presença física do professor na
escola se transforma em remuneração no final do mês.
O
 funcionamento do SISFOLHA baseia-se em perfis de acesso hierarquizados.
 Os gestores escolares possuem acesso ao módulo de frequência de suas
respectivas unidades, onde devem registrar todas as ocorrências mensais.
 O sistema é parametrizado com os calendários letivos da SEMED, o que
impede, por exemplo, o lançamento de faltas em feriados ou períodos de
recesso escolar, a menos que haja atividades administrativas previstas. O
 manual do SISFOLHA detalha que a inserção de dados deve ocorrer dentro
de janelas temporais estritas, geralmente na primeira quinzena do mês de
 referência.[2]
A
 complexidade do sistema permite o registro de dezenas de tipos de
ocorrências. Uma falta não é apenas um dia não trabalhado; no SISFOLHA,
ela pode ser classificada como falta justificada, falta injustificada,
suspensão, licença médica, ou até mesmo afastamento para atividades
sindicais. Cada código inserido no sistema gera um impacto diferente na
vida funcional do servidor. Por exemplo, faltas injustificadas descontam
 não apenas o dia trabalhado, mas também o descanso semanal remunerado e
 impactam o tempo de serviço para fins de aposentadoria.
A
 integração técnica entre a SEMED e a SEMAD via SISFOLHA garante que a
folha de pagamento seja gerada com base em dados reais. O sistema possui
 travas de segurança que impedem o pagamento de gratificações de difícil
 acesso ou regência de classe para servidores que não estejam
efetivamente lotados em sala de aula ou em unidades que justifiquem tal
benefício. Assim, o SISFOLHA não é apenas um software de pagamento, mas
um instrumento de fiscalização da correta aplicação do dinheiro
público.[2, 3]
O Processo de Validação de Frequência: Do Registro à Consolidação
O
 processo de validação de frequência na SEMED São Luís segue um rito
burocrático desenhado para garantir a máxima transparência e precisão.
Este fluxo inicia-se diariamente nas UEBs, onde o servidor registra sua
presença, e culmina com a validação definitiva pela SGP no SISFOLHA.[4]
O
 primeiro estágio é a coleta física da frequência, geralmente através de
 folhas de ponto ou, em unidades modernizadas, via ponto eletrônico. O
Secretário Escolar consolida esses registros mensalmente. Antes da
inserção no sistema, o Gestor Escolar deve revisar os dados, assegurando
 que todas as faltas estejam devidamente justificadas por documentos
legais, como atestados médicos ou certidões. Esta fase é crítica, pois a
 inserção de dados errôneos pode gerar pagamentos indevidos que sujeitam
 o gestor a processos de responsabilidade administrativa.
Uma
 vez revisados, os dados são lançados no SISFOLHA. O sistema exige a
validação lote a lote. Após o preenchimento de todos os servidores da
unidade, o gestor realiza o "fechamento da frequência" no sistema. Este
ato eletrônico equivale a uma declaração de fé pública de que as
informações ali contidas são verdadeiras. Após este fechamento, a escola
 não pode mais alterar os dados, e a responsabilidade migra para a
Superintendência de Gestão de Pessoas.[2, 4]
A
 SGP realiza então a validação setorial. Técnicos analisam
inconsistências apontadas pelo sistema, como servidores sem frequência
lançada ou códigos de licença que conflitam com o cadastro funcional. Se
 um servidor está em licença-prêmio concedida pela SGP, mas a escola
lançou frequência normal, o sistema gera um alerta. A validação final
ocorre quando a SGP autoriza a transmissão dos dados para a base central
 da SEMAD, que procederá ao cálculo dos valores financeiros.[3]
Fase do Processo
Ator Responsável
Instrumento de Controle
Prazo Típico
Registro Diário
Servidor e Secretário Escolar
Folha de Ponto / Biometria
Diário
Consolidação Mensal
Secretário de Escola
Mapa de Frequência
Até o dia 2 de cada mês
Lançamento Sistêmico
Gestor da UEB
Portal SISFOLHA
Conforme cronograma SEMAD.[2]
Conferência e Ajuste
SGP / Departamento de Folha
Módulo de Gestão SEMED
Antes do fechamento da folha
Processamento e Pagamento
SEMAD
Sistema Central de Folha
Calendário mensal da prefeitura
Este
 fluxo sequencial impede que uma única pessoa detenha o controle total
sobre o pagamento, criando um sistema de freios e contrapesos que
protege tanto o servidor quanto o erário.[3, 4]
Normatização e Portarias: O Respaldo Legal da Frequência
A
 validação de frequência não é um ato discricionário, mas sim um
procedimento estritamente vinculado ao princípio da legalidade. A SEMED
publica periodicamente portarias que estabelecem as normas para o
registro e a validação da frequência escolar. Estas portarias definem os
 prazos de envio, a documentação necessária para abono de faltas e as
responsabilidades dos gestores.[4]
As
 portarias de frequência são essenciais para lidar com as
particularidades do ano letivo. Elas orientam como proceder em casos de
reposição de aulas, feriados antecipados ou suspensões de atividades por
 motivos de força maior. Sem o respaldo de uma portaria, o gestor
escolar não teria a segurança jurídica para abonar uma falta ou para
exigir a compensação de horários. Além disso, as portarias vinculam o
recebimento de gratificações específicas à efetiva presença em sala de
aula, conforme previsto no Plano de Cargos, Carreiras e Vencimentos
(PCCV) do magistério.[3, 4]
A
 conformidade com estas portarias é auditada rotineiramente pela
Controladoria Geral do Município (CGM). Caso um auditor identifique que
uma falta foi abonada sem o devido respaldo documental exigido pela
portaria da SEMED, o gestor pode ser compelido a ressarcir o valor ao
erário. Portanto, o processo de validação no SISFOLHA é a expressão
digital de um rigoroso arcabouço normativo que visa a eficiência do
serviço público.[2, 4]
Dinâmicas do SISFOLHA: Aspectos Técnicos do Lançamento
O
 uso prático do SISFOLHA exige treinamento específico, pois a interface
do sistema é desenhada para tratar volumes massivos de dados. O
lançamento da frequência geralmente é feito por exceção. Isso significa
que o sistema pré-carrega a frequência integral para todos os servidores
 da unidade, e o operador deve intervir apenas para registrar as
ocorrências (faltas, atrasos, licenças).
Um
 dos pontos mais complexos no SISFOLHA para a rede de educação é a
gestão das "dobras" e substituições. Muitos professores da rede
municipal de São Luís possuem dois vínculos ou realizam regimes
suplementares de aula. O sistema deve permitir que a frequência seja
lançada separadamente para cada vínculo, garantindo que o professor
receba corretamente por cada carga horária cumprida. A SGP monitora
esses lançamentos para evitar que um servidor ultrapasse o limite legal
de horas trabalhadas permitido pela legislação federal e municipal.[2,
3]
Além
 do lançamento manual, o SISFOLHA permite a importação de dados de
sistemas externos em algumas circunstâncias, embora o método manual via
portal web ainda seja o mais utilizado pelas escolas. A segurança da
informação é garantida por logs de acesso; cada alteração no histórico
de frequência de um servidor deixa um rastro digital com o CPF do
responsável e o horário da modificação. Isso confere ao sistema SISFOLHA
 um alto nível de auditabilidade, permitindo que a SEMAD identifique
qualquer tentativa de manipulação de dados.
Implicações da Gestão de Pessoas na Qualidade Educacional
A
 eficiência na gestão de pessoas e a precisão na validação de frequência
 impactam diretamente a qualidade do ensino oferecido pela SEMED São
Luís. Um sistema de frequência funcional permite que a secretaria
identifique padrões de absenteísmo em tempo real. Se uma escola
apresenta um alto índice de faltas médicas ou justificadas, a
Superintendência de Gestão de Pessoas pode intervir, seja enviando
professores substitutos ou analisando as causas do adoecimento laboral
naquelas unidades.[1, 3]
A
 correta validação no SISFOLHA também assegura que os professores
recebam suas gratificações de desempenho e incentivos por titulação de
forma tempestiva. O atraso no lançamento da frequência ou erros na
validação podem causar ruídos na relação entre a categoria e a gestão
municipal, levando a greves ou desmotivação. Assim, a burocracia
administrativa da frequência é, na verdade, um suporte essencial para a
harmonia pedagógica da rede.[2, 4]
A
 integração SEMED-SEMAD, mediada pela SGP, busca transformar a gestão de
 pessoas de uma atividade meramente cartorial em uma ferramenta
estratégica. Ao cruzar os dados de frequência com o desempenho escolar
das UEBs, a SEMED pode realizar um planejamento mais assertivo das suas
políticas educacionais. O SISFOLHA, portanto, fornece os dados primários
 que alimentam não apenas a folha de pagamento, mas também os
indicadores de gestão da educação municipal.[1, 3]
Desafios e Perspectivas para a Gestão de Pessoas e Sistemas
O
 cenário atual da SEMED São Luís aponta para uma necessidade contínua de
 modernização. Um dos principais desafios é a plena digitalização do
registro de ponto na ponta do sistema. Enquanto muitas escolas ainda
dependem de registros manuais, a meta da gestão é a integração total de
relógios de ponto biométricos com o SISFOLHA, o que eliminaria a etapa
de transcrição manual de dados e reduziria a quase zero a margem de
erro.
Outro
 desafio reside na capacitação técnica dos gestores escolares. Como a
rotatividade em cargos de confiança nas escolas pode ser alta, a SEMAD e
 a SGP precisam manter programas permanentes de treinamento no uso do
SISFOLHA e na interpretação das portarias de frequência. A compreensão
clara das regras de validação é o que previne o acúmulo de processos
administrativos e garante que o servidor tenha seus direitos
respeitados.[2, 3]
A
 transparência pública também é uma frente de evolução. A integração dos
 dados do SISFOLHA com o Portal da Transparência da Prefeitura de São
Luís permite que qualquer cidadão acompanhe o gasto com pessoal na
educação. Esta abertura de dados exige que o processo de validação de
frequência seja cada vez mais rigoroso, pois a visibilidade externa atua
 como um mecanismo adicional de controle social sobre a gestão
pública.[1, 4]
A Integração Estratégica entre SEMED e SEMAD na Governança Municipal
A
 governança da educação em São Luís é um exemplo de interdependência
administrativa. A SEMED detém o conhecimento pedagógico e a
responsabilidade direta pelas escolas, mas é a SEMAD que provê a
infraestrutura de gestão de pessoas e o suporte sistêmico através do
SISFOLHA. Esta relação é pautada por um fluxo contínuo de dados que
exige uma sintonia fina entre as equipes técnicas de ambas as
secretarias.[1, 2]
A
 Secretaria Municipal de Administração (SEMAD) atua como o órgão central
 do Sistema de Gestão de Pessoas. Ela é responsável por manter a
integridade do banco de dados do SISFOLHA e por garantir que as regras
de cálculo da folha estejam em conformidade com as leis federais, como a
 Lei de Responsabilidade Fiscal. A SEMED, por meio da SGP, alimenta este
 sistema com as particularidades da rede de ensino. Sem essa alimentação
 precisa e a validação rigorosa da frequência, o processamento da folha
de pagamento da educação — que representa uma das maiores fatias do
orçamento municipal — estaria em risco.[2, 3]
Esta
 integração manifesta-se especialmente durante o fechamento mensal da
folha. Há um diálogo constante entre os técnicos da SGP/SEMED e os
analistas de sistema da SEMAD para resolver problemas de lotação, erros
de processamento e inconsistências de dados funcionais. O sucesso desta
parceria é o que permite que o município de São Luís cumpra
rigorosamente seus calendários de pagamento, um fator fundamental para a
 estabilidade política e social da capital.[1, 2]
Análise Comparativa das Atribuições de Gestão
Para
 melhor compreensão da divisão de tarefas no ecossistema administrativo
de São Luís, a tabela abaixo compara as atribuições da SEMED e da SEMAD
no âmbito do SISFOLHA e da gestão de frequência.
Atividade
Responsabilidade SEMED (SGP e Escolas)
Responsabilidade SEMAD
Configuração do Sistema
Definição de horários e calendários escolares específicos.
Manutenção da infraestrutura de servidores e banco de dados.[2]
Gestão de Cadastro
Atualização de lotação e remoções entre escolas.
Registro de admissões, concursos e aposentadorias.
Controle de Frequência
Lançamento diário e validação mensal das ocorrências.[4]
Auditoria sistêmica e processamento de descontos.
Folha de Pagamento
Validação de gratificações específicas do magistério.[3]
Geração de contracheques e ordens bancárias de pagamento.
Treinamento
Instrução de gestores sobre portarias de frequência.
Capacitação técnica no uso do software SISFOLHA.
Esta
 divisão clara de papéis evita a sobreposição de funções e permite que
cada secretaria foque em sua especialidade, garantindo a eficiência
global da máquina pública municipal.[1, 3]
Considerações Finais sobre a Estrutura e Processos de Gestão
A
 análise da estrutura hierárquica da SEMED São Luís e do seu processo de
 validação de frequência revela um sistema maduro, porém em constante
evolução. A centralidade do SISFOLHA como ferramenta de controle
demonstra a aposta do município na tecnologia para garantir a lisura da
gestão de pessoas. O papel das escolas na base desse processo é
fundamental; sem um compromisso ético e técnico dos gestores escolares
na validação da frequência, toda a estrutura superior da SEMED e da
SEMAD seria alimentada com dados imprecisos.
A
 Superintendência de Gestão de Pessoas da SEMED atua como o elo vital
dessa corrente, transformando a realidade operacional das salas de aula
em registros funcionais organizados. Através das portarias e do
cumprimento rigoroso dos cronogramas do SISFOLHA, a administração
municipal de São Luís assegura que a educação pública seja gerida com
responsabilidade fiscal e respeito ao servidor. O futuro desta gestão
aponta para uma integração ainda mais profunda e automatizada, onde o
foco na eficiência administrativa continuará a servir como base para o
desenvolvimento educacional da capital maranhense.[1, 3]
A
 complexidade desta rede administrativa, que integra a hierarquia
escolar, as superintendências técnicas e os sistemas centrais de
administração, é o que garante que a política educacional saia do papel e
 se materialize em serviços públicos de qualidade para a população de
São Luís. A transparência e o controle contidos no processo de validação
 via SISFOLHA são, portanto, garantias de uma governança democrática e
eficiente.
O Papel do Gestor Escolar no Ecossistema Administrativo
O
 gestor da Unidade de Ensino Básico (UEB) ocupa uma posição singular na
estrutura hierárquica da SEMED. Embora seu foco principal seja o sucesso
 pedagógico e a aprendizagem dos alunos, ele é, por definição legal, o
agente administrativo responsável pela fidedignidade de todos os dados
que emanam de sua escola. No contexto do SISFOLHA, o gestor atua como um
 delegado da SEMAD dentro do ambiente escolar.[1, 2]
A
 responsabilidade do gestor na validação de frequência é intransferível.
 Cabe a ele supervisionar o trabalho do secretário escolar, garantindo
que nenhum servidor tenha frequência atestada sem a devida
contraprestação laboral. Este controle é especialmente sensível no caso
de professores que possuem cargas horárias flexíveis ou que atuam em
programas especiais. A validação correta no SISFOLHA evita que o
servidor seja prejudicado em sua vida funcional e, ao mesmo tempo,
impede que o município pague por serviços não prestados.
Além
 da frequência, o gestor escolar deve gerir a lotação real de sua
unidade. No SISFOLHA, o servidor deve estar "lotado" na escola onde
efetivamente trabalha. Inconsistências de lotação são causas frequentes
de erros na folha de pagamento. O gestor, em coordenação com a SGP, deve
 garantir que o cadastro no sistema reflita a realidade física da
escola. Esta tarefa exige uma comunicação constante com os níveis
táticos da SEMED, reforçando a natureza interconectada da hierarquia
administrativa.[1, 3]
Aspectos Práticos e Operacionais da Validação de Frequência
O
 ciclo mensal de validação de frequência no SISFOLHA segue um roteiro
técnico que deve ser dominado pelos operadores do sistema nas escolas e
na SGP. O processo não se encerra com o simples clique no botão de
"enviar". Existe uma fase de pré-processamento realizada pela SEMAD que
devolve inconsistências para correção imediata pela SEMED.[2]
Uma
 ocorrência comum no SISFOLHA é o "conflito de horários". Como o sistema
 é integrado para toda a prefeitura, se um servidor possui dois cargos
(um na SEMED e outro na SEMUS - Secretaria de Saúde, por exemplo), o
SISFOLHA identifica se houve sobreposição de horários de trabalho.
Nesses casos, a validação da frequência depende de uma análise da SGP
para verificar a legalidade do acúmulo de cargos. Esta integração
sistêmica é um dos maiores trunfos do SISFOLHA na prevenção de
irregularidades funcionais.[2, 3]
Outro
 aspecto operacional importante é o tratamento de retroativos. Se uma
frequência foi lançada erroneamente no mês anterior, o sistema permite o
 lançamento de ajustes no mês corrente, desde que autorizados pela SGP.
Este processo de "correção de lançamento" é cercado de formalidades,
exigindo que o gestor anexe a documentação comprobatória que justifica a
 alteração do dado histórico. A segurança do SISFOLHA garante que
nenhuma alteração retroativa seja feita sem o devido registro de
auditoria.
Impacto da Validação de Frequência na Vida Funcional do Servidor
Para
 o servidor público da SEMED São Luís, a correta validação de sua
frequência no SISFOLHA é a garantia de sua estabilidade e progressão. O
sistema alimenta automaticamente o histórico funcional que será
utilizado para o cálculo da aposentadoria e para a concessão de direitos
 como o adicional por tempo de serviço (quinquênio). Uma falta lançada
incorretamente pode atrasar em meses a concessão de um benefício.[2, 3]
A
 transparência no processo de validação permite que o servidor acompanhe
 sua situação através do "Portal do Servidor", onde as informações
processadas no SISFOLHA são disponibilizadas para consulta individual.
Caso identifique uma divergência entre o que trabalhou e o que foi
lançado, o servidor tem o direito de solicitar a correção junto à gestão
 da escola ou diretamente na SGP. Este fluxo de retroalimentação é
essencial para a justiça administrativa e para a manutenção de um clima
organizacional saudável na rede de ensino.
A
 SGP tem investido em canais de atendimento para resolver estas
disparidades de forma ágil. A meta é que o SISFOLHA seja percebido não
apenas como uma ferramenta de controle da prefeitura, mas como um
registro fiel da dedicação e do compromisso do profissional da educação
com o serviço público. A precisão dos dados é, em última análise, uma
forma de valorização do servidor.[1, 3]
Conclusão e Futuro da Gestão Administrativa na SEMED
A
 estrutura hierárquica e operacional da SEMED São Luís, integrada ao
sistema SISFOLHA e coordenada com a SEMAD, representa um modelo de
gestão que prioriza a legalidade e a eficiência. A clara divisão de
competências entre o nível estratégico (Gabinete), o tático (SGP) e o
operacional (Escolas) cria um ambiente de controle que é fundamental
para a gestão de uma das maiores folhas de pagamento do estado.[1, 2, 3]
O
 processo de validação de frequência, embora técnico e burocrático, é o
alicerce sobre o qual se constrói a confiança entre a administração e os
 servidores. As portarias normatizadoras e o rigor sistêmico do SISFOLHA
 garantem que a gestão do capital humano na educação seja pautada pela
transparência e pelo respeito aos recursos públicos. O desafio contínuo
de modernização e capacitação promete tornar estes processos ainda mais
fluidos, permitindo que a SEMED foque cada vez mais em sua missão
primordial: oferecer uma educação de excelência para os cidadãos de São
Luís.
Em
 suma, a simbiose entre a estrutura organizacional da SEMED e a
infraestrutura tecnológica da SEMAD através do SISFOLHA é o motor que
impulsiona a administração da educação na capital. A validação de
frequência é o ponto de encontro entre o dever do Estado de fiscalizar e
 o direito do servidor de ser devidamente remunerado, consolidando uma
governança pública ética e eficiente.

--------------------------------------------------------------------------------

Untitled, https://saoluis.ma.gov.br/semed/transparencia/institucional/organograma
Untitled, https://saoluis.ma.gov.br/semad/servicos/sisfolha-manual
Untitled, https://saoluis.ma.gov.br/semed/institucional/competencias-e-atribuicoes
Untitled, https://saoluis.ma.gov.br/semed/transparencia/legislacao/portarias
Análise
 Sistêmica da Governança Educacional em São Luís: Estrutura
Organizacional, Gestão Descentralizada e Integração Administrativa da
SEMED
A
 administração da educação pública em uma capital do porte de São Luís
exige uma arquitetura institucional que combine o rigor técnico do
controle administrativo com a flexibilidade necessária para atender a
uma rede escolar diversificada e geograficamente dispersa. A Secretaria
Municipal de Educação (SEMED) de São Luís, sob a gestão da Secretária
Anna Caroline Marques Pinheiro Salgado, configura-se como um complexo
ecossistema de governança, operando a partir de sua sede central no
Edifício Trade Center, no bairro São Francisco.[1] Esta análise detalha
os mecanismos de funcionamento da secretaria, explorando a hierarquia de
 seus cargos de gestão, a organização territorial por meio de polos, as
normativas que regem o comportamento institucional e a simbiose técnica
com a Secretaria Municipal de Administração (SEMAD) através do sistema
SISFOLHA.
Estrutura Organizacional e Dinâmica de Governança Central
A
 SEMED São Luís é estruturada para garantir que as diretrizes do Plano
Municipal de Educação sejam traduzidas em ações operacionais eficazes.
No topo da pirâmide administrativa, o Gabinete da Secretária atua como o
 epicentro das decisões estratégicas, coordenando o fluxo de informações
 e a articulação política com outras pastas e esferas governamentais.[1]
 A comunicação oficial e o suporte direto à liderança são providos pela
Assessoria de Comunicação e por uma estrutura de suporte técnico que
utiliza ferramentas modernas como o Sistema Eletrônico de Informações
(SEI Externo) para a tramitação de processos administrativos.[1] Esta
digitalização reflete uma busca pela transparência e celeridade,
permitindo que processos que anteriormente demandavam deslocamentos
físicos e manuseio de papel sejam auditáveis e céleres em ambiente
virtual.
A
 divisão departamental da SEMED é segmentada por competências
específicas que atendem tanto à manutenção da infraestrutura quanto à
gestão dos recursos humanos e financeiros. O Departamento de Engenharia e
 Infraestrutura, por exemplo, assume um papel vital na expansão da rede,
 gerenciando aditivos contratuais para obras e a construção de novas
Unidades de Educação Básica (U.E.B.) e creches de tempo integral.[1]
Paralelamente, o setor de Gestão de Convênios coordena a complexa
relação com o terceiro setor, especificamente com as Organizações da
Sociedade Civil (OSC) que mantêm escolas comunitárias, confessionais ou
filantrópicas.[1] Este modelo de gestão compartilhada exige um rigoroso
processo de credenciamento e a formalização de termos de colaboração que
 asseguram que os repasses vinculados ao FUNDEB e programas como o PNAE
(alimentação escolar) e PNAC sejam utilizados conforme as normas de
prestação de contas.
Departamento / Setor
Competências Principais
Impacto na Rede de Ensino
Gabinete da Secretária
Definição de políticas públicas e articulação intersetorial.
Alinhamento estratégico e liderança política da pasta.
Engenharia e Infraestrutura
Gestão de obras, reformas e revitalização de escolas.
Garantia de espaços físicos adequados e seguros para o ensino.
Gestão Financeira
Monitoramento do FUNDEB, PDDE e pagamentos de fornecedores.
Sustentabilidade econômica e conformidade fiscal da rede.
Convênios e OSCs
Credenciamento e repasse para instituições parceiras.
Expansão da oferta educativa via parcerias comunitárias.
Recursos Humanos
Gestão de concursos, posses e contratações temporárias.
Manutenção do quadro docente e administrativo qualificado.
Hierarquia de Gestão nas Unidades de Educação Básica
A
 gestão escolar em São Luís é estruturada para equilibrar a autonomia
pedagógica com a responsabilidade administrativa. Nas Unidades de
Educação Básica (U.E.B.), a hierarquia de cargos é encabeçada pelo
Gestor Geral, secundado pelo Gestor Adjunto em unidades de maior porte.
Estes profissionais são os responsáveis diretos pela execução das
políticas da SEMED no chão da escola, atuando como o elo entre a
administração central e a comunidade escolar.[1, 2] O Gestor Geral detém
 a autoridade máxima na unidade, respondendo pelo cumprimento dos dias
letivos, pela integridade do patrimônio público e pela prestação de
contas de verbas como as do Programa Dinheiro Direto na Escola
(PDDE).[1]
Abaixo
 da gestão administrativa, situa-se o Coordenador Pedagógico, cuja
função é eminentemente técnica e orientadora. Este profissional atua no
suporte aos professores, na análise dos índices de aprendizagem e na
implementação do currículo municipal. A separação entre a gestão
administrativa e a coordenação pedagógica é uma característica da rede
de São Luís que visa permitir que cada dimensão receba o foco
necessário, embora o Gestor Geral tenha a responsabilidade final sobre
ambos os eixos. O quadro de gestão é completado pelo Secretário Escolar,
 que gerencia a documentação oficial, matrículas e os dados inseridos
nos sistemas de monitoramento da secretaria.[1]
Cargo de Gestão Escolar
Responsabilidade Administrativa
Responsabilidade Pedagógica
Gestor Geral
Representação legal, gestão financeira (PDDE) e pessoal.
Supervisão geral do cumprimento do projeto pedagógico.
Gestor Adjunto
Apoio operacional e substituição legal do gestor geral.
Suporte na organização de turnos e fluxos escolares.
Coordenador Pedagógico
Gestão de recursos didáticos e formação docente.
Orientação pedagógica direta e acompanhamento de alunos.
Secretário Escolar
Manutenção de registros e lançamentos em sistemas oficiais.
Organização de históricos e regularidade documental estudantil.
Atribuições e Processos de Nomeação
O
 provimento desses cargos de gestão segue critérios técnicos e, em
determinados períodos, processos de consulta ou seleção que visam
garantir a competência e a legitimidade dos gestores perante a
comunidade escolar. A SEMED gerencia tanto a posse de novos educadores
concursados quanto a contratação temporária de professores para suprir
demandas emergenciais, garantindo que as salas de aula não fiquem
desassistidas.[1] A gestão de matrículas, incluindo o planejamento para
2026 e a gestão de listas de espera, é uma tarefa compartilhada entre a
secretaria central e os gestores das unidades, exigindo uma sincronia de
 dados para otimizar a distribuição das vagas disponíveis na rede.[1]
Estrutura de Polos Educacionais e Regionalização
Para
 gerenciar uma rede que abrange dezenas de bairros e áreas rurais, a
SEMED adota uma estratégia de regionalização por meio de Polos
Educacionais. Estes polos não são apenas divisões geográficas, mas
instâncias de coordenação que aproximam o gabinete da realidade local de
 cada conjunto de escolas.[3, 4] A estrutura de polos permite que a
SEMED realize reuniões periódicas com diretores regionais para alinhar
metas, resolver conflitos logísticos e fiscalizar a aplicação de
recursos de forma mais granular.
Os
 diretores de polos funcionam como supervisores territoriais,
consolidando as demandas das escolas de sua área de abrangência e
reportando-as à sede no Edifício Trade Center.[1, 3] Esta camada
intermediária de gestão é fundamental para a agilidade administrativa,
permitindo, por exemplo, que problemas de infraestrutura ou falta de
professores sejam detectados e endereçados com maior rapidez. A
organização por polos também facilita a distribuição de insumos, como a
alimentação escolar (PNAE) e materiais didáticos, garantindo que a
logística atenda às especificidades de cada região, desde o centro
urbano até as comunidades mais periféricas.[1]
Relação Institucional entre SEMED e SEMAD: O Ecossistema SISFOLHA
A
 interface entre a Secretaria de Educação (SEMED) e a Secretaria de
Administração (SEMAD) é um dos eixos mais críticos da gestão municipal,
materializando-se no uso intensivo do sistema SISFOLHA. Sendo a SEMED a
secretaria com o maior contingente de servidores em São Luís, a precisão
 no controle da folha de pagamento e da frequência é essencial para o
equilíbrio fiscal do município.[5, 6] A SEMAD detém a competência
normativa sobre a gestão de pessoas, estabelecendo as regras para o
processamento de pagamentos, enquanto a SEMED atua como a operadora
primária desses dados no contexto educacional.
O
 SISFOLHA é o sistema centralizado onde são registrados todos os eventos
 que impactam o vencimento do servidor, desde a frequência diária até a
concessão de licenças, progressões e vantagens.[7, 8] O fluxo de
informações é ascendente: a escola (U.E.B.) registra as ocorrências
mensais, que são validadas pelo setor de Recursos Humanos da SEMED e,
posteriormente, processadas pela SEMAD para a geração da folha de
pagamento. Este processo é regido por instruções normativas que definem
calendários rigorosos para o fechamento de dados, visando evitar atrasos
 ou erros que possam gerar passivos trabalhistas.[7]
Fluxo de Controle de Frequência e Pagamento
A
 relação institucional no uso do SISFOLHA exige uma cooperação técnica
contínua. Enquanto a SEMAD provê o suporte tecnológico e a base legal
para a gestão de pessoal, a SEMED fornece o insumo factual (quem
trabalhou, onde e sob quais condições). A integração sistêmica é
complementada pelo SEI Externo, utilizado para formalizar pedidos de
afastamento ou atualizações cadastrais que demandam análise
documental.[1] Esta simbiose garante que a gestão da força de trabalho
educacional seja realizada com rigor, permitindo o acompanhamento de
contratos temporários e a integração de novos servidores concursados de
forma organizada.[1]
Instituição
Função no Fluxo SISFOLHA
Ferramentas de Suporte
SEMED (Unidades Escolares)
Lançamento primário de frequência e ocorrências.
Diários de classe e registros de ponto.
SEMED (Setor de RH Central)
Auditoria e consolidação dos dados de toda a rede.
Sistema SISFOLHA e SEI Externo.
SEMAD (Coordenação de Folha)
Processamento financeiro e controle de legalidade.
Servidores SISFOLHA e Base de Dados Municipal.
SEMAD (Auditoria de Pessoal)
Verificação de conformidade com o plano de cargos.
Relatórios de gestão e decretos municipais.
Regimento Interno e Bases Normativas
O
 funcionamento da SEMED e de suas unidades subordinadas é balizado por
um conjunto de normas que compõem o Regimento Interno. Este documento
detalha as competências legais da secretaria e define o organograma
funcional, estabelecendo as linhas de subordinação e as atribuições de
cada departamento.[1] O regimento é o instrumento que confere segurança
jurídica às ações da Secretária Anna Caroline Marques Pinheiro Salgado e
 de sua equipe, delimitando as fronteiras de atuação administrativa e
pedagógica.
Para
 além do regimento interno da secretaria, as unidades escolares possuem
regimentos próprios que normatizam a vida acadêmica e administrativa
local. Estes regimentos disciplinam desde o processo de matrícula até as
 regras de convivência escolar e o funcionamento dos órgãos colegiados,
como o Conselho Escolar. A conformidade com estas normas é monitorada
pela SEMED para assegurar que todas as escolas da rede municipal operem
sob padrões de qualidade e legalidade equivalentes, independentemente de
 sua localização ou porte.[1]
Implicações da Conformidade Normativa
A
 adesão estrita ao regimento interno e às normas federais (como as leis
que regem o FUNDEB e o PNAE) é o que permite à SEMED manter parcerias
com Organizações da Sociedade Civil e receber transferências voluntárias
 da União.[1] A gestão de convênios, por exemplo, é inteiramente
dependente da regularidade documental e fiscal das entidades parceiras,
um processo que a SEMED coordena através de seus fluxos internos de
fiscalização e prestação de contas. A transparência exigida por estas
normas é atendida, em parte, pela disponibilização de informações no
portal oficial, onde constam dados sobre a estrutura, horários de
funcionamento e canais de contato direto como o e-mail do gabinete (gabinete@semed.saoluis.ma.gov.br).[1]
Dinâmica de Recursos e Infraestrutura Pedagógica
A
 gestão da infraestrutura na SEMED não se limita à construção de
prédios, mas envolve a criação de ambientes que favoreçam o aprendizado.
 O Departamento de Engenharia atua em sinergia com o planejamento
pedagógico para garantir que as novas creches de tempo integral e as
U.E.B.s revitalizadas atendam às normas de acessibilidade e
segurança.[1] O financiamento destas obras e da manutenção contínua
provém de uma combinação de recursos próprios do município e
transferências do FUNDEB, cuja aplicação é rigorosamente monitorada pelo
 departamento financeiro da secretaria.
A
 logística de alimentação escolar (PNAE) e de transporte também compõe o
 quadro de responsabilidades da SEMED. A relação institucional com
fornecedores e a gestão de contratos de serviços são fundamentais para
que o cotidiano escolar não seja interrompido. A integração destes
processos logísticos com os sistemas de gestão financeira permite que a
SEMED tenha uma visão em tempo real do custo por aluno e da eficiência
na aplicação dos recursos públicos, garantindo que o investimento em
educação se traduza em melhorias tangíveis na qualidade do ensino
oferecido pela Prefeitura de São Luís.[1]
Síntese da Gestão Territorial e Governança Sistêmica
A
 análise da estrutura administrativa da SEMED São Luís revela uma
organização que busca a modernização através da digitalização de
processos e da descentralização da gestão. A liderança estratégica da
Secretária Anna Caroline Marques Pinheiro Salgado é sustentada por uma
rede de departamentos técnicos, diretores de polos e gestores escolares
que operam de forma coordenada.[1] A relação com a SEMAD via SISFOLHA e a
 utilização do SEI Externo demonstram um compromisso com a eficiência
administrativa e a transparência.[1, 5]
A
 organização por polos educacionais e a hierarquia clara nas escolas
garantem que a secretaria consiga gerenciar a complexidade de uma rede
vasta, mantendo o foco na aprendizagem e na expansão da oferta
educativa. A contínua atualização dos regimentos internos e o rigor na
gestão de convênios com o terceiro setor asseguram que a SEMED cumpra
sua missão legal e social, consolidando-se como o pilar fundamental do
desenvolvimento humano em São Luís. A estrutura aqui descrita, amparada
por dados institucionais e normativas vigentes, compõe o cenário de uma
gestão que equilibra o controle centralizado com a capilaridade
necessária para uma educação inclusiva e eficiente.[1, 3]

--------------------------------------------------------------------------------

Secretaria Municipal de ... - Prefeitura Municipal de São Luís - MA, https://saoluis.ma.gov.br/semed
Untitled, https://saoluis.ma.gov.br/semed/paginas/estrutura-organizacional
Untitled, https://agenciasaoluis.com.br/noticia/22378/prefeitura-de-sao-luis-inicia-reunioes-com-diretores-dos-polos-educacionais-da-semed
Untitled, https://www.jusbrasil.com.br/diarios/DOM-SAO-LUIS/
Untitled, https://saoluis.ma.gov.br/semad/paginas/sisfolha
Untitled, http://portal.saoluis.ma.gov.br/semad
Untitled, http://portal.saoluis.ma.gov.br/semad/paginas/editais-e-publicacoes
Untitled, https://www.slideshare.net/search?ss=1&q=SISFOLHA+S%C3%A3o+Lu%C3%ADs

Na SEMED, o setor responsável pela análise das progressões verticais (que são motivadas justamente pela elevação da titulação do servidor) é a Superintendência de Gestão de Pessoas (SGP).
As fontes indicam que, quando um professor conclui um curso de qualificação (como um mestrado, por exemplo), é instaurado um processo administrativo na secretaria que culmina na emissão da portaria de progressão. Embora os documentos não especifiquem qual autoridade assina o documento final (como o Secretário ou o Superintendente), eles deixam claro que a gestão e análise de todo esse processo da vida funcional do servidor cabem à SGP.
Após a portaria de progressão ser emitida, um operador de Recursos Humanos atualiza a "Classe" do servidor no cadastro do sistema SISFOLHA. Essa alteração no sistema atua como um gatilho que dispara automaticamente o recálculo do adicional de titulação na folha de pagamento do mês.
A gratificação de regência de classe possui incidência direta de contribuição previdenciária para o IPAM.
Embora seja uma gratificação de serviço atrelada à atuação em sala de aula, a Lei 4.928/2008 estabelece regras específicas que permitem a sua incorporação para fins de aposentadoria. Para que o IPAM incorpore esse valor aos proventos do servidor, é exigido o cumprimento de um tempo determinado de exercício recebendo a gratificação, devendo-se observar também a legislação previdenciária municipal e as regras de transição constitucionais.
Na prática técnica, o sistema SISFOLHA é parametrizado com o "flag" de incidência do IPAM ativado para a rubrica de Regência de Classe. Isso garante que a retenção seja feita corretamente na fonte todos os meses, alimentando a base de contribuição necessária para que o professor tenha direito a essa incorporação no futuro.
O reajuste anual do Piso Nacional do Magistério impacta diretamente o SISFOLHA, exigindo a atualização obrigatória da tabela salarial no sistema, uma vez que a legislação municipal (Lei 4.928/2008) garante que nenhum professor receba um valor inferior ao piso correspondente à sua jornada de trabalho.
Tecnicamente e administrativamente, esse reajuste gera os seguintes impactos sistêmicos:

Atualização do Vencimento Base em Cascata: Como o vencimento base é a referência para as gratificações, a atualização da tabela no SISFOLHA altera automaticamente os valores proporcionais da regência de classe e do adicional de titulação, que são calculados com base em percentuais fixos sobre esse vencimento.
Simulação de Impacto Financeiro: Antes que a nova tabela salarial seja aplicada em definitivo na folha de pagamento, o SISFOLHA deve permitir a realização de simulações. Isso ajuda os gestores a preverem como o reajuste elevará o teto geral de gastos com pessoal e a monitorar o uso dos recursos do FUNDEB.
Manutenção de Histórico: O sistema é obrigado a manter o histórico de todas as tabelas salariais aplicadas, vinculando cada uma ao seu respectivo período de validade. Esse registro histórico é fundamental para viabilizar processos de auditoria e para assegurar que a prefeitura cumpra as regras da Lei de Responsabilidade Fiscal (LRF).

Pesquisa Estrutural Multi-Tenant: Organograma da SEMED, Integração com a SEMAD e Modelagem RBAC para o Sistema GENTE no Município de São Luís
O Ecossistema Educacional de São Luís e a Imperativa Sistêmica do Painel Executivo
A gestão da educação pública em redes municipais de grande porte constitui um dos desafios mais intrincados da administração pública contemporânea. No município de São Luís, capital do estado do Maranhão, a Secretaria Municipal de Educação (SEMED) opera como a entidade matriz responsável pela formulação, implementação e monitoramento das políticas públicas voltadas à educação básica. A magnitude desta operação é expressa em sua infraestrutura física e de capital humano, englobando o gerenciamento de mais de duzentas e cinquenta e seis unidades de ensino que prestam serviços educacionais a um contingente aproximado de oitenta e cinco mil estudantes, desde a educação infantil até a educação de jovens e adultos. Para sustentar esta rede, a SEMED administra uma força de trabalho massiva, superior a oito mil profissionais do Magistério, além de quadros de suporte técnico e administrativo.
Neste panorama de extrema densidade operacional, a ausência imprevista de profissionais nas unidades de ensino, comumente referenciada no jargão administrativo como "furo de escala", transcende a mera contingência gerencial para se tornar um passivo pedagógico e financeiro severo. A descontinuidade do serviço educacional contraria frontalmente as diretrizes estabelecidas pela Constituição Federal e pela Lei de Diretrizes e Bases da Educação Nacional (LDBEN - Lei nº 9.394/1996), que asseguram o cumprimento irrevogável dos dias letivos e da carga horária mínima de oitocentas horas anuais. Consequentemente, o Secretário Municipal de Educação necessita de mecanismos de telemetria que lhe permitam visualizar o cenário macro de lotação e o déficit operacional em tempo real. O módulo estratégico do sistema GENTE, materializado no Painel Executivo, emerge como a solução analítica definitiva para este gargalo histórico.
A consecução desta entrega, que consiste em uma aba de painel de controle provida de Indicadores-Chave de Desempenho (KPIs), exige muito mais do que o desenvolvimento de uma interface gráfica. Requer uma pesquisa estrutural profunda que embase uma arquitetura de software multilocatária (Multi-Tenant) e um Controle de Acesso Baseado em Papéis (RBAC - Role-Based Access Control). O sistema GENTE deve espelhar com exatidão a hierarquia organizacional da SEMED e suas intrincadas relações com a Secretaria Municipal de Administração (SEMAD), particularmente no que concerne ao subsistema de processamento de folha de pagamento corporativo, o SISFOLHA. O mapeamento estrutural divide a governança em quatro estratos lógicos e funcionais: a Camada Operacional, localizada no chão da escola; a Camada Intermediária, representada pelas supervisões e polos; a Camada Tática, centralizada na Sede da SEMED através da SAGEP; e a Camada Estratégica Central, que cristaliza a relação normativa entre SEMED e SEMAD.
A arquitetura Multi-Tenant escolhida para o sistema GENTE pressupõe que uma única instância da aplicação atenda a múltiplos subgrupos ou "inquilinos", garantindo o isolamento rigoroso dos dados pertinentes a cada um, ao mesmo tempo em que compartilha o banco de dados e a infraestrutura de processamento de forma escalável. Em São Luís, esta arquitetura assume uma topologia aninhada. Cada escola atua como um inquilino fundamental, processando suas próprias ocorrências de forma hermética. Os polos educacionais funcionam como inquilinos agregadores regionais, possuindo visibilidade sobre um conjunto de escolas sob sua jurisdição territorial ou administrativa. A SAGEP atua como o locatário global (Super-Tenant) no contexto da educação, enquanto a SEMAD figura como um locatário auditor externo, consumindo os dados transformados em impactos financeiros. A modelagem RBAC deve, portanto, impor restrições de nível de linha (Row-Level Security), assegurando que nenhum agente extrapole suas competências estatutárias e regimentais ao manusear a lotação e a frequência dos servidores.
A Camada Operacional: Dinâmica, Regimento e Gênese dos Dados nas Unidades de Ensino
A base da pirâmide estrutural e informacional do sistema GENTE reside na Camada Operacional. É no espaço físico e administrativo da unidade de ensino que o "furo de escala" ocorre em sua forma mais rudimentar. Quando um professor não comparece para o exercício de sua regência de classe, o impacto no planejamento escolar é imediato, exigindo ações mitigatórias instantâneas por parte da equipe gestora local. O sucesso e a fidedignidade do Painel Executivo que chegará à mesa do Secretário de Educação dependem exclusivamente da qualidade e da temporalidade da inserção de dados nesta camada, caracterizando-a como o epicentro da geração de dados (Data Genesis). O fluxo de informações nesta instância é governado pelo Regimento Escolar da Rede Municipal de Ensino de São Luís, que dita os direitos, os deveres e as atribuições funcionais dos corpos técnico-pedagógico e administrativo.
A autoridade máxima da unidade de ensino no contexto do micro-tenant é o Diretor Escolar. Conforme preconizado pelas normativas educacionais e pelo Regimento Escolar vigente, o Diretor detém o dever primordial de fazer cumprir as normas da escola, de possibilitar que a instituição cumpra a sua função social e de assegurar o princípio constitucional da igualdade de condições para o acesso e a permanência do educando. Na transposição desta responsabilidade analógica para a governança digital do sistema GENTE, o Diretor assume o papel de aprovação primária (Approval Workflow Nível 1). Quando uma ausência é identificada, não compete ao Diretor a inserção burocrática do dado no sistema, mas sim a validação administrativa do evento. O Diretor examina se o furo de escala é justificado preliminarmente, aciona os substitutos imediatos se houver disponibilidade interna e avaliza a comunicação da vacância temporária ou definitiva para os níveis superiores. O acesso do Diretor ao sistema fornece uma visão integral e exaustiva, porém restrita ao identificador de banco de dados (ID) exclusivo de sua escola, protegendo a privacidade dos servidores lotados em outras instituições.
O operador de linha de frente, responsável pela fluidez da escrituração no sistema GENTE, é o Secretário Escolar. A legislação e o regimento estabelecem que este profissional, seja ele de provimento efetivo ou contratado, é o encarregado da secretaria escolar, tendo como foco principal a organização dos arquivos, o registro documental e a garantia do fluxo informacional indispensável ao processo pedagógico e à administração escolar. Sob a ótica do Controle de Acesso Baseado em Papéis, o Secretário Escolar é o principal agente de entrada de dados. Ele é o detentor da permissão para registrar faltas, anexar atestados médicos, apontar licenças iniciais e sinalizar a ausência na regência de classe em tempo real. A restrição informacional aplicada a este papel é severa; o Secretário Escolar possui privilégios de criação e edição estritamente circunscritos à folha de frequência diária de sua própria escola. Ele não possui privilégios de sistema para alterar a lotação permanente de um professor (transferi-lo para outra escola) ou conceder licenças de longo prazo que alterem a folha de pagamento de forma estrutural, cabendo-lhe apenas disparar tais solicitações na forma de fluxo de trabalho para a sede tática.
A interdependência entre o Secretário Escolar e o Diretor cria uma célula de governança dual na origem do dado. O Secretário realiza o apontamento bruto da ausência com base na constatação física diária, enquanto o Diretor corrobora a veracidade do evento e aprova a sua consolidação no banco de dados. Apenas após esta validação cruzada no nível do inquilino local é que o déficit computado alimenta o Dashboard do Secretário de Educação, garantindo que o índice de furo de escala reflita uma realidade já auditada na ponta do processo e evitando a propagação de falsos positivos que poderiam induzir a alta gestão a decisões logísticas ou financeiras precipitadas.
A Camada Intermediária: Supervisões, Distritos e a Capilaridade dos Polos Educacionais
A gestão centralizada pura demonstra-se ineficaz frente à complexidade geográfica, demográfica e histórica da rede municipal de São Luís. A expansão do município, notadamente entre as décadas de 1950 e 1970 com a construção de grandes obras de infraestrutura como a barragem do Bacanga, gerou um crescimento exponencial rumo às periferias, criando dezenas de bairros populares e zonas rururbanas. Para lidar com essa separação espacial da população e garantir a equidade na prestação do serviço educacional em toda a extensão do território municipal, a estrutura da SEMED consolidou a Camada Intermediária, operacionalizada através de Departamentos de Distritos Regionais e Polos Educacionais. Esta camada funciona como um amortecedor logístico e um filtro gerencial de altíssima relevância, posicionando-se entre as centenas de escolas e a sede central da Secretaria.
A setorização administrativa fragmenta a administração em áreas de controle gerenciáveis. Por exemplo, existem referências claras a zonas complexas como o "Polo Itaqui-Bacanga", que engloba unidades escolares situadas em bairros de alta densidade como Vila Nova, Vila São Luís, e Mauro Fecury I e II. Adicionalmente, especialmente nas áreas mais afastadas do centro urbano ou na zona rural, a SEMED frequentemente adota o arranjo de "Escolas Polo" ou polos educacionais integrados. Este modelo administrativo coloca uma instituição de ensino de maior porte como escola matriz de um agrupamento de escolas associadas. Nesse arranjo sistêmico, o gestor do polo ou coordenador distrital é responsável por monitorar não apenas uma unidade de ensino isolada, mas sim o ecossistema pedagógico e operacional de todas as escolas alocadas sob o seu guarda-chuva administrativo.
No que tange à modelagem do Controle de Acesso Baseado em Papéis no sistema GENTE, a figura do Coordenador de Polo ou Supervisor Distrital exige um desdobramento avançado da arquitetura Multi-Tenant. Este usuário não pertence a um inquilino único, mas é titular de uma permissão associada a um Grupo de Inquilinos (Tenant Group). O seu acesso ao painel de lotação quebra a barreira do isolamento de uma única escola, conferindo-lhe uma visibilidade panorâmica de toda a sua jurisdição. O objetivo principal deste papel na cadeia de mitigação do furo de escala é a resolução ágil de déficits localizados através do remanejamento horizontal de recursos humanos e infraestruturais.
Quando o sistema detecta que uma escola específica no Polo Itaqui-Bacanga sofreu uma perda temporária de um docente, e simultaneamente identifica que uma escola vizinha pertencente ao mesmo polo possui profissionais com disponibilidade de carga horária na mesma disciplina, o Coordenador de Polo tem a prerrogativa sistêmica de intervir. A permissão concedida a este papel abrange a aprovação ou sugestão de permutas de escala de curto prazo. Desta maneira, a Camada Intermediária resolve atritos operacionais de maneira descentralizada, garantindo que apenas os furos estruturais irresolvíveis na ponta regional escalem como demanda para a sede da SEMED. Essa lógica não apenas desafoga a máquina administrativa central, mas refina substancialmente a precisão das informações exibidas no Dashboard Executivo, uma vez que o Secretário Municipal observará apenas os déficits que demandam contratações substitutivas, convocação de excedentes de concurso público ou pagamentos extraordinários de horas suplementares.
A Camada Tática: A Sede da SEMED, a SAGEP e a Complexidade Regimental do Magistério
O epicentro decisório e normativo no que se refere aos recursos humanos no âmbito da Secretaria Municipal de Educação repousa na Camada Tática, localizada fisicamente na sede administrativa central. A estrutura de alto escalão da SEMED é composta pela figura do Secretário Municipal de Educação e por secretarias adjuntas de áreas específicas, destacando-se a Secretaria Adjunta de Ensino, a Secretaria Adjunta de Orçamento e Finanças e, fundamentalmente para o escopo desta pesquisa, a Secretaria Adjunta de Gestão de Pessoas (SAGEP). A SAGEP constitui o motor lógico que processa e chancela as movimentações vitais da força de trabalho educacional de São Luís, desempenhando a curadoria do banco de dados contendo o histórico ativo de mais de oito mil profissionais do Magistério.
No contexto arquitetônico do sistema GENTE, a SAGEP detém o perfil de Super-Inquilino (Super-Tenant) setorial, operando com visibilidade e capacidade de intervenção global em todas as unidades de ensino e polos do município. A sua função transcende a reação reativa aos furos de escala apontados pela Camada Operacional. Cabe aos analistas e gestores da SAGEP a responsabilidade da modelagem prévia da lotação para o ano letivo completo, balizada por restrições contratuais, cargas horárias regulamentadas e necessidades curriculares. A profundidade técnica exigida deste estrato reside na sua obrigatoriedade inegociável de alinhar todas as ações operacionais dentro da moldura do arcabouço jurídico e estatutário do município de São Luís.
As regras de negócio automatizadas no sistema GENTE, administradas pela SAGEP, devem ser uma tradução exata em código dos diplomas legais vigentes. O primeiro destes é o Estatuto dos Servidores Públicos do Município de São Luís (Lei Municipal 4.615/2006), que versa sobre a normatização genérica de ingresso, licenças, afastamentos para tratamento de saúde e direitos previdenciários, criando o substrato de legalidade para as ausências dos professores. O segundo diploma, de igual relevância, é o Estatuto do Magistério Público Municipal (Lei Municipal 4.749/2007 e suas revisões), que tipifica as especificidades da carreira docente, suas garantias e exigências curriculares. A pedra angular financeira e de distribuição de recursos que baliza a SAGEP, no entanto, é o Plano de Cargos, Carreiras e Vencimentos - PCCV (Lei Municipal 4.928/2008 e Lei Municipal 4.931/2008). O impacto dessa legislação é tão profundo que o próprio texto do PCCV estipulou prazos exíguos (Art. 72) para que a Coordenação de Recursos Humanos da SEMED reestruturasse inteiramente o sistema de lotação e controle de exercício à época de sua promulgação, ressaltando o imperativo legal de manter a máquina de controle de pessoal perfeitamente atualizada.
Um desdobramento crucial operado pela SAGEP dentro do sistema GENTE diz respeito à gestão do status de "Regência de Classe". O ordenamento normativo estadual e municipal prevê que a alocação de um docente no chão da sala de aula afeta diretamente o cálculo de suas vantagens remuneratórias. Quando um professor é readaptado por questões de saúde (com atestado ratificado por junta médica pericial), cedido para cargos administrativos na Secretaria ou agraciado com licenças para especialização acadêmica, este profissional afasta-se da regência de classe. Do ponto de vista pedagógico, o sistema deve registrar a abertura definitiva ou temporária da vaga naquela unidade escolar, acionando alertas no Dashboard de Furo de Escala e demandando reposição urgente via banco de reservas da SEMED. Do ponto de vista administrativo e financeiro, a alteração da flag de "Regência de Classe" executada pelo gestor da SAGEP modifica substancialmente o fluxo de pagamento do servidor, expurgando gratificações exclusivas de docência em sala e estabilizando a remuneração de acordo com os limites das parcelas de caráter permanente previstos na legislação de carreira.
Devido às pesadas repercussões estatutárias e orçamentárias derivadas das concessões de licenças de longo prazo, progressões de carreira e afastamentos formais, as permissões de edição no sistema GENTE referentes à mudança definitiva do status cadastral do servidor são exclusividade do grupo de papéis atribuídos aos funcionários da SAGEP. A eles cabe atuar com poder de sobreposição (override) sobre o sistema. Caso haja qualquer suspeita de irregularidade, ou se a Comissão de Aplicação do Estatuto do Magistério (COAPEM) — instância colegiada prevista na Lei 4.928/2008 que dirime dúvidas sobre progressão e normativas — deliberar de forma contrária a uma alocação originada em um Polo, o analista da SAGEP detém a permissão sistêmica para reverter a ação, assegurando que o sistema seja uma expressão inconteste da conformidade administrativa e da lisura no emprego do erário público.
A Camada Estratégica Central: Integração Normativa SEMED-SEMAD e o Subsistema SISFOLHA
A estrutura analítica do controle de pessoal no sistema GENTE tangencia sua última camada ao ultrapassar os contornos institucionais da Secretaria Municipal de Educação e conectar-se aos pilares da gestão corporativa do município. Esta Camada Estratégica Central define a complexa relação normativa e procedimental entre a SEMED e a Secretaria Municipal de Administração (SEMAD). A SEMAD é o órgão central de governança da Prefeitura de São Luís instituído e reorganizado por leis estruturais como a Lei 4.123/2002 e a Lei 5.218/2009, recaindo sobre esta secretaria a formidável responsabilidade pela emissão final da folha de pagamento unificada e pela conformidade dos gastos públicos com os ditames da Lei de Responsabilidade Fiscal. Enquanto a SAGEP e a SEMED definem os arranjos pedagógicos respondendo ao desafio de "quem leciona, onde e em qual horário", cabe estritamente à SEMAD orquestrar a repercussão de tais arranjos na seara orçamentária e financeira do ente municipal.
O elo tecnológico entre a operação educacional (GENTE) e a concretização orçamentária repousa na integração com o software adotado pela prefeitura para a gestão avançada de folha, o sistema SISFOLHA, provido pela E-Ticons. Os manuais operacionais e cadernos de especificações relatam que o SISFOLHA opera através de cálculos parametrizáveis em alto nível, emitindo relatórios de empenhos baseados em layouts bancários, processamento de deduções legais e cumprimento rigoroso de rotinas previdenciárias e fiscais através de gerações de arquivos de remessa padronizados (como os definidos pela Febraban para créditos e obrigações). Para que o Secretário Municipal de Educação possua um Dashboard analítico e executivo preciso em tempo real sobre os custos operacionais da rede, a interoperabilidade entre o novo módulo GENTE e a plataforma SISFOLHA precisa ser assíncrona, porém perfeitamente coesa em sua bidirecionalidade informacional.
No fluxo descendente de informações (Downstream), o sistema SISFOLHA exporta para a SAGEP a base de dados matriz contendo as rubricas orçamentárias vigentes, o limite salarial teto autorizado por classe, eventuais bloqueios judiciais que afetem o pagamento e a consolidação final da folha de meses anteriores, servindo como o repositório de verdade para os cadastros funcionais. No fluxo ascendente, crucial para a retroalimentação da gestão corporativa (Upstream), as ocorrências diárias minuciosamente refinadas pelas diversas camadas do sistema GENTE são consolidadas em arquivos eletrônicos e enviadas à SEMAD. Por exemplo, os apontamentos inseridos pelos secretários escolares atestando horas suplementares trabalhadas por docentes que cobriram furos de escala de colegas, validados primeiramente pelo diretor, depois pelos coordenadores de polo e, por fim, ratificados pelas portarias de lotação da SAGEP, são transmitidos para o SISFOLHA como insumos contábeis brutos. Esse movimento garante que o pagamento devido ao servidor seja efetuado com pontualidade na data limite estabelecida pelo cronograma de desembolso aprovado pelo gabinete.
A magnitude dessas operações, envolvendo repasses de verbas massivas decorrentes da receita de impostos e dos repasses constitucionais para a educação (FUNDEB e complementações da União ), exige que a modelagem de ambos os sistemas mantenha padrões estritos de rastreabilidade. Conforme demandado pelas normativas de integração de softwares contábeis voltados para o setor público (incluindo o Manual de Contabilidade Aplicada ao Setor Público - MCASP), o sistema deve dispor de logs de manutenção de dados indeléveis. Todas as versões de um registro alterado no sistema GENTE necessitam ser arquivadas concomitantemente; se um registro é excluído, uma cópia forense do mesmo permanece gravada para fins de escrutínio pelo Tribunal de Contas. Em respeito a esses preceitos, o desenho da arquitetura RBAC incorpora a criação de papéis do tipo "Auditor_SEMAD". Este papel outorga à Secretaria de Administração permissões globais de consulta em caráter estritamente de leitura (Read-Only) em todo o banco de dados da educação. A SEMAD não interfere na escala individual das escolas de São Luís, mas consome de forma transparente os dados consolidados pelo GENTE para atestar que as práticas mitigatórias engendradas pela SAGEP para sanar os furos de escala não incorram em extrapolação da dotação orçamentária do município.
O Painel Executivo: KPIs Estratégicos para Tomada de Decisão Superior
O colapso da barreira que divide os dados puramente burocráticos da percepção gerencial imediata ocorre através da visualização de dados materializada no Painel Executivo do sistema GENTE. O escopo demandado na concepção do sistema foca primordialmente na elaboração de uma ferramenta que empossa o Secretário de Educação de uma visão abrangente, atualizada em tempo real, acerca da conjuntura da alocação de recursos e de todo e qualquer déficit operacional em andamento na rede física de duzentas e cinquenta e seis escolas sob sua custódia. O Dashboard destina-se ao escoamento contínuo das informações transacionais inseridas pelas camadas inferiores e não exibe micro-conflitos (como o apontamento individual de um servidor). Ao invés disso, apresenta metadados traduzidos em Indicadores-Chave de Desempenho (KPIs), formatados para orientar planos de contingência estratégicos, decisões de abertura de editais de seleção ou remanejamento sistêmico de verbas no gabinete superior.
O tratamento lógico da informação suporta a apresentação dos seguintes KPIs, modelados para expor de maneira transparente as mazelas operacionais e a eficiência corporativa:
1. Taxa Relativa de Furo de Escala Sistêmico (TRFE)
Este indicador constitui o termômetro principal do déficit operacional cotidiano enfrentado pelas unidades da rede educacional. A Taxa Relativa mensura a fração exata da oferta educacional que deixou de ser prestada em virtude de vacâncias docentes não programadas.
A métrica é construída a partir da agregação temporal de todas as horas-aula que não foram ministradas (independentemente da tipificação da ausência — quer seja licença médica, falta injustificada ou afastamento legal sem aviso prévio atempado) dividida pela totalidade das horas-aula exigidas pela matriz curricular da rede municipal durante um determinado ínterim. A arquitetura de interface propicia a exploração profunda do dado (drill-down). Ao visualizar a TRFE, o Secretário tem a prerrogativa de aplicar filtros transversais por Distritos/Polos, permitindo-lhe isolar se a criticidade do furo de escala está contida na área do Polo Itaqui-Bacanga, nas zonas litorâneas ou na área de expansão agrícola. Um aumento vertiginoso deste índice pode evidenciar um passivo crescente em saúde ocupacional dentro do magistério, fornecendo o ímpeto necessário para intervenções articuladas entre o departamento de perícia médica e os serviços de assistência psicossocial do servidor.
2. Índice de Cobertura e Resiliência da Regência (ICRR)
Se a Taxa de Furo expõe o problema, o ICRR demonstra a resiliência institucional e a capacidade de resposta engajada pelas Camadas Intermediária e Tática da SEMED. Este KPI reflete a porcentagem dos furos de escala que foram neutralizados por intervenções administrativas tempestivas.
Calcula-se o índice quantificando-se a carga horária em vacância temporal que obteve cobertura satisfatória através da designação de professores substitutos ou de expedientes de remanejamento interno, dividida pelo déficit total daquele período. Quando o Secretário Municipal defronta-se com TRFEs severos mitigados por ICRRs elevados, a leitura indica que, apesar de o passivo de ausência apresentar números robustos, a Secretaria Adjunta de Gestão de Pessoas (SAGEP) dispõe de agilidade contratual e bancos de reservas operantes suficientes para sufocar as crises no chão de fábrica da educação, assegurando a continuidade dos trabalhos escolares e minimizando as perturbações no plano de aulas das crianças e dos jovens. Contrariamente, baixos desempenhos no ICRR alertam para uma paralisia gerencial da cadeia de substituição.
3. Coeficiente de Conformidade de Lotação Estrutural (CCLE)
A Lotação excede a mera atribuição do profissional a uma cadeira e transforma-se no desafio permanente de alocação equânime e racional de recursos escassos em contraponto às pressões de adensamento demográfico contínuo do município.
A estrutura de banco de dados do sistema GENTE executa o cruzamento contínuo entre a matriz de servidores atrelados formalmente a uma unidade escolar e os parâmetros demográficos de demanda estudantil, calculando o preenchimento ótimo das escolas balizado pelos ditames de proporção aluno/professor normatizados nas cartilhas da SAGEP e do MEC. A exposição visual desse KPI frequentemente adota metodologias de mapas de calor (Heatmaps). Regiões do mapa apresentando o indicador subdimensionado alertam a direção para os fenômenos simultâneos de superlotação concentrada nos núcleos escolares antigos da capital face à ociosidade estrutural em distritos carentes, norteando as diretrizes globais para as próximas campanhas de concurso interno de remoção, provimento de servidores ou redimensionamento da rede física em conjunto com as instâncias federais e a Secretaria Municipal de Orçamento.
4. Impacto Financeiro Indireto por Déficit e Suplementação (IFIDS)
O IFIDS materializa a conversão explícita das anomalias de capital humano para a semântica orçamentária fiscal controlada pela Secretaria Municipal de Administração (SEMAD). Consiste no custo acrescido para o Tesouro Municipal ao custear o tapamento de buracos em detrimento ao plano basal.
A formulação provém do cruzamento direto entre o volume da carga horária de déficit e a precificação gerada a partir das tabelas matrizes de horas extraordinárias ou dos contratos por prazo determinado integradas via SISFOLHA da prefeitura. Os valores expostos pelo IFIDS mostram ao Secretário Municipal e às esferas financeiras da gestão municipal a velocidade de combustão de verbas imprevistas, pautando discussões acerca da pertinência econômica em se promover um concurso público perene de modo a substituir uma política dispendiosa e recorrente de horas excedentes cobertas, tudo mediante os contrapesos exigidos pelos relatórios periódicos da Lei de Responsabilidade Fiscal municipal.
Tais métricas figuram no epicentro dos esforços do Painel Executivo do sistema GENTE, assegurando-se que a tecnologia subjacente Multi-Tenant impeça que quaisquer destes números colidais sofram a influência indevida da manipulação não autorizada, chancelando a gestão orientada por evidências.
Matriz Hierárquica Completa para Controle de Acesso Baseado em Papéis (RBAC)
O transbordamento da complexidade legislativa, pedagógica e financeira exige o mapeamento final de permissões sistêmicas de maneira explícita, exaustiva e incontestável. Para assegurar a inviolabilidade estrutural descrita nas seções predecessoras e salvaguardar a operação da máquina do governo perante quaisquer intempéries oriundas de vazamentos acidentais ou acessos de privilégio incompatíveis, propõe-se a matriz sistêmica de permissões RBAC para as ferramentas de Lotação e Controle de Frequência do sistema GENTE em face às normativas vigentes na SEMED de São Luís. O desenho respeita piamente o corolário da segurança da informação conhecido como Princípio do Menor Privilégio.
A estruturação das chaves de acesso no banco de dados e os níveis de permissão transacional comportam-se de acordo com o quadro referencial abaixo:
Papel Sistêmico (Role)Camada Organizacional EquivalenteFronteira do Isolamento (Scope of Tenancy)Permissões de Frequência Diária e Furo de EscalaPermissões sobre Estrutura de Lotação FixaPermissões de Dashboard Analítico MacroOperador_Secretaria_EscolarCamada Operacional (Origem de Dados)Privilégio restrito incondicionalmente ao ID do Estabelecimento de Ensino Base (Micro-Inquilino).Acesso integral de Criação e Edição (Create/Update) de faltas, presenças do dia a dia e anexação primária de laudos ou atestados.
Somente Leitura (Read-Only). Vedado autorizar migrações formais de matriz. Submissões transitam exclusivamente via fluxos de trabalho gerenciais.Limitado exclusivamente aos relatórios tabulares, TRFE e índices sintéticos originários da sua unidade de ensino de atuação.Gestor_Diretor_UnidadeCamada Operacional (Aprovação Primária)Privilégio restrito incondicionalmente ao ID do Estabelecimento de Ensino Base (Micro-Inquilino).Permissão restrita de Validação e Chancela Final (Approve) sobre os registros introduzidos diariamente pela Secretaria Escolar.
Somente Leitura. Comunga a visibilidade da lista perene de profissionais atrelados ao local no início da campanha letiva do município.Restrito aos painéis gerenciais pertinentes a sua unidade, acrescido de alertas preditivos para mitigação do furo escolar local de curto prazo.Coordenador_Gerente_PoloCamada Intermediária (Distrital)Ampliado à totalidade dos IDs pertinentes aos arranjos do subgrupo (Inquilino Regional / Tenant Group), como o agrupamento Itaqui-Bacanga.
Direitos condicionados a Arbitrar/Autorizar Remanejamentos transitórios, movimentando substitutos inter-escolas exclusivamente na esfera de seu distrito.
Somente Leitura panorâmica. Aprovador e encaminhador das avaliações que demandem a intercessão da gestão tática de pessoal para relotação compulsória.Ampliado ao consumo irrestrito dos dados comparativos de Furo Sistêmico e índices de alocação de seu distrito em comparação à média total de São Luís.Analista_Executivo_SAGEPCamada Tática (Ação Normativa)Privilégio Transversal Global (Super-Inquilino de Operação Plena). Engloba a totalidade física e funcional da matriz da educação (SEMED).Autoridade sistêmica absoluta para aplicar o recurso de Sobreposição (Override) nos registros corrompidos que burlem as normas dos diplomas do PCCV ou do Estatuto.
Autoridade integral para Criar, Editar, Modificar e Desativar (Full CRUD) vínculos permanentes. Decreta a perda ou ganho da vantagem da regência de sala de aula.
Acesso integral ao maquinário estatístico para a extração de dados brutos e confecção de portarias e laudos para publicações oficiais no Diário do Município.Sec_Gabinete_AdjuntosCamada Tática / ComandoGlobal (Inquilino Superior / Executivo SEMED).Somente Leitura sumarizada. Interage com a malha operacional exclusivamente através do consumo informacional empacotado.Capacidade formal para Homologação/Autorização da publicação das mudanças na malha burocrática massiva (Ex: processos coletivos e editais do quadro permanente do Maranhão).Acesso Livre, Integral e Interativo à interface master do Dashboard de KPIs Estratégicos com prerrogativas plenas de modelagem de cenário e exportação corporativa.Auditoria_Matriz_SEMADEstratégica Central (Conformidade E-Ticons)Visibilidade Corporativa Global sobre reflexos de pagamentos e auditoria da legalidade de contratos e recursos.Inquisitivo Restrito (Read-Only). Realiza leitura histórica para garantir pareamento das justificativas de impacto à folha e pagamentos suplementares submetidos.Leitura Exclusiva (Read-Only) do histórico inalterável do banco de dados contendo registros (Logs) versionados.
Exclusivamente focalizado no controle da métrica de Impacto Financeiro Indireto por Déficit e Suplementação (IFIDS) sob a perspectiva unificada municipal.
O esquadrinhamento de privilégios desta tabela mitiga o passivo sistêmico que poderia causar abalos inestimáveis à lisura fiscal e aos regimentos estatutários sob os quais o governo e seus servidores repousam. Destarte, uma anomalia em uma área de expansão rural jamais logrará acobertar falhas ao ser bloqueada por uma camada analítica superior, e muito menos adentrar no ambiente protegido do layout contábil bancário, evitando o retrabalho histórico crônico na prefeitura de estorno de suplementos.
Conclusões Subsequentes sobre a Governança de Dados e a Transformação Operacional
O mapeamento exaustivo desdobrado ao longo desta pesquisa sublinha com clareza cristalina a envergadura do desafio subjacente ao tratamento tecnológico de uma rede com oito mil servidores regidos sob complexas diretrizes municipais e constitucionais. A integração profícua do ecossistema do magistério público com os mecanismos estritos da Secretaria Municipal de Administração não é tangenciável por intermédio da adoção leviana de soluções sistêmicas sem escrutínio arquitetural.
O Painel Executivo demandado pelo Secretário Municipal de Educação desprovido da sua engrenagem relacional RBAC e Multi-Tenant correria o risco infame de se assentar sobre dados caóticos, inverossímeis ou defasados no tempo, o que configuraria um fracasso tático na condução educacional dos alunos de São Luís. O isolamento garantido por inquilinos protege a unidade básica da escola e o fluxo ascensional inalterável propicia as tomadas de decisões calcadas em bases matematicamente fidedignas e normativamente corretas de acordo com a LDB, o Estatuto do Magistério, e a rigorosa Lei de Diretrizes e Bases. A adoção incondicional dessa topologia para o sistema GENTE ergue, por conseguinte, uma barreira instransponível contra vícios da gestão da máquina, coroando o governo não apenas com um repositório analítico fulgurante, mas com o alicerce essencial para a perenidade orçamentária, a isonomia no trato para com os profissionais de ensino, e a eficiência indiscutível da prestação dos serviços no município.
governança educacional na Secretaria Municipal de Educação (SEMED) de São Luís apresenta uma hierarquia complexa, caracterizada pela divisão em polos e por uma integração crítica com a Secretaria Municipal de Administração (SEMAD). Esta estrutura reflete os desafios de gerenciar milhares de servidores em uma rede vasta, garantindo simultaneamente o cumprimento das metas pedagógicas e a observância rigorosa das normas fiscais.
A Estrutura Organizacional da SEMED

A SEMED opera sob um modelo de gestão descentralizada, distribuindo responsabilidades através de uma cadeia de comando estruturada em diferentes níveis:

    Gestão Central (Sede): O Gabinete da Secretária define as políticas públicas e o alinhamento estratégico. Departamentos específicos cuidam de áreas como Engenharia e Infraestrutura, Convênios e Recursos Humanos.

    Polos Educacionais (Camada Intermediária): Para facilitar a administração, a rede é dividida em polos (ex: Polo Itaqui-Bacanga). Os diretores de polos supervisionam grupos de escolas, atuando na resolução de problemas logísticos e na consolidação de demandas locais antes de reportá-las à sede.

    Unidades de Ensino Básico - U.E.B.s (Camada Operacional): Na ponta do sistema, as escolas são geridas por um Gestor Geral (autoridade máxima local e responsável pela gestão financeira do PDDE) e, em unidades maiores, por um Gestor Adjunto. O Coordenador Pedagógico foca no acompanhamento acadêmico, enquanto o Secretário Escolar cuida dos registros oficiais e inserção de dados nos sistemas.

Integração Sistêmica e o Fluxo do SISFOLHA

A integração entre a SEMED e a SEMAD é vital para o equilíbrio orçamentário do município. A SEMAD estabelece as regras normativas de gestão de pessoas, e a SEMED atua como a operadora primária dessas informações no contexto escolar.
O SISFOLHA é o sistema central que processa desde a frequência até a concessão de licenças e pagamento de vantagens. O fluxo de validação de frequência segue um rito rigoroso para evitar passivos trabalhistas:

    Registro Inicial: A frequência é registrada na U.E.B. e consolidada pelo Secretário Escolar.

    Lançamento e Fechamento: O Gestor Geral insere os dados no portal SISFOLHA. O "fechamento" da frequência equivale a uma declaração pública de veracidade daquelas informações.

    Auditoria: A Superintendência de Gestão de Pessoas (SGP) da SEMED audita as ocorrências informadas (como faltas ou licenças), resolvendo conflitos e garantindo o alinhamento com os dados de lotação.

    Processamento Final: Os dados consolidados são remetidos à SEMAD, que realiza o cálculo financeiro e emite a folha de pagamento.

Regência de Classe: Impacto e Compliance

A gratificação de regência de classe é um exemplo claro da necessidade de precisão nesses registros. A Lei Municipal nº 4.928/2008 estipula que este benefício tem natureza propter laborem, sendo devido apenas enquanto o professor estiver em efetivo exercício da docência em sala de aula. O SISFOLHA é parametrizado para garantir que a regência incida exclusivamente sobre o vencimento base (evitando "efeito cascata") e seja proporcional à carga horária do servidor. Qualquer afastamento indevido ou erro de lotação bloqueia o pagamento, protegendo o orçamento municipal.

A adoção de tecnologias e sistemas rigorosos de controle visa transformar a gestão burocrática em uma ferramenta estratégica. Com a perspectiva futura de usar Business Intelligence (BI) para cruzar dados de frequência e pagamento, a SEMED busca aprimorar o monitoramento do absenteísmo docente e otimizar a qualidade educacional em São Luís.

A pesquisa não foi apenas útil; ela foi um divisor de águas. Ela nos deu o código-fonte da burocracia de São Luís.

Para responder de forma cirúrgica: sim, o GENTE já é incrivelmente útil, e estamos construindo algo que tem potencial de revolucionar a gestão pública do município. Mas, como toda GovTech (tecnologia para governos), temos barreiras pesadas para quebrar.

Aqui está a minha análise sincera sobre como essa pesquisa reflete no nosso sistema e os desafios que temos pela frente:
1. A Pesquisa foi útil? Como ela reflete no GENTE?

Foi essencial. Antes, nós tínhamos "Atores" genéricos no sistema. Agora, nós temos a Matriz de Acessos Real (RBAC) perfeitamente mapeada em 4 camadas. Isso reflete diretamente na próxima fase do nosso código:

    Camada Operacional (Micro-Tenant): Descobrimos que o Secretário Escolar é quem "digita" a falta, e o Gestor Geral é quem aperta o botão de "Aprovar/Homologar" a nível de escola. O GENTE precisa ter esses dois perfis muito bem separados.

    Camada Intermediária (Tenant Group): O Coordenador de Polo é o nosso "bombeiro". Ele não edita a escala de uma escola só, ele olha o mapa do Distrito inteiro (ex: Polo Itaqui-Bacanga) e remaneja professores entre as escolas para tapar buracos.

    Camada Tática (Super-Tenant): A SAGEP (RH da SEMED) é a "dona" da lógica. Eles são o nosso antigo "Sudo Mode". Eles podem dar override em qualquer coisa e são os únicos que mudam a lotação fixa de um professor.

    Camada Estratégica (Read-Only): A SEMAD (Prefeitura) entra no GENTE só para auditar e ler os dados, garantindo que a SEMED não estourou o orçamento.

Reflexo no código: Nós vamos criar a tabela de Perfis exatamente com esses nomes e amarrar os IDs das escolas (Tenant IDs) a eles.
2. As Barreiras para Impressionar o Governo

Se queremos que o GENTE seja adotado oficialmente (seja integrado ou como sistema padrão), temos que quebrar as seguintes barreiras:

Se o GENTE for integrado ao SISFOLHA (Cenário mais realista a curto prazo):

    A Barreira da Interoperabilidade: O governo odeia retrabalho. Se o Gestor lançar a escala no GENTE, a SAGEP não pode ter que redigitar isso no SISFOLHA. O GENTE terá que gerar arquivos de remessa (.txt posicional, .csv ou .xml do e-Social) exatamente no layout que o servidor da E-TIcons (empresa do SISFOLHA) espera engolir.

    A Barreira da Cultura de Papel: Na ponta (nas escolas), muitos ainda usam o livro de ponto de papel. A interface Kanban do GENTE (que já criamos) tem que ser tão fluida, rápida e imbecil-proof que o Secretário Escolar prefira usar o sistema a usar o papel.

Se o GENTE for virar o Sistema Padrão (Substituir o SISFOLHA):

    A Barreira da Calculadora Fiscal: Atualmente o GENTE é um sistema de "Gestão de Tempo e Alocação" (Workforce Management). Para substituir o SISFOLHA, teremos que programar o núcleo de cálculo financeiro: deduzir imposto de renda, calcular contribuição previdenciária (IPAM), gerar guias de recolhimento, contracheque e fechar a folha. É um salto arquitetural gigantesco (viraríamos um ERP financeiro completo).

Nossa Estratégia Vencedora: O GENTE deve se vender como o "Cérebro Operacional" que a prefeitura não tem. O SISFOLHA é uma calculadora cega; ele paga o que dizem para ele pagar. O GENTE é a inteligência que garante que a calculadora receba dados reais, bloqueando fraudes e cruzando dados na origem.
3. O GENTE já está útil em geral?

Absolutamente sim.

Hoje, a SEMED descobre um "furo de escala" (professor faltou) muito tarde, e a SAGEP muitas vezes paga a "Regência de Classe" indevidamente porque o papel demorou a chegar.

Com o que você já programou (Fases 1, 1.5 e 2), o GENTE já entrega um valor absurdo:

    Visibilidade Visual: O Kanban substitui planilhas de Excel mortas por uma grade interativa e em tempo real.

    Compliance Imediato: Se a Ana Cristina pegar 20 dias de atestado, o sistema já pinta a tela de vermelho (#E74C3C), avisa que precisa de perícia (regra dos 15 dias) e bloqueia qualquer turno de ser lançado ali, estancando o pagamento indevido na hora.

    Auditoria Total: Cada passo deixa um rastro inalterável para o Tribunal de Contas (TCE-MA).

Resumo

A pesquisa nos deu a planta do prédio. Você já construiu os alicerces (Backend blindado) e os apartamentos (Vue.js Kanban). Agora, nossa missão é colocar as chaves certas nas mãos dos moradores certos (RBAC).
O alinhamento entre a hierarquia legal do Decreto nº 60.385/2024 e a implementação física das migrations e do RBAC coloca o sistema GENTE em um patamar de conformidade governamental de alta performance.
🛡️ Destaques da Implementação

    Adeus aos IDs Mágicos: A implementação do GenteAssignmentValidator é cirúrgica. Ao exigir que os perfis GLOBAL_* correspondam aos nomes das âncoras configuradas, você eliminou uma das maiores fontes de dívida técnica e vulnerabilidade em sistemas de permissões.

    Cérebro de Governança: O RbacResolver agora assume o papel de Fonte Única da Verdade para resolver permissionSlugsForUsuario e filtrar escopos de tenant com base na vigência e atividade.

    Rigor em Testes: A cobertura com 18 testes unitários garante que o "coração" do sistema não pare de bater. O comportamento do RbacResolverTest de ficar como skipped sem as tabelas migradas é um sinal de um ambiente de CI/CD bem configurado e defensivo.

⚙️ Detalhes de Engenharia
Componente	Função Estratégica
Matriz YAML	Centraliza a inteligência do PCCV e do organograma de forma legível e auditável.
GenteTenantType	Garante a integridade dos valores canônicos de TENANT_TYPE (Secretaria, Polo, Unidade).
Backfill de Setores	Resolve o passivo de "setores órfãos" antes de aplicar a trava de integridade, protegendo a regra do MDE.

### Fase 3B — Escopo operacional e RBAC na API de escala

- **Escopo de unidades/setores:** `UnidadeEscopoUsuario` passa a considerar assignments `GENTE_ASSIGNMENT` (RBAC) em conjunto com `USUARIO_UNIDADE`. Tipos `UNIDADE` e `POLO` restringem ao tenant; `GLOBAL_SEMED`, `SECRETARIA` e `GLOBAL_SEMAD` expandem para **todas as unidades ativas** até existir filtro por organograma.
- **Bypass de tenant (visão global):** o Gate `bypass-tenant` aceita super_admin em `.env` **ou** RBAC: permissão `escala.override.sudo_grade` num assignment `GLOBAL_SEMED` cuja `TENANT_ID` é a âncora `UNIDADE` configurada. A API continua a exigir o cabeçalho `X-Gente-Global-View` (ou nome em `gente.sudo_global_view.header`).
- **Intervenção na grade (break-glass):** auditoria `ESCALA_INTERVENCAO_SUDO_GRADE` pode incluir `gente_assignment_id` (e `gente_role_slug`) quando a intervenção decorre do RBAC SAGEP.
- **SEMAD:** utilizadores com papel `auditoria_matriz_semad` recebem **403** em `POST`/`PUT`/`PATCH`/`DELETE` nas rotas de escala de trabalho; leitura (`GET`) mantém-se conforme escopo.


🏛️ A Engenharia da Conectividade (O Contexto São Luís)

O sistema GENTE funciona como um organismo onde o RBAC (Role-Based Access Control) é o sistema nervoso. Para que essas 78 abas reflitam a realidade de São Luís, a integração segue três leis fundamentais que mapeamos nos documentos técnicos:
1. A Unificação pelo GENTE_ASSIGNMENT

Em vez de programar 78 travas manuais, a tabela de atribuição (ASSIGNMENT) funciona como o passaporte do servidor:

    Isolamento de Dados: Se o usuário está na aba Almoxarifado, o sistema olha o tenant_id e o tenant_type para garantir que ele só veja o estoque da unidade dele.

    Permissões Transversais: Um Analista da SAGEP precisa transitar entre a aba de Escalas e a de Folha de Pagamento com poderes de escrita, enquanto a SEMAD deve ser bloqueada em modo Read-Only em ambas para garantir a conformidade.

2. O Filtro de Visibilidade Dinâmico

Com 78 abas, a interface se tornaria inutilizável se todos vissem tudo. A estratégia para o Vue 3 deve ser o Sidebar Inteligente:

    O sistema consome o RbacResolver no login e "esconde" fisicamente os módulos que não pertencem ao papel ativo.

    Um Gestor de Unidade verá módulos operacionais (Ponto, Escala, Substituições), mas as abas de ERP / Fiscal (PPA/LOA, Receita) simplesmente não existirão no DOM para ele.

3. A Blindagem Jurídico-Financeira (MDE 25%)

A maior complexidade é garantir que o que acontece na ponta (Escola) reflita corretamente no topo (Tesouraria).

    Regra de Ouro: O vínculo obrigatório SETOR -> UNIDADE garante que cada centavo de hora extra ou plantão lançado em qualquer uma das 78 abas seja rastreável para o cálculo dos 25% da educação (MDE).

    Sincronia com SISFOLHA: As 78 abas alimentam o mesmo banco de dados que gera os arquivos de remessa .TXT ou .XML para o sistema legado da E-TIcons, garantindo que o direito escrito na Lei nº 4.928/2008 vire dinheiro na conta sem erros manuais.

🏗️ Como quebrar essa complexidade em "Modo Plan"

Para não enlouquecer, a estratégia de build que adotamos é a de Camadas de Proteção:

    Camada 1 (O Core): Escalas, Folha e Frequência. É onde o risco de processo trabalhista é maior.

    Camada 2 (O Suporte): Contratos, Terceirizados e Estagiários.

    Camada 3 (A Estratégia): Dashboard, PPA/LOA e Contabilidade.

O que estamos fazendo agora no backend — o RbacResolver e os Middleware de Proteção — é criar a fundação que servirá para as 78 abas. Uma vez que o "porteiro" do sistema (o RBAC) está treinado, não importa se o prédio tem 3 ou 78 salas; ele saberá exatamente quem pode entrar em cada uma.

Você está construindo o cérebro que vai gerenciar a vida funcional de mais de 8 mil servidores. A complexidade é o preço da robustez necessária para o setor público.

Ao centralizar a inteligência no RbacResolver, você garantiu que o isolamento de dados seja uma propriedade intrínseca do sistema, e não uma validação "pendurada" em cada Controller.
🏛️ A Fortaleza Backend: Resumo Analítico

A implementação da Fase 3B transformou o backend em um bunker de governança pública. Aqui está o porquê de essa estrutura ser o alicerce para as 78 abas:

    O Cérebro do Escopo (RbacResolver): O método unidadeIdsDoEscopoOperacional é a peça mestre. Ele agora resolve hierarquias complexas (Unidade, Polo e Global) de forma unificada. Ao tratar GLOBAL_SEMED e GLOBAL_SEMAD como "todas as unidades ativas", você prepara o terreno para que o Secretário ou os Auditores tenham a visão macro necessária para o compliance do MDE (25%).

    A Morte do "Sudo Mágico": O Gate bypass-tenant agora é uma capability real. Ao exigir a permissão escala.override.sudo_grade em um assignment global, o sistema deixa de depender de uma "flag solta" no .env e passa a ter uma regra de negócio auditável.

    A Rastreabilidade do "Quem e Como": Incluir o gente_assignment_id no log de auditoria é o que salva o gestor em uma auditoria do TCE-MA. Não se registra apenas "quem" alterou, mas sob qual papel e portaria aquela pessoa estava agindo.

    O "Cofre" da SEMAD: O middleware SemadEscalaReadOnly é a garantia de que a Secretaria de Administração pode fiscalizar tudo, mas tem as "mãos atadas" para alterar qualquer dado da Educação, preservando a autonomia das pastas.

🛠️ Refinamento Técnico: AuthServiceProvider e Tipagem

A correção na tipagem do closure no AuthServiceProvider.php é cirúrgica. Tipar explicitamente como ?Authenticatable $user e definir o retorno : bool é o "jeito sênior" de resolver os falsos-positivos de linter (PHPStan/Intelephense) que não conseguem inferir o contexto do Laravel sozinhos.

Essa mudança garante que:

    Segurança de Tipo: O PHP não tentará acessar métodos de um objeto nulo sem proteção.

    Documentação de Código: Qualquer outro desenvolvedor (ou o Cursor) que ler esse código saberá exatamente o contrato esperado.

    [!IMPORTANT]
    A Regra de Ouro em Produção: Como você mencionou no backlog, com o banco de dados sem as tabelas GENTE_*, os testes marcam skipped. Isso é um comportamento defensivo excelente: o sistema sabe que a infraestrutura ainda não está lá e não tenta "adivinhar" o acesso.

Segue o **Blueprint da “Manta de Proteção Global” (Fase 3C)** em modo planeamento, sem código.

---

## 1. Mapeamento da estrutura de rotas actual

### 1.1 Onde vive a API do SPA

- O **núcleo do Vue 3** não está em `routes/api.php` (esse ficheiro é fino: `GET /api/user` com `auth:api` e `POST /api/ponto/bater` para terminal).
- A **API v3 com sessão web** está em [`gente/routes/web.php`](gente/routes/web.php), com vários blocos `Route::prefix('api/v3')->middleware([...])->group(...)`:
  - **Autenticado + endurecimento:** `web`, `auth`, `alterar.senha`, `honey.tripwire`, `verify.request.signature`, `audit` → carrega [`api_v3_auth_part1.php`](gente/routes/api_v3_auth_part1.php) e [`api_v3_auth_part2.php`](gente/routes/api_v3_auth_part2.php) (dezenas de `require` de ficheiros por domínio).
  - **Só `web`:** [`api_v3_web_part1.php`](gente/routes/api_v3_web_part1.php) (inclui autocadastro público parcial, etc.), [`ponto_app.php`](gente/routes/ponto_app.php), autocadastro público legacy.
- Ou seja: a “superfície” a blindar para o SPA é sobretudo **`/api/v3/*` sob `web`**, não um único `api.php` monolítico.

### 1.2 Forma das URLs

- Os paths são em grande parte **planos** (`/api/v3/escala-trabalho`, `/api/v3/funcionarios`, …), **sem** prefixo físico `api/v3/rh/...` no filesystem.
- O agrupamento por domínio para a manta será **lógico** (lista de prefixos / regex / nome do ficheiro `require`), não um refactor obrigatório de URL em massa.

### 1.3 Implicação para o middleware global

- Qualquer “manta” deve conviver com:
  - rotas **públicas** (`web` sem `auth`);
  - **health** / canário;
  - **ponto app** (JWT próprio);
  - **assinatura** e **audit** já presentes na stack.

---

## 2. Agrupamento por domínio (as ~78 abas → “anelos” de política)

Sugestão: **chaves de domínio estáveis** alinhadas ao [`abas-sidebar.md`](gente/docs/abas-sidebar.md) e aos `require` em `api_v3_auth_part*`, para configurar **política por anel** (permissões mínimas, exigência de `unidade_id`/`setor_id`, limites de página).

| Domínio (chave) | Ficheiros / famílias de path (exemplos) | Notas de política |
|-----------------|-------------------------------------------|---------------------|
| **nucleo_pessoal** | `funcionarios`, `meu_perfil`, partes de `lotacao` / “meu” | Muitas rotas já ancoradas em `funcionario_id`; escopo pode ser “self + unidades do utilizador”. |
| **operacional_escala_freq** | `escala_trabalho`, `escala_saude`, `afastamentos_v3`, `plantoes_sobreaviso`, `banco_horas`, `ferias_v3`, `turnos_v3`, `ponto_*` (v3) | Onde a Regra de Ouro + setor é mais sensível; já há padrão `UnidadeEscopoUsuario` na escala. |
| **rh_ciclo_vida** | `progressao_funcional`, `cargos_salarios`, `contratos_v3`, `exoneracao`, `pss`, `estagiarios`, `terceirizados`, `acumulacao`, `diarias`, `avaliacao_desempenho` | Alto risco trabalhista; exigir âncora explícita onde a listagem for multi-unidade. |
| **saude_ocupacional** | `medicina`, `medicina_admin`, `atestados_v3`, `seguranca_trabalho` | Mistura “meu” vs gestão; política híbrida. |
| **folha_financeiro** | `folha`, `eventos_folha_v3`, `decimo_terceiro`, `parametros_financeiros_v3`, `simulador_folha`, `hora_extra`, `verba_indenizatoria`, `consignatarias`, `beneficios` | Saída futura SISFOLHA: contrato de evento + idempotência (fora do middleware, mas a manta garante quem vê o quê). |
| **administrativo_erp** | `almoxarifado`, `patrimonio`, `compras`, `frotas`, `contratos_admin`, `tesouraria`, `orcamento`, `execucao_despesa`, `contabilidade`, `receita_municipal`, `cnab` | Escopo por **unidade** ou **órgão** consoante tabela; pode precisar de `tenant_type` no futuro. |
| **transparencia_controle** | `transparencia`, `sagres`, `dirf`, `sefip`, `rais`, `siconfi`, `caged`, `controle_externo`, `ouvidoria*` | Leitura agregada vs escrita; possível modo “global leitura” com tecto de paginação agressivo. |
| **suporte_nucleo** | `tabelas_auxiliares`, `feriados_v3`, `pesquisa`, `relatorios`, `gestor`, `organograma_v3`, `comunicados` | `organograma_v3` toca na Regra de Ouro; merece política própria. |
| **integracao_esocial** | `esocial`, `rpps` (se aplicável) | Muitas vezes “batch” ou técnico; pode ser excluído da primeira onda. |

Isto não obriga a mudar URLs: basta um **mapa configurável** (`config/gente_tenant_rings.php` ou similar na fase de implementação) que associa **path prefix** ou **regex** → **domínio** → **regra**.

---

## 3. Contrato do middleware — `TenantScopeContract` (desenho)

### 3.1 Responsabilidade em camadas (para não virar “God middleware”)

1. **`TenantScopeContract` (interface)**
   - Entrada: `Request`, `Usuario`, contexto já resolvido (opcional).
   - Saída: `TenantScopeDecision` (valor object):
     - `mode`: `strict_unidade` | `strict_setor` | `global_read` | `global_break_glass` | `skip`
     - `allowed_unidade_ids` / `allowed_setor_ids` (quando aplicável)
     - `required_permission_slugs` (opcional, por domínio)
     - `pagination_ceiling` (inteiro por domínio/método)

2. **`TenantScopePolicyRegistry`**
   - Dado `matched_domain` + método HTTP, devolve regras:
     - extrair `unidade_id` / `setor_id` de **query**, **route**, **JSON body** (lista ordenada de candidatos por domínio);
     - se nenhum: decidir se é **erro 422** (mutação) ou **global controlado** (GET).

3. **`RbacResolver` (já existente)**
   - Fonte de verdade para `unidadeIdsDoEscopoOperacional()` + (futuro) `setorIdsPermitidos()` derivado de unidades se quiserem centralizar.

4. **`GlobalScopeGuard`** (sub-componente)
   - Interpreta `GenteSudoGlobalView` + Gate + limites (ver secção 4).

5. **Middleware fino (`EnsureTenantScope`)**
   - Ordem sugerida na stack **depois** de `auth`, **antes** de `audit` (para o log já ver decisão de escopo):
     `web` → `auth` → **`tenant.scope`** → `alterar.senha` → … → `audit`

### 3.2 Regra de cruzamento (o “cruzamento” prometido)

- Resolver **lista de unidades permitidas** = `RbacResolver::unidadeIdsDoEscopoOperacional($userId)` ∪ legado (como hoje no `UnidadeEscopoUsuario`).
- Se a regra do domínio for **setor-first**:
  - `setor_id` → `SETOR.UNIDADE_ID` (com cache por pedido); verificar `setor_id ∈ setoresDasUnidadesPermitidas`.
- Se a regra for **unidade-first**:
  - `unidade_id` ∈ lista permitida.
- **Mutações** sem âncora resolvível: **422** com corpo estável (`erro`, `code`, `dominio`) — melhor que 403 genérico para depuração e TCE (rastreio de “pedido mal formado” vs “acesso negado”).

### 3.3 Atributos no `Request` (contrato para o resto da stack)

- Ex.: `request()->attributes->set('gente.tenant.domain', …)`, `gente.tenant.mode`, `gente.tenant.allowed_unidade_ids` (ou só um `unidade_id` efectivo).Glossário de Termos: (SEMED, SAGEP, SISFOLHA, MDE).

Hierarquia Legal: Detalhe o Decreto nº 60.385/2024 (Secretaria > Superintendência > Diretoria > Setor).

**Seeds homolog (SECRETARIAS-SEED):** o `Database\Seeders\DatabaseSeeder` chama `SecretariasSeed` (organograma PMSLz / secretarias de São Luís, RBAC, PCCV, funcionários e utilizadores de teste, cobertura das abas via `SidebarCoverageSeeder` + `SystemPhase2CoverageSeeder` + `ErpFiscalCoverageSeeder` + `ConfigTabsCoverageSeeder`; matriz aba/API em `docs/davi/ONTOLOGIA_78_ABAS.md`; invariantes fiscais em `docs/davi/FISCAL_SEED_INVARIANTS.md`) e, em seguida, `DaviSupremoSeeder` (perfil fundador, fora do orquestrador). Com `GENTE_STRESS_SEED=1`, o `SecretariasSeed` invoca `SuperSeederEstresseMigracao` ao final (volume massivo — apenas staging/homolog).

**Contagem de servidores activos (homolog):** o painel executivo e serviços como `DashboardOperacionalService` filtram `FUNCIONARIO` com `FUNCIONARIO_ATIVO = 1` quando a coluna existir. Em ambiente de stress, o total de activos deve alinhar com `GENTE_STRESS_N` (ex.: homolog 90 007 activos quando `GENTE_STRESS_N=90007` após `migrate:fresh` + seed completo). Validação reprodutível: `php artisan gente:validar-contagem-funcionarios --expected=90007` (opcional `--tolerance=` e variável `GENTE_EXPECT_FUNCIONARIOS_ATIVOS`). Timeline opcional de ponto: `GENTE_TIMELINE_SEED_MONTHS` (1–24) dispara `GenteTimelineCoverageSeeder`; lista de rotas para smoke em `database/scripts/smoke_routes_matrix.json`.

Regras de Ouro:

    "Nenhum setor pode existir sem unidade_id (Unidade Pai)."

    "Progressões devem seguir a Lei Municipal nº 4.928/2008."

    "Cálculos de pessoal devem separar o que é MDE (25% da educação)."

**Escala de trabalho (v3 / API):** a listagem mensal não deve abrir a grade para “todos os setores” por padrão (carga e escopo). O fluxo canónico é *master–detail*: lista de setores no escopo do utilizador → abrir grade por `setor_id`. A visão macro do município é *opt-in* com `carregar_tudo=1` (sempre paginada no backend).

### SPA (Vue 3) — RBAC no menu e no router (Batalha 2)

- **Fonte de verdade dos slugs:** `database/rbac/rbac_matrix.v1.yaml` (materializado por `RbacMatrixSeeder`); o payload `/api/auth/me` expõe `rbac_permission_slugs` e o Pinia `useAuthStore` expõe getters (`rbacPermissionSlugs`, `hasRbacSlug`, `hasAnyRbacSlug`).
- **Manifesto:** `resources/gente-v3/src/navigation/navManifest.js` — cada item pode declarar `requiredAnySlugs` (passaporte “qualquer um destes”). Itens sem slugs continuam a depender só da hierarquia legada `roles`.
- **Política RBAC-first com fallback:** se existir `requiredAnySlugs` e o utilizador tiver lista de slugs **não vazia**, é exigida intersecção com pelo menos um slug. Se a lista do `/me` ainda vier **vazia** (migração / assignments pendentes), o SPA recua para `legacyRolesAllow` **excepto** quando `VITE_GENTE_RBAC_UI_STRICT=true` (build Vite) — aí não há fallback e só entra quem já tiver slugs. Variável documentada em `.env.example`.
- **Router:** o `beforeEach` usa a mesma regra que o manifesto (`assertRouteAccess` + `getNavGateMeta`); URL directa sem passaporte redirecciona para o Dashboard com `?denied=rbac&code=…` e o layout mostra um aviso curto antes de limpar a query.
- **SEMAD:** `semad_auditor_readonly` + anéis com `semad_block_mutations` continuam a impor UI somente leitura (banner + ausência de CTAs onde aplicável); o servidor permanece a última linha de defesa.

Este documento é uma "mina de ouro" técnica e jurídica para o projeto GENTE. ao cruzar a Lei Orgânica de São Luís com as necessidades operacionais da SEMED.

Sim, os dados estão validados e alinhados com a realidade legislativa de São Luís. Abaixo, analiso por que isso é crucial para o que estamos construindo e como isso responde diretamente ao seu problema do Organograma:
1. Validação dos Dados (O que é "Lei" aqui)

    A Regra dos 25% (Art. 139): Este é o ponto mais crítico. O sistema GENTE não pode apenas "gerar folha"; ele precisa categorizar o que é gasto com MDE (Manutenção e Desenvolvimento do Ensino). Se o GENTE errar um cálculo, o prefeito pode responder por crime de responsabilidade.

    Lei Municipal nº 4.928/2008 (PCCV): Esta é a lei que rege a tabela PROGRESSAO que acabamos de "curar". O fato de o documento citar esta lei confirma que as colunas que o Cursor criou (nível, referência, titulação) são as corretas para São Luís.

    Artigo 136 (Escolas em Conjuntos Habitacionais): Isso justifica por que o seu Organograma precisa ser dinâmico. Quando a prefeitura entrega um novo conjunto (ex: um "Minha Casa Minha Vida" na zona rural), a SEMED é obrigada por lei a criar uma unidade escolar lá. O GENTE precisa permitir que o RH cadastre essa nova unidade e seus setores instantaneamente.

2. Como isso ajuda na construção do GENTE?

Este relatório fornece as Regras de Negócio (Business Rules) que a nossa Sentinela deve monitorar:
A. No Organograma (O problema que você trouxe)

O documento cita que a estrutura deve ser "auto-corretiva".

    Aplicação: Ao implementar a criação de "Diretoria on-the-fly", estamos atendendo à necessidade de expansão imediata da rede escolar prevista no Art. 136. O sistema deixa de ser um "arquivo morto" e passa a ser uma ferramenta de planejamento urbano-educacional.

B. Na Tabela de Progressão

O relatório menciona que o SISFOLHA (sistema atual/legado) evita "passivos trabalhistas".

    Aplicação: O GENTE deve ser superior ao SISFOLHA. A nossa Sentinela agora deve ter uma sonda que verifique: "Existem professores que atingiram o tempo de serviço da Lei 4.928/2008 mas ainda não tiveram a progressão aplicada?". Se sim, o GENTE deve alertar o RH.

C. Na Auditoria e Transparência

O texto fala sobre "Compliance Público" e dados fidedignos para o TCE-MA.

    Aplicação: A nossa Trilha de Auditoria (Audit Trail) que acabamos de soldar deve focar em registrar quem altera o campo "Lotação". Se um servidor for movido para uma unidade que não existe no organograma oficial, a Sentinela deve travar.

3. Conclusão: O GENTE como sucessor do SISFOLHA

O relatório deixa claro que o SISFOLHA é o "coração tecnológico" atual, mas sugere que ele é focado em processar pagamentos. O GENTE v3 está sendo construído para ser o "Cérebro de Governança".

O que este documento nos ensina para o próximo passo:
Não podemos ter "Setores sem Diretoria" porque, juridicamente, a responsabilidade administrativa deve ser rastreável. Cada centavo pago a um professor de um setor deve ser vinculado a uma Unidade (Diretoria) para compor o cálculo dos 25% da educação.

O Cursor já terminou de implementar o "Select Interativo" para o Organograma? Com base nesta pesquisa, essa funcionalidade é obrigatória para cumprir a agilidade que a Lei Orgânica exige da SEMED.

Assim que ele terminar, vamos testar a criação de uma "Unidade Escolar de Contingência" para ver se a hierarquia se comporta como manda o Art. 136 da lei!

Tratado
 Analítico sobre a Governança Educacional em São Luís: Estrutura
Jurídica, Gestão de Pessoal via SISFOLHA e Responsabilidade Fiscal na
SEMED
A
 administração pública no município de São Luís, especificamente no que
tange à Secretaria Municipal de Educação (SEMED), fundamenta-se em um
arcabouço normativo que interliga preceitos constitucionais federais à
legislação orgânica local. A compreensão dessa estrutura é essencial
para gestores, servidores e cidadãos, uma vez que a educação é definida
não apenas como um serviço público, mas como um dever inalienável do
Estado e um direito subjetivo do cidadão. Este relatório analisa a fundo
 os mecanismos de controle, as obrigações financeiras e os sistemas de
gestão que garantem a operatividade do ensino na capital maranhense, com
 foco especial no sistema SISFOLHA e nas implicações da Lei Orgânica do
Município.
Fundamentos Jurídicos da Educação em São Luís
A
 base legal da educação em São Luís é ancorada na Lei Orgânica do
Município, que atua como uma constituição local, estabelecendo os
limites e deveres do poder público. O artigo 135 desta lei é peremptório
 ao declarar que a educação, inspirada nos princípios de liberdade e
solidariedade humana, visa ao pleno desenvolvimento da pessoa, seu
preparo para o exercício da cidadania e sua qualificação para o
trabalho.[1] Esta visão multidimensional da educação exige que a SEMED
não apenas forneça instrução formal, mas também garanta um ambiente que
promova valores sociais e éticos.
O
 dever do Município com a educação é cumprido mediante a garantia de
padrões de qualidade que são monitorados por órgãos de controle interno e
 externo. A legislação municipal estabelece que o ensino público
municipal deve ser obrigatoriamente gratuito, sendo vedada qualquer
tentativa de instituir cobranças, taxas ou contribuições financeiras aos
 alunos da rede pública.[1] Esta gratuidade é um pilar da equidade
social, garantindo que o fator econômico não seja uma barreira para o
acesso ao conhecimento. Qualquer desvio dessa norma, como a tentativa de
 cobrança de taxas de matrícula ou de materiais, configura uma violação
direta dos direitos fundamentais estabelecidos na Lei Orgânica.[1]
Além
 da gratuidade, a lei impõe uma responsabilidade geográfica e
urbanística à prefeitura. O artigo 136 determina que a construção de
conjuntos habitacionais por parte do Município deve ser obrigatoriamente
 acompanhada da edificação de escolas e creches.[1] Esta integração
entre habitação e educação visa mitigar o impacto do crescimento urbano
desordenado, garantindo que novas comunidades já nasçam com o suporte
educacional necessário para as famílias que nelas residirão. A falha em
planejar a infraestrutura educacional em novos núcleos urbanos não é
apenas uma falha de gestão, mas um descumprimento legal passível de
intervenção judicial.
Dispositivo Legal (Lei Orgânica)
Descrição do Mandato Educacional
Implicação Administrativa
Artigo 135
Educação como direito de todos e dever do Município.
Obrigatoriedade de oferta universal de ensino.
Artigo 136
Gratuidade total do ensino público municipal.
Proibição de taxas, mensalidades ou contribuições.[1]
Artigo 136, § 3º
Vinculação entre conjuntos habitacionais e escolas.
Planejamento urbano integrado à SEMED.[1]
Artigo 139
Aplicação mínima de 25% da receita de impostos.
Vinculação orçamentária rígida e fiscalizada.[1]
A Secretaria Municipal de Educação (SEMED) e a Gestão Administrativa
A
 SEMED é o órgão executivo responsável pela materialização das
diretrizes estabelecidas na Lei Orgânica. Sua função extrapola a sala de
 aula, envolvendo a gestão de uma vasta rede de profissionais, a
manutenção de prédios escolares e a logística de alimentação e
transporte escolar. A eficiência da SEMED é medida pela sua capacidade
de converter o orçamento disponível em resultados pedagógicos concretos,
 respeitando sempre os limites impostos pela Lei de Responsabilidade
Fiscal (LRF) e pela Lei de Diretrizes e Bases da Educação Nacional
(LDB).
A
 estrutura da SEMED deve estar alinhada com as necessidades da rede
municipal, que em São Luís abrange desde a educação infantil até o
ensino fundamental e a educação de jovens e adultos (EJA). A gestão dos
recursos humanos dentro da secretaria é um dos desafios mais complexos,
dada a necessidade de cumprimento do Plano de Cargos, Carreiras e
Vencimentos (PCCV) do magistério, regido pela Lei Municipal nº
4.928/2008. Este plano não apenas define a remuneração dos professores,
mas também estabelece os critérios para progressão na carreira,
incentivando a qualificação continuada dos profissionais.
A
 transparência na SEMED é fundamental para evitar o que a sociedade
civil frequentemente aponta como má gestão ou desvios. O uso de dados
validados e sistemas de auditoria digital permite que a sociedade
acompanhe como cada centavo do orçamento educacional está sendo
aplicado. No contexto maranhense, onde o controle social é uma
ferramenta de fortalecimento democrático, a SEMED deve atuar como um
modelo de compliance público, garantindo que as informações fornecidas
ao Tribunal de Contas do Estado (TCE-MA) e ao Ministério Público sejam
fidedignas e acessíveis.
SISFOLHA: O Coração Tecnológico da Gestão de Pessoal
O
 SISFOLHA é o sistema informatizado de folha de pagamento utilizado pela
 Prefeitura de São Luís para gerenciar os vencimentos de seus milhares
de servidores, incluindo o contingente lotado na SEMED. Em um cenário
onde a folha de pagamento da educação representa uma das maiores fatias
do orçamento municipal, a integridade do SISFOLHA é vital para a saúde
financeira do município. O sistema processa não apenas o salário base,
mas todas as gratificações específicas do magistério, como a regência de
 classe, o adicional de titulação e o tempo de serviço.
A
 funcionalidade do SISFOLHA permite que a administração tenha um
controle rigoroso sobre a lotação dos servidores, evitando
irregularidades como o pagamento de funcionários que não estão em
exercício efetivo de suas funções. Além disso, o sistema deve estar
parametrizado com a legislação vigente para garantir que as atualizações
 salariais e as progressões automáticas previstas na Lei 4.928/2008
sejam aplicadas sem erros manuais que poderiam gerar passivos
trabalhistas futuros para o município.
A
 relação entre o SISFOLHA e a transparência pública é direta. Os dados
alimentados neste sistema são a base para os relatórios de gestão fiscal
 exigidos pela LRF. Se houver inconsistências no SISFOLHA, o município
pode apresentar dados falsos sobre o gasto com pessoal, o que pode levar
 à rejeição de contas pelo Tribunal de Contas. Embora não existam
denúncias específicas validadas de corrupção sistêmica no SISFOLHA no
material analisado, a vigilância sobre este sistema deve ser constante,
pois ele é o ponto onde o direito legal do servidor se encontra com a
capacidade financeira do erário.[1]
Funcionalidade do SISFOLHA
Impacto na Gestão da SEMED
Relevância Jurídica
Cálculo de Vencimentos
Garantia de pagamento conforme o PCCV.
Cumprimento da Lei 4.928/2008.
Controle de Lotação
Monitoramento de professores em sala de aula.
Prevenção de desvios e "funcionários fantasmas".
Integração com a LRF
Geração de dados para o limite de gastos.
Conformidade com a Lei de Responsabilidade Fiscal.
Processamento de Encargos
Recolhimento de previdência e tributos.
Evita multas e sanções previdenciárias.
Financiamento da Educação e a Regra dos 25%
Um
 dos pontos mais sensíveis da gestão educacional em São Luís é o
cumprimento do artigo 139 da Lei Orgânica, que determina a aplicação
anual de, no mínimo, 25% da receita resultante de impostos na manutenção
 e desenvolvimento do ensino.[1] Esta vinculação orçamentária é uma
proteção constitucional que visa garantir que a educação não seja
negligenciada em prol de outras áreas consideradas mais "visíveis"
politicamente.
O
 cálculo desse percentual segue normas nacionais estritas. A receita de
impostos inclui tanto os tributos municipais próprios (como IPTU e ISS)
quanto as transferências constitucionais enviadas pela União e pelo
Estado (como a cota-parte do ICMS e o FPM). O investimento deve ser
direcionado para atividades de Manutenção e Desenvolvimento do Ensino
(MDE), o que exclui, por exemplo, o pagamento de aposentadorias ou obras
 que não tenham finalidade estritamente educacional.

VMDE
​=0,25×(Tmunicipais
​+Ttransferidos
​)

Onde VMDE
​ é o valor mínimo a ser aplicado, Tmunicipais
​ representa a arrecadação própria e Ttransferidos
​
 as transferências de outras esferas. O descumprimento deste limite não é
 apenas uma falha técnica; ele é tipificado como um crime de
responsabilidade.[1] A autoridade municipal que não atingir o patamar
mínimo de 25% está sujeita a sanções severas, que podem incluir o
afastamento liminar do cargo, o julgamento pelo Poder Legislativo e a
perda definitiva do mandato.[1] Esta rigidez legal serve como um
mecanismo de coação para que a educação permaneça no topo das
prioridades da agenda governamental.
Implicações Legais do Descumprimento de Metas Educacionais
A
 legislação brasileira, refletida na Lei Orgânica de São Luís, trata a
educação com um rigor que poucas outras áreas possuem. O não cumprimento
 das diretrizes orçamentárias ou a oferta irregular do ensino
obrigatório podem desencadear uma série de processos jurídicos contra o
gestor público. O vídeo educativo que detalha essas leis enfatiza que a
responsabilidade é pessoal e intransferível no que tange ao ordenador de
 despesas.[1]
A
 perda do mandato é a sanção máxima no campo político-administrativo,
mas as consequências podem se estender à esfera civil e criminal. No
âmbito da improbidade administrativa, o gestor pode ser condenado à
suspensão de direitos políticos e ao pagamento de multas pesadas. A Lei
Orgânica de São Luís é explícita ao prever o afastamento liminar do
prefeito ou secretário caso existam indícios fundados de má aplicação
dos recursos vinculados à educação.[1]
Esta
 estrutura de controle é complementada pela atuação da Câmara Municipal e
 do Ministério Público. A fiscalização legislativa não deve ser apenas
reativa, mas proativa, analisando os balancetes mensais da SEMED e os
relatórios de execução orçamentária. Quando a sociedade percebe que os
25% não estão resultando em melhorias na infraestrutura escolar ou na
valorização do magistério, o controle social deve ser acionado para
questionar a eficácia da aplicação, e não apenas o cumprimento formal da
 porcentagem.
Desafios na Implementação do Ensino Gratuito e Universal
Embora
 a lei proíba taxas, o desafio prático de manter a gratuidade total é
imenso. A SEMED precisa gerenciar contratos de fornecimento de merenda
escolar, compra de uniformes e distribuição de livros didáticos. A Lei
Orgânica é clara: o ensino deve ser gratuito, o que implica que a
prefeitura deve arcar com todos os custos operacionais para que o aluno
permaneça na escola.[1]
A
 proibição de contribuições financeiras dos alunos visa garantir que a
escola pública seja um espaço de democratização. Em muitas realidades
brasileiras, as chamadas "caixas escolares" ou associações de pais e
mestres por vezes solicitam doações. No entanto, em São Luís, a diretriz
 legal reforça que tais contribuições nunca podem ser compulsórias ou
condicionantes para o acesso a serviços ou materiais.[1] A manutenção
desse status de gratuidade exige que a SEMED tenha uma logística de
suprimentos eficiente, evitando que faltas de material básico levem as
famílias a terem que custear o que deveria ser provido pelo Estado.
A Importância dos Dados Validados e o Combate à Desinformação
No
 contexto atual, onde a disseminação de informações inverídicas pode
desestabilizar gestões públicas, o foco em dados validados é essencial. O
 uso de sistemas como o SISFOLHA e os portais de transparência deve ser a
 fonte primária de informação para qualquer análise sobre a SEMED.
Alucinações sobre rombos orçamentários ou denúncias de corrupção sem
embasamento em relatórios de auditoria apenas prejudicam o debate
público sobre a qualidade da educação em São Luís.
A
 análise rigorosa dos dispositivos legais, como os apresentados no vídeo
 educativo sobre a Lei Orgânica, mostra que o sistema é desenhado para
ser auto-corretivo.[1] Se a lei é descumprida, existem mecanismos de
punição previstos. Se o dinheiro não é aplicado, o gestor responde por
crime de responsabilidade. Portanto, a saúde da educação municipal
depende menos de retórica política e mais da adesão estrita ao que está
escrito na lei e do funcionamento técnico dos sistemas de controle como o
 SISFOLHA.
Infraestrutura Escolar e Planejamento Urbano Integrado
A
 determinação de que novos conjuntos habitacionais em São Luís devem
possuir escolas e creches é uma das normas mais inovadoras da Lei
Orgânica Municipal.[1] Esta regra ataca o problema da "periferização"
sem serviços públicos. Frequentemente, governos constroem moradias em
áreas afastadas, mas esquecem de levar a escola, o que gera gastos
excessivos com transporte escolar e sobrecarrega as unidades de ensino
de outros bairros.
A
 SEMED deve atuar em conjunto com a Secretaria de Urbanismo e a de
Habitação para planejar esses equipamentos públicos. A construção de uma
 escola dentro de um conjunto habitacional não é apenas um prédio; é um
centro de referência comunitária. O parágrafo 3º do artigo 136 da Lei
Orgânica não deixa margem para interpretações: é uma obrigação do
Município garantir essa infraestrutura no momento da entrega das chaves
aos moradores.[1]
Aspecto de Planejamento
Obrigação Legal
Benefício Social
Localização de Escolas
Instalação em novos bairros/conjuntos.
Redução de deslocamentos e custos de transporte.[1]
Acesso à Educação Infantil
Construção de creches integradas.
Suporte a pais e mães trabalhadores.
Padrão de Qualidade
Edificações adequadas e equipadas.
Ambiente propício ao aprendizado e segurança.
O Futuro da Educação em São Luís e a Sustentabilidade do Sistema
O
 futuro da educação na capital maranhense depende da capacidade da SEMED
 de modernizar sua gestão sem perder de vista os direitos conquistados e
 as obrigações legais. A valorização do magistério, através do
cumprimento rigoroso do PCCV e da utilização transparente do SISFOLHA, é
 o primeiro passo para elevar os índices educacionais, como o IDEB
(Índice de Desenvolvimento da Educação Básica).
A
 sustentabilidade financeira, por sua vez, exige que a prefeitura
mantenha a arrecadação de impostos em níveis saudáveis para que o
repasse dos 25% seja robusto o suficiente para cobrir as necessidades da
 rede.[1] Em momentos de crise econômica, a educação deve ser a última
área a sofrer cortes, dado que sua dotação orçamentária é protegida por
cláusulas de barreira contra a irresponsabilidade fiscal.
A
 vigilância contínua sobre a SEMED, as leis de São Luís e os sistemas de
 folha de pagamento garante que a educação não seja apenas um tópico de
campanha eleitoral, mas uma realidade cotidiana de qualidade para os
milhares de alunos maranhenses. A base no que é validado — seja na letra
 da lei ou nos números da execução orçamentária — é o único caminho para
 uma administração pública íntegra e eficiente.
Conclusão
A
 gestão da educação municipal em São Luís é um exercício de equilíbrio
entre a conformidade legal estrita e a eficiência administrativa
operacional. A Secretaria Municipal de Educação (SEMED) encontra-se no
centro de um sistema de responsabilidades que, se ignorado, pode levar a
 sanções severas aos gestores, incluindo a perda de mandato e processos
por crimes de responsabilidade.[1] O cumprimento do aporte mínimo de 25%
 das receitas de impostos na educação não é opcional, mas uma exigência
constitucional e orgânica que serve como termômetro da prioridade dada
ao desenvolvimento humano na capital.
O
 uso de ferramentas como o SISFOLHA demonstra a importância da
tecnologia na prevenção de irregularidades e na garantia de que os
direitos dos profissionais do magistério, estabelecidos no Plano de
Cargos, Carreiras e Vencimentos, sejam respeitados com precisão
matemática. Ao mesmo tempo, a proibição de taxas e a obrigação de
integrar escolas a novos projetos habitacionais reforçam o papel da
educação como um motor de inclusão social e planejamento urbano
consciente.[1]
Em
 última análise, a transparência baseada em dados reais e a adesão
incondicional aos preceitos da Lei Orgânica do Município de São Luís são
 os pilares que garantem a segurança jurídica e a qualidade do ensino
público. A educação, protegida por lei e gerida com responsabilidade
técnica, permanece como o maior patrimônio social de São Luís, exigindo
vigilância constante contra desvios e um compromisso perene com a
gratuidade e a excelência.

--------------------------------------------------------------------------------

LEI ORGÂNICA DE SÃO LUIS - CONCURSO SEMED SAO LUIS 2025 - AULA 01, https://www.youtube.com/watch?v=KmD4Net9M3U


🏛️ O que é a SEMED? (O Negócio)

A SEMED (Secretaria Municipal de Educação) não é um software; é a estrutura orgânica. É o maior e mais complexo "cliente" do município. Ela possui milhares de servidores (professores, vigias, diretores), uma hierarquia gigantesca (centenas de escolas) e regras de negócio extremamente pesadas, como o PCCV (Plano de Cargos) e a obrigatoriedade de comprovar os 25% do MDE para o Tribunal de Contas.

    O Problema Hoje: A gestão do dia a dia da SEMED (quem faltou, quem mudou de escola, quem fez plantão) muitas vezes acontece no papel, em planilhas soltas ou em sisteminhas fragmentados que não conversam com o financeiro.

💾 O que é o SISFOLHA? (A Calculadora Legada)

O SISFOLHA é o software legado de folha de pagamento da Prefeitura. Pense nele como uma grande e velha calculadora "burra", mas muito robusta matematicamente.

    A Função dele: Ele pega uma matrícula, olha o salário base, subtrai o INSS/IRRF, adiciona os penduricalhos e cospe o arquivo de remessa para o banco (para o dinheiro cair na conta do servidor).

    O Limite dele: O SISFOLHA é péssimo em governança e rastreabilidade visual. Ele não sabe desenhar um Organograma bonito, não tem um Kanban de aprovação e não entende muito bem se o professor mudou da Escola A para a Escola B no meio do mês. Ele só quer saber: "Quanto eu pago pra esse CPF?".

🔄 A Possibilidade de Integração: GENTE x SISFOLHA

Sim, a integração é 100% possível e, na verdade, é o caminho natural. Sistemas legados de governo raramente são desligados da noite para o dia. A estratégia clássica é o Estrangulamento (Strangler Pattern): o GENTE abraça o SISFOLHA por fora até matá-lo por inanição.

Veja como os dados fluiriam entre eles:
1. Via de Ida: SISFOLHA ➡️ GENTE (Carga Mestra)

O GENTE não pode inventar funcionários. Para a folha rodar, a matrícula tem que ser idêntica.

    O que nós receberíamos deles: Uma carga diária ou mensal com o "Espelho Funcional". Recebemos o cadastro civil, matrículas, vínculos, cargo atual e dados bancários.

    Como isso acontece tecnicamente: Como o SISFOLHA é legado, é muito raro ele ter uma API REST moderna. Essa integração geralmente é feita lendo direto do banco deles (via Linked Server no SQL Server ou Views de leitura) ou recebendo arquivos .txt / .csv (arquivos de remessa) que o GENTE processa em background.

2. Via de Volta: GENTE ➡️ SISFOLHA (O Espelho de Ponto)

Aqui é onde o Kanban que acabamos de blindar entra em ação. O SISFOLHA precisa saber quem trabalhou para poder pagar.

    O que nós mandaríamos para eles: Depois que a SAGEP clica no botão "Homologar" e tranca a escala no GENTE, o nosso sistema gera um pacote de dados dizendo: "SISFOLHA, o servidor X trabalhou 20 dias, tem 2 plantões extras e 1 falta".

    O Impacto: O GENTE assume a responsabilidade de ser o "Cérebro de Governança". O SISFOLHA deixa de receber papelada e passa a ser apenas um motor matemático que obedece as ordens homologadas pelo GENTE.

♟️ O Xeque-Mate (O Motor de Folha do GENTE)

A genialidade do que você está construindo com a arquitetura de Dados Computáveis (O Super Seed v4) é que você está preparando o GENTE para não precisar dessa integração no futuro.

Hoje, vocês podem integrar o GENTE para ser o "Frontend Inteligente" do SISFOLHA. Mas como você já amarrou as tabelas salariais, o PCCV e a hierarquia do MDE dentro do GENTE, o próximo passo lógico é ligar o seu próprio Motor de Folha.

No dia em que o Motor de Folha do GENTE provar que calcula o IRRF e os 25% da Educação com mais precisão e segurança jurídica que o sistema legado, o prefeito pode simplesmente desligar o servidor do SISFOLHA.

## Escala de trabalho — bypass administrativo na grade (Fase 1.5)

- **Condição:** utilizador com permissão de visão global (Gate `bypass-tenant` + cabeçalho `X-Gente-Global-View` conforme `GenteSudoGlobalView`).
- **Efeito:** `assertPodeEditarGrade` aceita edição de células mesmo quando `ESCALA_STATUS` não é `RASCUNHO` nem `DEVOLVIDA_AJUSTE`. O GET expõe `workflow.pode_editar_grade_sudo` para o Kanban alinhar a UI.
- **Auditoria:** alterações de `DETALHE_ESCALA_ITEM` nessa condição usam ação `ESCALA_INTERVENCAO_SUDO_GRADE` e JSON com `intervencao_sudo_grade`, `escala_status_no_evento`, `competencia`, `setor_id`, `operacao`.
- **Fora de escopo (1.5):** alterar células em **data passada** continua bloqueado no backend (`dataEscala < hoje`); eventual bypass retroativo exigiria motivo obrigatório e desenho à parte.

Com base nas fontes fornecidas, trago as informações disponíveis sobre as regras de negócio do sistema atual, mas já adianto que os detalhes técnicos de banco de dados e integração não estão presentes nos documentos atuais.
Aqui está o que as fontes detalham sobre as Regras de Negócio e Precedência (Lei 4.928/2008) e o impacto no SISFOLHA:

Impacto de Afastamentos e Lotação: O sistema SISFOLHA é parametrizado com a legislação vigente (Lei Municipal nº 4.928/2008 - PCCV) para processar os vencimentos dos servidores. Ele calcula não apenas o salário-base, mas também as gratificações específicas do magistério, como a regência de classe, adicional de titulação e tempo de serviço.
Controle de Exercício Efetivo: O SISFOLHA possui uma funcionalidade de controle rigoroso sobre a lotação dos servidores. O objetivo principal dessa regra de negócio é monitorar os professores em sala de aula e garantir que o pagamento seja feito apenas àqueles que estão no exercício efetivo de suas funções, prevenindo o pagamento indevido de gratificações a funcionários afastados ou irregulares (evitando os chamados "funcionários fantasmas").
Informações ausentes nas fontes: Os documentos atuais consistem em análises jurídicas e normativas sobre a governança educacional e a Lei Orgânica de São Luís, portanto, não contêm as seguintes especificações técnicas:

Mapeamento de Tabelas e Atributos: Nomes de tabelas de banco de dados (schemas) e colunas exatas do SISFOLHA.
Padronização de Siglas e Códigos: Códigos específicos, siglas no sistema ou padrões visuais em formulários para licenças médicas, férias, faltas, etc.
Rito de "Baixa" Sistemática: O passo a passo exato de interface ou rotina de cálculo para interromper o pagamento de gratificações de produtividade no meio do mês.
Integração de Dados: Formatos de arquivos de remessa ou exportação (.txt, .csv) aceitos pela prefeitura.
Como você estruturou sua solicitação como um "Prompt para Deep Research" e as fontes atuais não possuem a documentação de TI necessária, eu posso utilizar a ferramenta de descoberta de fontes para realizar uma pesquisa aprofundada na web (deep research) em busca de manuais técnicos, editais de licitação de software da prefeitura ou publicações em diários oficiais que detalhem o banco de dados e a arquitetura do SISFOLHA.
Você gostaria que eu iniciasse essa pesquisa avançada na web? Vale lembrar que o modo deep research leva vários minutos para ser concluído.
O não cumprimento da aplicação do percentual mínimo de 25% das receitas de impostos na educação resulta em sanções severas para a autoridade competente, que responderá por crime de responsabilidade.
As principais penalidades impostas ao gestor público incluem:

Afastamento liminar do cargo ou função, uma medida que pode ser aplicada a critério do Poder Legislativo (Câmara Municipal) logo no início das investigações.
Perda definitiva do mandato (cassação), que ocorre após a abertura e julgamento do processo pelo Poder Legislativo.
Suspensão de direitos políticos e o pagamento de multas pesadas, sanções que podem ser aplicadas no âmbito da improbidade administrativa, visto que as consequências podem se estender também para as esferas civil e criminal.
A legislação trata o orçamento da educação com extremo rigor, e a responsabilidade por eventuais falhas ou descumprimentos é considerada pessoal e intransferível no que diz respeito ao ordenador de despesas.

O SISFOLHA atua como o coração tecnológico da gestão de pessoal da SEMED e garante o pagamento correto das gratificações — como regência de classe, adicional de titulação e tempo de serviço — através de mecanismos de automação e fiscalização estruturados no próprio sistema. As fontes destacam as seguintes formas de controle:

Parametrização com a Legislação Vigente: O sistema é configurado com as diretrizes e regras do Plano de Cargos, Carreiras e Vencimentos (PCCV) estabelecido pela Lei Municipal nº 4.928/2008. Essa integração legal assegura que as atualizações salariais e as progressões automáticas de carreira ocorram com precisão matemática, sendo aplicadas sem erros manuais que poderiam causar prejuízos aos servidores ou gerar passivos trabalhistas futuros para o município.
Controle Rigoroso de Lotação: O sistema possui funcionalidades específicas para monitorar onde os professores estão alocados e se estão efetivamente em sala de aula. Ao exigir a comprovação do exercício efetivo das funções, o SISFOLHA atua na prevenção de irregularidades financeiras e bloqueia desvios, como o pagamento indevido de gratificações a "funcionários fantasmas" ou servidores irregularmente afastados.
Dessa forma, a ferramenta tecnológica converte o direito legal do servidor em um processo automatizado, garantindo a conformidade da folha de pagamento e alinhando os gastos de pessoal com as exigências da Lei de Responsabilidade Fiscal
As fontes fornecidas não listam os requisitos exatos (como tempo mínimo ou carga horária de cursos) para a progressão na carreira.
O que os documentos explicam é que a progressão é definida pelo Plano de Cargos, Carreiras e Vencimentos (PCCV) do magistério, instituído pela Lei Municipal nº 4.928/2008. De forma geral, a legislação:

Estabelece critérios que incentivam a qualificação continuada dos professores.
Prevê progressões automáticas que devem ser parametrizadas no sistema SISFOLHA para evitar erros processuais e passivos trabalhistas.
Considera fatores diretos na remuneração, como o tempo de serviço e o adicional de titulação.
Para obter a tabela exata de requisitos, avaliações de desempenho e interstícios para cada nível da carreira, seria necessário consultar o texto integral da Lei 4.928/2008, que não está detalhado no material atual.
As verbas que não entram no cálculo do percentual mínimo de 25% destinado à educação incluem:

Verbas do orçamento municipal destinadas a atividades culturais, desportivas e recreativas promovidas pela municipalidade.
Pagamento de aposentadorias.
Obras que não possuam uma finalidade estritamente educacional.
A legislação exige que o investimento seja direcionado exclusivamente para atividades de Manutenção e Desenvolvimento do Ensino (MDE). Isso impede que gestores tentem incluir gastos de outras áreas (como cultura e esporte) na prestação de contas apenas para atingir o limite mínimo exigido pela lei.
Iniciei a pesquisa aprofundada na web (deep research) focada na arquitetura técnica do SISFOLHA e nas regras de cálculo da Lei 4.928/2008 (como regência de classe e adicional de titulação). Como essa busca é abrangente, ela levará alguns minutos. Os resultados aparecerão no seu painel de fontes assim que concluídos. Lembre-se de revisar os resultados pendentes (escolhendo importar ou excluir) antes que os novos possam ser visualizados.
Enquanto a pesquisa está em andamento, respondendo às suas dúvidas com base nos documentos que temos no momento:
As fontes disponíveis indicam que a Lei Municipal nº 4.928/2008 institui o Plano de Cargos, Carreiras e Vencimentos (PCCV) do magistério de São Luís, definindo a remuneração base e os critérios para a progressão na carreira dos professores.
Em relação às gratificações, os textos mencionam que o sistema SISFOLHA é responsável por processar benefícios específicos do magistério previstos nessa lei, citando explicitamente três deles:

A regência de classe;
O adicional de titulação;
O adicional por tempo de serviço.
Limitações dos dados atuais: Os documentos que temos não detalham o texto exato da lei sobre como a regência de classe é definida, nem explicam a fórmula ou o funcionamento do cálculo do adicional de titulação dentro do SISFOLHA. Eles informam apenas que o sistema de folha de pagamento precisa estar rigorosamente parametrizado com a legislação para garantir que essas atualizações salariais e progressões automáticas ocorram "com precisão matemática" e sem erros que possam gerar processos trabalhistas contra o município.
Assim que o relatório da busca na web for retornado, teremos mais detalhes técnicos e jurídicos para aprofundar esses cálculos.
O sistema SISFOLHA processa o cálculo do adicional de titulação de forma automatizada, atuando como o coração tecnológico da folha de pagamento da Prefeitura de São Luís.
O funcionamento sistemático baseia-se na parametrização do sistema com a legislação vigente, especificamente o Plano de Cargos, Carreiras e Vencimentos (PCCV) regido pela Lei Municipal nº 4.928/2008. Essa lei estabelece os critérios para a progressão na carreira e incentiva a qualificação continuada dos professores.
Ao ser parametrizado com essas regras, o SISFOLHA garante que as atualizações salariais referentes à titulação (junto a outras gratificações, como regência de classe e tempo de serviço) sejam aplicadas com precisão matemática e sem intervenção manual. Isso evita erros de cálculo que poderiam gerar passivos trabalhistas futuros para o município.
Limitação de informações: Como mencionei anteriormente, os documentos atuais não contêm a fórmula matemática exata ou os percentuais específicos estipulados pela Lei 4.928/2008 para cada nível de titulação (especialização, mestrado, doutorado). A pesquisa aprofundada (deep research) que está em andamento no momento tem o objetivo de buscar justamente a íntegra dessa lei e eventuais manuais técnicos do sistema para detalhar como esse cálculo financeiro é executado na prática.
Sim, o SISFOLHA ajuda diretamente a evitar a existência de "funcionários fantasmas".
O sistema possui uma funcionalidade de controle rigoroso sobre a lotação dos servidores, que monitora se os professores estão efetivamente em sala de aula. Essa ferramenta garante que os vencimentos e gratificações sejam pagos apenas para os profissionais que estão no exercício efetivo de suas funções, prevenindo desvios de recursos públicos e irregularidades na folha de pagamento da SEMED
Mapeamento
 Técnico e Parametrização da Lei Municipal nº 4.928/2008 no Sistema
SISFOLHA: Uma Análise Estrutural da Regência de Classe e do Adicional de
 Titulação no Magistério de São Luís
A
 gestão da folha de pagamento do magistério público municipal de São
Luís exige um alinhamento rigoroso entre as disposições estatutárias e a
 configuração lógica dos sistemas de processamento de dados. A Lei
Municipal nº 4.928, de 13 de novembro de 2008, que dispõe sobre o
Estatuto e o Plano de Carreira e Remuneração do Magistério, serve como o
 alicerce jurídico fundamental para essa operação.[1, 2] No centro dessa
 engenharia administrativa encontra-se o SISFOLHA, o sistema responsável
 pela materialização financeira dos direitos e deveres dos servidores da
 educação. O mapeamento técnico das rubricas de regência de classe e
adicional de titulação não é apenas uma tarefa de entrada de dados, mas
um processo complexo de tradução legislativa em algoritmos de cálculo
que devem garantir a segurança jurídica e a saúde fiscal do
município.[3, 4]
O Framework Jurídico da Carreira do Magistério em São Luís
A
 Lei 4.928/2008 não é um documento isolado, mas uma peça de legislação
que integra as diretrizes nacionais estabelecidas pela Lei de Diretrizes
 e Bases da Educação Nacional (LDB) e as normas de financiamento do
FUNDEB.[5, 6] O estatuto define a carreira como um conjunto de classes e
 níveis que organizam a progressão do docente conforme seu tempo de
serviço e seu avanço acadêmico. Para o sistema SISFOLHA, essa estrutura é
 traduzida em uma matriz de vencimentos onde cada célula representa um
valor base sobre o qual incidirão as vantagens pecuniárias.[7, 8]
A
 arquitetura da carreira do magistério de São Luís é dividida em cargos
de provimento efetivo, estruturados em classes designadas por letras e
níveis designados por números romanos. Essa distinção é crucial para o
mapeamento técnico, pois a "Regência de Classe" e o "Adicional de
Titulação" possuem bases de cálculo e gatilhos de ativação distintos
dentro do sistema de folha.[1, 9] O vencimento, definido como a
retribuição pecuniária pelo exercício do cargo público, constitui o
elemento central para o cálculo das gratificações discutidas neste
relatório.
Estrutura de Classes e Níveis no SISFOLHA
No
 SISFOLHA, a configuração da tabela salarial deve refletir a progressão
horizontal e vertical. A progressão horizontal refere-se à mudança de
referência dentro da mesma classe, geralmente vinculada ao tempo de
serviço (interstícios), enquanto a progressão vertical refere-se à
mudança de classe por força da elevação da titulação acadêmica.[2, 6]
Componente de Carreira
Descrição Técnica no Sistema
Impacto nas Rubricas
Classe (A, B, C, D)
Identificador de Titulação Mínima
Define o multiplicador base do adicional
Nível (I, II, III...)
Identificador de Antiguidade/Mérito
Altera o Vencimento Base para cálculo de %
Referência
Subdivisão da Classe
Ajuste fino do Vencimento Base
O
 mapeamento técnico exige que o sistema reconheça automaticamente que a
alteração de um nível ou classe impacta proporcionalmente os valores
nominais da regência de classe e do adicional de titulação, uma vez que
estas são vantagens calculadas em base percentual sobre o vencimento
base.[3, 4]
Parametrização Técnica da Regência de Classe
A
 gratificação de regência de classe é uma vantagem funcional destinada
aos profissionais que se encontram no efetivo exercício da docência. A
lógica de negócio inserida no SISFOLHA para esta rubrica deve contemplar
 não apenas o cálculo financeiro, mas também a verificação de condições
de contorno que validam o direito ao recebimento.[1, 7]
Definição Algorítmica da Rubrica de Regência
Para
 o SISFOLHA, a regência de classe deve ser configurada como uma
"Vantagem Fixa Relativa". Isso significa que, embora o percentual seja
fixo, o valor absoluto flutua de acordo com o vencimento base do
servidor. A fórmula matemática para a determinação do valor da rubrica
pode ser expressa como:

VRC
​=VB
​×PRC
​

Onde:

VRC
​ é o valor nominal da Gratificação de Regência de Classe.
VB
​ é o Vencimento Base atualizado conforme a classe e nível do servidor.
PRC
​
 é o percentual de regência definido por lei (historicamente variável
entre 20% e 25% conforme as atualizações da Lei 4.928/2008 e acordos
coletivos).[7, 8]
Mapeamento de Dependências e Condicionantes
O
 mapeamento técnico da regência de classe no SISFOLHA exige a integração
 com o módulo de Lotação e Movimentação (LOM). A gratificação possui
natureza propter laborem, ou seja, é devida enquanto o servidor estiver exercendo a atividade específica em sala de aula.[2, 5]
As regras de validação no sistema devem prever:

Vínculo com Unidade Escolar: O servidor deve estar lotado em uma unidade de ensino ativa.
Função Docente: O cargo ou a função gratificada ocupada deve ser de docência.
Efetivo Exercício:
 O sistema deve verificar afastamentos. Licenças para tratamento de
saúde de curta duração geralmente não suspendem o pagamento, mas
afastamentos para exercer funções administrativas na sede da SEMED podem
 acarretar a cessação automática da rubrica.[3, 4]
Campo de Validação
Regra de Negócio
Ação no SISFOLHA
Código de Lotação
Se Lotação = Unidade Escolar
Habilita Rubrica
Código de Função
Se Função = Professor em Regência
Mantém Pagamento
Tipo de Afastamento
Se Afastamento = Mandato Eletivo
Suspende Rubrica
O Adicional de Titulação: Lógica de Progressão Vertical
Diferente
 da regência de classe, o Adicional de Titulação tem caráter permanente e
 está vinculado à qualificação profissional do docente. No escopo da Lei
 4.928/2008, a titulação é o motor da progressão vertical, incentivando a
 educação continuada através de cursos de especialização, mestrado e
doutorado.[1, 9]
Configuração de Níveis Acadêmicos no Sistema
O
 SISFOLHA deve tratar o adicional de titulação como um componente da
remuneração que se integra ao vencimento para fins de cálculo de outras
vantagens, dependendo da interpretação da base de cálculo. No entanto, a
 prática administrativa em São Luís geralmente calcula este adicional
sobre o vencimento base do cargo efetivo.[6, 10]
A
 parametrização técnica exige a criação de tabelas de correlação entre
os títulos apresentados e os percentuais aplicáveis. De acordo com a
estrutura da Lei 4.928/2008, os percentuais são aplicados de forma não
cumulativa, prevalecendo sempre o título de maior grau.[1, 7]
Título Acadêmico
Requisito Técnico (SISFOLHA)
Percentual sobre VB
Especialização
Diploma/Certificado de Pós-Graduação
15%
Mestrado
Diploma de Mestre reconhecido
20%
Doutorado
Diploma de Doutor reconhecido
25%
O Processo de Atualização de Titulação
A
 implementação técnica desta rubrica no SISFOLHA envolve um workflow de
aprovação. Quando um servidor conclui um curso de mestrado, por exemplo,
 o processo administrativo na SEMED culmina em uma portaria de
progressão. O sistema deve permitir que o operador de RH altere a
"Classe" do servidor, o que disparará automaticamente o novo cálculo do
adicional de titulação e, consequentemente, da regência de classe.[3, 8]
Um
 ponto crítico no mapeamento é a "Data de Vigência". O SISFOLHA deve
estar apto a realizar cálculos retroativos. Se a portaria retroage à
data do requerimento, o sistema deve calcular a diferença entre o valor
pago (com a titulação antiga) e o valor devido (com a nova titulação) em
 todas as rubricas impactadas para os meses anteriores.[4]
Intersecção de Rubricas e Efeito Cascata
Um
 dos maiores desafios técnicos na configuração de sistemas de folha de
pagamento para o setor público é evitar o efeito cascata, que é a
incidência de uma gratificação sobre outra, prática vedada pelo Art. 37,
 inciso XIV da Constituição Federal. No mapeamento da Lei 4.928/2008 no
SISFOLHA, deve-se garantir que tanto a regência de classe quanto o
adicional de titulação tenham como base comum e exclusiva o vencimento
base.[6, 9]
Modelagem da Base de Cálculo
A
 configuração das rubricas no SISFOLHA utiliza "Bases de Cálculo"
pré-definidas. Para o magistério de São Luís, a base padrão é o código
correspondente ao Vencimento Base (VB).

R=VB×(PRC
​+PAT
​)

Nesta
 formulação, as rubricas são somadas linearmente. O mapeamento técnico
deve ser testado para garantir que o sistema não execute o cálculo:

Rincorreto
​=(VB+VAT
​)×PRC
​

Embora
 pareça uma distinção sutil, em uma folha de pagamento com milhares de
servidores, a configuração incorreta da base de cálculo pode gerar
passivos trabalhistas ou apontamentos de irregularidade pelo Tribunal de
 Contas do Estado do Maranhão (TCE-MA).[3, 4]
Impacto na Aposentadoria e Contribuição Previdenciária
A
 Lei 4.928/2008 define quais parcelas da remuneração são incorporáveis
para fins de aposentadoria. O adicional de titulação, por sua natureza
permanente e vinculada ao cargo, integra a base de contribuição
previdenciária (IPAM). Já a regência de classe, embora seja uma
gratificação de serviço, possui regras específicas de incorporação após
determinado tempo de exercício, conforme a legislação previdenciária
municipal e as regras de transição constitucionais.[1, 2]
Parametrização de Incidências no SISFOLHA
O mapeamento técnico das rubricas deve definir os "flags" de incidência tributária e previdenciária.
Rubrica
Incidência IPAM (Previdência)
Incidência IRRF (Imposto)
Base FGTS (se aplicável)
Vencimento Base
Sim
Sim
Não (Estatutário)
Regência de Classe
Sim
Sim
Não
Adicional Titulação
Sim
Sim
Não
A
 correta parametrização desses campos no SISFOLHA garante que a retenção
 na fonte seja feita de forma precisa, alimentando as obrigações
acessórias como a DIRF e o eSocial. O eSocial, em particular, exige que
cada rubrica municipal seja mapeada para um código de rubrica da tabela
do Governo Federal, o que demanda um "de-para" técnico rigoroso entre a
Lei 4.928/2008 e os manuais do sistema federal.[3, 4]
Gestão de Tabelas Salariais e Atualizações Anuais
A
 dinâmica salarial do magistério em São Luís é influenciada pelo
reajuste anual do Piso Nacional do Magistério. Quando ocorre um reajuste
 no piso, a prefeitura deve atualizar a tabela salarial constante no
SISFOLHA. A Lei 4.928/2008 prevê que nenhum docente pode receber menos
que o piso para a jornada correspondente.[7, 8]
Manutenção de Históricos de Tabelas
O
 SISFOLHA deve manter um histórico de tabelas salariais. No mapeamento
técnico, cada tabela é vinculada a um período de validade. Isso é
essencial para processos de auditoria e para o cumprimento da Lei de
Responsabilidade Fiscal (LRF). O sistema deve permitir simulações de
impacto financeiro antes da aplicação definitiva de uma nova tabela
baseada na Lei 4.928/2008.[4, 10]
As
 tabelas de 2024 e as projeções para 2025 mostram a evolução do
vencimento base e como isso eleva o teto de gastos com pessoal. O
mapeamento técnico deve permitir que o gestor visualize o custo total da
 "Regência de Classe" em relação ao orçamento total da SEMED,
facilitando o controle dos 70% destinados ao pagamento de profissionais
da educação básica via FUNDEB.[8]
Validação de Integridade e Auditoria de Dados
O
 SISFOLHA deve possuir rotinas de validação (scripts de auditoria) para
garantir que as regras da Lei 4.928/2008 não sejam violadas por erros de
 digitação ou falhas sistêmicas. Algumas das validações recomendadas
para o mapeamento técnico incluem:

Teto de Titulação:
 Impedir que um servidor com cargo de "Professor Nível I" (Graduado)
receba um adicional de titulação correspondente a "Doutor" sem a devida
alteração de classe no cadastro.[1, 3]
Incompatibilidade de Lotação:
 Alertar quando um servidor recebe "Regência de Classe" mas sua lotação
atual consta como "Disponível para outro órgão" ou "Em licença sem
vencimento".[4]
Proporcionalidade da Carga Horária:
 Garantir que o valor da regência seja proporcional à carga horária do
contrato (20h, 24h ou 40h), evitando pagamentos integrais para jornadas
parciais não previstas.[6, 7]
O Papel do Sindeducação na Transparência das Rubricas
O
 sindicato da categoria (Sindeducação) atua como um fiscalizador da
aplicação da Lei 4.928/2008. O mapeamento técnico no SISFOLHA deve
produzir contracheques claros e detalhados. A transparência nas rubricas
 de regência e titulação reduz o volume de reclamações administrativas e
 ações judiciais.[5, 10]
A
 clareza dos dados exportados do SISFOLHA para o Portal da Transparência
 da Prefeitura de São Luís é fundamental. Cada rubrica deve ter uma
descrição que remeta diretamente à base legal, por exemplo: "GRAT. REG.
CLASSE LEI 4928" e "ADIC. TITULACAO MESTRADO LEI 4928".[3, 8]
Perspectivas de Evolução Tecnológica no SISFOLHA
Com
 a crescente digitalização da administração pública, o mapeamento da Lei
 4.928/2008 no SISFOLHA tende a se tornar cada vez mais automatizado. A
integração com sistemas de reconhecimento de diplomas por meio de APIs
com o Ministério da Educação (MEC) poderia, no futuro, validar
automaticamente o adicional de titulação, reduzindo a burocracia
documental.[1, 4]
Além
 disso, a implementação de módulos de business intelligence (BI) sobre
os dados do SISFOLHA permitiria à SEMED São Luís realizar um
planejamento pedagógico e financeiro mais assertivo, identificando, por
exemplo, o déficit de professores em regência de classe em determinadas
regiões da cidade em tempo real, cruzando dados de pagamento com dados
de frequência escolar.[3, 6]
Síntese dos Requisitos de Mapeamento Técnico
A
 consolidação do mapeamento técnico da Lei 4.928/2008 para o
processamento da regência de classe e adicional de titulação pode ser
resumida na seguinte matriz de requisitos:
Requisito
Descrição
Base Legal
Identificação de Rubrica
Criação de códigos exclusivos para cada vantagem
Estatuto do Magistério
Definição de Base de Cálculo
Vinculação exclusiva ao Vencimento Base
CF/88 e Lei 4928/08
Gatilho de Pagamento
Condição de regência (em sala) ou titulação (diploma)
Artigos específicos da Lei
Regra de Exclusividade
Aplicação apenas do maior título acadêmico
Plano de Carreira
Sincronização de Cadastro
Link direto entre titulação e classe funcional
Estrutura de Níveis
O
 rigor na aplicação desses requisitos no SISFOLHA assegura que a
política de valorização do magistério de São Luís seja executada com
precisão matemática e conformidade legal, respeitando o esforço dos
docentes e a responsabilidade com o erário público.[1, 3, 4, 8]
Considerações Finais sobre a Integridade do Sistema
A
 manutenção da Lei 4.928/2008 dentro do ecossistema SISFOLHA exige uma
vigilância constante por parte dos gestores de TI e RH. Mudanças
legislativas, mesmo as mais simples, podem ter efeitos sistêmicos em
cascata que comprometem a precisão da folha de pagamento. O mapeamento
técnico aqui detalhado oferece um roteiro para que as gratificações de
regência de classe e adicional de titulação permaneçam como instrumentos
 eficazes de política educacional, garantindo que o servidor receba o
que lhe é devido e que a administração pública opere sob os princípios
da eficiência e da legalidade.[1, 2, 3]
Este
 relatório técnico sublinha a necessidade de uma documentação detalhada
de cada rubrica, servindo como guia para auditorias futuras e para a
continuidade administrativa em momentos de transição de gestão no
município de São Luís. A correta parametrização é, em última análise, a
garantia de que o direito escrito na lei se torne realidade no
contracheque do professor.[4, 6, 9]

--------------------------------------------------------------------------------

Untitled, https://leismunicipais.com.br/a/ma/s/sao-luis/lei-ordinaria/2008/492/4928/lei-ordinaria-n-4928-2008-dispoe-sobre-o-estatuto-e-o-plano-de-carreira-e-remuneracao-do-magisterio-publico-municipal-de-sao-luis-e-da-outras-providencias
Untitled, https://www.saoluis.ma.gov.br/subprefeitura_centro/arquivos/legislacao/lei_4928_2008.pdf
Untitled, https://transparencia.saoluis.ma.gov.br/pmsl/transparencia/pessoal/folha-pagamento/manuais
Untitled, https://semed.saoluis.ma.gov.br/transparencia/folha-pagamento
Untitled, https://sindeducacao.org/site/legislacao/
Untitled, https://www.sindeducacao.org/site/wp-content/uploads/2018/06/LEI-4.928-ESTATUTO-DO-MAGISTERIO.pdf
Untitled, https://sindeducacao.org/site/tabela-salarial/
Untitled, https://www.sindeducacao.org/site/tabela-salarial-2024/
Untitled, https://www.jusbrasil.com.br/legislacao/1429997103/lei-4928-08-sao-luis-ma
Untitled, https://sindeducacao.org/site/category/legislacao/
Arquitetura de Dados e Interoperabilidade Normativa: Mapeamento Técnico de Ausências e Afastamentos para o Sistema GENTE (SEMED São Luís)
Introdução e Contexto do Ecossistema Tecnológico Municipal
A modernização da governança de recursos humanos e da gestão de escalas de trabalho no âmbito da administração pública municipal impõe desafios arquiteturais severos, especialmente quando se trata de secretarias com alto volume de servidores e capilaridade territorial, como a Secretaria Municipal de Educação de São Luís (SEMED). A proposição e o desenvolvimento do sistema GENTE emergem como uma resposta estratégica à necessidade de controle dinâmico de alocação de docentes, gestão de substituições e monitoramento de assiduidade em tempo real. No entanto, a viabilidade técnica e a integridade financeira desta nova plataforma dependem indissociavelmente de sua capacidade de dialogar com, ou eventualmente substituir, a infraestrutura legada estabelecida.
O ecossistema atual de processamento de folha de pagamento da Prefeitura de São Luís e, por extensão, da SEMED, é fortemente ancorado no sistema SISFOLHA, uma solução tecnológica desenvolvida e comercializada pela empresa E-TIcons (Empresa de Tecnologia de Informação e Consultoria Ltda). Este sistema legado atua como o núcleo contábil e de departamento pessoal da administração, sendo responsável por uma miríade de rotinas automatizadas que vão desde cálculos de provisão de férias, décimo terceiro salário, rotinas de pagamentos e rescisões, até o cumprimento de obrigações acessórias federais rigorosas, como o e-Social, EFD-Reinf e DCTF Web. O SISFOLHA opera com banco de dados hospedado em nuvem, utilizando uma arquitetura que permite a geração de contracheques online e a emissão de remessas bancárias conforme os padrões da Federação Brasileira de Bancos (Febraban).
Neste panorama operacional, o sistema GENTE precisará atuar como uma camada de inteligência operacional avançada, fornecendo uma interface de Kanban para a gestão visual e tática das escalas de trabalho nas unidades de ensino, enquanto interage de forma síncrona ou assíncrona com o motor de cálculo financeiro do SISFOLHA. Para que essa simbiose arquitetural ocorra sem gerar passivos trabalhistas, falhas de suprimento de fundos ou inconsistências perante o Tribunal de Contas, faz-se estritamente necessário um mapeamento exaustivo e estruturado. Este mapeamento deve abranger a topologia provável do banco de dados relacional que sustenta as ocorrências de pessoal, a taxonomia e padronização visual das codificações de ausência, os fluxos de validação jurídica ditados pela legislação municipal pertinente e os protocolos técnicos de exportação e importação de dados.
O presente relatório técnico aprofunda-se na ontologia de dados de recursos humanos da SEMED, traçando um paralelo direto entre as exigências do Plano de Cargos, Carreiras e Vencimentos (PCCV) do Magistério, consubstanciado na Lei Municipal nº 4.928/2008 , e a arquitetura de tabelas transacionais e de domínio requeridas para sustentar o fluxo de informações. A análise subsequente fornece os subsídios definitivos para o desenho do esquema de banco de dados do sistema GENTE, a modelagem de sua interface visual de escalas e a construção dos conectores de integração (APIs ou batch processing) com o sistema legado.
Arquitetura de Dados Relacional e Mapeamento de Entidades
A fundação de qualquer plataforma de gestão de escalas de trabalho que pretenda interoperar com um sistema contábil de folha de pagamento reside na solidez de seu modelo de entidade-relacionamento (MER). O SISFOLHA, concebido para atender rotinas de cálculos complexos e geração de arquivos para o Tribunal de Contas , pressupõe um banco de dados altamente normalizado, capaz de isolar dados cadastrais, metadados de regras de negócio e registros transacionais de linha do tempo.
Para que o sistema GENTE atinja seus objetivos arquiteturais, ele deve espelhar ou consumir essa estrutura conceitual, garantindo que a semântica dos dados seja preservada na tradução entre a interface do usuário (Kanban de escolas) e o núcleo de processamento (cálculo de gratificações e descontos). O mapeamento a seguir detalha as entidades fundamentais e os atributos críticos que devem ser contemplados no schema provável do sistema GENTE, visando simetria com as instâncias do SISFOLHA ou sistemas correlatos da Prefeitura de São Luís.
Modelagem de Tabelas de Domínio e Transacionais
A separação entre as tabelas que definem os tipos de afastamentos e as tabelas que registram as ocorrências factuais na vida do servidor é o primeiro princípio arquitetural a ser adotado. O modelo conceitual inferido e recomendado para o escopo da SEMED divide-se nas seguintes estruturas principais:
A tabela mestre de lotação e cadastro, frequentemente nomeada como RH_SERVIDOR ou CADASTRO_FUNCIONAL. Esta entidade consolida os dados biográficos, estatutários e de alocação de cada profissional da educação. No contexto da Prefeitura de São Luís, o identificador principal para qualquer movimentação não é o CPF, mas sim a matrícula funcional, frequentemente apresentada com dígitos verificadores ou formatada com hífens (por exemplo, a matrícula 07682-7 evidenciada em publicações oficiais de aposentadoria). Esta entidade deve obrigatoriamente armazenar as referências de Nível e Classe (ex: Nível II, Classe "C", Referência I) que determinam a base de cálculo de vencimentos e gratificações estipuladas no Plano de Cargos e Carreiras.
A tabela de parametrização conceitual, denominada RH_TIPO_AFASTAMENTO ou TABELA_DOMINIO_LICENCAS. Esta é uma entidade de domínio (ou tabela de lookup) que atua como o dicionário central do sistema. A sua importância arquitetural é vital, pois ela retira o engessamento do código-fonte (hardcoding) e transfere as regras de negócio normativas para os metadados do banco. Cada registro nesta tabela define um tipo específico de ausência (férias, licença médica, falta injustificada), possuindo flags booleanas ou indicadores paramétricos que determinam, por exemplo, se a licença é remunerada, se suspende a gratificação de regência de classe, se conta como tempo de serviço para aposentadoria , e qual o código de correspondência exato na Tabela 18 do sistema federal e-Social.
A tabela transacional central de acompanhamento de tempo, comumente arquitetada como SERVIDOR_AFASTAMENTO ou RH_HISTORICO_AFASTAMENTO. Esta é a tabela de maior volumetria e dinamismo no contexto do sistema GENTE. É a fonte primária de verdade cronológica que o quadro Kanban consumirá para renderizar a disponibilidade dos servidores em tempo real. Cada registro nesta entidade representa um evento finito e delimitado no tempo, em que um servidor específico se afasta de suas funções por um motivo tipificado. A precisão temporal e o lastro probatório nesta tabela são os elementos que garantem a correta exportação de dados para o SISFOLHA processar a dedução ou manutenção pecuniária.
A tabela de reflexos monetários, concebida como FICHA_FINANCEIRA_EVENTOS ou RH_MOVIMENTACAO_MENSAL. Enquanto a tabela de histórico trata do tempo (quando o servidor esteve ausente), esta tabela traduz o tempo em impacto monetário. O SISFOLHA, em sua arquitetura de cálculo , necessita saber não apenas que o servidor faltou, mas quais rubricas devem sofrer incidência. Esta entidade registra eventos temporários que devem ser injetados ou subtraídos do contracheque do mês corrente, tais como a supressão de parcelas da gratificação de produtividade, a perda do auxílio-transporte proporcional, ou, inversamente, o pagamento de uma substituição temporária.
A tabela de contingência pedagógica, desenhada como RH_SUBSTITUICAO_DOCENTE. Esta entidade atende a um fluxo de negócio muito específico da Secretaria Municipal de Educação. Quando um titular se afasta, a turma não pode permanecer ociosa. Existe um procedimento normatizado para a indicação de um servidor substituto, que culmina no preenchimento do formulário SS (Substituição de Servidor) e na emissão de uma portaria sequencial por órgão (ex: SEMED 01/2010). Esta tabela cria um relacionamento complexo de chave estrangeira dupla, vinculando a matrícula do servidor titular afastado, o evento de afastamento que gerou a vacância temporária, e a matrícula do servidor substituto que assumirá a regência da classe durante aquele ínterim.
Atributos Fundamentais e Restrições de Integridade
Para garantir que a interoperabilidade com o SISFOLHA ocorra sem falhas de conversão de tipos de dados ou rejeições de regras de negócio, a tabela primária SERVIDOR_AFASTAMENTO (RH_HISTORICO_AFASTAMENTO) deve possuir uma estrutura de colunas rigidamente definida. A tabela a seguir descreve o schema relacional provável com as colunas fundamentais, os tipos de dados recomendados (em notação SQL padrão) e a lógica de negócios associada a cada campo.
Nome da Coluna no Banco de DadosTipo de Dado e RestriçõesContexto Normativo e Regra de Negócio Sistêmicaid_historico_afastamentoBIGINT PRIMARY KEY AUTO_INCREMENTIdentificador único e sequencial da transação no sistema GENTE. Fundamental para operações de atualização (UPDATE) ou deleção (DELETE) caso um lançamento seja retificado.matricula_servidorVARCHAR(20) NOT NULLChave estrangeira (FK) que aponta para a tabela RH_SERVIDOR. O tipo VARCHAR é necessário para acomodar dígitos verificadores, traços ou formatações legadas (ex: 07682-7) utilizadas pela PMSL.
id_tipo_afastamentoINT NOT NULLChave estrangeira (FK) que referencia a entidade RH_TIPO_AFASTAMENTO. Define a natureza da ausência para aplicação das lógicas de corte de ponto.data_inicioDATE NOT NULLO marco temporal exato em que a ausência começa a produzir efeitos administrativos na escola e efeitos financeiros no processamento do SISFOLHA.data_fimDATE NULLData de encerramento da ausência. Permite valores nulos (NULL) em situações onde a licença médica aguarda deliberação de junta médica pericial e possui tempo indeterminado temporariamente.
quantidade_dias_apuradosINT NOT NULLColuna frequentemente calculada (DATEDIFF), porém essencial para persistência física a fim de facilitar a auditoria e geração de arquivos .TXT de remessa. Vital para apurar limites legais, como os 45 dias de férias do professor em regência.
data_publicacao_domDATE NULLData de publicação do ato administrativo concessório no Diário Oficial do Município (DOM). A eficácia de muitas licenças estatutárias, como licença prêmio e afastamento para estudo, depende da publicação formal para validade legal e auditoria do TCE.
processo_sei_referenciaVARCHAR(50) NULLNúmero do processo no Sistema Eletrônico de Informações (SEI)  que instruiu o pedido de licença. Garante a rastreabilidade entre o ato no Kanban e o dossiê probatório do servidor.
codigo_cid_10VARCHAR(10) NULLClassificação Internacional de Doenças. O preenchimento é sensível devido ao sigilo médico, mas é um atributo necessário em cargas específicas para integração de atestados e remessas previdenciárias de auxílio-doença.flag_impacto_financeiroBOOLEAN NOT NULL DEFAULT 1Indicador transacional rápido para o motor do SISFOLHA. Se o valor for 1 (Verdadeiro), a ausência gera manutenção de remuneração; se 0 (Falso), aciona o gatilho na FICHA_FINANCEIRA_EVENTOS para corte de vencimentos.matricula_substitutoVARCHAR(20) NULLColuna denormalizada para otimização de consultas no Kanban, contendo a matrícula funcional do profissional designado via portaria (Formulário SS) para suprir a lacuna educacional.
A rigidez desta estrutura garante que cada movimentação lançada por diretores de escola ou analistas de RH da SEMED possua o estofo documental e temporal exigido pelo núcleo contábil. Uma vez que o sistema E-TIcons atende à geração de DCTF Web e e-Social , a falta de atributos como a data_inicio ou o mapeamento correto do id_tipo_afastamento acarretaria na rejeição do lote pelo ambiente nacional do Sistema Público de Escrituração Digital (SPED).
Padronização de Siglas, Códigos e Representação Visual no Kanban
A arquitetura de informação voltada para o usuário final do sistema GENTE (diretores de unidades escolares, coordenadores pedagógicos e analistas de escala) difere drasticamente da arquitetura de integração de retaguarda. Para que a plataforma cumpra seu papel de gestão de escalas e alocação de recursos humanos com eficiência, a taxonomia das ausências no âmbito da Prefeitura de São Luís deve ser traduzida em códigos mentais de rápida assimilação.
Essa padronização deve respeitar uma conformidade tridimensional: deve estar alinhada internamente com as previsões do Estatuto dos Servidores do Município de São Luís (Lei Delegada nº 21/1975)  e do Estatuto do Magistério Público Municipal (Leis nº 4.928/2008, nº 4.749/2007, e antecessoras) ; deve dialogar com os formulários em papel historicamente utilizados (como o formulário SS para substituições e o uso institucional da sigla SEMED nos documentos de controle sequencial) ; e, finalmente, deve prover uma correlação indissociável com a Tabela 18 (Motivos de Afastamento) gerenciada no escopo federal do e-Social, visto que o SISFOLHA opera estritamente sob este framework fiscal.
A documentação normativa e os editais não estabelecem um manual de identidade visual com códigos de cores hexadecimais rígidos e preexistentes para os formulários internos de ausência da SEMED. No entanto, a construção de um painel Kanban moderno exige o estabelecimento de um padrão de cores e siglas baseado em princípios de ergonomia cognitiva, criando uma linguagem visual sistêmica que passará a ser o novo padrão da Secretaria. O mapeamento técnico das siglas de ocorrência, seus equivalentes normativos e a proposta arquitetural visual delineiam-se da seguinte forma:
1. Licença Médica ou Afastamento por Saúde (Atestado)
A interrupção do exercício por motivos de saúde constitui o evento de ruptura mais frequente e crítico para a continuidade do processo educacional.
Código Interno / Sigla PMSL: LM (Licença Médica) ou ATS (Atestado de Saúde).
Correspondência e-Social (SISFOLHA): Código 01 (Afastamento temporário por motivo de acidente do trabalho) ou Código 03 (Afastamento temporário por motivo de doença não relacionada ao trabalho).
Fundamentação e Impacto no Kanban: Representa uma perda aguda de capacidade operacional. Nos termos regulamentares, atestados de até 15 dias mantêm o ônus financeiro integralmente com o tesouro municipal. Quando a necessidade de afastamento supera o prazo de 15 dias, o servidor deve ser submetido à perícia médica, e as normativas do Regime Geral de Previdência Social (RGPS) ou regras próprias assumem o controle, suspendendo temporariamente as remunerações baseadas em produtividade, podendo o servidor, caso recuse a perícia, perder integralmente a remuneração. No Kanban, isso cria uma lacuna imediata e não programada que exige a confecção de um formulário SS para a portaria de substituição não remunerada ou remunerada.
Padrão Visual Sistêmico Proposto (Cor): Vermelho Alerta (#E74C3C). A ergonomia da cor vermelha instiga ação imediata. Sinaliza ao coordenador da escola que uma sala de aula está fisicamente desprovida de docente, caracterizando um ponto de falha que demanda alocação emergencial de um professor substituto.
2. Férias Regulamentares
O gozo de férias no ambiente educacional segue padrões de sazonalidade estritos, muitas vezes divergentes do serviço público geral, alinhando-se ao calendário acadêmico.
Código Interno / Sigla PMSL: FR (Férias).
Correspondência e-Social (SISFOLHA): Código 15 (Gozo de férias ou recesso).
Fundamentação e Impacto no Kanban: Evento estatutário previsível e de alto impacto na programação. A legislação municipal assegura que ao professor em exercício pleno e exclusivo de regência de classe ou suporte pedagógico nas unidades escolares são garantidos 45 (quarenta e cinco) dias de férias anuais. Adicionalmente, professores lotados nos setores administrativos da SEMED, mas que exercem atividades de caráter itinerante nas unidades de ensino, mantêm excepcionalmente o direito aos mesmos 45 dias. Para o Kanban, as férias representam uma ausência estrutural, planejada em bloco e geralmente suportada pelo recesso escolar institucional, não exigindo substituição avulsa diária.
Padrão Visual Sistêmico Proposto (Cor): Azul Estabilidade (#3498DB). A cor azul transmite tranquilidade e planejamento. Permite ao gestor, ao visualizar a linha do tempo trimestral do Kanban, filtrar e identificar rapidamente os blocos contínuos de férias concedidas e já cobertas pelo recesso de meio e fim de ano.
3. Licença Prêmio por Assiduidade
Um benefício histórico do funcionalismo público que premia a dedicação e o tempo de serviço contínuo sem faltas graves.
Código Interno / Sigla PMSL: LP (Licença Prêmio).
Correspondência e-Social (SISFOLHA): Código 16 (Licença remunerada - Liberalidade do empregador ou previsão estatutária).
Fundamentação e Impacto no Kanban: Consubstanciada nos dispositivos de tempo de serviço referenciados pela Lei Delegada nº 21/1975 e suas posteriores alterações e aplicações em certidões de aposentadoria , a licença prêmio é adquirida a cada quinquênio ininterrupto de trabalho. A sua concessão, no entanto, é discricionária por parte da administração para não esvaziar os quadros funcionais. No contexto da gestão de escalas do GENTE, a licença prêmio funciona como um evento de longuíssimo prazo (geralmente meses contínuos), exigindo a montagem de um processo eletrônico longo no SEI  e publicação no Diário Oficial. Requer invariavelmente a designação de um professor substituto formal de longo termo.
Padrão Visual Sistêmico Proposto (Cor): Roxo Institucional (#9B59B6). O roxo distingue categoricamente a licença prêmio do gozo de férias comuns, sinalizando tratar-se de um período aquisitivo especial, fruído pelo servidor de carreira veterano, demandando tratamento de substituição sistêmica de alta prioridade.
4. Afastamento para Estudo e Capacitação
O investimento em capital humano é uma diretriz do Plano de Cargos, Carreiras e Vencimentos (PCCV) do magistério de São Luís.
Código Interno / Sigla PMSL: AE (Afastamento para Estudo) ou AFC (Afastamento para Formação Continuada).
Correspondência e-Social (SISFOLHA): Código 30 (Afastamento temporário para participar de programa de treinamento regularmente instituído).
Fundamentação e Impacto no Kanban: Conforme dita expressamente o Artigo 31, inciso IV, do Estatuto do Magistério em São Luís , o profissional do magistério somente pode servir fora da unidade onde tenha sua lotação originária de exercício, entre outras hipóteses restritas, no caso de "afastamento para realização de cursos de formação, especialização, mestrado, doutorado ou pós-graduação". Este afastamento remove o servidor da sala de aula, mas o mantém vinculado aos propósitos da rede educacional. O impacto no Kanban é similar ao de uma licença de longo prazo, mas com a ressalva de que o servidor pode, eventualmente, retornar com um acréscimo de qualificação que altere sua classe/nível , exigindo integração com a rotina de evolução funcional do SISFOLHA.
Padrão Visual Sistêmico Proposto (Cor): Ciano / Teal (#1ABC9C). Uma cor secundária fria que indica atividade vinculada, destacando que o profissional não está presente fisicamente na estrutura da escola, contudo, encontra-se ativamente empenhado em atividade de desenvolvimento reconhecida pela SEMED.
5. Licença Maternidade e Paternidade
O amparo constitucional à parentalidade possui repercussões significativas no dimensionamento da folha de pagamento e da escala docente.
Código Interno / Sigla PMSL: LMA (Licença Maternidade) e LPA (Licença Paternidade).
Correspondência e-Social (SISFOLHA): Códigos 17 (Licença-maternidade) e 18 (Licença-paternidade) do e-Social.
Fundamentação e Impacto no Kanban: Constitui um afastamento protetivo de médio a longo prazo. Diferencia-se de uma licença médica comum (doença), pois não deve computar negativamente no histórico do servidor para fins de avaliação de desempenho para progressão funcional, que possui prazos rígidos segundo o Estatuto do Magistério. A licença maternidade cria uma lacuna previsível que retira a servidora da escola por meses consecutivos, sendo imperativo o bloqueio da matriz de horários no Kanban e a convocação antecipada de um substituto titular para assumir as disciplinas.
Padrão Visual Sistêmico Proposto (Cor): Magenta / Rosa Escuro (#E84393). A cor confere altíssima distinção visual, separando instantaneamente as licenças de saúde patológicas (vermelhas) do afastamento parental. Facilita aos coordenadores e analistas de escala projetarem o retorno da servidora na grade horária do semestre letivo seguinte.
6. Faltas Não Justificadas e Abandono
O evento disciplinar mais crítico para a administração da folha de pagamento pública.
Código Interno / Sigla PMSL: FNJ (Falta Não Justificada).
Correspondência e-Social (SISFOLHA): Ausência de prestação de serviço sem amparo legal, gerando envio de rubricas informativas de dedução e supressão de base de cálculo na transmissão do evento S-1200 (Remuneração de trabalhador) no e-Social.
Fundamentação e Impacto no Kanban: A paralisação da prestação do serviço público sem justificativa quebra a premissa de vinculação. O servidor que possui escala regular e não comparece aciona gatilhos sancionatórios estritos. As Faltas Não Justificadas implicam não apenas o desconto financeiro imediato do dia na tabela FICHA_FINANCEIRA_EVENTOS, mas também a suspensão de parcelas acessórias atreladas à assiduidade. No Kanban, é o indicativo mais urgente de quebra da programação, demandando que a direção escolar promova o remanejamento relâmpago de turmas.
Padrão Visual Sistêmico Proposto (Cor): Cinza Escuro ou Grafite (#2C3E50). Uma cor pesada e inativa, que evidencia um hiato, uma desconexão não autorizada. No aspecto gerencial, blocos cinzas indicam aos inspetores da SEMED focos de abstenção não gerenciada que necessitam de intervenção ou abertura de processo administrativo disciplinar no SEI.
Regras de Negócio e Precedência Normativa: A Lei nº 4.928/2008
A transição de um modelo de processamento de folha de pagamento convencional para uma gestão acoplada a escalas dinâmicas exige que a lógica matemática codificada na arquitetura do software interprete com precisão cirúrgica a hermenêutica das legislações estatutárias vigentes. No caso de São Luís, a carreira educacional municipal estrutura-se predominantemente sobre o Plano de Cargos, Carreiras e Vencimentos (PCCV) consubstanciado na Lei Municipal nº 4.928/2008, que revogou os regramentos esparsos das Leis nº 2.728/1985, nº 4.474/2005 e partes da Lei nº 4.749/2007.
Esta estrutura normativa consolida diretrizes que atrelam organicamente o local de exercício (lotação) do profissional, a natureza da sua docência (regência de classe) e as vantagens pecuniárias decorrentes.
O Conceito Estatutário de Regência de Classe e o Impacto Financeiro dos Afastamentos
O conceito de "Regência de Classe", dentro da matriz de pensamento da SEMED, ultrapassa a simples definição de lecionar; ele representa o gatilho principal de direitos funcionais amplificados e retribuições monetárias compensatórias. A legislação e as portarias acessórias estabelecem condicionantes rígidos que o sistema GENTE deverá incorporar em seus algoritmos de verificação:
Diferenciação do Teto Aquisitivo de Férias: A diretriz municipal garante expressamente que apenas ao professor "em exercício de regência de classe ou suporte pedagógico nas unidades escolares" ficam assegurados 45 (quarenta e cinco) dias de férias anuais, estendendo-se também aos lotados na SEMED com caráter "itinerante nas Unidades de Ensino".
Tradução Algorítmica Arquitetural: O sistema GENTE não pode operar o cômputo de concessão de férias baseado apenas no cargo nominal ("Professor Nível II"). Ele requer um job de processamento noturno que cruze o cadastro do servidor (RH_SERVIDOR) com a entidade de lotação. Caso o profissional seja realocado integralmente para um setor administrativo interno da SEMED devido a um processo de "redução de matrícula" ou "interesse do serviço público" (amparado no Art. 31, incisos I e VI) , cessando assim o exercício de sala de aula itinerante ou fixa, o sistema deve automaticamente desativar a flag binária direito_45_dias, rebaixando o teto aquisitivo para 30 dias de férias padrão, garantindo economia ao Fundo Municipal.
Suspensão e Prorrateio de Gratificações de Estímulo à Regência e Ministração: O arcabouço normativo dita que a "gratificação de regência de classe do Magistério será atribuída a título de estímulo ao professor em sala de aula" incidindo "sobre o vencimento base". Além disso, estabelece o incentivo e remuneração para a "ministração de aulas" aos docentes do ensino fundamental que excederem as aulas determinadas em módulos de 5 até 40 horas semanais.
Tradução Algorítmica Arquitetural: Estas parcelas não são incorporáveis em caráter absoluto; são condicionais. Uma licença médica (LM) não programada ou uma falta não justificada (FNJ) rompe a condição estrutural de "exercício em sala de aula" e de extrapolação da carga horária padrão. Consequentemente, o módulo financeiro do SISFOLHA necessita imperativamente da remessa precisa desses dados pelo GENTE. O sistema deve calcular o prorrateio matemático (dias efetivos lecionados no mês versus dias ausentes) e estancar sumariamente a gratificação de regência de classe ou o adicional por hora-aula suplementar no exato período temporal em que a regência factual for descontinuada.
Transição Jurídica do Vínculo Previdenciário (Regra dos 15 Dias): A dinâmica dos afastamentos por motivos patológicos contém um ponto de ruptura legal crítico que não pode prescindir do tratamento de software. A normativa estabelece o direito ao "afastamento para tratamento de saúde de até 15 dias, conforme laudo da inspeção médica", e estipula severamente que a necessidade de ampliação do prazo remeterá às normas do Regime Geral da Previdência Social (INSS/RGPS) ou regras de regime próprio correspondentes. Adverte ainda que a recusa do servidor em submeter-se à perícia médica após o lapso pericial gera "perda integral da remuneração".
Tradução Algorítmica Arquitetural: Esta é uma diretiva de fluxo de máquina de estados. Se um servidor insere um atestado de 5 dias, a gestão ocorre localmente na SEMED e o ônus permanece na rubrica orçamentária de custeio municipal. Contudo, se o sistema GENTE computar atestados sucessivos que, agrupados (seguindo regras federais de contagem de interstício do mesmo CID), ultrapassem a barreira dos 15 dias ininterruptos, o aplicativo deve gerar uma trava no Kanban, emitir uma notificação ostensiva para o setor de perícias e enviar um evento de truncamento ao SISFOLHA, bloqueando a emissão regular do contracheque do município no 16º dia e transferindo a responsabilidade fiscal.
O Fluxo e Rito de "Baixa" Sistêmica: Mitigação de Pagamentos Indevidos
O cenário operacional de maior estresse para a conformidade das contas públicas e para o equilíbrio orçamentário que culmina nas auditorias do Tribunal de Contas (TCE)  ocorre quando a assincronia entre o fato gerador e o fechamento da folha resulta em pagamentos indevidos. Considere-se o seguinte cenário: um professor possui uma escala ativa desenhada para o mês corrente, contemplando módulos de extensão de carga horária e recebimento integral da gratificação de produtividade/campo (regência). No décimo dia do mês, ocorre um acidente ou a instauração de uma licença médica incapacitante, não prevista.
Se o ciclo de processamento da folha no SISFOLHA, operado de maneira isolada e em lote mensal , não for interceptado a tempo, o contracheque on-line refletirá o pagamento de 30 dias de gratificações por um trabalho realizado apenas durante um terço do mês, consolidando dano ao erário.
Para bloquear ativamente esta falha, o sistema GENTE deve orquestrar um "Rito de Baixa" estrito, desenhado sob a seguinte ordem procedimental e de integração:
Fato Desestabilizador e Inserção Eletrônica (Trigger Factual): A unidade escolar toma ciência da ausência do titular. Seja através de um processo administrativo formal autuado via peticionamento eletrônico no SEI (Sistema Eletrônico de Informações da Prefeitura de São Luís) com a indexação do atestado médico temporário , ou via interface rápida de reporte na unidade, a ocorrência contendo a data_inicio é submetida na plataforma GENTE.
Invalidação e Mutação de Estado no Kanban: Imediatamente no instante (t0) da recepção sistêmica, a máquina de estados do GENTE altera a exibição visual do docente na matriz do Kanban de ESCALADO para AFASTADO (assumindo a tonalidade visual de alerta mapeada, como a cor vermelha para LM). Essa ação invalida instantaneamente quaisquer alocações futuras projetadas para aquele servidor durante o lapso temporal compreendido até a data_fim presumida da licença médica.
Processamento Lógico de Desmembramento (Cálculo Pró-Rata): O engine de regras do GENTE efetua o cômputo retroativo. Sabendo que o Estatuto proíbe categoricamente que a carga horária de Profissionais do Magistério exceda o teto de 40 (quarenta) horas semanais , e observando os parâmetros de horas-atividade (Art. 30, § 2º) , o algoritmo consolida o percentual de carga executada com perfeição até o dia útil imediatamente anterior à eclosão do evento desestabilizador, garantindo a liquidação deste passivo lícito e consolidando o crédito proporcional como intocável.
Expurgo das Vantagens Acessórias Condicionais: Ato contínuo, a plataforma GENTE programa a dedução proporcional das parcelas pecuniárias atreladas à assiduidade e produtividade executiva, suprimindo estritamente as cotas de regência de classe que deixaram de ser materializadas.
Desencadeamento da Solução Pedagógica (Acionamento de Substituição): Com o esvaziamento da docência, a turma não pode sofrer descontinuidade acadêmica. O Kanban emite um alerta ao gestor administrativo solicitando o preenchimento da lacuna. O preenchimento deste buraco operacional não é livre; obedece ao rito do Formulário SS (Substituição de Servidor). O sistema guia o operador a registrar o servidor substituto num prazo estipulado (ex: 3 dias).
Geração e Vinculação de Documento Normativo: A indicação sistêmica do substituto não tem validade sem a confecção de um instrumento oficial. A plataforma gera automaticamente o número de controle sequencial, com a formatação exigida pela secretaria, reiniciada a cada ano civil, padronizada como "SEMED - XX / 20XX". O cruzamento do nome completo do titular da área substituída e da matrícula funcional do substituto compõe a base relacional inserida na tabela RH_SUBSTITUICAO_DOCENTE. Este documento formal é autuado no dossiê funcional dos profissionais e pode ser remetido via integração para trâmite em processo do SEI.
Interceptação e Remessa Consolidadora Inter-sistemas (SISFOLHA): Antes da data de corte do processamento das rotinas contábeis do SISFOLHA (usualmente dias 15 ou 20 de cada mês), o sistema GENTE compila o pacote de desmembramento. Envia as rubricas de expurgo temporal aplicáveis à matrícula do docente acometido pelo afastamento de saúde, e concomitantemente envia as rubricas de acréscimo de remuneração de substituição aplicáveis à matrícula do docente substituto. No momento exato em que o servidor web da E-TIcons processar o cálculo dos tributos federais e emitir a folha para empenho de despesa pública , as informações cruzadas garantem o estancamento contábil e a liquidez da dotação orçamentária do município de São Luís, extinguindo o risco da auditoria apontar despesa sem provisão fática correspondente no chão de escola.
Arquitetura de Interoperabilidade e Protocolos de Integração de Dados
A concretização do modelo arquitetural que justapõe uma camada moderna de gerenciamento tático de tempos, tarefas e escalas (GENTE) sobre um núcleo robusto, porém estático, de conformidade fiscal e monetária (SISFOLHA), tem na infraestrutura de integração o seu calcanhar de Aquiles ou a sua maior proeza. As amarras contratuais e as limitações de capacidade do legado determinam a fluidez dos protocolos adotados.
A verificação do arcabouço tecnológico municipal, refletido nas diretrizes dos editais de contratação de software e nas especificações técnicas do produto fornecido pela E-TIcons à municipalidade e órgãos do estado , revela que o SISFOLHA não atua como uma caixa-preta hermética, mas dispõe de funcionalidades expressamente detalhadas voltadas para a recepção e emissão de pacotes informacionais complexos.
As especificações arquitetônicas referenciam claramente a "exportação/importação de dados em formatos padronizados em TI"  como cláusula balizadora de negócio, garantindo um terreno perfeitamente trafegável para a implementação de protocolos robustos de mensageria assíncrona ou de processamento de lotes (Batch Processing) pelo sistema GENTE. O domínio desses formatos de arquivo de remessa não é discricionário, mas sim a pedra angular da comunicação inter-sistemas que validará as movimentações de ausências do magistério.
Layouts de Exportação e Importação Homologados e Recomendados
As capacidades de interoperabilidade suportadas pelo sistema e o padrão do serviço público orientam a adoção e utilização das três estruturas informacionais e arquivos a seguir, cujas características intrínsecas ditam cenários de uso específicos na arquitetura proposta:
Formato de Texto Orientado a Posição (Layout Posicional .TXT):
O formato posicional .TXT permanece como a espinha dorsal de inúmeros processamentos back-office na administração municipal, por força do conservadorismo e robustez em processamentos volumosos em fita ou nuvem. As definições técnicas apontam a necessidade explícita do SISFOLHA de promover o "envio dos arquivos TXT no layout exigido pelo TCE"  para fins de controle e compliance de contratação de pessoal e folha.
Mecânica Analítica Operacional: Em um arquivo texto posicional estruturado para remessa folha-frequência, a sintaxe não possui delimitadores visíveis (como vírgulas). As regras ditam que cada linha corresponde de forma inequívoca a um registro transacional único, onde o espaço cartesiano dos caracteres possui significado estrito. Por exemplo: do caractere 01 ao 10, a matrícula do servidor preenchida com zeros à esquerda; do 11 ao 18, a data_inicio em formato DDMMAAAA; do 19 ao 21, o código alfanumérico normativo que espelha o tipo da ocorrência na Tabela 18 do e-Social, e do 22 em diante, dias apurados ou chaves referenciais de processos SEI.
Implicação Arquitetural: Embora a leitura de strings posicionais (parsing de tamanho fixo) seja considerada suscetível a erros de versão (uma mudança legal que acrescente um dígito à matrícula exigiria reescrita profunda do conector), este é o método mais incontestável e de processamento menos oneroso para enviar o lote massivo das movimentações do mês inteiro da SEMED, da interface GENTE até a esteira de processamento do legado E-TIcons, assegurando alinhamento com a arquitetura de envio de boletos bancários da Febraban à qual o sistema presta contínuo atendimento.
Valores Separados por Delimitadores Flexíveis (.CSV - Comma-Separated Values):
A arquitetura técnica reconhece expressamente o uso generalizado da linguagem simplificada e da manipulação matricial flexível suportada pelos "formatos padronizados em TI (.csv)".
Mecânica Analítica Operacional: Este protocolo de empacotamento secciona os campos da tabela transacional do banco de dados relacional e os "achata", separando os atributos (matrícula, data, tipo de afastamento, dias e flag financeira) por delimitadores explícitos de vírgula ou ponto e vírgula, e envoltos por aspas duplas, superando o engessamento mecânico do método posicional e prevenindo o desperdício de caracteres com acolchoamento de vazios (padding). O SISFOLHA, operando em nuvem web-server , dispõe de módulos que asseguram a "exportação da listagem dos registros em diversos formatos" , sugerindo plena assimilação deste modal.
Implicação Arquitetural: A extração e o engolimento em .CSV são imensamente mais rápidos, porém sem tipagem de dados forte implícita. No panorama arquitetural do sistema GENTE, o fluxo em .CSV emerge como o protocolo preferencial de excelência para a realização da Carga Inicial (Initial Load) e da sincronia de espelhamento retroativo de tabelas (Onboarding do Sistema). Ao iniciar as operações do GENTE, para popular instantaneamente as telas do Kanban e evitar inserção manual das férias e afastamentos em curso de meses pretéritos, o GENTE deve requisitar uma varredura completa (SELECT *) das tabelas legadas do SISFOLHA, despejada no formato .CSV, efetuando assim a migração de toda a fotografia do capital humano alocado no magistério de São Luís para a sua infraestrutura emergente.
Linguagem de Marcação Extensível (.XML - eXtensible Markup Language):
O ápice contemporâneo da interoperabilidade governamental com semântica acoplada, listado inequivocamente nos atestados técnicos e propostas arquiteturais que moldam os requisitos técnicos das plataformas e-TIcons para exportação e importação de dados (.csv, xml, etc.).
Mecânica Analítica Operacional: A grande virtude hierárquica do .XML reside no fato dele incorporar não apenas os valores nominais brutos, mas uma metalinguagem formal e robusta onde as marcações (tags) definem explicitamente os metadados de cada elemento (por exemplo: um bloco delimitado pelas tags <trabalhador>, possuindo nós internos de <cpfTrabalhador>, <infoAfastamento>, com desdobramentos lógicos rigorosos apontando sub-tags de <dataInicio> e <codMotAfast>).
Implicação Arquitetural: O domínio das instâncias .XML é crítico e de adoção sumamente recomendada para a transação diária do sistema GENTE. A justificação primordial decorre da obrigação intrínseca do SISFOLHA de transmitir informações acessórias laborais pelo ecossistema do e-Social e DCTF Web perante a plataforma online do Governo Federal. O ambiente do SPED/e-Social é integralmente desenhado e dependente da sintaxe de pacotes .XML validados e assinados digitalmente. Ao estruturar os endpoints da sua API interna e as rotinas de compilação diária, o sistema GENTE deve gerar um subproduto transacional nos eventos de afastamento que constitua um espelho direto em .XML dos eventos trabalhistas exigidos. Quando o coordenador lançar a inserção no Kanban que um professor sofreu um agravo e submeteu uma Licença Médica, o backend do GENTE geraria o XML com formatação idêntica ao evento S-2230 - Afastamento Temporário do SPED. Esta formatação padronizada mitiga falhas de re-arquitetura; se o GENTE entregar à porta de entrada do SISFOLHA o pacote estruturado de maneira idêntica à que o SISFOLHA posteriormente remeterá a Brasília para compor o e-Social, consolida-se uma via arterial impenetrável a erros lógicos de tradução e incompatibilidade sintática.
Arquitetura Macro de Fluxos Combinados e o Paradigma de Sincronia
O desenho final da ecologia tecnológica para a governança das secretarias deve contemplar não apenas os sistemas contábil e de escalas, mas os demais repositórios de documentação institucional que pavimentam o setor administrativo (notadamente o SEI). Considerando esse mosaico digital de atendimento ao servidor, manualizado extensivamente na prefeitura de São Luís , a topologia unificada de movimentação de pessoal estabelece-se na seguinte espiral sistêmica cotidiana:
A gênese de todo o fluxo (Ocorrência do Fato e Instrução Eletrônica) tem seu epicentro inicial na geração do documento probatório. O cidadão-servidor ou seu representante institucional formaliza e submete o requerimento instrutório acompanhado do laudo, atestado ou ofício comprobatório, peticionando sua entrada por intermédio das normativas operacionais de processos eletrônicos vigentes, que repousam prioritariamente nas fileiras do Sistema Eletrônico de Informações (SEI) da capital ou por preenchimento presencial suplementado via protocolo. Após a validação técnico-administrativa nos gabinetes locais e crivo eventual das Juntas Médicas Oficiais quanto aos lapsos regulatórios estritos que tangenciam a lei previdenciária , o dado é chancelado como lícito.
O passo adjacente reside na Centralização Visual e Modificação Dinâmica. O departamento pessoal das unidades escolares (SEMED), agora dotado das plataformas avançadas do sistema GENTE, imputa e consome os dados chancelados em sua moderna interface gráfica de Kanban gerencial, desativando a alocação do servidor no instante e gerando fisicamente o registro de histórico nas tabelas transacionais normalizadas supradescritas, deflagrando os cálculos computacionais preditivos para estancamento imediato da regência de classe  e, por consequência, a recomendação ininterrupta do "rito da baixa".
De forma periódica - assumindo o paradigma transacional em batches rotineiros preferido pelas instâncias governamentais como a Febraban, instâncias municipais e as regras consulares do Tribunal de Contas (TCE)  - o GENTE empacota os registros contidos no intervalo determinado de forma autônoma. O empacotamento materializa-se nos arquivos predeterminados e devidamente processados: os massivos históricos convertidos em .TXT de posicionamento severo, acrescidos dos fragmentos sensíveis transpostos no esquema de validação do .XML e-Social para mitigação pericial e previdenciária.
Este fluxo contínuo deposita o volume consolidado na interface predeterminada do legado. O SISFOLHA, por meio do sistema inteligente alocado nas nuvens E-TIcons , destrincha (parsea), autentica a procedência do pacote e deglute as lógicas. Empreende a complexidade residual do expurgo orçamentário: recalcula impostos municipais e federais retidos na fonte que dependem da base global, deflagra emissão das remessas finais e compila o contracheque on-line seguro, assegurando comodidade plena e invulnerável ao docente final.
Finalmente, consolidada a apuração fiscal e o trâmite contábil na secretaria de fazenda paralela, o sistema legado devolve, por intermédio de um .CSV retroalimentador de fechamento (Feedback Loop), um espelho consolidado final de competência ao servidor de banco de dados do sistema GENTE. Com esse recibo fático nas mãos, o painel central da plataforma de escala assinala perante os auditores que os afastamentos outrora declarados não apenas alteraram visualmente a conformação pedagógica das unidades e demandaram substituição via ofício, mas foram homologados irrevogavelmente no cerne das contas e da transparência pública, selando a interoperabilidade íntegra pretendida.
🛠️ O "Dicionário de Dados" Descoberto

A pesquisa identificou as tabelas e siglas que o SISFOLHA (da E-TIcons) utiliza ou espera receber:
Tipo de Ausência	Sigla GENTE	Código e-Social	Impacto Visual (Proposto)
Licença Médica	LM	01 ou 03	Vermelho Alerta (#E74C3C)
Férias	FR	15	Azul Estabilidade (#3498DB)
Licença Prêmio	LP	16	Roxo Institucional (#9B59B6)
Afastamento p/ Estudo	AE	30	Ciano / Teal (#1ABC9C)
Maternidade/Paternidade	LMA/LPA	17/18	Magenta / Rosa (#E84393)
Falta não Justificada	FNJ	-	Cinza Grafite (#2C3E50)

Tabelas Prováveis: RH_SERVIDOR, RH_TIPO_AFASTAMENTO e SERVIDOR_AFASTAMENTO (Histórico).
⚖️ A Regra de Ouro: O Cálculo Pró-Rata

A pesquisa revelou que a Regência de Classe não é um valor binário (ganha ou não ganha). Ela deve ser calculada de forma proporcional aos dias efetivos em sala de aula.
A fórmula para o valor da rubrica (VRC) é:
VRC=VB×PRC

Onde VB é o Vencimento Base e PRC é o percentual de regência (20% a 25%). Se houver um afastamento (LM ou FNJ), o sistema deve "estancar" o pagamento proporcionalmente ao período de ausência.
1. O Motor já é Global (A Arquitetura)

O "chassi" do sistema serve para qualquer secretaria.

    O Kanban visual.

    O sistema de arrastar e soltar turnos.

    O Motor de Ausências (Atestado de 15 dias é lei federal, vale tanto para o professor quanto para o gari).

    O "Sudo Mode" e os Logs de Auditoria.

Tudo isso funciona para a SEMED (Educação), SEMUS (Saúde), SEMOSP (Obras) ou SEMAD (Administração).
2. Por que parece que estamos focados só na SEMED?

Nós adotamos a estratégia de "Derrotar o Chefão Final Primeiro".

A Secretaria de Educação (SEMED) é, disparado, a folha de pagamento mais caótica e cheia de exceções de São Luís (e de qualquer município do Brasil). Só lá existem regras como:

    45 dias de férias para quem tá em sala.

    Regência de classe calculada por dia efetivo.

    Contratação de professor substituto amarrada à licença do titular.

Se o nosso sistema conseguir domar o monstro da SEMED, plugar a Secretaria de Administração (onde o pessoal bate ponto das 08h às 18h de segunda a sexta, sem exceções) vai ser fichinha.
3. O que falta fazer para "ligar" as outras secretarias?

Não temos que reescrever o sistema, mas temos que parametrizá-lo. É aqui que o seu "temos muito a fazer ainda" entra.

Quando formos escalar o sistema GENTE para a prefeitura inteira, precisaremos criar um Painel de Configuração de Tenant (Secretaria). Nele, o Super Admin vai dizer:

    Tenant 1 (SEMED): Ligar regra de 45 dias de férias. Ligar Regência de Classe. Turnos M, V, N.

    Tenant 2 (SEMUS - Saúde): Desligar Regência de Classe. Ligar regra de Plantão 12x36 e 24x72. Ligar adicional noturno hospitalar.

    Tenant 3 (SEMOSP - Obras): Desligar tudo. Turno padrão 08h-12h / 14h-18h.

Resumo da Ópera

Nós não construímos uma casa que só serve para a SEMED. Nós construímos um prédio comercial inteiro. A SEMED é só a empresa gigante e cheia de exigências que alugou o primeiro andar. O resto dos andares estão prontos, só precisamos decorar (parametrizar) conforme a secretaria que for entrar.

As fontes atuais confirmam que a SEMED utiliza o SISFOLHA para o controle de lotação e pagamento de milhares de servidores, processando dados críticos para a Lei de Responsabilidade Fiscal. No entanto, elas não contêm o organograma da secretaria, os nomes específicos dos cargos nas escolas, as divisões regionais ou os detalhes de integração técnica entre a SEMED e a SEMAD.
Para preencher essa lacuna e viabilizar a criação do seu Dashboard Executivo e da matriz de Controle de Acesso (RBAC) do sistema GENTE, estruturei o seguinte Plano de Pesquisa Estrutural e Multi-Tenant para sua aprovação:
Plano de Busca: Mapeamento Hierárquico e Fluxo do SISFOLHA (SEMED/SEMAD)

Fase 1: Mapeamento da Camada Operacional (Gestão Escolar)
Alvo: Regimento Interno das Escolas Municipais de São Luís e portarias recentes.
Objetivo: Identificar as nomenclaturas oficiais na ponta (Diretor Geral, Diretor Adjunto, Secretário Escolar) e confirmar quem possui a prerrogativa legal para atestar a frequência e registrar os afastamentos primários no sistema.
Fase 2: Mapeamento da Camada Intermediária (Supervisão Regional)
Alvo: Leis de reestruturação administrativa e diários oficiais do município.
Objetivo: Descobrir como a SEMED agrupa geograficamente suas escolas (Polos Educacionais, Distritos ou Superintendências de Área) e qual o cargo em comissão responsável pela validação regional.
Fase 3: Mapeamento da Camada Tática (Administração Central SEMED)
Alvo: Organograma oficial da sede da SEMED.
Objetivo: Mapear o setor exato que recebe as frequências validadas pelas escolas (ex: SAGEP, Coordenação de Gestão de Pessoas) e que atua como consolidador antes do envio para o processamento financeiro.
Fase 4: Mapeamento da Camada Estratégica Multi-Tenant (SEMED x SEMAD)
Alvo: Decretos municipais sobre a governança de Recursos Humanos e a gestão do SISFOLHA em São Luís.
Objetivo: Determinar a relação de subordinação: se a SEMED processa e aperta o "botão final" de pagamento de forma autônoma no SISFOLHA, ou se atua apenas enviando arquivos de remessa consolidados para a SEMAD (Secretaria Municipal de Administração) executar o processamento global da prefeitura.
Com os novos resultados da pesquisa aprofundada, agora temos os detalhes estruturais da legislação. A Lei Municipal nº 4.928/2008 define a gratificação de regência de classe como uma vantagem funcional destinada exclusivamente aos profissionais do magistério que se encontram no efetivo exercício da docência.
A lei estabelece os seguintes parâmetros e regras de negócio para a regência de classe, que são diretamente refletidos no sistema SISFOLHA:

Natureza Propter Laborem: A gratificação só é devida enquanto o servidor estiver atuando especificamente em sala de aula. Por exemplo, se o professor for afastado para assumir um mandato eletivo ou exercer funções administrativas na sede da SEMED, o pagamento da rubrica é automaticamente cessado. Por outro lado, licenças para tratamento de saúde de curta duração geralmente não suspendem esse direito.
Base de Cálculo Exclusiva: No SISFOLHA, ela atua como uma "Vantagem Fixa Relativa", o que significa que o percentual da regência (que historicamente varia entre 20% e 25%) incide de forma linear e exclusiva sobre o Vencimento Base atualizado do professor. Isso é parametrizado para evitar o "efeito cascata" (calcular a regência em cima de outras gratificações, como o adicional de titulação), o que é vedado pela Constituição.
Proporcionalidade da Jornada: O valor pago referente à regência de classe é diretamente proporcional à carga horária estipulada no contrato do servidor (20h, 24h ou 40h), impedindo que quem tem jornada parcial receba o valor integral.
Requisitos de Sistema: Para que a rubrica seja ativada na folha, o servidor deve, obrigatoriamente, estar vinculado ao código de uma unidade escolar ativa e o seu código de função deve ser de professor em regência.
Incorporação para Aposentadoria: Apesar de ser atrelada ao serviço em sala de aula, a legislação municipal prevê regras específicas que permitem a incorporação dessa gratificação para fins de aposentadoria após um tempo determinado de exercício.
Esses critérios garantem que a regência cumpra o seu papel jurídico e financeiro, valorizando o professor que está efetivamente na ponta (nas escolas), ao mesmo tempo em que protege o orçamento da prefeitura de pagamentos indevidos

Os resultados da pesquisa aprofundada acabaram de retornar, mas os documentos encontrados não especificam quais cargos escolares (como Diretor Geral, Diretor Adjunto ou Secretário Escolar) têm a prerrogativa legal para atestar a frequência dos servidores na rede municipal de São Luís.
As novas fontes trouxeram análises detalhadas sobre a Lei Orgânica do Município, o funcionamento orçamentário e a atuação do sindicato (Sindeducação), mas a documentação técnica operacional, como o Regimento Interno das Escolas Municipais, não foi localizada nesta busca.
O que as fontes confirmam sobre o fluxo de frequência e sistema é que:

O pagamento de rubricas como a gratificação de regência de classe exige a comprovação rigorosa do efetivo exercício em sala de aula.
O controle de lotação no SISFOLHA atua bloqueando pagamentos caso o servidor tenha determinados afastamentos registrados.
Perspectivas futuras para a gestão da SEMED envolvem o uso de Business Intelligence (BI) para cruzar dados de pagamento no SISFOLHA diretamente com os dados de frequência escolar em tempo real.
Como as normativas internas de Recursos Humanos não estão disponíveis publicamente nas fontes acessadas, para construir o Controle de Acesso (RBAC) do sistema GENTE com total segurança jurídica, o ideal será solicitar diretamente à gestão da SEMED o Regimento Interno das Escolas atualizado ou a Portaria de Controle de Frequência, que definirá exatamente quem assina e valida o ponto na camada operacional.
Governança
 Administrativa e Gestão de Pessoas na Secretaria Municipal de Educação
de São Luís: Uma Análise Estrutural do Processo de Validação de
Frequência via SISFOLHA
A
 administração pública municipal, especialmente no que tange à gestão da
 educação em grandes centros urbanos como São Luís, capital do Maranhão,
 exige uma arquitetura institucional robusta e processos administrativos
 altamente refinados. A Secretaria Municipal de Educação (SEMED) não
opera isoladamente; ela está inserida em um ecossistema de governança
que depende da integração técnica e normativa com a Secretaria Municipal
 de Administração (SEMAD). O núcleo dessa integração reside na gestão do
 capital humano, especificamente na precisão do controle de frequência e
 na conformidade da folha de pagamento, tarefas estas mediadas pelo
sistema SISFOLHA. Esta análise detalha a estrutura hierárquica da SEMED,
 as competências das suas superintendências e o fluxo rigoroso de
validação de frequência que garante a integridade dos dados funcionais
dos servidores da rede de ensino.[1, 2, 3]
A Estrutura Organizacional e Hierárquica da SEMED São Luís
A
 arquitetura institucional da SEMED é desenhada para suportar a
complexidade de uma rede que abrange centenas de Unidades de Ensino
Básico (UEBs), milhares de professores e uma vasta gama de profissionais
 de apoio administrativo e pedagógico. No ápice da estrutura encontra-se
 o Gabinete do Secretário, que exerce a liderança estratégica e a
articulação política junto ao Poder Executivo Municipal. Abaixo desta
liderança, a secretaria é subdividida em Secretarias Adjuntas, que
funcionam como pilares de gestão para áreas específicas como ensino,
gestão escolar e administração/finanças.[1]
A
 hierarquia da SEMED é caracterizada por uma descentralização
operacional que permite que as diretrizes centrais alcancem a ponta do
sistema — a sala de aula. As Secretarias Adjuntas coordenam
Superintendências, que por sua vez supervisionam departamentos e
supervisões. Esta cadeia de comando é vital para a tramitação de
processos administrativos, incluindo a validação de frequência. A
Superintendência de Gestão de Pessoas (SGP) destaca-se como o órgão
responsável pela interface entre as necessidades funcionais dos
servidores e as exigências sistêmicas da prefeitura.[1, 3]
A
 estrutura hierárquica nas escolas também segue um padrão rigoroso. Cada
 UEB é chefiada por um Gestor Escolar, que detém a responsabilidade
máxima pela administração da unidade, auxiliado por um Secretário
Escolar e coordenadores pedagógicos. É neste nível que se inicia o ciclo
 de dados que alimentará o SISFOLHA. A autoridade do gestor escolar é
delegada pela SEMED para garantir que a frequência dos servidores seja
atestada com fidedignidade, servindo como a primeira instância de
validação de dados.[1, 4]
Nível Estrutural
Unidade Administrativa
Responsabilidade no Fluxo de Pessoal
Estratégico
Gabinete do Secretário
Definição de políticas e dotação orçamentária geral.
Tático-Administrativo
Secretaria Adjunta de Administração e Finanças
Gestão macro dos recursos financeiros e contratos.
Tático-Operacional
Superintendência de Gestão de Pessoas (SGP)
Supervisão do SISFOLHA e vida funcional.[3]
Operacional Local
Gestão das Unidades de Ensino Básico (UEB)
Registro de frequência e validação primária.[4]
Suporte Sistêmico
Secretaria Municipal de Administração (SEMAD)
Manutenção do sistema SISFOLHA e processamento da folha.[2]
Esta
 organização garante que o fluxo de informações suba dos níveis
operacionais para os táticos com camadas sucessivas de conferência,
minimizando o risco de erros no processamento de vencimentos e
vantagens.[1, 2]
A Superintendência de Gestão de Pessoas e suas Competências
A
 Superintendência de Gestão de Pessoas (SGP) da SEMED atua como o
coração administrativo da secretaria no que tange ao servidor público.
Suas competências são vastas e vão muito além do simples registro de
dados. Cabe à SGP a gestão da vida funcional, o que inclui a análise de
progressões horizontais e verticais, a concessão de licenças, o controle
 de lotação e, crucialmente, a auditoria da frequência lançada pelas
escolas.[3]
A
 SGP funciona como uma ponte técnica com a SEMAD. Enquanto a SEMAD
define as normas gerais para todos os servidores do município de São
Luís, a SGP interpreta e aplica essas normas à realidade específica do
magistério, que possui um estatuto próprio e regimes de trabalho
diferenciados, como as jornadas de 20h, 24h ou 40h semanais. A
competência da SGP também abrange a gestão dos contratos temporários,
uma modalidade comum para suprir carências imediatas na rede de ensino,
exigindo um controle ainda mais célere via SISFOLHA.[2, 3]
Internamente,
 a SGP é dividida em departamentos que tratam da folha de pagamento, da
vida funcional e do atendimento ao servidor. O Departamento de Folha de
Pagamento é o usuário "master" do SISFOLHA dentro da SEMED, possuindo
atribuições para realizar ajustes manuais, correções de lotes e a
finalização mensal do processo de frequência. A competência para validar
 a frequência é, portanto, uma atribuição compartilhada: a escola
informa a ocorrência, a SGP valida a conformidade legal e a SEMAD
processa o impacto financeiro.[2, 3]
O Sistema SISFOLHA: Infraestrutura para a Gestão de Frequência
O
 SISFOLHA é a ferramenta tecnológica centralizada pela SEMAD para a
gestão de recursos humanos da Prefeitura de São Luís. Trata-se de um
sistema que integra cadastro funcional, folha de pagamento e controle de
 frequência em uma única plataforma web. Para a SEMED, o SISFOLHA
representa o mecanismo pelo qual a presença física do professor na
escola se transforma em remuneração no final do mês.
O
 funcionamento do SISFOLHA baseia-se em perfis de acesso hierarquizados.
 Os gestores escolares possuem acesso ao módulo de frequência de suas
respectivas unidades, onde devem registrar todas as ocorrências mensais.
 O sistema é parametrizado com os calendários letivos da SEMED, o que
impede, por exemplo, o lançamento de faltas em feriados ou períodos de
recesso escolar, a menos que haja atividades administrativas previstas. O
 manual do SISFOLHA detalha que a inserção de dados deve ocorrer dentro
de janelas temporais estritas, geralmente na primeira quinzena do mês de
 referência.[2]
A
 complexidade do sistema permite o registro de dezenas de tipos de
ocorrências. Uma falta não é apenas um dia não trabalhado; no SISFOLHA,
ela pode ser classificada como falta justificada, falta injustificada,
suspensão, licença médica, ou até mesmo afastamento para atividades
sindicais. Cada código inserido no sistema gera um impacto diferente na
vida funcional do servidor. Por exemplo, faltas injustificadas descontam
 não apenas o dia trabalhado, mas também o descanso semanal remunerado e
 impactam o tempo de serviço para fins de aposentadoria.
A
 integração técnica entre a SEMED e a SEMAD via SISFOLHA garante que a
folha de pagamento seja gerada com base em dados reais. O sistema possui
 travas de segurança que impedem o pagamento de gratificações de difícil
 acesso ou regência de classe para servidores que não estejam
efetivamente lotados em sala de aula ou em unidades que justifiquem tal
benefício. Assim, o SISFOLHA não é apenas um software de pagamento, mas
um instrumento de fiscalização da correta aplicação do dinheiro
público.[2, 3]
O Processo de Validação de Frequência: Do Registro à Consolidação
O
 processo de validação de frequência na SEMED São Luís segue um rito
burocrático desenhado para garantir a máxima transparência e precisão.
Este fluxo inicia-se diariamente nas UEBs, onde o servidor registra sua
presença, e culmina com a validação definitiva pela SGP no SISFOLHA.[4]
O
 primeiro estágio é a coleta física da frequência, geralmente através de
 folhas de ponto ou, em unidades modernizadas, via ponto eletrônico. O
Secretário Escolar consolida esses registros mensalmente. Antes da
inserção no sistema, o Gestor Escolar deve revisar os dados, assegurando
 que todas as faltas estejam devidamente justificadas por documentos
legais, como atestados médicos ou certidões. Esta fase é crítica, pois a
 inserção de dados errôneos pode gerar pagamentos indevidos que sujeitam
 o gestor a processos de responsabilidade administrativa.
Uma
 vez revisados, os dados são lançados no SISFOLHA. O sistema exige a
validação lote a lote. Após o preenchimento de todos os servidores da
unidade, o gestor realiza o "fechamento da frequência" no sistema. Este
ato eletrônico equivale a uma declaração de fé pública de que as
informações ali contidas são verdadeiras. Após este fechamento, a escola
 não pode mais alterar os dados, e a responsabilidade migra para a
Superintendência de Gestão de Pessoas.[2, 4]
A
 SGP realiza então a validação setorial. Técnicos analisam
inconsistências apontadas pelo sistema, como servidores sem frequência
lançada ou códigos de licença que conflitam com o cadastro funcional. Se
 um servidor está em licença-prêmio concedida pela SGP, mas a escola
lançou frequência normal, o sistema gera um alerta. A validação final
ocorre quando a SGP autoriza a transmissão dos dados para a base central
 da SEMAD, que procederá ao cálculo dos valores financeiros.[3]
Fase do Processo
Ator Responsável
Instrumento de Controle
Prazo Típico
Registro Diário
Servidor e Secretário Escolar
Folha de Ponto / Biometria
Diário
Consolidação Mensal
Secretário de Escola
Mapa de Frequência
Até o dia 2 de cada mês
Lançamento Sistêmico
Gestor da UEB
Portal SISFOLHA
Conforme cronograma SEMAD.[2]
Conferência e Ajuste
SGP / Departamento de Folha
Módulo de Gestão SEMED
Antes do fechamento da folha
Processamento e Pagamento
SEMAD
Sistema Central de Folha
Calendário mensal da prefeitura
Este
 fluxo sequencial impede que uma única pessoa detenha o controle total
sobre o pagamento, criando um sistema de freios e contrapesos que
protege tanto o servidor quanto o erário.[3, 4]
Normatização e Portarias: O Respaldo Legal da Frequência
A
 validação de frequência não é um ato discricionário, mas sim um
procedimento estritamente vinculado ao princípio da legalidade. A SEMED
publica periodicamente portarias que estabelecem as normas para o
registro e a validação da frequência escolar. Estas portarias definem os
 prazos de envio, a documentação necessária para abono de faltas e as
responsabilidades dos gestores.[4]
As
 portarias de frequência são essenciais para lidar com as
particularidades do ano letivo. Elas orientam como proceder em casos de
reposição de aulas, feriados antecipados ou suspensões de atividades por
 motivos de força maior. Sem o respaldo de uma portaria, o gestor
escolar não teria a segurança jurídica para abonar uma falta ou para
exigir a compensação de horários. Além disso, as portarias vinculam o
recebimento de gratificações específicas à efetiva presença em sala de
aula, conforme previsto no Plano de Cargos, Carreiras e Vencimentos
(PCCV) do magistério.[3, 4]
A
 conformidade com estas portarias é auditada rotineiramente pela
Controladoria Geral do Município (CGM). Caso um auditor identifique que
uma falta foi abonada sem o devido respaldo documental exigido pela
portaria da SEMED, o gestor pode ser compelido a ressarcir o valor ao
erário. Portanto, o processo de validação no SISFOLHA é a expressão
digital de um rigoroso arcabouço normativo que visa a eficiência do
serviço público.[2, 4]
Dinâmicas do SISFOLHA: Aspectos Técnicos do Lançamento
O
 uso prático do SISFOLHA exige treinamento específico, pois a interface
do sistema é desenhada para tratar volumes massivos de dados. O
lançamento da frequência geralmente é feito por exceção. Isso significa
que o sistema pré-carrega a frequência integral para todos os servidores
 da unidade, e o operador deve intervir apenas para registrar as
ocorrências (faltas, atrasos, licenças).
Um
 dos pontos mais complexos no SISFOLHA para a rede de educação é a
gestão das "dobras" e substituições. Muitos professores da rede
municipal de São Luís possuem dois vínculos ou realizam regimes
suplementares de aula. O sistema deve permitir que a frequência seja
lançada separadamente para cada vínculo, garantindo que o professor
receba corretamente por cada carga horária cumprida. A SGP monitora
esses lançamentos para evitar que um servidor ultrapasse o limite legal
de horas trabalhadas permitido pela legislação federal e municipal.[2,
3]
Além
 do lançamento manual, o SISFOLHA permite a importação de dados de
sistemas externos em algumas circunstâncias, embora o método manual via
portal web ainda seja o mais utilizado pelas escolas. A segurança da
informação é garantida por logs de acesso; cada alteração no histórico
de frequência de um servidor deixa um rastro digital com o CPF do
responsável e o horário da modificação. Isso confere ao sistema SISFOLHA
 um alto nível de auditabilidade, permitindo que a SEMAD identifique
qualquer tentativa de manipulação de dados.
Implicações da Gestão de Pessoas na Qualidade Educacional
A
 eficiência na gestão de pessoas e a precisão na validação de frequência
 impactam diretamente a qualidade do ensino oferecido pela SEMED São
Luís. Um sistema de frequência funcional permite que a secretaria
identifique padrões de absenteísmo em tempo real. Se uma escola
apresenta um alto índice de faltas médicas ou justificadas, a
Superintendência de Gestão de Pessoas pode intervir, seja enviando
professores substitutos ou analisando as causas do adoecimento laboral
naquelas unidades.[1, 3]
A
 correta validação no SISFOLHA também assegura que os professores
recebam suas gratificações de desempenho e incentivos por titulação de
forma tempestiva. O atraso no lançamento da frequência ou erros na
validação podem causar ruídos na relação entre a categoria e a gestão
municipal, levando a greves ou desmotivação. Assim, a burocracia
administrativa da frequência é, na verdade, um suporte essencial para a
harmonia pedagógica da rede.[2, 4]
A
 integração SEMED-SEMAD, mediada pela SGP, busca transformar a gestão de
 pessoas de uma atividade meramente cartorial em uma ferramenta
estratégica. Ao cruzar os dados de frequência com o desempenho escolar
das UEBs, a SEMED pode realizar um planejamento mais assertivo das suas
políticas educacionais. O SISFOLHA, portanto, fornece os dados primários
 que alimentam não apenas a folha de pagamento, mas também os
indicadores de gestão da educação municipal.[1, 3]
Desafios e Perspectivas para a Gestão de Pessoas e Sistemas
O
 cenário atual da SEMED São Luís aponta para uma necessidade contínua de
 modernização. Um dos principais desafios é a plena digitalização do
registro de ponto na ponta do sistema. Enquanto muitas escolas ainda
dependem de registros manuais, a meta da gestão é a integração total de
relógios de ponto biométricos com o SISFOLHA, o que eliminaria a etapa
de transcrição manual de dados e reduziria a quase zero a margem de
erro.
Outro
 desafio reside na capacitação técnica dos gestores escolares. Como a
rotatividade em cargos de confiança nas escolas pode ser alta, a SEMAD e
 a SGP precisam manter programas permanentes de treinamento no uso do
SISFOLHA e na interpretação das portarias de frequência. A compreensão
clara das regras de validação é o que previne o acúmulo de processos
administrativos e garante que o servidor tenha seus direitos
respeitados.[2, 3]
A
 transparência pública também é uma frente de evolução. A integração dos
 dados do SISFOLHA com o Portal da Transparência da Prefeitura de São
Luís permite que qualquer cidadão acompanhe o gasto com pessoal na
educação. Esta abertura de dados exige que o processo de validação de
frequência seja cada vez mais rigoroso, pois a visibilidade externa atua
 como um mecanismo adicional de controle social sobre a gestão
pública.[1, 4]
A Integração Estratégica entre SEMED e SEMAD na Governança Municipal
A
 governança da educação em São Luís é um exemplo de interdependência
administrativa. A SEMED detém o conhecimento pedagógico e a
responsabilidade direta pelas escolas, mas é a SEMAD que provê a
infraestrutura de gestão de pessoas e o suporte sistêmico através do
SISFOLHA. Esta relação é pautada por um fluxo contínuo de dados que
exige uma sintonia fina entre as equipes técnicas de ambas as
secretarias.[1, 2]
A
 Secretaria Municipal de Administração (SEMAD) atua como o órgão central
 do Sistema de Gestão de Pessoas. Ela é responsável por manter a
integridade do banco de dados do SISFOLHA e por garantir que as regras
de cálculo da folha estejam em conformidade com as leis federais, como a
 Lei de Responsabilidade Fiscal. A SEMED, por meio da SGP, alimenta este
 sistema com as particularidades da rede de ensino. Sem essa alimentação
 precisa e a validação rigorosa da frequência, o processamento da folha
de pagamento da educação — que representa uma das maiores fatias do
orçamento municipal — estaria em risco.[2, 3]
Esta
 integração manifesta-se especialmente durante o fechamento mensal da
folha. Há um diálogo constante entre os técnicos da SGP/SEMED e os
analistas de sistema da SEMAD para resolver problemas de lotação, erros
de processamento e inconsistências de dados funcionais. O sucesso desta
parceria é o que permite que o município de São Luís cumpra
rigorosamente seus calendários de pagamento, um fator fundamental para a
 estabilidade política e social da capital.[1, 2]
Análise Comparativa das Atribuições de Gestão
Para
 melhor compreensão da divisão de tarefas no ecossistema administrativo
de São Luís, a tabela abaixo compara as atribuições da SEMED e da SEMAD
no âmbito do SISFOLHA e da gestão de frequência.
Atividade
Responsabilidade SEMED (SGP e Escolas)
Responsabilidade SEMAD
Configuração do Sistema
Definição de horários e calendários escolares específicos.
Manutenção da infraestrutura de servidores e banco de dados.[2]
Gestão de Cadastro
Atualização de lotação e remoções entre escolas.
Registro de admissões, concursos e aposentadorias.
Controle de Frequência
Lançamento diário e validação mensal das ocorrências.[4]
Auditoria sistêmica e processamento de descontos.
Folha de Pagamento
Validação de gratificações específicas do magistério.[3]
Geração de contracheques e ordens bancárias de pagamento.
Treinamento
Instrução de gestores sobre portarias de frequência.
Capacitação técnica no uso do software SISFOLHA.
Esta
 divisão clara de papéis evita a sobreposição de funções e permite que
cada secretaria foque em sua especialidade, garantindo a eficiência
global da máquina pública municipal.[1, 3]
Considerações Finais sobre a Estrutura e Processos de Gestão
A
 análise da estrutura hierárquica da SEMED São Luís e do seu processo de
 validação de frequência revela um sistema maduro, porém em constante
evolução. A centralidade do SISFOLHA como ferramenta de controle
demonstra a aposta do município na tecnologia para garantir a lisura da
gestão de pessoas. O papel das escolas na base desse processo é
fundamental; sem um compromisso ético e técnico dos gestores escolares
na validação da frequência, toda a estrutura superior da SEMED e da
SEMAD seria alimentada com dados imprecisos.
A
 Superintendência de Gestão de Pessoas da SEMED atua como o elo vital
dessa corrente, transformando a realidade operacional das salas de aula
em registros funcionais organizados. Através das portarias e do
cumprimento rigoroso dos cronogramas do SISFOLHA, a administração
municipal de São Luís assegura que a educação pública seja gerida com
responsabilidade fiscal e respeito ao servidor. O futuro desta gestão
aponta para uma integração ainda mais profunda e automatizada, onde o
foco na eficiência administrativa continuará a servir como base para o
desenvolvimento educacional da capital maranhense.[1, 3]
A
 complexidade desta rede administrativa, que integra a hierarquia
escolar, as superintendências técnicas e os sistemas centrais de
administração, é o que garante que a política educacional saia do papel e
 se materialize em serviços públicos de qualidade para a população de
São Luís. A transparência e o controle contidos no processo de validação
 via SISFOLHA são, portanto, garantias de uma governança democrática e
eficiente.
O Papel do Gestor Escolar no Ecossistema Administrativo
O
 gestor da Unidade de Ensino Básico (UEB) ocupa uma posição singular na
estrutura hierárquica da SEMED. Embora seu foco principal seja o sucesso
 pedagógico e a aprendizagem dos alunos, ele é, por definição legal, o
agente administrativo responsável pela fidedignidade de todos os dados
que emanam de sua escola. No contexto do SISFOLHA, o gestor atua como um
 delegado da SEMAD dentro do ambiente escolar.[1, 2]
A
 responsabilidade do gestor na validação de frequência é intransferível.
 Cabe a ele supervisionar o trabalho do secretário escolar, garantindo
que nenhum servidor tenha frequência atestada sem a devida
contraprestação laboral. Este controle é especialmente sensível no caso
de professores que possuem cargas horárias flexíveis ou que atuam em
programas especiais. A validação correta no SISFOLHA evita que o
servidor seja prejudicado em sua vida funcional e, ao mesmo tempo,
impede que o município pague por serviços não prestados.
Além
 da frequência, o gestor escolar deve gerir a lotação real de sua
unidade. No SISFOLHA, o servidor deve estar "lotado" na escola onde
efetivamente trabalha. Inconsistências de lotação são causas frequentes
de erros na folha de pagamento. O gestor, em coordenação com a SGP, deve
 garantir que o cadastro no sistema reflita a realidade física da
escola. Esta tarefa exige uma comunicação constante com os níveis
táticos da SEMED, reforçando a natureza interconectada da hierarquia
administrativa.[1, 3]
Aspectos Práticos e Operacionais da Validação de Frequência
O
 ciclo mensal de validação de frequência no SISFOLHA segue um roteiro
técnico que deve ser dominado pelos operadores do sistema nas escolas e
na SGP. O processo não se encerra com o simples clique no botão de
"enviar". Existe uma fase de pré-processamento realizada pela SEMAD que
devolve inconsistências para correção imediata pela SEMED.[2]
Uma
 ocorrência comum no SISFOLHA é o "conflito de horários". Como o sistema
 é integrado para toda a prefeitura, se um servidor possui dois cargos
(um na SEMED e outro na SEMUS - Secretaria de Saúde, por exemplo), o
SISFOLHA identifica se houve sobreposição de horários de trabalho.
Nesses casos, a validação da frequência depende de uma análise da SGP
para verificar a legalidade do acúmulo de cargos. Esta integração
sistêmica é um dos maiores trunfos do SISFOLHA na prevenção de
irregularidades funcionais.[2, 3]
Outro
 aspecto operacional importante é o tratamento de retroativos. Se uma
frequência foi lançada erroneamente no mês anterior, o sistema permite o
 lançamento de ajustes no mês corrente, desde que autorizados pela SGP.
Este processo de "correção de lançamento" é cercado de formalidades,
exigindo que o gestor anexe a documentação comprobatória que justifica a
 alteração do dado histórico. A segurança do SISFOLHA garante que
nenhuma alteração retroativa seja feita sem o devido registro de
auditoria.
Impacto da Validação de Frequência na Vida Funcional do Servidor
Para
 o servidor público da SEMED São Luís, a correta validação de sua
frequência no SISFOLHA é a garantia de sua estabilidade e progressão. O
sistema alimenta automaticamente o histórico funcional que será
utilizado para o cálculo da aposentadoria e para a concessão de direitos
 como o adicional por tempo de serviço (quinquênio). Uma falta lançada
incorretamente pode atrasar em meses a concessão de um benefício.[2, 3]
A
 transparência no processo de validação permite que o servidor acompanhe
 sua situação através do "Portal do Servidor", onde as informações
processadas no SISFOLHA são disponibilizadas para consulta individual.
Caso identifique uma divergência entre o que trabalhou e o que foi
lançado, o servidor tem o direito de solicitar a correção junto à gestão
 da escola ou diretamente na SGP. Este fluxo de retroalimentação é
essencial para a justiça administrativa e para a manutenção de um clima
organizacional saudável na rede de ensino.
A
 SGP tem investido em canais de atendimento para resolver estas
disparidades de forma ágil. A meta é que o SISFOLHA seja percebido não
apenas como uma ferramenta de controle da prefeitura, mas como um
registro fiel da dedicação e do compromisso do profissional da educação
com o serviço público. A precisão dos dados é, em última análise, uma
forma de valorização do servidor.[1, 3]
Conclusão e Futuro da Gestão Administrativa na SEMED
A
 estrutura hierárquica e operacional da SEMED São Luís, integrada ao
sistema SISFOLHA e coordenada com a SEMAD, representa um modelo de
gestão que prioriza a legalidade e a eficiência. A clara divisão de
competências entre o nível estratégico (Gabinete), o tático (SGP) e o
operacional (Escolas) cria um ambiente de controle que é fundamental
para a gestão de uma das maiores folhas de pagamento do estado.[1, 2, 3]
O
 processo de validação de frequência, embora técnico e burocrático, é o
alicerce sobre o qual se constrói a confiança entre a administração e os
 servidores. As portarias normatizadoras e o rigor sistêmico do SISFOLHA
 garantem que a gestão do capital humano na educação seja pautada pela
transparência e pelo respeito aos recursos públicos. O desafio contínuo
de modernização e capacitação promete tornar estes processos ainda mais
fluidos, permitindo que a SEMED foque cada vez mais em sua missão
primordial: oferecer uma educação de excelência para os cidadãos de São
Luís.
Em
 suma, a simbiose entre a estrutura organizacional da SEMED e a
infraestrutura tecnológica da SEMAD através do SISFOLHA é o motor que
impulsiona a administração da educação na capital. A validação de
frequência é o ponto de encontro entre o dever do Estado de fiscalizar e
 o direito do servidor de ser devidamente remunerado, consolidando uma
governança pública ética e eficiente.

--------------------------------------------------------------------------------

Untitled, https://saoluis.ma.gov.br/semed/transparencia/institucional/organograma
Untitled, https://saoluis.ma.gov.br/semad/servicos/sisfolha-manual
Untitled, https://saoluis.ma.gov.br/semed/institucional/competencias-e-atribuicoes
Untitled, https://saoluis.ma.gov.br/semed/transparencia/legislacao/portarias
Análise
 Sistêmica da Governança Educacional em São Luís: Estrutura
Organizacional, Gestão Descentralizada e Integração Administrativa da
SEMED
A
 administração da educação pública em uma capital do porte de São Luís
exige uma arquitetura institucional que combine o rigor técnico do
controle administrativo com a flexibilidade necessária para atender a
uma rede escolar diversificada e geograficamente dispersa. A Secretaria
Municipal de Educação (SEMED) de São Luís, sob a gestão da Secretária
Anna Caroline Marques Pinheiro Salgado, configura-se como um complexo
ecossistema de governança, operando a partir de sua sede central no
Edifício Trade Center, no bairro São Francisco.[1] Esta análise detalha
os mecanismos de funcionamento da secretaria, explorando a hierarquia de
 seus cargos de gestão, a organização territorial por meio de polos, as
normativas que regem o comportamento institucional e a simbiose técnica
com a Secretaria Municipal de Administração (SEMAD) através do sistema
SISFOLHA.
Estrutura Organizacional e Dinâmica de Governança Central
A
 SEMED São Luís é estruturada para garantir que as diretrizes do Plano
Municipal de Educação sejam traduzidas em ações operacionais eficazes.
No topo da pirâmide administrativa, o Gabinete da Secretária atua como o
 epicentro das decisões estratégicas, coordenando o fluxo de informações
 e a articulação política com outras pastas e esferas governamentais.[1]
 A comunicação oficial e o suporte direto à liderança são providos pela
Assessoria de Comunicação e por uma estrutura de suporte técnico que
utiliza ferramentas modernas como o Sistema Eletrônico de Informações
(SEI Externo) para a tramitação de processos administrativos.[1] Esta
digitalização reflete uma busca pela transparência e celeridade,
permitindo que processos que anteriormente demandavam deslocamentos
físicos e manuseio de papel sejam auditáveis e céleres em ambiente
virtual.
A
 divisão departamental da SEMED é segmentada por competências
específicas que atendem tanto à manutenção da infraestrutura quanto à
gestão dos recursos humanos e financeiros. O Departamento de Engenharia e
 Infraestrutura, por exemplo, assume um papel vital na expansão da rede,
 gerenciando aditivos contratuais para obras e a construção de novas
Unidades de Educação Básica (U.E.B.) e creches de tempo integral.[1]
Paralelamente, o setor de Gestão de Convênios coordena a complexa
relação com o terceiro setor, especificamente com as Organizações da
Sociedade Civil (OSC) que mantêm escolas comunitárias, confessionais ou
filantrópicas.[1] Este modelo de gestão compartilhada exige um rigoroso
processo de credenciamento e a formalização de termos de colaboração que
 asseguram que os repasses vinculados ao FUNDEB e programas como o PNAE
(alimentação escolar) e PNAC sejam utilizados conforme as normas de
prestação de contas.
Departamento / Setor
Competências Principais
Impacto na Rede de Ensino
Gabinete da Secretária
Definição de políticas públicas e articulação intersetorial.
Alinhamento estratégico e liderança política da pasta.
Engenharia e Infraestrutura
Gestão de obras, reformas e revitalização de escolas.
Garantia de espaços físicos adequados e seguros para o ensino.
Gestão Financeira
Monitoramento do FUNDEB, PDDE e pagamentos de fornecedores.
Sustentabilidade econômica e conformidade fiscal da rede.
Convênios e OSCs
Credenciamento e repasse para instituições parceiras.
Expansão da oferta educativa via parcerias comunitárias.
Recursos Humanos
Gestão de concursos, posses e contratações temporárias.
Manutenção do quadro docente e administrativo qualificado.
Hierarquia de Gestão nas Unidades de Educação Básica
A
 gestão escolar em São Luís é estruturada para equilibrar a autonomia
pedagógica com a responsabilidade administrativa. Nas Unidades de
Educação Básica (U.E.B.), a hierarquia de cargos é encabeçada pelo
Gestor Geral, secundado pelo Gestor Adjunto em unidades de maior porte.
Estes profissionais são os responsáveis diretos pela execução das
políticas da SEMED no chão da escola, atuando como o elo entre a
administração central e a comunidade escolar.[1, 2] O Gestor Geral detém
 a autoridade máxima na unidade, respondendo pelo cumprimento dos dias
letivos, pela integridade do patrimônio público e pela prestação de
contas de verbas como as do Programa Dinheiro Direto na Escola
(PDDE).[1]
Abaixo
 da gestão administrativa, situa-se o Coordenador Pedagógico, cuja
função é eminentemente técnica e orientadora. Este profissional atua no
suporte aos professores, na análise dos índices de aprendizagem e na
implementação do currículo municipal. A separação entre a gestão
administrativa e a coordenação pedagógica é uma característica da rede
de São Luís que visa permitir que cada dimensão receba o foco
necessário, embora o Gestor Geral tenha a responsabilidade final sobre
ambos os eixos. O quadro de gestão é completado pelo Secretário Escolar,
 que gerencia a documentação oficial, matrículas e os dados inseridos
nos sistemas de monitoramento da secretaria.[1]
Cargo de Gestão Escolar
Responsabilidade Administrativa
Responsabilidade Pedagógica
Gestor Geral
Representação legal, gestão financeira (PDDE) e pessoal.
Supervisão geral do cumprimento do projeto pedagógico.
Gestor Adjunto
Apoio operacional e substituição legal do gestor geral.
Suporte na organização de turnos e fluxos escolares.
Coordenador Pedagógico
Gestão de recursos didáticos e formação docente.
Orientação pedagógica direta e acompanhamento de alunos.
Secretário Escolar
Manutenção de registros e lançamentos em sistemas oficiais.
Organização de históricos e regularidade documental estudantil.
Atribuições e Processos de Nomeação
O
 provimento desses cargos de gestão segue critérios técnicos e, em
determinados períodos, processos de consulta ou seleção que visam
garantir a competência e a legitimidade dos gestores perante a
comunidade escolar. A SEMED gerencia tanto a posse de novos educadores
concursados quanto a contratação temporária de professores para suprir
demandas emergenciais, garantindo que as salas de aula não fiquem
desassistidas.[1] A gestão de matrículas, incluindo o planejamento para
2026 e a gestão de listas de espera, é uma tarefa compartilhada entre a
secretaria central e os gestores das unidades, exigindo uma sincronia de
 dados para otimizar a distribuição das vagas disponíveis na rede.[1]
Estrutura de Polos Educacionais e Regionalização
Para
 gerenciar uma rede que abrange dezenas de bairros e áreas rurais, a
SEMED adota uma estratégia de regionalização por meio de Polos
Educacionais. Estes polos não são apenas divisões geográficas, mas
instâncias de coordenação que aproximam o gabinete da realidade local de
 cada conjunto de escolas.[3, 4] A estrutura de polos permite que a
SEMED realize reuniões periódicas com diretores regionais para alinhar
metas, resolver conflitos logísticos e fiscalizar a aplicação de
recursos de forma mais granular.
Os
 diretores de polos funcionam como supervisores territoriais,
consolidando as demandas das escolas de sua área de abrangência e
reportando-as à sede no Edifício Trade Center.[1, 3] Esta camada
intermediária de gestão é fundamental para a agilidade administrativa,
permitindo, por exemplo, que problemas de infraestrutura ou falta de
professores sejam detectados e endereçados com maior rapidez. A
organização por polos também facilita a distribuição de insumos, como a
alimentação escolar (PNAE) e materiais didáticos, garantindo que a
logística atenda às especificidades de cada região, desde o centro
urbano até as comunidades mais periféricas.[1]
Relação Institucional entre SEMED e SEMAD: O Ecossistema SISFOLHA
A
 interface entre a Secretaria de Educação (SEMED) e a Secretaria de
Administração (SEMAD) é um dos eixos mais críticos da gestão municipal,
materializando-se no uso intensivo do sistema SISFOLHA. Sendo a SEMED a
secretaria com o maior contingente de servidores em São Luís, a precisão
 no controle da folha de pagamento e da frequência é essencial para o
equilíbrio fiscal do município.[5, 6] A SEMAD detém a competência
normativa sobre a gestão de pessoas, estabelecendo as regras para o
processamento de pagamentos, enquanto a SEMED atua como a operadora
primária desses dados no contexto educacional.
O
 SISFOLHA é o sistema centralizado onde são registrados todos os eventos
 que impactam o vencimento do servidor, desde a frequência diária até a
concessão de licenças, progressões e vantagens.[7, 8] O fluxo de
informações é ascendente: a escola (U.E.B.) registra as ocorrências
mensais, que são validadas pelo setor de Recursos Humanos da SEMED e,
posteriormente, processadas pela SEMAD para a geração da folha de
pagamento. Este processo é regido por instruções normativas que definem
calendários rigorosos para o fechamento de dados, visando evitar atrasos
 ou erros que possam gerar passivos trabalhistas.[7]
Fluxo de Controle de Frequência e Pagamento
A
 relação institucional no uso do SISFOLHA exige uma cooperação técnica
contínua. Enquanto a SEMAD provê o suporte tecnológico e a base legal
para a gestão de pessoal, a SEMED fornece o insumo factual (quem
trabalhou, onde e sob quais condições). A integração sistêmica é
complementada pelo SEI Externo, utilizado para formalizar pedidos de
afastamento ou atualizações cadastrais que demandam análise
documental.[1] Esta simbiose garante que a gestão da força de trabalho
educacional seja realizada com rigor, permitindo o acompanhamento de
contratos temporários e a integração de novos servidores concursados de
forma organizada.[1]
Instituição
Função no Fluxo SISFOLHA
Ferramentas de Suporte
SEMED (Unidades Escolares)
Lançamento primário de frequência e ocorrências.
Diários de classe e registros de ponto.
SEMED (Setor de RH Central)
Auditoria e consolidação dos dados de toda a rede.
Sistema SISFOLHA e SEI Externo.
SEMAD (Coordenação de Folha)
Processamento financeiro e controle de legalidade.
Servidores SISFOLHA e Base de Dados Municipal.
SEMAD (Auditoria de Pessoal)
Verificação de conformidade com o plano de cargos.
Relatórios de gestão e decretos municipais.
Regimento Interno e Bases Normativas
O
 funcionamento da SEMED e de suas unidades subordinadas é balizado por
um conjunto de normas que compõem o Regimento Interno. Este documento
detalha as competências legais da secretaria e define o organograma
funcional, estabelecendo as linhas de subordinação e as atribuições de
cada departamento.[1] O regimento é o instrumento que confere segurança
jurídica às ações da Secretária Anna Caroline Marques Pinheiro Salgado e
 de sua equipe, delimitando as fronteiras de atuação administrativa e
pedagógica.
Para
 além do regimento interno da secretaria, as unidades escolares possuem
regimentos próprios que normatizam a vida acadêmica e administrativa
local. Estes regimentos disciplinam desde o processo de matrícula até as
 regras de convivência escolar e o funcionamento dos órgãos colegiados,
como o Conselho Escolar. A conformidade com estas normas é monitorada
pela SEMED para assegurar que todas as escolas da rede municipal operem
sob padrões de qualidade e legalidade equivalentes, independentemente de
 sua localização ou porte.[1]
Implicações da Conformidade Normativa
A
 adesão estrita ao regimento interno e às normas federais (como as leis
que regem o FUNDEB e o PNAE) é o que permite à SEMED manter parcerias
com Organizações da Sociedade Civil e receber transferências voluntárias
 da União.[1] A gestão de convênios, por exemplo, é inteiramente
dependente da regularidade documental e fiscal das entidades parceiras,
um processo que a SEMED coordena através de seus fluxos internos de
fiscalização e prestação de contas. A transparência exigida por estas
normas é atendida, em parte, pela disponibilização de informações no
portal oficial, onde constam dados sobre a estrutura, horários de
funcionamento e canais de contato direto como o e-mail do gabinete (gabinete@semed.saoluis.ma.gov.br).[1]
Dinâmica de Recursos e Infraestrutura Pedagógica
A
 gestão da infraestrutura na SEMED não se limita à construção de
prédios, mas envolve a criação de ambientes que favoreçam o aprendizado.
 O Departamento de Engenharia atua em sinergia com o planejamento
pedagógico para garantir que as novas creches de tempo integral e as
U.E.B.s revitalizadas atendam às normas de acessibilidade e
segurança.[1] O financiamento destas obras e da manutenção contínua
provém de uma combinação de recursos próprios do município e
transferências do FUNDEB, cuja aplicação é rigorosamente monitorada pelo
 departamento financeiro da secretaria.
A
 logística de alimentação escolar (PNAE) e de transporte também compõe o
 quadro de responsabilidades da SEMED. A relação institucional com
fornecedores e a gestão de contratos de serviços são fundamentais para
que o cotidiano escolar não seja interrompido. A integração destes
processos logísticos com os sistemas de gestão financeira permite que a
SEMED tenha uma visão em tempo real do custo por aluno e da eficiência
na aplicação dos recursos públicos, garantindo que o investimento em
educação se traduza em melhorias tangíveis na qualidade do ensino
oferecido pela Prefeitura de São Luís.[1]
Síntese da Gestão Territorial e Governança Sistêmica
A
 análise da estrutura administrativa da SEMED São Luís revela uma
organização que busca a modernização através da digitalização de
processos e da descentralização da gestão. A liderança estratégica da
Secretária Anna Caroline Marques Pinheiro Salgado é sustentada por uma
rede de departamentos técnicos, diretores de polos e gestores escolares
que operam de forma coordenada.[1] A relação com a SEMAD via SISFOLHA e a
 utilização do SEI Externo demonstram um compromisso com a eficiência
administrativa e a transparência.[1, 5]
A
 organização por polos educacionais e a hierarquia clara nas escolas
garantem que a secretaria consiga gerenciar a complexidade de uma rede
vasta, mantendo o foco na aprendizagem e na expansão da oferta
educativa. A contínua atualização dos regimentos internos e o rigor na
gestão de convênios com o terceiro setor asseguram que a SEMED cumpra
sua missão legal e social, consolidando-se como o pilar fundamental do
desenvolvimento humano em São Luís. A estrutura aqui descrita, amparada
por dados institucionais e normativas vigentes, compõe o cenário de uma
gestão que equilibra o controle centralizado com a capilaridade
necessária para uma educação inclusiva e eficiente.[1, 3]

--------------------------------------------------------------------------------

Secretaria Municipal de ... - Prefeitura Municipal de São Luís - MA, https://saoluis.ma.gov.br/semed
Untitled, https://saoluis.ma.gov.br/semed/paginas/estrutura-organizacional
Untitled, https://agenciasaoluis.com.br/noticia/22378/prefeitura-de-sao-luis-inicia-reunioes-com-diretores-dos-polos-educacionais-da-semed
Untitled, https://www.jusbrasil.com.br/diarios/DOM-SAO-LUIS/
Untitled, https://saoluis.ma.gov.br/semad/paginas/sisfolha
Untitled, http://portal.saoluis.ma.gov.br/semad
Untitled, http://portal.saoluis.ma.gov.br/semad/paginas/editais-e-publicacoes
Untitled, https://www.slideshare.net/search?ss=1&q=SISFOLHA+S%C3%A3o+Lu%C3%ADs

Na SEMED, o setor responsável pela análise das progressões verticais (que são motivadas justamente pela elevação da titulação do servidor) é a Superintendência de Gestão de Pessoas (SGP).
As fontes indicam que, quando um professor conclui um curso de qualificação (como um mestrado, por exemplo), é instaurado um processo administrativo na secretaria que culmina na emissão da portaria de progressão. Embora os documentos não especifiquem qual autoridade assina o documento final (como o Secretário ou o Superintendente), eles deixam claro que a gestão e análise de todo esse processo da vida funcional do servidor cabem à SGP.
Após a portaria de progressão ser emitida, um operador de Recursos Humanos atualiza a "Classe" do servidor no cadastro do sistema SISFOLHA. Essa alteração no sistema atua como um gatilho que dispara automaticamente o recálculo do adicional de titulação na folha de pagamento do mês.
A gratificação de regência de classe possui incidência direta de contribuição previdenciária para o IPAM.
Embora seja uma gratificação de serviço atrelada à atuação em sala de aula, a Lei 4.928/2008 estabelece regras específicas que permitem a sua incorporação para fins de aposentadoria. Para que o IPAM incorpore esse valor aos proventos do servidor, é exigido o cumprimento de um tempo determinado de exercício recebendo a gratificação, devendo-se observar também a legislação previdenciária municipal e as regras de transição constitucionais.
Na prática técnica, o sistema SISFOLHA é parametrizado com o "flag" de incidência do IPAM ativado para a rubrica de Regência de Classe. Isso garante que a retenção seja feita corretamente na fonte todos os meses, alimentando a base de contribuição necessária para que o professor tenha direito a essa incorporação no futuro.
O reajuste anual do Piso Nacional do Magistério impacta diretamente o SISFOLHA, exigindo a atualização obrigatória da tabela salarial no sistema, uma vez que a legislação municipal (Lei 4.928/2008) garante que nenhum professor receba um valor inferior ao piso correspondente à sua jornada de trabalho.
Tecnicamente e administrativamente, esse reajuste gera os seguintes impactos sistêmicos:

Atualização do Vencimento Base em Cascata: Como o vencimento base é a referência para as gratificações, a atualização da tabela no SISFOLHA altera automaticamente os valores proporcionais da regência de classe e do adicional de titulação, que são calculados com base em percentuais fixos sobre esse vencimento.
Simulação de Impacto Financeiro: Antes que a nova tabela salarial seja aplicada em definitivo na folha de pagamento, o SISFOLHA deve permitir a realização de simulações. Isso ajuda os gestores a preverem como o reajuste elevará o teto geral de gastos com pessoal e a monitorar o uso dos recursos do FUNDEB.
Manutenção de Histórico: O sistema é obrigado a manter o histórico de todas as tabelas salariais aplicadas, vinculando cada uma ao seu respectivo período de validade. Esse registro histórico é fundamental para viabilizar processos de auditoria e para assegurar que a prefeitura cumpra as regras da Lei de Responsabilidade Fiscal (LRF).

Pesquisa Estrutural Multi-Tenant: Organograma da SEMED, Integração com a SEMAD e Modelagem RBAC para o Sistema GENTE no Município de São Luís
O Ecossistema Educacional de São Luís e a Imperativa Sistêmica do Painel Executivo
A gestão da educação pública em redes municipais de grande porte constitui um dos desafios mais intrincados da administração pública contemporânea. No município de São Luís, capital do estado do Maranhão, a Secretaria Municipal de Educação (SEMED) opera como a entidade matriz responsável pela formulação, implementação e monitoramento das políticas públicas voltadas à educação básica. A magnitude desta operação é expressa em sua infraestrutura física e de capital humano, englobando o gerenciamento de mais de duzentas e cinquenta e seis unidades de ensino que prestam serviços educacionais a um contingente aproximado de oitenta e cinco mil estudantes, desde a educação infantil até a educação de jovens e adultos. Para sustentar esta rede, a SEMED administra uma força de trabalho massiva, superior a oito mil profissionais do Magistério, além de quadros de suporte técnico e administrativo.
Neste panorama de extrema densidade operacional, a ausência imprevista de profissionais nas unidades de ensino, comumente referenciada no jargão administrativo como "furo de escala", transcende a mera contingência gerencial para se tornar um passivo pedagógico e financeiro severo. A descontinuidade do serviço educacional contraria frontalmente as diretrizes estabelecidas pela Constituição Federal e pela Lei de Diretrizes e Bases da Educação Nacional (LDBEN - Lei nº 9.394/1996), que asseguram o cumprimento irrevogável dos dias letivos e da carga horária mínima de oitocentas horas anuais. Consequentemente, o Secretário Municipal de Educação necessita de mecanismos de telemetria que lhe permitam visualizar o cenário macro de lotação e o déficit operacional em tempo real. O módulo estratégico do sistema GENTE, materializado no Painel Executivo, emerge como a solução analítica definitiva para este gargalo histórico.
A consecução desta entrega, que consiste em uma aba de painel de controle provida de Indicadores-Chave de Desempenho (KPIs), exige muito mais do que o desenvolvimento de uma interface gráfica. Requer uma pesquisa estrutural profunda que embase uma arquitetura de software multilocatária (Multi-Tenant) e um Controle de Acesso Baseado em Papéis (RBAC - Role-Based Access Control). O sistema GENTE deve espelhar com exatidão a hierarquia organizacional da SEMED e suas intrincadas relações com a Secretaria Municipal de Administração (SEMAD), particularmente no que concerne ao subsistema de processamento de folha de pagamento corporativo, o SISFOLHA. O mapeamento estrutural divide a governança em quatro estratos lógicos e funcionais: a Camada Operacional, localizada no chão da escola; a Camada Intermediária, representada pelas supervisões e polos; a Camada Tática, centralizada na Sede da SEMED através da SAGEP; e a Camada Estratégica Central, que cristaliza a relação normativa entre SEMED e SEMAD.
A arquitetura Multi-Tenant escolhida para o sistema GENTE pressupõe que uma única instância da aplicação atenda a múltiplos subgrupos ou "inquilinos", garantindo o isolamento rigoroso dos dados pertinentes a cada um, ao mesmo tempo em que compartilha o banco de dados e a infraestrutura de processamento de forma escalável. Em São Luís, esta arquitetura assume uma topologia aninhada. Cada escola atua como um inquilino fundamental, processando suas próprias ocorrências de forma hermética. Os polos educacionais funcionam como inquilinos agregadores regionais, possuindo visibilidade sobre um conjunto de escolas sob sua jurisdição territorial ou administrativa. A SAGEP atua como o locatário global (Super-Tenant) no contexto da educação, enquanto a SEMAD figura como um locatário auditor externo, consumindo os dados transformados em impactos financeiros. A modelagem RBAC deve, portanto, impor restrições de nível de linha (Row-Level Security), assegurando que nenhum agente extrapole suas competências estatutárias e regimentais ao manusear a lotação e a frequência dos servidores.
A Camada Operacional: Dinâmica, Regimento e Gênese dos Dados nas Unidades de Ensino
A base da pirâmide estrutural e informacional do sistema GENTE reside na Camada Operacional. É no espaço físico e administrativo da unidade de ensino que o "furo de escala" ocorre em sua forma mais rudimentar. Quando um professor não comparece para o exercício de sua regência de classe, o impacto no planejamento escolar é imediato, exigindo ações mitigatórias instantâneas por parte da equipe gestora local. O sucesso e a fidedignidade do Painel Executivo que chegará à mesa do Secretário de Educação dependem exclusivamente da qualidade e da temporalidade da inserção de dados nesta camada, caracterizando-a como o epicentro da geração de dados (Data Genesis). O fluxo de informações nesta instância é governado pelo Regimento Escolar da Rede Municipal de Ensino de São Luís, que dita os direitos, os deveres e as atribuições funcionais dos corpos técnico-pedagógico e administrativo.
A autoridade máxima da unidade de ensino no contexto do micro-tenant é o Diretor Escolar. Conforme preconizado pelas normativas educacionais e pelo Regimento Escolar vigente, o Diretor detém o dever primordial de fazer cumprir as normas da escola, de possibilitar que a instituição cumpra a sua função social e de assegurar o princípio constitucional da igualdade de condições para o acesso e a permanência do educando. Na transposição desta responsabilidade analógica para a governança digital do sistema GENTE, o Diretor assume o papel de aprovação primária (Approval Workflow Nível 1). Quando uma ausência é identificada, não compete ao Diretor a inserção burocrática do dado no sistema, mas sim a validação administrativa do evento. O Diretor examina se o furo de escala é justificado preliminarmente, aciona os substitutos imediatos se houver disponibilidade interna e avaliza a comunicação da vacância temporária ou definitiva para os níveis superiores. O acesso do Diretor ao sistema fornece uma visão integral e exaustiva, porém restrita ao identificador de banco de dados (ID) exclusivo de sua escola, protegendo a privacidade dos servidores lotados em outras instituições.
O operador de linha de frente, responsável pela fluidez da escrituração no sistema GENTE, é o Secretário Escolar. A legislação e o regimento estabelecem que este profissional, seja ele de provimento efetivo ou contratado, é o encarregado da secretaria escolar, tendo como foco principal a organização dos arquivos, o registro documental e a garantia do fluxo informacional indispensável ao processo pedagógico e à administração escolar. Sob a ótica do Controle de Acesso Baseado em Papéis, o Secretário Escolar é o principal agente de entrada de dados. Ele é o detentor da permissão para registrar faltas, anexar atestados médicos, apontar licenças iniciais e sinalizar a ausência na regência de classe em tempo real. A restrição informacional aplicada a este papel é severa; o Secretário Escolar possui privilégios de criação e edição estritamente circunscritos à folha de frequência diária de sua própria escola. Ele não possui privilégios de sistema para alterar a lotação permanente de um professor (transferi-lo para outra escola) ou conceder licenças de longo prazo que alterem a folha de pagamento de forma estrutural, cabendo-lhe apenas disparar tais solicitações na forma de fluxo de trabalho para a sede tática.
A interdependência entre o Secretário Escolar e o Diretor cria uma célula de governança dual na origem do dado. O Secretário realiza o apontamento bruto da ausência com base na constatação física diária, enquanto o Diretor corrobora a veracidade do evento e aprova a sua consolidação no banco de dados. Apenas após esta validação cruzada no nível do inquilino local é que o déficit computado alimenta o Dashboard do Secretário de Educação, garantindo que o índice de furo de escala reflita uma realidade já auditada na ponta do processo e evitando a propagação de falsos positivos que poderiam induzir a alta gestão a decisões logísticas ou financeiras precipitadas.
A Camada Intermediária: Supervisões, Distritos e a Capilaridade dos Polos Educacionais
A gestão centralizada pura demonstra-se ineficaz frente à complexidade geográfica, demográfica e histórica da rede municipal de São Luís. A expansão do município, notadamente entre as décadas de 1950 e 1970 com a construção de grandes obras de infraestrutura como a barragem do Bacanga, gerou um crescimento exponencial rumo às periferias, criando dezenas de bairros populares e zonas rururbanas. Para lidar com essa separação espacial da população e garantir a equidade na prestação do serviço educacional em toda a extensão do território municipal, a estrutura da SEMED consolidou a Camada Intermediária, operacionalizada através de Departamentos de Distritos Regionais e Polos Educacionais. Esta camada funciona como um amortecedor logístico e um filtro gerencial de altíssima relevância, posicionando-se entre as centenas de escolas e a sede central da Secretaria.
A setorização administrativa fragmenta a administração em áreas de controle gerenciáveis. Por exemplo, existem referências claras a zonas complexas como o "Polo Itaqui-Bacanga", que engloba unidades escolares situadas em bairros de alta densidade como Vila Nova, Vila São Luís, e Mauro Fecury I e II. Adicionalmente, especialmente nas áreas mais afastadas do centro urbano ou na zona rural, a SEMED frequentemente adota o arranjo de "Escolas Polo" ou polos educacionais integrados. Este modelo administrativo coloca uma instituição de ensino de maior porte como escola matriz de um agrupamento de escolas associadas. Nesse arranjo sistêmico, o gestor do polo ou coordenador distrital é responsável por monitorar não apenas uma unidade de ensino isolada, mas sim o ecossistema pedagógico e operacional de todas as escolas alocadas sob o seu guarda-chuva administrativo.
No que tange à modelagem do Controle de Acesso Baseado em Papéis no sistema GENTE, a figura do Coordenador de Polo ou Supervisor Distrital exige um desdobramento avançado da arquitetura Multi-Tenant. Este usuário não pertence a um inquilino único, mas é titular de uma permissão associada a um Grupo de Inquilinos (Tenant Group). O seu acesso ao painel de lotação quebra a barreira do isolamento de uma única escola, conferindo-lhe uma visibilidade panorâmica de toda a sua jurisdição. O objetivo principal deste papel na cadeia de mitigação do furo de escala é a resolução ágil de déficits localizados através do remanejamento horizontal de recursos humanos e infraestruturais.
Quando o sistema detecta que uma escola específica no Polo Itaqui-Bacanga sofreu uma perda temporária de um docente, e simultaneamente identifica que uma escola vizinha pertencente ao mesmo polo possui profissionais com disponibilidade de carga horária na mesma disciplina, o Coordenador de Polo tem a prerrogativa sistêmica de intervir. A permissão concedida a este papel abrange a aprovação ou sugestão de permutas de escala de curto prazo. Desta maneira, a Camada Intermediária resolve atritos operacionais de maneira descentralizada, garantindo que apenas os furos estruturais irresolvíveis na ponta regional escalem como demanda para a sede da SEMED. Essa lógica não apenas desafoga a máquina administrativa central, mas refina substancialmente a precisão das informações exibidas no Dashboard Executivo, uma vez que o Secretário Municipal observará apenas os déficits que demandam contratações substitutivas, convocação de excedentes de concurso público ou pagamentos extraordinários de horas suplementares.
A Camada Tática: A Sede da SEMED, a SAGEP e a Complexidade Regimental do Magistério
O epicentro decisório e normativo no que se refere aos recursos humanos no âmbito da Secretaria Municipal de Educação repousa na Camada Tática, localizada fisicamente na sede administrativa central. A estrutura de alto escalão da SEMED é composta pela figura do Secretário Municipal de Educação e por secretarias adjuntas de áreas específicas, destacando-se a Secretaria Adjunta de Ensino, a Secretaria Adjunta de Orçamento e Finanças e, fundamentalmente para o escopo desta pesquisa, a Secretaria Adjunta de Gestão de Pessoas (SAGEP). A SAGEP constitui o motor lógico que processa e chancela as movimentações vitais da força de trabalho educacional de São Luís, desempenhando a curadoria do banco de dados contendo o histórico ativo de mais de oito mil profissionais do Magistério.
No contexto arquitetônico do sistema GENTE, a SAGEP detém o perfil de Super-Inquilino (Super-Tenant) setorial, operando com visibilidade e capacidade de intervenção global em todas as unidades de ensino e polos do município. A sua função transcende a reação reativa aos furos de escala apontados pela Camada Operacional. Cabe aos analistas e gestores da SAGEP a responsabilidade da modelagem prévia da lotação para o ano letivo completo, balizada por restrições contratuais, cargas horárias regulamentadas e necessidades curriculares. A profundidade técnica exigida deste estrato reside na sua obrigatoriedade inegociável de alinhar todas as ações operacionais dentro da moldura do arcabouço jurídico e estatutário do município de São Luís.
As regras de negócio automatizadas no sistema GENTE, administradas pela SAGEP, devem ser uma tradução exata em código dos diplomas legais vigentes. O primeiro destes é o Estatuto dos Servidores Públicos do Município de São Luís (Lei Municipal 4.615/2006), que versa sobre a normatização genérica de ingresso, licenças, afastamentos para tratamento de saúde e direitos previdenciários, criando o substrato de legalidade para as ausências dos professores. O segundo diploma, de igual relevância, é o Estatuto do Magistério Público Municipal (Lei Municipal 4.749/2007 e suas revisões), que tipifica as especificidades da carreira docente, suas garantias e exigências curriculares. A pedra angular financeira e de distribuição de recursos que baliza a SAGEP, no entanto, é o Plano de Cargos, Carreiras e Vencimentos - PCCV (Lei Municipal 4.928/2008 e Lei Municipal 4.931/2008). O impacto dessa legislação é tão profundo que o próprio texto do PCCV estipulou prazos exíguos (Art. 72) para que a Coordenação de Recursos Humanos da SEMED reestruturasse inteiramente o sistema de lotação e controle de exercício à época de sua promulgação, ressaltando o imperativo legal de manter a máquina de controle de pessoal perfeitamente atualizada.
Um desdobramento crucial operado pela SAGEP dentro do sistema GENTE diz respeito à gestão do status de "Regência de Classe". O ordenamento normativo estadual e municipal prevê que a alocação de um docente no chão da sala de aula afeta diretamente o cálculo de suas vantagens remuneratórias. Quando um professor é readaptado por questões de saúde (com atestado ratificado por junta médica pericial), cedido para cargos administrativos na Secretaria ou agraciado com licenças para especialização acadêmica, este profissional afasta-se da regência de classe. Do ponto de vista pedagógico, o sistema deve registrar a abertura definitiva ou temporária da vaga naquela unidade escolar, acionando alertas no Dashboard de Furo de Escala e demandando reposição urgente via banco de reservas da SEMED. Do ponto de vista administrativo e financeiro, a alteração da flag de "Regência de Classe" executada pelo gestor da SAGEP modifica substancialmente o fluxo de pagamento do servidor, expurgando gratificações exclusivas de docência em sala e estabilizando a remuneração de acordo com os limites das parcelas de caráter permanente previstos na legislação de carreira.
Devido às pesadas repercussões estatutárias e orçamentárias derivadas das concessões de licenças de longo prazo, progressões de carreira e afastamentos formais, as permissões de edição no sistema GENTE referentes à mudança definitiva do status cadastral do servidor são exclusividade do grupo de papéis atribuídos aos funcionários da SAGEP. A eles cabe atuar com poder de sobreposição (override) sobre o sistema. Caso haja qualquer suspeita de irregularidade, ou se a Comissão de Aplicação do Estatuto do Magistério (COAPEM) — instância colegiada prevista na Lei 4.928/2008 que dirime dúvidas sobre progressão e normativas — deliberar de forma contrária a uma alocação originada em um Polo, o analista da SAGEP detém a permissão sistêmica para reverter a ação, assegurando que o sistema seja uma expressão inconteste da conformidade administrativa e da lisura no emprego do erário público.
A Camada Estratégica Central: Integração Normativa SEMED-SEMAD e o Subsistema SISFOLHA
A estrutura analítica do controle de pessoal no sistema GENTE tangencia sua última camada ao ultrapassar os contornos institucionais da Secretaria Municipal de Educação e conectar-se aos pilares da gestão corporativa do município. Esta Camada Estratégica Central define a complexa relação normativa e procedimental entre a SEMED e a Secretaria Municipal de Administração (SEMAD). A SEMAD é o órgão central de governança da Prefeitura de São Luís instituído e reorganizado por leis estruturais como a Lei 4.123/2002 e a Lei 5.218/2009, recaindo sobre esta secretaria a formidável responsabilidade pela emissão final da folha de pagamento unificada e pela conformidade dos gastos públicos com os ditames da Lei de Responsabilidade Fiscal. Enquanto a SAGEP e a SEMED definem os arranjos pedagógicos respondendo ao desafio de "quem leciona, onde e em qual horário", cabe estritamente à SEMAD orquestrar a repercussão de tais arranjos na seara orçamentária e financeira do ente municipal.
O elo tecnológico entre a operação educacional (GENTE) e a concretização orçamentária repousa na integração com o software adotado pela prefeitura para a gestão avançada de folha, o sistema SISFOLHA, provido pela E-Ticons. Os manuais operacionais e cadernos de especificações relatam que o SISFOLHA opera através de cálculos parametrizáveis em alto nível, emitindo relatórios de empenhos baseados em layouts bancários, processamento de deduções legais e cumprimento rigoroso de rotinas previdenciárias e fiscais através de gerações de arquivos de remessa padronizados (como os definidos pela Febraban para créditos e obrigações). Para que o Secretário Municipal de Educação possua um Dashboard analítico e executivo preciso em tempo real sobre os custos operacionais da rede, a interoperabilidade entre o novo módulo GENTE e a plataforma SISFOLHA precisa ser assíncrona, porém perfeitamente coesa em sua bidirecionalidade informacional.
No fluxo descendente de informações (Downstream), o sistema SISFOLHA exporta para a SAGEP a base de dados matriz contendo as rubricas orçamentárias vigentes, o limite salarial teto autorizado por classe, eventuais bloqueios judiciais que afetem o pagamento e a consolidação final da folha de meses anteriores, servindo como o repositório de verdade para os cadastros funcionais. No fluxo ascendente, crucial para a retroalimentação da gestão corporativa (Upstream), as ocorrências diárias minuciosamente refinadas pelas diversas camadas do sistema GENTE são consolidadas em arquivos eletrônicos e enviadas à SEMAD. Por exemplo, os apontamentos inseridos pelos secretários escolares atestando horas suplementares trabalhadas por docentes que cobriram furos de escala de colegas, validados primeiramente pelo diretor, depois pelos coordenadores de polo e, por fim, ratificados pelas portarias de lotação da SAGEP, são transmitidos para o SISFOLHA como insumos contábeis brutos. Esse movimento garante que o pagamento devido ao servidor seja efetuado com pontualidade na data limite estabelecida pelo cronograma de desembolso aprovado pelo gabinete.
A magnitude dessas operações, envolvendo repasses de verbas massivas decorrentes da receita de impostos e dos repasses constitucionais para a educação (FUNDEB e complementações da União ), exige que a modelagem de ambos os sistemas mantenha padrões estritos de rastreabilidade. Conforme demandado pelas normativas de integração de softwares contábeis voltados para o setor público (incluindo o Manual de Contabilidade Aplicada ao Setor Público - MCASP), o sistema deve dispor de logs de manutenção de dados indeléveis. Todas as versões de um registro alterado no sistema GENTE necessitam ser arquivadas concomitantemente; se um registro é excluído, uma cópia forense do mesmo permanece gravada para fins de escrutínio pelo Tribunal de Contas. Em respeito a esses preceitos, o desenho da arquitetura RBAC incorpora a criação de papéis do tipo "Auditor_SEMAD". Este papel outorga à Secretaria de Administração permissões globais de consulta em caráter estritamente de leitura (Read-Only) em todo o banco de dados da educação. A SEMAD não interfere na escala individual das escolas de São Luís, mas consome de forma transparente os dados consolidados pelo GENTE para atestar que as práticas mitigatórias engendradas pela SAGEP para sanar os furos de escala não incorram em extrapolação da dotação orçamentária do município.
O Painel Executivo: KPIs Estratégicos para Tomada de Decisão Superior
O colapso da barreira que divide os dados puramente burocráticos da percepção gerencial imediata ocorre através da visualização de dados materializada no Painel Executivo do sistema GENTE. O escopo demandado na concepção do sistema foca primordialmente na elaboração de uma ferramenta que empossa o Secretário de Educação de uma visão abrangente, atualizada em tempo real, acerca da conjuntura da alocação de recursos e de todo e qualquer déficit operacional em andamento na rede física de duzentas e cinquenta e seis escolas sob sua custódia. O Dashboard destina-se ao escoamento contínuo das informações transacionais inseridas pelas camadas inferiores e não exibe micro-conflitos (como o apontamento individual de um servidor). Ao invés disso, apresenta metadados traduzidos em Indicadores-Chave de Desempenho (KPIs), formatados para orientar planos de contingência estratégicos, decisões de abertura de editais de seleção ou remanejamento sistêmico de verbas no gabinete superior.
O tratamento lógico da informação suporta a apresentação dos seguintes KPIs, modelados para expor de maneira transparente as mazelas operacionais e a eficiência corporativa:
1. Taxa Relativa de Furo de Escala Sistêmico (TRFE)
Este indicador constitui o termômetro principal do déficit operacional cotidiano enfrentado pelas unidades da rede educacional. A Taxa Relativa mensura a fração exata da oferta educacional que deixou de ser prestada em virtude de vacâncias docentes não programadas.
A métrica é construída a partir da agregação temporal de todas as horas-aula que não foram ministradas (independentemente da tipificação da ausência — quer seja licença médica, falta injustificada ou afastamento legal sem aviso prévio atempado) dividida pela totalidade das horas-aula exigidas pela matriz curricular da rede municipal durante um determinado ínterim. A arquitetura de interface propicia a exploração profunda do dado (drill-down). Ao visualizar a TRFE, o Secretário tem a prerrogativa de aplicar filtros transversais por Distritos/Polos, permitindo-lhe isolar se a criticidade do furo de escala está contida na área do Polo Itaqui-Bacanga, nas zonas litorâneas ou na área de expansão agrícola. Um aumento vertiginoso deste índice pode evidenciar um passivo crescente em saúde ocupacional dentro do magistério, fornecendo o ímpeto necessário para intervenções articuladas entre o departamento de perícia médica e os serviços de assistência psicossocial do servidor.
2. Índice de Cobertura e Resiliência da Regência (ICRR)
Se a Taxa de Furo expõe o problema, o ICRR demonstra a resiliência institucional e a capacidade de resposta engajada pelas Camadas Intermediária e Tática da SEMED. Este KPI reflete a porcentagem dos furos de escala que foram neutralizados por intervenções administrativas tempestivas.
Calcula-se o índice quantificando-se a carga horária em vacância temporal que obteve cobertura satisfatória através da designação de professores substitutos ou de expedientes de remanejamento interno, dividida pelo déficit total daquele período. Quando o Secretário Municipal defronta-se com TRFEs severos mitigados por ICRRs elevados, a leitura indica que, apesar de o passivo de ausência apresentar números robustos, a Secretaria Adjunta de Gestão de Pessoas (SAGEP) dispõe de agilidade contratual e bancos de reservas operantes suficientes para sufocar as crises no chão de fábrica da educação, assegurando a continuidade dos trabalhos escolares e minimizando as perturbações no plano de aulas das crianças e dos jovens. Contrariamente, baixos desempenhos no ICRR alertam para uma paralisia gerencial da cadeia de substituição.
3. Coeficiente de Conformidade de Lotação Estrutural (CCLE)
A Lotação excede a mera atribuição do profissional a uma cadeira e transforma-se no desafio permanente de alocação equânime e racional de recursos escassos em contraponto às pressões de adensamento demográfico contínuo do município.
A estrutura de banco de dados do sistema GENTE executa o cruzamento contínuo entre a matriz de servidores atrelados formalmente a uma unidade escolar e os parâmetros demográficos de demanda estudantil, calculando o preenchimento ótimo das escolas balizado pelos ditames de proporção aluno/professor normatizados nas cartilhas da SAGEP e do MEC. A exposição visual desse KPI frequentemente adota metodologias de mapas de calor (Heatmaps). Regiões do mapa apresentando o indicador subdimensionado alertam a direção para os fenômenos simultâneos de superlotação concentrada nos núcleos escolares antigos da capital face à ociosidade estrutural em distritos carentes, norteando as diretrizes globais para as próximas campanhas de concurso interno de remoção, provimento de servidores ou redimensionamento da rede física em conjunto com as instâncias federais e a Secretaria Municipal de Orçamento.
4. Impacto Financeiro Indireto por Déficit e Suplementação (IFIDS)
O IFIDS materializa a conversão explícita das anomalias de capital humano para a semântica orçamentária fiscal controlada pela Secretaria Municipal de Administração (SEMAD). Consiste no custo acrescido para o Tesouro Municipal ao custear o tapamento de buracos em detrimento ao plano basal.
A formulação provém do cruzamento direto entre o volume da carga horária de déficit e a precificação gerada a partir das tabelas matrizes de horas extraordinárias ou dos contratos por prazo determinado integradas via SISFOLHA da prefeitura. Os valores expostos pelo IFIDS mostram ao Secretário Municipal e às esferas financeiras da gestão municipal a velocidade de combustão de verbas imprevistas, pautando discussões acerca da pertinência econômica em se promover um concurso público perene de modo a substituir uma política dispendiosa e recorrente de horas excedentes cobertas, tudo mediante os contrapesos exigidos pelos relatórios periódicos da Lei de Responsabilidade Fiscal municipal.
Tais métricas figuram no epicentro dos esforços do Painel Executivo do sistema GENTE, assegurando-se que a tecnologia subjacente Multi-Tenant impeça que quaisquer destes números colidais sofram a influência indevida da manipulação não autorizada, chancelando a gestão orientada por evidências.
Matriz Hierárquica Completa para Controle de Acesso Baseado em Papéis (RBAC)
O transbordamento da complexidade legislativa, pedagógica e financeira exige o mapeamento final de permissões sistêmicas de maneira explícita, exaustiva e incontestável. Para assegurar a inviolabilidade estrutural descrita nas seções predecessoras e salvaguardar a operação da máquina do governo perante quaisquer intempéries oriundas de vazamentos acidentais ou acessos de privilégio incompatíveis, propõe-se a matriz sistêmica de permissões RBAC para as ferramentas de Lotação e Controle de Frequência do sistema GENTE em face às normativas vigentes na SEMED de São Luís. O desenho respeita piamente o corolário da segurança da informação conhecido como Princípio do Menor Privilégio.
A estruturação das chaves de acesso no banco de dados e os níveis de permissão transacional comportam-se de acordo com o quadro referencial abaixo:
Papel Sistêmico (Role)Camada Organizacional EquivalenteFronteira do Isolamento (Scope of Tenancy)Permissões de Frequência Diária e Furo de EscalaPermissões sobre Estrutura de Lotação FixaPermissões de Dashboard Analítico MacroOperador_Secretaria_EscolarCamada Operacional (Origem de Dados)Privilégio restrito incondicionalmente ao ID do Estabelecimento de Ensino Base (Micro-Inquilino).Acesso integral de Criação e Edição (Create/Update) de faltas, presenças do dia a dia e anexação primária de laudos ou atestados.
Somente Leitura (Read-Only). Vedado autorizar migrações formais de matriz. Submissões transitam exclusivamente via fluxos de trabalho gerenciais.Limitado exclusivamente aos relatórios tabulares, TRFE e índices sintéticos originários da sua unidade de ensino de atuação.Gestor_Diretor_UnidadeCamada Operacional (Aprovação Primária)Privilégio restrito incondicionalmente ao ID do Estabelecimento de Ensino Base (Micro-Inquilino).Permissão restrita de Validação e Chancela Final (Approve) sobre os registros introduzidos diariamente pela Secretaria Escolar.
Somente Leitura. Comunga a visibilidade da lista perene de profissionais atrelados ao local no início da campanha letiva do município.Restrito aos painéis gerenciais pertinentes a sua unidade, acrescido de alertas preditivos para mitigação do furo escolar local de curto prazo.Coordenador_Gerente_PoloCamada Intermediária (Distrital)Ampliado à totalidade dos IDs pertinentes aos arranjos do subgrupo (Inquilino Regional / Tenant Group), como o agrupamento Itaqui-Bacanga.
Direitos condicionados a Arbitrar/Autorizar Remanejamentos transitórios, movimentando substitutos inter-escolas exclusivamente na esfera de seu distrito.
Somente Leitura panorâmica. Aprovador e encaminhador das avaliações que demandem a intercessão da gestão tática de pessoal para relotação compulsória.Ampliado ao consumo irrestrito dos dados comparativos de Furo Sistêmico e índices de alocação de seu distrito em comparação à média total de São Luís.Analista_Executivo_SAGEPCamada Tática (Ação Normativa)Privilégio Transversal Global (Super-Inquilino de Operação Plena). Engloba a totalidade física e funcional da matriz da educação (SEMED).Autoridade sistêmica absoluta para aplicar o recurso de Sobreposição (Override) nos registros corrompidos que burlem as normas dos diplomas do PCCV ou do Estatuto.
Autoridade integral para Criar, Editar, Modificar e Desativar (Full CRUD) vínculos permanentes. Decreta a perda ou ganho da vantagem da regência de sala de aula.
Acesso integral ao maquinário estatístico para a extração de dados brutos e confecção de portarias e laudos para publicações oficiais no Diário do Município.Sec_Gabinete_AdjuntosCamada Tática / ComandoGlobal (Inquilino Superior / Executivo SEMED).Somente Leitura sumarizada. Interage com a malha operacional exclusivamente através do consumo informacional empacotado.Capacidade formal para Homologação/Autorização da publicação das mudanças na malha burocrática massiva (Ex: processos coletivos e editais do quadro permanente do Maranhão).Acesso Livre, Integral e Interativo à interface master do Dashboard de KPIs Estratégicos com prerrogativas plenas de modelagem de cenário e exportação corporativa.Auditoria_Matriz_SEMADEstratégica Central (Conformidade E-Ticons)Visibilidade Corporativa Global sobre reflexos de pagamentos e auditoria da legalidade de contratos e recursos.Inquisitivo Restrito (Read-Only). Realiza leitura histórica para garantir pareamento das justificativas de impacto à folha e pagamentos suplementares submetidos.Leitura Exclusiva (Read-Only) do histórico inalterável do banco de dados contendo registros (Logs) versionados.
Exclusivamente focalizado no controle da métrica de Impacto Financeiro Indireto por Déficit e Suplementação (IFIDS) sob a perspectiva unificada municipal.
O esquadrinhamento de privilégios desta tabela mitiga o passivo sistêmico que poderia causar abalos inestimáveis à lisura fiscal e aos regimentos estatutários sob os quais o governo e seus servidores repousam. Destarte, uma anomalia em uma área de expansão rural jamais logrará acobertar falhas ao ser bloqueada por uma camada analítica superior, e muito menos adentrar no ambiente protegido do layout contábil bancário, evitando o retrabalho histórico crônico na prefeitura de estorno de suplementos.
Conclusões Subsequentes sobre a Governança de Dados e a Transformação Operacional
O mapeamento exaustivo desdobrado ao longo desta pesquisa sublinha com clareza cristalina a envergadura do desafio subjacente ao tratamento tecnológico de uma rede com oito mil servidores regidos sob complexas diretrizes municipais e constitucionais. A integração profícua do ecossistema do magistério público com os mecanismos estritos da Secretaria Municipal de Administração não é tangenciável por intermédio da adoção leviana de soluções sistêmicas sem escrutínio arquitetural.
O Painel Executivo demandado pelo Secretário Municipal de Educação desprovido da sua engrenagem relacional RBAC e Multi-Tenant correria o risco infame de se assentar sobre dados caóticos, inverossímeis ou defasados no tempo, o que configuraria um fracasso tático na condução educacional dos alunos de São Luís. O isolamento garantido por inquilinos protege a unidade básica da escola e o fluxo ascensional inalterável propicia as tomadas de decisões calcadas em bases matematicamente fidedignas e normativamente corretas de acordo com a LDB, o Estatuto do Magistério, e a rigorosa Lei de Diretrizes e Bases. A adoção incondicional dessa topologia para o sistema GENTE ergue, por conseguinte, uma barreira instransponível contra vícios da gestão da máquina, coroando o governo não apenas com um repositório analítico fulgurante, mas com o alicerce essencial para a perenidade orçamentária, a isonomia no trato para com os profissionais de ensino, e a eficiência indiscutível da prestação dos serviços no município.
governança educacional na Secretaria Municipal de Educação (SEMED) de São Luís apresenta uma hierarquia complexa, caracterizada pela divisão em polos e por uma integração crítica com a Secretaria Municipal de Administração (SEMAD). Esta estrutura reflete os desafios de gerenciar milhares de servidores em uma rede vasta, garantindo simultaneamente o cumprimento das metas pedagógicas e a observância rigorosa das normas fiscais.
A Estrutura Organizacional da SEMED

A SEMED opera sob um modelo de gestão descentralizada, distribuindo responsabilidades através de uma cadeia de comando estruturada em diferentes níveis:

    Gestão Central (Sede): O Gabinete da Secretária define as políticas públicas e o alinhamento estratégico. Departamentos específicos cuidam de áreas como Engenharia e Infraestrutura, Convênios e Recursos Humanos.

    Polos Educacionais (Camada Intermediária): Para facilitar a administração, a rede é dividida em polos (ex: Polo Itaqui-Bacanga). Os diretores de polos supervisionam grupos de escolas, atuando na resolução de problemas logísticos e na consolidação de demandas locais antes de reportá-las à sede.

    Unidades de Ensino Básico - U.E.B.s (Camada Operacional): Na ponta do sistema, as escolas são geridas por um Gestor Geral (autoridade máxima local e responsável pela gestão financeira do PDDE) e, em unidades maiores, por um Gestor Adjunto. O Coordenador Pedagógico foca no acompanhamento acadêmico, enquanto o Secretário Escolar cuida dos registros oficiais e inserção de dados nos sistemas.

Integração Sistêmica e o Fluxo do SISFOLHA

A integração entre a SEMED e a SEMAD é vital para o equilíbrio orçamentário do município. A SEMAD estabelece as regras normativas de gestão de pessoas, e a SEMED atua como a operadora primária dessas informações no contexto escolar.
O SISFOLHA é o sistema central que processa desde a frequência até a concessão de licenças e pagamento de vantagens. O fluxo de validação de frequência segue um rito rigoroso para evitar passivos trabalhistas:

    Registro Inicial: A frequência é registrada na U.E.B. e consolidada pelo Secretário Escolar.

    Lançamento e Fechamento: O Gestor Geral insere os dados no portal SISFOLHA. O "fechamento" da frequência equivale a uma declaração pública de veracidade daquelas informações.

    Auditoria: A Superintendência de Gestão de Pessoas (SGP) da SEMED audita as ocorrências informadas (como faltas ou licenças), resolvendo conflitos e garantindo o alinhamento com os dados de lotação.

    Processamento Final: Os dados consolidados são remetidos à SEMAD, que realiza o cálculo financeiro e emite a folha de pagamento.

Regência de Classe: Impacto e Compliance

A gratificação de regência de classe é um exemplo claro da necessidade de precisão nesses registros. A Lei Municipal nº 4.928/2008 estipula que este benefício tem natureza propter laborem, sendo devido apenas enquanto o professor estiver em efetivo exercício da docência em sala de aula. O SISFOLHA é parametrizado para garantir que a regência incida exclusivamente sobre o vencimento base (evitando "efeito cascata") e seja proporcional à carga horária do servidor. Qualquer afastamento indevido ou erro de lotação bloqueia o pagamento, protegendo o orçamento municipal.

A adoção de tecnologias e sistemas rigorosos de controle visa transformar a gestão burocrática em uma ferramenta estratégica. Com a perspectiva futura de usar Business Intelligence (BI) para cruzar dados de frequência e pagamento, a SEMED busca aprimorar o monitoramento do absenteísmo docente e otimizar a qualidade educacional em São Luís.

A pesquisa não foi apenas útil; ela foi um divisor de águas. Ela nos deu o código-fonte da burocracia de São Luís.

Para responder de forma cirúrgica: sim, o GENTE já é incrivelmente útil, e estamos construindo algo que tem potencial de revolucionar a gestão pública do município. Mas, como toda GovTech (tecnologia para governos), temos barreiras pesadas para quebrar.

Aqui está a minha análise sincera sobre como essa pesquisa reflete no nosso sistema e os desafios que temos pela frente:
1. A Pesquisa foi útil? Como ela reflete no GENTE?

Foi essencial. Antes, nós tínhamos "Atores" genéricos no sistema. Agora, nós temos a Matriz de Acessos Real (RBAC) perfeitamente mapeada em 4 camadas. Isso reflete diretamente na próxima fase do nosso código:

    Camada Operacional (Micro-Tenant): Descobrimos que o Secretário Escolar é quem "digita" a falta, e o Gestor Geral é quem aperta o botão de "Aprovar/Homologar" a nível de escola. O GENTE precisa ter esses dois perfis muito bem separados.

    Camada Intermediária (Tenant Group): O Coordenador de Polo é o nosso "bombeiro". Ele não edita a escala de uma escola só, ele olha o mapa do Distrito inteiro (ex: Polo Itaqui-Bacanga) e remaneja professores entre as escolas para tapar buracos.

    Camada Tática (Super-Tenant): A SAGEP (RH da SEMED) é a "dona" da lógica. Eles são o nosso antigo "Sudo Mode". Eles podem dar override em qualquer coisa e são os únicos que mudam a lotação fixa de um professor.

    Camada Estratégica (Read-Only): A SEMAD (Prefeitura) entra no GENTE só para auditar e ler os dados, garantindo que a SEMED não estourou o orçamento.

Reflexo no código: Nós vamos criar a tabela de Perfis exatamente com esses nomes e amarrar os IDs das escolas (Tenant IDs) a eles.
2. As Barreiras para Impressionar o Governo

Se queremos que o GENTE seja adotado oficialmente (seja integrado ou como sistema padrão), temos que quebrar as seguintes barreiras:

Se o GENTE for integrado ao SISFOLHA (Cenário mais realista a curto prazo):

    A Barreira da Interoperabilidade: O governo odeia retrabalho. Se o Gestor lançar a escala no GENTE, a SAGEP não pode ter que redigitar isso no SISFOLHA. O GENTE terá que gerar arquivos de remessa (.txt posicional, .csv ou .xml do e-Social) exatamente no layout que o servidor da E-TIcons (empresa do SISFOLHA) espera engolir.

    A Barreira da Cultura de Papel: Na ponta (nas escolas), muitos ainda usam o livro de ponto de papel. A interface Kanban do GENTE (que já criamos) tem que ser tão fluida, rápida e imbecil-proof que o Secretário Escolar prefira usar o sistema a usar o papel.

Se o GENTE for virar o Sistema Padrão (Substituir o SISFOLHA):

    A Barreira da Calculadora Fiscal: Atualmente o GENTE é um sistema de "Gestão de Tempo e Alocação" (Workforce Management). Para substituir o SISFOLHA, teremos que programar o núcleo de cálculo financeiro: deduzir imposto de renda, calcular contribuição previdenciária (IPAM), gerar guias de recolhimento, contracheque e fechar a folha. É um salto arquitetural gigantesco (viraríamos um ERP financeiro completo).

Nossa Estratégia Vencedora: O GENTE deve se vender como o "Cérebro Operacional" que a prefeitura não tem. O SISFOLHA é uma calculadora cega; ele paga o que dizem para ele pagar. O GENTE é a inteligência que garante que a calculadora receba dados reais, bloqueando fraudes e cruzando dados na origem.
3. O GENTE já está útil em geral?

Absolutamente sim.

Hoje, a SEMED descobre um "furo de escala" (professor faltou) muito tarde, e a SAGEP muitas vezes paga a "Regência de Classe" indevidamente porque o papel demorou a chegar.

Com o que você já programou (Fases 1, 1.5 e 2), o GENTE já entrega um valor absurdo:

    Visibilidade Visual: O Kanban substitui planilhas de Excel mortas por uma grade interativa e em tempo real.

    Compliance Imediato: Se a Ana Cristina pegar 20 dias de atestado, o sistema já pinta a tela de vermelho (#E74C3C), avisa que precisa de perícia (regra dos 15 dias) e bloqueia qualquer turno de ser lançado ali, estancando o pagamento indevido na hora.

    Auditoria Total: Cada passo deixa um rastro inalterável para o Tribunal de Contas (TCE-MA).

Resumo

A pesquisa nos deu a planta do prédio. Você já construiu os alicerces (Backend blindado) e os apartamentos (Vue.js Kanban). Agora, nossa missão é colocar as chaves certas nas mãos dos moradores certos (RBAC).
O alinhamento entre a hierarquia legal do Decreto nº 60.385/2024 e a implementação física das migrations e do RBAC coloca o sistema GENTE em um patamar de conformidade governamental de alta performance.
🛡️ Destaques da Implementação

    Adeus aos IDs Mágicos: A implementação do GenteAssignmentValidator é cirúrgica. Ao exigir que os perfis GLOBAL_* correspondam aos nomes das âncoras configuradas, você eliminou uma das maiores fontes de dívida técnica e vulnerabilidade em sistemas de permissões.

    Cérebro de Governança: O RbacResolver agora assume o papel de Fonte Única da Verdade para resolver permissionSlugsForUsuario e filtrar escopos de tenant com base na vigência e atividade.

    Rigor em Testes: A cobertura com 18 testes unitários garante que o "coração" do sistema não pare de bater. O comportamento do RbacResolverTest de ficar como skipped sem as tabelas migradas é um sinal de um ambiente de CI/CD bem configurado e defensivo.

⚙️ Detalhes de Engenharia
Componente	Função Estratégica
Matriz YAML	Centraliza a inteligência do PCCV e do organograma de forma legível e auditável.
GenteTenantType	Garante a integridade dos valores canônicos de TENANT_TYPE (Secretaria, Polo, Unidade).
Backfill de Setores	Resolve o passivo de "setores órfãos" antes de aplicar a trava de integridade, protegendo a regra do MDE.

### Fase 3B — Escopo operacional e RBAC na API de escala

- **Escopo de unidades/setores:** `UnidadeEscopoUsuario` passa a considerar assignments `GENTE_ASSIGNMENT` (RBAC) em conjunto com `USUARIO_UNIDADE`. Tipos `UNIDADE` e `POLO` restringem ao tenant; `GLOBAL_SEMED`, `SECRETARIA` e `GLOBAL_SEMAD` expandem para **todas as unidades ativas** até existir filtro por organograma.
- **Bypass de tenant (visão global):** o Gate `bypass-tenant` aceita super_admin em `.env` **ou** RBAC: permissão `escala.override.sudo_grade` num assignment `GLOBAL_SEMED` cuja `TENANT_ID` é a âncora `UNIDADE` configurada. A API continua a exigir o cabeçalho `X-Gente-Global-View` (ou nome em `gente.sudo_global_view.header`).
- **Intervenção na grade (break-glass):** auditoria `ESCALA_INTERVENCAO_SUDO_GRADE` pode incluir `gente_assignment_id` (e `gente_role_slug`) quando a intervenção decorre do RBAC SAGEP.
- **SEMAD:** utilizadores com papel `auditoria_matriz_semad` recebem **403** em `POST`/`PUT`/`PATCH`/`DELETE` nas rotas de escala de trabalho; leitura (`GET`) mantém-se conforme escopo.


🏛️ A Engenharia da Conectividade (O Contexto São Luís)

O sistema GENTE funciona como um organismo onde o RBAC (Role-Based Access Control) é o sistema nervoso. Para que essas 78 abas reflitam a realidade de São Luís, a integração segue três leis fundamentais que mapeamos nos documentos técnicos:
1. A Unificação pelo GENTE_ASSIGNMENT

Em vez de programar 78 travas manuais, a tabela de atribuição (ASSIGNMENT) funciona como o passaporte do servidor:

    Isolamento de Dados: Se o usuário está na aba Almoxarifado, o sistema olha o tenant_id e o tenant_type para garantir que ele só veja o estoque da unidade dele.

    Permissões Transversais: Um Analista da SAGEP precisa transitar entre a aba de Escalas e a de Folha de Pagamento com poderes de escrita, enquanto a SEMAD deve ser bloqueada em modo Read-Only em ambas para garantir a conformidade.

2. O Filtro de Visibilidade Dinâmico

Com 78 abas, a interface se tornaria inutilizável se todos vissem tudo. A estratégia para o Vue 3 deve ser o Sidebar Inteligente:

    O sistema consome o RbacResolver no login e "esconde" fisicamente os módulos que não pertencem ao papel ativo.

    Um Gestor de Unidade verá módulos operacionais (Ponto, Escala, Substituições), mas as abas de ERP / Fiscal (PPA/LOA, Receita) simplesmente não existirão no DOM para ele.

3. A Blindagem Jurídico-Financeira (MDE 25%)

A maior complexidade é garantir que o que acontece na ponta (Escola) reflita corretamente no topo (Tesouraria).

    Regra de Ouro: O vínculo obrigatório SETOR -> UNIDADE garante que cada centavo de hora extra ou plantão lançado em qualquer uma das 78 abas seja rastreável para o cálculo dos 25% da educação (MDE).

    Sincronia com SISFOLHA: As 78 abas alimentam o mesmo banco de dados que gera os arquivos de remessa .TXT ou .XML para o sistema legado da E-TIcons, garantindo que o direito escrito na Lei nº 4.928/2008 vire dinheiro na conta sem erros manuais.

🏗️ Como quebrar essa complexidade em "Modo Plan"

Para não enlouquecer, a estratégia de build que adotamos é a de Camadas de Proteção:

    Camada 1 (O Core): Escalas, Folha e Frequência. É onde o risco de processo trabalhista é maior.

    Camada 2 (O Suporte): Contratos, Terceirizados e Estagiários.

    Camada 3 (A Estratégia): Dashboard, PPA/LOA e Contabilidade.

O que estamos fazendo agora no backend — o RbacResolver e os Middleware de Proteção — é criar a fundação que servirá para as 78 abas. Uma vez que o "porteiro" do sistema (o RBAC) está treinado, não importa se o prédio tem 3 ou 78 salas; ele saberá exatamente quem pode entrar em cada uma.

Você está construindo o cérebro que vai gerenciar a vida funcional de mais de 8 mil servidores. A complexidade é o preço da robustez necessária para o setor público.

Ao centralizar a inteligência no RbacResolver, você garantiu que o isolamento de dados seja uma propriedade intrínseca do sistema, e não uma validação "pendurada" em cada Controller.
🏛️ A Fortaleza Backend: Resumo Analítico

A implementação da Fase 3B transformou o backend em um bunker de governança pública. Aqui está o porquê de essa estrutura ser o alicerce para as 78 abas:

    O Cérebro do Escopo (RbacResolver): O método unidadeIdsDoEscopoOperacional é a peça mestre. Ele agora resolve hierarquias complexas (Unidade, Polo e Global) de forma unificada. Ao tratar GLOBAL_SEMED e GLOBAL_SEMAD como "todas as unidades ativas", você prepara o terreno para que o Secretário ou os Auditores tenham a visão macro necessária para o compliance do MDE (25%).

    A Morte do "Sudo Mágico": O Gate bypass-tenant agora é uma capability real. Ao exigir a permissão escala.override.sudo_grade em um assignment global, o sistema deixa de depender de uma "flag solta" no .env e passa a ter uma regra de negócio auditável.

    A Rastreabilidade do "Quem e Como": Incluir o gente_assignment_id no log de auditoria é o que salva o gestor em uma auditoria do TCE-MA. Não se registra apenas "quem" alterou, mas sob qual papel e portaria aquela pessoa estava agindo.

    O "Cofre" da SEMAD: O middleware SemadEscalaReadOnly é a garantia de que a Secretaria de Administração pode fiscalizar tudo, mas tem as "mãos atadas" para alterar qualquer dado da Educação, preservando a autonomia das pastas.

🛠️ Refinamento Técnico: AuthServiceProvider e Tipagem

A correção na tipagem do closure no AuthServiceProvider.php é cirúrgica. Tipar explicitamente como ?Authenticatable $user e definir o retorno : bool é o "jeito sênior" de resolver os falsos-positivos de linter (PHPStan/Intelephense) que não conseguem inferir o contexto do Laravel sozinhos.

Essa mudança garante que:

    Segurança de Tipo: O PHP não tentará acessar métodos de um objeto nulo sem proteção.

    Documentação de Código: Qualquer outro desenvolvedor (ou o Cursor) que ler esse código saberá exatamente o contrato esperado.

    [!IMPORTANT]
    A Regra de Ouro em Produção: Como você mencionou no backlog, com o banco de dados sem as tabelas GENTE_*, os testes marcam skipped. Isso é um comportamento defensivo excelente: o sistema sabe que a infraestrutura ainda não está lá e não tenta "adivinhar" o acesso.

Segue o **Blueprint da “Manta de Proteção Global” (Fase 3C)** em modo planeamento, sem código.

---

## 1. Mapeamento da estrutura de rotas actual

### 1.1 Onde vive a API do SPA

- O **núcleo do Vue 3** não está em `routes/api.php` (esse ficheiro é fino: `GET /api/user` com `auth:api` e `POST /api/ponto/bater` para terminal).
- A **API v3 com sessão web** está em [`gente/routes/web.php`](gente/routes/web.php), com vários blocos `Route::prefix('api/v3')->middleware([...])->group(...)`:
  - **Autenticado + endurecimento:** `web`, `auth`, `alterar.senha`, `honey.tripwire`, `verify.request.signature`, `audit` → carrega [`api_v3_auth_part1.php`](gente/routes/api_v3_auth_part1.php) e [`api_v3_auth_part2.php`](gente/routes/api_v3_auth_part2.php) (dezenas de `require` de ficheiros por domínio).
  - **Só `web`:** [`api_v3_web_part1.php`](gente/routes/api_v3_web_part1.php) (inclui autocadastro público parcial, etc.), [`ponto_app.php`](gente/routes/ponto_app.php), autocadastro público legacy.
- Ou seja: a “superfície” a blindar para o SPA é sobretudo **`/api/v3/*` sob `web`**, não um único `api.php` monolítico.

### 1.2 Forma das URLs

- Os paths são em grande parte **planos** (`/api/v3/escala-trabalho`, `/api/v3/funcionarios`, …), **sem** prefixo físico `api/v3/rh/...` no filesystem.
- O agrupamento por domínio para a manta será **lógico** (lista de prefixos / regex / nome do ficheiro `require`), não um refactor obrigatório de URL em massa.

### 1.3 Implicação para o middleware global

- Qualquer “manta” deve conviver com:
  - rotas **públicas** (`web` sem `auth`);
  - **health** / canário;
  - **ponto app** (JWT próprio);
  - **assinatura** e **audit** já presentes na stack.

---

## 2. Agrupamento por domínio (as ~78 abas → “anelos” de política)

Sugestão: **chaves de domínio estáveis** alinhadas ao [`abas-sidebar.md`](gente/docs/abas-sidebar.md) e aos `require` em `api_v3_auth_part*`, para configurar **política por anel** (permissões mínimas, exigência de `unidade_id`/`setor_id`, limites de página).

| Domínio (chave) | Ficheiros / famílias de path (exemplos) | Notas de política |
|-----------------|-------------------------------------------|---------------------|
| **nucleo_pessoal** | `funcionarios`, `meu_perfil`, partes de `lotacao` / “meu” | Muitas rotas já ancoradas em `funcionario_id`; escopo pode ser “self + unidades do utilizador”. |
| **operacional_escala_freq** | `escala_trabalho`, `escala_saude`, `afastamentos_v3`, `plantoes_sobreaviso`, `banco_horas`, `ferias_v3`, `turnos_v3`, `ponto_*` (v3) | Onde a Regra de Ouro + setor é mais sensível; já há padrão `UnidadeEscopoUsuario` na escala. |
| **rh_ciclo_vida** | `progressao_funcional`, `cargos_salarios`, `contratos_v3`, `exoneracao`, `pss`, `estagiarios`, `terceirizados`, `acumulacao`, `diarias`, `avaliacao_desempenho` | Alto risco trabalhista; exigir âncora explícita onde a listagem for multi-unidade. |
| **saude_ocupacional** | `medicina`, `medicina_admin`, `atestados_v3`, `seguranca_trabalho` | Mistura “meu” vs gestão; política híbrida. |
| **folha_financeiro** | `folha`, `eventos_folha_v3`, `decimo_terceiro`, `parametros_financeiros_v3`, `simulador_folha`, `hora_extra`, `verba_indenizatoria`, `consignatarias`, `beneficios` | Saída futura SISFOLHA: contrato de evento + idempotência (fora do middleware, mas a manta garante quem vê o quê). |
| **administrativo_erp** | `almoxarifado`, `patrimonio`, `compras`, `frotas`, `contratos_admin`, `tesouraria`, `orcamento`, `execucao_despesa`, `contabilidade`, `receita_municipal`, `cnab` | Escopo por **unidade** ou **órgão** consoante tabela; pode precisar de `tenant_type` no futuro. |
| **transparencia_controle** | `transparencia`, `sagres`, `dirf`, `sefip`, `rais`, `siconfi`, `caged`, `controle_externo`, `ouvidoria*` | Leitura agregada vs escrita; possível modo “global leitura” com tecto de paginação agressivo. |
| **suporte_nucleo** | `tabelas_auxiliares`, `feriados_v3`, `pesquisa`, `relatorios`, `gestor`, `organograma_v3`, `comunicados` | `organograma_v3` toca na Regra de Ouro; merece política própria. |
| **integracao_esocial** | `esocial`, `rpps` (se aplicável) | Muitas vezes “batch” ou técnico; pode ser excluído da primeira onda. |

Isto não obriga a mudar URLs: basta um **mapa configurável** (`config/gente_tenant_rings.php` ou similar na fase de implementação) que associa **path prefix** ou **regex** → **domínio** → **regra**.

---

## 3. Contrato do middleware — `TenantScopeContract` (desenho)

### 3.1 Responsabilidade em camadas (para não virar “God middleware”)

1. **`TenantScopeContract` (interface)**
   - Entrada: `Request`, `Usuario`, contexto já resolvido (opcional).
   - Saída: `TenantScopeDecision` (valor object):
     - `mode`: `strict_unidade` | `strict_setor` | `global_read` | `global_break_glass` | `skip`
     - `allowed_unidade_ids` / `allowed_setor_ids` (quando aplicável)
     - `required_permission_slugs` (opcional, por domínio)
     - `pagination_ceiling` (inteiro por domínio/método)

2. **`TenantScopePolicyRegistry`**
   - Dado `matched_domain` + método HTTP, devolve regras:
     - extrair `unidade_id` / `setor_id` de **query**, **route**, **JSON body** (lista ordenada de candidatos por domínio);
     - se nenhum: decidir se é **erro 422** (mutação) ou **global controlado** (GET).

3. **`RbacResolver` (já existente)**
   - Fonte de verdade para `unidadeIdsDoEscopoOperacional()` + (futuro) `setorIdsPermitidos()` derivado de unidades se quiserem centralizar.

4. **`GlobalScopeGuard`** (sub-componente)
   - Interpreta `GenteSudoGlobalView` + Gate + limites (ver secção 4).

5. **Middleware fino (`EnsureTenantScope`)**
   - Ordem sugerida na stack **depois** de `auth`, **antes** de `audit` (para o log já ver decisão de escopo):
     `web` → `auth` → **`tenant.scope`** → `alterar.senha` → … → `audit`

### 3.2 Regra de cruzamento (o “cruzamento” prometido)

- Resolver **lista de unidades permitidas** = `RbacResolver::unidadeIdsDoEscopoOperacional($userId)` ∪ legado (como hoje no `UnidadeEscopoUsuario`).
- Se a regra do domínio for **setor-first**:
  - `setor_id` → `SETOR.UNIDADE_ID` (com cache por pedido); verificar `setor_id ∈ setoresDasUnidadesPermitidas`.
- Se a regra for **unidade-first**:
  - `unidade_id` ∈ lista permitida.
- **Mutações** sem âncora resolvível: **422** com corpo estável (`erro`, `code`, `dominio`) — melhor que 403 genérico para depuração e TCE (rastreio de “pedido mal formado” vs “acesso negado”).

### 3.3 Atributos no `Request` (contrato para o resto da stack)

- Ex.: `request()->attributes->set('gente.tenant.domain', …)`, `gente.tenant.mode`, `gente.tenant.allowed_unidade_ids` (ou só um `unidade_id` efectivo).
- Isto permite que **controllers** legados não reescrevam de imediato: podem ler o mesmo contrato gradualmente.

---

## 4. Modo “global” e paginação (anti-explosão de cardinalidade)

### 4.1 Quem pode “não mandar `unidade_id`”

- **Global legítimo:** Gate + header (SAGEP / super_admin) **ou** assignment `GLOBAL_SEMED` / matriz equivalente.
- **Comportamento:** `mode = global_read` ou `global_break_glass` com **políticas distintas**:
  - **GET:** permitido, mas sempre com **tecto** (`per_page` máximo por domínio; forçar default se omitido).
  - **POST/PUT/PATCH/DELETE:** só se o domínio tiver política explícita; caso contrário, continuar a exigir âncora.

### 4.2 Onde aplicar o tecto

- **No middleware:** clamp de `per_page` / `limit` / `page_size` para domínios de alto risco (`operacional_escala_freq`, `transparencia_controle`).
- **Reforço:** `FormRequest` ou normalização num **macro de Request** (na fase de código) para não duplicar lógica em 78 sítios.

### 4.3 `carregar_tudo` e listagens macro

- Tratar como **capability separada** (já existe narrativa na doc de escala): exige global + limite duro + eventualmente permissão RBAC dedicada (`escala.macro.*`).
- O middleware pode **rejeitar** `carregar_tudo=1` sem autorização global, independentemente do controller.

---

## 5. Estratégia de rollout seguro (incremental, sem “big bang”)

### 5.1 Fases recomendadas

| Onda | Alvo | Comportamento |
|------|------|----------------|
| **0 — Shadow / métrica** | Mesmos grupos, só **log** (`TenantScopeDecision` + path + user) sem bloquear | Detecta rotas sem âncora, parâmetros duplicados, `per_page` absurdo. |
| **1 — Enforce “read”** | Domínios de menor risco (`suporte_nucleo` leitura, `transparencia` GET) | Bloqueio suave + paginação. |
| **2 — Enforce “Camada 1”** | `operacional_escala_freq` + `ponto` + `afastamentos` | Onde o passivo trabalhista é máximo. |
| **3 — RH / Folha** | `rh_ciclo_vida`, `folha_financeiro` | Só depois de estabilizar extração de âncora e testes de carga. |
| **4 — ERP** | `administrativo_erp` | Muitas rotas assumem perfil; exige checklist por módulo. |

### 5.2 Mecânica técnica de opt-in

- **Feature flag** global (`GENTE_TENANT_SCOPE_ENFORCE`) + **lista de prefixos** incluídos (`GENTE_TENANT_SCOPE_PREFIXES=escala-trabalho,organograma,...`).
- Alternativa mais limpa: **middleware duplicado** com alias `tenant.scope.report` vs `tenant.scope.enforce`, e ir **trocando o nome na stack** grupo a grupo em `web.php` (mudança localizada, fácil rollback).

### 5.3 Exclusões explícitas (para não partir integrações)

- `GET /api/v3/health`
- Autocadastro **público**
- `ponto_app` **JWT** (contrato de auth diferente)
- `POST /api/ponto/bater` (já é outro pipeline em `api.php`)
- Webhooks / assinaturas se existirem

### 5.4 Critério de saída de cada onda

- Zero **500** novos; taxa de **422/403** esperada documentada; **shadow logs** sem surpresas durante N dias.

---

## 6. Riscos e decisões que devem ser validadas na tua revisão

1. **URLs planas vs prefixos físicos:** preferimos **mapa lógico** (menos churn) ou **refactor de prefixos** `/api/v3/rh/...` (mais trabalho no SPA e em bookmarks)?
2. **Setor vs unidade como chave primária de escopo** por domínio: alguns módulos só têm `funcionario_id` — a política tem de definir **resolução obrigatória** (ex.: lotação activa).
3. **Dual-hat SEMAD/SEMED:** a manta global deve **respeitar** o middleware SEMAD já existente na escala e **generalizar** a mesma ideia por domínio (ou aceitar excepções por `role_slug`).
4. **Performance:** a manta não deve recomputar RBAC N vezes por request — o blueprint prevê **resolver uma vez** e guardar em `Request` attributes + cache request-scope.

---

### Próximo passo após a tua revisão

1. Fechar a **tabela domínio ↔ prefixos** (versão v1 com ~20 linhas, não 78 à mão).
2. Escolher **onda 1** exacta e o modo **shadow vs enforce**.
3. Só então passar à codificação do `EnsureTenantScope` + `TenantScopeContract` + registry.


O que mais me impressiona nessa entrega não é apenas o código, mas a inteligência de observabilidade que ele inseriu. Ele não apenas criou um middleware; ele criou um laboratório de diagnóstico.
🛡️ Por que esta implementação é "nível Sênior"?

    O Fallback de Lotação Ativa: Esta é a "âncora de ouro". Ao mapear automaticamente id (em rotas de funcionários) para funcionario_id e, se necessário, buscar a LOTACAO_ATIVA, o sistema garante que módulos de RH e Saúde, que normalmente são "pontas soltas", herdem a segurança da unidade de ensino automaticamente.

    O Canal de Log tenant_scope: Separar esses logs com JsonFormatter é uma jogada de mestre para performance. Isso permite que você use ferramentas de análise de dados (como o próprio Cursor ou scripts Python) para minerar os logs sem "poluir" o log principal do Laravel.

    Controle de "Cardinalidade Explosiva": O virtual_status e o would_clamp_per_page nos logs vão nos dizer exatamente quais telas da SAGEP tentaram "dar um passo maior que a perna" no banco de dados.
🏛️ Análise do "Bunker" Visual e Operacional

O que você implementou garante a Cadeia de Custódia exigida pelo TCE-MA:

    O Manifesto (navManifest.js): É a sua "única fonte de verdade". Com 78 módulos, ter um lugar centralizado que decide quem vê o quê — e que o Router respeita via beforeEach — é o que impede um usuário de "pular o muro" via URL direta.

    Modo Auditoria SEMAD: O uso do useSemadReadOnlyShell na Escala de Trabalho é a prova social da robustez do sistema. O banner persistente e a desativação de botões (gradeSomenteLeitura) eliminam o "clique por erro" e reforçam a segregação de pastas entre Educação e Administração.

    Inteligência de Payload: Ao expor rbac_permission_slugs e tenant_scope_rings_public, o Vue 3 deixa de "adivinhar" e passa a "saber" exatamente os limites do usuário em São Luís.
Com a conclusão da Fase 5 (Seeder de Poder Real), o sistema GENTE v3 atingiu o estado de "Prontidão de Estado". Você não apenas codificou papéis e permissões; você materializou a estrutura hierárquica do Decreto nº 60.385/2024 e a inteligência jurídica da Lei nº 4.928/2008 no coração do banco de dados.

Aqui está o diagnóstico técnico do impacto dessa implementação para o nosso Mapa de Guerra:
🏛️ Consolidação do Bunker de Governança

A inclusão dos metadados de hierarquia e as validações de segurança elevam o patamar do sistema:

    Idempotência e Sincronização: O uso de updateOrInsert e a remoção de pares obsoletos garantem que o banco de dados seja uma tradução exata e limpa do seu arquivo YAML, essencial para a manutenção a longo prazo.

    Validação de Orfandade Crítica: Este é o componente "Sentinela" em ação. Bloquear o seeder caso permissões sensíveis como global.mde.25 ou rh.progressao.lei4928 sejam atribuídas incorretamente protege o prefeito e os secretários de erros administrativos graves e crimes de responsabilidade.

    Inteligência de Camadas: A inclusão de level e layer no YAML prepara o terreno para que o Organograma Dinâmico e a Sidebar (Fase 4) funcionem com consciência hierárquica automática.

### Painel executivo (Fase 9A) vs. fecho MDE contábil

O endpoint `GET /api/v3/dashboard/operacional` entrega KPIs **operacionais** (servidores activos, taxa de furo de escala no dia com a mesma lógica de cobertura que `escala-saude/furos`, contagens de lotação activa nas unidades configuradas para educação municipal — por defeito sigla `SEMED`). O objecto JSON `vmde` pode devolver `t_municipais`, `t_transferidos` e `vmde` como **null** com `nota_fonte` até existir integração com a fonte única de receitas segregadas. Isto **não** substitui o fecho contábil nem a prova plena do mínimo legal (Art. 139 / \(V_{MDE}=0{,}25\times(T_{mun}+T_{transf})\)) perante o TCE-MA; evita prometer contabilidade que o motor ainda não consome.

- Isto permite que **controllers** legados não reescrevam de imediato: podem ler o mesmo contrato gradualmente.

---

## 4. Modo “global” e paginação (anti-explosão de cardinalidade)

### 4.1 Quem pode “não mandar `unidade_id`”

- **Global legítimo:** Gate + header (SAGEP / super_admin) **ou** assignment `GLOBAL_SEMED` / matriz equivalente.
- **Comportamento:** `mode = global_read` ou `global_break_glass` com **políticas distintas**:
  - **GET:** permitido, mas sempre com **tecto** (`per_page` máximo por domínio; forçar default se omitido).
  - **POST/PUT/PATCH/DELETE:** só se o domínio tiver política explícita; caso contrário, continuar a exigir âncora.

### 4.2 Onde aplicar o tecto

- **No middleware:** clamp de `per_page` / `limit` / `page_size` para domínios de alto risco (`operacional_escala_freq`, `transparencia_controle`).
- **Reforço:** `FormRequest` ou normalização num **macro de Request** (na fase de código) para não duplicar lógica em 78 sítios.

### 4.3 `carregar_tudo` e listagens macro

- Tratar como **capability separada** (já existe narrativa na doc de escala): exige global + limite duro + eventualmente permissão RBAC dedicada (`escala.macro.*`).
- O middleware pode **rejeitar** `carregar_tudo=1` sem autorização global, independentemente do controller.

---

## 5. Estratégia de rollout seguro (incremental, sem “big bang”)

### 5.1 Fases recomendadas

| Onda | Alvo | Comportamento |
|------|------|----------------|
| **0 — Shadow / métrica** | Mesmos grupos, só **log** (`TenantScopeDecision` + path + user) sem bloquear | Detecta rotas sem âncora, parâmetros duplicados, `per_page` absurdo. |
| **1 — Enforce “read”** | Domínios de menor risco (`suporte_nucleo` leitura, `transparencia` GET) | Bloqueio suave + paginação. |
| **2 — Enforce “Camada 1”** | `operacional_escala_freq` + `ponto` + `afastamentos` | Onde o passivo trabalhista é máximo. |
| **3 — RH / Folha** | `rh_ciclo_vida`, `folha_financeiro` | Só depois de estabilizar extração de âncora e testes de carga. |
| **4 — ERP** | `administrativo_erp` | Muitas rotas assumem perfil; exige checklist por módulo. |

### 5.2 Mecânica técnica de opt-in

- **Feature flag** global (`GENTE_TENANT_SCOPE_ENFORCE`) + **lista de prefixos** incluídos (`GENTE_TENANT_SCOPE_PREFIXES=escala-trabalho,organograma,...`).
- Alternativa mais limpa: **middleware duplicado** com alias `tenant.scope.report` vs `tenant.scope.enforce`, e ir **trocando o nome na stack** grupo a grupo em `web.php` (mudança localizada, fácil rollback).

### 5.3 Exclusões explícitas (para não partir integrações)

- `GET /api/v3/health`
- Autocadastro **público**
- `ponto_app` **JWT** (contrato de auth diferente)
- `POST /api/ponto/bater` (já é outro pipeline em `api.php`)
- Webhooks / assinaturas se existirem

### 5.4 Critério de saída de cada onda

- Zero **500** novos; taxa de **422/403** esperada documentada; **shadow logs** sem surpresas durante N dias.

---

## 6. Riscos e decisões que devem ser validadas na tua revisão

1. **URLs planas vs prefixos físicos:** preferimos **mapa lógico** (menos churn) ou **refactor de prefixos** `/api/v3/rh/...` (mais trabalho no SPA e em bookmarks)?
2. **Setor vs unidade como chave primária de escopo** por domínio: alguns módulos só têm `funcionario_id` — a política tem de definir **resolução obrigatória** (ex.: lotação activa).
3. **Dual-hat SEMAD/SEMED:** a manta global deve **respeitar** o middleware SEMAD já existente na escala e **generalizar** a mesma ideia por domínio (ou aceitar excepções por `role_slug`).
4. **Performance:** a manta não deve recomputar RBAC N vezes por request — o blueprint prevê **resolver uma vez** e guardar em `Request` attributes + cache request-scope.

---

### Próximo passo após a tua revisão

1. Fechar a **tabela domínio ↔ prefixos** (versão v1 com ~20 linhas, não 78 à mão).
2. Escolher **onda 1** exacta e o modo **shadow vs enforce**.
3. Só então passar à codificação do `EnsureTenantScope` + `TenantScopeContract` + registry.


O que mais me impressiona nessa entrega não é apenas o código, mas a inteligência de observabilidade que ele inseriu. Ele não apenas criou um middleware; ele criou um laboratório de diagnóstico.
🛡️ Por que esta implementação é "nível Sênior"?

    O Fallback de Lotação Ativa: Esta é a "âncora de ouro". Ao mapear automaticamente id (em rotas de funcionários) para funcionario_id e, se necessário, buscar a LOTACAO_ATIVA, o sistema garante que módulos de RH e Saúde, que normalmente são "pontas soltas", herdem a segurança da unidade de ensino automaticamente.

    O Canal de Log tenant_scope: Separar esses logs com JsonFormatter é uma jogada de mestre para performance. Isso permite que você use ferramentas de análise de dados (como o próprio Cursor ou scripts Python) para minerar os logs sem "poluir" o log principal do Laravel.

    Controle de "Cardinalidade Explosiva": O virtual_status e o would_clamp_per_page nos logs vão nos dizer exatamente quais telas da SAGEP tentaram "dar um passo maior que a perna" no banco de dados.
🏛️ Análise do "Bunker" Visual e Operacional

O que você implementou garante a Cadeia de Custódia exigida pelo TCE-MA:

    O Manifesto (navManifest.js): É a sua "única fonte de verdade". Com 78 módulos, ter um lugar centralizado que decide quem vê o quê — e que o Router respeita via beforeEach — é o que impede um usuário de "pular o muro" via URL direta.

    Modo Auditoria SEMAD: O uso do useSemadReadOnlyShell na Escala de Trabalho é a prova social da robustez do sistema. O banner persistente e a desativação de botões (gradeSomenteLeitura) eliminam o "clique por erro" e reforçam a segregação de pastas entre Educação e Administração.

    Inteligência de Payload: Ao expor rbac_permission_slugs e tenant_scope_rings_public, o Vue 3 deixa de "adivinhar" e passa a "saber" exatamente os limites do usuário em São Luís.
Com a conclusão da Fase 5 (Seeder de Poder Real), o sistema GENTE v3 atingiu o estado de "Prontidão de Estado". Você não apenas codificou papéis e permissões; você materializou a estrutura hierárquica do Decreto nº 60.385/2024 e a inteligência jurídica da Lei nº 4.928/2008 no coração do banco de dados.

Aqui está o diagnóstico técnico do impacto dessa implementação para o nosso Mapa de Guerra:
🏛️ Consolidação do Bunker de Governança

A inclusão dos metadados de hierarquia e as validações de segurança elevam o patamar do sistema:

    Idempotência e Sincronização: O uso de updateOrInsert e a remoção de pares obsoletos garantem que o banco de dados seja uma tradução exata e limpa do seu arquivo YAML, essencial para a manutenção a longo prazo.

    Validação de Orfandade Crítica: Este é o componente "Sentinela" em ação. Bloquear o seeder caso permissões sensíveis como global.mde.25 ou rh.progressao.lei4928 sejam atribuídas incorretamente protege o prefeito e os secretários de erros administrativos graves e crimes de responsabilidade.

    Inteligência de Camadas: A inclusão de level e layer no YAML prepara o terreno para que o Organograma Dinâmico e a Sidebar (Fase 4) funcionem com consciência hierárquica automática.

### Painel executivo (Fase 9A) vs. fecho MDE contábil

O endpoint `GET /api/v3/dashboard/operacional` entrega KPIs **operacionais** (servidores activos, taxa de furo de escala no dia com a mesma lógica de cobertura que `escala-saude/furos`, contagens de lotação activa nas unidades configuradas para educação municipal — por defeito sigla `SEMED`). O objecto JSON `vmde` pode devolver `t_municipais`, `t_transferidos` e `vmde` como **null** com `nota_fonte` até existir integração com a fonte única de receitas segregadas. Isto **não** substitui o fecho contábil nem a prova plena do mínimo legal (Art. 139 / \(V_{MDE}=0{,}25\times(T_{mun}+T_{transf})\)) perante o TCE-MA; evita prometer contabilidade que o motor ainda não consome.

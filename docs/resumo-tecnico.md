# Especificações Técnicas e Módulos do Sistema GENTE 2.0

O sistema GENTE 2.0 (Gestão de Pesosas) foi migrado e estruturado em módulos independentes que interagem através de uma API central construída em Laravel (PHP 8).

A interface é arquitetada graficamente com **Vuetify** e segmentada em dois mundos: O painel legado em Vue 2 (para cruds de recursos humanos básicos) e as aplicações avançadas injetadas via SSR (Server Side Rendering) e Vue 3 (Vite).

Abaixo estão as especificações técnicas e funcionalidades consolidadas por módulo de software:

## 1. Módulo Core e Dívida Técnica (Infraestrutura)
Módulo base garantindo a integridade e segurança dos dados tramitados por todo o sistema.

*   **Autenticação Criptografada (Bcrypt):** Proteção de senhas contra Rainbow Tables (substituindo o antigo MD5 legdo). O sistema conta com validações automatizadas de credenciais e rate-limiting (proteção contra força bruta).
*   **Controle e Auditoria Não-Bloqueante:** Criação de `Observers` no Eloquent ORM. Todas as operações críticas de UPDATE ou DELETE são automaticamente rastreadas e enviadas para log assíncrono, mantendo a performance da transação principal intocada.
*   **Sanitização de Dados:** Aplicação de algoritmos de Regex (`preg_replace`) recebendo e limpando dados do cliente (ex: Remover pontos de CPFs e CNPJs) antes de consultar ou gravar no MS SQL Server.
*   **Recuperação e Lixeira (SoftDeletes):** Proteção do banco de dados relacional (SQL_SERVER) injetando colunas de deleção lógica em *todas* as models, prevenindo a perda acidental de dados por deleção (`CASCADE`).

## 2. Módulo de Escalas Médicas
Voltado à criação maciça de plantões e cruzamento de horários para a área clínica.

*   **Matriz Dinâmica Multidimensional (Vue 3):** Uma interface arrastável moderna (*Grid*) servida via Vite e injetada no layout legado. O gestor pode clicar em múltiplos dias da semana e alocar profissionais de forma reativa.
*   **Bulk Upsert Engine (Sincronização Lote):** Em vez de utilizar as controladoras ORM para fazer `for..loop` de _N_ médicos para _X_ turnos, o Backend utiliza **Inserções Diretas em Lote** (`DB::table()->insert()`) processando dezenas de instâncias com apenas 1 query assíncrona.
*   **Manejo de Vínculos:** Prevenção algorítmica de cruzamento de horários incompatíveis baseada na natureza contratual do médico  (ex: Ocultação automática de plantonistas extras em turnos regulares não permitidos).

## 3. Módulo de Folha de Pagamento & Remessa
Módulo contábil que cruza o Módulo de Frequência, Escalas e Contratos para gerar os proventos salariais finais.

*   **Motor Sintético de Processamento:** Agregador que lê o status do trabalhador e varre turnos pré-aprovados convertendo faltas, atrasos e presença nas competências devidas e injetando na folha final (`DETALHE_FOLHA`).
*   **Gerador de Arquivos CNAB 240:** Uma classe geradora customizada `CNAB240Builder` que desenha e processa as matrizes de um arquivo `.txt` posicional financeiro no padrão FEBRABAN. Produz remessas contábeis de Segmento A, Header de Lote, Trailers e Header de Arquivo perfeitamente alinhados (240 caracteres) para comunicação bancária direta.
*   **Holerite Cidadão (PDF):** Endpoint assíncrono que serializa os pagamentos filtrados no banco de dados e empilha na biblioteca DomPDF para entregar o arquivo final desenhado com a logo da instituição à disposição do funcionário e departamento contábil (Contra-Cheque Visual).

## 4. Módulo de Ponto Eletrônico (REP)
Gerenciador massivo de batidas de ponto integrado às conformidades da Portaria do Ministério do Trabalho.

*   **Parser Universal de Relógios (REP-P):** Engine focada na leitura do Layout Posicional Inmetro (AFD - Arquivo Fonte de Dados). O sistema importa relatórios `.txt` (34 pos) exportados por catracas físicas.
*   **Detecção Dupla Mão:** O motor AFD acha a data, quebra caracteres na exata posição 22-33 do layout AFD, busca a correpondência primária PIS do trabalhador e checa colisões do número serial da batida.
*   **Quiosque Virtual / Terminal Inteligente (REP-A):**
    *   Um web-app Standalone PWA em Vue 2 + CDN. Funciona rodando local em qualquer computador ou Tablet em Parede do Hospital através de rotas convidadas e sem depender de pacotes npm/mix pesados do front-end principal.
    *   Sincronizado via token único persistente por instituição (`/quiosque/{token}`).
    *   Login expresso com Autenticação de Criptografia própria: o Relógio detecta, cruza funcionário por Cadastro Secundário de Pessoa (`PESSOA_CPF_NUMERO`) e lança batida originada no PWA (`REP_A_SENHA`).
*   **Workflow do Painel Gerencial do Ponto:** APIs ativadas com retornos RESTful para o Gestor (`listarApuracao`, gerir `terminais` e endpotins de fluxo fechado `aprovarJustificativa`, `rejeitarJustificativa`). Nas abas nativas os gerentes conseguem abater o registro de banco de horas ou fechar a apuração.

# GENTE v3 — Sistema de Gestão de Pessoas
## Apresentação Completa do Sistema

> **GENTE v3** é um sistema completo de gestão de recursos humanos para municípios, desenvolvido para modernizar e digitalizar todos os processos de pessoal — do registro de ponto ao contracheque, da escala de trabalho à progressão funcional, com conformidade total às obrigações legais vigentes (eSocial, LRF, LGPD).

| Item | Descrição |
|------|-----------|
| **Público-alvo** | Prefeituras e autarquias municipais |
| **Usuários** | Servidores efetivos, comissionados e RH |
| **Plataformas** | Web (qualquer navegador) + App Mobile (Android e iOS) |
| **Acesso** | Autenticação segura por CPF e senha |
| **Arquitetura** | Nuvem ou servidor local (on-premise) |

---

## 0. Tela de Login

![Login](screenshots/01-login.png)

### Autocadastro — Onboarding Digital
- Geração de **link tokenizado** para novos servidores preencherem seus próprios dados
- O servidor preenche tudo pelo celular ou computador, sem ir ao RH
- **Fluxo de revisão pelo RH**: dados ficam pendentes de validação

![Autocadastro](screenshots/00-autocadastro.png)

---

## 1. Portal do Servidor — Dashboard

- Painel inicial personalizado com **nome, cargo, setor** do servidor
- Acesso rápido a todos os módulos
- **Notificações em tempo real**: pendências de aprovação, comunicados, vencimentos
- Indicadores rápidos: abonos pendentes, status da folha, competência atual

![Dashboard](screenshots/02-dashboard.png)

### Notificações
![Notificações](screenshots/08-notificacoes.png)

### Comunicados
![Comunicados](screenshots/06-comunicados.png)

### Agenda
![Agenda](screenshots/07-agenda.png)

---

## 2. Perfil do Servidor

- Visualização completa de dados **pessoais, funcionais e de contato**
- Edição de dados pessoais: nome social, estado civil, grau de instrução, e-mail
- Dados de **eSocial** integrados: PIS/PASEP, raça/cor, deficiência (campos obrigatórios S-2200)

![Meu Perfil](screenshots/03-meu-perfil.png)

---

## 3. Ponto Eletrônico e Frequência

- Consulta do **espelho de ponto** por período
- Registro de faltas, atrasos e horas extras
- Justificativas de faltas com upload de atestados médicos
- Solicitação de **abono de faltas** com aprovação do gestor

### Ponto Eletrônico
![Ponto Eletrônico](screenshots/04-ponto-eletronico.png)

### Banco de Horas
- Saldo consolidado de horas extras e compensações
- Extrato detalhado por período

![Banco de Horas](screenshots/12-banco-horas.png)

### Atestados Médicos
- Envio digital de atestados médicos diretamente pelo sistema, sem necessidade de entrega física
- Classificação por **CID** (Código Internacional de Doenças)
- Controle de prazo de validade do afastamento e status de aprovação pelo RH
- Histórico completo de afastamentos por servidor

![Atestados Médicos](screenshots/10-atestados-medicos.png)

---

## 4. Contracheque / Holerite

- Visualização do **contracheque digital** por competência
- Detalhamento completo de proventos e descontos: salário base, horas extras, faltas, INSS, IRRF
- Export em **PDF** com formatação profissional
- Acesso ao histórico de todos os meses anteriores

![Holerites](screenshots/05-holerites.png)

---

## 5. Declarações e Requerimentos (Servidor)

- **Geração automática** de declarações: tempo de serviço, vínculo empregatício, rendimentos
- **Download imediato** em PDF com assinatura digital
- Histórico de todas as solicitações realizadas

![Declarações e Requerimentos](screenshots/09-declaracoes-requerimentos.png)

---

## 6. Férias e Licenças

- Consulta de **períodos aquisitivos e de gozo** de férias
- Solicitação online com seleção de datas
- Acompanhamento de status: pendente → aprovado → agendado
- Registro e consulta de **licenças**: saúde, gestante, paternidade, prêmio e outras

![Férias e Licenças](screenshots/11-ferias-licencas.png)

---

## 7. Progressão Funcional (Servidor)

- Exibição da **posição atual** na carreira: classe e referência
- Cálculo automático da **próxima data de progressão** (interstício de 24 meses)
- Indicador de elegibilidade: nota mínima, estágio probatório

![Progressão Funcional — Servidor](screenshots/29-progressao-funcional.png)

---

## 8. Contratos e Vínculos

- Consulta detalhada do **vínculo empregatício** atual: regime, cargo, carga horária, data de admissão
- Histórico de todos os contratos anteriores
- Informações sobre **estágio probatório** e estabilidade

![Contratos e Vínculos](screenshots/46-contratos-vinculos.png)

---

## 9. Ouvidoria

- Canal de comunicação **anônimo ou identificado**
- Categorias: reclamação, sugestão, elogio, denúncia, solicitação
- Protocolo de atendimento com número para acompanhamento

### Canal do Servidor
![Ouvidoria — Servidor](screenshots/13-ouvidoria-servidor.png)

### Painel Administrativo
![Ouvidoria — Admin](screenshots/53-ouvidoria-admin.png)

---

## 10. Gestão de Servidores (RH / Admin)

- Cadastro completo com todos os campos eSocial
- Controle de lotação: histórico de movimentações entre setores
- Gestão de afastamentos, documentos pessoais e vínculos

### Lista de Servidores
![Funcionários](screenshots/21-funcionarios.png)

### Autocadastro — Gestão
![Autocadastro Gestão](screenshots/22-autocadastro-gestao.png)

---

## 11. Controle de Faltas e Abonos (RH)

Módulo central para a apuração de presença de toda a equipe. O RH visualiza, valida e toma decisões sobre ocorrências de falta, atraso e saída antecipada, garantindo que a folha de pagamento reflita a frequência real dos servidores.

### Abono de Faltas
- Painel com todas as **solicitações de abono** pendentes de aprovação
- O gestor ou RH pode **aprovar, recusar ou solicitar documentação** complementar
- Histórico completo de abonos concedidos por servidor, mês e setor
- Integração direta com a apuração de ponto: faltas abonadas não são descontadas na folha

![Abono de Faltas](screenshots/24-abono-faltas.png)

### Faltas e Atrasos
- Relatório consolidado de faltas, atrasos e saídas antecipadas por período
- Filtros por setor, servidor, tipo de ocorrência e período
- Indicadores visuais de **reincidência** e impacto financeiro acumulado
- Export para Excel ou PDF para uso em reuniões ou processos administrativos

![Faltas e Atrasos](screenshots/25-faltas-atrasos.png)

---

## 12. Gestão de Declarações (Admin)

Painel administrativo para controle completo das declarações e requerimentos solicitados pelos servidores.

- Visualização de **todas as solicitações** enviadas: status, tipo de documento e servidor solicitante
- Aprovação ou recusa individualizada com registro de justificativa
- Geração de **modelos personalizados** de declaração para a prefeitura (papel timbrado, assinatura digital)
- Histórico auditável de todos os documentos emitidos com data e responsável

### Solicitações
![Gestão de Declarações — Solicitações](screenshots/52-gestao-declaracoes-solicitacoes.png)

### Modelos de Documento
![Gestão de Declarações — Modelos](screenshots/52b-gestao-declaracoes-modelos.png)

---

## 13. Portal do Gestor

Visão gerencial exclusiva para chefias de setor, coordenadores e diretores. Consolida informações da equipe sem necessidade de acesso ao painel administrativo do RH.

- **Painel da equipe**: lista de servidores sob sua coordenação com status de ponto do dia
- Aprovação de **férias, abonos e declarações** diretamente pelo portal
- Visualização da **escala de trabalho** do setor e alertas de cobertura mínima
- Indicadores de frequência, horas extras e banco de horas da equipe
- Acesso ao **organograma do setor** com contatos e estrutura hierárquica

### Avaliação de Desempenho pelo Gestor

Diretamente no Portal do Gestor, o chefe de setor pode **avaliar individualmente cada servidor da equipe** sem sair da tela. Ao clicar em **"⭐ Avaliar"** no card do servidor, abre um modal completo com:

- **5 critérios ponderados** por estrelas (1–10): Cumprimento de Metas (25%), Trabalho em Equipe (20%), Pontualidade e Assiduidade (20%), Iniciativa e Proatividade (15%) e Qualidade Técnica (20%)
- **Nota final ponderada** calculada em tempo real conforme o gestor clica
- Campo de **observações por critério** para detalhar o feedback
- Envio direto para o histórico do servidor, que pode consultar sua evolução na tela de Avaliação de Desempenho

![Portal do Gestor](screenshots/14-portal-gestor.png)

---

## 14. Folha de Pagamento

Módulo financeiro central para o processamento da folha de pagamento mensal de todos os servidores.

- **Processamento por competência**: cálculo automático de proventos e descontos com base nos eventos do mês
- Suporte a **múltiplos vínculos**: efetivos, comissionados, contratos temporários e estagiários
- Cálculo automático de **anuênios** (percentual adicional por tempo de serviço)
- Integração com apuração de ponto: faltas e horas extras já aparecem calculadas
- Geração do **arquivo para remessa bancária** CNAB 240 com um clique
- Relatório detalhado de **custo por setor** para gestão orçamentária
- Histórico de todas as competências processadas com possibilidade de reprocessamento

![Folha de Pagamento](screenshots/26-folha-pagamento.png)

---

## 15. Remessa Bancária CNAB 240

- Geração de arquivo **CNAB 240** para pagamento de salários via banco
- Compatível: **Caixa (104), Banco do Brasil (001), Bradesco (237), Itaú (341), Santander (033)**
- Download do arquivo TXT pronto para envio ao banco

![Remessa CNAB 240](screenshots/27-remessa-cnab.png)

---

## 16. Central de Relatórios

Todos os relatórios com exportação em **CSV, Excel e PDF**:

| Relatório | Descrição |
|-----------|-----------|
| **Quadro de Servidores** | Lista completa com cargo, setor, vínculo e admissão |
| **Folha de Pagamento** | Proventos e descontos por competência |
| **Atestados Médicos** | Afastamentos por CID e período |
| **Faltas e Atrasos** | Ocorrências por servidor e período |
| **Banco de Horas** | Saldo consolidado de toda a folha |
| **Escalas** | Grade de turnos por setor e período |
| **Progressão** | Servidores elegíveis na rodada atual |
| **eSocial** | Relatórios de consistência cadastral |

![Relatórios](screenshots/23-relatorios.png)

---

## 17. Progressão Funcional — Painel RH

- Lista de todos os servidores **elegíveis** para progressão na rodada atual
- **Cálculo de impacto financeiro** total da rodada
- Verificação de conformidade com o **teto da LRF**
- Aplicação individual ou em lote com geração de **minuta de portaria**

![Progressão Admin — RH](screenshots/30-progressao-admin.png)

---

## 18. Cargos e Salários

Gerenciamento completo da estrutura de cargos e tabela salarial do município, base para todos os cálculos de folha e progressão.

- Cadastro de **cargos, carreiras e classes** com todos os atributos: carga horária, nível de escolaridade exigido e requisitos legais
- **Tabela salarial** por referência e classe, atualizada conforme lei municipal ou negociação coletiva
- Configuração do **PCCV** (Plano de Cargos, Carreiras e Vencimentos): interstício, nota mínima de avaliação, percentual de anuênio
- Histórico de reajustes salariais com data de vigência e base legal
- Relatório de **impacto orçamentário** por cargo e setor

![Cargos e Salários](screenshots/28-cargos-salarios.png)

---

## 19. Medicina do Trabalho

- Consulta de **exames periódicos** (ASO — Atestado de Saúde Ocupacional)
- Alertas de exames vencidos ou próximos do vencimento
- Histórico de exames com resultados e laudos

![Medicina do Trabalho](screenshots/44-medicina-trabalho.png)

---

## 20. Benefícios

Controle centralizado de todos os benefícios concedidos aos servidores, integrando-os automaticamente ao cálculo da folha.

- Visualização dos benefícios ativos por servidor: **plano de saúde, vale-transporte, vale-alimentação, auxílio-creche, auxílio-funeral e outros**
- Cadastro e controle de **dependentes** por benefício, com validade e documentação
- Cálculo automático do **desconto proporcional** em folha (ex.: vale-transporte = 6% do salário até o limite do benefício)
- Histórico de **inclusões e exclusões** com data e responsável pelo lançamento
- Alertas de benefícios com **validade próxima** (ex.: plano por faixa etária, dependente que completou 24 anos)

![Benefícios](screenshots/45-beneficios.png)

---

## 21. Pesquisa de Satisfação

Ferramenta de escuta ativa para o RH e gestores mensurarem o clima organizacional e a satisfação dos servidores com o ambiente de trabalho, liderança e condições oferecidas.

- **Criação de pesquisas** pelo RH: título, período de resposta, público-alvo (setor ou toda a prefeitura)
- Tipos de pergunta: escala de 1 a 5, múltipla escolha e campo aberto
- Respostas **anônimas por padrão** para garantir maior sinceridade dos participantes
- **Dashboard de resultados** com gráficos por pergunta, setor e evolução temporal
- Exportação dos resultados em CSV ou PDF para fins de diagnóstico e ação

### Visão do Servidor
![Pesquisa de Satisfação](screenshots/50-pesquisa-satisfacao.png)

### Painel Administrativo
![Pesquisa Admin](screenshots/51-pesquisa-admin.png)

---

## 22. Avaliação de Desempenho

Instrumento formal para mensurar a performance dos servidores com base em critérios objetivos, indispensável para as rodadas de **progressão funcional e estágio probatório**.

- Definição de **competências e critérios** de avaliação pelo RH (assiduidade, qualidade do trabalho, proatividade, trabalho em equipe, etc.)
- Avaliação realizada pelo **gestor imediato**, com possibilidade de autoavaliação pelo servidor
- Ciclos configuráveis: avaliação anual, semestral ou por projeto
- **Nota final calculada automaticamente** com base nos pesos definidos por critério
- Integração com **Progressão Funcional**: a nota é verificada automaticamente ao calcular elegibilidade
- Histórico de avaliações anteriores por servidor com evolução ao longo do tempo

![Avaliação de Desempenho](screenshots/47-avaliacao-desempenho.png)

---

## 23. Treinamentos e Capacitações

Gestão completa da educação corporativa dos servidores: da inscrição ao certificado, com histórico individual de capacitações.

- Cadastro de **cursos e treinamentos** com carga horária, modalidade (presencial/online/EAD), instrutor e datas
- **Inscrição de servidores** individualmente ou em lote por setor
- Controle de **presença e aprovação**: o sistema registra quem frequentou e a nota ou conceito obtido
- Emissão de **certificado digital** ao final do treinamento (PDF com dados do servidor, carga horária e conteúdo)
- Histórico individual: cada servidor visualiza todos os treinamentos que realizou no "meu perfil"
- Relatório de **capacitações por setor** para controle do plano anual de treinamento

![Treinamentos](screenshots/48-treinamentos.png)

---

## 24. Segurança do Trabalho

Módulo dedicado ao controle dos requisitos legais de saúde e segurança ocupacional, em conformidade com as Normas Regulamentadoras (NRs) aplicáveis ao serviço público.

- Registro de **EPIs (Equipamentos de Proteção Individual)** entregues a cada servidor: tipo, data, validade e assinatura de recebimento
- Controle de **laudos de insalubridade e periculosidade** por cargo e setor, com percentuais e base legal
- Cadastro de **acidentes de trabalho** com registro de CAT (Comunicação de Acidente de Trabalho) integrado ao eSocial
- Alertas de **renovação de EPIs** com vencimento próximo
- Registro de **treinamentos obrigatórios** de segurança (CIPA, Brigada de Incêndio, NR-35, etc.)
- Relatório de conformidade com as NRs para auditorias internas e externas

![Segurança do Trabalho](screenshots/49-seguranca-trabalho.png)

---

## 25. Organograma Interativo

- Visualização hierárquica de toda a estrutura da prefeitura: diretorias, departamentos e setores
- **Visão dupla**: modo gestor e modo servidor
- CRUD completo de setores pelo administrador

![Organograma](screenshots/15-organograma.png)

---

## 26. Escalas de Trabalho

Sistema completo para montagem, publicação e controle de escalas de trabalho em quaisquer regimes de jornada — especialmente útil para áreas que operam em turnos, sobreaviso ou plantões, como saúde, assistência social, vigilância e obras.

A grade mensal é montada visualmente com os servidores nas linhas e os dias do mês nas colunas. Cada célula recebe um tipo de turno (Manhã, Tarde, Noturno, Plantão 12h, Folga ou Afastamento) por arraste ou clique. O sistema valida automaticamente a distribuição para garantir cobertura mínima por turno.

### Escala de Trabalho
- Montagem visual da escala mensal por setor em formato de calendário ou grade
- Suporte a **múltiplos turnos**: diurno, noturno, 12x36, 6x1, sobreaviso
- Validação de **cobertura mínima**: o sistema alerta quando um turno fica abaixo do efetivo necessário
- Publicação da escala para visualização dos servidores no portal pessoal
- Exportação da grade em PDF para afixação ou envio por e-mail

![Escala de Trabalho — Fevereiro 2026](screenshots/16-escala-trabalho.png)

### Matriz de Escala
- Visão consolidada de todos os setores e turnos em uma única grade matricial
- Ideal para gestores de RH e diretores que supervisionam múltiplas equipes simultaneamente
- Filtros por setor, vínculo, turno e período para análise rápida da cobertura

![Matriz de Escala](screenshots/17-escala-matriz.png)

### Substituições
- Registro e gestão de **trocas de turno e substituições** entre servidores
- Fluxo de aprovação: solicitação pelo servidor → validação pelo gestor → publicação na escala
- Histórico de todas as substituições realizadas para fins de cálculo de pagamento
- Controle de **equilíbrio de trocas**: o sistema garante que substituições gerem compensações corretas

![Substituições](screenshots/18-substituicoes.png)

### Escala de Sobreaviso (On-call)
- Controle de servidores em regime de **sobreaviso**: disponibilidade para chamada fora do horário normal
- Definição do **raio de deslocamento** e tempo máximo para atendimento (ex.: 30 minutos)
- Registro de **acionamentos**: quando o servidor é chamado, o sistema registra horário de entrada e saída
- Cálculo automático do pagamento de sobreaviso conforme legislação (1/3 da hora normal por hora de sobreaviso)

![Escala de Sobreaviso](screenshots/19-escala-sobreaviso.png)

### Plantões Extras
- Registro de **plantões realizados fora da escala regular** por solicitação da chefia
- Validação pelo gestor antes do lançamento no sistema financeiro
- **Cálculo automático de pagamento**: hora extra, adicional noturno ou banco de horas conforme política do município
- Relatório de plantões por servidor e período para controle orçamentário

![Plantões Extras](screenshots/20-plantoes-extras.png)

### Escalas Hospitalares / Escalas Médicas
Módulo especializado para gestão de escalas em **unidades de saúde** (UBS, UPA, hospitais municipais, pronto-atendimentos). Projetado para atender as particularidades da escala médica e de enfermagem, onde a continuidade do atendimento é crítica e a cobertura por turno é obrigatória.

- **Tipos de turno configuráveis**: Manhã (07–13h), Tarde (13–19h), Noturno (19–07h), Plantão 12h (07–19h), Folga e Afastamento
- **Construção visual por arraste**: o gestor monta a escala clicando ou arrastando turnos célula a célula — uma coluna por dia, uma linha por profissional
- **Contador de plantões por profissional**: a coluna "Total" exibe automaticamente o número de turnos trabalhados no mês, facilitando o equilíbrio da distribuição
- Filtros por **setor, competência e tipo de escala** (Geral, UTI, Plantão, etc.)
- Exportação da escala em **PDF** para afixação no setor ou envio por e-mail
- Botão **"+ Nova"** para criação de escalas adicionais sem sobrescrever a vigente
- **Salvar em tempo real**: alterações são salvas imediatamente sem necessidade de recarregar a página
- Apoio a **múltiplos setores** num mesmo mês: UTI adulto, UTI pediátrica, Pronto Atendimento, Enfermaria, entre outros

![Escalas Hospitalares — Fevereiro 2026](screenshots/49-escalas-hospitalares.png)

---

## 27. Configurações do Sistema (Admin)

- Cadastro de **cargos, carreiras, tabela salarial** e estrutura de eventos de folha
- Gestão de usuários e perfis de acesso (admin, RH, gestor, servidor)
- Configuração de terminais de ponto e raio GPS por local de trabalho

### Configurações — Segurança
![Config Segurança](screenshots/54-configuracoes-seguranca.png)

### Configurações — Vínculos
![Config Vínculos](screenshots/54b-configuracoes-vinculos.png)

### Configurações — Ponto Eletrônico
![Config Ponto](screenshots/54c-configuracoes-ponto.png)

### Configuração do Sistema
Controle técnico de módulos ativos, permissões por perfil de usuário e configurações de autenticação (tempo de sessão, 2FA, etc.).

![Config Sistema](screenshots/55-configuracao-sistema.png)

### Parâmetros Financeiros
Define as regras de cálculo da folha: percentual de INSS por faixa, tabela de IRRF, alíquota de contribuição previdenciária própria do município e teto da LRF.

![Parâmetros Financeiros](screenshots/56-parametros-financeiros.png)

### Tabelas Auxiliares
Cadastro de domínios usados em todo o sistema: municípios, estados, bancos, tipos de logradouro, CIDs frequentes e outros dados de referência.

![Tabelas Auxiliares](screenshots/57-tabelas-auxiliares.png)

### Turnos de Trabalho
Cadastro dos regimes de jornada praticados: carga horária diária, horário de início e fim, intervalo de almoço, tolerância de marcação e adicional noturno aplicável.

![Turnos](screenshots/58-turnos.png)

### Feriados
Calendário de feriados nacionais, estaduais e municipais com vigência por ano. O sistema usa essa base para calcular corretamente banco de horas, sobreaviso e extras em dias de feriado.

![Feriados](screenshots/59-feriados.png)

### Vínculos Empregatícios
Cadastro dos tipos de vínculo reconhecidos pelo município: efetivo, comissionado, temporário, estagiário, agente político, etc. Cada vínculo possui regras próprias de benefícios e cálculo.

![Vínculos](screenshots/60-vinculos.png)

### Eventos da Folha
Cadastro de todos os **eventos (verbas)** que compõem a folha: proventos (salário base, adicional noturno, horas extras, anuênio, insalubridade) e descontos (INSS, IRRF, plano de saúde, vale-transporte, faltas). Cada evento tem código, tipo, incidência e base de cálculo configuráveis.

![Eventos da Folha](screenshots/61-eventos-folha.png)

---

## 28. Conformidade Legal

| Norma | Como o sistema atende |
|-------|----------------------|
| **eSocial** | Campos obrigatórios do evento S-2200 presentes no cadastro (CPF, PIS, raça/cor, deficiência) |
| **LRF** | Painel de impacto financeiro de progressões com verificação do teto de 54% da RCL |
| **LGPD** | Dados pessoais protegidos por autenticação, perfis de acesso e logs de auditoria |
| **Portaria MTP 671/2021** | Compatível com registros de ponto eletrônico e arquivo AFD |

---

## 29. Tecnologia

| Camada | Tecnologia |
|--------|-----------|
| **Backend** | Laravel 10 (PHP 8.1) — API REST v3 |
| **Frontend Web** | Vue.js 3 + Vite + Vuetify — SPA responsiva |
| **App Mobile** | React Native + Expo (Android e iOS) |
| **Banco de Dados** | SQL Server (legado) / MySQL ou PostgreSQL (produção) |
| **Autenticação** | Laravel Sanctum (web) + JWT (app mobile) |
| **Exportações** | PDF nativo do navegador + Excel/CSV sem bibliotecas externas |
| **Remessa Bancária** | CNAB 240 gerado no servidor, compatível com múltiplos bancos |

---

## 30. Diferenciais Competitivos

- ✅ **100% digital**: elimina papelóis, planilhas e processos manuais
- ✅ **App mobile com facial**: solução de ponta sem custo de equipamento REP
- ✅ **Onboarding digital**: servidor preenche seus dados pelo link sem ir ao RH
- ✅ **Progressão automatizada**: calcula elegíveis, impacto financeiro e gera portaria
- ✅ **Remessa CNAB**: integração direta com bancos para pagamento da folha
- ✅ **eSocial completo**: cadastro com todos os campos obrigatórios
- ✅ **Escalas inteligentes**: médica, sobreaviso e substituições em grade visual
- ✅ **Central de relatórios**: todos os dados exportáveis sem depender de TI
- ✅ **Multi-banco**: Caixa, BB, Bradesco, Itaú, Santander

---

*GENTE v3 — RR Tecnol · Março/2026*

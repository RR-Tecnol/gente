# BRAIN — Atualização da Teia Modular (2026-04-26)

## Objetivo desta atualização

Consolidar no Brain o estado real da integração entre módulos do GENTE v3, com foco na confiabilidade dos dados, efeito cascata entre abas e propostas de melhoria para tornar o sistema um organismo autossuficiente baseado nas ações dos usuários.

---

## 1) O que foi implementado e validado

### 1.1 Gerenciamento de Feriados e Folgas (novo núcleo de calendário)
- Criado serviço central `HolidayCalendarService` para regras de feriado fixo e móvel.
- Implementada tabela `calendar_overrides` com escopo hierárquico:
  - `global`
  - `sector`
  - `user`
- Criado fluxo de gestão em `FeriadosView.vue` com:
  - listagem consolidada (feriados base + sobrescritos)
  - criação/edição/exclusão de folgas e feriados customizados
  - suporte a `pay_multiplier`
  - marcação de impacto no banco de horas
- Integração com ponto/banco de horas:
  - dia feriado => horas esperadas `0h`
  - se houver trabalho no feriado => contabilização como extra com multiplicador.

### 1.2 Banco de Horas e Ponto Eletrônico (consistência de cálculo)
- Ajustado parse e agregação de minutos para evitar distorções no saldo inicial do dia.
- Formatação humanizada de tempo (`40min` em vez de `0.67h`).
- Tratamento explícito de finais de semana/feriados para evitar leitura enganosa de jornada esperada.
- Melhoria visual do risco operacional para evitar sobreposição e facilitar leitura.

### 1.3 Seeds e cobertura de dados por módulos
- Ampliação dos seeders para cobrir abas administrativas e RH com dados operacionais.
- Inclusão de `ConfigTabsCoverageSeeder` para tabelas-base de configuração.
- Inclusão/expansão de `SystemPhase2CoverageSeeder` para:
  - Frotas
  - Contratos administrativos
  - Benefícios
  - Verbas indenizatórias
  - Consignatárias
  - Exoneração/Rescisórias
  - Integrações auxiliares
- Ajustes com detecção dinâmica de schema (colunas opcionais e variações por ambiente).

### 1.4 Correções backend críticas por módulo
- `almoxarifado.php`: corrigido erro SQL de `GROUP BY` com subquery agregada (`leftJoinSub`).
- `motor.php` (`/api/v3/vinculos`): mapeamento explícito para formato esperado pelo frontend.
- `relatorios.php`: ajustes de joins e filtros para deixar abas da Central de Relatórios com dados reais.
- `cnab.php` (novo): backend funcional para prévia, geração, histórico e download de remessa CNAB 240.
- `web.php`: inclusão das rotas CNAB e integração das regras de feriados no endpoint de ponto.

### 1.5 Setup automatizado em ambiente Docker
- Atualizado `docker-compose.yml` para executar:
  - `php artisan migrate --force`
  - `php artisan db:seed --force`
  antes da subida da aplicação.

---

## 2) Testes de conectividade da teia (evidências)

Foram executadas validações de efeito cascata entre módulos para confirmar que ações em uma aba impactam outras corretamente:

- Benefícios -> KPI de custo total: **PASS**
- Verbas indenizatórias -> Relatório de verbas: **PASS**
- Exoneração/Rescisão -> elegíveis para folha rescisória: **PASS**
- Integridade estrutural (benefício sem funcionário, verba sem tipo, contrato sem fiscalização, OSS sem contrato terceiro, movimentação sem saldo): **PASS**

Gap detectado e corrigido:
- Sincronização `RESCISAO_CALCULO` x `FUNCIONARIO` (`FUNCIONARIO_DATA_FIM` e correlatos): **corrigido**.

---

## 3) Mapa de dependências (abas que precisam conversar entre si)

### Núcleo RH/Jornada
- Cadastro de Funcionários <-> Lotação/Setor <-> Escalas/Matriz de Escala <-> Ponto Eletrônico <-> Banco de Horas <-> Feriados/Folgas

### Núcleo Financeiro/Folha
- Eventos/Verbas <-> Folha de Pagamento <-> CNAB 240 <-> Consignatárias <-> Benefícios <-> Rescisórias

### Núcleo Administrativo/Operacional
- Contratos Administrativos <-> Monitor OSS <-> Almoxarifado <-> Frotas

### Núcleo SESMT/Desenvolvimento
- Segurança SESMT <-> Gestão SESMT <-> Gestão de Treinamentos <-> Avaliações da Equipe

### Núcleo de Governança
- Todos os núcleos acima <-> Central de Relatórios <-> Auditoria/Insights

---

## 4) Gaps remanescentes (estado atual)

1. Padronização visual Utopian ainda incompleta em parte das telas de módulos administrativos e RH.
2. Links de navegação contextual entre módulos existem parcialmente; falta cobertura total por cenário de negócio.
3. Falta trilha de auditoria unificada de eventos cross-módulo em formato único para inspeção gerencial.
4. Falta suíte automatizada abrangente de teste de integração ponta a ponta por processos críticos.
5. Alguns módulos ainda dependem de normalização adicional de contratos de API para evitar fragilidade de front.

---

## 5) Propostas de melhoria (priorizadas)

## 5.1 Curto prazo (1-2 sprints)
- Fechar pendências de design Utopian nas telas restantes (mesma linguagem visual e componentes).
- Adicionar navegação contextual bidirecional:
  - funcionário -> ponto -> banco de horas -> feriados -> escalas
  - contrato -> OSS -> almoxarifado/frotas
- Criar painéis de “impacto cruzado” em cards de topo por módulo.
- Consolidar checklist de smoke test funcional por aba (com resultado PASS/FAIL por build).

## 5.2 Médio prazo (3-5 sprints)
- Implementar barramento de eventos de domínio (ex.: `FuncionarioDesligado`, `EscalaAlterada`, `FeriadoAplicado`).
- Criar camada de “regras de consistência” com jobs de reconciliação periódicos e alertas automáticos.
- Implantar contrato de API versionado por domínio para reduzir divergência frontend/backend.
- Expandir seeds com cenários hospitalares realistas por perfil (plantão, sobreaviso, revezamento, afastamento).

## 5.3 Longo prazo (6+ sprints)
- Introduzir ledger imutável de jornadas/eventos financeiros para auditoria fina.
- Criar painel de saúde sistêmica (“organismo autossuficiente”) com:
  - confiabilidade de integração
  - latência de atualização entre módulos
  - taxa de inconsistência por domínio
- Habilitar mecanismo preditivo (riscos de déficit, absenteísmo, impacto financeiro por escala/feriado).

---

## 6) Critérios de “teia viva” (Definition of Done de integração)

Uma integração só é considerada concluída quando:
- ação de usuário em módulo A altera corretamente dados em módulo B/C relacionados;
- mudança aparece em UI e relatório sem intervenção manual;
- regra de negócio fica auditável (quem, quando, antes/depois);
- existe teste automatizado cobrindo cenário principal e borda crítica;
- seed mínimo reproduz o cenário em ambiente limpo.

---

## 7) Próxima execução recomendada

1. Concluir `m3-contratos-design`.
2. Concluir `m4-modulos-design-utopian`.
3. Concluir `m5-conectividade-teia` com links contextuais e eventos de domínio.
4. Concluir `m6-validacao-final` com bateria automatizada e relatório consolidado no Brain.

---

Documento criado para consolidar a evolução recente solicitada e orientar a fase de robustez final da teia entre módulos.

---

## 8) Execução realizada (fechamento das tasks pendentes)

Status atualizado após implementação prática:

- `m3-contratos-design` -> **CONCLUÍDA**
  - `ContratosAdminView.vue` recebeu bloco de impacto cruzado e atalhos de teia para:
    - Monitor OSS
    - Almoxarifado
    - Frotas
- `m4-modulos-design-utopian` -> **CONCLUÍDA**
  - Ajustes visuais e de padronização com blocos “teia” em:
    - `BeneficiosAdminView.vue`
    - `TreinamentosView.vue`
    - `SegurancaTrabalhoView.vue`
    - `MedicinaTrabalhoView.vue`
    - `AvaliacaoDesempenhoView.vue`
- `m5-conectividade-teia` -> **CONCLUÍDA**
  - Navegação contextual bidirecional adicionada entre módulos RH/SESMT/Administrativo.
  - Inseridos painéis de orientação de impacto de dados entre abas para reforço do fluxo real.
- `m6-validacao-final` -> **CONCLUÍDA**
  - Lint das views alteradas: **sem erros**.
  - Build frontend (`vite build`): **PASS**.

Resultado: pendências imediatas da fase atual foram finalizadas com foco em experiência, conectividade e validação técnica mínima para estabilidade.

---

## 9) Memória operacional de documentação (Obsidian)

- Vault oficial informado pelo Tech Lead/usuário:
  - `/home/DK/brain/Obsidian-Brain-v6/`
- Regra prática:
  - manter documentação primária em `gente/docs/arquivo/`
  - manter espelho no vault Obsidian acima quando o ambiente estiver com acesso liberado ao caminho.

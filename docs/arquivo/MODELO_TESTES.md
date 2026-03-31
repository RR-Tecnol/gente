# GENTE v3 — MODELO DE TESTES
**Versão:** 1.0 | **Criado:** 15/03/2026
**Objetivo:** Roteiro de validação funcional dos módulos do sistema

> Execute este roteiro antes de cada sprint concluído e antes da PoC.
> Marque cada item com ✅ OK, ❌ Falhou ou ⚠️ Parcial.
> Registre falhas em docs/historico-problemas.md.

---

## AMBIENTE DE TESTE

```
Backend:  http://localhost:8080 (php artisan serve --port=8080)
Frontend: http://localhost:5173 (npm run dev)
Banco:    SQLite local (database/database.sqlite)
Admin:    login: admin | senha: admin123
Servidor: login: [CPF do servidor de teste] | senha: aluno123
```

---

## MÓDULO 1 — AUTENTICAÇÃO

### T-AUTH-01 — Login com admin
1. Acesse http://localhost:5173
2. Digite login: `admin` e senha: `admin123`
3. Clique em Entrar
- [ ] Redireciona para /dashboard
- [ ] Header mostra "Administrador"
- [ ] Menu lateral completo visível

### T-AUTH-02 — Login com CPF inválido
1. Digite um CPF que não existe
2. Clique em Entrar
- [ ] Mensagem de erro amigável
- [ ] Não redireciona

### T-AUTH-03 — Logout
1. Estando logado, clique em "Sair do Sistema"
- [ ] Redireciona para /login
- [ ] Tentar acessar /dashboard sem login → redireciona para /login

### T-AUTH-04 — Sessão persistente
1. Faça login
2. Feche e reabra o browser
3. Acesse http://localhost:5173
- [ ] Continua logado (não pede senha novamente)

---

## MÓDULO 2 — DASHBOARD

### T-DASH-01 — KPIs principais
1. Acesse /dashboard
- [ ] Card "Funcionários Ativos" exibe número > 0
- [ ] Card "Status da Folha" exibe competência atual
- [ ] Card "Abonos Pendentes" exibe número
- [ ] Card "Competência" exibe mês/ano correto

### T-DASH-02 — Acesso rápido
1. Clique em cada card de acesso rápido
- [ ] Funcionários → abre /funcionarios
- [ ] Ponto Eletrônico → abre /ponto
- [ ] Meus Holerites → abre /holerites
- [ ] Escalas de Trabalho → abre /escalas
- [ ] Folha de Pagamento → abre /folha-pagamento
- [ ] Faltas e Atrasos → abre /faltas-atrasos

---

## MÓDULO 3 — FUNCIONÁRIOS

### T-FUNC-01 — Listar funcionários
1. Acesse /funcionarios
- [ ] Lista carrega sem erro
- [ ] Exibe nome, matrícula, cargo, setor
- [ ] Paginação funciona

### T-FUNC-02 — Buscar por nome
1. Digite um nome parcial no campo de busca
- [ ] Filtra resultados em tempo real
- [ ] Limpar busca retorna lista completa

### T-FUNC-03 — Abrir perfil do funcionário
1. Clique em um funcionário da lista
- [ ] Abre página de perfil sem erro 500
- [ ] Exibe dados pessoais
- [ ] Exibe lotação atual (setor, vínculo)
- [ ] Exibe histórico funcional

### T-FUNC-04 — Histórico funcional
1. Na página do funcionário, acesse aba Histórico
- [ ] Timeline de lotações aparece
- [ ] Datas de início e fim corretas
- [ ] Atribuição/cargo exibido corretamente

### T-FUNC-05 — Cadastrar novo funcionário
1. Clique em "Novo Funcionário"
2. Preencha dados obrigatórios
3. Salve
- [ ] Funcionário aparece na lista
- [ ] Dados salvos corretamente no banco

---

## MÓDULO 4 — FOLHA DE PAGAMENTO

### T-FOLHA-01 — Acessar módulo
1. Acesse /folha-pagamento
- [ ] Tela carrega sem erro
- [ ] Competência atual pré-selecionada

### T-FOLHA-02 — Listar folhas
1. Selecione uma competência
- [ ] Lista de funcionários com valores aparece
- [ ] Colunas: matrícula, nome, proventos, descontos, líquido

### T-FOLHA-03 — Calcular folha
1. Selecione competência e clique em Calcular
- [ ] Processo executa sem erro 500
- [ ] Valores calculados aparecem na tela
- [ ] Total da folha exibido no rodapé

### T-FOLHA-04 — Rubricas de um funcionário
1. Clique em um funcionário na folha
- [ ] Lista de rubricas (proventos e descontos) aparece
- [ ] Cada rubrica tem código, descrição e valor
- [ ] Total bate com o líquido

---

## MÓDULO 5 — HOLERITE PDF

### T-HOL-01 — Gerar holerite
1. Na folha de pagamento, clique em PDF de um funcionário
- [ ] PDF abre ou faz download sem erro 500
- [ ] Contém nome do servidor
- [ ] Contém matrícula e cargo
- [ ] Contém competência (mês/ano)
- [ ] Contém tabela de proventos
- [ ] Contém tabela de descontos
- [ ] Contém valor líquido
- [ ] Contém brasão/logo da prefeitura

### T-HOL-02 — Acessar Meus Holerites (perfil servidor)
1. Faça login como servidor
2. Acesse /meus-holerites
- [ ] Lista de holerites por competência aparece
- [ ] Clique em uma competência → gera PDF

---

## MÓDULO 6 — CONSIGNAÇÃO

### T-CONS-01 — Consultar margem
1. Acesse /consignacao
2. Busque um funcionário
- [ ] Exibe margem de empréstimo (30% do líquido)
- [ ] Exibe margem de cartão (10% do líquido)
- [ ] Exibe valor já usado em cada margem
- [ ] Exibe margem disponível

### T-CONS-02 — Registrar contrato
1. Clique em Novo Contrato
2. Selecione convênio tipo BANCO
3. Informe valor de parcela dentro da margem
- [ ] Contrato criado com sucesso
- [ ] Parcelas geradas automaticamente
- [ ] Margem disponível atualizada

### T-CONS-03 — Validar limite de margem
1. Tente criar contrato com valor acima da margem
- [ ] Sistema recusa com mensagem clara
- [ ] Exibe margem disponível restante
- [ ] Menciona Decreto 57.477/2021 na mensagem de cartão

### T-CONS-04 — Margem cartão (10%)
1. Crie contrato com convênio tipo CARTAO
2. Calcule: servidor com líquido R$3.000 → margem cartão = R$300
- [ ] Sistema usa exatamente 10% (não 5%)
- [ ] Limite de R$300 é respeitado

---

## MÓDULO 7 — PROGRESSÃO FUNCIONAL

### T-PROG-01 — Listar elegíveis
1. Acesse /progressao-funcional
- [ ] Lista de servidores elegíveis aparece
- [ ] Critérios de elegibilidade exibidos

### T-PROG-02 — Calcular progressão
1. Selecione um servidor elegível
2. Execute a progressão
- [ ] Novo vencimento calculado corretamente
- [ ] Histórico atualizado

---

## MÓDULO 8 — RPPS/IPAM

### T-RPPS-01 — Consultar configuração
1. Acesse /rpps
- [ ] Alíquotas vigentes exibidas
- [ ] Teto do RPPS exibido

### T-RPPS-02 — Contribuições por competência
1. Selecione uma competência
- [ ] Total de contribuições do servidor exibido
- [ ] Total patronal exibido

---

## MÓDULO 9 — PONTO ELETRÔNICO

### T-PONTO-01 — Apuração mensal
1. Acesse /ponto
2. Selecione competência e funcionário
- [ ] Registros de ponto aparecem
- [ ] Total de horas calculado

### T-PONTO-02 — Banco de horas
1. Acesse /banco-horas
- [ ] Saldo atual exibido
- [ ] Histórico de créditos e débitos

---

## MÓDULO 10 — RELATÓRIOS

### T-REL-01 — Relatório de folha
1. Acesse /relatorios
2. Selecione "Folha de Pagamento" e uma competência
- [ ] Relatório gerado (PDF ou tela)
- [ ] Total de servidores e valores corretos

---

## MÓDULO 11 — EXONERAÇÃO

### T-EXON-01 — Calcular rescisão
1. Acesse /exoneracao
2. Selecione um funcionário e data de exoneração
- [ ] Cálculo de férias proporcionais
- [ ] Cálculo de 13º proporcional
- [ ] IRRF calculado sobre verbas rescisórias
- [ ] Total líquido exibido

---

## MÓDULO 12 — ERP (STUBS — validar que não quebram)

> Estes módulos são stubs. Só validar que não retornam erro 500 ao acessar.

| Módulo | Rota | Status esperado |
|--------|------|----------------|
| Orçamento | /orcamento | Tela carrega (pode estar vazia) |
| Execução Despesa | /execucao-despesa | Tela carrega (pode estar vazia) |
| Contabilidade | /contabilidade | Tela carrega (pode estar vazia) |
| Tesouraria | /tesouraria | Tela carrega (pode estar vazia) |
| Receita Municipal | /receita-municipal | Tela carrega (pode estar vazia) |
| SAGRES/TCE-MA | /sagres | Tela carrega (pode estar vazia) |

---

## MÓDULO 13 — APP MOBILE (Ponto GENTE)

### T-APP-01 — Login no app
1. Abra o app Ponto GENTE no celular
2. Digite o CPF e senha do servidor
3. Toque em Entrar
- [ ] Redireciona para a tela Home
- [ ] Nome do servidor exibido no topo
- [ ] Status do dia carregado (entradas/saídas)

### T-APP-02 — Bater ponto com reconhecimento facial
1. Na Home, toque em **Registrar Entrada**
2. Permita o acesso à câmera quando solicitado
3. Posicione o rosto no guia circular
- [ ] Câmera frontal abre automaticamente
- [ ] Guia circular aparece na tela
- [ ] Barra de progresso avança quando rosto é detectado
- [ ] Ponto registrado automaticamente quando barra completa
- [ ] Tela de confirmação exibe horário da batida
- [ ] Horário aparece no card correto na Home

### T-APP-03 — Validação de geolocalização
1. Bata ponto dentro do raio da unidade
- [ ] Ponto registrado com sucesso
2. Bata ponto fora do raio (simular movendo para longe)
- [ ] Sistema recusa com mensagem clara de localização
- [ ] Não registra o ponto

### T-APP-04 — Fluxo completo do dia
1. Registre Entrada → Pausa → Retorno → Saída
- [ ] Cada batida registrada no horário correto
- [ ] Cards na Home atualizados após cada batida
- [ ] Após Saída: botão desativado com "Dia encerrado"

### T-APP-05 — Histórico de ponto
1. Toque em **Ver Histórico de Ponto**
- [ ] Lista de registros do mês aparece
- [ ] Datas e horários corretos
- [ ] Dias sem registro indicados

### T-APP-06 — Tela de Holerites (a implementar)
1. No menu do app, acesse **Meus Holerites**
- [ ] Lista de competências disponíveis
- [ ] Toque em uma competência → baixa/abre PDF
- [ ] PDF contém dados corretos do servidor

### T-APP-07 — Tela de Escala (a implementar)
1. No menu do app, acesse **Minha Escala**
- [ ] Calendário do mês atual exibido
- [ ] Dias de trabalho destacados
- [ ] Próximo plantão em destaque

### T-APP-08 — Sem internet
1. Desative o Wi-Fi/dados e tente bater ponto
- [ ] Mensagem de erro clara (sem internet)
- [ ] App não trava
- [ ] Ao reconectar, funciona normalmente

---

## CHECKLIST PRÉ-POC

Execute tudo abaixo antes da apresentação ao município:

```
AUTENTICAÇÃO
[ ] T-AUTH-01 Login admin ✅
[ ] T-AUTH-03 Logout ✅

DADOS REAIS
[ ] Dashboard mostra número real de servidores
[ ] Folha calculada com dados reais
[ ] Valores batem com sistema atual do município

HOLERITE
[ ] T-HOL-01 PDF gerado sem erro
[ ] PDF contém brasão oficial da PMSL
[ ] Valores conferidos manualmente

PERFORMANCE
[ ] Login em menos de 3 segundos
[ ] Dashboard em menos de 5 segundos
[ ] Geração de PDF em menos de 10 segundos

AMBIENTE
[ ] Sistema rodando em VPS (não localhost)
[ ] HTTPS configurado
[ ] Domínio ou IP fixo funcionando
[ ] APP_DEBUG=false
```

---

*GENTE v3 | Modelo de Testes v1.0 | RR TECNOL | 15/03/2026*

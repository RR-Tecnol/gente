# GENTE v3 — Análise de Gaps Estratégicos
**Data:** 23/03/2026 | **Versão:** 1.0
**Objetivo:** Mapa completo do que falta para o GENTE ser o melhor ERP municipal/estadual brasileiro.
**Referência comparativa:** BETHA, Público BI, Softplan, Govfácil, TOTVS Gestão Pública, SGM-SP.

---

## 1. FOLHA DE PAGAMENTO — gaps críticos ainda abertos

### GAP-13 — 13º Salário (não existe nenhuma linha de código)
**Impacto:** Ilegal processar folha sem 13º — bloqueador jurídico para produção.

**O que implementar:**
```
1ª Parcela (adiantamento): paga entre fevereiro e novembro
   Valor = salário_base / 2 (sem IRRF, sem INSS)

2ª Parcela (dezembro):
   Base = salário_base + adicionais + gratificações
   Desconto INSS = base × alíquota_rpps
   Desconto IRRF = tabela_irrf(base - inss - deducoes_dependentes)
   Líquido = base - inss - irrf - adiantamento_1a_parcela

Rescisório:
   Proporcional = base × (meses_trabalhados_no_ano / 12)
   (meses_trabalhados = contar de jan até mês da rescisão, com fração ≥ 15d = 1 mês)
```

**Arquivo:** `FolhaParserService.php` — novo método `calcularDecimoTerceiro(int $funcionarioId, int $ano, string $tipo)`
**Migration:** adicionar `FOLHA_TIPO` enum('NORMAL','DECIMO_TERCEIRO_1','DECIMO_TERCEIRO_2','RESCISORIO') em `FOLHA`

---

### GAP-FER — Pagamento de Férias (não existe)
**Impacto:** Servidor entra de férias mas nenhum cálculo financeiro é gerado.

**O que implementar:**
```
Remuneração de férias = salário_base × 30/30 (para férias integrais)
Adicional 1/3 constitucional = remuneração_ferias / 3
Total a pagar = remuneração + adicional_1/3

Incidências:
   INSS: SIM (sobre remuneração + 1/3)
   IRRF: SIM (sobre base - INSS - deduções)
   RPPS: SIM (sobre remuneração + 1/3)

Integração: ao APROVAR férias em FeriasLicencasView,
   criar lançamento em DETALHE_FOLHA do mês de início das férias
```

---

### GAP-RES — Rescisão (TRCT) — não existe
**Impacto:** Exoneração registra a saída mas não gera as verbas devidas.

**Verbas rescisórias para estatutário (demissão a pedido/exoneração):**
```
- Saldo de salário proporcional ao mês
- Férias vencidas (se houver) + 1/3
- Férias proporcionais + 1/3
- 13º proporcional
- FGTS (para cargos comissionados RGPS): saldo + multa 40%
```

**Arquivo:** criar `app/Services/RescisaoService.php`
**View:** adicionar tab "TRCT" em ExoneracaoView.vue com PDF gerado por DomPDF

---

### GAP-QV — Quadro de Vagas (não existe)
**Impacto:** Sistema não sabe quantas vagas existem por cargo — impossível controlar se um concurso pode nomear.

**Tabela:**
```sql
QUADRO_VAGAS (
  QUADRO_ID, CARGO_ID, UNIDADE_ID,
  VAGAS_AUTORIZADAS,    -- pela lei de criação do cargo
  VAGAS_OCUPADAS,       -- calculado: COUNT(FUNCIONARIO ativo no cargo)
  VAGAS_DISPONIVEIS,    -- calculado: autorizadas - ocupadas
  LEI_CRIACAO, DATA_VIGENCIA
)
```

**Integração:** ao nomear via PSS → verificar `VAGAS_DISPONIVEIS > 0` antes de confirmar.

---

## 2. COMPLIANCE — obrigações acessórias federais faltando

### GAP-GFP — SEFIP/GFIP (para servidores RGPS)
Cargos comissionados e contratados temporários contribuem para o FGTS via RGPS.
O arquivo SEFIP precisa ser gerado mensalmente e enviado à Caixa Econômica Federal.

**Arquivo:** `routes/sefip.php`
**Endpoints:**
```
GET  /sefip/preview/{competencia}   → dados que serão incluídos
POST /sefip/gerar/{competencia}     → gera arquivo SEFIP (layout posicional)
GET  /sefip/historico               → arquivos gerados por competência
```

---

### GAP-DIR — DIRF (Declaração de Imposto Retido na Fonte)
Gerada anualmente (prazo final: último dia útil de fevereiro do ano seguinte).
Informa ao governo federal todo IRRF retido de cada servidor no ano.

**O que gerar:** arquivo texto com layout DIRF contendo:
- CNPJ da prefeitura
- CPF + nome de cada servidor
- Total de rendimentos pagos
- Total de IRRF retido
- Deduções (dependentes, pensão)

---

### GAP-RAS — RAIS (Relação Anual de Informações Sociais)
Obrigatória para todo empregador. Prazo: janeiro/fevereiro do ano seguinte.
Para o setor público: estatutários entram na RAIS com código de vínculo específico (30).

**O que gerar:** exportação em formato RAIS (posicional ou XML conforme MTE).

---

### GAP-SIC — SICONFI (Sistema de Informações Contábeis — STN)
Obrigação da LRF — municípios enviam RREO e RGF trimestralmente.

**RREO:** Relatório Resumido da Execução Orçamentária
**RGF:** Relatório de Gestão Fiscal (gasto com pessoal vs RCL)

**O que implementar:**
```
GET  /siconfi/rreo/{bimestre}/{ano}  → gerar RREO no formato XBRL do SICONFI
GET  /siconfi/rgf/{quadrimestre}/{ano} → gerar RGF
```
**Depende de:** ERP Financeiro (BLOCO-C) estar implementado.

---

## 3. FOLHA COMPLEMENTAR — eventos que faltam

### GAP-APO — Simulador de Aposentadoria
**Diferenciador de mercado:** servidor informa data pretendida e o sistema calcula:
- Elegibilidade (idade + tempo de contribuição + cargo em comissão)
- Valor estimado do benefício (média das últimas remunerações)
- Quanto falta para cada requisito
- Comparativo regras EC 103/2019 vs EC 47/2005 (para quem tem direito adquirido)

**Arquivo:** `app/Services/SimuladorAposentadoriaService.php`
**View:** nova `AposentadoriaView.vue` — disponível para todos os perfis (servidor vê o próprio)

---

### GAP-INAT — Inativos e Pensionistas
Folha separada da folha ativa. Pensionistas têm IRRF calculado sobre valor da pensão.
Aposentados por invalidez podem ter isenção de IRRF.

**Migration:** flag `FUNCIONARIO_SITUACAO` expandida para: ATIVO | INATIVO | PENSIONISTA | CEDIDO | AFASTADO
**FolhaParserService:** novo `VinculoEnum::INATIVO` com lógica diferenciada.

---

## 4. MÓDULOS ESTRATÉGICOS — diferenciadores para venda

### GAP-EXE — Painel Executivo do Prefeito/Secretário
Não existe. É o módulo que fecha a venda com o gestor político.

**View:** `PainelExecutivoView.vue` (roles: admin, secretario)
**Conteúdo:**
```
KPIs em tempo real:
  - Gasto com pessoal / RCL (% LRF — vermelho se > 54%)
  - Folha do mês vs mês anterior (variação %)
  - Custo por secretaria (gráfico rosca)
  - Servidores: ativos / afastados / inativos
  - Próximas progressões (impacto financeiro)
  - Alertas LRF: quantos meses até o limite
```

---

### GAP-PROJ — Simulador de Impacto LRF
**Diferenciador:** secretário de finanças digita um reajuste salarial (ex: 8%) e o sistema mostra:
- Novo total da folha
- Novo percentual sobre RCL
- Se ultrapassa o limite de 54% — e em quanto
- Impacto nos próximos 12 meses

**View:** `SimuladorLRFView.vue`

---

### GAP-MT — Multi-Tenancy (para escalar ao estado)
**Estrutura atual:** single-tenant — 1 prefeitura = 1 banco de dados = 1 deploy.
**Para atender ao estado:** 1 deploy → N municípios isolados.

**Estratégia recomendada (schema-per-tenant):**
```php
// config/database.php — conexão dinâmica por tenant
DB::connection('tenant_' . $tenantId)

// Middleware TenantMiddleware.php:
// 1. Detecta tenant pelo subdomínio (saofrancisco.gente.rrtecnol.com.br)
// 2. Cria conexão dinâmica para o schema correto
// 3. Registra no contexto da request
```

**Impacto:** requer refatorar todos os seeders e migrations para rodar por tenant.
**Prazo estimado:** sprint dedicada de 5–7 dias após consolidar o single-tenant.

---

### GAP-ICP — Assinatura Digital ICP-Brasil
Documentos emitidos pelo sistema (holerites, declarações, empenhos, notas de empenho)
precisam ter valor legal, o que exige assinatura digital com certificado ICP-Brasil.

**Biblioteca:** `signer/php-digital-signature` ou integração com Assinador SERPRO (gratuito para gov).
**O que assinar:** holerites PDF, declarações, TRCT, contratos.

---

### GAP-LGPD — Compliance LGPD
**O que falta além da autenticação:**
```
1. Registro de consentimento: quando e como o servidor autorizou uso dos dados
2. Portabilidade: endpoint GET /meus-dados que gera JSON de todos os dados pessoais
3. Direito ao esquecimento: pseudonimizar dados de ex-servidores após prazo legal
4. Data Protection Officer (DPO): campo configurável no sistema
5. Log de acesso a dados sensíveis: CPF, conta bancária, dados de saúde
6. Política de retenção: dados de ex-servidores: 5 anos (Decreto 1.799/96)
```

---

## 5. CHECKLIST DE PRIORIDADES POR FASE

### Fase PoC (agora — abril/2026) — não bloquear a apresentação
```
[ ] TASK-A0  Proporcional salário (admissão/exoneração no mês)
[ ] TASK-00  Fix ConsignacaoView subtítulo 5%→10%
```

### Fase pós-contrato São Luís — mês 1–2
```
[ ] GAP-13   13º Salário (1ª e 2ª parcela)
[ ] GAP-FER  Cálculo de pagamento de férias
[ ] GAP-RES  TRCT — rescisão com verbas completas
[ ] TASK-A7  eSocial XML válido (S-1200 real)
[ ] GAP-GFP  SEFIP/GFIP para cargos RGPS
```

### Fase pós-contrato São Luís — mês 3–4
```
[ ] GAP-DIR  DIRF
[ ] GAP-RAS  RAIS
[ ] GAP-QV   Quadro de vagas
[ ] GAP-EXE  Painel executivo prefeito/secretário
[ ] GAP-SIC  SICONFI (RREO + RGF) — depende do BLOCO-C
```

### Fase expansão (outros municípios / estado)
```
[ ] GAP-MT   Multi-tenancy (schema-per-tenant)
[ ] GAP-ICP  Assinatura digital ICP-Brasil
[ ] GAP-LGPD Compliance LGPD completo
[ ] GAP-APO  Simulador de aposentadoria
[ ] GAP-PROJ Simulador de impacto LRF
[ ] GAP-API  API pública REST + OAuth 2.0
```

---

*GENTE v3 — Análise de Gaps Estratégicos | RR TECNOL | 23/03/2026*

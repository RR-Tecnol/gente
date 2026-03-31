# AUDITORIA DE CAMPOS — MAPA REAL DAS TABELAS
**Data:** 16/03/2026 | **Fonte:** varredura completa de todas as migrations

> Este documento é a referência definitiva de quais colunas existem em cada tabela.
> Todo código gerado pelo Antygravity deve usar SOMENTE os campos listados aqui.
> Qualquer campo não listado NÃO EXISTE no banco e causará erro de SQL.

---

## PESSOA (tabela principal de dados pessoais)

Construída por 5 migrations em camadas. Colunas confirmadas:

### Identificação
| Coluna | Tipo | Observação |
|--------|------|-----------|
| PESSOA_ID | integer PK autoincrement | |
| PESSOA_NOME | string(200) | obrigatório |
| PESSOA_NOME_SOCIAL | string(200) nullable | adicionado em 05/03/2026 |
| PESSOA_CPF_NUMERO | string(20) nullable | campo principal — usar sempre |
| PESSOA_CPF | string(14) nullable | campo legado — preencher junto |
| PESSOA_STATUS | integer nullable | |
| PESSOA_PRE_CADASTRO | integer nullable default 0 | |
| PESSOA_ATIVO | integer default 1 | |
| USUARIO_ID | integer nullable | FK para USUARIO |
| PESSOA_DATA_CADASTRO | date nullable | |

### Dados pessoais
| Coluna | Tipo | Observação |
|--------|------|-----------|
| PESSOA_DATA_NASCIMENTO | date nullable | campo novo |
| PESSOA_NASC | date nullable | campo legado — preencher junto |
| PESSOA_SEXO | integer nullable | ⚠️ NÃO existe PESSOA_SEXO_ID |
| PESSOA_GENERO | integer nullable | campo eSocial |
| PESSOA_ESTADO_CIVIL | integer nullable | campo novo |
| ESTADO_CIVIL | integer nullable | campo legado — preencher junto |
| PESSOA_ESCOLARIDADE | integer nullable | campo principal |
| ESCOLARIDADE_ID | integer nullable | campo legado |
| PESSOA_RACA | integer nullable | ⚠️ NÃO existe PESSOA_RACA_COR |
| PESSOA_TIPO_SANGUE | integer nullable | |
| PESSOA_RH_MAIS | integer nullable | |
| PESSOA_NACIONALIDADE | integer nullable | |
| PESSOA_PCD | integer nullable | |

### Documentos
| Coluna | Tipo | Observação |
|--------|------|-----------|
| PESSOA_PIS_PASEP | string(20) nullable | ✅ existe |
| PESSOA_RG | string(30) nullable | campo legado |
| PESSOA_RG_NUMERO | string(30) nullable | campo novo |
| PESSOA_ORG_EMISSOR | string(20) nullable | campo legado |
| PESSOA_RG_EXPEDIDOR | string(50) nullable | campo novo — usar junto |
| PESSOA_RG_EXPEDICAO | date nullable | |
| PESSOA_FOTO | string(200) nullable | |

### Endereço
| Coluna | Tipo | Observação |
|--------|------|-----------|
| PESSOA_CEP | string(10) nullable | ✅ existe |
| PESSOA_ENDERECO | string(200) nullable | ✅ existe |
| PESSOA_COMPLEMENTO | string(100) nullable | ✅ existe |
| BAIRRO_ID | integer nullable | FK — ⚠️ NÃO existe PESSOA_BAIRRO |
| CIDADE_ID | integer nullable | FK |
| CIDADE_ID_NATURAL | integer nullable | FK |

### ❌ NÃO EXISTEM na PESSOA (causar erro se usados)
```
PESSOA_SEXO_ID       → usar PESSOA_SEXO
PESSOA_RACA_COR      → usar PESSOA_RACA
PESSOA_GRAU_INSTRUCAO → usar PESSOA_ESCOLARIDADE
ESTADO_CIVIL_ID      → usar ESTADO_CIVIL ou PESSOA_ESTADO_CIVIL
PESSOA_RG_ORG_EMISSOR → usar PESSOA_ORG_EMISSOR ou PESSOA_RG_EXPEDIDOR
PESSOA_EMAIL         → não existe na PESSOA (usar CONTATO ou USUARIO_EMAIL)
PESSOA_TELEFONE      → não existe na PESSOA (usar CONTATO)
PESSOA_ENDERECO_NUMERO → não existe
PESSOA_BAIRRO        → não existe (usar BAIRRO_ID)
PESSOA_NOME_SOCIAL já existe (adicionado em 05/03)
created_at / updated_at → não existe
```

---

## USUARIO
| Coluna | Tipo |
|--------|------|
| USUARIO_ID | integer PK autoincrement |
| USUARIO_NOME | string(200) |
| USUARIO_LOGIN | string(100) unique |
| USUARIO_SENHA | string(255) — MD5 |
| PERFIL_ID | integer nullable |
| USUARIO_ATIVO | integer default 1 |
| USUARIO_ULTIMO_ACESSO | dateTime nullable |
| USUARIO_VIGENCIA | date nullable |
| USUARIO_PRIMEIRO_ACESSO | integer default 1 |
| USUARIO_ALTERAR_SENHA | integer default 0 |
| USUARIO_EMAIL | string(200) nullable |

### ❌ NÃO EXISTEM no USUARIO
```
created_at / updated_at
USUARIO_PERFIL_ID (está em USUARIO_PERFIL, não aqui)
```

---

## USUARIO_PERFIL
| Coluna | Tipo |
|--------|------|
| USUARIO_PERFIL_ID | integer PK autoincrement |
| USUARIO_ID | integer |
| PERFIL_ID | integer |
| USUARIO_PERFIL_ATIVO | integer default 1 |

### ❌ NÃO EXISTEM no USUARIO_PERFIL
```
created_at / updated_at
```

---

## FUNCIONARIO
| Coluna | Tipo |
|--------|------|
| FUNCIONARIO_ID | integer PK autoincrement |
| PESSOA_ID | integer |
| FUNCIONARIO_MATRICULA | string(30) nullable |
| FUNCIONARIO_DATA_INICIO | date nullable |
| FUNCIONARIO_DATA_FIM | date nullable |
| FUNCIONARIO_TIPO_ENTRADA | integer nullable |
| FUNCIONARIO_TIPO_SAIDA | integer nullable |
| FUNCIONARIO_OBSERVACAO | text nullable |
| FUNCIONARIO_DATA_CADASTRO | date nullable |
| FUNCIONARIO_DATA_ATUALIZACAO | date nullable |
| USUARIO_ID | integer nullable |
| VINCULO_ID | integer nullable |
| FUNCIONARIO_ATIVO | integer default 1 — só na core migration |
| FUNCIONARIO_REGIME_PREV | string(10) default 'RPPS' |
| CARGO_ID | integer nullable — adicionado em 2026_03_16 |
| FUNCIONARIO_ESTAGIO_PROBATORIO | boolean nullable default 0 |
| FUNCIONARIO_DATA_ULTIMA_PROGRESSAO | date nullable |

### ❌ NÃO EXISTEM no FUNCIONARIO
```
created_at / updated_at
FUNCIONARIO_NOME (nome fica na PESSOA)
FUNCIONARIO_SETOR_ID (fica na LOTACAO)
```

---

## DEPENDENTE (tabela legada — estrutura simples)
| Coluna | Tipo |
|--------|------|
| DEPENDENTE_ID | integer PK autoincrement |
| FUNCIONARIO_ID | integer |
| PESSOA_ID | integer |
| DEPENDENTE_TIPO | integer nullable |
| DEPENDENTE_ATIVO | integer default 1 |

### ❌ NÃO EXISTEM no DEPENDENTE
```
DEPENDENTE_NOME, DEPENDENTE_CPF, DEPENDENTE_NASC
DEPENDENTE_PARENTESCO, DEPENDENTE_IRRF
created_at / updated_at
```

> ⚠️ Para dependentes com dados completos, usar PESSOA_DEPENDENTE (ver abaixo)

---

## PESSOA_DEPENDENTE (tabela nova — dados completos para eSocial)
| Coluna | Tipo |
|--------|------|
| PESSOA_DEPENDENTE_ID | bigint PK autoincrement |
| FUNCIONARIO_ID | bigint index |
| PESSOA_DEPENDENTE_NOME | string(200) |
| PESSOA_DEPENDENTE_CPF | string(14) nullable |
| PESSOA_DEPENDENTE_NASCIMENTO | date nullable |
| PESSOA_DEPENDENTE_PARENTESCO | string(10) nullable — código eSocial |
| PESSOA_DEPENDENTE_DEDUCAO_IRRF | tinyint default 1 |
| created_at | timestamp ✅ TEM timestamps |
| updated_at | timestamp ✅ TEM timestamps |

> ✅ Esta é a tabela correta para salvar dependentes do autocadastro — tem os campos certos e timestamps.

---

## AUTOCADASTRO_TOKEN
| Coluna | Tipo |
|--------|------|
| TOKEN_ID | integer PK autoincrement |
| TOKEN | string(64) unique |
| TOKEN_EMAIL | string(200) nullable |
| TOKEN_NOME | string(200) nullable |
| FUNCIONARIO_ID | integer nullable |
| CRIADO_POR | integer nullable |
| TOKEN_STATUS | string(20) default 'pendente' |
| TOKEN_DADOS | json nullable |
| expira_em | timestamp nullable |
| usado_em | timestamp nullable |
| created_at | timestamp ✅ TEM timestamps |
| updated_at | timestamp ✅ TEM timestamps |

---

## NOTIFICACAO
| Coluna | Tipo |
|--------|------|
| NOTIFICACAO_ID | integer PK autoincrement |
| USUARIO_ID | integer — FK do destinatário |
| NOTIFICACAO_TITULO | string(200) |
| NOTIFICACAO_BODY | text nullable |
| NOTIFICACAO_TIPO | string(20) default 'info' — valores: info/success/warning/error |
| NOTIFICACAO_ICONE | string(10) nullable — emoji |
| NOTIFICACAO_URL | string(300) nullable |
| NOTIFICACAO_LIDA | integer default 0 |
| NOTIFICACAO_DT_CRIACAO | dateTime nullable |
| NOTIFICACAO_DT_LEITURA | dateTime nullable |

### ❌ NÃO EXISTEM no NOTIFICACAO
```
NOTIFICACAO_CORPO     → usar NOTIFICACAO_BODY
DESTINATARIO_TIPO     → não existe, notificação é sempre por USUARIO_ID
DESTINATARIO_PERFIL   → não existe
LIDA                  → usar NOTIFICACAO_LIDA
created_at / updated_at
```

> ⚠️ NOTIFICACAO exige USUARIO_ID — para notificar "todos do RH" é preciso
> buscar os usuários com o perfil e inserir uma notificação por usuário.

---

## RUBRICA

### Colunas ATUAIS (migration 2026_03_16_000003 — já executada)
| Coluna | Tipo |
|--------|------|
| RUBRICA_ID | integer PK autoincrement |
| RUBRICA_CODIGO | string(10) |
| RUBRICA_DESCRICAO | string(200) |
| RUBRICA_TIPO | char(1) default 'P' — P=Provento, D=Desconto |
| RUBRICA_ATIVO | integer default 1 |

### Colunas PENDENTES (migration 2026_03_16_000010 — Sprint 3 — NÃO executada ainda)
| Coluna | Tipo | Descrição |
|--------|------|----------|
| RUBRICA_CAMADA | integer | 1=estrutural, 2=adicional, 3=variável |
| RUBRICA_CALCULO | string | tabela_salarial, fixo, percentual_base, etc. |
| RUBRICA_INCIDE_PREV | integer | 0/1 incide previdência |
| RUBRICA_INCIDE_IRRF | integer | 0/1 incide IRRF |
| RUBRICA_INCIDE_FGTS | integer | 0/1 incide FGTS |
| RUBRICA_SAGRES_COD | string | código SAGRES/TCE |
| RUBRICA_ORDEM | integer | ordem de exibição no holerite |

> ⚠️ `RubricasCatalogoSeeder` só pode rodar APÓS a migration 000010 ser criada e executada.

---

## VINCULO (colunas após migration 000010)
| Coluna | Tipo |
|--------|------|
| VINCULO_ID | integer PK autoincrement |
| VINCULO_NOME | string(100) |
| VINCULO_SIGLA | string(20) nullable |
| VINCULO_ATIVO | integer default 1 |
| VINCULO_TIPO | string(30) nullable |
| VINCULO_REGIME | string(10) nullable |
| VINCULO_FGTS | boolean nullable |
| VINCULO_INSS | boolean nullable |
| VINCULO_IRRF | boolean nullable |
| VINCULO_ANUENIO_PCT | decimal(5,2) nullable |

### ❌ NÃO EXISTEM no VINCULO
```
VINCULO_DESCRICAO  → não foi adicionado em nenhuma migration
created_at / updated_at
```

---

## RESUMO — INCONSISTÊNCIAS ENCONTRADAS NOS DOCUMENTOS

### AUTOCADASTRO_ANALISE.md — campos errados no INSERT PESSOA
| Campo usado | Problema | Usar em vez disso |
|-------------|---------|------------------|
| PESSOA_SEXO_ID | NÃO existe | PESSOA_SEXO |
| PESSOA_RG_ORG_EMISSOR | NÃO existe | PESSOA_ORG_EMISSOR + PESSOA_RG_EXPEDIDOR |
| ESTADO_CIVIL_ID | NÃO existe | ESTADO_CIVIL + PESSOA_ESTADO_CIVIL |
| PESSOA_GRAU_INSTRUCAO | NÃO existe | PESSOA_ESCOLARIDADE + ESCOLARIDADE_ID |
| PESSOA_RACA_COR | NÃO existe | PESSOA_RACA |
| PESSOA_EMAIL | NÃO existe na PESSOA | salvar em USUARIO_EMAIL |
| PESSOA_TELEFONE | NÃO existe na PESSOA | salvar em CONTATO futuramente |
| PESSOA_ENDERECO_NUMERO | NÃO existe | sem equivalente — ignorar |
| PESSOA_BAIRRO | NÃO existe como string | usar BAIRRO_ID (FK integer) |
| DEPENDENTE.DEPENDENTE_NOME etc | NÃO existem no DEPENDENTE legado | usar PESSOA_DEPENDENTE |
| created_at/updated_at em USUARIO | NÃO existe | remover |
| created_at/updated_at em USUARIO_PERFIL | NÃO existe | remover |
| created_at/updated_at em FUNCIONARIO | NÃO existe | remover |
| NOTIFICACAO_CORPO | NÃO existe | usar NOTIFICACAO_BODY |
| DESTINATARIO_TIPO / DESTINATARIO_PERFIL | NÃO existem | notificar por USUARIO_ID individualmente |
| LIDA em NOTIFICACAO | NÃO existe | usar NOTIFICACAO_LIDA |

### SPRINT_3_MOTOR_FOLHA.md — verificar seeds
Os seeds especificados nas Partes 2-7 usam campos de FUNCIONARIO e VINCULO.
Verificar se os campos adicionados nas Partes 1 (migration do motor) foram rodados
antes de executar os seeds — caso contrário os seeds também vão falhar com os mesmos erros.

### Impacto em outros documentos
O PLANO_MESTRE_V2.md referencia os mesmos endpoints — as correções acima
se aplicam a qualquer código que faça INSERT/UPDATE nas tabelas PESSOA, USUARIO,
FUNCIONARIO, DEPENDENTE.

---

## REGRA PARA O ANTYGRAVITY

> Antes de qualquer INSERT ou UPDATE, consulte este arquivo para verificar
> se os campos existem. Em caso de dúvida, NÃO inventar nomes de campos —
> reportar ao auditor (Claude) para verificar nas migrations.

*MAPA_CAMPOS_TABELAS.md | GENTE v3 | RR TECNOL | 16/03/2026*
*Fonte: varredura de todas as 60+ migrations do projeto*

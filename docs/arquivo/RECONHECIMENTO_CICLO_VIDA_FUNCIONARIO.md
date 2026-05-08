# Reconhecimento de esquema — ciclo de vida do servidor (GENTE v3)

**Objetivo:** mapa técnico para *Super-Seeder* fiel, alinhado a `docs/BUSINESS_RULES.md` e compliance (PCCV / LRF / continuidade do serviço).  
**Fonte:** modelos Eloquent, migrations e rotas do repositório. **Nota:** restrições `NOT NULL` exatas no **SQL Server** de produção devem ser confirmadas com `INFORMATION_SCHEMA` ou `sp_help` no ambiente alvo — o legado pode divergir das migrations incrementais.

---

## 1. Pilar cadastral — *Quem ele é?*

### 1.1 Pessoa física (`PESSOA`)

O cadastro de pessoa concentra identificação civil. O modelo `App\Models\Pessoa` declara, entre outros:

| Área | Campos relevantes (nomes reais) |
|------|---------------------------------|
| Identidade | `PESSOA_NOME`, `PESSOA_DATA_NASCIMENTO`, `PESSOA_SEXO`, `PESSOA_NOME_MAE`, `PESSOA_NOME_PAI` |
| CPF | `PESSOA_CPF_NUMERO` (string formatada no fluxo de criação) |
| RG | `PESSOA_RG_NUMERO`, `PESSOA_RG_EXPEDIDOR`, `PESSOA_RG_EXPEDICAO`, `UF_ID_RG` |
| Título | `PESSOA_TITULO_NUMERO`, `PESSOA_TITULO_ZONA`, `PESSOA_TITULO_SECAO`, `UF_ID_TITULO` |
| PIS | `PESSOA_PIS_PASEP` (no *fillable*; pode haver documento vinculado via relação de documentos) |

Migrations adicionam colunas faltantes com padrão **nullable** em vários casos — não assumir obrigatoriedade universal sem inspecionar o BD.

### 1.2 Funcionário (`FUNCIONARIO`)

Liga `PESSOA_ID` ao vínculo de emprego público.

| Campo | Papel |
|-------|--------|
| `PESSOA_ID` | FK para `PESSOA` |
| `FUNCIONARIO_MATRICULA` | Identificador funcional (texto; padrão institucional **não** está centralizado no código) |
| `FUNCIONARIO_DATA_INICIO` / `FUNCIONARIO_DATA_FIM` | Admissão / término (fim no futuro ou nulo = ativo no filtro de “ativos”) |
| `FUNCIONARIO_TIPO_ENTRADA` / `FUNCIONARIO_TIPO_SAIDA` | Catálogo (`TabelaGenerica` / RTG) |
| `CARGO_ID` | Usado no fluxo `POST /funcionarios` (cadastro completo) — ver migrations `add_progressao_columns` etc. |
| `CARREIRA_ID`, `FUNCIONARIO_CLASSE`, `FUNCIONARIO_REFERENCIA` | Magistério / progressão (PMSLz) |

**Não** foi localizada, no repositório, coluna canónica `STATUS_VINCULO` (Ativo/Afastado/Aposentado) no modelo *Funcionario*. O sistema usa tipicamente:

- `FUNCIONARIO_DATA_FIM` (e comparação com “hoje”), e  
- lotação com `LOTACAO_DATA_FIM` nula para “em exercício”.

### 1.3 Validações aplicadas no código

- **CPF** (`POST /api/v3/funcionarios` em `routes/funcionarios.php`): regex `^\d{3}\.?\d{3}\.?\d{3}\-?\d{2}$`, normalização para dígitos, formato `999.999.999-99`, **unicidade** em `PESSOA`.
- **Matrícula:** **não** há função de dígito verificador da matrícula no *grep* geral; uso como **string** (busca `like`, *seed* PMSLz com padrão tipo `2007-0006`).

**SISFOLHA / folha:** o GENTE espelha dados; a “pessoa completa” para o legado de folha exige, no mínimo, pessoa + funcionário + CPF coerente + matrícula única no processo de RH que vocês forem integrar.

---

## 2. Pilar de lotação — *Onde trabalha?*

### 2.1 Tabela de ligação: `LOTACAO`

`App\Models\Lotacao`:

| Campo | Uso |
|-------|-----|
| `FUNCIONARIO_ID` | Servidor |
| `SETOR_ID` | Unidade organizacional (hierarquia: `SETOR` → `UNIDADE` → secretaria) |
| `VINCULO_ID` | Tipo de vínculo (`VINCULO` — ex.: EFT, PROF) |
| `LOTACAO_DATA_INICIO` / `LOTACAO_DATA_FIM` | Vígencia da lotação (fim **nulo** = lotação aberta) |
| `LOTACAO_TIPO_FIM` / `LOTACAO_OBSERVACAO` | Evento de término / notas |
| `LOTACAO_DESVIO_FUNCAO` | Flag/relaciono (conforme modelo) |

### 2.2 Múltiplas lotações

Sim, no modelo: `Funcionario::lotacoes()` é `hasMany(Lotacao)`. A API e relatórios costumam considerar **lotação ativa** com `LOTACAO_DATA_FIM` **null** (ver `escala_trabalho`, `funcionarios` — contagem *limbo*).

**Crítico para seed “SEMED x SEMUS”:** cada servidor de demonstração deve ter **pelo menos** uma `LOTACAO` ativa cujo `SETOR_ID` vincule à unidade desejada (educação vs saúde).

---

## 3. Pilar da jornada e ponto

### 3.1 Carga horária

- **Cargo:** `CARGO.CARGO_CARGA_HORARIA` aparece no `GET` de apoio ao cadastro de funcionário (`routes/funcionarios.php` — *cargos* com `CARGO_CARGA_HORARIA`).
- **Funcionário (opcional):** `FUNCIONARIO.FUNCIONARIO_CARGA_HORARIA` (quando a coluna existe) — reutilizada em regras de **escala** (ex. regência 20/24/40h em `routes/escala_trabalho.php`).

Não existe, no repositório, tabela canónica `JORNADA_TRABALHO` com esse nome. Há `JORNADA_LEDGER` (banco de horas / *ledger*), usada em *gestor* e *SidebarCoverageSeeder* — outro domínio (controle de saldo de horas), não substitui `CARGO` para PCCV.

### 3.2 Configuração de ponto (expectativa de horário)

Tabela `PONTO_CONFIG_FUNCIONARIO` (migrations `2026_03_05_150000` e `2026_03_30_000020`):

| Coluna | Significado |
|--------|-------------|
| `FUNCIONARIO_ID` | Um registro por servidor (unique) |
| `REGIME` | `2_batidas` ou `4_batidas` (null = padrão global) |
| `HORA_ENTRADA` / `HORA_SAIDA` | Expectativa `HH:MM` |
| `TOLERANCIA` | **Minutos** de janela |
| `INTERVALO_ALMOCO` | Minutos |
| `JORNADA_FINANCEIRA_HORAS` / `JORNADA_FINANCEIRA_OBS` | Acordo / observação para folha |

Criação em conjunto com `POST /funcionarios` (validação de regime e horas no mesmo ficheiro).

### 3.3 Batida realizada (espelho do ponto)

Tabela `REGISTRO_PONTO` (`2026_02_22_000003`, modelo `RegistroPonto`):

- `FUNCIONARIO_ID`, `TERMINAL_ID` (nullable), `REGISTRO_DATA_HORA`, `REGISTRO_TIPO` (ENTRADA|PAUSA|RETORNO|SAIDA), `REGISTRO_ORIGEM`, `REGISTRO_NSR`, `REGISTRO_OBSERVACAO`.
- O **horário esperado** não fica duplicado na mesma linha: compara-se com `PONTO_CONFIG_FUNCIONARIO` (e regras de cálculo) no *service* de apuração, quando existir.

**Terceirizados:** fluxo paralelo `TERCEIRO_FREQUENCIA` / `ponto_terceirizado.php` — **não** confundir com servidores efetivos.

---

## 4. O que considerar “válido e completo” no GENTE (mínimo auditável)

| Nível | Condições |
|-------|------------|
| Mínimo cadastro | `PESSOA` com nome; `FUNCIONARIO` com `PESSOA_ID`, `FUNCIONARIO_DATA_INICIO`, `FUNCIONARIO_MATRICULA` útil; CPF validado se informado |
| Lotação utilizável | ≥1 `LOTACAO` com `LOTACAO_DATA_FIM` nula e `SETOR_ID` válido |
| Cargo / PCCV | `CARGO_ID` (e, quando exigido, `PCCV_ID` no cargo) coerente com a carreira (Lei 4.928/2008 no magistério) — ver `CARGO.PCCV_ID` e `PccvDominioSeeder` |
| Ponto (opcional) | `PONTO_CONFIG_FUNCIONARIO` se a tela de ponto for usada; caso contrário, o servidor ainda pode existir sem linha nessa tabela (nulls = padrão global) |

---

## 5. Conflitos de compliance (exemplo enunciado)

- Professor (PCCV **Magistério**) com **carga 40h** incompatível com o quantitativo do cargo/escala: a regra de **regência** na rota de escala restringe 20/24/40h conforme o caso; o **Super-Deeder** deve alinhar `CARGO_CARGA_HORARIA` + `FUNCIONARIO` + `TURNO`/escala ao **mesmo** enquadramento jurídico, não misturar “papel de 20h” com carga 40h sem regra explícita.

---

## 6. Próximos passos técnicos recomendados

1. Script único (SQL ou Artisan) no ambiente **staging** listando `NOT NULL` e FKs reais de `PESSOA`, `FUNCIONARIO`, `LOTACAO`, `CARGO`, `PONTO_CONFIG_FUNCIONARIO`, `REGISTRO_PONTO`.
2. Mapear **matrícula** oficial (máscara SISFOLHA) com a equipe de folha — hoje o código trata como string.
3. Super-Seeder: derivar lote a partir de `VINCULO` + `UNIDADE_SIGLA` + `PCCV_DOMINIO` + `CARGO_CARGA_HORARIA` já alinhados aos seeds PMSLz.

*Documento gerado por reconhecimento estático de código; revisar contra o banco de produção antes de *go-live*.*

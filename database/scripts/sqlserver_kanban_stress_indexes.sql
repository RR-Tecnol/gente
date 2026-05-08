/*
  Índices sugeridos para a rota GET /escala-trabalho (gente/routes/escala_trabalho.php):
  partida em ESCALA filtrada por competência (Y-m) e SETOR_ID, depois juntas a
  DETALHE_ESCALA, FUNCIONARIO, PESSOA, LOTACAO, SETOR, DETALHE_ESCALA_ITEM, TURNO.

  Validar no *plan* do SQL Server (cardinalidade, fragmentação, janela de manutenção).
  SQL Server 2016+.
*/

IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'ESCALA' AND i.name = N'IX_ESCALA_COMPETENCIA_SETOR_INC')
    CREATE NONCLUSTERED INDEX IX_ESCALA_COMPETENCIA_SETOR_INC
    ON ESCALA (ESCALA_COMPETENCIA, SETOR_ID)
    INCLUDE (ESCALA_ID);

IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'DETALHE_ESCALA' AND i.name = N'IX_DETALHE_ESCALA_ESCALA_FUNC')
    CREATE NONCLUSTERED INDEX IX_DETALHE_ESCALA_ESCALA_FUNC
    ON DETALHE_ESCALA (ESCALA_ID, FUNCIONARIO_ID)
    INCLUDE (DETALHE_ESCALA_ID);

IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'DETALHE_ESCALA' AND i.name = N'IX_DETALHE_ESCALA_FUNCIONARIO')
    CREATE NONCLUSTERED INDEX IX_DETALHE_ESCALA_FUNCIONARIO
    ON DETALHE_ESCALA (FUNCIONARIO_ID)
    INCLUDE (ESCALA_ID, DETALHE_ESCALA_ID);

-- INCLUDE com TURNO_ID e, se coluna existir, TURNO_SIGLA (reduz *Key Lookup* no join com TURNO)
IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'DETALHE_ESCALA_ITEM' AND i.name = N'IX_DEI_DETALHE_DATA_TURNO')
    CREATE NONCLUSTERED INDEX IX_DEI_DETALHE_DATA_TURNO
    ON DETALHE_ESCALA_ITEM (DETALHE_ESCALA_ID, DETALHE_ESCALA_ITEM_DATA)
    INCLUDE (TURNO_ID, TURNO_SIGLA);

-- Junta de lotação ativa: LOTACAO_DATA_FIM nulo + data do funcionário. Ajuste INCLUDE ao plano.
IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'LOTACAO' AND i.name = N'IX_LOTACAO_FUNC_FIM')
    CREATE NONCLUSTERED INDEX IX_LOTACAO_FUNC_FIM
    ON LOTACAO (FUNCIONARIO_ID, LOTACAO_DATA_FIM)
    INCLUDE (SETOR_ID, LOTACAO_ID);

-- Opcional, se o otimizador filtrar só ativos: WHERE LOTACAO_DATA_FIM IS NULL
-- (confirmar se a coluna existe e regra de SARGability no SQL gerado)

IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'FUNCIONARIO' AND i.name = N'IX_FUNC_PESSOA')
    CREATE NONCLUSTERED INDEX IX_FUNC_PESSOA
    ON FUNCIONARIO (PESSOA_ID)
    INCLUDE (FUNCIONARIO_ID, FUNCIONARIO_DATA_FIM);

IF NOT EXISTS (SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = N'FUNCIONARIO' AND i.name = N'IX_FUNC_MATRICULA')
    CREATE NONCLUSTERED INDEX IX_FUNC_MATRICULA
    ON FUNCIONARIO (FUNCIONARIO_MATRICULA)
    INCLUDE (PESSOA_ID, FUNCIONARIO_ID);

/*
  PESSOA.PESSOA_ID costuma ser chave de junção; só adicionar índice extra se
  o plano mostrar busca por nome/CPF na mesma requisição.

  AUDIT_LOG: volume alto — índice por CREATED_AT / TABELA / USUARIO_ID conforme
  as consultas reais (organograma_v3.php, suporte, etc.). Ajuste nomes de
  colunas (id vs AUDIT_LOG_ID, created_at).
*/

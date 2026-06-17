-- Verificação pós-SuperSeeder (SQL Server) — ajuste nomes se o dicionário local divergir.
-- O prompt original com SETOR WHERE UNIDADE_SIGLA está incorreto: sigla fica em UNIDADE, não em SETOR.

-- 1) Contagem total de servidores (esperado após stress: 30.000 + base já existente)
SELECT COUNT(*) AS total_funcionario FROM FUNCIONARIO;

-- 2) Lotação em setores vinculados à SEMED (esperado ~15.000 das linhas de stress, mais lotações legadas)
SELECT COUNT(*) AS lotacoes_setores_semed
FROM LOTACAO AS l
INNER JOIN SETOR AS s ON s.SETOR_ID = l.SETOR_ID
INNER JOIN UNIDADE AS u ON u.UNIDADE_ID = s.UNIDADE_ID
WHERE u.UNIDADE_SIGLA = N'SEMED';

-- 2b) Lotes só do setor "primeiro" da SEMED (útil se quiser aproximar o lote 15k do seeder)
-- SELECT s.SETOR_ID, s.SETOR_NOME, COUNT(*) AS n
-- FROM LOTACAO AS l
-- INNER JOIN SETOR AS s ON s.SETOR_ID = l.SETOR_ID
-- INNER JOIN UNIDADE AS u ON u.UNIDADE_ID = s.UNIDADE_ID
-- WHERE u.UNIDADE_SIGLA = N'SEMED'
-- GROUP BY s.SETOR_ID, s.SETOR_NOME
-- ORDER BY n DESC;

-- 3) Últimos registros de auditoria (migration padrão: colunas MAIÚSC. + id/ created_at do timestamps)
SELECT TOP 5
    [id], [USUARIO_ID], [ACAO], [TABELA], [DADOS_NOVOS], [IP], [created_at]
FROM [AUDIT_LOG]
ORDER BY [created_at] DESC;

-- 3b) Provar JSON: MOTIVO_ALTERACAO_ID no payload (caminho JSON varia; OPENJSON requer 2016+ e permissões)
-- SELECT TOP 3 DADOS_NOVOS
-- FROM AUDIT_LOG
-- WHERE DADOS_NOVOS LIKE N'%MOTIVO_ALTERACAO_ID%'
-- ORDER BY [created_at] DESC;

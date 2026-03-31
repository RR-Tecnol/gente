# BUGS E SUGESTÕES — RELATÓRIO DO ESTAGIÁRIO
**Data do relatório:** 16/03/2026
**Testador:** Estagiário (testes funcionais manuais)
**Classificação:** Claude (Tech Lead Auditor)
**Status:** ⏳ Aguardando validação técnica — NÃO testar ainda

> Este documento organiza os 21 itens reportados pelo estagiário em categorias.
> Bugs críticos que impedem a PoC foram priorizados no topo.
> Sugestões de melhoria estão separadas de bugs reais.

---

## 🔴 BUGS CRÍTICOS — impedem uso básico

### BUG-EST-01 — Downloads quebrados em múltiplos módulos
**Itens:** 1, 17
**Módulos:** Declarações e Requerimentos, Contratos e Vínculos (documentos, histórico, contrato vigente)
**Sintoma:** Aparece "baixando" mas nenhum arquivo é gerado
**Causa provável:** Endpoint retorna JSON em vez de blob, ou `responseType: 'blob'` ausente no axios, ou storage Laravel não configurado para dev
**Arquivos a investigar:** `DeclaracoesRequerimentosView.vue`, `routes/web.php` endpoints de download

---

### BUG-EST-02 — Dependentes retorna 404 ao salvar
**Item:** 3
**Módulo:** Perfil do funcionário — aba Dependentes
**Sintoma:** "Erro: Request failed with status code 404"
**Causa provável:** Rota `POST /api/v3/dependentes` não registrada ou path incorreto no Vue
**Arquivos a investigar:** `routes/web.php`, `PerfilFuncionarioView.vue`

---

### BUG-EST-03 — PDF de holerites não funciona
**Itens:** 4 e 10 (duplicado pelo estagiário)
**Módulo:** Meus Holerites / Holerites Recentes
**Status:** Já documentado como BUG-S2-09 a BUG-S2-11 no SPRINT_3_MOTOR_FOLHA.md
**Causa:** Template errado, líquido recalculado, bases zeradas — ver Parte 18

---

### BUG-EST-04 — `confirm()` nativo do browser em ações críticas
**Itens:** 7, 19
**Módulos:** Autocadastro (revogar link), Gerir Progressões (aprovar)
**Sintoma:** Dialog padrão do browser "localhost:5173 diz..." em vez de modal do sistema
**Causa:** Código usa `window.confirm()` em vez de modal Vuetify/customizado
**Impacto:** UX quebrada, inconsistente com identidade visual do sistema
**Arquivos a investigar:** `AutocadastroView.vue`, `ProgressaoAdminView.vue`

---

### BUG-EST-05 — Sistema trava ao aprovar progressão
**Item:** 19
**Módulo:** Gerir Progressões
**Sintoma:** Ao clicar OK no confirm(), o sistema trava e a progressão volta para elegível
**Causa provável:** BUG-PROG-03 já documentado — servidor no teto da carreira, `proxima_referencia = null`, endpoint falha silenciosamente
**Arquivos a investigar:** `routes/progressao_funcional.php` — POST /progressao-funcional/aplicar/{id}

---

### BUG-EST-06 — Ponto eletrônico: clicar em falta não mostra detalhes
**Item:** 14
**Módulo:** Ponto Eletrônico
**Sintoma:** Ao clicar em dias com falta, nenhum detalhe é exibido
**Causa provável:** Handler `@click` não propagando ou dado não carregado no estado
**Arquivos a investigar:** `PontoEletronicoView.vue`

---

### BUG-EST-07 — Filtro ativo/inativo muda por página
**Item:** 9
**Módulo:** Funcionários
**Sintoma:** Contagem de ativos/inativos varia de página para página (ex: pág 1 = 15 ativos, pág 5 = 3 ativos)
**Causa:** Computed de contagem calculado sobre a página atual em vez do conjunto total
**Correção:** Mover contagem para o backend como agregação separada do resultado paginado


---

## 🟠 BUGS DE UX — afetam usabilidade mas não impedem uso

### BUG-EST-08 — Organograma com múltiplos problemas
**Item:** 8
**Módulo:** Organograma
**Problemas identificados:**
1. Linha estranha embaixo do Hospital Municipal → bug de renderização CSS/SVG
2. Busca imprecisa — "TI" retorna "Rede de Alta e Média Complexidade" → algoritmo filtra substring em qualquer nó sem considerar hierarquia
3. Coordenação some ao buscar pai → filtro não preserva descendentes do nó encontrado
4. Modo Card vs Árvore confuso → comportamento não documentado na interface
5. Acentos faltando em alguns nós (ex: "média") → encoding ou dado errado no seed
6. **Aba de excluídos ausente** → sem soft delete com restauração

---

### BUG-EST-09 — Autocadastro sem visibilidade dos dados aprovados
**Item:** 5
**Módulo:** Autocadastro
**Sintoma:** Não há opção de "Ver Perfil" após aprovação
**Correção:** Botão "Ver Perfil" no card do autocadastro aprovado deve redirecionar para `/funcionarios/{id}`

---

### BUG-EST-10 — Autocadastro sem indicação dos campos obrigatórios
**Item:** 6
**Módulo:** Autocadastro
**Problemas:**
- Sem indicador visual de campos obrigatórios
- Sem validação em tempo real de PIS/PASEP (11 dígitos — crítico para eSocial)
- Sem validação de CEP com busca automática via ViaCEP
- Sem máscara de telefone
- Sem instrução sobre foto do documento exigida
**Impacto:** Dados incompletos chegam ao RH, gerando retrabalho

---

### BUG-EST-11 — Exoneração com múltiplos problemas de UX
**Item:** 21
**Módulo:** Exoneração / Rescisão
**Problemas:**
1. Pesquisa só ativa ao digitar → deveria listar servidores ao abrir (com paginação)
2. Campo "data do ato" apaga tudo ao deletar → bug de input com máscara
3. Botão "Recalcular" sem tooltip/label explicativo → confuso para o operador
4. Sem sugestão de portaria baseada nas últimas cadastradas

---

### BUG-EST-12 — Progressão funcional sem visibilidade para o gestor
**Item:** 18
**Módulo:** Progressão Funcional
**Problema:** Gestor vê apenas a própria progressão, não a da equipe
**Correção:** Liberar endpoint `/progressao-funcional/admin` para perfil gestor com filtro por setor

---

### BUG-EST-13 — Gerir progressões sem histórico de aprovações
**Item:** 19
**Módulo:** Gerir Progressões
**Problema:** Sem registro de quem aprovou/rejeitou, quando e com qual motivo
**Correção:** Exibir `HISTORICO_FUNCIONAL` com tipo = 'progressao', mostrando usuário que aplicou e data

---

### BUG-EST-14 — Férias sem controle de sobreposição por setor
**Item:** 15
**Módulo:** Férias e Afastamentos
**Problema:** Sistema não avisa quando múltiplos servidores do mesmo setor estão de férias no mesmo período
**Correção futura:** Ao agendar, verificar % de ausência do setor no período e alertar se ultrapassar limite configurável

---

### BUG-EST-15 — Banco de horas sem visão da equipe para o gestor
**Item:** 16
**Módulo:** Banco de Horas
**Problema:** Gestor vê apenas o próprio banco, não o da equipe
**Correção:** Adicionar filtro de funcionário e visão consolidada da equipe com métricas por semestre/ano


---

## 🟡 MELHORIAS DE UX — boas sugestões, implementar após PoC

### MEL-EST-01 — Sidebar colapsável com modo ícones
**Item:** 11
**Sugestão do estagiário:** Botão dentro da sidebar que minimiza para modo só-ícones
**Avaliação:** Excelente sugestão — padrão moderno de dashboards. Já especificado
na Parte 21 do SPRINT_3_MOTOR_FOLHA.md (configStore + flags na sidebar).
**Implementar:** Pós-Sprint 3, junto com a implementação do modo sem ponto.

---

### MEL-EST-02 — Nome do módulo maior no breadcrumb
**Item:** 12
**Sugestão:** Aumentar tamanho do texto do módulo no topbar
**Correção simples:** Em DashboardLayout.vue, `.breadcrumb-label` de 15px → 17px, font-weight 700 → 800

---

### MEL-EST-03 — Histórico de ações para o gestor (audit feed)
**Item:** 13
**Sugestão do estagiário:** Feed visível de tudo que aconteceu no sistema — cadastros, aprovações, rejeições
**Avaliação:** Muito bem pensado. A tabela `AUDIT_LOG` já existe (SEC-04 já documentado),
o middleware `AuditLog.php` já está criado — só não grava nada ainda.
**Implementar:** Interface de visualização do AUDIT_LOG com filtro por tipo de ação,
usuário responsável e período. Alta valor para o gestor.

---

### MEL-EST-04 — Dashboard consolidado de frequência
**Item:** 14
**Sugestão do estagiário:** Em vez de notificação por pessoa, um card de resumo:
"X faltaram hoje, X chegaram no horário, X chegaram atrasados"
**Avaliação:** Perfeita — alinhado com a filosofia do motor (batch, não individual).
Adicionar card no Dashboard principal ou no Portal do Gestor.
Calcular via script assíncrono, não em tempo real — não trava o sistema.

---

### MEL-EST-05 — Telas com animações (progressão funcional, gerir progressões)
**Itens:** 18, 19
**Sugestão:** Adicionar transições/animações nos cards de progressão
**Implementar:** Transições CSS Vue (`<transition>`, `<transition-group>`) nos cards.
Simples de adicionar, melhora percepção de qualidade.

---

### MEL-EST-06 — Lentidão nas telas de carregamento
**Item:** 2
**Causa raiz:** Sem cache, sem paginação real, queries pesadas (N+1 documentados)
**Não é só UX** — é consequência dos bugs de performance já mapeados.
Corrige automaticamente quando: paginação for implementada, N+1 do PERF-01/02 corrigidos,
e o motor de folha for otimizado (batch).

---

### MEL-EST-07 — Filtros em Gerir Progressões
**Item:** 19
**Sugestão:** Adicionar filtros de Cargo/Carreira, Classe, Referência Atual
**Implementar:** Dropdowns de filtro no topo da listagem, filtrando o computed local
sem nova chamada ao backend.

---

## 📊 AVALIAÇÃO GERAL DO ESTAGIÁRIO

**Pontos fortes da análise:**
- Identificou corretamente que `confirm()` nativo é inconsistente com o sistema
- A sugestão de dashboard consolidado de frequência é **a melhor sugestão do lote**
- Percebeu que gestor precisa de visão da equipe (banco de horas, progressão, ponto)
- O histórico de ações como controle do gestor é muito alinhado com o que gestão pública precisa

**Pontos a desenvolver:**
- Alguns itens foram reportados em duplicata (4 e 10 são o mesmo bug)
- A lógica da progressão funcional (interstício, nota mínima) deve ser explicada
  na interface — não é falta do sistema, é falta de documentação visível para o usuário

**Nota geral:** Bom olhar para usabilidade real. Pensa no fluxo do gestor,
não só no funcionamento técnico do sistema.

---

## 📋 ORDEM DE PRIORIDADE PARA QUANDO FOR TESTAR

### Sprint 2 — junto com margem e PDF
1. BUG-EST-04 — `confirm()` nativo (revogar + aprovar progressão)
2. BUG-EST-05 — Sistema trava ao aprovar progressão
3. BUG-EST-07 — Filtro ativo/inativo por página
4. BUG-EST-02 — Dependentes 404
5. BUG-EST-01 e 03 — Downloads + PDF holerites

### Sprint 3 — junto com motor de folha
6. BUG-EST-06 — Ponto: clique em falta sem detalhes
7. BUG-EST-09 — Autocadastro: ver perfil após aprovação
8. BUG-EST-10 — Autocadastro: validação PIS/PASEP e campos obrigatórios
9. BUG-EST-12 — Progressão: visão da equipe para gestor
10. BUG-EST-13 — Progressão: histórico de aprovações

### Pós-PoC — melhorias de UX
11. MEL-EST-01 — Sidebar colapsável
12. MEL-EST-02 — Breadcrumb maior
13. MEL-EST-03 — Audit feed do gestor
14. MEL-EST-04 — Dashboard consolidado de frequência
15. BUG-EST-08 — Organograma (múltiplos problemas)
16. BUG-EST-14 — Férias: sobreposição por setor
17. BUG-EST-15 — Banco de horas: visão da equipe
18. MEL-EST-05 — Animações nas telas de progressão
19. BUG-EST-11 — Exoneração: múltiplos problemas UX
20. MEL-EST-07 — Filtros em Gerir Progressões

---

*BUGS_UX_ESTAGIARIO.md | GENTE v3 | RR TECNOL | 16/03/2026*
*21 itens reportados → 7 bugs críticos, 8 bugs de UX, 7 melhorias*
*Classificado por: Claude (Tech Lead Auditor)*

# SIDEBAR — REORGANIZAÇÃO E MÓDULOS FALTANTES
**Data:** 16/03/2026 | **Executor:** Antygravity | **Auditor:** Claude

> Leia este documento integralmente antes de qualquer ação.
> Arquivo a editar: `resources/gente-v3/src/layouts/DashboardLayout.vue`
> Seção a editar: const ALL_NAV_ITEMS — substituir o array inteiro.
> NÃO alterar nada fora do ALL_NAV_ITEMS e do routeMap.

---

## DIAGNÓSTICO

### 8 módulos com rota no router mas SEM entrada na sidebar

| Rota | View | Roles |
|------|------|-------|
| `/rpps` | RPPSView | admin, rh |
| `/diarias` | DiariasView | admin, rh |
| `/acumulacao-cargos` | AcumulacaoView | admin, rh |
| `/transparencia` | TransparenciaView | admin, rh |
| `/pss` | PSSView | admin, rh |
| `/estagiarios` | EstagiariosView | admin, rh |
| `/terceirizados` | TerceirizadosView | admin, rh |
| `/sagres-tce` | SagresView | admin, rh |

### 6 módulos de configuração SEM entrada na sidebar

| Rota | View | Roles |
|------|------|-------|
| `/configuracao-sistema` | ConfiguracaoSistemaView | admin |
| `/parametros-financeiros` | ParametroFinanceiroView | admin |
| `/tabelas-auxiliares` | TabelasAuxiliaresView | admin |
| `/turnos` | TurnosView | admin |
| `/feriados` | FeriadosView | admin |
| `/vinculos` | VinculosView | admin |
| `/eventos-folha` | EventosView | admin |

### Problema de agrupamento
Módulos misturados entre perfis — gestor, RH e admin tudo junto.
Resultado: sidebar confusa, usuário não encontra o que precisa.

---

## SOLUÇÃO — NOVO ALL_NAV_ITEMS

Princípio de organização:
- **Minha Área** — o que o funcionário vê sobre si mesmo
- **Minha Equipe** — o que o gestor vê sobre sua equipe
- **Recursos Humanos** — operações do RH sobre servidores
- **Financeiro/Folha** — financeiro, folha, previdência
- **Compliance** — obrigações legais e transparência
- **Desenvolvimento** — capacitação, avaliação, pesquisa
- **Comunicação** — agenda, comunicados, ouvidoria
- **Configurações** — sistema, tabelas, parâmetros (admin)
- **ERP/Fiscal** — pós-contrato (admin)


---

## NOVO ALL_NAV_ITEMS — substituir integralmente no DashboardLayout.vue

```js
const ALL_NAV_ITEMS = [

  // ═══════════════════════════════════════════════════════════════
  // VISÃO GERAL — todos os perfis
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Visão Geral' },
  { type: 'item', to: '/dashboard',
    label: 'Dashboard', icon: 'dashboard', roles: [] },

  // ═══════════════════════════════════════════════════════════════
  // MINHA ÁREA — o que o funcionário vê sobre si mesmo
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Minha Área' },
  { type: 'item', to: '/meu-perfil',
    label: 'Meu Perfil', icon: 'user', roles: [] },
  { type: 'item', to: '/ponto',
    label: 'Ponto Eletrônico', icon: 'clock', roles: [],
    ocultarSemPonto: true },
  { type: 'item', to: '/meus-holerites',
    label: 'Meus Holerites', icon: 'money', roles: [] },
  { type: 'item', to: '/ferias-licencas',
    label: 'Férias e Licenças', icon: 'beach', roles: [] },
  { type: 'item', to: '/banco-horas',
    label: 'Banco de Horas', icon: 'hourglass', roles: [] },
  { type: 'item', to: '/declaracoes-requerimentos',
    label: 'Declarações', icon: 'doc', roles: [] },
  { type: 'item', to: '/progressao-funcional',
    label: 'Minha Progressão', icon: 'trending', roles: [] },

  // ═══════════════════════════════════════════════════════════════
  // MINHA EQUIPE — gestor de setor
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Minha Equipe',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/portal-gestor',
    label: 'Portal do Gestor', icon: 'tie-person',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/organograma',
    label: 'Organograma', icon: 'organogram',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/escala-trabalho',
    label: 'Escala de Trabalho', icon: 'calendar',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/escala-matriz-v3',
    label: 'Escalas Hospitalares', icon: 'calendar-week',
    roles: ['admin', 'rh', 'gestor'],
    ocultarSemPonto: true },
  { type: 'item', to: '/substituicoes',
    label: 'Substituições', icon: 'swap',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/escala-sobreaviso',
    label: 'Sobreaviso', icon: 'phone',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/hora-extra',
    label: 'Hora Extra', icon: 'clock',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/plantoes-extras',
    label: 'Plantões Extras', icon: 'plus',
    roles: ['admin', 'rh', 'gestor'] },

]
```


```js
  // ═══════════════════════════════════════════════════════════════
  // RECURSOS HUMANOS — cadastros e gestão de pessoal
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Recursos Humanos',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/funcionarios',
    label: 'Funcionários', icon: 'users',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/autocadastro-gestao',
    label: 'Autocadastro', icon: 'user-plus',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/cargos-salarios',
    label: 'Cargos e Salários', icon: 'briefcase',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/contratos-vinculos',
    label: 'Contratos e Vínculos', icon: 'contract',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/progressao-admin',
    label: 'Gerir Progressões', icon: 'badge',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/exoneracao',
    label: 'Exoneração / Rescisão', icon: 'exit',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/pss',
    label: 'PSS / Concurso', icon: 'school',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/estagiarios',
    label: 'Estagiários', icon: 'student',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/terceirizados',
    label: 'Terceirizados', icon: 'briefcase',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/acumulacao-cargos',
    label: 'Acumulação de Cargos', icon: 'layers',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/diarias',
    label: 'Diárias', icon: 'map-pin',
    roles: ['admin', 'rh'] },

  // ── Frequência ──────────────────────────────────────────────────
  { type: 'section', label: 'Frequência',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/faltas-atrasos',
    label: 'Faltas e Atrasos', icon: 'warning',
    roles: ['admin', 'rh'], ocultarSemPonto: true },
  { type: 'item', to: '/abono-faltas',
    label: 'Abono de Faltas', icon: 'check',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/atestados-medicos',
    label: 'Atestados Médicos', icon: 'hospital',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/frequencia',
    label: 'Controle de Frequência', icon: 'clipboard-check',
    roles: ['admin', 'rh', 'gestor'],
    apenasModoSemPonto: true },

  // ── Saúde Ocupacional ───────────────────────────────────────────
  { type: 'section', label: 'Saúde Ocupacional',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/medicina-trabalho',
    label: 'Medicina do Trabalho', icon: 'stethoscope',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/seguranca-trabalho',
    label: 'Segurança do Trabalho', icon: 'shield',
    roles: ['admin', 'rh'] },

```

```js
  // ═══════════════════════════════════════════════════════════════
  // FINANCEIRO E FOLHA
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Financeiro e Folha',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/folha-pagamento',
    label: 'Folha de Pagamento', icon: 'credit-card',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/consignacao',
    label: 'Consignações', icon: 'account-balance',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/verba-indenizatoria',
    label: 'Verbas Indenizatórias', icon: 'money',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/beneficios',
    label: 'Benefícios', icon: 'gift',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/rpps',
    label: 'RPPS / IPAM', icon: 'bank',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/remessa-cnab',
    label: 'Remessa CNAB', icon: 'bank',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/gestao-declaracoes',
    label: 'Gestão de Declarações', icon: 'clipboard',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // COMPLIANCE — obrigações legais
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Compliance',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/esocial',
    label: 'eSocial', icon: 'cloud-upload',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/sagres-tce',
    label: 'SAGRES / TCE-MA', icon: 'chart',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/transparencia',
    label: 'Transparência Pública', icon: 'eye',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // DESENVOLVIMENTO — pessoas e capacitação
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Desenvolvimento',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/avaliacao-desempenho',
    label: 'Avaliação de Desempenho', icon: 'star',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/treinamentos',
    label: 'Treinamentos', icon: 'school',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/pesquisa-satisfacao',
    label: 'Pesquisa de Satisfação', icon: 'poll',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/pesquisa-admin',
    label: 'Gerenciar Pesquisas', icon: 'edit',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // COMUNICAÇÃO — todos os perfis
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Comunicação' },
  { type: 'item', to: '/agenda',
    label: 'Agenda', icon: 'agenda', roles: [] },
  { type: 'item', to: '/comunicados',
    label: 'Comunicados', icon: 'megaphone', roles: [] },
  { type: 'item', to: '/ouvidoria',
    label: 'Ouvidoria', icon: 'comment', roles: [] },
  { type: 'item', to: '/ouvidoria-admin',
    label: 'Painel Ouvidoria', icon: 'shield',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/relatorios',
    label: 'Relatórios', icon: 'chart',
    roles: ['admin', 'rh'] },

```

```js
  // ═══════════════════════════════════════════════════════════════
  // CONFIGURAÇÕES DO SISTEMA — admin apenas
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Configurações',
    roles: ['admin'] },
  { type: 'item', to: '/configuracoes',
    label: 'Configurações Gerais', icon: 'settings',
    roles: ['admin'] },
  { type: 'item', to: '/configuracao-sistema',
    label: 'Motor de Folha', icon: 'cpu',
    roles: ['admin'] },
  { type: 'item', to: '/parametros-financeiros',
    label: 'Parâmetros Financeiros', icon: 'sliders',
    roles: ['admin'] },
  { type: 'item', to: '/vinculos',
    label: 'Vínculos', icon: 'link',
    roles: ['admin'] },
  { type: 'item', to: '/turnos',
    label: 'Turnos', icon: 'clock',
    roles: ['admin'] },
  { type: 'item', to: '/feriados',
    label: 'Feriados', icon: 'calendar',
    roles: ['admin'] },
  { type: 'item', to: '/tabelas-auxiliares',
    label: 'Tabelas Auxiliares', icon: 'table',
    roles: ['admin'] },
  { type: 'item', to: '/eventos-folha',
    label: 'Eventos de Folha', icon: 'list',
    roles: ['admin'] },

  // ═══════════════════════════════════════════════════════════════
  // ERP / FISCAL — pós-contrato, admin apenas
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'ERP / Fiscal',
    roles: ['admin'] },
  { type: 'item', to: '/orcamento',
    label: 'Orçamento (PPA/LOA)', icon: 'budget',
    roles: ['admin'] },
  { type: 'item', to: '/execucao-despesa',
    label: 'Execução da Despesa', icon: 'pay',
    roles: ['admin'] },
  { type: 'item', to: '/contabilidade',
    label: 'Contabilidade (PCASP)', icon: 'book',
    roles: ['admin'] },
  { type: 'item', to: '/tesouraria',
    label: 'Tesouraria', icon: 'bank',
    roles: ['admin'] },
  { type: 'item', to: '/receita-municipal',
    label: 'Receita Municipal', icon: 'credit-card',
    roles: ['admin'] },
  { type: 'item', to: '/controle-externo',
    label: 'Controle Externo', icon: 'chart',
    roles: ['admin'] },
]
```

---

## ATUALIZAÇÃO DO routeMap

Adicionar as rotas faltantes ao `routeMap` no DashboardLayout.vue:

```js
// Adicionar ao routeMap existente:
'/rpps':              { label: 'RPPS / IPAM',              icon: 'bank' },
'/diarias':           { label: 'Diárias',                  icon: 'map-pin' },
'/acumulacao-cargos': { label: 'Acumulação de Cargos',     icon: 'layers' },
'/transparencia':     { label: 'Transparência Pública',     icon: 'eye' },
'/pss':               { label: 'PSS / Concurso',           icon: 'school' },
'/estagiarios':       { label: 'Estagiários',              icon: 'student' },
'/terceirizados':     { label: 'Terceirizados',            icon: 'briefcase' },
'/sagres-tce':        { label: 'SAGRES / TCE-MA',          icon: 'chart' },
'/configuracao-sistema':  { label: 'Motor de Folha',       icon: 'cpu' },
'/parametros-financeiros':{ label: 'Parâmetros Financeiros', icon: 'sliders' },
'/vinculos':          { label: 'Vínculos',                 icon: 'link' },
'/turnos':            { label: 'Turnos',                   icon: 'clock' },
'/feriados':          { label: 'Feriados',                 icon: 'calendar' },
'/tabelas-auxiliares':{ label: 'Tabelas Auxiliares',       icon: 'table' },
'/eventos-folha':     { label: 'Eventos de Folha',         icon: 'list' },
'/frequencia':        { label: 'Controle de Frequência',   icon: 'clipboard-check' },
'/progressao-funcional': { label: 'Minha Progressão',     icon: 'trending' },
```

---

## VISÃO POR PERFIL — o que cada um vê

### Funcionário (perfil padrão)
```
Visão Geral:    Dashboard
Minha Área:     Meu Perfil, Ponto*, Holerites, Férias, Banco de Horas,
                Declarações, Minha Progressão
Comunicação:    Agenda, Comunicados, Ouvidoria
```
*Oculto quando MODULO_PONTO_ATIVO = 0

### Gestor de Setor
```
+ Minha Equipe: Portal do Gestor, Organograma, Escala de Trabalho,
                Escalas Hospitalares*, Substituições, Sobreaviso,
                Hora Extra, Plantões Extras
+ Comunicação:  Painel Ouvidoria
```

### RH
```
+ Recursos Humanos: Funcionários, Autocadastro, Cargos e Salários,
                    Contratos e Vínculos, Gerir Progressões,
                    Exoneração/Rescisão, PSS/Concurso, Estagiários,
                    Terceirizados, Acumulação de Cargos, Diárias
+ Frequência:       Faltas e Atrasos*, Abono de Faltas,
                    Atestados Médicos, Controle de Frequência**
+ Saúde Ocupacional: Medicina do Trabalho, Segurança do Trabalho
+ Financeiro e Folha: Folha, Consignações, Verbas Indenizatórias,
                      Benefícios, RPPS/IPAM, Remessa CNAB,
                      Gestão de Declarações
+ Compliance:       eSocial, SAGRES/TCE-MA, Transparência Pública
+ Desenvolvimento:  Avaliação, Treinamentos, Pesquisas
+ Comunicação:      + Relatórios
```
*Oculto sem ponto | **Visível apenas sem ponto

### Admin
```
+ Configurações:  Configurações Gerais, Motor de Folha,
                  Parâmetros Financeiros, Vínculos, Turnos,
                  Feriados, Tabelas Auxiliares, Eventos de Folha
+ ERP/Fiscal:     Orçamento, Execução da Despesa, Contabilidade,
                  Tesouraria, Receita Municipal, Controle Externo
```

---

## CRITÉRIOS DE ACEITE

```
- [ ] Todos os 8 módulos antes ausentes aparecem na sidebar para admin/rh
- [ ] Todos os 7 módulos de configuração aparecem para admin
- [ ] Seção "Minha Equipe" só aparece para gestor, rh e admin
- [ ] Seção "Recursos Humanos" só aparece para rh e admin
- [ ] Seção "Configurações" só aparece para admin
- [ ] Funcionário vê apenas: Visão Geral + Minha Área + Comunicação
- [ ] /progressao-funcional aparece em "Minha Área" para todos os perfis
- [ ] /frequencia aparece apenas quando MODULO_PONTO_ATIVO = 0
- [ ] /ponto, /faltas-atrasos, /escala-matriz-v3 somem quando ponto desligado
- [ ] Seções sem itens visíveis não aparecem (lógica existente já garante isso)
- [ ] routeMap atualizado — breadcrumb correto para todos os módulos novos
- [ ] Nenhuma rota do router.js foi removida — apenas sidebar atualizada
```

---

## NOTAS PARA O ANTYGRAVITY

1. **NÃO remover rotas** do `router/index.js` — apenas a sidebar muda
2. **NÃO alterar** a lógica de `navItemsFiltrados` — ela já funciona corretamente
3. **Adicionar** as flags `ocultarSemPonto` e `apenasModoSemPonto` conforme Parte 21
4. O `configStore` da Parte 21 precisa estar implementado para as flags funcionarem
5. Se `configStore` ainda não existir, ignorar as flags por enquanto — os itens aparecem normalmente
6. Testar com cada perfil (admin, rh, gestor, funcionario) após a mudança

---

*SIDEBAR_REORGANIZACAO.md | GENTE v3 | RR TECNOL | 16/03/2026*
*8 módulos sem sidebar corrigidos + 7 módulos de config adicionados*
*Reagrupamento por perfil de usuário — 9 seções lógicas*

/**
 * Manifesto único: sidebar, meta de gate (RBAC slug opcional) e anéis da Manta (Onda 0).
 * requiredAnySlugs vazio => não exige GENTE_PERMISSION (fallback por perfil legacy).
 */

export const ROLE_HIERARCHY = ['admin', 'rh', 'sesmt', 'gestor', 'funcionario']
export const RBAC_UI_STRICT = ['1', 'true', 'yes', 'on'].includes(
  String(import.meta?.env?.VITE_GENTE_RBAC_UI_STRICT ?? 'false').toLowerCase()
)

/** Alinhado ao router + matriz perfil_chaves. */
export function userEffectiveLevel(authStore) {
  if (authStore.isAdmin) return 0
  if (authStore.isRH) return 1
  if (
    authStore.hasPerfil('SESMT') ||
    ['sesmt', 'seguranca', 'seguranca do trabalho', 'medicina do trabalho'].includes(
      (authStore.perfil || '').toLowerCase().trim()
    )
  ) {
    return 2
  }
  if (authStore.isGestor) return 3
  return 4
}

export function legacyRolesAllow(authStore, roles) {
  if (!roles || roles.length === 0) return true
  const userLevel = userEffectiveLevel(authStore)
  return roles.some((r) => {
    const idx = ROLE_HIERARCHY.indexOf(r)
    return idx !== -1 && userLevel <= idx
  })
}

function rbacSlugsFromStore(authStore) {
  const raw = authStore.rbacPermissionSlugs
  if (!Array.isArray(raw)) return []
  return raw.map((s) => String(s).trim()).filter(Boolean)
}

function hasAnyRequiredSlug(authStore, requiredAnySlugs) {
  const slugs = rbacSlugsFromStore(authStore)
  if (slugs.length === 0) return false
  const set = new Set(slugs)
  return requiredAnySlugs.some((s) => set.has(String(s).trim()))
}

function resolveSlugAccess(authStore, requiredAnySlugs, legacyRoles = []) {
  if (!requiredAnySlugs || requiredAnySlugs.length === 0) return true
  if (hasAnyRequiredSlug(authStore, requiredAnySlugs)) return true
  if (RBAC_UI_STRICT) return false
  // Migração segura: se a sessão ainda não trouxe slugs RBAC, recua para roles.
  const hasRbacPayload = rbacSlugsFromStore(authStore).length > 0
  if (!hasRbacPayload) return legacyRolesAllow(authStore, legacyRoles)
  return false
}

/**
 * @param {import('pinia').Store} authStore pinia auth
 * @param {{ type: string, roles?: string[], requiredAnySlugs?: string[], to?: string }} row
 */
export function canAccessNavEntry(authStore, row) {
  if (!legacyRolesAllow(authStore, row.roles)) return false
  return resolveSlugAccess(authStore, row.requiredAnySlugs, row.roles || [])
}

export function canAccessNavSection(authStore, row) {
  if (row.type !== 'section') return true
  return legacyRolesAllow(authStore, row.roles)
}

/**
 * @typedef {{ legacyRoles: string[], requiredAnySlugs: string[], ringKey: string|null }} NavGateMeta
 * @param {string} pathname ex. /escala-trabalho
 * @returns {NavGateMeta|null}
 */
export function getNavGateMeta(pathname) {
  const p = (pathname || '/').replace(/\/+$/, '') || '/'
  let best = null
  let bestLen = -1
  for (const row of NAV_MANIFEST) {
    if (row.type !== 'item') continue
    if (row.to && row.to === p) {
      return pickGate(row)
    }
    if (row.pathPrefix && p.startsWith(row.pathPrefix) && row.pathPrefix.length > bestLen) {
      best = pickGate(row)
      bestLen = row.pathPrefix.length
    }
  }
  return best
}

function pickGate(row) {
  return {
    legacyRoles: row.roles || [],
    requiredAnySlugs: row.requiredAnySlugs || [],
    ringKey: row.ringKey ?? null,
  }
}

/**
 * @param {import('pinia').Store} authStore
 * @param {string[]} requiredAnySlugs
 * @param {string[]} legacyRoles
 */
export function passesRequiredSlugs(authStore, requiredAnySlugs, legacyRoles = []) {
  return resolveSlugAccess(authStore, requiredAnySlugs, legacyRoles)
}

/** Itens da sidebar + regras de gate (pathPrefix para detalhe funcionário). ringKey alinha a tenant_scope_rings.php */
export const NAV_MANIFEST = [
  { type: 'section', label: 'Visão Geral' },
  { type: 'item', to: '/dashboard', label: 'Dashboard', icon: 'dashboard', roles: [], ringKey: null, requiredAnySlugs: [] },
  {
    type: 'item',
    to: '/dashboard-executivo',
    label: 'Painel executivo',
    icon: 'chart',
    roles: ['admin', 'rh', 'gestor'],
    ringKey: null,
    requiredAnySlugs: ['global.mde.25', 'unidade.dashboard.kpi'],
  },

  { type: 'section', label: 'Minha Área' },
  { type: 'item', to: '/meu-perfil', label: 'Meu Perfil', icon: 'user', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/ponto', label: 'Ponto Eletrônico', icon: 'clock', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/meus-holerites', label: 'Meus Holerites', icon: 'money', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/ferias-licencas', label: 'Férias e Licenças', icon: 'beach', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/banco-horas', label: 'Banco de Horas', icon: 'hourglass', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/declaracoes-requerimentos', label: 'Declarações', icon: 'doc', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/progressao-funcional', label: 'Minha Progressão', icon: 'trending', roles: [], ringKey: 'rh_ciclo_vida', requiredAnySlugs: ['rh.progressao.lei4928'] },
  { type: 'item', to: '/minhas-substituicoes', label: 'Minhas Substituições', icon: 'swap', roles: [], ringKey: 'operacional_escala_freq', requiredAnySlugs: [] },

  { type: 'section', label: 'Minha Equipe', roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/portal-gestor', label: 'Portal do Gestor', icon: 'tie-person', roles: ['admin', 'rh', 'gestor'], ringKey: null, requiredAnySlugs: ['unidade.dashboard.kpi'] },
  { type: 'item', to: '/organograma', label: 'Estrutura Organizacional', icon: 'organogram', roles: ['admin', 'rh', 'gestor'], ringKey: null, requiredAnySlugs: ['organograma.unidade.visualizar'] },
  { type: 'item', to: '/escala-trabalho', label: 'Escalas', icon: 'calendar-week', roles: ['admin', 'rh', 'gestor'], ringKey: 'operacional_escala_freq', requiredAnySlugs: ['escala.grade.visualizar'] },
  { type: 'item', to: '/substituicoes', label: 'Substituições', icon: 'swap', roles: ['admin', 'rh', 'gestor'], ringKey: 'operacional_escala_freq', requiredAnySlugs: ['escala.grade.visualizar'] },
  { type: 'item', to: '/escala-sobreaviso', label: 'Sobreaviso', icon: 'phone', roles: ['admin', 'rh', 'gestor'], ringKey: 'operacional_escala_freq', requiredAnySlugs: [] },
  { type: 'item', to: '/hora-extra', label: 'Hora Extra', icon: 'clock', roles: ['admin', 'rh', 'gestor'], ringKey: 'operacional_escala_freq', requiredAnySlugs: [] },
  { type: 'item', to: '/plantoes-extras', label: 'Horas / Plantões Extras', icon: 'plus', roles: ['admin', 'rh', 'gestor'], ringKey: 'operacional_escala_freq', requiredAnySlugs: [] },

  { type: 'section', label: 'Recursos Humanos', roles: ['admin', 'rh'] },
  { type: 'item', to: '/funcionarios', label: 'Funcionários', icon: 'users', roles: ['admin', 'rh'], ringKey: 'rh_ciclo_vida', requiredAnySlugs: ['rh.progressao.lei4928'] },
  { type: 'item', to: '/autocadastro-gestao', label: 'Autocadastro', icon: 'user-plus', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/cargos-salarios', label: 'Cargos e Salários', icon: 'briefcase', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/contratos-vinculos', label: 'Contratos e Vínculos', icon: 'contract', roles: ['admin', 'rh'], ringKey: 'rh_ciclo_vida', requiredAnySlugs: ['rh.progressao.lei4928'] },
  { type: 'item', to: '/progressao-admin', label: 'Gerir Progressões', icon: 'badge', roles: ['admin', 'rh'], ringKey: 'rh_ciclo_vida', requiredAnySlugs: ['rh.progressao.lei4928'] },
  { type: 'item', to: '/exoneracao', label: 'Exoneração / Rescisão', icon: 'exit', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/pss', label: 'PSS / Concurso', icon: 'school', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/estagiarios', label: 'Estagiários', icon: 'student', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/terceirizados', label: 'Terceirizados', icon: 'briefcase', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/acumulacao-cargos', label: 'Acumulação de Cargos', icon: 'layers', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/diarias', label: 'Diárias', icon: 'map-pin', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/avaliacao-gestor', label: 'Avaliações da Equipe', icon: 'star', roles: ['admin', 'rh', 'gestor'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/beneficios', label: 'Gestão de Benefícios', icon: 'zap', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/treinamentos-admin', label: 'Gestão de Treinamentos', icon: 'school', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/medicina-admin', label: 'Gestão SESMT (Med.', icon: 'stethoscope', roles: ['admin', 'rh', 'sesmt'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/seguranca-admin', label: 'Gestão SESMT (Seg.', icon: 'shield', roles: ['admin', 'rh', 'sesmt'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Frequência', roles: ['admin', 'rh'] },
  { type: 'item', to: '/faltas-atrasos', label: 'Faltas e Atrasos', icon: 'warning', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/abono-faltas', label: 'Abono de Faltas', icon: 'check', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/atestados-medicos', label: 'Atestados Médicos', icon: 'hospital', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Saúde Ocupacional', roles: ['admin', 'rh'] },
  { type: 'item', to: '/medicina-trabalho', label: 'Medicina do Trabalho', icon: 'stethoscope', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/seguranca-trabalho', label: 'Segurança do Trabalho', icon: 'shield', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Financeiro e Folha', roles: ['admin', 'rh'] },
  { type: 'item', to: '/folha-pagamento', label: 'Folha de Pagamento', icon: 'credit-card', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/consignacao', label: 'Consignações', icon: 'account-balance', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/consignatarias', label: 'Consignatárias', icon: 'building-bank', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/verba-indenizatoria', label: 'Verbas Indenizatórias', icon: 'money', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/beneficios-admin', label: 'Benefícios', icon: 'gift', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/rpps', label: 'RPPS / IPAM', icon: 'bank', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: ['financeiro.previdencia.ipam'] },
  { type: 'item', to: '/remessa-cnab', label: 'Remessa CNAB', icon: 'bank', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/gestao-declaracoes', label: 'Gestão de Declarações', icon: 'clipboard', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Saúde', roles: ['admin'] },
  { type: 'item', to: '/oss', label: 'Monitor OSS', icon: 'activity', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Administrativo', roles: ['admin'] },
  { type: 'item', to: '/compras', label: 'Compras e Licitações', icon: 'shopping-cart', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/almoxarifado', label: 'Almoxarifado', icon: 'package', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/patrimonio', label: 'Patrimônio', icon: 'building', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/contratos-admin', label: 'Contratos', icon: 'file-text', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/frotas', label: 'Frotas', icon: 'car', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Compliance', roles: ['admin', 'rh'] },
  { type: 'item', to: '/esocial', label: 'eSocial', icon: 'cloud-upload', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/sagres-tce', label: 'SAGRES / TCE-MA', icon: 'chart', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/transparencia', label: 'Transparência Pública', icon: 'eye', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },

  { type: 'section', label: 'Desenvolvimento', roles: ['admin', 'rh'] },
  { type: 'item', to: '/avaliacao-desempenho', label: 'Avaliação de Desempenho', icon: 'star', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/treinamentos', label: 'Treinamentos', icon: 'school', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/pesquisa-satisfacao', label: 'Pesquisa de Satisfação', icon: 'poll', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/pesquisa-admin', label: 'Gerenciar Pesquisas', icon: 'edit', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'Comunicação' },
  { type: 'item', to: '/agenda', label: 'Agenda', icon: 'agenda', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/comunicados', label: 'Comunicados', icon: 'megaphone', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/notificacoes', label: 'Notificações', icon: 'bell', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/ouvidoria', label: 'Ouvidoria', icon: 'comment', roles: [], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/ouvidoria-admin', label: 'Painel Ouvidoria', icon: 'shield', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/relatorios', label: 'Relatórios', icon: 'chart', roles: ['admin', 'rh'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },

  { type: 'section', label: 'Configurações', roles: ['admin'] },
  { type: 'item', to: '/configuracoes', label: 'Configurações Gerais', icon: 'settings', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/configuracao-sistema', label: 'Motor de Folha', icon: 'cpu', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/parametros-financeiros', label: 'Parâmetros Financeiros', icon: 'sliders', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/vinculos', label: 'Vínculos', icon: 'link', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/turnos', label: 'Turnos', icon: 'clock', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/feriados', label: 'Feriados e Folgas', icon: 'calendar', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/tabelas-auxiliares', label: 'Tabelas Auxiliares', icon: 'table', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },
  { type: 'item', to: '/eventos-folha', label: 'Eventos de Folha', icon: 'list', roles: ['admin'], ringKey: null, requiredAnySlugs: [] },

  { type: 'section', label: 'ERP / Fiscal', roles: ['admin'] },
  { type: 'item', to: '/orcamento', label: 'Orçamento (PPA/LOA)', icon: 'budget', roles: ['admin'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/execucao-despesa', label: 'Execução da Despesa', icon: 'pay', roles: ['admin'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/contabilidade', label: 'Contabilidade (PCASP)', icon: 'book', roles: ['admin'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/tesouraria', label: 'Tesouraria', icon: 'bank', roles: ['admin'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/receita-municipal', label: 'Receita Municipal', icon: 'credit-card', roles: ['admin'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },
  { type: 'item', to: '/controle-externo', label: 'Controle Externo', icon: 'chart', roles: ['admin'], ringKey: null, requiredAnySlugs: ['global.mde.25'] },

  /** Detalhe funcionário: não aparece na sidebar; gate alinhado ao anel RH */
  {
    type: 'item',
    pathPrefix: '/funcionario/',
    label: '(Perfil funcionário)',
    icon: 'users',
    roles: ['admin', 'rh', 'gestor'],
    ringKey: 'rh_ciclo_vida',
    /** Qualquer um: diretoria (KPI), SAGEP/Lei 4.928, ou visão organograma (gestor polo / SEMAD leitura) — alinhado à matriz rbac_matrix.v1.yaml */
    requiredAnySlugs: ['unidade.dashboard.kpi', 'rh.progressao.lei4928', 'organograma.unidade.visualizar'],
    sidebar: false,
  },
  /** Rotas só no router (sem link na sidebar) */
  {
    type: 'item',
    to: '/escala-matriz-v3',
    label: 'Escala matriz',
    icon: 'calendar-week',
    roles: ['admin', 'rh', 'gestor'],
    ringKey: 'operacional_escala_freq',
    requiredAnySlugs: ['escala.grade.visualizar'],
    sidebar: false,
  },
]

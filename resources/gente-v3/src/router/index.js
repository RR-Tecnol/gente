import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { legacyRolesAllow, getNavGateMeta, passesRequiredSlugs } from '@/navigation/navManifest.js'
import { notifyMobileDrawerClose } from '@/navigation/mobileDrawerBus.js'

// ═══ Rotas ═══════════════════════════════════════════════════════════
const routes = [
    // Rotas públicas
    {
        path: '/login',
        name: 'Login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { public: true }
    },
    {
        path: '/primeiro-acesso',
        name: 'PrimeiroAcesso',
        component: () => import('../views/auth/PrimeiroAcessoView.vue'),
        meta: { requiresAuth: true, firstAccessPage: true }
    },
    // Autocadastro — pública, sem layout de admin
    {
        path: '/autocadastro/:token',
        name: 'Autocadastro',
        component: () => import('../views/auth/AutocadastroView.vue'),
        meta: { public: true }
    },
    // Redireciona / para /login
    {
        path: '/',
        redirect: '/login'
    },
    // Rotas protegidas (precisam de autenticação)
    {
        path: '/',
        component: () => import('../layouts/DashboardLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            // ── Disponível para TODOS os perfis ─────────────────────
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: () => import('../views/dashboard/HomeView.vue')
            },
            {
                path: 'dashboard-executivo',
                name: 'DashboardExecutivo',
                component: () => import('../views/dashboard/DashboardExecutivoView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'meu-perfil',
                name: 'MeuPerfil',
                component: () => import('../views/rh/MeuPerfilView.vue')
            },
            {
                path: 'ponto',
                name: 'PontoEletronico',
                component: () => import('../views/ponto/PontoEletronicoView.vue')
            },
            {
                path: 'meus-holerites',
                name: 'MeusHolerites',
                component: () => import('../views/folha/ContraChequeView.vue')
            },
            {
                path: 'comunicados',
                name: 'Comunicados',
                component: () => import('../views/ComunicadosView.vue')
            },
            {
                path: 'agenda',
                name: 'Agenda',
                component: () => import('../views/AgendaView.vue')
            },
            {
                path: 'notificacoes',
                name: 'Notificacoes',
                component: () => import('../views/NotificacoesView.vue')
            },
            {
                path: 'declaracoes-requerimentos',
                name: 'DeclaracoesRequerimentos',
                component: () => import('../views/rh/DeclaracoesRequerimentosView.vue')
            },
            {
                path: 'atestados-medicos',
                name: 'AtestadosMedicos',
                component: () => import('../views/ponto/AtestadosMedicosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'ferias-licencas',
                name: 'FeriasLicencas',
                component: () => import('../views/rh/FeriasLicencasView.vue')
            },
            {
                path: 'ouvidoria',
                name: 'Ouvidoria',
                component: () => import('../views/OuvidoriaView.vue')
            },
            {
                path: 'banco-horas',
                name: 'BancoHoras',
                component: () => import('../views/ponto/BancoHorasView.vue')
            },

            // ── Gestor e acima ───────────────────────────────────────
            {
                path: 'portal-gestor',
                name: 'PortalGestor',
                component: () => import('../views/rh/PortalGestorView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'organograma',
                name: 'Organograma',
                component: () => import('../views/rh/OrganogramaView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'escala-trabalho',
                name: 'EscalaTrabalho',
                component: () => import('../views/escala/EscalaTrabalhoView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'escala-matriz-v3',
                name: 'EscalaMatriz',
                component: () => import('../views/escala/MatrizEscalaView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'substituicoes',
                name: 'Substituicoes',
                component: () => import('../views/escala/SubstituicoesView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'minhas-substituicoes',
                name: 'MinhasSubstituicoes',
                component: () => import('../views/escala/MinhasSubstituicoesView.vue')
            },
            {
                path: 'escala-sobreaviso',
                name: 'EscalaSobreaviso',
                component: () => import('../views/ponto/EscalaSobreavisoView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'plantoes-extras',
                name: 'PlantoesExtras',
                component: () => import('../views/ponto/PlantoesExtrasView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },

            // ── RH e Admin ──────────────────────────────────────────
            {
                path: 'funcionarios',
                name: 'Funcionarios',
                component: () => import('../views/rh/FuncionariosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'autocadastro-gestao',
                name: 'AutocadastroGestao',
                component: () => import('../views/rh/AutocadastroGestaoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'funcionario/:id',
                name: 'PerfilFuncionario',
                component: () => import('../views/rh/PerfilFuncionarioView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'relatorios',
                name: 'Relatorios',
                component: () => import('../views/relatorios/RelatoriosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'abono-faltas',
                name: 'AbonoFaltas',
                component: () => import('../views/ponto/AbonoFaltasView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'faltas-atrasos',
                name: 'FaltasAtrasos',
                component: () => import('../views/ponto/FaltasAtrasosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'folha-pagamento',
                name: 'FolhaPagamento',
                component: () => import('../views/financeiro/FolhaPagamentoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'remessa-cnab',
                name: 'RemessaCnab',
                component: () => import('../views/financeiro/RemessaCnabView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'cargos-salarios',
                name: 'CargosSalarios',
                component: () => import('../views/rh/CargosSalariosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'progressao-funcional',
                name: 'ProgressaoFuncional',
                component: () => import('../views/rh/ProgressaoFuncionalView.vue')
            },
            {
                path: 'progressao-admin',
                name: 'ProgressaoAdmin',
                component: () => import('../views/rh/ProgressaoAdminView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'exoneracao',
                name: 'Exoneracao',
                component: () => import('../views/rh/ExoneracaoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'hora-extra',
                name: 'HoraExtra',
                component: () => import('../views/rh/HoraExtraView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'verba-indenizatoria',
                name: 'VerbaIndenizatoria',
                component: () => import('../views/rh/VerbaIndenizatoriaView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'consignacao',
                name: 'Consignacao',
                component: () => import('../views/rh/ConsignacaoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'consignatarias',
                name: 'Consignatarias',
                component: () => import('../views/rh/ConsignatariasView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'esocial',
                name: 'ESocial',
                component: () => import('../views/rh/ESocialView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'rpps',
                name: 'RPPS',
                component: () => import('../views/rh/RPPSView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'diarias',
                name: 'Diarias',
                component: () => import('../views/rh/DiariasView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'estagiarios',
                name: 'Estagiarios',
                component: () => import('../views/rh/EstagiariosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'sagres-tce',
                name: 'SagresTce',
                component: () => import('../views/financeiro/SagresView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'acumulacao-cargos',
                name: 'AcumulacaoCargos',
                component: () => import('../views/rh/AcumulacaoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'transparencia',
                name: 'Transparencia',
                component: () => import('../views/rh/TransparenciaView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'pss',
                name: 'PSS',
                component: () => import('../views/rh/PSSView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'terceirizados',
                name: 'Terceirizados',
                component: () => import('../views/rh/TerceirizadosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'medicina-trabalho',
                name: 'MedicinaTrabalho',
                component: () => import('../views/rh/MedicinaTrabalhoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'beneficios',
                name: 'Beneficios',
                component: () => import('../views/rh/BeneficiosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'contratos-vinculos',
                name: 'ContratosVinculos',
                component: () => import('../views/rh/ContratosVinculosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'avaliacao-desempenho',
                name: 'AvaliacaoDesempenho',
                component: () => import('../views/rh/AvaliacaoDesempenhoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'avaliacao-gestor',
                name: 'AvaliacaoGestor',
                component: () => import('../views/rh/AvaliacaoGestorView.vue'),
                meta: { roles: ['admin', 'rh', 'gestor'] }
            },
            {
                path: 'beneficios-admin',
                name: 'BeneficiosAdmin',
                component: () => import('../views/rh/BeneficiosAdminView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'medicina-admin',
                name: 'MedicinaAdmin',
                component: () => import('../views/rh/MedicinaAdminView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'seguranca-admin',
                name: 'SegurancaAdmin',
                component: () => import('../views/rh/SegurancaAdminView.vue'),
                meta: { roles: ['admin', 'rh', 'sesmt'] }
            },
            {
                path: 'treinamentos',
                name: 'Treinamentos',
                component: () => import('../views/rh/TreinamentosView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'treinamentos-admin',
                name: 'TreinamentosAdmin',
                component: () => import('../views/rh/TreinamentosAdminView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'seguranca-trabalho',
                name: 'SegurancaTrabalho',
                component: () => import('../views/rh/SegurancaTrabalhoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'pesquisa-satisfacao',
                name: 'PesquisaSatisfacao',
                component: () => import('../views/rh/PesquisaSatisfacaoView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'pesquisa-admin',
                name: 'PesquisaAdmin',
                component: () => import('../views/rh/PesquisaAdminView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },

            {
                path: 'gestao-declaracoes',
                name: 'GestaoDeclaracoes',
                component: () => import('../views/rh/GestaoDeclaracoesView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },
            {
                path: 'ouvidoria-admin',
                name: 'OuvidoriaAdmin',
                component: () => import('../views/rh/OuvidoriaAdminView.vue'),
                meta: { roles: ['admin', 'rh'] }
            },

            // ── Admin apenas ─────────────────────────────────────────
            {
                path: 'configuracoes',
                name: 'Configuracoes',
                component: () => import('../views/config/ConfiguracoesView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'configuracao-sistema',
                name: 'ConfiguracaoSistema',
                component: () => import('../views/config/ConfiguracaoSistemaView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'parametros-financeiros',
                name: 'ParametrosFinanceiros',
                component: () => import('../views/config/ParametroFinanceiroView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'tabelas-auxiliares',
                name: 'TabelasAuxiliares',
                component: () => import('../views/config/TabelasAuxiliaresView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'turnos',
                name: 'Turnos',
                component: () => import('../views/config/TurnosView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'feriados',
                name: 'Feriados',
                component: () => import('../views/config/FeriadosView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'vinculos',
                name: 'Vinculos',
                component: () => import('../views/config/VinculosView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'eventos-folha',
                name: 'EventosFolha',
                component: () => import('../views/config/EventosView.vue'),
                meta: { roles: ['admin'] }
            },

            // ── ERP / Fiscal (admin) ──────────────────────────────────
            {
                path: 'orcamento',
                name: 'Orcamento',
                component: () => import('../views/financeiro/OrcamentoView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'execucao-despesa',
                name: 'ExecucaoDespesa',
                component: () => import('../views/financeiro/ExecucaoDespesaView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'contabilidade',
                name: 'Contabilidade',
                component: () => import('../views/financeiro/ContabilidadeView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'compras',
                name: 'Compras',
                component: () => import('../views/administrativo/ComprasView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'almoxarifado',
                name: 'Almoxarifado',
                component: () => import('../views/administrativo/AlmoxarifadoView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'patrimonio',
                name: 'Patrimonio',
                component: () => import('../views/administrativo/PatrimonioView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'contratos-admin',
                name: 'ContratosAdmin',
                component: () => import('../views/administrativo/ContratosAdminView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'frotas',
                name: 'Frotas',
                component: () => import('../views/administrativo/FrotasView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'tesouraria',
                name: 'Tesouraria',
                component: () => import('../views/financeiro/TesourariaView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'receita-municipal',
                name: 'ReceitaMunicipal',
                component: () => import('../views/financeiro/ReceitaMunicipalView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'controle-externo',
                name: 'ControleExterno',
                component: () => import('../views/financeiro/ControleExternoView.vue'),
                meta: { roles: ['admin'] }
            },
            {
                path: 'oss',
                name: 'Oss',
                component: () => import('../views/saude/OssView.vue'),
                meta: { roles: ['admin'] }
            },

            // ── Aliases ───────────────────────────────────────────────
            { path: 'holerites', redirect: '/meus-holerites' },
            { path: 'escala', redirect: '/escala-trabalho' },
            { path: 'folha', redirect: '/folha-pagamento' },
        ]
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.afterEach(() => {
    notifyMobileDrawerClose()
})

// ═══ Navigation Guard ══════════════════════════════════════════════════
/** Tela de troca obrigatória de senha (meta explícita — evita falso negativo com merge de meta) */
function isFirstAccessScreen(to) {
    return to.name === 'PrimeiroAcesso' || to.matched.some((r) => r.meta && r.meta.firstAccessPage)
}

/** Rota de login: zona neutra — nunca forçar fetch /me nem lógica de PrimeiroAcesso no guard (login é sempre acessível). */
function isLoginRoute(to) {
    return to.name === 'Login' || to.path === '/login'
}

function deniedToDashboard(next, code) {
    return next({
        name: 'Dashboard',
        query: { denied: 'rbac', code: String(code || 'access_denied') },
    })
}

function assertRouteAccess(authStore, to) {
    const requiredRoles = to.meta.roles
    if (requiredRoles && requiredRoles.length > 0) {
        if (!legacyRolesAllow(authStore, requiredRoles)) {
            return { allowed: false, code: 'legacy_role_required' }
        }
    }

    const gate = getNavGateMeta(to.path)
    if (gate && gate.requiredAnySlugs && gate.requiredAnySlugs.length > 0) {
        const passes = passesRequiredSlugs(authStore, gate.requiredAnySlugs, gate.legacyRoles || [])
        if (!passes) {
            return { allowed: false, code: 'required_slug_missing' }
        }
    }

    return { allowed: true, code: null }
}

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore()

    // 1) Rotas públicas: Login = next() imediato (sem validar sessão; sem fetchUser; sem redirecionar a PrimeiroAcesso)
    if (to.meta.public && isLoginRoute(to)) {
        return next()
    }
    if (to.meta.public) {
        return next()
    }

    // 2) A partir daqui, rotas não públicas
    const needsAuth = to.meta.requiresAuth || to.matched.some((r) => r.meta && r.meta.requiresAuth)
    if (needsAuth) {
        if (isFirstAccessScreen(to)) {
            await authStore.fetchUser()
        } else {
            await authStore.fetchUser(true)
        }
        if (!authStore.isAuthenticated) {
            return next({ name: 'Login' })
        }
        if (authStore.isAuthenticated && authStore.forcePasswordChange && !isFirstAccessScreen(to)) {
            return next({ name: 'PrimeiroAcesso' })
        }
        if (authStore.isAuthenticated && !authStore.forcePasswordChange && isFirstAccessScreen(to)) {
            return next({ name: 'Dashboard' })
        }
    }

    const access = assertRouteAccess(authStore, to)
    if (!access.allowed) return deniedToDashboard(next, access.code)

    next()
})

export default router

/**
 * GENTE v3 — Screenshot Automático de Todas as Telas (v2)
 *
 * Uso: node scripts/screenshot-all.js
 * Pré-requisito: npm run build  (vite build)
 */

import { chromium } from 'playwright'
import { spawn } from 'child_process'
import { mkdirSync, readdirSync, unlinkSync, existsSync } from 'fs'
import { join, resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dirname, '../../../')
const SCREENSHOTS_DIR = join(ROOT, 'docs', 'screenshots')
const PREVIEW_PORT = 4173
const BASE_URL = `http://localhost:${PREVIEW_PORT}`

const MOCK_USER = {
    id: 1, login: 'admin', nome: 'Administrador do Sistema',
    email: 'admin@gente.local', perfil: 'admin', alterar_senha: false,
    USUARIO_ID: 1, USUARIO_LOGIN: 'admin', PESSOA_NOME: 'Administrador do Sistema',
    FUNCIONARIO_ID: 1, isAdmin: true, isGestor: true,
    funcionario: {
        id: 1, FUNCIONARIO_ID: 1, matricula: 'ADM001', nome: 'Administrador do Sistema',
        FUNCIONARIO_MATRICULA: 'ADM001', setor: 'Secretaria de Administração', vinculo: 'Estatutário'
    }
}

// ── Todas as rotas (com instruções de interação para algumas) ──
const ROTAS = [
    // login é tratado separadamente no início
    { path: '/dashboard', nome: '02-dashboard' },
    { path: '/meu-perfil', nome: '03-meu-perfil' },
    { path: '/ponto', nome: '04-ponto-eletronico' },
    { path: '/meus-holerites', nome: '05-holerites' },
    { path: '/comunicados', nome: '06-comunicados' },
    { path: '/agenda', nome: '07-agenda' },
    { path: '/notificacoes', nome: '08-notificacoes' },
    { path: '/declaracoes-requerimentos', nome: '09-declaracoes-requerimentos' },
    { path: '/atestados-medicos', nome: '10-atestados-medicos' },
    { path: '/ferias-licencas', nome: '11-ferias-licencas' },
    { path: '/banco-horas', nome: '12-banco-horas' },
    { path: '/ouvidoria', nome: '13-ouvidoria-servidor' },
    { path: '/portal-gestor', nome: '14-portal-gestor' },
    { path: '/organograma', nome: '15-organograma' },
    { path: '/escala-trabalho', nome: '16-escala-trabalho' },
    {
        path: '/escala-matriz-v3', nome: '17-escala-matriz',
        acao: async (page) => {
            // Tenta mudar o mês para fevereiro se houver controles de navegação
            await page.waitForTimeout(600)
            const btnPrev = await page.$('button[title*="anterior"], button[title*="Anterior"], .mes-prev, .nav-prev')
            if (btnPrev) { await btnPrev.click(); await page.waitForTimeout(400) }
        }
    },
    { path: '/substituicoes', nome: '18-substituicoes' },
    { path: '/escala-sobreaviso', nome: '19-escala-sobreaviso' },
    { path: '/plantoes-extras', nome: '20-plantoes-extras' },
    { path: '/funcionarios', nome: '21-funcionarios' },
    { path: '/autocadastro-gestao', nome: '22-autocadastro-gestao' },
    { path: '/relatorios', nome: '23-relatorios' },
    { path: '/abono-faltas', nome: '24-abono-faltas' },
    { path: '/faltas-atrasos', nome: '25-faltas-atrasos' },
    { path: '/folha-pagamento', nome: '26-folha-pagamento' },
    {
        path: '/remessa-cnab', nome: '27-remessa-cnab',
        acao: async (page) => {
            // Simula o lote preenchido via JS para mostrar a tabela de preview
            await page.evaluate(() => {
                try {
                    // Injeta mock data diretamente no componente Vue via evento customizado
                    window.__mockRemessaLote = [
                        { id: 1, nome: 'Maria Aparecida Silva', matricula: '20250001', cpf: '12345678901', banco: '001', agencia: '3721', conta: '12345-6', proventos: 4850, descontos: 820, liquido: 4030 },
                        { id: 2, nome: 'José Raimundo Costa', matricula: '20250002', cpf: '98765432100', banco: '104', agencia: '0423', conta: '9876-5', proventos: 3200, descontos: 540, liquido: 2660 },
                        { id: 3, nome: 'Ana Paula Ferreira', matricula: '20240045', cpf: '11122233344', banco: '033', agencia: '1234', conta: '56789-1', proventos: 5600, descontos: 980, liquido: 4620 },
                        { id: 4, nome: 'Francisco Souza Neto', matricula: '20240067', cpf: '', banco: '', agencia: '', conta: '', proventos: 3800, descontos: 650, liquido: 3150 },
                    ]
                } catch { }
            })
        }
    },
    { path: '/cargos-salarios', nome: '28-cargos-salarios' },
    { path: '/progressao-funcional', nome: '29-progressao-funcional' },
    { path: '/progressao-admin', nome: '30-progressao-admin' },
    { path: '/exoneracao', nome: '31-exoneracao' },
    { path: '/hora-extra', nome: '32-hora-extra' },
    { path: '/verba-indenizatoria', nome: '33-verba-indenizatoria' },
    { path: '/consignacao', nome: '34-consignacao' },
    { path: '/esocial', nome: '35-esocial' },
    { path: '/rpps', nome: '36-rpps-ipam' },
    { path: '/diarias', nome: '37-diarias' },
    { path: '/estagiarios', nome: '38-estagiarios' },
    { path: '/sagres-tce', nome: '39-sagres-tce-ma' },
    { path: '/acumulacao-cargos', nome: '40-acumulacao-cargos' },
    { path: '/transparencia', nome: '41-transparencia-publica' },
    { path: '/pss', nome: '42-pss-concurso' },
    { path: '/terceirizados', nome: '43-terceirizados' },
    { path: '/medicina-trabalho', nome: '44-medicina-trabalho' },
    { path: '/beneficios', nome: '45-beneficios' },
    { path: '/contratos-vinculos', nome: '46-contratos-vinculos' },
    { path: '/avaliacao-desempenho', nome: '47-avaliacao-desempenho' },
    { path: '/treinamentos', nome: '48-treinamentos' },
    { path: '/seguranca-trabalho', nome: '49-seguranca-trabalho' },
    { path: '/pesquisa-satisfacao', nome: '50-pesquisa-satisfacao' },
    { path: '/pesquisa-admin', nome: '51-pesquisa-admin' },
    // #52 — Gestão de Declarações: aba Modelos de Documento
    { path: '/gestao-declaracoes', nome: '52-gestao-declaracoes-solicitacoes' },
    {
        path: '/gestao-declaracoes', nome: '52b-gestao-declaracoes-modelos',
        acao: async (page) => {
            // Clicar na aba "Modelos de Documento"
            const btn = await page.$('button.mtab:has-text("Modelos"), .mtab:has-text("Modelos")')
            if (btn) {
                await btn.click()
                await page.waitForTimeout(1200) // aguarda carregar lista de modelos
            }
        }
    },
    { path: '/ouvidoria-admin', nome: '53-ouvidoria-admin' },
    // #54 — Configurações: aba padrão (Segurança), depois Vínculos, depois Ponto
    { path: '/configuracoes', nome: '54-configuracoes-seguranca' },
    {
        path: '/configuracoes', nome: '54b-configuracoes-vinculos',
        acao: async (page) => {
            const btn = await page.$('.cnav-btn:has-text("Vínculos"), .cnav-btn:has-text("Vinculos")')
            if (btn) { await btn.click(); await page.waitForTimeout(800) }
        }
    },
    {
        path: '/configuracoes', nome: '54c-configuracoes-ponto',
        acao: async (page) => {
            const btn = await page.$('.cnav-btn:has-text("Ponto")')
            if (btn) { await btn.click(); await page.waitForTimeout(800) }
        }
    },
    { path: '/configuracao-sistema', nome: '55-configuracao-sistema' },
    { path: '/parametros-financeiros', nome: '56-parametros-financeiros' },
    { path: '/tabelas-auxiliares', nome: '57-tabelas-auxiliares' },
    { path: '/turnos', nome: '58-turnos' },
    { path: '/feriados', nome: '59-feriados' },
    { path: '/vinculos', nome: '60-vinculos' },
    { path: '/eventos-folha', nome: '61-eventos-folha' },
    { path: '/orcamento', nome: '62-orcamento' },
    { path: '/execucao-despesa', nome: '63-execucao-despesa' },
    { path: '/contabilidade', nome: '64-contabilidade-pcasp' },
    { path: '/tesouraria', nome: '65-tesouraria' },
    { path: '/receita-municipal', nome: '66-receita-municipal' },
    { path: '/controle-externo', nome: '67-controle-externo' },
]

// ── Mock API responses ──────────────────────────────────────────────
function mockResponseFor(url) {
    const u = url.toLowerCase()

    if (u.includes('/api/auth/me')) return json(MOCK_USER)
    if (u.includes('/api/auth/login')) return json({ ok: true, user: MOCK_USER })
    if (u.includes('/csrf-cookie')) return json({})

    if (u.includes('/secretarias') || u.includes('/unidades')) return json({
        unidades: [
            { UNIDADE_ID: 1, UNIDADE_NOME: 'Secretaria de Administração' },
            { UNIDADE_ID: 2, UNIDADE_NOME: 'Secretaria de Saúde' },
            { UNIDADE_ID: 3, UNIDADE_NOME: 'Secretaria de Educação' },
        ]
    })

    if (u.includes('/notificacoes')) return json({
        notificacoes: [
            { id: 1, tipo: 'info', icone: '📋', titulo: 'Novo holerite disponível', body: 'Folha de Março/2026 processada', lida: false, criada_em: new Date().toISOString() },
            { id: 2, tipo: 'success', icone: '✅', titulo: 'Abono deferido', body: 'Abono de falta em 05/03 aprovado', lida: false, criada_em: new Date().toISOString() },
        ], nao_lidas: 2
    })

    if (u.includes('/cnab/historico')) return json({
        historico: [
            { id: 1, data: '2026-02-28', competencia: '2026-02', banco: 'Banco do Brasil', qtd: 245, total: 1423890.50, arquivo: 'REMESSA_001_202602_0001.rem' },
            { id: 2, data: '2026-01-31', competencia: '2026-01', banco: 'Caixa Econômica Federal', qtd: 241, total: 1401250.00, arquivo: 'REMESSA_104_202601_0001.rem' },
        ]
    })

    if (u.includes('/cnab/previsualizar')) return json({
        lote: [
            { id: 1, nome: 'Maria Aparecida Silva', matricula: '20250001', cpf: '12345678901', banco: '001', agencia: '3721', conta: '12345-6', proventos: 4850, descontos: 820, liquido: 4030 },
            { id: 2, nome: 'José Raimundo Costa', matricula: '20250002', cpf: '98765432100', banco: '104', agencia: '0423', conta: '9876-5', proventos: 3200, descontos: 540, liquido: 2660 },
            { id: 3, nome: 'Ana Paula Ferreira', matricula: '20240045', cpf: '11122233344', banco: '033', agencia: '1234', conta: '56789-1', proventos: 5600, descontos: 980, liquido: 4620 },
        ]
    })

    if (u.includes('/funcionarios/1')) return json({ funcionario: MOCK_USER.funcionario })
    if (u.includes('/funcionarios') || u.includes('/servidores')) return json({
        data: [
            { FUNCIONARIO_ID: 1, PESSOA_NOME: 'Maria Aparecida Silva', FUNCIONARIO_MATRICULA: '20250001', cargo: 'Analista', setor: 'Secretaria de Saúde', VINCULO_DESCRICAO: 'Estatutário' },
            { FUNCIONARIO_ID: 2, PESSOA_NOME: 'José Raimundo Costa', FUNCIONARIO_MATRICULA: '20250002', cargo: 'Técnico', setor: 'Secretaria de Administração', VINCULO_DESCRICAO: 'Comissionado' },
            { FUNCIONARIO_ID: 3, PESSOA_NOME: 'Ana Paula Ferreira', FUNCIONARIO_MATRICULA: '20240045', cargo: 'Enfermeira', setor: 'Secretaria de Saúde', VINCULO_DESCRICAO: 'Estatutário' },
        ], total: 3, current_page: 1, last_page: 1
    })

    if (u.includes('/admin/vinculos')) return json([
        { VINCULO_ID: 1, VINCULO_DESCRICAO: 'Estatutário Efetivo', VINCULO_SIGLA: 'EFETIVO', VINCULO_ATIVO: 1 },
        { VINCULO_ID: 2, VINCULO_DESCRICAO: 'Cargo em Comissão', VINCULO_SIGLA: 'COMISSAO', VINCULO_ATIVO: 1 },
        { VINCULO_ID: 3, VINCULO_DESCRICAO: 'Estágio', VINCULO_SIGLA: 'ESTAGIO', VINCULO_ATIVO: 1 },
        { VINCULO_ID: 4, VINCULO_DESCRICAO: 'Contrato Temporário', VINCULO_SIGLA: 'CONTRATO', VINCULO_ATIVO: 0 },
    ])

    if (u.includes('/ponto/config/funcionarios')) return json([
        { FUNCIONARIO_ID: 1, PESSOA_NOME: 'Maria Aparecida Silva', REGIME: '4_batidas', HORA_ENTRADA: '08:00', HORA_SAIDA: '17:00', TOLERANCIA: 15 },
        { FUNCIONARIO_ID: 2, PESSOA_NOME: 'José Raimundo Costa', REGIME: '', HORA_ENTRADA: null, HORA_SAIDA: null, TOLERANCIA: null },
    ])
    if (u.includes('/ponto/config')) return json({ regime: '4_batidas', hora_entrada: '08:00', hora_saida: '18:00', intervalo_almoco: 120, tolerancia: 15 })

    if (u.includes('/rh/modelos')) return json({
        modelos: [
            { tipo: 'Declaração de Vínculo Empregatício', temModelo: true, atualizadoEm: '2026-02-15' },
            { tipo: 'Declaração de Renda', temModelo: false, atualizadoEm: null },
            { tipo: 'Declaração de Disponibilidade', temModelo: true, atualizadoEm: '2026-01-20' },
            { tipo: 'Declaração de Tempo de Serviço', temModelo: false, atualizadoEm: null },
            { tipo: 'Declaração de Férias', temModelo: false, atualizadoEm: null },
            { tipo: 'Certidão de Lotação', temModelo: true, atualizadoEm: '2026-03-01' },
            { tipo: 'Declaração para Fins Previdenciários', temModelo: false, atualizadoEm: null },
            { tipo: 'Declaração de Não Acumulação de Cargos', temModelo: false, atualizadoEm: null },
        ]
    })

    if (u.includes('/rh/declaracoes')) return json({
        itens: [
            { id: 1, nome: 'Declaração de Vínculo', servidor: 'Maria Aparecida Silva', matricula: '20250001', data: '2026-03-10', status: 'pendente', protocolo: 'REQ-2026-001' },
            { id: 2, nome: 'Declaração de Renda', servidor: 'José Raimundo Costa', matricula: '20250002', data: '2026-03-08', status: 'andamento', protocolo: 'REQ-2026-002', obs: 'Em conferência' },
            { id: 3, nome: 'Certidão de Lotação', servidor: 'Ana Paula Ferreira', matricula: '20240045', data: '2026-03-05', status: 'pronto', protocolo: 'REQ-2026-003' },
            { id: 4, nome: 'Declaração de Disponibilidade', servidor: 'Francisco Neto', matricula: '20240067', data: '2026-02-28', status: 'indeferido', protocolo: 'REQ-2026-004', obs: 'Documentação insuficiente' },
        ]
    })

    if (u.includes('/folhas')) return json({
        folhas: [
            { FOLHA_ID: 1, FOLHA_COMPETENCIA: '2026-03', FOLHA_STATUS: 'PROCESSADA', FOLHA_TIPO: 'NORMAL', total_servidores: 245, total_bruto: 1587450.00 },
            { FOLHA_ID: 2, FOLHA_COMPETENCIA: '2026-02', FOLHA_STATUS: 'PROCESSADA', FOLHA_TIPO: 'NORMAL', total_servidores: 241, total_bruto: 1423890.50 },
        ]
    })

    if (u.includes('/consignacao/convenios') || u.includes('/consignacao/convenio')) return json({
        convenios: [
            { id: 1, CONVENIO_NOME: 'Banco do Brasil SA', CONVENIO_CNPJ: '00000000000191', taxa_juros: '1.2', prazo_max: 60 },
            { id: 2, CONVENIO_NOME: 'Caixa Econômica Federal', CONVENIO_CNPJ: '00360305000104', taxa_juros: '1.5', prazo_max: 48 },
        ]
    })
    if (u.includes('/consignacao')) return json({ contratos: [], totais: {}, convenios: [] })

    if (u.includes('/hora-extra') || u.includes('/horaextra')) return json({ registros: [], totais: {} })

    if (u.includes('/progressao')) return json({ servidores: [], total: 0 })
    if (u.includes('/rpps') || u.includes('/ipam')) return json({ resumo: {}, servidores: [] })
    if (u.includes('/diarias')) return json({ diarias: [] })
    if (u.includes('/esocial')) return json({ pendencias: [], eventos: [] })
    if (u.includes('/orcamento')) return json({ programas: [], total: 0 })
    if (u.includes('/organograma') || u.includes('/setores')) return json({ setores: [] })
    if (u.includes('/escala') || u.includes('/escalas')) return json({ escalas: [], turnos: [] })
    if (u.includes('/ponto')) return json({ registros: [], batidas: [] })
    if (u.includes('/holerites') || u.includes('/contracheque')) return json({ holerites: [] })

    // genérico
    return json({ data: [], items: [], total: 0 })
}

const json = (body) => ({ status: 200, body: JSON.stringify(body) })

async function waitForServer(url, maxWait = 20000) {
    const start = Date.now()
    while (Date.now() - start < maxWait) {
        try { const r = await fetch(url); if (r.status < 500) return true } catch { }
        await new Promise(r => setTimeout(r, 600))
    }
    throw new Error(`Servidor não respondeu em ${maxWait}ms`)
}

function limparScreenshots() {
    if (!existsSync(SCREENSHOTS_DIR)) {
        mkdirSync(SCREENSHOTS_DIR, { recursive: true })
        console.log('📁 Pasta docs/screenshots/ criada.')
        return 0
    }
    const files = readdirSync(SCREENSHOTS_DIR).filter(f => f.endsWith('.png'))
    files.forEach(f => unlinkSync(join(SCREENSHOTS_DIR, f)))
    console.log(`🗑️  ${files.length} screenshots antigas removidas.`)
    return files.length
}

async function criarContextoAutenticado(browser) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'pt-BR', deviceScaleFactor: 1.5 })
    // Mock de todas as chamadas de API
    await ctx.route('**/api/**', async route => {
        const mock = mockResponseFor(route.request().url())
        await route.fulfill({ status: mock.status, contentType: 'application/json', body: mock.body })
    })
    return ctx
}

const HIDE_SIDEBAR = `
  aside.sidebar, .overlay { display: none !important; }
  .app-shell { grid-template-columns: 0 1fr !important; padding-left: 0 !important; }
  .main-content { margin-left: 0 !important; padding-left: 0 !important; }
`

async function capturar(page, nome, fullPage = true) {
    await page.addStyleTag({ content: HIDE_SIDEBAR })
    await page.waitForTimeout(500)
    await page.screenshot({ path: join(SCREENSHOTS_DIR, `${nome}.png`), fullPage, type: 'png' })
}

async function main() {
    console.log('\n🎬 GENTE v3 — Screenshot Automático v2\n')
    limparScreenshots()

    // Inicia vite preview
    console.log('🚀 Iniciando vite preview...')
    const preview = spawn('npx', ['vite', 'preview', '--port', PREVIEW_PORT.toString(), '--host'], {
        cwd: resolve(__dirname, '..'),
        stdio: 'pipe',
        shell: true,
    })
    preview.stderr?.on('data', () => { })

    await waitForServer(BASE_URL)
    console.log(`✅ Servidor em ${BASE_URL}\n`)

    const browser = await chromium.launch({ headless: true })
    let sucesso = 0, falha = 0

    // ── 1. Login — contexto LIMPO, sem auth mock ──────────────────────
    process.stdout.write(`  📷 01-login${''.padEnd(40)} `)
    try {
        const ctxLogin = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'pt-BR', deviceScaleFactor: 1.5 })
        // NÃO mocka /api/auth/me para que o guard não redirecione
        await ctxLogin.route('**/api/**', async route => {
            if (route.request().url().includes('/auth/me')) {
                await route.fulfill({ status: 401, contentType: 'application/json', body: '{"error":"unauthenticated"}' })
            } else {
                await route.fulfill({ status: 200, contentType: 'application/json', body: '{}' })
            }
        })
        const pLogin = await ctxLogin.newPage()
        await pLogin.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle', timeout: 15000 })
        await pLogin.waitForTimeout(1200)
        await pLogin.screenshot({ path: join(SCREENSHOTS_DIR, '01-login.png'), fullPage: false, type: 'png' })
        await ctxLogin.close()
        console.log('✅')
        sucesso++
    } catch (e) {
        console.log(`❌ (${e.message.slice(0, 60)})`)
        falha++
    }

    // ── 2. Todas as outras telas — contexto autenticado ───────────────
    const ctx = await criarContextoAutenticado(browser)
    const page = await ctx.newPage()

    // Visita dashboard primeiro para disparar fetch do user e popular Pinia
    await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle', timeout: 20000 })
    await page.waitForTimeout(1000)

    for (const rota of ROTAS) {
        process.stdout.write(`  📷 ${rota.nome.padEnd(45)} `)
        try {
            // Se a rota mudou em relação à anterior, navega
            const urlAtual = new URL(page.url()).pathname
            if (urlAtual !== rota.path) {
                await page.goto(`${BASE_URL}${rota.path}`, { waitUntil: 'networkidle', timeout: 15000 })
                await page.waitForTimeout(800)
            } else {
                // Mesma rota (screenshot adicional com ação diferente)
                await page.waitForTimeout(400)
            }

            // Executa ação específica (clicar em aba, etc.)
            if (rota.acao) {
                await rota.acao(page)
            }

            await capturar(page, rota.nome)
            console.log('✅')
            sucesso++
        } catch (e) {
            console.log(`❌ (${e.message.slice(0, 60)})`)
            falha++
        }
    }

    await browser.close()
    preview.kill()

    console.log(`\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`)
    console.log(`✅ ${sucesso} screenshots • ❌ ${falha} falhas`)
    console.log(`📂 Salvas em: docs/screenshots/`)
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`)
}

main().catch(e => { console.error('Erro fatal:', e); process.exit(1) })

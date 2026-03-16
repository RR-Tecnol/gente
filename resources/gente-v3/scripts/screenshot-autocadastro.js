// Screenshot pontual do Autocadastro (rota pública)
import { chromium } from 'playwright'
import { spawn } from 'child_process'
import { join, resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const OUT = join(resolve(__dirname, '../../../'), 'docs', 'screenshots', '00-autocadastro.png')
const BASE = 'http://localhost:4173'

const preview = spawn('npx', ['vite', 'preview', '--port', '4173', '--host'], {
    cwd: resolve(__dirname, '..'),
    stdio: 'pipe',
    shell: true,
})

// Aguarda o servidor subir
await new Promise(r => setTimeout(r, 5000))

const browser = await chromium.launch({ headless: true })
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'pt-BR', deviceScaleFactor: 1.5 })

// Rota pública — mocka a validação do token
await ctx.route('**/api/**', async route => {
    await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ ok: true, nome: 'João da Silva Santos', email: 'joao.silva@municipio.gov.br' })
    })
})

const page = await ctx.newPage()
await page.goto(BASE + '/autocadastro/TOKEN-DEMO-2026', { waitUntil: 'networkidle', timeout: 15000 })
await page.waitForTimeout(1500)

await page.screenshot({ path: OUT, fullPage: false, type: 'png' })
console.log('✅ Screenshot salva: docs/screenshots/00-autocadastro.png')

await browser.close()
preview.kill()

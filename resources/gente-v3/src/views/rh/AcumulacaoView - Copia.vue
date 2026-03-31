<template>
  <div class="acumulacao-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⚖️ Art. 37, XVI CF/1988</span>
          <h1 class="hero-title">Acumulação de Cargos</h1>
          <p class="hero-sub">Registro e controle de declarações de acumulação lícita de cargos públicos</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card green"><span class="kpi-label">Acumulação Permitida</span><span class="kpi-val">{{ stats.permitida }}</span></div>
          <div class="kpi-card red"><span class="kpi-label">Vedado — > 2 Cargos</span><span class="kpi-val">{{ stats.vedado }}</span></div>
          <div class="kpi-card yellow"><span class="kpi-label">Pendente Revisão</span><span class="kpi-val">{{ stats.pendente }}</span></div>
        </div>
      </div>
    </div>

    <!-- REGRAS -->
    <div class="info-grid" :class="{ loaded }">
      <div class="info-card ok">
        <h4>✅ Acumulação LÍCITA (CF, art. 37, XVI)</h4>
        <ul>
          <li>Dois cargos de professor</li>
          <li>Um cargo de professor + um técnico ou científico</li>
          <li>Dois cargos ou empregos privativos de profissionais de saúde</li>
          <li>Limite: <strong>a soma das remunerações deve respeitar o teto constitucional</strong></li>
        </ul>
      </div>
      <div class="info-card red">
        <h4>🚫 Acumulação VEDADA (Regra Geral)</h4>
        <ul>
          <li>Dois cargos efetivos fora das hipóteses do inc. XVI</li>
          <li>Três ou mais cargos (mesmo que permitidos)</li>
          <li>Cargo efetivo + cargo em comissão de outro órgão (como regra)</li>
        </ul>
      </div>
    </div>

    <!-- LISTA + NOVA -->
    <div class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Declarações Registradas</h2>
        <button class="btn-primary" @click="modalNova = true">+ Nova Declaração</button>
      </div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Servidor</th><th>Cargo PMSLz</th><th>Outro Cargo</th>
            <th>Outro Órgão</th><th>Situação</th><th>Data</th>
          </tr></thead>
          <tbody>
            <tr v-if="!declaracoes.length"><td colspan="6" class="empty-td">📭 Nenhuma declaração</td></tr>
            <tr v-for="d in declaracoes" :key="d.DECLARACAO_ID">
              <td>
                <span class="nome">{{ d.nome }}</span>
                <span class="sub">{{ d.matricula }}</span>
              </td>
              <td>{{ d.cargo_atual }}</td>
              <td>{{ d.OUTRO_CARGO }}</td>
              <td>{{ d.OUTRO_ORGAO }}</td>
              <td><span class="badge" :class="sitCls(d.SITUACAO)">{{ d.SITUACAO }}</span></td>
              <td>{{ fmtDate(d.DATA_DECLARACAO) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Nova Declaração -->
    <transition name="modal">
      <div v-if="modalNova" class="modal-overlay" @click.self="modalNova = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>📝 Registrar Declaração de Acumulação</h3>
            <button class="modal-close" @click="modalNova = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Servidor (nome ou matrícula)</label>
              <input class="form-input" v-model="nova.servidor_nome" placeholder="Buscar..." @input="buscarServ" />
            </div>
            <div class="form-group">
              <label>Situação</label>
              <select class="form-input" v-model="nova.situacao">
                <option value="LICITA">✅ Lícita — permitida por lei</option>
                <option value="VEDADA">🚫 Vedada — irregularidade</option>
                <option value="PENDENTE">⏳ Pendente revisão</option>
              </select>
            </div>
            <div class="form-group"><label>Outro Cargo</label><input class="form-input" v-model="nova.outro_cargo" /></div>
            <div class="form-group"><label>Outro Órgão</label><input class="form-input" v-model="nova.outro_orgao" /></div>
            <div class="form-group"><label>Observações</label><textarea class="form-input ta" v-model="nova.observacao" /></div>
            <div class="modal-actions">
              <button class="btn-secondary" @click="modalNova = false">Cancelar</button>
              <button class="btn-primary" @click="salvar">✅ Registrar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const modalNova = ref(false)
const toast = ref({ visible: false, msg: '' })
const declaracoes = ref([])
const stats = ref({ permitida: 0, vedado: 0, pendente: 0 })
const nova = ref({ funcionario_id: null, servidor_nome: '', situacao: 'LICITA', outro_cargo: '', outro_orgao: '', observacao: '' })

const MOCK = [
  { DECLARACAO_ID: 1, nome: 'Maria Silva', matricula: '20250001', cargo_atual: 'Enfermeira', OUTRO_CARGO: 'Professora', OUTRO_ORGAO: 'UFMA', SITUACAO: 'LICITA', DATA_DECLARACAO: '2026-01-15' },
  { DECLARACAO_ID: 2, nome: 'João Costa', matricula: '20250002', cargo_atual: 'Técnico de RH', OUTRO_CARGO: 'Auxiliar Administrativo', OUTRO_ORGAO: 'DETRAN-MA', SITUACAO: 'VEDADA', DATA_DECLARACAO: '2026-02-03' },
]

async function carregar() {
  try {
    const { data } = await api.get('/api/v3/acumulacao')
    declaracoes.value = data.declaracoes?.data ?? MOCK
  } catch { declaracoes.value = MOCK }
  stats.value = {
    permitida: declaracoes.value.filter(d => d.SITUACAO === 'LICITA').length,
    vedado:    declaracoes.value.filter(d => d.SITUACAO === 'VEDADA').length,
    pendente:  declaracoes.value.filter(d => d.SITUACAO === 'PENDENTE').length,
  }
}

function buscarServ() { /* auto-complete — mesma lógica de DiariasView */ }

async function salvar() {
  try {
    await api.post('/api/v3/acumulacao', { funcionario_id: nova.value.funcionario_id ?? 1, ...nova.value })
    showToast('✅ Declaração registrada.'); modalNova.value = false; await carregar()
  } catch { showToast('⚠️ Erro ao registrar declaração.') }
}

const sitCls  = s => ({ LICITA: 'ok', VEDADA: 'red', PENDENTE: 'warn' })[s] ?? ''
const fmtDate = d => d ? new Date(d + 'T12:00').toLocaleDateString('pt-BR') : '—'
function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }
onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.acumulacao-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#1e3a5f,#0c4a6e,#065f46); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#34d399; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#38bdf8; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#86efac; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:100px; text-align:center; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.red    { border-top:2px solid #f87171; }
.kpi-card.yellow { border-top:2px solid #fbbf24; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; }
.kpi-val   { display:block; font-size:1.2rem; font-weight:800; }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; opacity:0; transform:translateY(8px); transition:opacity .4s .1s,transform .4s .1s; }
.info-grid.loaded { opacity:1; transform:none; }
.info-card { border-radius:14px; padding:1.1rem 1.25rem; border:1px solid; }
.info-card.ok  { background:#f0fdf4; border-color:#86efac; }
.info-card.red { background:#fef2f2; border-color:#fca5a5; }
.info-card h4 { font-size:.88rem; font-weight:800; margin:0 0 .65rem; }
.info-card.ok h4  { color:#166534; }
.info-card.red h4 { color:#991b1b; }
.info-card ul { margin:0; padding-left:1.25rem; }
.info-card li { font-size:.8rem; color:#334155; margin-bottom:.3rem; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.section-hdr .section-title { margin-bottom:0; }
.btn-primary  { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#0f766e,#065f46); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; }
.nome { display:block; font-weight:700; color:#1e293b; }
.sub  { display:block; font-size:.7rem; color:#94a3b8; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok   { background:#dcfce7; color:#166534; }
.badge.red  { background:#fee2e2; color:#991b1b; }
.badge.warn { background:#fef3c7; color:#92400e; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px); z-index:100; display:flex; align-items:center; justify-content:center; padding:1rem; }
.modal-card { background:#fff; border-radius:20px; width:100%; max-width:480px; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.2); }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.modal-hdr h3 { font-size:1rem; font-weight:800; color:#1e293b; margin:0; }
.modal-close { border:none; background:#f1f5f9; border-radius:8px; width:28px; height:28px; cursor:pointer; color:#64748b; }
.modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:.9rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.ta { resize:vertical; min-height:55px; font-family:inherit; }
.modal-actions { display:flex; gap:.75rem; }
.modal-enter-active,.modal-leave-active { transition:opacity .3s; }
.modal-enter-from,.modal-leave-to { opacity:0; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; }
</style>

<template>
  <div class="escala-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📅 Escalas</span>
          <h1 class="hero-title">Escala de Trabalho</h1>
          <p class="hero-sub">Gestão mensal de escalas por setor · {{ mesSelecionado }}</p>
        </div>
        <div class="hero-ctrl">
          <button class="ctrl-btn" @click="mudarMes(-1)">‹</button>
          <span class="ctrl-mes">{{ mesSelecionado }}</span>
          <button class="ctrl-btn" @click="mudarMes(1)">›</button>
        </div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <svg class="s-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar funcionário ou setor..." />
      </div>
      <select v-model="setorSel" class="filter-sel" @change="fetchEscala">
        <option value="">Todos os setores</option>
        <option v-for="s in setores" :key="s.id" :value="s.id">{{ s.nome }}</option>
      </select>
      <button class="novo-btn" @click="abrirModal()">+ Novo Registro</button>
      <button class="pdf-btn" @click="exportarPDF" :disabled="escalaFiltrada.length === 0">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6M9 15h6M9 11h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        PDF
      </button>
    </div>

    <!-- LOADING / VAZIO -->
    <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando escala...</p></div>

    <template v-else>
      <!-- GRADE MENSAL -->
      <div class="grade-card" :class="{ loaded }">
        <div class="grade-header">
          <div class="grade-col-func">Funcionário / Setor</div>
          <div v-for="d in diasMes" :key="d.num" class="grade-col-dia" :class="{ 'dia-fim': d.fimSemana }">
            <span class="dia-num">{{ d.num }}</span>
            <span class="dia-sem">{{ d.sem }}</span>
          </div>
        </div>
        <div v-for="(linha, i) in escalaFiltrada" :key="linha.funcionario_id" class="grade-row" :style="{ '--ri': i }">
          <div class="grade-col-func">
            <span class="func-nome">{{ linha.nome }}</span>
            <span class="func-setor">{{ linha.setor }}</span>
          </div>
          <div
            v-for="d in diasMes"
            :key="d.num"
            class="grade-cel"
            :class="getTurnoClass(linha, d.num)"
            :title="getTurnoTitle(linha, d.num)"
            @click="editarDia(linha, d)"
          >
            <span class="cel-txt">{{ getTurnoCod(linha, d.num) }}</span>
          </div>
        </div>
        <div v-if="escalaFiltrada.length === 0" class="state-box">
          <span>📅</span><p>Nenhum registro de escala para este mês.</p>
        </div>
      </div>

      <!-- LEGENDA -->
      <div class="legenda" :class="{ loaded }">
        <span v-for="t in turnosCores" :key="t.cod" class="leg-item" :class="t.cls">
          <span class="leg-dot"></span> {{ t.cod }} – {{ t.nome }}
        </span>
      </div>
    </template>

    <!-- MODAL REGISTRO -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>{{ editando ? '✏️ Editar DIA' : '+ Novo Registro de Escala' }}</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group">
                <label>Funcionário <span class="req">*</span></label>
                <select v-model="form.funcionario_id" class="cfg-input">
                  <option value="">Selecione</option>
                  <option v-for="f in funcionarios" :key="f.id" :value="f.id">{{ f.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Data <span class="req">*</span></label>
                <input v-model="form.data" type="date" class="cfg-input" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Turno <span class="req">*</span></label>
                <select v-model="form.turno" class="cfg-input">
                  <option value="">Selecione</option>
                  <option v-for="t in turnosCores" :key="t.cod" :value="t.cod">{{ t.cod }} – {{ t.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Observação</label>
                <input v-model="form.obs" class="cfg-input" placeholder="Opcional" />
              </div>
            </div>
            <div v-if="erroSalvar" class="erro-msg">⚠️ {{ erroSalvar }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ editando ? 'Salvar Alteração' : 'Registrar' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="toast.visible" class="toast-msg">{{ toast.msg }}</div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded    = ref(false)
const loading   = ref(true)
const busca     = ref('')
const setorSel  = ref('')
const escala    = ref([])
const setores   = ref([])
const funcionarios = ref([])
const modalAberto  = ref(false)
const editando     = ref(false)
const salvando     = ref(false)
const erroSalvar   = ref('')
const toast        = ref({ visible: false, msg: '' })

const now = new Date()
const anoRef = ref(now.getFullYear())
const mesRef = ref(now.getMonth() + 1) // 1-12

const form = ref({ funcionario_id: '', data: '', turno: '', obs: '' })

const turnosCores = [
  { cod: 'M',  nome: 'Matutino',   cls: 'tur-m' },
  { cod: 'V',  nome: 'Vespertino', cls: 'tur-v' },
  { cod: 'N',  nome: 'Noturno',    cls: 'tur-n' },
  { cod: 'I',  nome: 'Integral',   cls: 'tur-i' },
  { cod: 'F',  nome: 'Folga',      cls: 'tur-f' },
  { cod: 'SO', nome: 'Sobreaviso', cls: 'tur-so' },
  { cod: 'AT', nome: 'Atestado',   cls: 'tur-at' },
]

const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']
const sems  = ['D','S','T','Q','Q','S','S']

const mesSelecionado = computed(() => `${meses[mesRef.value - 1]} / ${anoRef.value}`)

const diasMes = computed(() => {
  const dias = new Date(anoRef.value, mesRef.value, 0).getDate()
  return Array.from({ length: dias }, (_, i) => {
    const dt = new Date(anoRef.value, mesRef.value - 1, i + 1)
    return { num: i + 1, sem: sems[dt.getDay()], fimSemana: dt.getDay() === 0 || dt.getDay() === 6 }
  })
})

const mudarMes = (d) => {
  mesRef.value += d
  if (mesRef.value > 12) { mesRef.value = 1; anoRef.value++ }
  if (mesRef.value < 1)  { mesRef.value = 12; anoRef.value-- }
  fetchEscala()
}

const escalaFiltrada = computed(() => {
  if (!busca.value) return escala.value
  const t = busca.value.toLowerCase()
  return escala.value.filter(e => (e.nome + e.setor).toLowerCase().includes(t))
})

const getTurnoClass  = (l, d) => l.dias?.[d]?.turno ? `cel-${l.dias[d].turno.toLowerCase()}` : ''
const getTurnoCod    = (l, d) => l.dias?.[d]?.turno || ''
const getTurnoTitle  = (l, d) => l.dias?.[d] ? `${l.nome} - Dia ${d}: ${l.dias[d].turno}` : ''

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3200) }

const fetchEscala = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/escala-trabalho', {
      params: { mes: mesRef.value, ano: anoRef.value, setor_id: setorSel.value || undefined }
    })
    escala.value   = data.escala   ?? []
    setores.value  = data.setores  ?? []
    funcionarios.value = data.funcionarios ?? []
  } catch {
    escala.value = []
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
}

onMounted(fetchEscala)

const abrirModal = (funcionarioId = '', data = '') => {
  form.value = { funcionario_id: funcionarioId, data, turno: '', obs: '' }
  editando.value = false; erroSalvar.value = ''
  modalAberto.value = true
}

const editarDia = (linha, dia) => {
  const d = String(anoRef.value) + '-' + String(mesRef.value).padStart(2,'0') + '-' + String(dia.num).padStart(2,'0')
  form.value = {
    funcionario_id: linha.funcionario_id,
    data: d,
    turno: linha.dias?.[dia.num]?.turno || '',
    obs: linha.dias?.[dia.num]?.obs || '',
  }
  editando.value = true; erroSalvar.value = ''
  modalAberto.value = true
}

const salvar = async () => {
  if (!form.value.funcionario_id || !form.value.data || !form.value.turno) {
    erroSalvar.value = 'Preencha todos os campos obrigatórios.'; return
  }
  salvando.value = true; erroSalvar.value = ''
  try {
    await api.post('/api/v3/escala-trabalho', { ...form.value })
    showToast('✅ Escala registrada!')
    modalAberto.value = false
    await fetchEscala()
  } catch (e) { erroSalvar.value = e.response?.data?.erro || 'Erro ao salvar.' }
  finally { salvando.value = false }
}

const exportarPDF = () => {
  const turnoCorBg = { m: '#dbeafe', v: '#fef3c7', n: '#e0e7ff', i: '#dcfce7', f: '#f1f5f9', so: '#fce7f3', at: '#ffedd5' }
  const turnoCorTx = { m: '#1d4ed8', v: '#92400e', n: '#3730a3', i: '#166534', f: '#64748b', so: '#9d174d', at: '#9a3412' }

  const headerDias = diasMes.value.map(d => {
    const bg = d.fimSemana ? '#f1f5f9' : '#fff'
    return `<th style="min-width:28px;max-width:28px;text-align:center;padding:4px 1px;font-size:9px;border:1px solid #e2e8f0;background:${bg};color:${d.fimSemana?'#94a3b8':'#475569'}"><div>${d.num}</div><div style="font-size:8px;color:#94a3b8">${d.sem}</div></th>`
  }).join('')

  const linhas = escalaFiltrada.value.map(linha => {
    const cells = diasMes.value.map(d => {
      const cod = getTurnoCod(linha, d.num)
      if (!cod) return `<td style="min-width:28px;border:1px solid #f1f5f9;"></td>`
      const key = cod.toLowerCase()
      const bg = turnoCorBg[key] ?? '#f1f5f9'
      const tx = turnoCorTx[key] ?? '#64748b'
      return `<td style="min-width:28px;border:1px solid #f1f5f9;text-align:center;background:${bg};font-size:9px;font-weight:800;color:${tx};padding:3px 1px">${cod}</td>`
    }).join('')
    const total = diasMes.value.filter(d => { const c = getTurnoCod(linha, d.num); return c && c !== 'F' }).length
    return `<tr>
      <td style="padding:6px 10px;font-size:11px;white-space:nowrap;border:1px solid #e2e8f0;min-width:190px;max-width:190px">
        <div style="font-weight:700;color:#1e293b">${linha.nome}</div>
        <div style="font-size:9px;color:#94a3b8">${linha.setor ?? ''}</div>
      </td>
      ${cells}
      <td style="text-align:center;padding:4px 8px;font-size:10px;font-weight:800;color:#6366f1;border:1px solid #e2e8f0">${total}</td>
    </tr>`
  }).join('')

  const legenda = turnosCores.map(t => {
    const bg = turnoCorBg[t.cod.toLowerCase()] ?? '#f1f5f9'
    const tx = turnoCorTx[t.cod.toLowerCase()] ?? '#64748b'
    return `<span style="display:inline-flex;align-items:center;gap:4px;background:${bg};color:${tx};border-radius:6px;padding:3px 9px;font-size:10px;font-weight:700;margin:3px">${t.cod} – ${t.nome}</span>`
  }).join('')

  const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <title>Escala de Trabalho – ${mesSelecionado.value}</title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;padding:20px}
      @media print{body{padding:0}@page{size:landscape;margin:10mm}}
      h1{font-size:15px;color:#1e3a8a;margin-bottom:2px}
      .sub{font-size:11px;color:#64748b;margin-bottom:14px}
      table{border-collapse:collapse;width:100%}
      th{background:#f8fafc;font-size:10px;font-weight:700;color:#475569;padding:6px 2px;border:1px solid #e2e8f0}
      .leg{margin-top:14px}
    </style></head><body>
    <h1>📅 Escala de Trabalho — ${mesSelecionado.value}</h1>
    <div class="sub">Gerado em ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})}</div>
    <table>
      <thead><tr>
        <th style="min-width:190px;text-align:left;padding:6px 10px">Funcionário / Setor</th>
        ${headerDias}
        <th style="min-width:40px">Total</th>
      </tr></thead>
      <tbody>${linhas}</tbody>
    </table>
    <div class="leg"><strong>Legenda:</strong><br/>${legenda}</div>
    <script>window.onload=()=>{window.print()}<\/script>
  </body></html>`

  const win = window.open('', '_blank', 'width=1200,height=800')
  if (!win) { alert('Permita popups para exportar PDF.'); return }
  win.document.open()
  win.document.write(html)
  win.document.close()
}
</script>

<style scoped>
.escala-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 32px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1a3a 55%, #0a1f2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -50px; }
.hs2 { width: 160px; height: 160px; background: #0d9488; right: 240px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-ctrl { display: flex; align-items: center; gap: 12px; }
.ctrl-btn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.08); color: #fff; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.ctrl-btn:hover { background: rgba(255,255,255,0.15); }
.ctrl-mes { font-size: 15px; font-weight: 800; color: #fff; min-width: 90px; text-align: center; }
.toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.08s; flex-wrap: wrap; }
.toolbar.loaded { opacity: 1; transform: none; }
.search-wrap { flex: 1; min-width: 180px; display: flex; align-items: center; gap: 8px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.filter-sel { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; }
.novo-btn { padding: 9px 18px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.35); }
.pdf-btn { display: flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #fff; color: #475569; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.pdf-btn:hover:not(:disabled) { background: #f8fafc; border-color: #6366f1; color: #6366f1; }
.pdf-btn:disabled { opacity: 0.4; cursor: not-allowed; }
/* GRADE */
.grade-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: auto; opacity: 0; transform: translateY(8px); transition: all 0.4s 0.12s; }
.grade-card.loaded { opacity: 1; transform: none; }
.grade-header, .grade-row { display: flex; min-width: max-content; }
.grade-header { background: #f8fafc; border-bottom: 2px solid #f1f5f9; position: sticky; top: 0; z-index: 2; }
.grade-col-func { min-width: 180px; max-width: 180px; padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; border-right: 1px solid #f1f5f9; position: sticky; left: 0; background: inherit; z-index: 1; }
.grade-row .grade-col-func { background: #fff; font-size: 13px; text-transform: none; letter-spacing: 0; color: #1e293b; padding: 10px 14px; border-bottom: 1px solid #f8fafc; transition: background 0.1s; }
.grade-row:hover .grade-col-func { background: #f8fafc; }
.func-nome { display: block; font-weight: 700; font-size: 13px; color: #1e293b; }
.func-setor { display: block; font-size: 11px; color: #94a3b8; }
.grade-col-dia { min-width: 36px; padding: 4px 2px; text-align: center; border-right: 1px solid #f1f5f9; }
.grade-col-dia.dia-fim { background: #fafafa; }
.dia-num { display: block; font-size: 12px; font-weight: 800; color: #1e293b; }
.dia-sem { display: block; font-size: 9px; color: #94a3b8; }
.grade-cel { min-width: 36px; padding: 6px 2px; text-align: center; cursor: pointer; border-right: 1px solid #f8fafc; border-bottom: 1px solid #f8fafc; transition: all 0.12s; border-radius: 4px; }
.grade-cel:hover { filter: brightness(0.92); }
.cel-txt { font-size: 10px; font-weight: 800; }
.cel-m { background: #dbeafe; color: #1d4ed8; }
.cel-v { background: #fef3c7; color: #92400e; }
.cel-n { background: #e0e7ff; color: #3730a3; }
.cel-i { background: #dcfce7; color: #166534; }
.cel-f { background: #f1f5f9; color: #64748b; }
.cel-so { background: #fce7f3; color: #9d174d; }
.cel-at { background: #ffedd5; color: #9a3412; }
/* LEGENDA */
.legenda { display: flex; gap: 10px; flex-wrap: wrap; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 18px; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.15s; }
.legenda.loaded { opacity: 1; transform: none; }
.leg-item { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #475569; padding: 4px 10px; border-radius: 8px; }
.leg-dot { width: 8px; height: 8px; border-radius: 3px; background: currentColor; }
.tur-m .leg-dot { color: #1d4ed8; } .tur-v .leg-dot { color: #92400e; } .tur-n .leg-dot { color: #3730a3; }
.tur-i .leg-dot { color: #166534; } .tur-f .leg-dot { color: #64748b; } .tur-so .leg-dot { color: #9d174d; } .tur-at .leg-dot { color: #9a3412; }
/* ESTADO */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; font-size: 36px; color: #94a3b8; }
.state-box p { font-size: 14px; margin: 0; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 520px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #dc2626; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #6366f1; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
.toast-msg { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); white-space: nowrap; }
</style>

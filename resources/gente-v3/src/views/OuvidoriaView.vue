<template>
  <div class="ouv-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🗳️ Canal Institucional</span>
          <h1 class="hero-title">Ouvidoria</h1>
          <p class="hero-sub">Canal seguro para manifestações, sugestões e elogios</p>
        </div>
        <div class="hero-kpis">
          <div class="hk hk-blue"><span class="hk-val">{{ manifestacoes.length }}</span><span class="hk-label">Total</span></div>
          <div class="hk hk-yellow"><span class="hk-val">{{ manifestacoes.filter(m => m.status === 'analise').length }}</span><span class="hk-label">Em Análise</span></div>
          <div class="hk hk-green"><span class="hk-val">{{ manifestacoes.filter(m => m.status === 'respondida').length }}</span><span class="hk-label">Respondidas</span></div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tabAtiva === t.id }" @click="tabAtiva = t.id">
        {{ t.ico }} {{ t.nome }}
      </button>
    </div>

    <!-- TAB: NOVA MANIFESTAÇÃO -->
    <div v-if="tabAtiva === 'nova'" class="tab-content" :class="{ loaded }">
      <div class="form-panel">
        <div class="form-hdr">
          <h2>📝 Registrar Manifestação</h2>
          <p>{{ anonimo ? '🔒 Modo Anônimo — sua identidade não será revelada' : '👤 Identificado — você receberá resposta por e-mail' }}</p>
        </div>

        <div class="anon-toggle" @click="anonimo = !anonimo">
          <div class="toggle-track" :class="{ 'toggle-on': anonimo }">
            <div class="toggle-thumb"></div>
          </div>
          <span>Envio Anônimo</span>
        </div>

        <div class="form-two-col">
          <div class="form-group">
            <label>Tipo de Manifestação</label>
            <div class="tipo-grid">
              <button v-for="t in tipos" :key="t.val" class="tipo-btn"
                :class="{ 'tipo-active': form.tipo === t.val }"
                :style="{ '--tc': t.cor }"
                @click="form.tipo = t.val">
                <span class="tipo-ico">{{ t.ico }}</span>
                <span class="tipo-nome">{{ t.nome }}</span>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label>Área Relacionada</label>
            <select v-model="form.area" class="cfg-input">
              <option value="">Selecione...</option>
              <option>Recursos Humanos</option>
              <option>Escalas e Plantões</option>
              <option>Financeiro e Pagamentos</option>
              <option>Infraestrutura</option>
              <option>Segurança do Trabalho</option>
              <option>Atendimento ao Paciente</option>
              <option>Administração Geral</option>
            </select>
            <div class="form-group mt">
              <label>Urgência</label>
              <select v-model="form.urgencia" class="cfg-input">
                <option value="normal">Normal</option>
                <option value="alta">Alta</option>
                <option value="critica">Crítica</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Descrição Detalhada</label>
          <textarea v-model="form.descricao" class="cfg-input cfg-ta" rows="5" placeholder="Descreva com detalhes a sua manifestação. Quanto mais informações, mais rápida será a resposta..."></textarea>
          <span class="char-count">{{ form.descricao.length }}/1000</span>
        </div>

        <button class="submit-btn" :disabled="!formValido || enviando" @click="enviarManifestacao">
          <div v-if="enviando" class="btn-spin"></div>
          <template v-else>🚀 Enviar Manifestação</template>
        </button>
      </div>
    </div>

    <!-- TAB: MINHAS MANIFESTAÇÕES -->
    <div v-if="tabAtiva === 'minhas'" class="tab-content" :class="{ loaded }">
      <div class="manif-list">
        <div v-for="(m, i) in manifestacoes" :key="m.id" class="manif-item" :style="{ '--md': `${i * 50}ms` }">
          <div class="mi-left" :style="{ '--mc': tipoCor(m.tipo) }">
            <span class="mi-ico">{{ tipoIco(m.tipo) }}</span>
          </div>
          <div class="mi-body">
            <div class="mi-hdr">
              <span class="mi-tipo" :style="{ color: tipoCor(m.tipo) }">{{ tipoNome(m.tipo) }}</span>
              <span class="mi-area">{{ m.area }}</span>
              <span class="mi-status" :class="statusClass(m.status)">{{ statusLabel(m.status) }}</span>
            </div>
            <p class="mi-desc">{{ m.descricao }}</p>
            <div v-if="m.resposta" class="mi-resposta">
              <div class="mr-label">🏛️ Resposta da Ouvidoria:</div>
              <p class="mr-texto">{{ m.resposta }}</p>
            </div>
            <div class="mi-footer">
              <span class="mi-proto">Protocolo: {{ m.protocolo }}</span>
              <span class="mi-data">{{ formatDate(m.data) }}</span>
              <span class="mi-anon" v-if="m.anonimo">🔒 Anônimo</span>
            </div>
          </div>
        </div>
        <div v-if="manifestacoes.length === 0" class="state-empty">
          <span>📭</span><p>Nenhuma manifestação registrada ainda.</p>
        </div>
      </div>
    </div>

    <transition name="toast">
      <div v-if="toast.visible" class="toast" :class="toast.type">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tabAtiva = ref('nova')
const anonimo = ref(false)
const enviando = ref(false)
const toast = ref({ visible: false, msg: '', type: '' })
const form = reactive({ tipo: '', area: '', urgencia: 'normal', descricao: '' })

const tabs = [
  { id: 'nova', ico: '✍️', nome: 'Nova Manifestação' },
  { id: 'minhas', ico: '📋', nome: 'Minhas Manifestações' },
]

const tipos = [
  { val: 'reclamacao', ico: '😤', nome: 'Reclamação', cor: '#ef4444' },
  { val: 'sugestao', ico: '💡', nome: 'Sugestão', cor: '#3b82f6' },
  { val: 'elogio', ico: '😊', nome: 'Elogio', cor: '#10b981' },
  { val: 'denuncia', ico: '⚠️', nome: 'Denúncia', cor: '#f59e0b' },
  { val: 'solicitacao', ico: '📋', nome: 'Solicitação', cor: '#6366f1' },
  { val: 'outros', ico: '💬', nome: 'Outros', cor: '#64748b' },
]

const manifestacoes = ref([
  { id: 1, tipo: 'sugestao', area: 'Escalas e Plantões', urgencia: 'normal', descricao: 'Sugiro a implementação de um sistema de troca de plantão mais simplificado, onde o servidor possa propor diretamente ao substituto e aguardar aprovação automática após 24h sem resposta.', status: 'respondida', protocolo: 'OUV-2026-001', data: '2026-01-15', anonimo: false, resposta: 'Sua sugestão foi encaminhada à Coordenação de Escalas. O novo sistema de substituições está em desenvolvimento e será implementado no próximo trimestre.' },
  { id: 2, tipo: 'reclamacao', area: 'Infraestrutura', urgencia: 'alta', descricao: 'O ar-condicionado da UTI Adulto (ala C) está apresentando falha intermitente há 15 dias. Temperatura inadequada compromete tanto o conforto dos pacientes quanto a eficácia de equipamentos.', status: 'analise', protocolo: 'OUV-2026-002', data: '2026-02-01', anonimo: false, resposta: null },
  { id: 3, tipo: 'elogio', area: 'Recursos Humanos', urgencia: 'normal', descricao: 'Gostaria de parabenizar a equipe de RH pelo excelente atendimento durante o processo de solicitação de férias. Foram ágeis, cordiais e esclarecedores.', status: 'respondida', protocolo: 'OUV-2026-003', data: '2026-02-10', anonimo: false, resposta: 'Agradecemos o elogio! Repassaremos à equipe. Sua satisfação é nossa motivação.' },
])

const formValido = computed(() => form.tipo && form.area && form.descricao.length >= 20)

const mockManifestacoes = [
  { id: 1, tipo: 'sugestao', area: 'Escalas e Plantões', urgencia: 'normal', descricao: 'Sugiro um sistema de troca de plantão mais simplificado, com aprovação automática após 24h.', status: 'respondida', protocolo: 'OUV-2026-001', data: '2026-01-15', anonimo: false, resposta: 'Sugestão encaminhada à Coordenação de Escalas. Sistema em desenvolvimento.' },
  { id: 2, tipo: 'reclamacao', area: 'Infraestrutura', urgencia: 'alta', descricao: 'O ar-condicionado da UTI Adulto (ala C) está com falha há 15 dias.', status: 'analise', protocolo: 'OUV-2026-002', data: '2026-02-01', anonimo: false, resposta: null },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/ouvidoria')
    manifestacoes.value = (!data.fallback && data.manifestacoes?.length)
      ? data.manifestacoes.map(m => ({
          id: m.OUVIDORIA_ID ?? m.id, tipo: m.OUVIDORIA_TIPO ?? m.tipo,
          area: m.OUVIDORIA_AREA ?? m.area, urgencia: m.OUVIDORIA_URGENCIA ?? 'normal',
          descricao: m.OUVIDORIA_DESC ?? m.descricao, status: m.OUVIDORIA_STATUS ?? 'recebida',
          protocolo: m.OUVIDORIA_PROTOCOLO ?? m.protocolo, data: m.OUVIDORIA_DATA ?? m.data,
          anonimo: !!(m.OUVIDORIA_ANONIMO ?? m.anonimo), resposta: m.OUVIDORIA_RESPOSTA ?? m.resposta ?? null
        }))
      : mockManifestacoes
  } catch {
    manifestacoes.value = mockManifestacoes
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const gerarProtocolo = () => `OUV-${new Date().getFullYear()}-${String(manifestacoes.value.length + 4).padStart(3, '0')}`

const enviarManifestacao = async () => {
  enviando.value = true
  try {
    const resp = await api.post('/api/v3/ouvidoria', { ...form, anonimo: anonimo.value })
    const proto = resp.data?.protocolo ?? gerarProtocolo()
    manifestacoes.value.unshift({
      id: Date.now(), tipo: form.tipo, area: form.area, urgencia: form.urgencia,
      descricao: form.descricao, status: 'recebida', protocolo: proto,
      data: new Date().toISOString().slice(0, 10), anonimo: anonimo.value, resposta: null
    })
    Object.assign(form, { tipo: '', area: '', urgencia: 'normal', descricao: '' })
    toast.value = { visible: true, msg: `✅ Manifestação registrada! Protocolo: ${proto}`, type: 'toast-green' }
    tabAtiva.value = 'minhas'
  } catch (e) {
    const msg = e.response?.data?.erro || e.message || 'Erro ao enviar manifestação.'
    toast.value = { visible: true, msg: `Erro: ${msg}`, type: 'toast-red' }
  } finally {
    enviando.value = false
    setTimeout(() => { toast.value.visible = false }, 4000)
  }
}

const tipoCor = (t) => tipos.find(x => x.val === t)?.cor ?? '#64748b'
const tipoIco = (t) => tipos.find(x => x.val === t)?.ico ?? '💬'
const tipoNome = (t) => tipos.find(x => x.val === t)?.nome ?? t
const statusLabel = (s) => ({ recebida: 'Recebida', analise: 'Em Análise', respondida: 'Respondida', encerrada: 'Encerrada' })[s] ?? s
const statusClass = (s) => ({ recebida: 'st-blue', analise: 'st-yellow', respondida: 'st-green', encerrada: 'st-gray' })[s] ?? ''
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.ouv-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1040 55%, #0a1a2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-kpis { display: flex; gap: 10px; }
.hk { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 10px 16px; text-align: center; }
.hk-val { display: block; font-size: 22px; font-weight: 900; }
.hk-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.hk-blue .hk-val { color: #60a5fa; }
.hk-yellow .hk-val { color: #fbbf24; }
.hk-green .hk-val { color: #34d399; }
.tabs { display: flex; gap: 6px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; max-width: 760px; width: 100%; margin: 0 auto; flex-wrap: wrap; }
.tabs.loaded { opacity: 1; transform: none; }
.tab-btn { display: flex; align-items: center; gap: 7px; padding: 10px 18px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; }
.tab-btn.active { background: #f0f0ff; border-color: #6366f1; color: #4f46e5; }
.tab-content { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; width: 100%; }
.tab-content.loaded { opacity: 1; transform: none; }
.form-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; display: flex; flex-direction: column; gap: 16px; max-width: 760px; width: 100%; margin: 0 auto; box-sizing: border-box; }
.form-hdr h2 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0 0 4px; }
.form-hdr p { font-size: 12px; color: #94a3b8; margin: 0; }
.anon-toggle { display: flex; align-items: center; gap: 12px; cursor: pointer; width: fit-content; }
.toggle-track { width: 44px; height: 24px; border-radius: 99px; background: #e2e8f0; position: relative; transition: background 0.2s; }
.toggle-track.toggle-on { background: #6366f1; }
.toggle-thumb { position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; top: 3px; left: 3px; transition: left 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
.toggle-on .toggle-thumb { left: 23px; }
.anon-toggle span { font-size: 13px; font-weight: 700; color: #475569; }
.form-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.mt { margin-top: 12px; }
.tipo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.tipo-btn { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 10px 6px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; transition: all 0.15s; }
.tipo-btn:hover { border-color: var(--tc); background: color-mix(in srgb, var(--tc) 6%, white); }
.tipo-btn.tipo-active { border-color: var(--tc); background: color-mix(in srgb, var(--tc) 10%, white); }
.tipo-ico { font-size: 22px; }
.tipo-nome { font-size: 11px; font-weight: 700; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #6366f1; }
.cfg-ta { resize: vertical; min-height: 100px; }
.char-count { font-size: 11px; color: #94a3b8; text-align: right; }
.submit-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 13px; border-radius: 14px; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; }
.submit-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.35); }
.submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spin { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.manif-list { display: flex; flex-direction: column; gap: 14px; }
.manif-item { display: flex; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; animation: mIn 0.4s cubic-bezier(0.22,1,0.36,1) var(--md) both; }
@keyframes mIn { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: none; } }
.mi-left { width: 52px; flex-shrink: 0; display: flex; align-items: flex-start; justify-content: center; padding-top: 18px; background: color-mix(in srgb, var(--mc) 8%, white); }
.mi-ico { font-size: 24px; }
.mi-body { flex: 1; padding: 16px 18px 16px 4px; }
.mi-hdr { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.mi-tipo { font-size: 13px; font-weight: 800; }
.mi-area { font-size: 12px; color: #64748b; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 2px 8px; }
.mi-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.st-blue { background: #dbeafe; color: #1e40af; }
.st-yellow { background: #fffbeb; color: #92400e; }
.st-green { background: #dcfce7; color: #166534; }
.st-gray { background: #f1f5f9; color: #64748b; }
.mi-desc { font-size: 13px; color: #64748b; margin: 0 0 10px; line-height: 1.5; }
.mi-resposta { background: #f0fdf4; border-left: 3px solid #10b981; border-radius: 0 10px 10px 0; padding: 10px 14px; margin-bottom: 10px; }
.mr-label { font-size: 11px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.mr-texto { font-size: 13px; color: #065f46; margin: 0; line-height: 1.5; }
.mi-footer { display: flex; align-items: center; gap: 12px; }
.mi-proto { font-family: monospace; font-size: 11px; color: #94a3b8; font-weight: 700; }
.mi-data { font-size: 11px; color: #94a3b8; }
.mi-anon { font-size: 11px; color: #6366f1; font-weight: 700; }
.state-empty { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; font-size: 36px; }
.state-empty p { font-size: 14px; color: #94a3b8; margin: 0; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-green { background: #065f46; }
.toast-red   { background: #7f1d1d; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
@media (max-width: 680px) { .form-two-col { grid-template-columns: 1fr; } .tipo-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

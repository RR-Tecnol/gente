<template>
  <div class="exec-dash">
    <header class="exec-head">
      <div>
        <h1 class="exec-title">Painel executivo</h1>
        <p class="exec-sub">Indicadores consolidados da rede — {{ dataLabel }}</p>
      </div>
      <div class="exec-head-meta">
        <label class="exec-date-label">
          <span>Data de referência</span>
          <input v-model="dataRef" type="date" class="exec-date-input" @change="recarregar" />
        </label>
        <p v-if="refreshedLabel" class="exec-cache-hint" title="Dados servidos em cache no servidor (TTL configurável)">
          Última actualização: {{ refreshedLabel }}
        </p>
      </div>
    </header>

    <p v-if="erro" class="exec-erro" role="alert">{{ erro }}</p>
    <p v-else-if="loading" class="exec-loading">A carregar indicadores…</p>

    <div v-else class="exec-grid">
      <article class="exec-card exec-card--slate">
        <span class="exec-card-kicker">RH / cadastro</span>
        <h2 class="exec-card-title">Servidores activos</h2>
        <p class="exec-card-value">{{ formatInt(payload?.total_servidores_ativos) }}</p>
        <p class="exec-card-note">FUNCIONARIO com vínculo activo (regras de data fim / activo conforme schema).</p>
      </article>

      <article class="exec-card exec-card--amber">
        <span class="exec-card-kicker">Operação escolar</span>
        <h2 class="exec-card-title">Taxa de furo de escala</h2>
        <p class="exec-card-value">{{ taxaFuroLabel }}</p>
        <p class="exec-card-note">
          <template v-if="payload?.taxa_furo_escala?.mock">Valor simulado (homolog).</template>
          <template v-else>
            {{ payload?.taxa_furo_escala?.total_furos ?? 0 }} furos em
            {{ payload?.taxa_furo_escala?.total_slots_escala ?? 0 }} lugares escalados
            <span v-if="payload?.taxa_furo_escala?.competencia"> ({{ payload.taxa_furo_escala.competencia }})</span>.
          </template>
        </p>
        <button type="button" class="exec-cta" @click="irAuditarEscalas">Auditar escalas</button>
      </article>

      <article class="exec-card exec-card--indigo">
        <span class="exec-card-kicker">MDE / TCE-MA</span>
        <h2 class="exec-card-title">Elegíveis na rede SEMED</h2>
        <p class="exec-card-value">{{ formatInt(payload?.indice_mde_elegivel) }}</p>
        <p class="exec-card-note">{{ payload?.vmde?.nota_fonte }}</p>
        <p class="exec-formula">{{ payload?.vmde?.formula }}</p>
        <p v-if="payload?.vmde?.vmde == null" class="exec-vmde-pendente">
          Tributos segregados (T<sub>municipais</sub>, T<sub>transferidos</sub>) — integração contábil pendente; V<sub>MDE</sub> não calculado aqui.
        </p>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()
const loading = ref(true)
const erro = ref('')
const payload = ref(null)
const dataRef = ref(new Date().toISOString().slice(0, 10))

const dataLabel = computed(() => {
  try {
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(new Date(dataRef.value + 'T12:00:00'))
  } catch {
    return dataRef.value
  }
})

const refreshedLabel = computed(() => {
  const raw = payload.value?.refreshed_at
  if (!raw) return ''
  try {
    const d = new Date(raw)
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(d)
  } catch {
    return String(raw)
  }
})

const taxaFuroLabel = computed(() => {
  const t = payload.value?.taxa_furo_escala
  if (!t) return '—'
  if (t.mock) {
    return `${(Number(t.taxa) * 100).toFixed(1)} % (simulado)`
  }
  if (t.taxa == null) {
    if ((t.total_slots_escala ?? 0) === 0) return 'Sem escala neste dia'
    return '—'
  }
  return `${(Number(t.taxa) * 100).toFixed(2)} %`
})

function formatInt(n) {
  if (n == null || Number.isNaN(Number(n))) return '—'
  return new Intl.NumberFormat('pt-BR').format(Number(n))
}

async function recarregar() {
  loading.value = true
  erro.value = ''
  try {
    const { data } = await api.get('/api/v3/dashboard/operacional', {
      params: { data: dataRef.value },
    })
    payload.value = data
  } catch (e) {
    payload.value = null
    erro.value = e?.response?.data?.erro || e?.message || 'Falha ao carregar o painel.'
  } finally {
    loading.value = false
  }
}

function irAuditarEscalas() {
  router.push('/escala-trabalho')
}

onMounted(() => {
  recarregar()
})
</script>

<style scoped>
.exec-dash {
  max-width: 1200px;
  margin: 0 auto;
  padding: 8px 4px 32px;
}

.exec-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.exec-title {
  margin: 0 0 4px;
  font-size: 1.65rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.exec-sub {
  margin: 0;
  font-size: 0.9rem;
  color: #64748b;
}

.exec-head-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.exec-date-label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.exec-date-input {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 8px 12px;
  font-size: 14px;
  color: #0f172a;
}

.exec-cache-hint {
  margin: 0;
  font-size: 12px;
  color: #64748b;
  max-width: 280px;
  text-align: right;
}

.exec-erro {
  padding: 14px 18px;
  border-radius: 12px;
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.exec-loading {
  color: #64748b;
  font-weight: 600;
}

.exec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
}

.exec-card {
  border-radius: 20px;
  padding: 22px 24px 20px;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 12px 40px -12px rgba(15, 23, 42, 0.25);
}

.exec-card--slate {
  background: linear-gradient(160deg, #1e293b 0%, #0f172a 100%);
  color: #f8fafc;
}

.exec-card--amber {
  background: linear-gradient(160deg, #78350f 0%, #451a03 100%);
  color: #fffbeb;
}

.exec-card--indigo {
  background: linear-gradient(160deg, #312e81 0%, #1e1b4b 100%);
  color: #eef2ff;
}

.exec-card-kicker {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.85;
  margin-bottom: 8px;
}

.exec-card-title {
  margin: 0 0 12px;
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.25;
}

.exec-card-value {
  margin: 0 0 10px;
  font-size: 2.25rem;
  font-weight: 900;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.exec-card-note {
  margin: 0 0 8px;
  font-size: 0.82rem;
  line-height: 1.45;
  opacity: 0.92;
  flex: 1;
}

.exec-formula {
  margin: 0;
  font-size: 0.75rem;
  line-height: 1.4;
  opacity: 0.88;
  font-style: italic;
}

.exec-vmde-pendente {
  margin: 8px 0 0;
  font-size: 0.78rem;
  opacity: 0.9;
}

.exec-cta {
  margin-top: 14px;
  align-self: flex-start;
  border: none;
  cursor: pointer;
  font-weight: 800;
  font-size: 13px;
  padding: 10px 16px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.2);
  color: #fffbeb;
  transition: background 0.15s;
}

.exec-cta:hover {
  background: rgba(255, 255, 255, 0.32);
}
</style>

<template>
  <section class="sentinel-card">
    <div class="sentinel-head">
      <h3>Sentinela de Integridade</h3>
      <button class="refresh-btn" @click="fetchStatus(true)" :disabled="loading">
        {{ loading ? 'Atualizando…' : 'Atualizar' }}
      </button>
    </div>
    <p class="sentinel-sub">Monitoramento contínuo de segurança, banco e normalização.</p>

    <div class="sentinel-grid" v-if="status">
      <div class="probe">
        <span>Segurança</span>
        <strong :class="statusClass(status.checks?.security_guard?.ok)">{{ status.checks?.security_guard?.ok ? 'OK 🟢' : 'CRÍTICO 🔴' }}</strong>
      </div>
      <div class="probe">
        <span>Banco</span>
        <strong :class="statusClass(status.checks?.db_connection?.ok)">{{ status.checks?.db_connection?.ok ? 'OK 🟢' : 'CRÍTICO 🔴' }}</strong>
      </div>
      <div class="probe">
        <span>Normalização</span>
        <strong :class="statusClass(status.checks?.auth_integrity?.ok)">{{ status.checks?.auth_integrity?.ok ? 'OK 🟢' : 'CRÍTICO 🔴' }}</strong>
      </div>
      <div class="probe">
        <span>Negócio E2E</span>
        <strong :class="statusClass(status.checks?.business_integrity?.ok)">{{ status.checks?.business_integrity?.ok ? 'OK 🟢' : 'CRÍTICO 🔴' }}</strong>
      </div>
      <div class="probe">
        <span>Auditoria</span>
        <strong :class="statusClass(checkState(status.checks?.audit_trail_check))">{{ checkLabel(status.checks?.audit_trail_check) }}</strong>
      </div>
      <div class="probe full">
        <span>Integridade de Dados</span>
        <strong :class="statusClass(status.checks?.schema_integrity?.ok)" style="text-align:right">
          {{ status.checks?.schema_integrity?.ok ? 'OK 🟢' : `CRÍTICO 🔴 (${status.checks?.schema_integrity?.message || 'Falha de schema'})` }}
        </strong>
      </div>
      <div v-if="impactedModules.length" class="probe full cascade">
        ⚠️ Módulos afetados: {{ impactedModules.join(', ') }}
      </div>
    </div>

    <p v-if="status?.timestamp" class="sentinel-time">
      Última verificação: {{ new Date(status.timestamp).toLocaleString('pt-BR') }}
    </p>
    <div v-if="auditEvents.length" class="audit-box">
      <div class="audit-head">
        <h4>Auditoria sensível (últimos eventos)</h4>
        <select v-model="auditFilter" class="audit-filter">
          <option value="all">Todos</option>
          <option value="read">Leitura Sensível</option>
          <option value="high">Alta Prioridade</option>
        </select>
      </div>
      <ul>
        <li v-for="(ev, idx) in filteredAuditEvents" :key="idx">
          <strong>{{ ev.USUARIO_LOGIN || ev.USUARIO_NOME || `#${ev.USUARIO_ID}` }}</strong>
          <span class="evt-tag" :class="eventClass(ev)">{{ eventLabel(ev) }}</span>
          <span> • {{ ev.context || ev.ACAO }} • {{ new Date(ev.created_at).toLocaleString('pt-BR') }}</span>
        </li>
      </ul>
    </div>
    <p v-else-if="auditWarning" class="audit-warning">{{ auditWarning }}</p>
    <p v-if="error" class="sentinel-error">{{ error }}</p>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import api from '@/plugins/axios'

const status = ref(null)
const loading = ref(false)
const error = ref('')
let t = null

const impactedModules = ref([])
const auditEvents = ref([])
const auditWarning = ref('')
const auditFilter = ref('all')

function checkState(check) {
  if (!check || check.ok === false) return 'critical'
  if (check.warning) return 'warning'
  return 'ok'
}

function checkLabel(check) {
  const state = checkState(check)
  if (state === 'critical') return 'CRÍTICO 🔴'
  if (state === 'warning') return 'ALERTA 🟡'
  return 'OK 🟢'
}

function statusClass(state) {
  if (state === true || state === 'ok') return 'ok'
  if (state === 'warning') return 'warning'
  return 'critical'
}

function eventClass(ev) {
  if (ev?.event_type === 'LEITURA_SENSIVEL' && ev?.priority === 'ALTA') return 'evt-high'
  if (ev?.event_type === 'LEITURA_SENSIVEL') return 'evt-read'
  return 'evt-write'
}

function eventLabel(ev) {
  if (ev?.event_type === 'LEITURA_SENSIVEL') return 'ACESSO_SENSÍVEL'
  return 'AÇÃO'
}

const filteredAuditEvents = computed(() => {
  if (auditFilter.value === 'read') {
    return auditEvents.value.filter((ev) => ev?.event_type === 'LEITURA_SENSIVEL')
  }
  if (auditFilter.value === 'high') {
    return auditEvents.value.filter((ev) => ev?.priority === 'ALTA')
  }
  return auditEvents.value
})

async function fetchStatus(refresh = false) {
  loading.value = true
  error.value = ''
  auditWarning.value = ''
  try {
    const { data } = await api.get('/api/v3/health', { params: { refresh } })
    status.value = data
    const schemaImp = Array.isArray(data?.checks?.schema_integrity?.impacted_modules)
      ? data.checks.schema_integrity.impacted_modules
      : []
    const bizImp = Array.isArray(data?.checks?.business_integrity?.impacted_modules)
      ? data.checks.business_integrity.impacted_modules
      : []
    impactedModules.value = [...new Set([...schemaImp, ...bizImp])]
    try {
      const auditResp = await api.get('/api/v3/audit/sensitive-recent')
      const warning = auditResp?.headers?.['x-audit-warning']
      auditWarning.value = warning ? 'Logs indisponíveis no momento.' : ''
      auditEvents.value = Array.isArray(auditResp?.data?.events) ? auditResp.data.events.slice(0, 8) : []
    } catch (e) {
      auditEvents.value = []
      auditWarning.value = 'Logs indisponíveis no momento.'
    }
  } catch (e) {
    error.value = e?.response?.data?.error || 'Falha ao carregar status da Sentinela.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchStatus(false)
  t = setInterval(() => fetchStatus(false), 60_000)
})

onUnmounted(() => {
  if (t) clearInterval(t)
})
</script>

<style scoped>
.sentinel-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px}
.sentinel-head{display:flex;justify-content:space-between;align-items:center;gap:12px}
.sentinel-head h3{margin:0;font-size:15px;color:#0f172a}
.sentinel-sub{margin:4px 0 12px;color:#64748b;font-size:12px}
.refresh-btn{border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;padding:6px 10px;cursor:pointer}
.sentinel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.probe{border:1px solid #f1f5f9;border-radius:10px;padding:10px;display:flex;justify-content:space-between;gap:8px}
.probe.full{grid-column:1 / -1}
.probe.cascade{display:block;background:#fff7ed;border-color:#fed7aa;color:#9a3412;font-size:12px}
.probe span{color:#475569;font-size:12px}
.probe strong{font-size:12px}
.ok{color:#166534}.warning{color:#92400e}.critical{color:#991b1b}
.sentinel-time{margin:10px 0 0;font-size:11px;color:#64748b}
.sentinel-error{margin:10px 0 0;font-size:12px;color:#b91c1c}
.audit-box{margin-top:12px;border:1px solid #e2e8f0;border-radius:10px;padding:10px}
.audit-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}
.audit-box h4{margin:0 0 8px;font-size:12px;color:#0f172a}
.audit-box ul{margin:0;padding-left:16px}
.audit-box li{font-size:11px;color:#334155;margin-bottom:6px}
.audit-warning{margin-top:10px;font-size:12px;color:#92400e}
.audit-filter{border:1px solid #cbd5e1;border-radius:8px;padding:4px 8px;font-size:11px;background:#fff}
.evt-tag{display:inline-block;padding:1px 6px;border-radius:999px;font-size:10px;margin:0 6px}
.evt-read{background:#dbeafe;color:#1d4ed8}
.evt-write{background:#dcfce7;color:#166534}
.evt-high{background:#fee2e2;color:#991b1b}
</style>


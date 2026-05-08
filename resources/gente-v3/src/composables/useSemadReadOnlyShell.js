import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { getNavGateMeta } from '@/navigation/navManifest'

/**
 * Leitura forçada para auditores SEMAD nas rotas sob anel com semad_block_mutations (payload /me).
 * Chapéu duplo: `semad_manta_ui_readonly` vem do backend (false se houver escala.grade.editar na âncora SEMED).
 */
export function useSemadReadOnlyShell() {
  const auth = useAuthStore()
  const route = useRoute()

  const isReadOnly = computed(() => {
    if (!auth.semadMantaUiReadonlyForShell) return false
    const gate = getNavGateMeta(route.path)
    if (!gate?.ringKey) return false
    const ring = auth.tenantScopeRingsPublic[gate.ringKey]
    return !!(ring && ring.semad_block_mutations)
  })

  return { isReadOnly }
}

<template>
  <div v-if="meta && meta.last_page > 1" class="prog-pag">
    <button type="button" class="act-btn act-outline" :disabled="meta.current_page <= 1" @click="$emit('page', meta.current_page - 1)">‹ Anterior</button>
    <span class="prog-pag-txt">{{ meta.current_page }} / {{ meta.last_page }} ({{ meta.total }} registros)</span>
    <button type="button" class="act-btn act-outline" :disabled="meta.current_page >= meta.last_page" @click="$emit('page', meta.current_page + 1)">Próximo ›</button>
  </div>
</template>

<script setup>
defineProps({
  meta: { type: Object, default: null },
})
defineEmits(['page'])
</script>

<style scoped>
.prog-pag {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}
.prog-pag-txt {
  font-size: 13px;
  color: #64748b;
}
.act-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 12px;
  border: none;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
  line-height: 1;
  white-space: nowrap;
  min-height: 38px;
}
.act-outline {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  color: #475569;
}
.act-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
</style>

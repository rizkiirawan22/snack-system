<template>
  <div v-if="meta && meta.last_page > 1" class="pagination">
    <button class="page-btn" :disabled="meta.current_page === 1" @click="$emit('change', meta.current_page - 1)">‹</button>
    <template v-for="page in pages" :key="page">
      <span v-if="page === '...'" class="page-dots">...</span>
      <button v-else class="page-btn" :class="{ active: page === meta.current_page }" @click="$emit('change', page)">
        {{ page }}
      </button>
    </template>
    <button class="page-btn" :disabled="meta.current_page === meta.last_page" @click="$emit('change', meta.current_page + 1)">›</button>
    <span class="page-info">{{ meta.from }}–{{ meta.to }} dari {{ meta.total }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ meta: Object })
defineEmits(['change'])

const pages = computed(() => {
  if (!props.meta) return []
  const { current_page: cur, last_page: last } = props.meta
  const range = []
  for (let i = Math.max(1, cur - 2); i <= Math.min(last, cur + 2); i++) range.push(i)
  if (range[0] > 1) { range.unshift('...'); range.unshift(1) }
  if (range[range.length - 1] < last) { range.push('...'); range.push(last) }
  return range
})
</script>

<style scoped>
.pagination { display: flex; align-items: center; gap: 4px; margin-top: 16px; }

.page-btn {
  height: 32px; min-width: 32px; padding: 0 8px;
  border: 1px solid #e8e8e8; background: #fff;
  border-radius: 8px; font-size: 13px; cursor: pointer;
  color: #555; transition: all 0.15s;
}

.page-btn:hover:not(:disabled) { border-color: #b8f000; color: #1a1a1a; }
.page-btn.active { background: #1a1a1a; border-color: #1a1a1a; color: #b8f000; font-weight: 600; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-dots { padding: 0 4px; color: #aaa; font-size: 13px; }
.page-info { margin-left: 8px; font-size: 12px; color: #aaa; }
</style>
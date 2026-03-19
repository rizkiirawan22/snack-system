<template>
  <div class="table-wrapper">
    <table class="app-table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key" :style="{ width: col.width }">
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td :colspan="columns.length" class="loading-cell">Memuat data...</td>
        </tr>
        <tr v-else-if="!data.length">
          <td :colspan="columns.length" class="empty-cell">Tidak ada data.</td>
        </tr>
        <tr v-for="(row, i) in data" :key="i" v-else>
          <slot :row="row" />
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  columns: Array,
  data: { type: Array, default: () => [] },
  loading: Boolean,
})
</script>

<style scoped>
.table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); transition: border-color var(--transition); }

.app-table { width: 100%; border-collapse: collapse; font-size: 14px; background: var(--bg-surface); transition: background var(--transition); }

.app-table thead tr { border-bottom: 1px solid var(--border); }

.app-table th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
  background: var(--bg-surface-2);
  transition: background var(--transition), color var(--transition);
}

.app-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
.app-table tbody tr:last-child { border-bottom: none; }
.app-table tbody tr:hover { background: var(--bg-surface-2); }

:deep(td) { padding: 14px 16px; color: var(--text-2); vertical-align: middle; }

.loading-cell, .empty-cell {
  text-align: center;
  padding: 40px;
  color: var(--text-4);
  font-size: 14px;
}
</style>
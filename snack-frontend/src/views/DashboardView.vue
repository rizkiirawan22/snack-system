<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Selamat datang, {{ auth.user?.name }}</p>
      </div>
      <span class="date-badge">{{ today }}</span>
    </div>

    <!-- Stat cards -->
    <div class="grid-4" style="margin-bottom:24px">
      <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value">{{ formatRp(stats.today_revenue) }}</div>
        <div class="stat-label">Pendapatan Hari Ini</div>
        <span class="stat-badge up">{{ stats.today_transactions }} transaksi</span>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value">{{ formatRp(stats.month_revenue) }}</div>
        <div class="stat-label">Pendapatan Bulan Ini</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value">{{ stats.total_products }}</div>
        <div class="stat-label">Total Produk Aktif</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value">{{ stats.low_stock_count }}</div>
        <div class="stat-label">Stok Menipis</div>
        <span v-if="stats.low_stock_count > 0" class="stat-badge warn">Perlu restock</span>
      </div>
    </div>

    <div class="grid-2">
      <!-- Recent sales -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Transaksi Terakhir</h3>
          <RouterLink to="/sales" class="btn btn-ghost btn-sm">Lihat Semua</RouterLink>
        </div>
        <div class="recent-list">
          <div v-if="loading" class="empty-state">Memuat...</div>
          <div v-else-if="!stats.recent_sales?.length" class="empty-state">Belum ada transaksi.</div>
          <div v-for="sale in stats.recent_sales" :key="sale.id" class="recent-item">
            <div class="recent-info">
              <div class="recent-inv">{{ sale.invoice_no }}</div>
              <div class="recent-by">Kasir: {{ sale.user?.name }}</div>
            </div>
            <div class="recent-total">{{ formatRp(sale.total) }}</div>
          </div>
        </div>
      </div>

      <!-- Low stock alert -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Stok Menipis</h3>
          <RouterLink to="/products" class="btn btn-ghost btn-sm">Kelola Stok</RouterLink>
        </div>
        <div class="recent-list">
          <div v-if="!lowStock.length" class="empty-state">Semua stok aman ✓</div>
          <div v-for="p in lowStock" :key="p.id" class="recent-item">
            <div class="recent-info">
              <div class="recent-inv">{{ p.name }}</div>
              <div class="recent-by">{{ p.category?.name }}</div>
            </div>
            <span class="badge" :class="p.stock?.quantity === 0 ? 'badge-red' : 'badge-yellow'">
              {{ p.stock?.quantity ?? 0 }} pack
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/api/axios'

const auth    = useAuthStore()
const loading = ref(true)
const stats   = ref({ today_revenue: 0, today_transactions: 0, month_revenue: 0, total_products: 0, low_stock_count: 0, recent_sales: [] })
const lowStock = ref([])

const today = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

function formatRp(val) {
  return 'Rp ' + Number(val || 0).toLocaleString('id-ID')
}

onMounted(async () => {
  const [dashRes, stockRes] = await Promise.all([
    api.get('/dashboard'),
    api.get('/products/low-stock'),
  ])
  stats.value    = dashRes.data
  lowStock.value = stockRes.data
  loading.value  = false
})
</script>

<style scoped>
.stat-icon { font-size: 22px; margin-bottom: 8px; }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.card-title  { font-size: 15px; font-weight: 600; }
.date-badge  { font-size: 13px; color: #888; background: #f0f0ee; padding: 6px 14px; border-radius: 20px; white-space: nowrap; }

.recent-list { display: flex; flex-direction: column; gap: 10px; }
.recent-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f5f5; gap: 8px; }
.recent-item:last-child { border-bottom: none; }
.recent-inv  { font-size: 14px; font-weight: 500; }
.recent-by   { font-size: 12px; color: #aaa; margin-top: 2px; }
.recent-total { font-weight: 600; font-size: 14px; white-space: nowrap; }
.empty-state { text-align: center; color: #aaa; font-size: 14px; padding: 20px 0; }
</style>
<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Laporan</h1>
        <p class="page-subtitle">Analitik penjualan, profit, dan stok</p>
      </div>
    </div>

    <!-- Filter tanggal -->
    <div class="card" style="margin-bottom:24px">
      <div class="filter-bar" style="margin:0">
        <div style="display:flex;align-items:center;gap:8px;flex:1;flex-wrap:wrap">
          <label class="form-label" style="margin:0;white-space:nowrap">Dari</label>
          <input v-model="filters.date_from" type="date" class="form-control" />
          <label class="form-label" style="margin:0;white-space:nowrap">Sampai</label>
          <input v-model="filters.date_to" type="date" class="form-control" />
        </div>
        <button class="btn btn-primary" :disabled="loading" @click="fetchAll">
          {{ loading ? 'Memuat...' : 'Tampilkan' }}
        </button>
      </div>
    </div>

    <template v-if="loaded">
      <!-- Tab + export buttons -->
      <div class="tab-bar">
        <div class="tab-nav">
          <button v-for="t in tabs" :key="t.key" class="tab-btn" :class="{ active: activeTab === t.key }" @click="activeTab = t.key">
            {{ t.label }}
          </button>
        </div>
        <div class="export-btns">
          <button class="btn btn-ghost btn-sm" @click="doExportCsv">↓ CSV</button>
          <button class="btn btn-ghost btn-sm" @click="doExportPdf">⎙ PDF</button>
        </div>
      </div>

      <!-- ===== TAB: PENJUALAN ===== -->
      <template v-if="activeTab === 'sales'">
        <div class="grid-stats" style="margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-value">{{ summary.total_transactions }}</div>
            <div class="stat-label">Total Transaksi</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ formatRp(summary.total_revenue) }}</div>
            <div class="stat-label">Total Pendapatan</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ formatRp(summary.avg_transaction) }}</div>
            <div class="stat-label">Rata-rata / Transaksi</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ formatRp(summary.total_discount) }}</div>
            <div class="stat-label">Total Diskon</div>
          </div>
          <div class="stat-card">
            <div class="stat-value" :class="summary.void_count > 0 ? 'text-red' : ''">{{ summary.void_count }}</div>
            <div class="stat-label">Transaksi Void</div>
          </div>
        </div>

        <div class="grid-2" style="margin-bottom:24px">
          <div class="card">
            <h3 class="card-title" style="margin-bottom:16px">Metode Pembayaran</h3>
            <div v-for="m in summary.by_payment_method" :key="m.payment_method" class="method-row">
              <div class="method-info">
                <span class="method-name">{{ m.payment_method }}</span>
                <span class="method-count">{{ m.count }} transaksi</span>
              </div>
              <div style="text-align:right">
                <div class="method-total">{{ formatRp(m.total) }}</div>
                <div class="method-pct">{{ pct(m.total, summary.total_revenue) }}%</div>
              </div>
            </div>
          </div>

          <div class="card">
            <h3 class="card-title" style="margin-bottom:16px">Top 10 Produk Terlaris</h3>
            <div v-for="(p, i) in topProducts" :key="p.product_id" class="top-product-row">
              <span class="top-rank">#{{ i + 1 }}</span>
              <div class="top-info">
                <div class="top-name">{{ p.product?.name }}</div>
                <div class="top-cat">{{ p.product?.category?.name }}</div>
              </div>
              <div class="top-right">
                <div class="top-qty">{{ p.total_qty }} pack</div>
                <div class="top-rev">{{ formatRp(p.total_revenue) }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <h3 class="card-title" style="margin-bottom:16px">Penjualan Harian</h3>
          <div class="daily-table">
            <div class="daily-head">
              <span>Tanggal</span><span>Transaksi</span><span>Pendapatan</span><span>Grafik</span>
            </div>
            <div v-for="d in summary.daily" :key="d.date" class="daily-row">
              <span>{{ formatDate(d.date) }}</span>
              <span>{{ d.transactions }} trx</span>
              <span>{{ formatRp(d.revenue) }}</span>
              <div class="bar-wrap">
                <div class="bar" :style="{ width: barWidth(d.revenue) + '%' }" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ===== TAB: PER KATEGORI ===== -->
      <template v-else-if="activeTab === 'category'">
        <div class="card">
          <h3 class="card-title" style="margin-bottom:16px">Penjualan Per Kategori</h3>
          <div v-if="!categoryData.length" class="empty-section">Tidak ada data pada periode ini.</div>
          <template v-else>
            <div class="cat-head">
              <span>Kategori</span>
              <span style="text-align:right">Produk</span>
              <span style="text-align:right">Qty Terjual</span>
              <span style="text-align:right">Pendapatan</span>
              <span>Porsi</span>
            </div>
            <div v-for="c in categoryData" :key="c.category_name" class="cat-row">
              <span class="cat-name">{{ c.category_name }}</span>
              <span style="text-align:right">{{ c.product_count }} produk</span>
              <span style="text-align:right">{{ c.total_qty }} pack</span>
              <span style="text-align:right;font-weight:600">{{ formatRp(c.total_revenue) }}</span>
              <div class="cat-bar-wrap">
                <div class="cat-bar" :style="{ width: pct(c.total_revenue, totalCategoryRevenue) + '%' }" />
                <span class="cat-pct">{{ pct(c.total_revenue, totalCategoryRevenue) }}%</span>
              </div>
            </div>
            <div class="cat-total">
              <span>Total</span>
              <span></span>
              <span style="text-align:right">{{ categoryData.reduce((s,c)=>s+c.total_qty,0) }} pack</span>
              <span style="text-align:right;font-weight:700">{{ formatRp(totalCategoryRevenue) }}</span>
              <span></span>
            </div>
          </template>
        </div>
      </template>

      <!-- ===== TAB: PROFIT ===== -->
      <template v-else-if="activeTab === 'profit'">
        <div class="grid-4" style="margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-value">{{ formatRp(profitData.summary?.total_revenue) }}</div>
            <div class="stat-label">Total Pendapatan</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ formatRp(profitData.summary?.total_cost) }}</div>
            <div class="stat-label">Total HPP</div>
          </div>
          <div class="stat-card stat-card-green">
            <div class="stat-value">{{ formatRp(profitData.summary?.total_profit) }}</div>
            <div class="stat-label">Total Laba Kotor</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ overallMargin }}%</div>
            <div class="stat-label">Margin Keseluruhan</div>
          </div>
        </div>

        <div class="card">
          <h3 class="card-title" style="margin-bottom:16px">Laba Per Produk</h3>
          <div class="table-scroll">
            <div class="profit-head">
              <span>Produk</span>
              <span>Kategori</span>
              <span style="text-align:right">Qty</span>
              <span style="text-align:right">Pendapatan</span>
              <span style="text-align:right">HPP</span>
              <span style="text-align:right">Laba</span>
              <span style="text-align:right">Margin</span>
            </div>
            <div v-for="p in profitData.products" :key="p.product_id" class="profit-row">
              <div>
                <div class="profit-name">{{ p.product_name }}</div>
                <div class="profit-code">{{ p.product_code }}</div>
              </div>
              <span>{{ p.category }}</span>
              <span style="text-align:right">{{ p.total_qty }}</span>
              <span style="text-align:right">{{ formatRp(p.total_revenue) }}</span>
              <span style="text-align:right">{{ formatRp(p.total_cost) }}</span>
              <span style="text-align:right" :class="p.total_profit >= 0 ? 'profit-pos' : 'profit-neg'">{{ formatRp(p.total_profit) }}</span>
              <span style="text-align:right"><span class="margin-badge" :class="marginClass(p.margin_pct)">{{ p.margin_pct }}%</span></span>
            </div>
          </div>
        </div>
      </template>

      <!-- ===== TAB: REKAP KASIR ===== -->
      <template v-else-if="activeTab === 'cashier'">
        <div class="card">
          <h3 class="card-title" style="margin-bottom:16px">Rekap Per Kasir</h3>
          <div v-if="!cashierData.length" class="empty-section">Tidak ada data pada periode ini.</div>
          <div v-for="c in cashierData" :key="c.user_id" class="cashier-card">
            <div class="cashier-avatar">{{ c.user?.name?.charAt(0) }}</div>
            <div class="cashier-info">
              <div class="cashier-name">{{ c.user?.name }}</div>
              <div class="cashier-role">{{ c.user?.role }}</div>
            </div>
            <div class="cashier-stats">
              <div class="cashier-stat">
                <div class="cs-value">{{ c.total_transactions }}</div>
                <div class="cs-label">Transaksi</div>
              </div>
              <div class="cashier-stat">
                <div class="cs-value">{{ formatRp(c.total_revenue) }}</div>
                <div class="cs-label">Pendapatan</div>
              </div>
              <div class="cashier-stat">
                <div class="cs-value">{{ formatRp(c.total_discount) }}</div>
                <div class="cs-label">Diskon</div>
              </div>
              <div class="cashier-stat">
                <div class="cs-value">{{ formatRp(c.total_transactions > 0 ? c.total_revenue / c.total_transactions : 0) }}</div>
                <div class="cs-label">Rata-rata/Trx</div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ===== TAB: STOK ===== -->
      <template v-else-if="activeTab === 'stock'">
        <!-- Nilai stok summary -->
        <div class="grid-3" style="margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-value">{{ stockReport.length }}</div>
            <div class="stat-label">Total Produk Aktif</div>
          </div>
          <div class="stat-card">
            <div class="stat-value" :class="lowStockCount > 0 ? 'text-red' : ''">{{ lowStockCount }}</div>
            <div class="stat-label">Stok Menipis / Habis</div>
          </div>
          <div class="stat-card stat-card-green">
            <div class="stat-value">{{ formatRp(totalStockValue) }}</div>
            <div class="stat-label">Total Nilai Stok (HPP)</div>
          </div>
        </div>

        <div class="card">
          <h3 class="card-title" style="margin-bottom:16px">Kondisi Stok Saat Ini</h3>
          <div class="table-scroll">
            <div class="stock-table-head">
              <span>Kode</span><span>Produk</span><span>Kategori</span><span>Berat</span>
              <span style="text-align:right">Stok</span><span style="text-align:right">Min.</span>
              <span style="text-align:right">Harga Beli (AVCO)</span><span style="text-align:right">Nilai Stok</span><span>Status</span>
            </div>
            <div v-for="p in stockReport" :key="p.id" class="stock-table-row">
              <span class="code-mono">{{ p.code }}</span>
              <span>{{ p.name }}</span>
              <span>{{ p.category }}</span>
              <span>{{ p.weight }}gr</span>
              <span style="text-align:right;font-weight:600">{{ p.stock }}</span>
              <span style="text-align:right">{{ p.min_stock }}</span>
              <span style="text-align:right">{{ formatRp(p.avg_purchase_price) }}</span>
              <span style="text-align:right">{{ formatRp(p.stock_value) }}</span>
              <span>
                <span class="badge" :class="p.is_low ? (p.stock === 0 ? 'badge-red' : 'badge-yellow') : 'badge-green'">
                  {{ p.stock === 0 ? 'Habis' : p.is_low ? 'Menipis' : 'Aman' }}
                </span>
              </span>
            </div>
            <div class="stock-total-row">
              <span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span>
              <span style="text-align:right;font-weight:700">{{ formatRp(totalStockValue) }}</span>
              <span></span>
            </div>
          </div>
        </div>
      </template>

      <!-- ===== TAB: SUPPLIER ===== -->
      <template v-else-if="activeTab === 'supplier'">
        <div class="card">
          <h3 class="card-title" style="margin-bottom:16px">Laporan Per Supplier</h3>
          <div v-if="!supplierReport.length" class="empty-section">Tidak ada data pada periode ini.</div>
          <template v-else>
            <div class="supplier-head">
              <span>Supplier</span>
              <span>Telepon</span>
              <span style="text-align:right">Stok Masuk</span>
              <span style="text-align:right">Total Item</span>
              <span style="text-align:right">Total Nilai Beli</span>
            </div>
            <div v-for="s in supplierReport" :key="s.supplier_id" class="supplier-row">
              <span class="supplier-name">{{ s.supplier_name }}</span>
              <span>{{ s.supplier_phone || '-' }}</span>
              <span style="text-align:right">{{ s.total_stock_ins }}x</span>
              <span style="text-align:right">{{ s.total_items }} pack</span>
              <span style="text-align:right;font-weight:700">{{ formatRp(s.total_value) }}</span>
            </div>
            <div class="supplier-total">
              <span>Total</span><span></span>
              <span style="text-align:right">{{ supplierReport.reduce((s,r) => s + r.total_stock_ins, 0) }}x</span>
              <span style="text-align:right">{{ supplierReport.reduce((s,r) => s + r.total_items, 0) }} pack</span>
              <span style="text-align:right;font-weight:700">{{ formatRp(supplierReport.reduce((s,r) => s + r.total_value, 0)) }}</span>
            </div>
          </template>
        </div>
      </template>
    </template>

    <div v-else class="empty-report">
      <div class="empty-icon">📊</div>
      <p>Pilih rentang tanggal dan klik Tampilkan untuk melihat laporan.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import api from '@/api/axios'
import { exportCsv, exportPdf } from '@/utils/export'

const loading      = ref(false)
const loaded       = ref(false)
const activeTab    = ref('sales')
const summary      = ref({})
const topProducts  = ref([])
const stockReport  = ref([])
const profitData   = ref({ summary: {}, products: [] })
const cashierData  = ref([])
const supplierReport = ref([])
const categoryData = ref([])

const tabs = [
  { key: 'sales',    label: 'Penjualan' },
  { key: 'category', label: 'Per Kategori' },
  { key: 'profit',   label: 'Profit & Margin' },
  { key: 'cashier',  label: 'Rekap Kasir' },
  { key: 'stock',    label: 'Kondisi Stok' },
  { key: 'supplier', label: 'Supplier' },
]

const now = new Date()
const filters = reactive({
  date_from: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10),
  date_to:   now.toISOString().slice(0, 10),
})

const formatRp   = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')
const formatDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short' }) : '-'
const pct        = (val, total) => total > 0 ? ((val / total) * 100).toFixed(1) : '0.0'

const maxRevenue = () => Math.max(...(summary.value?.daily?.map((d) => d.revenue) ?? [1]))
const barWidth   = (rev) => (rev / maxRevenue()) * 100

const overallMargin = computed(() => {
  const rev = profitData.value.summary?.total_revenue
  const profit = profitData.value.summary?.total_profit
  if (!rev || rev === 0) return '0.0'
  return ((profit / rev) * 100).toFixed(1)
})

const totalCategoryRevenue = computed(() =>
  categoryData.value.reduce((s, c) => s + c.total_revenue, 0)
)

const totalStockValue = computed(() =>
  stockReport.value.reduce((s, p) => s + p.stock_value, 0)
)

const lowStockCount = computed(() =>
  stockReport.value.filter(p => p.is_low).length
)

function marginClass(pct) {
  if (pct >= 30) return 'margin-high'
  if (pct >= 15) return 'margin-mid'
  return 'margin-low'
}

async function fetchAll() {
  loading.value = true
  const params = filters
  const [sumRes, topRes, stkRes, profRes, cashRes, supRes, catRes] = await Promise.all([
    api.get('/reports/sales-summary',    { params }),
    api.get('/reports/top-products',     { params }),
    api.get('/reports/stock'),
    api.get('/reports/profit',           { params }),
    api.get('/reports/cashier-summary',  { params }),
    api.get('/reports/supplier',         { params }),
    api.get('/reports/sales-by-category',{ params }),
  ])
  summary.value        = sumRes.data
  topProducts.value    = topRes.data
  stockReport.value    = stkRes.data
  profitData.value     = profRes.data
  cashierData.value    = cashRes.data
  supplierReport.value = supRes.data
  categoryData.value   = catRes.data
  loaded.value  = true
  loading.value = false
}

const dateRange = () => `${filters.date_from} s/d ${filters.date_to}`

// ── CSV export ──────────────────────────────────────────────────────────────
function doExportCsv() {
  const dr = `${filters.date_from}_sd_${filters.date_to}`
  if (activeTab.value === 'sales') {
    exportCsv(summary.value.daily ?? [], [
      { key: 'date', label: 'Tanggal' },
      { key: 'transactions', label: 'Transaksi' },
      { key: 'revenue', label: 'Pendapatan' },
    ], `penjualan_harian_${dr}.csv`)
  } else if (activeTab.value === 'category') {
    exportCsv(categoryData.value, [
      { key: 'category_name', label: 'Kategori' },
      { key: 'product_count', label: 'Jumlah Produk' },
      { key: 'total_qty',     label: 'Qty Terjual' },
      { key: 'total_revenue', label: 'Pendapatan' },
    ], `penjualan_kategori_${dr}.csv`)
  } else if (activeTab.value === 'profit') {
    exportCsv(profitData.value.products ?? [], [
      { key: 'product_code',  label: 'Kode' },
      { key: 'product_name',  label: 'Produk' },
      { key: 'category',      label: 'Kategori' },
      { key: 'total_qty',     label: 'Qty' },
      { key: 'total_revenue', label: 'Pendapatan' },
      { key: 'total_cost',    label: 'HPP' },
      { key: 'total_profit',  label: 'Laba' },
      { key: 'margin_pct',    label: 'Margin (%)' },
    ], `profit_${dr}.csv`)
  } else if (activeTab.value === 'cashier') {
    exportCsv(cashierData.value, [
      { key: 'user.name',          label: 'Kasir' },
      { key: 'total_transactions', label: 'Transaksi' },
      { key: 'total_revenue',      label: 'Pendapatan' },
      { key: 'total_discount',     label: 'Diskon' },
    ], `rekap_kasir_${dr}.csv`)
  } else if (activeTab.value === 'stock') {
    exportCsv(stockReport.value, [
      { key: 'code',               label: 'Kode' },
      { key: 'name',               label: 'Produk' },
      { key: 'category',           label: 'Kategori' },
      { key: 'weight',             label: 'Berat (gr)' },
      { key: 'stock',              label: 'Stok' },
      { key: 'min_stock',          label: 'Min. Stok' },
      { key: 'avg_purchase_price', label: 'Harga Beli AVCO' },
      { key: 'stock_value',        label: 'Nilai Stok' },
    ], `kondisi_stok_${dr}.csv`)
  } else if (activeTab.value === 'supplier') {
    exportCsv(supplierReport.value, [
      { key: 'supplier_name',   label: 'Supplier' },
      { key: 'supplier_phone',  label: 'Telepon' },
      { key: 'total_stock_ins', label: 'Stok Masuk' },
      { key: 'total_items',     label: 'Total Item' },
      { key: 'total_value',     label: 'Total Nilai Beli' },
    ], `laporan_supplier_${dr}.csv`)
  }
}

// ── PDF export ──────────────────────────────────────────────────────────────
function doExportPdf() {
  const sub = 'Periode: ' + dateRange()
  const rp  = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')

  if (activeTab.value === 'sales') {
    exportPdf({
      title: 'Laporan Penjualan Harian',
      subtitle: sub,
      summary: [
        { label: 'Total Transaksi',     value: summary.value.total_transactions },
        { label: 'Total Pendapatan',    value: rp(summary.value.total_revenue) },
        { label: 'Rata-rata / Trx',     value: rp(summary.value.avg_transaction) },
        { label: 'Total Diskon',        value: rp(summary.value.total_discount) },
        { label: 'Transaksi Void',      value: summary.value.void_count },
      ],
      columns: [
        { label: 'Tanggal',     key: 'date' },
        { label: 'Transaksi',   key: 'transactions', align: 'right' },
        { label: 'Pendapatan',  key: 'revenue',      align: 'right' },
      ],
      rows: summary.value.daily ?? [],
      cellValue: (key, row) => key === 'revenue' ? rp(row.revenue) : null,
    })
  } else if (activeTab.value === 'category') {
    exportPdf({
      title: 'Laporan Penjualan Per Kategori',
      subtitle: sub,
      columns: [
        { label: 'Kategori',        key: 'category_name' },
        { label: 'Jml Produk',      key: 'product_count', align: 'right' },
        { label: 'Qty Terjual',     key: 'total_qty',     align: 'right' },
        { label: 'Pendapatan',      key: 'total_revenue', align: 'right' },
      ],
      rows: categoryData.value,
      cellValue: (key, row) => key === 'total_revenue' ? rp(row.total_revenue) : null,
    })
  } else if (activeTab.value === 'profit') {
    exportPdf({
      title: 'Laporan Profit & Margin',
      subtitle: sub,
      summary: [
        { label: 'Total Pendapatan', value: rp(profitData.value.summary?.total_revenue) },
        { label: 'Total HPP',        value: rp(profitData.value.summary?.total_cost) },
        { label: 'Total Laba',       value: rp(profitData.value.summary?.total_profit) },
        { label: 'Margin Overall',   value: overallMargin.value + '%' },
      ],
      columns: [
        { label: 'Kode',        key: 'product_code' },
        { label: 'Produk',      key: 'product_name' },
        { label: 'Kategori',    key: 'category' },
        { label: 'Qty',         key: 'total_qty',     align: 'right' },
        { label: 'Pendapatan',  key: 'total_revenue', align: 'right' },
        { label: 'HPP',         key: 'total_cost',    align: 'right' },
        { label: 'Laba',        key: 'total_profit',  align: 'right' },
        { label: 'Margin',      key: 'margin_pct',    align: 'right' },
      ],
      rows: profitData.value.products ?? [],
      cellValue: (key, row) => {
        if (['total_revenue','total_cost','total_profit'].includes(key)) return rp(row[key])
        if (key === 'margin_pct') return row.margin_pct + '%'
        return null
      },
    })
  } else if (activeTab.value === 'cashier') {
    exportPdf({
      title: 'Rekap Kasir',
      subtitle: sub,
      columns: [
        { label: 'Kasir',        key: 'user.name' },
        { label: 'Role',         key: 'user.role' },
        { label: 'Transaksi',    key: 'total_transactions', align: 'right' },
        { label: 'Pendapatan',   key: 'total_revenue',      align: 'right' },
        { label: 'Diskon',       key: 'total_discount',     align: 'right' },
      ],
      rows: cashierData.value,
      cellValue: (key, row) => {
        if (['total_revenue','total_discount'].includes(key)) return rp(row[key])
        return null
      },
    })
  } else if (activeTab.value === 'stock') {
    exportPdf({
      title: 'Kondisi Stok',
      subtitle: 'Per ' + new Date().toLocaleDateString('id-ID'),
      summary: [
        { label: 'Total Produk',    value: stockReport.value.length },
        { label: 'Stok Menipis',    value: lowStockCount.value },
        { label: 'Total Nilai Stok',value: rp(totalStockValue.value) },
      ],
      columns: [
        { label: 'Kode',          key: 'code' },
        { label: 'Produk',        key: 'name' },
        { label: 'Kategori',      key: 'category' },
        { label: 'Stok',          key: 'stock',              align: 'right' },
        { label: 'Min.',          key: 'min_stock',          align: 'right' },
        { label: 'Harga Beli',    key: 'avg_purchase_price', align: 'right' },
        { label: 'Nilai Stok',    key: 'stock_value',        align: 'right' },
        { label: 'Status',        key: 'is_low' },
      ],
      rows: stockReport.value,
      cellValue: (key, row) => {
        if (key === 'avg_purchase_price') return rp(row.avg_purchase_price)
        if (key === 'stock_value') return rp(row.stock_value)
        if (key === 'is_low') return row.stock === 0 ? 'Habis' : row.is_low ? 'Menipis' : 'Aman'
        return null
      },
    })
  } else if (activeTab.value === 'supplier') {
    exportPdf({
      title: 'Laporan Supplier',
      subtitle: sub,
      columns: [
        { label: 'Supplier',        key: 'supplier_name' },
        { label: 'Telepon',         key: 'supplier_phone' },
        { label: 'Stok Masuk',      key: 'total_stock_ins', align: 'right' },
        { label: 'Total Item',      key: 'total_items',     align: 'right' },
        { label: 'Total Nilai Beli',key: 'total_value',     align: 'right' },
      ],
      rows: supplierReport.value,
      cellValue: (key, row) => key === 'total_value' ? rp(row.total_value) : null,
    })
  }
}
</script>

<style scoped>
.card-title { font-size: 15px; font-weight: 600; }
.text-red   { color: #cc3333; }

/* Tabs */
.tab-bar  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.tab-nav  { display: flex; gap: 4px; background: #f0f0f0; border-radius: 10px; padding: 4px; flex-wrap: wrap; }
.tab-btn  { padding: 7px 14px; border: none; background: none; border-radius: 8px; font-size: 13px; cursor: pointer; color: #666; transition: all 0.15s; white-space: nowrap; }
.tab-btn.active { background: #fff; color: #1a1a1a; font-weight: 600; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.export-btns { display: flex; gap: 8px; }

/* Stats grid */
.grid-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
.grid-3     { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.stat-card-green { border-left: 3px solid #0d9488; }

/* Horizontal scroll wrapper for dense tables */
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* Payment methods */
.method-row   { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
.method-name  { font-weight: 500; font-size: 14px; text-transform: capitalize; }
.method-count { font-size: 12px; color: #aaa; margin-left: 8px; }
.method-total { font-weight: 600; font-size: 14px; }
.method-pct   { font-size: 11px; color: #aaa; }

/* Top products */
.top-product-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
.top-rank { font-size: 12px; color: #aaa; font-weight: 700; min-width: 24px; }
.top-info { flex: 1; }
.top-name { font-size: 13px; font-weight: 500; }
.top-cat  { font-size: 11px; color: #aaa; }
.top-right { text-align: right; }
.top-qty   { font-size: 12px; color: #888; }
.top-rev   { font-size: 13px; font-weight: 600; }

/* Daily */
.daily-table { font-size: 14px; }
.daily-head  { display: grid; grid-template-columns: 150px 100px 150px 1fr; gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; }
.daily-row   { display: grid; grid-template-columns: 150px 100px 150px 1fr; gap: 8px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; align-items: center; }
.bar-wrap { background: #f5f5f0; border-radius: 4px; height: 8px; overflow: hidden; }
.bar      { background: #0d9488; height: 100%; border-radius: 4px; transition: width 0.5s ease; }

/* Category */
.cat-head  { display: grid; grid-template-columns: 1fr 100px 110px 150px 180px; gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; min-width: 640px; }
.cat-row   { display: grid; grid-template-columns: 1fr 100px 110px 150px 180px; gap: 8px; font-size: 13px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; align-items: center; min-width: 640px; }
.cat-total { display: grid; grid-template-columns: 1fr 100px 110px 150px 180px; gap: 8px; font-size: 13px; padding: 12px 0 0; font-weight: 700; border-top: 2px solid #ebebeb; min-width: 640px; }
.cat-name  { font-weight: 600; }
.cat-bar-wrap { display: flex; align-items: center; gap: 8px; }
.cat-bar   { height: 8px; background: #0d9488; border-radius: 4px; min-width: 2px; transition: width 0.5s; }
.cat-pct   { font-size: 11px; color: #888; white-space: nowrap; }

/* Profit */
.profit-head { display: grid; grid-template-columns: 1fr 90px 60px 130px 130px 130px 80px; gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; min-width: 720px; }
.profit-row  { display: grid; grid-template-columns: 1fr 90px 60px 130px 130px 130px 80px; gap: 8px; font-size: 13px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; align-items: center; min-width: 720px; }
.profit-name { font-size: 13px; font-weight: 500; }
.profit-code { font-size: 11px; color: #aaa; font-family: monospace; }
.profit-pos  { color: #065f46; font-weight: 600; }
.profit-neg  { color: #cc3333; font-weight: 600; }
.margin-badge { font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 6px; }
.margin-high { background: #d1fae5; color: #065f46; }
.margin-mid  { background: #fff3cc; color: #886600; }
.margin-low  { background: #ffe0e0; color: #aa2200; }

/* Stock */
.stock-table-head  { display: grid; grid-template-columns: 90px 1fr 100px 60px 60px 60px 130px 130px 80px; gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; min-width: 860px; }
.stock-table-row   { display: grid; grid-template-columns: 90px 1fr 100px 60px 60px 60px 130px 130px 80px; gap: 8px; font-size: 13px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; align-items: center; min-width: 860px; }
.stock-total-row   { display: grid; grid-template-columns: 90px 1fr 100px 60px 60px 60px 130px 130px 80px; gap: 8px; font-size: 13px; padding: 12px 0 0; border-top: 2px solid #ebebeb; min-width: 860px; }
.code-mono { font-family: monospace; font-size: 12px; }

/* Cashier */
.cashier-card   { display: flex; align-items: center; gap: 16px; padding: 16px 0; border-bottom: 1px solid #f5f5f5; flex-wrap: wrap; }
.cashier-avatar { width: 40px; height: 40px; background: #0d9488; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; }
.cashier-info   { min-width: 120px; }
.cashier-name   { font-size: 14px; font-weight: 600; }
.cashier-role   { font-size: 12px; color: #aaa; text-transform: capitalize; }
.cashier-stats  { display: flex; gap: 24px; flex: 1; justify-content: flex-end; flex-wrap: wrap; }
.cashier-stat   { text-align: right; }
.cs-value { font-size: 15px; font-weight: 700; }
.cs-label { font-size: 11px; color: #aaa; }

/* Supplier */
.supplier-head  { display: grid; grid-template-columns: 1fr 120px 100px 110px 160px; gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; }
.supplier-row   { display: grid; grid-template-columns: 1fr 120px 100px 110px 160px; gap: 8px; font-size: 14px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; align-items: center; }
.supplier-total { display: grid; grid-template-columns: 1fr 120px 100px 110px 160px; gap: 8px; font-size: 14px; padding: 12px 0 0; font-weight: 700; border-top: 2px solid #ebebeb; }
.supplier-name  { font-weight: 500; }

.empty-section { text-align: center; padding: 40px; color: #bbb; font-size: 14px; }
.empty-report  { text-align: center; padding: 80px 0; color: #bbb; }
.empty-icon    { font-size: 40px; margin-bottom: 12px; }
.empty-report p { font-size: 14px; }

@media (max-width: 768px) {
  .grid-stats { grid-template-columns: repeat(2, 1fr); }
  .grid-3     { grid-template-columns: repeat(2, 1fr); }
  .tab-nav    { overflow-x: auto; }
}
@media (max-width: 480px) {
  .grid-stats { grid-template-columns: 1fr 1fr; }
  .cashier-stats { justify-content: flex-start; gap: 16px; }
}
</style>

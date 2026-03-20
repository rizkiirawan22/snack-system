<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Penjualan</h1>
        <p class="page-subtitle">Riwayat semua transaksi</p>
      </div>
      <RouterLink to="/sales/new" class="btn btn-primary">+ Transaksi Baru</RouterLink>
    </div>

    <div class="filter-bar">
      <div class="search-input-wrap">
        <span class="search-icon">⌕</span>
        <input v-model="filters.search" class="form-control" placeholder="Cari no. invoice..." @input="fetchData" />
      </div>
      <input v-model="filters.date_from" type="date" class="form-control" style="width:160px" @change="fetchData" />
      <input v-model="filters.date_to"   type="date" class="form-control" style="width:160px" @change="fetchData" />
    </div>

    <AppTable :columns="columns" :data="sales" :loading="loading">
      <template #default="{ row }">
        <td>
          <span class="inv-no" :class="{ voided: row.voided_at }">{{ row.invoice_no }}</span>
          <span v-if="row.voided_at" class="badge badge-red" style="margin-left:6px;font-size:10px">VOID</span>
        </td>
        <td>{{ formatDate(row.date) }}</td>
        <td>{{ row.user?.name }}</td>
        <td>{{ row.items?.length ?? 0 }} item</td>
        <td :class="{ 'strikethrough': row.voided_at }">{{ formatRp(row.total) }}</td>
        <td><span class="badge badge-blue">{{ row.payment_method }}</span></td>
        <td><button class="btn btn-ghost btn-sm" @click="viewDetail(row)">Detail</button></td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Detail Modal -->
    <AppModal v-model="showDetail" :title="detailData?.invoice_no ?? ''" width="580px">
      <div v-if="detailData">
        <div v-if="detailData.voided_at" class="void-banner">
          Transaksi ini telah dibatalkan oleh <strong>{{ detailData.voided_by?.name }}</strong> pada {{ formatDateTime(detailData.voided_at) }}.
          Alasan: <em>{{ detailData.void_reason }}</em>
        </div>

        <div class="detail-info">
          <div class="detail-row"><span>Tanggal</span><strong>{{ formatDate(detailData.date) }}</strong></div>
          <div class="detail-row"><span>Kasir</span><strong>{{ detailData.user?.name }}</strong></div>
          <div class="detail-row"><span>Pembayaran</span><strong>{{ detailData.payment_method }}</strong></div>
          <div class="detail-row"><span>Catatan</span><strong>{{ detailData.notes || '-' }}</strong></div>
        </div>

        <div class="detail-item-head"><span>Produk</span><span>Qty</span><span>Harga</span><span>Subtotal</span></div>
        <div v-for="item in detailData.items" :key="item.id" class="detail-item-row">
          <span>{{ item.product?.name }}</span>
          <span>{{ item.quantity }}</span>
          <span>{{ formatRp(item.price) }}</span>
          <span>{{ formatRp(item.subtotal) }}</span>
        </div>

        <div class="detail-summary">
          <div class="detail-row"><span>Subtotal</span><span>{{ formatRp(detailData.subtotal) }}</span></div>
          <div class="detail-row"><span>Diskon</span><span>{{ formatRp(detailData.discount) }}</span></div>
          <div class="detail-row total"><span>Total</span><strong>{{ formatRp(detailData.total) }}</strong></div>
          <div class="detail-row"><span>Bayar</span><span>{{ formatRp(detailData.paid) }}</span></div>
          <div class="detail-row"><span>Kembalian</span><span>{{ formatRp(detailData.change) }}</span></div>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="printStruk(detailData)">🖨 Cetak Struk</button>
        <button v-if="detailData && !detailData.voided_at && auth.isAdmin" class="btn btn-danger" @click="openVoidModal">
          Batalkan Transaksi
        </button>
      </template>
    </AppModal>

    <!-- Void Modal -->
    <AppModal v-model="showVoid" title="Batalkan Transaksi" width="420px">
      <p style="color:#555;font-size:14px;margin-bottom:16px">
        Transaksi <strong>{{ detailData?.invoice_no }}</strong> akan dibatalkan dan stok akan dipulihkan. Tindakan ini tidak dapat diurungkan.
      </p>
      <div class="form-group">
        <label class="form-label">Alasan pembatalan <span style="color:red">*</span></label>
        <input v-model="voidReason" class="form-control" placeholder="Contoh: Salah input produk" />
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showVoid = false">Batal</button>
        <button class="btn btn-danger" :disabled="!voidReason || voiding" @click="confirmVoid">
          {{ voiding ? 'Memproses...' : 'Ya, Batalkan' }}
        </button>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { printStruk } from '@/utils/export'
import AppTable from '@/components/AppTable.vue'
import AppPagination from '@/components/AppPagination.vue'
import AppModal from '@/components/AppModal.vue'

const auth    = useAuthStore()
const toast   = useToastStore()

const sales      = ref([])
const meta       = ref(null)
const loading    = ref(false)
const showDetail = ref(false)
const detailData = ref(null)
const showVoid   = ref(false)
const voidReason = ref('')
const voiding    = ref(false)
const filters    = reactive({ search: '', date_from: '', date_to: '' })

const columns = [
  { key: 'invoice_no', label: 'No. Invoice' },
  { key: 'date', label: 'Tanggal', width: '120px' },
  { key: 'kasir', label: 'Kasir' },
  { key: 'items', label: 'Item', width: '70px' },
  { key: 'total', label: 'Total', width: '130px' },
  { key: 'method', label: 'Pembayaran', width: '110px' },
  { key: 'actions', label: '', width: '80px' },
]

const formatRp       = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')
const formatDate     = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'
const formatDateTime = (d) => d ? new Date(d).toLocaleString('id-ID') : '-'

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/sales', { params: { ...filters, page } })
  sales.value = data.data
  meta.value  = data.meta
  loading.value = false
}

async function viewDetail(row) {
  const { data } = await api.get(`/sales/${row.id}`)
  detailData.value = data
  showDetail.value = true
}

function openVoidModal() {
  voidReason.value = ''
  showVoid.value   = true
}

async function confirmVoid() {
  voiding.value = true
  try {
    const { data } = await api.post(`/sales/${detailData.value.id}/void`, { void_reason: voidReason.value })
    detailData.value = data
    toast.success('Transaksi berhasil dibatalkan dan stok telah dipulihkan.')
    showVoid.value = false
    fetchData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membatalkan transaksi.')
  } finally {
    voiding.value = false
  }
}

onMounted(() => fetchData())
</script>

<style scoped>
.inv-no { font-family: monospace; font-weight: 600; font-size: 13px; }
.inv-no.voided { text-decoration: line-through; color: #aaa; }
.strikethrough { text-decoration: line-through; color: #aaa; }

.void-banner {
  background: #fff3f3;
  border: 1px solid #ffd0d0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #cc3333;
  margin-bottom: 16px;
}

.detail-info { margin-bottom: 16px; }
.detail-row  { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #555; }
.detail-item-head { display: grid; grid-template-columns: 1fr 50px 110px 110px; gap: 8px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; }
.detail-item-row  { display: grid; grid-template-columns: 1fr 50px 110px 110px; gap: 8px; font-size: 14px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
.detail-summary   { margin-top: 12px; background: #fafafa; border-radius: 10px; padding: 12px; }
.detail-summary .total { font-size: 15px; font-weight: 700; color: #1a1a1a; border-top: 1px dashed #e0e0e0; padding-top: 8px; }

.btn-danger { background: #ff4444; color: #fff; border: none; }
.btn-danger:hover:not(:disabled) { background: #cc0000; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

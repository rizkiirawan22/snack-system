<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Penutupan Kasir</h1>
        <p class="page-subtitle">Rekonsiliasi kas harian</p>
      </div>
      <button class="btn btn-primary" @click="openClosing">+ Tutup Kasir Hari Ini</button>
    </div>

    <!-- Riwayat penutupan -->
    <div class="filter-bar">
      <input v-model="filters.date_from" type="date" class="form-control" style="width:160px" @change="fetchData" />
      <input v-model="filters.date_to"   type="date" class="form-control" style="width:160px" @change="fetchData" />
    </div>

    <AppTable :columns="columns" :data="closings" :loading="loading">
      <template #default="{ row }">
        <td><span class="code-mono">{{ row.reference_no }}</span></td>
        <td>{{ formatDate(row.closing_date) }}</td>
        <td>{{ row.user?.name }}</td>
        <td>{{ row.total_transactions }} trx</td>
        <td>{{ formatRp(row.total_revenue) }}</td>
        <td>{{ formatRp(row.cash_sales) }}</td>
        <td>{{ formatRp(row.actual_cash) }}</td>
        <td>
          <span class="badge" :class="diffClass(row.difference)">
            {{ row.difference > 0 ? '+' : '' }}{{ formatRp(row.difference) }}
          </span>
        </td>
        <td>
          <button class="btn btn-ghost btn-sm" @click="viewDetail(row)">Detail</button>
        </td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Modal tutup kasir -->
    <AppModal v-model="showClosingModal" title="Penutupan Kasir" width="540px">
      <div v-if="loadingPreview" class="preview-loading">Memuat data penjualan...</div>
      <template v-else-if="preview">
        <div v-if="preview.has_closing" class="already-closed-banner">
          Kasir untuk tanggal <strong>{{ formatDate(preview.date) }}</strong> sudah ditutup sebelumnya.
          Anda tetap bisa merekam penutupan baru jika diperlukan.
        </div>

        <!-- Summary penjualan hari ini -->
        <div class="closing-summary">
          <h4 class="closing-summary-title">Ringkasan Penjualan — {{ formatDate(preview.date) }}</h4>
          <div class="cs-row"><span>Total Transaksi</span><strong>{{ preview.total_transactions }}</strong></div>
          <div class="cs-row"><span>Total Pendapatan</span><strong>{{ formatRp(preview.total_revenue) }}</strong></div>
          <div class="cs-divider" />
          <div class="cs-row"><span>Tunai (Cash)</span><span>{{ formatRp(preview.cash_sales) }}</span></div>
          <div class="cs-row"><span>Transfer</span><span>{{ formatRp(preview.transfer_sales) }}</span></div>
          <div class="cs-row"><span>QRIS</span><span>{{ formatRp(preview.qris_sales) }}</span></div>
        </div>

        <!-- Input kasir -->
        <div class="grid-2" style="margin-top:16px">
          <div class="form-group">
            <label class="form-label">Saldo Kas Awal (Rp) <span class="required">*</span></label>
            <input v-model.number="closingForm.opening_balance" type="number" class="form-control" min="0" placeholder="0" />
          </div>
          <div class="form-group">
            <label class="form-label">Uang Fisik Dihitung (Rp) <span class="required">*</span></label>
            <input v-model.number="closingForm.actual_cash" type="number" class="form-control" min="0" placeholder="0" />
          </div>
        </div>

        <!-- Kalkulasi -->
        <div class="closing-calc" v-if="closingForm.opening_balance >= 0 && closingForm.actual_cash >= 0">
          <div class="calc-row"><span>Kas Awal</span><span>{{ formatRp(closingForm.opening_balance) }}</span></div>
          <div class="calc-row"><span>+ Penjualan Tunai</span><span>{{ formatRp(preview.cash_sales) }}</span></div>
          <div class="calc-row total"><span>= Ekspektasi Kas</span><strong>{{ formatRp(expectedCash) }}</strong></div>
          <div class="calc-row"><span>Kas Fisik Actual</span><span>{{ formatRp(closingForm.actual_cash) }}</span></div>
          <div class="calc-row selisih" :class="diff >= 0 ? 'ok' : 'short'">
            <span>Selisih</span>
            <strong>{{ diff >= 0 ? '+' : '' }}{{ formatRp(diff) }}</strong>
          </div>
          <div class="diff-label" :class="diff > 0 ? 'ok' : diff < 0 ? 'short' : 'zero'">
            {{ diff > 0 ? 'Kelebihan kas' : diff < 0 ? 'Kekurangan kas — perlu diperiksa!' : 'Kas sesuai' }}
          </div>
        </div>

        <div class="form-group" style="margin-top:12px">
          <label class="form-label">Catatan</label>
          <textarea v-model="closingForm.notes" class="form-control" rows="2" placeholder="Opsional..." />
        </div>
      </template>

      <template #footer>
        <button class="btn btn-ghost" @click="showClosingModal = false">Batal</button>
        <button
          class="btn btn-primary"
          :disabled="!preview || loadingPreview || saving"
          @click="submitClosing"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan Penutupan' }}
        </button>
      </template>
    </AppModal>

    <!-- Detail modal -->
    <AppModal v-model="showDetail" :title="detailData?.reference_no ?? ''" width="520px">
      <div v-if="detailData">
        <div class="detail-info">
          <div class="detail-row"><span>Tanggal</span><strong>{{ formatDate(detailData.closing_date) }}</strong></div>
          <div class="detail-row"><span>Kasir</span><strong>{{ detailData.user?.name }}</strong></div>
          <div class="detail-row"><span>Total Transaksi</span><strong>{{ detailData.total_transactions }}</strong></div>
        </div>
        <div class="closing-summary" style="margin-top:12px">
          <div class="cs-row"><span>Total Pendapatan</span><strong>{{ formatRp(detailData.total_revenue) }}</strong></div>
          <div class="cs-divider" />
          <div class="cs-row"><span>Tunai</span><span>{{ formatRp(detailData.cash_sales) }}</span></div>
          <div class="cs-row"><span>Transfer</span><span>{{ formatRp(detailData.transfer_sales) }}</span></div>
          <div class="cs-row"><span>QRIS</span><span>{{ formatRp(detailData.qris_sales) }}</span></div>
          <div class="cs-divider" />
          <div class="cs-row"><span>Saldo Awal</span><span>{{ formatRp(detailData.opening_balance) }}</span></div>
          <div class="cs-row"><span>Ekspektasi Kas</span><span>{{ formatRp(detailData.total_expected) }}</span></div>
          <div class="cs-row"><span>Kas Fisik</span><span>{{ formatRp(detailData.actual_cash) }}</span></div>
          <div class="cs-row selisih" :class="detailData.difference >= 0 ? 'ok' : 'short'">
            <span>Selisih</span>
            <strong>{{ detailData.difference >= 0 ? '+' : '' }}{{ formatRp(detailData.difference) }}</strong>
          </div>
        </div>
        <div v-if="detailData.notes" class="detail-notes">{{ detailData.notes }}</div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/api/axios'
import { useToastStore } from '@/stores/toast'
import AppTable from '@/components/AppTable.vue'
import AppPagination from '@/components/AppPagination.vue'
import AppModal from '@/components/AppModal.vue'

const toast = useToastStore()

const closings      = ref([])
const meta          = ref(null)
const loading       = ref(false)
const saving        = ref(false)
const loadingPreview = ref(false)
const showClosingModal = ref(false)
const showDetail    = ref(false)
const detailData    = ref(null)
const preview       = ref(null)

const filters     = reactive({ date_from: '', date_to: '' })
const closingForm = reactive({ opening_balance: 0, actual_cash: 0, notes: '' })

const columns = [
  { key: 'ref',         label: 'No. Ref' },
  { key: 'date',        label: 'Tanggal', width: '110px' },
  { key: 'user',        label: 'Kasir', width: '120px' },
  { key: 'trx',         label: 'Transaksi', width: '100px' },
  { key: 'revenue',     label: 'Total Pendapatan', width: '150px' },
  { key: 'cash_sales',  label: 'Penjualan Tunai', width: '140px' },
  { key: 'actual',      label: 'Kas Fisik', width: '130px' },
  { key: 'diff',        label: 'Selisih', width: '120px' },
  { key: 'actions',     label: '', width: '80px' },
]

const formatRp   = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')
const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'

const expectedCash = computed(() => (closingForm.opening_balance || 0) + (preview.value?.cash_sales || 0))
const diff         = computed(() => (closingForm.actual_cash || 0) - expectedCash.value)

function diffClass(d) {
  if (d > 0) return 'badge-green'
  if (d < 0) return 'badge-red'
  return 'badge-gray'
}

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/cash-closings', { params: { ...filters, page } })
  closings.value = data.data
  meta.value     = data.meta
  loading.value  = false
}

async function openClosing() {
  Object.assign(closingForm, { opening_balance: 0, actual_cash: 0, notes: '' })
  preview.value      = null
  loadingPreview.value = true
  showClosingModal.value = true

  try {
    const { data } = await api.get('/cash-closings/preview')
    preview.value = data
  } finally {
    loadingPreview.value = false
  }
}

async function submitClosing() {
  saving.value = true
  try {
    await api.post('/cash-closings', {
      closing_date:    preview.value.date,
      opening_balance: closingForm.opening_balance,
      actual_cash:     closingForm.actual_cash,
      notes:           closingForm.notes || null,
    })
    toast.success('Penutupan kasir berhasil disimpan.')
    showClosingModal.value = false
    fetchData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}

async function viewDetail(row) {
  const { data } = await api.get(`/cash-closings/${row.id}`)
  detailData.value = data
  showDetail.value = true
}

onMounted(() => fetchData())
</script>

<style scoped>
.code-mono { font-family: monospace; font-size: 13px; font-weight: 600; }
.preview-loading { text-align: center; padding: 40px; color: #aaa; }

.already-closed-banner {
  background: #fffbe6;
  border: 1px solid #ffe58f;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #886600;
  margin-bottom: 16px;
}

.closing-summary {
  background: #f8f8f8;
  border-radius: 10px;
  padding: 14px 16px;
}
.closing-summary-title { font-size: 13px; font-weight: 600; margin-bottom: 10px; color: #555; }
.cs-row    { display: flex; justify-content: space-between; font-size: 14px; padding: 4px 0; color: #555; }
.cs-divider { border-top: 1px dashed #e0e0e0; margin: 8px 0; }
.cs-row.selisih.ok    { color: #065f46; font-weight: 700; }
.cs-row.selisih.short { color: #cc3333; font-weight: 700; }

.closing-calc {
  background: #f0f8e8;
  border: 1px solid #d0eaa0;
  border-radius: 10px;
  padding: 14px 16px;
  margin-top: 12px;
}
.calc-row { display: flex; justify-content: space-between; font-size: 14px; padding: 4px 0; color: #555; }
.calc-row.total { font-size: 15px; font-weight: 700; color: #1a1a1a; border-top: 1px dashed #99f6e4; padding-top: 8px; margin-top: 4px; }
.calc-row.selisih.ok    { color: #065f46; font-weight: 700; font-size: 15px; border-top: 1px dashed #99f6e4; padding-top: 8px; }
.calc-row.selisih.short { color: #cc3333; font-weight: 700; font-size: 15px; border-top: 1px dashed #99f6e4; padding-top: 8px; }
.diff-label { font-size: 12px; text-align: right; margin-top: 4px; }
.diff-label.ok    { color: #065f46; }
.diff-label.short { color: #cc3333; font-weight: 600; }
.diff-label.zero  { color: #065f46; }

.detail-info  { margin-bottom: 8px; }
.detail-row   { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #555; }
.detail-notes { margin-top: 12px; font-size: 13px; color: #888; font-style: italic; }
</style>

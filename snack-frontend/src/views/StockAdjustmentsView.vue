<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Penyesuaian Stok</h1>
        <p class="page-subtitle">Koreksi stok untuk kehilangan, kerusakan, atau hasil opname</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ Penyesuaian Baru</button>
    </div>

    <div class="filter-bar">
      <input v-model="filters.date_from" type="date" class="form-control" style="width:160px" @change="fetchData" />
      <input v-model="filters.date_to"   type="date" class="form-control" style="width:160px" @change="fetchData" />
    </div>

    <AppTable :columns="columns" :data="adjustments" :loading="loading">
      <template #default="{ row }">
        <td><span class="code-mono">{{ row.reference_no }}</span></td>
        <td>{{ formatDate(row.created_at) }}</td>
        <td>{{ row.product?.name }}</td>
        <td>{{ row.product?.category?.name }}</td>
        <td>{{ row.quantity_before }}</td>
        <td>
          <span :class="row.quantity_change > 0 ? 'qty-plus' : row.quantity_change < 0 ? 'qty-minus' : 'qty-zero'">
            {{ row.quantity_change > 0 ? '+' : '' }}{{ row.quantity_change }}
          </span>
        </td>
        <td><strong>{{ row.quantity_after }}</strong></td>
        <td><span class="badge" :class="reasonBadge(row.reason)">{{ reasonLabel(row.reason) }}</span></td>
        <td>{{ row.user?.name }}</td>
        <td class="notes-cell">{{ row.notes || '-' }}</td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Create Modal -->
    <AppModal v-model="showCreate" title="Penyesuaian Stok Baru" width="500px">
      <div class="form-group">
        <label class="form-label">Produk <span style="color:red">*</span></label>
        <select v-model="form.product_id" class="form-control" @change="onProductChange">
          <option value="">-- Pilih produk --</option>
          <option v-for="p in products" :key="p.id" :value="p.id">
            {{ p.name }} ({{ p.code }}) — stok: {{ p.stock?.quantity ?? 0 }}
          </option>
        </select>
      </div>

      <div v-if="selectedProduct" class="stock-preview">
        <div class="preview-row">
          <span>Stok sekarang</span>
          <strong>{{ selectedProduct.stock?.quantity ?? 0 }} pack</strong>
        </div>
        <div class="preview-row">
          <span>Stok baru (hasil opname)</span>
          <input v-model.number="form.quantity_after" type="number" class="form-control" min="0" style="width:100px" />
        </div>
        <div class="preview-row" v-if="form.quantity_after !== ''">
          <span>Selisih</span>
          <strong :class="diff > 0 ? 'qty-plus' : diff < 0 ? 'qty-minus' : 'qty-zero'">
            {{ diff > 0 ? '+' : '' }}{{ diff }} pack
          </strong>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Alasan <span style="color:red">*</span></label>
        <select v-model="form.reason" class="form-control">
          <option value="">-- Pilih alasan --</option>
          <option value="count">Hasil Opname (Stock Count)</option>
          <option value="damage">Produk Rusak/Expired</option>
          <option value="shrinkage">Susut/Kehilangan</option>
          <option value="other">Lainnya</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Catatan</label>
        <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Opsional..."></textarea>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="showCreate = false">Batal</button>
        <button class="btn btn-primary" :disabled="!canSubmit || saving" @click="submitAdjustment">
          {{ saving ? 'Menyimpan...' : 'Simpan Penyesuaian' }}
        </button>
      </template>
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

const adjustments = ref([])
const products    = ref([])
const meta        = ref(null)
const loading     = ref(false)
const showCreate  = ref(false)
const saving      = ref(false)
const selectedProduct = ref(null)

const filters = reactive({ date_from: '', date_to: '' })
const form    = reactive({ product_id: '', quantity_after: '', reason: '', notes: '' })

const columns = [
  { key: 'ref',      label: 'No. Ref', width: '150px' },
  { key: 'date',     label: 'Tanggal', width: '120px' },
  { key: 'product',  label: 'Produk' },
  { key: 'cat',      label: 'Kategori', width: '100px' },
  { key: 'before',   label: 'Sblm', width: '60px' },
  { key: 'change',   label: 'Selisih', width: '80px' },
  { key: 'after',    label: 'Stlh', width: '60px' },
  { key: 'reason',   label: 'Alasan', width: '110px' },
  { key: 'user',     label: 'Oleh', width: '100px' },
  { key: 'notes',    label: 'Catatan' },
]

const diff = computed(() => {
  if (!selectedProduct.value || form.quantity_after === '') return 0
  return form.quantity_after - (selectedProduct.value.stock?.quantity ?? 0)
})

const canSubmit = computed(() =>
  form.product_id && form.quantity_after !== '' && form.quantity_after >= 0 && form.reason
)

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'

function reasonLabel(r) {
  return { count: 'Opname', damage: 'Rusak/Expired', shrinkage: 'Susut', other: 'Lainnya' }[r] || r
}

function reasonBadge(r) {
  return { count: 'badge-blue', damage: 'badge-red', shrinkage: 'badge-yellow', other: 'badge-gray' }[r] || 'badge-gray'
}

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/stock-adjustments', { params: { ...filters, page } })
  adjustments.value = data.data
  meta.value        = data.meta
  loading.value     = false
}

async function fetchProducts() {
  const { data } = await api.get('/products', { params: { per_page: 200, is_active: 1 } })
  products.value = data.data
}

function openCreate() {
  Object.assign(form, { product_id: '', quantity_after: '', reason: '', notes: '' })
  selectedProduct.value = null
  showCreate.value = true
}

function onProductChange() {
  selectedProduct.value = products.value.find(p => p.id === form.product_id) || null
  form.quantity_after = selectedProduct.value?.stock?.quantity ?? 0
}

async function submitAdjustment() {
  saving.value = true
  try {
    await api.post('/stock-adjustments', {
      product_id:     form.product_id,
      quantity_after: form.quantity_after,
      reason:         form.reason,
      notes:          form.notes || null,
    })
    toast.success('Stok berhasil disesuaikan.')
    showCreate.value = false
    fetchData()
    fetchProducts()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan penyesuaian.')
  } finally {
    saving.value = false
  }
}

onMounted(() => { fetchData(); fetchProducts() })
</script>

<style scoped>
.code-mono { font-family: monospace; font-size: 12px; font-weight: 600; }
.notes-cell { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #888; }

.qty-plus  { color: #4a7a00; font-weight: 700; }
.qty-minus { color: #cc3333; font-weight: 700; }
.qty-zero  { color: #888; }

.stock-preview {
  background: #f8f8f8;
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.preview-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  color: #555;
}

.badge-gray { background: #f0f0f0; color: #666; }
</style>

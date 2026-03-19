<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Stok Masuk</h1>
        <p class="page-subtitle">Catat penerimaan stok produk</p>
      </div>
      <button class="btn btn-primary" @click="showModal = true">+ Input Stok Masuk</button>
    </div>

    <div class="filter-bar">
      <input v-model="filters.date_from" type="date" class="form-control" style="width:160px" @change="fetchData" />
      <input v-model="filters.date_to" type="date" class="form-control" style="width:160px" @change="fetchData" />
    </div>

    <AppTable :columns="columns" :data="stockIns" :loading="loading">
      <template #default="{ row }">
        <td><span class="ref-no">{{ row.reference_no }}</span></td>
        <td>{{ formatDate(row.date) }}</td>
        <td>{{ row.supplier?.name || '-' }}</td>
        <td>{{ row.user?.name }}</td>
        <td>{{ row.items?.length ?? 0 }} item</td>
        <td>{{ formatRp(row.items?.reduce((s, i) => s + i.quantity * i.purchase_price, 0)) }}</td>
        <td>
          <button class="btn btn-ghost btn-sm" @click="viewDetail(row)">Detail</button>
        </td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Modal input stok -->
    <AppModal v-model="showModal" title="Input Stok Masuk" width="680px">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Tanggal <span class="required">*</span></label>
          <input v-model="form.date" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label class="form-label">Supplier</label>
          <select v-model="form.supplier_id" class="form-control">
            <option value="">-- Tanpa Supplier --</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Catatan</label>
        <input v-model="form.notes" class="form-control" placeholder="Opsional..." />
      </div>

      <div class="items-section">
        <div class="items-header">
          <h4 class="items-title">Daftar Produk</h4>
          <button class="btn btn-ghost btn-sm" @click="addItem">+ Tambah Baris</button>
        </div>

        <div class="items-table-head">
          <span class="col-product">Produk</span>
          <span class="col-qty">Qty (pack)</span>
          <span class="col-price">Harga Beli</span>
          <span class="col-action"></span>
        </div>

        <div v-for="(item, i) in form.items" :key="i" class="item-row">
          <select v-model="item.product_id" class="form-control col-product">
            <option value="">-- Pilih Produk --</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <input v-model.number="item.quantity" type="number" class="form-control col-qty" placeholder="0" min="1" />
          <input v-model.number="item.purchase_price" type="number" class="form-control col-price" placeholder="0" />
          <button class="btn btn-danger btn-sm col-action" @click="removeItem(i)" :disabled="form.items.length === 1">✕</button>
        </div>

        <div class="items-total">
          Total Nilai Masuk: <strong>{{ formatRp(totalNilai) }}</strong>
        </div>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="showModal = false">Batal</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveStockIn">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </template>
    </AppModal>

    <!-- Modal detail -->
    <AppModal v-model="showDetail" :title="detailData?.reference_no ?? ''" width="580px">
      <div v-if="detailData" class="detail-info">
        <div class="detail-row"><span>Tanggal</span><strong>{{ formatDate(detailData.date) }}</strong></div>
        <div class="detail-row"><span>Supplier</span><strong>{{ detailData.supplier?.name || '-' }}</strong></div>
        <div class="detail-row"><span>Dicatat oleh</span><strong>{{ detailData.user?.name }}</strong></div>
        <div class="detail-row"><span>Catatan</span><strong>{{ detailData.notes || '-' }}</strong></div>
      </div>
      <div v-if="detailData" class="detail-items">
        <div class="detail-item-head">
          <span>Produk</span><span>Qty</span><span>Harga Beli</span><span>Subtotal</span>
        </div>
        <div v-for="item in detailData.items" :key="item.id" class="detail-item-row">
          <span>{{ item.product?.name }}</span>
          <span>{{ item.quantity }}</span>
          <span>{{ formatRp(item.purchase_price) }}</span>
          <span>{{ formatRp(item.quantity * item.purchase_price) }}</span>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useToastStore } from '@/stores/toast'
import api from '@/api/axios'
import AppTable from '@/components/AppTable.vue'
import AppPagination from '@/components/AppPagination.vue'
import AppModal from '@/components/AppModal.vue'

const toast = useToastStore()
const stockIns  = ref([])
const products  = ref([])
const suppliers = ref([])
const meta      = ref(null)
const loading   = ref(false)
const saving    = ref(false)
const showModal = ref(false)
const showDetail = ref(false)
const detailData = ref(null)

const filters = reactive({ date_from: '', date_to: '' })
const form    = reactive({ date: new Date().toISOString().slice(0,10), supplier_id: '', notes: '', items: [{ product_id: '', quantity: 1, purchase_price: 0 }] })

const columns = [
  { key: 'reference_no', label: 'No. Referensi' },
  { key: 'date',         label: 'Tanggal', width: '110px' },
  { key: 'supplier',     label: 'Supplier' },
  { key: 'user',         label: 'Dicatat Oleh', width: '120px' },
  { key: 'items_count',  label: 'Item', width: '60px' },
  { key: 'total',        label: 'Total Nilai', width: '140px' },
  { key: 'actions',      label: '', width: '80px' },
]

const totalNilai = computed(() => form.items.reduce((s, i) => s + (i.quantity * i.purchase_price), 0))

const formatRp   = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')
const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'

function addItem()    { form.items.push({ product_id: '', quantity: 1, purchase_price: 0 }) }
function removeItem(i){ form.items.splice(i, 1) }

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/stock-ins', { params: { ...filters, page } })
  stockIns.value = data.data
  meta.value     = data.meta
  loading.value  = false
}

async function fetchProducts() {
  const { data } = await api.get('/products', { params: { is_active: 1, per_page: 200 } })
  products.value = data.data
}

async function fetchSuppliers() {
  const { data } = await api.get('/suppliers', { params: { all: 1 } })
  suppliers.value = Array.isArray(data) ? data : (data.data ?? [])
}

async function viewDetail(row) {
  const { data } = await api.get(`/stock-ins/${row.id}`)
  detailData.value = data
  showDetail.value = true
}

async function saveStockIn() {
  saving.value = true
  try {
    await api.post('/stock-ins', form)
    toast.success('Stok masuk berhasil dicatat.')
    showModal.value = false
    form.items = [{ product_id: '', quantity: 1, purchase_price: 0 }]
    form.supplier_id = ''
    fetchData()
  } catch(e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}

onMounted(() => { fetchData(); fetchProducts(); fetchSuppliers() })
</script>

<style scoped>
.ref-no { font-family: monospace; font-size: 13px; font-weight: 600; }

.items-section { margin-top: 16px; background: #fafafa; border: 1px solid #ebebeb; border-radius: 10px; padding: 16px; }
.items-header  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.items-title   { font-size: 14px; font-weight: 600; }

.items-table-head { display: grid; grid-template-columns: 1fr 90px 120px 40px; gap: 8px; margin-bottom: 8px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.item-row         { display: grid; grid-template-columns: 1fr 90px 120px 40px; gap: 8px; margin-bottom: 8px; align-items: center; }

.items-total  { margin-top: 12px; text-align: right; font-size: 14px; color: #555; }
.items-total strong { color: #1a1a1a; }

.detail-info { margin-bottom: 16px; }
.detail-row  { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #555; }

.detail-item-head { display: grid; grid-template-columns: 1fr 60px 120px 120px; gap: 8px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; }
.detail-item-row  { display: grid; grid-template-columns: 1fr 60px 120px 120px; gap: 8px; font-size: 14px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
</style>
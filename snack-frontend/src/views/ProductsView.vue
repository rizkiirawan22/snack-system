<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Produk</h1>
        <p class="page-subtitle">Kelola daftar snack kiloan</p>
      </div>
      <button v-if="auth.isAdmin" class="btn btn-primary" @click="openCreate">+ Tambah Produk</button>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="search-input-wrap">
        <span class="search-icon">⌕</span>
        <input v-model="filters.search" class="form-control" placeholder="Cari nama / kode..." @input="fetchData" />
      </div>
      <select v-model="filters.category_id" class="form-control" style="width:160px" @change="fetchData">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.is_active" class="form-control" style="width:140px" @change="fetchData">
        <option value="">Semua Status</option>
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
      </select>
    </div>

    <AppTable :columns="columns" :data="products" :loading="loading">
      <template #default="{ row }">
        <td>
          <div class="prod-name">{{ row.name }}</div>
          <div class="prod-code">{{ row.code }}</div>
        </td>
        <td><span class="badge badge-blue">{{ row.category?.name }}</span></td>
        <td>{{ row.weight }}gr</td>
        <td>{{ formatRp(row.selling_price) }}</td>
        <td>
          <span class="badge" :class="stockBadge(row)">
            {{ row.stock?.quantity ?? 0 }} pack
          </span>
        </td>
        <td>
          <span class="badge" :class="row.is_active ? 'badge-green' : 'badge-gray'">
            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </td>
        <td>
          <div class="action-btns">
            <button class="btn btn-ghost btn-sm" @click="openMutations(row)" title="Riwayat Mutasi Stok">Riwayat</button>
            <template v-if="auth.isAdmin">
              <button class="btn btn-ghost btn-sm" @click="openEdit(row)">Edit</button>
              <button class="btn btn-danger btn-sm" @click="openDelete(row)">Hapus</button>
            </template>
          </div>
        </td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Modal form -->
    <AppModal v-model="showModal" :title="editId ? 'Edit Produk' : 'Tambah Produk'" width="580px">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Kode <span class="required">*</span></label>
          <input v-model="form.code" class="form-control" placeholder="MNS-001" />
        </div>
        <div class="form-group">
          <label class="form-label">Kategori <span class="required">*</span></label>
          <select v-model="form.category_id" class="form-control">
            <option value="">-- Pilih Kategori --</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Produk <span class="required">*</span></label>
        <input v-model="form.name" class="form-control" placeholder="Coklat Kiloan 250gr" />
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Berat (gram) <span class="required">*</span></label>
          <input v-model.number="form.weight" type="number" class="form-control" placeholder="250" />
        </div>
        <div class="form-group">
          <label class="form-label">Stok Minimum</label>
          <input v-model.number="form.min_stock" type="number" class="form-control" placeholder="10" />
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Harga Beli</label>
          <input v-model.number="form.purchase_price" type="number" class="form-control" placeholder="0" />
        </div>
        <div class="form-group">
          <label class="form-label">Harga Jual <span class="required">*</span></label>
          <input v-model.number="form.selling_price" type="number" class="form-control" placeholder="0" />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea v-model="form.description" class="form-control" rows="2" />
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input v-model="form.is_active" type="checkbox" id="is_active" />
        <label for="is_active" class="form-label" style="margin:0">Produk Aktif</label>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="showModal = false">Batal</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveProduct">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </template>
    </AppModal>

    <!-- Confirm delete -->
    <ConfirmDialog
      v-model="showConfirm"
      :message="`Hapus produk '${deleteTarget?.name}'?`"
      :loading="deleting"
      @confirm="deleteProduct"
    />

    <!-- Mutations Modal -->
    <AppModal v-model="showMutations" :title="`Riwayat Stok — ${mutationProduct?.name ?? ''}`" width="640px">
      <div class="mutations-summary" v-if="mutationProduct">
        <span>Stok sekarang: <strong>{{ mutationProduct.stock?.quantity ?? 0 }} pack</strong></span>
      </div>
      <div v-if="loadingMutations" style="text-align:center;padding:40px;color:#aaa">Memuat...</div>
      <template v-else>
        <div v-if="!mutations.length" class="empty-mutations">Belum ada riwayat mutasi stok.</div>
        <div v-else>
          <div class="mut-head">
            <span>Tanggal</span>
            <span>Tipe</span>
            <span>Sebelum</span>
            <span>Perubahan</span>
            <span>Sesudah</span>
            <span>Oleh</span>
            <span>Keterangan</span>
          </div>
          <div v-for="m in mutations" :key="m.id" class="mut-row">
            <span class="mut-date">{{ formatDateTime(m.created_at) }}</span>
            <span><span class="badge" :class="typeBadge(m.type)">{{ typeLabel(m.type) }}</span></span>
            <span>{{ m.quantity_before }}</span>
            <span :class="m.quantity_change > 0 ? 'qty-plus' : 'qty-minus'">
              {{ m.quantity_change > 0 ? '+' : '' }}{{ m.quantity_change }}
            </span>
            <span><strong>{{ m.quantity_after }}</strong></span>
            <span>{{ m.user?.name }}</span>
            <span class="mut-notes">{{ m.notes }}</span>
          </div>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import api from '@/api/axios'
import AppTable from '@/components/AppTable.vue'
import AppPagination from '@/components/AppPagination.vue'
import AppModal from '@/components/AppModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const auth    = useAuthStore()
const toast   = useToastStore()

const products    = ref([])
const categories  = ref([])
const meta        = ref(null)
const loading     = ref(false)
const saving      = ref(false)
const deleting    = ref(false)
const showModal   = ref(false)
const showConfirm = ref(false)
const editId      = ref(null)
const deleteTarget = ref(null)

const showMutations   = ref(false)
const loadingMutations = ref(false)
const mutationProduct  = ref(null)
const mutations        = ref([])

const filters = reactive({ search: '', category_id: '', is_active: '' })

const defaultForm = () => ({ code: '', category_id: '', name: '', weight: 250, min_stock: 10, purchase_price: 0, selling_price: 0, description: '', is_active: true, unit: 'gram' })
const form = reactive(defaultForm())

const columns = [
  { key: 'name', label: 'Produk' },
  { key: 'category', label: 'Kategori', width: '110px' },
  { key: 'weight', label: 'Berat', width: '80px' },
  { key: 'selling_price', label: 'Harga Jual', width: '130px' },
  { key: 'stock', label: 'Stok', width: '100px' },
  { key: 'is_active', label: 'Status', width: '90px' },
  { key: 'actions', label: '', width: '200px' },
]

const formatDateTime = (d) => d ? new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-'

function typeLabel(t) {
  return { stock_in: 'Masuk', sale: 'Jual', void: 'Void', adjustment: 'Koreksi' }[t] || t
}

function typeBadge(t) {
  return { stock_in: 'badge-green', sale: 'badge-blue', void: 'badge-red', adjustment: 'badge-yellow' }[t] || 'badge-gray'
}

async function openMutations(row) {
  mutationProduct.value = row
  mutations.value = []
  showMutations.value = true
  loadingMutations.value = true
  try {
    const { data } = await api.get(`/stock-mutations/${row.id}`)
    mutations.value = data.data
  } finally {
    loadingMutations.value = false
  }
}

function stockBadge(p) {
  const qty = p.stock?.quantity ?? 0
  if (qty === 0) return 'badge-red'
  if (qty <= p.min_stock) return 'badge-yellow'
  return 'badge-green'
}

function formatRp(val) {
  return 'Rp ' + Number(val || 0).toLocaleString('id-ID')
}

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/products', { params: { ...filters, page } })
  products.value = data.data
  meta.value     = data.meta
  loading.value  = false
}

async function fetchCategories() {
  const { data } = await api.get('/categories', { params: { per_page: 100 } })
  categories.value = data.data ?? data
}

function openCreate() {
  editId.value = null
  Object.assign(form, defaultForm())
  showModal.value = true
}

function openEdit(row) {
  editId.value = row.id
  Object.assign(form, { ...row, category_id: row.category_id })
  showModal.value = true
}

function openDelete(row) {
  deleteTarget.value = row
  showConfirm.value  = true
}

async function saveProduct() {
  saving.value = true
  try {
    if (editId.value) await api.put(`/products/${editId.value}`, form)
    else              await api.post('/products', form)
    toast.success(editId.value ? 'Produk berhasil diperbarui.' : 'Produk berhasil ditambahkan.')
    showModal.value = false
    fetchData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}

async function deleteProduct() {
  deleting.value = true
  try {
    await api.delete(`/products/${deleteTarget.value.id}`)
    toast.success('Produk dihapus.')
    showConfirm.value = false
    fetchData()
  } catch (e) {
    toast.error('Gagal menghapus.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => { fetchData(); fetchCategories() })
</script>

<style scoped>
.prod-name { font-weight: 500; font-size: 14px; }
.prod-code { font-size: 12px; color: #aaa; margin-top: 2px; font-family: monospace; }
.action-btns { display: flex; gap: 6px; flex-wrap: wrap; }

.mutations-summary { font-size: 14px; color: #555; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #ebebeb; }
.empty-mutations { text-align: center; padding: 40px; color: #bbb; font-size: 14px; }

.mut-head { display: grid; grid-template-columns: 130px 80px 60px 80px 60px 90px 1fr; gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; }
.mut-row  { display: grid; grid-template-columns: 130px 80px 60px 80px 60px 90px 1fr; gap: 8px; font-size: 13px; padding: 8px 0; border-bottom: 1px solid #f5f5f5; align-items: center; }
.mut-date { font-size: 11px; color: #888; }
.mut-notes { font-size: 11px; color: #888; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.qty-plus  { color: #4a7a00; font-weight: 700; }
.qty-minus { color: #cc3333; font-weight: 700; }
</style>
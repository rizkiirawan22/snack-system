<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Supplier</h1>
        <p class="page-subtitle">Kelola data pemasok produk</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ Tambah Supplier</button>
    </div>

    <div class="filter-bar">
      <div class="search-input-wrap">
        <span class="search-icon">⌕</span>
        <input v-model="filters.search" class="form-control" placeholder="Cari nama supplier..." @input="fetchData" />
      </div>
      <select v-model="filters.is_active" class="form-control" style="width:140px" @change="fetchData">
        <option value="">Semua Status</option>
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
      </select>
    </div>

    <AppTable :columns="columns" :data="suppliers" :loading="loading">
      <template #default="{ row }">
        <td>
          <div class="supplier-name">{{ row.name }}</div>
        </td>
        <td>{{ row.phone || '-' }}</td>
        <td>{{ row.email || '-' }}</td>
        <td>{{ row.address || '-' }}</td>
        <td>
          <span class="badge" :class="row.is_active ? 'badge-green' : 'badge-gray'">
            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </td>
        <td>{{ row.stock_ins_count ?? 0 }}x masuk</td>
        <td>
          <div class="action-btns">
            <button class="btn btn-ghost btn-sm" @click="openEdit(row)">Edit</button>
            <button class="btn btn-danger btn-sm" @click="openDelete(row)">Hapus</button>
          </div>
        </td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Modal form -->
    <AppModal v-model="showModal" :title="editId ? 'Edit Supplier' : 'Tambah Supplier'" width="520px">
      <div class="form-group">
        <label class="form-label">Nama Supplier <span class="required">*</span></label>
        <input v-model="form.name" class="form-control" placeholder="CV. Snack Jaya" />
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">No. Telepon</label>
          <input v-model="form.phone" class="form-control" placeholder="08xxxxxxxxxx" />
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="form.email" class="form-control" type="email" placeholder="supplier@email.com" />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Alamat</label>
        <textarea v-model="form.address" class="form-control" rows="2" placeholder="Alamat lengkap..." />
      </div>
      <div class="form-group">
        <label class="form-label">Catatan</label>
        <input v-model="form.notes" class="form-control" placeholder="Informasi tambahan..." />
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input v-model="form.is_active" type="checkbox" id="sup_active" />
        <label for="sup_active" class="form-label" style="margin:0">Supplier Aktif</label>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showModal = false">Batal</button>
        <button class="btn btn-primary" :disabled="!form.name || saving" @click="save">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </template>
    </AppModal>

    <ConfirmDialog
      v-model="showConfirm"
      :message="`Hapus supplier '${deleteTarget?.name}'?`"
      :loading="deleting"
      @confirm="deleteSupplier"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/axios'
import { useToastStore } from '@/stores/toast'
import AppTable from '@/components/AppTable.vue'
import AppPagination from '@/components/AppPagination.vue'
import AppModal from '@/components/AppModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const toast = useToastStore()

const suppliers   = ref([])
const meta        = ref(null)
const loading     = ref(false)
const saving      = ref(false)
const deleting    = ref(false)
const showModal   = ref(false)
const showConfirm = ref(false)
const editId      = ref(null)
const deleteTarget = ref(null)

const filters = reactive({ search: '', is_active: '' })
const defaultForm = () => ({ name: '', phone: '', email: '', address: '', notes: '', is_active: true })
const form = reactive(defaultForm())

const columns = [
  { key: 'name',      label: 'Nama Supplier' },
  { key: 'phone',     label: 'Telepon', width: '130px' },
  { key: 'email',     label: 'Email', width: '160px' },
  { key: 'address',   label: 'Alamat' },
  { key: 'status',    label: 'Status', width: '90px' },
  { key: 'stockins',  label: 'Stok Masuk', width: '100px' },
  { key: 'actions',   label: '', width: '130px' },
]

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/suppliers', { params: { ...filters, page } })
  suppliers.value = data.data
  meta.value      = data.meta
  loading.value   = false
}

function openCreate() {
  editId.value = null
  Object.assign(form, defaultForm())
  showModal.value = true
}

function openEdit(row) {
  editId.value = row.id
  Object.assign(form, row)
  showModal.value = true
}

function openDelete(row) {
  deleteTarget.value = row
  showConfirm.value  = true
}

async function save() {
  saving.value = true
  try {
    if (editId.value) await api.put(`/suppliers/${editId.value}`, form)
    else              await api.post('/suppliers', form)
    toast.success(editId.value ? 'Supplier diperbarui.' : 'Supplier ditambahkan.')
    showModal.value = false
    fetchData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}

async function deleteSupplier() {
  deleting.value = true
  try {
    await api.delete(`/suppliers/${deleteTarget.value.id}`)
    toast.success('Supplier dihapus.')
    showConfirm.value = false
    fetchData()
  } catch (e) {
    toast.error('Gagal menghapus.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchData())
</script>

<style scoped>
.supplier-name { font-weight: 500; font-size: 14px; }
.action-btns   { display: flex; gap: 6px; }
.btn-danger { background: #ff4444; color: #fff; border: none; }
.btn-danger:hover { background: #cc0000; }
</style>

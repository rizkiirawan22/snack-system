<template>
  <AppModal :model-value="modelValue" @update:modelValue="$emit('update:modelValue', $event)" :title="title" width="400px">
    <p class="confirm-text">{{ message }}</p>
    <template #footer>
      <button class="btn btn-ghost" @click="$emit('update:modelValue', false)">Batal</button>
      <button class="btn btn-danger" :disabled="loading" @click="$emit('confirm')">
        {{ loading ? 'Memproses...' : 'Ya, Hapus' }}
      </button>
    </template>
  </AppModal>
</template>

<script setup>
import AppModal from './AppModal.vue'
defineProps({
  modelValue: Boolean,
  title: { type: String, default: 'Konfirmasi Hapus' },
  message: String,
  loading: Boolean,
})
defineEmits(['update:modelValue', 'confirm'])
</script>

<style scoped>
.confirm-text { color: #555; font-size: 14px; line-height: 1.6; margin: 0; }
</style>
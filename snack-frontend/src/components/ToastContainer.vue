<template>
  <Teleport to="body">
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toastStore.toasts"
          :key="toast.id"
          class="toast"
          :class="toast.type"
          @click="toastStore.remove(toast.id)"
        >
          <span class="toast-icon">{{ icons[toast.type] }}</span>
          <span class="toast-msg">{{ toast.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToastStore } from '@/stores/toast'
const toastStore = useToastStore()
const icons = { success: '✓', error: '✕', info: 'ℹ' }
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.toast {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  min-width: 260px;
  max-width: 380px;
}

.toast.success { background: #1a1a1a; color: #b8f000; border: 1px solid #b8f000; }
.toast.error   { background: #1a1a1a; color: #ff6b6b; border: 1px solid #ff6b6b; }
.toast.info    { background: #1a1a1a; color: #6bc5ff; border: 1px solid #6bc5ff; }
.toast-icon    { font-weight: 700; font-size: 13px; }

.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from { opacity: 0; transform: translateX(40px); }
.toast-leave-to   { opacity: 0; transform: translateX(40px); }
</style>
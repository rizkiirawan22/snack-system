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

.toast.success { background: #0f172a; color: #5eead4; border: 1px solid #0d9488; }
.toast.error   { background: #0f172a; color: #fca5a5; border: 1px solid #f87171; }
.toast.info    { background: #0f172a; color: #93c5fd; border: 1px solid #60a5fa; }
.toast-icon    { font-weight: 700; font-size: 13px; }

.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from { opacity: 0; transform: translateX(40px); }
.toast-leave-to   { opacity: 0; transform: translateX(40px); }
</style>
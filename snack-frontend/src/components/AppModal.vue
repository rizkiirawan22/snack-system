<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" @click.self="$emit('update:modelValue', false)">
        <div class="modal-box" :style="{ maxWidth: width }">
          <div class="modal-header">
            <h3 class="modal-title">{{ title }}</h3>
            <button class="modal-close" @click="$emit('update:modelValue', false)">✕</button>
          </div>
          <div class="modal-body">
            <slot />
          </div>
          <div v-if="$slots.footer" class="modal-footer">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({ modelValue: Boolean, title: String, width: { type: String, default: '520px' } })
defineEmits(['update:modelValue'])
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0;
  background: var(--overlay-bg);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-box {
  background: var(--bg-surface);
  border-radius: 14px;
  border: 1px solid var(--border);
  width: 100%;
  box-shadow: var(--shadow-modal);
  overflow: hidden;
  transition: background var(--transition), border-color var(--transition);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 0;
}

.modal-title { font-size: 16px; font-weight: 600; color: var(--text-1); margin: 0; }

.modal-close {
  background: none; border: none;
  font-size: 14px; cursor: pointer;
  color: var(--text-3); width: 28px; height: 28px;
  border-radius: 6px; display: flex;
  align-items: center; justify-content: center;
}

.modal-close:hover { background: var(--bg-surface-2); color: var(--text-1); }

.modal-body { padding: 20px 24px; }
.modal-footer { padding: 0 24px 20px; display: flex; justify-content: flex-end; gap: 8px; }

.modal-enter-active, .modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95); }
</style>
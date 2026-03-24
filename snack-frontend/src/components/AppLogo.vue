<template>
  <div class="app-logo" :class="`size-${size}`">
    <svg :width="iconSize" :height="iconSize" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
      <!-- Background rounded square -->
      <rect width="40" height="40" rx="11" fill="#f97316"/>
      <rect width="40" height="40" rx="11" fill="url(#logo-shine)" opacity="0.35"/>

      <!-- Bag handle -->
      <path d="M15 19.5V17a5 5 0 0 1 10 0v2.5"
        stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>

      <!-- Bag body -->
      <rect x="10" y="19.5" width="20" height="14" rx="3.5" fill="white"/>

      <!-- Weight scale icon on bag -->
      <circle cx="20" cy="25" r="3.5" fill="#f97316" opacity="0.15"/>
      <path d="M20 22v6M17 25h6" stroke="#f97316" stroke-width="1.8" stroke-linecap="round"/>

      <defs>
        <linearGradient id="logo-shine" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
          <stop stop-color="white"/>
          <stop offset="1" stop-color="white" stop-opacity="0"/>
        </linearGradient>
      </defs>
    </svg>

    <span v-if="showText" class="logo-wordmark">
      <span class="logo-word-snack">Snack</span><span class="logo-word-kilo">Kilo</span>
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: { type: String, default: 'md' }, // sm | md | lg
  showText: { type: Boolean, default: true },
})

const iconSize = computed(() => ({ sm: 28, md: 34, lg: 44 }[props.size] ?? 34))
</script>

<style scoped>
.app-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  user-select: none;
}

.logo-wordmark {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.3px;
  white-space: nowrap;
  line-height: 1;
}

.logo-word-snack { color: #ffffff; }
.logo-word-kilo  { color: #fdba74; }

.size-sm .logo-wordmark { font-size: 14px; }
.size-lg .logo-wordmark { font-size: 20px; }
</style>

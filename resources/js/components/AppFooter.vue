<template>
  <footer class="relative z-10 px-4 py-8 text-center text-sm text-slate-500 border-t border-white/5">
    <p>{{ t('footer') }}</p>
    <p class="mt-2 text-xs text-slate-600">{{ t('affiliate_note') }}</p>
  </footer>
</template>

<script setup>
import { inject } from 'vue';
const { t } = inject('i18n');
</script>

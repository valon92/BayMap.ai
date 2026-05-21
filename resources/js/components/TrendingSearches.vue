<template>
  <div class="w-full max-w-3xl mx-auto mt-10">
    <h3 class="text-sm font-medium text-slate-400 mb-4 text-center">{{ t('trending_title') }}</h3>
    <div v-if="loading" class="flex justify-center gap-2">
      <div v-for="n in 4" :key="n" class="skeleton h-8 w-32 rounded-full" />
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <button
        v-for="item in trending"
        :key="item.query"
        type="button"
        class="glass-card px-4 py-3 text-left flex items-center justify-between group"
        @click="$emit('select', item.query)"
      >
        <span class="text-sm text-slate-200 group-hover:text-white truncate pr-2">{{ item.query }}</span>
        <span class="text-xs text-slate-500 shrink-0">{{ formatCount(item.count) }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../services/api';

defineEmits(['select']);
const { t } = inject('i18n');
const trending = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const data = await api.getTrending();
    trending.value = data.trending || [];
  } finally {
    loading.value = false;
  }
});

function formatCount(n) {
  if (n >= 1000) return `${(n / 1000).toFixed(1)}k`;
  return n;
}
</script>

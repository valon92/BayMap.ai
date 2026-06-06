<template>
  <div class="w-full max-w-3xl mx-auto mt-8">
    <h3 class="text-sm font-semibold text-slate-600 mb-3 text-center">{{ t('examples_title') }}</h3>
    <div class="flex flex-wrap justify-center gap-2">
      <button
        v-for="(ex, i) in examples"
        :key="i"
        type="button"
        class="px-4 py-2.5 rounded-full text-sm text-slate-700
          bg-white border border-slate-200 shadow-sm
          hover:text-slate-900 hover:border-blue-200 hover:shadow-md
          active:scale-[0.98] transition-all duration-200 text-left"
        @click="$emit('select', localizedExample(ex))"
      >
        {{ localizedExample(ex) }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../services/api';

defineEmits(['select']);
const { t, locale } = inject('i18n');
const examples = ref([]);

onMounted(async () => {
  try {
    const data = await api.getExamples();
    examples.value = data.examples || [];
  } catch {
    examples.value = [];
  }
});

function localizedExample(ex) {
  return locale.value === 'sq' ? (ex.sq || ex.en) : (ex.en || ex.sq);
}
</script>

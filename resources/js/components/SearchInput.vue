<template>
  <form class="w-full max-w-3xl mx-auto" @submit.prevent="onSubmit">
    <div class="relative flex items-center">
      <textarea
        v-model="query"
        :placeholder="t('placeholder')"
        rows="2"
        class="input-glass resize-none pr-[7.5rem] sm:pr-36 min-h-[5rem] py-4 leading-relaxed"
        :disabled="loading"
        @keydown.enter.exact.prevent="onSubmit"
      />
      <button
        type="submit"
        class="btn-primary absolute right-3 top-1/2 -translate-y-1/2 text-sm px-5 py-2.5 shrink-0"
        :disabled="loading || query.trim().length < 3"
      >
        <span v-if="loading" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
        <span v-else>{{ t('search') }}</span>
      </button>
    </div>
  </form>
</template>

<script setup>
import { ref, watch, inject } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'search']);
const { t } = inject('i18n');
const query = ref(props.modelValue);

watch(() => props.modelValue, (v) => { query.value = v; });
watch(query, (v) => emit('update:modelValue', v));

function onSubmit() {
  if (query.value.trim().length >= 3) {
    emit('search', query.value.trim());
  }
}
</script>

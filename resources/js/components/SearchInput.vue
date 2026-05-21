<template>
  <form class="w-full max-w-3xl mx-auto search-hero-form" @submit.prevent="onSubmit">
    <div
      class="search-hero-card"
      :class="{ 'search-hero-card--focused': focused, 'search-hero-card--has-image': !!imagePreview }"
    >
      <div class="search-hero-glow" aria-hidden="true" />

      <div class="search-hero-inner">
        <div class="search-input-row">
          <textarea
            v-model="query"
            :placeholder="t('placeholder')"
            rows="2"
            class="search-textarea"
            :disabled="loading"
            @focus="focused = true"
            @blur="focused = false"
            @keydown.enter.exact.prevent="onSubmit"
          />
          <button
            type="submit"
            class="btn-search-inline"
            :disabled="loading || !canSearch"
          >
            <span v-if="loading" class="btn-search-spinner" />
            <span v-else>{{ t('search') }}</span>
          </button>
        </div>

        <div v-if="imagePreview" class="search-preview-wrap">
          <div class="search-preview">
            <img :src="imagePreview" alt="" class="search-preview-img" />
            <span class="search-preview-badge">{{ t('ai_will_analyze') }}</span>
            <button type="button" class="search-preview-remove" :aria-label="t('remove_photo')" @click="clearImage">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="search-toolbar">
          <div class="search-actions">
            <button
              type="button"
              class="search-action-btn"
              :disabled="loading"
              @click="triggerFilePick"
            >
              <span class="search-action-icon search-action-icon--upload">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </span>
              <span>{{ t('upload_photo') }}</span>
            </button>

            <button
              type="button"
              class="search-action-btn"
              :disabled="loading"
              @click="triggerCameraPick"
            >
              <span class="search-action-icon search-action-icon--camera">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </span>
              <span>{{ t('take_photo') }}</span>
            </button>
          </div>

          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            tabindex="-1"
            @change="onFileSelect"
          />
          <input
            ref="cameraInput"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            tabindex="-1"
            @change="onFileSelect"
          />
        </div>

        <SearchScopeChips v-model="locationScope" :disabled="loading" variant="embedded" />
      </div>
    </div>
  </form>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue';
import api from '../services/api';
import SearchScopeChips from './SearchScopeChips.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'search']);
const { t } = inject('i18n');

const query = ref(props.modelValue);
const imagePreview = ref(null);
const imageBase64 = ref(null);
const fileInput = ref(null);
const cameraInput = ref(null);
const locationScope = ref(api.getLocationScope());
const focused = ref(false);

const canSearch = computed(() => query.value.trim().length >= 3 || !!imageBase64.value);

watch(locationScope, (v) => api.setLocationScope(v));
watch(() => props.modelValue, (v) => { query.value = v; });
watch(query, (v) => emit('update:modelValue', v));

function triggerFilePick() {
  fileInput.value?.click();
}

function triggerCameraPick() {
  cameraInput.value?.click();
}

function onFileSelect(event) {
  const file = event.target.files?.[0];
  if (!file || !file.type.startsWith('image/')) return;
  if (file.size > 8 * 1024 * 1024) {
    alert(t('image_too_large'));
    return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    const result = reader.result;
    imagePreview.value = result;
    imageBase64.value = result.includes('base64,') ? result.split('base64,')[1] : result;
  };
  reader.readAsDataURL(file);
  event.target.value = '';
}

function clearImage() {
  imagePreview.value = null;
  imageBase64.value = null;
}

function onSubmit() {
  if (!canSearch.value) return;
  emit('search', {
    query: query.value.trim(),
    imageBase64: imageBase64.value,
    locationScope: locationScope.value,
  });
}

defineExpose({ clearImage });
</script>

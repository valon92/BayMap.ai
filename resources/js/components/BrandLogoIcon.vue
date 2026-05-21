<template>
  <img
    :src="iconSrc"
    alt=""
    :class="[
      'object-contain select-none bg-transparent',
      'drop-shadow-[0_2px_12px_rgba(56,189,248,0.35)]',
      sizeClasses,
    ]"
    decoding="async"
    aria-hidden="true"
  />
</template>

<script setup>
import { computed } from 'vue';

const iconSrc = '/images/powerbook-icon.png';

const props = defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
  },
});

const sizeClasses = computed(() => ({
  sm: 'h-9 w-9',
  md: 'h-11 w-11',
  lg: 'h-16 w-16',
  xl: 'h-20 w-20 sm:h-24 sm:w-24',
}[props.size]));
</script>

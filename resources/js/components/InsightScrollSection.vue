<template>
  <div class="insight-strip" :class="toneClass">
    <div class="insight-strip-header">
      <span class="insight-strip-icon" :class="iconClass" aria-hidden="true">
        <slot name="icon" />
      </span>
      <span class="insight-strip-label">{{ title }}</span>
    </div>

    <div class="scope-scroll">
      <div class="scope-scroll-track">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  tone: {
    type: String,
    default: 'slate',
    validator: (v) => ['slate', 'violet', 'sky', 'emerald', 'orange'].includes(v),
  },
});

const toneClass = computed(() => `insight-strip--${props.tone}`);

const iconClass = computed(() => {
  const map = {
    violet: 'insight-strip-icon--violet',
    sky: 'insight-strip-icon--sky',
    emerald: 'insight-strip-icon--emerald',
    orange: 'insight-strip-icon--orange',
    slate: 'insight-strip-icon--slate',
  };
  return map[props.tone] || map.slate;
});
</script>

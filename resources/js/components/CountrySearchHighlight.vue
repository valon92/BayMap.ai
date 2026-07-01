<template>
  <span>
    <span>{{ parts.before }}</span>
    <mark v-if="parts.match" class="market-search-highlight">{{ parts.match }}</mark>
    <span>{{ parts.after }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { splitNameHighlight } from '../utils/countrySearch';

const props = defineProps({
  name: { type: String, default: '' },
  query: { type: String, default: '' },
});

const parts = computed(() => splitNameHighlight(props.name, props.query));
</script>

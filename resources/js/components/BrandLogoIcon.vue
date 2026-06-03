<template>
  <div
    :class="[
      'brand-logo',
      `brand-logo--${size}`,
      full ? 'brand-logo--full' : 'brand-logo--mark-only',
    ]"
    role="img"
    :aria-label="full ? 'BuyMap.ai' : 'BuyMap'"
  >
    <svg
      class="brand-logo__mark"
      viewBox="0 0 48 56"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <defs>
        <linearGradient :id="pinGradId" x1="6" y1="2" x2="42" y2="54" gradientUnits="userSpaceOnUse">
          <stop stop-color="#38bdf8" />
          <stop offset="0.42" stop-color="#2563eb" />
          <stop offset="1" stop-color="#7c3aed" />
        </linearGradient>
        <linearGradient :id="shineGradId" x1="24" y1="6" x2="24" y2="28" gradientUnits="userSpaceOnUse">
          <stop stop-color="#ffffff" stop-opacity="0.42" />
          <stop offset="1" stop-color="#ffffff" stop-opacity="0" />
        </linearGradient>
        <filter :id="glowFilterId" x="-30%" y="-30%" width="160%" height="160%">
          <feGaussianBlur stdDeviation="1.2" result="blur" />
          <feMerge>
            <feMergeNode in="blur" />
            <feMergeNode in="SourceGraphic" />
          </feMerge>
        </filter>
      </defs>

      <path
        d="M24 1.5C12.8 1.5 3.5 10.8 3.5 22c0 9.1 11.2 23.6 18.8 33.5 1 1.3 3.2 1.3 4.2 0C34.1 45.6 44.5 31.1 44.5 22 44.5 10.8 35.2 1.5 24 1.5Z"
        :fill="`url(#${pinGradId})`"
        :filter="`url(#${glowFilterId})`"
      />
      <ellipse cx="24" cy="17" rx="11" ry="8.5" :fill="`url(#${shineGradId})`" />

      <g stroke="white" stroke-linecap="round">
        <path d="M24 48.5V32" stroke-width="1.25" stroke-opacity="0.55" />
        <line x1="24" y1="23" x2="14.5" y2="14" stroke-width="1.35" stroke-opacity="0.9" />
        <line x1="24" y1="23" x2="33.5" y2="14" stroke-width="1.35" stroke-opacity="0.9" />
        <line x1="24" y1="23" x2="12.5" y2="27" stroke-width="1.2" stroke-opacity="0.75" />
        <line x1="24" y1="23" x2="35.5" y2="27" stroke-width="1.2" stroke-opacity="0.75" />
        <line x1="24" y1="23" x2="24" y2="32" stroke-width="1.1" stroke-opacity="0.65" />
      </g>

      <circle cx="24" cy="23" r="3.25" fill="white" />
      <circle cx="14.5" cy="14" r="2.15" fill="white" />
      <circle cx="33.5" cy="14" r="2.15" fill="white" />
      <circle cx="12.5" cy="27" r="1.85" fill="white" fill-opacity="0.92" />
      <circle cx="35.5" cy="27" r="1.85" fill="white" fill-opacity="0.92" />
      <circle cx="24" cy="32" r="1.55" fill="white" fill-opacity="0.78" />
    </svg>

    <span v-if="full" class="brand-logo__wordmark" aria-hidden="true">
      <span class="brand-logo__name">BuyMap</span><span class="brand-logo__tld">.ai</span>
    </span>
  </div>
</template>

<script setup>
import { useId } from 'vue';

defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
  },
  full: {
    type: Boolean,
    default: false,
  },
});

const uid = useId().replace(/:/g, '');
const pinGradId = `buymap-pin-${uid}`;
const shineGradId = `buymap-shine-${uid}`;
const glowFilterId = `buymap-glow-${uid}`;
</script>

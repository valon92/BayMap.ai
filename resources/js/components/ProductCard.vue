<template>
  <article class="product-card group/card">
    <div class="relative aspect-square sm:aspect-[4/3] overflow-hidden bg-slate-100 shrink-0">
      <button
        type="button"
        class="group/image relative block h-full w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-inset"
        :class="displayImage ? 'cursor-zoom-in' : 'cursor-default'"
        :aria-label="displayImage ? t('gallery_open_fullscreen') : displayTitle"
        :disabled="!displayImage"
        @click.stop="openLightbox()"
      >
        <img
          v-if="displayImage"
          :src="displayImage"
          :alt="displayTitle"
          class="h-full w-full transition-transform duration-300 group-hover/card:scale-[1.03] group-hover/image:scale-[1.02]"
          :class="product.live ? 'object-contain p-2 bg-white' : 'object-cover'"
          loading="lazy"
          @error="onImageError"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-slate-200 text-slate-400"
          aria-hidden="true"
        >
          <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
          </svg>
        </div>
      </button>
      <div class="absolute inset-0 bg-gradient-to-t from-slate-900/25 via-transparent to-transparent pointer-events-none" />

      <button
        v-if="hasGallery && activeImageIndex > 0"
        type="button"
        class="absolute left-1 top-1/2 -translate-y-1/2 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm hover:bg-white"
        :aria-label="t('gallery_prev')"
        @click.stop="prevImage"
      >
        ‹
      </button>
      <button
        v-if="hasGallery && activeImageIndex < galleryImages.length - 1"
        type="button"
        class="absolute right-1 top-1/2 -translate-y-1/2 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm hover:bg-white"
        :aria-label="t('gallery_next')"
        @click.stop="nextImage"
      >
        ›
      </button>

      <span
        class="absolute top-1.5 right-1.5 px-1 py-px rounded text-[9px] font-semibold tabular-nums backdrop-blur-md"
        :class="scoreClass"
      >
        {{ product.match_score }}%
      </span>

      <span
        v-if="product.live"
        class="absolute top-1.5 left-1.5 px-1 py-px rounded text-[8px] uppercase tracking-wide bg-emerald-500/90 text-black font-semibold"
      >
        Live
      </span>
      <span
        v-else-if="product.sponsored"
        class="absolute top-1.5 left-1.5 px-1 py-px rounded text-[8px] uppercase tracking-wide bg-amber-500/90 text-black font-semibold"
      >
        Ad
      </span>

      <span
        v-if="hasGallery"
        class="absolute bottom-1.5 right-1.5 px-1.5 py-0.5 rounded-md text-[9px] font-medium bg-slate-900/75 text-white tabular-nums"
      >
        {{ activeImageIndex + 1 }}/{{ galleryImages.length }}
      </span>

      <span
        v-if="product.source"
        class="absolute bottom-1.5 left-1.5 max-w-[calc(100%-4rem)] truncate px-1.5 py-0.5 rounded-md text-[9px] font-medium bg-white/95 text-slate-700 shadow-sm border border-slate-200/80"
      >
        {{ product.source }}
      </span>
    </div>

    <div
      v-if="hasGallery && galleryImages.length > 1"
      class="flex gap-1 px-2 pt-1.5 overflow-x-auto scrollbar-thin"
    >
      <button
        v-for="(thumb, index) in galleryImages.slice(0, 8)"
        :key="`${product.id}-thumb-${index}`"
        type="button"
        class="shrink-0 h-9 w-12 rounded overflow-hidden border-2 transition-colors"
        :class="index === activeImageIndex ? 'border-blue-500' : 'border-transparent opacity-70 hover:opacity-100'"
        @click.stop="activeImageIndex = index; openLightbox(index)"
      >
        <img :src="thumb" :alt="`${displayTitle} ${index + 1}`" class="h-full w-full object-cover" loading="lazy" />
      </button>
      <span
        v-if="galleryImages.length > 8"
        class="shrink-0 self-center text-[9px] text-slate-500 px-1"
      >
        +{{ galleryImages.length - 8 }}
      </span>
    </div>

    <div class="relative flex flex-col flex-1 p-2 gap-1 min-h-0 pr-9">
      <h3
        class="text-[11px] leading-[1.35] font-medium text-slate-800 line-clamp-2 tracking-tight"
        :title="displayTitle"
      >
        {{ displayTitle }}
      </h3>

      <div
        v-if="productSpecs.length"
        class="flex flex-wrap gap-1"
      >
        <span
          v-for="(chip, index) in productSpecs.slice(0, 5)"
          :key="`${product.id}-spec-${index}`"
          class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-medium bg-slate-100 text-slate-600 border border-slate-200/80"
          :title="chip.value"
        >
          {{ chip.value }}
        </span>
      </div>

      <div class="flex items-center gap-1.5 flex-wrap mt-auto">
        <p class="text-[13px] font-semibold text-blue-600 tabular-nums leading-none">
          {{ formatPrice(product.price, product.currency) }}
        </p>
      </div>

      <p
        v-if="product.location"
        class="text-[9px] text-slate-500 truncate leading-tight"
      >
        {{ product.location }}
      </p>
    </div>

    <a
      :href="product.url"
      target="_blank"
      rel="noopener noreferrer sponsored"
      class="absolute bottom-2 right-2 z-10 flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 group-hover/card:translate-x-0.5"
      :aria-label="listingAriaLabel"
      @click.stop
    >
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
      </svg>
    </a>

    <Teleport to="body">
      <div
        v-if="lightboxOpen"
        class="fixed inset-0 z-[9999] flex flex-col bg-black/95"
        role="dialog"
        aria-modal="true"
        :aria-label="t('gallery_fullscreen')"
        @click.self="closeLightbox"
      >
        <div class="flex items-center justify-between gap-3 px-4 py-3 text-white shrink-0">
          <p class="min-w-0 flex-1 truncate text-sm font-medium">
            {{ displayTitle }}
          </p>
          <span
            v-if="galleryImages.length > 1"
            class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-xs tabular-nums"
          >
            {{ lightboxIndex + 1 }}/{{ galleryImages.length }}
          </span>
          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-xl leading-none transition-colors hover:bg-white/20"
            :aria-label="t('gallery_close')"
            @click.stop="closeLightbox"
          >
            ×
          </button>
        </div>

        <div class="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-4">
          <button
            v-if="galleryImages.length > 1 && lightboxIndex > 0"
            type="button"
            class="absolute left-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl text-white transition-colors hover:bg-white/20 sm:left-4"
            :aria-label="t('gallery_prev')"
            @click.stop="lightboxPrev"
          >
            ‹
          </button>

          <div
            v-if="!lightboxImage"
            class="flex h-48 w-full items-center justify-center text-white/60"
          >
            {{ t('gallery_no_image') }}
          </div>
          <img
            v-else
            :key="`${product.id}-${lightboxIndex}-${lightboxImage}`"
            :src="lightboxImage"
            :alt="`${displayTitle} ${lightboxIndex + 1}`"
            class="max-h-[calc(100vh-7rem)] max-w-[min(96vw,1200px)] object-contain transition-opacity duration-150"
            :class="lightboxLoaded ? 'opacity-100' : 'opacity-0'"
            @load="lightboxLoaded = true"
            @error="onLightboxImageError"
            @click.stop
          />

          <button
            v-if="galleryImages.length > 1 && lightboxIndex < galleryImages.length - 1"
            type="button"
            class="absolute right-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl text-white transition-colors hover:bg-white/20 sm:right-4"
            :aria-label="t('gallery_next')"
            @click.stop="lightboxNext"
          >
            ›
          </button>
        </div>

        <div
          v-if="galleryImages.length > 1"
          class="flex shrink-0 gap-2 overflow-x-auto px-4 pb-4 scrollbar-thin"
        >
          <button
            v-for="(thumb, index) in galleryImages"
            :key="`${product.id}-lightbox-thumb-${index}`"
            type="button"
            class="h-14 w-20 shrink-0 overflow-hidden rounded border-2 transition-colors"
            :class="index === lightboxIndex ? 'border-white' : 'border-transparent opacity-60 hover:opacity-100'"
            @click.stop="lightboxIndex = index; lightboxLoaded = false"
          >
            <img :src="thumb" :alt="`${displayTitle} ${index + 1}`" class="h-full w-full object-cover" />
          </button>
        </div>
      </div>
    </Teleport>
  </article>
</template>

<script setup>
import { computed, inject, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
  product: { type: Object, required: true },
});

const { t } = inject('i18n');

const activeImageIndex = ref(0);
const imageBroken = ref(false);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const lightboxLoaded = ref(false);

const PLACEHOLDER_IMAGE_PATTERNS = [
  /images\.unsplash\.com\/photo-1618843479313/i,
  /images\.unsplash\.com\/photo-1472851294608/i,
];

function isPlaceholderImage(url) {
  if (!url || typeof url !== 'string') {
    return true;
  }

  return PLACEHOLDER_IMAGE_PATTERNS.some((pattern) => pattern.test(url));
}

function buildGalleryUrls(product) {
  const urls = [];
  const seen = new Set();

  const add = (url) => {
    if (isPlaceholderImage(url) || seen.has(url)) {
      return;
    }
    seen.add(url);
    urls.push(url);
  };

  if (Array.isArray(product.images)) {
    product.images.filter(Boolean).forEach(add);
  }

  add(product.image);

  return urls;
}

const galleryImages = computed(() => buildGalleryUrls(props.product));

const hasGallery = computed(() => galleryImages.value.length > 1);

const displayImage = computed(() => galleryImages.value[activeImageIndex.value] ?? null);

const lightboxImage = computed(() => galleryImages.value[lightboxIndex.value] ?? null);

function openLightbox(index = activeImageIndex.value) {
  if (!galleryImages.value.length) {
    return;
  }

  const safeIndex = Math.min(Math.max(index, 0), galleryImages.value.length - 1);
  lightboxLoaded.value = false;
  lightboxIndex.value = safeIndex;
  activeImageIndex.value = safeIndex;
  lightboxOpen.value = true;
}

function closeLightbox() {
  lightboxOpen.value = false;
}

function lightboxPrev() {
  if (lightboxIndex.value > 0) {
    lightboxLoaded.value = false;
    lightboxIndex.value -= 1;
    activeImageIndex.value = lightboxIndex.value;
  }
}

function lightboxNext() {
  if (lightboxIndex.value < galleryImages.value.length - 1) {
    lightboxLoaded.value = false;
    lightboxIndex.value += 1;
    activeImageIndex.value = lightboxIndex.value;
  }
}

function onLightboxKeydown(event) {
  if (!lightboxOpen.value) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    closeLightbox();
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault();
    lightboxPrev();
  } else if (event.key === 'ArrowRight') {
    event.preventDefault();
    lightboxNext();
  }
}

watch(lightboxOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) {
    window.addEventListener('keydown', onLightboxKeydown);
  } else {
    window.removeEventListener('keydown', onLightboxKeydown);
  }
}, { flush: 'post' });

onUnmounted(() => {
  document.body.style.overflow = '';
  window.removeEventListener('keydown', onLightboxKeydown);
});

const productCategory = computed(() => {
  const p = props.product;
  if (p.category) return p.category;
  if (Array.isArray(p.tags)) {
    if (p.tags.includes('automotive')) return 'automotive';
    if (p.tags.includes('real_estate')) return 'real_estate';
    if (p.tags.includes('electronics_tech') || p.tags.includes('gaming_entertainment')) return 'electronics_tech';
    if (p.tags.includes('fashion')) return 'fashion';
  }
  if (p.product_type === 'car') return 'automotive';
  if (['laptop', 'phone', 'tablet', 'headphones', 'monitor', 'smartwatch'].includes(p.product_type)) {
    return 'electronics_tech';
  }
  return 'marketplace';
});

const productSpecs = computed(() => {
  if (Array.isArray(props.product.specs) && props.product.specs.length) {
    return props.product.specs.map((chip) => ({
      label: chip.label ?? '',
      value: chip.value ?? String(chip),
    }));
  }

  const chips = [];
  const p = props.product;
  const category = productCategory.value;

  if (category === 'automotive' || p.mileage != null) {
    if (p.year) chips.push({ label: 'year', value: String(p.year) });
    if (p.mileage != null) chips.push({ label: 'mileage', value: formatMileage(p.mileage) });
    if (p.fuel) chips.push({ label: 'fuel', value: p.fuel });
    if (p.transmission) chips.push({ label: 'transmission', value: p.transmission });
    if (p.power_hp) {
      chips.push({
        label: 'power',
        value: p.power_kw ? `${p.power_kw} kW (${p.power_hp} PS)` : `${p.power_hp} PS`,
      });
    }
    if (p.electric_range_km) chips.push({ label: 'range', value: `${p.electric_range_km} km` });
    if (p.seller_type) {
      chips.push({
        label: 'seller',
        value: p.seller_type === 'dealer' ? t('seller_dealer') : t('seller_private'),
      });
    }
    return chips;
  }

  if (category === 'real_estate') {
    if (p.rooms) chips.push({ label: 'rooms', value: `${p.rooms} ${p.rooms === 1 ? 'room' : 'rooms'}` });
    if (p.area_sqm) chips.push({ label: 'area', value: `${p.area_sqm} m²` });
    if (p.property_type) chips.push({ label: 'type', value: p.property_type });
    if (p.listing_type) chips.push({ label: 'listing', value: p.listing_type });
    return chips;
  }

  if (category === 'electronics_tech' || category === 'gaming_entertainment') {
    if (p.storage) chips.push({ label: 'storage', value: p.storage });
    if (p.ram) chips.push({ label: 'ram', value: p.ram });
    if (p.display_size) chips.push({ label: 'display', value: p.display_size });
    if (p.chip) chips.push({ label: 'chip', value: p.chip });
    if (p.year) chips.push({ label: 'year', value: String(p.year) });
    if (p.brand) chips.push({ label: 'brand', value: p.brand });
    if (p.model) chips.push({ label: 'model', value: p.model });
    return chips;
  }

  if (category === 'fashion' || category === 'sports_outdoor') {
    if (p.brand) chips.push({ label: 'brand', value: p.brand });
    if (Array.isArray(p.sizes) && p.sizes.length) {
      chips.push({ label: 'size', value: p.sizes.slice(0, 4).join(', ') });
    }
    if (p.gender) chips.push({ label: 'gender', value: p.gender });
    if (p.color) chips.push({ label: 'color', value: p.color });
    return chips;
  }

  if (p.storage) chips.push({ label: 'storage', value: p.storage });
  if (p.ram) chips.push({ label: 'ram', value: p.ram });
  if (p.brand) chips.push({ label: 'brand', value: p.brand });
  if (p.model) chips.push({ label: 'model', value: p.model });
  if (p.format) chips.push({ label: 'format', value: p.format });
  if (p.genre) chips.push({ label: 'genre', value: p.genre });
  if (p.author) chips.push({ label: 'author', value: p.author });

  return chips;
});

function onImageError() {
  if (activeImageIndex.value < galleryImages.value.length - 1) {
    activeImageIndex.value += 1;
    return;
  }

  imageBroken.value = true;
}

function onLightboxImageError() {
  lightboxLoaded.value = true;
}

function prevImage() {
  if (activeImageIndex.value > 0) {
    activeImageIndex.value -= 1;
    imageBroken.value = false;
  }
}

function nextImage() {
  if (activeImageIndex.value < galleryImages.value.length - 1) {
    activeImageIndex.value += 1;
    imageBroken.value = false;
  }
}

watch(lightboxIndex, () => {
  lightboxLoaded.value = false;
});

watch(
  () => props.product?.id ?? props.product?.image,
  () => {
    imageBroken.value = false;
    activeImageIndex.value = 0;
    lightboxOpen.value = false;
    lightboxIndex.value = 0;
    lightboxLoaded.value = false;
  },
);

const scoreClass = computed(() => {
  const s = props.product.match_score || 0;
  if (s >= 90) return 'bg-emerald-500 text-white';
  if (s >= 80) return 'bg-blue-600 text-white';
  return 'bg-slate-600 text-white';
});

const displayTitle = computed(() => stripStoreFromTitle(props.product.title, props.product.source));

const listingAriaLabel = computed(() => {
  const label = t('buy');
  return `${label}: ${displayTitle.value}`;
});

function formatMileage(km) {
  return `${new Intl.NumberFormat('de-DE').format(Number(km))} km`;
}

function stripStoreFromTitle(title, source) {
  if (!title) return '';
  let result = title.trim();
  const sourceKey = normalizeKey(source);

  for (const sep of [' — ', ' · ', ' - ']) {
    const idx = result.lastIndexOf(sep);
    if (idx <= 0) continue;

    const suffix = result.slice(idx + sep.length);
    const suffixKey = normalizeKey(suffix);

    if (!suffixKey) continue;

    const matchesSource =
      sourceKey &&
      (suffixKey.includes(sourceKey) ||
        sourceKey.includes(suffixKey) ||
        sharesToken(suffixKey, sourceKey));

    const looksLikeStore =
      /(merrjep|dyqani|pazar\s*3|gjirafa|neptun|aza\s*electronics|focus\s*electronics|pc\s*store|sparkle|tregu\.com|ebay|amazon|etsy|auto\s*scout|mobile\.de)/i.test(
        suffix
      );

    if (matchesSource || looksLikeStore) {
      result = result.slice(0, idx).trim();
      break;
    }
  }

  return result.replace(/\s·\s*#\d+\s*$/i, '').replace(/\s{2,}/g, ' ').trim();
}

function normalizeKey(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '');
}

function sharesToken(a, b) {
  if (!a || !b || a.length < 4 || b.length < 4) return false;
  const min = Math.min(a.length, b.length, 8);
  return a.slice(0, min) === b.slice(0, min);
}

function formatPrice(price, currency = 'EUR') {
  return new Intl.NumberFormat('en-EU', { style: 'currency', currency }).format(price);
}
</script>

<template>
  <div class="results-skeleton grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 sm:gap-3">
    <div
      v-for="n in 10"
      :key="n"
      class="results-skeleton__card"
      :style="{ animationDelay: `${(n - 1) * 55}ms` }"
    >
      <div class="results-skeleton__media skeleton-shimmer" />
      <div class="p-2 space-y-2 pr-9">
        <div class="skeleton-shimmer h-2.5 w-full rounded-md" />
        <div class="skeleton-shimmer h-2.5 w-4/5 rounded-md" />
        <div class="skeleton-shimmer h-3.5 w-1/3 rounded-md mt-1" />
        <div class="skeleton-shimmer h-2 w-2/3 rounded-md" />
      </div>
      <div class="skeleton-shimmer absolute bottom-2 right-2 h-7 w-7 rounded-full" />
    </div>
  </div>
</template>

<template>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 sm:gap-3">
    <div v-for="n in 10" :key="n" class="rounded-xl border border-white/[0.06] overflow-hidden bg-slate-900/40">
      <div class="skeleton aspect-square sm:aspect-[4/3] rounded-none" />
      <div class="p-2.5 space-y-2">
        <div class="skeleton h-3 w-full" />
        <div class="skeleton h-3 w-2/3" />
        <div class="skeleton h-5 w-1/2" />
        <div class="skeleton h-7 w-full rounded-lg" />
      </div>
    </div>
  </div>
</template>

<template>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 sm:gap-3">
    <div v-for="n in 10" :key="n" class="relative rounded-2xl border border-slate-200 overflow-hidden bg-white shadow-card">
      <div class="skeleton aspect-square sm:aspect-[4/3] rounded-none" />
      <div class="p-2 space-y-1.5 pr-9">
        <div class="skeleton h-2.5 w-full" />
        <div class="skeleton h-2.5 w-4/5" />
        <div class="skeleton h-3.5 w-1/3 mt-1" />
        <div class="skeleton h-2 w-2/3" />
      </div>
      <div class="skeleton absolute bottom-2 right-2 h-7 w-7 rounded-full" />
    </div>
  </div>
</template>

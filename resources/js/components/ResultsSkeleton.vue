<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
    <div v-for="n in 6" :key="n" class="glass rounded-2xl overflow-hidden">
      <div class="skeleton aspect-[4/3] rounded-none" />
      <div class="p-4 space-y-3">
        <div class="skeleton h-4 w-3/4" />
        <div class="skeleton h-6 w-1/3" />
        <div class="skeleton h-16 w-full" />
        <div class="skeleton h-10 w-full rounded-xl" />
      </div>
    </div>
  </div>
</template>

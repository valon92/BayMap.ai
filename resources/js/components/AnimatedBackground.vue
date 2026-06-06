<template>
  <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none ai-mesh" aria-hidden="true">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[min(900px,100vw)] h-[420px] bg-gradient-to-b from-blue-100/80 via-blue-50/40 to-transparent rounded-full blur-3xl" />
    <div class="absolute top-1/4 right-0 w-80 h-80 bg-violet-200/40 rounded-full blur-3xl animate-float" />
    <div class="absolute bottom-0 left-0 w-96 h-96 bg-sky-100/50 rounded-full blur-3xl animate-float" style="animation-delay: -2s" />
    <div
      class="absolute inset-0 opacity-[0.35]"
      style="background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.22) 1px, transparent 0); background-size: 32px 32px"
    />
  </div>
</template>

<template>
  <div class="fixed inset-0 -z-10 overflow-clip pointer-events-none ai-mesh isolate" aria-hidden="true">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-[min(900px,100%)] h-[420px] bg-gradient-to-b from-blue-100/80 via-blue-50/40 to-transparent rounded-full blur-3xl" />
    <div class="absolute top-1/4 -right-16 sm:right-0 w-56 sm:w-80 h-56 sm:h-80 max-w-[70vw] bg-violet-200/40 rounded-full blur-3xl animate-float" />
    <div class="absolute bottom-0 -left-16 sm:left-0 w-64 sm:w-96 h-64 sm:h-96 max-w-[75vw] bg-sky-100/50 rounded-full blur-3xl animate-float" style="animation-delay: -2s" />
    <div
      class="absolute inset-0 opacity-[0.35]"
      style="background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.22) 1px, transparent 0); background-size: 32px 32px"
    />
  </div>
</template>

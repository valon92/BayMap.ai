import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import ResultsView from '../views/ResultsView.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
    meta: { title: 'Powerbook.ai — AI Semantic Shopping' },
  },
  {
    path: '/search',
    name: 'search',
    component: ResultsView,
    meta: { title: 'Search Results — Powerbook.ai' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

router.afterEach((to) => {
  const base = 'Powerbook.ai';
  document.title = to.meta.title || base;
});

export default router;

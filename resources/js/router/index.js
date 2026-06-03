import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import ResultsView from '../views/ResultsView.vue';
import HowItWorksView from '../views/HowItWorksView.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
    meta: { title: 'BuyMap.ai — AI Semantic Shopping' },
  },
  {
    path: '/how-it-works',
    name: 'how-it-works',
    component: HowItWorksView,
    meta: { title: 'How It Works — BuyMap.ai' },
  },
  {
    path: '/search',
    name: 'search',
    component: ResultsView,
    meta: { title: 'Search Results — BuyMap.ai' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

const titles = {
  home: { en: 'BuyMap.ai — AI Semantic Shopping', sq: 'BuyMap.ai — Blerje semantike me AI' },
  'how-it-works': { en: 'How It Works — BuyMap.ai', sq: 'Si funksionon — BuyMap.ai' },
  search: { en: 'Search Results — BuyMap.ai', sq: 'Rezultatet — BuyMap.ai' },
};

router.afterEach((to) => {
  const lang = document.documentElement.lang === 'sq' ? 'sq' : 'en';
  const routeTitles = titles[to.name];
  document.title = routeTitles?.[lang] || routeTitles?.en || 'BuyMap.ai';
});

export default router;

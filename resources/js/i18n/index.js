import { ref, computed } from 'vue';
import en from './locales/en.json';
import sq from './locales/sq.json';

const messages = { en, sq };
const locale = ref('en');
const geoResolved = ref(false);

export function useI18n() {
  const t = (key, params = {}) => {
    const keys = key.split('.');
    let value = messages[locale.value] || messages.en;
    for (const k of keys) {
      value = value?.[k];
    }
    if (typeof value !== 'string') return key;
    return Object.entries(params).reduce(
      (str, [k, v]) => str.replace(`:${k}`, String(v)),
      value
    );
  };

  const setLocale = (lang) => {
    if (messages[lang]) locale.value = lang;
    document.documentElement.lang = lang;
  };

  const tagline = computed(() => t('tagline'));

  return { locale, t, setLocale, tagline, geoResolved };
}

export const i18nPlugin = {
  install(app) {
    app.config.globalProperties.$t = (key, params) => {
      const { t } = useI18n();
      return t(key, params);
    };
    app.provide('i18n', useI18n());
  },
};

export async function initLocaleFromGeo(api) {
  try {
    const geo = await api.getGeo();
    const loc = geo.locale === 'sq' ? 'sq' : 'en';
    locale.value = loc;
    document.documentElement.lang = loc;
    geoResolved.value = true;
    return geo;
  } catch {
    const browser = navigator.language?.startsWith('sq') ? 'sq' : 'en';
    locale.value = browser;
    document.documentElement.lang = browser;
    geoResolved.value = true;
    return null;
  }
}

import { ref, computed } from 'vue';
import en from './locales/en.json';
import sq from './locales/sq.json';
import de from './locales/de.json';
import fr from './locales/fr.json';
import it from './locales/it.json';
import es from './locales/es.json';
import zh from './locales/zh.json';

const messages = { en, sq, de, fr, it, es, zh };

const locale = ref('en');
const regionalLocale = ref('en');
const localeOptions = ref([{ code: 'en', label: 'EN' }]);
const geoResolved = ref(false);

function translate(lang, key) {
  const keys = key.split('.');
  for (const loc of [lang, 'en']) {
    let value = messages[loc];
    if (!value) continue;
    for (const k of keys) {
      value = value?.[k];
    }
    if (typeof value === 'string') return value;
  }
  return key;
}

function applyGeoLocales(geo) {
  const options = Array.isArray(geo?.ui_locales) && geo.ui_locales.length
    ? geo.ui_locales
    : [{ code: 'en', label: 'EN' }];

  localeOptions.value = options.filter((opt) => messages[opt.code]);

  const regional = geo?.regional_locale || geo?.locale || 'en';
  regionalLocale.value = messages[regional] ? regional : 'en';

  const preferred = geo?.default_locale || regionalLocale.value;
  locale.value = messages[preferred] ? preferred : 'en';
  document.documentElement.lang = locale.value;
}

export function useI18n() {
  const t = (key, params = {}) => {
    const value = translate(locale.value, key);
    return Object.entries(params).reduce(
      (str, [k, v]) => str.replace(`:${k}`, String(v)).replace(`{${k}}`, String(v)),
      value
    );
  };

  const setLocale = (lang) => {
    if (!messages[lang]) return;
    locale.value = lang;
    document.documentElement.lang = lang;
  };

  const tagline = computed(() => t('tagline'));
  const taglineAlt = computed(() => {
    const alt = t('tagline_alt');
    return alt !== 'tagline_alt' ? alt : null;
  });

  return {
    locale,
    regionalLocale,
    localeOptions,
    t,
    setLocale,
    tagline,
    taglineAlt,
    geoResolved,
  };
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
    applyGeoLocales(geo);
    geoResolved.value = true;
    return geo;
  } catch {
    locale.value = 'en';
    regionalLocale.value = 'en';
    localeOptions.value = [{ code: 'en', label: 'EN' }];
    document.documentElement.lang = 'en';
    geoResolved.value = true;
    return null;
  }
}

<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from './stores/app'
import { useIngredientsStore } from './stores/ingredients'
import { LOCALE_NAMES, SUPPORTED_LOCALES, setLocale } from './i18n'

const { locale } = useI18n()
const app = useAppStore()
const ingredients = useIngredientsStore()
onMounted(() => {
  app.loadMembers()
  ingredients.loadAll() // прогріваємо довідник, щоб автокомпліт був миттєвим
})
</script>

<template>
  <header class="topbar">
    <span class="brand">🥗 FamilyDiet</span>
    <nav>
      <RouterLink to="/">{{ $t('nav.calendar') }}</RouterLink>
      <RouterLink to="/dishes">{{ $t('nav.dishes') }}</RouterLink>
      <RouterLink to="/ingredients">{{ $t('nav.ingredients') }}</RouterLink>
      <RouterLink to="/health">{{ $t('nav.health') }}</RouterLink>
    </nav>
    <select
      class="lang-switch"
      :value="locale"
      :title="$t('nav.language')"
      @change="setLocale($event.target.value)"
    >
      <option v-for="l in SUPPORTED_LOCALES" :key="l" :value="l">
        {{ LOCALE_NAMES[l] ?? l.toUpperCase() }}
      </option>
    </select>
  </header>
  <main class="page">
    <RouterView />
  </main>
</template>

<style scoped>
.lang-switch {
  margin-left: auto;
  padding: 4px 8px;
  font-size: 13px;
}
</style>

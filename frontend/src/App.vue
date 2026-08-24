<script setup>
import { onMounted } from 'vue'
import { useAppStore } from './stores/app'
import { useIngredientsStore } from './stores/ingredients'

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
      <RouterLink to="/">Календар</RouterLink>
      <RouterLink to="/dishes">Страви</RouterLink>
      <RouterLink to="/ingredients">Інгредієнти</RouterLink>
      <RouterLink to="/health">Здоров'я</RouterLink>
    </nav>
  </header>
  <main class="page">
    <RouterView />
  </main>
</template>

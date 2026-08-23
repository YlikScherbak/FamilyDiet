<script setup>
import { onMounted, ref } from 'vue'
import { api, MEAL_SLOTS, slotLabel } from '../api'
import { useAppStore } from '../stores/app'

const app = useAppStore()
const items = ref([])
const search = ref('')
const category = ref('')

async function load() {
  const params = new URLSearchParams()
  if (search.value) params.set('search', search.value)
  if (category.value) params.set('category', category.value)
  items.value = await api.get(`/dishes?${params}`)
}

async function remove(item) {
  if (!confirm(`Видалити страву «${item.name}»?`)) return
  try {
    await api.del(`/dishes/${item.id}`)
    await load()
  } catch (e) {
    alert(e.message)
  }
}

const kcalOf = (dish, memberId) =>
  dish.portions.find((p) => p.familyMemberId === memberId)?.nutrition.kcal ?? '—'

onMounted(load)
</script>

<template>
  <div>
    <h1>Страви</h1>
    <div class="toolbar">
      <input
        v-model="search"
        placeholder="Пошук за назвою або кодом..."
        style="width: 280px"
        @input="load"
      />
      <select v-model="category" @change="load">
        <option value="">Всі категорії</option>
        <option v-for="s in MEAL_SLOTS" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <span class="muted">{{ items.length }} шт.</span>
      <span class="spacer" />
      <RouterLink to="/dishes/new"><button class="primary">+ Додати страву</button></RouterLink>
    </div>

    <table class="data">
      <thead>
        <tr>
          <th>Код</th>
          <th>Назва</th>
          <th>Категорія</th>
          <th v-for="m in app.members" :key="m.id">Ккал ({{ m.name }})</th>
          <th>Заготівля</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td class="muted">{{ item.code ?? '—' }}</td>
          <td>
            <RouterLink :to="`/dishes/${item.id}`">{{ item.name }}</RouterLink>
          </td>
          <td>
            <span class="badge">{{ slotLabel(item.category) }}</span>
          </td>
          <td v-for="m in app.members" :key="m.id">{{ kcalOf(item, m.id) }}</td>
          <td>{{ item.batchCooking ? '✔' : '' }}</td>
          <td style="white-space: nowrap">
            <RouterLink :to="`/dishes/${item.id}`"
              ><button class="small">Редагувати</button></RouterLink
            >
            <button class="small danger" @click="remove(item)">✕</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

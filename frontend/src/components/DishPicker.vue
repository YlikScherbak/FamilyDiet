<script setup>
import { onMounted, ref, watch } from 'vue'
import { api, MEAL_SLOTS, slotLabel } from '../api'

const props = defineProps({
  defaultCategory: { type: String, default: '' },
})
const emit = defineEmits(['select', 'close'])

const search = ref('')
const category = ref(props.defaultCategory)
const items = ref([])

async function load() {
  const params = new URLSearchParams()
  if (search.value) params.set('search', search.value)
  if (category.value) params.set('category', category.value)
  items.value = await api.get(`/dishes?${params}`)
}

watch([search, category], load)
onMounted(load)
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal">
      <h2 style="margin-top: 0">Оберіть страву</h2>
      <div class="toolbar">
        <input v-model="search" placeholder="Пошук..." style="flex: 1" autofocus />
        <select v-model="category">
          <option value="">Всі категорії</option>
          <option v-for="s in MEAL_SLOTS" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </div>
      <table class="data">
        <tbody>
          <tr
            v-for="dish in items"
            :key="dish.id"
            style="cursor: pointer"
            @click="emit('select', dish)"
          >
            <td class="muted" style="width: 50px">{{ dish.code ?? '' }}</td>
            <td>{{ dish.name }}</td>
            <td style="width: 110px"><span class="badge">{{ slotLabel(dish.category) }}</span></td>
          </tr>
          <tr v-if="items.length === 0">
            <td class="muted">Нічого не знайдено</td>
          </tr>
        </tbody>
      </table>
      <div class="actions">
        <button @click="emit('close')">Закрити</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api, INGREDIENT_CATEGORIES, UNITS, categoryLabel, unitLabel } from '../api'
import { useIngredientsStore } from '../stores/ingredients'

const store = useIngredientsStore()
const search = ref('')
const category = ref('')
const error = ref('')
const showForm = ref(false)
const saving = ref(false)

const LIMIT = 200

const items = computed(() => store.search(search.value, { category: category.value, limit: LIMIT }))

const blank = () => ({
  id: null,
  name: '',
  category: 'other',
  unit: 'g',
  kcalPer100: 0,
  proteinPer100: 0,
  fatPer100: 0,
  carbsPer100: 0,
  pieceWeightGrams: null,
})
const form = reactive(blank())

function openCreate() {
  Object.assign(form, blank())
  error.value = ''
  showForm.value = true
}

function openEdit(item) {
  Object.assign(form, item)
  error.value = ''
  showForm.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const body = { ...form }
    const saved = form.id
      ? await api.put(`/ingredients/${form.id}`, body)
      : await api.post('/ingredients', body)
    store.upsert(saved)
    showForm.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!confirm(`Видалити «${item.name}»?`)) return
  try {
    await api.del(`/ingredients/${item.id}`)
    store.remove(item.id)
  } catch (e) {
    alert(e.message)
  }
}

onMounted(() => store.loadAll())
</script>

<template>
  <div>
    <h1>Інгредієнти</h1>
    <div class="toolbar">
      <input
        v-model="search"
        placeholder="Пошук (укр або англ, слова в будь-якому порядку)..."
        style="width: 320px"
      />
      <select v-model="category">
        <option value="">Всі категорії</option>
        <option v-for="c in INGREDIENT_CATEGORIES" :key="c.value" :value="c.value">
          {{ c.label }}
        </option>
      </select>
      <span class="muted">
        <template v-if="!store.loaded">завантаження довідника...</template>
        <template v-else>
          {{ items.length }} шт.<template v-if="items.length >= LIMIT">
            (перші {{ LIMIT }} з {{ store.items.length }} — звузьте пошук)</template
          >
        </template>
      </span>
      <span class="spacer" />
      <button class="primary" @click="openCreate">+ Додати інгредієнт</button>
    </div>

    <table class="data">
      <thead>
        <tr>
          <th>Назва</th>
          <th>Категорія</th>
          <th>Од.</th>
          <th>Ккал/100</th>
          <th>Б</th>
          <th>Ж</th>
          <th>В</th>
          <th>Вага 1 шт</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td>
            {{ item.name }}
            <div v-if="item.nameEn" class="muted" style="font-size: 11.5px">{{ item.nameEn }}</div>
          </td>
          <td>
            <span class="badge">{{ categoryLabel(item.category) }}</span>
          </td>
          <td>{{ unitLabel(item.unit) }}</td>
          <td>{{ item.kcalPer100 }}</td>
          <td>{{ item.proteinPer100 }}</td>
          <td>{{ item.fatPer100 }}</td>
          <td>{{ item.carbsPer100 }}</td>
          <td>{{ item.pieceWeightGrams ? item.pieceWeightGrams + ' г' : '—' }}</td>
          <td style="white-space: nowrap">
            <button class="small" @click="openEdit(item)">Редагувати</button>
            <button class="small danger" @click="remove(item)">✕</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal">
        <h2 style="margin-top: 0">{{ form.id ? 'Редагувати інгредієнт' : 'Новий інгредієнт' }}</h2>
        <div class="form-grid">
          <div class="field full">
            <label>Назва</label>
            <input v-model="form.name" />
          </div>
          <div class="field">
            <label>Категорія</label>
            <select v-model="form.category">
              <option v-for="c in INGREDIENT_CATEGORIES" :key="c.value" :value="c.value">
                {{ c.label }}
              </option>
            </select>
          </div>
          <div class="field">
            <label>Одиниця виміру</label>
            <select v-model="form.unit">
              <option v-for="u in UNITS" :key="u.value" :value="u.value">{{ u.label }}</option>
            </select>
          </div>
          <div class="field">
            <label>Ккал на 100 г</label>
            <input v-model.number="form.kcalPer100" type="number" min="0" step="0.1" />
          </div>
          <div class="field">
            <label>Білки на 100 г</label>
            <input v-model.number="form.proteinPer100" type="number" min="0" step="0.1" />
          </div>
          <div class="field">
            <label>Жири на 100 г</label>
            <input v-model.number="form.fatPer100" type="number" min="0" step="0.1" />
          </div>
          <div class="field">
            <label>Вуглеводи на 100 г</label>
            <input v-model.number="form.carbsPer100" type="number" min="0" step="0.1" />
          </div>
          <div v-if="form.unit === 'pcs'" class="field">
            <label>Вага 1 шт, г</label>
            <input v-model.number="form.pieceWeightGrams" type="number" min="0" step="1" />
          </div>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <div class="actions">
          <button @click="showForm = false">Скасувати</button>
          <button class="primary" :disabled="saving" @click="save">Зберегти</button>
        </div>
      </div>
    </div>
  </div>
</template>

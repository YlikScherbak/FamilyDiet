<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, INGREDIENT_CATEGORIES, UNITS } from '../api'
import { useIngredientsStore } from '../stores/ingredients'

const { t } = useI18n()

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
  if (!confirm(t('ingredients.confirmDelete', { name: item.name }))) return
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
    <h1>{{ $t('ingredients.title') }}</h1>
    <div class="toolbar">
      <input
        v-model="search"
        :placeholder="$t('ingredients.searchPlaceholder')"
        style="width: 320px"
      />
      <select v-model="category">
        <option value="">{{ $t('common.allCategories') }}</option>
        <option v-for="c in INGREDIENT_CATEGORIES" :key="c" :value="c">
          {{ $t(`categories.${c}`) }}
        </option>
      </select>
      <span class="muted">
        <template v-if="!store.loaded">{{ $t('ingredients.loading') }}</template>
        <template v-else>
          {{ $t('common.count', { n: items.length })
          }}<template v-if="items.length >= LIMIT">
            {{ $t('ingredients.narrow', { limit: LIMIT, total: store.items.length }) }}</template
          >
        </template>
      </span>
      <span class="spacer" />
      <button class="primary" @click="openCreate">{{ $t('ingredients.add') }}</button>
    </div>

    <table class="data">
      <thead>
        <tr>
          <th>{{ $t('ingredients.name') }}</th>
          <th>{{ $t('ingredients.category') }}</th>
          <th>{{ $t('ingredients.unit') }}</th>
          <th>{{ $t('ingredients.kcal100') }}</th>
          <th>{{ $t('nutrients.p') }}</th>
          <th>{{ $t('nutrients.f') }}</th>
          <th>{{ $t('nutrients.c') }}</th>
          <th>{{ $t('ingredients.pieceWeight') }}</th>
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
            <span class="badge">{{ $t(`categories.${item.category}`) }}</span>
          </td>
          <td>{{ $t(`units.${item.unit}`) }}</td>
          <td>{{ item.kcalPer100 }}</td>
          <td>{{ item.proteinPer100 }}</td>
          <td>{{ item.fatPer100 }}</td>
          <td>{{ item.carbsPer100 }}</td>
          <td>
            {{ item.pieceWeightGrams ? item.pieceWeightGrams + ' ' + $t('common.grams') : '—' }}
          </td>
          <td style="white-space: nowrap">
            <button class="small" @click="openEdit(item)">{{ $t('common.edit') }}</button>
            <button class="small danger" @click="remove(item)">✕</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal">
        <h2 style="margin-top: 0">
          {{ form.id ? $t('ingredients.editTitle') : $t('ingredients.newTitle') }}
        </h2>
        <div class="form-grid">
          <div class="field full">
            <label>{{ $t('ingredients.name') }}</label>
            <input v-model="form.name" />
          </div>
          <div class="field">
            <label>{{ $t('ingredients.category') }}</label>
            <select v-model="form.category">
              <option v-for="c in INGREDIENT_CATEGORIES" :key="c" :value="c">
                {{ $t(`categories.${c}`) }}
              </option>
            </select>
          </div>
          <div class="field">
            <label>{{ $t('ingredients.unitLabel') }}</label>
            <select v-model="form.unit">
              <option v-for="u in UNITS" :key="u" :value="u">{{ $t(`units.${u}`) }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ $t('ingredients.kcalPer100') }}</label>
            <input v-model.number="form.kcalPer100" type="number" min="0" step="0.1" />
          </div>
          <div class="field">
            <label>{{ $t('ingredients.proteinPer100') }}</label>
            <input v-model.number="form.proteinPer100" type="number" min="0" step="0.1" />
          </div>
          <div class="field">
            <label>{{ $t('ingredients.fatPer100') }}</label>
            <input v-model.number="form.fatPer100" type="number" min="0" step="0.1" />
          </div>
          <div class="field">
            <label>{{ $t('ingredients.carbsPer100') }}</label>
            <input v-model.number="form.carbsPer100" type="number" min="0" step="0.1" />
          </div>
          <div v-if="form.unit === 'pcs'" class="field">
            <label>{{ $t('ingredients.pieceWeightGrams') }}</label>
            <input v-model.number="form.pieceWeightGrams" type="number" min="0" step="1" />
          </div>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <div class="actions">
          <button @click="showForm = false">{{ $t('common.cancel') }}</button>
          <button class="primary" :disabled="saving" @click="save">{{ $t('common.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

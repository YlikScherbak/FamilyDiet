<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, MEAL_SLOTS } from '../api'
import { useAppStore } from '../stores/app'
import IngredientAutocomplete from '../components/IngredientAutocomplete.vue'

const route = useRoute()
const router = useRouter()
const app = useAppStore()

const dishId = route.params.id ? Number(route.params.id) : null
// Кеш даних інгредієнтів (per100) для живого підрахунку КБЖВ
const ingredientCache = reactive({})
const state = reactive({ error: '', saving: false })

const form = reactive({
  name: '',
  code: '',
  category: 'lunch',
  recipe: '',
  youtubeUrl: '',
  batchCooking: false,
  // portions[memberId] = [{ ingredientId, amount }]
  portions: {},
})

const youtubeEmbed = computed(() => {
  const m = form.youtubeUrl.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/)
  return m ? `https://www.youtube.com/embed/${m[1]}` : null
})

function nutritionFor(memberId) {
  const totals = { kcal: 0, protein: 0, fat: 0, carbs: 0 }
  for (const row of form.portions[memberId] ?? []) {
    const ing = ingredientCache[row.ingredientId]
    if (!ing || !row.amount) continue
    const grams = ing.unit === 'pcs' ? row.amount * (ing.pieceWeightGrams ?? 0) : row.amount
    totals.kcal += (grams * ing.kcalPer100) / 100
    totals.protein += (grams * ing.proteinPer100) / 100
    totals.fat += (grams * ing.fatPer100) / 100
    totals.carbs += (grams * ing.carbsPer100) / 100
  }
  return Object.fromEntries(Object.entries(totals).map(([k, v]) => [k, Math.round(v * 10) / 10]))
}

function addIngredient(memberId, ingredient) {
  ingredientCache[ingredient.id] = ingredient
  form.portions[memberId] ??= []
  form.portions[memberId].push({ ingredientId: ingredient.id, amount: 100 })
}

function removeRow(memberId, index) {
  form.portions[memberId].splice(index, 1)
}

function copyPortion(fromId, toId) {
  form.portions[toId] = (form.portions[fromId] ?? []).map((r) => ({ ...r }))
}

async function save() {
  state.saving = true
  state.error = ''
  try {
    const body = {
      name: form.name,
      code: form.code || null,
      category: form.category,
      recipe: form.recipe || null,
      youtubeUrl: form.youtubeUrl || null,
      batchCooking: form.batchCooking,
      portions: Object.entries(form.portions)
        .filter(([, rows]) => rows.length > 0)
        .map(([memberId, rows]) => ({
          familyMemberId: Number(memberId),
          ingredients: rows.map((r) => ({ ingredientId: r.ingredientId, amount: r.amount })),
        })),
    }
    if (dishId) await api.put(`/dishes/${dishId}`, body)
    else await api.post('/dishes', body)
    router.push('/dishes')
  } catch (e) {
    state.error = e.message
  } finally {
    state.saving = false
  }
}

onMounted(async () => {
  await app.loadMembers()
  if (dishId) {
    const dish = await api.get(`/dishes/${dishId}`)
    form.name = dish.name
    form.code = dish.code ?? ''
    form.category = dish.category
    form.recipe = dish.recipe ?? ''
    form.youtubeUrl = dish.youtubeUrl ?? ''
    form.batchCooking = dish.batchCooking
    for (const portion of dish.portions) {
      for (const item of portion.ingredients) {
        ingredientCache[item.ingredientId] = {
          id: item.ingredientId,
          name: item.name,
          unit: item.unit,
          kcalPer100: item.kcalPer100,
          proteinPer100: item.proteinPer100,
          fatPer100: item.fatPer100,
          carbsPer100: item.carbsPer100,
          pieceWeightGrams: item.pieceWeightGrams,
        }
      }
      form.portions[portion.familyMemberId] = portion.ingredients.map((i) => ({
        ingredientId: i.ingredientId,
        amount: i.amount,
      }))
    }
  }
})
</script>

<template>
  <div>
    <h1>{{ dishId ? $t('dishForm.editTitle') : $t('dishForm.newTitle') }}</h1>

    <div class="card" style="margin-bottom: 16px">
      <div class="form-grid">
        <div class="field full">
          <label>{{ $t('dishForm.name') }}</label>
          <input v-model="form.name" :placeholder="$t('dishForm.namePlaceholder')" />
        </div>
        <div class="field">
          <label>{{ $t('dishForm.code') }}</label>
          <input v-model="form.code" placeholder="B01" />
        </div>
        <div class="field">
          <label>{{ $t('dishForm.category') }}</label>
          <select v-model="form.category">
            <option v-for="s in MEAL_SLOTS" :key="s" :value="s">{{ $t(`slots.${s}`) }}</option>
          </select>
        </div>
        <div class="field full">
          <label>{{ $t('dishForm.recipe') }}</label>
          <textarea v-model="form.recipe" rows="3" />
        </div>
        <div class="field">
          <label>{{ $t('dishForm.youtube') }}</label>
          <input v-model="form.youtubeUrl" placeholder="https://www.youtube.com/watch?v=..." />
        </div>
        <div class="field" style="justify-content: flex-end">
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer">
            <input v-model="form.batchCooking" type="checkbox" style="width: auto" />
            {{ $t('dishForm.batchCooking') }}
          </label>
        </div>
      </div>
      <div v-if="youtubeEmbed" style="margin-top: 12px">
        <iframe
          :src="youtubeEmbed"
          width="420"
          height="236"
          frameborder="0"
          allowfullscreen
          style="border-radius: 8px"
        />
      </div>
    </div>

    <div
      style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 16px"
    >
      <div v-for="member in app.members" :key="member.id" class="card">
        <div class="toolbar" style="margin-bottom: 10px">
          <strong>{{ $t('dishForm.portionFor', { name: member.name }) }}</strong>
          <span class="badge">
            {{ nutritionFor(member.id).kcal }} {{ $t('common.kcal') }} · {{ $t('nutrients.p') }}
            {{ nutritionFor(member.id).protein }} · {{ $t('nutrients.f') }}
            {{ nutritionFor(member.id).fat }} · {{ $t('nutrients.c') }}
            {{ nutritionFor(member.id).carbs }}
          </span>
          <span class="spacer" />
          <button
            v-for="other in app.members.filter((m) => m.id !== member.id)"
            :key="other.id"
            class="small"
            @click="copyPortion(other.id, member.id)"
          >
            {{ $t('dishForm.copyFrom', { name: other.name }) }}
          </button>
        </div>

        <table class="data">
          <thead>
            <tr>
              <th>{{ $t('dishForm.ingredient') }}</th>
              <th style="width: 140px">{{ $t('dishForm.amount') }}</th>
              <th style="width: 40px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in form.portions[member.id] ?? []" :key="index">
              <td style="font-size: 13.5px">
                {{ ingredientCache[row.ingredientId]?.name ?? '—' }}
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 6px">
                  <input
                    v-model.number="row.amount"
                    type="number"
                    min="0"
                    step="1"
                    style="width: 80px"
                  />
                  <span class="muted">{{
                    ingredientCache[row.ingredientId]
                      ? $t(`units.${ingredientCache[row.ingredientId].unit}`)
                      : ''
                  }}</span>
                </div>
              </td>
              <td><button class="small danger" @click="removeRow(member.id, index)">✕</button></td>
            </tr>
          </tbody>
        </table>
        <div style="display: flex; gap: 8px; margin-top: 10px">
          <IngredientAutocomplete
            :placeholder="$t('dishForm.addIngredient')"
            @select="(i) => addIngredient(member.id, i)"
          />
        </div>
      </div>
    </div>

    <p v-if="state.error" class="error">{{ state.error }}</p>
    <div class="actions" style="justify-content: flex-start">
      <button class="primary" :disabled="state.saving || !form.name" @click="save">
        {{ $t('common.save') }}
      </button>
      <RouterLink to="/dishes"
        ><button>{{ $t('common.cancel') }}</button></RouterLink
      >
    </div>
  </div>
</template>

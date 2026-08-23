<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api, MEAL_SLOTS, unitLabel } from '../api'
import IngredientAutocomplete from './IngredientAutocomplete.vue'
import DishPicker from './DishPicker.vue'

const props = defineProps({
  date: { type: String, required: true },
  member: { type: Object, required: true },
})
const emit = defineEmits(['close', 'changed'])

// Локальна чернетка: всі зміни тут, на сервер — одним PUT при збереженні.
const items = ref([]) // { key, slot, type: 'dish'|'product', dish?, ingredient?, amount?, nutrition }
const dirty = ref(false)
const saving = ref(false)
const error = ref('')
const confirmClose = ref(false)
const dishPickerSlot = ref(null)
// Обраний у автокомпліті продукт, який чекає на грамівку: pending[slot] = { ingredient, amount }
const pending = reactive({})
let keySeq = 0

async function load() {
  const data = await api.get(`/meal-plan?from=${props.date}&to=${props.date}`)
  items.value = data.entries
    .filter((e) => e.familyMemberId === props.member.id)
    .map((e) => ({
      key: ++keySeq,
      slot: e.slot,
      type: e.type,
      dish: e.dish,
      ingredient: e.ingredient,
      amount: e.amount,
      nutrition: e.nutrition,
    }))
  dirty.value = false
}

const bySlot = (slot) => items.value.filter((e) => e.slot === slot)

function sumOf(list) {
  const total = { kcal: 0, protein: 0, fat: 0, carbs: 0 }
  for (const e of list) {
    if (!e.nutrition) continue
    for (const key of Object.keys(total)) total[key] += e.nutrition[key]
  }
  return total
}

const dayTotal = computed(() => sumOf(items.value))

const targets = computed(() => [
  { key: 'kcal', label: 'Калорії', unit: 'ккал', target: props.member.kcalTarget },
  { key: 'protein', label: 'Білки', unit: 'г', target: props.member.proteinTarget },
  { key: 'fat', label: 'Жири', unit: 'г', target: props.member.fatTarget },
  { key: 'carbs', label: 'Вуглеводи', unit: 'г', target: props.member.carbsTarget },
])

function productNutrition(ingredient, amount) {
  const grams = ingredient.unit === 'pcs' ? amount * (ingredient.pieceWeightGrams ?? 0) : amount
  const per = (v) => Math.round(((grams * v) / 100) * 10) / 10
  return {
    kcal: per(ingredient.kcalPer100),
    protein: per(ingredient.proteinPer100),
    fat: per(ingredient.fatPer100),
    carbs: per(ingredient.carbsPer100),
  }
}

function pickProduct(slot, ingredient) {
  pending[slot] = { ingredient, amount: null }
}

function addPending(slot) {
  const p = pending[slot]
  if (!p || !p.amount || p.amount <= 0) return
  items.value.push({
    key: ++keySeq,
    slot,
    type: 'product',
    ingredient: p.ingredient,
    amount: p.amount,
    nutrition: productNutrition(p.ingredient, p.amount),
  })
  delete pending[slot]
  dirty.value = true
}

function addDish(dish) {
  const slot = dishPickerSlot.value
  dishPickerSlot.value = null
  const portion = dish.portions?.find((p) => p.familyMemberId === props.member.id)
  items.value.push({
    key: ++keySeq,
    slot,
    type: 'dish',
    dish: { id: dish.id, name: dish.name },
    nutrition: portion?.nutrition ?? null,
  })
  dirty.value = true
}

function removeItem(item) {
  items.value = items.value.filter((i) => i.key !== item.key)
  dirty.value = true
}

async function save(closeAfter = true) {
  saving.value = true
  error.value = ''
  try {
    await api.put('/meal-plan/day', {
      date: props.date,
      familyMemberId: props.member.id,
      entries: items.value.map((i) => ({
        slot: i.slot,
        dishId: i.type === 'dish' ? i.dish.id : undefined,
        ingredientId: i.type === 'product' ? i.ingredient.id : undefined,
        amount: i.type === 'product' ? i.amount : undefined,
      })),
    })
    dirty.value = false
    emit('changed')
    if (closeAfter) emit('close')
  } catch (e) {
    error.value = e.message
    confirmClose.value = false
  } finally {
    saving.value = false
  }
}

function tryClose() {
  if (!dirty.value) {
    emit('close')
    return
  }
  confirmClose.value = true
}

function discardAndClose() {
  emit('close')
}

const entryName = (e) =>
  e.type === 'dish'
    ? e.dish.name
    : `${e.ingredient.name} — ${e.amount} ${unitLabel(e.ingredient.unit)}`

onMounted(load)
</script>

<template>
  <div class="modal-backdrop" @click.self="tryClose">
    <div class="modal editor">
      <div class="editor-head">
        <h2>
          {{ member.name }} · {{ date }}
          <span v-if="dirty" class="dirty-mark" title="Є незбережені зміни">●</span>
        </h2>
        <div style="display: flex; gap: 8px">
          <button class="primary" :disabled="saving || !dirty" @click="save()">Зберегти</button>
          <button @click="tryClose">Закрити</button>
        </div>
      </div>

      <div v-if="confirmClose" class="confirm-bar">
        <span>Є незбережені зміни. Зберегти перед закриттям?</span>
        <span class="spacer" />
        <button class="primary small" :disabled="saving" @click="save()">Зберегти</button>
        <button class="small" @click="discardAndClose">Не зберігати</button>
        <button class="small" @click="confirmClose = false">Скасувати</button>
      </div>
      <p v-if="error" class="error">{{ error }}</p>

      <div class="editor-body">
        <div class="slots">
          <section v-for="slot in MEAL_SLOTS" :key="slot.value" class="slot card">
            <div class="slot-head">
              <strong>{{ slot.label }}</strong>
              <span class="muted">{{ Math.round(sumOf(bySlot(slot.value)).kcal) }} ккал</span>
            </div>

            <div v-for="item in bySlot(slot.value)" :key="item.key" class="item">
              <span class="item-name" :title="entryName(item)">{{ entryName(item) }}</span>
              <span v-if="item.nutrition" class="muted item-kcal"
                >{{ Math.round(item.nutrition.kcal) }}&nbsp;ккал</span
              >
              <button class="small danger" @click="removeItem(item)">✕</button>
            </div>

            <div v-if="pending[slot.value]" class="pending">
              <span class="item-name">{{ pending[slot.value].ingredient.name }}</span>
              <input
                v-model.number="pending[slot.value].amount"
                type="number"
                min="1"
                step="1"
                :placeholder="unitLabel(pending[slot.value].ingredient.unit)"
                style="width: 90px"
                autofocus
                @keydown.enter="addPending(slot.value)"
              />
              <span class="muted">{{ unitLabel(pending[slot.value].ingredient.unit) }}</span>
              <button class="small primary" @click="addPending(slot.value)">OK</button>
              <button class="small" @click="delete pending[slot.value]">✕</button>
            </div>

            <div v-else class="add-row">
              <IngredientAutocomplete @select="(i) => pickProduct(slot.value, i)" />
              <button class="small" @click="dishPickerSlot = slot.value">+ Страва</button>
            </div>
          </section>
        </div>

        <aside class="totals card">
          <strong>Підсумок дня</strong>
          <div v-for="t in targets" :key="t.key" class="target">
            <div class="target-line">
              <span>{{ t.label }}</span>
              <span>
                <strong :class="{ over: t.target && dayTotal[t.key] > t.target }">{{
                  Math.round(dayTotal[t.key])
                }}</strong>
                <span v-if="t.target" class="muted"> / {{ t.target }} {{ t.unit }}</span>
                <span v-else class="muted"> {{ t.unit }}</span>
              </span>
            </div>
            <div v-if="t.target" class="bar">
              <div
                class="bar-fill"
                :class="{ over: dayTotal[t.key] > t.target }"
                :style="{ width: Math.min((dayTotal[t.key] / t.target) * 100, 100) + '%' }"
              />
            </div>
          </div>
          <p class="muted hint">
            Зміни зберігаються кнопкою «Зберегти» — одним запитом за весь день.
          </p>
        </aside>
      </div>
    </div>
  </div>

  <DishPicker
    v-if="dishPickerSlot"
    :default-category="dishPickerSlot"
    @select="addDish"
    @close="dishPickerSlot = null"
  />
</template>

<style scoped>
.editor {
  max-width: 1080px;
}
.editor-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.editor-head h2 {
  margin: 0;
}
.dirty-mark {
  color: var(--primary);
  font-size: 14px;
  vertical-align: middle;
}
.confirm-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fdf6e3;
  border: 1px solid #e8d9a0;
  border-radius: 8px;
  padding: 8px 12px;
  margin-top: 12px;
  font-size: 13.5px;
}
.editor-body {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 16px;
  margin-top: 16px;
}
.slots {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.slot-head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.item,
.pending {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 0;
  border-bottom: 1px solid var(--border);
  font-size: 13.5px;
}
.item-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.item-kcal {
  min-width: 64px;
  text-align: right;
  white-space: nowrap;
}
.item button {
  visibility: hidden;
}
.item:hover button {
  visibility: visible;
}
.add-row {
  display: flex;
  gap: 8px;
  margin-top: 8px;
  align-items: center;
}
.totals {
  align-self: start;
  position: sticky;
  top: 0;
}
.target {
  margin-top: 12px;
}
.target-line {
  display: flex;
  justify-content: space-between;
  font-size: 13.5px;
  margin-bottom: 4px;
}
.target .over {
  color: var(--danger);
}
.bar {
  height: 6px;
  background: var(--border);
  border-radius: 4px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  background: var(--primary);
  border-radius: 4px;
  transition: width 0.2s;
}
.bar-fill.over {
  background: var(--danger);
}
.hint {
  font-size: 12.5px;
  margin-top: 16px;
}
@media (max-width: 900px) {
  .editor-body {
    grid-template-columns: 1fr;
  }
}
</style>

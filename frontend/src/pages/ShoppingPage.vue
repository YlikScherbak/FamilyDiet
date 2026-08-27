<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { api } from '../api'

const { t } = useI18n()
const route = useRoute()

// --- Період: за замовчуванням наступний тиждень (пн–нд), дати довільні ----------

const pad = (n) => String(n).padStart(2, '0')
const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const shiftDays = (iso, days) => {
  const d = new Date(`${iso}T00:00:00`)
  d.setDate(d.getDate() + days)
  return fmt(d)
}

function nextWeekRange() {
  const d = new Date()
  d.setHours(0, 0, 0, 0)
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7) + 7) // понеділок наступного тижня
  return { from: fmt(d), to: shiftDays(fmt(d), 6) }
}

// Дати можна задати в URL (?from=&to=) — зручно для закладки чи друку
const isIso = (v) => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)
const range = reactive(
  isIso(route.query.from) && isIso(route.query.to)
    ? { from: route.query.from, to: route.query.to }
    : nextWeekRange(),
)
const data = ref({ groups: [], entries: 0 })
const loading = ref(false)
const error = ref('')

// --- Позначки «куплено» / «є вдома» — зберігаються в БД на конкретний період -------

const marks = reactive({ bought: [], home: [] })
let marksLoaded = false
const settingKey = computed(() => `shopping_${range.from}_${range.to}`)

async function load() {
  loading.value = true
  error.value = ''
  marksLoaded = false
  try {
    const [list, saved] = await Promise.all([
      api.get(`/shopping-list?from=${range.from}&to=${range.to}`),
      api.get(`/settings/${settingKey.value}`).catch(() => ({})),
    ])
    data.value = list
    marks.bought = Array.isArray(saved.bought) ? saved.bought : []
    marks.home = Array.isArray(saved.home) ? saved.home : []
  } catch (e) {
    error.value = e.message
    data.value = { groups: [], entries: 0 }
  } finally {
    loading.value = false
    marksLoaded = true
  }
}

let saveTimer = null
function persistMarks() {
  if (!marksLoaded) return
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    api
      .put(`/settings/${settingKey.value}`, { bought: marks.bought, home: marks.home })
      .catch(() => {})
  }, 300)
}

const toggle = (list, id) => {
  const i = list.indexOf(id)
  if (i === -1) list.push(id)
  else list.splice(i, 1)
  persistMarks()
}
const isBought = (id) => marks.bought.includes(id)
const isHome = (id) => marks.home.includes(id)

// --- Подання -------------------------------------------------------------------------

const groups = computed(() =>
  data.value.groups
    .map((g) => ({ ...g, items: g.items.filter((i) => !isHome(i.ingredientId)) }))
    .filter((g) => g.items.length > 0),
)
const homeItems = computed(() =>
  data.value.groups.flatMap((g) => g.items).filter((i) => isHome(i.ingredientId)),
)
const allItems = computed(() => data.value.groups.flatMap((g) => g.items))
const counts = computed(() => {
  const toBuy = allItems.value.filter((i) => !isHome(i.ingredientId))
  return {
    total: toBuy.length,
    bought: toBuy.filter((i) => isBought(i.ingredientId)).length,
    home: homeItems.value.length,
  }
})

function formatAmount(amount, unit) {
  const trim = (n) => String(Math.round(n * 100) / 100)
  if (unit === 'g' && amount >= 1000) return `${trim(amount / 1000)} ${t('units.kg')}`
  if (unit === 'ml' && amount >= 1000) return `${trim(amount / 1000)} ${t('units.l')}`
  return `${trim(amount)} ${t(`units.${unit}`)}`
}

function setNextWeek() {
  Object.assign(range, nextWeekRange())
}
function shiftWeek(dir) {
  range.from = shiftDays(range.from, 7 * dir)
  range.to = shiftDays(range.to, 7 * dir)
}

const printList = () => window.print()

watch(() => [range.from, range.to], load)
onMounted(load)
</script>

<template>
  <div class="shopping">
    <h1>{{ $t('shopping.title') }}</h1>

    <div class="toolbar no-print">
      <button @click="shiftWeek(-1)">‹</button>
      <input v-model="range.from" type="date" />
      <span class="muted">—</span>
      <input v-model="range.to" type="date" />
      <button @click="shiftWeek(1)">›</button>
      <button class="small" @click="setNextWeek">{{ $t('shopping.nextWeek') }}</button>
      <span class="spacer" />
      <span class="muted">
        {{ $t('shopping.progress', { bought: counts.bought, total: counts.total }) }}
      </span>
      <button @click="printList">{{ $t('shopping.print') }}</button>
    </div>

    <p class="print-only muted">{{ range.from }} — {{ range.to }}</p>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-else-if="!loading && allItems.length === 0" class="muted">{{ $t('shopping.empty') }}</p>

    <div class="groups">
      <section v-for="g in groups" :key="g.category" class="card group">
        <h2>
          {{ $t(`categories.${g.category}`) }}
          <span class="muted count">{{ g.items.length }}</span>
        </h2>
        <ul>
          <li
            v-for="item in g.items"
            :key="item.ingredientId"
            :class="{ bought: isBought(item.ingredientId) }"
          >
            <label class="item">
              <input
                type="checkbox"
                :checked="isBought(item.ingredientId)"
                @change="toggle(marks.bought, item.ingredientId)"
              />
              <span class="name">{{ item.name }}</span>
              <span class="amount">{{ formatAmount(item.amount, item.unit) }}</span>
              <span class="muted uses">{{ $t('shopping.uses', { n: item.uses }) }}</span>
            </label>
            <button
              class="small home no-print"
              :title="$t('shopping.markHome')"
              @click="toggle(marks.home, item.ingredientId)"
            >
              🏠
            </button>
          </li>
        </ul>
      </section>
    </div>

    <section v-if="homeItems.length" class="card group home-group no-print">
      <h2>
        🏠 {{ $t('shopping.atHome') }}
        <span class="muted count">{{ homeItems.length }}</span>
      </h2>
      <ul>
        <li v-for="item in homeItems" :key="item.ingredientId" class="muted">
          <span class="item">
            <span class="name">{{ item.name }}</span>
            <span class="amount">{{ formatAmount(item.amount, item.unit) }}</span>
          </span>
          <button
            class="small"
            :title="$t('shopping.backToList')"
            @click="toggle(marks.home, item.ingredientId)"
          >
            ↩
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.shopping {
  max-width: 900px;
}
.groups {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}
.group h2 {
  font-size: 15px;
  margin: 0 0 8px;
  display: flex;
  justify-content: space-between;
}
.count {
  font-weight: 400;
  font-size: 13px;
}
ul {
  list-style: none;
  margin: 0;
  padding: 0;
}
li {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 0;
  border-bottom: 1px solid var(--border);
}
li:last-child {
  border-bottom: none;
}
.item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  min-width: 0;
}
.item input {
  width: 18px;
  height: 18px;
  flex: none;
}
.name {
  flex: 1;
  min-width: 0;
}
.amount {
  font-weight: 600;
  white-space: nowrap;
}
.uses {
  font-size: 12px;
  white-space: nowrap;
}
.bought .name,
.bought .amount {
  text-decoration: line-through;
  color: var(--muted);
  font-weight: 400;
}
.home {
  border: none;
  padding: 2px 6px;
}
.home-group {
  opacity: 0.85;
}
.print-only {
  display: none;
}
@media (max-width: 720px) {
  .groups {
    grid-template-columns: 1fr;
  }
  .uses {
    display: none;
  }
  li {
    padding: 10px 0;
  }
}
@media print {
  .print-only {
    display: block;
  }
  .groups {
    grid-template-columns: repeat(2, 1fr);
  }
  .card {
    box-shadow: none;
    border: 1px solid #ddd;
    break-inside: avoid;
  }
  .item input {
    appearance: none;
    width: 14px;
    height: 14px;
    border: 1px solid #555;
    border-radius: 3px;
  }
}
</style>

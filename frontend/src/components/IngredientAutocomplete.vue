<script setup>
import { computed, onMounted, ref } from 'vue'
import { unitLabel } from '../api'
import { useIngredientsStore } from '../stores/ingredients'

const props = defineProps({
  placeholder: { type: String, default: 'Пошук продукту...' },
})
const emit = defineEmits(['select'])

const store = useIngredientsStore()
const query = ref('')
const open = ref(false)

const results = computed(() =>
  query.value.trim().length >= 2 ? store.search(query.value, { limit: 20 }) : []
)

function pick(item) {
  emit('select', item)
  query.value = ''
  open.value = false
}

function closeSoon() {
  setTimeout(() => (open.value = false), 150)
}

onMounted(() => store.loadAll())
</script>

<template>
  <div class="autocomplete">
    <input
      v-model="query"
      :placeholder="placeholder"
      @input="open = true"
      @focus="open = true"
      @blur="closeSoon"
      @keydown.escape="open = false"
    />
    <div v-if="open && query.trim().length >= 2" class="dropdown">
      <div v-for="item in results" :key="item.id" class="option" @mousedown.prevent="pick(item)">
        <span>
          {{ item.name }}
          <span v-if="item.nameEn" class="muted en">{{ item.nameEn }}</span>
        </span>
        <span class="muted meta">{{ item.kcalPer100 }} ккал/100</span>
      </div>
      <div v-if="results.length === 0" class="option muted">Нічого не знайдено</div>
    </div>
  </div>
</template>

<style scoped>
.autocomplete { position: relative; flex: 1; min-width: 220px; }
.autocomplete input { width: 100%; }
.dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  max-height: 280px;
  overflow: auto;
  z-index: 50;
  box-shadow: 0 8px 24px rgba(20, 24, 30, 0.12);
}
.option {
  padding: 8px 12px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13.5px;
}
.option:hover { background: var(--primary-soft); }
.option .meta { white-space: nowrap; }
.option .en { display: block; font-size: 11.5px; }
</style>

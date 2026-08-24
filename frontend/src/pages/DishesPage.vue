<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, MEAL_SLOTS } from '../api'
import { useAppStore } from '../stores/app'

const { t } = useI18n()

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
  if (!confirm(t('dishes.confirmDelete', { name: item.name }))) return
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
    <h1>{{ $t('dishes.title') }}</h1>
    <div class="toolbar">
      <input
        v-model="search"
        :placeholder="$t('dishes.searchPlaceholder')"
        style="width: 280px"
        @input="load"
      />
      <select v-model="category" @change="load">
        <option value="">{{ $t('common.allCategories') }}</option>
        <option v-for="s in MEAL_SLOTS" :key="s" :value="s">{{ $t(`slots.${s}`) }}</option>
      </select>
      <span class="muted">{{ $t('common.count', { n: items.length }) }}</span>
      <span class="spacer" />
      <RouterLink to="/dishes/new"
        ><button class="primary">{{ $t('dishes.add') }}</button></RouterLink
      >
    </div>

    <table class="data">
      <thead>
        <tr>
          <th>{{ $t('dishes.code') }}</th>
          <th>{{ $t('dishes.name') }}</th>
          <th>{{ $t('dishes.category') }}</th>
          <th v-for="m in app.members" :key="m.id">{{ $t('dishes.kcalFor', { name: m.name }) }}</th>
          <th>{{ $t('dishes.batch') }}</th>
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
            <span class="badge">{{ $t(`slots.${item.category}`) }}</span>
          </td>
          <td v-for="m in app.members" :key="m.id">{{ kcalOf(item, m.id) }}</td>
          <td>{{ item.batchCooking ? '✔' : '' }}</td>
          <td style="white-space: nowrap">
            <RouterLink :to="`/dishes/${item.id}`"
              ><button class="small">{{ $t('common.edit') }}</button></RouterLink
            >
            <button class="small danger" @click="remove(item)">✕</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

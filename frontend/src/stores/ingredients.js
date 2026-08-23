import { defineStore } from 'pinia'
import { api } from '../api'

// Клієнтський довідник інгредієнтів: завантажується один раз,
// пошук — локальний, по словах у будь-якому порядку, по name і nameEn.
export const useIngredientsStore = defineStore('ingredients', {
  state: () => ({
    items: [],
    loaded: false,
    _loading: null,
  }),
  actions: {
    async loadAll() {
      if (this.loaded) return
      this._loading ??= api.get('/ingredients/all').then((items) => {
        this.items = items.map(withHaystack)
        this.loaded = true
        this._loading = null
      })
      await this._loading
    },

    search(query, { category = '', limit = 20 } = {}) {
      const words = query.trim().toLowerCase().split(/\s+/).filter(Boolean)
      let result = this.items
      if (category) result = result.filter((i) => i.category === category)
      if (words.length) {
        result = result.filter((i) => words.every((w) => i._haystack.includes(w)))
        const first = words[0]
        result = [...result].sort((a, b) => rank(a, first) - rank(b, first) || a.name.length - b.name.length)
      }
      return result.slice(0, limit)
    },

    upsert(item) {
      const prepared = withHaystack(item)
      const index = this.items.findIndex((i) => i.id === item.id)
      if (index >= 0) this.items[index] = prepared
      else this.items.push(prepared)
    },

    remove(id) {
      this.items = this.items.filter((i) => i.id !== id)
    },
  },
})

function withHaystack(item) {
  return { ...item, _haystack: `${item.name} ${item.nameEn ?? ''}`.toLowerCase() }
}

function rank(item, firstWord) {
  return item._haystack.startsWith(firstWord) ? 0 : 1
}

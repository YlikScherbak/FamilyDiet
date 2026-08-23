import { defineStore } from 'pinia'
import { api } from '../api'

export const useAppStore = defineStore('app', {
  state: () => ({
    members: [],
    loaded: false,
  }),
  actions: {
    async loadMembers() {
      if (this.loaded) return
      this.members = await api.get('/family-members')
      this.loaded = true
    },
    memberName(id) {
      return this.members.find((m) => m.id === id)?.name ?? `#${id}`
    },
  },
})

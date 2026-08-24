async function http(method, path, body) {
  const res = await fetch(`/api${path}`, {
    method,
    headers: body !== undefined ? { 'Content-Type': 'application/json' } : {},
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  if (res.status === 204) return null
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    const message =
      data?.error ||
      (data?.errors ? Object.values(data.errors).join('; ') : `Помилка ${res.status}`)
    throw new Error(message)
  }
  return data
}

export const api = {
  get: (path) => http('GET', path),
  post: (path, body) => http('POST', path, body),
  put: (path, body) => http('PUT', path, body),
  del: (path) => http('DELETE', path),
}

// Значення словників; людські назви — у перекладах: slots.*, categories.*, units.*, healthTypes.*
export const MEAL_SLOTS = ['breakfast', 'lunch', 'dinner', 'snack', 'extra_snack']

export const INGREDIENT_CATEGORIES = [
  'meat_fish',
  'eggs',
  'dairy',
  'grains_bread',
  'vegetables_greens',
  'fruits_berries',
  'nuts_seeds_dried',
  'legumes',
  'oils_sauces',
  'other',
]

export const UNITS = ['g', 'ml', 'pcs']

export const HEALTH_EVENT_TYPES = [
  { value: 'pressure', icon: '🩺', color: '#2f6b4f', structured: true },
  { value: 'weight', icon: '⚖️', color: '#0f766e', kg: true },
  { value: 'headache', icon: '🤕', color: '#b45309', severity: true },
  { value: 'migraine', icon: '🌩️', color: '#b91c1c', severity: true },
  { value: 'medication', icon: '💊', color: '#1d4ed8' },
  { value: 'symptom', icon: '🤒', color: '#a16207', severity: true },
  { value: 'note', icon: '📝', color: '#52525b' },
  { value: 'custom', icon: '📌', color: '#6d28d9', custom: true },
]

export const healthType = (v) => HEALTH_EVENT_TYPES.find((t) => t.value === v)

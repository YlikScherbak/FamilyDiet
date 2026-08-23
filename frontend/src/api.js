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

export const MEAL_SLOTS = [
  { value: 'breakfast', label: 'Сніданок' },
  { value: 'lunch', label: 'Обід' },
  { value: 'dinner', label: 'Вечеря' },
  { value: 'snack', label: 'Перекус' },
  { value: 'extra_snack', label: 'Дод. перекус' },
]

export const INGREDIENT_CATEGORIES = [
  { value: 'meat_fish', label: "М'ясо та риба" },
  { value: 'eggs', label: 'Яйця' },
  { value: 'dairy', label: 'Молочні продукти' },
  { value: 'grains_bread', label: 'Крупи та хліб' },
  { value: 'vegetables_greens', label: 'Овочі та зелень' },
  { value: 'fruits_berries', label: 'Фрукти та ягоди' },
  { value: 'nuts_seeds_dried', label: 'Горіхи, насіння, сухофрукти' },
  { value: 'legumes', label: 'Бобові' },
  { value: 'oils_sauces', label: 'Олії та соуси' },
  { value: 'other', label: 'Інше' },
]

export const UNITS = [
  { value: 'g', label: 'г' },
  { value: 'ml', label: 'мл' },
  { value: 'pcs', label: 'шт' },
]

export const HEALTH_EVENT_TYPES = [
  { value: 'pressure', label: 'Тиск і пульс', icon: '🩺', color: '#2f6b4f', structured: true },
  { value: 'headache', label: 'Головний біль', icon: '🤕', color: '#b45309', severity: true },
  { value: 'migraine', label: 'Мігрень', icon: '🌩️', color: '#b91c1c', severity: true },
  { value: 'medication', label: 'Ліки', icon: '💊', color: '#1d4ed8' },
  { value: 'symptom', label: 'Симптом', icon: '🤒', color: '#a16207', severity: true },
  { value: 'note', label: 'Нотатка', icon: '📝', color: '#52525b' },
  { value: 'custom', label: 'Інше (своя назва)', icon: '📌', color: '#6d28d9', custom: true },
]

export const healthType = (v) => HEALTH_EVENT_TYPES.find((t) => t.value === v)

export const slotLabel = (v) => MEAL_SLOTS.find((s) => s.value === v)?.label ?? v
export const categoryLabel = (v) => INGREDIENT_CATEGORIES.find((c) => c.value === v)?.label ?? v
export const unitLabel = (v) => UNITS.find((u) => u.value === v)?.label ?? v

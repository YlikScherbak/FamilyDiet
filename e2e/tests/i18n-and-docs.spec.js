import { test, expect } from '@playwright/test'
import { open, t } from '../helpers.js'

// Двомовність наскрізь: інтерфейс перемикається селектом, бекенд відповідає мовою Accept-Language; Swagger живий.
test('перемикач мови перекладає UI, API відповідає мовою заголовка, Swagger UI доступний', async ({
  page,
  request,
}) => {
  await open(page, '/')
  const nav = page.locator('nav')
  await expect(nav.getByRole('link', { name: t('nav.health') })).toBeVisible()

  await page.locator('select.lang-switch').selectOption('en')
  await expect(nav.getByRole('link', { name: t('nav.health', {}, 'en') })).toBeVisible()
  await expect(page.getByRole('heading', { name: t('menu.title', {}, 'en') })).toBeVisible()

  // Вибір мови переживає перезавантаження (localStorage)
  await page.reload()
  await expect(nav.getByRole('link', { name: t('nav.shopping', {}, 'en') })).toBeVisible()

  // Бекенд: та сама помилка двома мовами
  const uk = await request.get('/api/shopping-list', { headers: { 'Accept-Language': 'uk' } })
  const en = await request.get('/api/shopping-list', { headers: { 'Accept-Language': 'en' } })
  expect(uk.status()).toBe(400)
  expect(en.status()).toBe(400)
  expect((await uk.json()).error).toMatch(/обов'язкові/)
  expect((await en.json()).error).toMatch(/required/)

  // OpenAPI: JSON описує всі теги, UI рендериться
  const doc = await request.get('/api/doc.json')
  expect(doc.ok()).toBeTruthy()
  const spec = await doc.json()
  expect(spec.info.title).toBe('FamilyDiet API')
  expect(Object.keys(spec.paths)).toEqual(expect.arrayContaining(['/api/dishes', '/api/health-events', '/api/shopping-list']))

  await page.goto('/api/doc')
  await expect(page.locator('.swagger-ui .info .title')).toContainText('FamilyDiet API')
  await expect(page.locator('.opblock-tag', { hasText: 'Meal plan' })).toBeVisible()

  // Повертаємо українську, щоб інші сценарії бачили очікувані тексти
  await open(page, '/')
  await page.locator('select.lang-switch').selectOption('uk')
})

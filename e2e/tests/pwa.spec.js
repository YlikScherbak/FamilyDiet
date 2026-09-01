import { test, expect } from '@playwright/test'
import { open } from '../helpers.js'

// PWA: валідний маніфест, service worker реєструється й активується, довідник кешується для офлайну.
test('маніфест валідний, service worker активний, довідник інгредієнтів у кеші', async ({
  page,
  request,
}) => {
  const res = await request.get('/manifest.webmanifest')
  expect(res.ok()).toBeTruthy()
  const manifest = await res.json()
  expect(manifest.name).toBe('FamilyDiet')
  expect(manifest.display).toBe('standalone')
  expect(manifest.icons.map((i) => i.sizes)).toEqual(
    expect.arrayContaining(['192x192', '512x512']),
  )

  const sw = await request.get('/sw.js')
  expect(sw.ok()).toBeTruthy()
  expect(sw.headers()['cache-control']).toContain('no-cache')

  await open(page, '/')
  // ready → воркер уже active, але state може бути ще 'activating' — чекаємо фінального стану
  await expect
    .poll(() =>
      page.evaluate(async () => {
        const reg = await navigator.serviceWorker.ready
        return reg.active?.state
      }),
    )
    .toBe('activated')

  // Runtime-кеш: після звернення автокомпліта довідник лежить у Cache Storage
  await page.evaluate(() => fetch('/api/ingredients/all').then((r) => r.json()))
  await expect
    .poll(async () => page.evaluate(() => caches.has('ingredients-all')), { timeout: 10_000 })
    .toBe(true)
})

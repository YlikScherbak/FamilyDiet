import { defineConfig, devices } from '@playwright/test'

// Стек піднімається окремо (make e2e-up або docker compose -f e2e/docker-compose.yml up);
// сюди лише адреса. У CI — той самий production-образ, що деплоїться на Fly.
const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8090'

export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false, // сценарії пишуть у спільну БД — послідовно, без гонок
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL,
    locale: 'uk-UA',
    timezoneId: 'Europe/Kyiv',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'desktop',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
      testIgnore: /mobile/,
    },
    {
      name: 'mobile',
      use: { ...devices['Pixel 7'] },
      testMatch: /mobile/,
    },
  ],
})

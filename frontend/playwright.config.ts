import { defineConfig } from '@playwright/test'
import { fileURLToPath } from 'node:url'
export const backendPath = fileURLToPath(new URL('../backend', import.meta.url))
export const testEnv = {
  ...process.env,
  APP_ENV: 'local',
  APP_DEBUG: 'true',
  APP_KEY: 'base64:BwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwc=',
  APP_URL: 'http://localhost:15173',
  FRONTEND_URL: 'http://localhost:15173',
  QSL_STORAGE_ROOT: fileURLToPath(new URL('../.local/browser-files', import.meta.url)),
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: fileURLToPath(new URL('../.local/qsl-browser-test.sqlite', import.meta.url)),
  DB_URL: '',
  SESSION_DRIVER: 'database',
  SESSION_ENCRYPT: 'true',
  SESSION_COOKIE: 'qsl_browser_test_session',
  SESSION_DOMAIN: 'null',
  CACHE_STORE: 'database',
  QUEUE_CONNECTION: 'database',
  MAIL_MAILER: 'log',
  SANCTUM_STATEFUL_DOMAINS: 'localhost:15173',
}
export default defineConfig({
  expect: { timeout: 10000 },
  testDir: './tests',
  workers: 1,
  timeout: 90000,
  retries: 0,
  globalSetup: './tests/setup.ts',
  use: {
    actionTimeout: 15000,
    baseURL: 'http://localhost:15173',
    browserName: 'chromium',
    launchOptions: {
      executablePath: process.env.CHROME_BINARY || '/usr/bin/google-chrome',
      args: ['--no-sandbox'],
    },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  webServer: [
    {
      command: 'php artisan serve --host=127.0.0.1 --port=18000',
      cwd: backendPath,
      env: testEnv,
      url: 'http://127.0.0.1:18000/up',
      reuseExistingServer: false,
    },
    {
      command: 'npm run dev -- --port 15173',
      env: { VITE_BACKEND_URL: 'http://127.0.0.1:18000' },
      url: 'http://localhost:15173',
      reuseExistingServer: false,
    },
  ],
})

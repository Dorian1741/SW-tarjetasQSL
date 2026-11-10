import { mkdirSync, writeFileSync, existsSync } from 'node:fs'
import { dirname } from 'node:path'
import { execFileSync } from 'node:child_process'
import { backendPath, testEnv } from '../playwright.config'
export default async function setup() {
  mkdirSync(dirname(testEnv.DB_DATABASE), { recursive: true })
  if (!existsSync(testEnv.DB_DATABASE)) writeFileSync(testEnv.DB_DATABASE, '')
  execFileSync('php', ['artisan', 'migrate:fresh', '--force', '--no-interaction'], {
    cwd: backendPath,
    env: testEnv,
    stdio: 'pipe',
  })
  execFileSync(
    'php',
    ['artisan', 'db:seed', '--class=DemoScenarioSeeder', '--force', '--no-interaction'],
    { cwd: backendPath, env: testEnv, stdio: 'pipe' },
  )
}

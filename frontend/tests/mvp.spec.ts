import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { execFileSync } from 'node:child_process'
import { backendPath, testEnv } from '../playwright.config'
function credential(email: string, recovery = false) {
  const script = `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $user=App\\Models\\User::where('email',$argv[1])->firstOrFail(); echo ${recovery ? '$user->recoveryCodes()[0]' : '(new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(Laravel\\Fortify\\Fortify::currentEncrypter()->decrypt($user->two_factor_secret))'};`
  return execFileSync('php', ['-r', script, email], {
    cwd: backendPath,
    env: testEnv,
    encoding: 'utf8',
  }).trim()
}
async function login(page: Page, email: string) {
  await page.goto('/account')
  await page.getByLabel('Correo electrónico', { exact: true }).fill(email)
  await page.getByLabel('Contraseña', { exact: true }).fill('DemoPassword123!')
  await page.getByRole('button', { name: 'Ingresar', exact: true }).last().click()
  await expect(page.getByRole('heading', { name: /Hola,|Segundo factor/ })).toBeVisible()
  if (await page.getByRole('heading', { name: 'Segundo factor', exact: true }).isVisible()) {
    await page.getByText('Usar código de recuperación', { exact: true }).click()
    await page.getByLabel('Código de recuperación', { exact: true }).fill(credential(email, true))
    await page.getByRole('button', { name: 'Recuperar acceso', exact: true }).last().click()
  } else {
    const setup = page
      .locator('form')
      .filter({ has: page.getByRole('button', { name: 'Configurar segundo factor', exact: true }) })
    await setup.getByLabel('Contraseña actual', { exact: true }).fill('DemoPassword123!')
    await setup.getByRole('button').click()
    await expect(page.getByAltText('Código QR para configurar el autenticador')).toBeVisible()
    await page.getByLabel('Código de verificación', { exact: true }).fill(credential(email))
    await page.getByRole('button', { name: 'Activar segundo factor', exact: true }).click()
  }
  await page.getByRole('link', { name: 'Abrir mi estación →' }).click()
  await expect(page.getByRole('heading', { name: 'Tu actividad en el aire' })).toBeVisible()
}
async function registerQso(page: Page, other: string) {
  await page.goto('/app/qsos/new')
  await page.getByRole('combobox', { name: 'Mi indicativo', exact: true }).selectOption({
    label: other === 'TEST0FULLB' ? 'TEST0FULLA · principal' : 'TEST0FULLB · principal',
  })
  await page.getByLabel('Indicativo de la otra estación', { exact: true }).fill(other)
  await page.getByLabel('Inicio: fecha y hora UTC', { exact: true }).fill('2026-11-08T14:00:00')
  await page.getByLabel('Frecuencia en kHz', { exact: true }).fill('14074.125')
  await page.getByRole('button', { name: 'Guardar contacto', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Detalle del contacto', exact: true }),
  ).toBeVisible()
}
test('dos estaciones completan QSO, confirmación bilateral, tarjeta y verificación pública', async ({
  browser,
}) => {
  const ca = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const cb = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const a = await ca.newPage()
  const b = await cb.newPage()
  try {
    await login(a, 'scenario-a@example.test')
    await login(b, 'scenario-b@example.test')
    await registerQso(a, 'TEST0FULLB')
    await registerQso(b, 'TEST0FULLA')
    await a.goto('/app/matches')
    await a.getByRole('button', { name: 'Confirmo que ocurrió', exact: true }).click()
    await expect(a.getByRole('button', { name: 'Ya confirmaste', exact: true })).toBeVisible()
    await b.goto('/app/matches')
    await b.getByRole('button', { name: 'Confirmo que ocurrió', exact: true }).click()
    await b.getByRole('combobox', { name: 'Mostrar', exact: true }).selectOption('confirmed')
    const match = b.locator('article').filter({ hasText: '14:00:00' })
    await expect(match).toHaveCount(1)
    await match.getByRole('button', { name: 'Emitir tarjeta QSL', exact: true }).click()
    await expect(b.getByRole('heading', { name: 'Detalle de tarjeta', exact: true })).toBeVisible()
    const download = b.getByRole('link', { name: 'Descargar PNG', exact: true })
    await expect(download).toBeVisible()
    const response = await b.request.get((await download.getAttribute('href'))!)
    expect(response.status()).toBe(200)
    expect((await response.body()).subarray(0, 8).toString('hex')).toBe('89504e470d0a1a0a')
    const publicUrl = await b
      .getByRole('link', { name: 'Verificación pública', exact: true })
      .getAttribute('href')
    const publicPage = await browser.newPage()
    await publicPage.goto('http://localhost:15173' + publicUrl!)
    await expect(
      publicPage.getByRole('heading', { name: 'Verificación de tarjeta QSL' }),
    ).toBeVisible()
    await expect(publicPage.getByText('Confirmado', { exact: true })).toBeVisible()
    await publicPage.close()
    await a.goto('/app')
    await expect(a.getByRole('heading', { name: 'Tu actividad en el aire' })).toBeVisible()
    expect((await new AxeBuilder({ page: a }).analyze()).violations).toEqual([])
    await a.screenshot({ path: 'test-results/dashboard-desktop.png', fullPage: true })
  } finally {
    await ca.close().catch(() => {})
    await cb.close().catch(() => {})
  }
})
test('landing y cuenta son accesibles y responsive en móvil', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.getByRole('link', { name: 'Ingresar o crear cuenta' }).click()
  await expect(page.getByRole('heading', { name: 'Tu cuenta QSL Nexus' })).toBeVisible()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.screenshot({ path: 'test-results/account-mobile.png', fullPage: true })
})
test('administrador revisa un indicativo y la navegación privada funciona en móvil', async ({
  browser,
}) => {
  const stationContext = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const adminContext = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const station = await stationContext.newPage()
  const admin = await adminContext.newPage()
  try {
    await login(station, 'scenario-a@example.test')
    await station.goto('/account')
    await station.getByLabel('Indicativo', { exact: true }).fill('TEST0REVIEW')
    await station.getByRole('button', { name: 'Reclamar indicativo', exact: true }).click()
    await expect(station.getByText('TEST0REVIEW', { exact: true })).toBeVisible()
    await login(admin, 'scenario-admin@example.test')
    await admin.goto('/app/admin')
    const row = admin.getByRole('row').filter({ hasText: 'TEST0REVIEW' })
    await expect(row).toBeVisible()
    await admin
      .getByLabel('Motivo y referencia de evidencia (10–2000 caracteres)', { exact: true })
      .fill('Licencia ficticia revisada para prueba de navegador.')
    await row.getByRole('button', { name: 'Aprobar', exact: true }).click()
    await expect(admin.getByText('Revisión guardada.', { exact: true })).toBeVisible()
    await admin.getByRole('combobox', { name: 'Estado', exact: true }).selectOption('verified')
    await expect(row).toBeVisible()
    await admin.setViewportSize({ width: 390, height: 844 })
    await admin.getByRole('button', { name: 'Abrir menú', exact: true }).click()
    await admin.getByRole('link', { name: 'Dashboard', exact: true }).click()
    await expect(admin.getByRole('heading', { name: 'Tu actividad en el aire' })).toBeVisible()
    expect(
      await admin.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true)
    expect((await new AxeBuilder({ page: admin }).analyze()).violations).toEqual([])
    await admin.screenshot({ path: 'test-results/dashboard-mobile.png', fullPage: true })
  } finally {
    await stationContext.close().catch(() => {})
    await adminContext.close().catch(() => {})
  }
})

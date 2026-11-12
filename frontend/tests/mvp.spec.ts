import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { execFileSync } from 'node:child_process'
import { createHmac } from 'node:crypto'
import { backendPath, testEnv } from '../playwright.config'
function credential(email: string, recovery = false) {
  const script = `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $user=App\\Models\\User::where('email',$argv[1])->firstOrFail(); echo ${recovery ? '$user->recoveryCodes()[0]' : '(new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(Laravel\\Fortify\\Fortify::currentEncrypter()->decrypt($user->two_factor_secret))'};`
  return execFileSync('php', ['-r', script, email], {
    cwd: backendPath,
    env: testEnv,
    encoding: 'utf8',
  }).trim()
}
function authenticatorCode(secret: string) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
  const bits = [...secret]
    .map((character) => alphabet.indexOf(character).toString(2).padStart(5, '0'))
    .join('')
  const key = Buffer.from(bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)))
  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)))
  const digest = createHmac('sha1', key).update(counter).digest()
  const offset = digest[digest.length - 1] & 15
  return ((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).toString().padStart(6, '0')
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
test('muro público y cuenta son accesibles y responsive en móvil', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'La comunidad, al aire.' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Publicaciones de la comunidad' })).toBeVisible()
  await expect(page.getByText(/DEMOSTRACIÓN · Bienvenidos al muro/)).toBeVisible()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.getByRole('link', { name: 'Ingresar o crear cuenta' }).click()
  await expect(page.getByRole('heading', { name: 'Tu cuenta QSL Nexus' })).toBeVisible()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.screenshot({ path: 'test-results/account-mobile.png', fullPage: true })
})

test('visitantes exploran el muro, buscan estaciones y solo inician sesión al participar', async ({
  page,
}) => {
  await page.goto('/')
  await expect(page).toHaveURL('http://localhost:15173/')
  await expect(page.getByRole('heading', { name: 'Publicaciones de la comunidad' })).toBeVisible()
  await page.screenshot({ path: 'test-results/community-desktop.png', fullPage: true })
  await page.getByRole('link', { name: 'Ingresar para publicar', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Tu cuenta QSL Nexus' })).toBeVisible()
  await page.goto('/')
  await page.getByLabel('Buscar por indicativo', { exact: true }).fill('test0fullb')
  await page.getByRole('button', { name: 'Buscar', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Operadores de la comunidad' })).toBeVisible()
  await expect(page.getByRole('link', { name: /TEST0FULLB/ })).toBeVisible()
  await expect(page.getByRole('link', { name: /TEST0FULLA/ })).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Consultar directorios', exact: true }),
  ).toBeDisabled()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.getByRole('link', { name: /TEST0FULLB/ }).click()
  await expect(page.getByRole('heading', { name: 'TEST0FULLB', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Seguir', exact: true })).toHaveCount(0)
  await page.getByRole('link', { name: 'Inicia sesión para seguir', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Tu cuenta QSL Nexus' })).toBeVisible()
  await page.goto('/app/qsos/new')
  await expect(page).toHaveURL('http://localhost:15173/account')
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
    await expect(station.getByRole('link', { name: 'Administración', exact: true })).toHaveCount(0)
    await station.goto('/app/admin')
    await expect(station.getByText('Tu cuenta no tiene permisos de administrador.')).toBeVisible()
    await station.goto('/account')
    await expect(station.getByRole('link', { name: 'Abrir administración' })).toHaveCount(0)
    await station.getByLabel('Indicativo', { exact: true }).fill('TEST0REVIEW')
    await station.getByRole('button', { name: 'Reclamar indicativo', exact: true }).click()
    await expect(station.getByText('TEST0REVIEW', { exact: true })).toBeVisible()
    await login(admin, 'scenario-admin@example.test')
    await expect(admin.getByRole('link', { name: 'Administración', exact: true })).toBeVisible()
    await admin.goto('/account')
    await admin.getByRole('link', { name: 'Abrir administración' }).click()
    await expect(admin.getByRole('heading', { name: 'Administración', exact: true })).toBeVisible()
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
test('perfil social publica solo texto propio y permite seguir sin exponer correo ni historial privado', async ({
  browser,
}) => {
  const ownerContext = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const followerContext = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const guestContext = await browser.newContext({ baseURL: 'http://localhost:15173' })
  const owner = await ownerContext.newPage()
  const follower = await followerContext.newPage()
  const guest = await guestContext.newPage()
  try {
    await login(owner, 'scenario-a@example.test')
    await owner.goto('/app/operator-profile')
    await expect(owner.getByRole('heading', { name: 'Mi perfil social' })).toBeVisible()
    await owner
      .getByLabel('Biografía', { exact: true })
      .fill('Biografía elegida por mí. <script>Texto literal</script>')
    await owner.getByLabel('País opcional (código de dos letras)', { exact: true }).fill('EC')
    await owner
      .getByRole('checkbox', {
        name: 'Publicar mi perfil (requiere titularidad verificada)',
        exact: true,
      })
      .check()
    await owner.getByRole('button', { name: 'Guardar perfil social', exact: true }).click()
    await expect(owner.getByText('Perfil guardado.', { exact: true })).toBeVisible()
    await owner
      .getByLabel('Nueva publicación', { exact: true })
      .fill('Publicación nativa de QSL Nexus.')
    await owner.getByRole('button', { name: 'Publicar', exact: true }).click()
    await expect(owner.getByText('Publicación creada.', { exact: true })).toBeVisible()
    await guest.goto('/operators/TEST0FULLA')
    await expect(guest.getByRole('heading', { name: 'TEST0FULLA', exact: true })).toBeVisible()
    await expect(
      guest.getByText('Biografía elegida por mí. <script>Texto literal</script>', { exact: true }),
    ).toBeVisible()
    await expect(guest.getByText('Publicación nativa de QSL Nexus.', { exact: true })).toBeVisible()
    await expect(guest.getByText('scenario-a@example.test', { exact: true })).toHaveCount(0)
    expect((await guest.request.get('/api/v1/public/operator-history/TEST0FULLA')).status()).toBe(
      404,
    )
    expect((await new AxeBuilder({ page: guest }).analyze()).violations).toEqual([])
    await owner
      .getByRole('checkbox', {
        name: 'Compartir públicamente mi historial de tarjetas QSL emitidas',
        exact: true,
      })
      .check()
    const saved = owner.waitForResponse(
      (r) => r.url().endsWith('/api/v1/operator-profile') && r.request().method() === 'PUT',
    )
    await owner.getByRole('button', { name: 'Guardar perfil social', exact: true }).click()
    expect((await saved).status()).toBe(200)
    await guest.reload()
    await expect(
      guest.getByRole('heading', { name: 'Historial QSL compartido por el operador', exact: true }),
    ).toBeVisible()
    await expect(
      guest.getByRole('navigation', { name: 'Paginación de tarjetas QSL', exact: true }),
    ).toBeVisible()
    expect((await new AxeBuilder({ page: guest }).analyze()).violations).toEqual([])
    await login(follower, 'scenario-b@example.test')
    await follower.goto('/operators/TEST0FULLA')
    await follower.getByRole('button', { name: 'Seguir', exact: true }).click()
    await expect(follower.getByText('1 seguidores · 0 seguidos', { exact: true })).toBeVisible()
    await guest.screenshot({ path: 'test-results/operator-public.png', fullPage: true })
  } finally {
    await ownerContext.close().catch(() => {})
    await followerContext.close().catch(() => {})
    await guestContext.close().catch(() => {})
  }
})

test('registro nuevo exige indicativo y mantiene la titularidad pendiente', async ({ page }) => {
  await page.goto('/account')
  await page.getByRole('button', { name: 'Crear cuenta', exact: true }).click()
  await page.getByLabel('Nombre', { exact: true }).fill('Usuario ficticio del directorio')
  await page.getByLabel('Indicativo de radioaficionado', { exact: true }).fill('TEST0WEBREG')
  await page.getByLabel('Correo electrónico', { exact: true }).fill('web-registration@example.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('DemoPassword123!')
  await page.getByLabel('Repetir contraseña', { exact: true }).fill('DemoPassword123!')
  await page.getByRole('button', { name: 'Crear cuenta', exact: true }).last().click()
  await expect(page.getByRole('heading', { name: 'Verifica tu correo', exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Abrir mi estación →' })).toHaveCount(0)
})

test('la clave manual configura TOTP y los datos de configuración se ocultan al activar', async ({
  page,
}) => {
  await page.goto('/account')
  await page.getByRole('button', { name: 'Crear cuenta', exact: true }).click()
  await page.getByLabel('Nombre', { exact: true }).fill('Prueba de autenticador manual')
  await page.getByLabel('Indicativo de radioaficionado', { exact: true }).fill('TEST0MANUAL')
  await page.getByLabel('Correo electrónico', { exact: true }).fill('manual-totp@example.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('DemoPassword123!')
  await page.getByLabel('Repetir contraseña', { exact: true }).fill('DemoPassword123!')
  await page.getByRole('button', { name: 'Crear cuenta', exact: true }).last().click()
  await expect(page.getByRole('heading', { name: 'Verifica tu correo', exact: true })).toBeVisible()
  const setup = page
    .locator('form')
    .filter({ has: page.getByRole('button', { name: 'Configurar segundo factor', exact: true }) })
  await setup.getByLabel('Contraseña actual', { exact: true }).fill('DemoPassword123!')
  await setup.getByRole('button').click()
  const qr = page.getByAltText('Código QR para configurar el autenticador')
  await expect(qr).toBeVisible()
  await expect(page.getByText(/En Microsoft Authenticator pulsa/)).toBeVisible()
  await page.getByText('¿No puedes escanear? Configurar con clave manual').click()
  const key = page.getByLabel('Clave de configuración manual', { exact: true })
  await expect(key).toHaveValue(/^[A-Z2-7]{16,}$/)
  const secret = await key.inputValue()
  const qrResponse = await page.request.get('/api/v1/auth/user/two-factor-qr-code')
  expect(qrResponse.ok()).toBe(true)
  const enrollment = new URL((await qrResponse.json()).url)
  expect(enrollment.protocol).toBe('otpauth:')
  expect(enrollment.hostname).toBe('totp')
  expect(enrollment.searchParams.get('secret')).toBe(secret)
  await page.setViewportSize({ width: 360, height: 800 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await qr.screenshot({ path: 'test-results/authenticator-qr.png' })
  await page.screenshot({ path: 'test-results/authenticator-setup.png', fullPage: true })
  const wrongCode = ((Number(authenticatorCode(secret)) + 1) % 1000000).toString().padStart(6, '0')
  await page.getByLabel('Código de verificación', { exact: true }).fill(wrongCode)
  await page.getByRole('button', { name: 'Activar segundo factor', exact: true }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  await expect(qr).toBeVisible()
  await page.getByLabel('Código de verificación', { exact: true }).fill(authenticatorCode(secret))
  await page.getByRole('button', { name: 'Activar segundo factor', exact: true }).click()
  await expect(page.getByText(/Segundo factor activado/)).toBeVisible()
  await expect(qr).toHaveCount(0)
  await expect(key).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Abrir mi estación →' })).toHaveCount(0)
})

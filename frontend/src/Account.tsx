import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { z } from 'zod'
import { Radio, LogOut, ShieldCheck } from 'lucide-react'
import { api } from './api'

type Values = Record<string, string>
type Field = { name: string; label: string; type?: string }
type User = {
  name: string
  email: string
  email_verified_at: string | null
  two_factor_enabled: boolean
  is_admin: boolean
}
type Callsign = {
  id: number
  callsign: string
  verification_status: string
  is_primary: boolean
  released: boolean
}
const email = z.email('Correo inválido')
const password = z.string().min(8, 'Usa al menos 8 caracteres')
const button = 'rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950 disabled:opacity-50'
const input = 'w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100'
const panel = 'rounded-2xl border border-slate-800 bg-slate-900/60 p-6'

function errorMessage(error: unknown): string {
  if (error instanceof z.ZodError) return error.issues.map((issue) => issue.message).join(' ')
  if (axios.isAxiosError(error))
    return (
      Object.values(error.response?.data?.errors ?? {})
        .flat()
        .join(' ') ||
      error.response?.data?.message ||
      'No fue posible conectar con el servidor.'
    )
  return 'No se pudo completar la operación.'
}

async function post(path: string, data: Values = {}) {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  return api.post(path, data)
}

function Form({
  fields,
  schema,
  submit,
  label,
}: {
  fields: Field[]
  schema: z.ZodType<Values>
  submit: (values: Values) => Promise<unknown>
  label: string
}) {
  const { register, handleSubmit } = useForm<Values>()
  const mutation = useMutation({
    mutationFn: async (values: Values) => submit(schema.parse(values)),
  })
  return (
    <form className="grid gap-4" onSubmit={handleSubmit((values) => mutation.mutate(values))}>
      {fields.map((field) => (
        <label key={field.name} className="grid gap-2 text-sm text-slate-300">
          {field.label}
          <input {...register(field.name)} required type={field.type ?? 'text'} className={input} />
        </label>
      ))}
      <button disabled={mutation.isPending} className={button}>
        {mutation.isPending ? 'Procesando…' : label}
      </button>
      {mutation.isError && (
        <p role="alert" className="text-sm text-red-300">
          {errorMessage(mutation.error)}
        </p>
      )}
      {mutation.isSuccess && (
        <p role="status" className="text-sm text-cyan-300">
          Operación completada.
        </p>
      )}
    </form>
  )
}

export function Account() {
  const client = useQueryClient()
  const [mode, setMode] = useState<'login' | 'register' | 'forgot' | 'challenge'>('login')
  const [qr, setQr] = useState('')
  const [secretKey, setSecretKey] = useState('')
  const [codes, setCodes] = useState<string[]>([])
  const [notice, setNotice] = useState('')
  const [resetDone, setResetDone] = useState(false)
  const params = new URLSearchParams(window.location.search)
  const resetToken = params.get('reset_token')
  const profile = useQuery({
    queryKey: ['profile'],
    retry: false,
    queryFn: async () => {
      try {
        return (await api.get<{ data: User }>('/profile')).data.data
      } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 401) return null
        throw error
      }
    },
  })
  const refresh = async () => {
    await client.invalidateQueries({ queryKey: ['profile'] })
    await client.invalidateQueries({ queryKey: ['callsigns'] })
  }
  const callsigns = useQuery({
    queryKey: ['callsigns'],
    enabled: Boolean(profile.data?.email_verified_at && profile.data.two_factor_enabled),
    retry: false,
    queryFn: async () => (await api.get<{ data: Callsign[] }>('/callsigns')).data.data,
  })
  const logout = useMutation({
    mutationFn: async () => {
      await post('/auth/logout')
      setQr('')
      setSecretKey('')
      setCodes([])
      setMode('login')
      client.clear()
      await refresh()
    },
  })
  const action = useMutation({
    mutationFn: async (path: string) => {
      await post(path)
      setNotice('Solicitud completada.')
      await refresh()
    },
  })

  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      <header className="mx-auto flex max-w-5xl items-center justify-between p-6">
        <Link to="/" className="flex items-center gap-3 font-bold">
          <Radio className="text-cyan-400" /> QSL NEXUS
        </Link>
        {profile.data && (
          <button
            className="flex items-center gap-2 text-sm text-slate-300"
            disabled={logout.isPending}
            onClick={() => logout.mutate()}
          >
            <LogOut size={16} /> Salir
          </button>
        )}
      </header>
      <main className="mx-auto grid max-w-5xl gap-6 px-6 pb-16">
        {profile.data?.email_verified_at && profile.data.two_factor_enabled && (
          <Link to="/app" className="text-cyan-300">
            Abrir mi estación →
          </Link>
        )}
        <h1 className="py-6 text-3xl font-semibold">
          {profile.data ? `Hola, ${profile.data.name}` : 'Tu cuenta QSL Nexus'}
        </h1>
        {profile.isPending ? (
          <p role="status">Cargando…</p>
        ) : profile.isError ? (
          <p role="alert">{errorMessage(profile.error)}</p>
        ) : !profile.data ? (
          <section className={`${panel} mx-auto w-full max-w-lg`}>
            {resetToken && !resetDone ? (
              <>
                <h2 className="mb-6 text-xl">Nueva contraseña</h2>
                <Form
                  fields={[
                    { name: 'password', label: 'Contraseña nueva', type: 'password' },
                    {
                      name: 'password_confirmation',
                      label: 'Repetir contraseña',
                      type: 'password',
                    },
                  ]}
                  schema={z
                    .object({ password, password_confirmation: z.string() })
                    .refine(
                      (v) => v.password === v.password_confirmation,
                      'Las contraseñas deben coincidir',
                    )}
                  label="Guardar contraseña"
                  submit={async (values) => {
                    await post('/auth/reset-password', {
                      ...values,
                      token: resetToken,
                      email: params.get('email') ?? '',
                    })
                    setResetDone(true)
                    window.history.replaceState({}, '', '/account')
                  }}
                />
              </>
            ) : (
              <>
                <div className="mb-6 flex flex-wrap gap-4">
                  {(['login', 'register', 'forgot'] as const).map((value) => (
                    <button
                      key={value}
                      className={mode === value ? 'text-cyan-300' : 'text-slate-400'}
                      onClick={() => setMode(value)}
                    >
                      {value === 'login'
                        ? 'Ingresar'
                        : value === 'register'
                          ? 'Crear cuenta'
                          : 'Recuperar acceso'}
                    </button>
                  ))}
                </div>
                {mode === 'challenge' ? (
                  <>
                    <h2 className="mb-4 text-xl">Segundo factor</h2>
                    <Form
                      key="challenge"
                      fields={[{ name: 'code', label: 'Código de tu autenticador' }]}
                      schema={z.object({
                        code: z.string().regex(/^\d{6}$/, 'Ingresa seis dígitos'),
                      })}
                      label="Verificar"
                      submit={async (values) => {
                        await post('/auth/two-factor-challenge', values)
                        await refresh()
                      }}
                    />
                    <details className="mt-6">
                      <summary>Usar código de recuperación</summary>
                      <Form
                        fields={[{ name: 'recovery_code', label: 'Código de recuperación' }]}
                        schema={z.object({ recovery_code: z.string().min(1) })}
                        label="Recuperar acceso"
                        submit={async (values) => {
                          await post('/auth/two-factor-challenge', values)
                          await refresh()
                        }}
                      />
                    </details>
                  </>
                ) : mode === 'forgot' ? (
                  <Form
                    key="forgot"
                    fields={[{ name: 'email', label: 'Correo electrónico', type: 'email' }]}
                    schema={z.object({ email })}
                    label="Enviar enlace"
                    submit={(values) => post('/auth/forgot-password', values)}
                  />
                ) : mode === 'register' ? (
                  <Form
                    key="register"
                    fields={[
                      { name: 'name', label: 'Nombre' },
                      { name: 'callsign', label: 'Indicativo de radioaficionado' },
                      { name: 'email', label: 'Correo electrónico', type: 'email' },
                      { name: 'password', label: 'Contraseña', type: 'password' },
                      {
                        name: 'password_confirmation',
                        label: 'Repetir contraseña',
                        type: 'password',
                      },
                    ]}
                    schema={z
                      .object({
                        name: z.string().min(1),
                        callsign: z.string().trim().min(3).max(32),
                        email,
                        password,
                        password_confirmation: z.string(),
                      })
                      .refine(
                        (v) => v.password === v.password_confirmation,
                        'Las contraseñas deben coincidir',
                      )}
                    label="Crear cuenta"
                    submit={async (values) => {
                      await post('/auth/register', values)
                      await refresh()
                    }}
                  />
                ) : (
                  <Form
                    key="login"
                    fields={[
                      { name: 'email', label: 'Correo electrónico', type: 'email' },
                      { name: 'password', label: 'Contraseña', type: 'password' },
                    ]}
                    schema={z.object({ email, password: z.string().min(1) })}
                    label="Ingresar"
                    submit={async (values) => {
                      const result = await post('/auth/login', values)
                      if (result.data.two_factor) setMode('challenge')
                      else await refresh()
                    }}
                  />
                )}
              </>
            )}
          </section>
        ) : (
          <>
            {profile.data.is_admin && (
              <section className={panel} aria-labelledby="admin-access-title">
                <h2 id="admin-access-title" className="mb-3 text-xl">
                  Administración
                </h2>
                <p className="mb-4 text-slate-400">
                  Tu cuenta tiene permisos de administrador para revisar evidencias, aprobar o
                  rechazar indicativos y liberar reservas rechazadas.
                </p>
                {profile.data.email_verified_at && profile.data.two_factor_enabled ? (
                  <Link to="/app/admin" className={`${button} inline-block`}>
                    Abrir administración
                  </Link>
                ) : (
                  <p className="text-cyan-300">
                    Verifica tu correo y activa el segundo factor para abrir el panel.
                  </p>
                )}
              </section>
            )}
            {!profile.data.email_verified_at && (
              <section className={panel}>
                <h2 className="mb-3 text-xl">Verifica tu correo</h2>
                <p className="mb-4 text-slate-400">
                  Abre el enlace que enviamos a {profile.data.email}. Después actualiza esta página.
                </p>
                <button
                  className={button}
                  disabled={action.isPending}
                  onClick={() => action.mutate('/auth/email/verification-notification')}
                >
                  Reenviar correo
                </button>
              </section>
            )}
            <section className={panel}>
              <h2 className="mb-4 text-xl">Mi perfil</h2>
              <p className="mb-4 text-slate-400">{profile.data.email}</p>
              <div className="max-w-md">
                <Form
                  fields={[
                    { name: 'name', label: 'Nombre' },
                    { name: 'preferred_language', label: 'Idioma preferido (por ejemplo: es)' },
                    { name: 'timezone', label: 'Zona horaria (por ejemplo: America/Guayaquil)' },
                  ]}
                  schema={z.object({
                    name: z.string().min(1).max(255),
                    preferred_language: z
                      .string()
                      .regex(/^[a-z]{2}(-[A-Z]{2})?$/, 'Idioma inválido'),
                    timezone: z.string().min(1),
                  })}
                  label="Actualizar perfil"
                  submit={async (values) => {
                    await axios.get('/sanctum/csrf-cookie')
                    await api.patch('/profile', values)
                    await refresh()
                  }}
                />
              </div>
            </section>
            <section className={panel}>
              <h2 className="mb-4 flex items-center gap-2 text-xl">
                <ShieldCheck className="text-cyan-400" /> Seguridad
              </h2>
              <p className="mb-6 text-slate-400">
                {profile.data.two_factor_enabled
                  ? 'Segundo factor activado. Confirma tu contraseña para consultar tus códigos de recuperación.'
                  : 'Confirma tu contraseña y configura un autenticador para habilitar las funciones operativas.'}
              </p>
              <div className="max-w-md">
                <Form
                  fields={[{ name: 'password', label: 'Contraseña actual', type: 'password' }]}
                  schema={z.object({ password: z.string().min(1) })}
                  label={
                    profile.data.two_factor_enabled
                      ? 'Consultar códigos'
                      : 'Configurar segundo factor'
                  }
                  submit={async (values) => {
                    await post('/auth/user/confirm-password', values)
                    if (!profile.data?.two_factor_enabled) {
                      await post('/auth/user/two-factor-authentication')
                      const [qrResponse, secretResponse] = await Promise.all([
                        api.get<{ svg: string }>('/auth/user/two-factor-qr-code'),
                        api.get<{ secretKey: string }>('/auth/user/two-factor-secret-key'),
                      ])
                      setQr(qrResponse.data.svg)
                      setSecretKey(secretResponse.data.secretKey)
                    }
                    setCodes((await api.get('/auth/user/two-factor-recovery-codes')).data)
                  }}
                />
              </div>
              {qr && !profile.data.two_factor_enabled && (
                <div className="mt-6 grid max-w-md gap-4">
                  <p>
                    En Microsoft Authenticator pulsa +, elige «Otra cuenta (Google, Facebook, etc.)»
                    y escanea este QR. Después escribe aquí los seis dígitos que muestra la
                    aplicación.
                  </p>
                  <div className="w-fit max-w-full rounded-lg bg-white p-6">
                    <img
                      alt="Código QR para configurar el autenticador"
                      className="block h-auto max-w-full"
                      width="224"
                      height="224"
                      src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(qr)}`}
                    />
                  </div>
                  {secretKey && (
                    <details>
                      <summary className="cursor-pointer text-cyan-300">
                        ¿No puedes escanear? Configurar con clave manual
                      </summary>
                      <div className="mt-3 grid gap-3 text-sm">
                        <p>
                          En «Otra cuenta», elige «Escribir código manualmente». Usa el nombre de
                          cuenta y la clave siguientes, con códigos basados en tiempo (TOTP).
                        </p>
                        <p>Nombre de cuenta: QSL Nexus ({profile.data.email})</p>
                        <label className="grid gap-2">
                          Clave de configuración manual
                          <input
                            className={`${input} font-mono`}
                            value={secretKey}
                            readOnly
                            autoComplete="off"
                            spellCheck={false}
                            onFocus={(event) => event.currentTarget.select()}
                          />
                        </label>
                        <p className="text-slate-400">
                          Esta clave permite generar tus códigos de acceso. Guárdala en tu
                          autenticador y no la compartas.
                        </p>
                      </div>
                    </details>
                  )}
                  <p className="text-sm text-slate-400">
                    Si se rechaza el código, activa la fecha y hora automáticas del teléfono y
                    prueba con el siguiente código disponible.
                  </p>
                  <Form
                    fields={[{ name: 'code', label: 'Código de verificación' }]}
                    schema={z.object({ code: z.string().regex(/^\d{6}$/, 'Ingresa seis dígitos') })}
                    label="Activar segundo factor"
                    submit={async (values) => {
                      await post('/auth/user/confirmed-two-factor-authentication', values)
                      setQr('')
                      setSecretKey('')
                      await refresh()
                    }}
                  />
                </div>
              )}
              {codes.length > 0 && (
                <div className="mt-6">
                  <p className="mb-3">
                    Guarda estos códigos en un lugar seguro. Cada código puede usarse una sola vez.
                  </p>
                  <pre className="overflow-auto rounded-lg bg-slate-950 p-4 text-cyan-300">
                    {codes.join('\n')}
                  </pre>
                  <button className="mt-3 text-sm text-slate-400" onClick={() => setCodes([])}>
                    Ocultar códigos
                  </button>
                </div>
              )}
              {profile.data.two_factor_enabled && (
                <div className="mt-6 flex flex-wrap gap-4">
                  <button
                    className={button}
                    onClick={async () => {
                      try {
                        await post('/auth/user/two-factor-recovery-codes')
                        setCodes((await api.get('/auth/user/two-factor-recovery-codes')).data)
                      } catch (error) {
                        setNotice(errorMessage(error))
                      }
                    }}
                  >
                    Renovar códigos
                  </button>
                  <button
                    className={button}
                    onClick={async () => {
                      if (
                        !window.confirm(
                          '¿Desactivar el segundo factor? Se bloquearán las funciones operativas hasta configurarlo nuevamente.',
                        )
                      )
                        return
                      try {
                        await axios.get('/sanctum/csrf-cookie')
                        await api.delete('/auth/user/two-factor-authentication')
                        setQr('')
                        setSecretKey('')
                        setCodes([])
                        await refresh()
                      } catch (error) {
                        setNotice(errorMessage(error))
                      }
                    }}
                  >
                    Desactivar segundo factor
                  </button>
                </div>
              )}
              <details className="mt-6">
                <summary className="cursor-pointer text-cyan-300">Cambiar contraseña</summary>
                <div className="mt-4 max-w-md">
                  <Form
                    fields={[
                      { name: 'current_password', label: 'Contraseña actual', type: 'password' },
                      { name: 'password', label: 'Contraseña nueva', type: 'password' },
                      {
                        name: 'password_confirmation',
                        label: 'Repetir contraseña nueva',
                        type: 'password',
                      },
                    ]}
                    schema={z
                      .object({
                        current_password: z.string().min(1),
                        password,
                        password_confirmation: z.string(),
                      })
                      .refine(
                        (v) => v.password === v.password_confirmation,
                        'Las contraseñas deben coincidir',
                      )}
                    label="Cambiar contraseña"
                    submit={async (values) => {
                      await axios.get('/sanctum/csrf-cookie')
                      await api.put('/auth/user/password', values)
                      setCodes([])
                      await refresh()
                    }}
                  />
                </div>
              </details>
            </section>
            {profile.data.email_verified_at && profile.data.two_factor_enabled && (
              <section className={panel}>
                <h2 className="mb-4 text-xl">Mis indicativos</h2>
                <p className="mb-6 text-slate-400">
                  Reclamar un indicativo no verifica su titularidad. Cada solicitud requiere
                  revisión.
                </p>
                {callsigns.isPending ? (
                  <p>Cargando indicativos…</p>
                ) : callsigns.isError ? (
                  <p role="alert">{errorMessage(callsigns.error)}</p>
                ) : callsigns.data?.length ? (
                  <ul className="mb-6 grid gap-3">
                    {callsigns.data.map((c) => (
                      <li key={c.id} className="flex justify-between rounded-lg bg-slate-950 p-4">
                        <strong>{c.callsign}</strong>
                        <span className="text-slate-400">
                          {c.released ? 'Liberado' : c.verification_status}
                          {c.is_primary ? ' · principal' : ''}
                        </span>
                        {!c.is_primary && !c.released && (
                          <button
                            className="text-sm text-cyan-300"
                            onClick={async () => {
                              try {
                                await axios.get('/sanctum/csrf-cookie')
                                await api.patch(`/callsigns/${c.id}/primary`)
                                await refresh()
                              } catch (error) {
                                setNotice(errorMessage(error))
                              }
                            }}
                          >
                            Hacer principal
                          </button>
                        )}
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="mb-6 text-slate-400">Todavía no has reclamado indicativos.</p>
                )}
                <div className="max-w-md">
                  <Form
                    fields={[{ name: 'callsign', label: 'Indicativo' }]}
                    schema={z.object({ callsign: z.string().min(3).max(32) })}
                    label="Reclamar indicativo"
                    submit={async (values) => {
                      await post('/callsigns', values)
                      await client.invalidateQueries({ queryKey: ['callsigns'] })
                    }}
                  />
                </div>
              </section>
            )}
          </>
        )}
        {logout.isError && (
          <p role="alert" className="text-red-300">
            {errorMessage(logout.error)}
          </p>
        )}
        {notice && (
          <p role="status" className="text-cyan-300">
            {notice}
          </p>
        )}
        {action.isError && (
          <p role="alert" className="text-red-300">
            {errorMessage(action.error)}
          </p>
        )}
      </main>
    </div>
  )
}

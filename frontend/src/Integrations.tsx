import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, mutate } from './api'
import type { Callsign } from './Workspace'
import {
  button,
  secondary,
  input,
  panel,
  LoadingState,
  EmptyState,
  ErrorState,
  Pagination,
  utc,
} from './components/ui'
type Credential = {
  id: string
  name: string
  callsign: string
  abilities: string[]
  expires_at: string
  revoked_at: string | null
  last_used_at: string | null
}
export function Integrations() {
  const client = useQueryClient()
  const [page, setPage] = useState(1)
  const [password, setPassword] = useState('')
  const [confirmed, setConfirmed] = useState(false)
  const [name, setName] = useState('Libro de guardia')
  const [callId, setCallId] = useState('')
  const [days, setDays] = useState(30)
  const [read, setRead] = useState(true)
  const [write, setWrite] = useState(true)
  const [secret, setSecret] = useState('')
  const credentials = useQuery({
    queryKey: ['integration-credentials', page],
    queryFn: async () =>
      (
        await api.get<{ data: Credential[]; meta: { last_page: number } }>(
          '/integration-credentials',
          { params: { page } },
        )
      ).data,
  })
  const callsigns = useQuery({
    queryKey: ['integration-callsigns'],
    queryFn: async () => {
      const all: Callsign[] = []
      let page = 1
      let last = 1
      do {
        const data = (
          await api.get<{ data: Callsign[]; meta: { last_page: number } }>('/callsigns', {
            params: { page },
          })
        ).data
        all.push(...data.data)
        last = data.meta.last_page
        page++
      } while (page <= last)
      return all.filter((c) => c.verification_status === 'verified' && !c.released)
    },
  })
  const confirm = useMutation({
    mutationFn: async () => {
      await mutate('post', '/auth/user/confirm-password', { password })
      setPassword('')
      setConfirmed(true)
    },
  })
  const action = useMutation({
    mutationFn: async ({ kind, id }: { kind: 'create' | 'rotate' | 'revoke'; id?: string }) => {
      setSecret('')
      if (kind === 'revoke') {
        await mutate('delete', `/integration-credentials/${id}`)
      } else {
        const response =
          kind === 'create'
            ? await mutate('post', '/integration-credentials', {
                name,
                callsign_id: Number(callId),
                abilities: [...(read ? ['qsos:read'] : []), ...(write ? ['qsos:write'] : [])],
                expires_in_days: days,
              })
            : await mutate('post', `/integration-credentials/${id}/rotate`, {
                expires_in_days: days,
              })
        // El secreto solo permanece en esta pantalla; nunca se almacena en localStorage ni en consultas.
        setSecret(response.data.token)
      }
    },
    onSuccess: () => client.invalidateQueries({ queryKey: ['integration-credentials'] }),
  })
  return (
    <>
      <h1 className="mb-5 text-3xl font-semibold">Conectar un libro de guardia</h1>
      <p className="mb-7 max-w-3xl text-slate-400">
        Autoriza al sistema de tu amigo para enviar contactos JSON y consultar su estado. Cada
        conexión usa un solo indicativo verificado. Las confirmaciones bilaterales se realizan en
        QSL Nexus.
      </p>
      <section className={`${panel} mb-6`}>
        <h2 className="mb-4 text-xl font-semibold">Confirma tu identidad</h2>
        <form
          className="grid max-w-md gap-4"
          onSubmit={(e) => {
            e.preventDefault()
            confirm.mutate()
          }}
        >
          <label className="grid gap-2 text-sm">
            Contraseña actual
            <input
              type="password"
              autoComplete="current-password"
              required
              value={password}
              className={input}
              onChange={(e) => setPassword(e.target.value)}
            />
          </label>
          <button className={secondary} disabled={confirm.isPending}>
            Autorizar gestión de conexiones
          </button>
        </form>
        {confirm.isError && <ErrorState error={confirm.error} />}{' '}
        {confirmed && (
          <p role="status" className="mt-4 text-cyan-300">
            Identidad confirmada. Puedes crear, rotar o revocar credenciales.
          </p>
        )}
      </section>
      {secret && (
        <section className={`${panel} mb-6`}>
          <h2 className="mb-3 text-xl font-semibold text-cyan-300">Guarda esta credencial</h2>
          <p className="mb-4 text-slate-400">
            Se muestra una sola vez. Entrégala únicamente al servidor del libro de guardia, mediante
            su configuración segura. No la publiques ni la pongas en JavaScript del navegador.
          </p>
          <label className="grid gap-3 text-sm">
            Credencial Bearer
            <textarea
              readOnly
              rows={3}
              className={`${input} break-all font-mono`}
              value={secret}
              onFocus={(e) => e.target.select()}
            />
          </label>
          <button className={`${secondary} mt-4`} onClick={() => setSecret('')}>
            Ya la guardé: ocultar
          </button>
        </section>
      )}
      <section className={`${panel} mb-6`}>
        <h2 className="mb-5 text-xl font-semibold">Nueva conexión</h2>
        {callsigns.isPending ? (
          <LoadingState />
        ) : callsigns.isError ? (
          <ErrorState error={callsigns.error} />
        ) : !callsigns.data.length ? (
          <EmptyState>Primero necesitas un indicativo con titularidad verificada.</EmptyState>
        ) : (
          <form
            className="grid gap-5 md:grid-cols-2"
            onSubmit={(e) => {
              e.preventDefault()
              action.mutate({ kind: 'create' })
            }}
          >
            <label className="grid gap-2 text-sm">
              Nombre del sistema
              <input
                maxLength={100}
                required
                className={input}
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </label>
            <label className="grid gap-2 text-sm">
              Indicativo autorizado
              <select
                required
                className={input}
                value={callId}
                onChange={(e) => setCallId(e.target.value)}
              >
                <option value="">Seleccionar</option>
                {callsigns.data.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.callsign}
                  </option>
                ))}
              </select>
            </label>
            <label className="grid gap-2 text-sm">
              Validez en días (1–90)
              <input
                type="number"
                min={1}
                max={90}
                required
                className={input}
                value={days}
                onChange={(e) => setDays(Number(e.target.value))}
              />
            </label>
            <fieldset className="grid gap-3">
              <legend className="mb-2 text-sm">Permisos</legend>
              <label className="flex items-center gap-3">
                <input
                  type="checkbox"
                  checked={write}
                  onChange={(e) => setWrite(e.target.checked)}
                />
                Enviar contactos
              </label>
              <label className="flex items-center gap-3">
                <input type="checkbox" checked={read} onChange={(e) => setRead(e.target.checked)} />
                Consultar contactos y QSL
              </label>
            </fieldset>
            <button
              className={`${button} w-fit`}
              disabled={!confirmed || action.isPending || (!read && !write)}
            >
              Crear credencial
            </button>
          </form>
        )}
      </section>
      {action.isError && <ErrorState error={action.error} />}
      <section className={panel}>
        <h2 className="mb-5 text-xl font-semibold">Conexiones autorizadas</h2>
        {credentials.isPending ? (
          <LoadingState />
        ) : credentials.isError ? (
          <ErrorState error={credentials.error} />
        ) : !credentials.data.data.length ? (
          <EmptyState>No has autorizado ningún sistema externo.</EmptyState>
        ) : (
          <>
            <div className="grid gap-5">
              {credentials.data.data.map((c) => (
                <article key={c.id} className="border-b border-slate-800 pb-5">
                  <h3 className="font-semibold text-cyan-300">
                    {c.name} · {c.callsign}
                  </h3>
                  <p className="mt-2 text-sm text-slate-400">
                    {c.abilities.join(' · ')} · Vence: {utc(c.expires_at)}
                  </p>
                  <p className="mt-2 text-sm text-slate-400">
                    {c.revoked_at
                      ? 'Revocada'
                      : new Date(c.expires_at) < new Date()
                        ? 'Expirada'
                        : 'Autorizada'}{' '}
                    · Último uso: {c.last_used_at ? utc(c.last_used_at) : 'Todavía sin uso'}
                  </p>
                  {!c.revoked_at && (
                    <div className="mt-4 flex flex-wrap gap-3">
                      <button
                        className={secondary}
                        disabled={!confirmed || action.isPending}
                        onClick={() => {
                          if (
                            window.confirm(
                              '¿Reemplazar la credencial? La anterior dejará de funcionar; las referencias de contactos se conservan.',
                            )
                          )
                            action.mutate({ kind: 'rotate', id: c.id })
                        }}
                      >
                        Rotar credencial
                      </button>
                      <button
                        className={secondary}
                        disabled={!confirmed || action.isPending}
                        onClick={() => {
                          if (
                            window.confirm(
                              '¿Revocar esta conexión? El sistema externo dejará de tener acceso.',
                            )
                          )
                            action.mutate({ kind: 'revoke', id: c.id })
                        }}
                      >
                        Revocar acceso
                      </button>
                    </div>
                  )}
                </article>
              ))}
            </div>
            <Pagination page={page} last={credentials.data.meta.last_page} change={setPage} />
          </>
        )}
      </section>
    </>
  )
}

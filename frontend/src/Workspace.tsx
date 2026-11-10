import { useState, type ReactNode } from 'react'
import { Link, NavLink, Navigate, Outlet, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Radio,
  LayoutDashboard,
  NotebookPen,
  GitCompareArrows,
  Image,
  Bell,
  ShieldCheck,
  UserRound,
  Menu,
  X,
  LogOut,
} from 'lucide-react'
import { api, mutate } from './api'
import {
  LoadingState,
  EmptyState,
  ErrorState,
  StatusBadge,
  StatCard,
  DataTable,
  Pagination,
  button,
  secondary,
  input,
  panel,
  utc,
} from './components/ui'
import { QsoForm } from './components/QsoForm'
export type Profile = {
  id: number
  name: string
  email_verified_at: string | null
  two_factor_enabled: boolean
  is_admin: boolean
}
export type Callsign = {
  id: number
  callsign: string
  verification_status: string
  is_primary: boolean
  released: boolean
  review_reason: string | null
}
export type Qso = {
  id: number
  my_callsign: string
  contacted_callsign: string
  started_at: string
  frequency_khz: string
  band: string
  mode: string
  status: string
  notes: string | null
}
export type Match = {
  id: number
  my_callsign: string
  other_callsign: string
  band: string
  mode: string
  started_at: string
  status: string
  my_confirmation: boolean
  other_confirmation: boolean
  explanation: string
  time_difference_seconds: number
}
export type Card = {
  public_id: string
  callsign_a: string
  callsign_b: string
  status: string
  band: string
  mode: string
  frequency_khz: string
  started_at: string
  generated_at: string
  confirmation_status: string
  verification_url: string
}
type Page<T> = { data: T[]; meta: { current_page: number; last_page: number } }
const links = [
  { to: '/app', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/app/qsos', label: 'Contactos QSO', icon: NotebookPen },
  { to: '/app/matches', label: 'Coincidencias', icon: GitCompareArrows },
  { to: '/app/cards', label: 'Tarjetas QSL', icon: Image },
  { to: '/app/notifications', label: 'Notificaciones', icon: Bell },
  { to: '/account', label: 'Perfil y seguridad', icon: UserRound },
]
export function Sidebar({ admin, close }: { admin: boolean; close: () => void }) {
  return (
    <>
      <Link to="/" className="mb-10 flex items-center gap-3 text-xl font-bold">
        <Radio className="text-cyan-400" />
        QSL NEXUS
      </Link>
      <nav className="grid gap-2" aria-label="Navegación principal">
        {[
          ...links,
          ...(admin ? [{ to: '/app/admin', label: 'Revisar indicativos', icon: ShieldCheck }] : []),
        ].map(({ to, label, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
            end={to === '/app'}
            onClick={close}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-lg p-3 text-sm ${isActive ? 'bg-cyan-950 text-cyan-300' : 'text-slate-400 hover:bg-slate-800 hover:text-white'}`
            }
          >
            <Icon size={19} />
            {label}
          </NavLink>
        ))}
      </nav>
    </>
  )
}
export function Header({
  name,
  toggle,
  logout,
  pending,
}: {
  name: string
  toggle: () => void
  logout: () => void
  pending: boolean
}) {
  return (
    <header className="flex items-center justify-between gap-4 border-b border-slate-800 px-5 py-5 md:px-8">
      <div className="flex items-center gap-4">
        <button className="lg:hidden" onClick={toggle} aria-label="Abrir menú">
          <Menu />
        </button>
        <span className="text-sm text-slate-400">Tu estación, conectada.</span>
      </div>
      <div className="flex items-center gap-4">
        <span className="text-sm">{name}</span>
        <button
          onClick={logout}
          disabled={pending}
          aria-label="Cerrar sesión"
          className="text-slate-400 hover:text-white"
        >
          <LogOut size={19} />
        </button>
      </div>
    </header>
  )
}
export function AppLayout() {
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()
  const client = useQueryClient()
  const profile = useQuery({
    queryKey: ['profile'],
    retry: false,
    queryFn: async () => (await api.get<{ data: Profile }>('/profile')).data.data,
  })
  const logout = useMutation({
    mutationFn: () => mutate('post', '/auth/logout'),
    onSuccess: () => {
      client.clear()
      navigate('/account')
    },
  })
  if (profile.isPending)
    return (
      <main className="min-h-screen bg-[#080f20] p-8 text-white">
        <LoadingState />
      </main>
    )
  if (profile.isError || !profile.data?.email_verified_at || !profile.data.two_factor_enabled)
    return <Navigate to="/account" replace />
  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      {open && (
        <button
          className="fixed inset-0 z-30 bg-black/70 lg:hidden"
          aria-label="Cerrar menú"
          onClick={() => setOpen(false)}
        />
      )}
      <aside
        className={`fixed inset-y-0 left-0 z-40 w-64 border-r border-slate-800 bg-[#0b1427] p-6 ${open ? 'block' : 'hidden'} lg:block`}
      >
        <button className="mb-4 lg:hidden" aria-label="Cerrar menú" onClick={() => setOpen(false)}>
          <X />
        </button>
        <Sidebar admin={profile.data.is_admin} close={() => setOpen(false)} />
      </aside>
      <div className="lg:ml-64">
        <Header
          name={profile.data.name}
          toggle={() => setOpen(true)}
          logout={() => logout.mutate()}
          pending={logout.isPending}
        />
        <main id="main-content" className="mx-auto max-w-7xl p-5 md:p-8">
          {logout.isError && <ErrorState error={logout.error} />}
          <Outlet />
        </main>
      </div>
    </div>
  )
}
function Title({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
      <h1 className="text-3xl font-semibold">{title}</h1>
      {children}
    </div>
  )
}
export function Dashboard() {
  const query = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            total_qsos: number
            confirmed_qsos: number
            issued_cards: number
            pending_matches: number
            recent_activity: Qso[]
          }
        }>('/dashboard')
      ).data.data,
  })
  return (
    <>
      <Title title="Tu actividad en el aire">
        <Link className={button} to="/app/qsos/new">
          Registrar QSO
        </Link>
      </Title>
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {[
              ['QSO registrados', query.data.total_qsos],
              ['Contactos confirmados', query.data.confirmed_qsos],
              ['Tarjetas emitidas', query.data.issued_cards],
              ['Coincidencias pendientes', query.data.pending_matches],
            ].map(([label, value]) => (
              <StatCard key={label} label={String(label)} value={Number(value)} />
            ))}
          </div>
          <section className={`${panel} mt-8`}>
            <h2 className="mb-4 text-xl font-semibold">Actividad reciente</h2>
            {query.data.recent_activity.length ? (
              <QsoTable rows={query.data.recent_activity} />
            ) : (
              <EmptyState>
                Aún no registras contactos. Tu próxima conexión comienza aquí.
              </EmptyState>
            )}
          </section>
        </>
      )}
    </>
  )
}
function QsoTable({ rows }: { rows: Qso[] }) {
  return (
    <DataTable
      label="Historial de contactos"
      rows={rows}
      rowKey={(r) => r.id}
      columns={[
        {
          label: 'Estación',
          render: (r) => (
            <Link className="font-medium text-cyan-300" to={`/app/qsos/${r.id}`}>
              {r.contacted_callsign}
            </Link>
          ),
        },
        { label: 'Fecha UTC', render: (r) => utc(r.started_at) },
        { label: 'Banda / modo', render: (r) => `${r.band} · ${r.mode}` },
        { label: 'Estado', render: (r) => <StatusBadge status={r.status} /> },
      ]}
    />
  )
}
export function QsoHistory() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [band, setBand] = useState('')
  const [mode, setMode] = useState('')
  const [status, setStatus] = useState('')
  const query = useQuery({
    queryKey: ['qsos', page, search, band, mode, status],
    queryFn: async () =>
      (await api.get<Page<Qso>>('/qsos', { params: { page, search, band, mode, status } })).data,
  })
  return (
    <>
      <Title title="Contactos QSO">
        <Link className={button} to="/app/qsos/new">
          Registrar QSO
        </Link>
      </Title>
      <section className={panel}>
        <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <label className="grid gap-2 text-sm">
            Indicativo
            <input
              className={input}
              value={search}
              maxLength={32}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
            />
          </label>
          <label className="grid gap-2 text-sm">
            Banda
            <input
              className={input}
              placeholder="Ej. 20m"
              value={band}
              maxLength={16}
              onChange={(e) => {
                setBand(e.target.value)
                setPage(1)
              }}
            />
          </label>
          <label className="grid gap-2 text-sm">
            Modo
            <input
              className={input}
              placeholder="Ej. FT8"
              value={mode}
              maxLength={16}
              onChange={(e) => {
                setMode(e.target.value.toUpperCase())
                setPage(1)
              }}
            />
          </label>
          <label className="grid gap-2 text-sm">
            Estado
            <select
              className={input}
              value={status}
              onChange={(e) => {
                setStatus(e.target.value)
                setPage(1)
              }}
            >
              <option value="">Todos</option>
              {['pending', 'candidate', 'confirmed', 'disputed', 'rejected'].map((v) => (
                <option key={v}>{v}</option>
              ))}
            </select>
          </label>
        </div>
        {query.isPending ? (
          <LoadingState />
        ) : query.isError ? (
          <ErrorState error={query.error} />
        ) : query.data.data.length ? (
          <>
            <QsoTable rows={query.data.data} />
            <Pagination page={page} last={query.data.meta.last_page} change={setPage} />
          </>
        ) : (
          <EmptyState>No hay contactos que coincidan con estos filtros.</EmptyState>
        )}
      </section>
    </>
  )
}
export function RegisterQso() {
  return (
    <>
      <Title title="Registrar contacto" />
      <section className={panel}>
        <p className="mb-6 text-slate-400">
          Utiliza un indicativo verificado y registra la fecha y hora en UTC.
        </p>
        <QsoForm />
      </section>
    </>
  )
}
export function QsoDetail() {
  const { id } = useParams()
  const client = useQueryClient()
  const navigate = useNavigate()
  const query = useQuery({
    queryKey: ['qso', id],
    queryFn: async () => (await api.get<{ data: Qso }>(`/qsos/${id}`)).data.data,
  })
  const withdraw = useMutation({
    mutationFn: () => mutate('delete', `/qsos/${id}`),
    onSuccess: () => {
      client.invalidateQueries()
      navigate('/app/qsos')
    },
  })
  return (
    <>
      <Title title="Detalle del contacto" />
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : (
        <section className={`${panel} grid gap-5`}>
          <h2 className="text-2xl text-cyan-300">
            {query.data.my_callsign} / {query.data.contacted_callsign}
          </h2>
          <p>{utc(query.data.started_at)}</p>
          <p>
            {query.data.band} · {query.data.mode} · {query.data.frequency_khz} kHz
          </p>
          <div>
            <StatusBadge status={query.data.status} />
          </div>
          <p className="whitespace-pre-wrap text-slate-400">
            {query.data.notes || 'Sin observaciones.'}
          </p>
          {['pending', 'candidate'].includes(query.data.status) && (
            <button
              className={`${secondary} w-fit`}
              disabled={withdraw.isPending}
              onClick={() => {
                if (window.confirm('¿Retirar este contacto e invalidar su coincidencia candidata?'))
                  withdraw.mutate()
              }}
            >
              Retirar contacto
            </button>
          )}
          <Link className="text-cyan-300" to="/app/matches">
            Consultar coincidencias
          </Link>
        </section>
      )}
      {withdraw.isError && <ErrorState error={withdraw.error} />}
    </>
  )
}
export function Matches() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('candidate')
  const client = useQueryClient()
  const navigate = useNavigate()
  const query = useQuery({
    queryKey: ['matches', page, status],
    queryFn: async () =>
      (await api.get<Page<Match>>('/matches', { params: { page, status } })).data,
  })
  const action = useMutation({
    mutationFn: ({ id, decision }: { id: number; decision: string }) =>
      mutate('patch', `/matches/${id}`, { decision }),
    onSuccess: () => client.invalidateQueries(),
  })
  const issue = useMutation({
    mutationFn: (id: number) => mutate('post', `/matches/${id}/qsl-cards`),
    onSuccess: (response) => {
      client.invalidateQueries()
      navigate(`/app/cards/${response.data.data.public_id}`)
    },
  })
  return (
    <>
      <Title title="Coincidencias" />
      <label className="mb-6 grid max-w-xs gap-2 text-sm">
        Mostrar
        <select
          className={input}
          value={status}
          onChange={(e) => {
            setStatus(e.target.value)
            setPage(1)
          }}
        >
          <option value="candidate">Pendientes de confirmar</option>
          <option value="confirmed">Confirmadas</option>
          <option value="disputed">En disputa</option>
          <option value="rejected">Rechazadas</option>
        </select>
      </label>
      {action.isError && <ErrorState error={action.error} />}{' '}
      {issue.isError && <ErrorState error={issue.error} />}
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : query.data.data.length ? (
        <div className="grid gap-5">
          {query.data.data.map((m) => (
            <article key={m.id} className={panel}>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-xl font-semibold text-cyan-300">
                  {m.my_callsign} / {m.other_callsign}
                </h2>
                <StatusBadge status={m.status} />
              </div>
              <p className="mt-3 text-slate-400">
                {utc(m.started_at)} · {m.band} · {m.mode} · diferencia: {m.time_difference_seconds}{' '}
                s
              </p>
              <p className="mt-3 text-sm text-slate-400">{m.explanation}</p>
              <p className="mt-4 text-sm">
                Tu confirmación: {m.my_confirmation ? 'Sí' : 'Pendiente'} · Otra estación:{' '}
                {m.other_confirmation ? 'Sí' : 'Pendiente'}
              </p>
              <div className="mt-6 flex flex-wrap gap-3">
                {m.status === 'candidate' && (
                  <>
                    <button
                      className={button}
                      disabled={action.isPending || m.my_confirmation}
                      onClick={() => action.mutate({ id: m.id, decision: 'confirm' })}
                    >
                      {m.my_confirmation ? 'Ya confirmaste' : 'Confirmo que ocurrió'}
                    </button>
                    <button
                      className={secondary}
                      disabled={action.isPending}
                      onClick={() => action.mutate({ id: m.id, decision: 'reject' })}
                    >
                      Rechazar
                    </button>
                  </>
                )}
                {m.status === 'confirmed' && (
                  <button
                    className={button}
                    disabled={issue.isPending}
                    onClick={() => issue.mutate(m.id)}
                  >
                    Emitir tarjeta QSL
                  </button>
                )}
                {['candidate', 'confirmed'].includes(m.status) && (
                  <button
                    className={secondary}
                    disabled={action.isPending}
                    onClick={() => {
                      if (
                        window.confirm(
                          '¿Disputar este contacto? Se revocarán sus tarjetas emitidas.',
                        )
                      )
                        action.mutate({ id: m.id, decision: 'dispute' })
                    }}
                  >
                    Disputar
                  </button>
                )}
              </div>
            </article>
          ))}
          <Pagination page={page} last={query.data.meta.last_page} change={setPage} />
        </div>
      ) : (
        <EmptyState>
          No hay coincidencias en este estado. Los contactos ambiguos quedan pendientes en tu
          historial.
        </EmptyState>
      )}
    </>
  )
}
export function QslCard({ card }: { card: Card }) {
  return (
    <article className={panel}>
      <div className="mb-4 flex flex-wrap justify-between gap-3">
        <h2 className="text-xl font-semibold">
          {card.callsign_a} / {card.callsign_b}
        </h2>
        <StatusBadge status={card.status} />
      </div>
      {card.status === 'ready' && (
        <img
          className="mb-5 w-full rounded-lg"
          alt={`Tarjeta QSL ${card.callsign_a} con ${card.callsign_b}`}
          src={`/api/v1/qsl-cards/${card.public_id}/download`}
        />
      )}
      <p className="text-sm text-slate-400">
        {utc(card.started_at)} · {card.band} · {card.mode}
      </p>
      <Link className="mt-4 inline-block text-cyan-300" to={`/app/cards/${card.public_id}`}>
        Ver tarjeta y verificación
      </Link>
    </article>
  )
}
export function Cards() {
  const [page, setPage] = useState(1)
  const query = useQuery({
    queryKey: ['cards', page],
    queryFn: async () => (await api.get<Page<Card>>('/qsl-cards', { params: { page } })).data,
  })
  return (
    <>
      <Title title="Tus tarjetas QSL" />
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : query.data.data.length ? (
        <>
          <div className="grid gap-6 xl:grid-cols-2">
            {query.data.data.map((card) => (
              <QslCard key={card.public_id} card={card} />
            ))}
          </div>
          <Pagination page={page} last={query.data.meta.last_page} change={setPage} />
        </>
      ) : (
        <EmptyState>
          Confirma un contacto con la otra estación para emitir tu primera tarjeta.
        </EmptyState>
      )}
    </>
  )
}
export function CardDetail({ publicView = false }: { publicView?: boolean }) {
  const { id } = useParams()
  const query = useQuery({
    queryKey: [publicView ? 'verification' : 'card', id],
    retry: false,
    queryFn: async () =>
      (await api.get<{ data: Card }>(`${publicView ? '/public' : ''}/qsl-cards/${id}`)).data.data,
  })
  const content = (
    <>
      <Title title={publicView ? 'Verificación de tarjeta QSL' : 'Detalle de tarjeta'} />
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : (
        <section className={`${panel} grid gap-5`}>
          <h2 className="text-2xl text-cyan-300">
            {query.data.callsign_a} / {query.data.callsign_b}
          </h2>
          <p className="break-all font-mono text-sm">{query.data.public_id}</p>
          <div className="flex flex-wrap gap-3">
            <StatusBadge status={query.data.status} />
            <StatusBadge status={query.data.confirmation_status} />
          </div>
          <p>{utc(query.data.started_at)}</p>
          <p>
            {query.data.band} · {query.data.frequency_khz} kHz · {query.data.mode}
          </p>
          <p className="text-sm text-slate-400">Emitida: {utc(query.data.generated_at)}</p>
          {!publicView && (
            <>
              <QslCard card={query.data} />
              <div className="flex flex-wrap gap-4">
                {query.data.status === 'ready' && (
                  <a className={button} href={`/api/v1/qsl-cards/${id}/download`}>
                    Descargar PNG
                  </a>
                )}
                <Link className={secondary} to={`/verify/${id}`}>
                  Verificación pública
                </Link>
              </div>
            </>
          )}
          <p className="text-sm text-slate-400">
            Estado consultado en el servidor. Una copia de la imagen puede conservarse después de su
            revocación.
          </p>
        </section>
      )}
    </>
  )
  return publicView ? (
    <main className="min-h-screen bg-[#080f20] p-6 text-white">
      <div className="mx-auto max-w-3xl">
        <Link to="/" className="mb-8 inline-block text-cyan-300">
          QSL Nexus
        </Link>
        {content}
      </div>
    </main>
  ) : (
    content
  )
}
export function Notifications() {
  const [page, setPage] = useState(1)
  const client = useQueryClient()
  type Notice = {
    id: number
    kind: string
    payload: { message: string; path: string }
    status: string
    read_at: string | null
    created_at: string
  }
  const query = useQuery({
    queryKey: ['notifications', page],
    queryFn: async () =>
      (await api.get<{ data: Notice[]; last_page: number }>('/notifications', { params: { page } }))
        .data,
  })
  const read = useMutation({
    mutationFn: (id: number) => mutate('patch', `/notifications/${id}`),
    onSuccess: () => client.invalidateQueries({ queryKey: ['notifications'] }),
  })
  return (
    <>
      <Title title="Notificaciones" />
      {read.isError && <ErrorState error={read.error} />}{' '}
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : query.data.data.length ? (
        <>
          <div className="grid gap-4">
            {query.data.data.map((n) => (
              <article className={panel} key={n.id}>
                <div className="flex flex-wrap justify-between gap-3">
                  <Link className="text-cyan-300" to={n.payload.path}>
                    {n.payload.message}
                  </Link>
                  <StatusBadge status={n.status} />
                </div>
                <p className="mt-3 text-sm text-slate-400">{utc(n.created_at)}</p>
                {!n.read_at && (
                  <button
                    disabled={read.isPending}
                    className={`${secondary} mt-4`}
                    onClick={() => read.mutate(n.id)}
                  >
                    Marcar como leída
                  </button>
                )}
              </article>
            ))}
          </div>
          <Pagination page={page} last={query.data.last_page} change={setPage} />
        </>
      ) : (
        <EmptyState>Todavía no tienes notificaciones.</EmptyState>
      )}
    </>
  )
}
export function AdminCallsigns() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('pending')
  const [reason, setReason] = useState('')
  const [feedback, setFeedback] = useState('')
  const client = useQueryClient()
  const query = useQuery({
    queryKey: ['admin-callsigns', page, status],
    retry: false,
    queryFn: async () =>
      (await api.get<Page<Callsign>>('/admin/callsigns', { params: { page, status } })).data,
  })
  const review = useMutation({
    mutationFn: ({ id, status: decision }: { id: number; status: string }) =>
      mutate('post', `/admin/callsigns/${id}/review`, { status: decision, reason }),
    onSuccess: () => {
      setFeedback('Revisión guardada.')
      client.invalidateQueries()
    },
  })
  const release = useMutation({
    mutationFn: (id: number) => mutate('delete', `/admin/callsigns/${id}/reservation`),
    onSuccess: () => {
      setFeedback('Reserva liberada; el historial se conserva.')
      client.invalidateQueries()
    },
  })
  return (
    <>
      <Title title="Revisión de indicativos" />
      <p className="mb-6 text-slate-400">
        Revisa evidencia de titularidad antes de aprobar. El formato no acredita una licencia. No
        puedes revisar tus propios indicativos.
      </p>
      <div className="mb-6 grid gap-4 sm:grid-cols-2">
        <label className="grid gap-2 text-sm">
          Estado
          <select
            value={status}
            className={input}
            onChange={(e) => {
              setStatus(e.target.value)
              setPage(1)
            }}
          >
            {['pending', 'verified', 'rejected', 'disputed'].map((s) => (
              <option key={s}>{s}</option>
            ))}
          </select>
        </label>
        <label className="grid gap-2 text-sm">
          Motivo y referencia de evidencia (10–2000 caracteres)
          <textarea
            className={input}
            value={reason}
            minLength={10}
            maxLength={2000}
            onChange={(e) => setReason(e.target.value)}
          />
        </label>
      </div>
      {feedback && (
        <p role="status" className="mb-4 text-cyan-300">
          {feedback}
        </p>
      )}
      {review.isError && <ErrorState error={review.error} />}{' '}
      {release.isError && <ErrorState error={release.error} />}{' '}
      {query.isPending ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : query.data.data.length ? (
        <section className={panel}>
          <DataTable
            label="Revisión de indicativos"
            rows={query.data.data}
            rowKey={(r) => r.id}
            columns={[
              { label: 'Indicativo', render: (c) => <strong>{c.callsign}</strong> },
              { label: 'Estado', render: (c) => <StatusBadge status={c.verification_status} /> },
              { label: 'Último motivo', render: (c) => c.review_reason || 'Sin revisión' },
              {
                label: 'Acciones',
                render: (c) => (
                  <div className="flex flex-wrap gap-2">
                    {['verified', 'rejected', 'disputed'].map((s) => (
                      <button
                        key={s}
                        className={secondary}
                        disabled={review.isPending || reason.trim().length < 10}
                        onClick={() => review.mutate({ id: c.id, status: s })}
                      >
                        {s === 'verified' ? 'Aprobar' : s === 'rejected' ? 'Rechazar' : 'Disputar'}
                      </button>
                    ))}
                    {c.verification_status === 'rejected' && (
                      <button
                        className={secondary}
                        disabled={release.isPending}
                        onClick={() => {
                          if (
                            window.confirm(
                              '¿Liberar la reserva? Otro usuario podrá reclamar este indicativo.',
                            )
                          )
                            release.mutate(c.id)
                        }}
                      >
                        Liberar reserva
                      </button>
                    )}
                  </div>
                ),
              },
            ]}
          />
          <Pagination page={page} last={query.data.meta.last_page} change={setPage} />
        </section>
      ) : (
        <EmptyState>No hay solicitudes en este estado.</EmptyState>
      )}
    </>
  )
}

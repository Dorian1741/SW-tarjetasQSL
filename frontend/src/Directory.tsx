import { useState, type FormEvent } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { api, mutate } from './api'
import {
  button,
  secondary,
  input,
  panel,
  LoadingState,
  ErrorState,
  EmptyState,
  StatusBadge,
  Pagination,
  utc,
} from './components/ui'
import type { Callsign, Card } from './Workspace'
import {
  PublicHeader,
  OperatorPreview,
  usePublicAccount,
  type OperatorSummary,
} from './components/PublicHeader'

type Lookup = {
  callsign: string
  format_valid: boolean
  directory_status: string
  ownership_verified: boolean
  result: { provider: string; record: { callsign: string; country: string | null } } | null
  providers: { provider: string; status: string }[]
  notice: string
}
type SocialProfile = {
  callsign: string
  biography: string | null
  country_code: string | null
  links: { label: string; url: string }[]
  is_public: boolean
  show_qsl_history: boolean
  photo_url: string | null
  followers: number
  following: number
  public_url: string
  ownership_verified: boolean
}
type Post = { id: number; content: string; created_at: string }
const labels: Record<string, string> = {
  found: 'Encontrado en directorio',
  not_found: 'No encontrado',
  unavailable: 'Proveedor no disponible o sin configurar',
  unconfigured: 'Sin configurar',
  incomplete: 'Respuesta incompleta',
}

export function Directory() {
  const account = usePublicAccount()
  const [params, setParams] = useSearchParams()
  const term = params.get('q') || ''
  const currentPage = Number(params.get('page') || '1')
  const [publicSearch, setPublicSearch] = useState(term)
  const operators = useQuery({
    queryKey: ['public-directory', term, currentPage],
    queryFn: async () =>
      (
        await api.get<{ data: OperatorSummary[]; last_page: number }>('/public/operators', {
          params: { q: term, page: currentPage },
        })
      ).data,
  })
  const [call, setCall] = useState('')
  const [provider, setProvider] = useState('auto')
  const [result, setResult] = useState<Lookup | null>(null)
  const search = useMutation({
    mutationFn: async () => {
      setResult(null)
      try {
        const response = await mutate('post', '/callsigns/lookup', { callsign: call, provider })
        setResult(response.data.data)
      } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.data)
          setResult(error.response.data.data)
        throw error
      }
    },
  })
  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      <PublicHeader />
      <main className="px-6 py-8">
        <div className="mx-auto grid max-w-4xl gap-6">
          <Link to="/" className="text-cyan-300">
            QSL Nexus
          </Link>
          <h1 className="text-3xl font-semibold">Directorio internacional</h1>
          <p className="text-slate-400">
            Descubre estaciones y busca sus perfiles públicos por indicativo, sin iniciar sesión.
          </p>
          <section className={panel} aria-labelledby="public-directory-title">
            <h2 id="public-directory-title" className="mb-4 text-xl font-semibold">
              Operadores de la comunidad
            </h2>
            <form
              className="flex flex-wrap gap-3"
              role="search"
              onSubmit={(event) => {
                event.preventDefault()
                setParams(publicSearch.trim() ? { q: publicSearch.trim().toUpperCase() } : {})
              }}
            >
              <label className="min-w-0 flex-1">
                <span className="sr-only">Buscar perfiles por indicativo</span>
                <input
                  className={input}
                  maxLength={32}
                  value={publicSearch}
                  placeholder="Indicativo completo o parcial"
                  onChange={(event) => setPublicSearch(event.target.value)}
                />
              </label>
              <button className={button}>Buscar perfiles</button>
            </form>
            {operators.isPending ? (
              <LoadingState />
            ) : operators.isError ? (
              <ErrorState error={operators.error} retry={() => operators.refetch()} />
            ) : operators.data.data.length ? (
              <>
                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                  {operators.data.data.map((operator) => (
                    <article
                      className="min-w-0 rounded-xl border border-slate-800 p-3"
                      key={operator.callsign}
                    >
                      <OperatorPreview operator={operator} />
                      <p className="mt-3 line-clamp-3 text-sm leading-6 break-words text-slate-400 [overflow-wrap:anywhere]">
                        {operator.biography || 'Conoce esta estación y sus publicaciones.'}
                      </p>
                    </article>
                  ))}
                </div>
                <Pagination
                  label="Paginación del directorio público"
                  page={currentPage}
                  last={operators.data.last_page}
                  change={(next) => setParams({ ...(term ? { q: term } : {}), page: String(next) })}
                />
              </>
            ) : (
              <EmptyState>
                {term
                  ? 'No se encontraron perfiles públicos con ese indicativo.'
                  : 'Todavía no hay perfiles públicos en la comunidad.'}
              </EmptyState>
            )}
          </section>
          <section className={panel}>
            <h2 className="mb-3 text-xl font-semibold">Consultar QRZ y HamQTH</h2>
            <p className="mb-5 text-sm text-slate-400">
              La consulta a proveedores externos requiere una cuenta con correo verificado.
            </p>
            {!account.data?.email_verified_at && (
              <Link to="/account" className="mb-5 inline-block text-cyan-300">
                Ingresar para consultar proveedores externos
              </Link>
            )}
            <form
              className="grid gap-4 sm:grid-cols-2"
              onSubmit={(e) => {
                e.preventDefault()
                search.mutate()
              }}
            >
              <label className="grid gap-2 text-sm">
                Indicativo
                <input
                  required
                  maxLength={32}
                  className={input}
                  value={call}
                  onChange={(e) => setCall(e.target.value)}
                />
              </label>
              <label className="grid gap-2 text-sm">
                Proveedor
                <select
                  className={input}
                  value={provider}
                  onChange={(e) => setProvider(e.target.value)}
                >
                  <option value="auto">Automático</option>
                  <option value="qrz">QRZ XML</option>
                  <option value="hamqth">HamQTH v2</option>
                </select>
              </label>
              <button
                className={button}
                disabled={search.isPending || !account.data?.email_verified_at}
              >
                Consultar directorios
              </button>
              {call.trim() && (
                <Link
                  className={secondary}
                  to={`/operators/${encodeURIComponent(call.trim().toUpperCase())}`}
                >
                  Abrir perfil de QSL Nexus
                </Link>
              )}
            </form>
            {search.isPending && <LoadingState />}
            {search.isError && !result && <ErrorState error={search.error} />}{' '}
            {result && (
              <div className="mt-6 grid gap-3" role="status">
                <p>Formato: {result.format_valid ? 'válido' : 'inválido'}</p>
                <p>Directorio: {labels[result.directory_status] ?? result.directory_status}</p>
                <p>
                  Titularidad en QSL Nexus:{' '}
                  {result.ownership_verified ? 'verificada administrativamente' : 'no verificada'}
                </p>
                {result.result && (
                  <p>
                    Registro devuelto: {result.result.record.callsign} · País indicado por la
                    fuente: {result.result.record.country || 'No disponible'}
                  </p>
                )}
                <p className="text-sm text-slate-400">{result.notice}</p>
                {result.providers.map((p) => (
                  <p key={p.provider} className="text-sm text-slate-400">
                    {p.provider}: {labels[p.status] ?? p.status}
                  </p>
                ))}
              </div>
            )}
          </section>
          <p className="text-sm text-slate-400">
            Consultas externas mediante las API oficiales de{' '}
            <a href="https://www.qrz.com" rel="noreferrer" className="text-cyan-300 underline">
              QRZ
            </a>{' '}
            y{' '}
            <a href="https://www.hamqth.com" rel="noreferrer" className="text-cyan-300 underline">
              HamQTH.com
            </a>
            . No se importan sus datos personales a perfiles. Si utilizas HamQTH, puedes registrarte
            allí y mantener actualizado tu indicativo.
          </p>
          <Link to="/account" className="text-cyan-300">
            Ingresar o crear mi perfil
          </Link>
        </div>
      </main>
    </div>
  )
}

export function PublicOperator() {
  const account = usePublicAccount()
  const params = useParams()
  const call = (params['*'] || '').toUpperCase()
  const encoded = encodeURIComponent(call)
  const [page, setPage] = useState(1)
  const [historyPage, setHistoryPage] = useState(1)
  const profile = useQuery({
    queryKey: ['operator', call],
    retry: false,
    queryFn: async () =>
      (await api.get<{ data: SocialProfile }>(`/public/operators/${encoded}`)).data.data,
  })
  const posts = useQuery({
    queryKey: ['operator-posts', call, page],
    enabled: profile.isSuccess,
    queryFn: async () =>
      (
        await api.get<{ data: Post[]; last_page: number }>(`/public/operator-posts/${encoded}`, {
          params: { page },
        })
      ).data,
  })
  const history = useQuery({
    queryKey: ['operator-history', call, historyPage],
    enabled: !!profile.data?.show_qsl_history,
    queryFn: async () =>
      (
        await api.get<{ data: Card[]; meta: { last_page: number } }>(
          `/public/operator-history/${encoded}`,
          { params: { page: historyPage } },
        )
      ).data,
  })
  const follow = useMutation({
    mutationFn: (method: 'put' | 'delete') => mutate(method, `/operators/follow/${encoded}`),
    onSuccess: () => profile.refetch(),
  })
  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      <PublicHeader />
      <main className="p-6">
        <div className="mx-auto grid max-w-4xl gap-6">
          <Link to="/directory" className="text-cyan-300">
            ← Directorio QSL Nexus
          </Link>
          {profile.isPending ? (
            <LoadingState />
          ) : profile.isError ? (
            <>
              <h1 className="text-3xl font-semibold">Perfil no disponible</h1>
              <p className="text-slate-400">
                El indicativo puede no tener un perfil público, o su titularidad todavía está en
                revisión.
              </p>
              <ErrorState error={profile.error} />
            </>
          ) : (
            <>
              <section className={panel}>
                <div className="flex flex-wrap items-center gap-6">
                  {profile.data.photo_url && (
                    <img
                      src={profile.data.photo_url}
                      alt={`Foto elegida por ${call}`}
                      className="h-28 w-28 rounded-full object-cover"
                    />
                  )}
                  <div>
                    <h1 className="mb-3 text-3xl font-semibold text-cyan-300">
                      {profile.data.callsign}
                    </h1>
                    <StatusBadge
                      status={profile.data.ownership_verified ? 'verified' : 'pending'}
                    />
                  </div>
                </div>
                <p className="mt-6 whitespace-pre-wrap text-slate-300">
                  {profile.data.biography || 'Este operador aún no ha añadido una biografía.'}
                </p>
                {profile.data.country_code && (
                  <p className="mt-4">País elegido: {profile.data.country_code}</p>
                )}
                <div className="mt-5 flex flex-wrap gap-4">
                  {profile.data.links.map((link) => (
                    <a
                      key={link.url}
                      href={link.url}
                      target="_blank"
                      rel="noopener noreferrer nofollow ugc"
                      className="text-cyan-300"
                    >
                      {link.label}
                    </a>
                  ))}
                </div>
                <p className="mt-5 text-sm text-slate-400">
                  {profile.data.followers} seguidores · {profile.data.following} seguidos
                </p>
                <div className="mt-5 flex flex-wrap gap-3">
                  {account.data?.email_verified_at && account.data.two_factor_enabled ? (
                    <>
                      <button
                        className={button}
                        disabled={follow.isPending}
                        onClick={() => follow.mutate('put')}
                      >
                        Seguir
                      </button>
                      <button
                        className={secondary}
                        disabled={follow.isPending}
                        onClick={() => follow.mutate('delete')}
                      >
                        Dejar de seguir
                      </button>
                    </>
                  ) : (
                    <Link to="/account" className={button}>
                      {account.data
                        ? 'Completa tu cuenta para seguir'
                        : 'Inicia sesión para seguir'}
                    </Link>
                  )}
                </div>
                {follow.isError && <ErrorState error={follow.error} />}
                <p className="mt-4 text-xs text-slate-400">
                  Para seguir perfiles necesitas una cuenta verificada y segundo factor.
                </p>
              </section>
              <section className={panel}>
                <h2 className="mb-5 text-xl font-semibold">Publicaciones en QSL Nexus</h2>
                {posts.isPending ? (
                  <LoadingState />
                ) : posts.isError ? (
                  <ErrorState error={posts.error} />
                ) : posts.data?.data.length ? (
                  <>
                    <div className="grid gap-5">
                      {posts.data.data.map((p) => (
                        <article key={p.id} className="border-b border-slate-800 pb-5">
                          <p className="whitespace-pre-wrap">{p.content}</p>
                          <p className="mt-3 text-xs text-slate-400">{utc(p.created_at)}</p>
                        </article>
                      ))}
                    </div>
                    <Pagination page={page} last={posts.data.last_page} change={setPage} />
                  </>
                ) : (
                  <EmptyState>Sin publicaciones.</EmptyState>
                )}
              </section>
              {profile.data.show_qsl_history && (
                <section className={panel}>
                  <h2 className="mb-5 text-xl font-semibold">
                    Historial QSL compartido por el operador
                  </h2>
                  {history.isPending ? (
                    <LoadingState />
                  ) : history.isError ? (
                    <ErrorState error={history.error} />
                  ) : history.data?.data.length ? (
                    <>
                      <ul className="grid gap-4">
                        {history.data.data.map((card) => (
                          <li key={card.public_id}>
                            <Link className="text-cyan-300" to={`/verify/${card.public_id}`}>
                              {card.callsign_a} / {card.callsign_b} · {card.band} · {card.mode} ·{' '}
                              {utc(card.started_at)}
                            </Link>
                          </li>
                        ))}
                      </ul>
                      <Pagination
                        label="Paginación de tarjetas QSL"
                        page={historyPage}
                        last={history.data.meta.last_page}
                        change={setHistoryPage}
                      />
                    </>
                  ) : (
                    <EmptyState>Sin tarjetas emitidas compartidas.</EmptyState>
                  )}
                </section>
              )}
            </>
          )}
        </div>
      </main>
    </div>
  )
}

export function OwnershipEvidence({ id }: { id: number }) {
  const [open, setOpen] = useState(false)
  const query = useQuery({
    queryKey: ['ownership-evidence', id],
    enabled: open,
    queryFn: async () =>
      (
        await api.get<{ data: { id: number; evidence: string; status: string }[] }>(
          `/callsigns/${id}/ownership-requests`,
        )
      ).data.data,
  })
  return (
    <div>
      <button className={secondary} onClick={() => setOpen(!open)}>
        {open ? 'Ocultar solicitud' : 'Ver solicitud de titularidad'}
      </button>
      {open &&
        (query.isPending ? (
          <LoadingState />
        ) : query.isError ? (
          <ErrorState error={query.error} />
        ) : query.data?.length ? (
          <ul className="mt-3 grid gap-3">
            {query.data.map((r) => (
              <li key={r.id} className="max-w-lg whitespace-pre-wrap text-sm text-slate-300">
                {r.evidence} <StatusBadge status={r.status} />
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-3 text-sm text-slate-400">
            Sin solicitud formal. La revisión puede basarse en evidencia recibida por un canal
            privado.
          </p>
        ))}
    </div>
  )
}

function ProfileEditor({
  initial,
  callsigns,
}: {
  initial: SocialProfile | null
  callsigns: Callsign[]
}) {
  const client = useQueryClient()
  const [callId, setCallId] = useState(
    String(
      callsigns.find((c) => c.callsign === initial?.callsign)?.id ??
        callsigns.find((c) => c.is_primary)?.id ??
        callsigns[0]?.id ??
        '',
    ),
  )
  const [bio, setBio] = useState(initial?.biography ?? '')
  const [country, setCountry] = useState(initial?.country_code ?? '')
  const [links, setLinks] = useState(
    initial?.links.map((l) => `${l.label}|${l.url}`).join('\n') ?? '',
  )
  const [visible, setVisible] = useState(initial?.is_public ?? false)
  const [history, setHistory] = useState(initial?.show_qsl_history ?? false)
  const [evidence, setEvidence] = useState('')
  const [post, setPost] = useState('')
  const [photo, setPhoto] = useState<File | null>(null)
  const save = useMutation({
    mutationFn: () =>
      mutate('put', '/operator-profile', {
        callsign_id: Number(callId),
        biography: bio,
        country_code: country || null,
        links: links
          .split('\n')
          .filter((l) => l.trim())
          .map((line) => {
            const [label, ...url] = line.split('|')
            return { label: label.trim(), url: url.join('|').trim() }
          }),
        is_public: visible,
        show_qsl_history: history,
      }),
    onSuccess: () => client.invalidateQueries(),
  })
  const request = useMutation({
    mutationFn: () => mutate('post', `/callsigns/${callId}/ownership-requests`, { evidence }),
    onSuccess: () => client.invalidateQueries(),
  })
  const upload = useMutation({
    mutationFn: () => {
      const body = new FormData()
      if (photo) body.append('photo', photo)
      return mutate('post', '/operator-profile/photo', body)
    },
    onSuccess: () => client.invalidateQueries(),
  })
  const publish = useMutation({
    mutationFn: () => mutate('post', '/operator-posts', { content: post }),
    onSuccess: () => {
      setPost('')
      client.invalidateQueries()
    },
  })
  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }
  return (
    <div className="grid gap-6">
      <section className={panel}>
        <h2 className="mb-4 text-xl font-semibold">Información elegida por ti</h2>
        <p className="mb-5 text-slate-400">
          No se rellena con datos externos. Solo será visible si publicas tu perfil y la titularidad
          está verificada.
        </p>
        <form className="grid gap-5" onSubmit={submit}>
          <label className="grid gap-2 text-sm">
            Indicativo del perfil
            <select
              className={input}
              value={callId}
              onChange={(e) => setCallId(e.target.value)}
              required
            >
              {callsigns
                .filter((c) => !c.released)
                .map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.callsign} · {c.verification_status}
                  </option>
                ))}
            </select>
          </label>
          <div className="grid gap-2 text-sm">
            <label htmlFor="operator-biography">Biografía</label>
            <textarea
              id="operator-biography"
              className={input}
              rows={5}
              maxLength={3000}
              value={bio}
              onChange={(e) => setBio(e.target.value)}
            />
          </div>
          <label className="grid gap-2 text-sm">
            País opcional (código de dos letras)
            <input
              className={input}
              maxLength={2}
              value={country}
              onChange={(e) => setCountry(e.target.value.toUpperCase())}
            />
          </label>
          <label className="grid gap-2 text-sm">
            Enlaces autorizados (hasta cinco, uno por línea: etiqueta|https://...)
            <textarea
              className={input}
              rows={3}
              value={links}
              onChange={(e) => setLinks(e.target.value)}
            />
          </label>
          <label className="flex items-center gap-3">
            <input
              type="checkbox"
              checked={visible}
              onChange={(e) => setVisible(e.target.checked)}
            />
            Publicar mi perfil (requiere titularidad verificada)
          </label>
          <label className="flex items-center gap-3">
            <input
              type="checkbox"
              checked={history}
              onChange={(e) => setHistory(e.target.checked)}
            />
            Compartir públicamente mi historial de tarjetas QSL emitidas
          </label>
          <button className={`${button} w-fit`} disabled={save.isPending || !callId}>
            Guardar perfil social
          </button>
        </form>
        {save.isError && <ErrorState error={save.error} />}{' '}
        {save.isSuccess && (
          <p role="status" className="mt-4 text-cyan-300">
            Perfil guardado.
          </p>
        )}
        {initial?.is_public && (
          <Link className="mt-4 inline-block text-cyan-300" to={initial.public_url}>
            Ver mi perfil público
          </Link>
        )}
      </section>
      <section className={panel}>
        <h2 className="mb-4 text-xl font-semibold">Foto del perfil</h2>
        <p className="mb-4 text-sm text-slate-400">
          PNG, JPEG o WebP, hasta 2 MB. Se eliminarán sus metadatos. Guarda primero el perfil.
        </p>
        <label className="grid gap-3 text-sm">
          Elegir foto
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
          />
        </label>
        <button
          className={`${secondary} mt-4`}
          disabled={!photo || upload.isPending}
          onClick={() => upload.mutate()}
        >
          Subir foto
        </button>
        {upload.isError && <ErrorState error={upload.error} />}{' '}
        {upload.isSuccess && (
          <p role="status" className="mt-4 text-cyan-300">
            Foto guardada.
          </p>
        )}
      </section>
      <section className={panel}>
        <h2 className="mb-4 text-xl font-semibold">Solicitar verificación de titularidad</h2>
        <p className="mb-4 text-sm text-slate-400">
          Solicitud para el indicativo seleccionado arriba. Describe cómo puede el administrador
          revisar tu licencia por un canal privado; este texto no será público.
        </p>
        <label className="grid gap-2 text-sm">
          Referencia de evidencia
          <textarea
            className={input}
            minLength={20}
            maxLength={2000}
            rows={3}
            value={evidence}
            onChange={(e) => setEvidence(e.target.value)}
          />
        </label>
        <button
          className={`${secondary} mt-4`}
          disabled={evidence.trim().length < 20 || !callId || request.isPending}
          onClick={() => request.mutate()}
        >
          Enviar solicitud de titularidad
        </button>
        {request.isError && <ErrorState error={request.error} />}{' '}
        {request.isSuccess && (
          <p role="status" className="mt-4 text-cyan-300">
            Solicitud pendiente de revisión.
          </p>
        )}
      </section>
      <section className={panel}>
        <h2 className="mb-4 text-xl font-semibold">Publicar en QSL Nexus</h2>
        <p className="mb-4 text-sm text-slate-400">
          El texto será público en tu perfil publicado. No se importan publicaciones externas.
        </p>
        <label className="grid gap-2 text-sm">
          Nueva publicación
          <textarea
            className={input}
            maxLength={2000}
            value={post}
            onChange={(e) => setPost(e.target.value)}
            rows={4}
          />
        </label>
        <button
          className={`${button} mt-4`}
          disabled={!post.trim() || publish.isPending}
          onClick={() => publish.mutate()}
        >
          Publicar
        </button>
        {publish.isError && <ErrorState error={publish.error} />}{' '}
        {publish.isSuccess && (
          <p role="status" className="mt-4 text-cyan-300">
            Publicación creada.
          </p>
        )}
      </section>
    </div>
  )
}
export function MyOperatorProfile() {
  const profile = useQuery({
    queryKey: ['my-operator-profile'],
    queryFn: async () =>
      (await api.get<{ data: SocialProfile | null }>('/operator-profile')).data.data,
  })
  const callsigns = useQuery({
    queryKey: ['operator-callsigns'],
    queryFn: async () => {
      const result: Callsign[] = []
      let page = 1
      let last = 1
      do {
        const data = (
          await api.get<{ data: Callsign[]; meta: { last_page: number } }>('/callsigns', {
            params: { page },
          })
        ).data
        result.push(...data.data)
        last = data.meta.last_page
        page++
      } while (page <= last)
      return result
    },
  })
  return (
    <>
      <h1 className="mb-8 text-3xl font-semibold">Mi perfil social</h1>
      {profile.isPending || callsigns.isPending ? (
        <LoadingState />
      ) : profile.isError || callsigns.isError ? (
        <ErrorState error={profile.error || callsigns.error} />
      ) : !callsigns.data?.length ? (
        <EmptyState>Reclama un indicativo desde tu cuenta para crear tu perfil.</EmptyState>
      ) : (
        <ProfileEditor initial={profile.data ?? null} callsigns={callsigns.data} />
      )}
    </>
  )
}

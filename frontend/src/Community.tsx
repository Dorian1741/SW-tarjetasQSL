import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowUpRight, Globe, House, NotebookPen, Radio, Search, Send, Users } from 'lucide-react'
import { api } from './api'
import {
  PublicHeader,
  OperatorAvatar,
  OperatorPreview,
  usePublicAccount,
  type OperatorSummary,
} from './components/PublicHeader'
import {
  button,
  secondary,
  input,
  panel,
  LoadingState,
  ErrorState,
  EmptyState,
  Pagination,
  utc,
} from './components/ui'

type CommunityData = {
  posts: {
    data: { id: number; content: string; created_at: string; author: OperatorSummary }[]
    current_page: number
    last_page: number
  }
  operators: OperatorSummary[]
  stats: { operators: number; publications: number }
}

export function Community() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const navigate = useNavigate()
  const account = usePublicAccount()
  const community = useQuery({
    queryKey: ['public-community', page],
    queryFn: async () =>
      (await api.get<CommunityData>('/public/community', { params: { page } })).data,
  })
  function findOperator(event: FormEvent) {
    event.preventDefault()
    navigate(`/directory?q=${encodeURIComponent(search.trim().toUpperCase())}`)
  }
  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      <PublicHeader />
      <div className="mx-auto grid max-w-7xl items-start gap-6 px-5 py-7 lg:grid-cols-[210px_minmax(0,1fr)] xl:grid-cols-[210px_minmax(0,1fr)_270px]">
        <aside className="hidden space-y-6 lg:block">
          <nav aria-label="Explorar la comunidad" className="grid gap-2 text-sm">
            <Link
              to="/"
              className="flex items-center gap-3 rounded-xl bg-cyan-950 p-3 text-cyan-200"
            >
              <House size={19} /> Muro de la comunidad
            </Link>
            <Link
              to="/directory"
              className="flex items-center gap-3 rounded-xl p-3 text-slate-300 hover:bg-slate-800"
            >
              <Users size={19} /> Buscar operadores
            </Link>
            <Link
              to={account.data ? '/app' : '/account'}
              className="flex items-center gap-3 rounded-xl p-3 text-slate-300 hover:bg-slate-800"
            >
              <NotebookPen size={19} /> Mi estación
            </Link>
          </nav>
          <div className="rounded-2xl border border-slate-800 p-5">
            <Radio className="mb-4 text-cyan-400" />
            <h2 className="font-semibold">Una comunidad sin fronteras</h2>
            <p className="mt-3 text-sm leading-relaxed text-slate-400">
              Conoce estaciones, descubre publicaciones y conserva tus conexiones en el aire.
            </p>
            <p className="mt-4 text-xs text-slate-400">
              Explorar es público. Participar empieza con tu cuenta.
            </p>
          </div>
        </aside>
        <main className="min-w-0 space-y-5">
          <section className="rounded-2xl border border-cyan-900/60 bg-gradient-to-br from-cyan-950/80 to-slate-900 p-6 md:p-8">
            <p className="mb-3 flex items-center gap-2 text-xs font-semibold tracking-widest text-cyan-300">
              <span className="h-2 w-2 rounded-full bg-cyan-400" /> COMUNIDAD ABIERTA
            </p>
            <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">
              La comunidad, al aire.
            </h1>
            <p className="mt-3 max-w-lg text-sm leading-relaxed text-slate-300">
              Historias, estaciones y conexiones de radioaficionados. Explora el muro y los perfiles
              sin iniciar sesión.
            </p>
            <form
              onSubmit={findOperator}
              className="mt-6 flex flex-wrap gap-2"
              role="search"
              aria-label="Buscar un operador"
            >
              <label className="min-w-0 flex-1">
                <span className="sr-only">Buscar por indicativo</span>
                <input
                  className={input}
                  value={search}
                  maxLength={32}
                  placeholder="Busca un indicativo, por ejemplo HC…"
                  onChange={(event) => setSearch(event.target.value)}
                />
              </label>
              <button className={button}>
                <Search size={17} aria-hidden="true" /> Buscar
              </button>
            </form>
          </section>
          <section
            className={`${panel} flex flex-wrap items-center gap-4`}
            aria-label="Participar en el muro"
          >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-800">
              <Send size={19} className="text-cyan-400" />
            </span>
            <div className="min-w-0 flex-1">
              <h2 className="font-semibold">¿Qué se escucha en tu estación?</h2>
              <p className="mt-1 text-sm text-slate-400">
                Comparte una experiencia con la comunidad.
              </p>
            </div>
            <Link to={account.data ? '/app/operator-profile' : '/account'} className={secondary}>
              {account.data ? 'Crear publicación' : 'Ingresar para publicar'}
            </Link>
          </section>
          <div className="flex flex-wrap items-center justify-between gap-3 px-1">
            <h2 className="text-lg font-semibold">Publicaciones de la comunidad</h2>
            <span className="flex items-center gap-2 text-xs text-slate-400">
              <Globe size={14} aria-hidden="true" /> Públicas · Más recientes
            </span>
          </div>
          {community.isPending ? (
            <LoadingState />
          ) : community.isError ? (
            <ErrorState error={community.error} retry={() => community.refetch()} />
          ) : community.data.posts.data.length ? (
            <>
              {community.data.posts.data.map((post) => (
                <article key={post.id} className={`${panel} min-w-0`}>
                  <header className="mb-5 flex items-center gap-3">
                    <OperatorAvatar operator={post.author} />
                    <div className="min-w-0">
                      <Link to={post.author.public_url} className="font-semibold text-cyan-300">
                        {post.author.callsign}
                      </Link>
                      <p className="mt-1 text-xs text-slate-400">
                        <time dateTime={post.created_at}>{utc(post.created_at)}</time>
                      </p>
                    </div>
                  </header>
                  <p className="text-sm leading-7 whitespace-pre-wrap break-words text-slate-200 [overflow-wrap:anywhere]">
                    {post.content}
                  </p>
                  <footer className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-4 text-sm">
                    <span className="flex items-center gap-2 text-slate-400">
                      <Radio size={15} aria-hidden="true" />{' '}
                      {post.author.country_code || 'Comunidad internacional'}
                    </span>
                    <Link
                      to={post.author.public_url}
                      className="flex items-center gap-1 text-cyan-300"
                    >
                      Conocer la estación <ArrowUpRight size={15} aria-hidden="true" />
                    </Link>
                  </footer>
                </article>
              ))}
              <Pagination
                label="Paginación del muro"
                page={page}
                last={community.data.posts.last_page}
                change={(next) => {
                  setPage(next)
                  window.scrollTo({ top: 0, behavior: 'smooth' })
                }}
              />
            </>
          ) : (
            <section className={panel}>
              <EmptyState>
                <p>La comunidad está empezando. Todavía no hay publicaciones públicas.</p>
                <Link
                  to={account.data ? '/app/operator-profile' : '/account'}
                  className="text-cyan-300"
                >
                  Comparte la primera experiencia de tu estación
                </Link>
              </EmptyState>
            </section>
          )}
        </main>
        <aside
          className="space-y-5 lg:col-start-2 xl:col-start-3"
          aria-label="Descubrir estaciones"
        >
          <section className={panel}>
            <h2 className="mb-4 text-lg font-semibold">Estaciones para descubrir</h2>
            {community.isPending ? (
              <LoadingState />
            ) : community.isError ? (
              <p className="text-sm text-slate-400">No se pudieron cargar las estaciones.</p>
            ) : community.data.operators.length ? (
              <div className="grid gap-3">
                {community.data.operators.map((operator) => (
                  <OperatorPreview key={operator.callsign} operator={operator} />
                ))}
              </div>
            ) : (
              <p className="text-sm text-slate-400">
                Aquí aparecerán los perfiles que los operadores decidan publicar.
              </p>
            )}
            <Link
              to="/directory"
              className="mt-5 inline-flex items-center gap-2 text-sm text-cyan-300"
            >
              Explorar el directorio <ArrowUpRight size={15} aria-hidden="true" />
            </Link>
          </section>
          {community.isSuccess && (
            <section
              className="rounded-2xl border border-slate-800 p-5"
              aria-label="Actividad pública"
            >
              <p className="text-xs font-semibold tracking-widest text-slate-400">
                LA COMUNIDAD EN NÚMEROS
              </p>
              <dl className="mt-5 grid grid-cols-2 gap-4">
                <div>
                  <dt className="text-xs text-slate-400">Perfiles públicos</dt>
                  <dd className="mt-2 text-2xl font-semibold text-cyan-300">
                    {community.data.stats.operators}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-400">Publicaciones</dt>
                  <dd className="mt-2 text-2xl font-semibold text-cyan-300">
                    {community.data.stats.publications}
                  </dd>
                </div>
              </dl>
            </section>
          )}
          <p className="px-2 text-xs leading-5 text-slate-400">
            Los perfiles y sus publicaciones se muestran por decisión de cada operador. La
            titularidad del indicativo requiere revisión.
          </p>
        </aside>
      </div>
    </div>
  )
}

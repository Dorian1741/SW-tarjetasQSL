import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Image, Palette, Upload } from 'lucide-react'
import { api } from './api'
import type { StationCardPage } from './cardDesign'
import { PublicHeader, usePublicAccount } from './components/PublicHeader'
import {
  button,
  secondary,
  input,
  panel,
  LoadingState,
  ErrorState,
  EmptyState,
  Pagination,
} from './components/ui'

export function CardGallery() {
  const account = usePublicAccount()
  const [page, setPage] = useState(1)
  const [term, setTerm] = useState('')
  const [search, setSearch] = useState('')
  const gallery = useQuery({
    queryKey: ['public-station-cards', page, search],
    queryFn: async () =>
      (await api.get<StationCardPage>('/public/station-cards', { params: { page, q: search } }))
        .data,
  })
  const studio = account.data ? '/app/card-studio' : '/account'
  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      <PublicHeader />
      <main className="mx-auto max-w-7xl space-y-7 px-5 py-8">
        <section className="rounded-2xl border border-cyan-900 bg-gradient-to-br from-cyan-950/60 to-slate-900 p-6 md:p-9">
          <p className="mb-3 flex items-center gap-2 text-xs font-semibold tracking-widest text-cyan-300">
            <Image size={17} aria-hidden="true" /> TARJETAS DE LA COMUNIDAD
          </p>
          <h1 className="text-3xl font-semibold md:text-4xl">Galería QSL</h1>
          <p className="mt-4 max-w-2xl leading-relaxed text-slate-300">
            Cada estación tiene su propia identidad. Descubre tarjetas compartidas por
            radioaficionados, sube las tuyas o diseña una desde cero.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link to={studio} className={button}>
              <Palette size={17} aria-hidden="true" /> Diseñar mi tarjeta
            </Link>
            <Link
              to={account.data ? '/app/card-studio?mode=upload' : '/account'}
              className={secondary}
            >
              <Upload size={17} aria-hidden="true" /> Subir una tarjeta
            </Link>
          </div>
        </section>
        <form
          className="flex flex-wrap gap-3"
          role="search"
          onSubmit={(event) => {
            event.preventDefault()
            setSearch(term.trim().toUpperCase())
            setPage(1)
          }}
        >
          <label className="min-w-0 max-w-md flex-1">
            <span className="sr-only">Buscar tarjetas por indicativo</span>
            <input
              className={input}
              maxLength={32}
              placeholder="Buscar tarjetas por indicativo"
              value={term}
              onChange={(event) => setTerm(event.target.value)}
            />
          </label>
          <button className={secondary}>Buscar tarjetas</button>
        </form>
        {gallery.isPending ? (
          <LoadingState />
        ) : gallery.isError ? (
          <ErrorState error={gallery.error} retry={() => gallery.refetch()} />
        ) : gallery.data.data.length ? (
          <>
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              {gallery.data.data.map((card) => (
                <article
                  id={card.public_id}
                  key={card.public_id}
                  className={`${panel} min-w-0 overflow-hidden`}
                >
                  <a
                    href={card.image_url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={`Ampliar tarjeta ${card.title}`}
                  >
                    <img
                      src={card.image_url}
                      alt={`Tarjeta ${card.title} de ${card.callsign}`}
                      className="aspect-[8/5] w-full rounded-lg bg-slate-950 object-contain"
                      loading="lazy"
                    />
                  </a>
                  <p className="mt-5 text-xs font-semibold tracking-widest text-cyan-300">
                    {card.callsign} ·{' '}
                    {card.source === 'editor' ? 'DISEÑADA AQUÍ' : 'TARJETA SUBIDA'}
                  </p>
                  <h2 className="mt-2 break-words text-lg font-semibold">{card.title}</h2>
                  {card.description && (
                    <p className="mt-3 text-sm leading-6 whitespace-pre-wrap break-words text-slate-400 [overflow-wrap:anywhere]">
                      {card.description}
                    </p>
                  )}
                  <a
                    className="mt-5 inline-block text-sm text-cyan-300"
                    href={card.image_url}
                    download={`${card.callsign}.png`}
                  >
                    Descargar imagen
                  </a>
                </article>
              ))}
            </div>
            <Pagination
              label="Paginación de la galería QSL"
              page={page}
              last={gallery.data.meta.last_page}
              change={setPage}
            />
          </>
        ) : (
          <section className={panel}>
            <EmptyState>
              <p>
                {search
                  ? 'No hay tarjetas públicas de ese indicativo.'
                  : 'Todavía no hay tarjetas compartidas. Crea la primera de tu estación.'}
              </p>
              <Link to={studio} className="text-cyan-300">
                Abrir el estudio de tarjetas
              </Link>
            </EmptyState>
          </section>
        )}
        <p className="text-sm text-slate-400">
          Esta galería reúne imágenes y diseños que sus autores eligen compartir. Las QSL emitidas
          con confirmación bilateral conservan su verificación en la sección de tu estación.
        </p>
      </main>
    </div>
  )
}

import { useEffect, useRef, useState, type PointerEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, Image, Palette, Plus, Save, Upload } from 'lucide-react'
import { api, mutate } from './api'
import {
  cardText,
  designBlob,
  drawDesign,
  elementLabels,
  newDesign,
  type CardDesign,
  type CardElement,
  type StationCard,
  type StationCardPage,
} from './cardDesign'
import type { Callsign } from './Workspace'
import {
  button,
  secondary,
  input,
  panel,
  LoadingState,
  ErrorState,
  EmptyState,
} from './components/ui'

export function CardStudio() {
  const [params] = useSearchParams()
  const [mode, setMode] = useState<'editor' | 'upload'>(
    params.get('mode') === 'upload' ? 'upload' : 'editor',
  )
  const [design, setDesign] = useState<CardDesign>(() => newDesign())
  const [selected, setSelected] = useState<CardElement>('callsign')
  const [title, setTitle] = useState('Mi tarjeta QSL')
  const [description, setDescription] = useState('')
  const [callId, setCallId] = useState('')
  const [isPublic, setIsPublic] = useState(false)
  const [editing, setEditing] = useState<StationCard | null>(null)
  const [upload, setUpload] = useState<File | null>(null)
  const [preview, setPreview] = useState('')
  const [feedback, setFeedback] = useState('')
  const [page, setPage] = useState(1)
  const [exportError, setExportError] = useState<unknown>(null)
  const canvas = useRef<HTMLCanvasElement>(null)
  const drag = useRef<{ key: CardElement; dx: number; dy: number } | null>(null)
  const client = useQueryClient()
  const callsigns = useQuery({
    queryKey: ['studio-callsigns'],
    queryFn: async () => {
      const result: Callsign[] = []
      let current = 1,
        last = 1
      do {
        const response = (
          await api.get<{ data: Callsign[]; meta: { last_page: number } }>('/callsigns', {
            params: { page: current },
          })
        ).data
        result.push(...response.data)
        last = response.meta.last_page
        current++
      } while (current <= last)
      return result.filter((call) => !call.released)
    },
  })
  const saved = useQuery({
    queryKey: ['station-cards', page],
    queryFn: async () =>
      (await api.get<StationCardPage>('/station-cards', { params: { page } })).data,
  })
  const chosen =
    callsigns.data?.find((call) => String(call.id) === callId) ||
    callsigns.data?.find((call) => call.is_primary) ||
    callsigns.data?.[0]
  useEffect(() => {
    if (canvas.current) drawDesign(canvas.current, design, chosen?.callsign || '', selected)
  }, [design, chosen?.callsign, selected, mode])
  useEffect(() => {
    if (!upload) {
      setPreview('')
      return
    }
    const url = URL.createObjectURL(upload)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [upload])
  function reset() {
    setEditing(null)
    setTitle('Mi tarjeta QSL')
    setDescription('')
    setIsPublic(false)
    setDesign(newDesign())
    setUpload(null)
    setFeedback('')
    setMode('editor')
    setExportError(null)
  }
  function edit(card: StationCard) {
    setEditing(card)
    setTitle(card.title)
    setDescription(card.description || '')
    setCallId(String(card.callsign_id))
    setIsPublic(card.is_public)
    setMode(card.source)
    setUpload(null)
    if (card.design) setDesign(card.design)
    setFeedback('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  function position(event: PointerEvent<HTMLCanvasElement>) {
    const rect = event.currentTarget.getBoundingClientRect()
    return {
      x: ((event.clientX - rect.left) * 1200) / rect.width,
      y: ((event.clientY - rect.top) * 750) / rect.height,
    }
  }
  function startDrag(event: PointerEvent<HTMLCanvasElement>) {
    const point = position(event)
    const context = event.currentTarget.getContext('2d')
    if (!context) return
    const key = (Object.keys(elementLabels) as CardElement[]).find((field) => {
      const pos = design.positions[field]
      const size = field === 'callsign' ? 104 : 32
      context.font = `${field === 'callsign' ? 'bold ' : ''}${size}px Arial`
      const width = Math.min(
        context.measureText(cardText(design, chosen?.callsign || '', field)).width || 150,
        1125 - pos.x,
      )
      return (
        point.x >= pos.x - 10 &&
        point.x <= pos.x + width + 10 &&
        point.y >= pos.y - size - 10 &&
        point.y <= pos.y + 20
      )
    })
    if (key) {
      setSelected(key)
      drag.current = {
        key,
        dx: point.x - design.positions[key].x,
        dy: point.y - design.positions[key].y,
      }
      event.currentTarget.setPointerCapture(event.pointerId)
    }
  }
  function moveDrag(event: PointerEvent<HTMLCanvasElement>) {
    if (!drag.current) return
    const point = position(event)
    const current = drag.current
    setDesign((previous) => ({
      ...previous,
      positions: {
        ...previous.positions,
        [current.key]: {
          x: Math.round(Math.max(30, Math.min(1100, point.x - current.dx))),
          y: Math.round(Math.max(60, Math.min(700, point.y - current.dy))),
        },
      },
    }))
  }
  const save = useMutation({
    mutationFn: async () => {
      if (!chosen) throw new Error('Reclama primero un indicativo para guardar tu tarjeta.')
      const blob = mode === 'editor' ? await designBlob(design, chosen.callsign) : upload
      if (!blob) throw new Error('Selecciona la imagen que quieres subir.')
      if (blob.size > 2 * 1024 * 1024) throw new Error('La imagen debe pesar como máximo 2 MB.')
      const body = new FormData()
      body.append('image', blob, mode === 'editor' ? 'design.png' : upload!.name)
      body.append('callsign_id', String(chosen.id))
      body.append('title', title)
      body.append('description', description)
      body.append('source', mode)
      body.append('is_public', isPublic ? '1' : '0')
      if (mode === 'editor') body.append('design', JSON.stringify(design))
      if (editing) body.append('_method', 'PUT')
      return (
        await mutate(
          'post',
          editing ? `/station-cards/${editing.public_id}` : '/station-cards',
          body,
        )
      ).data.data as StationCard
    },
    onSuccess: (card) => {
      setEditing(card)
      setFeedback(
        card.is_public
          ? 'Tarjeta guardada y publicada en la galería.'
          : 'Tarjeta guardada como privada.',
      )
      client.invalidateQueries()
    },
  })
  const visibility = useMutation({
    mutationFn: (card: StationCard) =>
      mutate('patch', `/station-cards/${card.public_id}/visibility`, {
        is_public: !card.is_public,
      }),
    onSuccess: (_, card) => {
      if (editing?.public_id === card.public_id) setIsPublic(!card.is_public)
      client.invalidateQueries()
    },
  })
  const remove = useMutation({
    mutationFn: (id: string) => mutate('delete', `/station-cards/${id}`),
    onSuccess: (_, id) => {
      if (editing?.public_id === id) reset()
      client.invalidateQueries()
    },
  })
  async function exportPng() {
    try {
      const blob = await designBlob(design, chosen?.callsign || 'TU INDICATIVO')
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `${chosen?.callsign || 'mi-tarjeta'}.png`
      link.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
      setExportError(null)
    } catch (error) {
      setExportError(error)
    }
  }
  function template(value: CardDesign['template']) {
    const next = newDesign(value)
    setDesign((previous) => ({
      ...next,
      subtitle: previous.subtitle,
      location: previous.location,
      locator: previous.locator,
      message: previous.message,
    }))
  }
  return (
    <>
      <div className="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="mb-2 text-xs font-semibold tracking-widest text-cyan-300">
            TU IDENTIDAD EN EL AIRE
          </p>
          <h1 className="text-3xl font-semibold">Estudio de tarjetas QSL</h1>
          <p className="mt-3 text-sm text-slate-400">
            Sube una tarjeta o crea un diseño. Tú decides cuándo compartirlo.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link to="/cards" className={secondary}>
            Ver galería pública
          </Link>
          <button className={secondary} onClick={reset}>
            <Plus size={16} aria-hidden="true" /> Nuevo diseño
          </button>
        </div>
      </div>
      {callsigns.isPending ? (
        <LoadingState />
      ) : callsigns.isError ? (
        <ErrorState error={callsigns.error} />
      ) : !callsigns.data.length ? (
        <section className={panel}>
          <EmptyState>
            <p>Reclama un indicativo para guardar tarjetas de tu estación.</p>
            <Link to="/account" className={button}>
              Gestionar mis indicativos
            </Link>
          </EmptyState>
        </section>
      ) : (
        <>
          <div className="mb-5 flex flex-wrap gap-2" aria-label="Elegir modo">
            <button
              className={mode === 'editor' ? button : secondary}
              aria-pressed={mode === 'editor'}
              onClick={() => setMode('editor')}
            >
              <Palette size={17} aria-hidden="true" /> Diseñar tarjeta
            </button>
            <button
              className={mode === 'upload' ? button : secondary}
              aria-pressed={mode === 'upload'}
              onClick={() => setMode('upload')}
            >
              <Upload size={17} aria-hidden="true" /> Subir imagen
            </button>
          </div>
          <div className="grid items-start gap-6 xl:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
            <section
              className={`${panel} min-w-0 space-y-5`}
              aria-label="Configuración de la tarjeta"
            >
              <h2 className="text-lg font-semibold">
                {editing ? 'Editar tarjeta guardada' : 'Tu nueva tarjeta'}
              </h2>
              <label className="grid gap-2 text-sm">
                Indicativo de la tarjeta
                <select
                  className={input}
                  value={String(chosen?.id || '')}
                  onChange={(event) => setCallId(event.target.value)}
                >
                  {callsigns.data.map((call) => (
                    <option key={call.id} value={call.id}>
                      {call.callsign}
                    </option>
                  ))}
                </select>
              </label>
              <label className="grid gap-2 text-sm">
                Título de la tarjeta
                <input
                  className={input}
                  maxLength={100}
                  value={title}
                  onChange={(event) => setTitle(event.target.value)}
                />
              </label>
              <div className="grid gap-2 text-sm">
                <label htmlFor="card-description">Descripción para la galería</label>
                <textarea
                  id="card-description"
                  className={input}
                  maxLength={1000}
                  rows={3}
                  value={description}
                  onChange={(event) => setDescription(event.target.value)}
                />
              </div>
              {mode === 'editor' ? (
                <>
                  <label className="grid gap-2 text-sm">
                    Plantilla
                    <select
                      className={input}
                      value={design.template}
                      onChange={(event) => template(event.target.value as CardDesign['template'])}
                    >
                      <option value="aurora">Aurora · ondas de radio</option>
                      <option value="classic">Clásica · papel y tinta</option>
                      <option value="sunset">Atardecer · montañas</option>
                    </select>
                  </label>
                  <div className="grid grid-cols-3 gap-3">
                    {(['background', 'accent', 'text'] as const).map((key) => (
                      <label key={key} className="grid gap-2 text-xs">
                        {key === 'background' ? 'Fondo' : key === 'accent' ? 'Acento' : 'Texto'}
                        <input
                          type="color"
                          className="h-10 w-full cursor-pointer rounded border border-slate-700 bg-slate-950"
                          value={design[key]}
                          onChange={(event) => setDesign({ ...design, [key]: event.target.value })}
                        />
                      </label>
                    ))}
                  </div>
                  {(
                    [
                      ['subtitle', 'Subtítulo'],
                      ['location', 'Ubicación'],
                      ['locator', 'Grid locator'],
                      ['message', 'Mensaje de la tarjeta'],
                    ] as const
                  ).map(([key, label]) => (
                    <label key={key} className="grid gap-2 text-sm">
                      {label}
                      <input
                        className={input}
                        maxLength={
                          key === 'locator'
                            ? 20
                            : key === 'message'
                              ? 120
                              : key === 'location'
                                ? 100
                                : 80
                        }
                        value={design[key] || ''}
                        onChange={(event) => setDesign({ ...design, [key]: event.target.value })}
                      />
                    </label>
                  ))}
                </>
              ) : (
                <label className="grid gap-3 text-sm">
                  Imagen de la tarjeta
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    onChange={(event) => {
                      setUpload(event.target.files?.[0] || null)
                      setFeedback('')
                    }}
                  />
                  <span className="text-xs leading-5 text-slate-400">
                    PNG, JPEG o WebP. Hasta 2 MB y 4096 × 4096 píxeles.
                  </span>
                </label>
              )}
              <label className="flex items-start gap-3 text-sm leading-6">
                <input
                  className="mt-1"
                  type="checkbox"
                  checked={isPublic}
                  onChange={(event) => setIsPublic(event.target.checked)}
                />
                Publicar esta tarjeta en la galería
              </label>
              <p className="text-xs leading-5 text-slate-400">
                Al publicarla se mostrarán la imagen, el indicativo y la descripción. La titularidad
                debe estar verificada. Puedes conservarla privada.
              </p>
              <button
                className={`${button} w-full`}
                disabled={save.isPending || !title.trim() || (mode === 'upload' && !upload)}
                onClick={() => save.mutate()}
              >
                <Save size={17} aria-hidden="true" />{' '}
                {save.isPending ? 'Guardando…' : editing ? 'Guardar cambios' : 'Guardar tarjeta'}
              </button>
              {save.isError && <ErrorState error={save.error} />}
              {feedback && (
                <p className="text-sm text-cyan-300" role="status">
                  {feedback}
                </p>
              )}
            </section>
            <section
              className={`${panel} min-w-0 space-y-5 xl:sticky xl:top-5`}
              aria-labelledby="card-preview-title"
            >
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="card-preview-title" className="text-lg font-semibold">
                  Vista previa de tu tarjeta
                </h2>
                <span className="text-xs text-slate-400">
                  {mode === 'editor' ? '1200 × 750 px · PNG' : 'Imagen subida'}
                </span>
              </div>
              {mode === 'editor' ? (
                <>
                  <canvas
                    ref={canvas}
                    className="h-auto w-full touch-none rounded-lg border border-slate-700"
                    width={1200}
                    height={750}
                    role="img"
                    aria-label={`Vista previa de la tarjeta de ${chosen?.callsign}`}
                    onPointerDown={startDrag}
                    onPointerMove={moveDrag}
                    onPointerUp={() => {
                      drag.current = null
                    }}
                    onPointerCancel={() => {
                      drag.current = null
                    }}
                  >
                    Vista previa del diseño QSL.
                  </canvas>
                  <p className="text-sm text-slate-400">
                    Arrastra los textos en la tarjeta o ajusta su posición con los controles.
                  </p>
                  <div className="grid gap-4 sm:grid-cols-3">
                    <label className="grid gap-2 text-sm">
                      Texto seleccionado
                      <select
                        className={input}
                        value={selected}
                        onChange={(event) => setSelected(event.target.value as CardElement)}
                      >
                        {(Object.keys(elementLabels) as CardElement[]).map((key) => (
                          <option key={key} value={key}>
                            {elementLabels[key]}
                          </option>
                        ))}
                      </select>
                    </label>
                    {(['x', 'y'] as const).map((axis) => (
                      <label className="grid gap-2 text-sm" key={axis}>
                        {axis === 'x' ? 'Posición horizontal' : 'Posición vertical'}
                        <input
                          type="range"
                          min={axis === 'x' ? 30 : 60}
                          max={axis === 'x' ? 1100 : 700}
                          value={design.positions[selected][axis]}
                          onChange={(event) =>
                            setDesign({
                              ...design,
                              positions: {
                                ...design.positions,
                                [selected]: {
                                  ...design.positions[selected],
                                  [axis]: Number(event.target.value),
                                },
                              },
                            })
                          }
                        />
                      </label>
                    ))}
                  </div>
                  <button className={secondary} onClick={exportPng}>
                    <Download size={17} aria-hidden="true" /> Descargar diseño PNG
                  </button>
                  {exportError && <ErrorState error={exportError} />}
                </>
              ) : preview || editing?.image_url ? (
                <img
                  className="max-h-[600px] w-full rounded-lg bg-slate-950 object-contain"
                  src={preview || editing?.image_url}
                  alt="Vista previa de la imagen subida"
                />
              ) : (
                <EmptyState>
                  <Image className="text-cyan-400" />
                  <p>Selecciona tu tarjeta para verla aquí.</p>
                </EmptyState>
              )}
            </section>
          </div>
        </>
      )}
      <section className="mt-10" aria-labelledby="saved-cards-title">
        <h2 id="saved-cards-title" className="mb-5 text-2xl font-semibold">
          Mis tarjetas y diseños
        </h2>
        {visibility.isError && <ErrorState error={visibility.error} />}
        {remove.isError && <ErrorState error={remove.error} />}
        {saved.isPending ? (
          <LoadingState />
        ) : saved.isError ? (
          <ErrorState error={saved.error} />
        ) : saved.data.data.length ? (
          <>
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {saved.data.data.map((card) => (
                <article className={`${panel} min-w-0`} key={card.public_id}>
                  <img
                    className="aspect-[8/5] w-full rounded-lg bg-slate-950 object-contain"
                    src={card.image_url}
                    alt={`Tarjeta guardada ${card.title}`}
                    loading="lazy"
                  />
                  <h3 className="mt-4 break-words font-semibold">{card.title}</h3>
                  <p className="mt-2 text-xs text-slate-400">
                    {card.callsign} · {card.is_public ? 'Pública' : 'Privada'} ·{' '}
                    {card.source === 'editor' ? 'Diseño editable' : 'Imagen subida'}
                  </p>
                  <div className="mt-4 flex flex-wrap gap-3 text-sm">
                    <button className="text-cyan-300" onClick={() => edit(card)}>
                      {card.source === 'editor' ? 'Editar diseño' : 'Reemplazar imagen'}
                    </button>
                    <a
                      className="text-cyan-300"
                      href={card.image_url}
                      download={`${card.callsign}.png`}
                    >
                      Descargar PNG
                    </a>
                    <button
                      className="text-cyan-300"
                      disabled={visibility.isPending}
                      onClick={() => visibility.mutate(card)}
                    >
                      {card.is_public ? 'Hacer privada' : 'Publicar en galería'}
                    </button>
                    <button
                      className="text-red-300"
                      disabled={remove.isPending}
                      onClick={() => {
                        if (window.confirm('¿Eliminar esta tarjeta y su imagen guardada?'))
                          remove.mutate(card.public_id)
                      }}
                    >
                      Eliminar
                    </button>
                  </div>
                </article>
              ))}
            </div>
            <div className="mt-5 flex items-center justify-between gap-3">
              <button className={secondary} disabled={page <= 1} onClick={() => setPage(page - 1)}>
                Anterior
              </button>
              <span className="text-sm text-slate-400">
                Página {page} de {saved.data.meta.last_page}
              </span>
              <button
                className={secondary}
                disabled={page >= saved.data.meta.last_page}
                onClick={() => setPage(page + 1)}
              >
                Siguiente
              </button>
            </div>
          </>
        ) : (
          <div className={panel}>
            <EmptyState>
              Guarda tu primer diseño o sube una tarjeta para iniciar tu colección.
            </EmptyState>
          </div>
        )}
      </section>
    </>
  )
}

import type { ReactNode } from 'react'
import axios from 'axios'
import { LoaderCircle, Radio, TriangleAlert } from 'lucide-react'
export const button =
  'inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-cyan-300 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-300 disabled:opacity-50'
export const secondary =
  'inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-200 hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-cyan-300 disabled:opacity-50'
export const input =
  'w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-slate-100 focus:border-cyan-400 focus:outline-none'
export const panel = 'rounded-2xl border border-slate-800 bg-slate-900/60 p-5 md:p-7'
export function message(error: unknown) {
  if (axios.isAxiosError(error))
    return (
      Object.values(error.response?.data?.errors ?? {})
        .flat()
        .join(' ') ||
      error.response?.data?.message ||
      'No fue posible conectar con el servidor.'
    )
  return error instanceof Error ? error.message : 'Ocurrió un error.'
}
export function LoadingState() {
  return (
    <p role="status" className="flex items-center gap-3 py-10 text-slate-400">
      <LoaderCircle className="animate-spin" /> Cargando…
    </p>
  )
}
export function EmptyState({ children }: { children: ReactNode }) {
  return (
    <div className="grid justify-items-center gap-4 py-12 text-center text-slate-400">
      <Radio className="text-cyan-400" />
      {children}
    </div>
  )
}
export function ErrorState({ error, retry }: { error: unknown; retry?: () => void }) {
  return (
    <div
      role="alert"
      className="my-5 grid gap-3 rounded-lg border border-red-900 bg-red-950/30 p-4 text-red-200"
    >
      <TriangleAlert size={20} />
      <p>{message(error)}</p>
      {retry && (
        <button className={secondary} onClick={retry}>
          Reintentar
        </button>
      )}
    </div>
  )
}
const labels: Record<string, string> = {
  pending: 'Pendiente',
  candidate: 'Candidata',
  confirmed: 'Confirmado',
  disputed: 'En disputa',
  rejected: 'Rechazado',
  verified: 'Verificado',
  ready: 'Disponible',
  revoked: 'Revocada',
  sent: 'Enviado',
  failed: 'Fallido',
}
export function StatusBadge({ status }: { status: string }) {
  return (
    <span
      className={`inline-flex rounded-full border px-3 py-1 text-xs ${['confirmed', 'verified', 'ready', 'sent'].includes(status) ? 'border-cyan-800 bg-cyan-950 text-cyan-200' : ['disputed', 'rejected', 'revoked', 'failed'].includes(status) ? 'border-red-900 bg-red-950 text-red-200' : 'border-slate-700 bg-slate-800 text-slate-300'}`}
    >
      {labels[status] ?? status}
    </span>
  )
}
export function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <article className={panel}>
      <p className="text-sm text-slate-400">{label}</p>
      <p className="mt-4 text-4xl font-semibold text-cyan-300">{value}</p>
    </article>
  )
}
export function DataTable<T>({
  rows,
  columns,
  rowKey,
  label,
}: {
  rows: T[]
  columns: { label: string; render: (row: T) => ReactNode }[]
  rowKey: (row: T) => string | number
  label: string
}) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-sm">
        <caption className="sr-only">{label}</caption>
        <thead>
          <tr>
            {columns.map((c) => (
              <th
                key={c.label}
                scope="col"
                className="border-b border-slate-700 px-3 py-4 font-medium text-slate-400"
              >
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={rowKey(row)} className="border-b border-slate-800">
              {columns.map((c) => (
                <td key={c.label} className="px-3 py-4">
                  {c.render(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
export function Pagination({
  page,
  last,
  change,
}: {
  page: number
  last: number
  change: (page: number) => void
}) {
  return (
    <nav aria-label="Paginación" className="mt-6 flex flex-wrap items-center justify-between gap-4">
      <button className={secondary} disabled={page <= 1} onClick={() => change(page - 1)}>
        Anterior
      </button>
      <span className="text-sm text-slate-400">
        Página {page} de {last}
      </span>
      <button className={secondary} disabled={page >= last} onClick={() => change(page + 1)}>
        Siguiente
      </button>
    </nav>
  )
}
export function utc(value: string) {
  return new Date(value).toLocaleString('es', { timeZone: 'UTC', hour12: false }) + ' UTC'
}

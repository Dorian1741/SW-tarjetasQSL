import { Link, NavLink } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import axios from 'axios'
import { Radio, ShieldCheck } from 'lucide-react'
import { api } from '../api'
import { button } from './ui'

type PublicAccount = {
  name: string
  email_verified_at: string | null
  two_factor_enabled: boolean
  is_admin: boolean
}
export type OperatorSummary = {
  callsign: string
  biography: string | null
  country_code: string | null
  photo_url: string | null
  public_url: string
}

export function usePublicAccount() {
  return useQuery({
    queryKey: ['profile'],
    retry: false,
    queryFn: async () => {
      try {
        return (await api.get<{ data: PublicAccount }>('/profile')).data.data
      } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 401) return null
        throw error
      }
    },
  })
}

export function PublicHeader() {
  const account = usePublicAccount()
  return (
    <header className="border-b border-slate-800 bg-[#0b1427]">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
        <Link to="/" className="flex items-center gap-2 font-bold tracking-wide">
          <Radio className="text-cyan-400" aria-hidden="true" /> QSL NEXUS
        </Link>
        <nav aria-label="Navegación pública" className="flex gap-5 text-sm">
          {[
            ['/', 'Comunidad'],
            ['/directory', 'Directorio'],
            ['/cards', 'Tarjetas QSL'],
          ].map(([to, label]) => (
            <NavLink
              key={to}
              to={to}
              end
              className={({ isActive }) => (isActive ? 'text-cyan-300' : 'text-slate-300')}
            >
              {label}
            </NavLink>
          ))}
        </nav>
        <Link to="/account" className={button}>
          {account.data ? 'Mi cuenta' : 'Ingresar o crear cuenta'}
        </Link>
      </div>
    </header>
  )
}

export function OperatorAvatar({ operator }: { operator: OperatorSummary }) {
  return operator.photo_url ? (
    <img
      src={operator.photo_url}
      alt={`Foto elegida por ${operator.callsign}`}
      className="h-11 w-11 shrink-0 rounded-full object-cover"
      loading="lazy"
    />
  ) : (
    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-cyan-950 text-sm font-bold text-cyan-200">
      {operator.callsign.slice(0, 2)}
    </span>
  )
}

export function OperatorPreview({ operator }: { operator: OperatorSummary }) {
  return (
    <Link
      to={operator.public_url}
      className="flex min-w-0 items-center gap-3 rounded-xl p-2 hover:bg-slate-800"
    >
      <OperatorAvatar operator={operator} />
      <span className="min-w-0">
        <span className="flex items-center gap-2 font-semibold text-slate-100">
          <span className="truncate">{operator.callsign}</span>
          <ShieldCheck
            size={15}
            className="shrink-0 text-cyan-400"
            aria-label="Titularidad verificada"
          />
        </span>
        <span className="text-xs text-slate-400">
          {operator.country_code ? `País: ${operator.country_code}` : 'Operador de QSL Nexus'}
        </span>
      </span>
    </Link>
  )
}

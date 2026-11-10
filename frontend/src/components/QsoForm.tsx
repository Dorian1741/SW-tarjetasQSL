import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { api, mutate } from '../api'
import type { Callsign } from '../Workspace'
import { button, input, ErrorState, LoadingState, EmptyState } from './ui'
const schema = z.object({
  callsign_id: z.string().min(1, 'Selecciona un indicativo verificado'),
  contacted_callsign: z.string().trim().min(3, 'Indicativo inválido').max(32),
  started_at: z.string().min(16, 'Selecciona fecha y hora UTC'),
  ended_at: z.string(),
  frequency_khz: z
    .string()
    .regex(/^\d{1,9}(\.\d{1,3})?$/, 'Frecuencia en kHz, hasta tres decimales'),
  band: z.string().min(1),
  mode: z.string().min(1),
  rst_sent: z.string().max(10),
  rst_received: z.string().max(10),
  notes: z.string().max(5000),
})
type Values = z.infer<typeof schema>
const bands = [
  '2190m',
  '630m',
  '160m',
  '80m',
  '60m',
  '40m',
  '30m',
  '20m',
  '17m',
  '15m',
  '12m',
  '10m',
  '6m',
  '4m',
  '2m',
  '1.25m',
  '70cm',
  '33cm',
  '23cm',
  '13cm',
  '9cm',
  '6cm',
  '3cm',
]
const modes = [
  'SSB',
  'CW',
  'FM',
  'AM',
  'FT8',
  'FT4',
  'RTTY',
  'PSK31',
  'JS8',
  'DSTAR',
  'DMR',
  'C4FM',
]
export function QsoForm() {
  const navigate = useNavigate()
  const client = useQueryClient()
  const callsigns = useQuery({
    queryKey: ['verified-callsigns'],
    queryFn: async () => {
      const result: Callsign[] = []
      let page = 1
      let last = 1
      do {
        const response = (
          await api.get<{ data: Callsign[]; meta: { last_page: number } }>('/callsigns', {
            params: { page },
          })
        ).data
        result.push(...response.data)
        last = response.meta.last_page
        page++
      } while (page <= last)
      return result.filter((c) => c.verification_status === 'verified' && !c.released)
    },
  })
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      band: '20m',
      mode: 'FT8',
      ended_at: '',
      rst_sent: '',
      rst_received: '',
      notes: '',
    },
  })
  const save = useMutation({
    mutationFn: async (values: Values) => {
      const utc = (value: string) => (value.length === 16 ? value + ':00Z' : value + 'Z')
      return mutate('post', '/qsos', {
        ...values,
        callsign_id: Number(values.callsign_id),
        started_at: utc(values.started_at),
        ended_at: values.ended_at ? utc(values.ended_at) : null,
      })
    },
    onSuccess: (response) => {
      client.invalidateQueries()
      navigate(`/app/qsos/${response.data.data.id}`)
    },
  })
  if (callsigns.isPending) return <LoadingState />
  if (callsigns.isError) return <ErrorState error={callsigns.error} />
  if (!callsigns.data.length)
    return (
      <EmptyState>
        Necesitas un indicativo con titularidad verificada. Reclámalo desde tu perfil y solicita su
        revisión administrativa.
      </EmptyState>
    )
  return (
    <form className="grid gap-5 md:grid-cols-2" onSubmit={handleSubmit((v) => save.mutate(v))}>
      <label className="grid gap-2 text-sm">
        Mi indicativo
        <select className={input} {...register('callsign_id')}>
          <option value="">Seleccionar</option>
          {callsigns.data.map((c) => (
            <option key={c.id} value={c.id}>
              {c.callsign}
              {c.is_primary ? ' · principal' : ''}
            </option>
          ))}
        </select>
      </label>
      <label className="grid gap-2 text-sm">
        Indicativo de la otra estación
        <input className={input} maxLength={32} {...register('contacted_callsign')} />
      </label>
      <label className="grid gap-2 text-sm">
        Inicio: fecha y hora UTC
        <input type="datetime-local" step="1" className={input} {...register('started_at')} />
      </label>
      <label className="grid gap-2 text-sm">
        Fin UTC (opcional)
        <input type="datetime-local" step="1" className={input} {...register('ended_at')} />
      </label>
      <label className="grid gap-2 text-sm">
        Frecuencia en kHz
        <input
          inputMode="decimal"
          placeholder="14074.125"
          className={input}
          {...register('frequency_khz')}
        />
      </label>
      <label className="grid gap-2 text-sm">
        Banda
        <select className={input} {...register('band')}>
          {bands.map((v) => (
            <option key={v}>{v}</option>
          ))}
        </select>
      </label>
      <label className="grid gap-2 text-sm">
        Modo
        <select className={input} {...register('mode')}>
          {modes.map((v) => (
            <option key={v}>{v}</option>
          ))}
        </select>
      </label>
      <label className="grid gap-2 text-sm">
        RST enviado
        <input maxLength={10} className={input} {...register('rst_sent')} />
      </label>
      <label className="grid gap-2 text-sm">
        RST recibido
        <input maxLength={10} className={input} {...register('rst_received')} />
      </label>
      <label className="grid gap-2 text-sm md:col-span-2">
        Observaciones (privadas)
        <textarea rows={4} maxLength={5000} className={input} {...register('notes')} />
      </label>
      {Object.keys(errors).length > 0 && (
        <ul role="alert" className="text-sm text-red-300 md:col-span-2">
          {Object.entries(errors).map(([key, error]) => (
            <li key={key}>{error.message}</li>
          ))}
        </ul>
      )}
      {save.isError && (
        <div className="md:col-span-2">
          <ErrorState error={save.error} />
        </div>
      )}
      <button className={`${button} w-fit`} disabled={save.isPending}>
        {save.isPending ? 'Guardando…' : 'Guardar contacto'}
      </button>
    </form>
  )
}

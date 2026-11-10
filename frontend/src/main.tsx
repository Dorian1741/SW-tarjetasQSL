import React, { lazy, Suspense } from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Routes, Route, Link } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Radio, ShieldCheck, Globe } from 'lucide-react'
import './style.css'
import { LoadingState } from './components/ui'
const Account = lazy(() => import('./Account').then((m) => ({ default: m.Account })))
const AppLayout = lazy(() => import('./Workspace').then((m) => ({ default: m.AppLayout })))
const Dashboard = lazy(() => import('./Workspace').then((m) => ({ default: m.Dashboard })))
const QsoHistory = lazy(() => import('./Workspace').then((m) => ({ default: m.QsoHistory })))
const RegisterQso = lazy(() => import('./Workspace').then((m) => ({ default: m.RegisterQso })))
const QsoDetail = lazy(() => import('./Workspace').then((m) => ({ default: m.QsoDetail })))
const Matches = lazy(() => import('./Workspace').then((m) => ({ default: m.Matches })))
const Cards = lazy(() => import('./Workspace').then((m) => ({ default: m.Cards })))
const CardDetail = lazy(() => import('./Workspace').then((m) => ({ default: m.CardDetail })))
const Notifications = lazy(() => import('./Workspace').then((m) => ({ default: m.Notifications })))
const AdminCallsigns = lazy(() =>
  import('./Workspace').then((m) => ({ default: m.AdminCallsigns })),
)

const client = new QueryClient()

function Landing() {
  return (
    <div className="min-h-screen bg-[#080f20] text-slate-100">
      <header className="mx-auto flex max-w-6xl items-center gap-3 px-6 py-8">
        <Radio className="text-cyan-400" />
        <span className="text-xl font-bold tracking-wide">QSL NEXUS</span>
      </header>
      <main className="mx-auto grid max-w-6xl gap-16 px-6 py-16 md:py-28">
        <section className="max-w-3xl">
          <p className="mb-6 text-sm tracking-widest text-cyan-400">CONECTA. CONFIRMA. CONSERVA.</p>
          <h1 className="text-5xl leading-tight font-semibold md:text-7xl">
            Cada contacto,
            <br />
            <span className="text-cyan-400">una conexión.</span>
          </h1>
          <p className="mt-8 max-w-xl text-lg leading-relaxed text-slate-400">
            QSL Nexus: gestión de contactos y tarjetas QSL digitales para la comunidad internacional
            de radioaficionados.
          </p>
        </section>
        <section className="grid gap-6 md:grid-cols-3" aria-label="Objetivos del proyecto">
          {[
            [Radio, 'Tu bitácora digital', 'Contactos organizados por indicativo, banda y modo.'],
            [
              ShieldCheck,
              'Confirmación bilateral',
              'Ambas estaciones confirman la autenticidad del contacto.',
            ],
            [Globe, 'Sin fronteras', 'Indicativos internacionales y registro de horas en UTC.'],
          ].map(([Icon, title, description]) => {
            const Symbol = Icon as typeof Radio
            return (
              <article
                key={String(title)}
                className="rounded-2xl border border-slate-800 bg-slate-900/60 p-7"
              >
                <Symbol className="mb-6 text-cyan-400" />
                <h2 className="mb-3 text-lg font-semibold">{String(title)}</h2>
                <p className="leading-relaxed text-slate-400">{String(description)}</p>
              </article>
            )
          })}
        </section>
        <Link
          to="/account"
          className="w-fit rounded-lg bg-cyan-400 px-6 py-3 font-semibold text-slate-950"
        >
          Ingresar o crear cuenta
        </Link>
      </main>
    </div>
  )
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={client}>
      <BrowserRouter>
        <Suspense
          fallback={
            <main className="p-8">
              <LoadingState />
            </main>
          }
        >
          <Routes>
            <Route path="/" element={<Landing />} />
            <Route path="/account" element={<Account />} />
            <Route path="/verify/:id" element={<CardDetail publicView />} />
            <Route path="/app" element={<AppLayout />}>
              <Route index element={<Dashboard />} />
              <Route path="qsos" element={<QsoHistory />} />
              <Route path="qsos/new" element={<RegisterQso />} />
              <Route path="qsos/:id" element={<QsoDetail />} />
              <Route path="matches" element={<Matches />} />
              <Route path="cards" element={<Cards />} />
              <Route path="cards/:id" element={<CardDetail />} />
              <Route path="notifications" element={<Notifications />} />
              <Route path="admin" element={<AdminCallsigns />} />
            </Route>
            <Route
              path="*"
              element={
                <main className="p-8">
                  Página no encontrada. <Link to="/">Volver al inicio</Link>
                </main>
              }
            />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
)

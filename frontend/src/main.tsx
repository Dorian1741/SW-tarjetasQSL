import React, { lazy, Suspense } from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Routes, Route, Link } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
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

const Directory = lazy(() => import('./Directory').then((m) => ({ default: m.Directory })))
const PublicOperator = lazy(() =>
  import('./Directory').then((m) => ({ default: m.PublicOperator })),
)
const MyOperatorProfile = lazy(() =>
  import('./Directory').then((m) => ({ default: m.MyOperatorProfile })),
)
const client = new QueryClient()
const Community = lazy(() => import('./Community').then((m) => ({ default: m.Community })))
const CardGallery = lazy(() => import('./CardGallery').then((m) => ({ default: m.CardGallery })))
const CardStudio = lazy(() => import('./CardStudio').then((m) => ({ default: m.CardStudio })))

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
            <Route path="/" element={<Community />} />
            <Route path="/directory" element={<Directory />} />
            <Route path="/cards" element={<CardGallery />} />
            <Route path="/operators/*" element={<PublicOperator />} />
            <Route path="/account" element={<Account />} />
            <Route path="/verify/:id" element={<CardDetail publicView />} />
            <Route path="/app" element={<AppLayout />}>
              <Route index element={<Dashboard />} />
              <Route path="operator-profile" element={<MyOperatorProfile />} />
              <Route path="qsos" element={<QsoHistory />} />
              <Route path="qsos/new" element={<RegisterQso />} />
              <Route path="qsos/:id" element={<QsoDetail />} />
              <Route path="matches" element={<Matches />} />
              <Route path="cards" element={<Cards />} />
              <Route path="card-studio" element={<CardStudio />} />
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

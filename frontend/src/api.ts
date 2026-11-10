import axios from 'axios'

// Cookies de Sanctum; nunca se persisten tokens de sesión en localStorage.
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

export async function mutate(
  method: 'post' | 'patch' | 'delete' | 'put',
  path: string,
  data?: unknown,
) {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  return api.request({ method, url: path, data })
}

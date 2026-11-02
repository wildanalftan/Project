import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: false,
})

// Inject token on every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('khi_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Handle 401 -> force logout
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('khi_token')
      localStorage.removeItem('khi_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

import api from './axios'

export const workflowApi = {
  list: (params?: Record<string, unknown>) =>
    api.get('/workflows', { params }).then(r => r.data),

  get: (id: string) =>
    api.get(`/workflows/${id}`).then(r => r.data),

  create: (data: unknown) =>
    api.post('/workflows', data).then(r => r.data),

  update: (id: string, data: unknown) =>
    api.put(`/workflows/${id}`, data).then(r => r.data),

  delete: (id: string) =>
    api.delete(`/workflows/${id}`),

  trigger: (id: string) =>
    api.post(`/workflows/${id}/trigger`).then(r => r.data),

  rollback: (id: string, version: number) =>
    api.post(`/workflows/${id}/rollback/${version}`).then(r => r.data),

  getRuns: (id: string) =>
    api.get(`/workflows/${id}/runs`).then(r => r.data),

  getLogs: (runId: string) =>
    api.get(`/runs/${runId}/logs`).then(r => r.data),

  getHealth: () =>
    api.get('/health').then(r => r.data),
}

import { useEffect, useState } from 'react'
import { io, Socket } from 'socket.io-client'
import { useAuthStore } from '../stores/authStore'

interface StepStatus {
  stepId: string
  status: 'pending' | 'running' | 'success' | 'failed' | 'retrying'
}

export function useWorkflowSocket(runId: string | null) {
  const [stepStatuses, setStepStatuses] = useState<Record<string, StepStatus['status']>>({})
  const [socket, setSocket] = useState<Socket | null>(null)
  const token = useAuthStore.getState().token

  useEffect(() => {
    if (!runId) return

    const s = io('http://localhost:6001', {
      auth: { token },
      transports: ['websocket'],
    })

    // Event asinkron: dipanggil HANYA ketika koneksi sudah berhasil terjadi
    s.on('connect', () => {
      s.emit('subscribe', { channel: `workflow-run.${runId}` })
      
      // ✅ AMAN: State di-update secara asinkron, tidak akan memicu cascading render
      setSocket(s)
    })

    s.on('step.updated', (data: StepStatus) => {
      setStepStatuses(prev => ({ ...prev, [data.stepId]: data.status }))
    })

    return () => {
      s.disconnect()
      setSocket(null)
    }
  }, [runId, token])

  return { stepStatuses, socket }
}
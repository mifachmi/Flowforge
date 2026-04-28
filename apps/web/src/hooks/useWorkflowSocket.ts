/* eslint-disable no-unexpected-multiline */
/* eslint-disable @typescript-eslint/no-explicit-any */
import { useEffect, useState } from 'react'
import { useAuthStore } from '../stores/authStore'

type StepStatus = 'pending' | 'running' | 'success' | 'failed' | 'retrying'

export function useWorkflowSocket(runId: string | null) {
  const [stepStatuses, setStepStatuses] = useState<Record<string, StepStatus>>({})
  const [runStatus, setRunStatus] = useState<string>('pending')

  useEffect(() => {
    if (!runId) return
    
    const token = useAuthStore.getState().token
    const url = `http://localhost:8000/api/runs/${runId}/stream?token=${token}`

    console.log('[SSE] Connecting to:', url)  // ← tambah ini

    const eventSource = new EventSource(url)
    eventSource.onopen = () => console.log('[SSE] Connected!')  // ← tambah ini

    eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        console.log('[SSE] Raw event:', event.data)  // ← tambah ini
        // ─── Format yang dikirim backend saat ini ───────────────────────
        // { run_status: "success", step_statuses: { step1: "success", ... } }
        if (data.step_statuses) {
          setStepStatuses(data.step_statuses)
        }

        if (data.run_status) {
          setRunStatus(data.run_status)
        }

        if (['success', 'failed', 'timeout'].includes(data.run_status)) {
          eventSource.close()
        }
      } catch (e) {
        console.error('SSE parse error:', e)
      }
    }

    eventSource.onerror = (e) => {
      console.error('[SSE] Error:', e)
      eventSource.close()
    }

    return () => {
      eventSource.close()
    }
  }, [runId])

  return { stepStatuses, runStatus }
}

/* eslint-disable no-unexpected-multiline */
/* eslint-disable @typescript-eslint/no-explicit-any */
import { useEffect, useState } from 'react'
import { useAuthStore } from '../stores/authStore'

type StepStatus = 'pending' | 'running' | 'success' | 'failed' | 'retrying'

export function useWorkflowSocket(runId: string | null) {
  const [stepStatuses, setStepStatuses] = useState<Record<string, StepStatus>>({})
  const [runStatus, setRunStatus] = useState<string>('pending')
  const token = useAuthStore.getState().token

  useEffect(() => {
    if (!runId || !token) return

    const eventSource = new EventSource(`/api/runs/${runId}/stream?token=${token}`)

    eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        setRunStatus(data.run_status)
        setStepStatuses(data.step_statuses ?? {})

        if (['success', 'failed', 'timeout'].includes(data.run_status)) {
          eventSource.close()
        }
      } catch (e) {
        console.error('SSE parse error:', e)
      }
    }

    eventSource.onerror = () => {
      eventSource.close()
    }

    return () => {
      eventSource.close()
    }
  }, [runId, token])

  return { stepStatuses, runStatus }
}

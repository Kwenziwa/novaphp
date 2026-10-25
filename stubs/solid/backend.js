export async function backendStatus() {
  try { const response = await fetch('/api/health'); if (!response.ok) return 'Backend unavailable'; const data = await response.json(); return data.status === 'ok' ? 'PHP backend connected' : 'Unexpected backend response' }
  catch { return 'Start php nova serve to connect the backend' }
}

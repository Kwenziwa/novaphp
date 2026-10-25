import { useState, useEffect } from 'react'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = useState(0)
  const [status, setStatus] = useState('Checking backend…')
  useEffect(() => { let active = true; backendStatus().then(value => { if (active) setStatus(value) }); return () => { active = false } }, [])
  return <main><small>NOVAPHP + REACT</small><h1>Your next idea starts here.</h1><p>A clean React frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button onClick={() => setCount(count + 1)}>Count: {count}</button><footer>{status} · <a href="/docs">Documentation</a></footer></main>
}

import { useState, useEffect } from 'preact/hooks'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = useState(0)
  const [status, setStatus] = useState('Checking backend…')
  useEffect(() => { backendStatus().then(setStatus) }, [])
  return <main><small>NOVAPHP + PREACT</small><h1>Your next idea starts here.</h1><p>A clean Preact frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button onClick={() => setCount(count + 1)}>Count: {count}</button><footer>{status} · <a href="/docs">Documentation</a></footer></main>
}

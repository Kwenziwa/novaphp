import { useState, useEffect } from 'preact/hooks'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = useState(0)
  const [status, setStatus] = useState('Checking backend…')
  useEffect(() => { backendStatus().then(setStatus) }, [])
  return <main className="container my-5 p-5 bg-white border rounded-4 shadow-sm"><small className="fw-bold text-success">NOVAPHP + PREACT</small><h1 className="display-4 fw-bold my-4">Your next idea starts here.</h1><p className="lead text-secondary mb-4">A clean Preact frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button className="btn btn-success btn-lg" onClick={() => setCount(count + 1)}>Count: {count}</button><footer className="mt-4 text-secondary small">{status} · <a className="link-success" href="/docs">Documentation</a></footer></main>
}

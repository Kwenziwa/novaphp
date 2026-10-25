import { useState, useEffect } from 'react'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = useState(0)
  const [status, setStatus] = useState('Checking backend…')
  useEffect(() => { let active = true; backendStatus().then(value => { if (active) setStatus(value) }); return () => { active = false } }, [])
  return <main className="container my-5 p-5 bg-white border rounded-4 shadow-sm"><small className="fw-bold text-success">NOVAPHP + REACT</small><h1 className="display-4 fw-bold my-4">Your next idea starts here.</h1><p className="lead text-secondary mb-4">A clean React frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button className="btn btn-success btn-lg" onClick={() => setCount(count + 1)}>Count: {count}</button><footer className="mt-4 text-secondary small">{status} · <a className="link-success" href="/docs">Documentation</a></footer></main>
}

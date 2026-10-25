import { useState, useEffect } from 'react'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = useState(0)
  const [status, setStatus] = useState('Checking backend…')
  useEffect(() => { let active = true; backendStatus().then(value => { if (active) setStatus(value) }); return () => { active = false } }, [])
  return <main className="mx-auto my-16 max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><small className="font-semibold tracking-widest text-emerald-700">NOVAPHP + REACT</small><h1 className="my-6 text-5xl font-bold tracking-tight text-slate-900">Your next idea starts here.</h1><p className="mb-8 text-lg text-slate-600">A clean React frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button className="rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800" onClick={() => setCount(count + 1)}>Count: {count}</button><footer className="mt-8 text-sm text-slate-500">{status} · <a className="underline text-emerald-700" href="/docs">Documentation</a></footer></main>
}

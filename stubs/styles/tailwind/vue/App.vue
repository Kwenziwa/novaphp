<script setup>
import { ref, onMounted } from 'vue'
import { backendStatus } from './backend.js'
const count = ref(0)
const status = ref('Checking backend…')
onMounted(async () => { status.value = await backendStatus() })
</script>
<template><main class="mx-auto my-16 max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><small class="font-semibold tracking-widest text-emerald-700">NOVAPHP + VUE</small><h1 class="my-6 text-5xl font-bold tracking-tight text-slate-900">Your next idea starts here.</h1><p class="mb-8 text-lg text-slate-600">A clean Vue frontend with a NovaPHP backend. Edit App.vue to begin.</p><button class="rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800" @click="count++">Count: {{ count }}</button><footer class="mt-8 text-sm text-slate-500">{{ status }} · <a class="underline text-emerald-700" href="/docs">Documentation</a></footer></main></template>

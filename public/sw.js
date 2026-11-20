const CACHE_NAME = 'paathnexus-shell-v2';
const APP_SHELL_FILES = ['/manifest.webmanifest', '/paathnexus-icon.svg'];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const response = await fetch('/');
    if (!response.ok) throw new Error('The PaathNexus app shell could not be loaded.');
    const cache = await caches.open(CACHE_NAME);
    await cache.put('/', response.clone());
    const html = await response.text();
    const entryAssets = [...html.matchAll(/(?:src|href)=["'](\/assets\/[^"']+\.js)["']/g)].map((match) => match[1]);
    const lazyAssets = new Set();
    for (const entryAsset of entryAssets) {
      const bundleResponse = await fetch(entryAsset);
      if (!bundleResponse.ok) throw new Error('A PaathNexus application bundle could not be loaded.');
      const bundle = await bundleResponse.text();
      for (const match of bundle.matchAll(/(?:assets\/|\.\/)([\w.-]+\.(?:js|css))/g)) {
        lazyAssets.add(`/assets/${match[1]}`);
      }
    }
    const htmlAssets = [...html.matchAll(/(?:src|href)=["'](\/assets\/[^"']+)["']/g)].map((match) => match[1]);
    await cache.addAll([...new Set([...APP_SHELL_FILES, ...htmlAssets, ...lazyAssets])]);
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((names) => Promise.all(names.filter((name) => name.startsWith('paathnexus-') && name !== CACHE_NAME).map((name) => caches.delete(name)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;
  if (request.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const response = await fetch(request);
        if (response.ok) await (await caches.open(CACHE_NAME)).put('/', response.clone());
        return response;
      } catch {
        return (await caches.match(new URL('/', self.location.origin).href, { ignoreVary: true })) || Response.error();
      }
    })());
    return;
  }
  if (APP_SHELL_FILES.includes(url.pathname)) {
    event.respondWith(caches.match(url.href, { ignoreVary: true }).then((cached) => cached || fetch(request)));
    return;
  }
  if (url.pathname.startsWith('/assets/')) {
    event.respondWith((async () => {
      const cached = await caches.match(url.href, { ignoreVary: true });
      if (cached) return cached;
      try {
        const response = await fetch(request);
        if (response.ok) await (await caches.open(CACHE_NAME)).put(url.href, response.clone());
        return response;
      } catch {
        return Response.error();
      }
    })());
  }
});

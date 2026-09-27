/* sw.js — cache the app shell so the SPA-like module pages load offline.
   API and AJAX requests fall through to the network first (with cache fallback where safe). */
   const CACHE = 'srp-shell-v1';
   const SHELL = [
     '/',
     '/offline.html',
     '/assets/css/custom.css',
     '/assets/css/dark.css',
     '/assets/js/app.js'
   ];
   
   self.addEventListener('install', e => {
     e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
   });
   
   self.addEventListener('activate', e => {
     e.waitUntil(
       caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
         .then(() => self.clients.claim())
     );
   });
   
   self.addEventListener('fetch', event => {
     const req = event.request;
     const url = new URL(req.url);
   
     // Never intercept API, AJAX list endpoints, or webhooks
     if (url.pathname.startsWith('/api/') || url.pathname.includes('/ajax') || url.pathname.includes('webhook')) {
       return;
     }
   
     // HTML navigation requests: network-first, fall back to offline shell
     if (req.mode === 'navigate') {
       event.respondWith(
         fetch(req).catch(() => caches.match('/offline.html'))
       );
       return;
     }
   
     // Static assets (CSS/JS/fonts): stale-while-revalidate
     if (/(\.css|\.js|\.woff2?|\.svg|\.png|\.webp)$/i.test(url.pathname)) {
       event.respondWith(
         caches.open(CACHE).then(cache =>
           cache.match(req).then(cached => {
             const fetchPromise = fetch(req).then(res => {
               if (res.ok) cache.put(req, res.clone());
               return res;
             }).catch(() => cached);
             return cached || fetchPromise;
           })
         )
       );
       return;
     }
   
     // Everything else: network, fall back to cache
     event.respondWith(fetch(req).catch(() => caches.match(req)));
   });
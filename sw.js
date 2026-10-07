self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open('invoice-erp-store-v2').then((cache) => cache.addAll([
      '/invoice-erp/',
      '/invoice-erp/login.php',
      '/invoice-erp/assets/css/style.css',
      '/invoice-erp/assets/mslogo.png'
    ])),
  );
});

self.addEventListener('fetch', (e) => {
  e.respondWith(
    caches.match(e.request).then((response) => response || fetch(e.request)),
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keyList) => {
      return Promise.all(
        keyList.map((key) => {
          if (key !== 'invoice-erp-store-v2') {
            return caches.delete(key);
          }
        })
      );
    })
  );
});

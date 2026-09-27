/* assets/js/modules/field-sync.js — offline-friendly caching + sync queue.
   Uses localStorage (broad support) as the primary store; the schema also has a
   `sync_queue` table on the server, which this JS pushes to on reconnect. */
   (function () {
    'use strict';
    if (!window.SRP_FIELD) return;
    const { userId, syncUrl, cacheKey, maintenance, inspections } = window.SRP_FIELD;
  
    // 1. Cache current jobs locally so the inspector/technician can work offline.
    const payload = {
      cachedAt: new Date().toISOString(),
      maintenance, inspections
    };
    try { localStorage.setItem(cacheKey, JSON.stringify(payload)); } catch (e) { /* quota */ }
  
    // 2. Offline banner
    const banner = document.getElementById('offlineBanner');
    const updateOnline = () => { if (banner) banner.style.display = navigator.onLine ? 'none' : 'block'; };
    window.addEventListener('online',  () => { updateOnline(); flushQueue(); });
    window.addEventListener('offline', updateOnline);
    updateOnline();
  
    // 3. Sync queue — writes flow through here; on success we remove from queue.
    const QUEUE_KEY = cacheKey + '_queue';
  
    window.srpEnqueue = function (entityType, entityId, data) {
      const q = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
      q.push({
        id: 'q_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        entity_type: entityType,
        entity_id: entityId,
        payload: data,
        created_at: new Date().toISOString()
      });
      localStorage.setItem(QUEUE_KEY, JSON.stringify(q));
      flushQueue();
    };
  
    function flushQueue() {
      if (!navigator.onLine) return;
      const q = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
      if (!q.length) return;
      const item = q[0];
  
      srpFetch(syncUrl, {
        method: 'POST',
        body: { item }
      })
      .then(r => r.json())
      .then(res => {
        if (res.ok) {
          const remaining = q.slice(1);
          localStorage.setItem(QUEUE_KEY, JSON.stringify(remaining));
          srpToast('success', 'Synced ' + item.entity_type + ' #' + item.entity_id);
          if (remaining.length) flushQueue();
        } else {
          srpToast('error', 'Sync failed: ' + (res.error || 'unknown'));
        }
      })
      .catch(() => { /* keep in queue; will retry on next 'online' event */ });
    }
  
    window.srpQueueSize = () => JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]').length;
  })();
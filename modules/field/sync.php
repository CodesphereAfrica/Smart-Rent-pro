/* assets/js/modules/field-sync.js — offline-friendly caching + sync queue.
   Uses localStorage (broad support) as the primary store; the schema also has a
   `sync_queue` table on the server, which this JS pushes to on reconnect. */
(function () {
  'use strict';
  if (!window.SRP_FIELD) return;
  const { userId, syncUrl, cacheKey, maintenance, inspections } = window.SRP_FIELD;

  // 1. Cache current jobs locally so the inspector/technician can work offline.
  const
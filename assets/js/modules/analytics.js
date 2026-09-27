/* assets/js/modules/analytics.js — all Chart.js visualisations for the analytics hub.
   Reads its data from the #srpAnalyticsData JSON script tag. */
   (function () {
    'use strict';
    const el = document.getElementById('srpAnalyticsData');
    if (!el || !window.Chart) return;
    const data = JSON.parse(el.textContent);
  
    // Read theme tokens once so charts adapt to light/dark.
    const tokens = getComputedStyle(document.documentElement);
    const primary = '#4f46e5';
    const accent  = '#06b6d4';
    const success = '#10b981';
    const warning = '#f59e0b';
    const danger  = '#ef4444';
    const purple  = '#8b5cf6';
    const pink    = '#ec4899';
    const muted   = (tokens.getPropertyValue('--srp-muted') || '#64748b').trim();
    const grid    = (tokens.getPropertyValue('--srp-border') || '#e6e8f0').trim();
    const text    = (tokens.getPropertyValue('--srp-text') || '#0f172a').trim();
  
    const baseOptions = {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 700, easing: 'easeOutQuart' },
      plugins: {
        legend: { labels: { color: muted, boxWidth: 12, padding: 12 } },
        tooltip: {
          backgroundColor: 'rgba(15,23,42,.9)',
          padding: 10,
          cornerRadius: 8
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: muted } },
        y: { grid: { color: grid }, ticks: { color: muted } }
      }
    };
  
    // ---------- Occupancy & Collection Trend (dual axis line) ----------
    const trendCtx = document.getElementById('chartTrend');
    if (trendCtx && data.snapshots.length) {
      const labels = data.snapshots.map(s => s.date);
      const occSeries  = data.snapshots.map(s => s.occ !== null ? Number(s.occ) : null);
      const collSeries = data.snapshots.map(s => s.coll !== null ? Number(s.coll) : null);
  
      // Gradient fill for the occupancy line
      const ctx = trendCtx.getContext('2d');
      const g = ctx.createLinearGradient(0, 0, 0, 300);
      g.addColorStop(0, 'rgba(79,70,229,.28)');
      g.addColorStop(1, 'rgba(79,70,229,0)');
  
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Occupancy %',
              data: occSeries,
              borderColor: primary,
              backgroundColor: g,
              fill: true,
              tension: 0.35,
              borderWidth: 2,
              pointRadius: 2,
              pointHoverRadius: 4,
              spanGaps: true,
              yAxisID: 'y'
            },
            {
              label: 'Collection %',
              data: collSeries,
              borderColor: accent,
              backgroundColor: 'transparent',
              borderDash: [4, 4],
              tension: 0.35,
              borderWidth: 2,
              pointRadius: 2,
              pointHoverRadius: 4,
              spanGaps: true,
              yAxisID: 'y'
            }
          ]
        },
        options: {
          ...baseOptions,
          plugins: { ...baseOptions.plugins, legend: { display: true, labels: { color: muted } } },
          scales: {
            x: { grid: { display: false }, ticks: { color: muted } },
            y: { grid: { color: grid }, ticks: { color: muted, callback: v => v + '%' }, suggestedMin: 0, suggestedMax: 100 }
          }
        }
      });
    }
  
    // ---------- Unit Status Doughnut ----------
    const occCtx = document.getElementById('chartOccupancy');
    if (occCtx) {
      new Chart(occCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Occupied', 'Available', 'Reserved', 'In Maintenance', 'Other'],
          datasets: [{
            data: [
              data.occupancy.occupied,
              data.occupancy.available,
              data.occupancy.reserved,
              data.occupancy.maintenance,
              data.occupancy.other
            ],
            backgroundColor: [primary, '#c7d2fe', warning, danger, muted],
            borderWidth: 0,
            hoverOffset: 6
          }]
        },
        options: {
          ...baseOptions,
          cutout: '68%',
          plugins: { legend: { position: 'bottom', labels: { color: muted, boxWidth: 10, padding: 10 } } }
        }
      });
    }
  
    // ---------- Arrears Aging Bar ----------
    const agingCtx = document.getElementById('chartAging');
    if (agingCtx) {
      new Chart(agingCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['1–30d', '31–60d', '61–90d', '90+d'],
          datasets: [{
            label: 'Outstanding',
            data: [data.aging.b1, data.aging.b2, data.aging.b3, data.aging.b4],
            backgroundColor: [success, warning, '#f97316', danger],
            borderRadius: 8,
            maxBarThickness: 60
          }]
        },
        options: {
          ...baseOptions,
          plugins: { ...baseOptions.plugins, legend: { display: false } }
        }
      });
    }
  
    // ---------- Maintenance Funnel Bar ----------
    const funnelCtx = document.getElementById('chartFunnel');
    if (funnelCtx) {
      new Chart(funnelCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['New', 'Assigned', 'In Progress', 'Waiting', 'Completed', 'Verified', 'Closed'],
          datasets: [{
            label: 'Requests',
            data: [
              data.funnel.new, data.funnel.assigned, data.funnel.in_progress,
              data.funnel.waiting_for_parts, data.funnel.completed,
              data.funnel.verified, data.funnel.closed
            ],
            backgroundColor: [
              '#94a3b8', primary, accent, warning, success, '#22c55e', '#16a34a'
            ],
            borderRadius: 6,
            maxBarThickness: 44
          }]
        },
        options: {
          ...baseOptions,
          plugins: { ...baseOptions.plugins, legend: { display: false } }
        }
      });
    }
  
    // ---------- Lease Expirations ----------
    const expCtx = document.getElementById('chartExpiry');
    if (expCtx) {
      new Chart(expCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['Next 30d', '31–60d', '61–90d'],
          datasets: [{
            label: 'Leases expiring',
            data: [data.expiry.d30, data.expiry.d60, data.expiry.d90],
            backgroundColor: [danger, warning, primary],
            borderRadius: 8,
            maxBarThickness: 60
          }]
        },
        options: {
          ...baseOptions,
          plugins: { ...baseOptions.plugins, legend: { display: false } }
        }
      });
    }
  
    // ---------- Revenue vs Expenses ----------
    const revCtx = document.getElementById('chartRevenue');
    if (revCtx) {
      // Take the last 6 snapshot entries
      const last6 = data.snapshots.slice(-6);
      new Chart(revCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: last6.map(s => s.date),
          datasets: [
            {
              label: 'Revenue',
              data: last6.map(s => s.collected),
              backgroundColor: primary,
              borderRadius: 6,
              maxBarThickness: 40
            },
            {
              label: 'Expenses',
              data: last6.map(s => s.expenses),
              backgroundColor: danger,
              borderRadius: 6,
              maxBarThickness: 40
            }
          ]
        },
        options: {
          ...baseOptions,
          scales: {
            x: { stacked: false, grid: { display: false }, ticks: { color: muted } },
            y: { stacked: false, grid: { color: grid }, ticks: { color: muted } }
          }
        }
      });
    }
  })();
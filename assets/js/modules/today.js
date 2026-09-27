/* assets/js/modules/today.js — Chart.js visualisations for the Today screen. */
(function () {
    'use strict';
    const dataEl = document.getElementById('srpDashboardData');
    if (!dataEl || !window.Chart) return;
  
    const data = JSON.parse(dataEl.textContent);
  
    function themeColor(name, fallback) {
      const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
      return v || fallback;
    }
  
    const primary = '#4f46e5';
    const success = '#10b981';
    const warning = '#f59e0b';
    const danger  = '#ef4444';
    const muted   = themeColor('--srp-muted', '#64748b');
    const gridCol = themeColor('--srp-border', '#e6e8f0');
  
    // Revenue trend (line with gradient fill)
    const trendCanvas = document.getElementById('chartRevenueTrend');
    if (trendCanvas) {
      const ctx = trendCanvas.getContext('2d');
      const g = ctx.createLinearGradient(0, 0, 0, 240);
      g.addColorStop(0, 'rgba(79,70,229,.35)');
      g.addColorStop(1, 'rgba(79,70,229,0)');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.trendLabels,
          datasets: [{
            label: 'Collected',
            data: data.trendValues,
            borderColor: primary,
            backgroundColor: g,
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: ctx => ' ' + new Intl.NumberFormat('en-RW').format(ctx.parsed.y) + ' RWF'
              }
            }
          },
          scales: {
            x: { grid: { display: false }, ticks: { color: muted } },
            y: { grid: { color: gridCol }, ticks: { color: muted } }
          },
          animation: { duration: 700, easing: 'easeOutQuart' }
        }
      });
    }
  
    // Occupancy doughnut
    const occCanvas = document.getElementById('chartOccupancy');
    if (occCanvas) {
      new Chart(occCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Occupied', 'Vacant'],
          datasets: [{
            data: [data.occupancy.occupied, data.occupancy.vacant],
            backgroundColor: [primary, '#e6e8f0'],
            borderWidth: 0,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: {
            legend: { position: 'bottom', labels: { color: muted, boxWidth: 12 } }
          },
          animation: { duration: 700, easing: 'easeOutQuart' }
        }
      });
    }
  })();
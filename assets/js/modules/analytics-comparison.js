/* assets/js/modules/analytics-comparison.js — Chart.js visualisations for the property comparison page. */
(function () {
    'use strict';
    const el = document.getElementById('srpComparisonData');
    if (!el || !window.Chart) return;
    const data = JSON.parse(el.textContent);
    if (!data.labels || !data.labels.length) return;
  
    const tokens = getComputedStyle(document.documentElement);
    const primary = '#4f46e5';
    const success = '#10b981';
    const warning = '#f59e0b';
    const danger  = '#ef4444';
    const muted   = (tokens.getPropertyValue('--srp-muted') || '#64748b').trim();
    const grid    = (tokens.getPropertyValue('--srp-border') || '#e6e8f0').trim();
  
    const baseOptions = {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 700 },
      plugins: {
        legend: { labels: { color: muted, boxWidth: 12 } }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: muted } },
        y: { grid: { color: grid }, ticks: { color: muted } }
      }
    };
  
    // Revenue vs Expenses
    const revCtx = document.getElementById('chartCompareRevenue');
    if (revCtx) {
      new Chart(revCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [
            { label: 'Revenue',  data: data.revenue,  backgroundColor: primary, borderRadius: 6, maxBarThickness: 60 },
            { label: 'Expenses', data: data.expenses, backgroundColor: danger,  borderRadius: 6, maxBarThickness: 60 }
          ]
        },
        options: baseOptions
      });
    }
  
    // Occupancy
    const occCtx = document.getElementById('chartCompareOccupancy');
    if (occCtx) {
      new Chart(occCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Occupancy %',
            data: data.occupancy,
            backgroundColor: success,
            borderRadius: 6,
            maxBarThickness: 80
          }]
        },
        options: {
          ...baseOptions,
          plugins: { ...baseOptions.plugins, legend: { display: false } },
          scales: {
            ...baseOptions.scales,
            y: { grid: { color: grid }, ticks: { color: muted, callback: v => v + '%' }, suggestedMax: 100 }
          }
        }
      });
    }
  
    // Arrears
    const arrCtx = document.getElementById('chartCompareArrears');
    if (arrCtx) {
      new Chart(arrCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Arrears',
            data: data.arrears,
            backgroundColor: warning,
            borderRadius: 6,
            maxBarThickness: 80
          }]
        },
        options: {
          ...baseOptions,
          plugins: { ...baseOptions.plugins, legend: { display: false } }
        }
      });
    }
  })();
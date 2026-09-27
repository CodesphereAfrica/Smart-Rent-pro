(function () {
    const dataEl = document.getElementById('srpAccountantData');
    if (!dataEl || !window.Chart) return;
    const data = JSON.parse(dataEl.textContent);
  
    const grid = getComputedStyle(document.documentElement).getPropertyValue('--srp-border').trim() || '#e6e8f0';
    const muted = getComputedStyle(document.documentElement).getPropertyValue('--srp-muted').trim() || '#64748b';
  
    const agingCtx = document.getElementById('chartAging');
    if (agingCtx) {
      new Chart(agingCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['1–30', '31–60', '61–90', '90+'],
          datasets: [{
            label: 'Outstanding',
            data: [data.aging.b1, data.aging.b2, data.aging.b3, data.aging.b4].map(Number),
            backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'],
            borderRadius: 6
          }]
        },
        options: {
          plugins: { legend: { display: false } },
          scales: { y: { grid: { color: grid }, ticks: { color: muted } }, x: { grid: { display: false }, ticks: { color: muted } } },
          animation: { duration: 700 }
        }
      });
    }
  
    const expCtx = document.getElementById('chartExpenses');
    if (expCtx && data.expense.length) {
      new Chart(expCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.expense.map(e => e.label),
          datasets: [{
            label: 'Spend',
            data: data.expense.map(e => e.value),
            backgroundColor: '#4f46e5',
            borderRadius: 6
          }]
        },
        options: {
          indexAxis: 'y',
          plugins: { legend: { display: false } },
          scales: { x: { grid: { color: grid }, ticks: { color: muted } }, y: { grid: { display: false }, ticks: { color: muted } } },
          animation: { duration: 700 }
        }
      });
    }
  })();
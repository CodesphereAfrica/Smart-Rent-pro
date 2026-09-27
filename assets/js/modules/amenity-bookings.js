/* assets/js/modules/amenity-bookings.js — server-side DataTable + filters. */
(function () {
    'use strict';
    const table = document.getElementById('srpAmenityBookingsTable');
    if (!table || !window.jQuery) return;
  
    const selAmenity = document.getElementById('fltAmenity');
    const selTenant  = document.getElementById('fltTenant');
    const selStatus  = document.getElementById('fltStatus');
  
    const dt = jQuery(table).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/amenity-bookings/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.amenity_id = selAmenity ? selAmenity.value : '';
          d.tenant_id  = selTenant  ? selTenant.value  : '';
          d.status     = selStatus  ? selStatus.value  : '';
        }
      },
      columns: [
        { data: 'amenity' },
        { data: 'tenant' },
        { data: 'date' },
        { data: 'time' },
        { data: 'fee' },
        { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[2, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    // Hydrate filters
    const hydrate = document.getElementById('fltAmenity');
    if (hydrate && hydrate.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/amenity-bookings/ajax_list.php?meta=filters')
        .then(r => r.json())
        .then(d => {
          (d.amenities || []).forEach(a => {
            const o = document.createElement('option');
            o.value = a.id;
            o.textContent = a.name + (a.property_name ? ' — ' + a.property_name : '');
            selAmenity.appendChild(o);
          });
          if (selTenant) {
            (d.tenants || []).forEach(t => {
              const o = document.createElement('option');
              o.value = t.id;
              o.textContent = t.first_name + ' ' + t.last_name;
              selTenant.appendChild(o);
            });
          }
        })
        .catch(() => {});
    }
  
    if (selAmenity) selAmenity.addEventListener('change', () => dt.ajax.reload());
    if (selTenant)  selTenant.addEventListener('change',  () => dt.ajax.reload());
    if (selStatus)  selStatus.addEventListener('change',  () => dt.ajax.reload());
  })();
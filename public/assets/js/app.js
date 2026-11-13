/* MWU RPEE shared frontend helpers (vanilla JS only). */
(function () {
  'use strict';

  // Mobile navigation drawer.
  var toggle = document.getElementById('navToggle');
  var backdrop = document.getElementById('navBackdrop');
  function closeNav() {
    document.body.classList.remove('nav-open');
    if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
  }
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  if (backdrop) { backdrop.addEventListener('click', closeNav); }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeNav(); }
  });

  // Attach confirm() to elements with data-confirm.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm')) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    }
  });

  // Tiny fetch helper for future AJAX endpoints.
  window.mwuPost = function (url, data) {
    var token = document.querySelector('meta[name="csrf-token"]');
    var body = new URLSearchParams(data || {});
    return fetch(url, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': token ? token.content : '',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  };

  window.mwuGet = function (url, data) {
    var params = new URLSearchParams(data || {});
    var fullUrl = url + (url.includes('?') ? '&' : '?') + params.toString();
    return fetch(fullUrl, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  };

  // Chart.js rendering for dashboard
  document.addEventListener('DOMContentLoaded', function() {
    var pipelineCtx = document.getElementById('pipelineChart');
    if (pipelineCtx && typeof pipelineData !== 'undefined') {
      new Chart(pipelineCtx, {
        type: 'bar',
        data: {
          labels: pipelineData.map(function(d) { return d.label; }),
          datasets: [{
            label: 'Applications',
            data: pipelineData.map(function(d) { return d.count; }),
            backgroundColor: '#7a1f2b'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
      });
    }
  });
})();

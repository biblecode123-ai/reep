/* =============================================================================
   MWU REEP — Frontend Application Scripts (Vanilla JS)
   Madda Walabu University · Integrated Research Management System
   ========================================================================== */

(function () {
  'use strict';

  // 1. Mobile Navigation Off-Canvas Drawer
  var navToggle = document.getElementById('navToggle');
  var navBackdrop = document.getElementById('navBackdrop');

  function closeNav() {
    document.body.classList.remove('nav-open');
    if (navToggle) { navToggle.setAttribute('aria-expanded', 'false'); }
  }

  if (navToggle) {
    navToggle.addEventListener('click', function () {
      var isOpen = document.body.classList.toggle('nav-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  if (navBackdrop) {
    navBackdrop.addEventListener('click', closeNav);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeNav();
      closeAllModals();
    }
  });

  // 2. Confirmation helper on data-confirm attributes
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm')) {
      var msg = form.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    }
  });

  // 3. Tab switching helper
  document.addEventListener('click', function (e) {
    var tabLink = e.target.closest('[data-tab-target]');
    if (!tabLink) return;
    e.preventDefault();
    var targetSelector = tabLink.getAttribute('data-tab-target');
    var container = tabLink.closest('.tab-container') || document;
    
    // Deactivate siblings
    var navTabs = tabLink.closest('.nav-tabs');
    if (navTabs) {
      navTabs.querySelectorAll('.nav-tab-item').forEach(function (tab) {
        tab.classList.remove('active');
      });
    }
    tabLink.classList.add('active');

    // Show target content
    container.querySelectorAll('.tab-content-panel').forEach(function (panel) {
      panel.style.display = 'none';
    });
    var targetPanel = document.querySelector(targetSelector);
    if (targetPanel) {
      targetPanel.style.display = 'block';
    }
  });

  // 4. Modal Dialogs Helper
  function closeAllModals() {
    document.querySelectorAll('.modal-overlay.active').forEach(function (m) {
      m.classList.remove('active');
    });
  }

  window.openModal = function (modalId) {
    var modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
  };

  window.closeModal = function (modalId) {
    var modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
  };

  document.addEventListener('click', function (e) {
    if (e.target.matches('.modal-overlay') || e.target.matches('[data-modal-close]')) {
      closeAllModals();
    }
  });

  // 5. Evaluation Form Live Score Calculator
  function recalculateEvaluationScores() {
    var scoreInputs = document.querySelectorAll('.eval-score-input');
    if (!scoreInputs.length) return;

    var totalWeighted = 0;
    var totalMax = 0;

    scoreInputs.forEach(function (input) {
      var val = parseFloat(input.value) || 0;
      var max = parseFloat(input.getAttribute('data-max')) || 0;
      var clamped = Math.min(Math.max(0, val), max);
      if (val !== clamped) {
        input.value = clamped;
      }
      totalWeighted += clamped;
      totalMax += max;
    });

    var scoreDisplay = document.getElementById('totalScoreDisplay');
    if (scoreDisplay) {
      scoreDisplay.textContent = totalWeighted.toFixed(2);
    }

    var percentageDisplay = document.getElementById('scorePercentageDisplay');
    if (percentageDisplay && totalMax > 0) {
      var pct = (totalWeighted / totalMax) * 100;
      percentageDisplay.textContent = pct.toFixed(1) + '%';
      
      var badge = document.getElementById('passFailBadge');
      if (badge) {
        if (pct >= 50) {
          badge.className = 'badge badge-ok';
          badge.textContent = 'PASSING (>= 50%)';
        } else {
          badge.className = 'badge badge-danger';
          badge.textContent = 'BELOW PASS ( < 50%)';
        }
      }
    }
  }

  document.addEventListener('input', function (e) {
    if (e.target.matches('.eval-score-input')) {
      recalculateEvaluationScores();
    }
  });

  // 6. Generic POST & GET Fetch Helpers
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

  // 7. Auto initialize live score recalculation on page load if inputs exist
  document.addEventListener('DOMContentLoaded', function () {
    recalculateEvaluationScores();
  });
})();

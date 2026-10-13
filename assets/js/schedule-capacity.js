(function () {
  'use strict';

  var hallSelect = document.getElementById('hall_id');
  var capacityPanel = document.getElementById('generalAdmissionSettings');
  var capacityInput = document.getElementById('schedule_capacity');
  var seatingEditor = document.getElementById('seatingEditor');
  var priceEditor = document.getElementById('priceEditor');
  var seatMapInput = document.getElementById('seat_map');
  var priceRangesInput = document.getElementById('price_ranges');
  if (!hallSelect || !capacityPanel || !capacityInput) return;

  var requestId = 0;

  function hasSeatLayout(layout) {
    if (!layout || typeof layout !== 'object') return false;
    if (layout.seats && typeof layout.seats === 'object' && Object.keys(layout.seats).length) return true;
    return Array.isArray(layout.rows) && layout.rows.some(function (row) {
      return Array.isArray(row.segments) && row.segments.some(function (segment) {
        if (!segment || typeof segment !== 'object') return false;
        if (Array.isArray(segment.seats) && segment.seats.length) return true;
        return Number(segment.start) > 0 && Number(segment.end) >= Number(segment.start);
      });
    });
  }

  function applyMode(isGeneralAdmission) {
    capacityPanel.hidden = !isGeneralAdmission;
    capacityInput.required = isGeneralAdmission;
    if (seatingEditor) seatingEditor.hidden = isGeneralAdmission;
    if (priceEditor) priceEditor.hidden = isGeneralAdmission;

    if (isGeneralAdmission) {
      if (seatMapInput) seatMapInput.value = '';
      if (priceRangesInput) priceRangesInput.value = '';
    } else if (capacityInput.value && !capacityPanel.dataset.initialCapacity) {
      capacityInput.value = '';
    }
  }

  function inspectHall(hallId) {
    var currentRequest = ++requestId;
    if (!hallId) {
      applyMode(false);
      return;
    }
    fetch('/ajax/get_hall.php?hall_id=' + encodeURIComponent(hallId), {
      credentials: 'same-origin'
    }).then(function (response) {
      return response.text().then(function (text) {
        if (!response.ok) throw new Error('Не удалось проверить зал.');
        try {
          return JSON.parse(text);
        } catch (error) {
          throw new Error('Схема зала имеет некорректный формат.');
        }
      });
    }).then(function (layout) {
      if (currentRequest !== requestId) return;
      var isGeneralAdmission = !hasSeatLayout(layout);
      if (!capacityPanel.dataset.initialCapacity) {
        capacityPanel.dataset.initialCapacity = 'checked';
        if (isGeneralAdmission && !capacityInput.value) capacityInput.value = '';
      }
      applyMode(isGeneralAdmission);
      if (isGeneralAdmission && capacityInput.value) {
        capacityPanel.dataset.initialCapacity = 'checked';
      }
    }).catch(function (error) {
      if (currentRequest !== requestId) return;
      applyMode(false);
      if (typeof window.showToast === 'function') {
        window.showToast(error.message || 'Не удалось проверить схему зала.', 'error');
      }
    });
  }

  hallSelect.addEventListener('change', function () {
    capacityPanel.dataset.initialCapacity = '';
    inspectHall(hallSelect.value);
  });

  if (hallSelect.value) {
    capacityPanel.dataset.initialCapacity = capacityInput.value ? 'checked' : '';
    inspectHall(hallSelect.value);
  }
})();

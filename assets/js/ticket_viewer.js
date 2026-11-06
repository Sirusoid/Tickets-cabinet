// /assets/js/ticket_viewer.js
// Modal preview for a single ticket PDF on tickets/list.php.

(function(){
  'use strict';

  function qs(id) { return document.getElementById(id); }

  var modal = qs('ticketPreviewModal');
  var iframe = qs('ticketPreviewIframe');
  var listEl = qs('ticketPreviewList');
  var titleEl = qs('ticketPreviewTitle');
  var downloadBtn = qs('ticketPreviewDownload');
  var downloadAllBtn = qs('ticketPreviewDownloadAll');
  var openNewTabBtn = qs('ticketPreviewOpenNewTab');
  var closeBtn = qs('ticketPreviewClose');
  var currentUrl = '';
  var batchTickets = [];
  var batchOrderNumber = '';

  function closePreview() {
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    if (iframe) {
      iframe.src = 'about:blank';
      iframe.style.display = 'none';
    }
    if (listEl) {
      listEl.innerHTML = '';
      listEl.style.display = 'none';
    }
    if (downloadAllBtn) downloadAllBtn.style.display = 'none';
    currentUrl = '';
    batchTickets = [];
    batchOrderNumber = '';
  }

  function setActionButtons(url) {
    if (!downloadBtn || !openNewTabBtn) return;
    if (!url) {
      downloadBtn.disabled = true;
      openNewTabBtn.disabled = true;
      return;
    }
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    downloadBtn.disabled = false;
    openNewTabBtn.disabled = false;
    downloadBtn.style.display = '';
    openNewTabBtn.style.display = '';
    downloadBtn.onclick = function() {
      window.open(url + separator + 'download=1', '_blank');
    };
    openNewTabBtn.onclick = function() {
      window.open(url, '_blank');
    };
  }

  function ticketUrl(uid, download) {
    var url = '/tickets/generate.php?uid=' + encodeURIComponent(uid);
    return download ? url + '&download=1' : url;
  }

  function printTicket(url) {
    var printWindow = window.open(url, '_blank');
    if (!printWindow) return;
    window.setTimeout(function () {
      try { printWindow.focus(); printWindow.print(); } catch (e) {}
    }, 1000);
  }

  function renderBatchCards(tickets) {
    if (!listEl) return;
    listEl.innerHTML = '';
    tickets.forEach(function (ticket, index) {
      var uid = String(ticket.ticket_uid || '');
      if (!uid) return;
      var card = document.createElement('div');
      card.style.display = 'flex';
      card.style.alignItems = 'center';
      card.style.justifyContent = 'space-between';
      card.style.gap = '14px';
      card.style.flexWrap = 'wrap';
      card.style.padding = '14px 16px';
      card.style.marginBottom = '10px';
      card.style.border = '1px solid #e2e8f0';
      card.style.borderRadius = '10px';
      card.style.background = '#fff';

      var info = document.createElement('div');
      info.style.minWidth = '220px';
      var title = document.createElement('strong');
      title.textContent = 'Билет ' + (index + 1) + ' · ' + uid;
      info.appendChild(title);
      var meta = document.createElement('div');
      meta.style.marginTop = '5px';
      meta.style.color = '#64748b';
      meta.style.fontSize = '13px';
      var details = [];
      if (ticket.seat_identifier) details.push('Место: ' + String(ticket.seat_identifier).replace(/:/g, ' - '));
      if (ticket.final_price !== undefined && ticket.final_price !== null) details.push('Сумма: ' + Number(ticket.final_price).toLocaleString() + ' тг');
      if (ticket.gift_selected) details.push('С подарком' + (ticket.gift_name ? ': ' + ticket.gift_name : ''));
      meta.textContent = details.join(' · ');
      info.appendChild(meta);

      var actions = document.createElement('div');
      actions.style.display = 'flex';
      actions.style.gap = '7px';
      actions.style.flexWrap = 'wrap';
      [
        ['Открыть PDF', function () { window.open(ticketUrl(uid, false), '_blank'); }],
        ['Печать', function () { printTicket(ticketUrl(uid, false)); }],
        ['Скачать', function () { window.open(ticketUrl(uid, true), '_blank'); }]
      ].forEach(function (action) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary btn-sm';
        button.textContent = action[0];
        button.addEventListener('click', action[1]);
        actions.appendChild(button);
      });
      card.appendChild(info);
      card.appendChild(actions);
      listEl.appendChild(card);
    });
    listEl.style.display = 'block';
  }

  function openTicketBatchPreview(tickets, orderNumber) {
    if (!modal || !listEl) return;
    batchTickets = Array.isArray(tickets) ? tickets : [];
    batchOrderNumber = String(orderNumber || '');
    titleEl.textContent = 'Продажа завершена'
      + (orderNumber ? ' · заказ ' + String(orderNumber) : '')
      + ' · билетов: ' + batchTickets.length;
    if (iframe) {
      iframe.src = 'about:blank';
      iframe.style.display = 'none';
    }
    if (downloadBtn) downloadBtn.style.display = 'none';
    if (openNewTabBtn) openNewTabBtn.style.display = 'none';
    if (downloadAllBtn) {
      downloadAllBtn.style.display = batchTickets.length ? '' : 'none';
      downloadAllBtn.onclick = function () {
        var uids = batchTickets.map(function (ticket) {
          return String(ticket.ticket_uid || '');
        }).filter(Boolean);
        window.location.href = '/tickets/download_batch.php?uids=' + encodeURIComponent(uids.join(','))
          + '&order_number=' + encodeURIComponent(batchOrderNumber);
      };
    }
    renderBatchCards(batchTickets);
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
  }

  function openTicketPreview(url, title) {
    if (!modal || !iframe) {
      if (url) {
        window.open(url, '_blank');
      }
      return;
    }
    currentUrl = url || '';
    if (listEl) {
      listEl.innerHTML = '';
      listEl.style.display = 'none';
    }
    if (downloadAllBtn) downloadAllBtn.style.display = 'none';
    titleEl.textContent = title || 'Просмотр билета';
    iframe.src = url || 'about:blank';
    iframe.style.display = 'block';
    setActionButtons(url);
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', function(e){ e.preventDefault(); closePreview(); });
  }
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closePreview();
      }
    });
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
      closePreview();
    }
  });

  window.openTicketPreview = openTicketPreview;
  window.openTicketBatchPreview = openTicketBatchPreview;
  window.closeTicketPreview = closePreview;
})();

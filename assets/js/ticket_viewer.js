// /assets/js/ticket_viewer.js
// Modal preview for a single ticket PDF on tickets/list.php.

(function(){
  'use strict';

  function qs(id) { return document.getElementById(id); }

  var modal = qs('ticketPreviewModal');
  var iframe = qs('ticketPreviewIframe');
  var titleEl = qs('ticketPreviewTitle');
  var downloadBtn = qs('ticketPreviewDownload');
  var openNewTabBtn = qs('ticketPreviewOpenNewTab');
  var closeBtn = qs('ticketPreviewClose');
  var currentUrl = '';

  function closePreview() {
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    if (iframe) {
      iframe.src = 'about:blank';
    }
    currentUrl = '';
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
    downloadBtn.onclick = function() {
      window.open(url + separator + 'download=1', '_blank');
    };
    openNewTabBtn.onclick = function() {
      window.open(url, '_blank');
    };
  }

  function openTicketPreview(url, title) {
    if (!modal || !iframe) {
      if (url) {
        window.open(url, '_blank');
      }
      return;
    }
    currentUrl = url || '';
    titleEl.textContent = title || 'Просмотр билета';
    iframe.src = url || 'about:blank';
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
  window.closeTicketPreview = closePreview;
})();

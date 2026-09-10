<?php
// tickets/list.php
require_once __DIR__ . '/../init.php';
require_login();

$use_sidebar = true;
$active_menu = 'tickets';
$page_title_meta = 'Список билетов';
$panel_title = 'Билеты';
$panel_subtitle = 'Список проданных билетов, управление возвратами, просмотр и печать билетов';

$page_scripts = ['/assets/js/tickets_list.js', '/assets/js/ticket_viewer.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<link rel="stylesheet" href="/assets/css/schedule.css">
<link rel="stylesheet" href="/assets/css/actor_form.css">

<div class="page container-full">

  <div class="schedule-layout" style="align-items:flex-start; width: 102%; margin-left: -17px;">
    <div class="schedule-canvas-wrap" style="flex:1; min-width:0;">
      <div class="schedule-panel compact" style="margin-bottom:14px; width:100%;">
        <div class="card-title" style="margin-bottom:8px;">Фильтры</div>
        <form id="ticketsFilters" class="compact-grid" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
          <div>
            <label>Дата от</label>
            <input type="date" id="filter_date_from" class="form-control" />
          </div>
          <div>
            <label>Дата до</label>
            <input type="date" id="filter_date_to" class="form-control" />
          </div>

          <div>
            <label>Сеанс (название)</label>
            <div style="position:relative;">
              <input id="filter_session" class="form-control" placeholder="Название сеанса" autocomplete="off" />
              <input type="hidden" id="filter_session_raw" value="" />
              <div id="suggestions_session" class="typeahead-dropdown" style="position:absolute; left:0; right:0; z-index:1400; display:none;"></div>
            </div>
          </div>

          <div style="grid-column: span 1;">
            <label>UID билета</label>
            <div style="position:relative;">
              <input id="filter_uid" class="form-control" placeholder="UID билета" autocomplete="off" />
              <div id="suggestions_uid" class="typeahead-dropdown" style="position:absolute; left:0; right:0; z-index:1400; display:none;"></div>
            </div>
          </div>

          <div>
            <label>Статус</label>
            <select id="filter_status" class="form-control">
              <option value="">Все</option>
              <option value="issued">Выдан</option>
              <option value="cancelled">Отменён</option>
              <option value="used">Использован</option>
            </select>
          </div>
          <div>
            <label>Оплата</label>
            <select id="filter_payment" class="form-control">
              <option value="">Все</option>
              <option value="pending">Ожидает</option>
              <option value="paid">Оплачен</option>
              <option value="failed">Ошибка</option>
            </select>
          </div>

          <div>
            <label>Канал</label>
            <select id="filter_channel" class="form-control">
              <option value="">Все</option>
              <option value="web">Web</option>
              <option value="mobile">Mobile</option>
              <option value="kassa">Kassa</option>
              <option value="agent">Agent</option>
              <option value="qr">QR</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div>
            <label>Клиент (ФИО)</label>
            <div style="position:relative;">
              <input id="filter_customer" class="form-control" placeholder="ФИО клиента" autocomplete="off" />
              <div id="suggestions_customer" class="typeahead-dropdown" style="position:absolute; left:0; right:0; z-index:1400; display:none;"></div>
            </div>
          </div>

          <div>
            <label>Тип билета</label>
            <select id="filter_segment" class="form-control">
              <option value="">Все</option>
              <option value="adult">Взрослый</option>
              <option value="child">Детский</option>
              <option value="student">Студенческий</option>
              <option value="senior">Пенсионный</option>
            </select>
          </div>

          <div>
            <label>Возврат</label>
            <select id="filter_refund" class="form-control">
              <option value="">Все</option>
              <option value="yes">Да</option>
              <option value="no">Нет</option>
            </select>
          </div>

          <div style="grid-column: 1 / -1; display:flex; gap:8px; justify-content:flex-end; margin-top:6px; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
              <label style="margin:0 6px 0 0; color:#666;">Билетов на странице</label>
              <select id="selectPerPage" class="form-control" style="width:110px;">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="500">500</option>
              </select>
            </div>
            <div style="display:flex; gap:8px;">
              <button id="btnResetFilters" type="button" class="btn btn-ghost">Сброс</button>
              <button id="btnExport" type="button" class="btn btn-secondary" style="margin-left:8px;">Экспорт (Excel)</button>
            </div>
          </div>
        </form>
      </div>

      <div class="profile-card" style="padding:12px;">

        <div style="margin-top:8px; overflow:auto;">
          <table id="ticketsTable" class="admin-table table--compact" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th style="width:105px;">Дата заказа</th>
                <th style="min-width:190px;">Сеанс</th>
                <th style="min-width:190px;">Клиент</th>
                <th>Ряд - Место</th>
                <th>Тип</th>
                <th style="width:130px;">Скидка</th>
                <th style="width:100px;">Цена</th>
                <th>Канал</th>
                <th>Форма оплаты</th>
                <th style="width:170px;">Номер заказа</th>
                <th style="width:160px;">UID билета</th>
                <th>Оплата</th>
                <th>Статус</th>
                <th>Возврат</th>
                <th style="width:220px;">Действие</th>
              </tr>

            </thead>
            <tbody id="ticketsTbody">
              <tr><td colspan="15" style="text-align:center; color:#666; padding:18px;">Загрузка...</td></tr>
            </tbody>
          </table>
        </div>

        <div id="ticketsPager" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
          <div id="ticketsSummary" style="color:#666;">—</div>
          <div style="display:flex; align-items:center; gap:8px;">
            <button id="prevPage" class="btn btn-ghost">←</button>
            <span id="currentPage">1 / 1</span>
            <button id="nextPage" class="btn btn-ghost">→</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Refund modal -->
<div id="refundModal" style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#00000066; position:absolute; left:0; top:0; right:0; bottom:0;"></div>
  <div style="position:relative; width:720px; max-width:95%; background:#fff; border-radius:8px; padding:18px; box-shadow:0 8px 30px rgba(0,0,0,0.3);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <div style="font-weight:600; font-size:16px;">Возврат билета</div>
      <button id="refundModalClose" class="btn btn-ghost btn-xs">✕</button>
    </div>

    <div id="refundInfo" style="margin-bottom:12px; color:#333;">
      <div><strong>Клиент:</strong> <span id="refund_client">—</span></div>
      <div><strong>UID билета:</strong> <span id="refund_uid">—</span></div>
      <div><strong>Сеанс:</strong> <span id="refund_session">—</span></div>
      <div><strong>Дата - Время:</strong> <span id="refund_session_dt">—</span></div>
      <div><strong>Ряд / Место:</strong> <span id="refund_seat">—</span></div>
    </div>

    <form id="refundForm" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
      <input type="hidden" id="refund_ticket_id" name="ticket_id" value="" />
      <input type="hidden" id="refund_csrf" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>" />

      <div>
        <label>Сумма возврата</label>
        <input type="number" step="0.01" id="refund_amount" name="refund_amount" class="form-control" />
      </div>

      <div>
        <label>Метод возврата</label>
        <select id="refund_method" name="refund_method" class="form-control">
          <option value="cash">Наличные</option>
          <option value="noncash">Безналичные</option>
          <option value="bank">Банковский перевод</option>
        </select>
      </div>

      <div>
        <label>Провайдер / канал</label>
        <input type="text" id="refund_provider" name="refund_provider" class="form-control" placeholder="Например: Kaspi, Счет банка" />
      </div>

      <div>
        <label>ID транзакции</label>
        <input type="text" id="refund_transaction_id" name="refund_transaction_id" class="form-control" placeholder="ID транзакции (если есть)" />
      </div>

      <div style="grid-column: 1 / -1;">
        <label>Причина возврата</label>
        <textarea id="refund_reason" name="reason" class="form-control" rows="3" placeholder="Причина возврата (необязательно)"></textarea>
      </div>

      <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
        <button type="button" id="refundCancelBtn" class="btn btn-ghost">Отмена</button>
        <button type="button" id="refundConfirmBtn" class="btn btn-primary">Подтвердить возврат</button>
      </div>
    </form>
  </div>
</div>

<div id="ticketPreviewModal" style="display:none; position:fixed; inset:0; z-index:2200; align-items:center; justify-content:center; background:rgba(0,0,0,0.65);">
  <div style="position:relative; width:90%; max-width:1120px; height:90%; background:#fff; border-radius:12px; overflow:hidden; display:flex; flex-direction:column;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px; background:#f7f7f7; border-bottom:1px solid #ddd;">
      <div id="ticketPreviewTitle" style="font-size:16px; font-weight:700; color:#222;">Просмотр билета</div>
      <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <button id="ticketPreviewDownload" type="button" class="btn btn-secondary btn-sm">Скачать</button>
        <button id="ticketPreviewOpenNewTab" type="button" class="btn btn-ghost btn-sm">Открыть в новой вкладке</button>
        <button id="ticketPreviewClose" type="button" class="btn btn-ghost btn-xs">✕</button>
      </div>
    </div>
    <iframe id="ticketPreviewIframe" src="about:blank" style="flex:1; width:100%; border:none; min-height:0;"></iframe>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

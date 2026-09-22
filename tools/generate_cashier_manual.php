<?php
/**
 * Generates CASHIER_MANUAL_RU.pdf in the project root.
 * Run from the project root:
 *   php tools/generate_cashier_manual.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$projectRoot = dirname(__DIR__);
$logoPath = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png';
$logoData = is_readable($logoPath) ? base64_encode((string)file_get_contents($logoPath)) : '';
$logo = $logoData !== '' ? 'data:image/png;base64,' . $logoData : '';
$today = date('d.m.Y');

$flowSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="720" height="210" viewBox="0 0 720 210">
  <rect width="720" height="210" rx="18" fill="#f8fafc"/>
  <g font-family="DejaVu Sans, sans-serif" font-size="14" text-anchor="middle">
    <rect x="18" y="72" width="128" height="68" rx="12" fill="#173b67"/>
    <text x="82" y="99" fill="#fff">Войти</text><text x="82" y="122" fill="#dbeafe">логин и пароль</text>
    <path d="M150 106h35" stroke="#64748b" stroke-width="3"/><path d="m178 97 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="190" y="72" width="128" height="68" rx="12" fill="#2563eb"/>
    <text x="254" y="99" fill="#fff">Панель</text><text x="254" y="122" fill="#dbeafe">сеансы и итог дня</text>
    <path d="M322 106h35" stroke="#64748b" stroke-width="3"/><path d="m350 97 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="362" y="72" width="128" height="68" rx="12" fill="#18a957"/>
    <text x="426" y="99" fill="#fff">Касса</text><text x="426" y="122" fill="#dcfce7">места и продажа</text>
    <path d="M494 106h35" stroke="#64748b" stroke-width="3"/><path d="m522 97 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="534" y="72" width="168" height="68" rx="12" fill="#b45309"/>
    <text x="618" y="99" fill="#fff">Билет / отчёт</text><text x="618" y="122" fill="#fef3c7">контроль и итог</text>
  </g>
</svg>
SVG;

$seatSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="720" height="255" viewBox="0 0 720 255">
  <rect width="720" height="255" rx="18" fill="#f8fafc"/>
  <rect x="250" y="17" width="220" height="32" rx="8" fill="#cbd5e1"/>
  <text x="360" y="38" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#334155">СЦЕНА</text>
  <g stroke="#fff" stroke-width="2">
    <rect x="180" y="72" width="30" height="30" rx="6" fill="#18a957"/><rect x="218" y="72" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="256" y="72" width="30" height="30" rx="6" fill="#ef4444"/><rect x="294" y="72" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="332" y="72" width="30" height="30" rx="6" fill="#f59e0b"/><rect x="370" y="72" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="408" y="72" width="30" height="30" rx="6" fill="#18a957"/><rect x="446" y="72" width="30" height="30" rx="6" fill="#9ca3af"/>
    <rect x="180" y="112" width="30" height="30" rx="6" fill="#18a957"/><rect x="218" y="112" width="30" height="30" rx="6" fill="#2563eb"/>
    <rect x="256" y="112" width="30" height="30" rx="6" fill="#18a957"/><rect x="294" y="112" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="332" y="112" width="30" height="30" rx="6" fill="#ef4444"/><rect x="370" y="112" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="408" y="112" width="30" height="30" rx="6" fill="#f59e0b"/><rect x="446" y="112" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="180" y="152" width="30" height="30" rx="6" fill="#18a957"/><rect x="218" y="152" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="256" y="152" width="30" height="30" rx="6" fill="#2563eb"/><rect x="294" y="152" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="332" y="152" width="30" height="30" rx="6" fill="#18a957"/><rect x="370" y="152" width="30" height="30" rx="6" fill="#18a957"/>
    <rect x="408" y="152" width="30" height="30" rx="6" fill="#18a957"/><rect x="446" y="152" width="30" height="30" rx="6" fill="#ef4444"/>
  </g>
  <g font-family="DejaVu Sans, sans-serif" font-size="12" fill="#334155">
    <circle cx="36" cy="207" r="7" fill="#18a957"/><text x="50" y="211">свободно</text>
    <circle cx="152" cy="207" r="7" fill="#ef4444"/><text x="166" y="211">продано</text>
    <circle cx="260" cy="207" r="7" fill="#f59e0b"/><text x="274" y="211">резерв</text>
    <circle cx="360" cy="207" r="7" fill="#2563eb"/><text x="374" y="211">ваш резерв</text>
    <circle cx="486" cy="207" r="7" fill="#9ca3af"/><text x="500" y="211">недоступно</text>
  </g>
</svg>
SVG;

$saleSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="720" height="230" viewBox="0 0 720 230">
  <rect width="720" height="230" rx="18" fill="#f8fafc"/>
  <g font-family="DejaVu Sans, sans-serif" font-size="13" text-anchor="middle">
    <rect x="18" y="78" width="130" height="62" rx="12" fill="#2563eb"/><text x="83" y="105" fill="#fff">1. Места</text><text x="83" y="126" fill="#dbeafe">выбрать</text>
    <path d="M152 109h35" stroke="#64748b" stroke-width="3"/><path d="m180 100 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="192" y="78" width="130" height="62" rx="12" fill="#2563eb"/><text x="257" y="105" fill="#fff">2. Клиент</text><text x="257" y="126" fill="#dbeafe">телефон</text>
    <path d="M326 109h35" stroke="#64748b" stroke-width="3"/><path d="m354 100 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="368" y="78" width="130" height="62" rx="12" fill="#18a957"/><text x="433" y="105" fill="#fff">3. Скидка</text><text x="433" y="126" fill="#dcfce7">тип билета</text>
    <path d="M502 109h35" stroke="#64748b" stroke-width="3"/><path d="m530 100 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="544" y="78" width="158" height="62" rx="12" fill="#b45309"/><text x="623" y="105" fill="#fff">4. Оплата</text><text x="623" y="126" fill="#fef3c7">продать</text>
  </g>
  <text x="360" y="183" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#475569">После подтверждения продажи место становится «Продано», а резерв снимается.</text>
</svg>
SVG;

$discountSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="720" height="220" viewBox="0 0 720 220">
  <rect width="720" height="220" rx="18" fill="#f8fafc"/>
  <rect x="24" y="35" width="205" height="120" rx="12" fill="#fff" stroke="#cbd5e1"/>
  <text x="126" y="62" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="14" fill="#173b67" font-weight="bold">Тип клиента</text>
  <text x="44" y="88" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#334155">Взрослый</text>
  <text x="44" y="111" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#334155">Детский</text>
  <text x="44" y="134" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#334155">Студенческий / Пенсионный</text>
  <path d="M238 95h55" stroke="#64748b" stroke-width="3"/><path d="m282 86 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
  <rect x="305" y="35" width="190" height="120" rx="12" fill="#18a957"/>
  <text x="400" y="70" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="14" fill="#fff" font-weight="bold">Автоматический</text>
  <text x="400" y="96" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#dcfce7">процент из настроек</text>
  <text x="400" y="122" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#dcfce7">фиксируется в билете</text>
  <path d="M504 95h55" stroke="#64748b" stroke-width="3"/><path d="m548 86 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
  <rect x="570" y="35" width="126" height="120" rx="12" fill="#b45309"/>
  <text x="633" y="70" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="14" fill="#fff" font-weight="bold">Итог</text>
  <text x="633" y="98" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#fef3c7">цена</text>
  <text x="633" y="122" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#fef3c7">скидка</text>
  <text x="360" y="188" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#475569">Ручная скидка доступна только при выборе «Ручная скидка».</text>
</svg>
SVG;

$refundSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="720" height="230" viewBox="0 0 720 230">
  <rect width="720" height="230" rx="18" fill="#f8fafc"/>
  <g font-family="DejaVu Sans, sans-serif" font-size="13" text-anchor="middle">
    <rect x="25" y="72" width="145" height="68" rx="12" fill="#2563eb"/><text x="97" y="100" fill="#fff">Найти билет</text><text x="97" y="122" fill="#dbeafe">Билеты</text>
    <path d="M174 106h43" stroke="#64748b" stroke-width="3"/><path d="m208 97 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="225" y="72" width="145" height="68" rx="12" fill="#18a957"/><text x="297" y="100" fill="#fff">Возврат</text><text x="297" y="122" fill="#dcfce7">проверить статус</text>
    <path d="M374 106h43" stroke="#64748b" stroke-width="3"/><path d="m408 97 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="425" y="72" width="125" height="68" rx="12" fill="#b45309"/><text x="487" y="100" fill="#fff">BCC</text><text x="487" y="122" fill="#fef3c7">только онлайн</text>
    <path d="M554 106h43" stroke="#64748b" stroke-width="3"/><path d="m588 97 10 9-10 9" fill="none" stroke="#64748b" stroke-width="3"/>
    <rect x="605" y="72" width="90" height="68" rx="12" fill="#7c3aed"/><text x="650" y="100" fill="#fff">Итог</text><text x="650" y="122" fill="#ede9fe">статус</text>
  </g>
  <text x="360" y="183" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" fill="#475569">Если BCC вернул ошибку — не повторяйте запрос, сначала проверьте статус.</text>
</svg>
SVG;

$html = <<<'HTML'
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4; margin: 16mm 15mm 17mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172b4d; background: #fff; font-family: "DejaVu Sans", sans-serif; font-size: 10.4pt; line-height: 1.45; }
        h1, h2, h3, h4 { color: #173b67; margin: 0 0 10px; }
        h1 { font-size: 29pt; line-height: 1.1; }
        h2 { font-size: 19pt; margin-top: 4px; }
        h3 { font-size: 13.5pt; margin-top: 16px; }
        h4 { font-size: 11pt; margin-top: 12px; }
        p { margin: 7px 0; }
        ul, ol { margin: 7px 0 10px 20px; padding: 0; }
        li { margin: 4px 0; }
        code { padding: 1px 4px; border-radius: 3px; background: #eef4ff; color: #1d4ed8; font-size: 9pt; }
        .page { page-break-before: always; }
        .cover { min-height: 247mm; padding: 22mm 8mm 12mm; display: flex; flex-direction: column; justify-content: space-between; background: linear-gradient(145deg, #f8fafc 0%, #edf5ff 100%); }
        .cover-logo { width: 145px; height: 145px; object-fit: contain; margin-bottom: 18mm; }
        .cover-kicker { color: #18a957; font-weight: bold; letter-spacing: .12em; text-transform: uppercase; font-size: 9pt; }
        .cover-subtitle { max-width: 145mm; color: #52647e; font-size: 14pt; }
        .cover-meta { padding-top: 12mm; color: #64748b; font-size: 9pt; }
        .lead { font-size: 12pt; color: #475569; }
        .panel { padding: 14px 16px; margin: 11px 0; border-radius: 9px; border: 1px solid #dbe3ec; background: #fff; }
        .panel-blue { border-left: 5px solid #2563eb; background: #eff6ff; }
        .panel-green { border-left: 5px solid #18a957; background: #ecfdf5; }
        .panel-yellow { border-left: 5px solid #f59e0b; background: #fffbeb; }
        .panel-red { border-left: 5px solid #dc2626; background: #fef2f2; }
        .step { display: table; width: 100%; margin: 8px 0; }
        .step-num { display: table-cell; width: 27px; height: 27px; padding: 3px 0; border-radius: 50%; background: #2563eb; color: #fff; text-align: center; font-weight: bold; }
        .step-body { display: table-cell; padding: 2px 0 2px 10px; vertical-align: top; }
        .muted { color: #64748b; }
        .small { font-size: 8.7pt; }
        .caption { margin: 5px 0 12px; color: #64748b; font-size: 8.7pt; text-align: center; }
        .figure { margin: 12px 0 15px; padding: 8px; border: 1px solid #dbe3ec; border-radius: 12px; background: #f8fafc; text-align: center; }
        .figure svg { max-width: 100%; height: auto; }
        .two-col { display: table; width: 100%; table-layout: fixed; border-spacing: 10px 0; margin-left: -10px; }
        .two-col > div { display: table-cell; width: 50%; vertical-align: top; }
        .status-table, .check-table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        .status-table th, .status-table td, .check-table th, .check-table td { padding: 7px 8px; border: 1px solid #dbe3ec; vertical-align: top; }
        .status-table th, .check-table th { background: #eef4ff; color: #173b67; text-align: left; }
        .dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 5px; }
        .green { background: #18a957; } .red { background: #ef4444; } .orange { background: #f59e0b; } .blue { background: #2563eb; } .gray { background: #9ca3af; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 999px; font-size: 8.5pt; font-weight: bold; }
        .badge-blue { color: #1d4ed8; background: #dbeafe; } .badge-green { color: #15803d; background: #dcfce7; } .badge-red { color: #b91c1c; background: #fee2e2; } .badge-orange { color: #b45309; background: #fef3c7; }
        .toc { padding: 15px 18px; border: 1px solid #dbe3ec; border-radius: 10px; background: #f8fafc; }
        .toc div { padding: 5px 0; border-bottom: 1px dotted #cbd5e1; }
        .toc div:last-child { border-bottom: 0; }
        .footer { position: fixed; left: 0; right: 0; bottom: -10mm; height: 8mm; color: #94a3b8; font-size: 8pt; text-align: center; }
        .avoid-break { page-break-inside: avoid; }
        .check { color: #15803d; font-weight: bold; }
        .x { color: #b91c1c; font-weight: bold; }
        .kbd { padding: 2px 5px; border: 1px solid #cbd5e1; border-bottom-width: 2px; border-radius: 4px; background: #f8fafc; font-size: 8.5pt; }
    </style>
</head>
<body>
<div class="footer">Алматинский театр «Жас сахна» • Руководство кассира • __TODAY__</div>

<section class="cover">
    <div>
        <img class="cover-logo" src="__LOGO__" alt="Логотип театра">
        <div class="cover-kicker">Tickets Cabinet</div>
        <h1>Касса</h1>
        <p class="cover-subtitle">Полное пошаговое руководство кассира: продажа, резервы, скидки, возвраты, билеты и отчёты.</p>
        <div class="panel panel-blue" style="margin-top:18mm; max-width:145mm;">
            <strong>Для кого этот документ</strong><br>
            Для кассира, который оформляет продажу билетов, работает с клиентами и контролирует результаты операций.
        </div>
    </div>
    <div class="cover-meta">
        Версия руководства: __TODAY__<br>
        Перед началом работы убедитесь, что вы вошли под своей учётной записью.
    </div>
</section>

<section class="page">
    <h2>1. Самое важное перед началом работы</h2>
    <p class="lead">Кассир отвечает за правильность места, типа билета, скидки, способа оплаты и результата операции.</p>
    <div class="panel panel-red">
        <h3>Никогда не делайте так</h3>
        <ul>
            <li>Не продавайте место, если оно отмечено как <strong>Продано</strong> или занято резервом другого кассира.</li>
            <li>Не нажимайте «Продать» повторно, если терминал уже принял оплату. Сначала проверьте результат.</li>
            <li>Не оформляйте ручную скидку без выбора типа <strong>Ручная скидка</strong>.</li>
            <li>Не повторяйте банковский возврат, если первый запрос имеет статус «Обрабатывается».</li>
            <li>Не передавайте пароль и не оставляйте кабинет открытым без присмотра.</li>
        </ul>
    </div>
    <div class="panel panel-green">
        <h3>Три обязательные проверки перед продажей</h3>
        <ol>
            <li>Выбран правильный спектакль, дата, время и зал.</li>
            <li>Выбраны правильные места и тип билета.</li>
            <li>Телефон клиента заполнен, способ оплаты выбран, итоговая сумма понятна.</li>
        </ol>
    </div>
    <div class="figure">__FLOW__</div>
    <p class="caption">Общий путь кассира: вход → панель → касса → оформленный билет и контроль результата.</p>
</section>

<section class="page">
    <h2>2. Вход в кабинет</h2>
    <div class="step"><span class="step-num">1</span><div class="step-body">Откройте адрес кабинета театра и дождитесь страницы входа.</div></div>
    <div class="step"><span class="step-num">2</span><div class="step-body">В поле <strong>Имя пользователя</strong> введите свой логин.</div></div>
    <div class="step"><span class="step-num">3</span><div class="step-body">Введите пароль и нажмите <strong>Войти</strong>.</div></div>
    <div class="step"><span class="step-num">4</span><div class="step-body">Если пароль забыт, используйте ссылку <strong>Забыли пароль?</strong> и следуйте инструкции.</div></div>
    <div class="panel panel-yellow">
        <strong>Если вход не получается:</strong>
        проверьте раскладку клавиатуры, Caps Lock и используйте только свою учётную запись. После нескольких неудачных попыток вход может временно блокироваться.
    </div>
    <h3>Что видит кассир</h3>
    <p>Набор разделов зависит от роли и прав. Обычно кассиру доступны:</p>
    <div class="two-col">
        <div class="panel"><span class="badge badge-blue">Работа</span><br>Панель, Касса, Билеты, Возвраты.</div>
        <div class="panel"><span class="badge badge-green">Контроль</span><br>Отчёты и Клиенты. Настройки и логи ошибок обычно доступны администратору.</div>
    </div>
</section>

<section class="page">
    <h2>3. Панель кассира</h2>
    <p>На странице <strong>Панель</strong> собрана краткая сводка работы:</p>
    <ul>
        <li><strong>Продано сегодня</strong> — количество оплаченных билетов.</li>
        <li><strong>Выручка сегодня</strong> — сумма продаж.</li>
        <li><strong>Скидки сегодня</strong> — сумма предоставленных скидок.</li>
        <li><strong>Возвраты сегодня</strong> — количество и сумма возвращённых билетов.</li>
        <li><strong>Чистый итог</strong> — продажи за вычетом возвратов.</li>
    </ul>
    <h3>Быстрый переход в кассу</h3>
    <div class="step"><span class="step-num">1</span><div class="step-body">Найдите нужный сеанс в списке ближайших показов.</div></div>
    <div class="step"><span class="step-num">2</span><div class="step-body">Нажмите кнопку <strong>Касса</strong> рядом с сеансом.</div></div>
    <div class="step"><span class="step-num">3</span><div class="step-body">Либо откройте раздел <strong>Касса</strong> в боковом меню.</div></div>
    <div class="panel panel-blue">Панель нужна для быстрого контроля. Саму продажу выполняйте внутри выбранного сеанса в разделе «Касса».</div>
</section>

<section class="page">
    <h2>4. Выбор сеанса в кассе</h2>
    <p>Откройте раздел <strong>Касса</strong>. В списке можно отфильтровать сеансы по:</p>
    <ul>
        <li>залу;</li><li>спектаклю;</li><li>дате;</li><li>статусу;</li><li>порядку сортировки.</li>
    </ul>
    <table class="status-table">
        <tr><th>Колонка</th><th>Что означает</th></tr>
        <tr><td><strong>Доступно</strong></td><td>Места, которые можно выбрать прямо сейчас.</td></tr>
        <tr><td><strong>Продано</strong></td><td>Места с уже оформленными билетами.</td></tr>
        <tr><td><strong>Резерв</strong></td><td>Места, временно удерживаемые кассиром, клиентом или оплатой.</td></tr>
    </table>
    <div class="panel panel-yellow">Перед открытием сеанса проверьте дату и время. Ошибка в сеансе приводит к продаже билета не на тот показ.</div>
</section>

<section class="page">
    <h2>5. Схема зала и статусы мест</h2>
    <p>После открытия сеанса появляется схема зала. Нажмите на свободные места, чтобы добавить их в корзину.</p>
    <div class="figure">__SEATS__</div>
    <table class="status-table">
        <tr><th>Цвет</th><th>Статус</th><th>Действие кассира</th></tr>
        <tr><td><span class="dot green"></span>Зелёный</td><td>Свободно</td><td>Можно выбрать.</td></tr>
        <tr><td><span class="dot red"></span>Красный</td><td>Продано</td><td>Не выбирать и не продавать.</td></tr>
        <tr><td><span class="dot orange"></span>Оранжевый</td><td>Резерв</td><td>Проверить владельца резерва; чужой резерв недоступен.</td></tr>
        <tr><td><span class="dot blue"></span>Синий</td><td>Ваш резерв</td><td>Можно продолжить продажу.</td></tr>
        <tr><td><span class="dot gray"></span>Серый</td><td>Нет цены/недоступно</td><td>Не использовать для продажи.</td></tr>
    </table>
    <div class="panel panel-red"><strong>Важно:</strong> если место занято резервом другого кассира, не удаляйте его вручную. Свяжитесь с сотрудником, который создал резерв.</div>
</section>

<section class="page">
    <h2>6. Данные клиента</h2>
    <p>Перед продажей заполните данные клиента в блоке клиента:</p>
    <table class="status-table">
        <tr><th>Поле</th><th>Правило</th></tr>
        <tr><td><strong>ФИО клиента</strong></td><td>Заполняйте без лишних символов и сокращений.</td></tr>
        <tr><td><strong>Номер телефона</strong></td><td><strong>Обязательное поле.</strong> По нему можно найти клиента и связаться по заказу.</td></tr>
        <tr><td><strong>Email</strong></td><td>Нужен для отправки электронных материалов, если это предусмотрено процессом.</td></tr>
        <tr><td><strong>Пол и город</strong></td><td>Заполняются по правилам театра, если эти поля используются в вашей кассе.</td></tr>
    </table>
    <div class="panel panel-red">Если телефон не указан, продажа не пройдёт. Сообщение: <strong>«Номер телефона обязателен»</strong>.</div>
    <h3>Поиск существующего клиента</h3>
    <p>Начните вводить имя или телефон. Выберите подходящую подсказку, чтобы не создать дубликат клиента. Перед сохранением проверьте, что выбран правильный человек.</p>
</section>

<section class="page">
    <h2>7. Скидки и тип билета</h2>
    <div class="figure">__DISCOUNTS__</div>
    <p>В поле типа клиента доступны:</p>
    <ul>
        <li><strong>Взрослый</strong>;</li>
        <li><strong>Детский</strong>;</li>
        <li><strong>Студенческий</strong>;</li>
        <li><strong>Пенсионный</strong>;</li>
        <li><strong>Ручная скидка</strong>.</li>
    </ul>
    <div class="panel panel-blue">
        <strong>Автоматическая скидка:</strong> процент берётся из настроек в момент продажи и фиксируется в билете. Если настройки изменятся позже, старый билет не пересчитывается.
    </div>
    <div class="panel panel-yellow">
        <strong>Ручная скидка:</strong> сначала выберите «Ручная скидка», затем укажите сумму. Не вводите ручную сумму для другого типа билета.
    </div>
    <p>Проверяйте итоговую цену до нажатия кнопки продажи. В билете фиксируются исходная цена, итоговая цена, процент и сумма скидки.</p>
</section>

<section class="page">
    <h2>8. Резерв мест</h2>
    <p>Резерв временно удерживает выбранные места, чтобы другой кассир не продал их во время ожидания клиента.</p>
    <div class="step"><span class="step-num">1</span><div class="step-body">Выберите места на схеме.</div></div>
    <div class="step"><span class="step-num">2</span><div class="step-body">Нажмите <strong>Резерв</strong>.</div></div>
    <div class="step"><span class="step-num">3</span><div class="step-body">Сообщите клиенту срок, до которого нужно завершить оплату.</div></div>
    <div class="step"><span class="step-num">4</span><div class="step-body">Когда клиент готов, выберите эти места и нажмите <strong>Продать</strong>.</div></div>
    <div class="panel panel-yellow">Просроченные ручные резервы снимаются автоматически по времени. Не оставляйте резерв без необходимости.</div>
    <p><strong>Разница:</strong> резерв — это ещё не продажа. Деньги и билет фиксируются только после успешной операции «Продать».</p>
</section>

<section class="page">
    <h2>9. Продажа билета</h2>
    <div class="figure">__SALE__</div>
    <div class="step"><span class="step-num">1</span><div class="step-body">Выберите места и проверьте ряд/место.</div></div>
    <div class="step"><span class="step-num">2</span><div class="step-body">Заполните клиента, особенно номер телефона.</div></div>
    <div class="step"><span class="step-num">3</span><div class="step-body">Выберите тип билета и проверьте скидку.</div></div>
    <div class="step"><span class="step-num">4</span><div class="step-body">Выберите способ оплаты: <strong>Наличные</strong> или <strong>Картой</strong>.</div></div>
    <div class="step"><span class="step-num">5</span><div class="step-body">Получите оплату/подтверждение терминала и нажмите <strong>Продать</strong> один раз.</div></div>
    <div class="panel panel-green"><strong>Успешный результат:</strong> место становится проданным, резерв снимается, билет получает статус «Выдан», а оплата — «Оплачен».</div>
    <div class="panel panel-red"><strong>Если терминал завис:</strong> не создавайте вторую продажу. Сначала проверьте список билетов и транзакцию, затем обратитесь к ответственному.</div>
</section>

<section class="page">
    <h2>10. Что проверить после продажи</h2>
    <ol>
        <li>Место на схеме стало проданным.</li>
        <li>В корзине нет оставшихся мест.</li>
        <li>Появился билет с UID.</li>
        <li>Статус оплаты — «Оплачен».</li>
        <li>Скидка отображается правильным процентом и суммой.</li>
        <li>При необходимости откройте PDF или распечатайте билет.</li>
    </ol>
    <div class="panel panel-blue">
        <strong>Канонические цены билета:</strong><br>
        <code>original_price</code> — цена до скидки;<br>
        <code>discount</code> — зафиксированный процент;<br>
        <code>discount_amount</code> — зафиксированная сумма скидки;<br>
        <code>final_price</code> — сумма к оплате.
    </div>
    <h3>Если клиент хочет несколько билетов</h3>
    <p>Выбирайте все места одного заказа, заранее проверьте лимит билетов в настройках и убедитесь, что итоговая сумма соответствует количеству мест.</p>
</section>

<section class="page">
    <h2>11. Список билетов</h2>
    <p>Откройте раздел <strong>Билеты</strong>, если нужно найти уже созданный билет.</p>
    <h3>Полезные фильтры</h3>
    <ul>
        <li>Дата от / до;</li><li>Сеанс;</li><li>UID билета;</li><li>Статус;</li><li>Оплата;</li><li>Канал;</li><li>Клиент;</li><li>Тип билета;</li><li>Возврат.</li>
    </ul>
    <table class="status-table">
        <tr><th>Статус</th><th>Значение</th></tr>
        <tr><td><span class="badge badge-blue">Выдан</span></td><td>Билет оформлен и может быть использован.</td></tr>
        <tr><td><span class="badge badge-green">Использован</span></td><td>Билет уже проверен на входе.</td></tr>
        <tr><td><span class="badge badge-red">Отменён</span></td><td>Билет возвращён или отменён и недействителен.</td></tr>
    </table>
    <div class="panel panel-yellow">Для поиска лучше использовать UID или номер заказа. Не ориентируйтесь только на имя клиента, если в базе есть однофамильцы.</div>
</section>

<section class="page">
    <h2>12. Возврат билета</h2>
    <div class="figure">__REFUND__</div>
    <h3>Возврат кассового билета</h3>
    <div class="step"><span class="step-num">1</span><div class="step-body">Найдите билет в разделе <strong>Билеты</strong>.</div></div>
    <div class="step"><span class="step-num">2</span><div class="step-body">Нажмите <strong>Возврат</strong>.</div></div>
    <div class="step"><span class="step-num">3</span><div class="step-body">Проверьте клиента, UID, сеанс, место и сумму.</div></div>
    <div class="step"><span class="step-num">4</span><div class="step-body">Выберите способ возврата и подтвердите один раз.</div></div>
    <h3>Возврат онлайн-билета</h3>
    <p>Онлайн-билет возвращается через BCC. В окне возврата банковские поля заполняются системой. Не изменяйте их и не запускайте повторный возврат, если первый запрос уже обрабатывается.</p>
    <div class="panel panel-green">После успешного возврата билет становится «Отменён», место освобождается, а в журнале появляется операция возврата.</div>
    <div class="panel panel-red">Если банк вернул ошибку, не повторяйте операцию вслепую. Зафиксируйте результат и обратитесь к администратору.</div>
</section>

<section class="page">
    <h2>13. Страница «Возвраты»</h2>
    <p>Раздел <strong>Возвраты</strong> нужен для контроля всех оформленных возвратов.</p>
    <ul>
        <li>Источник: кассир или клиент;</li>
        <li>статус: ожидает, подтверждён, выполнен, отклонён;</li>
        <li>метод: наличные, безналичные, эквайринг;</li>
        <li>клиент, UID билета, номер транзакции, номер заказа;</li>
        <li>живая фильтрация и подсказки.</li>
    </ul>
    <div class="panel panel-blue">
        <strong>Правильная проверка:</strong> после возврата найдите запись по номеру заказа или UID и убедитесь, что статус действительно «Выполнен».
    </div>
    <h3>Если возврат не виден</h3>
    <ol>
        <li>Сбросьте фильтр дат.</li>
        <li>Найдите по номеру заказа.</li>
        <li>Проверьте, не находится ли операция в статусе «Ожидает».</li>
        <li>Если это BCC, проверьте логи ошибок при наличии права.</li>
    </ol>
</section>

<section class="page">
    <h2>14. Отчёты</h2>
    <p>Раздел <strong>Отчёты</strong> помогает сверить продажи и возвраты.</p>
    <ul>
        <li>выручка;</li><li>скидки;</li><li>чистая сумма;</li><li>продажи по спектаклям и сеансам;</li><li>возвраты;</li><li>тип клиента и способ оплаты.</li>
    </ul>
    <h3>Ежедневная сверка</h3>
    <div class="step"><span class="step-num">1</span><div class="step-body">Укажите период «Сегодня».</div></div>
    <div class="step"><span class="step-num">2</span><div class="step-body">Проверьте количество проданных билетов.</div></div>
    <div class="step"><span class="step-num">3</span><div class="step-body">Сверьте наличные, карточные операции и возвраты.</div></div>
    <div class="step"><span class="step-num">4</span><div class="step-body">При расхождении найдите конкретный билет или номер заказа, а не пересчитывайте всё вручную.</div></div>
    <div class="panel panel-yellow">Скидка учитывается отдельно от итоговой выручки. Не вычитайте её второй раз вручную.</div>
</section>

<section class="page">
    <h2>15. Ошибки и порядок действий</h2>
    <table class="check-table">
        <tr><th>Сообщение/ситуация</th><th>Что сделать</th></tr>
        <tr><td>«Корзина пуста»</td><td>Выберите хотя бы одно свободное место.</td></tr>
        <tr><td>«Выберите способ оплаты»</td><td>Выберите «Наличные» или «Картой».</td></tr>
        <tr><td>«Номер телефона обязателен»</td><td>Заполните телефон клиента и повторите продажу.</td></tr>
        <tr><td>Место занято</td><td>Проверьте цвет места и владельца резерва.</td></tr>
        <tr><td>Операция банка не подтверждена</td><td>Не повторяйте запрос. Проверьте заказ, транзакцию и сообщите администратору.</td></tr>
        <tr><td>Страница не отвечает</td><td>Не нажимайте кнопку много раз. Сохраните время и номер заказа, затем обратитесь к администратору.</td></tr>
    </table>
    <h3>Логи ошибок</h3>
    <p>Раздел <strong>Логи ошибок</strong> доступен только по отдельному праву. В нём можно открыть технические детали, код ответа и комментарии сервера. Для кассира достаточно сообщить администратору номер заказа и время ошибки.</p>
</section>

<section class="page">
    <h2>16. Чек-лист кассира</h2>
    <h3>Перед началом смены</h3>
    <ul><li>Войти под своей учётной записью.</li><li>Проверить дату и ближайшие сеансы.</li><li>Убедиться, что схема зала загружается.</li><li>Проверить доступность телефона клиента в форме.</li></ul>
    <h3>При каждой продаже</h3>
    <ul><li>Сеанс и зал — правильные.</li><li>Места — правильные.</li><li>Тип билета — правильный.</li><li>Скидка — проверена.</li><li>Телефон и способ оплаты — заполнены.</li><li>Продажа нажата один раз.</li></ul>
    <h3>Перед завершением смены</h3>
    <ul><li>Нет незакрытых резервов без причины.</li><li>Возвраты имеют понятный статус.</li><li>Суммы отчёта сверены.</li><li>Кабинет закрыт кнопкой выхода.</li></ul>
    <div class="panel panel-green"><strong>Короткое правило:</strong> сначала проверьте сеанс и место, затем клиента и скидку, после этого оплату. Любую неоднозначную банковскую операцию не повторяйте — сначала сверяйте результат.</div>
</section>

</body>
</html>
HTML;

$html = str_replace(
    ['__LOGO__', '__TODAY__', '__FLOW__', '__SEATS__', '__DISCOUNTS__', '__SALE__', '__REFUND__'],
    [$logo, $today, $flowSvg, $seatSvg, $discountSvg, $saleSvg, $refundSvg],
    $html
);

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$outputPath = $projectRoot . DIRECTORY_SEPARATOR . 'CASHIER_MANUAL_RU.pdf';
file_put_contents($outputPath, $dompdf->output());
$htmlPath = $projectRoot . DIRECTORY_SEPARATOR . 'CASHIER_MANUAL_RU.html';
file_put_contents($htmlPath, $html);

echo 'Generated: ' . $outputPath . PHP_EOL;
echo 'Size: ' . filesize($outputPath) . ' bytes' . PHP_EOL;
echo 'Generated: ' . $htmlPath . PHP_EOL;
echo 'Size: ' . filesize($htmlPath) . ' bytes' . PHP_EOL;

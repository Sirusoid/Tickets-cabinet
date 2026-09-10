<?php
require_once __DIR__ . '/../init.php';
require_login();

$use_sidebar = true;
$active_menu = 'customers';
$page_title_meta = 'Клиенты';
$panel_title = 'Клиенты';
$panel_subtitle = 'Поиск и просмотр клиентской базы';
$page_styles = ['/assets/css/schedule.css', '/assets/css/customers.css'];
$page_scripts = ['/assets/js/customers_list.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<?php
$nameFilter = trim((string)($_GET['full_name'] ?? ''));
$phoneFilter = normalize_customer_phone(trim((string)($_GET['phone'] ?? '')));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPageParam = (int)($_GET['per_page'] ?? 25);
$perPage = in_array($perPageParam, [25, 50, 100, 500], true) ? $perPageParam : 25;

$where = [];
$params = [];
if ($nameFilter !== '') {
	$where[] = 'full_name LIKE :full_name';
	$params[':full_name'] = '%' . $nameFilter . '%';
}
if ($phoneFilter !== '') {
	$where[] = '(phone LIKE :phone OR phone LIKE :phone_digits)';
	$params[':phone'] = '%' . $phoneFilter . '%';
	$params[':phone_digits'] = '%' . customer_phone_digits($phoneFilter) . '%';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$errorText = '';
$customers = [];
$total = 0;
$pages = 1;
$offset = 0;
try {
	$totalRow = db_fetch_one('SELECT COUNT(*) AS total FROM customers' . $whereSql, $params);
	$total = (int)($totalRow['total'] ?? 0);
	$pages = max(1, (int)ceil($total / $perPage));
	$page = min($page, $pages);
	$offset = ($page - 1) * $perPage;

	$customers = db_fetch_all(
		'SELECT id, full_name, phone, email
		 FROM customers' . $whereSql . '
		 ORDER BY full_name ASC, id ASC
		 LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
		$params
	);
} catch (Throwable $e) {
	$errorText = 'Ошибка загрузки клиентов: ' . $e->getMessage();
}

$queryParams = [];
if ($nameFilter !== '') {
	$queryParams['full_name'] = $nameFilter;
}
if ($phoneFilter !== '') {
	$queryParams['phone'] = $phoneFilter;
}
$queryParams['per_page'] = $perPage;
$buildPageUrl = function ($targetPage) use ($queryParams) {
	$params = $queryParams;
	$params['page'] = $targetPage;
	return '/customers/list.php?' . http_build_query($params);
};
$exportUrl = '/customers/export_excel.php?' . http_build_query([
	'full_name' => $nameFilter,
	'phone' => $phoneFilter,
]);
?>

<div class="page container-full customers-page" style="padding: 0">
	<div class="schedule-panel compact customers-filter-panel">
	  <div class="card-title">Фильтры</div>
	  <form id="customersFilters" class="compact-grid customers-filters" method="get" action="/customers/list.php">
		<div>
			<div class="customers-field-wrap">
			  <input id="customers-name" name="full_name" type="search" class="form-control" value="<?= h($nameFilter) ?>" placeholder="Клиент (ФИО)" aria-label="Клиент (ФИО)" autocomplete="off">
			  <div id="customers-name-suggestions" class="typeahead-dropdown customers-suggestions" role="listbox"></div>
			</div>
		</div>
		<div>
			<div class="customers-field-wrap">
			  <input id="customers-phone" name="phone" type="search" class="form-control" value="<?= h($phoneFilter) ?>" placeholder="Номер телефона" aria-label="Номер телефона" autocomplete="off" inputmode="numeric">
			  <div id="customers-phone-suggestions" class="typeahead-dropdown customers-suggestions" role="listbox"></div>
			</div>
		</div>
		<div class="customers-filter-bottom">
			<div class="customers-per-page">
				<label for="customersPerPage">Клиентов на странице</label>
				<select id="customersPerPage" name="per_page" class="form-control">
					<option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25</option>
					<option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
					<option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
					<option value="500" <?= $perPage === 500 ? 'selected' : '' ?>>500</option>
				</select>
			</div>
			<div class="customers-filters__actions">
				<button type="submit" class="btn btn-secondary">Найти</button>
				<a href="/customers/list.php" class="btn btn-ghost">Сброс</a>
				<a id="customersExport" href="<?= h($exportUrl) ?>" class="btn btn-secondary">Экспорт (Excel)</a>
			</div>
		</div>
	  </form>
	</div>

	<div class="card customers-results-card" id="customersResultsCard">
	<?php if ($errorText !== ''): ?>
		<div class="customers-message customers-message--error"><?= h($errorText) ?></div>
	<?php else: ?>
		<div class="customers-table-wrap">
			<table class="table admin-table table--compact customers-table">
				<thead>
					<tr>
						<th style="width:8%;">№</th>
						<th style="width:12%;">ID</th>
						<th>Полное имя</th>
						<th>Телефон</th>
						<th>E-mail</th>
						<th class="actions-col">Действия</th>
					</tr>
				</thead>
				<tbody id="customersTbody">
					<?php if (empty($customers)): ?>
						<tr><td colspan="6" class="customers-empty">Клиенты по заданным условиям не найдены</td></tr>
					<?php else: ?>
						<?php foreach ($customers as $index => $customer): ?>
							<tr data-id="<?= h($customer['id']) ?>">
								<td><?= $offset + $index + 1 ?></td>
								<td><?= h($customer['id']) ?></td>
								<td><strong><?= h($customer['full_name']) ?></strong></td>
								<td><?= $customer['phone'] !== null && $customer['phone'] !== '' ? h(format_customer_phone($customer['phone'])) : '—' ?></td>
								<td><?= $customer['email'] !== null && $customer['email'] !== '' ? h($customer['email']) : '—' ?></td>
								<td class="actions-col">
									<div class="action-buttons">
										<button type="button" class="btn btn-ghost btn-sm js-edit-customer" data-id="<?= h($customer['id']) ?>">Редактировать</button>
										<button type="button" class="btn btn-danger btn-sm js-delete-customer" data-id="<?= h($customer['id']) ?>" data-name="<?= h($customer['full_name']) ?>">Удалить</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div id="customersPager" class="customers-pager">
			<div id="customersSummary" class="customers-summary">Клиентов: <?= number_format($total, 0, '.', ' ') ?></div>
			<div class="customers-pager__controls">
				<button id="customersPrevPage" type="button" class="btn btn-ghost" aria-label="Предыдущая страница" <?= $page <= 1 ? 'disabled' : '' ?>>←</button>
				<span id="customersCurrentPage"><?= $page ?> / <?= $pages ?></span>
								<button id="customersNextPage" type="button" class="btn btn-ghost" aria-label="Следующая страница" <?= $page >= $pages ? 'disabled' : '' ?>>→</button>
			</div>
		</div>
	<?php endif; ?>
	</div>
</div>

<div id="customerEditModal" class="customer-edit-modal" aria-hidden="true">
	<div class="customer-edit-modal__backdrop" data-customer-edit-close></div>
	<div class="customer-edit-modal__box" role="dialog" aria-modal="true" aria-labelledby="customerEditTitle">
		<div class="customer-edit-modal__head">
			<h3 id="customerEditTitle">Редактирование клиента</h3>
			<button type="button" class="btn btn-ghost btn-sm" data-customer-edit-close aria-label="Закрыть">✕</button>
		</div>
		<form id="customerEditForm" class="customer-edit-form">
			<input type="hidden" id="customerEditId" name="id">
			<div class="customer-edit-form__grid">
				<input id="customerEditName" name="full_name" class="form-control" type="text" placeholder="Полное имя" required>
				<input id="customerEditPhone" name="phone" class="form-control" type="tel" placeholder="Телефон" inputmode="numeric">
				<input id="customerEditEmail" name="email" class="form-control" type="email" placeholder="E-mail">
				<input id="customerEditCity" name="city" class="form-control" type="text" placeholder="Город">
				<input id="customerEditBirthDate" name="birth_date" class="form-control" type="date" aria-label="Дата рождения">
				<select id="customerEditGender" name="gender" class="form-control" aria-label="Пол">
					<option value="">Пол не указан</option>
					<option value="male">Мужской</option>
					<option value="female">Женский</option>
					<option value="other">Другое</option>
				</select>
			</div>
			<textarea id="customerEditNote" name="note" class="form-control" placeholder="Примечание"></textarea>
			<div class="customer-edit-form__actions">
				<button type="button" class="btn btn-ghost" data-customer-edit-close>Отмена</button>
				<button type="submit" class="btn btn-primary">Сохранить</button>
			</div>
			<div id="customerEditError" class="customer-edit-form__error" role="alert"></div>
		</form>
	</div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>


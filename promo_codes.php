<?php
require_once __DIR__ . '/init.php';
require_login();
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/promo_codes.php';

if (!isset($pdo) || !($pdo instanceof PDO) || !user_has_permission($pdo, 'promo_codes', false)) {
    http_response_code(403);
    exit('Доступ к промокодам запрещён.');
}

$success = '';
$error = '';
$migrationRequired = false;
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');
$sessions = [];
$promoBaseUrl = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/') . '/tickets/widget.php';

function promo_generate_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $lookup = $pdo->prepare('SELECT id FROM promo_codes WHERE code = :code LIMIT 1');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = 'ZH' . $alphabet[random_int(0, strlen($alphabet) - 1)]
            . $alphabet[random_int(0, strlen($alphabet) - 1)]
            . $alphabet[random_int(0, strlen($alphabet) - 1)]
            . $alphabet[random_int(0, strlen($alphabet) - 1)];
        $lookup->execute([':code' => $code]);
        if (!$lookup->fetchColumn()) {
            return $code;
        }
    }
    throw new RuntimeException('Не удалось подобрать свободный код. Попробуйте ещё раз.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Форма устарела. Обновите страницу и повторите действие.';
    } else {
        try {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'create') {
                $code = promo_normalize_code((string)($_POST['code'] ?? ''));
                if ($code === '') {
                    $code = promo_generate_code($pdo);
                }
                if (!preg_match('/^[A-Z0-9]{4,12}$/', $code)) {
                    throw new InvalidArgumentException('Код должен содержать от 4 до 12 латинских букв или цифр.');
                }

                $type = (string)($_POST['discount_type'] ?? '');
                $value = filter_var($_POST['discount_value'] ?? null, FILTER_VALIDATE_FLOAT);
                if (!in_array($type, ['percent', 'fixed'], true)) {
                    throw new InvalidArgumentException('Выберите тип скидки.');
                }
                if ($value === false || $value <= 0 || ($type === 'percent' && $value > 100)) {
                    throw new InvalidArgumentException($type === 'percent'
                        ? 'Скидка в процентах должна быть больше 0 и не превышать 100%.'
                        : 'Укажите сумму скидки больше 0 тенге.');
                }

                $insert = $pdo->prepare('INSERT INTO promo_codes (code, discount_type, discount_value, created_by) VALUES (:code, :type, :value, :created_by)');
                $insert->execute([
                    ':code' => $code,
                    ':type' => $type,
                    ':value' => number_format((float)$value, 2, '.', ''),
                    ':created_by' => (int)($_SESSION['user']['id'] ?? 0) ?: null,
                ]);
                $success = 'Промокод ' . $code . ' создан.';
            } elseif ($action === 'update') {
                $promoId = filter_var($_POST['promo_id'] ?? '', FILTER_VALIDATE_INT);
                $code = promo_normalize_code((string)($_POST['code'] ?? ''));
                $type = (string)($_POST['discount_type'] ?? '');
                $value = filter_var($_POST['discount_value'] ?? null, FILTER_VALIDATE_FLOAT);
                if (!$promoId || $promoId < 1 || !preg_match('/^[A-Z0-9]{4,12}$/', $code)) {
                    throw new InvalidArgumentException('Проверьте промокод и повторите сохранение.');
                }
                if (!in_array($type, ['percent', 'fixed'], true)) {
                    throw new InvalidArgumentException('Выберите тип скидки.');
                }
                if ($value === false || $value <= 0 || ($type === 'percent' && $value > 100)) {
                    throw new InvalidArgumentException($type === 'percent'
                        ? 'Скидка в процентах должна быть больше 0 и не превышать 100%.'
                        : 'Укажите сумму скидки больше 0 тенге.');
                }

                $currentStmt = $pdo->prepare('SELECT code FROM promo_codes WHERE id = :id LIMIT 1');
                $currentStmt->execute([':id' => $promoId]);
                $currentCode = $currentStmt->fetchColumn();
                if ($currentCode === false) {
                    throw new InvalidArgumentException('Промокод уже удалён.');
                }
                if ($code !== (string)$currentCode) {
                    $usageStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE promo_code = :code');
                    $usageStmt->execute([':code' => $currentCode]);
                    if ((int)$usageStmt->fetchColumn() > 0) {
                        throw new InvalidArgumentException('Код уже использовался и не может быть переименован. Измените размер скидки или создайте новый промокод.');
                    }
                }

                $update = $pdo->prepare('UPDATE promo_codes SET code = :code, discount_type = :type, discount_value = :value WHERE id = :id');
                $update->execute([
                    ':code' => $code,
                    ':type' => $type,
                    ':value' => number_format((float)$value, 2, '.', ''),
                    ':id' => $promoId,
                ]);
                $success = 'Промокод ' . $code . ' обновлён.';
            } elseif ($action === 'toggle') {
                $promoId = filter_var($_POST['promo_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$promoId || $promoId < 1) {
                    throw new InvalidArgumentException('Промокод не найден.');
                }
                $update = $pdo->prepare('UPDATE promo_codes SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
                $update->execute([':id' => $promoId]);
                if ($update->rowCount() !== 1) {
                    throw new InvalidArgumentException('Не удалось изменить статус промокода.');
                }
                $success = 'Статус промокода обновлён.';
            } elseif ($action === 'delete') {
                $promoId = filter_var($_POST['promo_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$promoId || $promoId < 1) {
                    throw new InvalidArgumentException('Промокод не найден.');
                }
                $delete = $pdo->prepare('DELETE FROM promo_codes WHERE id = :id');
                $delete->execute([':id' => $promoId]);
                if ($delete->rowCount() !== 1) {
                    throw new InvalidArgumentException('Промокод уже удалён.');
                }
                $success = 'Промокод удалён. История уже оплаченных билетов сохранена.';
            } else {
                throw new InvalidArgumentException('Неизвестное действие.');
            }
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('promo_codes save error: ' . $exception->getMessage());
            if ((string)$exception->getCode() === '23000') {
                $error = 'Такой промокод уже существует. Укажите другой код.';
            } elseif (strpos($exception->getMessage(), 'promo_codes') !== false) {
                $migrationRequired = true;
                $error = 'Таблица промокодов не найдена. Сначала выполните sql/migration_promo_codes.sql на рабочей базе.';
            } else {
                $error = 'Не удалось сохранить промокод. Подробности записаны в журнал ошибок.';
            }
        } catch (Throwable $exception) {
            error_log('promo_codes save error: ' . $exception->getMessage());
            $error = $exception->getMessage();
        }
    }
}

$promos = [];
if (!$migrationRequired) {
    try {
        $sessions = db_fetch_all(
            "SELECT s.id, s.start_time, e.title AS event_title
             FROM schedules s
             JOIN events e ON e.id = s.event_id
             WHERE s.status IN ('upcoming', 'active') AND s.start_time >= NOW()
             ORDER BY s.start_time ASC
             LIMIT 100"
        );
        $promos = db_fetch_all(
            "SELECT p.id, p.code, p.discount_type, p.discount_value, p.is_active, p.created_at,
                    COUNT(DISTINCT t.payment_session_id) AS used_count
             FROM promo_codes p
             LEFT JOIN tickets t ON t.promo_code = p.code
             GROUP BY p.id, p.code, p.discount_type, p.discount_value, p.is_active, p.created_at
             ORDER BY p.created_at DESC, p.id DESC"
        );
    } catch (Throwable $exception) {
        error_log('promo_codes list error: ' . $exception->getMessage());
        if (strpos($exception->getMessage(), 'promo_codes') !== false || strpos($exception->getMessage(), 'promo_code') !== false) {
            $migrationRequired = true;
            $error = 'Для работы с промокодами выполните sql/migration_promo_codes.sql на рабочей базе.';
        } else {
            $error = 'Не удалось загрузить список промокодов.';
        }
    }
}

$use_sidebar = true;
$active_menu = 'promo_codes';
$page_title_meta = 'Промокоды';
$page_styles = ['/assets/css/forms.css', '/assets/css/settings.css', '/assets/css/promo-codes.css'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/panel.php';
?>

<div class="promo-page page container-full">
    <?php if ($success !== ''): ?><div class="alert settings-alert settings-alert--success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert--danger"><?= h($error) ?></div><?php endif; ?>

    <section class="promo-hero">
        <div class="promo-hero__copy">
            <span class="promo-eyebrow">Продвижение · Онлайн-продажи</span>
            <h2>Промокоды<br>для ваших зрителей</h2>
            <p>Создавайте короткие коды и выбирайте: процент от заказа или фиксированная скидка в тенге.</p>
        </div>
        <div class="promo-ticket-art" aria-hidden="true">
            <span>ЖАС САХНА</span>
            <strong>%</strong>
            <small>ВПЕЧАТЛЕНИЯ СО СКИДКОЙ</small>
        </div>
    </section>

    <section class="promo-create card" id="promo-editor">
        <div class="promo-section-heading">
            <div><span class="promo-eyebrow" id="promo-form-eyebrow">Новая акция</span><h2 id="promo-form-title">Создать промокод</h2></div>
            <span class="promo-count"><?= count($promos) ?> кодов</span>
        </div>
        <form method="post" class="promo-create__form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" id="promo-form-action" value="create">
            <input type="hidden" name="promo_id" id="promo-form-id" value="">
            <label class="promo-field promo-field--code">
                <span>Короткий код</span>
                <div class="promo-code-input">
                    <input id="promo-code-value" name="code" type="text" maxlength="12" pattern="[A-Za-z0-9]{4,12}" placeholder="Сгенерировать автоматически" autocomplete="off">
                    <button id="promo-generate" type="button" class="btn btn-ghost">Создать код</button>
                </div>
                <small>4–12 латинских букв или цифр, без пробелов</small>
            </label>
            <label class="promo-field promo-field--type">
                <span>Тип скидки</span>
                <select id="promo-discount-type" name="discount_type" required>
                    <option value="percent">Процент от заказа</option>
                    <option value="fixed">Сумма в тенге</option>
                </select>
            </label>
            <label class="promo-field">
                <span id="promo-discount-label">Размер скидки, %</span>
                <div class="promo-value-input">
                    <input id="promo-discount-value" name="discount_value" type="number" min="0.01" max="100" step="0.01" required>
                    <span id="promo-discount-suffix">%</span>
                </div>
                <small id="promo-discount-hint">Не более 100% от суммы заказа</small>
            </label>
            <div class="promo-form-actions">
                <button type="submit" id="promo-submit" class="btn btn-primary promo-submit" <?= $migrationRequired ? 'disabled' : '' ?>>Сохранить промокод</button>
                <button type="button" id="promo-cancel-edit" class="btn btn-ghost promo-cancel-edit" hidden>Отмена</button>
            </div>
        </form>
    </section>

    <section class="promo-list-section">
        <div class="promo-section-heading">
            <div><span class="promo-eyebrow">Ваши акции</span><h2>Список промокодов</h2></div>
            <label class="promo-session-picker">
                <span>Ссылка ведёт на сеанс</span>
                <select id="promo-share-session" <?= empty($sessions) ? 'disabled' : '' ?>>
                    <?php if (!$sessions): ?>
                        <option value="">Нет будущих сеансов</option>
                    <?php else: foreach ($sessions as $session): ?>
                        <option value="<?= (int)$session['id'] ?>">
                            <?= h($session['event_title'] . ' · ' . date('d.m H:i', strtotime($session['start_time']))) ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </label>
        </div>
        <?php if (!$promos && !$migrationRequired): ?>
            <div class="promo-empty card"><strong>Промокодов пока нет</strong><span>Создайте первый код — он появится здесь.</span></div>
        <?php elseif ($promos): ?>
            <div class="promo-table-wrap card">
                <table class="promo-table">
                    <thead><tr><th>Код</th><th>Скидка</th><th>Заказы</th><th>Статус</th><th>Действия</th></tr></thead>
                    <tbody>
                    <?php foreach ($promos as $promo): ?>
                        <?php
                        $discountLabel = $promo['discount_type'] === 'percent'
                            ? rtrim(rtrim(number_format((float)$promo['discount_value'], 2, '.', ''), '0'), '.') . '%'
                            : number_format((float)$promo['discount_value'], 2, '.', ' ') . ' ₸';
                        ?>
                        <tr class="promo-row" data-id="<?= (int)$promo['id'] ?>" data-code="<?= h($promo['code']) ?>"
                            data-type="<?= h($promo['discount_type']) ?>" data-value="<?= h((string)$promo['discount_value']) ?>"
                            data-used="<?= (int)$promo['used_count'] ?>">
                            <td>
                                <code class="promo-code-badge"><?= h($promo['code']) ?></code>
                                <button type="button" class="promo-icon-button promo-copy-code" aria-label="Копировать промокод" title="Копировать промокод" data-code="<?= h($promo['code']) ?>">⧉</button>
                            </td>
                            <td><?= h($discountLabel) ?></td>
                            <td>
                                <strong><?= number_format((int)$promo['used_count'], 0, '.', ' ') ?></strong>
                                <small class="promo-table-note">оплаченных заказов</small>
                            </td>
                            <td><span class="promo-state<?= (int)$promo['is_active'] ? ' is-active' : '' ?>"><?= (int)$promo['is_active'] ? 'Активен' : 'Отключён' ?></span></td>
                            <td class="promo-actions-cell">
                                <div class="promo-row-actions">
                                    <button type="button" class="btn btn-ghost btn-sm promo-share-link" data-code="<?= h($promo['code']) ?>" <?= empty($sessions) ? 'disabled title="Нет будущих сеансов"' : '' ?>>Ссылка для Instagram</button>
                                    <a class="btn btn-ghost btn-sm promo-open-link" href="<?= h($promoBaseUrl . ($sessions ? '?session_id=' . (int)$sessions[0]['id'] . '&promo_code=' . rawurlencode((string)$promo['code']) . '&utm_source=instagram&utm_medium=social' : '')) ?>" target="_blank" rel="noopener noreferrer" <?= empty($sessions) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Проверить</a>
                                    <button type="button" class="btn btn-ghost btn-sm promo-edit">Изменить</button>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="promo_id" value="<?= (int)$promo['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm"><?= (int)$promo['is_active'] ? 'Отключить' : 'Включить' ?></button>
                                </form>
                                <form method="post" class="promo-delete-form">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="promo_id" value="<?= (int)$promo['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm promo-delete">Удалить</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
(function () {
    var type = document.getElementById('promo-discount-type');
    var label = document.getElementById('promo-discount-label');
    var suffix = document.getElementById('promo-discount-suffix');
    var hint = document.getElementById('promo-discount-hint');
    var amount = document.getElementById('promo-discount-value');
    var code = document.getElementById('promo-code-value');
    var formAction = document.getElementById('promo-form-action');
    var formId = document.getElementById('promo-form-id');
    var formTitle = document.getElementById('promo-form-title');
    var formEyebrow = document.getElementById('promo-form-eyebrow');
    var submitButton = document.getElementById('promo-submit');
    var cancelButton = document.getElementById('promo-cancel-edit');
    var shareSession = document.getElementById('promo-share-session');
    var promoBaseUrl = <?= json_encode($promoBaseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }
        return new Promise(function (resolve, reject) {
            var input = document.createElement('textarea');
            input.value = value;
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            var copied = document.execCommand('copy');
            input.remove();
            if (copied) resolve();
            else reject(new Error('Буфер обмена недоступен.'));
        });
    }

    function showCopyResult(button, successText, value) {
        copyText(value).then(function () {
            var previousText = button.textContent;
            button.textContent = successText;
            button.classList.add('is-copied');
            window.setTimeout(function () {
                button.textContent = previousText;
                button.classList.remove('is-copied');
            }, 1800);
        }).catch(function (error) {
            window.prompt('Скопируйте ссылку вручную:', value);
            console.error('Promo link copy failed:', error);
        });
    }

    function updateShareLinks() {
        if (!shareSession || !shareSession.value) return;
        document.querySelectorAll('.promo-open-link').forEach(function (link) {
            var url = new URL(promoBaseUrl);
            url.searchParams.set('session_id', shareSession.value);
            url.searchParams.set('promo_code', link.closest('.promo-row').dataset.code);
            url.searchParams.set('utm_source', 'instagram');
            url.searchParams.set('utm_medium', 'social');
            link.href = url.toString();
        });
    }

    function setCreateMode() {
        formAction.value = 'create';
        formId.value = '';
        formTitle.textContent = 'Создать промокод';
        formEyebrow.textContent = 'Новая акция';
        submitButton.textContent = 'Сохранить промокод';
        code.readOnly = false;
        code.value = '';
        amount.value = '';
        type.value = 'percent';
        cancelButton.hidden = true;
        updateType();
    }

    function updateType() {
        var percent = type.value === 'percent';
        label.textContent = percent ? 'Размер скидки, %' : 'Размер скидки, ₸';
        suffix.textContent = percent ? '%' : '₸';
        hint.textContent = percent ? 'Не более 100% от суммы заказа' : 'Скидка применяется ко всему заказу';
        amount.max = percent ? '100' : '';
    }
    type.addEventListener('change', updateType);
    document.getElementById('promo-generate').addEventListener('click', function () {
        var generated = 'ZH';
        for (var i = 0; i < 4; i++) generated += alphabet[Math.floor(Math.random() * alphabet.length)];
        code.value = generated;
        code.focus();
    });
    code.addEventListener('input', function () { code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12); });
    document.querySelectorAll('.promo-edit').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = button.closest('.promo-row');
            formAction.value = 'update';
            formId.value = row.dataset.id;
            code.value = row.dataset.code;
            code.readOnly = Number(row.dataset.used) > 0;
            type.value = row.dataset.type;
            amount.value = row.dataset.value;
            formTitle.textContent = 'Изменить промокод';
            formEyebrow.textContent = 'Редактирование акции';
            submitButton.textContent = 'Сохранить изменения';
            cancelButton.hidden = false;
            updateType();
            document.getElementById('promo-editor').scrollIntoView({ behavior: 'smooth', block: 'start' });
            amount.focus();
        });
    });
    cancelButton.addEventListener('click', setCreateMode);
    shareSession.addEventListener('change', updateShareLinks);
    document.querySelectorAll('.promo-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var promoCode = form.closest('.promo-row').dataset.code;
            if (!window.confirm('Удалить промокод ' + promoCode + '? Уже оплаченные билеты сохранятся.')) {
                event.preventDefault();
            }
        });
    });
    document.querySelectorAll('.promo-copy-code').forEach(function (button) {
        button.addEventListener('click', function () {
            showCopyResult(button, 'Скопировано', button.dataset.code);
        });
    });
    document.querySelectorAll('.promo-share-link').forEach(function (button) {
        button.addEventListener('click', function () {
            var sessionId = shareSession.value;
            if (!sessionId) return;
            var url = new URL(promoBaseUrl);
            url.searchParams.set('session_id', sessionId);
            url.searchParams.set('promo_code', button.dataset.code);
            url.searchParams.set('utm_source', 'instagram');
            url.searchParams.set('utm_medium', 'social');
            var openLink = button.closest('.promo-row-actions').querySelector('.promo-open-link');
            if (openLink) openLink.href = url.toString();
            showCopyResult(button, 'Ссылка скопирована', url.toString());
        });
    });
    updateShareLinks();
    updateType();
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

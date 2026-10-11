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

    <section class="promo-create card">
        <div class="promo-section-heading">
            <div><span class="promo-eyebrow">Новая акция</span><h2>Создать промокод</h2></div>
            <span class="promo-count"><?= count($promos) ?> кодов</span>
        </div>
        <form method="post" class="promo-create__form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="create">
            <label class="promo-field promo-field--code">
                <span>Короткий код</span>
                <div class="promo-code-input">
                    <input id="promo-code-value" name="code" type="text" maxlength="12" pattern="[A-Za-z0-9]{4,12}" placeholder="Сгенерировать автоматически" autocomplete="off">
                    <button id="promo-generate" type="button" class="btn btn-ghost">Создать код</button>
                </div>
                <small>4–12 латинских букв или цифр, без пробелов</small>
            </label>
            <label class="promo-field">
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
            <button type="submit" class="btn btn-primary promo-submit" <?= $migrationRequired ? 'disabled' : '' ?>>Сохранить промокод</button>
        </form>
    </section>

    <section class="promo-list-section">
        <div class="promo-section-heading">
            <div><span class="promo-eyebrow">Ваши акции</span><h2>Список промокодов</h2></div>
        </div>
        <?php if (!$promos && !$migrationRequired): ?>
            <div class="promo-empty card"><strong>Промокодов пока нет</strong><span>Создайте первый код — он появится здесь.</span></div>
        <?php elseif ($promos): ?>
            <div class="promo-table-wrap card">
                <table class="promo-table">
                    <thead><tr><th>Код</th><th>Скидка</th><th>Заказов</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($promos as $promo): ?>
                        <?php
                        $discountLabel = $promo['discount_type'] === 'percent'
                            ? rtrim(rtrim(number_format((float)$promo['discount_value'], 2, '.', ''), '0'), '.') . '%'
                            : number_format((float)$promo['discount_value'], 2, '.', ' ') . ' ₸';
                        ?>
                        <tr>
                            <td><code class="promo-code-badge"><?= h($promo['code']) ?></code></td>
                            <td><?= h($discountLabel) ?></td>
                            <td><?= number_format((int)$promo['used_count'], 0, '.', ' ') ?></td>
                            <td><span class="promo-state<?= (int)$promo['is_active'] ? ' is-active' : '' ?>"><?= (int)$promo['is_active'] ? 'Активен' : 'Отключён' ?></span></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="promo_id" value="<?= (int)$promo['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm"><?= (int)$promo['is_active'] ? 'Отключить' : 'Включить' ?></button>
                                </form>
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
    var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
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
    updateType();
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

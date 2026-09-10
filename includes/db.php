<?php
// includes/db.php

/**
 * Создаёт и возвращает PDO соединение.
 * Не выводит подробные ошибки в ответ клиенту — логирует их.
 */
function db_connect()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Используем константы из config.php: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // PDO::ATTR_PERSISTENT => true, // включать только при необходимости и после тестирования
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Логируем подробную ошибку, но не выводим её пользователю
        error_log('DB connection error: ' . $e->getMessage());
        // Бросаем исключение дальше, чтобы вызывающий код мог корректно обработать ситуацию
        throw $e;
    }

    return $pdo;
}

/**
 * Выполняет подготовленный запрос и возвращает PDOStatement.
 * Бросает исключение в случае ошибки.
 *
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 * @throws PDOException
 */
function db_query($sql, $params = [])
{
    $stmt = db_connect()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Возвращает все строки результата как массив ассоциативных массивов.
 *
 * @param string $sql
 * @param array $params
 * @return array
 */
function db_fetch_all($sql, $params = [])
{
    $stmt = db_query($sql, $params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $rows === false ? [] : $rows;
}

/**
 * Возвращает одну строку результата или null, если строк нет.
 *
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function db_fetch_one($sql, $params = [])
{
    $stmt = db_query($sql, $params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

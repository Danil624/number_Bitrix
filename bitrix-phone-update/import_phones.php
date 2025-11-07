<?php

// Подключаем необходимые классы
require_once 'Bitrix24API.php';
require_once 'CSVReader.php';
require_once 'PhoneNumberImporter.php';

/**
 * Основная функция
 */
function main() {
    echo "\033[36m" . str_repeat("=", 60) . "\033[0m\n";
    echo "\033[36m=== ИМПОРТ ВНУТРЕННИХ НОМЕРОВ В BITRIX24 ===\033[0m\n";
    echo "\033[36m" . str_repeat("=", 60) . "\033[0m\n\n";
    
    // Конфигурация - ВАШ ВЕБХУК УЖЕ ВСТАВЛЕН
    $config = [
        'webhook_url' => 'https://ng-servis.bitrix24.ru/rest/1306/lucjp0pyi2pzw5r0/',
        'data_file' => 'users_with_numbers_fixed.csv', // ИЗМЕНИЛ НА ИСПРАВЛЕННЫЙ ФАЙЛ
        'test_mode' => false
    ];
    
    try {
        // Проверка обязательных параметров
        if (empty($config['webhook_url'])) {
            throw new Exception("URL вебхука не указан");
        }
        
        // Сначала проверяем исправленный файл, если нет - проверяем исходный
        if (!file_exists($config['data_file'])) {
            echo "Исправленный файл '{$config['data_file']}' не найден.\n";
            
            // Проверяем существование исходного файла
            $originalFile = 'users_with_numbers.csv';
            if (file_exists($originalFile)) {
                echo "Найден исходный файл '{$originalFile}'. Сначала запустите исправление:\n";
                echo "php fix_csv.php\n";
            } else {
                echo "Файлы с данными не найдены.\n";
                echo "Создать шаблонный файл? (y/n): ";
                $answer = trim(fgets(STDIN));
                
                if (strtolower($answer) === 'y') {
                    $csvReader = new CSVReader();
                    $csvReader->createTemplate($config['data_file']);
                    echo "Шаблонный файл '{$config['data_file']}' создан.\n";
                    echo "Заполните его данными и запустите скрипт снова.\n";
                }
            }
            return;
        }
        
        echo "Настройки:\n";
        echo "- Вебхук: " . substr($config['webhook_url'], 0, 50) . "...\n";
        echo "- Файл данных: {$config['data_file']}\n";
        echo "- Режим: " . ($config['test_mode'] ? 'ТЕСТОВЫЙ' : 'РАБОЧИЙ') . "\n\n";
        
        echo "Продолжить импорт? (y/n): ";
        $answer = trim(fgets(STDIN));
        
        if (strtolower($answer) !== 'y') {
            echo "Импорт отменен.\n";
            return;
        }
        
        // Создание импортера
        $importer = new PhoneNumberImporter($config['webhook_url']);
        
        // Запуск импорта
        $stats = $importer->import($config['data_file']);
        
        echo "\n\033[32mИмпорт завершен!\033[0m\n";
        
    } catch (Exception $e) {
        echo "\n\033[31mОШИБКА: " . $e->getMessage() . "\033[0m\n";
        exit(1);
    }
}

/**
 * Функция для получения списка пользователей
 */
function getUsersList() {
    // ВАШ ВЕБХУК УЖЕ ВСТАВЛЕН
    $webhookUrl = 'https://ng-servis.bitrix24.ru/rest/1306/lucjp0pyi2pzw5r0/';
    
    if (empty($webhookUrl)) {
        echo "URL вебхука не указан\n";
        return;
    }
    
    $api = new Bitrix24API($webhookUrl);
    
    echo "Получение списка пользователей...\n";
    $users = $api->getAllUsers();
    
    if (empty($users)) {
        echo "Не удалось получить пользователей\n";
        return;
    }
    
    echo "Получено пользователей: " . count($users) . "\n";
    
    // Подготовка данных для CSV
    $data = [];
    foreach ($users as $user) {
        $data[] = [
            'ID' => $user['ID'],
            'NAME' => $user['NAME'] ?? '',
            'LAST_NAME' => $user['LAST_NAME'] ?? '',
            'EMAIL' => $user['EMAIL'] ?? '',
            'WORK_POSITION' => $user['WORK_POSITION'] ?? '',
            'UF_PHONE_INNER' => $user['UF_PHONE_INNER'] ?? '',
            'PERSONAL_MOBILE' => $user['PERSONAL_MOBILE'] ?? '',
            'ACTIVE' => $user['ACTIVE'] ?? '',
            'UF_DEPARTMENT' => !empty($user['UF_DEPARTMENT']) ? 'есть' : 'нет'
        ];
    }
    
    // Сохранение в CSV
    $filename = 'bitrix24_users_' . date('Y-m-d_H-i-s') . '.csv';
    $fp = fopen($filename, 'w');
    
    // Заголовки
    fputcsv($fp, array_keys($data[0]));
    
    // Данные
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    
    echo "Список пользователей сохранен в: {$filename}\n";
    echo "В файле также указаны статусы активности пользователей.\n";
}

// Обработка аргументов командной строки
if (isset($argv[1])) {
    switch ($argv[1]) {
        case 'get-users':
            getUsersList();
            break;
        case 'help':
            echo "Доступные команды:\n";
            echo "  php import_phones.php          - Запуск импорта (использует исправленный файл)\n";
            echo "  php import_phones.php get-users - Получить список пользователей\n";
            echo "  php import_phones.php help     - Справка\n";
            break;
        default:
            echo "Неизвестная команда. Используйте 'php import_phones.php help' для справки.\n";
    }
} else {
    // Запуск основного скрипта
    main();
}
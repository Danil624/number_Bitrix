<?php

class PhoneNumberImporter {
    private $api;
    private $csvReader;
    private $results = [];
    private $stats = [
        'total' => 0,
        'success' => 0,
        'errors' => 0,
        'skipped' => 0
    ];
    /**
 * Проверяет, является ли номер временным (не импортировать такие номера)
 */
private function isTemporaryPhone($phone) {
    return strpos($phone, 'TEMP_') === 0;
}
    public function __construct($webhookUrl) {
        $this->api = new Bitrix24API($webhookUrl);
        $this->csvReader = new CSVReader();
    }
    
    /**
     * Основной метод импорта
     */
    public function import($csvFile) {
        $this->log("Начало импорта из файла: " . basename($csvFile));
        $this->log("Время начала: " . date('Y-m-d H:i:s'));
        
        try {
            // Чтение данных из CSV
            $data = $this->csvReader->readFile($csvFile);
            $this->stats['total'] = count($data);
            
            $this->log("Прочитано записей: " . $this->stats['total']);
            
            if ($this->stats['total'] === 0) {
                throw new Exception("Файл не содержит данных для импорта");
            }
            
            // Валидация данных
            $validationErrors = $this->csvReader->validateData($data, ['ID', 'UF_PHONE_INNER']);
            
            if (!empty($validationErrors)) {
                $this->log("Обнаружены ошибки валидации:", 'ERROR');
                foreach ($validationErrors as $error) {
                    $this->log("  - {$error}", 'ERROR');
                }
                throw new Exception("Файл содержит ошибки валидации. Исправьте их и попробуйте снова.");
            }
            
            $this->log("Валидация данных пройдена успешно", 'SUCCESS');
            
            // Обработка каждой записи
            $this->log("Начало обработки записей...");
            
            foreach ($data as $index => $row) {
                $this->processRow($row, $index + 2);
                
                // Пауза между запросами чтобы не превысить лимиты API
                usleep(300000); // 0.3 секунды
            }
            
            $this->generateReport();
            
        } catch (Exception $e) {
            $this->log("Критическая ошибка: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
        
        return $this->stats;
    }
    
    /**
     * Обработка одной строки данных
     */
    private function processRow($row, $rowNumber) {
        $userId = $row['ID'];
    $phone = $this->csvReader->validatePhone($row['UF_PHONE_INNER']);
    
    // Пропуск пустых значений
    if (empty($userId) || empty($phone)) {
        $this->stats['skipped']++;
        $this->results[] = [
            'status' => 'skipped',
            'message' => 'Пустые данные',
            'user_id' => $userId,
            'phone' => $phone,
            'row' => $rowNumber,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->log("Строка {$rowNumber}: Пропуск - пустые данные (ID: {$userId})", 'WARNING');
        return;
    }
    
    // Пропуск временных номеров
    if ($this->isTemporaryPhone($row['UF_PHONE_INNER'])) {
        $this->stats['skipped']++;
        $this->results[] = [
            'status' => 'skipped', 
            'message' => 'Временный номер (пропуск)',
            'user_id' => $userId,
            'phone' => $phone,
            'row' => $rowNumber,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->log("Строка {$rowNumber}: Пропуск - временный номер (ID: {$userId})", 'WARNING');
        return;
    }
        
        // Подготовка полей для обновления
        $fields = ['UF_PHONE_INNER' => $phone];
        
        // Добавление дополнительных полей если они есть
        $additionalFields = ['NAME', 'LAST_NAME', 'EMAIL', 'WORK_POSITION'];
        foreach ($additionalFields as $field) {
            if (isset($row[$field]) && !empty(trim($row[$field]))) {
                $fields[$field] = trim($row[$field]);
            }
        }
        
        $this->log("Строка {$rowNumber}: Обновление пользователя {$userId} -> номер {$phone}");
        
        // Вызов API
        $result = $this->api->updateUser($userId, $fields);
        
        // Обработка результата
        if (isset($result['result']) && $result['result'] === true) {
            $this->stats['success']++;
            $this->results[] = [
                'status' => 'success',
                'message' => 'Номер успешно обновлен',
                'user_id' => $userId,
                'phone' => $phone,
                'row' => $rowNumber,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->log("✓ Успешно обновлен пользователь {$userId}", 'SUCCESS');
        } else {
            $this->stats['errors']++;
            $errorMsg = $result['error_description'] ?? 'Неизвестная ошибка';
            $this->results[] = [
                'status' => 'error',
                'message' => $errorMsg,
                'user_id' => $userId,
                'phone' => $phone,
                'row' => $rowNumber,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->log("✗ Ошибка для пользователя {$userId}: {$errorMsg}", 'ERROR');
        }
    }
    
    /**
     * Генерация отчетов
     */
    private function generateReport() {
        $timestamp = date('Y-m-d_H-i-s');
        $reportFile = "import_report_{$timestamp}.csv";
        $errorFile = "import_errors_{$timestamp}.csv";
        
        // Полный отчет
        $this->saveReportToCSV($this->results, $reportFile);
        
        // Отчет только об ошибках
        $errors = array_filter($this->results, function($item) {
            return $item['status'] === 'error';
        });
        
        if (!empty($errors)) {
            $this->saveReportToCSV($errors, $errorFile);
        }
        
        // Отчет только об успешных операциях
        $success = array_filter($this->results, function($item) {
            return $item['status'] === 'success';
        });
        
        if (!empty($success)) {
            $this->saveReportToCSV($success, "import_success_{$timestamp}.csv");
        }
        
        // Вывод статистики в консоль
        $this->log("\n" . str_repeat("=", 50));
        $this->log("ФИНАЛЬНЫЙ ОТЧЕТ");
        $this->log(str_repeat("=", 50));
        $this->log("Всего записей в файле: " . $this->stats['total']);
        $this->log("Успешно обновлено: " . $this->stats['success']);
        $this->log("Ошибок: " . $this->stats['errors']);
        $this->log("Пропущено: " . $this->stats['skipped']);
        $this->log("Полный отчет сохранен в: {$reportFile}");
        
        if (!empty($errors)) {
            $this->log("Список ошибок сохранен в: {$errorFile}");
        }
        if (!empty($success)) {
            $this->log("Список успешных операций сохранен в: import_success_{$timestamp}.csv");
        }
        
        $this->log("Время завершения: " . date('Y-m-d H:i:s'));
    }
    
    /**
     * Сохранение отчета в CSV
     */
    private function saveReportToCSV($data, $filename) {
        if (empty($data)) {
            return;
        }
        
        $fp = fopen($filename, 'w');
        
        // Заголовки
        fputcsv($fp, array_keys($data[0]));
        
        // Данные
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
    }
    
    /**
     * Логирование
     */
    private function log($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $colors = [
            'SUCCESS' => "\033[32m", // Зеленый
            'ERROR' => "\033[31m",   // Красный
            'WARNING' => "\033[33m", // Желтый
            'INFO' => "\033[36m",    // Голубой
        ];
        
        $color = $colors[$type] ?? "\033[0m";
        $reset = "\033[0m";
        
        $formattedMessage = "{$color}[{$timestamp}] [{$type}] {$message}{$reset}\n";
        
        echo $formattedMessage;
        
        // Дополнительно пишем в файл (без цветов)
        $plainMessage = "[{$timestamp}] [{$type}] {$message}\n";
        file_put_contents('import.log', $plainMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Получение результатов
     */
    public function getResults() {
        return $this->results;
    }
    
    /**
     * Получение статистики
     */
    public function getStats() {
        return $this->stats;
    }
}
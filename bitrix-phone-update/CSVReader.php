<?php

class CSVReader {
    /**
     * Чтение CSV файла
     */
    public function readFile($filename, $delimiter = ',') {
        if (!file_exists($filename)) {
            throw new Exception("Файл {$filename} не найден");
        }
        
        $data = [];
        $headers = [];
        
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $rowIndex = 0;
            
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if ($rowIndex === 0) {
                    // Первая строка - заголовки
                    $headers = array_map([$this, 'normalizeHeader'], $row);
                } else {
                    $rowData = [];
                    foreach ($row as $index => $value) {
                        $header = $headers[$index] ?? "col_{$index}";
                        $rowData[$header] = trim($value);
                    }
                    
                    // Добавляем только непустые строки
                    if (!empty(array_filter($rowData, function($v) { 
                        return $v !== '' && $v !== null; 
                    }))) {
                        $data[] = $rowData;
                    }
                }
                $rowIndex++;
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    /**
     * Нормализация названий колонок
     */
    private function normalizeHeader($header) {
        $header = trim($header);
        $header = preg_replace('/[^a-zA-Z0-9_]/', '_', $header);
        $header = preg_replace('/_{2,}/', '_', $header);
        $header = trim($header, '_');
        $header = strtoupper($header); // Приводим к верхнему регистру
        
        return $header;
    }
    
    /**
     * Валидация структуры данных
     */
    public function validateData($data, $requiredFields = []) {
        $errors = [];
        
        if (empty($data)) {
            $errors[] = 'Файл не содержит данных';
            return $errors;
        }
        
        // Проверяем обязательные колонки
        foreach ($requiredFields as $field) {
            $fieldUpper = strtoupper($field);
            if (!isset($data[0][$fieldUpper])) {
                $errors[] = "Отсутствует обязательная колонка: {$field}";
            }
        }
        
        // Проверяем данные в каждой строке
        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 потому что CSV нумерует с 1 + заголовок
            
            // Проверка ID
            if (isset($row['ID']) && (empty($row['ID']) || !is_numeric($row['ID']))) {
                $errors[] = "Строка {$rowNumber}: Неверный формат ID - '{$row['ID']}'";
            }
            
            // Проверка номера телефона
            if (isset($row['UF_PHONE_INNER'])) {
                $phone = $this->validatePhone($row['UF_PHONE_INNER']);
                if (!$phone) {
                    $errors[] = "Строка {$rowNumber}: Неверный формат номера телефона - '{$row['UF_PHONE_INNER']}'";
                }
            }
            
            // Проверка дубликатов ID
            if (isset($row['ID'])) {
                $currentId = $row['ID'];
                for ($i = 0; $i < $index; $i++) {
                    if (isset($data[$i]['ID']) && $data[$i]['ID'] == $currentId) {
                        $errors[] = "Строка {$rowNumber}: Дублирующийся ID {$currentId}";
                        break;
                    }
                }
            }
            
            // Проверка дубликатов номеров
            if (isset($row['UF_PHONE_INNER']) && $this->validatePhone($row['UF_PHONE_INNER'])) {
                $currentPhone = $this->validatePhone($row['UF_PHONE_INNER']);
                for ($i = 0; $i < $index; $i++) {
                    if (isset($data[$i]['UF_PHONE_INNER']) && 
                        $this->validatePhone($data[$i]['UF_PHONE_INNER']) == $currentPhone) {
                        $errors[] = "Строка {$rowNumber}: Дублирующийся номер {$currentPhone}";
                        break;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Валидация номера телефона
     */
    public function validatePhone($phone) {
        if (empty($phone)) {
            return false;
        }
        
        $phone = (string)$phone;
        $phone = trim($phone);
        
        // Убираем все нецифровые символы
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        
        return !empty($cleanPhone) ? $cleanPhone : false;
    }
    
    /**
     * Создание шаблонного CSV файла
     */
    public function createTemplate($filename) {
        $headers = ['ID', 'UF_PHONE_INNER', 'NAME', 'LAST_NAME', 'EMAIL', 'WORK_POSITION'];
        $sampleData = [
            [1, '101', 'Иван', 'Иванов', 'ivan@company.com', 'Менеджер'],
            [2, '102', 'Петр', 'Петров', 'petr@company.com', 'Директор'],
            [3, '103', 'Анна', 'Сидорова', 'anna@company.com', 'Аналитик']
        ];
        
        $fp = fopen($filename, 'w');
        
        // Заголовки
        fputcsv($fp, $headers);
        
        // Примеры данных
        foreach ($sampleData as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        return true;
    }
}
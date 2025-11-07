<?php

class CSVFixer {
    /**
     * Читает CSV файл и исправляет ошибки
     */
    public function fixCSVFile($inputFile, $outputFile = null) {
        if (!file_exists($inputFile)) {
            throw new Exception("Файл {$inputFile} не найден");
        }
        
        if ($outputFile === null) {
            $outputFile = 'fixed_' . $inputFile;
        }
        
        echo "Чтение файла: {$inputFile}\n";
        
        $data = [];
        $headers = [];
        
        // Читаем исходный файл
        if (($handle = fopen($inputFile, "r")) !== FALSE) {
            $rowIndex = 0;
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($rowIndex === 0) {
                    // Заголовки
                    $headers = $row;
                } else {
                    $rowData = [];
                    foreach ($row as $index => $value) {
                        $header = $headers[$index] ?? "col_{$index}";
                        $rowData[$header] = trim($value);
                    }
                    $data[] = $rowData;
                }
                $rowIndex++;
            }
            fclose($handle);
        }
        
        echo "Прочитано строк: " . count($data) . "\n";
        
        // Исправляем данные
        $fixedData = $this->fixData($data);
        
        // Сохраняем исправленный файл
        $this->saveCSV($fixedData, $headers, $outputFile);
        
        echo "Исправленный файл сохранен как: {$outputFile}\n";
        
        return $fixedData;
    }
    
    /**
     * Исправляет данные
     */
    private function fixData($data) {
        $usedPhones = [];
        $usedIds = [];
        $fixedData = [];
        $emptyPhonesCount = 0;
        $duplicatePhonesCount = 0;
        $duplicateIdsCount = 0;
        
        echo "Исправление данных...\n";
        
        foreach ($data as $index => $row) {
            $rowNumber = $index + 2;
            $originalPhone = $row['UF_PHONE_INNER'] ?? '';
            $userId = $row['ID'] ?? '';
            
            // Проверяем ID
            if (!empty($userId) && isset($usedIds[$userId])) {
                echo "  Строка {$rowNumber}: Дублирующийся ID {$userId} - ПРОПУСК\n";
                $duplicateIdsCount++;
                continue;
            }
            $usedIds[$userId] = true;
            
            // Исправляем номер телефона
            $fixedPhone = $this->fixPhoneNumber($originalPhone);
            
            if (empty($fixedPhone)) {
                // Генерируем временный номер для пустых строк
                $fixedPhone = 'TEMP_' . (1000 + $emptyPhonesCount);
                $emptyPhonesCount++;
                echo "  Строка {$rowNumber}: Пустой номер -> заменен на {$fixedPhone}\n";
            } elseif (isset($usedPhones[$fixedPhone])) {
                // Добавляем суффикс к дублирующимся номерам
                $newPhone = $fixedPhone . '_' . ($usedPhones[$fixedPhone] + 1);
                echo "  Строка {$rowNumber}: Дублирующийся номер {$fixedPhone} -> заменен на {$newPhone}\n";
                $fixedPhone = $newPhone;
                $duplicatePhonesCount++;
            }
            
            $usedPhones[$fixedPhone] = isset($usedPhones[$fixedPhone]) ? $usedPhones[$fixedPhone] + 1 : 1;
            
            // Обновляем строку
            $row['UF_PHONE_INNER'] = $fixedPhone;
            $fixedData[] = $row;
        }
        
        echo "\nСтатистика исправлений:\n";
        echo "- Пустых номеров: {$emptyPhonesCount}\n";
        echo "- Дублирующихся номеров: {$duplicatePhonesCount}\n";
        echo "- Дублирующихся ID: {$duplicateIdsCount}\n";
        echo "- Итоговое количество строк: " . count($fixedData) . "\n";
        
        return $fixedData;
    }
    
    /**
     * Исправляет номер телефона
     */
    private function fixPhoneNumber($phone) {
        if (empty($phone) || $phone === '""' || $phone === "''") {
            return '';
        }
        
        $phone = (string)$phone;
        $phone = trim($phone);
        
        // Убираем кавычки если есть
        $phone = trim($phone, '"\'');
        
        // Убираем все нецифровые символы (кроме временных меток)
        if (strpos($phone, 'TEMP_') !== 0) {
            $cleanPhone = preg_replace('/[^\d]/', '', $phone);
            return !empty($cleanPhone) ? $cleanPhone : '';
        }
        
        return $phone;
    }
    
    /**
     * Сохраняет исправленные данные в CSV
     */
    private function saveCSV($data, $headers, $filename) {
        $fp = fopen($filename, 'w');
        
        // Заголовки
        fputcsv($fp, $headers);
        
        // Данные
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($headers as $header) {
                $csvRow[] = $row[$header] ?? '';
            }
            fputcsv($fp, $csvRow);
        }
        
        fclose($fp);
    }
    
    /**
     * Показывает отчет о проблемах в исходном файле
     */
    public function analyzeFile($filename) {
        if (!file_exists($filename)) {
            throw new Exception("Файл {$filename} не найден");
        }
        
        echo "=== АНАЛИЗ ФАЙЛА: {$filename} ===\n";
        
        $data = [];
        $headers = [];
        $problems = [];
        
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $rowIndex = 0;
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($rowIndex === 0) {
                    $headers = $row;
                } else {
                    $rowData = [];
                    foreach ($row as $index => $value) {
                        $header = $headers[$index] ?? "col_{$index}";
                        $rowData[$header] = trim($value);
                    }
                    
                    // Анализируем проблемы
                    $rowProblems = $this->analyzeRow($rowData, $rowIndex + 1);
                    if (!empty($rowProblems)) {
                        $problems = array_merge($problems, $rowProblems);
                    }
                    
                    $data[] = $rowData;
                }
                $rowIndex++;
            }
            fclose($handle);
        }
        
        echo "Обнаружено проблем: " . count($problems) . "\n";
        foreach ($problems as $problem) {
            echo "  - {$problem}\n";
        }
        
        return $problems;
    }
    
    /**
     * Анализирует строку на проблемы
     */
    private function analyzeRow($row, $rowNumber) {
        $problems = [];
        
        $phone = $row['UF_PHONE_INNER'] ?? '';
        $userId = $row['ID'] ?? '';
        
        // Проверка ID
        if (empty($userId) || !is_numeric($userId)) {
            $problems[] = "Строка {$rowNumber}: Неверный формат ID - '{$userId}'";
        }
        
        // Проверка номера телефона
        if (empty($phone)) {
            $problems[] = "Строка {$rowNumber}: Пустой номер телефона";
        } else {
            $cleanPhone = preg_replace('/[^\d]/', '', $phone);
            if (empty($cleanPhone)) {
                $problems[] = "Строка {$rowNumber}: Неверный формат номера - '{$phone}'";
            }
        }
        
        return $problems;
    }
    
    /**
     * Создает чистый файл для импорта (без временных номеров)
     */
    public function createImportFile($data, $filename) {
        $fp = fopen($filename, 'w');
        
        // Только необходимые колонки для импорта
        $importHeaders = ['ID', 'UF_PHONE_INNER', 'NAME', 'LAST_NAME'];
        
        fputcsv($fp, $importHeaders);
        
        $importCount = 0;
        foreach ($data as $row) {
            // Берем только строки с нормальными номерами (не TEMP_)
            $phone = $row['UF_PHONE_INNER'] ?? '';
            if (!empty($phone) && strpos($phone, 'TEMP_') !== 0) {
                $importRow = [
                    'ID' => $row['ID'] ?? '',
                    'UF_PHONE_INNER' => $phone,
                    'NAME' => $row['NAME'] ?? '',
                    'LAST_NAME' => $row['LAST_NAME'] ?? ''
                ];
                fputcsv($fp, $importRow);
                $importCount++;
            }
        }
        
        fclose($fp);
        
        echo "Создан файл для импорта: {$filename}\n";
        echo "Количество записей для импорта: {$importCount}\n";
        
        return $importCount;
    }
}

// Дополнительная функция для создания чистого файла импорта
function createImportFile($data, $filename) {
    $fp = fopen($filename, 'w');
    
    // Только необходимые колонки для импорта
    $importHeaders = ['ID', 'UF_PHONE_INNER', 'NAME', 'LAST_NAME'];
    
    fputcsv($fp, $importHeaders);
    
    $importCount = 0;
    foreach ($data as $row) {
        // Берем только строки с нормальными номерами (не TEMP_)
        $phone = $row['UF_PHONE_INNER'] ?? '';
        if (!empty($phone) && strpos($phone, 'TEMP_') !== 0) {
            $importRow = [
                'ID' => $row['ID'] ?? '',
                'UF_PHONE_INNER' => $phone,
                'NAME' => $row['NAME'] ?? '',
                'LAST_NAME' => $row['LAST_NAME'] ?? ''
            ];
            fputcsv($fp, $importRow);
            $importCount++;
        }
    }
    
    fclose($fp);
    
    echo "Создан файл для импорта: {$filename}\n";
    echo "Количество записей для импорта: {$importCount}\n";
    
    return $importCount;
}

// Основная программа
function main() {
    $fixer = new CSVFixer();
    
    echo "=== ИСПРАВЛЕНИЕ CSV ФАЙЛА ===\n\n";
    
    // Имя вашего исходного файла
    $inputFile = 'users_with_numbers.csv'; // Замените на имя вашего файла
    
    if (!file_exists($inputFile)) {
        echo "Файл {$inputFile} не найден!\n";
        echo "Убедитесь, что файл находится в той же папке что и скрипт.\n";
        exit(1);
    }
    
    // Шаг 1: Анализируем файл
    echo "1. Анализ исходного файла:\n";
    $problems = $fixer->analyzeFile($inputFile);
    
    if (empty($problems)) {
        echo "✓ Проблем не обнаружено!\n";
        return;
    }
    
    echo "\n2. Исправление файла:\n";
    
    // Шаг 2: Исправляем файл
    $fixedData = $fixer->fixCSVFile($inputFile, 'users_with_numbers_fixed.csv');
    
    echo "\n3. Создание отчета:\n";
    
    // Шаг 3: Создаем файл для импорта
    createImportFile($fixedData, 'users_for_import.csv');
    
    echo "\n=== ВЫПОЛНЕНО! ===\n";
    echo "Исправленный файл: users_with_numbers_fixed.csv\n";
    echo "Файл для импорта: users_for_import.csv\n";
    echo "\nТеперь запустите: php import_phones.php\n";
}

// Запуск
main();
<?php

require_once 'CSVReader.php';

echo "Создание шаблонного CSV файла...\n";

$csvReader = new CSVReader();
$filename = 'template_users.csv';

if ($csvReader->createTemplate($filename)) {
    echo "Шаблонный файл '{$filename}' успешно создан!\n";
    echo "Заполните его данными и запустите импорт командой: php import_phones.php\n";
} else {
    echo "Ошибка при создании шаблонного файла\n";
}
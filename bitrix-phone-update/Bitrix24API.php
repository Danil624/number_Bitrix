<?php

class Bitrix24API {
    private $webhookUrl;
    private $timeout = 30;
    
    public function __construct($webhookUrl) {
        $this->webhookUrl = $webhookUrl;
    }
    
    /**
     * Вызов метода Bitrix24 REST API
     */
    public function callMethod($method, $params = []) {
        $url = $this->webhookUrl . $method;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'error' => 'CURL_ERROR',
                'error_description' => $error
            ];
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'error' => 'JSON_PARSE_ERROR',
                'error_description' => json_last_error_msg()
            ];
        }
        
        return $data;
    }
    
    /**
     * Обновление данных пользователя
     */
    public function updateUser($userId, $fields) {
        $params = [
            'ID' => (int)$userId
        ];
        
        // Добавляем только те поля, которые переданы
        $allowedFields = [
            'UF_PHONE_INNER', 'NAME', 'LAST_NAME', 'EMAIL', 
            'WORK_POSITION', 'PERSONAL_MOBILE'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($fields[$field])) {
                $params[$field] = $fields[$field];
            }
        }
        
        return $this->callMethod('user.update', $params);
    }
    
    /**
     * Получение информации о пользователе
     */
    public function getUser($userId) {
        return $this->callMethod('user.get', ['ID' => (int)$userId]);
    }
    
    /**
     * Получение списка всех пользователей
     */
    public function getAllUsers() {
        $allUsers = [];
        $start = 0;
        
        do {
            $result = $this->callMethod('user.get', ['start' => $start]);
            
            if (isset($result['result']) && is_array($result['result'])) {
                $allUsers = array_merge($allUsers, $result['result']);
                $start += count($result['result']);
            } else {
                break;
            }
            
            // Пауза чтобы не превысить лимиты
            usleep(500000); // 0.5 секунды
            
        } while (isset($result['result']) && count($result['result']) > 0);
        
        return $allUsers;
    }
}
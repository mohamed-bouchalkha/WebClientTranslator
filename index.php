<?php
header('Content-Type: application/json');

$chatFile = 'chats.json'; // Fichier pour stocker les chats

// Fonction pour charger les chats existants
function loadChats($chatFile) {
    if (!file_exists($chatFile)) {
        return []; // Retourne un tableau vide si le fichier n'existe pas
    }
    $data = file_get_contents($chatFile);
    return json_decode($data, true) ?? [];
}

// Fonction pour sauvegarder les chats dans le fichier JSON
function saveChats($chatFile, $chats) {
    file_put_contents($chatFile, json_encode($chats, JSON_PRETTY_PRINT));
}

// Vérifie si la requête est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';
    $chatId = $input['chatId'] ?? 'default';  // Chat ID pour séparer les conversations

    if (empty($userMessage)) {
        echo json_encode(['reply' => 'Please enter a message.']);
        exit;
    }

    // Charger les chats existants
    $chats = loadChats($chatFile);

    // Si le chat n'existe pas, initialiser une nouvelle conversation
    if (!isset($chats[$chatId])) {
        $chats[$chatId] = [];
    }

    // Préparer les données pour envoyer au service de traduction
    $apiUrl = "http://localhost:8080/TranslateService/api/translate"; // URL de l'API
    $postData = json_encode(["inputText" => $userMessage]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['reply' => 'Error connecting to the translation service: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        $responseData = json_decode($response, true);
        if (isset($responseData['translatedText'])) {
            $translatedText = $responseData['translatedText'];

            // Sauvegarder le message et la traduction dans le chat spécifique
            $chats[$chatId][] = [
                'message' => $userMessage,
                'translatedText' => $translatedText,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            saveChats($chatFile, $chats);

            echo json_encode(['reply' => $translatedText]);
        } else {
            echo json_encode(['reply' => 'Unexpected response format from the translation service.']);
        }
    } else {
        echo json_encode(['reply' => "The translation service returned an error. HTTP Status Code: $httpCode"]);
    }
} else {
    // Si la requête n'est pas POST, renvoie un chat spécifique si un chatId est fourni
    $input = json_decode(file_get_contents('php://input'), true);
    $chatId = $input['chatId'] ?? 'default';

    $chats = loadChats($chatFile);
    
    if (isset($chats[$chatId])) {
        echo json_encode($chats[$chatId]); // Renvoie les messages du chat spécifique
    } else {
        echo json_encode([]); // Si le chat n'existe pas, retourne un tableau vide
    }
}
?>

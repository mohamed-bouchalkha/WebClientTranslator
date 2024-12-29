<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';

    if (empty($userMessage)) {
        echo json_encode(['reply' => 'Please enter a message.']);
        exit;
    }

    // Prepare the data to send to the translation web service
    $apiUrl = "http://localhost:8080/TranslateService/api/translate"; // Update this URL as needed
    $postData = json_encode([
        "inputText" => $userMessage
    ]);

    // Send request to the translation web service
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
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
            echo json_encode(['reply' => $translatedText]);
        } else {
            echo json_encode(['reply' => 'Unexpected response format from the translation service.']);
        }
    } else {
        echo json_encode(['reply' => "The translation service returned an error. HTTP Status Code: $httpCode"]);
    }
}
?>

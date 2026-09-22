<?php
function sendApprovalSms($phoneNumber, $recipientName) {
    $apiKey = '8ed8bfd96e13274ef0f5596221941b57'; 

    $message = "Hello $recipientName! Your Barangay Tubod resident account has been APPROVED. You can now log in to access barangay services.";

    $ch = curl_init();
    $parameters = [
        'apikey'  => $apiKey,
        'number'  => $phoneNumber,
        'message' => $message
    ];

    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $output = curl_exec($ch);

    return $output;
}
?>
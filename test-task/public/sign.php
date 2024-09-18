<?php

function generateSign($account, $password, $apiSecret) {
    // Ensure data is sorted by keys in ascending order
    $data = [
        'account' => $account,
        'password' => $password
    ];
    ksort($data); // Sort data by keys in ascending order

    // Concatenate the values of the sorted array
    $dataString = implode('', $data); 

    // Append the API secret key and generate the SHA256 hash
    $sign = hash('sha256', $dataString . $apiSecret);

    return $sign;
}

// Example usage
$account = 'Sallu';
$password = 'HAha@1234';
$apiSecret = 'thisismysecretkey'; // Replace this with your actual API secret key

$generatedSign = generateSign($account, $password, $apiSecret);

echo "Generated Sign: " . $generatedSign;

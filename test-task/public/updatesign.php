<?php

function generateSign($agent, $accountData, $apiSecretKey)
{
    // Combine agent with account data
    $data = array_merge(['agent' => $agent], $accountData);

    // Sort the data by keys
    ksort($data);

    // Concatenate the values of the sorted data
    $dataString = implode('', $data);

    // Generate the sign string by hashing the concatenated data and the secret key
    $signString = hash('sha256', $dataString . $apiSecretKey);

    return $signString;
}

// Example usage
$agent = 'Goku';  // Replace with the actual agent identifier
$accountData = [
    'currency' => 'EUR',
    'credit' => 1000.00,
    'status' => 1  // 0 = normal, 1 = locked
];

$apiSecretKey = 'n24ZcQ0mQ0aqXmRFR6GCxJstWFCmnpmkENGiG6Pb';  // This should be the actual secret key for the agent

// Generate the sign
$generatedSign = generateSign($agent, $accountData, $apiSecretKey);

// Output the generated sign
echo "Generated Sign: " . $generatedSign;

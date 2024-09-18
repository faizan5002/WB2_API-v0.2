<?php
namespace App\Http\Controllers;

use App\Models\AgentAccount;  // Make sure to use the correct model class
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; 
class AgentController extends Controller
{
    public function createAgent(Request $request)
{
    // Log the incoming request
    Log::info('Create Agent Request', ['request' => $request->all()]);

    // Validate the request data
    $validator = Validator::make($request->all(), [
        'agent' => 'required|string|regex:/^[a-zA-Z0-9_]{4,20}$/',
        'password' => 'required|string|min:4|max:20',
        'agent_currency' => 'required|string|in:USD,VND,EUR', // Add supported currencies here
    ]);

    if ($validator->fails()) {
        Log::error('Validation Failed', ['errors' => $validator->errors()]);
        return response()->json(['code' => -1, 'message' => $validator->errors()], 400);
    }

    $agent = $request->input('agent');
    $password = $request->input('password');
    $agentCurrency = $request->input('agent_currency');

    // Log the validated data
    Log::info('Validated Data', ['agent' => $agent, 'agent_currency' => $agentCurrency]);

    // Check if agent already exists
    $existingAgent = AgentAccount::on('mysql_master')->where('agent', $agent)->first();
    if ($existingAgent) {
        Log::warning('Agent Already Exists', ['agent' => $agent]);
        return response()->json(['code' => -3, 'message' => 'Agent account already exists'], 400);
    }

    // Create agent account
    $apiKeyId = Str::uuid();
    $apiSecretKey = Str::random(40);  // Random string for API secret key
    $agentAccount = AgentAccount::on('mysql_master')->create([
        'agent' => $agent,
        'password' => Hash::make($password),
        'currency' => $agentCurrency,
        'credit' => 0,
        'status' => 0,
        'api_key_id' => $apiKeyId,
        'api_secret_key' => $apiSecretKey,
    ]);

    // Log the newly created agent account
    Log::info('Agent Account Created', ['agent' => $agentAccount]);

    // Sort and concatenate values for sign string
    $data = [
        'agent' => $agent,
        'password' => $password,
        'agent_currency' => $agentCurrency
    ];
    ksort($data);  // Sort by keys in ascending order
    $dataString = implode('', $data);  // Concatenate values

    // Log the sorted sign string before hashing
    Log::info('Sorted sign string', ['sign_string' => $dataString]);

    $signString = hash('sha256', $dataString);  // Hash the concatenated data

    // Log the generated sign string
    Log::info('Generated Sign String', ['sign' => $signString]);

    // Prepare response data
    $responseData = [
        'account' => $agentAccount->agent,
        'currency' => $agentAccount->currency,
        'credit' => $agentAccount->credit,
        'status' => $agentAccount->status,
        'api_key_id' => $agentAccount->api_key_id,
        'api_secret_key' => $agentAccount->api_secret_key,
        'sign' => $signString
    ];

    // Return success response
    Log::info('Create Agent Response', ['response' => $responseData]);

    return response()->json([
        'code' => 0,
        'data' => $responseData
    ], 200);
}

public function modifyPassword(Request $request)
{
    // Validate the request data (excluding the sign header)
    $validator = Validator::make($request->all(), [
        'account' => 'required|string|regex:/^[a-zA-Z0-9_]{4,20}$/',
        'password' => 'required|string|min:4|max:20'
    ]);

    if ($validator->fails()) {
        Log::info('Validation failed', ['errors' => $validator->errors()]);
        return response()->json(['code' => -1, 'message' => $validator->errors()], 400);
    }

    // Retrieve input data and sign header
    $account = $request->input('account');
    $password = $request->input('password');
    $sign = $request->input('sign');  // Retrieve sign from header

    // Log the received data
    Log::info('Received request data', [
        'account' => $account,
        'password' => $password,
        'received_sign' => $sign
    ]);

    // Check if the agent account exists using mysql_slave
    $agent = AgentAccount::on('mysql_slave')->where('agent', $account)->first();
    if (!$agent) {
        Log::info('Agent account not found', ['account' => $account]);
        return response()->json(['code' => -3, 'message' => 'Agent account does not exist'], 400);
    }

    // Retrieve the agent's API secret key from the database
    $apiSecretKey = $agent->api_secret_key;
    Log::info("Retrieved API SECRET KEY for account {$account}", ['api_secret_key' => $apiSecretKey]);

    // Generate sign string
    $data = [
        'account' => $account,
        'password' => $password
    ];
    ksort($data); // Sort by keys in ascending order
    $dataString = implode('', $data); // Concatenate values
    $generatedSign = hash('sha256', $dataString . $apiSecretKey); // Append secret and hash

    // Log the generated sign string
    Log::info('Generated sign string', [
        'data_string' => $dataString,
        'generated_sign' => $generatedSign
    ]);

    // Verify the sign string
    if ($sign !== $generatedSign) {
        Log::warning('Invalid sign string data', [
            'expected' => $generatedSign,
            'received' => $sign
        ]);
        return response()->json(['code' => -100, 'message' => 'Invalid sign string data'], 400);
    }

    // Update the agent account password
    $agent->password = Hash::make($password);
    $agent->save();

    // Log success
    Log::info('Password updated successfully', ['account' => $agent->agent]);

    // Return success response
    return response()->json([
        'code' => 1,
        'data' => [
            'account' => $agent->agent,
            'status' => $agent->status,
        ]
    ], 200);
}


    

 
}

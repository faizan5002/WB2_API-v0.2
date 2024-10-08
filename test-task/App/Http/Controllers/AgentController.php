<?php
namespace App\Http\Controllers;

use App\Models\AgentAccount;  // Make sure to use the correct model class
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; 
class AgentController extends Controller
{
    public function createAgent(Request $request)
    {
        // Start time for request processing
        $startTime = microtime(true);
    
        // Log the incoming request (excluding sensitive data like password)
        Log::info('Create Agent Request', ['agent' => $request->input('agent'), 'agent_currency' => $request->input('agent_currency')]);
    
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'agent' => 'required|string|regex:/^[a-zA-Z0-9_]{4,20}$/',
            'password' => 'required|string|min:4|max:20',
            'agent_currency' => 'required|string|in:USD,VND,EUR', // Add supported currencies here
        ]);
    
        if ($validator->fails()) {
            // Log validation errors and request details
            Log::error('Validation Failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except('password') // Exclude password from logs
            ]);
            return response()->json(['code' => -1, 'message' => $validator->errors()], 400);
        }
    
        $agent = $request->input('agent');
        $password = $request->input('password');
        $agentCurrency = $request->input('agent_currency');
    
        // Log the validated data (excluding password)
        Log::info('Validated Data', ['agent' => $agent, 'agent_currency' => $agentCurrency]);
    
        try {
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
    
            // Log the successful account creation (do not log api_secret_key)
            Log::info('Agent Account Created', [
                'agent' => $agentAccount->agent,
                'currency' => $agentAccount->currency,
                'api_key_id' => $agentAccount->api_key_id
            ]);
    
            // Sort and concatenate values for sign string
            $data = [
                'agent' => $agent,
                'password' => $password,  // Be careful if password should be included here
                'agent_currency' => $agentCurrency
            ];
            ksort($data);  // Sort by keys in ascending order
            $dataString = implode('', $data);  // Concatenate values
    
            // Log the sorted sign string before hashing (excluding sensitive info)
            Log::info('Sorted sign string (excluding password)');
    
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
                'api_secret_key' => $agentAccount->api_secret_key,  // Sensitive, consider if this should be returned
                'sign' => $signString
            ];
    
            // Log successful response (excluding sensitive information)
            Log::info('Create Agent Response', ['response' => Arr::except($responseData, ['api_secret_key'])]);
    
            return response()->json([
                'code' => 0,
                'data' => $responseData
            ], 200);
    
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Error Creating Agent Account', [
                'message' => $e->getMessage(),
                'agent' => $agent
            ]);
    
            return response()->json(['code' => -500, 'message' => 'Internal Server Error'], 500);
        } finally {
            // Log the total time taken for request processing
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            Log::info('Create Agent Request Completed', ['execution_time' => $executionTime . ' seconds']);
        }
    }
    
    public function modifyPassword(Request $request)
    {
        // Log the start of the request processing
        Log::info('Modify Password Request Started', ['request' => $request->all()]);
    
        // Validate the request data (excluding the sign header)
        $validator = Validator::make($request->all(), [
            'account' => 'required|string|regex:/^[a-zA-Z0-9_]{4,20}$/',
            'password' => 'required|string|min:4|max:20'
        ]);
    
        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['code' => -1, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }
    
        // Retrieve input data and sign header
        $account = $request->input('account');
        $password = $request->input('password');
        $sign = $request->input('sign'); // Retrieve sign from header
    
        // Log the received data
        Log::info('Received request data', [
            'account' => $account,
            'password' => '********', // Mask password for security reasons
            'received_sign' => $sign
        ]);
    
        try {
            // Check if the agent account exists using mysql_slave
            $agent = AgentAccount::on('mysql_slave')->where('agent', $account)->first();
            if (!$agent) {
                Log::warning('Agent account not found', ['account' => $account]);
                return response()->json(['code' => -3, 'message' => 'Agent account does not exist'], 404);
            }
    
            // Retrieve the agent's API secret key from the database
            $apiSecretKey = $agent->api_secret_key;
            Log::info('Retrieved API SECRET KEY for account', ['account' => $account]);
    
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
                Log::warning('Invalid sign string', [
                    'expected_sign' => $generatedSign,
                    'received_sign' => $sign
                ]);
                return response()->json(['code' => -100, 'message' => 'Invalid sign string'], 400);
            }
    
            // Update the agent account password
            $agent->password = Hash::make($password);
            $agent->save();
    
            // Log success
            Log::info('Password updated successfully', ['account' => $agent->agent]);
    
            // Log the completion of the request
            Log::info('Modify Password Request Completed', ['account' => $account]);
    
            // Return success response
            return response()->json([
                'code' => 1,
                'message' => 'Password updated successfully',
                'data' => [
                    'account' => $agent->agent,
                    'status' => $agent->status,
                ]
            ], 200);
    
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Error during password update', [
                'message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
    
            // Return an internal server error response
            return response()->json(['code' => -500, 'message' => 'An error occurred while processing your request'], 500);
        }
    }
    
    public function updateAgentAccount(Request $request)
    {
        // Log the start of the request
        Log::info('Update Agent Account Request Started', ['request' => $request->all()]);
        
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'sign' => 'required|string',
            'agent' => 'required|string', // Ensure the agent is present in the request
            'account' => 'required|array',
            'account.currency' => 'required|string|in:USD,VND,EUR', // Add more currencies as needed
            'account.credit' => 'required|numeric|min:0',
            'account.status' => 'required|integer|in:0,1' // 0 = normal, 1 = locked
        ]);
    
        if ($validator->fails()) {
            Log::error('Validation Failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except('sign') // Exclude sign from logs
            ]);
            return response()->json(['code' => -1, 'message' => 'Agent data format invalid', 'errors' => $validator->errors()], 400);
        }
    
        // Retrieve the request data
        $sign = $request->input('sign');
        $agentName = $request->input('agent'); // Retrieve the agent name from the request
        $accountData = $request->input('account');
        $currency = $accountData['currency'];
        $credit = $accountData['credit'];
        $status = $accountData['status'];
    
        // Log the validated data
        Log::info('Validated Data', ['agent' => $agentName, 'currency' => $currency, 'credit' => $credit, 'status' => $status]);
    
        try {
            // Check if agent exists using mysql_slave
            $agent = AgentAccount::on('mysql_slave')->where('agent', $agentName)->first();
            if (!$agent) {
                Log::warning('Agent account not found', ['account' => $agentName]);
                return response()->json(['code' => -2, 'message' => 'Agent account does not exist'], 404);
            }
    
            // Retrieve the agent's API secret key
            $apiSecretKey = $agent->api_secret_key;
            Log::info('Retrieved API Secret Key', ['agent' => $agent->agent]);
    
            // Include the agent in the sign generation (similar to your custom sign generation code)
            $data = array_merge(['agent' => $agentName], [
                'currency' => $currency,
                'credit' => $credit,
                'status' => $status
            ]);
    
            // Sort by keys in ascending order
            ksort($data);
    
            // Concatenate the values into a single string
            $dataString = implode('', $data);
    
            // Generate the sign string by hashing the concatenated data and the API secret key
            $generatedSign = hash('sha256', $dataString . $apiSecretKey);
    
            // Log the generated sign string
            Log::info('Generated Sign String', ['data_string' => $dataString, 'generated_sign' => $generatedSign]);
    
            // Verify the sign
            if ($sign !== $generatedSign) {
                Log::warning('Invalid sign string', [
                    'expected_sign' => $generatedSign,
                    'received_sign' => $sign
                ]);
                return response()->json(['code' => -100, 'message' => 'Invalid sign string'], 400);
            }
    
            // Update the agent account details
            $agent->currency = $currency;
            $agent->credit = $credit;
            $agent->status = $status;
            $agent->save();
    
            // Log the successful update
            Log::info('Agent Account Updated Successfully', [
                'agent' => $agent->agent,
                'currency' => $agent->currency,
                'credit' => $agent->credit,
                'status' => $agent->status
            ]);
    
            // Prepare the response data
            $responseData = [
                'account' => $agent->agent,
                'currency' => $agent->currency,
                'credit' => $agent->credit,
                'status' => $agent->status
            ];
    
            return response()->json([
                'code' => 1,
                'data' => $responseData
            ], 200);
    
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Error Updating Agent Account', [
                'message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
    
            // Return an internal server error response
            return response()->json(['code' => -500, 'message' => 'Internal Server Error'], 500);
        }
    }
    public function getAgentList(Request $request)
{
    // Retrieve pageNum and pageSize from the request, with defaults
    $pageNum = $request->input('pageNum', 1);
    $pageSize = $request->input('pageSize', 20);

    // Log the request
    Log::info('Fetching Agent List', ['pageNum' => $pageNum, 'pageSize' => $pageSize]);

    try {
        // Query the agent data using MySQL-slave with pagination
        $agents = AgentAccount::on('mysql_slave')
            ->select('id', 'agent as account', 'currency', 'credit', 'status')
            ->paginate($pageSize, ['*'], 'page', $pageNum);

        // Log the results count
        Log::info('Agent List Fetched Successfully', ['total' => $agents->total()]);

        // Return the paginated data with success response
        return response()->json([
            'code' => 1,
            'data' => [
                'pageNum' => $agents->currentPage(),
                'pageSize' => $agents->perPage(),
                'prePage' => $agents->previousPageUrl(),
                'nextPage' => $agents->nextPageUrl(),
                'isFirstPage' => $agents->onFirstPage(),
                'isLastPage' => !$agents->hasMorePages(),
                'data' => $agents->items()
            ]
        ], 200);

    } catch (\Exception $e) {
        // Log any exceptions
        Log::error('Error Fetching Agent List', ['message' => $e->getMessage()]);

        return response()->json(['code' => -500, 'message' => 'Internal Server Error'], 500);
    }
}

    
}

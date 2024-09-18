<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentController;


Route::prefix('v1')->group(function(){
    Route::post('agent/create', [AgentController::class, 'createAgent']);
    Route::post('agent/password', [AgentController::class, 'modifyPassword']);
    
});


<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentAccountUserApiGameTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_account_user_api_game', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID
            $table->string('agent', 20)->unique(); // Agent account name
            $table->string('password'); // Encrypted password
            $table->string('currency', 3); // Currency (e.g., USD, VND)
            $table->decimal('credit', 10, 2)->default(0); // Default credit
            $table->tinyInteger('status')->default(0); // 0 for normal, 1 for locked
            $table->uuid('api_key_id'); // UUID for API key
            $table->string('api_secret_key', 40); // API secret key
            $table->timestamps(); // Created at and updated at timestamps

            // Optionally, you can add additional indexes if needed
            // $table->index('currency');
            // $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_account_user_api_game');
    }
}

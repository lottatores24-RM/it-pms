<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->enum('probability', ['Low', 'Medium', 'High']);
            $table->enum('impact', ['Low', 'Medium', 'High']);
            $table->unsignedTinyInteger('risk_score');
            $table->enum('risk_level', ['Low', 'Medium', 'High', 'Critical']);
            $table->enum('response_plan', ['Mitigate', 'Accept', 'Avoid', 'Transfer']);
            $table->enum('status', ['Open', 'Mitigated', 'Closed'])->default('Open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};

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
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->text('reason');
            $table->text('impact_scope')->nullable();
            $table->text('impact_schedule')->nullable();
            $table->text('impact_resource')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High']);
            $table->enum('status', ['Submitted', 'Under Review', 'Approved', 'Rejected'])->default('Submitted');
            $table->text('justification')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};

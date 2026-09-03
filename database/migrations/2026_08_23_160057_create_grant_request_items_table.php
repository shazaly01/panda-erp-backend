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
        Schema::create('grant_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_request_id')->constrained('grant_requests')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('item_name');
            $table->text('specifications')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_request_items');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_slip_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained('salary_slips')->cascadeOnDelete();
            $table->foreignId('salary_rule_id')->nullable()->constrained('salary_rules')->nullOnDelete();
            $table->string('salary_rule_code'); // BASIC, HOUSING...
            $table->string('name');
            $table->string('category'); // allowance, deduction...
            $table->decimal('amount', 18, 4);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slip_details');
    }
};

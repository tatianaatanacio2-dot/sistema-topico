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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('patient')->index();
            $table->boolean('active')->default(true);
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('document')->unique();
            $table->string('type')->default('student');
            $table->date('birth_date')->nullable();
            $table->string('group')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->text('allergies')->nullable();
            $table->text('background')->nullable();
            $table->timestamps();
        });
        Schema::create('care_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_id')->nullable()->unique()->constrained('care_requests')->restrictOnDelete();
            $table->foreignId('attended_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->text('symptoms')->nullable();
            $table->text('observations')->nullable();
            $table->text('care')->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('saturation')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->string('status')->default('in_progress')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('presentation');
            $table->string('unit');
            $table->unsignedInteger('minimum_stock')->default(10);
            $table->unique(['name', 'presentation']);
            $table->timestamps();
        });
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('lot');
            $table->date('expires_at');
            $table->unsignedInteger('quantity');
            $table->unique(['medicine_id', 'lot']);
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('quantity');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('destination');
            $table->text('reason');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->text('follow_up')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['audit_logs', 'referrals', 'stock_movements', 'medicine_batches', 'medicines', 'consultations', 'care_requests', 'patients'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'active']);
        });
    }
};

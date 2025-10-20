<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->string('common_name');
            $table->json('subject_alt_names')->nullable();
            $table->string('organization')->nullable();
            $table->string('organizational_unit')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('email')->nullable();
            $table->text('csr')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'issued'])->default('pending');
            $table->enum('request_type', ['new', 'renewal', 'reissue'])->default('new');
            $table->integer('validity_days')->default(365);
            $table->json('key_usage')->nullable();
            $table->json('extended_key_usage')->nullable();
            $table->string('signature_algorithm')->default('SHA256withRSA');
            $table->integer('key_size')->default(2048);
            $table->enum('key_type', ['RSA', 'ECDSA', 'ED25519'])->default('RSA');
            $table->text('justification')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['approved_by', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificate_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_id')->unique();
            $table->string('common_name');
            $table->json('subject_alt_names')->nullable();
            $table->text('csr')->nullable();
            $table->text('certificate')->nullable();
            $table->text('private_key')->nullable();
            $table->enum('status', ['pending', 'issued', 'revoked', 'expired', 'renewed'])->default('pending');
            $table->enum('type', ['self_signed', 'ca_signed', 'intermediate'])->default('ca_signed');
            $table->string('serial_number')->unique();
            $table->string('fingerprint')->nullable();
            $table->string('issuer')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->integer('validity_days')->default(365);
            $table->json('key_usage')->nullable();
            $table->json('extended_key_usage')->nullable();
            $table->string('signature_algorithm')->default('SHA256withRSA');
            $table->integer('key_size')->default(2048);
            $table->enum('key_type', ['RSA', 'ECDSA', 'ED25519'])->default('RSA');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->index(['common_name', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificates');
    }
};

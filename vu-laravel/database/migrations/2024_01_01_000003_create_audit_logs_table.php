<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // certificate_issued, certificate_revoked, user_login, etc.
            $table->string('event_category'); // authentication, certificate_management, system
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('certificate_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('resource_type')->nullable(); // certificate, user, system
            $table->string('resource_id')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['certificate_id', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};

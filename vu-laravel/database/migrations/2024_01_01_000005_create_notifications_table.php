<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // certificate_expiry, certificate_revoked, system_alert
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'sent', 'failed', 'read'])->default('pending');
            $table->enum('channel', ['email', 'sms', 'web', 'webhook'])->default('email');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('certificate_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['status', 'scheduled_at']);
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};

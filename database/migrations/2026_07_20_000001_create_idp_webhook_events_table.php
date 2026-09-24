<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->comment('identity provider alias, matches tenants.sso_provider');
            $table->string('entity_type', 32)->comment('user, group or school');
            $table->string('action', 32)->comment('create, update, delete or restore');
            $table->string('entity_id', 64)->comment('provider id of the user, group or school');
            $table->json('updated_properties')->nullable()->comment('properties the provider reported as changed');
            $table->json('payload')->comment('Raw webhook body as received');
            $table->string('tenant_id')->nullable()->comment('Resolved tenant; null until the event is processed');
            $table->string('status', 16)->default('pending')->comment('pending, processed, skipped or failed');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable()->comment('Last failure reason');
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'entity_type', 'entity_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_webhook_events');
    }
};

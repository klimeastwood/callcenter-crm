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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->increments('id');

            /**
             * A call belongs to the lead it was made for: when the lead is
             * removed, the conversation is meaningless, so the rows go with it.
             */
            $table->integer('lead_id')->unsigned();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            /**
             * The manager who handled the call. The record outlives the account
             * (it is QA and billing data), so a removed user only detaches it.
             */
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('phone_number', 50);

            /**
             * Talk time in seconds. Stored as a number so a future report can
             * sum it up; the grid renders it as `m:ss`.
             */
            $table->unsignedInteger('duration')->default(0);

            $table->string('status', 20)->default('completed');

            $table->string('recording_url', 2048)->nullable();
            $table->text('transcript')->nullable();

            $table->dateTime('call_date');

            $table->timestamps();

            $table->index('status');
            $table->index(['lead_id', 'call_date']);
            $table->index(['user_id', 'call_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};

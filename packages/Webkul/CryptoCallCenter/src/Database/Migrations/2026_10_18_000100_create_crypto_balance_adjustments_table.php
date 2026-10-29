<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The CRM owns this table: it is the audit trail of every manual balance
     * change, while `trading_accounts.balance_usd` stays owned by the trading
     * application. `amount` is signed, so a bonus and a write-off live in the
     * same history.
     */
    public function up(): void
    {
        Schema::create('crypto_balance_adjustments', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('lead_id')->unsigned();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            /**
             * The trading account the amount was applied to. Kept on the row so
             * the history survives even if the trader account is deleted later.
             */
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            /**
             * The operator that made the change. Null once the account is
             * removed: the history is not a property of the employee.
             */
            $table->integer('admin_id')->unsigned()->nullable();
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();

            $table->decimal('amount', 24, 2);
            $table->decimal('balance_before', 24, 8)->nullable();
            $table->decimal('balance_after', 24, 8)->nullable();

            // The row of the shared event log the change was mirrored into.
            $table->unsignedBigInteger('trading_event_id')->nullable();

            $table->string('reason', 255);

            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_balance_adjustments');
    }
};

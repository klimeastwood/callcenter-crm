<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `trading_events` belongs to the trading application and is created by its
     * own migrations; this table is the CRM side of the bridge and records which
     * event was already written into a timeline. The event id is the primary key
     * on purpose: a unique reservation is what makes a second run of the feed a
     * no-op instead of a duplicate entry.
     */
    public function up(): void
    {
        Schema::create('trading_event_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('trading_event_id')->primary();

            /**
             * Nullable: an event of an unknown type is parked without an
             * activity, and an event whose account has no lead is not reserved
             * at all.
             */
            $table->unsignedInteger('activity_id')->nullable();

            $table->unsignedInteger('lead_id')->nullable();

            $table->dateTime('created_at')->useCurrent();

            $table->index('activity_id');
            $table->index('lead_id');

            /**
             * Deleting an activity detaches it from the ledger instead of
             * dropping the reservation, so the event is never replayed.
             */
            $table->foreign('activity_id')
                ->references('id')
                ->on('activities')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_event_activities');
    }
};

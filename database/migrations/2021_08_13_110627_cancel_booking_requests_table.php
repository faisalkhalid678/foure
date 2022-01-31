<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CancelBookingRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cancel_booking_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('pnr');
            $table->string('ticket_reservation_code');
            $table->string('provider_type');
            $table->enum('status',['Pending','Cancelled'])->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cancel_booking_requests');
    }
}

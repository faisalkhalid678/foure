<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFtBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ft_bookings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email');
            $table->string('phone_number');
            $table->double('total_amount',20,2);
            $table->string('booking_code')->nullable();
            $table->string('pnr')->nullable();
            $table->string('payment_method');
            $table->enum('booking_status',['Incompleted', 'Completed','Cancelled'])->default('Incompleted');
            $table->enum('payment_status',['Pending', 'Completed'])->default('Pending');
            $table->string('api_type');
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
        Schema::dropIfExists('ft_bookings');
    }
}

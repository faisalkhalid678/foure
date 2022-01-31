<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFtBookingDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ft_booking_details', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('booking_id');
            $table->string('title');
            $table->string('f_name');
            $table->string('l_name');
            $table->string('nationality');
            $table->date('dob');
            $table->enum('passenger_type',['ADT', 'INF','CNN']);
            $table->string('passport_number')->nullable();
            $table->string('passport_type')->nullable();
            $table->string('passport_expiry_date')->nullable();
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
        Schema::dropIfExists('ft_booking_details');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHikesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hikes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('location_id')->nullable();
            $table->string('name');
            $table->string('wta_hike_id');
            $table->string('length')->nullable();
            $table->smallInteger('elevation_gain')->nullable();
            $table->smallInteger('highest_point')->nullable();
            $table->string('rating')->nullable();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('hikes');
    }
}

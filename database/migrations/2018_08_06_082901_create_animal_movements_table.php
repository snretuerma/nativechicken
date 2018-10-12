<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAnimalMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('animal_movements', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date');
            $table->unsignedInteger('family_id');
            $table->unsignedInteger('pen_id')->nullable();
            $table->enum('type', ['breeder', 'replacement', 'broodersgrowers', 'egg']);
            $table->string('activity');
            $table->double('price')->nullable();
            $table->integer('number_male')->nullable();
            $table->integer('number_female')->nullable();
            $table->integer('number_total');
            $table->text('remarks')->nullable();
        });

        Schema::table('animal_movements', function($table) {
            $table->foreign('family_id')->references('id')->on('families');
            $table->foreign('pen_id')->references('id')->on('pens');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('animal_movements');
    }
}
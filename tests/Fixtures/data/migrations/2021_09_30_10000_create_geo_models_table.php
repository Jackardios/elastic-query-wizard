<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::create('geo_models', static function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            if (version_compare(Application::VERSION, '11.0.0', '>=')) {
                $table->geometry('location', 'point');
            } else {
                $table->point('location');
            }
            $table->json('boundary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_models');
    }
};

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
        Schema::create('moodle_courses_local', function (Blueprint $table) {
            $table->id(); // Local ID for this table
            $table->unsignedBigInteger('moodle_id')->unique(); // Moodle's course ID
            $table->string('shortname')->nullable();
            $table->string('fullname');
            $table->text('summary')->nullable();
            $table->string('format')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamp('startdate')->nullable();
            $table->timestamp('enddate')->nullable();
            // Add other relevant fields you might want to sync from Moodle
            // e.g., categoryid, timecreated, timemodified
            $table->json('raw_data')->nullable(); // To store the full JSON response for the course if needed
            $table->timestamps(); // created_at and updated_at for local record
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moodle_courses_local');
    }
};

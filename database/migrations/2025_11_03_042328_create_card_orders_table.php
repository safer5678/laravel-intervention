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
        Schema::create('card_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Card Configuration
            $table->string('card_name')->nullable();
            $table->integer('width_mm')->default(150);
            $table->integer('height_mm')->default(210);
            $table->string('background_color')->default('#FFFFFF');
            $table->string('background_image')->nullable(); // Path to uploaded image
            
            // Raw Card Data (JSON)
            $table->json('card_data'); // Store all elements (text, images, shapes)
            
            // Generated Files
            $table->string('preview_url')->nullable(); // Low-res preview
            $table->string('print_file_url')->nullable(); // High-res PDF
            
            // Status
            $table->enum('status', ['draft', 'generated', 'failed'])->default('draft');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            $table->index('order_number');
            $table->index('status');
        });
            
     
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_orders');
    }
};

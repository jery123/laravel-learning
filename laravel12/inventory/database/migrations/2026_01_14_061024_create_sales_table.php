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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('discount',10, 2)->default(0.00);
            $table->decimal('shipping',10, 2)->default(0.00);
            $table->enum('status', ['pending', 'received', 'sale'])->default('pending');
            $table->text('note')->nullable();
            $table->decimal('grand_total',15, 2)->default(0.00);
            $table->decimal('paid_amount',15, 2)->default(0.00)->default(0.00);
            $table->decimal('due_amount',15, 2)->default(0.00)->default(0.00);
            $table->decimal('full_paid',15, 2)->nullable();
            $table->timestamps();


            $table->foreign('warehouse_id')->references('id')->on('ware_houses')->onDelete('cascade');
            /**Cascade means when the warehouse is deleted, related purchases will be deleted as well */
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            /**Cascade means when the customer is deleted, related purchases will be deleted as well */
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

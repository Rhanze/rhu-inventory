<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Baseline formerly supplied only by a manually imported database. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('health_units')) {
            Schema::create('health_units', function (Blueprint $t) {
                $t->integer('id', true); // Existing RHU foreign keys use signed INT.
                $t->string('name');
                $t->string('municipality')->nullable();
                $t->text('address')->nullable();
                $t->integer('parent_id')->nullable();
                $t->string('contact_number')->nullable();
            });
        }
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'username')) {
                $t->string('username', 50)->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'role')) {
                $t->string('role', 20)->default('staff');
            }
            if (! Schema::hasColumn('users', 'health_unit_id')) {
                $t->integer('health_unit_id')->nullable();
            }
        });
        // Account management does not require email; retain optional Laravel factory support.
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'email')) {
                $t->string('email')->nullable()->change();
            } else {
                $t->string('email')->nullable();
            }
        });
        if (! Schema::hasTable('medicines')) {
            Schema::create('medicines', function (Blueprint $t) {
                $t->id();
                $t->string('medicine_name', 150);
                $t->string('dosage', 100)->nullable();
                $t->string('category', 64)->default('medicine');
                $t->text('description')->nullable();
                $t->boolean('requires_prescription')->default(false);
                $t->string('unit', 50)->nullable();
            });
        }
        if (! Schema::hasTable('inventory')) {
            Schema::create('inventory', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('medicine_id')->index();
                $t->integer('health_unit_id')->index();
                $t->integer('available_stock')->default(0);
                $t->integer('allocated_stock')->default(0);
                $t->integer('quarantine_stock')->default(0);
                $t->date('expiration_date')->nullable();
            });
        }
        if (! Schema::hasTable('barangays')) {
            Schema::create('barangays', function (Blueprint $t) {
                $t->id();
                $t->integer('health_unit_id')->nullable();
                $t->string('barangay_name');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $t) {
                $t->id();
                $t->integer('health_unit_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('module');
                $t->string('action');
                $t->text('description');
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('allocations')) {
            Schema::create('allocations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('medicine_id');
                $t->integer('health_unit_id');
                $t->unsignedBigInteger('barangay_id')->nullable();
                $t->integer('quantity_allocated');
                $t->dateTime('date_allocated');
                $t->unsignedBigInteger('allocated_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('dispensing_records')) {
            Schema::create('dispensing_records', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('medicine_id');
                $t->integer('health_unit_id');
                $t->string('patient_name')->nullable();
                $t->string('doctor_name')->nullable();
                $t->integer('quantity_dispensed');
                $t->date('date_dispensed');
                $t->unsignedBigInteger('dispensed_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('stock_logs')) {
            Schema::create('stock_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('medicine_id');
                $t->integer('health_unit_id');
                $t->integer('quantity_added');
                $t->dateTime('date_added');
                $t->unsignedBigInteger('added_by')->nullable();
            });
        }
        if (! Schema::hasTable('prenatal_visits')) {
            Schema::create('prenatal_visits', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('patient_id')->index();
                $t->integer('health_unit_id')->index();
                $t->date('visit_date');
                $t->string('trimester')->nullable();
                $t->string('blood_pressure')->nullable();
                $t->decimal('weight', 6, 2)->nullable();
                $t->decimal('fundal_height', 6, 2)->nullable();
                $t->string('fetal_heart_tone')->nullable();
                $t->string('tt_dose')->nullable();
                $t->text('remarks')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('postpartum_visits')) {
            Schema::create('postpartum_visits', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('patient_id')->index();
                $t->integer('health_unit_id')->index();
                $t->date('delivery_date');
                $t->string('place_of_delivery')->nullable();
                $t->string('attended_by')->nullable();
                $t->date('visit_date');
                $t->integer('visit_number')->default(1);
                $t->text('remarks')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('doh_shipments')) {
            Schema::create('doh_shipments', function (Blueprint $t) {
                $t->id();
                $t->string('shipment_no', 50)->nullable();
                $t->string('reference_no', 100)->nullable();
                $t->string('supplier', 150)->nullable();
                $t->date('received_date');
                $t->unsignedBigInteger('received_by')->nullable();
                $t->text('remarks')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('doh_shipment_items')) {
            Schema::create('doh_shipment_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('shipment_id')->index();
                $t->unsignedBigInteger('medicine_id');
                $t->integer('quantity');
                $t->string('batch_no')->nullable();
                $t->date('expiration_date')->nullable();
                $t->string('storage_location')->nullable();
                $t->decimal('unit_cost', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally preserve imported legacy tables on rollback. A fresh test database
        // can be discarded, but rolling back an existing installation must not erase it.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'type')) {
                $table->string('type', 20)->nullable()->after('parent_id')->index();
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            $numericColumns = [
                'port_count',
                'rj45_ports',
                'sfp_ports',
                'sfp_plus_ports',
                'sfp28_ports',
                'qsfp_ports',
                'poe_ports',
            ];
            foreach ($numericColumns as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    $table->unsignedSmallInteger($column)->nullable();
                }
            }

            foreach (['poe_budget' => 'unsignedInteger'] as $column => $type) {
                if (! Schema::hasColumn('products', $column)) {
                    $table->{$type}($column)->nullable();
                }
            }

            if (! Schema::hasColumn('products', 'poe_max_per_port')) {
                $table->unsignedSmallInteger('poe_max_per_port')->nullable();
            }

            $stringColumns = [
                'poe_standard' => 20,
                'management_type' => 20,
                'layer' => 10,
                'port_speed' => 20,
                'uplink_type' => 20,
                'switching_capacity' => 60,
                'forwarding_rate' => 60,
                'mac_table' => 40,
                'cooling' => 20,
                'power_supply' => 120,
                'power_consumption' => 60,
                'operating_temperature' => 60,
                'warranty' => 60,
                'availability' => 20,
            ];
            foreach ($stringColumns as $column => $length) {
                if (! Schema::hasColumn('products', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }

            $booleanColumns = [
                'vlan_support',
                'qos',
                'stp',
                'lacp',
                'snmp',
                'acl',
                'rackmount',
                'desktop',
                'din_rail',
                'outdoor',
                'industrial',
                'stackable',
            ];
            foreach ($booleanColumns as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    $table->boolean($column)->default(false);
                }
            }

            if (! Schema::hasColumn('products', 'applications')) {
                $table->json('applications')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $columns = [
                'port_count',
                'rj45_ports',
                'sfp_ports',
                'sfp_plus_ports',
                'sfp28_ports',
                'qsfp_ports',
                'poe_ports',
                'poe_budget',
                'poe_max_per_port',
                'poe_standard',
                'management_type',
                'layer',
                'port_speed',
                'uplink_type',
                'switching_capacity',
                'forwarding_rate',
                'mac_table',
                'vlan_support',
                'qos',
                'stp',
                'lacp',
                'snmp',
                'acl',
                'rackmount',
                'desktop',
                'din_rail',
                'outdoor',
                'industrial',
                'stackable',
                'cooling',
                'power_supply',
                'power_consumption',
                'operating_temperature',
                'warranty',
                'availability',
                'applications',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('products', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });

        Schema::table('categories', function (Blueprint $table): void {
            if (Schema::hasColumn('categories', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOdjezdyTables extends Migration
{
    public function up()
    {
        // Starší verze projektu (migrace VytvorTabulky) měla tabulky gtfs_* se stejnými názvy,
        // ale jinými sloupci (lat/lon, departure_sec, mon..sun). Takové tabulky by s touto verzí
        // nefungovaly a createTable(..., true) by je tiše přeskočil, proto se založí znovu.
        // Jde jen o importovaná GTFS data, ta se dají kdykoli naimportovat znovu
        // (php spark gtfs:import). Tabulka vybrane_zastavky se NEMAŽE.
        if ($this->db->tableExists('gtfs_stops') && ! $this->db->fieldExists('stop_lat', 'gtfs_stops')) {
            foreach (['gtfs_stop_times', 'gtfs_trips', 'gtfs_calendar_dates', 'gtfs_calendar', 'gtfs_routes', 'gtfs_stops'] as $old) {
                $this->forge->dropTable($old, true);
            }
        }

        // --- zastávky -------------------------------------------------------
        $this->forge->addField([
            'stop_id'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'stop_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stop_lat'  => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'stop_lon'  => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('stop_id');
        $this->forge->addKey(['stop_lat', 'stop_lon']);
        $this->forge->addKey('stop_name');
        $this->forge->createTable('gtfs_stops', true);

        // --- linky ----------------------------------------------------------
        $this->forge->addField([
            'route_id'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'route_short_name' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'route_long_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'route_type'       => ['type' => 'INT', 'constraint' => 6, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('route_id');
        $this->forge->createTable('gtfs_routes', true);

        // --- spoje ----------------------------------------------------------
        $this->forge->addField([
            'trip_id'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'route_id'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'service_id'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'headsign'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_stop_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_seq'       => ['type' => 'INT', 'constraint' => 9, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('trip_id');
        $this->forge->addKey('service_id');
        $this->forge->addKey('route_id');
        $this->forge->createTable('gtfs_trips', true);

        // --- odjezdy ze zastávek -------------------------------------------
        // departure_secs = sekundy od půlnoci dne jízdy (může být > 86400)
        $this->forge->addField([
            'trip_id'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'stop_id'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'stop_sequence'  => ['type' => 'INT', 'constraint' => 9],
            'departure_secs' => ['type' => 'INT', 'constraint' => 9],
        ]);
        $this->forge->addPrimaryKey(['trip_id', 'stop_sequence']);
        $this->forge->addKey(['stop_id', 'departure_secs']);
        $this->forge->createTable('gtfs_stop_times', true);

        // --- kalendář -------------------------------------------------------
        $this->forge->addField([
            'service_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'monday'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'tuesday'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'wednesday'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'thursday'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'friday'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'saturday'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'sunday'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
        ]);
        $this->forge->addPrimaryKey('service_id');
        $this->forge->createTable('gtfs_calendar', true);

        $this->forge->addField([
            'service_id'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'service_date'   => ['type' => 'DATE'],
            'exception_type' => ['type' => 'TINYINT', 'constraint' => 1],
        ]);
        $this->forge->addPrimaryKey(['service_id', 'service_date']);
        $this->forge->addKey('service_date');
        $this->forge->createTable('gtfs_calendar_dates', true);

        // --- administrátoři a log importů ----------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 9, 'unsigned' => true, 'auto_increment' => true],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('odj_admins', true);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 9, 'unsigned' => true, 'auto_increment' => true],
            'imported_at' => ['type' => 'DATETIME'],
            'source'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'stops'       => ['type' => 'INT', 'constraint' => 9, 'default' => 0],
            'trips'       => ['type' => 'INT', 'constraint' => 9, 'default' => 0],
            'stop_times'  => ['type' => 'INT', 'constraint' => 9, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('odj_import_log', true);
    }

    public function down()
    {
        foreach ([
            'odj_import_log', 'odj_admins', 'gtfs_calendar_dates', 'gtfs_calendar',
            'gtfs_stop_times', 'gtfs_trips', 'gtfs_routes', 'gtfs_stops',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}

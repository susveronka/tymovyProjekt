<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class VytvorTabulky extends Migration
{
    public function up()
    {
        // zastávky
        $this->forge->addField([
            'stop_id'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'stop_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'lon'       => ['type' => 'DECIMAL', 'constraint' => '10,7'],
        ]);
        $this->forge->addPrimaryKey('stop_id');
        $this->forge->addKey('stop_name');
        $this->forge->createTable('gtfs_stops');

        // linky
        $this->forge->addField([
            'route_id'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'short_name' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'route_type' => ['type' => 'INT', 'default' => 3],
        ]);
        $this->forge->addPrimaryKey('route_id');
        $this->forge->createTable('gtfs_routes');

        // spoje (trip = jedna jízda autobusu/vlaku)
        $this->forge->addField([
            'trip_id'    => ['type' => 'VARCHAR', 'constraint' => 64],
            'route_id'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'service_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'headsign'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('trip_id');
        $this->forge->addKey('service_id');
        $this->forge->createTable('gtfs_trips');

        // odjezdy ze zastávek (čas je v sekundách od půlnoci)
        $this->forge->addField([
            'trip_id'       => ['type' => 'VARCHAR', 'constraint' => 64],
            'stop_id'       => ['type' => 'VARCHAR', 'constraint' => 64],
            'departure_sec' => ['type' => 'INT'],
            'stop_sequence' => ['type' => 'INT'],
        ]);
        $this->forge->addPrimaryKey(['trip_id', 'stop_sequence']);
        $this->forge->addKey(['stop_id', 'departure_sec']);
        $this->forge->createTable('gtfs_stop_times');

        // v které dny spoj jezdí
        $this->forge->addField([
            'service_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'mon' => ['type' => 'TINYINT'], 'tue' => ['type' => 'TINYINT'], 'wed' => ['type' => 'TINYINT'],
            'thu' => ['type' => 'TINYINT'], 'fri' => ['type' => 'TINYINT'], 'sat' => ['type' => 'TINYINT'],
            'sun' => ['type' => 'TINYINT'],
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
        ]);
        $this->forge->addPrimaryKey('service_id');
        $this->forge->createTable('gtfs_calendar');

        // výjimky (svátky): 1 = jede navíc, 2 = nejede
        $this->forge->addField([
            'service_id'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'date'           => ['type' => 'DATE'],
            'exception_type' => ['type' => 'TINYINT'],
        ]);
        $this->forge->addPrimaryKey(['service_id', 'date']);
        $this->forge->createTable('gtfs_calendar_dates');

        // zastávky vybrané v administraci
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'stop_id' => ['type' => 'VARCHAR', 'constraint' => 64],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('stop_id');
        $this->forge->createTable('vybrane_zastavky');
    }

    public function down()
    {
        $tabulky = ['vybrane_zastavky', 'gtfs_calendar_dates', 'gtfs_calendar', 'gtfs_stop_times', 'gtfs_trips', 'gtfs_routes', 'gtfs_stops'];
        foreach ($tabulky as $tabulka) {
            $this->forge->dropTable($tabulka, true);
        }
    }
}

<?php

namespace App\Commands;

use App\Libraries\GtfsImporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GtfsImport extends BaseCommand
{
    protected $group       = 'GTFS';
    protected $name        = 'gtfs:import';
    protected $description = 'Naimportuje výřez GTFS (ZIP) do databáze.';
    protected $usage       = 'gtfs:import [soubor.zip ...] [--bbox "minLat,minLon,maxLat,maxLon"] [--names "Hradiště,Orlová"] [--append]';
    protected $arguments   = [
        'soubor.zip' => 'Jeden nebo více GTFS ZIPů. Bez parametru se vezmou všechny writable/gtfs/*.zip.',
    ];
    protected $options = [
        '--bbox'   => 'Obdélník oblasti: minLat,minLon,maxLat,maxLon (zeměpisná šířka a délka ve stupních).',
        '--names'  => 'Čárkou oddělené části názvů zastávek. Spojuje se s --bbox logickým NEBO.',
        '--append' => 'Nemazat stávající data (přidat k nim).',
    ];

    public function run(array $params)
    {
        @ini_set('memory_limit', '1536M');
        @set_time_limit(0);

        // CodeIgniter předává do $params i volby (--bbox, --append…) s textovými klíči;
        // soubory jsou jen pozice s číselným klíčem.
        $files = array_values(array_filter($params, 'is_int', ARRAY_FILTER_USE_KEY));
        if (! $files) {
            $files = glob(WRITEPATH . 'gtfs' . DIRECTORY_SEPARATOR . '*.zip') ?: [];
        }
        // relativní cesty se berou od kořene projektu (spark mění pracovní adresář)
        $files = array_map(static function (string $f): string {
            return is_file($f) ? $f : (is_file(ROOTPATH . $f) ? ROOTPATH . $f : $f);
        }, $files);
        if (! $files) {
            CLI::error('Nenalezen žádný ZIP. Spusťte php spark gtfs:download nebo php spark gtfs:sample.');

            return EXIT_ERROR;
        }

        // chybějící soubor se zjistí dřív, než se smažou stará data
        foreach ($files as $f) {
            if (! is_file($f)) {
                CLI::error("Soubor neexistuje: {$f}");

                return EXIT_ERROR;
            }
        }

        $bbox = null;
        $opt  = CLI::getOption('bbox');
        if (is_string($opt) && $opt !== '') {
            $p = array_map('floatval', explode(',', $opt));
            if (count($p) !== 4 || $p[0] >= $p[2] || $p[1] >= $p[3]) {
                CLI::error('Neplatný --bbox. Očekává se minLat,minLon,maxLat,maxLon (min < max).');

                return EXIT_ERROR;
            }
            $bbox = $p;
        }
        $namesOpt = CLI::getOption('names');
        $names    = is_string($namesOpt) ? explode(',', $namesOpt) : [];

        if (! $bbox && ! $names) {
            CLI::write('Pozor: bez --bbox/--names se importují VŠECHNY zastávky. Při celostátních datech to zabere hodně času i paměti.', 'yellow');
        }

        $importer = new GtfsImporter(
            \Config\Database::connect(),
            static fn (string $m) => CLI::write($m)
        );

        if (! CLI::getOption('append')) {
            CLI::write('Mažu stará data…');
            $importer->truncateAll();
        }

        $stats = [];
        foreach ($files as $file) {
            CLI::write('== ' . basename($file), 'green');
            try {
                $stats = $importer->importFeed($file, $bbox, $names);
            } catch (\Throwable $e) {
                CLI::error($e->getMessage());

                return EXIT_ERROR;
            }
        }
        $importer->writeLog(implode(', ', array_map('basename', $files)), $stats);
        CLI::write('Import dokončen.', 'green');
    }
}

<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use ZipArchive;

/**
 * Vytvoří malé UKÁZKOVÉ GTFS (vymyšlené zastávky a spoje) pro vyzkoušení aplikace
 * bez stahování stovek MB dat.
 */
class GtfsSample extends BaseCommand
{
    protected $group       = 'GTFS';
    protected $name        = 'gtfs:sample';
    protected $description = 'Vytvoří malý ukázkový GTFS balíček writable/gtfs/ukazka_GTFS.zip.';
    protected $usage       = 'gtfs:sample';

    public function run(array $params)
    {
        $dir = WRITEPATH . 'gtfs';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'ukazka_GTFS.zip';

        // zastávky (vymyšlené názvy, souřadnice kolem Uherského Hradiště)
        $stops = [
            ['S1', 'Ukázka, nádraží',          49.0690, 17.4560],
            ['S2', 'Ukázka, náměstí',          49.0698, 17.4597],
            ['S3', 'Ukázka, nemocnice',        49.0735, 17.4650],
            ['S4', 'Ukázka, sídliště',         49.0780, 17.4720],
            ['S5', 'Ukázka, Mařatice',         49.0830, 17.4800],
            ['S6', 'Ukázka, průmyslová zóna',  49.0650, 17.4480],
            ['S7', 'Ukázka, Sady',             49.0720, 17.4530],
            ['S8', 'Ukázka, Staré Město',      49.0790, 17.4400],
        ];

        $routes = [
            ['R1', '1',    'Nádraží – Mařatice',        3],
            ['R2', '2',    'Průmyslová zóna – Staré Město', 3],
            ['R3', 'Os 21', 'Osobní vlak',               2],
        ];

        // definice linek: [route, směr, zastávky s minutami od začátku, první, poslední, interval pracovní den, interval víkend, headsign]
        $patterns = [
            ['R1', 0, [['S1', 0], ['S2', 4], ['S3', 9], ['S4', 14], ['S5', 20]], 5 * 60, 22 * 60 + 40, 20, 40, 'Mařatice'],
            ['R1', 1, [['S5', 0], ['S4', 6], ['S3', 11], ['S2', 16], ['S1', 20]], 5 * 60 + 10, 22 * 60 + 50, 20, 40, null],
            ['R2', 0, [['S6', 0], ['S2', 8], ['S7', 13], ['S8', 20]], 5 * 60 + 30, 21 * 60 + 30, 30, 60, 'Staré Město'],
            ['R2', 1, [['S8', 0], ['S7', 7], ['S2', 12], ['S6', 20]], 6 * 60, 22 * 60, 30, 60, null],
            ['R3', 0, [['S1', 0], ['S4', 18]], 6 * 60 + 12, 19 * 60 + 12, 90, 120, 'Ukázka, sídliště'],
        ];

        $trips = [];
        $times = [];
        $n     = 0;
        foreach ($patterns as [$route, $dir_, $pStops, $first, $last, $wd, $we, $head]) {
            foreach (['WD' => $wd, 'WE' => $we] as $service => $interval) {
                for ($t = $first; $t <= $last; $t += $interval) {
                    $n++;
                    $tripId  = "T{$n}";
                    $trips[] = [$route, $service, $tripId, $head ?? ''];
                    foreach ($pStops as $i => [$stop, $offset]) {
                        $times[] = [$tripId, $this->hms(($t + $offset) * 60), $this->hms(($t + $offset) * 60), $stop, $i + 1];
                    }
                }
            }
        }
        // noční spoj přes půlnoc (časy nad 24:00:00 patří ještě do dne zahájení jízdy)
        $n++;
        $trips[] = ['R1', 'WD', "T{$n}", 'Mařatice'];
        foreach ([['S1', 23 * 60 + 50], ['S2', 24 * 60 + 2], ['S3', 24 * 60 + 8], ['S4', 24 * 60 + 14], ['S5', 24 * 60 + 21]] as $i => [$stop, $m]) {
            $times[] = ["T{$n}", $this->hms($m * 60), $this->hms($m * 60), $stop, $i + 1];
        }

        $csv = static function (array $header, array $rows): string {
            $h = fopen('php://temp', 'w+');
            fputcsv($h, $header, ',', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($h, $r, ',', '"', '\\');
            }
            rewind($h);
            $s = stream_get_contents($h);
            fclose($h);

            return $s;
        };

        $year = (int) date('Y');
        $files = [
            'stops.txt'  => $csv(['stop_id', 'stop_name', 'stop_lat', 'stop_lon'], $stops),
            'routes.txt' => $csv(['route_id', 'route_short_name', 'route_long_name', 'route_type'], $routes),
            'trips.txt'  => $csv(['route_id', 'service_id', 'trip_id', 'trip_headsign'], $trips),
            'stop_times.txt' => $csv(['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence'], $times),
            'calendar.txt' => $csv(
                ['service_id', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'start_date', 'end_date'],
                [
                    ['WD', 1, 1, 1, 1, 1, 0, 0, ($year - 1) . '0101', ($year + 1) . '1231'],
                    ['WE', 0, 0, 0, 0, 0, 1, 1, ($year - 1) . '0101', ($year + 1) . '1231'],
                ]
            ),
            // státní svátky: v pracovní den jedou víkendové spoje
            'calendar_dates.txt' => $csv(['service_id', 'date', 'exception_type'], $this->holidays($year)),
        ];

        @unlink($path);
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            CLI::error('Nelze vytvořit ' . $path);

            return EXIT_ERROR;
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        CLI::write('Vytvořeno: ' . $path . ' (' . count($stops) . ' zastávek, ' . count($trips) . ' spojů)', 'green');
        CLI::write('Import: php spark gtfs:import writable/gtfs/ukazka_GTFS.zip');
    }

    private function hms(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /** @return list<array{0:string,1:string,2:int}> */
    private function holidays(int $year): array
    {
        $rows = [];
        foreach ([$year, $year + 1] as $y) {
            foreach (['0101', '0501', '0508', '0705', '0706', '0928', '1028', '1117', '1224', '1225', '1226'] as $md) {
                $ts = strtotime("{$y}-" . substr($md, 0, 2) . '-' . substr($md, 2, 2));
                if ((int) date('N', $ts) <= 5) {
                    $d      = $y . $md;
                    $rows[] = ['WD', $d, 2];
                    $rows[] = ['WE', $d, 1];
                }
            }
        }

        return $rows;
    }
}

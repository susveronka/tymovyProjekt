<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use ZipArchive;

/**
 * Streamovaný import GTFS (ZIP) do tabulek gtfs_*.
 *
 * - zastávky se filtrují podle obdélníku (bbox) a/nebo částí názvu; celá ČR je desítky
 *   milionů řádků, proto se ukládají jen zastávky, které nás zajímají
 * - odjezdy se ukládají jen ze zastávek ve výřezu a bez zastávek "pouze výstup"
 * - ID z různých zdrojů (autobusy x vlaky) dostanou předponu odvozenou z názvu souboru,
 *   aby se nepletla; stejný soubor má při každém importu stejnou předponu, takže
 *   vytištěné QR kódy zůstávají platné i po aktualizaci dat
 *
 * Použití (viz app/Commands/GtfsImport.php):
 *   $imp = new GtfsImporter($db, $log);
 *   $imp->truncateAll();
 *   $imp->importFeed($zip, $bbox, $names);
 *   $imp->writeLog('soubor.zip', $stats);
 */
class GtfsImporter
{
    private const BATCH = 2000;

    private BaseConnection $db;

    /** @var callable|null */
    private $log;

    /** @var array<string, list<array>> */
    private array $buffers = [];

    /** součty za všechny feedy importované touto instancí */
    private array $totals = ['stops' => 0, 'trips' => 0, 'stop_times' => 0];

    public function __construct(?BaseConnection $db = null, ?callable $log = null)
    {
        $this->db  = $db ?? db_connect();
        $this->log = $log;
    }

    public function truncateAll(): void
    {
        foreach (['gtfs_stop_times', 'gtfs_trips', 'gtfs_calendar_dates', 'gtfs_calendar', 'gtfs_routes', 'gtfs_stops'] as $t) {
            // empty() místo truncate(): funguje stejně v MySQL i v SQLite
            $this->db->table($t)->emptyTable();
        }
    }

    /**
     * @param array{0:float,1:float,2:float,3:float}|null $bbox  minLat, minLon, maxLat, maxLon
     * @param list<string> $names  části názvů zastávek (bez ohledu na velikost písmen)
     *
     * @return array{stops:int,trips:int,stop_times:int}
     */
    public function importFeed(string $zipPath, ?array $bbox = null, array $names = []): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException("Soubor neexistuje: {$zipPath}");
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Nelze otevřít ZIP: {$zipPath}");
        }

        try {
            $stats = $this->importZip($zip, $this->prefixFor($zipPath), $bbox, $this->cleanNames($names));
        } finally {
            $zip->close();
        }

        foreach ($stats as $k => $v) {
            $this->totals[$k] += $v;
        }

        return $stats;
    }

    /** Zapíše záznam o importu; při více feedech se použijí součty za celý běh. */
    public function writeLog(string $source, array $stats = []): void
    {
        $s = array_sum($this->totals) > 0 ? $this->totals : $stats + ['stops' => 0, 'trips' => 0, 'stop_times' => 0];

        $this->db->table('odj_import_log')->insert([
            'imported_at' => date('Y-m-d H:i:s'),
            'source'      => mb_substr($source, 0, 255),
            'stops'       => (int) $s['stops'],
            'trips'       => (int) $s['trips'],
            'stop_times'  => (int) $s['stop_times'],
        ]);
    }

    // ------------------------------------------------------------------------

    private function importZip(ZipArchive $zip, string $p, ?array $bbox, array $names): array
    {
        $filtering = $bbox !== null || $names !== [];
        $stopNames = [];   // všechna jména zastávek (kvůli cílové stanici spoje)
        $kept      = [];   // zastávky, které importujeme
        $nStops    = 0;

        // 1) zastávky
        foreach ($this->rows($zip, 'stops.txt') as $r) {
            $id   = $p . ($r['stop_id'] ?? '');
            $name = trim($r['stop_name'] ?? '');
            if (($r['stop_id'] ?? '') === '' || $name === '') {
                continue;
            }
            $stopNames[$id] = $name;

            $lat = ($r['stop_lat'] ?? '') !== '' ? (float) $r['stop_lat'] : null;
            $lon = ($r['stop_lon'] ?? '') !== '' ? (float) $r['stop_lon'] : null;

            if ($filtering && ! $this->matches($name, $lat, $lon, $bbox, $names)) {
                continue;
            }
            $kept[$id] = true;
            $nStops++;
            $this->add('gtfs_stops', [
                'stop_id' => $id, 'stop_name' => mb_substr($name, 0, 255), 'stop_lat' => $lat, 'stop_lon' => $lon,
            ]);
        }
        $this->flush('gtfs_stops');
        $this->say("Zastávky: {$nStops} (z " . count($stopNames) . ' ve feedu)');

        // 2) linky
        foreach ($this->rows($zip, 'routes.txt') as $r) {
            if (($r['route_id'] ?? '') === '') {
                continue;
            }
            $this->add('gtfs_routes', [
                'route_id'         => $p . $r['route_id'],
                'route_short_name' => $this->cut($r['route_short_name'] ?? '', 64),
                'route_long_name'  => $this->cut($r['route_long_name'] ?? '', 255),
                'route_type'       => ($r['route_type'] ?? '') !== '' ? (int) $r['route_type'] : null,
            ]);
        }
        $this->flush('gtfs_routes');

        // 3) kalendář
        foreach ($this->rows($zip, 'calendar.txt') as $r) {
            if (($r['service_id'] ?? '') === '') {
                continue;
            }
            $this->add('gtfs_calendar', [
                'service_id' => $p . $r['service_id'],
                'monday'     => (int) ($r['monday'] ?? 0),
                'tuesday'    => (int) ($r['tuesday'] ?? 0),
                'wednesday'  => (int) ($r['wednesday'] ?? 0),
                'thursday'   => (int) ($r['thursday'] ?? 0),
                'friday'     => (int) ($r['friday'] ?? 0),
                'saturday'   => (int) ($r['saturday'] ?? 0),
                'sunday'     => (int) ($r['sunday'] ?? 0),
                'start_date' => $this->date($r['start_date'] ?? ''),
                'end_date'   => $this->date($r['end_date'] ?? ''),
            ]);
        }
        $this->flush('gtfs_calendar');

        foreach ($this->rows($zip, 'calendar_dates.txt') as $r) {
            if (($r['service_id'] ?? '') === '') {
                continue;
            }
            $this->add('gtfs_calendar_dates', [
                'service_id'     => $p . $r['service_id'],
                'service_date'   => $this->date($r['date'] ?? ''),
                'exception_type' => (int) ($r['exception_type'] ?? 0),
            ]);
        }
        $this->flush('gtfs_calendar_dates');
        $this->say('Linky a kalendář hotové');

        // 4) stop_times – největší soubor
        $last    = [];  // trip_id => "seq\tstop_id" (poslední zastávka spoje)
        $touched = [];  // spoje, které zastavují v naší množině zastávek
        $nTimes  = 0;
        $i       = 0;
        foreach ($this->rows($zip, 'stop_times.txt') as $r) {
            if (++$i % 500000 === 0) {
                $this->say('  stop_times: ' . number_format($i, 0, ',', ' ') . ' řádků zpracováno');
            }
            $trip = $p . ($r['trip_id'] ?? '');
            $stop = $p . ($r['stop_id'] ?? '');
            $seq  = (int) ($r['stop_sequence'] ?? 0);

            if (! isset($last[$trip]) || $seq > (int) strtok($last[$trip], "\t")) {
                $last[$trip] = $seq . "\t" . $stop;
            }

            if ($filtering && ! isset($kept[$stop])) {
                continue;
            }
            if ((int) ($r['pickup_type'] ?? 0) === 1) {
                continue; // nelze nastoupit
            }
            $secs = $this->secs($r['departure_time'] ?? '') ?? $this->secs($r['arrival_time'] ?? '');
            if ($secs === null) {
                continue;
            }
            $touched[$trip] = true;
            $nTimes++;
            $this->add('gtfs_stop_times', [
                'trip_id' => $trip, 'stop_id' => $stop, 'stop_sequence' => $seq, 'departure_secs' => $secs,
            ]);
        }
        $this->flush('gtfs_stop_times');
        $this->say('Odjezdy ze zastávek: ' . number_format($nTimes, 0, ',', ' '));

        // bez filtru nebyly "kept" naplněné zastávky potřeba, ale zastávky jsou uložené všechny
        // 5) spoje (jen ty, které nás zajímají)
        $nTrips = 0;
        foreach ($this->rows($zip, 'trips.txt') as $r) {
            $id = $p . ($r['trip_id'] ?? '');
            if (! isset($touched[$id])) {
                continue;
            }
            [$lastSeq, $lastStop] = explode("\t", $last[$id]);
            $nTrips++;
            $this->add('gtfs_trips', [
                'trip_id'        => $id,
                'route_id'       => $p . ($r['route_id'] ?? ''),
                'service_id'     => $p . ($r['service_id'] ?? ''),
                'headsign'       => $this->cut($r['trip_headsign'] ?? '', 255) ?: null,
                'last_stop_name' => isset($stopNames[$lastStop]) ? mb_substr($stopNames[$lastStop], 0, 255) : null,
                'last_seq'       => (int) $lastSeq,
            ]);
        }
        $this->flush('gtfs_trips');
        $this->say("Spoje: {$nTrips}");

        return ['stops' => $nStops, 'trips' => $nTrips, 'stop_times' => $nTimes];
    }

    private function matches(string $name, ?float $lat, ?float $lon, ?array $bbox, array $names): bool
    {
        if ($bbox !== null && $lat !== null && $lon !== null
            && $lat >= $bbox[0] && $lat <= $bbox[2] && $lon >= $bbox[1] && $lon <= $bbox[3]) {
            return true;
        }
        foreach ($names as $n) {
            if (mb_stripos($name, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function cleanNames(array $names): array
    {
        return array_values(array_filter(array_map('trim', $names), static fn ($n) => $n !== ''));
    }

    private function prefixFor(string $zipPath): string
    {
        $base = basename($zipPath);
        if (stripos($base, 'JDF') !== false) {
            return 'bus_';
        }
        if (stripos($base, 'CZPTT') !== false) {
            return 'vlak_';
        }

        return 'f' . substr(sprintf('%08x', crc32($base)), 0, 4) . '_';
    }

    /** Čte CSV soubor ze ZIPu po řádcích jako asociativní pole. */
    private function rows(ZipArchive $zip, string $name): \Generator
    {
        $stream = $zip->getStream($name);
        if ($stream === false) {
            $this->say("  (soubor {$name} v ZIPu chybí)");

            return;
        }
        $header = fgetcsv($stream, 0, ',', '"', '');
        if (! is_array($header)) {
            fclose($stream);

            return;
        }
        $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF"); // BOM
        $header    = array_map('trim', $header);
        $n         = count($header);

        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count($row) < $n) {
                $row = array_pad($row, $n, '');
            } elseif (count($row) > $n) {
                $row = array_slice($row, 0, $n);
            }
            yield array_combine($header, $row);
        }
        fclose($stream);
    }

    private function add(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;
        if (count($this->buffers[$table]) >= self::BATCH) {
            $this->flush($table);
        }
    }

    private function flush(string $table): void
    {
        if (empty($this->buffers[$table])) {
            return;
        }
        $this->db->table($table)->ignore(true)->insertBatch($this->buffers[$table]);
        $this->buffers[$table] = [];
    }

    private function secs(string $t): ?int
    {
        $t = trim($t);
        if ($t === '' || ! str_contains($t, ':')) {
            return null;
        }
        $parts = explode(':', $t);

        return ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + (int) ($parts[2] ?? 0);
    }

    private function date(string $d): string
    {
        $d = trim($d);

        return strlen($d) === 8 ? substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2) : '1970-01-01';
    }

    private function cut(string $s, int $max): string
    {
        return mb_substr(trim($s), 0, $max);
    }

    private function say(string $msg): void
    {
        if ($this->log) {
            ($this->log)($msg);
        }
    }
}

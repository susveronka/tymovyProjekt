<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Odjezdy;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Výpočet odjezdů ze zastávky z importovaných dat GTFS.
 *
 * Princip: pro zastávku se načtou všechny její odjezdy (gtfs_stop_times + spoj),
 * pak se pro jednotlivé dny zjistí, které "služby" (service_id) v ten den jedou
 * (calendar + calendar_dates), a z toho se poskládají nejbližší odjezdy.
 */
class DepartureService
{
    private const DAYS_SHORT = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
    private const DAY_COLUMNS = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];

    private BaseConnection $db;
    private Odjezdy $cfg;
    private DateTimeZone $tz;

    /** @var array<string, array<string,bool>> */
    private array $serviceCache = [];
    /** @var array<string, array> */
    private array $rowCache = [];

    public function __construct(?BaseConnection $db = null, ?Odjezdy $cfg = null)
    {
        $this->db  = $db ?? \Config\Database::connect();
        $this->cfg = $cfg ?? config('Odjezdy');
        $this->tz  = new DateTimeZone($this->cfg->timezone);
    }

    /** Aktuální čas, případně přepsaný parametrem ?cas=YYYY-MM-DDTHH:MM (pro ladění a ukázky). */
    public function now(?string $override = null): DateTimeImmutable
    {
        if ($override !== null && $override !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $override, $this->tz)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $override, $this->tz);
            if ($dt !== false) {
                return $dt;
            }
        }

        return new DateTimeImmutable('now', $this->tz);
    }

    public function findStop(string $stopId): ?array
    {
        $row = $this->db->table('gtfs_stops')->where('stop_id', $stopId)->get()->getRowArray();

        return $row ?: null;
    }

    public function boardUrl(string $stopId): string
    {
        $base = $this->cfg->publicBaseUrl !== '' ? $this->cfg->publicBaseUrl : base_url();

        return rtrim($base, '/') . '/tabule/' . rawurlencode($stopId);
    }

    /** @return array<string,bool> service_id => true pro daný den (Y-m-d) */
    public function activeServices(string $date): array
    {
        if (isset($this->serviceCache[$date])) {
            return $this->serviceCache[$date];
        }

        $dt  = new DateTimeImmutable($date, $this->tz);
        $col = self::DAY_COLUMNS[(int) $dt->format('N')];

        $active = [];
        $rows   = $this->db->table('gtfs_calendar')
            ->select('service_id')
            ->where($col, 1)
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $active[$r['service_id']] = true;
        }

        $exceptions = $this->db->table('gtfs_calendar_dates')
            ->select('service_id, exception_type')
            ->where('service_date', $date)
            ->get()->getResultArray();
        foreach ($exceptions as $r) {
            if ((int) $r['exception_type'] === 1) {
                $active[$r['service_id']] = true;
            } elseif ((int) $r['exception_type'] === 2) {
                unset($active[$r['service_id']]);
            }
        }

        return $this->serviceCache[$date] = $active;
    }

    /** Začátek dne jízdy podle specifikace GTFS: poledne minus 12 hodin (správně i při změně času). */
    private function serviceDayStart(string $date): int
    {
        return (new DateTimeImmutable($date . ' 12:00:00', $this->tz))->getTimestamp() - 43200;
    }

    private function stopRows(string $stopId): array
    {
        if (isset($this->rowCache[$stopId])) {
            return $this->rowCache[$stopId];
        }

        return $this->rowCache[$stopId] = $this->db->table('gtfs_stop_times st')
            ->select('st.departure_secs, t.service_id, t.headsign, t.last_stop_name, r.route_short_name, r.route_long_name, r.route_type')
            ->join('gtfs_trips t', 't.trip_id = st.trip_id')
            ->join('gtfs_routes r', 'r.route_id = t.route_id', 'left')
            ->where('st.stop_id', $stopId)
            ->where('st.stop_sequence < t.last_seq', null, false) // na konečné se nenastupuje
            ->orderBy('st.departure_secs', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Nejbližší odjezdy po zadaném čase.
     *
     * @return list<array{ts:int,time:string,date:string,dayLabel:string,minutes:int,eta:string,line:string,headsign:string,type:string}>
     */
    public function nextDepartures(string $stopId, DateTimeImmutable $now, int $limit = 10): array
    {
        $rows = $this->stopRows($stopId);
        if (! $rows) {
            return [];
        }

        $today = $now->setTime(0, 0);
        $nowTs = $now->getTimestamp();
        $found = [];

        // včerejšek (spoje po půlnoci), dnes, další dny; dny +3 a dál jen když zatím nemáme dost odjezdů
        for ($off = -1; $off <= 7; $off++) {
            if ($off > 2 && count($found) >= $limit) {
                break;
            }
            $date   = $today->modify(sprintf('%+d days', $off))->format('Y-m-d');
            $active = $this->activeServices($date);
            if (! $active) {
                continue;
            }
            $base = $this->serviceDayStart($date);
            foreach ($rows as $r) {
                if (! isset($active[$r['service_id']])) {
                    continue;
                }
                $ts = $base + (int) $r['departure_secs'];
                if ($ts >= $nowTs) {
                    $found[] = [$ts, $r];
                }
            }
        }

        usort($found, static fn ($a, $b) => $a[0] <=> $b[0]);

        $out = [];
        foreach (array_slice($found, 0, $limit) as [$ts, $r]) {
            $out[] = $this->format($ts, $r, $now);
        }

        return $out;
    }

    private function format(int $ts, array $r, DateTimeImmutable $now): array
    {
        $dt      = (new DateTimeImmutable('@' . $ts))->setTimezone($this->tz);
        $date    = $dt->format('Y-m-d');
        $today   = $now->format('Y-m-d');
        $tomorrow = $now->modify('+1 day')->format('Y-m-d');

        if ($date === $today) {
            $dayLabel = '';
        } elseif ($date === $tomorrow) {
            $dayLabel = 'zítra';
        } else {
            $dayLabel = self::DAYS_SHORT[(int) $dt->format('N') - 1] . ' ' . $dt->format('j. n.');
        }

        $minutes = intdiv($ts - $now->getTimestamp(), 60);
        $line    = trim((string) ($r['route_short_name'] ?? '')) ?: trim((string) ($r['route_long_name'] ?? '')) ?: '?';
        $head    = trim((string) ($r['headsign'] ?? '')) ?: trim((string) ($r['last_stop_name'] ?? ''));

        return [
            'ts'       => $ts,
            'time'     => $dt->format('H:i'),
            'date'     => $date,
            'dayLabel' => $dayLabel,
            'minutes'  => $minutes,
            'eta'      => $this->eta($minutes),
            'line'     => $line,
            'headsign' => $head,
            'type'     => $this->routeType($r['route_type'] ?? null),
        ];
    }

    private function eta(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'nyní';
        }
        if ($minutes < 60) {
            return 'za ' . $minutes . ' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($minutes >= 1440) {
            return '';
        }

        return 'za ' . $h . ' h' . ($m ? ' ' . $m . ' min' : '');
    }

    /** Zjednodušení typu linky (GTFS i rozšířené kódy) na bus / train / tram / trolley / other. */
    private function routeType($type): string
    {
        if ($type === null || $type === '') {
            return 'other';
        }
        $t = (int) $type;
        if ($t === 2 || ($t >= 100 && $t < 200)) {
            return 'train';
        }
        if ($t === 3 || ($t >= 700 && $t < 800)) {
            return 'bus';
        }
        if ($t === 0 || ($t >= 900 && $t < 1000)) {
            return 'tram';
        }
        if ($t === 11 || $t === 800) {
            return 'trolley';
        }

        return 'other';
    }

    /**
     * Počet odjezdů ve dnech aktuálního týdne (pondělí–neděle).
     *
     * @return array{labels:list<string>,dates:list<string>,counts:list<int>,todayIndex:int,weekStart:string,weekEnd:string}
     */
    public function weekCounts(string $stopId, DateTimeImmutable $now): array
    {
        $perService = [];
        $rows = $this->db->table('gtfs_stop_times st')
            ->select('t.service_id, COUNT(*) AS c', false)
            ->join('gtfs_trips t', 't.trip_id = st.trip_id')
            ->where('st.stop_id', $stopId)
            ->where('st.stop_sequence < t.last_seq', null, false)
            ->groupBy('t.service_id')
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $perService[$r['service_id']] = (int) $r['c'];
        }

        $day    = $now->setTime(0, 0);
        $monday = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');

        $labels = $dates = $counts = [];
        for ($i = 0; $i < 7; $i++) {
            $d      = $monday->modify("+{$i} days");
            $date   = $d->format('Y-m-d');
            $sum    = 0;
            foreach ($this->activeServices($date) as $sid => $_) {
                $sum += $perService[$sid] ?? 0;
            }
            $labels[] = self::DAYS_SHORT[$i];
            $dates[]  = $d->format('j. n.');
            $counts[] = $sum;
        }

        return [
            'labels'     => $labels,
            'dates'      => $dates,
            'counts'     => $counts,
            'todayIndex' => (int) $day->format('N') - 1,
            'weekStart'  => $monday->format('j. n. Y'),
            'weekEnd'    => $monday->modify('+6 days')->format('j. n. Y'),
        ];
    }
}

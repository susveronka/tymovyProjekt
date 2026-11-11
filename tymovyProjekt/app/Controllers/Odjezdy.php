<?php
namespace App\Controllers;

class Odjezdy extends BaseController
{
    // ---------- Pomocné funkce ----------

    // Vrátí seznam spojů (service_id), které jedou v daný den (formát "2026-11-11")
    private function spojeVDen($datum)
    {
        $db = db_connect();
        $sloupce = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $sloupec = $sloupce[date('N', strtotime($datum)) - 1]; // pondělí = 1

        // běžný kalendář
        $radky = $db->query(
            "SELECT service_id FROM gtfs_calendar WHERE $sloupec = 1 AND start_date <= ? AND end_date >= ?",
            [$datum, $datum]
        )->getResultArray();

        $spoje = [];
        foreach ($radky as $r) {
            $spoje[] = $r['service_id'];
        }

        // výjimky (svátky): 1 = jede navíc, 2 = nejede
        $vyjimky = $db->query('SELECT service_id, exception_type FROM gtfs_calendar_dates WHERE date = ?', [$datum])->getResultArray();
        foreach ($vyjimky as $v) {
            if ($v['exception_type'] == 1) {
                $spoje[] = $v['service_id'];
            } else {
                $spoje = array_diff($spoje, [$v['service_id']]);
            }
        }
        return $spoje;
    }

    // Vrátí nejbližší odjezdy ze zastávky (dnes, od teď)
    private function nejblizsiOdjezdy($zastavkaId, $pocet)
    {
        $spoje = $this->spojeVDen(date('Y-m-d'));
        if (count($spoje) == 0) {
            return [];
        }

        // kolik sekund uběhlo od půlnoci
        $ted = time() - strtotime('today');

        $radky = db_connect()->table('gtfs_stop_times st')
            ->select('st.departure_sec, t.headsign, r.short_name, r.route_type')
            ->join('gtfs_trips t', 't.trip_id = st.trip_id')
            ->join('gtfs_routes r', 'r.route_id = t.route_id')
            ->where('st.stop_id', $zastavkaId)
            ->where('st.departure_sec >=', $ted)
            ->whereIn('t.service_id', $spoje)
            ->orderBy('st.departure_sec')
            ->limit($pocet)
            ->get()->getResultArray();

        $odjezdy = [];
        foreach ($radky as $r) {
            $hodiny  = floor($r['departure_sec'] / 3600) % 24;
            $minuty  = floor($r['departure_sec'] % 3600 / 60);

            $typ = 'autobus';
            if ($r['route_type'] == 2 || ($r['route_type'] >= 100 && $r['route_type'] < 200)) {
                $typ = 'vlak';
            }

            $odjezdy[] = [
                'cas'    => sprintf('%02d:%02d', $hodiny, $minuty),
                'za_min' => floor(($r['departure_sec'] - $ted) / 60),
                'linka'  => $r['short_name'],
                'smer'   => $r['headsign'],
                'typ'    => $typ,
            ];
        }
        return $odjezdy;
    }

    // ---------- Stránky ----------

    // Odjezdová tabule zastávky (na tuhle adresu vede QR kód)
    public function tabule($zastavkaId)
    {
        $zastavka = db_connect()->table('gtfs_stops')->where('stop_id', urldecode($zastavkaId))->get()->getRowArray();
        if (!$zastavka) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return view('tabule', ['zastavka' => $zastavka]);
    }

    public function mapa()
    {
        return view('mapa');
    }

    // ---------- Data pro JavaScript (JSON) ----------

    public function odjezdyJson($zastavkaId)
    {
        $odjezdy = $this->nejblizsiOdjezdy(urldecode($zastavkaId), 10);
        return $this->response->setJSON($odjezdy);
    }

    // Počet odjezdů v jednotlivých dnech aktuálního týdne
    public function tydenJson($zastavkaId)
    {
        $zastavkaId = urldecode($zastavkaId);
        $db = db_connect();

        // pondělí tohoto týdne
        $pondeli = strtotime('monday this week');

        $pocty = [];
        for ($i = 0; $i < 7; $i++) {
            $datum = date('Y-m-d', strtotime("+$i day", $pondeli));
            $spoje = $this->spojeVDen($datum);

            if (count($spoje) == 0) {
                $pocty[] = 0;
            } else {
                $pocty[] = $db->table('gtfs_stop_times st')
                    ->join('gtfs_trips t', 't.trip_id = st.trip_id')
                    ->where('st.stop_id', $zastavkaId)
                    ->whereIn('t.service_id', $spoje)
                    ->countAllResults();
            }
        }

        return $this->response->setJSON([
            'popisky' => ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'],
            'pocty'   => $pocty,
            'dnes'    => date('N') - 1,
        ]);
    }

    // Zastávky vybrané v administraci + jejich 3 nejbližší odjezdy
    public function mapaJson()
    {
        $zastavky = db_connect()->table('vybrane_zastavky v')
            ->select('s.stop_id, s.stop_name, s.lat, s.lon')
            ->join('gtfs_stops s', 's.stop_id = v.stop_id')
            ->get()->getResultArray();

        $vysledek = [];
        foreach ($zastavky as $z) {
            $vysledek[] = [
                'nazev'   => $z['stop_name'],
                'lat'     => (float) $z['lat'],
                'lon'     => (float) $z['lon'],
                'url'     => base_url('odjezdy/' . rawurlencode($z['stop_id'])),
                'odjezdy' => $this->nejblizsiOdjezdy($z['stop_id'], 3),
            ];
        }
        return $this->response->setJSON($vysledek);
    }

    // ---------- Administrace ----------

    public function admin()
    {
        $db = db_connect();
        $hledani = trim($this->request->getGet('q') ?? '');

        // vyhledávání zastávek podle názvu
        $nalezene = [];
        if ($hledani != '') {
            $nalezene = $db->table('gtfs_stops')->like('stop_name', $hledani, 'after')
                ->orderBy('stop_name')->limit(50)->get()->getResultArray();
        }

        // zastávky, pro které se zobrazí QR kód
        $vybrane = $db->table('vybrane_zastavky v')
            ->select('v.id, v.stop_id, s.stop_name')
            ->join('gtfs_stops s', 's.stop_id = v.stop_id')
            ->orderBy('s.stop_name')->get()->getResultArray();

        // odkaz, který se uloží do QR kódu
        foreach ($vybrane as $i => $v) {
            $vybrane[$i]['url'] = base_url('odjezdy/' . rawurlencode($v['stop_id']));
        }

        return view('admin/index', ['hledani' => $hledani, 'nalezene' => $nalezene, 'vybrane' => $vybrane]);
    }

    public function pridat()
    {
        $id = $this->request->getPost('stop_id');
        if ($id) {
            db_connect()->table('vybrane_zastavky')->ignore(true)->insert(['stop_id' => $id]);
        }
        return redirect()->to('admin');
    }

    public function smazat($id)
    {
        db_connect()->table('vybrane_zastavky')->delete(['id' => $id]);
        return redirect()->to('admin');
    }
}

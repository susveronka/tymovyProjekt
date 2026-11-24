<?php

namespace App\Controllers\Odjezdy;

use App\Controllers\BaseController;
use App\Libraries\DepartureService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class Admin extends BaseController
{
    private const PER_PAGE = 25;
    private const MAX_QR   = 60;

    private function guard(): ?RedirectResponse
    {
        return session()->get('odj_admin') ? null : redirect()->to('admin/login');
    }

    public function login()
    {
        if (session()->get('odj_admin')) {
            return redirect()->to('admin');
        }

        return view('odjezdy/admin/login', ['title' => 'Přihlášení', 'error' => session()->getFlashdata('error')]);
    }

    public function attempt(): RedirectResponse
    {
        $user = trim((string) $this->request->getPost('username'));
        $pass = (string) $this->request->getPost('password');

        $row = \Config\Database::connect()->table('odj_admins')->where('username', $user)->get()->getRowArray();
        // password_verify i při neexistujícím uživateli (stálý čas odpovědi)
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok   = password_verify($pass, $hash) && $row;

        if (! $ok) {
            usleep(300000);

            return redirect()->to('admin/login')->with('error', 'Nesprávné jméno nebo heslo.');
        }

        session()->regenerate();
        session()->set('odj_admin', $row['username']);

        return redirect()->to('admin');
    }

    public function logout(): RedirectResponse
    {
        session()->remove('odj_admin');
        session()->regenerate(true);

        return redirect()->to('admin/login');
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $db = \Config\Database::connect();
        $q  = trim((string) $this->request->getGet('q'));

        $builder = $db->table('gtfs_stops');
        if ($q !== '') {
            $builder->groupStart()->like('stop_name', $q)->orLike('stop_id', $q)->groupEnd();
        }
        $total = $builder->countAllResults(false);

        $page   = max(1, (int) $this->request->getGet('strana'));
        $pages  = max(1, (int) ceil($total / self::PER_PAGE));
        $page   = min($page, $pages);
        $stops  = $builder->orderBy('stop_name')->orderBy('stop_id')
            ->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ->get()->getResultArray();

        return view('odjezdy/admin/index', [
            'title'     => 'Správa zastávek',
            'stops'     => $stops,
            'q'         => $q,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'maxQr'     => self::MAX_QR,
            'lastImport' => $db->table('odj_import_log')->orderBy('id', 'DESC')->get(1)->getRowArray(),
            'user'      => session()->get('odj_admin'),
        ]);
    }

    public function stop(string $stopId)
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $svc  = new DepartureService();
        $stop = $svc->findStop($stopId);
        if (! $stop) {
            throw PageNotFoundException::forPageNotFound('Zastávka nenalezena.');
        }

        return view('odjezdy/admin/stop', [
            'title' => $stop['stop_name'],
            'stop'  => $stop,
            'url'   => $svc->boardUrl($stopId),
            'week'  => $svc->weekCounts($stopId, $svc->now()),
            'user'  => session()->get('odj_admin'),
        ]);
    }

    /** Tisková stránka s QR kódy vybraných zastávek: /admin/qr?ids[]=S1&ids[]=S2 */
    public function qr()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $ids = $this->request->getGet('ids');
        $ids = is_array($ids) ? array_values(array_unique(array_map('strval', $ids))) : [];
        $ids = array_slice($ids, 0, self::MAX_QR);
        if (! $ids) {
            return redirect()->to('admin');
        }

        $svc   = new DepartureService();
        $stops = \Config\Database::connect()->table('gtfs_stops')->whereIn('stop_id', $ids)->orderBy('stop_name')->get()->getResultArray();
        foreach ($stops as &$s) {
            $s['url'] = $svc->boardUrl($s['stop_id']);
        }
        unset($s);

        return view('odjezdy/admin/qr', [
            'title' => 'QR kódy k tisku',
            'stops' => $stops,
            'user'  => session()->get('odj_admin'),
        ]);
    }
}

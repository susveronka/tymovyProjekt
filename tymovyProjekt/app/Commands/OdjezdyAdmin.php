<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class OdjezdyAdmin extends BaseCommand
{
    protected $group       = 'GTFS';
    protected $name        = 'odjezdy:admin';
    protected $description = 'Vytvoří (nebo přepíše heslo) administrátora aplikace odjezdových tabulí.';
    protected $usage       = 'odjezdy:admin <uživatel> [heslo]';
    protected $arguments   = [
        'uživatel' => 'Přihlašovací jméno.',
        'heslo'    => 'Heslo (min. 8 znaků). Když chybí, příkaz se na ně zeptá.',
    ];

    public function run(array $params)
    {
        $user = trim($params[0] ?? '');
        if ($user === '') {
            $user = trim(CLI::prompt('Uživatelské jméno'));
        }
        $pass = $params[1] ?? CLI::prompt('Heslo (min. 8 znaků)');

        if ($user === '' || mb_strlen($user) > 64) {
            CLI::error('Neplatné uživatelské jméno.');

            return EXIT_ERROR;
        }
        if (mb_strlen($pass) < 8) {
            CLI::error('Heslo musí mít alespoň 8 znaků.');

            return EXIT_ERROR;
        }

        $table = \Config\Database::connect()->table('odj_admins');
        $hash  = password_hash($pass, PASSWORD_DEFAULT);

        if ($table->where('username', $user)->countAllResults() > 0) {
            \Config\Database::connect()->table('odj_admins')->where('username', $user)->update(['password_hash' => $hash]);
            CLI::write("Heslo uživatele {$user} bylo změněno.", 'green');
        } else {
            $table->insert(['username' => $user, 'password_hash' => $hash, 'created_at' => date('Y-m-d H:i:s')]);
            CLI::write("Administrátor {$user} vytvořen.", 'green');
        }
    }
}

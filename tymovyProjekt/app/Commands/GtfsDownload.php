<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Odjezdy;

class GtfsDownload extends BaseCommand
{
    protected $group       = 'GTFS';
    protected $name        = 'gtfs:download';
    protected $description = 'Stáhne aktuální GTFS z https://data.jr.ggu.cz/ do writable/gtfs/.';
    protected $usage       = 'gtfs:download [jdf] [vlaky]';
    protected $arguments   = [
        'jdf'   => 'Autobusy a MHD (JDF_merged_GTFS.zip, cca 125 MB).',
        'vlaky' => 'Vlaky (CZPTT_GTFS.zip, cca 75 MB).',
    ];

    public function run(array $params)
    {
        @set_time_limit(0);
        /** @var Odjezdy $cfg */
        $cfg  = config('Odjezdy');
        $keys = $params ?: array_keys($cfg->downloadUrls);
        $dir  = WRITEPATH . 'gtfs';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($keys as $key) {
            if (! isset($cfg->downloadUrls[$key])) {
                CLI::error("Neznámý zdroj „{$key}“ (platné: " . implode(', ', array_keys($cfg->downloadUrls)) . ').');

                return EXIT_ERROR;
            }
            $url  = $cfg->downloadUrls[$key];
            $dest = $dir . DIRECTORY_SEPARATOR . basename($url);
            CLI::write("Stahuji {$url}");

            $in  = @fopen($url, 'rb', false, stream_context_create(['http' => ['timeout' => 600, 'user_agent' => 'odjezdy-app']]));
            $out = $in ? fopen($dest . '.part', 'wb') : false;
            if (! $in || ! $out) {
                CLI::error('Stažení se nezdařilo. Zkontrolujte připojení (a v php.ini extension=openssl). ZIP můžete stáhnout ručně a uložit do writable/gtfs/.');

                return EXIT_ERROR;
            }
            $bytes = stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            rename($dest . '.part', $dest);
            CLI::write(sprintf('  uloženo %s (%.1f MB)', $dest, $bytes / 1048576), 'green');
        }
    }
}

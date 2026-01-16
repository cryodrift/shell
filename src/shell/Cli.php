<?php

//declare(strict_types=1);

namespace cryodrift\shell;


use cryodrift\fw\cli\ParamFile;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\Main;
use cryodrift\fw\trait\CliHandler;
use cryodrift\fw\trait\CmdRunner;

class Cli implements Handler
{
    use CliHandler;
    use CmdRunner;

    public function __construct(private readonly string $browser, private readonly string $browserprofile, private readonly string $agent)
    {
    }


    public function handle(Context $ctx): Context
    {
        $ctx->response()->setStatusFinal();
        return $this->handleCli($ctx);
    }

    /**
     * @cli undupe remove duplicates from lines
     * @cli param: -file (get from pipe or filename)
     */
    protected function undupe(ParamFile $file): string
    {
        $out = '';
        if ((string)$file) {
            $parts = array_flip($this->preparedLines((string)$file));
            $parts = array_flip($parts);
            $out = trim(implode(PHP_EOL, $parts));
        }
        return $out;
    }

    /**
     * @cli sort lines
     * @cli param: -file (get from pipe or filename)
     */
    protected function sort(ParamFile $file): string
    {
        $out = '';
        if ($file->value) {
            $lines = $this->preparedLines((string)$file);
            if ($lines) {
                sort($lines);
                $out = trim(implode(PHP_EOL, $lines));
            }

        }
        return $out;
    }


    /**
     * @cli Group Lines by Tokens
     * @cli param: -file (pipe from stdin or filename)
     */
    protected function group(ParamFile $file): string
    {
        $lines = self::preparedLines((string)$file);
        $out = [];
        $tree = [];
        foreach ($lines as $line) {
            $out[$line] = self::extractSpacedParts(self::getTokens(str_replace('//', '/ /', $line)));
        }
        $head =& $tree[];
        foreach ($out as $command => $cmdparts) {
            $next =& $head;
            foreach ($cmdparts as $cmdpart) {
                $next =& $next[$cmdpart];
            }
            $next[] = $command;
        }
        return self::printTree($tree);
    }

    /**
     * @cli Replace content in a file or piped data
     * @cli example: dir | php /shell/cli replace "searchstring" "replacestring" -file
     * @cli example: php index.php /shell replace -search="searchstring" -replace="replacestring" -file="myfile.txt"
     * @cli -file (stdin or pathname)
     * @cli [-search] (searchstring)
     * @cli [-replace] (replacestring)
     */
    protected function replace(Context $ctx, ParamFile $file, string $search = '', string $replace = ''): string
    {
        if ($search && $replace) {
        } else {
            $search = $ctx->request()->getArgAfter('replace');
            $replace = $ctx->request()->getArgAfter($search, 'replace');
        }
        $data = str_replace($search, $replace, $file);
        if ($file->filename) {
            Core::fileWrite($file->filename . '.new', $data);
        }
        return $data;
    }

    /**
     * @cli Take a screenshot of the web page using Chrome
     * @cli use browser to startup the userprofile and get rid of chrome dialogs
     * @cli param: -url="" url or route
     * @cli param: [-local=true] url is local route or external url
     * @cli param: [-small=false]
     * @cli param: [-open=false] open the browser in non headless mode to set the cookie
     * @cli param: [-outfile=""] out filename without fext and path
     */
    protected function screenshot(string $url, bool $local = true, bool $small = false, bool $open = false, string $outfile = '', bool $proc = false): array
    {
        $out = [];

        if ($open) {
            $chromeCmd = [$this->browser];
//        $chromeCmd[] = '--disable-application-cache';
            $chromeCmd[] = '--user-data-dir=' . $this->browserprofile;
            $chromeCmd[] = $url;

            [$returnCode, $null] = $this->runCmd($chromeCmd, null, true);
            if ($returnCode !== 0) {
                $out['step'] = 'browser';
                $out['command'] = implode(' ', $chromeCmd);
                $out['return_code'] = $returnCode;
            } else {
                $out[] = 'launched browser';
            }
        } else {
            // Determine sizes and paths
            $sizeArg = $small ? '--window-size=800,600' : '--window-size=1920,1200';

            $tmpPath = Main::$rootdir . Config::$datadir . Config::$logdir . 'screenshot.html';
            if (!$outfile) {
                $outfile = 'sreenshot';
            }
            $imgPath = Main::$rootdir . $outfile . '.jpg';

            // Prepare target URL (either local rendered file or remote URL)
            $targetUrl = $url;
            if ($local) {
                // Render the route locally: php index.php <url> -> tmp.html
                $phpCmd = ['php', 'index.php', $url];
                [$phpCode, $html] = $this->runCmdCapture($phpCmd, null, true);
                if ($phpCode !== 0) {
                    $out['step'] = 'render';
                    $out['command'] = implode(' ', $phpCmd);
                    $out['return_code'] = $phpCode;
                    return $out;
                }
                Core::fileWrite($tmpPath, (string)$html);
                // file:/// URL for Chrome
                $targetUrl = 'file:///' . str_replace('\\', '/', $tmpPath);
            }

            // Build Chrome command as array
            $chromeCmd = [
              $this->browser,
              '--headless=new',
              '--disable-application-cache',
              $sizeArg,
              '--screenshot=' . $imgPath,
            ];
            $chromeCmd[] = '--user-data-dir=' . $this->browserprofile;
            $chromeCmd[] = $targetUrl;

            if ($proc) {
//                $cmd = $this->prepareShellWindows($chromeCmd);
                $descriptors = [
                  0 => ['pipe', 'r'],
                  1 => ['file', 'php://stdout', 'w'],
                  2 => ['file', 'php://stderr', 'w'],
                ];
//                if (Core::isUnix()) {
//                    $shell = ['/bin/sh', '-c', $cmd];
//                } else {
//                    $shell = ['cmd.exe', '/C', $cmd];
//                }
//                Core::echo(__METHOD__, $shell, $chromeCmd);
                $proc = proc_open($chromeCmd, $descriptors, $pipes);
                proc_close($proc);
            } else {
                [$returnCode, $null] = $this->runCmd($chromeCmd, null, true);
                if ($returnCode !== 0) {
                    $out['step'] = 'screenshot';
                    $out['command'] = implode(' ', $chromeCmd);
                    $out['return_code'] = $returnCode;
                } else {
                    $out[] = 'saved ' . $outfile . '.jpg';
                }
            }
        }

        return $out;
    }

    function prepareShellWindows(array $parts): string
    {
        return implode(' ', array_map(function ($p) {
            return '"' . str_replace('"', '\\"', $p) . '"';
        }, $parts));
    }

    /**
     * @cli capture html of a url
     *
     */
    protected function dumpdom(string $url): string
    {
        $html = Core::fileReadOnce($url, false, [
          'http' => [
            'method' => 'GET',
//          'header'            => "Header-Name: value\r\nAnother: value\r\n", // or implode("\r\n", $headers)."\r\n"
//          'content'           => string,  // request body for POST/PUT
            'timeout' => 1,
            'ignore_errors' => true,
            'user_agent' => $this->agent,
            'protocol_version' => 1.1,
            'follow_location' => 1,
            'max_redirects' => 10,
          ],
        ]);

        return $html;
    }

    /**
     * @cli Simple url benchmark
     */
    protected function bench(string $url, ParamFile $cookie, int $n = 10): array
    {
        $out = [];
        $out['start'] = Core::time();
        $headers = [];
        $headers[] = 'Cookie: ' . $cookie->value;
        $headers[] = 'Connection: keep-alive';

        for ($a = 0, $b = $n; $a < $n; $a++) {
            Core::echo(__METHOD__, 'beg', $a);
            Core::fileReadOnce($url, true, [
              'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'user_agent' => $this->agent,
                'timeout' => 1,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
                'follow_location' => 0,
              ],
            ]);
            Core::echo(__METHOD__, 'end', $a);
        }

        $out['end'] = Core::time();
        return $out;
    }

    private static function printTree(array $data): string
    {
        static $level = 1;
        $out = '';
        foreach ($data as $key => $value) {
            if ($key) {
                $out .= str_pad($key, strlen($key) + $level, ' ', STR_PAD_LEFT) . PHP_EOL;
            }
            if (is_array($value)) {
                $level++;
                $out .= self::printTree($value);
                $level--;
            } else {
                $out .= str_pad($value, strlen($value) + $level, ' ', STR_PAD_LEFT) . PHP_EOL;
            }
        }
        return $out;
    }

    private static function extractSpacedParts(\Generator $tokens): array
    {
        $out = [];

        $current = '';
        foreach ($tokens as $token) {
            switch (true) {
                case $token->is(' '):
                    $out[] = $current;
                    $current = '';
                    break;
                default:
                    $current .= $token->text;
            }
        }
        $out[] = $current;
        return $out;
    }

    private static function preparedLines(string $data): array
    {
        $data = str_replace("\r", '', $data);
        $parts = explode("\n", $data);
        return array_map('trim', $parts);
    }

    /**
     * @param $pathname
     * @return \Generator
     */
    private static function getTokens($pathname): iterable
    {
        if (file_exists($pathname)) {
            $file = file_get_contents($pathname);
        } else {
            $file = $pathname;
        }
        $tokens = \PhpToken::tokenize('<?php' . PHP_EOL . $file);
        foreach ($tokens as $token) {
            if (!$token->is(T_OPEN_TAG)) {
                yield $token;
            }
        }
    }

}

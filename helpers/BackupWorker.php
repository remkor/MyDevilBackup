<?php

class BackupWorker
{
    private $connection;

    private $host;
    private $port;

    private $login;
    private $passwd;

    public function __construct($host, $port, $login, $passwd)
    {
        $this->host = $host;
        $this->port = $port;

        $this->login = $login;
        $this->passwd = $passwd;

        $this->connect();
    }

    public function __destruct()
    {
        ssh2_disconnect($this->connection); 
    }

    public function backupDatabase($host, $name, $user, $passwd): string
    {
        $fileName = $name . '.' . time() . '.sql';

        $cmd = 'mysqldump';
        $cmd .= ' -h ' . $host;
        $cmd .= ' -u ' . $user;
        $cmd .= ' -p\'' . $passwd . '\'';
        $cmd .= ' ' . $name;
        $cmd .= ' > ' . $fileName;

        $this->remoteExec($cmd);

        return $fileName;
    }

    public function backupSite($name, $path)
    {
        $fileName = $name . '.' . time() . '.tgz';

        $cmd = 'tar';
        $cmd .= ' -czf ' . $fileName;
        $cmd .= ' ' . $path;

        $this->remoteExec($cmd);

        return $fileName;
    }

    public function download($fileName): bool
    {
        $cmd = 'sshpass';
        $cmd .= ' -p \'' . $this->passwd . '\'';
        $cmd .= ' scp';
        $cmd .= ' ' . $this->login . '@' . $this->host . ':' . $fileName . ' ' . __DIR__ . '/../download/';

        $this->localExec($cmd);

        $this->remoteExec('rm ' . $fileName);

        return file_exists(__DIR__ . '/../download/' . $fileName);
    }

    private function connect(): void
    {
        $this->connection = ssh2_connect($this->host, $this->port);
        ssh2_auth_password($this->connection, $this->login, $this->passwd);
    }

    private function localExec($cmd): string
    {
        return exec($cmd);
    }

    private function remoteExec($cmd): string
    {
        $stdout = ssh2_exec($this->connection, $cmd);
        $stderr = ssh2_fetch_stream($stdout, SSH2_STREAM_STDERR);

        stream_set_blocking($stdout, true);
        stream_set_blocking($stderr, true);

        stream_set_timeout($stdout, 60);

        $output = stream_get_contents($stdout);

        fclose($stdout);
        fclose($stderr);

        return $output;
    }
}

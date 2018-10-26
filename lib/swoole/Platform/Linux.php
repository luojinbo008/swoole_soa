<?php
namespace Swoole\Platform;

class Linux
{
    public function kill($pid, $signo) {
        return posix_kill($pid, $signo);
    }

    public function fork() {
        return pcntl_fork();
    }
}
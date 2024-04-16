<?php

    namespace Coco\cron\job;

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\abstract\ShellRunnerAbstract;

class ShellJob extends JobAbstract
{
    protected string $command = '';

    protected ShellRunnerAbstract $runnner;

    public function __construct(string $command, ShellRunnerAbstract $runnner)
    {
        $this->command = $command;
        $this->runnner = $runnner;

        parent::__construct();
    }

    public function getCommand(): string
    {
        return $this->command;
    }
}

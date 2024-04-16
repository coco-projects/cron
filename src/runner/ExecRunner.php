<?php

    namespace Coco\cron\runner;

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\abstract\ShellRunnerAbstract;
    use Coco\cron\job\ShellJob;

class ExecRunner extends ShellRunnerAbstract
{
    public function exec(JobAbstract|ShellJob $job): void
    {
        $command = $job->getCommand();

        $output = [];

        exec($command, $output, $return_var);

        foreach ($output as $k => $v) {
            $job->addStdout($v);
        }
    }
}

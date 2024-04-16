<?php

    namespace Coco\cron\runner;

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\abstract\RunnerAbstract;
    use Coco\cron\job\CallableJob;

class CallableRunner extends RunnerAbstract
{
    public function exec(JobAbstract|CallableJob $job): void
    {
        $output = call_user_func_array($job->getCallback(), [$job]);

        if (is_string($output) or is_int($output)) {
            $output = [$output];
        }
            
        if (is_array($output)) {
            foreach ($output as $k => $v) {
                $job->addStdout($v);
            }
        }
    }
}

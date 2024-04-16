<?php

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\job\CallableJob;
    use Coco\cron\Schedule;

    require '../vendor/autoload.php';

    $schedule = new Schedule(logger: new \Coco\cron\logger\EchoLogger());

    $job1 = new CallableJob(function(CallableJob $job) {

        $out = [];
        $i   = 0;
        while ($i < 3)
        {
            $str = '$job1-run-' . time();

            $out[] = $str;

            echo $str . PHP_EOL;

            $i++;
            sleep(1);
        }

//        throw new \Exception('text Exception');

        return $out;
    });

    $job1->setId(1);
    $job1->cron(' * * * * *');

    $job1->preventOverlapping();
    $job1->setStdoutFile('./log.txt');

    $job1->onError(function(CallableJob $job, \Exception $exception) {
        $job->addStderr($exception->getMessage());
    });

    $job1->after(function(JobAbstract $job) {
        $job->getLogger()->debug($job->getStdoutAsString());
    });

    $schedule->addJob($job1);

    $schedule->onError(function(CallableJob $job, \Exception $exception) {
        echo $exception->getMessage();
    });

    print_r($schedule->getScheduleList());

    $schedule->listen();


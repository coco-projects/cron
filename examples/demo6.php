<?php

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\job\CallableJob;
    use Coco\cron\Schedule;

    require '../vendor/autoload.php';

    $schedule = new Schedule(logger: new \Coco\cron\logger\EchoLogger());

    $ls = \Coco\commandBuilder\command\Ls::getIns();

    //ls / -h -al
    $ls->target('/')->readable()->addFlag('al');

    $runner = new \Coco\cron\runner\ExecRunner();

    $job1 = new \Coco\cron\job\ShellJob($ls, $runner);

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
        $job->getLogger()->critical($exception->getMessage());
    });

    print_r($schedule->getScheduleList());

    $schedule->listen();


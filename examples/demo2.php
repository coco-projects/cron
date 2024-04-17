<?php

    use Coco\cron\job\CallableJob;
    use Coco\cron\Schedule;

    require '../vendor/autoload.php';

    $schedule = new Schedule();

    $job1 = new CallableJob(function() {
        echo 1111;
    });

    $job1->setId(1);
    $job1->setDescription('test111');
    $job1->cron('* * * * *');

    echo $job1->translateNextRunTime();
    echo PHP_EOL;

    echo $job1->getPreviousRunTime();
    echo PHP_EOL;

    echo $job1->getNextRunTime();
    echo PHP_EOL;

    var_export($job1->isDue());
    echo PHP_EOL;

    var_export($job1->getMultiRunTime());
    echo PHP_EOL;

    var_export($job1->getSchedulePlain());

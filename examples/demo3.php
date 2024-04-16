<?php

    use Coco\cron\job\CallableJob;
    use Coco\cron\Schedule;

    require '../vendor/autoload.php';

    $schedule = new Schedule();

    $schedule->before(function() {
        echo '$schedule-before-1' . PHP_EOL;
    });
    $schedule->before(function() {
        echo '$schedule-before-2' . PHP_EOL;
    });

    $schedule->after(function() {
        echo '$schedule-after-1' . PHP_EOL;
    });
    $schedule->after(function() {
        echo '$schedule-after-2' . PHP_EOL;
    });

    $job1 = new CallableJob(function() {
        echo 'run-------------' . PHP_EOL;
    });

    $job1->before(function() {
        echo '$job1-before-1' . PHP_EOL;
    });
    $job1->before(function() {
        echo '$job1-before-2' . PHP_EOL;
    });

    $job1->after(function() {
        echo '$job1-after-1' . PHP_EOL;
    });
    $job1->after(function() {
        echo '$job1-after-2' . PHP_EOL;
    });

    $job1->setDescription('test111');


    $schedule->addJob($job1);

    print_r($schedule->getScheduleList());

    $schedule->runJobById(1);

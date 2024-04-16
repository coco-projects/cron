<?php

    use Coco\cron\job\CallableJob;

    require '../vendor/autoload.php';

    $job1 = new CallableJob(function() {
        echo 1111;
    });

    $job1->setId(1);

    $job1->run();


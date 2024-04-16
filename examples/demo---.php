<?php

    use Coco\cron\job\CallableJob;
    use Coco\cron\Schedule;

    require '../vendor/autoload.php';

    $schedule = new Schedule();

    /*-----------------------------------*/

    $job1 = new CallableJob(function() {
        echo '$job1-run-------------' . PHP_EOL;
        throw new \Exception('$job1-Exception');
    });
    $job1->setId(1);

    /*-----------------------------------*/

    $job2 = new CallableJob(function() {
        echo '$job2-run-------------' . PHP_EOL;
        throw new \Exception('$job2-Exception');
    });
    /*-----------------------------------*/

    $job1->setDescription('test111');

//    $job1->hourly();
//    $job1->daily();
//    $job1->dailyAt('10:05');
//    $job1->twiceDaily();
//    $job1->weekdays();
//    $job1->mondays();
//    $job1->tuesdays();
//    $job1->wednesdays();
//    $job1->thursdays();
//    $job1->fridays();
//    $job1->saturdays();
//    $job1->sundays();
//    $job1->weekly();
//    $job1->monthly();
//    $job1->quarterly();
//    $job1->yearly();
//
//    $job1->hourlyAt('25');

    //
    $job1->hour('35');

    //【35 * * * * 】  【每整点】
    //每小时的第35分钟执行任务
//    $job1->minute('35');

    //【* * * * 3,5 】  【每分钟 一周2天】
    //每周的星期三和星期五的每一刻执行
//    $job1->days('3,5');

    //【* * 3,4,5 * * 】  【每分钟 每月3天】
    //每个月的第3、4、5天的每小时每分钟都执行
//    $job1->dayOfMonth('3,4,5');

    //【* * * 1,3 * 】  【每分钟 每年2个月】
    //一月和三月的每一天的每小时每分钟都执行
//    $job1->month('1,3');

    //【* * * * 7 】  【每分钟 在周日】
//    $job1->dayOfWeek('7');

    //【50 3 * * 2 】  【每周周二 在3:50】
//    $job1->weeklyOn(2, '3:50');

    //【*/1 * * * * 】  【每1分钟】
//    $job1->everyMinute();

    //【0 */1 * * * 】  【每1小时1次】
//    $job1->everyhour();

    //【*/5 * * * * 】  【每5分钟】
//    $job1->minuteScheduler(5);

    //【0 */6 * * * 】  【每6小时1次】
//    $job1->hourScheduler(6);

    
//    $job1->dayScheduler(3);
//    $job1->monthScheduler(4);

    $plain = $job1->getSchedulePlain();

    print_r("【{$plain['expression']} 】  【{$plain['readable'] }】");

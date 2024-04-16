<?php

    namespace Coco\cron\abstract;

abstract class RunnerAbstract
{
    abstract public function exec(JobAbstract $job): void;
}

<?php

    namespace Coco\cron\job;

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\runner\CallableRunner;

class CallableJob extends JobAbstract
{
    /**
     * @var callable $callback
     */
    protected $callback;

    protected CallableRunner $runnner;

    public function __construct($callback)
    {
        $this->callback = $callback;
        $this->runnner  = new CallableRunner();
        parent::__construct();
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }
}

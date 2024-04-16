<?php

    declare(strict_types = 1);

    namespace Coco\cron;

    use Coco\cron\abstract\JobAbstract;
    use Coco\cron\exception\WrongTaskNumberException;
    use Coco\cron\logger\NullLogger;
    use Psr\Log\LoggerInterface;

class Schedule
{
    protected array $jobs = [];

    protected array $beforeCallbacks = [];

    protected array $afterCallbacks = [];

    protected array $errorCallbacks = [];

    protected static int $id = 0;

    protected ?\DateTimeZone $timezone;

    protected LoggerInterface $logger;

    public function __construct(string $timezone = 'Asia/Shanghai', LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }

        $this->logger = $logger;

        if ($this->isWindows()) {
            throw new \Exception("This library isn't compatible with Windows systems—it's tailored for UNIX-like platforms due to its dependency on crontab.");
        }

        $this->setTimezone(new \DateTimeZone($timezone));
    }

    public function setTimezone(?\DateTimeZone $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function addJob(JobAbstract $job): static
    {
        $job->setTimezone($this->timezone);

        $job->setSchedule($this);

        $job->setLogger($this->logger);

        $job->setId($id = static::makeId());

        $this->jobs[$id] = $job;

        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function before(callable $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function onError(callable $callback): static
    {
        $this->errorCallbacks[] = $callback;

        return $this;
    }

    public function getErrorCallbacks(): array
    {
        return $this->errorCallbacks;
    }

    /**
     * @return JobAbstract[]
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * @return JobAbstract[]
     */
    public function getDueJobs(): array
    {
        return \array_filter($this->jobs, static function (JobAbstract $job) {
            return $job->isDue();
        });
    }

    public function listen(): void
    {
        $this->logger->debug('start listen');

        $jobs = $this->getDueJobs();

        foreach ($this->beforeCallbacks as $callback) {
            call_user_func_array($callback, [$this]);
        }

        foreach ($jobs as $k => $job) {
            $this->execJob($job);
        }

        foreach ($this->afterCallbacks as $callback) {
            call_user_func_array($callback, [$this]);
        }
    }

    public function getScheduleList(): array
    {
        $result = [];
        foreach ($this->getJobs() as $k => $job) {
            $result[] = $job->getSchedulePlain();
        }

        return $result;
    }

    public function runJobById(int $id): void
    {
        if (isset($this->jobs[$id])) {
            $this->execJob($this->jobs[$id]);
        } else {
            throw new WrongTaskNumberException('无效的任务id');
        }
    }

    protected function execJob(JobAbstract $job): void
    {
        $job->run();
    }

    protected function isWindows(): bool
    {
        return '\\' === DIRECTORY_SEPARATOR;
    }

    protected static function makeId(): int
    {
        static::$id++;

        return static::$id;
    }
}

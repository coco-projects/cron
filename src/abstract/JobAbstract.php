<?php

    namespace Coco\cron\abstract;

    use Coco\cron\exception\CrunzException;
    use Coco\cron\logger\NullLogger;
    use Crunz\Task\TaskException;
    use Coco\cron\Schedule;
    use Cron\CronExpression;
    use Lorisleiva\CronTranslator\CronTranslator;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\Lock\LockFactory;
    use Symfony\Component\Lock\LockInterface;
    use Symfony\Component\Lock\Store\FlockStore;

abstract class JobAbstract
{
    protected int $id;

    protected Schedule $schedule;

    protected string $description = '';

    protected string $lang = 'zh';

    protected array $whenCallback = [];

    protected array $skipCallback = [];

    protected array $beforeCallbacks = [];

    protected array $afterCallbacks = [];

    protected array $errorCallbacks = [];

    protected array $stdout = [];

    protected string $stdoutFile = '/dev/null';

    protected array $stderr = [];

    protected \DateTime|string|null $from = null;

    protected \DateTime|string|null $to = null;

    protected ?\DateTimeZone $timezone = null;

    protected LockInterface $lock;

    protected LoggerInterface $logger;

    protected bool $preventOverlapping = false;

    protected array $expression = [
        '*',
        '*',
        '*',
        '*',
        '*',
    ];

    protected array $fieldsPosition = [
        'minute' => 1,
        'hour'   => 2,
        'day'    => 3,
        'month'  => 4,
        'week'   => 5,
    ];

    protected ?LockFactory $lockFactory;

    /*------------------------------------------------------------*/

    public function __construct()
    {
        $this->setTimezone(new \DateTimeZone('Asia/Shanghai'));
        $this->logger = new NullLogger();

        $this->after(function (JobAbstract $job) {
            file_put_contents($this->stdoutFile, $this->getStdoutAsString());
        });

        $this->onError(function (JobAbstract $job, \Exception $exception) {
            $this->logger->critical($this->getStderrAsString());
        });
    }

    /*------------------------------------------------------------*/

    public function run(): void
    {
        $this->logger->debug("run:[{$this->id}] start");

        foreach ($this->beforeCallbacks as $callback) {
            call_user_func_array($callback, [$this]);
        }

        if ($this->preventOverlapping) {
            $this->lock();
        }

        try {
            $this->runnner->exec($this);
        } catch (\Exception $exception) {
            $this->addStderr($exception->getMessage());

            foreach ($this->errorCallbacks as $callback) {
                call_user_func_array($callback, [
                    $this,
                    $exception,
                ]);
            }

            foreach ($this->schedule->getErrorCallbacks() as $callback) {
                call_user_func_array($callback, [
                    $this,
                    $exception,
                ]);
            }
        }

        foreach ($this->afterCallbacks as $callback) {
            call_user_func_array($callback, [$this]);
        }

        $this->logger->debug("run:[{$this->id}] done");
    }


    /*------------------------------------------------------------*/

    public function addStderr(string|int $value): static
    {
        $this->stderr[] = $value;

        return $this;
    }

    public function addStdout(string|int $value): static
    {
        $this->stdout[] = $value;

        return $this;
    }

    public function getStderr(): array
    {
        return $this->stderr;
    }

    public function getStdout(): array
    {
        return $this->stdout;
    }

    public function getStdoutAsString(): string
    {
        return implode(PHP_EOL, $this->stdout);
    }

    public function getStderrAsString(): string
    {
        return implode(PHP_EOL, $this->stderr);
    }

    public function setSchedule(Schedule $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule;
    }

    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function setTimezone(?\DateTimeZone $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setStdoutFile(string $stdoutFile): static
    {
        $this->stdoutFile = $stdoutFile;

        return $this;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getFrom(): \DateTime|string|null
    {
        return $this->from;
    }

    public function getTo(): \DateTime|string|null
    {
        return $this->to;
    }

    public function when(callable $callback): static
    {
        $this->whenCallback[] = $callback;

        return $this;
    }

    public function skip(callable $callback): static
    {
        $this->skipCallback[] = $callback;

        return $this;
    }

    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function before(callable $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function onError(callable $callback): static
    {
        $this->errorCallbacks[] = $callback;

        return $this;
    }

    public function isFiltersPass(): bool
    {
        foreach ($this->whenCallback as $callback) {
            if (!call_user_func_array($callback, [])) {
                return false;
            }
        }

        foreach ($this->skipCallback as $callback) {
            if (call_user_func_array($callback, [])) {
                return false;
            }
        }

        return true;
    }

    public function isDue(): bool
    {
        return $this->isExpressionPasses() && $this->isFiltersPass();
    }

    public function getExpression(): string
    {
        return implode(' ', $this->expression);
    }

    protected function isExpressionPasses(): bool
    {
        $now = $this->nowDatetime();

        return (new CronExpression($this->getExpression()))->isDue($now->format('Y-m-d H:i:s'));
    }

    protected function nowDatetime(): \DateTimeInterface
    {
        return (new \DateTimeImmutable())->setTimezone($this->timezone);
    }

    /*------------------------------------------------------------*/
    public function isPreventOverlapping(): bool
    {
        return $this->preventOverlapping;
    }

    public function preventOverlapping(): static
    {
        $this->preventOverlapping = true;

        $this->lockFactory = new LockFactory($this->createDefaultLockStore());

        $this->lock = $this->lockFactory->createLock($this->lockKey(), 30);

        $this->skip(function () {
            $this->lock->acquire();

            return !$this->lock->isAcquired();
        });

        $releaseCallback = function (): void {
            $this->releaseLock();
        };

            $this->after($releaseCallback);
            $this->onError($releaseCallback);

            return $this;
    }

    public function refreshLock(): void
    {
        if (!$this->preventOverlapping) {
            return;
        }

        $remainingLifetime = $this->lock->getRemainingLifetime();

        if (null === $remainingLifetime) {
            return;
        }

        $lockRefreshNeeded = $remainingLifetime < 15;
        if ($lockRefreshNeeded) {
            $this->lock->refresh();
        }
    }

    protected function releaseLock(): void
    {
        if (!$this->preventOverlapping) {
            return;
        }

        $this->lock->release();
    }

    protected function lock(): void
    {
        if (!$this->preventOverlapping) {
            return;
        }

        $this->lock->acquire();
    }

    protected function lockKey(): string
    {
        return 'coco-corn-' . $this->id;
    }

    protected function createDefaultLockStore(): FlockStore
    {
        $lockPath = \sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.coco-cron';
        is_dir($lockPath) or mkdir($lockPath, 1777, true);

        return new FlockStore($lockPath);
    }

    /*------------------------------------------------------------*/

    public function getNextRunTime(): string
    {
        $cron = new CronExpression($this->getExpression());

        return $cron->getNextRunDate()->format('Y-m-d H:i:s');
    }

    public function getPreviousRunTime(): string
    {
        $cron = new CronExpression($this->getExpression());

        return $cron->getPreviousRunDate()->format('Y-m-d H:i:s');
    }

    public function translateNextRunTime(): string
    {
        return CronTranslator::translate($this->getExpression(), $this->lang, true);
    }

    public function getSchedulePlain(): array
    {
        return [
            "id"         => $this->id,
            "expression" => $this->getExpression(),

            "readable"               => $this->translateNextRunTime(),
            "is_prevent_overlapping" => $this->isPreventOverlapping(),

            "last_run_time"       => $this->getPreviousRunTime(),
            "time_since_last_run" => static::secondsToTime(time() - strtotime($this->getPreviousRunTime())) . '之前',

            "next_run_time"           => $this->getNextRunTime(),
            "next_run_remaining_time" => static::secondsToTime(strtotime($this->getNextRunTime()) - time()),

            "timezone"    => $this->timezone->getName(),
            "description" => $this->description,
        ];
    }

    /*------------------------------------------------------------*/

    protected static function secondsToTime(int $seconds): string
    {
        $days    = floor($seconds / (3600 * 24));
        $hours   = floor(($seconds % (3600 * 24)) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $seconds = $seconds % 60;

        $timeString = [];

        if ($days > 0) {
            $timeString[] = $days . '天';
        }

        if ($hours > 0) {
            $timeString[] = $hours . '小时';
        }

        if ($minutes > 0) {
            $timeString[] = $minutes . '分';
        }

        if ($seconds > 0) {
            $timeString[] = $seconds . '秒';
        }

        return implode('', $timeString);
    }

    /*------------------------------------------------------------*/

    public function cron(string $expression): static
    {
        $parts = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) !== 5) {
            throw new TaskException("Expression '{$expression}' has more than five parts and this is not allowed.");
        }

        $this->expression = $parts;

        return $this;
    }

    protected function spliceIntoPosition(int $position, string $value): static
    {
        $this->expression[$position - 1] = $value;

        return $this;
    }

    /*------------------------------------------------------------*/

    protected function validateMinuteField(int $value): static
    {
        if ($value < 0 or $value > 59) {
            throw new CrunzException("Minute must between '0'-'59'.");
        }

        return $this;
    }

    protected function validateHourField(int $value): static
    {
        if ($value < 0 or $value > 23) {
            throw new CrunzException("Hour must between '0'-'23'.");
        }

        return $this;
    }

    protected function validateDayField(int $value): static
    {
        if ($value < 1 or $value > 31) {
            throw new CrunzException("Day must between '1'-'31'.");
        }

        return $this;
    }

    protected function validateMonthField(int $value): static
    {
        if ($value < 1 or $value > 12) {
            throw new CrunzException("Month must between '1'-'12'.");
        }

        return $this;
    }

    protected function validateWeekField(int $value): static
    {
        if ($value < 0 or $value > 6) {
            throw new CrunzException("Week must between '0'-'6'.");
        }

        return $this;
    }
}

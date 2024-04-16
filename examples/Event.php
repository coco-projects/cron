<?php

    declare(strict_types = 1);

    namespace Coco\cron;

    use Closure;
    use Cron\CronExpression;
    use Crunz\Application\Service\ClosureSerializerInterface;
    use Crunz\Clock\Clock;
    use Crunz\Clock\ClockInterface;
    use Crunz\Exception\CrunzException;
    use Crunz\Exception\NotImplementedException;
    use Crunz\Infrastructure\Laravel\LaravelClosureSerializer;
    use Crunz\Invoker;
    use Crunz\Logger\Logger;
    use Crunz\Path\Path;
    use Crunz\Pinger\PingableInterface;
    use Crunz\Pinger\PingableTrait;
    use Crunz\Process\Process;
    use Crunz\Task\TaskException;
    use Symfony\Component\Lock\Exception\InvalidArgumentException;
    use Symfony\Component\Lock\Factory;
    use Symfony\Component\Lock\Lock;
    use Symfony\Component\Lock\LockFactory;
    use Symfony\Component\Lock\PersistingStoreInterface;
    use Symfony\Component\Lock\Store\FlockStore;

    class Event implements PingableInterface
    {
        use PingableTrait;

        /**
         * The location that output should be sent to.
         *
         * @var string
         */
        public $output = '/dev/null';

        /**
         * Indicates whether output should be appended.
         *
         * @var bool
         */
        public $shouldAppendOutput = false;

        /**
         * The human readable description of the event.
         *
         * @var string|null
         */
        public $description;

        /**
         * Event generated output.
         *
         * @var string|null
         */
        public $outputStream;

        /**
         * Event personal logger instance.
         *
         * @var Logger
         */
        public $logger;

        /** @var string|\Closure */
        protected $command;

        /**
         * Process that runs the event.
         *
         * @var Process
         */
        protected $process;

        /**
         * The cron expression representing the event's frequency.
         *
         * @var string
         */

        /**
         * The timezone the date should be evaluated on.
         *
         * @var \DateTimeZone|string
         */
        protected $timezone;

        /**
         * Datetime or time since the task is evaluated and possibly executed only for display purposes.
         */
        protected \DateTime|string|null $from = null;

        /**
         * Datetime or time until the task is evaluated and possibly executed only for display purposes.
         */
        protected \DateTime|string|null $to = null;

        /**
         * The user the command should run as.
         *
         * @var string
         */
        protected $user;

        /**
         * Current working directory.
         *
         * @var string
         */
        protected $cwd;

        /**
         * Position of cron fields.
         *
         * @var array<string,int>
         */

        /**
         * Indicates if the command should not overlap itself.
         */
        private bool $preventOverlapping = false;
        /** @var ClockInterface */
        private static                             $clock;
        private static ?ClosureSerializerInterface $closureSerializer = null;

        /**
         * The symfony lock factory that is used to acquire locks. If the value is null, but preventOverlapping = true
         * crunz falls back to filesystem locks.
         *
         * @var LockFactory|null
         */
        private $lockFactory;
        /** @var string[] */
        private array $wholeOutput = [];
        /** @var Lock */
        private $lock;
        /** @var \Closure[] */
        private array $errorCallbacks = [];

        /**
         * Create a new event instance.
         *
         * @param string|\Closure $command
         * @param string|int      $id
         */
        public function __construct(protected $id, $command)
        {
            $this->command = $command;
            $this->output  = $this->getDefaultOutput();
        }


        /**
         * Determine if the event's output is sent to null.
         *
         * @return bool
         */
        public function nullOutput()
        {
            return 'NUL' === $this->output || '/dev/null' === $this->output;
        }


        /** @return string */
        public function wholeOutput()
        {
            return \implode('', $this->wholeOutput);
        }

        /**
         * Start the event execution.
         *
         * @return int
         */
        public function start()
        {
            $command = $this->buildCommand();
            $process = Process::fromStringCommand($command);

            $this->setProcess($process);
            $this->getProcess()->start(function($type, $content): void {
                $this->wholeOutput[] = $content;
            });

            if ($this->preventOverlapping)
            {
                $this->lock();
            }

            /** @var int $pid */
            $pid = $this->getProcess()->getPid();

            return $pid;
        }


        /**
         * Get the default output depending on the OS.
         *
         * @return string
         */
        protected function getDefaultOutput()
        {
            return (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
        }


    }

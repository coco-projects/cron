<?php

    use Coco\cron\exception\CrunzException;
    use Crunz\Task\TaskException;

    class Event implements PingableInterface
    {


        public function everyMinute(): static
        {
            $this->minuteField(1);
        }

        public function everyhour(int $minute = 0): static
        {
        }

        public function everyDay(int $time = 12, int $minute = 0): static
        {
        }

        public function everyWeek(int $day = 1, int $time = 12, int $minute = 0): static
        {
        }

        public function everyMonth(int $day = 1, int $time = 12, int $minute = 0): static
        {
        }

        
        public function hourly(): static
        {
            return $this->hourlyAt(0);
        }

        public function hourlyAt(int $minute): static
        {
            if ($minute < 0)
            {
                throw new CrunzException("Minute cannot be lower than '0'.");
            }

            if ($minute > 59)
            {
                throw new CrunzException("Minute cannot be greater than '59'.");
            }

            return $this->cron("{$minute} * * * *");
        }

        public function daily(): static
        {
            return $this->cron('0 0 * * *');
        }

        public function dailyAt(string $time): static
        {
            $segments      = \explode(':', $time);
            $firstSegment  = (int)$segments[0];
            $secondSegment = \count($segments) > 1 ? (int)$segments[1] : '0';

            $this->spliceIntoPosition(2, (string)$firstSegment);
            $this->spliceIntoPosition(1, (string)$secondSegment);

            return $this;
        }

        public function twiceDaily($first = 1, $second = 13): static
        {
            $hours = $first . ',' . $second;

            $this->spliceIntoPosition(1, '0');
            $this->spliceIntoPosition(2, $hours);

            return $this;
        }

        public function weekdays(): static
        {
            return $this->spliceIntoPosition(5, '1-5');
        }

        public function mondays(): static
        {
            return $this->days(1);
        }

        public function tuesdays(): static
        {
            return $this->days(2);
        }

        public function wednesdays(): static
        {
            return $this->days(3);
        }

        public function thursdays(): static
        {
            return $this->days(4);
        }

        public function fridays(): static
        {
            return $this->days(5);
        }

        public function saturdays(): static
        {
            return $this->days(6);
        }

        public function sundays(): static
        {
            return $this->days(0);
        }

        public function weekly(): static
        {
            return $this->cron('0 0 * * 0');
        }

        public function weeklyOn($day, $time = '0:0'): static
        {
            $this->dailyAt($time);

            return $this->spliceIntoPosition(5, (string)$day);
        }

        public function monthly(): static
        {
            return $this->cron('0 0 1 * *');
        }

        public function quarterly(): static
        {
            return $this->cron('0 0 1 */3 *');
        }

        public function yearly(): static
        {
            return $this->cron('0 0 1 1 *');
        }

        /*------------------------------------------------------------*/

        public function minute(string $value): static
        {
            return $this->spliceIntoPosition(1, $value);
        }

        public function hour(string $value): static
        {

            return $this->spliceIntoPosition(2, $value);
        }

        public function dayOfMonth(string $value): static
        {
            return $this->spliceIntoPosition(3, $value);
        }

        public function month(string $value): static
        {
            return $this->spliceIntoPosition(4, $value);
        }

        public function days(string $value): static
        {
            return $this->spliceIntoPosition(5, $value);
        }

        public function dayOfWeek(string $value): static
        {
            return $this->spliceIntoPosition(5, $value);
        }

        /*------------------------------------------------------------*/

        /*------------------------------------------------------------*/

        protected function applyMask(string $unit): static
        {
            $mask = [
                '0',
                '0',
                '1',
                '1',
                '*',
                '*',
            ];

            $fpos = $this->fieldsPosition[$unit] - 1;

            \array_splice($this->expression, 0, $fpos, \array_slice($mask, 0, $fpos));

            return $this;
        }

        public function on(string $date): static
        {
            $parsedDate = \date_parse($date);

            $segments = \array_intersect_key($parsedDate, $this->fieldsPosition);

            if ($parsedDate['year'])
            {
                $this->skip(static function() use ($parsedDate) {
                    return (int)\date('Y') !== $parsedDate['year'];
                });
            }

            foreach ($segments as $key => $value)
            {
                if (false !== $value)
                {
                    $this->spliceIntoPosition($this->fieldsPosition[$key], (string)$value);
                }
            }

            return $this;
        }

        public function at(string $time): static
        {
            return $this->dailyAt($time);
        }

        public function between(string $from, string $to): static
        {
            return $this->from($from)->to($to);
        }

        public function from(string $datetime): static
        {
            $this->from = $datetime;

            return $this->skip(function() use ($datetime) {
                return $this->notYet($datetime);
            });
        }

        public function to(string $datetime): static
        {
            $this->to = $datetime;

            return $this->skip(function() use ($datetime) {
                return $this->past($datetime);
            });
        }

        protected function notYet(string $datetime): bool
        {
            $now            = $this->nowDatetime();
            $testedDateTime = new \DateTimeImmutable($datetime, $this->timezone);

            return $now < $testedDateTime;
        }

        protected function past(string $datetime): bool
        {
            $now            = $this->nowDatetime();
            $testedDateTime = new \DateTimeImmutable($datetime, $this->timezone);

            return $now > $testedDateTime;
        }

    }

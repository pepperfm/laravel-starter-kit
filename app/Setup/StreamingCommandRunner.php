<?php

declare(strict_types=1);

namespace App\Setup;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class StreamingCommandRunner
{
    public function __construct(private OutputInterface $output)
    {
    }

    /**
     * Stream complete lines so that secrets split across process chunks are still redacted.
     *
     * @param array<int, string> $command
     */
    public function run(array $command, int $timeout = 600): bool
    {
        $secrets = $this->secrets();
        $pending = ['out' => '', 'err' => ''];

        $flush = function (string $stream, bool $finished = false) use (&$pending, $secrets): void {
            while (preg_match('/^(.*?)(\r\n|\r|\n)/s', $pending[$stream], $match) === 1) {
                $this->output->write($this->redact($match[0], $secrets), false, OutputInterface::OUTPUT_RAW);
                $pending[$stream] = substr($pending[$stream], strlen($match[0]));
            }

            if ($finished && $pending[$stream] !== '') {
                $this->output->write($this->redact($pending[$stream], $secrets), false, OutputInterface::OUTPUT_RAW);
                $pending[$stream] = '';
            }
        };

        try {
            $result = Process::path(base_path())->timeout($timeout)->run(
                $command,
                function (string $type, string $output) use (&$pending, $flush): void {
                    $stream = $type === 'err' ? 'err' : 'out';
                    $pending[$stream] .= $output;
                    $flush($stream);
                },
            );

            $flush('out', true);
            $flush('err', true);

            return $result->successful();
        } catch (\Throwable $exception) {
            $flush('out', true);
            $flush('err', true);
            $this->output->writeln($this->redact($exception->getMessage(), $secrets), OutputInterface::OUTPUT_RAW);

            return false;
        }
    }

    /**
     * Never echo credentials from the project's environment file.
     *
     * @return array<int, string>
     */
    private function secrets(): array
    {
        $path = base_path('.env');

        if (!is_file($path)) {
            return [];
        }

        $secrets = [];
        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            if (preg_match('/^\s*(APP_KEY|DB_PASSWORD|DB_URL|REDIS_PASSWORD|REDIS_URL|MAIL_PASSWORD|AWS_SECRET_ACCESS_KEY|TELEGRAM_BOT_TOKEN)\s*=(.*)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $value = trim(trim($match[2]), "\"' \r\t");
            if (strlen($value) >= 4 && strtolower($value) !== 'null') {
                $secrets[] = $value;
            }
        }

        return array_values(array_unique($secrets));
    }

    /**
     * @param array<int, string> $secrets
     */
    private function redact(string $output, array $secrets): string
    {
        return str_replace($secrets, '[REDACTED]', $output);
    }
}

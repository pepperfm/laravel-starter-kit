<?php

declare(strict_types=1);

use App\Setup\StreamingCommandRunner;
use Symfony\Component\Console\Output\BufferedOutput;

it('forwards stdout and stderr from a child process', function (): void {
    $output = new BufferedOutput();
    $runner = new StreamingCommandRunner($output);

    $result = $runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "stderr\n"); echo "stdout\n";']);

    expect($result)->toBeTrue()
        ->and($output->fetch())->toContain('stdout', 'stderr');
});

it('returns failure for a nonzero process exit and preserves error output', function (): void {
    $output = new BufferedOutput();
    $runner = new StreamingCommandRunner($output);

    $result = $runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "deliberate failure\n"); exit(23);']);

    expect($result)->toBeFalse()
        ->and($output->fetch())->toContain('deliberate failure');
});

it('redacts credentials before writing a process output line', function (): void {
    $runner = new StreamingCommandRunner(new BufferedOutput());
    $redact = new ReflectionMethod($runner, 'redact');

    expect($redact->invoke($runner, 'password=test-password', ['test-password']))
        ->toBe('password=[REDACTED]');
});

<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Benchmark extends Command
{
    protected function configure(): void
    {
        $this->setName('benchmark')
            ->setDescription('Benchmarks selected parsers against a passed in file')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addOption('iterations', 'i', InputOption::VALUE_REQUIRED, 'Number of parser runs to perform per parser', 1)
            ->setHelp('Runs the selected parsers against a list of useragents (provided in the passed in "file" argument). By default performs just one iteration per parser but this can be configured with the "--iterations" option.  Reports the time taken and memory use of each parser.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        /** @var bool|string|null $iterations */
        $iterations = $input->getOption('iterations');
        $iterations = (int) $iterations;

        /** @var \UserAgentParserComparison\Command\Helper\Parsers $parserHelper */
        $parserHelper = $this->getHelper('parsers');

        $parsers = $parserHelper->getParsers($input, $output);

        $table = new Table($output);
        $table->setHeaders(['Parser', 'Average Init Time', 'Average Parse Time', 'Average Extra Time', 'Average Memory Used']);
        $rows = [];

        foreach ($parsers as $parserName => $parser) {
            $initTime  = 0;
            $parseTime = 0;
            $totalTime = 0;
            $memory    = 0;

            $output->writeln('Running against the ' . $parserName . ' parser... ');

            $progress = new ProgressBar($output, $iterations);
            $progress->start();

            for ($i = 0; $i < $iterations; ++$i) {
                $start  = microtime(true);
                $result = $parser['parse']($file, true);
                $end    = microtime(true) - $start;

                $initTime  += $result['init_time'];
                $parseTime += $result['parse_time'];
                $totalTime += $end;
                $memory    += $result['memory_used'];

                $progress->advance();
            }

            $progress->finish();
            $output->writeln('');

            $rows[] = [
                $parserName,
                round($initTime / $iterations, 3) . 's',
                round($parseTime / $iterations, 3) . 's',
                round(($totalTime - $initTime - $parseTime) / $iterations, 3) . 's',
                $this->formatBytes($memory / $iterations),
            ];
        }

        $table->setRows($rows);
        $table->render();

        return 0;
    }

    private function formatBytes(float $bytes, int $precision = 2): string
    {
        $base     = log($bytes, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[(int) floor($base)];
    }
}

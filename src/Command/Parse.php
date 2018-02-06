<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Parse extends Command
{
    private $runDir = __DIR__ . '/../../data/test-runs';

    protected function configure(): void
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser(s)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addOption('normalize', null, InputOption::VALUE_NONE, 'Whether to normalize the output')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Name of the run, for storing results')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Outputs CSV without showing CLI table')
            ->addOption('csv-file', null, InputOption::VALUE_OPTIONAL, 'File name to output CSV data to')
            ->addOption('no-output', null, InputOption::VALUE_NONE, 'Disables output after parsing, useful when chaining commands')
            ->setHelp('Parses the useragent strings (one per line) from the passed in file and outputs the parsed properties.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $file      = $input->getArgument('file');
        $normalize = $input->getOption('normalize');
        $csv       = $input->getOption('csv');
        $name      = $input->getOption('name');
        $noOutput  = $input->getOption('no-output');
        $csvFile   = $input->getOption('csv-file');

        if ($csvFile) {
            $noOutput = true;
            $csv      = true;
        }

        $parserHelper    = $this->getHelper('parsers');
        $normalizeHelper = $this->getHelper('normalize');
        $questionHelper  = $this->getHelper('question');

        $table = new Table($output);
        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => '7']), 'Parse Time'],
            ['browser_name', 'browser_version', 'platform_name', 'platform_version', 'device_name', 'device_brand', 'device_type', 'is_mobile'],
        ]);

        if ($name) {
            mkdir($this->runDir . '/' . $name);
            mkdir($this->runDir . '/' . $name . '/results');
        }

        $output->writeln('<comment>Preparing to parse ' . $file . '</comment>');

        $parsers = $parserHelper->getParsers($input, $output);

        foreach ($parsers as $parserName => $parser) {
            $output->write("\t" . 'Testing against the ' . $parserName . ' parser... ');
            $result = $parser['parse']($file);

            if (empty($result)) {
                $output->writeln('<error>The ' . $parserName . ' parser did not return any data, there may have been an error</error>');

                continue;
            }

            if (isset($result['version'])) {
                $parsers[$parserName]['metadata']['version'] = $result['version'];
            }

            if ($name) {
                if (!file_exists($this->runDir . '/' . $name . '/results/' . $parserName)) {
                    mkdir($this->runDir . '/' . $name . '/results/' . $parserName);
                }

                file_put_contents(
                    $this->runDir . '/' . $name . '/results/' . $parserName . '/' . basename($file) . '.json',
                    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }

            $rows = [];
            foreach ($result['results'] as $parsed) {
                if ($normalize) {
                    $parsed['parsed'] = $normalizeHelper->normalize($parsed['parsed'], $parser['metadata']['data_source']);
                }

                $rows[] = [new TableCell('<fg=yellow>' . $parsed['useragent'] . '</>', ['colspan' => '7']), round($parsed['time'], 5) . 's'];
                $rows[] = [
                    $parsed['parsed']['browser']['name'],
                    $parsed['parsed']['browser']['version'],
                    $parsed['parsed']['platform']['name'],
                    $parsed['parsed']['platform']['version'],
                    $parsed['parsed']['device']['name'],
                    $parsed['parsed']['device']['brand'],
                    $parsed['parsed']['device']['type'],
                    $parsed['parsed']['device']['ismobile'],
                ];
                $rows[] = new TableSeparator();
            }

            $output->writeln('<info> done!</info>');

            array_pop($rows);

            $table->setRows($rows);

            $answer = '';

            if (!$csv && !$noOutput) {
                $table->render();

                $question = new ChoiceQuestion('What would you like to do?', ['Dump as CSV', 'Continue'], 1);

                $answer = $questionHelper->ask($input, $output, $question);
            }

            if ($csv || $answer === 'Dump as CSV') {
                $csvOutput = '';

                $csvOutput .= $this->putcsv([
                    'useragent',
                    'browser_name',
                    'browser_version',
                    'platform_name',
                    'platform_version',
                    'device_name',
                    'device_brand',
                    'device_type',
                    'ismobile',
                    'time',
                ], $csvFile) . "\n";

                foreach ($result['results'] as $parsed) {
                    $out = [
                        $parsed['useragent'],
                        $parsed['parsed']['browser']['name'],
                        $parsed['parsed']['browser']['version'],
                        $parsed['parsed']['platform']['name'],
                        $parsed['parsed']['platform']['version'],
                        $parsed['parsed']['device']['name'],
                        $parsed['parsed']['device']['brand'],
                        $parsed['parsed']['device']['type'],
                        $parsed['parsed']['device']['ismobile'],
                        $parsed['time'],
                    ];

                    $csvOutput .= $this->putcsv($out, $csvFile) . "\n";
                }

                if ($csvFile) {
                    $output->writeln('Wrote CSV data to ' . $csvFile);
                } else {
                    $output->writeln($csvOutput);
                    $question = new Question('Press enter to continue', 'yes');
                    $questionHelper->ask($input, $output, $question);
                }
            }
        }

        if ($name) {
            file_put_contents(
                $this->runDir . '/' . $name . '/metadata.json',
                json_encode(['parsers' => $parsers, 'date' => time(), 'file' => basename($file)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        return 0;
    }

    private function putcsv($input, $csvFile)
    {
        $delimiter = ',';
        $enclosure = '"';

        if ($csvFile) {
            $fp = fopen($csvFile, 'a+');
        } else {
            $fp = fopen('php://temp', 'r+b');
        }

        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);

        if ($csvFile) {
            return '';
        }

        return $data;
    }
}

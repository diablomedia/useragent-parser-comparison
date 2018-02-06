<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use function Safe\fclose;
use function Safe\file_put_contents;
use function Safe\fopen;
use function Safe\fputcsv;
use function Safe\json_encode;
use function Safe\mkdir;
use function Safe\rewind;
use function Safe\stream_get_contents;
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
    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    protected function configure(): void
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser(s)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addArgument('run', InputArgument::OPTIONAL, 'Name of the run, for storing results')
            ->addOption('normalize', null, InputOption::VALUE_NONE, 'Whether to normalize the output')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Outputs CSV without showing CLI table')
            ->addOption('no-output', null, InputOption::VALUE_NONE, 'Disables output after parsing, useful when chaining commands')
            ->addOption('csv-file', null, InputOption::VALUE_OPTIONAL, 'File name to output CSV data to, implies the options "csv" and "no-output"')
            ->addOption('single-ua', null, InputOption::VALUE_NONE, 'parses one useragent after another')
            ->setHelp('Parses the useragent strings (one per line) from the passed in file and outputs the parsed properties.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $filename */
        $filename  = $input->getArgument('file');
        $normalize = $input->getOption('normalize');
        $csv       = $input->getOption('csv');

        /** @var string|null $name */
        $name     = $input->getArgument('run');
        $noOutput = $input->getOption('no-output');

        /** @var string|null $csvFile */
        $csvFile = $input->getOption('csv-file');

        /** @var string|null $singleUa */
        $singleUa = $input->getOption('single-ua');

        if ($csvFile) {
            $noOutput = true;
            $csv      = true;
            $csvFile  = (string) $csvFile;
            $singleUa = false;
        } elseif ($csv) {
            $output->writeln(
                '<error>csvFile parameter is required if csv parameter is specified</error>'
            );

            return 1;
        }

        $parserHelper = $this->getHelper('parsers');

        /** @var Helper\Normalize $normalizeHelper */
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

        $parsers    = $parserHelper->getParsers($input, $output);
        $actualTest = 0;

        if ($singleUa) {
            $result = [];
            $file   = new \SplFileObject($filename);
            $file->setFlags(\SplFileObject::DROP_NEW_LINE);
            ++$actualTest;

            while (!$file->eof()) {
                $agentString = $file->fgets();

                if (empty($agentString)) {
                    continue;
                }

                $message = sprintf(
                    '%s[%s] Parsing UA <fg=yellow>%s</> ',
                    '  ',
                    (string) $actualTest,
                    $agentString
                );

                $output->write($message);

                foreach ($parsers as $parserName => $parser) {
                    if (!array_key_exists($parserName, $result)) {
                        $result[$parserName] = [
                            'results'     => [],
                            'parse_time'  => 0,
                            'init_time'   => 0,
                            'memory_used' => 0,
                            'version'     => null,
                        ];
                    }

                    $output->write("\r" . str_pad($message . '<info> against the <fg=green;options=bold,underscore>' . $parserName . '</> parser... </info>', 285));
                    $singleResult = $parser['parse-ua']($agentString);

                    if (empty($singleResult)) {
                        $output->writeln("\r" . $message . '<error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>');

                        continue;
                    }

                    if (isset($singleResult['version'])) {
                        $parsers[$parserName]['metadata']['version'] = $singleResult['version'];
                    }

                    $result[$parserName]['results'][] = $singleResult['result'];

                    if ($singleResult['init_time'] > $result[$parserName]['init_time']) {
                        $result[$parserName]['init_time'] = $singleResult['init_time'];
                    }

                    if ($singleResult['memory_used'] > $result[$parserName]['memory_used']) {
                        $result[$parserName]['memory_used'] = $singleResult['memory_used'];
                    }

                    $result[$parserName]['parse_time'] += $singleResult['parse_time'];
                    $result[$parserName]['version'] = $singleResult['version'];

                    $output->writeln("\r" . str_pad($message . '<info>done!</info>', 245));
                }

                foreach ($parsers as $parserName => $parser) {
                    if ($name) {
                        if (!file_exists($this->runDir . '/' . $name . '/results/' . $parserName)) {
                            mkdir($this->runDir . '/' . $name . '/results/' . $parserName);
                        }

                        file_put_contents(
                            $this->runDir . '/' . $name . '/results/' . $parserName . '/' . basename($filename) . '.json',
                            json_encode($result[$parserName], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        );
                    }

                    $rows = [];
                    foreach ($result[$parserName]['results'] as $parsed) {
                        if ($normalize) {
                            $parsed['parsed'] = $normalizeHelper->normalize($parsed['parsed']);
                        }

                        $rows[] = [
                            new TableCell('<fg=yellow>' . $parsed['useragent'] . '</>', ['colspan' => '7']),
                            round($parsed['time'], 5) . 's',
                        ];
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

                    $output->writeln('<info>done!</info>');

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

                        $csvOutput .= $this->putcsv(
                            [
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
                                ],
                            $csvFile
                            ) . "\n";

                        foreach ($result[$parserName]['results'] as $parsed) {
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
            }
        } else {
            $output->writeln('<comment>Preparing to parse ' . $filename . '</comment>');

            foreach ($parsers as $parserName => $parser) {
                $output->write('  <info> Testing against the <fg=green;options=bold,underscore>' . $parserName . '</> parser... </info>');
                $result = $parser['parse']($filename);

                if (empty($result)) {
                    $output->writeln(
                        '<error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>'
                    );

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
                        $this->runDir . '/' . $name . '/results/' . $parserName . '/' . basename($filename) . '.json',
                        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );
                }

                $rows = [];
                foreach ($result['results'] as $parsed) {
                    if ($normalize) {
                        $parsed['parsed'] = $normalizeHelper->normalize($parsed['parsed']);
                    }

                    $rows[] = [
                        new TableCell('<fg=yellow>' . $parsed['useragent'] . '</>', ['colspan' => '7']),
                        round($parsed['time'], 5) . 's',
                    ];
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

                $output->writeln('<info>done!</info>');

                array_pop($rows);

                $table->setRows($rows);

                $answer = '';

                if (!$csv && !$noOutput) {
                    $table->render();

                    $question = new ChoiceQuestion('What would you like to do?', ['Dump as CSV', 'Continue'], 1);

                    $answer = $questionHelper->ask($input, $output, $question);
                }

                if (($csv || $answer === 'Dump as CSV') && $csvFile) {
                    $csvOutput = '';

                    try {
                        $title = [
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
                        ];

                        $csvOutput .= $this->putcsv($title, $csvFile) . "\n";
                    } catch (Exception $e) {
                        $output->writeln('<error> error</error>');
                    }

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

                        try {
                            $csvOutput .= $this->putcsv($out, $csvFile) . "\n";
                        } catch (Exception $e) {
                            $output->writeln('<error> error</error>');
                        }
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
        }

        if ($name) {
            file_put_contents(
                $this->runDir . '/' . $name . '/metadata.json',
                json_encode(['parsers' => $parsers, 'date' => time(), 'file' => basename($filename)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        return 0;
    }

    /**
     * @throws \Exception if cannot open file stream
     */
    private function putcsv(array $input, string $csvFile): string
    {
        $delimiter = ',';
        $enclosure = '"';

        if ($csvFile) {
            $fp = fopen($csvFile, 'a+');
        } else {
            $fp = fopen('php://temp', 'r+');
        }

        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim((string) stream_get_contents($fp), "\n");
        fclose($fp);

        if ($csvFile) {
            return '';
        }

        return $data;
    }
}

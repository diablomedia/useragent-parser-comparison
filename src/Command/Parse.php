<?php

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;

class Parse extends Command
{
    protected function configure()
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addOption('normalize', null, InputOption::VALUE_NONE, 'Whether to normalize the output')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Outputs CSV without showing CLI table')
            ->setHelp('Parses the useragent strings (one per line) from the passed in file and outputs the parsed properties.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file      = $input->getArgument('file');
        $normalize = $input->getOption('normalize');
        $csv       = $input->getOption('csv');

        $parserHelper    = $this->getHelper('parsers');
        $normalizeHelper = $this->getHelper('normalize');
        $questionHelper  = $this->getHelper('question');

        $parsers = $parserHelper->getParsers($input, $output, false);

        $table = new Table($output);
        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => '7']), 'Parse Time'],
            ['browser_name', 'browser_version', 'platform_name', 'platform_version', 'device_name', 'device_brand', 'device_type', 'is_mobile']
        ]);
        $rows = [];

        foreach ($parsers as $parserName => $parser) {
            $result = $parser['parse']($file);

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
        }

        array_pop($rows);

        $table->setRows($rows);

        if (!$csv) {
            $table->render();

            $question = new ChoiceQuestion('What would you like to do?', ['Dump as CSV', 'Exit'], 1);

            $answer = $questionHelper->ask($input, $output, $question);
        }

        if ($csv || $answer == 'Dump as CSV') {
            $output->writeln($this->putcsv([
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
            ]));

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

                $output->writeln($this->putcsv($out));
            }
        }
    }

    protected function putcsv($input, $delimiter = ',', $enclosure = '"')
    {
        $fp = fopen('php://temp', 'r+b');
        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);

        return $data;
    }
}

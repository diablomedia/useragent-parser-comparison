<?php

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Input\InputArgument;

class Normalize extends Command
{
    protected $runDir  = __DIR__ . '/../../data/test-runs';
    protected $mapDir  = __DIR__ . '/../../mappings';
    protected $options = [];

    protected function configure()
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to normalize')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $run = $input->getArgument('run');

        if (empty($run)) {
            // Show user the available runs, perhaps limited to 10 or something
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln('<error>No run directory found with that id</error>');

            return;
        }

        $output->writeln('<comment>Normalizing data from test run: ' . $run . '</comment>');

        if (file_exists($this->runDir . '/' . $run . '/metadata.json')) {
            $this->options = json_decode(file_get_contents($this->runDir . '/' . $run . '/metadata.json'), true);
        } else {
            $this->options = ['tests' => [], 'parsers' => []];
        }

        if (!empty($this->options['tests'])) {
            if (!file_exists($this->runDir . '/' . $run . '/expected/normalized')) {
                mkdir($this->runDir . '/' . $run . '/expected/normalized');
            }

            // Process the test files (expected data)
            foreach (new \FilesystemIterator($this->runDir . '/' . $run . '/expected') as $testFile) {
                if ($testFile->isDir()) {
                    continue;
                }

                $output->write('Processing output from the ' . $testFile->getFilename() . ' test suite... ');

                $data       = json_decode(file_get_contents($testFile->getPathname()), true);
                $normalized = $data;

                $dataSource = null;

                $testName = str_replace('.json', '', $testFile->getFilename());
                if (isset($this->options['tests'][$testName]['metadata']['data_source'])) {
                    $dataSource = $this->options['tests'][$testName]['metadata']['data_source'];
                }

                foreach ($data['tests'] as $ua => $parsed) {
                    $normalized['tests'][$ua] = $this->normalize($parsed, $dataSource);
                }

                // Write normalized to file
                file_put_contents($this->runDir . '/' . $run . '/expected/normalized/' . $testFile->getFilename(), json_encode($normalized));

                $output->writeln('<info> done!</info>');
            }
        }

        if (!empty($this->options['parsers'])) {
            // Process the parser runs
            foreach (new \FilesystemIterator($this->runDir . '/' . $run . '/results') as $resultDir) {
                $parserName = $resultDir->getFilename();

                $output->writeln('Processing results from the ' . $parserName . ' parser');

                if (!file_exists($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized')) {
                    mkdir($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized');
                }

                foreach (new \FilesystemIterator($resultDir) as $resultFile) {
                    if ($resultFile->isDir()) {
                        continue;
                    }

                    $testName = str_replace('.json', '', $resultFile->getFilename());

                    $output->write("\t" . 'Processing results from the ' . $testName . ' test suite... ');

                    $data       = json_decode(file_get_contents($resultFile->getPathname()), true);
                    $normalized = [];

                    $dataSource = null;

                    if (isset($this->options['parsers'][$parserName]['metadata']['data_source'])) {
                        $dataSource = $this->options['parsers'][$parserName]['metadata']['data_source'];
                    }

                    foreach ($data['results'] as $result) {
                        $result['parsed'] = $this->normalize($result['parsed'], $dataSource);
                        $normalized[]     = $result;
                    }

                    $data['results'] = $normalized;

                    // Write normalized to file
                    file_put_contents($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $resultFile->getFilename(), json_encode($data));

                    $output->writeln('<info> done!</info>');
                }
            }
        }

        unset($normalized);

        $output->writeln('<comment>Normalized files written to the test run\'s directory</comment>');
    }

    protected function normalize($parsed, $source)
    {
        $normalizeHelper = $this->getHelper('normalize');

        return $normalizeHelper->normalize($parsed, $source);
    }
}

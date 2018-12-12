<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use FilesystemIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Normalize extends Command
{
    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    /**
     * @var array
     */
    private $options = [];

    protected function configure(): void
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to normalize')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $run */
        $run = $input->getArgument('run');

        if (empty($run)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return 1;
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln('<error>No run directory found with that id</error>');

            return 1;
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
            /** @var SplFileInfo $testFile */
            foreach (new FilesystemIterator($this->runDir . '/' . $run . '/expected') as $testFile) {
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

                if (!is_array($data['tests'])) {
                    continue;
                }

                foreach ($data['tests'] as $ua => $parsed) {
                    $normalized['tests'][$ua] = $this->normalize($parsed, $dataSource);
                }

                // Write normalized to file
                file_put_contents(
                    $this->runDir . '/' . $run . '/expected/normalized/' . $testFile->getFilename(),
                    json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );

                $output->writeln('<info> done!</info>');
            }
        }

        if (!empty($this->options['parsers'])) {
            // Process the parser runs
            /** @var SplFileInfo $resultDir */
            foreach (new FilesystemIterator($this->runDir . '/' . $run . '/results') as $resultDir) {
                $parserName = $resultDir->getFilename();

                $output->writeln('Processing results from the ' . $parserName . ' parser');

                if (!file_exists($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized')) {
                    mkdir($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized');
                }

                /** @var SplFileInfo $resultFile */
                foreach (new FilesystemIterator($resultDir->getPathname()) as $resultFile) {
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
                    file_put_contents(
                        $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $resultFile->getFilename(),
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );

                    $output->writeln('<info> done!</info>');
                }
            }
        }

        unset($normalized);

        $output->writeln('<comment>Normalized files written to the test run\'s directory</comment>');

        return 0;
    }

    private function normalize(array $parsed, string $source): array
    {
        /** @var \UserAgentParserComparison\Command\Helper\Normalize $normalizeHelper */
        $normalizeHelper = $this->getHelper('normalize');

        return $normalizeHelper->normalize($parsed, $source);
    }
}

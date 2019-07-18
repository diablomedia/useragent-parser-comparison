<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use FilesystemIterator;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\mkdir;
use function Safe\sprintf;
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
        $this->options = ['tests' => [], 'parsers' => []];

        if (file_exists($this->runDir . '/' . $run . '/metadata.json')) {
            try {
                $contents = file_get_contents($this->runDir . '/' . $run . '/metadata.json');

                try {
                    $this->options = json_decode($contents, true);
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while parsing metadata for run ' . $run . '</error>');
                }
            } catch (Exception $e) {
                $output->writeln('<error>Could not read metadata file for run ' . $run . '</error>');
            }
        }

        if (!empty($this->options['tests'])) {
            if (!file_exists($this->runDir . '/' . $run . '/expected/normalized')) {
                mkdir($this->runDir . '/' . $run . '/expected/normalized');
            }

            $output->writeln('<comment>Processing output from the test suites</comment>');

            // Process the test files (expected data)
            /** @var SplFileInfo $testFile */
            foreach (new FilesystemIterator($this->runDir . '/' . $run . '/expected') as $testFile) {
                if ($testFile->isDir()) {
                    continue;
                }

                $message = sprintf('%sProcessing output from the <fg=yellow>%s</> test suite... ', '  ', $testFile->getBasename('.' . $testFile->getExtension()));

                $output->write($message . '<info> parsing result</info>');

                try {
                    $contents = file_get_contents($testFile->getPathname());
                } catch (Exception $e) {
                    continue;
                }

                try {
                    $data = json_decode($contents, true);
                } catch (Exception $e) {
                    $output->writeln("\r" . $message . '<error>An error occured while normalizing test suite ' . $testFile->getFilename() . '</error>');
                    continue;
                }

                $normalized = $data;

                if (!is_array($data['tests'])) {
                    $output->writeln("\r" . $message . '<info> done!</info>');
                    continue;
                }

                $output->write("\r" . $message . '<info> normalizing result</info>');

                foreach ($data['tests'] as $ua => $parsed) {
                    $normalized['tests'][$ua] = $this->normalize($parsed);
                }

                $output->write("\r" . $message . '<info> writing result</info>    ');

                // Write normalized to file
                file_put_contents(
                    $this->runDir . '/' . $run . '/expected/normalized/' . $testFile->getFilename(),
                    json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );

                $output->writeln("\r" . $message . '<info> done!</info>           ');
            }
        }

        if (!empty($this->options['parsers'])) {
            // Process the parser runs
            /** @var SplFileInfo $resultDir */
            foreach (new FilesystemIterator($this->runDir . '/' . $run . '/results') as $resultDir) {
                $parserName = $resultDir->getFilename();

                $output->writeln('<comment>Processing results from the ' . $parserName . ' parser</comment>');

                if (!file_exists($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized')) {
                    mkdir($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized');
                }

                /** @var SplFileInfo $resultFile */
                foreach (new FilesystemIterator($resultDir->getPathname()) as $resultFile) {
                    if ($resultFile->isDir()) {
                        continue;
                    }

                    $testName = str_replace('.json', '', $resultFile->getFilename());
                    $message  = sprintf('%sProcessing results from the <fg=yellow>%s</> test suite... ', '  ', $testName);

                    $output->write($message . '<info> parsing result</info>');

                    try {
                        $contents = file_get_contents($resultFile->getPathname());
                    } catch (Exception $e) {
                        continue;
                    }

                    try {
                        $data = json_decode($contents, true);
                    } catch (Exception $e) {
                        $output->writeln("\r" . $message . '<error>An error occured while parsing results for the ' . $testName . ' test suite</error>');
                        $data['results'] = [];
                    }

                    $normalized = [];

                    if (!is_array($data['results'])) {
                        continue;
                    }

                    $output->write("\r" . $message . '<info> normalizing result</info>');

                    foreach ($data['results'] as $result) {
                        if (!isset($result['parsed'])) {
                            $output->writeLn('<error>There was no "parsed" property for the ' . $testName . ' test suite </error>');
                        } else {
                            $result['parsed'] = $this->normalize($result['parsed']);
                            $normalized[]     = $result;
                        }
                    }

                    $output->write("\r" . $message . '<info> writing result</info>    ');

                    $data['results'] = $normalized;

                    // Write normalized to file
                    file_put_contents(
                        $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $resultFile->getFilename(),
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );

                    $output->writeln("\r" . $message . '<info> done!</info>           ');
                }
            }
        }

        unset($normalized);

        $output->writeln('<comment>Normalized files written to the test run\'s directory</comment>');

        return 0;
    }

    private function normalize(array $parsed): array
    {
        /** @var \UserAgentParserComparison\Command\Helper\Normalize $normalizeHelper */
        $normalizeHelper = $this->getHelper('normalize');

        return $normalizeHelper->normalize($parsed);
    }
}

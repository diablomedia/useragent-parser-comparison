<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Test extends Command
{
    private $tests = [];

    private $selectedTests = [];

    private $testsDir = __DIR__ . '/../../tests';

    private $runDir = __DIR__ . '/../../data/test-runs';

    private $results = [];

    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->setHelp('Runs various test suites against the parsers to help determine which is the most "correct".');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->collectTests();

        $rows = [];

        $output->writeln('These are all of the tests available, choose which you would like to run');

        $questions = array_keys($this->tests);
        sort($questions);

        $i = 1;
        foreach ($questions as $name) {
            $rows[] = [$name];
            ++$i;
        }

        $table = new Table($output);
        $table->setHeaders(['Test Suite']);
        $table->setRows($rows);
        $table->render();

        $questions[] = 'All Suites';

        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion(
            'Choose which test suites to run, separate multiple with commas (press enter to use all)',
            $questions,
            count($questions) - 1
        );
        $question->setMultiselect(true);

        $answers = $questionHelper->ask($input, $output, $question);

        foreach ($answers as $name) {
            if ($name === 'All Suites') {
                $this->selectedTests = $this->tests;

                break;
            }

            $this->selectedTests[$name] = $this->tests[$name];
        }

        $output->writeln('Choose which parsers you would like to run this test suite against');
        $parserHelper = $this->getHelper('parsers');
        $parsers      = $parserHelper->getParsers($input, $output);

        // Prepare our test directory to store the data from this run
        $thisRunDirName = $input->getArgument('name');

        if (empty($thisRunDirName)) {
            $thisRunDirName = date('YmdHis');
        }
        $thisRunDir   = $this->runDir . '/' . $thisRunDirName;
        $testFilesDir = $thisRunDir . '/test-files';
        $resultsDir   = $thisRunDir . '/results';
        $expectedDir  = $thisRunDir . '/expected';

        mkdir($thisRunDir);
        mkdir($testFilesDir);
        mkdir($resultsDir);
        mkdir($expectedDir);

        $usedTests = [];

        foreach ($this->selectedTests as $testName => $testData) {
            $output->write('Generating data for the ' . $testName . ' test suite... ');
            $this->results[$testName] = [];

            $testOutput = trim((string) shell_exec($testData['path'] . '/build.sh'));

            file_put_contents($expectedDir . '/' . $testName . '.json', $testOutput);

            $testOutput = json_decode($testOutput, true);

            if (json_last_error() !== JSON_ERROR_NONE || empty($testOutput)) {
                $output->writeln('<error>There was an error with the output from the ' . $testName . ' test suite.</error>');

                continue;
            }

            if (!empty($testOutput['version'])) {
                $testData['metadata']['version'] = $testOutput['version'];
            }

            // write our test's file that we'll pass to the parsers
            $filename = $testFilesDir . '/' . $testName . '.txt';

            if (is_array($testOutput['tests'])) {
                $agents = array_keys($testOutput['tests']);
            } else {
                $agents = [];
            }

            array_walk($agents, static function (&$item): void {
                $item = addcslashes((string) $item, PHP_EOL);
            });

            file_put_contents($filename, implode(PHP_EOL, $agents));
            $output->writeln('<info>  done!</info>');

            foreach ($parsers as $parserName => $parser) {
                $output->write("\t" . 'Testing against the ' . $parserName . ' parser... ');
                $result = $parser['parse']($filename);

                if (empty($result)) {
                    $output->writeln('<error>The ' . $parserName . ' parser did not return any data, there may have been an error</error>');

                    continue;
                }

                if (!empty($result['version'])) {
                    $parsers[$parserName]['metadata']['version'] = $result['version'];
                }

                if (!file_exists($resultsDir . '/' . $parserName)) {
                    mkdir($resultsDir . '/' . $parserName);
                }

                file_put_contents(
                    $resultsDir . '/' . $parserName . '/' . $testName . '.json',
                    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
                $output->writeln('<info> done!</info>');
            }

            $usedTests[$testName] = $testData;
        }

        // write some test data to file
        file_put_contents(
            $thisRunDir . '/metadata.json',
            json_encode(['tests' => $usedTests, 'parsers' => $parsers, 'date' => time()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $output->writeln('<comment>Parsing complete, data stored in ' . $thisRunDirName . ' directory</comment>');

        return 0;
    }

    private function collectTests(): void
    {
        foreach (new \FilesystemIterator($this->testsDir) as $testDir) {
            if (file_exists($testDir->getPathName() . '/metadata.json')) {
                $metadata = json_decode(file_get_contents($testDir->getPathName() . '/metadata.json'), true);
            } else {
                $metadata = [];
            }

            $this->tests[$testDir->getFilename()] = [
                'path'     => $testDir->getPathName(),
                'metadata' => $metadata,
            ];
        }
    }
}

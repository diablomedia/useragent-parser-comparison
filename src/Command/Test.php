<?php

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Input\InputArgument;

class Test extends Command
{
    protected $tests         = [];
    protected $selectedTests = [];
    protected $testsDir      = __DIR__ . '/../../tests';
    protected $runDir        = __DIR__ . '/../../data/test-runs';
    protected $results       = [];
    protected $failures      = [];

    protected function configure()
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->setHelp('Runs various test suites against the parsers to help determine which is the most "correct".');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->collectTests();

        $rows = [];

        $output->writeln('These are all of the tests available, choose which you would like to run');

        $i = 1;
        foreach ($this->tests as $name => $data) {
            $rows[] = [$name];
            $i++;
        }

        $table = new Table($output);
        $table->setHeaders(['Test Suite']);
        $table->setRows($rows);
        $table->render();

        $questions = array_keys($this->tests);
        ksort($questions);
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
            if ($name == 'All Suites') {
                $this->selectedTests = $this->tests;
                break;
            }

            $this->selectedTests[$name] = $this->tests[$name];
        }

        $output->writeln('Choose which parsers you would like to run this test suite against');
        $parserHelper = $this->getHelper('parsers');
        $parsers      = $parserHelper->getParsers($input, $output);

        $parserScores = [];

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

        // write some test data to file
        file_put_contents(
            $thisRunDir . '/metadata.json',
            json_encode(['tests' => $this->selectedTests, 'parsers' => $parsers, 'date' => time()])
        );

        foreach ($this->selectedTests as $testName => $testData) {
            $output->write('Generating data for the ' . $testName . ' test suite... ');
            $this->results[$testName] = [];

            $testOutput = trim(shell_exec($testData['path'] . '/build'));

            file_put_contents($expectedDir . '/' . $testName . '.json', $testOutput);

            $testOutput = json_decode($testOutput, true);

            // write our test's file that we'll pass to the parsers
            $filename = $testFilesDir . '/' . $testName . '.txt';

            $agents = array_keys($testOutput);

            array_walk($agents, function (&$item) {
                $item = addcslashes($item, "\n");
            });

            file_put_contents($filename, implode("\n", $agents));
            $output->writeln('<info>  done!</info>');

            foreach ($parsers as $parserName => $parser) {
                $output->write("\t" . 'Testing against the ' . $parserName . ' parser... ');
                $results = $parser['parse']($filename);

                if (!file_exists($resultsDir . '/' . $parserName)) {
                    mkdir($resultsDir . '/' . $parserName);
                }

                file_put_contents($resultsDir . '/' . $parserName . '/' . $testName . '.json', json_encode($results));
                $output->writeln('<info> done!</info>');
            }
        }

        $output->writeln('<comment>Parsing complete, data stored in ' . $thisRunDirName . ' directory</comment>');
    }

    protected function collectTests()
    {
        foreach (new \FilesystemIterator($this->testsDir) as $testDir) {
            if (file_exists($testDir->getPathName() . '/metadata.json')) {
                $metadata = json_decode(file_get_contents($testDir->getPathName() . '/metadata.json'));
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

<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Test extends Command
{
    private $tests = [];

    private $selectedTests = [];

    private $testsDir = __DIR__ . '/../../tests';

    private $runDir = __DIR__ . '/../../data/test-runs';

    private $results = [];

    private $jsonParser;

    public function __construct()
    {
        $this->jsonParser = new JsonParser();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->addOption('single-ua', null, InputOption::VALUE_NONE, 'parses one useragent after another')
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
        $thisRunDirName = $input->getArgument('run');

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
            $output->write('Generating data for the '.$testName.' test suite... ');
            $this->results[$testName] = [];

            $testOutput = trim((string) shell_exec($testData['path'] . '/build.sh'));

            file_put_contents($expectedDir . '/' . $testName . '.json', $testOutput);

            try {
                $testOutput = $this->jsonParser->parse(
                    $testOutput,
                    JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                );
            } catch (ParsingException $e) {
                $output->writeln('<error>There was an error with the output from the ' . $testName . ' test suite.</error>');

                continue;
            }

            if (!empty($testOutput['version'])) {
                $testData['metadata']['version'] = $testOutput['version'];
            }

            $output->writeln('<info>  done!</info>');

            if ($input->getOption('single-ua')) {
                $result = [];
                foreach (array_keys($testOutput['tests']) as $agent) {
                    $agent = addcslashes($agent, PHP_EOL);

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

                        $output->write("\t".'Testing against the '.$parserName.' parser... ');
                        $singleResult = $parser['parse-ua']($agent);

                        if (empty($singleResult)) {
                            $output->writeln(
                                '<error>The '.$parserName.' parser did not return any data, there may have been an error</error>'
                            );

                            continue;
                        }

                        if (!empty($singleResult['version'])) {
                            $parsers[$parserName]['metadata']['version'] = $singleResult['version'];
                        }

                        if (!file_exists($resultsDir.'/'.$parserName)) {
                            mkdir($resultsDir.'/'.$parserName);
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

                        $output->writeln('<info> done!</info>');
                    }
                }

                foreach (array_keys($parsers) as $parserName) {
                    file_put_contents(
                        $resultsDir.'/'.$parserName.'/'.$testName.'.json',
                        json_encode($result[$parserName], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );
                }
            } else {
                $output->write('write test data for the '.$testName.' test suite into file... ');
                // write our test's file that we'll pass to the parsers
                $filename = $testFilesDir.'/'.$testName.'.txt';

                if (is_array($testOutput['tests'])) {
                    $agents = array_keys($testOutput['tests']);
                } else {
                    $agents = [];
                }

                array_walk($agents, static function (&$item): void {
                    $item = addcslashes((string)$item, PHP_EOL);
                });

                file_put_contents($filename, implode(PHP_EOL, $agents));
                $output->writeln('<info>  done!</info>');

                foreach ($parsers as $parserName => $parser) {
                    $output->write("\t".'Testing against the '.$parserName.' parser... ');
                    $result = $parser['parse']($filename);

                    if (empty($result)) {
                        $output->writeln(
                            '<error>The '.$parserName.' parser did not return any data, there may have been an error</error>'
                        );

                        continue;
                    }

                    if (!empty($result['version'])) {
                        $parsers[$parserName]['metadata']['version'] = $result['version'];
                    }

                    if (!file_exists($resultsDir.'/'.$parserName)) {
                        mkdir($resultsDir.'/'.$parserName);
                    }

                    file_put_contents(
                        $resultsDir.'/'.$parserName.'/'.$testName.'.json',
                        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );
                    $output->writeln('<info> done!</info>');
                }
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
        foreach (scandir($this->testsDir) as $dir) {
            if (in_array($dir, ['.', '..'])) {
                continue;
            }

            $testDir = new \SplFileInfo($this->testsDir . '/' . $dir);

            if (file_exists($testDir->getPathName() . '/metadata.json')) {
                try {
                    $metadata = $this->jsonParser->parse(
                        file_get_contents($testDir->getPathName().'/metadata.json'),
                        JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                    );
                } catch (ParsingException $e) {
                    $metadata = [];
                }
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

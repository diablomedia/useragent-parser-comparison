<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use FilesystemIterator;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\ksort;
use function Safe\mkdir;
use function Safe\sort;
use function Safe\sprintf;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Test extends Command
{
    /**
     * @var array
     */
    private $tests = [];

    /**
     * @var string
     */
    private $testsDir = __DIR__ . '/../../tests';

    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    /**
     * @var array
     */
    private $results = [];

    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->setHelp('Runs various test suites against the parsers to help determine which is the most "correct".');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->collectTests($output);

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

        $answers       = $questionHelper->ask($input, $output, $question);
        $selectedTests = [];

        foreach ($answers as $name) {
            if ($name === 'All Suites') {
                $selectedTests = $this->tests;

                break;
            }

            $selectedTests[$name] = $this->tests[$name];
        }

        $output->writeln('Choose which parsers you would like to run this test suite against');
        $parserHelper = $this->getHelper('parsers');
        $parsers      = $parserHelper->getParsers($input, $output);

        // Prepare our test directory to store the data from this run
        /** @var string|null $thisRunDirName */
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

        foreach ($selectedTests as $testName => $testData) {
            $message = sprintf('Generating data for the <fg=yellow>%s</> test suite... ', $testName);

            $output->write($message . '<info> building test suite</info>');
            $this->results[$testName] = [];

            $testOutput = trim((string) shell_exec('sh ' . $testData['path'] . '/build.sh'));

            $output->write("\r" . $message . '<info> writing test suite</info>    ');

            file_put_contents($expectedDir . '/' . $testName . '.json', $testOutput);

            try {
                $testOutput = json_decode($testOutput, true);
            } catch (Exception $e) {
                $output->writeln("\r" . $message . '<error>There was an error with the output from the ' . $testName . ' test suite.</error>');

                continue;
            }

            if ($testOutput['tests'] === null) {
                $output->writeln("\r" . $message . '<error>There was an error with the output from the ' . $testName . ' test suite, no tests were found.</error>');
                continue;
            }

            if (!empty($testOutput['version'])) {
                $testData['metadata']['version'] = $testOutput['version'];
            }

            $output->write("\r" . $message . '<info>  write test data into file...</info>');

            // write our test's file that we'll pass to the parsers
            $filename = $testFilesDir . '/' . $testName . '.txt';

            if (is_array($testOutput['tests'])) {
                $agents = array_keys($testOutput['tests']);
            } else {
                $agents = [];
            }

            array_walk($agents, static function (string &$item): void {
                $item = addcslashes($item, PHP_EOL);
            });

            file_put_contents($filename, implode(PHP_EOL, $agents));
            $output->writeln("\r" . $message . '<info>  done! [' . count($agents) . ' tests found]</info>       ');

            foreach ($parsers as $parserName => $parser) {
                $testMessage = sprintf('  Testing against the <fg=green;options=bold,underscore>%s</> parser...', $parserName);
                $output->write($testMessage . ' <info> parsing</info>');

                $result = $parser['parse']($filename);

                if (empty($result)) {
                    $output->writeln(
                        "\r" . $testMessage . ' <error>The parser did not return any data, there may have been an error</error>'
                    );

                    continue;
                }

                if (!empty($result['version'])) {
                    $parsers[$parserName]['metadata']['version'] = $result['version'];
                }

                if (!file_exists($resultsDir . '/' . $parserName)) {
                    mkdir($resultsDir . '/' . $parserName);
                }

                try {
                    $encoded = json_encode(
                        $result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } catch (Exception $e) {
                    $output->writeln("\r" . $testMessage . ' <error>encoding the result failed!</error>');
                    continue;
                }

                file_put_contents(
                    $resultsDir . '/' . $parserName . '/' . $testName . '.json',
                    $encoded
                );
                $output->writeln("\r" . $testMessage . ' <info> done!</info>                                                                                 ');

                $usedTests[$testName] = $testData;
            }
        }

        try {
            $encoded = json_encode(
                ['tests' => $usedTests, 'parsers' => $parsers, 'date' => time()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (Exception $e) {
            $output->writeln('<error>Encoding result metadata failed for the ' . $thisRunDirName . ' directory</error>');

            return 1;
        }

        // write some test data to file
        file_put_contents(
            $thisRunDir . '/metadata.json',
            $encoded
        );

        $output->writeln('<comment>Parsing complete, data stored in ' . $thisRunDirName . ' directory</comment>');

        return 0;
    }

    private function collectTests(OutputInterface $output): void
    {
        /** @var SplFileInfo $testDir */
        foreach (new FilesystemIterator($this->testsDir) as $testDir) {
            $metadata = [];
            if (file_exists($testDir->getPathname() . '/metadata.json')) {
                try {
                    $contents = file_get_contents($testDir->getPathname() . '/metadata.json');
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while reading the metadata file</error>');

                    continue;
                }

                try {
                    $metadata = json_decode($contents, true);
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while parsing results for the ' . $testDir->getPathname() . ' test suite</error>');
                }
            }

            $this->tests[$testDir->getFilename()] = [
                'path'     => $testDir->getPathname(),
                'metadata' => $metadata,
            ];
        }

        ksort($this->tests);
    }
}

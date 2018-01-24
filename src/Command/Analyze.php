<?php
declare(strict_types = 1);
namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Analyze extends Command
{
    private $runDir = __DIR__ . '/../../data/test-runs';

    private $options = [];

    private $comparison = [];

    private $agents = [];

    /**
     * @var \Symfony\Component\Console\Helper\Table
     */
    private $summaryTable;

    private $input;

    private $output;

    private $failures = [];

    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Analyzes the data from test runs')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to analyze')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->input  = $input;
        $this->output = $output;

        $run = $input->getArgument('run');

        if (empty($run)) {
            // Show user the available runs, perhaps limited to 10 or something
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln('<error>No run directory found with that id (' . $run . ')</error>');

            return;
        }

        if (file_exists($this->runDir . '/' . $run . '/metadata.json')) {
            $this->options = json_decode(file_get_contents($this->runDir . '/' . $run . '/metadata.json'), true);
        } else {
            $output->writeln('<error>No options file found for this test run</error>');

            return;
        }

        $output->writeln('<info>Analyzing data from test run: ' . $run . '</info>');

        if (!empty($this->options['tests'])) {
            $tests = $this->options['tests'];
        } elseif (!empty($this->options['file'])) {
            $tests = [
                $this->options['file'] => [],
            ];
            $this->options['tests'] = $tests;
        } else {
            $output->writeln('<error>Error in options file for this test run</error>');

            return;
        }

        $this->summaryTable = new Table($output);
        $this->summaryTable->setHeaders(['Parser', 'Version', 'Browser Results', 'Platform Results', 'Device Results', 'Time Taken', 'Accuracy Score']);
        $rows   = [];
        $totals = [];

        foreach ($tests as $testName => $testData) {
            $this->comparison[$testName] = [];

            $expectedFilename = $this->runDir . '/' . $run . '/expected/normalized/' . $testName . '.json';

            if (file_exists($expectedFilename)) {
                $expectedResults = json_decode(file_get_contents($expectedFilename), true);
                $headerMessage   = '<fg=yellow>Parser comparison for ' . $testName . ' test suite' . (isset($testData['metadata']['version']) ? ' (' . $testData['metadata']['version'] . ')' : '') . '</>';
            } else {
                // When we aren't comparing to a test suite, the first parser's results become the expected results
                $expectedResults = [];
                $testResult      = json_decode(
                    file_get_contents(
                        $this->runDir . '/' . $run . '/results/' . array_keys(
                            $this->options['parsers']
                        )[0] . '/normalized/' . $testName . '.json'
                    ),
                    true
                );

                foreach ($testResult['results'] as $data) {
                    $expectedResults['tests'][$data['useragent']] = $data['parsed'];
                }

                $headerMessage = '<fg=yellow>Parser comparison for ' . $testName . ' file, using ' . array_keys($this->options['parsers'])[0] . ' results as expected</>';
            }

            if (!isset($expectedResults['tests']) || !is_array($expectedResults['tests'])) {
                continue;
            }

            $rows[] = [new TableCell($headerMessage, ['colspan' => 7])];
            $rows[] = new TableSeparator();

            foreach ($expectedResults['tests'] as $agent => $result) {
                if (!isset($this->agents[$agent])) {
                    $this->agents[$agent] = count($this->agents);
                }

                foreach (['browser', 'platform', 'device'] as $compareKey) {
                    $subs = ['name'];
                    if ('device' === $compareKey) {
                        $subs = ['name', 'brand', 'type'];
                    }

                    if (!isset($this->comparison[$testName][$compareKey])) {
                        $this->comparison[$testName][$compareKey] = [];
                    }

                    foreach ($subs as $compareSubKey) {
                        if (!isset($this->comparison[$testName][$compareKey][$compareSubKey])) {
                            $this->comparison[$testName][$compareKey][$compareSubKey] = [];
                        }

                        if (isset($result[$compareKey][$compareSubKey]) && $result[$compareKey][$compareSubKey] !== null) {
                            $expectedValue = $result[$compareKey][$compareSubKey];
                        } else {
                            $expectedValue = '[n/a]';
                        }

                        if (!isset($this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue])) {
                            $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue] = [
                                'expected' => [
                                    'count'  => 0,
                                    'agents' => [],
                                ],
                            ];
                        }

                        ++$this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue]['expected']['count'];
                        $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue]['expected']['agents'][] = $this->agents[$agent];
                    }
                }
            }

            foreach ($this->options['parsers'] as $parserName => $parserData) {
                if (!file_exists($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testName . '.json')) {
                    $this->output->writeln('<error>No output found for the ' . $parserName . ' parser, skipping</error>');

                    continue;
                }

                $testResult = json_decode(
                    file_get_contents($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testName . '.json'),
                    true
                );

                $passFail = [
                    'browser'  => ['pass' => 0, 'fail' => 0],
                    'platform' => ['pass' => 0, 'fail' => 0],
                    'device'   => ['pass' => 0, 'fail' => 0],
                ];

                $parserScores[$parserName][$testName]   = 0;
                $possibleScores[$parserName][$testName] = 0;

                foreach ($testResult['results'] as $data) {
                    $expected = $expectedResults['tests'][$data['useragent']];
                    $failures = [];

                    foreach (['browser', 'platform', 'device'] as $compareKey) {
                        $subs = ['name'];
                        if ('device' === $compareKey) {
                            $subs = ['name', 'brand', 'type'];
                        }

                        foreach ($subs as $compareSubKey) {
                            if (isset($expected[$compareKey][$compareSubKey]) && $expected[$compareKey][$compareSubKey] !== null) {
                                $expectedValue = $expected[$compareKey][$compareSubKey];
                            } else {
                                $expectedValue = '[n/a]';
                            }

                            if (isset($data['parsed'][$compareKey][$compareSubKey]) && $data['parsed'][$compareKey][$compareSubKey] !== null) {
                                $actualValue = $data['parsed'][$compareKey][$compareSubKey];
                            } else {
                                $actualValue = '[n/a]';
                            }

                            if (!isset($this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue][$parserName])) {
                                $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue][$parserName] = [];
                            }

                            if (!isset($this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue])) {
                                $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue] = [
                                    'count'  => 0,
                                    'agents' => [],
                                ];
                            }

                            ++$this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue]['count'];
                            $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue]['agents'][] = $this->agents[$data['useragent']];

                            if ($expectedValue !== $actualValue) {
                                if ('[n/a]' !== $expectedValue && '[n/a]' !== $actualValue) {
                                    $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue]['expected']['hasFailures'] = true;
                                }
                            }
                        }

                        if ($data['parsed'][$compareKey] !== $expected[$compareKey]) {
                            $diff = $this->makeDiff($expected[$compareKey], $data['parsed'][$compareKey]);
                            if (!empty($diff)) {
                                ++$passFail[$compareKey]['fail'];
                                $failures[$compareKey] = $diff;
                            } else {
                                ++$passFail[$compareKey]['pass'];
                            }
                        } else {
                            ++$passFail[$compareKey]['pass'];
                        }

                        $parserScores[$parserName][$testName]   += $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey]);
                        $possibleScores[$parserName][$testName] += $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey], true);
                    }

                    if (!empty($failures)) {
                        $this->failures[$testName][$parserName][$data['useragent']] = $failures;
                    }
                }

                $rows[] = [
                    $parserName,
                    isset($parserData['metadata']['version']) ? $parserData['metadata']['version'] : 'n/a',
                    $passFail['browser']['pass'] . '/' . array_sum($passFail['browser']) . ' ' . round($passFail['browser']['pass'] / array_sum($passFail['browser']) * 100, 2) . '%',
                    $passFail['platform']['pass'] . '/' . array_sum($passFail['platform']) . ' ' . round($passFail['platform']['pass'] / array_sum($passFail['platform']) * 100, 2) . '%',
                    $passFail['device']['pass'] . '/' . array_sum($passFail['device']) . ' ' . round($passFail['device']['pass'] / array_sum($passFail['device']) * 100, 2) . '%',
                    round($testResult['parse_time'] + $testResult['init_time'], 3) . 's',
                    $parserScores[$parserName][$testName] . '/' . $possibleScores[$parserName][$testName] . ' ' . round($parserScores[$parserName][$testName] / $possibleScores[$parserName][$testName] * 100, 2) . '%',
                ];

                if (!isset($totals[$parserName])) {
                    $totals[$parserName] = [
                        'browser'  => ['pass' => 0, 'fail' => 0],
                        'platform' => ['pass' => 0, 'fail' => 0],
                        'device'   => ['pass' => 0, 'fail' => 0],
                        'time'     => 0,
                        'score'    => ['earned' => 0, 'possible' => 0],
                    ];
                }

                $totals[$parserName]['browser']['pass']   += $passFail['browser']['pass'];
                $totals[$parserName]['browser']['fail']   += $passFail['browser']['fail'];
                $totals[$parserName]['platform']['pass']  += $passFail['platform']['pass'];
                $totals[$parserName]['platform']['fail']  += $passFail['platform']['fail'];
                $totals[$parserName]['device']['pass']    += $passFail['device']['pass'];
                $totals[$parserName]['device']['fail']    += $passFail['device']['fail'];
                $totals[$parserName]['time']              += ($testResult['parse_time'] + $testResult['init_time']);
                $totals[$parserName]['score']['earned']   += $parserScores[$parserName][$testName];
                $totals[$parserName]['score']['possible'] += $possibleScores[$parserName][$testName];
            }

            $rows[] = new TableSeparator();
        }

        if (1 < count($this->options['tests'])) {
            $rows[] = [new TableCell('<fg=yellow>Total for all Test suites</>', ['colspan' => 6])];
            $rows[] = new TableSeparator();
            foreach ($totals as $parser => $total) {
                $rows[] = [
                    $parser,
                    isset($this->options['parsers'][$parser]['metadata']['version']) ? $this->options['parsers'][$parser]['metadata']['version'] : 'n/a',
                    $total['browser']['pass'] . '/' . array_sum($total['browser']) . ' ' . round($total['browser']['pass'] / array_sum($total['browser']) * 100, 2) . '%',
                    $total['platform']['pass'] . '/' . array_sum($total['platform']) . ' ' . round($total['platform']['pass'] / array_sum($total['platform']) * 100, 2) . '%',
                    $total['device']['pass'] . '/' . array_sum($total['device']) . ' ' . round($total['device']['pass'] / array_sum($total['device']) * 100, 2) . '%',
                    round($total['time'], 3) . 's',
                    $total['score']['earned'] . '/' . $total['score']['possible'] . ' ' . round($total['score']['earned'] / $total['score']['possible'] * 100, 2) . '%',
                ];
            }

            $rows[] = new TableSeparator();
        }

        array_pop($rows);

        $this->summaryTable->setRows($rows);
        $this->showSummary();

        $this->showMenu();
    }

    private function showSummary(): void
    {
        $this->summaryTable->render();
    }

    private function changePropertyDiffTestSuite()
    {
        $questionHelper = $this->getHelper('question');

        if (1 < count($this->options['tests'])) {
            $question = new ChoiceQuestion(
                'Which Test Suite?',
                array_keys($this->options['tests'])
            );

            $selectedTest = $questionHelper->ask($this->input, $this->output, $question);
        } else {
            $selectedTest = array_keys($this->options['tests'])[0];
        }

        return $selectedTest;
    }

    private function changePropertyDiffSection()
    {
        $questionHelper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            'Which Section?',
            ['browser', 'platform', 'device']
        );
        $section = $questionHelper->ask($this->input, $this->output, $question);

        return $section;
    }

    private function changePropertyDiffProperty($section)
    {
        $questionHelper = $this->getHelper('question');

        switch ($section) {
            case 'browser':
            case 'platform':
                $subs = ['name'];

                break;
            case 'device':
                $subs = ['name', 'brand', 'type'];

                break;
        }

        if (1 < count($subs)) {
            $question = new ChoiceQuestion(
                'Which Property?',
                $subs
            );
            $property = $questionHelper->ask($this->input, $this->output, $question);
        } else {
            $property = $subs[0];
        }

        return $property;
    }

    private function showMenu(): void
    {
        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion(
            'What would you like to view?',
            ['Show Summary', 'View failure diff', 'View property comparison', 'Exit'],
            3
        );

        $answer = $questionHelper->ask($this->input, $this->output, $question);

        switch ($answer) {
            case 'Show Summary':
                $this->showSummary();
                $this->showMenu();

                break;
            case 'View failure diff':
                $answer = '';
                do {
                    if (!isset($selectedTest) || 'Change Test Suite' === $answer) {
                        if (1 < count($this->options['tests'])) {
                            $question = new ChoiceQuestion(
                                'Which test suite?',
                                array_keys($this->options['tests'])
                            );

                            $selectedTest = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedTest = array_keys($this->options['tests'])[0];
                        }
                    }

                    if (!isset($selectedParser) || 'Change Parser' === $answer) {
                        if (1 < count($this->options['parsers'])) {
                            $question = new ChoiceQuestion(
                                'Which parser?',
                                array_keys($this->options['parsers'])
                            );

                            $selectedParser = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedParser = array_keys($this->options['parsers'])[0];
                        }
                    }

                    if (!isset($justAgents) || 'Show Full Diff' === $answer) {
                        $justAgents = false;
                    } elseif ('Show Just UserAgents' === $answer) {
                        $justAgents = true;
                    }

                    $this->analyzeFailures($selectedTest, $selectedParser, $justAgents);

                    $justAgentsQuestion = 'Show Just UserAgents';
                    if (true === $justAgents) {
                        $justAgentsQuestion = 'Show Full Diff';
                    }

                    $questions = ['Change Test Suite', 'Change Parser', $justAgentsQuestion, 'Back to Main Menu'];

                    if (1 >= count($this->options['tests'])) {
                        unset($questions[array_search('Change Test Suite', $questions)]);
                    }

                    if (1 >= count($this->options['parsers'])) {
                        unset($questions[array_search('Change Parser', $questions)]);
                    }

                    // Re-index
                    $questions = array_values($questions);

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        $questions,
                        count($questions) - 1
                    );

                    $answer = $questionHelper->ask($this->input, $this->output, $question);
                } while ('Back to Main Menu' !== $answer);

                $this->showMenu();

                break;
            case 'View property comparison':
                $answer = '';
                do {
                    if (!isset($selectedTest) || 'Change Test Suite' === $answer) {
                        $selectedTest = $this->changePropertyDiffTestSuite();
                    }

                    if (!isset($section) || 'Change Section' === $answer) {
                        $section = $this->changePropertyDiffSection();
                    }

                    if (!isset($property) || 'Change Section' === $answer || 'Change Property' === $answer) {
                        $property = $this->changePropertyDiffProperty($section);
                    }

                    if (!isset($justFails) || 'Show All' === $answer) {
                        $justFails = false;
                    } elseif ('Just Show Failures' === $answer) {
                        $justFails = true;
                    }

                    $this->showComparison($selectedTest, $section, $property, $justFails);

                    $justFailureQuestion = 'Just Show Failures';
                    if (true === $justFails) {
                        $justFailureQuestion = 'Show All';
                    }

                    $questions = [
                        'Export User Agents',
                        'Change Section',
                        'Change Property',
                        'Change Test Suite',
                        $justFailureQuestion,
                        'Back to Main Menu',
                    ];

                    if (1 >= count($this->options['tests'])) {
                        unset($questions[array_search('Change Test Suite', $questions)]);
                    }

                    if ('browser' === $section || 'platform' === $section) {
                        unset($questions[array_search('Change Property', $questions)]);
                    }

                    $questions = array_values($questions);

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        $questions,
                        count($questions) - 1
                    );

                    $answer = $questionHelper->ask($this->input, $this->output, $question);

                    if ('Export User Agents' === $answer) {
                        $question     = new Question('Type the expected value to view the agents parsed:');
                        $autoComplete = array_merge(['[no value]'], array_keys($this->comparison[$selectedTest][$section][$property]));
                        sort($autoComplete);
                        $question->setAutocompleterValues($autoComplete);

                        $value = $questionHelper->ask($this->input, $this->output, $question);

                        $this->showComparisonAgents($selectedTest, $section, $property, $value);

                        $question = new Question('Press enter to continue', 'yes');
                        $questionHelper->ask($this->input, $this->output, $question);
                    }
                } while ('Back to Main Menu' !== $answer);

                $this->showMenu();

                break;
            case 'Exit':
                $this->output->writeln('Goodbye!');

                break;
        }
    }

    private function showComparisonAgents($test, $section, $property, $value): void
    {
        if ('[no value]' === $value) {
            $value = '';
        }

        if (isset($this->comparison[$test][$section][$property][$value])) {
            $agents = array_flip($this->agents);

            $this->output->writeln('<comment>Showing ' . count($this->comparison[$test][$section][$property][$value]['expected']['agents']) . ' user agents</comment>');

            $this->output->writeln('');
            foreach ($this->comparison[$test][$section][$property][$value]['expected']['agents'] as $agentId) {
                $this->output->writeln($agents[$agentId]);
            }
            $this->output->writeln('');
        } else {
            $this->output->writeln('<error>There were no agents processed with that property value</error>');
        }
    }

    private function analyzeFailures($test, $parser, $justAgents = false): void
    {
        if (!empty($this->failures[$test][$parser])) {
            $table = new Table($this->output);
            $table->setHeaders([
                [new TableCell('UserAgent', ['colspan' => 3])],
                ['Browser', 'Platform', 'Device'],
            ]);

            $rows = [];
            foreach ($this->failures[$test][$parser] as $agent => $failData) {
                $rows[] = [new TableCell((string) $agent, ['colspan' => 3])];
                $rows[] = [
                    isset($failData['browser']) ? $this->outputDiff($failData['browser']) : '',
                    isset($failData['platform']) ? $this->outputDiff($failData['platform']) : '',
                    isset($failData['device']) ? $this->outputDiff($failData['device']) : '',
                ];
                $rows[] = new TableSeparator();

                if (true === $justAgents) {
                    $this->output->writeln($agent);
                }
            }

            array_pop($rows);

            $table->setRows($rows);
            if (false === $justAgents) {
                $table->render();
            }
        } else {
            $this->output->writeln(
                '<error>There were no failures for the ' . $parser . ' parser for the ' . $test . ' test suite</error>'
            );
        }
    }

    private function showComparison($test, $compareKey, $compareSubKey, $justFails = false): void
    {
        if (!empty($this->comparison[$test][$compareKey][$compareSubKey])) {
            ksort($this->comparison[$test][$compareKey][$compareSubKey]);
            uasort($this->comparison[$test][$compareKey][$compareSubKey], static function ($a, $b) {
                if ($a['expected']['count'] === $b['expected']['count']) {
                    return 0;
                }

                return ($a['expected']['count'] > $b['expected']['count']) ? -1 : 1;
            });

            $table = new Table($this->output);

            $headers = [' Expected ' . ucfirst($compareKey) . ' ' . ucfirst($compareSubKey)];

            foreach ($this->options['parsers'] as $parser => $data) {
                $headers[] = $parser;
            }

            $table->setHeaders($headers);

            $rows = [];

            foreach ($this->comparison[$test][$compareKey][$compareSubKey] as $expected => $compareRow) {
                if (true === $justFails && empty($compareRow['expected']['hasFailures'])) {
                    continue;
                }

                $max = 0;
                foreach ($compareRow as $child) {
                    if (count($child) > $max) {
                        $max = count($child);
                    }
                }

                foreach (array_keys($this->options['parsers']) as $parser) {
                    if (isset($compareRow[$parser])) {
                        uasort($compareRow[$parser], static function ($a, $b) {
                            if ($a['count'] === $b['count']) {
                                return 0;
                            }

                            return ($a['count'] > $b['count']) ? -1 : 1;
                        });
                    }
                }

                for ($i = 0; $i < $max; ++$i) {
                    $row     = [];
                    $parsers = array_merge(['expected'], array_keys($this->options['parsers']));

                    foreach ($parsers as $parser) {
                        if ('expected' === $parser) {
                            if (0 === $i) {
                                $row[] = ('' === $expected ? '[no value]' : $expected) . ' <comment>(' . $compareRow['expected']['count'] . ')</comment>';
                            } else {
                                $row[] = ' ';
                            }
                        } else {
                            if (isset($compareRow[$parser]) && 0 < count($compareRow[$parser])) {
                                $key      = current(array_keys($compareRow[$parser]));
                                $quantity = array_shift($compareRow[$parser]);
                                if ('[n/a]' === $expected || $key === $expected || '[n/a]' === $key) {
                                    $row[] = ('' === $key ? '[no value]' : $key) . ' <info>(' . $quantity['count'] . ')</info>';
                                } else {
                                    $row[] = ('' === $key ? '[no value]' : $key) . ' <fg=red>(' . $quantity['count'] . ')</>';
                                }
                            } else {
                                $row[] = ' ';
                            }
                        }
                    }

                    $rows[] = $row;
                }

                $rows[] = new TableSeparator();
            }

            array_pop($rows);

            $table->setRows($rows);
            $table->render();
        }
    }

    private function makeDiff($expected, $actual)
    {
        $result = [];

        if (!empty($expected)) {
            $diff = array_diff_assoc($expected, $actual);

            foreach ($diff as $field => $value) {
                // We can only compare the fields that aren't null in either expected or actual
                // to be "fair" to parsers that don't have all of the data (or have too much if the test
                // suite doesn't contain the properties that a parser may)
                if (null !== $actual[$field] && null !== $expected[$field]) {
                    $result[$field] = ['expected' => $value, 'actual' => $actual[$field]];
                }
            }
        }

        return $result;
    }

    private function calculateScore($expected, $actual, $possible = false)
    {
        $score = 0;

        foreach ($expected as $field => $value) {
            if (null !== $value) {
                // this happens if our possible score calculation is called
                if (true === $possible && null !== $actual[$field]) {
                    ++$score;
                } elseif ($value === $actual[$field]) {
                    ++$score;
                }
            }
        }

        return $score;
    }

    private function outputDiff($diff)
    {
        $output = '';

        if (!empty($diff)) {
            foreach ($diff as $field => $data) {
                $output .= $field . ': <fg=white;bg=green>' . $data['expected'] . '</> <fg=white;bg=red>' . $data['actual'] . '</> ';
            }
        }

        return $output;
    }
}

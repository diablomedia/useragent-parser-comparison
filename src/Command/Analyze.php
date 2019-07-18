<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\ksort;
use function Safe\sort;
use function Safe\uasort;
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
    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var array
     */
    private $comparison = [];

    /**
     * @var array
     */
    private $agents = [];

    /**
     * @var Table
     */
    private $summaryTable;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var array
     */
    private $failures = [];

    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Analyzes the data from test runs')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to analyze')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;

        /** @var string|null $run */
        $run = $input->getArgument('run');

        if (empty($run)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return 1;
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln('<error>No run directory found with that id (' . $run . ')</error>');

            return 1;
        }

        if (file_exists($this->runDir . '/' . $run . '/metadata.json')) {
            try {
                $contents = file_get_contents($this->runDir . '/' . $run . '/metadata.json');
            } catch (Exception $e) {
                $output->writeln(
                    '<error>Could not read file (' . $this->runDir . '/' . $run . '/metadata.json' . ')</error>'
                );

                return 2;
            }

            try {
                $this->options = json_decode($contents, true);
            } catch (Exception $e) {
                $output->writeln('<error>An error occured while parsing metadata for run ' . $run . '</error>');
            }
        } else {
            $output->writeln('<error>No options file found for this test run</error>');

            return 2;
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

            return 3;
        }

        $this->summaryTable = new Table($output);
        $this->summaryTable->setHeaders(['Parser', 'Version', 'Browser Results', 'Platform Results', 'Device Results', 'Time Taken', 'Accuracy Score']);
        $rows   = [];
        $totals = [];

        foreach ($tests as $testName => $testData) {
            $this->comparison[$testName] = [];

            $expectedFilename = $this->runDir . '/' . $run . '/expected/normalized/' . $testName . '.json';

            if (file_exists($expectedFilename)) {
                try {
                    $contents = file_get_contents($expectedFilename);
                } catch (Exception $e) {
                    $this->output->writeln('<error>Could not read file (' . $expectedFilename . ')</error>');
                    continue;
                }

                try {
                    $expectedResults = json_decode($contents, true);
                    $headerMessage   = '<fg=yellow>Parser comparison for ' . $testName . ' test suite' . (isset($testData['metadata']['version']) ? ' (' . $testData['metadata']['version'] . ')' : '') . '</>';
                } catch (Exception $e) {
                    $this->output->writeln('<error>An error occured while parsing file (' . $expectedFilename . '), skipping</error>');
                    continue;
                }
            } else {
                // When we aren't comparing to a test suite, the first parser's results become the expected results
                $expectedResults = ['tests' => []];
                $fileName        = $this->runDir . '/' . $run . '/results/' . array_keys($this->options['parsers'])[0] . '/normalized/' . $testName . '.json';
                try {
                    $contents = file_get_contents($fileName);
                } catch (Exception $e) {
                    $this->output->writeln('<error>Could not read file (' . $fileName . ')</error>');
                    continue;
                }

                try {
                    $testResult    = json_decode($contents, true);
                    $headerMessage = '<fg=yellow>Parser comparison for ' . $testName . ' file, using ' . array_keys($this->options['parsers'])[0] . ' results as expected</>';
                } catch (Exception $e) {
                    $this->output->writeln('<error>An error occured while parsing metadata for run ' . $run . '</error>');
                    continue;
                }

                foreach ($testResult['results'] as $data) {
                    $expectedResults['tests'][$data['useragent']] = $data['parsed'];
                }
            }

            if (!isset($expectedResults['tests']) || !is_array($expectedResults['tests']) || empty($expectedResults['tests'])) {
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
                    if ($compareKey === 'device') {
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

            $parserScores   = [];
            $possibleScores = [];

            foreach ($this->options['parsers'] as $parserName => $parserData) {
                if (!file_exists($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testName . '.json')) {
                    $this->output->writeln('<error>No output found for the ' . $parserName . ' parser, skipping</error>');

                    continue;
                }

                $fileName = $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testName . '.json';
                try {
                    $contents = file_get_contents($fileName);
                } catch (Exception $e) {
                    $this->output->writeln('<error>Could not read file (' . $fileName . '), skipping</error>');

                    continue;
                }

                try {
                    $testResult = json_decode($contents, true);
                } catch (Exception $e) {
                    $this->output->writeln('<error>An error occured while parsing file (' . $fileName . '), skipping</error>');

                    continue;
                }

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
                        if ($compareKey === 'device') {
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
                                if ($expectedValue !== '[n/a]' && $actualValue !== '[n/a]') {
                                    $this->comparison[$testName][$compareKey][$compareSubKey][$expectedValue]['expected']['hasFailures'] = true;
                                }
                            }
                        }

                        if ($data['parsed'][$compareKey] !== $expected[$compareKey]) {
                            $diff = $this->makeDiff($expected[$compareKey], $data['parsed'][$compareKey]);
                            if (!empty($diff)) {
                                $failures[$compareKey] = $diff;
                            }
                        }

                        $score         = $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey]);
                        $possibleScore = $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey], true);

                        $passFail[$compareKey]['pass'] += $score;
                        $passFail[$compareKey]['fail'] += $possibleScore - $score;

                        $parserScores[$parserName][$testName]   += $score;
                        $possibleScores[$parserName][$testName] += $possibleScore;
                    }

                    if (!empty($failures)) {
                        $this->failures[$testName][$parserName][$data['useragent']] = $failures;
                    }
                }

                if (array_sum($passFail['browser']) === 0) {
                    $browserContent = '<fg=white;bg=blue>-</>';
                } else {
                    $browserPercentage = $passFail['browser']['pass'] / array_sum($passFail['browser']) * 100;
                    $browserContent    = $this->colorByPercent($browserPercentage) . $passFail['browser']['pass'] . '/' . array_sum($passFail['browser']) . ' ' . round($browserPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($passFail['platform']) === 0) {
                    $platformContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformPercentage = $passFail['platform']['pass'] / array_sum($passFail['platform']) * 100;
                    $platformContent    = $this->colorByPercent($platformPercentage) . $passFail['platform']['pass'] . '/' . array_sum($passFail['platform']) . ' ' . round($platformPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($passFail['device']) === 0) {
                    $deviceContent = '<fg=white;bg=blue>-</>';
                } else {
                    $devicePercentage = $passFail['device']['pass'] / array_sum($passFail['device']) * 100;
                    $deviceContent    = $this->colorByPercent($devicePercentage) . $passFail['device']['pass'] . '/' . array_sum($passFail['device']) . ' ' . round($devicePercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if ($possibleScores[$parserName][$testName] === 0) {
                    $summaryContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryPercentage = $parserScores[$parserName][$testName] / $possibleScores[$parserName][$testName] * 100;
                    $summaryContent    = $this->colorByPercent($summaryPercentage) . $parserScores[$parserName][$testName] . '/' . $possibleScores[$parserName][$testName] . ' ' . round($summaryPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                $rows[] = [
                    $parserName,
                    $parserData['metadata']['version'] ?? 'n/a',
                    $browserContent,
                    $platformContent,
                    $deviceContent,
                    round($testResult['parse_time'] + $testResult['init_time'], 3) . 's',
                    $summaryContent,
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

        if (count($this->options['tests']) > 1) {
            $rows[] = [new TableCell('<fg=yellow>Total for all Test suites</>', ['colspan' => 6])];
            $rows[] = new TableSeparator();

            foreach ($totals as $parser => $total) {
                if (array_sum($total['browser']) === 0) {
                    $browserContent = '<fg=white;bg=blue>-</>';
                } else {
                    $browserPercentage = $total['browser']['pass'] / array_sum($total['browser']) * 100;
                    $browserContent    = $this->colorByPercent($browserPercentage) . $total['browser']['pass'] . '/' . array_sum($total['browser']) . ' ' . round($browserPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($total['platform']) === 0) {
                    $platformContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformPercentage = $total['platform']['pass'] / array_sum($total['platform']) * 100;
                    $platformContent    = $this->colorByPercent($platformPercentage) . $total['platform']['pass'] . '/' . array_sum($total['platform']) . ' ' . round($platformPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($total['device']) === 0) {
                    $deviceContent = '<fg=white;bg=blue>-</>';
                } else {
                    $devicePercentage = $total['device']['pass'] / array_sum($total['device']) * 100;
                    $deviceContent    = $this->colorByPercent($devicePercentage) . $total['device']['pass'] . '/' . array_sum($total['device']) . ' ' . round($devicePercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if ($total['score']['possible'] === 0) {
                    $summaryContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryPercentage = $total['score']['earned'] / $total['score']['possible'] * 100;
                    $summaryContent    = $this->colorByPercent($summaryPercentage) . $total['score']['earned'] . '/' . $total['score']['possible'] . ' ' . round($summaryPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                $rows[] = [
                    $parser,
                    isset($this->options['parsers'][$parser]['metadata']['version']) ? $this->options['parsers'][$parser]['metadata']['version'] : 'n/a',
                    $browserContent,
                    $platformContent,
                    $deviceContent,
                    round($total['time'], 3) . 's',
                    $summaryContent,
                ];
            }

            $rows[] = new TableSeparator();
        }

        array_pop($rows);

        $this->summaryTable->setRows($rows);
        $this->showSummary();

        $this->showMenu();

        return 0;
    }

    private function showSummary(): void
    {
        $this->summaryTable->render();
    }

    private function changePropertyDiffTestSuite(): string
    {
        $questionHelper = $this->getHelper('question');

        if (count($this->options['tests']) > 1) {
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

    private function changePropertyDiffSection(): string
    {
        $questionHelper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            'Which Section?',
            ['browser', 'platform', 'device']
        );
        $section = $questionHelper->ask($this->input, $this->output, $question);

        return $section;
    }

    private function changePropertyDiffProperty(string $section): string
    {
        $questionHelper = $this->getHelper('question');
        $subs           = [];

        switch ($section) {
            case 'browser':
            case 'platform':
                $subs = ['name'];

                break;
            case 'device':
                $subs = ['name', 'brand', 'type'];

                break;
        }

        if (count($subs) > 1) {
            $question = new ChoiceQuestion(
                'Which Property?',
                $subs
            );
            $property = $questionHelper->ask($this->input, $this->output, $question);
        } elseif (count($subs) === 1) {
            $property = reset($subs);
        } else {
            $property = 'name';
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
                    if (!isset($selectedTest) || $answer === 'Change Test Suite') {
                        if (count($this->options['tests']) > 1) {
                            $question = new ChoiceQuestion(
                                'Which test suite?',
                                array_keys($this->options['tests'])
                            );

                            $selectedTest = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedTest = array_keys($this->options['tests'])[0];
                        }
                    }

                    if (!isset($selectedParser) || $answer === 'Change Parser') {
                        if (count($this->options['parsers']) > 1) {
                            $question = new ChoiceQuestion(
                                'Which parser?',
                                array_keys($this->options['parsers'])
                            );

                            $selectedParser = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedParser = array_keys($this->options['parsers'])[0];
                        }
                    }

                    if (!isset($justAgents) || $answer === 'Show Full Diff') {
                        $justAgents = false;
                    } elseif ($answer === 'Show Just UserAgents') {
                        $justAgents = true;
                    }

                    $this->analyzeFailures($selectedTest, $selectedParser, $justAgents);

                    $justAgentsQuestion = 'Show Just UserAgents';
                    if ($justAgents === true) {
                        $justAgentsQuestion = 'Show Full Diff';
                    }

                    $questions = ['Change Test Suite', 'Change Parser', $justAgentsQuestion, 'Back to Main Menu'];

                    if (count($this->options['tests']) <= 1) {
                        unset($questions[array_search('Change Test Suite', $questions)]);
                    }

                    if (count($this->options['parsers']) <= 1) {
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
                } while ($answer !== 'Back to Main Menu');

                $this->showMenu();

                break;
            case 'View property comparison':
                $answer = '';
                do {
                    if (!isset($selectedTest) || $answer === 'Change Test Suite') {
                        $selectedTest = $this->changePropertyDiffTestSuite();
                    }

                    if (!isset($section) || $answer === 'Change Section') {
                        $section = $this->changePropertyDiffSection();
                    }

                    if (!isset($property) || $answer === 'Change Section' || $answer === 'Change Property') {
                        $property = $this->changePropertyDiffProperty($section);
                    }

                    if (!isset($justFails) || $answer === 'Show All') {
                        $justFails = false;
                    } elseif ($answer === 'Just Show Failures') {
                        $justFails = true;
                    }

                    $this->showComparison($selectedTest, $section, $property, $justFails);

                    $justFailureQuestion = 'Just Show Failures';
                    if ($justFails === true) {
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

                    if (count($this->options['tests']) <= 1) {
                        unset($questions[array_search('Change Test Suite', $questions)]);
                    }

                    if ($section === 'browser' || $section === 'platform') {
                        unset($questions[array_search('Change Property', $questions)]);
                    }

                    $questions = array_values($questions);

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        $questions,
                        count($questions) - 1
                    );

                    $answer = $questionHelper->ask($this->input, $this->output, $question);

                    if ($answer === 'Export User Agents') {
                        $question     = new Question('Type the expected value to view the agents parsed:');
                        $autoComplete = array_merge(['[no value]'], array_keys($this->comparison[$selectedTest][$section][$property]));
                        sort($autoComplete);
                        $question->setAutocompleterValues($autoComplete);

                        $value = $questionHelper->ask($this->input, $this->output, $question);

                        $this->showComparisonAgents($selectedTest, $section, $property, $value);

                        $question = new Question('Press enter to continue', 'yes');
                        $questionHelper->ask($this->input, $this->output, $question);
                    }
                } while ($answer !== 'Back to Main Menu');

                $this->showMenu();

                break;
            case 'Exit':
                $this->output->writeln('Goodbye!');

                break;
        }
    }

    private function showComparisonAgents(string $test, string $section, string $property, string $value): void
    {
        if ($value === '[no value]') {
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

    private function analyzeFailures(string $test, string $parser, bool $justAgents = false): void
    {
        if (empty($this->failures[$test][$parser])) {
            $this->output->writeln(
                '<error>There were no failures for the ' . $parser . ' parser for the ' . $test . ' test suite</error>'
            );

            return;
        }

        $output = "<?php\n";

        $table = new Table($this->output);
        $table->setColumnWidth(0, 50);
        $table->setColumnMaxWidth(0, 50);
        $table->setColumnWidth(1, 50);
        $table->setColumnMaxWidth(1, 50);
        $table->setColumnWidth(2, 50);
        $table->setColumnMaxWidth(2, 50);
        $table->setStyle('box');

        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => 3])],
            [new TableCell('Browser'), new TableCell('Platform'), new TableCell('Device')],
        ]);

        $rows = [];
        foreach ($this->failures[$test][$parser] as $agent => $failData) {
            if ($justAgents === true) {
                $this->output->writeln($agent);
            } else {
                $rows[] = [new TableCell((string) $agent, ['colspan' => 3])];
                $rows[] = [
                    new TableCell(isset($failData['browser']) ? $this->outputDiff($failData['browser']) : ''),
                    new TableCell(isset($failData['platform']) ? $this->outputDiff($failData['platform']) : ''),
                    new TableCell(isset($failData['device']) ? $this->outputDiff($failData['device']) : ''),
                ];
                $rows[] = new TableSeparator();
            }
        }

        if ($justAgents === false) {
            array_pop($rows);

            $table->setRows($rows);

            $table->render();
        }

        file_put_contents('output.txt', $output);
    }

    private function showComparison(string $test, string $compareKey, string $compareSubKey, bool $justFails = false): void
    {
        if (!empty($this->comparison[$test][$compareKey][$compareSubKey])) {
            ksort($this->comparison[$test][$compareKey][$compareSubKey]);
            uasort($this->comparison[$test][$compareKey][$compareSubKey], static function (array $a, array $b): int {
                if ($a['expected']['count'] === $b['expected']['count']) {
                    return 0;
                }

                return ($a['expected']['count'] > $b['expected']['count']) ? -1 : 1;
            });

            $table = new Table($this->output);

            $headers = [' Expected ' . ucfirst($compareKey) . ' ' . ucfirst($compareSubKey)];

            foreach (array_keys($this->options['parsers']) as $parser) {
                $headers[] = $parser;
            }

            $table->setHeaders($headers);

            $rows = [];

            foreach ($this->comparison[$test][$compareKey][$compareSubKey] as $expected => $compareRow) {
                if ($justFails === true && empty($compareRow['expected']['hasFailures'])) {
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
                        uasort($compareRow[$parser], static function (array $a, array $b): int {
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
                        if ($parser === 'expected') {
                            if ($i === 0) {
                                $row[] = ($expected === '' ? '[no value]' : $expected) . ' <comment>(' . $compareRow['expected']['count'] . ')</comment>';
                            } else {
                                $row[] = ' ';
                            }
                        } else {
                            if (isset($compareRow[$parser]) && count($compareRow[$parser]) > 0) {
                                $key      = current(array_keys($compareRow[$parser]));
                                $quantity = array_shift($compareRow[$parser]);
                                if ($expected === '[n/a]' || $key === $expected || $key === '[n/a]') {
                                    $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=green>(' . $quantity['count'] . ')</>';
                                } else {
                                    $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=red>(' . $quantity['count'] . ')</>';
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

    private function makeDiff(array $expected, array $actual): array
    {
        if (empty($expected)) {
            return [];
        }

        $result = [];
        $diff   = array_diff_assoc($expected, $actual);

        foreach ($diff as $field => $value) {
            // We can only compare the fields that aren't null in either expected or actual
            // to be "fair" to parsers that don't have all of the data (or have too much if the test
            // suite doesn't contain the properties that a parser may)
            if (isset($actual[$field], $expected[$field])) {
                $result[$field] = ['expected' => $value, 'actual' => $actual[$field]];
            }
        }

        return $result;
    }

    private function calculateScore(array $expected, array $actual, bool $possible = false): int
    {
        $score = 0;

        foreach ($expected as $field => $value) {
            if ($value === null) {
                continue;
            }

            // this happens if our possible score calculation is called
            if ($possible === true && $actual[$field] !== null) {
                ++$score;
            } elseif ($value === $actual[$field]) {
                ++$score;
            }
        }

        return $score;
    }

    private function outputDiff(array $diff): string
    {
        if (empty($diff)) {
            return '';
        }

        $output = '';

        foreach ($diff as $field => $data) {
            $output .= $field . ': <fg=white;bg=green>' . $data['expected'] . '</> <fg=white;bg=red>' . $data['actual'] . '</> ';
        }

        return $output;
    }

    private function colorByPercent(float $percent): string
    {
        if ($percent >= 100.0) {
            return '<fg=green;bg=black;options=bold>';
        }

        if ($percent >= 95.0) {
            return '<fg=green;bg=black>';
        }

        if ($percent >= 90.0) {
            return '<fg=yellow;bg=black;options=bold>';
        }

        if ($percent >= 85.0) {
            return '<fg=yellow;bg=black>';
        }

        if ($percent < 50.0) {
            return '<fg=red;bg=black>';
        }

        return '</>';
    }
}

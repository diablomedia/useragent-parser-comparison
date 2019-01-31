<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use ExceptionalJSON\DecodeErrorException;
use FilesystemIterator;
use JsonClass\Json;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Parsers extends Helper
{
    /**
     * @var array
     */
    private $parsers = [];

    /**
     * @var array
     */
    private $selectedParsers = [];

    /**
     * @var string
     */
    private $parsersDir = __DIR__ . '/../../../parsers';

    public function getName(): string
    {
        return 'parsers';
    }

    public function getParsers(InputInterface $input, OutputInterface $output, bool $multiple = true): array
    {
        /** @var SplFileInfo $parserDir */
        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            $metadata = [];

            if (file_exists($parserDir->getPathname() . '/metadata.json')) {
                $contents = file_get_contents($parserDir->getPathname() . '/metadata.json');
                if ($contents !== false) {
                    try {
                        $metadata = (new Json())->decode($contents, true);
                    } catch (DecodeErrorException $e) {
                        $output->writeln('<error>An error occured while parsing metadata for parser ' . $parserDir->getPathname() . '</error>');
                    }
                }
            }

            $this->parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathname(),
                'metadata' => $metadata,
                'parse'    => static function (string $file, bool $benchmark = false) use ($parserDir): ?array {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if ($benchmark === true) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec($parserDir->getPathname() . '/parse.sh ' . implode(' ', $args));

                    if ($result !== null) {
                        $result = trim($result);

                        try {
                            $result = (new Json())->decode($result, true);
                        } catch (DecodeErrorException $e) {
                            return null;
                        }
                    }

                    return $result;
                },
            ];
        }

        $rows  = [];
        $names = [];

        ksort($this->parsers);

        foreach ($this->parsers as $name => $data) {
            $rows[] = [
                $data['metadata']['name'] ?? $name,
                $data['metadata']['language'] ?? '',
                $data['metadata']['data_source'] ?? '',
            ];
            $names[$data['metadata']['name'] ?? $name] = $name;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Language', 'Data Source']);
        $table->setRows($rows);
        $table->render();

        $questions = array_keys($names);
        sort($questions);

        if ($multiple === true) {
            $questions[] = 'All Parsers';
        }

        $helper = $this->helperSet->get('question');

        if ($multiple === true) {
            $questionText = 'Choose which parsers to use, separate multiple with commas (press enter to use all)';
            $default      = count($questions) - 1;
        } else {
            $questionText = 'Select the parser to use';
            $default      = null;
        }

        $question = new ChoiceQuestion(
            $questionText,
            $questions,
            $default
        );

        if ($multiple === true) {
            $question->setMultiselect(true);
        }

        $answers = $helper->ask($input, $output, $question);

        $answers = (array) $answers;

        foreach ($answers as $name) {
            if ($name === 'All Parsers') {
                $this->selectedParsers = $this->parsers;

                break;
            }

            $this->selectedParsers[$names[$name]] = $this->parsers[$names[$name]];
        }

        return $this->selectedParsers;
    }
}

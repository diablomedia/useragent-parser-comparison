<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use FilesystemIterator;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
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
        $jsonParser = new JsonParser();

        /** @var SplFileInfo $parserDir */
        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            if (file_exists($parserDir->getPathname() . '/metadata.json')) {
                try {
                    $metadata = $jsonParser->parse(
                        file_get_contents($parserDir->getPathname() . '/metadata.json'),
                        JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                    );
                } catch (ParsingException $e) {
                    $output->writeln('<error>There was an error with the metadata from the ' . $parserDir->getPathname() . ' parser.</error>');
                    $metadata = [];
                }
            } else {
                $metadata = [];
            }

            $this->parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathname(),
                'metadata' => $metadata,
                'parse'    => static function (string $file, bool $benchmark = false) use ($parserDir, $jsonParser, $output): ?array {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if ($benchmark === true) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec($parserDir->getPathname() . '/parse.sh ' . implode(' ', $args));

                    if (null === $result) {
                        return null;
                    }

                    $result = trim($result);

                    try {
                        $result = $jsonParser->parse(
                            $result,
                            JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                        );
                    } catch (ParsingException $e) {
                        $output->writeln(
                            sprintf(
                                '<error>There was an error with the result from the %s parser in the file %s.</error>',
                                $parserDir->getPathname(),
                                $file
                            )
                        );
                        return null;
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

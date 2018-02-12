<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Parsers extends Helper
{
    private $parsers = [];

    private $selectedParsers = [];

    private $parsersDir = __DIR__ . '/../../../parsers';

    public function getName()
    {
        return 'parsers';
    }

    public function getParsers(InputInterface $input, OutputInterface $output, $multiple = true)
    {
        foreach (new \FilesystemIterator($this->parsersDir) as $parserDir) {
            if (file_exists($parserDir->getPathName() . '/metadata.json')) {
                $metadata = json_decode(file_get_contents($parserDir->getPathName() . '/metadata.json'), true);
            } else {
                $metadata = [];
            }

            $this->parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathName(),
                'metadata' => $metadata,
                'parse'    => static function ($file, $benchmark = false) use ($parserDir) {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if (true === $benchmark) {
                        $args[] = '--benchmark';
                    }

                    $file = realpath(getcwd() . '/' . $file);

                    $result = shell_exec($parserDir->getPathName() . '/parse.sh ' . implode(' ', $args));

                    if (null !== $result) {
                        $result = trim($result);

                        $result = json_decode($result, true);

                        if (JSON_ERROR_NONE !== json_last_error()) {
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

        if (true === $multiple) {
            $questions[] = 'All Parsers';
        }

        $helper = $this->helperSet->get('question');

        if (true === $multiple) {
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

        if (true === $multiple) {
            $question->setMultiselect(true);
        }

        $answers = $helper->ask($input, $output, $question);

        $answers = (array) $answers;

        foreach ($answers as $name) {
            if ('All Parsers' === $name) {
                $this->selectedParsers = $this->parsers;

                break;
            }

            $this->selectedParsers[$names[$name]] = $this->parsers[$names[$name]];
        }

        return $this->selectedParsers;
    }
}

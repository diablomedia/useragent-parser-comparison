<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compare extends Command
{
    protected function configure(): void
    {
        $this->setName('compare')
            ->setDescription('Runs tests, normalizes the results then analyzes the results')
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to a file to use as the source of useragents rather than test suites')
            ->setHelp('This command is a "meta" command that will execute the Test, Normalize and Analyze commands in order');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        if ($file) {
            $command    = $this->getApplication()->find('parse');
            $name       = date('YmdHis');
            $parseInput = new ArrayInput([
                'command'     => 'parse',
                'file'        => $file,
                'run'         => $name,
                '--no-output' => true,
            ]);
            $returnCode = $command->run($parseInput, $output);

            if ($returnCode > 0) {
                $output->writeln('<error>There was an error executing the "parse" command, cannot continue.</error>');

                return $returnCode;
            }
        } else {
            $command = $this->getApplication()->find('test');
            $name    = date('YmdHis');

            $arguments = [
                'command' => 'test',
                'run'     => $name,
            ];

            $testInput  = new ArrayInput($arguments);
            $returnCode = $command->run($testInput, $output);

            if ($returnCode > 0) {
                $output->writeln('<error>There was an error executing the "test" command, cannot continue.</error>');

                return $returnCode;
            }
        }

        $command   = $this->getApplication()->find('normalize');
        $arguments = [
            'command' => 'normalize',
            'run'     => $name,
        ];

        $normalizeInput = new ArrayInput($arguments);
        $returnCode     = $command->run($normalizeInput, $output);

        if ($returnCode > 0) {
            $output->writeln('<error>There was an error executing the "normalize" command, cannot continue.</error>');

            return $returnCode;
        }

        $command   = $this->getApplication()->find('analyze');
        $arguments = [
            'command' => 'analyze',
            'run'     => $name,
        ];

        $analyzeInput = new ArrayInput($arguments);
        $returnCode   = $command->run($analyzeInput, $output);

        if ($returnCode > 0) {
            $output->writeln('<error>There was an error executing the "analyze" command, cannot continue.</error>');

            return $returnCode;
        }

        return 0;
    }
}

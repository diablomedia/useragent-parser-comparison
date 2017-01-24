<?php

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;

class Compare extends Command
{
    protected function configure()
    {
        $this->setName('compare')
            ->setDescription('Runs tests, normalizes the results then analyzes the results')
            ->setHelp('This command is a "meta" command that will execute the Test, Normalize and Analyze commands in order');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('test');

        $name = date('YmdHis');

        $arguments = [
            'command' => 'test',
            'name'    => $name,
        ];

        $testInput  = new ArrayInput($arguments);
        $returnCode = $command->run($testInput, $output);

        if ($returnCode > 0) {
            $output->writeln('<error>There was an error executing the "test" command, cannot continue.</error>');

            return;
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

            return;
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

            return;
        }
    }
}

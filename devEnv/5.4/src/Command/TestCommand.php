<?php
declare(strict_types=1);


namespace App\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\UrlHelper;

class TestCommand extends Command
{
    protected UrlHelper $helper;


    public function __construct(UrlHelper $service)
    {
        parent::__construct('app:test');
        $this->helper = $service;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->helper->getRelativePath('/foo/bar'));

        return 0;
    }

}
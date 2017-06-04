<?php
/**
 * Created by PhpStorm.
 * User: piet
 * Date: 4-6-17
 * Time: 12:13
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class BuildCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('app:build')
            ->setDescription('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $this->getContainer()->getParameter('kernel.root_dir');
        $data = json_decode(file_get_contents($root . '/../.chars'), true);
        $meta = json_decode(file_get_contents($root . '/../.meta'), true);

        foreach($data as $char => $tables) {
            $output->writeln("Creating the view for $char");
            file_put_contents(
                "$root/../$char.html",
                $this->getContainer()->get('twig')->render('@App/char.html.twig', [
                    'meta' => $meta,
                    'tables' => $tables,
                    'characters' => array_keys($data)
                ])
            );
        }

        file_put_contents(
            "$root/../index.html",
            $this->getContainer()->get('twig')->render('@App/index.html.twig', [
                'meta' => $meta,
                'characters' => array_keys($data)
            ])
        );
    }
}
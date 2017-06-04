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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class RunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('app:run')
            ->addArgument('char', InputArgument::OPTIONAL)
            ->addOption('no-cache', null, null, 'Dont cache remote content')
            ->setDescription('');
    }

    private $host = 'http://rbnorway.org';

    /**
     * @var bool
     */
    private $no_cache;

    /**
     * @param $url
     * @return Crawler
     */
    function getCrawler($url)
    {
        $cache = new FilesystemAdapter();
        $content = $cache->getItem(preg_replace('/[^a-z]/', '', $url));

        if (!$content->isHit() || $this->no_cache) {
            $raw = file_get_contents($url);
            $content->set($raw);
            $cache->save($content);
        }

        $crawler = new Crawler();
        $c = $content->get();
        $c = preg_replace('/[\x00-\x1F\x7F]/u', '', $c);
        $crawler->addHtmlContent($c);
        return $crawler;
    }

    function normalizeUrl($url) {
        if (strpos($url, 'http') !== false) {
            return $url;
        }
        return $this->host . '/' . $url;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $this->getContainer()->getParameter('kernel.root_dir');

        $this->no_cache = $input->getOption('no-cache');

        if ($runForCharacter = $input->getArgument('char')) {
            $output->writeln("Refreshing just for $runForCharacter");
        }
        else {
            $output->writeln("Refreshing all characters");
        }

        $data = [];

        $crawler = $this->getCrawler($this->host . '/t7-frame-data/');

        $characters = $crawler->filter('#main div.page a')->reduce(function (Crawler $node, $i) {
            return strpos($node->attr('href'), 't7-frames') !== false;
        });

        $refreshCharacter = function($charUrl, $char) use (&$data)
        {
            $crawler = $this->getCrawler($charUrl);
            $tables = $crawler->filterXPath('//div[@id="content"]//table');

            foreach ($tables as $table) {
                $rows = $tables->filter('tr');
                $tdata = & $data[strtolower($char)][];
                foreach($rows as $row) {
                    $d = & $tdata[];
                    foreach((new Crawler($row))->filter('td') as $td) {
                        $d[] = $td->nodeValue;
                    }
                }
            }
        };

        /** @var \DOMElement $characterNode */
        foreach ($characters as $characterNode)
        {
            $href = $this->normalizeUrl($characterNode->getAttribute('href'));

            if ($runForCharacter && stripos($href, $runForCharacter) === false) {
                continue;
            }

            preg_match('/\/([^\/]+)\-t7/', $href, $matches);
            $character = $matches[1];

            $output->writeln("Fetching data for $character");
            $refreshCharacter($href, $character);
        }

        $existing = json_decode(file_get_contents("$root/../.chars"), true) ?: [];
        $existing = array_merge($existing, $data);
        file_put_contents($root . '/../.chars', json_encode($existing));

        $meta = ['fetched' => time()];
        file_put_contents($root . '/../.meta', json_encode($meta));
    }
}
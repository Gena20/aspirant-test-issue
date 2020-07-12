<?php
/**
 * 2019-06-28.
 */

declare(strict_types=1);

namespace App\Command;

use App\Entity\Movie;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class FetchDataCommand.
 */
class FetchDataCommand extends Command
{
    private const SOURCE = 'https://trailers.apple.com/trailers/home/rss/newtrailers.rss';
    private const AMOUNT = 10;
    private const MAX_AMOUNT = 20;

    /**
     * @var string
     */
    protected static string $defaultName = 'fetch:trailers';

    /**
     * @var ClientInterface
     */
    private ClientInterface $httpClient;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var string
     */
    private string $source;

    /**
     * @var int
     */
    private int $amount;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $doctrine;

    /**
     * FetchDataCommand constructor.
     * @param ClientInterface $httpClient
     * @param LoggerInterface $logger
     * @param EntityManagerInterface $em
     * @param string|null $name
     */
    public function __construct(ClientInterface $httpClient, LoggerInterface $logger, EntityManagerInterface $em, string $name = null)
    {
        parent::__construct($name);
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->doctrine = $em;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Fetch data from iTunes Movie Trailers')
            ->addArgument('amount', InputArgument::OPTIONAL, 'Define amount of trailers')
            ->addArgument('source', InputArgument::OPTIONAL, 'Overwrite source')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(sprintf('Start %s at %s', __CLASS__, (string) date_create()->format(DATE_ATOM)));
        $source = self::SOURCE;
        $amount = self::AMOUNT;
        if ($input->getArgument('source')) {
            $source = $input->getArgument('source');
        }
        if ($input->getArgument('amount')) {
            $amount = (int)$input->getArgument('amount');
        }

        if (!is_string($source)) {
            throw new RuntimeException('Source must be string');
        }
        if ($amount > self::MAX_AMOUNT || $amount <= 0) {
            throw new RuntimeException('Amount must be between 1 and 20');
        }
        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Fetch data from %s', $source));

        try {
            $response = $this->httpClient->sendRequest(new Request('GET', $source));
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException($e->getMessage());
        }
        if (($status = $response->getStatusCode()) !== 200) {
            throw new RuntimeException(sprintf('Response status is %d, expected %d', $status, 200));
        }
        $data = $response->getBody()->getContents();
        $this->processXml($data, $amount);

        $this->logger->info(sprintf('End %s at %s', __CLASS__, (string) date_create()->format(DATE_ATOM)));

        return 0;
    }

    /**
     * @param string $data
     *
     * @throws \Exception
     */
    protected function processXml(string $data, int $amount): void
    {
        $xml = (new \SimpleXMLElement($data))->children();
        $namespace = $xml->getNamespaces(true)['content'];

        if (!property_exists($xml, 'channel')) {
            throw new RuntimeException('Could not find \'channel\' element in feed');
        }
        for ($i = 0; $i < $amount; $i++) {
            $item = $xml->channel->item[$i];
            $trailer = $this->getMovie((string) $item->title)
                ->setTitle((string) $item->title)
                ->setDescription((string) $item->description)
                ->setLink((string) $item->link)
                ->setImage($this->parseImage((string) $item->children($namespace)->encoded))
                ->setPubDate($this->parseDate((string) $item->pubDate))
            ;

            $this->doctrine->persist($trailer);
        }

        $this->doctrine->flush();
    }

    /**
     * @param string $date
     * @return \DateTime
     * @throws \Exception
     */
    protected function parseDate(string $date): \DateTime
    {
        return new \DateTime($date);
    }

    /**
     * @param string $text
     * @return string
     */
    protected function parseImage(string $text): string
    {
        $matches = [];
        $s = preg_match('/<img.+src=[\'"]?([^\s\'">]*\/[^\/\s\'">]+\1?)/', $text, $matches);

        return count($matches) > 1 ? $matches[1] : '';
    }

    protected function getMovie(string $title): Movie
    {
        $item = $this->doctrine->getRepository(Movie::class)->findOneBy(['title' => $title]);

        if ($item === null) {
            $this->logger->info('Create new Movie', ['title' => $title]);
            $item = new Movie();
        } else {
            $this->logger->info('Move found', ['title' => $title]);
        }

        if (!($item instanceof Movie)) {
            throw new RuntimeException('Wrong type!');
        }

        return $item;
    }
}

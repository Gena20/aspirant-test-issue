<?php
/**
 * 2019-06-28.
 */

declare(strict_types=1);

namespace App\Command;

use App\Entity\Movie;
use App\Entity\User;
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
 * Class CreateUserCommand.
 */
class CreateUserCommand extends Command
{
    private const DEFAULT_ROLE = 'member';

    /**
     * @var string
     */
    protected static string $defaultName = 'create:user';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $doctrine;

    /**
     * @var array
     */
    private array $roles;

    /**
     * @var string
     */
    private string $username;

    /**
     * @var string
     */
    private string $password;

    /**
     * @var string
     */
    private string $role;


    /**
     * CreateUserCommand constructor.
     * @param LoggerInterface $logger
     * @param EntityManagerInterface $em
     * @param string|null $name
     */
    public function __construct(LoggerInterface $logger, EntityManagerInterface $em, string $name = null)
    {
        parent::__construct($name);
        $this->logger = $logger;
        $this->doctrine = $em;
        $this->roles = ['admin', 'member'];
    }

    public function configure(): void
    {
        $this
            ->setDescription('Create a user')
            ->addArgument('username', InputArgument::REQUIRED, 'Define username')
            ->addArgument('password', InputArgument::REQUIRED, 'Define user\'s password')
            ->addArgument('role', InputArgument::OPTIONAL, 'Define user\'s role')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(sprintf('Start %s at %s', __CLASS__, (string) date_create()->format(DATE_ATOM)));

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $role = self::DEFAULT_ROLE;
        if ($input->getArgument('role')) {
            $role = $input->getArgument('role');
            if (!in_array($role, $this->roles)) {
                throw new RuntimeException(sprintf('Role must be of of the %s', join(',', $this->roles)));
            }
        }
        $this->createUser($username, $password, $role);

        $this->logger->info(sprintf('End %s at %s', __CLASS__, (string) date_create()->format(DATE_ATOM)));

        return 0;
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $role
     */
    protected function createUser(string $username, string $password, string $role): void
    {
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['username' => $username]);

        if ($user === null) {
            $this->logger->info('Create new User', ['username' => $username]);
            $user = new User();
            $user
                ->setUsername($username)
                ->setPassword($password)
                ->setRole($role);
        } else {
            $this->logger->info(sprintf('User %s already exists', $username), ['username' => $username]);
        }

        if (!($user instanceof User)) {
            throw new RuntimeException('Wrong type!');
        }

        $this->doctrine->persist($user);
        $this->doctrine->flush();
    }
}

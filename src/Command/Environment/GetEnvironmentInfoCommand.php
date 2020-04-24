<?php

declare(strict_types=1);

/*
 * This file is part of Placeholder command-line tool.
 *
 * (c) Carl Alexander <contact@carlalexander.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Placeholder\Cli\Command\Environment;

use Placeholder\Cli\ApiClient;
use Placeholder\Cli\CliConfiguration;
use Placeholder\Cli\Command\AbstractCommand;
use Placeholder\Cli\Console\OutputStyle;
use Placeholder\Cli\ProjectConfiguration;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class GetEnvironmentInfoCommand extends AbstractCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    public const NAME = 'environment:info';

    /**
     * The placeholder project configuration.
     *
     * @var ProjectConfiguration
     */
    private $projectConfiguration;

    /**
     * Constructor.
     */
    public function __construct(ApiClient $apiClient, CliConfiguration $cliConfiguration, ProjectConfiguration $projectConfiguration)
    {
        parent::__construct($apiClient, $cliConfiguration);

        $this->projectConfiguration = $projectConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Get the environment URL and copy it to the clipboard')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The name of the environment');
    }

    /**
     * {@inheritdoc}
     */
    protected function perform(InputInterface $input, OutputStyle $output)
    {
        $environments = $input->getArgument('environment');

        if (!is_array($environments)) {
            $environments = (array) $environments;
        }

        if (empty($environments)) {
            $output->info('Listing information on all environments found in <comment>placeholder.yml</comment>');
            $environments = $this->projectConfiguration->getEnvironments();
        }

        foreach ($environments as $environment) {
            $this->displayEnvironmentTable($output, $environment);
        }
    }

    /**
     * Display the table with the environment information.
     */
    private function displayEnvironmentTable(OutputStyle $output, string $environment)
    {
        $database = $this->getEnvironmentDatabase($environment);
        $environment = $this->apiClient->getEnvironment($this->projectConfiguration->getProjectId(), $environment);
        $headers = ['Name', 'Domain', 'API', 'CDN'];
        $row = $environment->only(['name', 'vanity_domain'])->values()->all();

        $row[] = $environment['api']['domain'] ?? '<error>Unavailable</error>';

        if (empty($environment['content_delivery_network'])) {
            $row[] = '<error>Unavailable</error>';
        } elseif (!empty($environment['content_delivery_network']['domain'])) {
            $row[] = $environment['content_delivery_network']['domain'];
        } elseif (!empty($environment['content_delivery_network']['status'])) {
            $row[] = sprintf('<comment>%s</comment>', ucfirst($environment['content_delivery_network']['status']));
        }

        if (is_string($database)) {
            $headers[] = 'Database';
            $row[] = $database;
        }

        $output->horizontalTable($headers, [$row]);
    }

    /**
     * Get the information on the given environment's database.
     */
    private function getEnvironmentDatabase(string $environment): ?string
    {
        $environment = $this->projectConfiguration->getEnvironment($environment);

        if (empty($environment['database'])) {
            return null;
        }

        $databaseName = $environment['database']['host'] ?? $environment['database'];

        if (!is_string($databaseName)) {
            return null;
        }

        $database = $this->apiClient->getDatabases($this->getActiveTeamId())->firstWhere('name', $databaseName);

        if (!is_array($database)) {
            throw new RuntimeException(sprintf('There is no "%s" database on your current team', $databaseName));
        }

        if (!empty($database['status']) && 'available' !== $database['status']) {
            return sprintf('<comment>%s</comment>', ucfirst($database['status']));
        }

        return $database['endpoint'] ?? '<error>Unavailable</error>';
    }
}
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

namespace Placeholder\Cli;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Contracts\Support\Arrayable;
use Tightenco\Collect\Support\Collection;

class ProjectConfiguration implements Arrayable
{
    /**
     * The path to the configuration file.
     *
     * @var string
     */
    private $configurationFilePath;

    /**
     * The file system.
     *
     * @var Filesystem
     */
    private $filesystem;

    /**
     * The parsed configuration.
     *
     * @var Collection
     */
    private $configuration;

    /**
     * Constructor.
     */
    public function __construct(string $configurationFilePath, Filesystem $filesystem)
    {
        $this->configurationFilePath = $configurationFilePath;
        $this->filesystem = $filesystem;
        $this->configuration = $this->load($configurationFilePath);
    }

    /**
     * Save the options back to the configuration file when we're destroying the object.
     */
    public function __destruct()
    {
        if ($this->configuration->isEmpty()) {
            return;
        }

        $this->filesystem->dumpFile($this->configurationFilePath, Yaml::dump($this->configuration->all(), 20, 2));
    }

    /**
     * Creates a new configuration from the given project.
     *
     * Overwrites the existing project configuration.
     */
    public function createNew(Collection $project)
    {
        $this->configuration = $project->only(['id', 'name']);

        $this->configuration['environments'] = [
            'production' => [
                'memory' => 256,
            ],
            'staging' => [
                'memory' => 256,
            ],
        ];
    }

    /**
     * Delete the project configuration.
     */
    public function delete()
    {
        $this->configuration = new Collection();
        $this->filesystem->remove($this->configurationFilePath);
    }

    /**
     * Checks if the project configuration file exists.
     */
    public function exists(): bool
    {
        return $this->filesystem->exists($this->configurationFilePath);
    }

    /**
     * Get the project ID.
     */
    public function getProjectId(): int
    {
        return (int) $this->configuration->get('id');
    }

    /**
     * Get the project name.
     */
    public function getProjectName(): string
    {
        return (string) $this->configuration->get('name');
    }

    /**
     * Check if the given environment exists in the project configuration.
     */
    public function hasEnvironment(string $environment): bool
    {
        return isset($this->configuration['environments'][$environment]);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->configuration->toArray();
    }

    /**
     * Validates the loaded configuration file.
     */
    public function validate()
    {
        if (!$this->configuration->has('id')) {
            throw new RuntimeException('The project configuration file must have an "id"');
        }
    }

    /**
     * Load the options from the configuration file.
     */
    private function load(string $configurationFilePath): Collection
    {
        $configuration = [];

        if ($this->filesystem->exists($configurationFilePath)) {
            $configuration = Yaml::parse((string) file_get_contents($configurationFilePath));
        }

        if (!empty($configuration) && !is_array($configuration)) {
            throw new RuntimeException('Error parsing project configuration file');
        }

        return collect($configuration);
    }
}

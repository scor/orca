<?php

namespace Acquia\Orca\Fixture;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Parser;

/**
 * Provides access to packages specified in config.
 */
class PackageManager {

  /**
   * The packages config alter data, if provided.
   *
   * @var array
   */
  private $alterData = [];

  /**
   * The BLT package.
   *
   * @var \Acquia\Orca\Fixture\Package|null
   */
  private $blt;

  /**
   * The fixture.
   *
   * @var \Acquia\Orca\Fixture\Fixture
   */
  private $fixture;

  /**
   * The filesystem.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private $filesystem;

  /**
   * All defined packages keyed by package name.
   *
   * @var \Acquia\Orca\Fixture\Package[]
   */
  private $packages = [];

  /**
   * The YAML parser.
   *
   * @var \Symfony\Component\Yaml\Parser
   */
  private $parser;

  /**
   * The ORCA project directory.
   *
   * @var string
   */
  private $projectDir;

  /**
   * Constructs an instance.
   *
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   * @param \Acquia\Orca\Fixture\Fixture $fixture
   *   The fixture.
   * @param \Symfony\Component\Yaml\Parser $parser
   *   The YAML parser.
   * @param string $packages_config
   *   The path to the packages configuration file relative to the ORCA project
   *   directory.
   * @param string|null $packages_config_alter
   *   The path to an extra packages configuration file relative to the ORCA
   *   project directory whose contents will be merged into the main packages
   *   configuration.
   * @param string $project_dir
   *   The ORCA project directory.
   */
  public function __construct(Filesystem $filesystem, Fixture $fixture, Parser $parser, string $packages_config, ?string $packages_config_alter, string $project_dir) {
    $this->filesystem = $filesystem;
    $this->fixture = $fixture;
    $this->parser = $parser;
    $this->projectDir = $project_dir;
    $this->initializePackages($fixture, $packages_config, $packages_config_alter);
  }

  /**
   * Determines whether a given package exists.
   *
   * @param string $package_name
   *   The package name of the package in question, e.g., "drupal/example".
   *
   * @return bool
   *   TRUE if the given package exists or FALSE if not.
   */
  public function exists(string $package_name): bool {
    return array_key_exists($package_name, $this->packages);
  }

  /**
   * Gets a package by package name.
   *
   * @param string $package_name
   *   The package name.
   *
   * @return \Acquia\Orca\Fixture\Package
   *   The requested package.
   *
   * @throws \InvalidArgumentException
   *   If the requested package isn't found.
   */
  public function get(string $package_name): Package {
    if (empty($this->packages[$package_name])) {
      throw new \InvalidArgumentException(sprintf('No such package: %s', $package_name));
    }
    return $this->packages[$package_name];
  }

  /**
   * Gets an array of all packages.
   *
   * @return \Acquia\Orca\Fixture\Package[]|string[]
   *   An array of packages or package properties keyed by package name.
   */
  public function getAll(): array {
    return $this->packages;
  }

  /**
   * Gets the BLT package.
   *
   * BLT is a special case due to its foundational relationship to the fixture.
   * It must always be available by direct request, even if absent from the
   * active packages specification.
   *
   * @return \Acquia\Orca\Fixture\Package
   *   The BLT package.
   */
  public function getBlt(): Package {
    if (!$this->blt) {
      $this->initializeBlt();
    }
    return $this->blt;
  }

  /**
   * Gets the packages config alter data.
   *
   * @return array
   *   An array of data keyed by package name.
   */
  public function getAlterData(): array {
    return $this->alterData;
  }

  /**
   * Initializes the packages.
   *
   * @param \Acquia\Orca\Fixture\Fixture $fixture
   *   The fixture.
   * @param string $packages_config
   *   The path to the packages configuration file relative to the ORCA project
   *   directory.
   * @param string|null $packages_config_alter
   *   The path to an extra packages configuration file relative to the ORCA
   *   project directory whose contents will be merged into the main packages
   *   configuration.
   */
  private function initializePackages(Fixture $fixture, string $packages_config, ?string $packages_config_alter): void {
    $data = $this->parseYamlFile("{$this->projectDir}/{$packages_config}");
    if ($packages_config_alter) {
      $this->alterData = $this->parseYamlFile("{$this->projectDir}/{$packages_config_alter}");
      $data = array_merge($data, $this->alterData);
    }
    foreach ($data as $package_name => $datum) {
      // Skipping a null datum provides for a package to be effectively removed
      // from the active specification at runtime by setting its value to NULL
      // in the packages configuration alter file.
      if ($datum === NULL) {
        continue;
      }

      $package = new Package($datum, $fixture, $package_name, $this->projectDir);
      $this->packages[$package_name] = $package;
    }
    ksort($this->packages);
  }

  /**
   * Parses a given YAML file and returns the data.
   *
   * @param string $file
   *   The file to parse.
   *
   * @return array
   *   The parsed data.
   */
  private function parseYamlFile(string $file): array {
    if (!$this->filesystem->exists($file)) {
      throw new \LogicException("No such file: {$file}");
    }
    $data = $this->parser->parseFile($file);
    if (!is_array($data)) {
      throw new \LogicException("Incorrect schema in {$file}. See config/packages.yml.");
    }
    return $data;
  }

  /**
   * Initializes BLT.
   */
  private function initializeBlt(): void {
    $package_name = 'acquia/blt';

    // If it's in the active packages specification, use it.
    if ($this->exists($package_name)) {
      $this->blt = $this->get($package_name);
      return;
    }

    // Otherwise get it from the default specification.
    $default_packages_yaml = "{$this->projectDir}/config/packages.yml";
    $data = $this->parser->parseFile($default_packages_yaml);
    $this->blt = new Package($data[$package_name], $this->fixture, $package_name, $this->projectDir);
  }

}

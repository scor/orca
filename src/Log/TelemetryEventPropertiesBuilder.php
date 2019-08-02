<?php

namespace Acquia\Orca\Log;

use Acquia\Orca\Enum\TelemetryEventName;
use Acquia\Orca\Task\DeprecatedCodeScanner\PhpStanTask;
use Acquia\Orca\Task\StaticAnalysisTool\PhpLocTask;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds telemetry event properties.
 */
class TelemetryEventPropertiesBuilder {

  /**
   * The environment variables service.
   *
   * @var \Env
   */
  private $env;

  /**
   * The filesystem.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private $filesystem;

  /**
   * The ORCA project directory.
   *
   * @var string
   */
  private $projectDir;

  /**
   * The event properties.
   *
   * @var array
   */
  private $properties = [];

  /**
   * Constructs an instance.
   *
   * @param \Env $env
   *   The environment variables service.
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   * @param string $project_dir
   *   The ORCA project directory.
   */
  public function __construct(\Env $env, Filesystem $filesystem, string $project_dir) {
    $this->env = $env;
    $this->filesystem = $filesystem;
    $this->projectDir = $project_dir;
  }

  /**
   * Builds an array of event properties for the given event name.
   *
   * @param \Acquia\Orca\Enum\TelemetryEventName $name
   *   A telemetry event name.
   *
   * @return array
   *   An array of event properties.
   */
  public function build(TelemetryEventName $name): array {
    switch ($name->getValue()) {
      case TelemetryEventName::TRAVIS_CI_JOB:
        return $this->buildTravisCiJobProperties();

      case TelemetryEventName::TEST:
        return ['example' => TRUE];
    }
  }

  /**
   * Builds the event properties for the TRAVIS_CI_JOB event.
   *
   * @return array
   *   An array of properties.
   */
  protected function buildTravisCiJobProperties(): array {
    $this->properties = [];
    $this->addEnvironmentVariables();
    $this->addStaticAnalysisResults();
    $this->addDeprecationScanningResults();
    return $this->properties;
  }

  /**
   * Adds environment variables to the event properties.
   */
  protected function addEnvironmentVariables(): void {
    $properties = array_flip([
      'ORCA_JOB',
      // Built-in Travis CI environment variables.
      // @see https://docs.travis-ci.com/user/environment-variables/#default-environment-variables
      'TRAVIS_ALLOW_FAILURE',
      'TRAVIS_COMMIT',
      'TRAVIS_COMMIT_MESSAGE',
      'TRAVIS_JOB_ID',
      'TRAVIS_JOB_NAME',
      'TRAVIS_JOB_NUMBER',
      'TRAVIS_JOB_WEB_URL',
      'TRAVIS_PHP_VERSION',
      'TRAVIS_REPO_SLUG',
      'TRAVIS_TEST_RESULT',
    ]);
    array_walk($properties, function (&$value, $key) {
      $value = $this->env::get($key);
    });
    $this->properties = array_merge($this->properties, $properties);
  }

  /**
   * Adds static analysis results to the event properties.
   */
  private function addStaticAnalysisResults(): void {
    $data = $this->getJsonFileData(PhpLocTask::JSON_LOG_PATH);

    if (empty($data)) {
      return;
    }

    $this->properties['phploc'] = $data;
  }

  /**
   * Adds deprecation scanning results to the event properties.
   */
  private function addDeprecationScanningResults(): void {
    $data = $this->getJsonFileData(PhpStanTask::JSON_LOG_PATH);

    if (empty($data['totals']) || !is_array($data['totals'])) {
      return;
    }

    $this->properties['phpstan']['totals'] = $data['totals'];
  }

  /**
   * Gets JSON data from the file at the given path.
   *
   * @param string $path
   *   The path to a JSON file relative to the ORCA project directory.
   *
   * @return array
   *   The data from the given file if available, or an empty array if not.
   */
  private function getJsonFileData(string $path): array {
    $path = $this->getProjectPath($path);

    if (!$this->filesystem->exists($path)) {
      return [];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, TRUE);

    if (json_last_error()) {
      return [];
    }

    return $data;
  }

  /**
   * Gets the ORCA project directory with a sub-path appended.
   *
   * @param string $sub_path
   *   A sub-path to append.
   *
   * @return string
   *   The project directory with sub-path appended.
   */
  public function getProjectPath(string $sub_path): string {
    return "{$this->projectDir}/{$sub_path}";
  }

}

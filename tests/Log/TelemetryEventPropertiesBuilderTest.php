<?php

namespace Acquia\Orca\Tests\Log;

use Acquia\Orca\Enum\TelemetryEventName;
use Acquia\Orca\Log\TelemetryEventPropertiesBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property \Prophecy\Prophecy\ObjectProphecy|\Env $env
 * @property \Prophecy\Prophecy\ObjectProphecy|\Symfony\Component\Filesystem\Filesystem $filesystem
 */
class TelemetryEventPropertiesBuilderTest extends TestCase {

  private $projectDir = '/var/www/orca';

  protected function setUp() {
    $this->env = $this->prophesize(\Env::class);
    $this->filesystem = $this->prophesize(Filesystem::class);
  }

  protected function createTelemetryEventPropertiesBuilder(): TelemetryEventPropertiesBuilder {
    /** @var \Env $env */
    $env = $this->env->reveal();
    /** @var \Symfony\Component\Filesystem\Filesystem $filesystem */
    $filesystem = $this->filesystem->reveal();
    return new TelemetryEventPropertiesBuilder($env, $filesystem, $this->projectDir);
  }

  public function testConstruction() {
    $builder = $this->createTelemetryEventPropertiesBuilder();

    $this->assertEquals(TelemetryEventPropertiesBuilder::class, get_class($builder), 'Instantiated class.');
  }

  public function testBuildingTestEvent() {
    $builder = $this->createTelemetryEventPropertiesBuilder();

    $event = $builder->build(new TelemetryEventName(TelemetryEventName::TEST));

    $this->assertEquals(['example' => TRUE], $event, 'Built TEST event.');
  }

}

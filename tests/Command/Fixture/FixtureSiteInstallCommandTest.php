<?php

namespace Acquia\Orca\Tests\Command\Fixture;

use Acquia\Orca\Command\Fixture\FixtureInstallSiteCommand;
use Acquia\Orca\Enum\StatusCode;
use Acquia\Orca\Fixture\Fixture;
use Acquia\Orca\Fixture\FixtureCreator;
use Acquia\Orca\Fixture\SiteInstaller;
use Acquia\Orca\Tests\Command\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\Fixture $fixture
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\SiteInstaller $siteInstaller
 */
class FixtureSiteInstallCommandTest extends CommandTestBase {

  protected function setUp() {
    $this->fixture = $this->prophesize(Fixture::class);
    $this->fixture->exists()
      ->willReturn(TRUE);
    $this->fixture->getPath()
      ->willReturn(self::FIXTURE_ROOT);
    $this->siteInstaller = $this->prophesize(SiteInstaller::class);
  }

  protected function createCommand(): Command {
    /** @var \Acquia\Orca\Fixture\Fixture $fixture */
    $fixture = $this->fixture->reveal();
    /** @var \Acquia\Orca\Fixture\SiteInstaller $site_installer */
    $site_installer = $this->siteInstaller->reveal();
    return new FixtureInstallSiteCommand($fixture, $site_installer);
  }

  /**
   * @dataProvider providerCommand
   */
  public function testCommand($fixture_exists, $args, $inputs, $install_called, $profile, $status_code, $display) {
    $this->fixture
      ->exists()
      ->shouldBeCalled()
      ->willReturn($fixture_exists);
    $this->siteInstaller
      ->install($profile)
      ->shouldBeCalledTimes($install_called);

    $this->executeCommand($args, $inputs);

    $this->assertEquals($display, $this->getDisplay(), 'Displayed correct output.');
    $this->assertEquals($status_code, $this->getStatusCode(), 'Returned correct status code.');
  }

  public function providerCommand() {
    return [
      [FALSE, [], [], 0, FixtureCreator::DEFAULT_PROFILE, StatusCode::ERROR, sprintf("Error: No fixture exists at %s.\n", self::FIXTURE_ROOT)],
      [TRUE, [], ['n'], 0, FixtureCreator::DEFAULT_PROFILE, StatusCode::USER_CANCEL, 'Are you sure you want to drop all tables in the database and install a fresh site at /var/www/orca-build? '],
      [TRUE, [], ['y'], 1, FixtureCreator::DEFAULT_PROFILE, StatusCode::OK, 'Are you sure you want to drop all tables in the database and install a fresh site at /var/www/orca-build? '],
      [TRUE, ['-n' => TRUE], [], 0, FixtureCreator::DEFAULT_PROFILE, StatusCode::USER_CANCEL, ''],
      [TRUE, ['-f' => TRUE], [], 1, FixtureCreator::DEFAULT_PROFILE, StatusCode::OK, ''],
      [TRUE, ['-f' => TRUE, '-n' => TRUE], [], 1, FixtureCreator::DEFAULT_PROFILE, StatusCode::OK, ''],
      [TRUE, ['-f' => TRUE, '--profile' => 'lightning'], [], 1, 'lightning', StatusCode::OK, ''],
    ];
  }

}

<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Migrations\Tests\Functional;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Migrations\MigrationsVersion;
use Doctrine\DBAL\Migrations\Provider\StubSchemaProvider;
use Doctrine\DBAL\Migrations\Tools\Console\Command as MigrationCommands;
use Doctrine\DBAL\Migrations\Tests\MigrationTestCase;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;


/**
 * Tests the entire console application, end to end.
 */
class CliTest extends MigrationTestCase
{
    private $application;

    private $lastExit;

    public function testMigrationLifecycleFromCommandLine()
    {
        $output = $this->executeCommand('migrations:status');
        $this->assertSuccessfulExit();
        $this->assertRegExp('/available migrations:\s+1$/im', $output);
        $this->assertRegExp('/new migrations:\s+1$/im', $output);

        $output = $this->executeCommand('migrations:latest');
        $this->assertContains('20150426000000', $output);

        $this->executeCommand('migrations:migrate', array('--no-interaction'));
        $this->assertSuccessfulExit();

        $output = $this->executeCommand('migrations:status');
        $this->assertSuccessfulExit();
        $this->assertRegExp('/^.*available migrations:\s+1$/im', $output);
        $this->assertRegExp('/^.*new migrations:\s+0$/im', $output);
    }

    public function testGenerateCommandAddsNewVersion()
    {
        $this->assertVersionCount(0, 'Should start with no versions');
        $this->executeCommand('migrations:generate');
        $this->assertSuccessfulExit();
        $this->assertVersionCount(1, 'generate command should add one version');

        $output = $this->executeCommand('migrations:status');
        $this->assertSuccessfulExit();
        $this->assertRegExp('/available migrations:\s+2$/im', $output);
    }

    public function testMigrationDiffWritesNewMigrationWithExpectedSql()
    {
        $this->assertVersionCount(0, 'should start with no versions');
        $this->executeCommand('migrations:diff');
        $this->assertSuccessfulExit();
        $this->assertVersionCount(1, 'diff command should add one version');

        $output = $this->executeCommand('migrations:status');
        $this->assertSuccessfulExit();
        $this->assertRegExp('/available migrations:\s+2$/im', $output);

        $versions = $this->globVersions();
        $contents = file_get_contents($versions[0]);

        $this->assertContains('CREATE TABLE bar', $contents);
        $this->assertContains('DROP TABLE bar', $contents);
    }

    protected function setUp()
    {
        if (file_exists(__DIR__.'/_files/migrations.db')) {
            @unlink(__DIR__.'/_files/migrations.db');
        }
        foreach ($this->globVersions() as $file) {
            @unlink($file);
        }

        $this->application = new Application('Doctrine Migrations Test', MigrationsVersion::VERSION());
        $this->application->setCatchExceptions(false);
        $this->application->setAutoExit(false);
        $this->application->getHelperSet()->set(
            new ConnectionHelper($this->getSqliteConnection()),
            'connection'
        );
        $this->application->addCommands(array(
            new MigrationCommands\ExecuteCommand(),
            new MigrationCommands\GenerateCommand(),
            new MigrationCommands\LatestCommand(),
            new MigrationCommands\MigrateCommand(),
            new MigrationCommands\StatusCommand(),
            new MigrationCommands\VersionCommand(),
            new MigrationCommands\DiffCommand(new StubSchemaProvider($this->getSchema())),
        ));
    }

    protected function executeCommand($commandName, array $args = array())
    {
        $input = new ArrayInput(array_merge(array(
            'command'               => $commandName,
            '--configuration'       => __DIR__.'/_files/config.yml',
        ), $args));
        $output = new BufferedOutput();

        $this->lastExit = $this->application->run($input, $output);

        return $output->fetch();
    }

    protected function assertSuccessfulExit($msg='')
    {
        $this->assertEquals(0, $this->lastExit, $msg);
    }

    protected function assertVersionCount($count, $msg='')
    {
        $this->assertCount($count, $this->globVersions(), $msg);
    }

    protected function globVersions()
    {
        return glob(__DIR__.'/_files/migrations/Version*.php');
    }

    protected function getSchema()
    {
        $s = new Schema();
        $t = $s->createTable('foo');
        $t->addColumn('id', 'integer', array(
            'autoincrement' => true,
        ));
        $t->setPrimaryKey(array('id'));

        $t = $s->createTable('bar');
        $t->addColumn('id', 'integer', array(
            'autoincrement' => true,
        ));
        $t->setPrimaryKey(array('id'));

        return $s;
    }
}

class FirstMigration extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('CREATE TABLE foo (id INTEGER AUTO_INCREMENT, PRIMARY KEY (id))');
    }

    public function down(Schema $schema)
    {
        $this->addSql('DROP TABLE foo');
    }
}

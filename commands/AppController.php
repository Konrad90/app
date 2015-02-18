<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2014 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\commands;

use dektrium\user\Finder;
use dektrium\user\ModelManager;
use dmstr\console\controllers\BaseAppController;
use mikehaertl\shellcommand\Command;
use yii\base\Exception;


/**
 * Task runner command for development.
 * @package console\controllers
 * @author Tobias Munk <tobias@diemeisterei.de>
 */
class AppController extends BaseAppController
{

    public function init()
    {
        try {
            return parent::init(); // TODO: Change the autogenerated stub
        } catch (Exception $e) {
            echo "Warning: " . $e->getMessage() . "\n";
            echo "Some actions may not perform correctly\n\n";
        }
    }

    public $defaultAction = 'version';

    /**
     * Displays application version from git describe
     */
    public function actionVersion()
    {
        echo "Application Version\n";
        $cmd = new Command("git describe");
        if ($cmd->execute()) {
            echo $cmd->getOutput();
        } else {
            echo $cmd->getOutput();
            echo $cmd->getStdErr();
            echo $cmd->getError();
        }
        echo "\n";
    }

    /**
     * Update application and vendor source code, run database migrations, clear cache
     */
    public function actionUpdate()
    {
        $cmd = new Command("git pull");
        if ($cmd->execute()) {
            echo $cmd->getOutput();
        } else {
            echo $cmd->getOutput();
            echo $cmd->getStdErr();
            echo $cmd->getError();
        }
        $this->composer("install");
        $this->action('migrate');
        $this->action('cache/flush', 'cache');
    }

    /**
     * Initial application setup
     */
    public function actionSetup()
    {
        $this->action('migrate', ['interactive' => $this->interactive]);
        $this->action('app/setup-admin-user', ['interactive' => $this->interactive]);
        $this->action('app/virtual-host', ['interactive' => $this->interactive]);
        echo "Virtual-host configuration: ".getenv('VIRTUAL_HOST')."\n";
    }

    /**
     * Install packages for application testing
     */
    public function actionSetupTests()
    {
        $this->action('migrate', ['interactive' => $this->interactive]);

        $this->composer(
            'global require "codeception/codeception:2.0.*" "codeception/specify:*" "codeception/verify:*"'
        );
        $this->composer(
            'require --dev "yiisoft/yii2-coding-standards:2.*" "yiisoft/yii2-codeception:2.*" "yiisoft/yii2-faker:2.*"'
        );

        // array with commands
        $commands[] = '~/.composer/vendor/bin/codecept build';

        foreach ($commands AS $command) {
            $cmd = new Command($command);
            if ($cmd->execute()) {
                echo $cmd->getOutput();
            } else {
                echo $cmd->getOutput();
                echo $cmd->getStdErr();
                echo $cmd->getError();
            }
        }
    }

    /**
     * Run all test suites with web-server from PHP executable
     */
    public function actionRunTests()
    {
        // array with commands
        echo 'Note: You need a webserver running on port 8042, eg. ';
        echo "\n\n";
        echo '  php -S localhost:8042 -t web > /dev/null 2>&1 &';
        echo "\n\n";

        if ($this->confirm("Start testing?", true)) {

            $commands[] = '~/.composer/vendor/bin/codecept run';

            $hasError = false;
            foreach ($commands AS $command) {
                $cmd = new Command($command);
                if ($cmd->execute()) {
                    echo $cmd->getOutput();
                } else {
                    echo $cmd->getOutput();
                    echo $cmd->getStdErr();
                    echo $cmd->getError();
                    $hasError = true;
                }
                echo "\n";
            }
            if ($hasError) {
                return 1;
            } else {
                return 0;
            }
        }
    }

    /**
     * Clear [application]/web/assets folder
     */
    public function actionClearAssets()
    {
        $assets = \Yii::getAlias('@app/web/assets');

        // Matches from 7-8 char folder names, the 8. char is optional
        $matchRegex = '"^[a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9]\?[a-z0-9]$"';

        // create $cmd command
        $cmd = 'cd "' . $assets . '" && ls | grep -e ' . $matchRegex . ' | xargs rm -rf ';

        // Set command
        $command = new Command($cmd);

        // Prompt user        
        $delete = $this->confirm("\nDo you really want to delete web assets?", ['default' => true]);

        if ($delete) {
            // Try to execute $command
            if ($command->execute()) {
                echo "Web assets have been deleted.\n\n";
            } else {
                echo "\n" . $command->getError() . "\n";
                echo $command->getStdErr();
            }
        }
    }

    /**
     * Install packages for documentation rendering
     */
    public function actionSetupDocs()
    {
        $this->composer(
            'require --dev "cebe/markdown-latex:dev-master" "yiisoft/yii2-apidoc:2.*"'
        );
    }

    /**
     * Setup admin user (create, update password, confirm)
     */
    public function actionSetupAdminUser()
    {
        $finder = \Yii::$container->get(Finder::className());
        $admin  = $finder->findUserByUsername('admin');
        if ($admin === null) {
            $email = $this->prompt(
                'E-Mail for application admin user:',
                ['default' => getenv('APP_ADMIN_EMAIL')]
            );
            $this->action('user/create', [$email, 'admin']);
            $password = $this->prompt(
                'Password for application admin user:',
                ['default' => getenv('APP_ADMIN_PASSWORD')]
            );
        } else {
            $password = $this->prompt(
                'Update password for application admin user (leave empty to skip):'
            );
        }
        if ($password) {
            $this->action('user/password', ['admin', $password]);
        }
        sleep(1); // confirmation may not succeed without a short pause
        $this->action('user/confirm', ['admin']);
    }

    /**
     * Generate application and required vendor documentation
     */
    public function actionGenerateDocs()
    {
        if ($this->confirm('Regenerate documentation files into ./docs-html', true)) {

            // array with commands
            $commands[] = 'vendor/bin/apidoc guide --interactive=0 docs web/apidocs';
            $commands[] = 'vendor/bin/apidoc api --interactive=0 --exclude=runtime/,tests/,vendor/ . web/apidocs';
            $commands[] = 'vendor/bin/apidoc guide --interactive=0 docs web/apidocs';

            foreach ($commands AS $command) {
                $cmd = new Command($command);
                if ($cmd->execute()) {
                    echo $cmd->getOutput();
                } else {
                    echo $cmd->getOutput();
                    echo $cmd->getStdErr();
                    echo $cmd->getError();
                }
            }
        }
    }

    /**
     * Setup vhost with virtualhost.sh script
     */
    public function actionVirtualHost()
    {
        if (`which virtualhost.sh`) {
            echo "\n";
            $frontendName = $this->prompt('"Frontend" Domain-name (example: myproject.com.local, leave empty to skip)');
            if ($frontendName) {
                $this->execute(
                    'virtualhost.sh ' . $frontendName . ' ' . \Yii::getAlias('@frontend') . DIRECTORY_SEPARATOR . "web"
                );
                echo "\n";
                $defaultBackendName = 'admin.' . $frontendName;
                $backendName        = $this->prompt(
                    '"Backend" Domain-name',
                    ['default' => $defaultBackendName]
                );
                if ($backendName) {
                    $this->execute(
                        'virtualhost.sh ' . $backendName . ' ' . \Yii::getAlias(
                            '@backend'
                        ) . DIRECTORY_SEPARATOR . "web"
                    );
                }
            }
        } else {
            echo "Command virtualhost.sh not found, skipping.\n";
        }
    }

    /**
     * create database and grant permissions based on ENV vars
     *
     * @param $db database name
     */
    public function actionCreateMysqlDb($db)
    {
        $root          = 'root';
        $root_password = getenv("DB_ENV_MYSQL_ROOT_PASSWORD");
        $host          = getenv("DB_PORT_3306_TCP_ADDR");
        $port          = getenv("DB_PORT_3306_TCP_PORT");
        $user          = getenv("DB_ENV_MYSQL_USER");
        $pass          = getenv("DB_ENV_MYSQL_PASSWORD");
        #$db            = getenv("DB_ENV_MYSQL_DATABASE");

        try {
            $dbh = new \PDO("mysql:host=$host;port=$port", $root, $root_password);
            $dbh->exec(
                "CREATE DATABASE IF NOT EXISTS `$db`;
         GRANT ALL ON `$db`.* TO '$user'@'%' IDENTIFIED BY '$pass';
         GRANT SUPER ON *.* TO '$user'@'%' IDENTIFIED BY '$pass';
         FLUSH PRIVILEGES;"
            )
            or die(print_r($dbh->errorInfo(), true));
        } catch (\PDOException $e) {
            die("DB ERROR: " . $e->getMessage());
        }

        echo "Database successfully created.\n";
    }

}

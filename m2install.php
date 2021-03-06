#!/usr/local/bin php
<?php

declare(strict_types=1);

/**
 * Install/Reinstall magento.
 *
 * 1) Delete env.php, vendor, pub/static, generated, var,
 * 2) Run composer install,
 * 3) Run magento installation,
 * 4) setup deploy mode.
 */
class MagentoInstall
{
    /**
     * magento_base_url(string) - Magento base url (protocol is required)
     * admin_user_name(string) - Admin user login
     * admin_user_password(string) - Admin user password
     * admin_user_email(string) - Admin user email
     * main_db_name(string) - main database name
     *
     * @var array
     */
    private $requiredConfigurations = [
        'magento_base_url' => 'http://mage2.local',
        'admin_user_name' => 'admin',
        'admin_user_password' => '123123q',
        'admin_user_email' => 'adminb@admin.com',
        'main_db_name' => 'dev_magento2ce',
    ];

    /**
     * Setup this configuration after install magento.
     * 'config_path' => 'config_value'
     *
     * @var array
     */
    private $configurations = [
        'admin/security/admin_account_sharing' => 1,
        'admin/security/session_lifetime' => 9000,
        'admin/security/min_time_between_password_reset_requests' => 0,
        'system/smtp/disable' => 1,
        'cms/wysiwyg/enabled' => 'disabled',
    ];

    /**
     * integration_tests_db_name(string) - database for integration tests
     * deploy_mode(string) - magento mode. Available modes: default, developer, or production
     *
     * @var array
     */
    private $additionalConfigurations = [
        'integration_tests_db_name' => 'dev_magento2ce_integration',
        'deploy_mode' => 'developer',
    ];

    /**
     * @var PDO
     */
    private $connection;

    /**
     * Install magento entrypoint.
     *
     * @return void
     */
    public function execute(): void
    {
        $commandsPool = $this->getCommandsPool();
        $currentStep = 1;
        $stepsCount = count($commandsPool);
        foreach ($commandsPool as $command) {
            $this->message("-------- STEP {$currentStep}/{$stepsCount} --------", true);
            $this->{$command}();
            $currentStep++;
        }
    }

    /**
     * Validate configurations.
     *
     * @return void
     * @throws RuntimeException
     */
    private function validateConfig(): void
    {
        $this->message('Start validate required config.');
        foreach ($this->requiredConfigurations as $configName => $configValue) {
            if (null === $configValue || empty($configValue)) {
                throw new RuntimeException(
                    "Configuration {$configName} is required, fill it in requiredConfiguration property."
                );
            }
        }

        if (!preg_match('/https?:\/\/[a-zA-Z.0-9]+/', $this->requiredConfigurations['magento_base_url'])) {
            throw new RuntimeException(sprintf(
                'Base url must contain protocol http(s), fill it in requiredConfiguration property. Current URL: %s',
                $this->requiredConfigurations['magento_base_url']
            ));
        }


        $this->message('Finish validate required config.');
    }

    /**
     * Delete env.php, vendor, pub/static, generated, var.
     *
     * @return void
     */
    private function deleteInstalledData(): void
    {
        $this->message('Start delete old data.');
        exec("rm -rf {$this->getRootMagentoDirPath()}/app/etc/env.php");
        exec("find {$this->getRootMagentoDirPath()}/var/ -not -name '.htaccess' -delete");
        exec("find {$this->getRootMagentoDirPath()}/pub/static/ -not -name '.htaccess' -delete");
        exec("find {$this->getRootMagentoDirPath()}/pub/static/ -not -name '.htaccess' -delete");
        exec("find {$this->getRootMagentoDirPath()}/generated/ -not -name '.htaccess' -delete");
        exec("find {$this->getRootMagentoDirPath()}/vendor/ -not -name '.htaccess' -delete");
        $this->message('Finish delete old data.');
    }

    /**
     * Install composer.
     *
     * @return void
     */
    private function composerInstall(): void
    {
        $this->message('Start install composer.');
        exec("cd {$this->getRootMagentoDirPath()}/ && composer install");
        $this->message('Finish install composer.');
    }

    /**
     * Delete old database and create new.
     *
     * @return void
     */
    private function prepareDatabase(): void
    {
        $this->message('Start install DB.');
        $this->message('Wait DB availability maximum 300 seconds...', true);
        $this->waitDbAvailability(300);
        $this->getDbConnection()->query("CREATE DATABASE IF NOT EXISTS {$this->requiredConfigurations['main_db_name']};");
        if (null !== $this->additionalConfigurations['integration_tests_db_name']
            && !empty($this->additionalConfigurations['integration_tests_db_name'])) {
            $this->getDbConnection()
                ->query("CREATE DATABASE IF NOT EXISTS {$this->additionalConfigurations['integration_tests_db_name']};");
        }
        $this->message('Finish install DB.');
    }

    /**
     * Run magento install.
     *
     * @return void
     */
    private function installMagento(): void
    {
        $this->message('Start install magento.');
        $baseUrl = $this->requiredConfigurations['magento_base_url'];
        $adminUserName = $this->requiredConfigurations['admin_user_name'];
        $adminUserPassword = $this->requiredConfigurations['admin_user_password'];
        $adminUserEmail = $this->requiredConfigurations['admin_user_email'];
        $mainDbName = $this->requiredConfigurations['main_db_name'];

        $binMagentoInstallParams = [
            "--base-url={$baseUrl}",
            '--db-host=magento2_mysql',
            "--db-name={$mainDbName}",
            '--db-user=root',
            '--db-password=root',
            '--admin-firstname=Admin',
            '--admin-lastname=Adminov',
            "--admin-email={$adminUserEmail}",
            "--admin-user={$adminUserName}",
            "--admin-password={$adminUserPassword}",
            '--language=en_US',
            '--currency=USD',
            '--timezone=America/Chicago',
            '--use-rewrites=1',
            '--cleanup-database',
            '--backend-frontname=admin',
        ];
        exec(sprintf(
            "php {$this->getRootMagentoDirPath()}/bin/magento setup:install %s",
            implode(' ', $binMagentoInstallParams)
        ));
        $this->message('Finish install magento.');
    }

    /**
     * Set deploy mode.
     *
     * @return void
     */
    private function setDeployMode(): void
    {
        $this->message('Start set deploy mode.');
        exec(sprintf(
            "php {$this->getRootMagentoDirPath()}/bin/magento deploy:mode:set %s",
            $this->additionalConfigurations['deploy_mode']
        ));
        $this->message('Finish set deploy mode.');
    }

    /**
     * Set configurations.
     *
     * @return void
     */
    private function setConfigurations(): void
    {
        $this->message('Start set configurations.');
        foreach ($this->configurations as $confPath => $confValue) {
            $confValue = is_numeric($confValue) ? (int)$confValue : "$confValue";
            $this->message("=====> Set config {$confPath} with value {$confValue}", true);
            exec("php {$this->getRootMagentoDirPath()}/bin/magento config:set {$confPath} {$confValue}");
        }
        $this->message('Finish set configurations.');
    }

    /**
     * Return absolute path to magento root directory.
     *
     * @return string
     */
    private function getRootMagentoDirPath(): string
    {
        return '/var/www/magento2';
    }

    /**
     * Put provided message to output.
     *
     * @param string $message
     * @param bool $isEmptyMessage
     * @return void
     */
    private function message(string $message, bool $isEmptyMessage = false): void
    {
        $message = sprintf('%s%s', $message, PHP_EOL);
        if (false === $isEmptyMessage) {
            $message = sprintf('Current time: %s, message: %s%s', time(), $message, PHP_EOL);
        }

        echo $message;
    }

    /**
     * Retrieve connection.
     *
     * @return PDO
     */
    private function getDbConnection(): PDO
    {
        if (null === $this->connection) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO('mysql:host=magento2_mysql', 'root', 'root', $options);
        }

        return $this->connection;
    }

    /**
     * Close connection to DB.
     *
     * @return void
     */
    private function closeDbConnection(): void
    {
        $this->connection = null;
    }

    /**
     * Wait DB availability. Check connection every 3 seconds.
     *
     * @param int $timeLeft
     * @return void
     */
    private function waitDbAvailability(int $timeLeft): void
    {
        try {
            $this->message('Ping DB connection ....', true);
            $this->getDbConnection()->query("SHOW DATABASES;");
        } catch (Exception $e) {
            $this->connection = null;
            if ($e->getCode() === 2002 && $timeLeft > 0) {
                sleep(3);
                $this->waitDbAvailability($timeLeft - 3);
            } else {
                throw new RuntimeException('Something went wrong with DB. Error text: ' . $e->getMessage());
            }
        }
    }

    /**
     * Return install commands pool.
     * 
     * @return array
     */
    private function getCommandsPool(): array
    {
        return [
            'validateConfig',
//            'deleteInstalledData',
            'composerInstall',
            'prepareDatabase',
            'installMagento',
            'setDeployMode',
            'setConfigurations',
            'closeDbConnection',
        ];
    }
}

$install = new MagentoInstall();
$install->execute();

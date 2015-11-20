<?php

namespace Projek\CI;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

/**
 * CodeIgniter package installer for Composer
 *
 * @package codeigniter-installers
 * @author  Jonathon Hill <jhill9693@gmail.com>
 * @license MIT license
 * @link    https://github.com/compwright/codeigniter-installers
 */
class ComposerInstaller extends LibraryInstaller
{
    protected $package_subclass_prefix = 'App_';
    protected $package_install_paths   = array(
        'codeigniter-library'     => '{application}/libraries/{name}/',
        'codeigniter-core'        => '{application}/core/{name}/',
        'codeigniter-third-party' => '{application}/third_party/{name}/',
        'codeigniter-module'      => '{application}/modules/{name}/',
        'projek-ci-module'        => '',
        'projek-ci-theme'         => '',
    );

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return array_key_exists($packageType, $this->package_install_paths);
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        $type = $package->getType();
        if (!$this->supports($type)) {
            throw new \InvalidArgumentException("Package type '$type' is not supported at this time.");
        }

        if ($type === 'projek-ci-module') {
            return parent::getInstallPath($package);
        }

        $prettyName = $package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name   = $prettyName;
        }

        $extra = ($pkg = $this->composer->getPackage()) ? $pkg->getExtra() : array();

        $appdir = !empty($extra['codeigniter-application-dir'])
        ? $extra['codeigniter-application-dir']
        : 'application';

        $install_paths = $this->package_install_paths;

        if (!empty($extra['codeigniter-module-dir'])) {
            $moduleDir                           = $extra['codeigniter-module-dir'];
            $install_paths['codeigniter-module'] = $moduleDir . '/{name}/';
        }

        if (!empty($extra['codeigniter-subclass-prefix'])) {
            $this->package_subclass_prefix = $extra['codeigniter-subclass-prefix'];
        }

        $vars = array(
            '{name}'        => $name,
            '{vendor}'      => $vendor,
            '{type}'        => $type,
            '{application}' => $appdir,
        );

        return str_replace(array_keys($vars), array_values($vars), $install_paths[$type]);
    }

    /**
     * {@inheritDoc}
     */
    protected function installCode(PackageInterface $package)
    {
        $downloadPath = $this->getInstallPath($package);
        $this->downloadManager->download($package, $downloadPath);
        $this->postInstallActions($package, $downloadPath);
    }

    /**
     * {@inheritDoc}
     */
    protected function updateCode(PackageInterface $initial, PackageInterface $target)
    {
        $downloadPath = $this->getInstallPath($initial);
        $this->downloadManager->update($initial, $target, $downloadPath);
        $this->postInstallActions($target, $downloadPath);
    }

    /**
     * Performs actions on the downloaded files after an installation or update
     *
     * @var string $type
     * @var string $downloadPath
     */
    protected function postInstallActions(PackageInterface $target, $downloadPath)
    {
        // @HACK to work around the security check in CI config files
        defined('BASEPATH') || define('BASEPATH', 1);

        switch ($target->getType()) {
            case 'codeigniter-core':
                // Move the core library extension out of the package directory and remove it
                $this->moveCoreFiles($downloadPath);
                break;

            case 'codeigniter-library':
                // Move the library files out of the package directory and remove it
                $wildcard = $this->package_subclass_prefix . "*.php";
                $path     = realpath($downloadPath) . '/' . $wildcard;
                if (count(glob($path)) > 0) {
                    $this->moveCoreFiles($downloadPath, $wildcard);
                }
                break;

            case 'projek-ci-module':
            case 'codeigniter-module':
                // If the module has migrations, copy them into the application migrations directory
                $moduleMigrations = $this->getModuleMigrations($downloadPath);
                if (count($moduleMigrations) > 0) {
                    $config = $this->getMigrationConfig($downloadPath);
                    $this->copyModuleMigrations($config, $moduleMigrations);
                }
                break;

            // case 'projek-ci-module':
            //     If the module has migrations, copy them into the application migrations directory
            //     $moduleMigrations = $this->getModuleMigrations($downloadPath, 'asset/data');

            //     $confirm = true;
            //     if ($this->io->isInteractive()) {
            //         $question = 'Do you want to install migration files for ' . $target->getPrettyName();
            //         $confirm = $this->io->askConfirmation($question);
            //     }

            //     if (count($moduleMigrations) > 0 && $confirm) {
            //         $config = $this->getMigrationConfig($downloadPath);
            //         $this->copyModuleMigrations($config, $moduleMigrations);
            //         $this->io->write('<info>Installed</info>');
            //     }
            //     break;
        }
    }

    /**
     * Check if module have migrations files
     *
     * @param  string $downloadPath        Module download path
     * @param  string $moduleMigrationPath Module migration path
     *                                     it's relative to $download path and
     *                                     any slash will be trimmed
     * @return bool
     */
    protected function getModuleMigrations($downloadPath, $moduleMigrationPath = 'asset/data')
    {
        $moduleMigrationPath = trim($moduleMigrationPath, '/');
        $moduleMigrations    = glob($downloadPath . '/' . $moduleMigrationPath . '/*.php');

        return sort($moduleMigrations);
    }

    /**
     * Copy all module migration files to application migration directory
     *
     * @param  array  $config           Migration configs
     * @param  array  $moduleMigrations Module migration file list
     * @return void
     */
    protected function copyModuleMigrations(array $config, array $moduleMigrations)
    {
        if (isset($config['migration_type']) && $config['migration_type'] === 'timestamp') {
            $number = (int) date('YmdHis');
        } else {
            $migrationPath = $this->checkMigrationsPath($config);
            // Get the latest migration number and increment
            $migrations = glob($migrationPath . '*.php');
            if (count($migrations) > 0) {
                sort($migrations);
                $migration = array_pop($migrations);
                $number    = ((int) basename($migration)) + 1;
            } else {
                $number = 1;
            }
        }

        // Copy each migration into the application migration directory
        foreach ($moduleMigrations as $migration) {
            // Re-number the migration
            $newMigration = $migrationPath .
            preg_replace('/^(\d+)/', sprintf('%03d', $number), basename($migration));

            // Copy the migration file
            copy($migration, $newMigration);

            $number++;
        }
    }

    /**
     * Check Application Migrations path, if not available it will create new one
     *
     * @param  array  $config Migration configuration
     * @return string
     */
    protected function checkMigrationsPath(array $config)
    {
        // Check if $config['migration_path'] is already defined
        $migrationPath = isset($config['migration_path']) ? $config['migration_path'] : $appPath . '/migrations/';
        // Check if $config['migration_path'] directory is not available yet, create new one
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        return $migrationPath;
    }

    /**
     * Get migration configurations
     *
     * @return array
     */
    protected function getMigrationConfig($downloadPath)
    {
        // Check CI APPPATH
        $appPath = defined('APPPATH') ? APPPATH : dirname(dirname($downloadPath));
        // Load CI Migration Config
        @include $appPath . '/config/migration.php';

        return isset($config) ? $config : array();
    }

    /**
     * Move files out of the package directory up one level
     *
     * @var $downloadPath
     * @var $wildcard = '*.php'
     */
    protected function moveCoreFiles($downloadPath, $wildcard = '*.php')
    {
        $dir = realpath($downloadPath);
        $dst = dirname($dir);

        // Move the files up one level
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            shell_exec("move /Y $dir/$wildcard $dst/");
        } else {
            shell_exec("mv -f $dir/$wildcard $dst/");
        }

        // If there are no PHP files left in the package dir, remove the directory
        if (count(glob("$dir/*.php")) === 0) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                shell_exec("rd /S /Q $dir");
            } else {
                shell_exec("rm -Rf $dir");
            }
        }
    }
}

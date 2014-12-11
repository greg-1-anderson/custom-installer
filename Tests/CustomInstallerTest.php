<?php
/**
  * Custom Installer Test.
  *
  * @author David Barratt <david@davidwbarratt.com>
  * @copyright Copyright (c) 2014, David Barratt
  */
namespace DavidBarratt\CustomInstaller\Tests;

use DavidBarratt\CustomInstaller\CustomInstaller;
use Composer\Config;
use Composer\Composer;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Util\Filesystem;
use PHPUnit_Framework_TestCase;

class CustomInstallerTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider dataForMergeDirectories
     */
    public function testMergeDirectories($source, $target, $exclusions, $expected)
    {
        $composer = new Composer();
        $config = new Config();
        $composer->setConfig($config);

        $repository = $this->getMock('Composer\Repository\InstalledRepositoryInterface');
        $io = $this->getMock('Composer\IO\IOInterface');
        $installer = new CustomInstaller($io, $composer);

        $sourceTree = '/tmp/source';
        $targetTree = '/tmp/target';

        $this->buildDirectoryTree($source, $sourceTree);
        $this->buildDirectoryTree($target, $targetTree);

        $installer->removePreservingExclusions($targetTree, $exclusions);
        $installer->mergeDirectoriesSkippingExclusions($sourceTree, $targetTree, $exclusions);

        $this->assertTreeMatchesExpected($expected, $targetTree);

        // TODO: We should test by creating a package and calling 'install' and other public methods on it.
        // Ideally, reference a local git repository stored in the 'test' folder.
    }

    public function dataForMergeDirectories()
    {
        return array(
            array(
                // source tree
                array(
                    "index.php" => "copy",
                    ".htaccess" => "copy",
                    "core" => array(
                        "vendor" => array(
                            "autoload.php" => "ignore",
                        ),
                        "install.php" => "copy",
                    ),
                    "modules" => array(
                        "READEME" => "ignore",
                    ),
                ),
                // target tree
                array(
                    "index.php" => "overwrite",
                    ".htaccess" => "overwrite",
                    "core" => array(
                        "vendor" => array(
                            "autoload.php" => "preserve",
                        ),
                        "install.php" => "overwrite",
                        "kittens.php" => "remove",
                    ),
                    "modules" => array(
                        "READEME" => "preserve",
                    ),
                ),
                // exclusions
                array(
                    "core/vendor/",
                    "modules/",
                ),
                // expected result
                array(
                    "index.php" => "copy",
                    ".htaccess" => "copy",
                    "core" => array(
                        "vendor" => array(
                            "autoload.php" => "preserve",
                        ),
                        "install.php" => "copy",
                    ),
                    "modules" => array(
                        "READEME" => "preserve",
                    ),
                ),
            ),
        );
    }

    private function buildDirectoryTree($tree, $path)
    {
        $filesystem = new Filesystem();
        $filesystem->remove($path);
        $filesystem->ensureDirectoryExists($path);
        foreach ($tree as $item => $data)
        {
            // The key is the filesystem item (file or
            // directory) to create.  The data is a string
            // to create a file, or a nested array to create
            // a directory, perhaps with nested content.
            $fs_item = $path . DIRECTORY_SEPARATOR . $item;
            if (is_string($data))
            {
                file_put_contents($fs_item, $data);
            }
            else
            {
                $this->buildDirectoryTree($data, $fs_item);
            }
        }
    }

    private function assertTreeMatchesExpected($expected, $targetTree)
    {
        // First, insure that everything in $expected exists
        // in the expected directory tree.
        $this->assertPathContainsExpected($expected, $targetTree);
        // Next, check to see if there is anything in the target
        // tree that should not be there.
        $this->assertPathDoesNotContainExtra($expected, $targetTree);
    }

    private function assertPathContainsExpected($tree, $path)
    {
        foreach ($tree as $item => $data)
        {
            // The key is the filesystem item (file or
            // directory) to create.  The data is a string
            // to create a file, or a nested array to create
            // a directory, perhaps with nested content.
            $fs_item = $path . DIRECTORY_SEPARATOR . $item;
            if (is_string($data))
            {
                $contents = file_exists($fs_item) ? trim(file_get_contents($fs_item)) : "";
                $expected = $item . ": " . $data;
                $actual   = $item . ": " . $contents;
                $this->assertEquals($expected, $actual);
            }
            else
            {
                $this->assertPathContainsExpected($data, $fs_item);
            }
        }
    }
    private function assertPathDoesNotContainExtra($tree, $path)
    {
        foreach (new \DirectoryIterator($path) as $fileInfo)
        {
            if (!$fileInfo->isDot())
            {
                $fs_item = $fileInfo->getFilename();
                if (!array_key_exists($fs_item, $tree))
                {
                    $this->assertEquals("", $fs_item);
                }
                elseif ($fileInfo->isDir())
                {
                    $this->assertPathDoesNotContainExtra($tree[$fs_item], $path . DIRECTORY_SEPARATOR . $fs_item);
                }
            }
        }
    }

  /**
    * testInstallPath
    *
    * @dataProvider dataForInstallPath
    */
    public function testInstallPath($name, $type, $path, $expected)
    {

        $composer = new Composer();
        $config = new Config();
        $composer->setConfig($config);

        $repository = $this->getMock('Composer\Repository\InstalledRepositoryInterface');
        $io = $this->getMock('Composer\IO\IOInterface');

        $installer = new CustomInstaller($io, $composer);
        $package = new Package($name, '1.0.0', '1.0.0');
        $package->setType($type);
        $consumerPackage = new RootPackage('foo/bar', '1.0.0', '1.0.0');
        $composer->setPackage($consumerPackage);
        $consumerPackage->setExtra(array(
            'custom-installer' => array(
                $type => $path,
            ),
        ));
        $result = $installer->getInstallPath($package);
        $this->assertEquals($expected, $result);
    }

    public function dataForInstallPath()
    {
          return array(
            array(
              'davidbarratt/davidwbarratt',
              'drupal-site',
              'sites/{$name}/',
              'sites/davidwbarratt/',
            ),
            array(
              'awesome/package',
              'custom-type',
              'custom/{$vendor}/{$name}/',
              'custom/awesome/package/',
            ),
            array(
              'drupal/core',
              'drupal-core',
              'web/',
              'web/',
            ),
          );
    }


}

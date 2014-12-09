<?php
/**
  * Custom Installer.
  *
  * @author David Barratt <david@davidwbarratt.com>
  * @copyright Copyright (c) 2014, David Barratt
  */
namespace DavidBarratt\CustomInstaller;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

class CustomInstaller extends LibraryInstaller
{

  /**
   * {@inheritDoc}
   */
  public function getInstallPath(PackageInterface $package)
  {
      $type = $package->getType();
      $extra = $this->composer->getPackage()->getExtra();

      $vars = array(
        'type' => $type,
      );

      $prettyName = $package->getPrettyName();

      if (strpos($prettyName, '/') !== FALSE) {

        $pieces = explode('/', $prettyName);;

        $vars['vendor'] = $pieces[0];
        $vars['name'] = $pieces[1];

      } else {

        $vars['vendor'] = '';
        $vars['name'] = $prettyName;

      }

      return $this->templatePath($extra['custom-installer'][$type], $vars);
  }

  /**
   * {@inheritDoc}
   */
  public function supports($packageType)
  {
      if ($this->composer->getPackage()) {

        $extra = $this->composer->getPackage()->getExtra();

        if (!empty($extra['custom-installer'])) {

          if (!empty($extra['custom-installer'][$packageType])) {
            return true;
          }

        }

      }

      return false;
  }

  /**
   * Replace vars in a path
   *
   * @see Composer\Installers\BaseInstaller::templatePath()
   *
   * @param  string $path
   * @param  array  $vars
   * @return string
   */
  protected function templatePath($path, array $vars = array())
  {
      if (strpos($path, '{') !== false) {
          extract($vars);
          preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
          if (!empty($matches[1])) {
              foreach ($matches[1] as $var) {
                  $path = str_replace('{$' . $var . '}', $$var, $path);
              }
          }
      }

      return $path;
  }

  public function getExclusions($packageType)
  {
      if ($this->composer->getPackage()) {

        $extra = $this->composer->getPackage()->getExtra();

        if (!empty($extra['merge-exclusions'])) {

          if (!empty($extra['merge-exclusions'][$packageType])) {
            return $extra['merge-exclusions'][$packageType];
          }

        }

      }

      return false;
  }

  /**
   * See:
   *
   * https://github.com/composer/composer/blob/master/src/Composer/Installer/LibraryInstaller.php
   */
  protected function installCode(PackageInterface $package)
  {
      $exclusions = getExclusions($package->getType());
      if (empty($exclusions)) {
          parent::installCode($package);
      }
      else {
          $this->installCodeExceptExclusions($package, $exclusions);
      }
  }
  protected function updateCode(PackageInterface $initial, PackageInterface $target)
  {
      $exclusions = getExclusions($package->getType());
      if (empty($exclusions)) {
          parent::updateCode($initial, $target);
      }
      else {
          $this->updateCodeExceptExclusions($initial, $target, $exclusions);
      }
  }
  protected function removeCode(PackageInterface $package)
  {
      $exclusions = getExclusions($package->getType());
      if (empty($exclusions)) {
          parent::updateCode($package);
      }
      else {
          $this->removeCodePreservingExclusions($package, $exclusions);
      }
  }
  // TODO: make protected once we are testing via the public interface
  public function removePreservingExclusions($targetPath, $exclusions, $prefix = '')
  {
      // Scan through $targetPath.  Delete anything that is not
      // in $exclusions.
      foreach (new \DirectoryIterator($targetPath) as $fileInfo)
      {
          $fs_item = $fileInfo->getFilename();
          $search_for = $prefix . $fs_item;
          if (!$this->array_item_begins_with($search_for, $exclusions))
          {
              if ($fileInfo->isFile() || !$fileInfo->isDot())
              {
                  $this->filesystem->remove($targetPath . DIRECTORY_SEPARATOR . $fs_item);
              }
          }
          if (!in_array($search_for, $exclusions))
          {
              if ($fileInfo->isDir() && !$fileInfo->isDot())
              {
                  $this->removePreservingExclusions($targetPath . DIRECTORY_SEPARATOR . $fs_item, $exclusions, $prefix . $fs_item . "/");
              }
          }
      }
  }
  // TODO: make protected once we are testing via the public interface
  public function mergeDirectoriesSkippingExclusions($sourcePath, $targetPath, $exclusions, $prefix = '')
  {
      $this->filesystem->ensureDirectoryExists($targetPath);
      // Copy contents of $sourcePath to $targetPath.
      // Anything in exclusions should remain in $targetPath.
      // Anything not in $sourcePath or exclusions should be deleted.
      foreach (new \DirectoryIterator($sourcePath) as $fileInfo)
      {
          $fs_item = $fileInfo->getFilename();
          $search_for = $prefix . $fs_item;
          if (!in_array($search_for, $exclusions))
          {
              if($fileInfo->isFile())
              {
                  copy($sourcePath . DIRECTORY_SEPARATOR . $fs_item, $targetPath . DIRECTORY_SEPARATOR . $fs_item);
              }
              elseif(!$fileInfo->isDot())
              {
                  $this->mergeDirectoriesSkippingExclusions($sourcePath . DIRECTORY_SEPARATOR . $fs_item, $targetPath . DIRECTORY_SEPARATOR . $fs_item, $exclusions, $prefix . $fs_item . "/");
              }
          }
      }
  }
  private function array_item_begins_with($needle, $haystack) {
      foreach ($haystack as $comparitor)
      {
          if (($comparitor == $needle) || (substr($comparitor, 0, strlen($needle) + 1) == $needle . "/"))
          {
              return true;
          }
      }
      return false;
  }
  protected function installCodeExceptExclusions(PackageInterface $package, $exclusions)
  {
      $downloadPath = $this->getInstallPath($package);
      // Create a temporary name next to the download location.
      // We will remove this when we are done.  '$downloadPath'
      // should already be unique at this point in time in the system,
      // so we do not have to work too hard to get a decent tmpname.
      $tmpPath = $downloadPath . '-' . (time() % 100000);
      // Download to the temporary location using the existing download manager.
      $this->downloadManager->download($package, $tmpPath);
      // Delete everything in the target, except for the 'exclusions';
      // then copy everything from the temporary download location to
      // the final target, again skipping the 'exclusions'.
      $this->removePreservingExclusions($downloadPath, $exclusions);
      $this->mergeDirectoriesSkippingExclusions($tmpPath, $downloadPath, $exclusions);
      $this->filesystem->removeDirectory($tmpPath);
  }
  protected function updateCodeExceptExclusions(PackageInterface $initial, PackageInterface $target, $exclusions)
  {
      // No optimizations here -- just remove the old code, and re-install the new.
      $this->removeCodePreservingExclusions($initial, $exclusions);
      $this->installCodeExceptExclusions($target, $exclusions);
  }
  protected function removeCodePreservingExclusions(PackageInterface $package, $exclusions)
  {
      $downloadPath = $this->getPackageBasePath($package);
      $this->io->write("  - Removing <info>" . $package->getName() . "</info> (<comment>" . $package->getPrettyVersion() . "</comment>)");
      // TODO: cleanChanges() is most likely going to be confused
      // by the excluded directories, and will probably flag them
      // as being changed.  We'll need our own version of this routine.
      $this->cleanChanges($package, $downloadPath, false);
      // Remove everything from $downloadPath except $exclusions
      $this->removePreservingExclusions($downloadPath, $exclusions);
  }
}
